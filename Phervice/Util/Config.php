<?php

namespace Phervice\Util;



/**
 * Allows reading and fetching data from ini files.
 * 
 * @see Config::__call()
 */
class Config {
	
	
	/**
	 * Holds assoc array from parsed ini file. Output of parse_ini_file().
	 * 
	 * @var array
	 */
	protected $_data = [];
	
	
	
	/**
	 * Creates new config instance, loads the passed path's ini data.
	 * 
	 * @param string $path
	 * @throws ExceptionConfig - If a provided path is not found, or an ini file is malformed.
	 */
	public function __construct (array $paths) {
		
		# iterate over all paths
		foreach($paths as $path) {
			
			# ensure the file exists
			if (file_exists($path) === false) {
				throw new ExceptionConfig(['Unkown config file path (%s)', $path]);
			}
			
			# load the file and parse ini data...
			$iniData = parse_ini_file($path, true);
			
			# ensure it has valid ini
			if (is_array($iniData) === false) {
				throw new ExceptionConfig(['Malformed config file data (%s)', $path]);
			}
			
			# iterate over all the data...
			foreach($iniData as $namespace => $values) {
				
				# if this is the first use of the namespace create a new array
				if (isset($this->_data[$namespace]) === false) {
					$this->_data[$namespace] = [];
				}
				
				# add the values to the namespace
				$this->_data[$namespace] += $values;
			}
		}
	}
	
	
	/**
	 * Allows values to be pulled from the config data. Method name is used to denote namespace,
	 * and args are used for key names.
	 * 
	 * Given the following ini file:
	 * [database]
	 * username='me'
	 * password='shhhh'
	 * dbname='mine'
	 * 
	 * The data could be retrieved like this:
	 * list($user, $passwd, $dbname) = $config->database('username','password','dbname');
	 * 
	 * @param string $name - Ini namespace to locate args for.
	 * @param array $args - Array of keys under the namespace to return values for.
	 * @return array - Array of values matching the passed args.
	 * @throws ExceptionConfig 
	 */
	public function __call($name, $args) {
		
		# Ensure the namespace is set, otherwise throw.
		if (isset($this->_data[$name]) === false) {
			throw new ExceptionConfig(['Invalid config section (%s)', $name]);
		}
		
		# Local copy of the data for ease of use.
		$data = $this->_data[$name];
		
		# Build list of values from the passed args
		$output = [];
		foreach($args as $configKey) {
			
			# throw exception if any args are not found
			if(isset($data[$configKey]) === false) {
				throw new ExceptionConfig(['Invalid config key (%s) (%s)', $name, $configKey]);
			}
			
			$output[] = $data[$configKey];
		}
		
		return $output;
	}
}