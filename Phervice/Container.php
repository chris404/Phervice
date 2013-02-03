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
	 * mapping of service names to thier observer service names
	 *
	 * @var array
	 */
	protected $serviceObservers = array();
	
	
	/**
	 * Instance of Cache used for caching observer mappings and annotations
	 *
	 * @var Cache
	 */
	protected $cache;
	

	/**
	 * creates new Container instance
	 * 
	 * @param array $config
	 * @param \Phervice\Cache $cache
	 */
	public function __construct(array $config=array(), Cache $cache=null) {
		
		if (isset($cache)) {
			$this->cache = $cache;
		} else {
			$this->cache = new Cache;
		}
		
		if (isset($config['aliases']) && is_array($config['aliases'])) {
			$this->aliases = $config['aliases'];
		}
		
		if (isset($config['observers']) && is_array($config['observers'])) {
			$this->observers = $config['observers'];
		}
		
		if (isset($config['autoInit']) && is_array($config['autoInit'])) {
			foreach($config['autoInit'] as $serviceName) {
				$this->init($serviceName);
			}
		}
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
	public function execArgs($serviceName, $args) {
		
		$service = $this->get($serviceName);
		$observers = $this->getObservers($serviceName);
		
		if (count($observers) === 0) {
			return $service->handle($args);
			
		} else {
			return $this->callObserverChain($observers, $args, $service);
		}
	}
	
	
	/**
	 * 
	 * @param string $serviceName
	 * @param array $args
	 */
	public function broadcastArgs($serviceName, $args) {
		$observers = $this->getObservers($serviceName);
		$this->callObserverChain($observers, $args);
	}
	
	
	/**
	 * Builds the "chain" closure to be passed to each observer.
	 * 
	 * @param array $observers - array of observer services
	 * @param array $args - array of args passed to the service
	 * @param Service $service - optional service to be invoked at "end" of observer chain
	 * @return mixed - result from service invocation, null if no service provided
	 */
	public function callObserverChain(array $observers, array $args, Service $service=null) {
		
		# Define the closure to invoke, this will be called for each observer and the service.
		$chain = function() use (&$chain, &$observers, $args, $service) {
			
			# If the invokation of this function included arguments then use those instead.
			if (func_num_args() > 0) {
				$args = func_get_args();
			}
			
			# If there are more observers then continue to cycle through them.
			if (count($observers) > 0) {
				$observer = array_shift($observers);
				# Add this closure as the first argument so observers can continue the chain.
				array_unshift($args, $chain);
				# invoke observer
				return $observer->handle($args);
				
			# No more observers but there is a service, so use that. Since this is the end
			# of the line there's no need to include this closure in the agrument array.			
			} elseif ($service !== null) {
				return $service->handle($args);
			}
			
			# No more observers and no service, so just return null.
			return null;
		};
		
		# start the chain!
		return $chain();
	}
	
	
	/**
	 * 
	 * 
	 * @param string $serviceName
	 * @return array
	 */
	protected function getObservers($serviceName) {
		
		if (isset($this->serviceObservers[$serviceName]) === false) {
			
			$cache = $this->getCache();
			$observerMap = $cache->getObserverMap($serviceName);
			
			if ($observerMap === null) {
			
				$observerMap = array();

				foreach($this->observers as $query => $observers) {
					if (preg_match($query, $serviceName)) {
						$observerMap += $observers;
					}
				}
				
				$observerMap = array_unique($observerMap);
				$cache->setObserverMap($serviceName, $observerMap);
			}
			
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
	 * @return Cache
	 */
	public function getCache() {
		return $this->cache;
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
		
		# if no class then create a new one
		if (isset($this->instances[$className]) === false) {
			$this->instances[$className] = new Service($className, $this);
		}
		
		# return instance and map to service name
		return $this->instances[$serviceName] = $this->instances[$className];
	}
}