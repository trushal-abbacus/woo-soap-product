jQuery(document).ready(function($){
    $("#display_popup").click(function(){
     showpopup();
    });
    $("#cancel_button").click(function(){
     hidepopup();
    });
    $("#close_button").click(function(){
     hidepopup();
    });
});
   
   
function showpopup()
{
    jQuery("#popup_box").fadeToggle();
    jQuery("#popup_box").css({"visibility":"visible","display":"block"});
}

function hidepopup()
{
    jQuery("#popup_box").fadeToggle();
    jQuery("#popup_box").css({"visibility":"hidden","display":"none"});
}

jQuery(document).ready(function($){
$('#submit_button').click( function(e) {

    e.preventDefault();
    var form = jQuery('#frmorder');
    var data = form.serialize();
    jQuery('body').append('<div class="loading"></div>');
    jQuery.ajax({
        type: "POST",
        url: primaAjax.ajaxurl,
        dataType: 'html',
        data: data,
        beforeSend: function() {
            jQuery('#ajaxBusy').show();    
        },
        success: function(data){

            var obj = jQuery.parseJSON( data );
            console.log(obj);
            code = obj.code
            if(code == 200){
                jQuery('#dataval').html(obj.resultdata);
                jQuery('.loading').hide();
            }else{
                //jQuery('.msgdata').html(obj.msg);
                jQuery('.loading').hide();
            }
        },
        complete: function(){
            jQuery('#ajaxBusy').hide();
        } 
    })

}); 
}); 
