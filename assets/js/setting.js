jQuery(function () {
    function set_yqtrack_tracking_provider(selected_couriers) {
        var couriers = [];
        var global = V5Front.ResGCarrier.items.itemsDict.data;
        for(var i in global){
            couriers.push(global[i]);
        }
        var express = V5Front.ResGExpress.items.itemsDict.data;
        for(var j in express){
            couriers.push(express[j]);
        }
        couriers = yqrack_sort_couriers(couriers);
        jQuery.each(couriers, function (key, courier) {
                var str = '<option ';
                str += 'value="' + courier['key'] + '" ';
                if (selected_couriers.hasOwnProperty(courier['key'])) {
                    str += 'selected="selected"';
                }
                str += '>' + courier['_name'] + '</option>';
                jQuery('#yqtrack_couriers_select').append(str);
        });
        //
        jQuery('#yqtrack_couriers_select').val(selected_couriers);
        jQuery('#yqtrack_couriers_select').chosen();
	    jQuery('#yqtrack_couriers_select').trigger('chosen:updated');
    }

    jQuery('#yqtrack_couriers_select').on('change',function () {
        var couriers_select = jQuery('#yqtrack_couriers_select').val();
        var value = (couriers_select) ? couriers_select.join(',') : '';
        jQuery('#couriers').val(value);
    });

    if (jQuery('#couriers')) {
        var couriers_select = jQuery('#couriers').val();
        var couriers_select_array = (couriers_select) ? couriers_select.split(',') : [];
        set_yqtrack_tracking_provider(couriers_select_array);
    }
    
    jQuery("#orderChk").click(function () {
        if (jQuery(this).prop("checked")) {
            jQuery('#order').val(1);
        }else{
            jQuery('#order').val(0);
        }
    });

    
    jQuery("#emailChk").click(function () {
        if (jQuery(this).prop("checked")) {
            jQuery('#email').val(1);
        }else{
            jQuery('#email').val(0);
        }
    });
});