<?php defined('SYSPATH') or die('No direct script access.');

class Request_Async implements Iterator, Countable {

	/**
	 * Factory method to create a new asynchronous request
	 * pool.
	 * 
	 *     // Create a new request pool
	 *     $request = Request_Async::factory()
	 *                 ->execute();
	 *
	 * @param array $config 
	 * @return void
	 * @author Sam de Freyssinet
	 */
	public static function factory(array $config = NULL)
	{
		return new Request_Async($config);
	}

	/**
	 * @var   array
	 */
	public $requests = array();

	/**
	 * @var   Request_Async_Driver
	 */
	protected $_driver;

	/**
	 * Constructor method, pass in properties in an array as
	 * key/value pairs
	 * 
	 *      // Create a new asynchronous request
	 *      $request_async = new Request_Async(array(
	 *           'driver'   => new Request_Async_Gearman,
	 *           'requests  => array(
	 *                Request::factory('/'),
	 *                Request::factory('/foo/bar'),
	 *      )));
	 *
	 * @param  array   $config 
	 */
	public function __construct(array $config = NULL)
	{
		if ($config !== NULL)
		{
			foreach ($config as $key => $value)
			{
				if (property_exists($this, $key) or ($protected = property_exists($this, '_'.$key)))
				{
					if ($protected !== NULL)
					{
						$key = '_'.$key;
					}

					$this->$key = $value;
					$protected === NULL;
					continue;
				}
			}
		}
	}

	/**
	 * Provide access to the asynchronous driver
	 *
	 * @param   Request_Async_Driver  driver 
	 * @return  Request_Async_Driver|self
	 */
	public function driver(Request_Async_Driver $driver = NULL)
	{
		if ($driver === NULL)
		{
			return $this->_driver;
		}
		else
		{
			$this->_driver = $driver;
			return $this;
		}
	}

	/**
	 * undocumented function
	 *
	 * @return  Request_Async
	 * @throws  Kohana_Request_Exception
	 */
	public function execute()
	{
		if ( ! $_driver instanceof Request_Async_Driver)
		{
			throw new Kohana_Request_Exception(__METHOD__.' unable to execute asynchronous request without async request driver');
		}

		// Process the request pool
		return $this->_driver->execute($this);
	}

	/**
	 * Move the request pointer onto the next element.
	 * 
	 * [SPL-Iterator](http://uk.php.net/manual/en/class.iterator.php)
	 *
	 * @return  [Request]
	 */
	public function next()
	{
		return next($this->requests);
	}

	/**
	 * Return the request at the current pointer.
	 * 
	 * [SPL-Iterator](http://uk.php.net/manual/en/class.iterator.php)
	 *
	 * @return  [Request]|boolean
	 */
	public function current()
	{
		return current($this->requests);
	}

	/**
	 * Check whether the current pointer position is valid.
	 * 
	 * [SPL-Iterator](http://uk.php.net/manual/en/class.iterator.php)
	 *
	 * @return  boolean
	 */
	public function valid()
	{
		return (current($this->requests) instanceof Kohana_Request);
	}

	/**
	 * Rewind the pointer to the beginning of the array
	 * 
	 * [SPL-Iterator](http://uk.php.net/manual/en/class.iterator.php)
	 * 
	 * @return  boolean
	 */
	public function rewind()
	{
		return reset($this->requests);
	}

	/**
	 * Return the number of requests within the object
	 * 
	 * [SPL-Countable](http://uk.php.net/manual/en/class.countable.php)
	 *
	 * @return  int
	 */
	public function count()
	{
		return count($this->requests);
	}
}