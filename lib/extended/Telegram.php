<?php

/**
 * Class Telegram
 *
 * @property string $url
 */
class Telegram extends Object
{
	const TO_TELEGRAM = 1;

	public $to = self::TO_TELEGRAM;

	public $send = false;

	protected $channel = null;
	protected $name = null;
	protected $username = null;
	protected $token = null;

	public $baseUrl = 'https://api.telegram.org';


	public function __construct($config = [])
	{
		$cfg = Cfg::get('telegram');
		if (!is_array($cfg))
			$cfg = [];

		parent::__construct($cfg);
	}


	/**
	 * Отправляет новости реципиенту
	 *
	 * @param $text
	 * @return bool
	 */
	public function send($text)
	{
		if (!$this->send)
			 return false;

		$bodyMsg = [
			'chat_id' => '@' . $this->channel,
			'text' => $text
		];
		$url = $this->baseUrl.'/bot' . $this->token . '/sendMessage';

		$curl = new CustomCurl($url);

		$curl
			->setHeaders([
				'Content-Type' => 'application/json'
			])
			->setJson($bodyMsg)
			->post()
			->getResponseJson();
		return true;
	}

}
