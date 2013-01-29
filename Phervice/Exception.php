<?php


namespace Phervice;


class Exception extends \Exception {
	public function __construct($message=null, $code=null, $previous=null) {
		if (is_array($message)) {
			$message = vsprintf(array_shift($message), $message);
		}
		parent::__construct($message, $code, $previous);
	}
}