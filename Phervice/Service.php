<?php

namespace Phervice;


use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;


class Service {
	
	
	/**
	 *
	 * @var ReflectionClass
	 */
	protected $reflectionClass;
	
	
	/**
	 *
	 * @var mixed
	 */
	protected $instance;
	
	
	/**
	 *
	 * @var string
	 */
	protected $className;
	
	
	/**
	 *
	 * @var Container
	 */
	protected $container;
	
	
	/**
	 *
	 * @var array
	 */
	protected $propertyAnnotations;
	
	
	/**
	 *
	 * @var array
	 */
	protected $methodAnnotations;
	
	
	/**
	 *
	 * @var array
	 */
	protected $constructAnnotations;


	/**
	 * 
	 * @param type $className
	 * @param \Phervice\Container $container
	 */
	public function __construct($className, Container $container) {
		$this->className = $className;
		$this->container = $container;
		$this->getInstance();
	}
	
	
	public function __sleep() {
		
		if ($this->getInstance() instanceof SerializableService) {
			return array('className', 'container', 'instance');
			
		} else {
			return array('className', 'container', 'propertyAnnotations', 'methodAnnotations', 'constructAnnotations');
		}
	}
	
	
	public function handle(array $args) {
		$callable = array($this->getInstance(), 'handle');
		return call_user_func_array($callable, $args);
	}
	
	
	protected function getReflectionClass() {
		if ($this->reflectionClass === false) {
			try {
				$this->reflectionClass = new ReflectionClass($this->className);
			} catch(ReflectionException $e) {
				throw new Exception($e->getMessage());
			}
		}
		return $this->reflectionClass;
	}
	
	
	protected function getInstance() {
		
		if ($this->instance === null) {
			
			$reflectionClass = $this->getReflectionClass();
			
			if ($reflectionClass->hasMethod('__construct')) {
				$this->getMethodAnnotations();
				$args = $this->buildArgs($this->constructAnnotations);
				$reflectionConstruct = $reflectionClass->getMethod('__construct');
				$this->instance = $reflectionConstruct->newInstanceArgs($args);

			} else {
				$this->instance = new $this->className;
			}

			$this->initProperties();
			$this->initMethods();
		}
		return $this->instance;
	}
	

	protected function initProperties() {
		$reflectionClass = $this->getReflectionClass();
		$instance = $this->getInstance();
		
		foreach($this->getPropertyAnnotations() as $propertName => $annotation) {
			$propertyValue = $this->buildArgs(array($annotation));
			$reflectionProperty = $reflectionClass->getProperty($propertName);
			$reflectionProperty->setAccessible(true);
			$reflectionProperty->setValue($instance, $propertyValue);
		}
	}
	
	
	protected function initMethods() {
		$reflectionClass = $this->getReflectionClass();
		$instance = $this->getInstance();
		
		foreach($this->getMethodAnnotations() as $methodName => $annotations) {
			$args = $this->buildArgs($annotations);
			$reflectionMethod = $reflectionClass->getMethod($methodName);
			$reflectionMethod->invokeArgs($instance, $args);
		}
	}
	
	
	protected function buildArgs(array $annotations) {
		$args = array();
		foreach($annotations as $annotation) {

			if ($annotation['name']) {
				$result = $this->container->execArgs($annotation['name'], $annotation['args']);
			} else {
				$result = $this->container;
			}

			if ($annotation['type'] == 'param') {
				$args[] = $result;
			} elseif ($annotation['type'] == 'set') {
				return $result;
			}
		}
		return $args;
	}


	protected function getPropertyAnnotations() {
		
		if ($this->propertyAnnotations === null) {
			$this->propertyAnnotations = array();
			
			/* @var $reflectionProperty ReflectionProperty */
			foreach($this->getReflectionClass()->getProperties() as $reflectionProperty) {
				$annotations = $this->parsePropertyAnnotations($reflectionProperty);
				if ($annotations) {
					$this->propertyAnnotations[$reflectionProperty->getName()] = $annotations;
				}
			}
		}
		return $this->propertyAnnotations;
	}
	
	
	protected function parsePropertyAnnotations(ReflectionProperty $reflectionProperty) {
		
		$docString = $reflectionProperty->getDocComment();
		if ($docString && preg_match('/ @set ~([\w\.]*)(?:\(([^)]*)\))?/', $docString, $annotation)) {

			list(, $name, $argString) = $annotation + array('', '', '');

			$args = $this->resolveArgString($argString, $reflectionProperty);

			return array(
				'type' => 'set',
				'name' => $name,
				'args' => $args
			);
		}
		
		return false;
	}
	
	
	protected function getMethodAnnotations() {
		
		if ($this->methodAnnotations === null) {
			$this->methodAnnotations = array();
		
			/* @var $reflectionMethod ReflectionMethod */
			foreach($this->getReflectionClass()->getMethods() as $reflectionMethod) {
				
				$methodName = $reflectionMethod->getName();
				if (strpos($methodName, 'init') === 0) {
					$annotations = $this->parseMethodAnnotations($reflectionMethod);
					$this->methodAnnotations[$reflectionMethod->getName()] = $annotations;
					
				} elseif ($methodName == '__construct') {
					$annotations = $this->parseMethodAnnotations($reflectionMethod);
					$this->constructAnnotations = $annotations;
				}
			}
		}
		return $this->methodAnnotations;
	}
	
	
	protected function parseMethodAnnotations(ReflectionMethod $reflectionMethod) {
		
		$docString = $reflectionMethod->getDocComment();
		if ($docString) {
			preg_match_all('/ @((?:init)|(?:param))(?: \w+(?: \$?\w+)?)? ~([\w\.]*)(?:\((.*)\))?/', $docString, $matches, PREG_SET_ORDER);
		} else {
			$matches = array();
		}

		$annotations = array();
		foreach($matches as $annotation) {
			list(, $type, $name, $argString) = $annotation + array('', '', '', '');

			$args = $this->resolveArgString($argString, $reflectionMethod);

			$annotations[] = array(
				'type' => $type,
				'name' => $name,
				'args' => $args
			);
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