<?php

namespace Phervice;

class Service {
	
	
	/**
	 *
	 * @var Container
	 */
	protected $container;
	
	
	/**
	 *
	 * @var type 
	 */
	protected $instance;
	
	
	/**
	 *
	 * @var \ReflectionHandle
	 */
	protected $reflectionHandle;

		
	/**
	 * 
	 * @param type $className
	 * @param \Phervice\Container $container
	 */
	public function __construct($className, Container $container) {
		
		$this->container = $container;
		
		try {
			$reflectionClass = new \ReflectionClass($className);
		} catch(\ReflectionException $e) {
			throw new Exception($e->getMessage());
		}
		
		// create instance
		if ($reflectionClass->hasMethod('__construct')) {
			$reflectionConstruct = $reflectionClass->getMethod('__construct');
			$args = $this->prepMethod($reflectionConstruct);
			$this->instance = $reflectionClass->newInstanceArgs($args);
			
		} else {
			$this->instance = new $className;
		}
		
		
		// set any pre-set properties
		/* @var $reflectionProperty \ReflectionProperty */
		foreach($reflectionClass->getProperties() as $reflectionProperty) {
			$this->setProperty($reflectionProperty);
		}
		
		// invoke any init methods
		/* @var $reflectionMethod \ReflectionMethod */
		foreach($reflectionClass->getMethods() as $reflectionMethod) {
			if (strpos($reflectionMethod->getName(), 'init') === 0) {
				$args = $this->prepMethod($reflectionMethod);
				$reflectionMethod->invokeArgs($this->instance, $args);
			}
		}
		
		$this->reflectionHandle = $reflectionClass->getMethod('handle');
	}
	
	
	public function handle(array $args) {
		return $this->reflectionHandle->invokeArgs($this->instance, $args);
	}
	
	

	protected function setProperty(\ReflectionProperty $reflectionProperty) {
		
		$cache = $this->container->getCache();
		$annotations = $cache->getPropertyAnnotations($reflectionProperty);
		
		if ($annotations === null) {
			$annotations = $this->buildPropertyAnnotations($reflectionProperty);
			$cache->setPropertyAnnotations($reflectionProperty, $annotations);
		}
		
		if (is_array($annotations)) {
			list($name, $args) = $annotations;

			if ($name) {
				$propertyValue = $this->container->execArgs($name, $args);
			} else {
				$propertyValue = $this->container;
			}

			$reflectionProperty->setAccessible(true);
			$reflectionProperty->setValue($this->instance, $propertyValue);
		}
	}
	
	
	
	protected function buildPropertyAnnotations(\ReflectionProperty $reflectionProperty) {
		
		$docString = $reflectionProperty->getDocComment();
		if ($docString && preg_match('/ @set ~([\w\.]*)(?:\(([^)]*)\))?/', $docString, $annotation)) {

			list(, $name, $argString) = $annotation + array('', '', '');

			$args = $this->resolveArgString($argString, $reflectionProperty);

			return array($name, $args);
		}
		
		return false;
	}



	protected function prepMethod(\ReflectionMethod $reflectionMethod) {
		
		$reflectionMethod->setAccessible(true);
		
		$cache = $this->container->getCache();
		$annotations = $cache->getMethodAnnotations($reflectionMethod);
		
		if ($annotations === null) {
			$annotations = $this->buildMethodAnnotations($reflectionMethod);
			$cache->setMethodAnnotations($reflectionMethod, $annotations);
		}
		
		$params = array();
		foreach($annotations as $annotation) {
			list($type, $name, $args) = $annotation;
			
			if ($type == 'init') {
				$this->container->execArgs($name, $args);
				
			} elseif ($type == 'param') {
				
				if ($name) {
					$params[] = $this->container->execArgs($name, $args);
				} else {
					$params[] = $this->container;
				}
			}
		}
		
		return $params;
	}
	
	
	protected function buildMethodAnnotations(\ReflectionMethod $reflectionMethod) {
		
		$docString = $reflectionMethod->getDocComment();
		if ($docString) {
			preg_match_all('/ @((?:init)|(?:param))(?: \w+)? ~([\w\.]*)(?:\((.*)\))?/', $docString, $matches, PREG_SET_ORDER);
		} else {
			$matches = array();
		}

		$annotations = array();
		foreach($matches as $annotation) {
			list(, $type, $name, $argString) = $annotation + array('', '', '', '');

			$args = $this->resolveArgString($argString, $reflectionMethod);

			$annotations[] = array($type, $name, $args);
		}
		
		return $annotations;
	}
	
	
	/**
	 * 
	 * @param string $argString
	 * @return array
	 */
	protected function resolveArgString($argString, $reflection) {
		$args = json_decode("[$argString]", true);
		
		if (is_array($args)) {
			return $args;
		}
		
		throw new Exception(array('Invalid argument signature (%s) on %s::%s', $argString, $reflection->class, $reflection->name));
	}
	
}