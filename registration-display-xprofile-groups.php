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
    class Registration_Display_xProfile_Groups
    {
        public function __construct()
        {
        }
        /*
         * set all action hooks and filters used by plugin
         */
        public function set_hooks()
        {
            //hook to display all xprofile field groups after buddypress base display runs
            add_action('bp_after_signup_profile_fields', array($this, 'render_xprofile_fields'));
            //add settings page to allow choosing of field groups to be parsed for registration
            add_action('admin_menu', array($this, 'options_menu'));
            //add admin scripts
            add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
      
            //add front end css
            add_action('wp_enqueue_scripts', array($this, 'add_frontend_styles_and_scripts'));
            //settings page AJAX action
            add_action('wp_ajax_store_xprofile_field_groups', array( $this, 'store_xprofile_field_groups' ));
        }
    
        /*
         * admin scripts
         */
        public function add_admin_scripts($hook_suffix)
        {
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
            }
        }
        /*
         * add_frontend_styles_and_scripts
         *
         */
        public function add_frontend_styles_and_scripts()
        {
            wp_enqueue_style("registration_display_xprofile_group_styles", plugin_dir_url().'/registration-display-xprofile-groups/css/style.css');
        }
        public function options_menu()
        {
            add_options_page("Registration xProfile Settings", "Registration xProfile Settings", "manage_options", "registration-xprofile-settings", array($this,"registration_xprofile_settings"));
        }
        public function store_xprofile_field_groups()
        {
            //verify nonce
            if (! wp_verify_nonce($_REQUEST['nonce'], 'settings_nonce')) {
                exit('Invalid AJAX call');
            }
            $selected = (isset($_REQUEST["selected"])) ? $_REQUEST["selected"] : array(); //if none are seleced this paramater won't be set
            //update selected boxes
            update_site_option("registration_selected_xprofile_groups", $_REQUEST["selected"]);
            wp_send_json_success();
        }
        /*
         * render_xprofile_fields
         * renders all required fields in differrent field groups not in base group
         */
        public function render_xprofile_fields()
        {
            $field_groups = bp_profile_get_field_groups();
            
            //get selected groups
            $selected = get_site_option("registration_selected_xprofile_groups");
            
            //iterate through each group
            foreach ($field_groups as $group) {
                //if group is not 'Base' then render it with it's required fields
                if ($group->id != 1 && in_array($group->id, $selected)) {
                    ?>
            <div class="register-section extended-profile" id="profile-details-section">

               <h2 class="bp-heading"><?php esc_html_e($group->name, 'buddypress'); ?></h2>

            </div>
            <?php
                }
            }
        }
        /*
         * Render the xProfile field group Selector page
         */
        public function registration_xprofile_settings()
        {
            //get field groups that are not base
            $field_groups = bp_profile_get_field_groups();
            //get site option of selected field groups
            $selected = get_site_option("registration_selected_xprofile_groups");
            //set to empty array if nothing is set
            $selected = ($selected) ? $selected : array();
      
            //check field groups for required fields and add those field groups to possible ones
            $possible_group_ids = array();
            $possible_groups = array();
            foreach ($field_groups as $group) {
                if ($group->id != 1) {
                    //store group name associated by id
                    $possible_groups[$group->id] = $group->name;
                    foreach ($group->fields as $field) {
                        if ($field->is_required) {
                            if (!in_array($group->id, $possible_group_ids)) {
                                $possible_group_ids[] = $group->id;
                            }
                            break;
                        }
                    }
                }
            }
      
            //make sure the selected only contains field groups that can possibly be selected
            $selected = array_intersect($selected, $possible_group_ids);
            //updated selected to be accurate
            update_site_option('registration_selected_xprofile_groups', $selected);
            //display selected and unselected groups in html?>
<div class="wrap">
<h1>Select xProfile Groups to Include on Registration</h1>
<small>Only groups with required fields will be listed and only required fields will be displayed</small>
<div class="group_holder">
    <?php foreach ($possible_groups as $id => $group) {
                ?>
    <p><label><input type="checkbox" name="field_group" value="<?php echo $id; ?>" <?php echo (in_array($id, $selected))? "checked" : ""; ?>  /><?php echo $group; ?></label></p>
   <?php
            } ?>
    </div> 
</div>
<?php
        }
    }
  
    $xprofile_group_display = new Registration_Display_xProfile_Groups();
    $xprofile_group_display->set_hooks();
}
