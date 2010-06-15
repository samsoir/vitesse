<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Request] Cache class. This class manages the cache decorators attached 
 * to it, posting and getting requests from the adaptor(s) as required. All 
 * executions iterate over the attached adaptors in the order they are
 * attached.
 * 
 * *Vitesse* adopts the [decorator pattern](http://en.wikipedia.org/wiki/Decorator_pattern)
 * to provide an interface between the request class and [Kohana_Cache].
 * 
 * The [Request_Cache] class initialises itself within the `init.php` 
 * bootstrap within the Vitesse module. It is possible to move the following 
 * initialisation code into the application bootstrap:
 *
 *     // Setup the Request_Cache object and attach a null and file decorator
 *     Request::$cache = Request_Cache::instance()
 *          ->attach(Request_Cache_Decorator::instance('null'))
 *          ->attach(Request_Cache_Decorator::instance('file'));
 *
 * The following [Request_Cache_Decorator] classes are provided:
 * 
 * *  **null** for testing and logging
 * *  **file** based caching
 * *  **memcache** for [Memcache](http://php.net/manual/en/book.memcache.php)
 * *  **nginx** for [nginx+memcache caching](http://wiki.nginx.org/NginxHttpMemcachedModule)
 * *  **apc** for [PHP APC](http://php.net/manual/en/book.apc.php)
 * 
 * **NOTE:** The [Request_Cache_Decorator_Nginx] uses the `memcache` cache driver, however
 * the decorator performs an additional parse of the [Kohana_Response] object
 * to render the response as a complete HTTP response, headers included.
 * 
 * @package    Vitesse
 * @category   Cache
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Request_Cache {

	/**
	 * @var   array  http methods that are allowed to be cached
	 */
	public static $cache_methods_allow = array('GET');

	/**
	 * Creates a new singleton of this class. This method should always be
	 * used to maintain the singleton pattern.
	 *
	 *     // Correct instantiation method
	 *     Request::$cache = Request_Cache::instance();
	 *     
	 *     // Incorrect usage
	 *     Request::$cache = new Request_Cache;
	 *
	 * @return  Request_Cache
	 */
	public static function instance()
	{
		static $instance;

		($instance === NULL) and $instance = new Request_Cache;

		return $instance;
	}

	/**
	 * Generates a [Cache-Control HTTP](http://en.wikipedia.org/wiki/List_of_HTTP_headers)
	 * header based on the supplied array.
	 * 
	 *     // Set the cache control headers you want to use
	 *     $cache_control = array(
	 *         'max-age'          => 3600,
	 *         'must-revalidate'  => NULL,
	 *         'public'           => NULL
	 *     );
	 *     
	 *     // Create the cache control header, creates :
	 *     // Cache-Control: max-age=3600, must-revalidate, public
	 *     $response->headers['Cache-Control'] = Request_Cache::create_cache_control($cache_control);
	 *
	 * @param   array    cache_control parts to render
	 * @return  string
	 */
	public static function create_cache_control(array $cache_control)
	{
		// Create a buffer
		$parts = array();

		// Foreach cache control entry
		foreach ($cache_control as $key => $value)
		{
			// Create a cache control fragment
			$parts[] = empty($value) ? $key : $key.'='.$value;
		}
		// Return the rendered parts
		return implode(', ', $parts);
	}

	/**
	 * Parses the Cache-Control header and returning an array representation of the Cache-Control
	 * header.
	 *
	 *     // Create the cache control header
	 *     $response->headers['Cache-Control'] = 'max-age=3600, must-revalidate, public';
	 *     
	 *     // Parse the cache control header
	 *     if($cache_control = Request_Cache::parse_cache_control($response->headers))
	 *     {
	 *          // Cache-Control header was found
	 *          $maxage = $cache_control['max-age'];
	 *     }
	 *
	 * @param   array    headers 
	 * @return  boolean|array
	 */
	public static function parse_cache_control(array $headers)
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
	 * Validates a [Kohana_Response] before caching. This method tests the response
	 * to ensure it should be cached, returning a boolean response.
	 *
	 * 1. Checks for 2xx HTTP status, redirects, errors or misses should not be cached
	 * 2. Tests for `no-cache` or `no-store` Cache-Control headers
	 * 3. Checks for `max-age` Cache-Control or `Expires` headers
	 * 4. Ensures the expiry is sensible (not negative)
	 *
	 * This method should be used ahead of caching the response.
	 *
	 *      // If the response is valid for caching
	 *      if (Request_Cache::validate_set($response))
	 *      {
	 *           // Set the response to the cache decorators
	 *           Request::$cache->set('foo', $response);
	 *      }
	 *
	 * @param   Kohana_Response  response
	 * @return  boolean
	 */
	public static function validate_set(Kohana_Response $response)
	{
		// If the response status is not Success
		if ($response->status < 200 or $response->status > 299)
		{
			// return FALSE
			return FALSE;
		}

		// Read the cache control headers
		$cache_control = Request_Cache::parse_cache_control($response->headers);

		// If there are no cache headers, or no-cache and/or no-store is set
		if ( ! $cache_control or isset($cache_control['no-cache']) or isset($cache_control['no-store']))
		{
			// return FALSE
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
			if ($cache_control['max-age'] <= 0)
			{
				return FALSE;
			}
		}
		else
		{
			// return TRUE
			return TRUE;
		}
	}

	/**
	 * Validates a cached response to check it is still fresh and available
	 * to the client. All validation is based on the `max-age` Cache-Control
	 * header.
	 *
	 *     // Get a response from the cache
	 *     $response = Request::$cache->get('foo');
	 * 
	 *     // Test the response
	 *     if (Request_Cache::validate_get($response))
	 *     {
	 *          // Return the valid cached response
	 *          return $response;
	 *     }
	 *
	 * @param   Kohana_Response resposne
	 * @return  boolean
	 */
	public static function validate_get(Kohana_Response $response)
	{
		// Get the Cache-Control Header
		$cache_control = Request_Cache::parse_cache_control((array) $response->headers);

		// If the response has expired
		if (($cache_control['max-age']+time()) < time())
		{
			// Remove this entry
			Request_Cache::delete($key);
			// return false
			return FALSE;
		}
		// Return true
		return TRUE;
	}

	/**
	 * @var   array  Request_Cache_Decorator instances
	 */
	public $cache_decorators = array();

	/**
	 * @var   boolean  If the caching fails, handle gracefully silently suppressing the Exception
	 */
	public $silent_cache_fail = TRUE;

	/**
	 * Do not use the standard constructor. This class maintains the singleton pattern
	 * and should be instantiated using the following:
	 * 
	 *     Request::$cache = Request_Cache::instance();
	 */
	final private function __construct() {}

	/**
	 * Attaches a [Request_Cache_Decorator] to the `Request_Cache` singleton.
	 *
	 *     // Attach a null decorator to the cache object
	 *     Request::$cache->attach(Request_Cache_Decorator::instance('null'));
	 *     
	 *     // Attach multiple decorators using chaining
	 *     Request::$cache->attach(Request_Cache_Decorator::instance('file'))
	 *          ->attach(Request_Cache_Decorator::instance('nginx'));
	 *
	 * @param   Request_Cache_Decorator  attaches a decorator to Request_Cache
	 * @return  self
	 */
	public function attach(Request_Cache_Decorator $decorator)
	{
		$this->cache_decorators["{$decorator}"] = $decorator;
		return $this;
	}

	/**
	 * Detaches a [Request_Cache_Decorator] from the `Request_Cache` singleton.
	 *
	 *     // Detach a null decorator from the cache object
	 *     Request::$cache->detach(Request_Cache_Decorator::instance('null'));
	 *     
	 *     // Detach multiple decorators using chaining
	 *     Request::$cache->detach(Request_Cache_Decorator::instance('file'))
	 *          ->detach(Request_Cache_Decorator::instance('nginx'));
	 *
	 * @param   Request_Cache_Decorator  detaches a decorator to Request_Cache
	 * @return  self
	 */
	public function detach(Request_Cache_Decorator $decorator)
	{
		unset($this->cache_decorators["{$decorator}"]);
		return $this;
	}

	/**
	 * Sets a [Kohana_Response] object to all of the cache decorators attached
	 * to this singleton. The response object will be applied to each decorator
	 * in the order that they are attached.
	 * 
	 *     // Get a response from the request
	 *     $response = $request->execute();
	 *     
	 *     // Set the response to cache
	 *     if (Request::$cache->set('foo', $response))
	 *     {
	 *           // Response was cached
	 *     }
	 *
	 * @param   string   key to use for this entry
	 * @param   Kohana_Response  response object to cache
	 * @return  boolean
	 */
	public function set($key, Kohana_Response $response)
	{
		// If there are no cache decorators
		if ( ! $this->cache_decorators)
		{
			// return
			return FALSE;
		}

		// Foreach cache decorator
		foreach ($this->cache_decorators as $decorator)
		{
			try
			{
				// Set the resposne
				$decorator->set($key, $response);
			}
			catch (Exception $e)
			{
				// If cache exceptions should be thrown
				if ( ! $this->silent_cache_fail)
				{
					// Throw the exception
					throw $e;
				}
			}
		}

		// return
		return TRUE;
	}

	/**
	 * Gets a response from the cache decorators based on `$key`. [Request_Cache] will
	 * iterate through each attached decorator searching for a hit. When a response
	 * is found, if it is still fresh it will be returned.
	 * 
	 *     // Search for a response
	 *     if (($response = Request::$cache->get('foo')) instanceof Kohana_Response)
	 *     {
	 *          // Return the response
	 *          return $response;
	 *     }
	 *
	 * @param   string   key of the response to fetch from cache
	 * @return  boolean|Kohana_Response
	 */
	public function get($key)
	{
		// If there are no decorators
		if ( ! $this->cache_decorators)
		{
			// return
			return FALSE;
		}

		// Foreach decorator
		foreach ($this->cache_decorators as $decorator)
		{
			try
			{
				// Try and load the response by key
				$cached_response = $decorator->get($key);

				// If the cache response is valid
				if ($cached_response instanceof Kohana_Response and Request_Cache::validate_get($cached_response))
				{
					// return the response
					return $cached_response;
				}
			}
			catch (Exception $e)
			{
				// If cache exceptions should be thrown
				if ( ! $this->silent_cache_fail)
				{
					// Throw the exception
					throw $e;
				}
			}
		}

		// return false if no response was found
		return FALSE;
	}

	/**
	 * Deletes a response based on `$key` from all cache decorators attached
	 * to this object.
	 *
	 *     // Delete a response from the decorators
	 *     Request::$cache->delete('foo');
	 *
	 * @param   string   key to delete from decorators
	 * @return  boolean
	 */
	public function delete($key)
	{
		// If there are no decorators
		if ( ! $this->cache_decorators)
		{
			// return
			return FALSE;
		}

		// Foreach cache decorator
		foreach ($this->cache_decorators as $decorator)
		{
			try
			{
				// Delete the key from the decorator
				$decorator->delete($key);
			}
			catch (Exception $e)
			{
				// If cache exceptions should be thrown
				if ( ! $this->silent_cache_fail)
				{
					// Throw the exception
					throw $e;
				}
			}
		}

		// return
		return TRUE;
	}

	/**
	 * Deletes all cached responses from all attached decorators. The `delete_all()` method
	 * will also remove all other cached values within each [Kohana_Cache] engine that is
	 * attached through a decorator. This may have undesired effects. To avoid this issue,
	 * it is best to dedicate one cache engine solely to response caching.
	 *
	 *     // Delete all responses from all decorators
	 *     Request::$cache->delete_all();
	 *
	 * @return  boolean
	 */
	public function delete_all()
	{
		// If there are no decorators
		if ( ! $this->cache_decorators)
		{
			// return
			return FALSE;
		}

		// Foreach attached decorator
		foreach ($this->cache_decorators as $decorator)
		{
			try
			{
				// Delete all cache entries
				$decorator->delete_all();
			}
			catch (Exception $e)
			{
				// If cache exceptions should be thrown
				if ( ! $this->silent_cache_fail)
				{
					// Throw exception
					throw $e;
				}
			}
		}

		// return
		return TRUE;
	}
}