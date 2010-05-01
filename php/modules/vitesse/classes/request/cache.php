<?php defined('SYSPATH') or die('No direct script access.');

class Request_Cache {

	public static $cache_methods_allow = array('GET');

	public static function instance()
	{
		static $instance;

		($instance === NULL) and $instance = new Request_Cache;

		return $instance;
	}

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
	 * Validates a response to set to cache.
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
	 * Validates a retreived cached response to check
	 * it is still fresh.
	 * 
	 *     Request_Cache::validate_get($response);
	 *
	 * @param   Kohana_Response resposne
	 * @return  boolean
	 */
	public static function validate_get(Kohana_Response $response)
	{
		// Get the Cache-Control Header
		$cache_control = Request_Cache::parse_cache_control($response->headers);

		// If the response has expired
		if ($cache_control['max-age']+time() < time())
		{
			// Remove this entry
			Request_Cache::delete($key);
			// return false
			return FALSE;
		}

		// Return true
		return TRUE;
	}

	public $cache_adaptors = array();

	public $silent_cache_fail = TRUE;

	/**
	 * Maintains singleton pattern.
	 */
	final private function __construct() {}

	public function attach(Request_Cache_Adaptor $adaptor)
	{
		$this->cache_adaptors["{$adaptor}"] = $adaptor;
		return $this;
	}

	public function detach(Request_Cache_Adaptor $adaptor)
	{
		unset($this->cache_adaptors["{$adaptor}"]);
		return $this;
	}

	public function set($key, Kohana_Response $response)
	{
		if ( ! $this->cache_adaptors)
		{
			return FALSE;
		}

		foreach ($this->cache_adaptors as $adaptor)
		{
			try
			{
				$adaptor->set($key, $response, $lifetime);
			}
			catch (Exception $e)
			{
				if ( ! $this->silent_cache_fail)
				{
					throw $e;
				}
			}
		}

		return TRUE;
	}

	public function get($key)
	{
		if ( ! $this->cache_adaptors)
		{
			return FALSE;
		}

		foreach ($this->cache_adaptors as $adaptor)
		{
			try
			{
				$cached_response = $adaptor->get($key);

				if ($cached_response instanceof Kohana_Response and Request_Cache::validate_get($cached_response))
				{
					return $cached_response;
				}
			}
			catch (Exception $e)
			{
				if ( ! $this->silent_cache_fail)
				{
					throw $e;
				}
			}
		}

		return FALSE;
	}

	public function delete($key)
	{
		if ( ! $this->cache_adaptors)
		{
			return FALSE;
		}

		foreach ($this->cache_adaptors as $adaptor)
		{
			try
			{
				$adaptor->delete($key);
			}
			catch (Exception $e)
			{
				if ( ! $this->silent_cache_fail)
				{
					throw $e;
				}
			}
		}

		return TRUE;
	}

	public function delete_all()
	{
		if ( ! $this->cache_adaptors)
		{
			return FALSE;
		}

		foreach ($this->cache_adaptors as $adaptor)
		{
			try
			{
				$adaptor->delete_all();
			}
			catch (Exception $e)
			{
				if ( ! $this->silent_cache_fail)
				{
					throw $e;
				}
			}
		}

		return TRUE;
	}
}