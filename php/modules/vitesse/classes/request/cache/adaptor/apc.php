<?php defined('SYSPATH') or die('No direct script access.');

class Request_Cache_Adaptor_Apc extends Request_Cache_Adaptor {

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