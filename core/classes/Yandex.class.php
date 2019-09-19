<?php
//https://tech.yandex.ru/metrika/
//http://www.1seo.by/skripti-dlya-saita/php-skript-dlya-raboti-s-api-yandeks-metriki
//https://tech.yandex.ru/metrika/doc/ref/reference/metrika-api-resources-docpage/

class Yandex
{
	private static $dimensionsMap = array(
		'ym:s:regionCountry' => 'Страна',
		'ym:s:deviceCategory' => ' Устройство',
		'ym:s:referer' => 'URL реферера',
		'ym:s:refererPathLevel2' => 'Второй уровень URL реферера',
		'ym:s:operatingSystem' => 'Операционная система',
		'ym:s:browserAndVersionMajor' => 'Браузер',
		'ym:s:cookieEnabled' => 'Браузер: Cookie',
		'ym:s:javascriptEnabled' => 'Браузер: JS',
		'ym:s:screenResolution' => 'Разрешение экрана'
	);

	private static $baseUrl = "http://api-metrika.yandex.ru/stat/v1/data";
	/**
	 * @var Yandex
	 */
	private static $instance;

	private function __construct()
	{
		$cfg = Cfg::get('counters');
		$this->ya = $cfg['ya'];
		$this->ya_app_id = $cfg['ya_app_id'];
		$this->ya_app_pass = $cfg['ya_app_pass'];
		$this->ya_login = $cfg['ya_login'];
		$this->ya_pass = $cfg['ya_pass'];
	}

	/**
	 * @return Yandex
	 */
	static function getInstance()
	{
		if (empty(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function request($url, $get = array(), $post = array())
	{
		$ch = curl_init();
		if ($get) {
			$url .= "?" . http_build_query($get);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		//        curl_setopt($ch, CURLOPT_HEADER, 1);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$err = curl_error($ch);

		$out = curl_exec($ch);
		curl_close($ch);

		return $out;
	}


	function getToken()
	{
		if (!empty($this->token)) {
			return $this->token;
		}
		if (!$this->ya_app_id) {
			return '';
		}
		$yandex_get_token_url = "https://oauth.yandex.ru/token";
		$post = array(
			'grant_type' => 'password',
			'username' => $this->ya_login,
			'password' => $this->ya_pass,
			'client_id' => $this->ya_app_id,
			'client_secret' => $this->ya_app_pass,
		);
		$out = $this->request($yandex_get_token_url, array(), $post);
		if ($out = json_decode($out, true)) {
			if (isset($out['access_token'])) {
				return $this->token = $out['access_token'];
			}
		}
	}

	static function stat()
	{
		$ya = self::getInstance();
		return $ya->getStatTech();

	}

	function getStat($path = 'traffic/summary', $date_from = null, $date_to = null)
	{
            
		if (!$this->getToken()) {
			return array();
		}
		if (empty($date_from)) {
			$date_from = date("Ymd");
		}
		if (empty($date_to)) {
			$date_to = date("Ymd");
		}
		//
//$metrika_url = "http://api-metrika.yandex.ru/stat/traffic/summary.json?id=ID_СЧЕТЧИКА&pretty=1&date1=$today&date2=$today&oauth_token=НАШ_ТОКЕН";
		$url = "http://api-metrika.yandex.ru/stat/$path.json";
		$get = array(
			'oauth_token' => $this->getToken(),
			'id' => $this->ya,
			'pretty' => 1,
			'date1' => $date_from,
			'date2' => $date_to,
                        'per_page' => 365,
		);
		$out = $this->request($url, $get);
		$out = json_decode($out, true);
               //echo('<pre>' . print_r($out));
		return $out;
                
	}

	function getStatTech($name = 'browsers', $date_from = null, $date_to = null)
	{
		if (!$this->getToken()) {
			return array();
		}
		if (empty($date_from)) {
			$date_from = date("Ymd");
		}
		if (empty($date_to)) {
			$date_to = date("Ymd");
		}

//    	$today=date("Ymd");
		$url = "http://api-metrika.yandex.ru/stat/tech/$name.json";
		$get = array(
			'oauth_token' => $this->getToken(),
			'id' => $this->ya,
			'pretty' => 1,
			'date1' => $date_from,
			'date2' => $date_to,
		);
		$out = $this->request($url, $get);
		$out = json_decode($out, true);
		return $out;
	}

	/**
	 * Возвращает техническую информацию по номеру заказа
	 * @param int $orderId
	 * @return mixed
	 * @throws Exception
	 */
	public static function getStatFullTechByOrderId($orderId)
	{
		if (!(int)$orderId)
			throw new Exception('Не правильный Номер заказа');

		$yandex = self::getInstance();

		if (!$yandex->getToken()){
                    return array();
			//throw new Exception('Не удалось получить токен');
                }

		$get = array(
			'oauth_token' => $yandex->getToken(),
			'id' => $yandex->ya,
			'date1' => date('Y-m-d',  strtotime('-1 year')),
			'metrics' => 'ym:s:visits,ym:s:pageviews',
			'filters' => "EXISTS(ym:s:paramsLevel1=='Order_ID' AND ym:s:paramsLevel2=='".$orderId."')",
			'dimensions' => 'ym:s:regionCountry,ym:s:referer,ym:s:refererPathLevel2,ym:s:deviceCategory,ym:s:operatingSystem,ym:s:browserAndVersionMajor,ym:s:screenResolution,ym:s:cookieEnabled,ym:s:javascriptEnabled'
		);
		$out = $yandex->request(self::$baseUrl, $get);

		$data = json_decode($out,1);

		if (!is_array($data) || empty($data['data']))
			return null;

		return self::toArray($data);
	}

	private static function toArray(array $data)
	{
		$query = $data['query'];
		$data = $data['data'];

		if (count($data)>1)
			throw new Exception('Данных для договора не должно быть больше одного');

		$data = reset($data);

		$params = array();
		foreach ($query['dimensions'] as $key => $dimension) {
			if (!isset(self::$dimensionsMap[$dimension]))
				continue;
			$params[$dimension] = array(
				'name' => self::$dimensionsMap[$dimension],
				'value' => empty($data['dimensions'][$key]) || empty($data['dimensions'][$key]['name']) ? null : $data['dimensions'][$key]
			);
		}

		return $params;
	}
}