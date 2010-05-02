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
abstract class Request_Cache_Decorator {

	/**
	 * @param   array    store of decorator instances
	 */
	protected static $_instances = array();

	/**
	 * Creates a new request cache decorator of the type defined. If no decorator type
	 * is supplied, the `vitesse.default_decorator` configuration setting will be used.
	 * 
	 *     // Create a new memcache decorator
	 *     $decorator = Request_Cache_Decorator::instance('memcache');
	 *
	 *     // Create a new default decorator
	 *     $default_decorator = Request_Cache_Decorator::instance();
	 *
	 * @param   string   decorator class name
	 * @return  Request_Cache_Adaptor
	 * @throws  Kohana_Request_Exception
	 */
	public static function instance($decorator = NULL)
	{
		// Load the vitesse config
		$config = Kohana::config('vitesse');

		// If the adaptor isn't defined
		if ($decorator === NULL)
		{
			// Set it to the default adaptor
			$decorator = $config->default_decorator;
		}

		// Create the full adaptor class name
		$decorator_class = 'Request_Cache_Decorator_'.ucfirst($decorator);

		// If an instance already exists
		if (isset(Request_Cache_Decorator::$_instances[$decorator_class]))
		{
			// Return the instance
			return Request_Cache_Decorator::$_instances[$decorator_class];
		}

		// Create a new decorator class
		$decorator = new $decorator_class($config->cache_configuration_groups);

		// If the decorator is not of the correct type
		if ( ! $decorator instanceof Request_Cache_Decorator)
		{
			// Throw an exception
			throw new Kohana_Request_Exception('Decorator supplied is not an instance of Request_Cache_Decorator : :class', array(':class' => get_class($decorator)));
		}

		// Set the instance to the class
		Request_Cache_Decorator::$_instances[$decorator_class] = $decorator;

		// Return the decorator
		return $decorator;
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
	 * Constructor for the class. Calls the initialisation method to load the correct
	 * cache class. This method should not be used to instantiate this class.
	 * 
	 *      // Correct instantiation method
	 *      $decorator = Request_Cache_Decorator::instance('file');
	 *      
	 *      // Incorrect instantiation method
	 *      $decorator = new Request_Cache_Decorator_File;
	 */
	protected function __construct(array $config)
	{
		// Load the configuration
		$this->_config = $config;

		// Initialise the decorator
		$this->_init();
	}

	/**
	 * Sets a Kohana Response to be cached by
	 * the decorator with a key. The decorator must
	 * handle the expiration logic.
	 *
	 *     // Set a value to the decorator
	 *     $decorator->set('foo', $bar);
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
	 * Gets a Kohana Response from the decorator
	 * cache. If the entry is no longer available
	 * or has expired, it is deleted and no
	 * response is returned.
	 *
	 *     // Get a response from the decorator
	 *     $response = $decorator->get('foo');
	 *
	 * @param   string   key
	 * @return  Kohana_Response|boolean
	 */
	public function get($key)
	{
		return $this->_cache->get($key);
	}

	/**
	 * Deletes a Kohana Response entry from cache.
	 *
	 *     // Delete a response from the decorator
	 *     $decorator->delete('foobar');
	 *
	 * @param   string   key 
	 * @return  boolean
	 */
	public function delete($key)
	{
		return $this->_cache->delete($key);
	}

	/**
	 * Deletes all caches Responses from all attached decorators.
	 * 
	 * Beware that if a decorator uses a cache which is used by other parts of the
	 * system, or other systems. This will delete ALL cache entries, flushing the cache.
	 *
	 *     // Delete all responses from this decorator
	 *     $decorator->delete_all();
	 *
	 * @return  boolean
	 */
	public function delete_all()
	{
		return $this->_cache->delete_all();
	}

	/**
	 * Handles the object being cast to string and returns the object id hash.
	 * 
	 *     // Get the id of this decorator
	 *     $id = (string) $decorator;
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return spl_object_hash($this);
	}

	/**
	 * Initialises the Kohana_Cache class for this decorator. Implemented by
	 * decorators that extend this class.
	 *
	 *     protected function _init()
	 *     {
	 *          // Create a new Kohana_Cache instance based on the configuration setting for this class.
	 *          $this->_cache = Cache::instance($this->_config[__CLASS__]);
	 *     }
	 *
	 * @return  void
	 */
	abstract protected function _init();

	/**
	 * Prepares the response for caching by the decorator. Sets up headers and renders the response
	 * body due to some caching methods not serialising [Kohana_View] correctly. The `_prepare_response()`
	 * method performs the following.
	 *
	 * 1.  Set correct headers to the Response object, formatting `Cache-Control` correctly
	 * 2.  Creates an `Expires:` header if required
	 * 3.  Renders the [Kohana_Reponse]::`$body`
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
			'Cache-Control'  => Request_Cache::create_cache_control($cache_control),
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