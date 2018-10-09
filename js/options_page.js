jQuery(document).ready(function ($) {
   //any time a check box is clicked get an array of all clicked boxes and send to server
   $(".group_holder").on("click", "input[name='field_group']", function(){
       //make array of all checked check boxes
       let boxes = [];
       $("input[name='field_group']:checked").each(function(){
           boxes.push($(this).val());
       });
       
       //send to server
       jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: xprofile_object.ajax_url,
                data: {
                        action: 'store_xprofile_field_groups',
                        selected: boxes,
                        nonce: xprofile_object.nonce,
                },
                success: function(response) {

                },
                error: function() {
                        // console.log( 'An error occurred.  Data not saved for student.' );
                },
        });
   });
   
   //listener for save button to save tinymce content
   $(document).on('click', '#intro_wording_save', function(event){
       event.preventDefault();
       //get tinymce text
       var content = tinymce.editors.intro_text.getContent();
       
       //send to server
       jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: xprofile_object.ajax_url,
                data: {
                        action: 'store_xprofile_field_groups_intro',
                        content: content,
                        nonce: xprofile_object.nonce,
                },
                success: function(response) {
                    var msg = '<div id="alert_message_success"><p>Data Saved</p></div>';
                    jQuery(".wrap").prepend(msg).fadeIn(2000);
                    setTimeout(function(){ jQuery('#alert_message_success').fadeOut(3000)}, 1000); //remove message
                },
                error: function() {
                        // console.log( 'An error occurred.  Data not saved for student.' );
                },
        });
       
   })
});
