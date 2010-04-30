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
	 * Re-creates the Cache-Control header string
	 *
	 * @param   array    cache_control parts to render
	 * @return  string
	 */
	public static function create_cache_control(array $cache_control)
	{
		$parts = array();
		foreach ($cache_control as $key => $value)
		{
			$parts[] = empty($value) ? $key : $key.'='.$value;
		}
		return implode(', ', $parts);
	}

	/**
	 * Parses the Cache-Control header and returns
	 * and array of settings
	 *
	 * @param   array    headers 
	 * @return  boolean|array
	 */
	public static function extract_cache_control(array $headers)
	{
		// If there is no Cache-Control header
		if ( ! isset($headers['Cache-Control']))
		{
			// return
			return FALSE;
		}

		// If no Cache-Control parts are detected
		if ( (bool) preg_match_all('/(?<key>[a-z\-]+)=?(?<value>\w+)?/', $headers['Cache-Control'], $matches))
		{
			// Return combined cache-control key/value pairs
			return array_combine($matches['key'], $matches['value']);
		}
		else
		{
			// Return
			return FALSE;
		}
	}

	/**
	 * @var  Kohana_Cache
	 */
	protected $_cache;

	/**
	 * Use nginx+memcache caching
	 * to prevent cached requests
	 * reaching Kohana if available
	 *
	 * @var  boolean
	 * @todo Make this a callback I think
	 */
	protected $_nginx_caching = FALSE;

	/**
	 * If the cache push fails, handle silently
	 *
	 * @var  boolean
	 */
	protected $_silent_cache_fail = TRUE;

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

		// Initialise the cache library (if required)
		if ($this->_cache === NULL)
		{
			$this->_cache = Cache::instance('vitesse');
		}

		// Check the cache engine type (Memcache works with nginx)
		if ( ! $this->_cache instanceof Kohana_Cache)
		{
			throw new Kohana_Request_Exception('Dependency injection failure. Unable to load required Memcache Cache driver.');
		}
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

		// Validate cached response
		$response = $this->validate($key);

		// If we get a valid response
		if ($response instanceof Kohana_Response)
		{
			// Return the response
			return $response;
		}

		// Get the response
		$response = parent::execute();

		// Try and cache the response and return it
		if (($_response = $this->_cache_response($key, $response) instanceof Kohana_Response)
		{
			return $_response;
		}

		return $response;
	}

	/**
	 * Validates a cached response to check
	 * it is still fresh
	 *
	 * @param   string   key 
	 * @return  bool|Kohana_Response
	 * @throws  Kohana_Request_Exception
	 */
	public function validate($key)
	{
		// Attempt to load the cache entry (hopefully, it should have expired in cache)
		try
		{
			// If there is no cached try
			if ( ! $response = $this->_cache->get($key))
			{
				// return
				return FALSE;
			}
		}
		catch (Kohana_Cache_Exception $e)
		{
			if ( ! $this->_silent_cache_fail)
			{
				throw new Kohana_Request_Exception('Failed to load cached page successfully using key : \':key\' with message : \':message\'', array(':message' => $e->getMessage(), ':key' => $key));
			}

			return FALSE;
		}

		// Now check the headers, just in case a stale entry remained in cache
		// past its expiry date (you never know!)

		// Get the Cache-Control Header
		$cache_control = Request::extract_cache_control($response->headers['Cache-Control']);

		// If the response has expired
		if (strtotime($cache_control['max-age']) > time())
		{
			// Remove this entry
			$this->_cache->delete($key);
			// return
			return FALSE;
		}

		// Return response
		return $response;
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
		return trim(Kohana::$base_url, '/').'/Vitesse'.$this->uri();
	}

	/**
	 * Caches a response to internal cache and
	 * if available, nginx+memcache
	 * 
	 * The secondary call may be made a callback
	 * in future.
	 *
	 * @param   string   key 
	 * @param   Kohana_Response $response 
	 * @return  bool
	 * @throws  Kohana_Request_Exception
	 */
	protected function _cache_response($key, Kohana_Response $response)
	{
		// If no caching headers are found
		if ( ! $cache_control = Request::extract_cache_control($response->headers))
		{
			// return
			return FALSE
		}
		// If no-cache or no-store is set
		else if (isset($cache_control['no-cache']) or isset($cache_control['no-store']))
		{
			// return
			return FALSE;
		}
		// If the response status is not Success
		else if ($response->status < 200 or $response->status > 299)
		{
			// return
			return FALSE;
		}

		// If no max-age or Expires values are set
		if ( ! isset($cache_control['max-age']))
		{
			// If there is no expires header
			if ( ! isset($response->headers['Expires']))
			{
				// return
				return FALSE;
			}

			// Calculate max-age cache control from Expires header
			$cache_control['max-age'] = strtotime($response->headers['Expires']) - time();

			// Check max age sanity
			if ($cache_control['max-age'] =< 0)
			{
				return FALSE;
			}
		}

		// Get time now
		$time = time();

		// If the expires header is not set
		if ( ! isset($response->headers['Expires']))
		{
			// Calculate expires header (DateTime would probably be better here - SdF)
			$expires = gmdate('D, d M Y H:i:s T', $time+$max_age);
		}

		$cache_control['max-age'] = $max_age;

		// Tell caches to check their validation
		$cache_control['must-revalidate'] = '';

		// Replace the headers with those that are not set
		$response->headers += array(
			'Cache-Control'  => Request::create_cache_control($cache_control),
			'Expires'        => $expires,
			'Last-Modified'  => gmdate('D, d M Y H:i:s T', $time),
			'Content-Length' => strlen((string) $response->body)
		);

		// Cache the response
		$this->_cache->set($key, $response, $max_age);

		try
		{
			// If no nginx available
			if ( ! $this->_nginx_caching)
			{
				// Return
				return TRUE;
			}
			else
			{
				// Cache for nginx and return
				return $this->_nginx_cache($key, $response, $max_age);
			}
		}
		catch (Kohana_Cache_Exception $e)
		{
			if ( ! $this->_silent_cache_fail)
			{
				throw new Kohana_Request_Exception('Failed to cache page successfully with message : :message', array(':message' => $e->getMessage()));
			}

			return FALSE;
		}
	}

	/**
	 * Push the cached page to Nginx/Memcache
	 *
	 * @param   string   key
	 * @param   Kohana_Response response
	 * @param   int      lifetime in seconds
	 * @return  boolean
	 */
	protected function _nginx_cache($key, Kohana_Response $response, $lifetime)
	{
		// Create empty buffer
		$buffer = '';

		// Generate HTTP header
		foreach ($response->headers as $key => $value)
		{
			$buffer .= "{$key}: {$value}\n";
		}

		// Generate cookies
		foreach ($response->cookies as $name => $value)
		{
			/**
			 * @todo Add full support for httpOnly and https
			 * restricted cookies
			 */
			$buffer .= 'Set-Cookie: '.$name.'='.Cookie::salt($name, $value['value']).'~'.$value['value'].
				'; expires: '.gmdate('D, d M Y H:i:s T', $value['expiration']).
				'; path: '.Cookie::$path.
				'; domain: '.Cookie::$domain."\n"; 
		}

		// Create HTTP body
		$buffer .= "\n".$response->body;

		// Cache full response
		Cache::instance('nginx+memcache')->set($key, $buffer, $lifetime);
	}
}
