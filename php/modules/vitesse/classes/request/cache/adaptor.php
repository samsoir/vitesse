<?php defined('SYSPATH') or die('No direct script access.');

abstract class Request_Cache_Adaptor {

	const DEFAULT_ADAPTOR = 'null';

	/**
	 * @param   array    store of adaptor instances
	 */
	protected static $_instances = array();

	/**
	 * Creates a new request cache adaptor of
	 * the type defined. If no adaptor type
	 * is supplied, the default File adaptor
	 * will be used.
	 * 
	 *     Request_Cache_Adaptor::instance('memcache');
	 *
	 * @param   string   adaptor class name
	 * @return  Request_Cache_Adaptor
	 * @throws  Kohana_Request_Exception
	 */
	public static function instance($adaptor = NULL)
	{
		// Load the vitesse config
		$config = Kohana::config('vitesse');

		// If the adaptor isn't defined
		if ($adaptor === NULL)
		{
			// Set it to the default adaptor
			$adaptor = $config->default_adaptor;
		}

		// Create the full adaptor class name
		$adaptor_class = 'Request_Cache_Adaptor_'.ucfirst($adaptor);

		// If an instance already exists
		if (isset(Request_Cache_Adaptor::$_instances[$adaptor_class]))
		{
			// Return the instance
			return Request_Cache_Adaptor::$_instances[$adaptor_class];
		}

		// Create a new adaptor class
		$adaptor = new $adaptor_class($config->cache_configuration_groups);

		// If the adaptor is not of the correct type
		if ( ! $adaptor instanceof Request_Cache_Adaptor)
		{
			// Throw an exception
			throw new Kohana_Request_Exception('Adaptor supplied is not an instance of Request_Cache_Adaptor : :class', array(':class' => get_class($adaptor)));
		}

		// Set the instance to the class
		Request_Cache_Adaptor::$_instances[$adaptor_class] = $adaptor;

		// Return the adaptor
		return $adaptor;
	}

	/**
	 * @var    Kohana_Cache
	 */
	protected $_cache;

	/**
	 * @var    integer
	 */
	protected $_lifetime;

	/**
	 * @var    array
	 */
	protected $_config;

	/**
	 * Constructor for the class. Calls the initialisation
	 * method to load the correct cache class.
	 */
	protected function __construct(array $config)
	{
		// Load the configuration
		$this->_config = $config;

		// Initialise the adaptor
		$this->_init();
	}

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
		return $this->_cache->set($key, $this->_prepare_response($response), $this->_lifetime);
	}

	/**
	 * Gets a Kohana Response from the adaptor
	 * cache. If the entry is no longer available
	 * or has expired, it is deleted and no
	 * response is returned.
	 * 
	 *     Request_Cache_Adaptor::get('foo');
	 *
	 * @param   string   key
	 * @return  Kohana_Response|boolean
	 */
	public function get($key)
	{
		return $this->_cache->get($key);
	}

	/**
	 * Deletes a Kohana Response cache from
	 * cache.
	 * 
	 *     Request_Cache_Adaptor::delete('foobar');
	 *
	 * @param   string   key 
	 * @return  boolean
	 */
	public function delete($key)
	{
		return $this->_cache->delete($key);
	}

	/**
	 * Deletes all caches Responses from all
	 * attached adaptors.
	 * 
	 * Beware that if a adaptor uses a cache
	 * which is used by other parts of the
	 * system, or other systems. This will
	 * delete ALL cache entries, flushing
	 * the cache.
	 * 
	 *     Request_Cache_Adaptor::delete_all();
	 *
	 * @return  boolean
	 */
	public function delete_all()
	{
		return $this->_cache->delete_all();
	}

	/**
	 * Handles the object being cast to string
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return spl_object_hash($this);
	}

	/**
	 * Initialises the Kohana_Cache class for
	 * this adaptor
	 *
	 * @return  void
	 */
	abstract protected function _init();

	/**
	 * Prepares the response for caching. Checks
	 * the following are available
	 *
	 * @param   Kohana_Response  response 
	 * @return  Kohana_Response
	 */
	protected function _prepare_response(Kohana_Response $response)
	{
		// Get the cache control headers
		$cache_control = Request_Cache::parse_cache_control($response->headers);

		// Set the lifetime of the cache from the header
		$this->_lifetime = $cache_control['max-age'];

		// Get time now
		$time = time();

		// If the expires header is not set
		if ( ! isset($response->headers['Expires']))
		{
			// Calculate expires header (DateTime would probably be better here - SdF)
			$expires = gmdate('D, d M Y H:i:s T', $time+$cache_control['max-age']);
		}

		// Tell caches to check their validation
		$cache_control['must-revalidate'] = '';

		// Replace the headers with those that are not set
		$response->headers += array(
			'Cache-Control'  => Request::create_cache_control($cache_control),
			'Expires'        => $expires,
			'Last-Modified'  => gmdate('D, d M Y H:i:s T', $time),
			'Content-Length' => strlen((string) $response->body)
		);

		// Render the body (some caches have trouble serializing)
		$response->body = (string) $response->body;

		// return
		return $response;
	}
}