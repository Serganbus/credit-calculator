<?php

/**
 * Class HttpRequest
 *
 * `
 * $opts = array(....);
 * @see Scorista:477
 * $requestId = HttpRequest::add('scorista', $opts);
 *
 * $requestId = HttpRequest::add('scorista', $data, 'POST', $url);
 * `
 * @property int $id
 */
class HttpRequest extends Model
{

	/**
	 * @inheritdoc
	 * @table 'httpRequest'
	 */
	public static function tableName()
	{
		return 'httpRequest';
	}


	/**
	 * Добавление лога сервиса
	 *
	 * @param $service
	 * @param $body
	 * @param null $keyField
	 * @param null $method
	 * @param null $path
	 * @return int|null
	 */
	public static function add($service, $body, $keyField = null, $method = null, $path = null)
	{
		if (is_array($body)) {
			if (!empty($body[CURLOPT_URL])) {

				$path = $body[CURLOPT_URL];
				$method = $body[CURLOPT_POST] ? 'POST' : 'GET';
				$body = $body[CURLOPT_POSTFIELDS];
			} else {
				$body = json_encode($body);
			}
		}

		$service = strtolower($service);
		$hash = self::generateHash($service, $path, $body);
		$userId = !empty($_SESSION[$_SERVER['HTTP_HOST']]['admin']['id']) ? $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'] : null;

		db()->query("INSERT INTO `" . self::tableName() . "` (`hash`,`serviceName`,`keyField`,`method`,`path`,`body`,`userId`) VALUES(:hash,:serviceName,:keyField,:method,:path,:body,:userId)", array(
			':hash' => $hash,
			':serviceName' => $service,
			':keyField' => $keyField,
			':method' => $method,
			':path' => $path,
			':body' => $body,
			':userId' => $userId
		));

		return db()->getLastInsetId();
	}

	/**
	 * Генерация уникального хеша запроса
	 *
	 * @param string $service
	 * @param string $path
	 * @param string $body
	 * @return string
	 */
	private static function generateHash($service, $path, $body)
	{
		return md5($service . $path . $body);
	}

	/**
	 * Возвращает лог ответа сервиса
	 *
	 * @return Model
	 * @throws Exception
	 */
	public function getResponse()
	{
		return HttpResponse::one("`requestId` = :requestId", array(':requestId' => $this->id));
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
