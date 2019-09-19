<?php

class HttpResponse extends Model
{
	/**
	 * @inheritdoc
	 * @table 'httpResponse'
	 */
	public static function tableName()
	{
		return 'httpResponse';
	}

	public static function add($requestId, $body)
	{
		db()->query("INSERT INTO `".self::tableName()."` (`requestId`,`body`) VALUES(:requestId,:body)",array(
			':requestId' => $requestId,
			':body' => $body
		));

		return db()->getLastInsetId();
	}

}
