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
 * @package    Vitesse
 * @category   Request
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Request_Vitesse extends Kohana_Request implements Serializable {

	/**
	 * @var  Request_Cache
	 */
	public static $cache;

	/**
	 * @var  string
	 */
	public $cache_key_prefix = ':Vitesse:';

	/**
	 * Processes the request, checking for attached
	 * Cache decorators. If decorators are found,
	 * the request performs the following tasks.
	 * 
	 * 1. Checks for existing cached response, returning valid hits
	 * 2. Runs the Kohana_Request::execute() logic to create a response
	 * 3. Caches the response using a decorator if valid
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
		if ( ! Request::$cache->cache_decorators)
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

	/**
	 * Serializes the object to json - handy if you
	 * need to pass the response data to other
	 * systems
	 *
	 * @param   array    array of data to serialize
	 * @return  string
	 * @throws  Kohana_Exception
	 */
	public function serialize(array $toSerialize = array())
	{
		// Serialize the class properties
		$toSerialize += array
		(
			'method'                 => $this->method,
			'route'                  => $this->route,
			'status'                 => $this->status,
			'response'               => serialize($this->response),
			'headers'                => $this->headers,
			'body'                   => $this->body,
			'cookies'                => $this->cookies,
			'directory'              => $this->directory,
			'controller'             => $this->controller,
			'action'                 => $this->action,
			'uri'                    => $this->uri,
			'get'                    => $this->get,
			'post'                   => $this->post,
			'is_ajax'                => $this->is_ajax,
			'_previous_environment'  => $this->_previous_environment,
			'_external'              => $this->_external
		);

		$string = json_encode($toSerialize);

		if (is_string($string))
		{
			return $string;
		}
		else
		{
			throw new Kohana_Exception('Unable to correctly encode object to json');
		}
	}

	/**
	 * JSON encoded object
	 *
	 * @param   string   json encoded object
	 * @return  bool
	 * @throws  Kohana_Exception
	 */
	public function unserialize($string)
	{
		// Unserialise object
		$unserialized = json_decode($string);

		// If failed
		if ($unserialized === NULL)
		{
			// Throw exception
			throw new Kohana_Exception('Unable to correctly decode object from json');
		}

		// Foreach key/value pair
		foreach ($unserialized as $key => $value)
		{
			// If it belongs here
			if (property_exists($this, $key))
			{
				if ($key === 'response')
				{
					$value = unserialize($value);
				}
				else if ($key === 'headers')
				{
					$value = (array) $value;
				}

				// Apply it
				$this->$key = $value;
			}
		}

		return TRUE;
	}
}
