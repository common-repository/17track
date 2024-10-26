<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if ( ! defined( 'ABSPATH' ) )exit;
class YqTrack_Upload
{ 
    private $options;
    public function __construct()
    {                  
        add_action('admin_menu', array( $this, 'register_woocommerce_menu' ),99 );
        add_action('admin_print_styles', array($this, 'admin_styles'));
        add_action('admin_print_scripts', array(&$this, 'library_scripts'));
    }

    public function admin_styles()
    {
		wp_enqueue_style('yqtrack_styles', plugins_url(basename(dirname(__FILE__))) . '/assets/css/admin.css');	
    }

    public function library_scripts()
    {
		wp_enqueue_script('material-js', plugins_url(basename(dirname(__FILE__))) . '/assets/js/material.min.js');
		wp_enqueue_script('ajax-queue', plugins_url(basename(dirname(__FILE__))) . '/assets/js/jquery.ajax.queue.js');
        wp_enqueue_script('yqtrack_script_util', plugins_url(basename(dirname(__FILE__))) . '/assets/js/util.js');
        wp_enqueue_script('yqtrack_script_couriers', '//res.17track.net/i18n/merge-i18n/enum/enum.en.js?src=woo');
				
    }    

    public function register_woocommerce_menu() {
		add_submenu_page( 'woocommerce', '17TRACK Upload CSV', 'Upload CSV', 'manage_options', 'yqtrack-woocommerce-tracking', array( $this, 'yqtrack_woocommerce_tracking_upload_csv' ) ); 
	}
    
    public function yqtrack_woocommerce_tracking_upload_csv()
    {       
        ?>
        <div class="wrap">
            <h2>17TRACK Upload CSV</h2>
			<link rel=stylesheet href="<?php echo plugin_dir_url( __FILE__ ) ?>assets/css/material.css" type="text/css">
            <form method="post" id="yqtrack_upload_csv_form" action="" enctype="multipart/form-data">
			<section>
			<h3></h3>
			<table>
				<tbody>
					<tr valign="top">
						<td style="height:60px">
							<input type="file" name="trcking_csv_file" id="trcking_csv_file">
						</td>
					</tr> 
					<tr valign="top">
						<td style="height:60px">
							<label for=""><?php _e('Replace tracking info if exists? (if not checked, the tracking info will be added)', 'yqtrack-woocommerce-tracking'); ?></label>
							<input type="checkbox" id="replace_tracking_info" name="replace_tracking_info" class="" value="1"/>	
							<br/>
							<a href="<?php echo plugin_dir_url( __FILE__ ) ?>assets/tracking.csv"><?php _e('Download sample csv file', 'yqtrack-woocommerce-tracking'); ?></a>						
						</td>
					</tr>
					<tr valign="top">
						<td style="height:60px">
							<label for=""><?php _e('Update the order status to "completed"', 'yqtrack-woocommerce-tracking'); ?></label>
							<input type="checkbox" checked id="update_order_status" name="update_order_status" class="" value="1"/>	
							</td>
					</tr>
					<tr valign="top">
						<td>
							<div class="submit">
								<button name="save" class="button-primary btn_ast2 btn_large" type="submit" value="Save"><?php _e('Upload', 'yqtrack-woocommerce-tracking'); ?></button>
								<div class="spinner" style="float:none"></div>
								<div class="success_msg" style="display:none;"><?php _e('Tracking Numbers Saved.', 'yqtrack-woocommerce-tracking'); ?></div>
								<div class="error_msg" style="display:none;"></div>
							</div>	
						</td>
					</tr>					
				</tbody>
			</table>				          
			<div id="demo-toast-example" class="mdl-js-snackbar mdl-snackbar">
				<div class="mdl-snackbar__text"></div>
				<button class="mdl-snackbar__action" type="button"></button>
			</div>																							
			<div id="p1" class="mdl-progress mdl-js-progress" style="display:none;"></div>
			<h3 class="progress_title" style="display:none;"><?php _e('Upload Progress - ', 'yqtrack-woocommerce-tracking'); ?><span class="progress_number"></span></h3>
			<ol class="csv_upload_status">				
			</ol>		
			</section>	
		</form>	
        </div>
    <?php
    }
    
}


if (is_admin())
    $yqtrack_upload = new YqTrack_Upload();
