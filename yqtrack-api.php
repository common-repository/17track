<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if ( ! defined( 'ABSPATH' ) )exit;
class YqTrack_API
{	
	const VERSION = 1;
	public $server;
	public function __construct()
	{
		add_filter('query_vars', array($this, 'add_query_vars'), 0);
		add_action('init', array($this, 'add_endpoint'), 0);
		add_action('parse_request', array($this, 'handle_api_requests'), 0);
	}

	public function add_query_vars($vars)
	{
		$vars[] = 'yqtrack-api';
		$vars[] = 'yqtrack-api-route';
		return $vars;
	}
	
	public function add_endpoint()
	{
		global $wp_rewrite;
		add_rewrite_rule('^yqtrack-api\/v' . self::VERSION . '/?$', 'index.php?yqtrack-api-route=/', 'top');
		add_rewrite_rule('^yqtrack-api\/v' . self::VERSION . '(.*)?', 'index.php?yqtrack-api-route=$matches[1]', 'top');
		$wp_rewrite->flush_rules();
	}
	
	public function handle_api_requests()
	{
		global $wp;
		if (!empty($_GET['yqtrack-api']))
			$wp->query_vars['yqtrack-api'] = $_GET['yqtrack-api'];
		if (!empty($_GET['yqtrack-api-route']))
			$wp->query_vars['yqtrack-api-route'] = $_GET['yqtrack-api-route'];
		if (!empty($wp->query_vars['yqtrack-api-route'])) {
			define('YQTRACK_API_REQUEST', true);
			$this->includes();
			$this->server = new YqTrack_Server($wp->query_vars['yqtrack-api-route']);
			$this->register_resources($this->server);
			$this->server->serve_request();
			exit;
		}
	}
	
	private function includes()
	{
		include_once('api/yqtrack-server.php');
		include_once('api/yqtrack-json-handler.php');
		include_once('api/yqtrack-authentication.php');
		$this->authentication = new YqTrack_Authentication();
		include_once('api/yqtrack-resource.php');
		include_once('api/yqtrack-orders.php');
	}
	
	public function register_resources($server)
	{
		$api_classes = apply_filters('yqtrack_api_classes',
			array(
				'YqTrack_Orders',
			)
		);
		foreach ($api_classes as $api_class) {
			$this->$api_class = new $api_class($server);
		}
	}
}
