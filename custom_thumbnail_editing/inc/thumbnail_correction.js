jQuery(document ).ready(function() {

    jQuery(".choose_thumbnail_single.thumbnail_not_yet_corrected .tc_img_wrap").click(function(){
        //find and toggle choosen images/checkboxes
        var checkbox = jQuery(this).parent().find(".tc_multiproduct_selectbox");
        checkbox.prop("checked", !checkbox.prop("checked"));
        jQuery(this).parent().toggleClass('choosen');
    });
});