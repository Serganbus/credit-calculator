<?php

/**
 * Class Config
 */
class Config extends Model
{

	private $_data = [];

	private static $_instance = null;

	public static function getInstance(array $params = [])
	{
		if (!static::$_instance)
			static::$_instance = new self($params);
		return static::$_instance;
	}

	public function init()
	{
		parent::init();

		$this->_data = self::indexedColumn('key', 'value');
	}

	/**
	 * @inheritdoc
	 * @table 'config'
	 */
	public static function tableName()
	{
		return 'config';
	}

	public static function getByKey($key)
	{
		if (!static::$_instance)
			static::$_instance = new self();

		return static::$_instance->getData($key);
	}

	public function getData($key = false)
	{
		if ($key === false)
			return $this->_data;

		if ($key === true) {
			return self::indexedList('key', ['value', 'desc']);
		}

		if (isset($this->_data[$key]))
			return $this->_data[$key];

		return null;
	}

	/**
	 * @param $key
	 * @param $value
	 * @param bool|false $insert Вставить в таблицу, если нет такой записи
	 * @return null
	 */
	public function setData($key, $value, $insert = false)
	{
		if (isset($this->_data[$key])) {

			return db()->query('UPDATE `' . self::tableName() . '` SET `value` = :value WHERE `key` = :key', [
				':key' => $key,
				':value' => $value
			]);
		} else {
			$userId = empty($_SESSION[$_SERVER['HTTP_HOST']]['admin']['id']) ? 1 : $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'];
			if ($insert) {
				return db()->query('INSERT INTO `' . self::tableName() . '` (`key`,`value`,`userId`) VALUES (:key, :value, :userId)', [
					':key' => $key,
					':value' => $value,
					':userId' => $userId
				]);
			}

		}

		return false;
	}

	public static function getList($full = false)
	{
		if (!static::$_instance)
			static::$_instance = new self();

		return static::$_instance->getData($full);
	}

	public static function setValue($key, $value)
	{
		if (!static::$_instance)
			static::$_instance = new self();

		return static::$_instance->setData($key, $value);
	}

}
