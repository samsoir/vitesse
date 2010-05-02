<?php defined('SYSPATH') or die('No direct script access.');
/**
 * The Null adaptor is a mock adaptor to
 * test the function of the adaptor without actually
 * caching or retrieving the response.
 * 
 * This should not be used on a production server.
 *
 * @package    Vitesse
 * @category   Decorator
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Request_Cache_Decorator_Null extends Request_Cache_Decorator {

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
	 * @throws  Kohana_Request_Exception
	 */
	public function set($key, Kohana_Response $response)
	{
		Kohana::$log->add('debug', 'Setting cache: :key to cache', array(':key' => $key));
		return TRUE;
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
	 * @throws  Kohana_Request_Exception
	 */
	public function get($key)
	{
		Kohana::$log->add('debug', 'Getting cache: :key from cache', array(':key' => $key));
		return FALSE;
	}

	/**
	 * Deletes a Kohana Response cache from
	 * all attached adaptors.
	 * 
	 *     Request_Cache_Adaptor::delete('foobar');
	 *
	 * @param   string   key 
	 * @return  boolean
	 * @throws  Kohana_Request_Exception
	 */
	public function delete($key)
	{
		Kohana::$log->add('debug', 'Deleting cache: :key from cache', array(':key' => $key));
		return TRUE;
	}

	/**
	 * Deletes all caches Responses from
	 * adaptors.
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
	 * @throws  Kohana_Request_Exception
	 */
	public function delete_all()
	{
		Kohana::$log->add('debug', 'Deleting all caches from NULL');
		return TRUE;
	}

	/**
	 * Initialises the Kohana_Cache class for
	 * this adaptor
	 *
	 * @return  void
	 */
	protected function _init()
	{
		Kohana::$log->add('debug', 'Initialising the NULL cache decorator');
	}
}