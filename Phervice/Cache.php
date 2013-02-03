<?php

namespace Phervice;

class Cache {
	
	
	protected $observerMap = array();
	
	
	protected $propertyAnnotations = array();
	
	
	protected $methodAnnotations = array();



	public function getObserverMap($serviceName) {
		if (isset($this->observerMap[$serviceName])) {
			return $this->observerMap[$serviceName];
		} else {
			return null;
		}
	}
	
	
	public function setObserverMap($serviceName, array $observerMap) {
		$this->observerMap[$serviceName] = $observerMap;
	}
	
	
	public function getPropertyAnnotations(\ReflectionProperty $reflectionProperty) {
		$propertyName = "{$reflectionProperty->class}::{$reflectionProperty->name}";
		
		if (isset($this->propertyAnnotations[$propertyName])) {
			return $this->propertyAnnotations[$propertyName];
		} else {
			return null;
		}
	}
	
	
	public function setPropertyAnnotations(\ReflectionProperty $reflectionProperty, array $annotations) {
		$propertyName = "{$reflectionProperty->class}::{$reflectionProperty->name}";
		$this->propertyAnnotations[$propertyName] = $annotations;
	}
	
	
	public function getMethodAnnotations(\ReflectionMethod $reflectionMethod) {
		$methodName = "{$reflectionMethod->class}::{$reflectionMethod->name}";
		
		if (isset($this->methodAnnotations[$methodName])) {
			return $this->methodAnnotations[$methodName];
		} else {
			return null;
		}
	}
	
	
	public function setMethodAnnotations(\ReflectionMethod $reflectionMethod, array $annotations) {
		$methodName = "{$reflectionMethod->class}::{$reflectionMethod->name}";
		$this->methodAnnotations[$methodName] = $annotations;
	}
}