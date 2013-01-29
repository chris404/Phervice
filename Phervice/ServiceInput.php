<?php

namespace Phervice;

/**
 * Used to hold the data input (not the args) of invoked services.  Just a child
 * of Dict, we don't use Dict directly incase we want to add stuff here in the
 * future.
 */
class ServiceInput extends Dict {
	
	
	/**
	 * The service name associated with this input
	 *
	 * @var string
	 */
	protected $serviceName;

	
	
	/**
	 * Creates new Dict using the supplied data.
	 * 
	 * @param string $serviceName - Name of the service this is an input for.
	 * @param array $data - Array to create the Dict with.
	 */
	public function __construct($serviceName, array $data=array()) {
		$this->serviceName = $serviceName;
		parent::__construct($data);
	}
	
	
	/**
	 * Returns the service name associated with this input
	 * 
	 * @return string
	 */
	public function getServiceName() {
		return $this->serviceName;
	}
}