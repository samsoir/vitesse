<?php defined('SYSPATH') or die('No direct script access.');

abstract class Request_Async_Driver {

	/**
	 * Executes an asynchronous request using the driver
	 * method.
	 * 
	 *      // Execute the asynchronous request
	 *      $driver->execute($request_async);
	 *
	 * @param   Request_Async   The asynchronous request to execute
	 * @return  Request_Async
	 */
	abstract public function execute(Request_Async $request_async);
}