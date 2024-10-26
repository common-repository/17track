<?php
/*
	Plugin Name: 17TRACK for WooCommerce
	Plugin URI: http://www.17track.net/
	Description: Add tracking number and carrier name to WooCommerce, display tracking info at order history page, auto import tracking numbers to 17TRACK.
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if (!defined('ABSPATH'))exit;
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){    
    if (!class_exists('_17TRACK')) {
        final class _17TRACK
        {
            protected static $_instance = null;
            public static function instance()
            {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }            
            public function __construct()
            {
                $this->includes();

                $this->api = new YqTrack_API();

                $options = get_option('yqtrack_option_name');
                if ($options) {

                    if (isset($options['plugin'])) {
                        $plugin = $options['plugin'];
                        if ($plugin == '17TRACK') {
                            add_action('admin_print_scripts', array(&$this, 'library_scripts'));
                            add_action('in_admin_footer', array(&$this, 'include_footer_script'));
                            add_action('admin_print_styles', array(&$this, 'admin_styles'));
                            add_action('add_meta_boxes', array(&$this, 'add_meta_box'));
                            add_action('woocommerce_process_shop_order_meta', array(&$this, 'save_meta_box'), 0, 2);
                            //
                            add_action( 'wp_ajax_yqtrack_tracking_delete_item', array( $this, 'meta_box_delete_tracking' ) );
                            add_action( 'wp_ajax_yqtrack_tracking_save_form', array( $this, 'save_meta_box_ajax' ) );
                            //
                            add_action( 'wp_ajax_yqtrack_upload_csv', array( $this, 'upload_tracking_csv_fun') );
                            //
                            add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

                            $this->couriers = $options['couriers'];
                        }
                        $this->plugin = $plugin;
                    } else {
                        $this->plugin = '';
                    }
                    
                    $orderDisplay = '1';
                    if (isset($options['order'])) {
                        $orderDisplay = $options['order'];
                    }
                    if($orderDisplay == '1') {
                        add_action('woocommerce_view_order', array(&$this, 'display_tracking_info'));
                    }
                    
                    $emailDisplay = '1';
                    if (isset($options['email'])) {
                        $emailDisplay = $options['email'];
                    }
                    if($emailDisplay == '1') {
                        add_action('woocommerce_email_after_order_table', array(&$this, 'email_display'));
                    }
                }
                
                add_action('show_user_profile', array($this, 'add_api_key_field'));
                add_action('edit_user_profile', array($this, 'add_api_key_field'));
                add_action('personal_options_update', array($this, 'generate_api_key'));
                add_action('edit_user_profile_update', array($this, 'generate_api_key'));

                register_activation_hook(__FILE__, array($this, 'install'));
            }

            public function install()
            {
                global $wp_roles;

                if (class_exists('WP_Roles')) {
                    if (!isset($wp_roles)) {
                        $wp_roles = new WP_Roles();
                    }
                }

                if (is_object($wp_roles)) {
                    $wp_roles->add_cap('administrator', 'manage_yqtrack');
                }
            }

            private function includes()
            {
                $this->yqtrack_fields = array(                
                    'yqtrack_tracking_number' => array(
                        'id' => 'yqtrack_tracking_number',
                        'type' => 'text',
                        'label' => 'Tracking number',
                        'placeholder' => '',
                        'description' => '',
                        'class' => ''
                    ),
                );
                include_once('yqtrack-api.php');                
                include_once('yqtrack-settings.php');               
                include_once('yqtrack-upload.php');
            }
            
            public function load_plugin_textdomain()
            {
                load_plugin_textdomain('17TRACK', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            public function admin_styles()
            {
                wp_enqueue_style('yqtrack_styles_chosen', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.min.css');
                wp_enqueue_style('yqtrack_styles', plugins_url(basename(dirname(__FILE__))) . '/assets/css/admin.css');
            }

            public function library_scripts()
            {
                wp_enqueue_script('yqtrack_styles_chosen_jquery', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.jquery.min.js');
                wp_enqueue_script('yqtrack_styles_chosen_proto', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.proto.min.js');
                wp_enqueue_script('yqtrack_script_util', plugins_url(basename(dirname(__FILE__))) . '/assets/js/util.js');
                wp_enqueue_script('yqtrack_script_couriers', '//res.17track.net/i18n/merge-i18n/enum/enum.en.js?src=woo');
                wp_enqueue_script('yqtrack_script_admin', plugins_url(basename(dirname(__FILE__))) . '/assets/js/admin.js');
            }
            
            public function include_footer_script()
            {
                wp_enqueue_script('yqtrack_script_footer', plugins_url(basename(dirname(__FILE__))) . '/assets/js/footer.js', true);
            }
            
            public function add_meta_box()
            {
                add_meta_box('woocommerce-yqtrack', __('17TRACK', 'wc_yqtrack'), array(&$this, 'meta_box'), 'shop_order', 'side', 'high');
            }
            
            public function meta_box()
            {
                global $post;

                $selected_provider = get_post_meta($post->ID, '_yqtrack_tracking_provider', true);
                $tracking_items = $this->get_tracking_items( $post->ID );

                echo '<div id="yqtrack-tracking-items">';
                if ( count( $tracking_items ) > 0 ) {
                    echo '<ul class="order_notes">';
                    foreach ( $tracking_items as $tracking_item ) {				
                        $this->display_html_tracking_item_for_meta_box( $post->ID, $tracking_item );
                    }
                    echo '</ul>';
                }
		        echo '</div>';
                echo '<p class="form-field"><label for="yqtrack_tracking_provider">' . __('Carrier:', 'wc_yqtrack') . '</label><br/><select id="yqtrack_tracking_provider" name="yqtrack_tracking_provider" class="chosen_select" style="width:100%">';
                echo '<option selected="selected" value="">Please Select</option>';
                echo '</select>';
                echo '<input type="hidden" id="yqtrack_couriers_selected" value="' . $this->couriers . '"/>';
                //
                $date = new DateTime();
                $date = $date->format('Y-m-d\TH:i:s\Z');
                echo '<input type="hidden" id="yqtrack_tracking_shipdate" name="yqtrack_tracking_shipdate" value="' . $date . '"/>';
                echo '<input type="hidden" id="yqtrack_tracking_provider_name" name="yqtrack_tracking_provider_name" value=""/>';
                //
                foreach ($this->yqtrack_fields as $field) {
                    woocommerce_wp_text_input(array(
                        'id' => $field['id'],
                        'label' => __($field['label'], 'wc_yqtrack'),
                        'placeholder' => $field['placeholder'],
                        'description' => $field['description'],
                        'class' => $field['class'],
                        'value' => '',
                    ));
                }
                echo '<button class="button button-primary button-save-form">' . __( ' Save ', 'woocommerce-yqtrack' ) . '</button>';
            }
            
            
            public function save_meta_box( $post_id, $post ) {
		
                if ( isset( $_POST['yqtrack_tracking_number'] ) &&  $_POST['yqtrack_tracking_provider'] != '' && strlen( $_POST['yqtrack_tracking_number'] ) > 0 ) {
                    $tracking_number = str_replace(' ', '', $_POST['yqtrack_tracking_number']);
                    $args = array(
                        'tracking_provider'        => wc_clean($_POST['yqtrack_tracking_provider']),
                        'tracking_provider_name'   => wc_clean($_POST['yqtrack_tracking_provider_name']),
                        'tracking_number'          => wc_clean( $_POST['yqtrack_tracking_number'] ),
                        'tracking_shipdate'        => wc_clean( $_POST['yqtrack_tracking_shipdate'] ),				
                    );
                    $this->add_tracking_item( $post_id, $args );
                }
            }

            public function save_meta_box_ajax() {
                $tracking_number = str_replace(' ', '', $_POST['tracking_number']);
                
                if ( isset( $_POST['tracking_number'] ) &&  $_POST['tracking_provider'] != '' && isset( $_POST['tracking_provider'] ) && strlen( $_POST['tracking_number'] ) > 0 ) {
            
                    $order_id = wc_clean( $_POST['order_id'] );
                    $args = array(
                        'tracking_provider'        => wc_clean($_POST['tracking_provider']),
                        'tracking_provider_name'   => wc_clean( $_POST['tracking_provider_name'] ),
                        'tracking_number'          => wc_clean( $_POST['tracking_number'] ),
                        'tracking_shipdate'             => wc_clean( $_POST['tracking_shipdate'] ),
                    );
        
                    $tracking_item = $this->add_tracking_item( $order_id, $args );
                                        
                    $this->display_html_tracking_item_for_meta_box( $order_id, $tracking_item );
                }
        
                die();
            }

            public function meta_box_delete_tracking() {
                $order_id    = wc_clean( $_POST['order_id'] );
                $tracking_number = wc_clean( $_POST['tracking_number'] );
                $this->delete_tracking_item( $order_id, $tracking_number );	
            }

            public function add_tracking_item( $order_id, $args, $is_updateDate = false) {
                $tracking_item = array();
                
                $tracking_items   = $this->get_tracking_items( $order_id );
                                    
                if(isset($args['tracking_number'])){
                    $tracking_item['tracking_number'] =  $args['tracking_number'] ;
                    if ( count( $tracking_items ) > 0 ) {
                        foreach ( $tracking_items as $key => $item ) {
                            if ( $item['tracking_number'] == $tracking_item['tracking_number'] ) {
                                return $tracking_item;
                            }
                        }
                    }
                }

                if(isset($args['tracking_provider'])){
                    $tracking_item['tracking_provider'] = $args['tracking_provider'];
                }
                
                if(isset($args['tracking_provider_name'])){
                    $tracking_item['tracking_provider_name'] =  $args['tracking_provider_name'] ;
                }
                
                if(isset($args['tracking_shipdate'])){                
                    $tracking_item['tracking_shipdate'] = $args['tracking_shipdate'];
                }
                $tracking_items[] = $tracking_item;
        
                $this->save_tracking_items( $order_id, $tracking_items, $is_updateDate);
                                
                return $tracking_item;
            }

            public function save_tracking_items( $order_id, $tracking_items, $is_updateDate = false) {
                update_post_meta( $order_id, '_yqtrack_tracking_items', $tracking_items );
                if($is_updateDate){
                    $date = new DateTime();
                    $my_post = array(
                        'ID'           => $order_id,
                        'post_modified'   => $date->format('Y-m-dH:i:s'),
                        'post_modified_gmt'   => $date->format('Y-m-d\TH:i:s\Z')
                    );
                    wp_update_post($my_post);
                }
            }

            public function delete_tracking_item( $order_id, $tracking_number ) {
                $tracking_items = $this->get_tracking_items( $order_id );
                $is_deleted = false;
                //
                $tracking_number_old  =  get_post_meta($order_id, '_yqtrack_tracking_number', true);
                if($tracking_number == $tracking_number_old)
                { 
                    delete_post_meta($order_id, '_yqtrack_tracking_number');
                    delete_post_meta($order_id, '_yqtrack_tracking_provider_name');
                    delete_post_meta($order_id, '_yqtrack_tracking_provider');
                    delete_post_meta($order_id, '_yqtrack_tracking_shipdate');
                }
                //
                if ( count( $tracking_items ) > 0 ) {
                    foreach ( $tracking_items as $key => $item ) {
                        if ( $item['tracking_number'] == $tracking_number ) {
                            unset( $tracking_items[ $key ] );
                            $is_deleted = true;
                            break;
                        }
                    }
                    $this->save_tracking_items( $order_id, $tracking_items, true);
                }
        
                return $is_deleted;
            }


            function upload_tracking_csv_fun(){				
		
                $replace_tracking_info = $_POST['replace_tracking_info'];
                $order_id = $_POST['order_id'];	

                $tracking_provider = $_POST['tracking_provider'];
                $tracking_provider_name = $_POST['tracking_provider_name'];
                $tracking_number = $_POST['tracking_number'];
                $tracking_shipdate = $_POST['tracking_shipdate'];
                $replace_tracking_info = $_POST['replace_tracking_info'];
                $update_order_status = $_POST['update_order_status'];
                
                if($tracking_provider == 0){
                    echo '<li class="error">Failed - Invalid Tracking Provider for Order Id - '.$_POST['order_id'].'</li>';exit;
                }
                if(empty($tracking_number)){
                    echo '<li class="error">Failed - Empty Tracking Number for Order Id - '.$_POST['order_id'].'</li>';exit;
                }
                if(preg_match('/[^a-z0-9 \b]+/i', $tracking_number)){
                    echo '<li class="error">Failed - Special character not allowd in tracking number for Order Id - '.$_POST['order_id'].'</li>';exit;
                }
                if(empty($tracking_shipdate)){
                    echo '<li class="error">Failed - Empty Date Shipped for Order Id - '.$_POST['order_id'].'</li>';exit;
                }	
                if(!$this->isDate($tracking_shipdate)){
                    echo '<li class="error">Failed - Invalid Date Shipped for Order Id - '.$_POST['order_id'].'</li>';exit;
                }	
                
                if($replace_tracking_info == 1){
                    $order = wc_get_order($order_id);
                    
                    if($order){	
                        $tracking_items = $this->get_tracking_items( $order_id );			
                        
                        if ( count( $tracking_items ) > 0 ) {
                            foreach ( $tracking_items as $key => $item ) {
                                $tracking_number = $item['tracking_number'];						
                                if(in_array($tracking_number, array_column($_POST['trackings'], 'tracking_number'))) {
                                    
                                } else{
                                    unset( $tracking_items[ $key ] );
                                }											
                            }
                            $this->save_tracking_items( $order_id, $tracking_items, true);
                            if($update_order_status == 1){
                                $order->update_status( 'completed' );
                            }
                        }
                    }
                }
                if($tracking_provider && $tracking_number && $tracking_shipdate){
        
                    $args = array(
                        'tracking_provider_name'     => wc_clean( $_POST['tracking_provider_name'] ),						
                        'tracking_provider'       => wc_clean( $_POST['tracking_provider'] ),				
                        'tracking_number'       => wc_clean( $_POST['tracking_number'] ),
                        'tracking_shipdate'          => wc_clean( $_POST['tracking_shipdate'] ),
                    );
                    $order = wc_get_order($order_id);
                            
                    if ( $order === false ) {
                        echo '<li class="error">Failed - Invalid Order Id - '.$_POST['order_id'].'</li>';exit;
                    } else{
                        $this->add_tracking_item( $order_id, $args, true);
                        if($update_order_status == 1){
                            $order->update_status( 'completed' );
                        }
                        echo '<li class="success">Success - Successfully added tracking info for Order Id- '.$_POST['order_id'].'</li>';
                        exit;
                    }
                    
                } else{
                    echo '<li class="error">Failed - Invalid Tracking Data</li>';exit;
                }	
            }
            //
            function isDate($value) 
            {
                if (!$value) {
                    return false;
                }
            
                try {
                    new \DateTime($value);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            }
            
            public function add_api_key_field($user)
            {
                if (!current_user_can('manage_yqtrack'))
                    return;

                if (current_user_can('edit_user', $user->ID)) {
                    ?>
                    <h3>17TRACK</h3>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th><label
                                    for="yqtrack_wp_api_key"><?php _e('17TRACK\'s WordPress API Key', '17TRACK'); ?></label>
                            </th>
                            <td>
                                <?php if (empty($user->yqtrack_wp_api_key)) : ?>
                                    <input name="yqtrack_wp_generate_api_key" type="checkbox"
                                           id="yqtrack_wp_generate_api_key" value="0"/>
                                    <span class="description"><?php _e('Generate API Key', '17TRACK'); ?></span>
                                <?php else : ?>
                                    <code id="yqtrack_wp_api_key"><?php echo $user->yqtrack_wp_api_key ?></code>
                                    <br/>
                                    <input name="yqtrack_wp_generate_api_key" type="checkbox"
                                           id="yqtrack_wp_generate_api_key" value="0"/>
                                    <span class="description"><?php _e('Revoke API Key', '17TRACK'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                <?php
                }
            }
            
            public function generate_api_key($user_id)
            {
                if (current_user_can('edit_user', $user_id)) {
                    $user = get_userdata($user_id);
                    if (isset($_POST['yqtrack_wp_generate_api_key'])) {                        
                        if (empty($user->yqtrack_wp_api_key)) {
                            $api_key = 'ck_' . hash('md5', $user->user_login . date('U') . mt_rand());
                            update_user_meta($user_id, 'yqtrack_wp_api_key', $api_key);
                        } else {
                            delete_user_meta($user_id, 'yqtrack_wp_api_key');
                        }
                    }
                }
            }
            
            function display_tracking_info($order_id)
            {
                $this->display_order_yqtrack($order_id);
            }

            private function display_order_yqtrack($order_id)
            {
                    $tracking_items = $this->get_tracking_items( $order_id );  
                    if($tracking_items){
                        ?>
                        <section>
                        <h2><?php echo  _e( 'Tracking Information', 'woocommerce-yqtrack' ); ?></h2>
                        <style>
                        .yqtrack_tracking td{
                            font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
                            font-size:px; 
                            color: #737373 ; 
                            border: 1px solid #e4e4e4; 
                            padding: 12px;
                        }
                        .yqtrack_tracking th{
                            text-align: center; 
                            font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
                            font-size:px; 
                            color: #737373 ; 
                            border: 1px solid #e4e4e4; 
                            padding: 12px;
                        }
                        </style>
                        <table class="shop_table shop_table_responsive yqtrack_tracking" style="width: 100%;border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Carrier', 'woocommerce-yqtrack' ); ?></th>
                                    <th><?php _e( 'Tracking number', 'woocommerce-yqtrack' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody><?php
                            foreach ( $tracking_items as $item ) {
                                    ?><tr>
                                        <td style="text-align:center;">
                                        <?php echo esc_html( $item['tracking_provider_name'] ); ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php echo esc_html( $item['tracking_number'] ); ?>
                                        </td>
                                        <td style="text-align:center;">
                                                <?php
                                                $url = 'https://t.17track.net#nums='.$item['tracking_number'].'&fc='.$item['tracking_provider'];
                                                ?>
                                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button"><?php _e( 'Track', 'woocommerce-yqtrack' ); ?></a>
                            
                                        </td>
                                    </tr><?php
                                }
                            ?></tbody>
                        </table>
                        </section>
                    <?php
                    }
            }

            
            function email_display($order)
            {
                $this->display_tracking_info_email(get_order_id($order));
            }

            private function display_tracking_info_email($order_id)
            {
                $tracking_items = $this->get_tracking_items( $order_id );  
                if($tracking_items){
                    echo '<h2 style="color: #96588a; display: block; font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">Tracking Info</h2>';
                    echo '<table class="td" cellspacing="0" cellpadding="6" border="1" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;">
                    <thead>
                        <tr>
                            <th class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">Carrier</th>
                            <th class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">Tracking number</th>
                            <th class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;"> </th>
                        </tr>
                    </thead><tbody>';
                    
                    foreach ( $tracking_items as $item ) {
                        $url = 'https://t.17track.net#nums='.$item['tracking_number'].'&fc='.$item['tracking_provider'];
                        $track = '<a href="' . esc_url( $url ) . '" target="_blank" class="button">Track</a>';
                        echo '<tr>';
                        echo '<td class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">' . esc_html($item['tracking_provider_name']) .'</td>';
                        echo '<td class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">' . esc_html($item['tracking_number']) .'</td>';
                        echo '<td class="td" scope="col" style="color: #636363; border: 1px solid #e5e5e5; vertical-align: middle; padding: 12px; text-align: left;">' . $track .'</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table><br/>';
                }
            }

            function display_html_tracking_item_for_meta_box( $order_id, $item ) {
                $formatted = $this->get_formatted_tracking_item( $order_id, $item );			
                ?>
                <li>
                <div id="tracking-item-<?php echo esc_attr( $item['tracking_number'] ); ?>">
                    <p class="note_content">
                        <strong><?php echo esc_html( $formatted['formatted_tracking_provider'] ); ?></strong>
                        <br/>
                        <em><?php echo esc_html( $item['tracking_number'] ); ?></em> 
                    </p>
                    <p class="meta">
					<?php if ( strlen( $formatted['formatted_tracking_link'] ) > 0 ) : ?>
                                <?php 
                                $url = str_replace('%number%',$item['tracking_number'],$formatted['formatted_tracking_link']);
                                echo sprintf( '<a href="%s" target="_blank" title="' . esc_attr( __( 'Click here to track in 17TRACK', 'woocommerce-yqtrack' ) ) . '">' . __( 'Track', 'woocommerce-yqtrack' ) . '</a>', esc_url( $url ) ); ?>
                            <?php endif; ?>
                            <a href="#" class="delete-tracking" rel="<?php echo esc_attr( $item['tracking_number'] ); ?>"><?php _e( 'Delete', 'woocommerce' ); ?></a>                   
				</p>
                </div>
            </li>
                <?php
                }

                public function get_formatted_tracking_item( $order_id, $tracking_item ) {
                    $formatted = array();              
                    $formatted['formatted_tracking_provider'] = $tracking_item['tracking_provider_name'];
                    $formatted['formatted_tracking_link'] = '//t.17track.net#nums='.$tracking_item['tracking_number'].'&fc='.$tracking_item['tracking_provider'];
                    return $formatted;
                }


                public function get_tracking_items( $order_id) {		
                    $order = wc_get_order( $order_id );		
                    if($order){
                        $tracking_items = get_post_meta( $order_id, '_yqtrack_tracking_items', true );
                        if ( is_array( $tracking_items ) ) {
                            return $tracking_items;
                        } else {
                            $tracking_items= array();
                            $tracking_number  =  get_post_meta($order_id, '_yqtrack_tracking_number', true);
                            if($tracking_number <> '')
                            {                                
                                $tracking_item['tracking_number'] = $tracking_number;
                                $tracking_item['tracking_provider_name'] = get_post_meta($order_id, '_yqtrack_tracking_provider_name', true);
                                $tracking_item['tracking_provider'] = get_post_meta($order_id, '_yqtrack_tracking_provider', true);
                                $tracking_item['tracking_shipdate'] = get_post_meta($order_id, '_yqtrack_tracking_shipdate', true);
                                $tracking_items[] = $tracking_item;
                            }                            
                            return $tracking_items;
                        }
                    } else {
                        return array();
                    }
                }

        }

        if ( ! function_exists( 'get_order_id' ) ) {
            function get_order_id($order) {
                return (method_exists($order, 'get_id'))? $order->get_id() : $order->id;
            }
        }

        if (!function_exists('getYqTrackInstance')) {
            function getYqTrackInstance()
            {
                return _17TRACK::Instance();
            }
        }
    }
    
    $GLOBALS['yqtrack'] = getYqTrackInstance();

}
