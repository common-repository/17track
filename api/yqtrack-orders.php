<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if (!defined('ABSPATH')) exit; 
class YqTrack_Orders extends YqTrack_Resource
{	
	protected $base = '/orders';
	protected $base2 = '/order';	
	public function register_routes($routes)
	{
		$routes[$this->base] = array(
			array(array($this, 'get_orders'), YqTrack_Server::READABLE),
		);
		$routes[$this->base . '/count'] = array(
			array(array($this, 'get_orders_count'), YqTrack_Server::READABLE),
		);
		$routes[$this->base . '/(?P<id>\d+)'] = array(
			array(array($this, 'get_order'), YqTrack_Server::READABLE),
		);
		$routes[$this->base2 . '/(?P<id>\d+)'] = array(
			array(array($this, 'edit_order'), YqTrack_Server::EDITABLE | YqTrack_Server::ACCEPT_DATA),
		);
		return $routes;
	}
	
	public function get_orders($fields = null, 
			$created_at_min = null, 
			$created_at_max = null, 
			$updated_at_min = null, 
			$updated_at_max = null, 
			$limit = null, 
			$offset = null, 
			$status = null)
	{		
		$filter = array();

		if (!empty($status))
			$filter['status'] = $status;

		if (!empty($created_at_min))
				$filter['created_at_min'] = $created_at_min;

		if (!empty($created_at_max))
			$filter['created_at_max'] = $created_at_max;
					
		if (!empty($updated_at_min))
			$filter['updated_at_min'] = $updated_at_min;

		if (!empty($updated_at_max))
			$filter['updated_at_max'] = $updated_at_max;

		if (!empty($limit))
			$filter['limit'] = $limit;

		if (!empty($offset))
			$filter['offset'] = $offset;

		$query = $this->query_orders($filter);

		$orders = array();

		foreach ($query->posts as $order_id) {

			if (!$this->is_readable($order_id))
				continue;

			$orders[] = current($this->get_order($order_id, $fields));
		}
		
		$total = $query->found_posts;
		$total_pages = $query->max_num_pages;

		return array('orders' => $orders,'total' => $total,'totalpages' => $total_pages);
	}
	
	public function get_order($id, $fields = null)
	{
		$id = $this->validate_request($id, 'shop_order', 'read');

		if (is_wp_error($id))
			return $id;

		$order = new WC_Order($id);

		$order_post = get_post($id);

		$order_data = array(
			'id' => get_order_id($order),
			'order_number' => $order->get_order_number(),
			'created_at' => $this->server->format_datetime($order_post->post_date_gmt),
			'updated_at' => $this->server->format_datetime($order_post->post_modified_gmt),
			'billing_address' => array(
				'first_name' => order_post_meta_getter($order, 'billing_first_name'),
				'last_name' => order_post_meta_getter($order, 'billing_last_name'),
				'company' => order_post_meta_getter($order, 'billing_company'),
				'address_1' => order_post_meta_getter($order, 'billing_address_1'),
				'address_2' => order_post_meta_getter($order, 'billing_address_2'),
				'city' => order_post_meta_getter($order, 'billing_city'),
				'state' => order_post_meta_getter($order, 'billing_state'),
				'postcode' => order_post_meta_getter($order, 'billing_postcode'),
				'country' => order_post_meta_getter($order,'billing_country'),
				'email' => order_post_meta_getter($order,'billing_email'),
				'phone' => order_post_meta_getter($order,'billing_phone'),
			),
			'shipping_address' => array(
				'first_name' => order_post_meta_getter($order,'shipping_first_name'),
				'last_name' => order_post_meta_getter($order,'shipping_last_name'),
				'company' => order_post_meta_getter($order,'shipping_company'),
				'address_1' => order_post_meta_getter($order,'shipping_address_1'),
				'address_2' => order_post_meta_getter($order,'shipping_address_2'),
				'city' => order_post_meta_getter($order,'shipping_city'),
				'state' => order_post_meta_getter($order,'shipping_state'),
				'postcode' => order_post_meta_getter($order,'shipping_postcode'),
				'country' => order_post_meta_getter($order,'shipping_country'),
			),
			'note' => (method_exists($order, 'get_customer_note'))? $order->get_customer_note() : $order->customer_note,
			'line_items' => array(),
			'status' => $order_post->post_status,
		);

		$total = 0;
		foreach ($order->get_items() as $item_id => $item) {
			$price = wc_format_decimal($order->get_item_total($item), 2);
			if($price == 0)
			{
				$price = wc_format_decimal(wc_format_decimal($order->get_line_subtotal($item), 2)/(int)$item['qty'],2);
			}
			$order_data['line_items'][] = array(
				'id' => $item_id,
				'subtotal' => wc_format_decimal($order->get_line_subtotal($item), 2),
				'total' => wc_format_decimal($order->get_line_total($item), 2),
				'total_tax' => wc_format_decimal($order->get_line_tax($item), 2),
				'price' => $price,
				'quantity' => (int)$item['qty'],
				'name' => $item['name'],
				'sku' => '',
			);

			$total = $total + wc_format_decimal($order->get_line_total($item), 2);
		}
		//
		$order_data["total"] = wc_format_decimal($total,2);
		$options = get_option('yqtrack_option_name');
		$plugin = $options['plugin'];
		if ($plugin == '17TRACK') {
			$tracking_items = order_post_meta_getter($order, 'yqtrack_tracking_items');
			if( is_array($tracking_items))
			{				
				foreach ( $tracking_items as $item ) {
					$order_data['yqtrack']['woocommerce']['trackings'][] = array(
						'tracking_provider' => $item["tracking_provider"],
						'tracking_number' => $item["tracking_number"],
						'tracking_ship_date' => $item["tracking_shipdate"],
						);
				}
			}
			else
			{
				$order_data['yqtrack']['woocommerce']['trackings'][] = array(
				'tracking_provider' => order_post_meta_getter($order, 'yqtrack_tracking_provider'),
				'tracking_number' => order_post_meta_getter($order, 'yqtrack_tracking_number'),
				'tracking_ship_date' => order_post_meta_getter($order, 'yqtrack_tracking_shipdate'),
				);
			}
		}
		return array('order' => apply_filters('yqtrack_api_order_response', $order_data, $order, $fields, $this->server));
	}
	
	public function get_orders_count($status = null, $filter = array())
	{
		if (!empty($status))
			$filter['status'] = $status;

		$query = $this->query_orders($filter);

		if (!current_user_can('read_private_shop_orders'))
			return new WP_Error('yqtrack_api_user_cannot_read_orders_count', __('You do not have permission to read the orders count', 'yqtrack'), array('status' => 401));

		return array('count' => (int)$query->found_posts);
	}
	

	public function edit_order($id, $status, $note)
	{

		$id = $this->validate_request($id, 'shop_order', 'edit');

		if (is_wp_error($id))
			return $id;

		$order = new WC_Order($id);

		if (!empty($status)) {

			$order->update_status('completed', isset($note) ? $note : '');
		}

		return $this->get_order($id);
	}
	
	private function query_orders($args)
	{
		function yqtrack_wpbo_get_woo_version_number()
		{
			if (!function_exists('get_plugins'))
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$plugin_folder = get_plugins('/' . 'woocommerce');
			$plugin_file = 'woocommerce.php';
			if (isset($plugin_folder[$plugin_file]['Version'])) {
				return $plugin_folder[$plugin_file]['Version'];

			} else {
				return NULL;
			}
		}

		$woo_version = yqtrack_wpbo_get_woo_version_number();

		if ($woo_version >= 2.2) {
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				'post_status' => array_keys(wc_get_order_statuses())
			);
		} else {
			$query_args = array(
				'fields' => 'ids',
				'post_type' => 'shop_order',
				'post_status' => 'publish',
			);
		}
		if (!empty($args['status'])) {

			$statuses = explode(',', $args['status']);

			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'shop_order_status',
					'field' => 'slug',
					'terms' => $statuses,
				),
			);

			unset($args['status']);
		}

		$query_args = $this->merge_query_args($query_args, $args);

        return new WP_Query($query_args);
	}
	
	private function get_order_subtotal($order)
	{
		$subtotal = 0;
		foreach ($order->get_items() as $item) {

			$subtotal += (isset($item['line_subtotal'])) ? $item['line_subtotal'] : 0;
		}
		return $subtotal;
	}
}

if ( ! function_exists( 'get_order_id' ) ) {
	function get_order_id($order) {
		return (method_exists($order, 'get_id'))? $order->get_id() : $order->id;
	}
}

if ( ! function_exists( 'order_post_meta_getter' ) ) {
	function order_post_meta_getter($order, $attr) {
		$meta = get_post_meta(get_order_id($order), '_'. $attr, true);
		return $meta;
	}
}

