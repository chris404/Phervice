<?php

namespace Phervice;


/**
 * Very basic log class, maybe it will get more interesting with age (like people).
 */
class Log {
	
	/**
	 * Dynamic logging method, the name of the invoked method will be prefixed before each commented
	 * line, each argument will be var_dump'ed individually.
	 * 
	 * @param string $name - The invoked method, prefixes log entry
	 * @param array $args - Array of passed args, each is dumped to log
	 */
	static public function __callStatic($name, $args) {
		ob_start();
		foreach($args as $arg) {
			echo "[$name] ";
			var_dump($arg);
		}
		error_log(ob_get_clean());
	}
	
}