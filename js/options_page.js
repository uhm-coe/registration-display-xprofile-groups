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
   })
});
