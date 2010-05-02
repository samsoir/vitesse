<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Kohana_Cache] decorator interfacing with [Request_Cache] singleton. Each cache
 * type must have its own decorator. Multiple cache engines of the same type can
 * be supported by defining unique decorators for each; see [Request_Cache_Decorator_Memcache] and
 * [Request_Cache_Decorator_Nginx] for example.
 *
 * All decorators must be instantiated using the `Request_Cache_Decorator::instance('foo')` method.
 * The index passed to the `instance()` method will be prefixed with `Request_Cache_Decorator_<foo>`
 * and then loaded.
 *
 *     // Create a new Nginx cache decorator
 *     $nginx_decorator = Request_Cache_Decorator::instance('nginx');
 *
 *     if ($nginx_decorator instanceof Request_Cache_Decorator_Nginx)
 *     {
 *           // Add this decorator to Request_Cache
 *           Request::$cache->add($nginx_decorator);
 *     }
 *
 * Decorators must extend this class and implement the `_init()` method, which instantiates a
 * new [Kohana_Cache] instance. This enables developers to define their own decorator rules and
 * function.
 * 
 * @package    Vitesse
 * @category   Decorator
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Request_Cache_Decorator_File extends Request_Cache_Decorator {

	/**
	 * Initialises the Kohana_Cache class for
	 * this adaptor
	 *
	 * @return  void
	 */
	protected function _init()
	{
		$this->_cache = Cache::instance($this->_config[__CLASS__]);
	}
}