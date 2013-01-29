<?php

namespace Phervice;


/**
 * Manages services, lazy loads them, and allows class to execute them and publish to them.
 */
class ServiceManager {
	

	/**
	 * Closure invoked to resolve service names into class names
	 *
	 * @var \Closure
	 */
	protected $dispatcher;

	
	/**
	 * Assoc list of service instances, all children of ServiceBase. Keys are class names.
	 *
	 * @var array
	 */
	protected $serviceInstances = [];
	
	
	/**
	 * Assoc list of service closures, wrapped around ServiceBase::handle(). Keys are service names.
	 *
	 * @var array
	 */
	protected $serviceClosures = [];


	/**
	 * Mapping of service names to class names
	 *
	 * @var array
	 */
	protected $classNames = [];
	
	
	/**
	 * Assoc list of subscriber closures, wrapped around ServiceBase::subscribe. Keys are service
	 * names.
	 *
	 * @var array
	 */
	protected $subscribers = [];

	

	/**
	 * Adds the dispatcher, necessary for resolving service names into class names.
	 * 
	 * @param \Closure $dispatcher - Closure invoked to resolve service names
	 */
	public function __construct(\Closure $dispatcher) {
		$this->dispatcher = $dispatcher;
	}
	
	
	/**
	 * Returns a list of subscribers that exactly match the passed serviceName.
	 * 
	 * @param string $serviceName - name of service to find subscribers for
	 * @return array - list of subscribers
	 */
	protected function buildSubscribers($serviceName) {
		
		# if there's no subscriber entry for this serviceName create an empty one
		if (isset($this->subscribers[$serviceName]) === false) {
			$this->subscribers[$serviceName] = [];
		}
		
		# iterate over all subscribers for this serivice
		foreach($this->subscribers[$serviceName] as $i => $subscriber) {
			# if any are strings then they have not been initialized yet, so do that now.
			if (is_string($subscriber)) {
				$this->subscribers[$serviceName][$i] = $this->getServiceClosure($subscriber, 'subscribe');
			}
		}
		
		return $this->subscribers[$serviceName];
	}


	/**
	 * Returns a list of subscribers based off the passed serviceName.
	 * 
	 * @param string $serviceName - Name of service to use in matching subscribers
	 * @return array - list of subscribers
	 */
	protected function getSubscribers($serviceName) {

		# build a list of "segments" for wild card matching; include the service class name and full wildcard.
		
		# first get all the wildcard subscribers
		$subscribers = $this->buildSubscribers('*');
		
		# iterate over each segment of the serviceName, obtaining any matching partial wildcards
		$base = '';
		foreach(explode('.', $serviceName) as $segment) {
			$base = $base==''? $segment: "{$base}.{$segment}";
			$subscribers += $this->buildSubscribers("{$base}.*");
		}
		
		# lastly get any subscribers that are specific to this serviceName.
		$subscribers += $this->buildSubscribers($serviceName);
		
		return $subscribers;
	}
	

	/**
	 * Creates the "chain()" method that is responsible for ensuring each subscriber and
	 * (optionally) the service are invoked.
	 * 
	 * @param array $subscribers - List of subscriber closures
	 * @param array $arguments - Arguments pass to the exec or publish methods
	 * @param \Closure $service - The service closure
	 * @return mixed - Output of the service, or null if there isn't any.
	 */
	protected function makeStackClosure(array $subscribers, array $arguments, \Closure $service=null) {
		
		# Define the closure to return, this will be called for each subscriber and the service.
		return $chain = function() use (&$chain, &$subscribers, $arguments, $service) {
			
			# If the invokation of this function included arguments then use those instead.
			if (func_num_args() > 0) {
				$arguments = func_get_args();
			}
			
			# If there are more subscribers then continue to cycle through them.
			if (count($subscribers) > 0) {
				$subscriber = array_shift($subscribers);
				# Add this closure as the first argument so subscribers can continue the chain.
				array_unshift($arguments, $chain);
				
				return $subscriber($arguments);
				
			# No more subscribers but there is a service, so use that. Since this is the end
			# of the line there's no need to include this closure in the agrument array.			
			} elseif ($service !== null) {
				return $service($arguments);
			}
			
			# No more subscribers and no service, so just return null.
			return null;
		};
	}
	
	
	/**
	 * Executes the specified service, also calls any services that are subscribed to the invoked
	 * service. ServiceManagerException is thrown if the service doesn't exist.
	 * 
	 * @param string $serviceName - Name of the service to exec
	 * @param array $arguments - Array of arguments to pass
	 * @param array $input - Data to input into the service
	 * @return mixed - Output of the service
	 */
	public function exec($serviceName, array $arguments=[], array $input=[]) {
		
		# normalize the input and add it to the beginning of the arguments array
		array_unshift($arguments, new ServiceInput($serviceName, $input));
		
		# get an array of any subscibers of the service
		$stack = $this->getSubscribers($serviceName);
		
		# get the actual service
		$service = $this->getServiceClosure($serviceName, 'handle');
		
		# finally create the "chain()" closure to iterate over all subscribers and the service
		$chain = $this->makeStackClosure($stack, $arguments, $service);
		return $chain();
	}
	
	
	/**
	 * Executes just subscribers of the specified service, the actual service is not invoked.
	 * 
	 * @param string $serviceName - Name of the service to exec
	 * @param array $arguments - Array of arguments to pass
	 * @param array $input - Data to input into the service
	 * @return mixed - Output of the service
	 */
	public function publish($serviceName, array $arguments=[], array $input=[]) {
		
		# normalize the input and add it to the beginning of the arguments array
		array_unshift($arguments, new ServiceInput($serviceName, $input));
		
		# get an array of any subscibers of the service
		$stack = $this->getSubscribers($serviceName);
		
		# finally create the "chain()" closure to iterate over all subscribers
		$chain = $this->makeStackClosure($stack, $arguments);
		return $chain();
	}
	
	
	/**
	 * Creates the specified service's "handle" closure, and if not already created its instance. Can
	 * be used to "prep" a service (creating its instance) without actually invoking it.
	 * 
	 * @param string $serviceName - Name of the service to init
	 * @param bool $reload - True to force the re-creation of the closure, default false.
	 */
	public function initService($serviceName, $reload=false) {
		$this->getServiceClosure($serviceName, 'handle', $reload);
	}
	
	
	/**
	 * Creates a closure to invoke a specific method of a service (usually "handle" or "subscribe").
	 * Closures are cached for repeat uses.
	 * 
	 * @param string $serviceName - Name of service to create a closure for.
	 * @param string $methodName - Name of the services method to wrap closure around.
	 * @param bool $reload - True to force the re-creation of the closure, default false.
	 * @return \Closure
	 */
	protected function getServiceClosure($serviceName, $methodName, $reload=false) {
		
		# Only create the closure once, unless reload is true
		if ($reload || isset($this->serviceClosures[$serviceName][$methodName]) === false) {
			
			# Get the service's instance, as well as a methodReflection instance
			$instance = $this->getServiceInstance($serviceName);
			$methodReflection = new \ReflectionMethod($instance, $methodName);
			
			# This is the actual definition of the closure, it gets invoked when the service is called.
			$this->serviceClosures[$serviceName][$methodName] = function($arguments) use ($methodReflection, $instance) {
				
				# Before we invoke the service, ensure the closure was called with an appropriate
				# number of args.
				if (count($arguments) < $methodReflection->getNumberOfRequiredParameters()) {
					throw new ServiceManagerException(['Attempted to invoke service method with too few arguments']);
				}
				
				# Using the methodReflection instance and the service's instance, invoke the
				# service's method.
				return $methodReflection->invokeArgs($instance, $arguments);
			};
		}
		
		# Here we return the defined closure
		return $this->serviceClosures[$serviceName][$methodName];
	}
	
	
	/**
	 * Creates an instance of a defined service so it's methods may be invoked. Instances are
	 * cached for repeat uses.
	 * 
	 * @param string $serviceName - Name of the service to get the instance of
	 * @return ServiceBase
	 * @throws ServiceManagerException - If dispatcher returns an unkown class or a class which
	 * doesn't inherit ServiceBase
	 */
	protected function getServiceInstance($serviceName) {
		
		# Only create the instance once
		if (isset($this->serviceInstances[$serviceName]) === false) {
			
			# Resolve the class name from the service name
			$className = $this->resolveClassName($serviceName);
			
			# Ensure the class exists
			if (class_exists($className) === false) {
				throw new ServiceManagerException(['Attempted to load unkown class (%s) for service (%s)', $className, $serviceName]);
			}
			
			# Create the instance, passing ServiceManager into the construct per the ServiceBase spec.
			$instance = new $className($this);
			
			# Ensure isntance inherits ServiceBase
			if ($instance instanceof ServiceBase) {
				$this->serviceInstances[$serviceName] = $instance;
			} else {
				throw new ServiceManagerException(['Attempted to load invalid class (%s) for service (%s) - must implement ServiceBase', $className, $serviceName]);
			}
		}
		
		# return the instance
		return $this->serviceInstances[$serviceName];
	}
	
	
	/**
	 * Adds subscriber services to a service name, supports wildcards (*).
	 * 
	 * @param array $subscribers
	 */
	public function addSubscribers(array $subscribers) {
		
		foreach($subscribers as $serviceName => $serviceSubscribers) {
			
			if (isset($this->subscribers[$serviceName]) === false) {
				$this->subscribers[$serviceName] = [];
			}

			foreach($serviceSubscribers as $subscriberServiceName) {
				$this->subscribers[$serviceName][] = $subscriberServiceName;
			}
		}
	}
	
	
	/**
	 * Return the matching className for a given serviceName, uses the dispatcher closure passed to
	 * the construct.
	 * 
	 * @param string $serviceName - name of the service to return a class name of.
	 * @return string - the name of the service's matching class
	 */
	protected function resolveClassName($serviceName) {
		
		if (isset($this->classNames[$serviceName]) === false) {
			$dispatcher = $this->dispatcher;
			$this->classNames[$serviceName] = $dispatcher($serviceName);
		}
		return $this->classNames[$serviceName];
	}
}