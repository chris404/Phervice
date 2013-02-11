<?php

namespace Phervice;


class Chain {
	
	
	/**
	 *
	 * @var string
	 */
	protected $serviceName;
	
	
	/**
	 *
	 * @var array
	 */
	protected $observers;

	
	/**
	 *
	 * @var array
	 */
	protected $args;
	
	
	/**
	 *
	 * @var Service
	 */
	protected $service;
	
	
	
	static public function invoke($serviceName, array $observers, array $args, Service $service=null) {
		$chain = new static($serviceName, $observers, $args, $service);
		return $chain();
	}



	public function __construct($serviceName, array $observers, array $args, Service $service=null) {
	
		$this->serviceName = $serviceName;
		$this->observers = $observers;
		$this->args = $args;
		$this->service = $service;
	}
	
	
	/**
	 * Builds the "chain" closure to be passed to each observer.
	 * 
	 * @param array $observers - array of observer services
	 * @param array $args - array of args passed to the service
	 * @param Service $service - optional service to be invoked at "end" of observer chain
	 * @return mixed - result from service invocation, null if no service provided
	 */
	public function __invoke() {
		
		# Define the closure to invoke, this will be called for each observer and the service.
			
		# If the invocation of this function included arguments then use those instead.
		if (func_num_args() > 0) {
			$this->args = func_get_args();
		}

		# If there are more observers then continue to cycle through them.
		if (count($this->observers) > 0) {
			$observer = array_shift($this->observers);
			# Add this closure as the first argument so observers can continue the chain.
			array_unshift($args, $this);
			# invoke observer
			return $observer->handle($args);

		# No more observers but there is a service, so use that. Since this is the end
		# of the line there's no need to include this closure in the agrument array.			
		} elseif ($this->service !== null) {
			return $this->service->handle($args);
		}

		# No more observers and no service, so just return null.
		return null;
	}
	
	
	public function getServiceName() {
		return $this->serviceName;
	}
}