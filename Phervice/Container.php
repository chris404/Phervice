<?php

namespace Phervice;


class Container {
	
	
	/**
	 * array of Service instances
	 *
	 * @var array
	 */
	protected $instances = array();


	/**
	 * mapping of aliases to service names
	 *
	 * @var array
	 */
	protected $aliases = array();
	
	
	/**
	 * mapping of regex queries to thier observer service names
	 *
	 * @var array
	 */
	protected $observers = array();
	
	
	/**
	 * mapping of service names to thier observer Service instances
	 *
	 * @var array
	 */
	protected $serviceObservers = array();
	
	
	/**
	 *
	 * @var bool
	 */
	protected $modified = false;


	
	/**
	 * creates new Container instance
	 * 
	 * @param array $config
	 * @param \Phervice\Cache $cache
	 */
	public function __construct(array $config=array()) {
		
		if (isset($config['aliases']) && is_array($config['aliases'])) {
			$this->aliases = $config['aliases'];
		}
		
		if (isset($config['observers']) && is_array($config['observers'])) {
			$this->observers = $config['observers'];
		}
	}
	
	
	/**
	 * 
	 * @return bool
	 */
	public function getModified() {
		return $this->modified;
	}
	
	
	/**
	 * inits a service by name, happens automatically first time a service is used
	 * 
	 * @param string $serviceName - name of service to init
	 */
	public function init($serviceName) {
		$this->get($serviceName);
	}
	
	
	/**
	 * invokes a service, first arg is the serviceName, additional args are passed to the service
	 * 
	 * @param string $serviceName
	 * @return mixed - result of the service invocation
	 */
	public function exec($serviceName) {
		$args = func_get_args();
		array_shift($args);
		return $this->execArgs($serviceName, $args);
	}
	
	
	/**
	 * invokes any observers of the passed service name, additional args after the first are passed
	 * to the observers
	 * 
	 * @param type $serviceName
	 */
	public function broadcast($serviceName) {
		$args = func_get_args();
		array_shift($args);
		$this->broadcastArgs($serviceName, $args);
	}
	
	
	/**
	 * invokes the named sesrvice
	 * 
	 * @param string $serviceName
	 * @param array $args
	 * @return mixed - result of the service invocation
	 */
	public function execArgs($serviceName, array $args) {
		
		$service = $this->get($serviceName);
		$observers = $this->getObservers($serviceName);
		
		if (count($observers) === 0) {
			return $service->handle($args);
			
		} else {
			return Chain::invoke($serviceName, $observers, $args, $service);
		}
	}
	
	
	/**
	 * 
	 * @param string $serviceName
	 * @param array $args
	 */
	public function broadcastArgs($serviceName, array $args) {
		$observers = $this->getObservers($serviceName);
		return Chain::invoke($serviceName, $observers, $args);
	}
	
	
	/**
	 * 
	 * 
	 * @param string $serviceName
	 * @return array
	 */
	protected function getObservers($serviceName) {
		
		if (isset($this->serviceObservers[$serviceName]) === false) {
			
			$this->modified = true;
			
			$observerMap = array();
			foreach($this->observers as $query => $observers) {
				if (preg_match($query, $serviceName)) {
					$observerMap += $observers;
				}
			}
			$observerMap = array_unique($observerMap);
			
			$observers = array();
			foreach($observerMap as $observerName) {
				$observers[] = $this->get($observerName);
			}
			
			$this->serviceObservers[$serviceName] = $observers;
		}
		
		return $this->serviceObservers[$serviceName];
	}
	
	
	/**
	 * 
	 * 
	 * @param string $serviceName
	 * @return Service
	 */
	protected function get($serviceName) {
		
		if (isset($this->aliases[$serviceName])) {
			$serviceName = $this->aliases[$serviceName];
		}
		
		# if service exists return it now
		if (isset($this->instances[$serviceName])) {
			return $this->instances[$serviceName];
		}
		
		# resolve class name
		$segments = str_replace('.', ' ', $serviceName);
		$ucSegments = ucwords($segments);
		$className = str_replace(' ', '\\', $ucSegments);
		
		$this->modified = true;
		
		# if no class then create a new one
		if (isset($this->instances[$className]) === false) {
			$this->instances[$className] = new Service($className, $this);
		}
		
		# return instance and map to service name
		return $this->instances[$serviceName] = $this->instances[$className];
	}
}