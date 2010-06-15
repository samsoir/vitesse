<?php defined('SYSPATH') or die('No direct script access.');

return array
(
	'cache_configuration_groups' => array
	(
		'Request_Cache_Decorator_Apc'      => 'apc',
		'Request_Cache_Decorator_Memcache' => 'memcache',
		'Request_Cache_Decorator_Nginx'    => 'memcache',
		'Request_Cache_Decorator_File'     => 'file',
	)
);