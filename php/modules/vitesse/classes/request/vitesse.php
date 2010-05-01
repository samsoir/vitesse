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
	 * @var  Request_Cache
	 */
	public static $cache;

	public $cache_key_prefix = ':Vitesse:';

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
	 * @uses    [Request_Cache]
	 */
	public function execute()
	{
		// If there are no cache adaptors
		if ( ! Request::$cache->cache_adaptors)
		{
			// Get out of here
			return parent::execute();
		}

		// Create the cache key
		$key = $this->create_cache_key($this->uri());

		// Try and retrieve a cached response
		$response = Request::$cache->get($key);

		// If a response is returned
		if ($response instanceof Kohana_Response)
		{
			// Return the cached response
			return $response;
		}
		else
		{
			// Execute the contoller to get the response
			$response = parent::execute();
		}

		// If the response method is in the allow list
		if ( ! in_array($this->method, Request_Cache::$cache_methods_allow))
		{
			// return FALSE
			return $response;
		}
		// Else if the response shouldn't be cached
		else if ( ! Request_Cache::validate_set($response))
		{
			// return the non-cached result
			return $response;
		}

		// Set the response to cache
		Request::$cache->set($key, $response);

		// Return the response
		return $response;
	}

	/**
	 * Creates a cache key.
	 * Method can be overloaded in Request
	 * if different implementation is required.
	 * 
	 *    $this->_create_cache_key($this->uri());
	 *
	 * @param   string   uri to create key for
	 * @return  string
	 */
	public function create_cache_key($uri)
	{
		return $this->cache_key_prefix.$uri;
	}

}
