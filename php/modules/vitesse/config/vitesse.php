<?php defined('SYSPATH') or die('No direct script access.');

return array
(
	'default_adaptor'            => 'null',
	'cache_configuration_groups' => array
	(
		'Request_Cache_Adaptor_Apc'      => 'apc',
		'Request_Cache_Adaptor_Memcache' => 'memcache',
		'Request_Cache_Adaptor_Nginx'    => 'nginx+memcache',
		'Request_Cache_Adaptor_File'     => 'file',
	)
);