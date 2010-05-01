<?php defined('SYSPATH') or die('No direct script access.');

class Request_Cache_Adaptor_Nginx extends Request_Cache_Adaptor_Memcache {

	/**
	 * Sets a Kohana Response to be cached by
	 * the adaptor with a key. The adaptor must
	 * handle the expiration logic.
	 * 
	 *     Request_Cache_Adaptor::set('foo', $bar);
	 *
	 * @param   string   key
	 * @param   Kohana_Response response
	 * @return  boolean
	 */
	public function set($key, Kohana_Response $response)
	{
		return $this->_cache->set($key, $this->create_nginx_cache($this->_prepare_response($response)), $this->_lifetime);
	}

	/**
	 * Prepares the Kohana_Reponse object for
	 * caching to nginx. This requires the object
	 * to be flattened to a standard HTTP response
	 * including full headers.
	 * 
	 *     $this->_create_nginx_cache($response);
	 *
	 * @param   Kohana_Response  response 
	 * @return  string
	 */
	protected function _create_nginx_cache(Kohana_Response $response)
	{
		// Create empty cache buffer
		$cache = '';

		// Generate HTTP header
		foreach ($response->headers as $key => $value)
		{
			$cache .= "{$key}: {$value}\n";
		}

		// Check for HTTPS and secure cookie setting
		if (empty($_SERVER['HTTPS']) and ! Cookie::$secure)
		{
			// Get the response cookies
			$cookies = $response->get_cookies();

			// Generate cookies
			foreach ($cookies as $name => $value)
			{
				$cache .= 'Set-Cookie: '.$name.'='.Cookie::salt($name, $value['value']).'~'.$value['value'].
					'; expires: '.gmdate('D, d M Y H:i:s T', $value['expiration']).
					'; path: '.Cookie::$path.
					'; domain: '.Cookie::$domain.
					(Cookie::$httponly ? '; httpOnly':'')."\n";
			}
		}

		// Create HTTP body
		$cache .= "\n".$response->body;

		return $cache;
	}

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