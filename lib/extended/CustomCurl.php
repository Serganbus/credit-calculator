<?php

/**
 * PHP Wrapper For Custom Console Curl
 *
 * @author efureev
 */
class CustomCurl
{
	const VERSION = '0.3';

	const DEFAULT_TIMEOUT = 30;

	const METHOD_REQUEST_POST = 'POST';
	const METHOD_REQUEST_GET = 'GET';
	const METHOD_REQUEST_DELETE = 'DELETE';
	const METHOD_REQUEST_OPTIONS = 'OPTIONS';

	private $_id = null;
	private $_baseUrl = null;
	private $_curl = null;
	private $_defaultCurl = 'curl';

	private $_methodRequest = null;

	/** @var bool Details */
	private $_verbose = false;

	private $_file = null;

	private $_body = null;

	private $_userAgent = null;

	public $url = null;

	private $_cmd = '';

	/** @var null|array  */
	private $_requestHeaders = [];


	private $_completeFn = null;
	private $_beforeSendFn = null;

	/** @var null|string  */
	private $_response = null;
	/** @var null|array  */
	private $_rawResponse = null;


	public function __construct($base_url = null)
	{
		$this->_id = 1;
		$this->setURL($base_url);
		$this->_curl = $this->_defaultCurl;
		$this->_methodRequest = self::METHOD_REQUEST_GET;
		$this->setDefaultUserAgent();
	}


	/**
	 * @param string $url
	 * @param array $data
	 * @return $this
	 */
	public function setURL($url, $data = array())
	{
		$this->_baseUrl = $url;
		$this->url = $this->_buildURL($url, $data);
		return $this;
	}

	/**
	 * @param string $path
	 * @return $this
	 */
	public function setCurl($path)
	{
		$this->_curl = $path;
		return $this;
	}

	/**
	 * @param $key
	 * @param $value
	 * @return $this
	 */
	public function setHeader($key, $value)
	{
		$this->_requestHeaders[$key] = $value;
		return $this;
	}

	/**
	 * @param array $headers
	 * @return $this
	 */
	public function setHeaders(array $headers)
	{
		foreach ($headers as $key => $value) {
			$this->setHeader($key, $value);
		}
		return $this;
	}

	public function setBody($body)
	{
		$this->_methodRequest = self::METHOD_REQUEST_POST;
		$this->_body = $body;
		return $this;
	}

	public function setJson($json)
	{
		$json = json_encode($json);
		$this->setBody($json);
		return $this;
	}

	public function sendFile($pathToFile)
	{
		if (file_exists($pathToFile)) {
			$this->_methodRequest = self::METHOD_REQUEST_POST;
			$this->_file = $pathToFile;
		}
		return $this;
	}

	public function setDefaultUserAgent()
	{
		$userAgent = 'Custom-Curl/' . self::VERSION . ' (+https://github.com/efureev/custom-curl)';
		$userAgent .= ' PHP/' . PHP_VERSION;
		$curlInfo = $this->getCurlInfo();
		$userAgent .= ' curl/' . $curlInfo['info']['1'];
		$this->setUserAgent($userAgent);
	}

	public function setUserAgent($userAgent)
	{
		$this->_userAgent = $userAgent;
		return $this;
	}

	/**
	 * Get curl's lib info
	 *
	 * @return array
	 */
	private function getCurlInfo()
	{
		$cmd = $this->_curl . ' -V';
		exec($cmd, $output);

		$features = preg_replace('/Features: /','',$output[2]);
		$features = explode(' ',$features);

		$protocols = preg_replace('/Protocols:: /','',$output[1]);
		$protocols = explode(' ',$protocols);

		$info = explode(' ',$output[0]);

		return [
			'info' => $info,
			'features' => $features,
			'protocols' => $protocols,
		];

	}

	/**
	 * Headers to inline
	 * @return string
	 */
	private function _inlineHeaders()
	{
		$line = array();
		foreach($this->_requestHeaders as $key => $value) {
			$value = addslashes($value);
			$line[] = '-H "'.$key.':'.$value.'"';
		}

		return implode(' ',$line);
	}

	/**
	 * @return string
	 */
	private function _inlineVerbose()
	{
		return $this->_verbose ? '-v' : '';
	}

	private function _inlineUserAgent()
	{
		return $this->_userAgent ? '-A "'.$this->_userAgent.'"' : '';
	}

	/**
	 * @return string
	 */
	private function _inlineMethodRequest()
	{
		return '-X ' .$this->_methodRequest;
	}

	/**
	 * @return string
	 */
	private function _inlineFile()
	{
		return $this->_file !== null ? '-d @' .$this->_file : '';
	}

	/**
	 * @return string
	 */
	private function _inlineBody()
	{
		return $this->_body !== null ? '-d "' .addslashes($this->_body).'"' : '';
	}

	/**
	 * @param null $url
	 * @return $this
	 * @throws \Exception
	 */
	public function request($url = null)
	{
		$this->_buildCommand();

		$this->beforeExec();
		$this->exec($url);
		$this->afterExec();
		return $this;
	}

	/**
	 * @param null $url
	 * @return CustomCurl
	 */
	public function post($url = null)
	{
		$this->_methodRequest = self::METHOD_REQUEST_POST;
		return $this->request($url);
	}

	public function get($url = null)
	{
		$this->_methodRequest = self::METHOD_REQUEST_GET;
		return $this->request($url);
	}

	public function delete($url = null)
	{
		$this->_methodRequest = self::METHOD_REQUEST_DELETE;
		return $this->request($url);
	}

	public function options($url = null)
	{
		$this->_methodRequest = self::METHOD_REQUEST_OPTIONS;
		return $this->request($url);
	}

	public function beforeExec()
	{
		$this->call($this->_beforeSendFn);
	}

	public function afterExec()
	{
		$this->_response = implode(PHP_EOL,$this->_rawResponse);
		$this->call($this->_completeFn);
	}

	/**
	 * @param null $url
	 * @throws \Exception
	 */
	public function exec($url = null)
	{
		if (!empty($this->url)) {
			$cmd = $this->_finalBuildCommand($this->url);
		} else {
			if ($url !== null) {
				$cmd = $this->_finalBuildCommand($url);
			} else
				throw new \Exception ('Missing URL');
		}

		exec($cmd, $this->_rawResponse);
	}

	/**
	 * @return null|string
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * @return null|string
	 */
	public function getResponseJson()
	{
		if (empty($this->_response))
			return null;
		return self::decode($this->_response);
	}

	/**
	 * @param string|null $response
	 * @return $this
	 */
	public function setResponse($response)
	{
		$this->_response = $response;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function enableDebug()
	{
		$this->_verbose = true;
		return  $this;
	}

	/**
	 * @param bool|true $full
	 * @return string
	 */
	public function getCmd($full = true)
	{
		$this->_buildCommand();
		return $full ? $this->_finalBuildCommand($this->url) : $this->_cmd;
	}

	/**
	 * @param string $url
	 * @param array $data
	 * @return string
	 */
	private function _buildURL($url, $data = array())
	{
		return $url . (empty($data) ? '' : '?' . http_build_query($data));
	}

	private function _buildCommand()
	{
		$cmd[] = $this->_curl;

		$cmd[] = $this->_inlineMethodRequest();
		$cmd[] = $this->_inlineUserAgent();
		$cmd[] = $this->_inlineHeaders();
		$cmd[] = $this->_inlineFile();
		$cmd[] = $this->_inlineBody();
		$cmd[] = $this->_inlineVerbose();
		$cmd = array_filter($cmd);

		$this->_cmd = implode(' ', $cmd);
	}

	/**
	 * @param $url
	 * @return string
	 */
	private function _finalBuildCommand($url)
	{
		return $this->_cmd . ' ' . $url;
	}

	public function complete(callable $callback)
	{
		$this->_completeFn = $callback;
		return $this;
	}

	public function beforeSend(callable $callback)
	{
		$this->_beforeSendFn = $callback;
	}

	/**
	 *
	 */
	public function call()
	{
		$args = func_get_args();
		$function = array_shift($args);
		if (is_callable($function)) {
			array_unshift($args, $this);
			call_user_func_array($function, $args);
		}
	}

	protected static function decode($json, $asArray = true)
	{
		if (is_array($json)) {
			throw new \Exception('Invalid JSON data.');
		}
		$decode = json_decode((string) $json, $asArray);
		static::handleJsonError(json_last_error());

		return $decode;
	}

	protected static function handleJsonError($lastError)
	{
		switch ($lastError) {
			case JSON_ERROR_NONE:
				break;
			case JSON_ERROR_DEPTH:
				throw new \Exception('The maximum stack depth has been exceeded.');
			case JSON_ERROR_CTRL_CHAR:
				throw new \Exception('Control character error, possibly incorrectly encoded.');
			case JSON_ERROR_SYNTAX:
				throw new \Exception('Syntax error.');
			case JSON_ERROR_STATE_MISMATCH:
				throw new \Exception('Invalid or malformed JSON.');
			case JSON_ERROR_UTF8:
				throw new \Exception('Malformed UTF-8 characters, possibly incorrectly encoded.');
			default:
				throw new \Exception('Unknown JSON decoding error.');
		}
	}
}