<?php

namespace Phervice;


/**
 * Abstract class that all services and subscribers must inherit from.
 * * If service, child class must provide a "handle" method
 * * If subscriber, child class must provide a "subscribe" method
 * 
 * Class may be both a service and subscriber.
 * 
 */
abstract class ServiceBase {
	
	
	/**
	 * Set in the construct and used in exec.
	 * 
	 * @var ServiceManager
	 */
	protected $service;


	/**
	 * Sets the service var and invokes init(). While this can be extended it's
	 * recomended to use init() for child specific tasks (that's why it's there).
	 * 
	 * @param ServiceManager $service - The ServiceManager that instantiated this
	 * class, allows other services to be called inside.
	 */
	public function __construct(ServiceManager $service) {
		$this->service = $service;
		$this->init();
	}
	
	
	/**
	 * Allows child classes to easily add custom "construct" logic. Not using
	 * abstract as not all services/subscribers need the functionality.
	 */
	protected function init() {}
	
	
	/**
	 * Convenience wrapper of $this->service->exec.
	 * 
	 * @param string $serviceName - Name of the service to exec
	 * @param array $arguments - Array of arguments to pass
	 * @param array $input - Data to input into the service
	 * @return mixed - Output of the service
	 */
	protected function exec($serviceName, array $arguments=[], array $input=[]) {
		return $this->service->exec($serviceName, $arguments, $input);
	}
	
}