<?php

if (!function_exists('db')) {
	function db(){
		global $settings;
		return PDOBase::getInstance(array(
			'host' => $settings['DB']['host'],
			'user' => $settings['DB']['login'],
			'pass' => $settings['DB']['password'],
			'base' => $settings['DB']['base']
		));
	}
}


function dump($var, $lvl=7, $end = true) {
	cdump($var, $lvl, $end, true);
}

function isDebug() {
	return defined('APP_DEBUG') && APP_DEBUG === true;
}

function cdump($var, $lvl=7, $end = true, $highlight = false) {
	echo VarDumper::dumpAsString($var, $lvl, $highlight);
	if ($end)
		die();
}

function renderJson(array $json = []) {
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($json);
	return true;
}


/**
 * Форматирует число на разряды
 * @param $number
 * @return mixed
 */
function formatNumberRus($number)
{
	return preg_replace('/(\d)(?=(\d{3})+([^\d]|$))/m', '$1&nbsp;', $number);
}

/**
 * Приводит телефон к читабельному виду
 * @param string $number
 * @return string
 */
function formatPhone($number)
{
	if (($l = strlen($number)) === 11)
		$number = substr($number, 1,$l-1);

	if (strlen($number) === 10) {
		return preg_replace('/^(\d{3})(\d{3})(\d{2})(\d{2})$/', '+7&nbsp;($1)&nbsp;$2-$3-$4', $number);
	}

	if (strlen($number) === 7) {
		return preg_replace('/^(\d{3})(\d{2})(\d{2})$/', '$1-$2-$3', $number);
	}
	return $number;
}


/**
 * Форматирование даты
 * @param $date
 * @return mixed
 */
function formatDate($date)
{
	return preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $date);
}
/**
 * 
 * @param str $str
 * @return str
 */
function ucfirst_utf8($str)
{
    return mb_substr(mb_strtoupper($str, 'utf-8'), 0, 1, 'utf-8') . mb_substr($str, 1, mb_strlen($str)-1, 'utf-8');
}