<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if (!defined('ABSPATH')) exit; 
if (!function_exists('getallheaders')) {
	function getallheaders()
	{
		$headers = '';
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}

class YqTrack_Authentication
{
	
	public function __construct()
	{
		add_filter('yqtrack_api_check_authentication', array($this, 'authenticate'), 0);
	}
	
	public function authenticate($user)
	{
		if ('/' === getYqTrackInstance()->api->server->path)
			return new WP_User(0);

		try {
			$user = $this->perform_authentication();

		} catch (Exception $e) {

			$user = new WP_Error('yqtrack_api_authentication_error', $e->getMessage(), array('status' => $e->getCode()));
		}

		return $user;
	}

	private function perform_authentication()
	{
		$yqkey = '';
		if (isset($_GET['yqkey'])) {
			$yqkey=$_GET['yqkey'];
		}
		if(!empty($yqkey))
		{
			$api_key = $yqkey;
		}else{
			$headers = getallheaders();
			$headers = json_decode(json_encode($headers), true);
			$key = 'YQTRACK_WP_KEY';
			$key1 = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
			$key2 = 'YQTRACK-WP-KEY';
			if (!empty($headers[$key])) {
				$api_key = $headers[$key];
			} else if (!empty($headers[$key1])){
				$api_key = $headers[$key1];
			} else if (!empty($headers[$key2])){
				$api_key = $headers[$key2];		
			} else {
				throw new Exception(__('YqTrack\'s WordPress Key is missing', 'yqtrack'), 404);
			}
		}

		$user = $this->get_user_by_api_key($api_key);

		return $user;

	}
	
	private function get_user_by_api_key($api_key)
	{

		$user_query = new WP_User_Query(
			array(
				'meta_key' => 'yqtrack_wp_api_key',
				'meta_value' => $api_key,
			)
		);

		$users = $user_query->get_results();

		if (empty($users[0]))
			throw new Exception(__('YqTrack\'s WordPress API Key is invalid', 'yqtrack'), 401);

		return $users[0];
	}
}
