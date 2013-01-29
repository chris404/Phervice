<?php

namespace Phervice;



/**
 * Provides a "dict" type, allowing safer access to data then an assoc array.
 */
class Dict {
	
	
	/**
	 * Underlying array holding the Dict's data.
	 * 
	 * @var array
	 */
	protected $_data = [];
	
	
	/**
	 * Creates new Dict using the supplied data.
	 * 
	 * @param array $data - Array to create the Dict with.
	 */
	public function __construct(array $data=array()) {
		$this->_data = $data;
	}
	
	
	/**
	 * Magic getter for Dict, throws exception if name is not found.
	 * 
	 * @param string $name - Name/key of the data to get.
	 * @return mixed - Value of the passed name.
	 * @throws DictException 
	 */
	public function __get($name) {
		if (isset($this->_data[$name])) {
			return $this->_data[$name];
		} else {
			throw new DictException;
		}
	}
	
	
	/**
	 * Magic setter for Dict. Simply adds/replaces data of the passed name.
	 * 
	 * @param string $name - Name of data to update.
	 * @param mixed $value - Actual value to be updated.
	 */
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
	
	/**
	 * Another getter, like the magic one but this getter forces a default value as a 2nd parameter
	 * and uses that value if no data is set with the passed name.
	 * 
	 * @param string $name - Name of the value to get.
	 * @param mixed $default - Value to use if the named value does not exist.
	 * @return mixed - Either the named value if exists, or the passed default value.
	 */
	public function get($name, $default) {
		if (isset($this->_data[$name])) {
			return $this->_data[$name];
		} else {
			return $default;
		}
	}
	
	
	/**
	 * Checks if the passed name has a value in the Dict.
	 * 
	 * @param string $name - Name of the value to check.
	 * @return boolean - True if named value is set, false otherwise.
	 */
	public function has($name) {
		return isset($this->_data[$name]);
	}
	
	
	/**
	 * Checks if the passed value has an entry in the Dict. Note this method only checks if the
	 * value exists, it does not give the key/name of the value.
	 * 
	 * @param mixed $value - Value to check if exists in Dict.
	 * @return boolean - True if the value exists in the Dict, false if it does not.
	 */
	public function hasValue($value) {
		return in_array($value, $this->_data);
	}
	
	
	/**
	 * Returns a copy of the Dict in the form of an array.
	 * 
	 * @return array - Copy of Dict as an array.
	 */
	public function data() {
		return $this->_data;
	}
}