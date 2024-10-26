
var V5Front = V5Front || {};

function yqrack_sort_couriers(data) {
	var n = data.length;
	for (var i = 0; i < n - 1; i++) {
		var find = false;
		for (var j = i + 1; j < n; j++) {
			if (data[i]._name.toLowerCase() > data[j]._name.toLowerCase()) {
				var tmp = data[i];
				data[i] = data[j];
				data[j] = tmp;
				find = true;
			}
		}
		if (!find) {
			break;
		}
	}
	return data;
}


function showerror(element) {
	element.css("border", "1px solid red");
}
function hideerror(element) {
	element.css("border", "1px solid #ddd");
}

jQuery(document).on("submit", "#yqtrack_upload_csv_form", function () {
	jQuery('.csv_upload_status li').remove();
	jQuery('.progress_title').hide();
	var form = jQuery('#yqtrack_upload_csv_form');
	var error;
	var trcking_csv_file = form.find("#trcking_csv_file");
	var replace_tracking_info = jQuery("#replace_tracking_info").prop("checked");
	if (replace_tracking_info == true) {
		replace_tracking_info = 1;
	} else {
		replace_tracking_info = 0;
	}
	var update_order_status = jQuery("#update_order_status").prop("checked");
	if (update_order_status == true) {
		update_order_status = 1;
	} else {
		update_order_status = 0;
	}


	var ext = jQuery('#trcking_csv_file').val().split('.').pop().toLowerCase();

	if (trcking_csv_file.val() === '') {
		showerror(trcking_csv_file);
		error = true;
	} else {
		if (ext != 'csv') {
			alert('Please upload csv file');
			showerror(trcking_csv_file);
			error = true;
		} else {
			hideerror(trcking_csv_file);
		}
	}

	if (error == true) {
		return false;
	}

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

	var regex = /([a-zA-Z0-9\s_\\.\-\(\):])+(.csv|.txt)$/;
	if (regex.test(jQuery("#trcking_csv_file").val().toLowerCase())) {
		if (typeof (FileReader) != "undefined") {
			var reader = new FileReader();
			reader.onload = function (e) {
				var trackings = new Array();
				var rows = e.target.result.split("\n");

				for (var i = 1; i < rows.length; i++) {
					var cells = rows[i].split(",");
					if (cells.length > 1) {
						var tracking = {};
						var provider= "0";
						var provider_name=cells[1];
						jQuery.each(couriers, function (index, courier) {
							if(courier._name.toLowerCase() == cells[1].toLowerCase())
							provider= courier.key;
						});

						tracking.order_id = cells[0];
						tracking.tracking_provider = provider;
						tracking.tracking_provider_name = provider_name;
						tracking.tracking_number = cells[2];
						tracking.tracking_shipdate = cells[3];
						if (tracking.order_id) {
							trackings.push(tracking);
						}
					}
				}

				var csv_length = trackings.length;
				jQuery("#yqtrack_upload_csv_form")[0].reset();
				jQuery("#p1 .progressbar").css('background-color', 'rgb(63,81,181)');
				var querySelector = document.querySelector('#p1');
				querySelector.MaterialProgress.setProgress(0);
				jQuery("#p1").show();
				jQuery(trackings).each(function (index, element) {

					var order_id = trackings[index]['order_id'];
					var tracking_provider = trackings[index]['tracking_provider'];
					var tracking_provider_name = trackings[index]['tracking_provider_name'];
					var tracking_number = trackings[index]['tracking_number'];
					var tracking_shipdate = trackings[index]['tracking_shipdate'];

					var data = {
						action: 'yqtrack_upload_csv',
						order_id: order_id,
						tracking_provider: tracking_provider,
						tracking_provider_name:tracking_provider_name,
						tracking_number: tracking_number,
						tracking_shipdate:tracking_shipdate,
						replace_tracking_info: replace_tracking_info,
						update_order_status:update_order_status,
						trackings: trackings,
					};

					var option = {
						url: ajaxurl,
						data: data,
						type: 'POST',
						success: function (data) {
							jQuery('.progress_number').html((index + 1) + '/' + csv_length);

							jQuery('.csv_upload_status').append(data);
							var progress = (index + 1) * 100 / csv_length;
							jQuery('.progress_title').show();
							querySelector.MaterialProgress.setProgress(progress);
							if (progress == 100) {
								jQuery("#p1 .progressbar").css('background-color', 'green');
								var snackbarContainer = document.querySelector('#demo-toast-example');
								var data = { message: 'Data saved successfully.' };
								snackbarContainer.MaterialSnackbar.showSnackbar(data);
							}
						},

					};

					jQuery.ajaxQueue.addRequest(option);

					jQuery.ajaxQueue.run();

				});
			}
			reader.readAsText(jQuery("#trcking_csv_file")[0].files[0]);


		} else {
			alert('This browser does not support HTML5.');
		}
	} else {
		alert('Please upload a valid CSV file.');
	}
	return false;
});