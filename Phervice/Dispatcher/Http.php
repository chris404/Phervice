<?php

namespace Phervice\Dispatcher;



class Http {
	
	/**
	 * Instance used in singleton pattern.
	 * 
	 * @var Http
	 */
	static protected $instance;

	
	/**
	 * Request's host.
	 * 
	 * @var string
	 */
	protected $host = '';
	
	
	/**
	 * Request's port.
	 * 
	 * @var int
	 */
	protected $port = 0;
	
	
	/**
	 * Request's method.
	 * 
	 * @var string
	 */
	protected $method = '';
	
	
	/**
	 * Request's uri.
	 * 
	 * @var string
	 */
	protected $uri = '';
	
	
	/**
	 * Request's referer.
	 * 
	 * @var string
	 */
	protected $referer = '';
	
	
	/**
	 * Request's raw, encoded input.
	 * 
	 * @var string
	 */
	protected $rawInput = '';
	
	
	/**
	 * Request's content type.
	 * 
	 * @var string
	 */
	protected $type = '';
	
	
	/**
	 * Request's decoded input data.
	 * 
	 * @var array
	 */
	protected $input = null;
	
	
	/**
	 * Request's user.
	 * 
	 * @var string
	 */
	protected $user = false;
	
	
	/**
	 * Request's password.
	 * 
	 * @var string
	 */
	protected $password = false;
	
	
	/**
	 * Request's controller service name.
	 * 
	 * @var string
	 */
	protected $controllerName;
	
	
	/**
	 * Request's output service name.
	 * 
	 * @var string
	 */
	protected $outputName;
	
	
	/**
	 * Exception service name.
	 * 
	 * @var string
	 */
	protected $exceptionName;


	/**
	 * Request's format.
	 * 
	 * @var string
	 */
	protected $format;
	
	
	/**
	 * Request's args.
	 * 
	 * @var array
	 */
	protected $args;
	
	
	/**
	 * Index array of callables to execute during post dispatch
	 * 
	 * @var array
	 */
	protected $tails = [];
	
	
	/**
	 * 
	 * @return Http
	 */
	static public function getInstance() {
		if (static::$instance === null) {
			static::$instance = new static;
		}
		return static::$instance;
	}


	protected function __construct() {
		
		if (static::$instance) {
			throw new HttpException('Attempted to re-instantiate a singleton.', 500);
		}

		$this->_buildEnvironment();
	}
	
	
	/**
	 * Defines various typical HTTP request data.
	 */
	protected function _buildEnvironment() {
		# Basic request data.
		list($this->host) = explode(':', $_SERVER['HTTP_HOST']);
		$this->port = (int)$_SERVER['SERVER_PORT'];
		$this->method = strtolower($_SERVER['REQUEST_METHOD']);
		$this->uri = $_SERVER['REQUEST_URI'];
		$this->referer = isset($_SERVER['REQUEST_REFERER'])? $_SERVER['REQUEST_REFERER']: '';
		
		# Raw input, used on puts and non-urlformencoded requests.
		$this->rawInput = file_get_contents("php://input");
		
		# Captures user and pw if they are supplied.
		if (isset($_SERVER['PHP_AUTH_USER'])  &&  isset($_SERVER['PHP_AUTH_PW'])) {
			$this->user = $_SERVER['PHP_AUTH_USER'];
			$this->password = $_SERVER['PHP_AUTH_PW'];
		}
		
		# Parse the actual body of the request based on content-type, default to urlformencoded.
		if (isset($_SERVER['CONTENT_TYPE']) && strtolower($_SERVER['CONTENT_TYPE']) == 'application/json') {
			$this->type = 'json';
			$this->input = $this->parseJsonData();
		} else {
			$this->type = 'form';
			$this->input = $this->parseFormData();
		}
	}
	
	
	/**
	 * Parses the raw input as a JSON encoded string.
	 * 
	 * @return array - Request body.
	 */
	public function parseJsonData() {
		return json_decode($this->getRawInput(), true);
	}
	
	
	/**
	 * Determines the body based off standard HTTP method.
	 * 
	 * @return array - Request body.
	 */
	public function parseFormData() {
		
		if ($this->getMethod() === 'GET') {
			$data = $_GET;
			
		} elseif ($this->getMethod() === 'POST') {
			$data = $_POST;
			
		} elseif ($this->getMethod() === 'PUT') {
			parse_str($this->getRawInput(), $data);
			
		} elseif ($this->getMethod() === 'DELETE') {
			parse_str($this->getRawInput(), $data);
			
		} else {
			$data = [];
		}
		
		return $data;
	}
	
	
	/**
	 * Preps the dispatcher by populating the Dispatcher::$_params array. Throws exception if any
	 * required params are not set.
	 * 
	 * @throws HttpException 
	 */
	public function preDispatch(\Closure $preDispatch) {
		
		$boundPreDispatch = $preDispatch->bindTo($this, $this);
		$boundPreDispatch();
		
		# Ensure that the following required params are set.
		if (isset($this->controllerName) === false) {
			throw new HttpException('No controllerName logic defined', 500);
		}
		
		if (isset($this->format) === false) {
			throw new HttpException('No format defined', 500);
		}
		
		if (isset($this->args) === false) {
			throw new HttpException('No args defined', 500);
		}
	}
	
	
	/**
	 * 
	 * @throws DispatcherException 
	 */
	public function dispatch(\Phervice\ServiceManager $service) {
		try {
			
			try {
				$service->initService($this->controllerName);
			} catch (\Phervice\ServiceManagerException $e) {
				throw new HttpException(['Attempted to load unknown controller service (%s)', $this->controllerName], 404);
			}
			
			$outputData = $service->exec(
				$this->controllerName,
				$this->args,
				$this->input
			);
			
			$service->exec($this->outputName, [$this], $outputData);
			
		# If any exceptions boil up to the top of this execution stack (which is this method by the
		# way) send them off to the ExceptionHandler.
		} catch (\Exception $e) {
			$service->exec($this->exceptionName, [$e, $this]);
		}
		
		# Finish the request, this allows the app to process data after the client has gone.
		fastcgi_finish_request();
		
		# Invoke any dispatch tails.
		foreach($this->tails as $tail) {
			$tail();
		}
	}
	
	
	public function addTail(\Closure $tail) {
		$this->tails[] = $tail;
	}
	
	
	public function redirect($url) {
		header("Location: $url", true, 303);
	}
	
	
	/**
	 * Getter for the requests's host.
	 * 
	 * @return string - Request's host.
	 */
	public function getHost() {
		return $this->host;
	}
	
	
	/**
	 * Getter for the requests's port.
	 * 
	 * @return int - Request's port.
	 */
	public function getPort() {
		return $this->port;
	}
	
	
	/**
	 * Getter for the requests's method.
	 * 
	 * @return string - Request's method.
	 */
	public function getMethod() {
		return $this->method;
	}
	
	
	/**
	 * Getter for the requests's uri.
	 * 
	 * @return string - Request's uri.
	 */
	public function getUri() {
		return $this->uri;
	}
	
	
	/**
	 * Getter for the requests's referer.
	 * 
	 * @return string - Request's referer.
	 */
	public function getReferer() {
		return $this->referer;
	}
	
	
	/**
	 * Getter for the requests's raw, encoded input.
	 * 
	 * @return string - Request's input.
	 */
	public function getRawInput() {
		return $this->rawInput;
	}
	
	
	/**
	 * Getter for the requests's content type.
	 * 
	 * @return string - Request's content type.
	 */
	public function getType() {
		return $this->type;
	}
	
	
	/**
	 * Getter for the requests's user.
	 * 
	 * @return string - Request's user.
	 */
	public function getUser() {
		return $this->user;
	}
	
	
	/**
	 * Getter for the requests's password.
	 * 
	 * @return string - Request's password.
	 */
	public function getPassword() {
		return $this->password;
	}
	
	
	/**
	 * Getter for the requests's controller service name.
	 * 
	 * @return string - Request's controller service name.
	 */
	public function getControllerName() {
		return $this->controllerName;
	}
	
	
	/**
	 * Getter for the requests's output service name.
	 * 
	 * @return string - Request's output service name.
	 */
	public function getOutputName() {
		return $this->outputName;
	}
	
	
	/**
	 * Getter for the requests's exception service name.
	 * 
	 * @return string - Request's exception service name.
	 */
	public function getExceptionName() {
		return $this->exceptionName;
	}
	
	
	/**
	 * Getter for the requests's format type.
	 * 
	 * @return string - Request's format.
	 */
	public function getFormat() {
		return $this->format;
	}
	
	
	/**
	 * Getter for the requests's args.
	 * 
	 * @return Dict - Request's args.
	 */
	public function getArgs() {
		return $this->args;
	}
	
	
	/**
	 * Generates an HMAC hash for the request using the following format as a "unique request
	 * identifier": 'HTTP_METHOD HTTP_URI RAW_INPUT'
	 * 
	 * @see \hash_hmac()
	 * 
	 * @param string $algorithm - Name of algorithm to use for hash, defaults to 'sha256'.
	 * @param string $key - Shared secret key to use in hash, defaults to ''.
	 * @param bool $binary - If true outputs hash in binary, defaults to false.
	 * @return string - HMAC hash unique to the request.
	 */
	public function getHmac($algorithm='sha256', $key='', $binary=false) {
		
		$data = "{$this->getMethod()} {$this->getUri()} {$this->getRawInput()}";
		
		return hash_hmac($algorithm, $data, $key, $binary);
	}
}