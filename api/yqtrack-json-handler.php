<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if (!defined('ABSPATH')) exit;
class YqTrack_JSON_Handler
{

	public function get_content_type()
	{
		return 'application/json; charset=' . get_option('blog_charset');
	}
	
	public function parse_body($body)
	{
		return json_decode($body, true);
	}
	
	public function generate_response($data)
	{
		if (isset($_GET['_jsonp'])) {
			if (!apply_filters('yqtrack_api_jsonp_enabled', true)) {
				WC()->api->server->send_status(400);
				$data = array(array('code' => 'yqtrack_api_jsonp_disabled', 'message' => __('JSONP support is disabled on this site', 'yqtrack')));
			}
			if (preg_match('/\W/', $_GET['_jsonp'])) {

				WC()->api->server->send_status(400);

				$data = array(array('code' => 'yqtrack_api_jsonp_callback_invalid', __('The JSONP callback function is invalid', 'yqtrack')));
			}
			return $_GET['_jsonp'] . '(' . json_encode($data) . ')';
		}
		return json_encode($data);
	}
}
