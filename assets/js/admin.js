var yqtrack_woocommerce_tracking_onload_run = false;

var yqtrack_woocommerce_tracking_onload = function () {
	if (yqtrack_woocommerce_tracking_onload_run) {
		return yqtrack_woocommerce_tracking_onload_run;
	}
	yqtrack_woocommerce_tracking_onload_run = true;

	var fields_id = {
		'tracking_ship_date': 'yqtrack_tracking_shipdate',
		'yqtrack_tracking_provider_name': 'yqtrack_tracking_provider_name'
	};

	var providers;

	function set_yqtrack_tracking_provider() {
		jQuery('#yqtrack_tracking_provider').on('change',function () {
			var key = jQuery(this).val();
			if (key) {
				var provider = providers[key];
				jQuery('#yqtrack_tracking_provider_name').val(provider);
			}else{
				jQuery('#yqtrack_tracking_provider_name').val('');
			}
		});
	}

	function fill_meta_box(couriers_selected) {
		var response = V5Front.ResGCarrier.items.itemsDict.data;
		var couriers = [];
		jQuery.each(response, function (index, courier) {
			if (couriers_selected.indexOf(courier.key) != -1) {
				couriers.push(courier);
			}
		});
		//
		response = V5Front.ResGExpress.items.itemsDict.data;
		jQuery.each(response, function (index, courier) {
			if (couriers_selected.indexOf(courier.key) != -1) {
				couriers.push(courier);
			}
		});

		var selected_provider = jQuery('#yqtrack_tracking_provider_hidden').val();
		var find_selected_provider = couriers_selected.indexOf(selected_provider) != -1;
		if (!find_selected_provider && selected_provider) {
			couriers.push({
				key: selected_provider,
				_name: jQuery("#yqtrack_tracking_provider_name").val()
			});
		}

		couriers = yqrack_sort_couriers(couriers);

		jQuery.each(couriers, function (key, courier) {
			var str = '<option ';
			if (!find_selected_provider && courier['key'] == selected_provider) {
				str += 'style="display:none;" ';
			}
			str += 'value="' + courier['key'] + '" ';
			if (courier['key'] == selected_provider) {
				str += 'selected="selected"';
			}
			str += '>' + courier['_name'] + '</option>';
			jQuery('#yqtrack_tracking_provider').append(str);
		});
		jQuery('#yqtrack_tracking_provider').trigger("chosen:updated");
		jQuery('#yqtrack_tracking_provider_chosen').css({width: '100%'});

		providers = {};
		jQuery.each(couriers, function (index, courier) {
			providers[courier.key] = courier._name;
		});
		set_yqtrack_tracking_provider();
		jQuery('#yqtrack_tracking_provider').trigger('change');
	}

	if (jQuery('#yqtrack_tracking_provider').length > 0) {
		var couriers_selected = jQuery('#yqtrack_couriers_selected').val();
		var couriers_selected_arr = (couriers_selected) ? couriers_selected.split(',') : [];
		fill_meta_box(couriers_selected_arr);
	}

	return yqtrack_woocommerce_tracking_onload_run;
};

jQuery( function( $ ) {

	var yqtrack_woocommerce_tracking_items = {

		// init Class
		init: function() {
			$( '#woocommerce-yqtrack' )
				.on( 'click', 'a.delete-tracking', this.delete_tracking )	
				.on( 'click', 'button.button-save-form', this.save_form );
		},

		// When a user enters a new tracking item
		save_form: function () {			
			var error;	
			var tracking_number = jQuery("#yqtrack_tracking_number");
			var tracking_provider = jQuery("#yqtrack_tracking_provider_name");
			if( tracking_number.val() === '' ){				
				showerror( tracking_number );error = true;
			} else{
				var pattern = /^[0-9a-zA-Z \b]+$/;		
				if(!pattern.test(tracking_number.val())){			
					showerror( tracking_number );
					error = true;
				} else{
					hideerror(tracking_number);
				}				
			}
			if( tracking_provider.val() === '' ){				
				jQuery("#yqtrack_tracking_provider").siblings('.select2-container').find('.select2-selection').css('border-color','red');
				error = true;
			} else{
				jQuery("#yqtrack_tracking_provider").siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
			}
			if(error == true){
				return false;
			}
			if ( !$( 'input#yqtrack_tracking_number' ).val() ) {
				return false;
			}					
			
			var data = {
				action: 'yqtrack_tracking_save_form',
				order_id: woocommerce_admin_meta_boxes.post_id,
				tracking_provider: $( '#yqtrack_tracking_provider' ).val(),
				tracking_provider_name: $( '#yqtrack_tracking_provider_name' ).val(),
				tracking_shipdate: $( 'input#yqtrack_tracking_shipdate' ).val(),
				tracking_number: $( 'input#yqtrack_tracking_number' ).val()
			};


			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				jQuery("#post").submit();
			});

			return false;
		},

		// Delete a tracking item
		delete_tracking: function() {

			var tracking_number = $( this ).attr( 'rel' );

			var data = {
				action:      'yqtrack_tracking_delete_item',
				order_id:    woocommerce_admin_meta_boxes.post_id,
				tracking_number: tracking_number
			};

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#tracking-item-' + tracking_number ).unblock();
				if ( response != '-1' ) {
					$( '#tracking-item-' + tracking_number).remove();
				}
			});

			return false;
		}
	}

	yqtrack_woocommerce_tracking_items.init();
} );


function showerror(element){
	element.css("border","1px solid red");
}
function hideerror(element){
	element.css("border","1px solid #ddd");
}