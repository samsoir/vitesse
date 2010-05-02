<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Applies Request_Cache_Decorators to the Kohana_Request
 * class. Multiple cache decorators can be applied and are
 * executed in the order that they are applied. This code
 * can be moved to the application/bootstrap.php file.
 */
Request::$cache = Request_Cache::instance()
	->attach(Request_Cache_Decorator::instance('null'))
	->attach(Request_Cache_Decorator::instance('nginx'));