jQuery(document).ready(function ($) {
    var valid = true;
    //add listener for submit button
    $(document).on('click', 'input[type="submit"]', function(){
        return validateNonBase();
    });
    //add verifier for non base fields as they are selected
    $(document).on( 'change', '.non-base .required-field', function(){
        validateField($(this));
    });

    function validateNonBase(){
         valid = true;
         //iterate through each xprofile group
         $('.non-base').each(function(){
                //iterate through each required field
                $(this).find('.required-field').each(function(){
                    //validate field
                    validateField($(this));
                });     
         });
         
         //return valid
         return valid;
    }
    
    function validateField( field ){
        //check for checkbox
        
        
        if( field.find('.checkbox-options input').length > 0 ){
            //see if there is s checked box
            var chkLen = field.find('.checkbox-options input:checked').length;
            if(chkLen == 0   ){
                valid = false;
                //add invalid box if necessary
                addInvalidBox(field);
            } else {
                removeInvalidBox(field);
            }
            //check fo select
        }else if( field.find('select').length > 0 ){
            //check for value in every select (datebox has more than one

            field.find('select').each(function(){

                if($(this).val() == ''){
                    addInvalidBox(field);
                    valid = false;
                } else {
                    removeInvalidBox(field);
                }
            });
        } else if( field.find('textarea').length > 0 ){
            //check for Tinymce  editor
            if(field.find('.mce-container').length > 0 ){
                var mce_id = $(this).find('textarea').first().attr('id');
                if( tinymce.editors.mce_id.getContent().replace(/(<([^>]+)>)/ig,"") == '' ){
                    addInvalidBox(field);
                    valid = false;
                } else {
                    removeInvalidBox(field);
                }
            } else {
                //none found check val of textarea
                if(field.find('textarea').first().val() == '' ){
                    addInvalidBox(field);
                    valid = false;
                } else {
                    removeInvalidBox(field);
                }
            }
        } else {
            //should just be input boxes left
            if( field.find('input').first().val() == '' ){
                    addInvalidBox(field);
                    valid = false;
                } else {
                    removeInvalidBox(field);
                }
        }
        return valid;
    }
    
    function addInvalidBox( field ){
        if( field.find('div.field-invalid').length == 0 ){
            field.prepend('<div class="field-invalid">This field is required</div.');
        }
    }
    function removeInvalidBox( field ){
        field.find('div.field-invalid').each(function(){
            $(this).remove();
        })
    }
});
