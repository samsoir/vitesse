<?php defined('SYSPATH') or die('No direct script access.');

class Request_Async_Gearman extends Request_Async_Driver {

	// Gearman Request Asynchronous context
	public static $context = 'Vitesse.Request';

	/**
	 * The Gearman Worker processes requests passed to it from a Gearman
	 * server. This class should be invoked as a daemon using the CLI
	 * interface. Multiple instances can be created to handle multiple requests
	 * asynchronously.
	 * 
	 *      // Initialize the Gearman Worker (in bootstrap)
	 *      Request_Async_Gearman::worker();
	 *      exit(0);
	 * 
	 * To create a daemon script, run the following command from your command
	 * line.
	 * 
	 *      php /path/to/index.php
	 *
	 * @return  void
	 */
	public static function worker()
	{
		$worker = new GearmanWorker;
		$worker->addServer();
		$worker->addFunction('request_async', array('Request_Async_Gearman', 'execute_request'), Request_Async_Gearman::$context);

		echo Request_Async_Gearman::$context.': Starting worker.'."\n";

		while ($worker->work() or $worker->returnCode() == GEARMAN_IO_WAIT or $worker->returnCode() == GEARMAN_NO_JOBS)
		{
			if ($worker->returnCode() == GEARMAN_SUCCESS)
			{
				continue;
			}
		
			echo Request_Async_Gearman::$context.': Waiting for next job...'."\n";
		
			if ( ! $worker->wait())
			{
				if ($worker->returnCode() == GEARMAN_NO_ACTIVE_FDS)
				{
					usleep(100);
					continue;
				}
			}
		
			break;
		}

		echo Request_Async_Gearman::$context.': Stopping worker.'."\n";
		echo Request_Async_Gearman::$context.': Worker error'.$worker->error()."\n";
	}

	/**
	 * Executes a [Request] object and returns the response
	 * 
	 *      // Create a request
	 *      $request = Request::factory('/users/load/1');
	 * 
	 *      // Execute the request
	 *      $response = Request_Async_Gearman::execute_request($request);
	 * 
	 *  Used as a callback by Request_Async_Gearman to handle the request
	 *  processing.
	 * 
	 * @param   Kohana_Request  request to process
	 * @return  Kohana_Response  the response from the request
	 */
	public static function execute_request(GearmanJob $job)
	{
		// Unserialise the request
		$request = unserialize($job->workload());

		// Send starting status
		$job->sendStatus(1, 2);

		// Encapsulate request execution
		try
		{
			// Get the response
			$response = $request->execute()
				->headers('X-Request-Uri', $request->uri());
		}
		catch (Exception $e)
		{
			// Send the exception to Gearman
			$job->sendException($e->getMessage());
			return;
		}

		// Send complete status
		$job->sendStatus(2,2);

		// Send the response
		$job->sendData(serialize($response));
	}

	/**
	 * @var    array
	 */
	public $errors = array();

	/**
	 * @var    GearmanClient
	 */
	protected $_gearman_client;

	/**
	 * @var    Request_Async
	 */
	protected $_request_async;

	/**
	 * @var    array
	 */
	protected $_requests = array();

	/**
	 * @var    array
	 */
	protected $_complete = array();

	/**
	 * @var    array
	 */
	protected $_task_handles = array();

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
	public function execute(Request_Async $request_async)
	{
		// Assign the asynchronous request to this driver
		$this->_request_async = $request_async;

		// Foreach request
		foreach ($request_async as $request)
		{
			// Add the task to the job
			$task = $this->_gearman_client->addTaskHigh('request_async', serialize($request), Request_Async_Gearman::$context);

			$uuid = $task->unique();

			$this->_requests[$uuid] = $request;
			$this->_task_handles[] = $task;
			$this->_complete[$uuid] = NULL;
		}

		// Run the tasks
		$this->_gearman_client->runTasks();

		// Return request async object
		return $request_async;
	}

	/**
	 * Constructor method checks for gearman
	 * 
	 * @throws  Kohana_Request_Exception
	 */
	public function __construct()
	{
		if ( ! extension_loaded('gearman'))
		{
			throw new Kohana_Request_Exception('Unable to load PHP Gearman. Check your local environment.');
		}

		// Create a new Gearman client and add a server
		$this->_gearman_client = new GearmanClient;

		/**
		 * @todo    support multiple Gearman servers
		 * @todo    support customisable servers
		 */
		if ( ! $this->_gearman_client->addServer())
		{
			throw new Kohana_Request_Exception('Unable to attach to Gearman server');
		}

		// Setup callback functions for Gearman tasks
		$this->_gearman_client->setDataCallback(array($this, '_task_data'));
		$this->_gearman_client->setFailCallback(array($this, '_task_failed'));
		$this->_gearman_client->setCompleteCallback(array($this, '_task_complete'));
		$this->_gearman_client->setExceptionCallback(array($this, '_task_exception'));
	}

	/**
	 * Read-only access to the Gearman tasks.
	 *
	 * @return  array
	 */
	public function tasks($uuid = NULL)
	{
		return ($uuid === NULL) ? $this->_task_handles : Arr::get($this->_task_handles, $uuid);
	}

	/**
	 * Returns the Gearman job status for any task or all tasks
	 * 
	 *     // Get the status of all tasks
	 *     $statuses = $request
	 *
	 * @param   GearmanTask  task to return status for (optional)
	 * @return  array
	 */
	public function status(GearmanTask $task = NULL)
	{
		if ($task !== NULL)
		{
			return $this->_gearman_client->jobStatus($task->jobHandle());
		}
		else
		{
			$status = array();

			foreach ($this->_task_handles as $uuid => $task)
			{
				$status[$uuid] = $this->_gearman_client->jobStatus($task->jobHandle());
			}

			return $status;
		}
	}

	/**
	 * Returns the complete status of the Gearman tasks, useful
	 * for checking if all asynchronous requests have completed
	 * including failed tasks.
	 * 
	 *      // If Request_Async_Gearman has completed
	 *      if ($request_async->driver()->complete())
	 *      {
	 *           // Do something
	 *      }
	 *
	 * @param   GearmanTask  task to check completed status (optional)
	 * @return  boolean|void
	 */
	public function complete(GearmanTask $task = NULL)
	{
		if ($task !== NULL)
		{
			return $this->_complete[$task->unique()];
		}
		else
		{
			$values = array_count_values($this->_complete);
			return ($values[TRUE] + $values[FALSE]) === count($this->_complete);
		}
	}

	/**
	 * Handles a completed task event called by the Gearman client,
	 * sets the completed table to TRUE for this task entry.
	 *
	 * @param   GearmanTask task that has completed
	 * @return  void
	 */
	protected function _task_complete(GearmanTask $task)
	{
		$this->_complete[$task->unique()] = TRUE;
	}

	/**
	 * Handles a failed task event called by the Gearman client,
	 * sets the completed table to FALSE for this task entry.
	 *
	 * @param   GearmanTask task that has failed
	 * @return  void
	 */
	protected function _task_failed(GearmanTask $task)
	{
		$this->_complete[$task->unique()] = FALSE;
	}

	/**
	 * Handles completed task event called by the Gearman client,
	 * parses the response object and assigns it to the request.
	 *
	 * @param   GearmanTask $task 
	 * @return  void
	 */
	protected function _task_data(GearmanTask $task)
	{
		try
		{
			// Assign the response to the request
			$this->_request[$task->unique()]->response = unserialize($task->data());
		}
		catch (Exception $e)
		{
			$this->_task_exception($task, $e);
		}
	}

	/**
	 * Handles exceptions thrown by Kohana and Gearman client, setting the
	 * errors array as appropriate
	 *
	 * @param GearmanTask $task 
	 * @param Exception $exception 
	 * @return void
	 * @author Sam de Freyssinet
	 */
	protected function _task_exception(GearmanTask $task, Exception $exception = NULL)
	{
		$uuid = $task->unique();
		$error = ($exception === NULL);

		$this->errors[$uuid] = array(
			'type'    => $error ? 'error' : 'exception',
			'errorNo' => $error ? $this->_gearman_client->getErrno() : $exception->getCode(),
			'error'   => $error ? $this->_gearman_client->error() : $exception,
		);
	}
}