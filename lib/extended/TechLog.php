<?php

/**
 * Class TechLog
 *
 * @property int $id
 */
class TechLog extends Model
{
	const TYPE_INFO = 'info';
	const TYPE_WARN = 'warn';
	const TYPE_ERROR = 'error';
	const TYPE_EXCEPTION = 'exception';

	const CATEGORY_DEFAULT = 'app';

	/**
	 * @inheritdoc
	 * @table 'tech_logs'
	 */
	public static function tableName()
	{
		return 'tech_logs';
	}


	/**
	 * Добавление лога
	 *
	 * @param $text
	 * @param string $type
	 * @param string $category
	 * @return int|null
	 */
	public static function add($text, $type = self::TYPE_INFO, $category = self::CATEGORY_DEFAULT)
	{
		$userId = !empty($_SESSION[$_SERVER['HTTP_HOST']]['admin']['id']) ? $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'] : null;

		db()->query("INSERT INTO `" . self::tableName() . "` (`type`,`cat`,`text`,`userId`) VALUES(:type,:cat,:text,:userId)", [
			':type' => $type,
			':cat' => $category,
			':text' => $text,
			':userId' => $userId
		]);

		return db()->getLastInsetId();
	}

	/**
	 * @param $condition
	 * @param array $params
	 * @param null $select
	 * @param null $orderBy
	 * @return HttpRequest
	 * @throws Exception
	 */
	public static function one($condition, $params = array(), $select = null, $orderBy = null)
	{
		return parent::one($condition, $params, $select, $orderBy);
	}
}
