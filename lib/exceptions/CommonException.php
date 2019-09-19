<?php

namespace admin\lib\exceptions;

use admin\lib\helpers\ArrayHelper;

class CommonException extends \Exception
{
	protected $telegram = false;

	protected $db = true;

	protected $_data = null;

	public function getName()
	{
		return 'Общая ошибка';
	}

	/**
	 * @param null $message
	 * @param int $code
	 * @param null|false|string $bodyText Тест ошибки. Если NULL, то текст берется из основного
	 *                                       сообщения, если FALSE, то не отправляется в Telegram
	 * @param array|string|null $data Данные для вывода в логах ошибки
	 * @param bool|true $db записывать ли ошибку в бд
	 * @param \Exception|null $previous
	 */
	public function __construct($message = null, $code = 0, $bodyText = null, $data = null, $db = true, \Exception $previous = null)
	{
		if ($data !== null)
			$this->setData($data);

		parent::__construct($this->getName() . ($message === null ? '' : ': ' . $message), $this->code, $previous);

		if ($bodyText !== false) {

			if ($this->telegram && $bodyText === null)
				$bodyText = $this->getMessage();

			$this->setData([
				'file' => $this->getFile(),
				'line' => $this->getLine()
			]);

			if (empty($bodyText)) {
				$bodyText = self::dataToTelegramStr();
			}

			(new \Telegram())->send($bodyText);
		}

		$this->db = $db;

		if ($this->db) {
			$this->saveToDb();
		}
	}

	/**
	 * @return string
	 */
	public function dataToTelegramStr()
	{
		$str = 'HOST: ' . $_SERVER['SERVER_NAME'] . PHP_EOL .
			'ERROR: ' . $this->_data['error'] . PHP_EOL .
			'TIME: ' . date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . PHP_EOL .
			'FILE: ' . $this->_data['file'] . ':' . $this->_data['line'];

		if (array_key_exists('data', $this->_data) && is_array($this->_data['data'])) {
			$str .= PHP_EOL . 'DATA: ' . PHP_EOL;
			$str .= self::array2Str($this->_data['data']);
		}

		return $str;
	}

	public function saveToDb()
	{
		$text = 'ERROR: ' . $this->_data['error'] . PHP_EOL . 'FILE: ' . $this->_data['file'] . ':' . $this->_data['line'];
		\TechLog::add($text, $type = \TechLog::TYPE_EXCEPTION, 'exception[' . __CLASS__ . ']');
	}

	/**
	 * @param array $array
	 * @return string
	 */
	private static function array2Str(array $array)
	{
		$str = '';
		foreach ($array as $k => $v) {
			if (is_array($v))
				$v = self::array2Str($v);
			$str .= $k . ' -> ' . $v . PHP_EOL;
		}
		return $str;
	}

	public function getData()
	{
		return $this->_data;
	}

	protected function setData($data)
	{
		if ($this->_data === null) {
			if (is_string($data))
				$this->_data = $data;
			elseif (is_array($data))
				$this->_data = $data;
		} else {
			$this->_data = ArrayHelper::merge($this->_data, $data);
		}

		if (array_key_exists('error', $this->_data) && empty($this->_data['error'])) ;
		$this->_data['error'] = $this->getMessage();
	}

}