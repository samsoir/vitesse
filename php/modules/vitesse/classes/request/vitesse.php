<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Request and response wrapper.
 * 
 * Request_Vitesse adds full caching support to the
 * request class. If using nginx + memcached combination
 * cached pages can be pushed to memcached to prevent
 * Kohana from being hit.
 * 
 * Request and response wrapper. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Request_Vitesse extends Kohana_Request {

	/**
	 * @var  Kohana_Cache
	 */
	public $cache;

	/**
	 * Use nginx+memcache caching
	 * to prevent cached requests
	 * reaching Kohana if available
	 *
	 * @var  boolean
	 */
	public $nginx_caching = FALSE;

	/**
	 * This is presently not used, but may be used
	 * to tailor memcached entries specifically for
	 * nginx-memcache proxy_pass routines
	 *
	 * @var  boolean
	 */
	protected $_nginx_present;

	/**
	 * Creates a new request object for the given URI. New requests should be
	 * created using the [Request::instance] or [Request::factory] methods.
	 *
	 *     $request = new Request($uri);
	 *
	 * @param   string  URI of the request
	 * @param   config  settings for this request object
	 * @return  void
	 * @throws  Kohana_Request_Exception
	 * @uses    Route::all
	 * @uses    Route::matches
	 */
	public function __construct($uri, array $config = array())
	{
		// Run the parent constructor
		parent::__construct($uri, $config);

		// Try to load the Vitesse configuration group
		$this->cache = Cache::instance('vitesse');

		// Check the cache engine type (Memcache works with nginx)
		if ( ! $this->cache instanceof Kohana_Cache)
		{
			throw new Kohana_Request_Exception('Dependency injection failure. Unable to load required Memcache Cache driver.');
		}

		// Test for nginx. Reset this value (incase set elsewhere or using D.I.)
		// (This is not reliable, as it can be overridden)
		$this->_nginx_present = preg_match('/nginx\//', $_SERVER['SERVER_SOFTWARE']);
	}

	/**
	 * Processes the request, executing the controller action that handles this
	 * request, determined by the [Route].
	 *
	 * 1. Before the controller action is called, the [Controller::before] method
	 * will be called.
	 * 2. Next the controller action will be called.
	 * 3. After the controller action is called, the [Controller::after] method
	 * will be called.
	 *
	 * By default, the output from the controller is captured and returned, and
	 * no headers are sent.
	 *
	 *     $request->execute();
	 *
	 * @return  Kohana_Response
	 * @throws  Kohana_Exception
	 * @uses    [Kohana::$profiling]
	 * @uses    [Profiler]
	 */
	public function execute()
	{
		// Check request type
		$cache_allow = 'GET' === $this->method;

		// We don't want to cache POST, PUT or DELETE requests
		if ( ! $cache_allow)
		{
			// Return the response
			return parent::execute();
		}

		// Create the cache key
		$key = $this->_create_cache_key();

		// Attempt to load cache entry
		$response = $this->_cache->get($key);

		if (NULL !== $response)
		{
			// Check the response
		}

		// Get the response
		$response = parent::execute();
	}

	/**
	 * Creates a cache key.
	 * Method can be overloaded in Request
	 * if different implementation is required
	 * 
	 * NGINX: This key must match the configuration
	 * setting within your nginx server definition
	 * if you want to bypass Kohana completely:
	 * 
	 * @example
	 * 
	 *     set $memcached_key = domain.tld/$uri
	 *
	 * @return  string
	 */
	protected function _create_cache_key()
	{
		return trim(Kohana::$base_url, '/').$this->uri();
	}
}
