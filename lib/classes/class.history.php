<?php

class history
{

	private static function getLogTxt($n)
	{
		$textArr = array(
			0 => 'Выполнил вход',
		);
		return isset($textArr[$n]) ? $textArr[$n] : $n;
	}

	static function write($userid, $log = '')
	{
		if (is_int($log))
			$log = self::getLogTxt($log);

		db()->query("INSERT INTO `adm_users_history` SET `date`=:date,`adm_user_id`=:adm_user_id,`log`=:log", array(
			':date' => date('Y-m-d H:i:s'),
			':adm_user_id' => $userid,
			':log' => $log
		));
	}

	static function writeUsr($log = '')
	{
		self::write(get_user_id(), $log);
	}

	static function writeOrder($userid, $order_id = 0, $status = '')
	{
		if (!$order_num = db()->scalar("SELECT order_num FROM orders WHERE id=:id",array(':id'=>$order_id)))
			$order_num = '-';
		db()->query("INSERT INTO `adm_users_history` SET `date`=:date,`adm_user_id`=:adm_user_id,`log`=:log", array(
			':date' => date('Y-m-d H:i:s'),
			':adm_user_id' => $userid,
			':log' => "Для заявки {$order_num} установлен статус {$status}"
		));
	}
}