<?php
/*
Plugin Name: Registration Display xProfile Groups
Plugin URI:
Description: Displays all required fields in xProfile Groups not included in Base group and stores them upon registration
Version: 1.0.0
Author: Kirk Johnson
Author URI:
License: GPL3
License URI: http://www.gnu.org/licenses/gpl.html
 */

if (!class_exists('Registration_Display_xProfile_Groups')) {
	class Registration_Display_xProfile_Groups {
		private $selected;

		public function __construct() {
			$this->selected = get_site_option("registration_selected_xprofile_groups");
		}

		/*
		 * set all action hooks and filters used by plugin
		 */
		public function set_hooks() {
			//hook to display all xprofile field groups after buddypress base display runs
			add_action('bp_after_signup_profile_fields', array($this, 'render_xprofile_fields'));
			add_action('bp_before_account_details_fields', array($this, 'render_intro'));

			//add settings page to allow choosing of field groups to be parsed for registration
			add_action('admin_menu', array($this, 'options_menu'));

			//add admin scripts
			add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));

			//add front end css
			add_action('wp_enqueue_scripts', array($this, 'add_frontend_styles_and_scripts'));

			//settings page AJAX action
			add_action('wp_ajax_store_xprofile_field_groups', array($this, 'store_xprofile_field_groups'));
			add_action('wp_ajax_store_xprofile_field_groups_intro', array($this, 'store_intro'));

			//add validation for forms
			if (isset($_POST) && !empty($_POST)) {
				add_action('bp_actions', array($this,"possible_form_validation"), 5);
			}

			//add signup meta from xprofile fields
			add_filter('bp_signup_usermeta', array($this, 'xprofile_add_signup_meta' ), 1, 1);

			//save xprofile field data to field on activation
			add_action('bp_core_activated_user', array($this, 'xprofile_activate_user'), 10, 3);
		}

		/*
		 * admin scripts
		 */
		public function add_admin_scripts($hook_suffix) {
			//load script for settings page
			if (strpos($hook_suffix, "registration-xprofile-settings") > 0) {
				wp_register_script("registration-xprofile-settings-script", plugins_url()."/registration-display-xprofile-groups/js/options_page.js", ['jquery']);
				wp_enqueue_script('registration-xprofile-settings-script');
				//localize for ajax calls
				wp_localize_script(
					'registration-xprofile-settings-script',
					'xprofile_object',
					array(
						'ajax_url' => admin_url('admin-ajax.php'),
						'nonce'    => wp_create_nonce('settings_nonce'),
					)
				);

				wp_register_style('registration-xprofile-settings-style', plugins_url()."/registration-display-xprofile-groups/css/admin.css");
				wp_enqueue_style('registration-xprofile-settings-style');
			}
		}

		/*
		 * form validation
		 */
		public function possible_form_validation() {
			global $bp;
			//make sure we are in registration
			if (!function_exists('bp_is_current_component') || !bp_is_current_component('register')) {
				return;
			}

			//get groups
			$field_groups = bp_profile_get_field_groups();

			//iterate through groups
			foreach ($field_groups as  $group) {
				//if not base and is used in registration check
				if ($group->id != 1 && $this->use_group_in_registration($group->id)) {
					//iterate through fields
					foreach ($group->fields as $field) {
						//check if requried
						if ($field->is_required) {
							//make key
							$key = "field_".$field->id;
							//if not in post set error
							if (!isset($_POST[$key]) || empty($_POST[$key])) {
								$bp->signup->errors = (!is_null($bp->signup->errors))? array() :$bp->signup->errors;
								//add error
								$bp->signup->errors["field_".$field->id] = "You must fill out field '".$field->name."' ";
							}
						}
					}
				}
			}
		}

		/*
		 * xprofile_add_signup_meta
		 * add meta from xprofile items not in base to pending user signup
		 */
		public function xprofile_add_signup_meta($meta) {
			$field_groups = bp_profile_get_field_groups();
			foreach ($field_groups as  $group) {
				if ($group->id != 1 && $this->use_group_in_registration($group->id)) {
					foreach ($group->fields as $field) {
						$key = "field_".$field->id;
						if (! empty($_POST[$key])) {
							$meta[$key] = $_POST[$key];
						}
					}
				}
			}
			return $meta;
		}

		public function xprofile_activate_user($user_id, $key, $user) {
			$meta = $user['meta'];
			//get field groups
			$field_groups = bp_profile_get_field_groups();
			//iterate those not in base
			foreach ($field_groups as  $group) {
				if ($group->id != 1 && $this->use_group_in_registration($group->id)) {
					//iterate through fields
					foreach ($group->fields as $field) {
						//generate key
						$key = "field_".$field->id;
						//if value is in meta store value in field associated with user
						if (isset($meta[$key]) && ! empty($meta[$key])) {
							xprofile_set_field_data($field->id, $user_id, $meta[$key], $field->is_required);
						}
					}
				}
			}
		}

		/*
		 * add_frontend_styles_and_scripts
		 *
		 */
		public function add_frontend_styles_and_scripts() {
			if (bp_is_register_page()) {
				wp_enqueue_script('xprofile-registration-validation', plugin_dir_url(__FILE__).'/js/registration.js', ['jquery']);
			}
			wp_enqueue_style("registration_display_xprofile_group_styles", plugin_dir_url(__FILE__).'/css/style.css');
		}

		public function options_menu() {
			add_options_page("Registration xProfile Settings", "Registration xProfile Settings", "manage_options", "registration-xprofile-settings", array($this, "registration_xprofile_settings"));
		}

		public function render_intro() {
			$content = get_site_option('registration_selected_xprofile_groups_intro');

			if ($content !== false) {
				echo "<div class='full_width'>".stripslashes($content)."</div>";
			}
		}

		/*
		 * AJAX call to store intro to be dipslayed before registation page
		 */
		public function store_intro() {
			//verify nonce
			if (!wp_verify_nonce($_REQUEST['nonce'], 'settings_nonce')) {
				exit('Invalid AJAX call');
			}

			$content = $_REQUEST['content'];

			update_site_option('registration_selected_xprofile_groups_intro', $content);
			wp_send_json(array('status' => "Success"));
		}

		/*
		 * AJAX call to store check xprofile groups to be rendered on registration page
		 */
		public function store_xprofile_field_groups() {
			//verify nonce
			if (!wp_verify_nonce($_REQUEST['nonce'], 'settings_nonce')) {
				exit('Invalid AJAX call');
			}
			$selected = (isset($_REQUEST["selected"]))?$_REQUEST["selected"]:array();//if none are seleced this paramater won't be set
			//update selected boxes
			update_site_option("registration_selected_xprofile_groups", $_REQUEST["selected"]);
			wp_send_json_success();
		}

		/*
		 * True or false if group is used in registration
		 * @input $group_id
		 * @return bool
		 */
		private function use_group_in_registration($group_id) {
			return (bool) in_array($group_id, $this->selected);
		}

		/*
		 * render_xprofile_fields
		 * renders all required fields in differrent field groups not in base group
		 */
		public function render_xprofile_fields() {
			$field_groups = bp_profile_get_field_groups();

			//get selected groups
			$selected = get_site_option("registration_selected_xprofile_groups");

			//iterate through each group
			foreach ($field_groups as $group) {

				//if group is not 'Base' then render it with it's required fields
				if ($group->id != 1 && $this->use_group_in_registration($group->id)) {
					?>
					<div class="register-section extended-profile non-base" id="profile-details-section">
						<h2 class="bp-heading"><?php esc_html_e($group->name, 'buddypress'); ?></h2>
						<?php foreach ($group->fields as $field) : ?>
							<?php echo $this->get_field_html($field); ?>
						<?php endforeach; ?>
					</div>
					<?php
				}
			}
		}

		private function get_field_html($field) {
			$id = apply_filters('bp_get_the_profile_field_input_name', 'field_' . $field->id);
			$name = apply_filters('bp_get_the_profile_field_input_name', 'field_' . $field->id);

			$aria_required = ($field->is_required) ? ' aria-required="true" required ' : ' aria-required="false" ';
			$aria_required .= ' aria-labelledby="field_'.$id.'-1" ';

			$html = '<div class ="editfield '.$id.
						'field_'.trim(strtolower($field->name)).' visibility-public field_type_'.$field->type.' '
					.(($field->is_required)? "required-field" : ''). '">'
					. '<fieldset>';

			//label
			$html .= '<legend id="'.$id.'-1">'
					. ' '.$field->name.' '
					. (($field->is_required)? '<span class="bp-required-field-label">(required)</span>' : '')
					. '</legend>';

			if ($field->description) {
				$html .='<p class="description" tabindex="0">'.$field->description.'</p>';
			}

			//possible error
			ob_start();
			do_action('bp_'. $id.'_errors');
			$errors = ob_get_contents();
			ob_get_clean();

			$html .= $errors;

			//get specific type html
			switch ($field->type) {
			case 'text':
				$value = (isset($_POST[$id])) ? $_POST[$id] : '';
				$html .= '<input id="'.$id.'" name="'.$name.'" type="text" '
					  . $aria_required.' value="'.$value.'" /> ';
				break;
			case 'checkbox':
				$html .= '<div id="'.$id.'" class="input-options checkbox-options">';
				$options = $field->type_obj->field_obj->get_children();
				$count = 0;
				foreach ($options as $opt) {
					$checked = (isset($_POST[$id]) && is_array($_POST[$id]) && in_array($opt->name, $_POST[$id])) ?
							" checked " : '';
					$opt_id = 'field_'.$opt->id.'_'.$count;
					$html .= '<label for="'.$opt_id.'"  class="option-label">'
							. '<input type="checkbox" name="'.$id.'[]" id="'.$opt_id.'" value="'.$opt->name.'" '.$checked.'>'.$opt->name
							. '</label>';
					$count++;
				}
				$option_values = maybe_unserialize(BP_XProfile_ProfileData::get_value_byid($this->field_obj->id, $args['user_id']));
				$html .= '</div>';
				break;
			case 'radio':
				$html .= '<div id="'.$id.'" class="input-options checkbox-options">';
				$options = $field->type_obj->field_obj->get_children();
				foreach ($options as $opt) {
					$checked = (isset($_POST[$id]) && $_POST[$id] == $opt->name) ? " checked " : "";
					$opt_id = 'option_'.$opt->id;
					$html .= '<label for="'.$opt_id.'"  class="option-label">'
							. '<input type="radio" name="'.$id.'" id="'.$opt_id.'" value="'.$opt->name.'" '.$checked.'>'.$opt->name
							. '</label>';
				}
				$option_values = maybe_unserialize(BP_XProfile_ProfileData::get_value_byid($this->field_obj->id, $args['user_id']));
				$html .= '</div>';
				break;
			case 'multiselectbox':
				$html .= '<select id="'.$id.'[]" name="'.$id.'[]" multiple="multiple" '.$aria_required.' >';
				$args = bp_parse_args(array(), array(
					'type'    => false,
					'user_id' => bp_displayed_user_id(),
				), 'get_the_profile_field_options');
				ob_start();
				$field->type_obj->edit_field_options_html($args);
				$select_options = ob_get_contents();
				ob_end_clean();
				$html .= $select_options;
				$html .= '</select>';
				break;
			case 'selectbox':
				$html .= '<select id="'.$id.'" name="'.$id.'"  '.$aria_required.' >';
				$args = bp_parse_args(array(), array(
					'type'    => false,
					'user_id' => bp_displayed_user_id(),
				), 'get_the_profile_field_options');
				ob_start();
				$field->type_obj->edit_field_options_html($args);
				$select_options = ob_get_contents();
				ob_end_clean();
				$html .= $select_options;
				$html .= '</select>';
				break;
			case 'datebox':
				$user_id = bp_displayed_user_id();
				$html .= '<div class="input-options datebox-selects">';
				//days
				$html .= '<label for="'.$id.'_day" class="xprofile-field-label">Day<label>';
				$html .= '<select id="'.$id.'_day" name="'.$id.'_day" '.$aria_required.' >';
				$args = bp_parse_args(
					array(
						'type'    => 'day',
						'user_id' => $user_id
					),
					array(
						'type'    => false,
						'user_id' => bp_displayed_user_id(),
					),
					'get_the_profile_field_options'
				);
				ob_start();
				$field->type_obj->edit_field_options_html($args);
				$select_options = ob_get_contents();
				ob_end_clean();
				$html .= $select_options;
				$html .= '</select>';
				//months
				$html .= '<label for="'.$id.'_month" class="xprofile-field-label">Month<label>';
				$html .= '<select id="'.$id.'_month" name="'.$id.'_month" '.$aria_required.' >';
				$args = bp_parse_args(
					array(
						'type'    => 'month',
						'user_id' => $user_id
					),
					array(
						'type'    => false,
						'user_id' => bp_displayed_user_id(),
					),
					'get_the_profile_field_options'
				);
				ob_start();
				$field->type_obj->edit_field_options_html($args);
				$select_options = ob_get_contents();
				ob_end_clean();
				$html .= $select_options;
				$html .= '</select>';
				//years
				$html .= '<label for="'.$id.'_year" class="xprofile-field-label">Year<label>';
				$html .= '<select id="'.$id.'_year" name="'.$id.'_year" '.$aria_required.' >';
				$args = bp_parse_args(
					array(
						'type'    => 'year',
						'user_id' => $user_id
					),
					array(
						'type'    => false,
						'user_id' => bp_displayed_user_id(),
					),
					'get_the_profile_field_options'
				);
				ob_start();
				$field->type_obj->edit_field_options_html($args);
				$select_options = ob_get_contents();
				ob_end_clean();
				$html .= $select_options;
				$html .= '</select>';
				break;
			case 'textbox':
				$value = (isset($_POST[$id])) ? $_POST[$id] : '';
				$html .= '<input id="'.$id.'" name="'.$id.'" type ="text" '.$aria_required.' value="'.$value.'" />';
				break;
			case 'textarea':
				$value = (isset($_POST[$id])) ? stripslashes($_POST[$id]) : '';
				$richtext_enabled = bp_xprofile_is_richtext_enabled_for_field($field->id);
				if (! $richtext_enabled) {
					$r = wp_parse_args($raw_properties, array(
						'cols' => 40,
						'rows' => 5,
					));
					$html .= '<textarea cols="40" rows="5" id="'.$id.'" name="'.$name.'" '.$aria_required.' >'.$value.'</textarea>';
				} else {
					$editor_args = apply_filters('bp_xprofile_field_type_textarea_editor_args', array(
						'teeny'         => true,
						'media_buttons' => false,
						'quicktags'     => true,
						'textarea_rows' => 10,
					), 'edit');
					ob_start();
					wp_editor($value, $name, $editor_args);
					$editor = ob_get_contents();
					ob_end_clean();

					$html .= $editor;
				}
				break;
			case 'telephone':
				$value = (isset($_POST[$id])) ? $_POST[$id] : '';
				$html .= '<input id="'.$id.'" name="'.$name.'" type="tel" '.$aria_required.' value="'.$value.'" />';
				break;
			case 'url':
				$value = (isset($_POST[$id])) ? $_POST[$id] : '';
				$html .= '<input id="'.$id.'" name="'.$name.'" type="text" '.$aria_required.'  value="'.$value.'" />';
				break;
			default:
				//found no type return blank string
				return '';
				break;
			}
			$html .= '</fieldset>'
					. '</div>';

			return $html;
		}

		/*
		 * Render the xProfile field group Selector page
		 */
		public function registration_xprofile_settings() {
			//get field groups that are not base
			$field_groups = bp_profile_get_field_groups();

			//get site option of selected field groups
			$selected = get_site_option("registration_selected_xprofile_groups");

			//set to empty array if nothing is set
			$selected = ($selected)?$selected:array();

			//check field groups for required fields and add those field groups to possible ones
			$possible_group_ids = array();
			$possible_groups    = array();
			foreach ($field_groups as $group) {
				if ($group->id != 1) {
					//store group name associated by id
					$possible_groups[$group->id] = $group->name;
					$possible_group_ids[] = $group->id;
				}
			}

			//make sure the selected only contains field groups that can possibly be selected
			$selected = array_intersect($selected, $possible_group_ids);

			//updated selected to be accurate
			update_site_option('registration_selected_xprofile_groups', $selected);

			//display selected and unselected groups in html
			?>
			<div class="wrap">
				<h1>Select xProfile Groups to Include on Registration</h1>
				<div class="group_holder">
					<?php foreach ($possible_groups as $id => $group) {
						?>
						<p><label><input type="checkbox" name="field_group" value="<?php echo $id; ?>" <?php echo (in_array($id, $selected))?"checked":""; ?>  /><?php echo $group; ?></label></p>
						<?php
					} ?>
				</div>
				<h3>Text to insert before start of registration (optional)</h3>
			</div>
			<?php

			//wording to output before registration
			$wording = get_site_option('registration_selected_xprofile_groups_intro');
			$wording = (empty($wording) || $wording === false) ? '' : stripslashes($wording);
			wp_editor($wording, 'intro_text');
			submit_button("Save", "primary", 'intro_wording_save', true, array(
				'tabindex' => '0',
				'id' => 'intro_wording_save'
			));
		}
	}

	$xprofile_group_display = new Registration_Display_xProfile_Groups();
	$xprofile_group_display->set_hooks();
}
