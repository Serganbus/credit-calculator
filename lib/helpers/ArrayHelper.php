<?php
namespace admin\lib\helpers;
/**
 * Class ArrayHelper
 * @package app\helpers
 */
class ArrayHelper
{
	/**
	 * Объединяет массивы рекурсивно
	 *
	 * @param array $a array to be merged to
	 * @param array $b array to be merged from. You can specify additional
	 * arrays via third argument, fourth argument etc.
	 * @return array the merged array (the original arrays are not changed.)
	 */
	public static function merge($a, $b)
	{
		$args = func_get_args();
		$res = array_shift($args);
		while (!empty($args)) {
			$next = array_shift($args);
			foreach ($next as $k => $v) {
				if (is_int($k)) {
					if (isset($res[$k])) {
						$res[] = $v;
					} else {
						$res[$k] = $v;
					}
				} elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
					$res[$k] = self::merge($res[$k], $v);
				} else {
					$res[$k] = $v;
				}
			}
		}

		return $res;
	}

	/**
	 * ~~~
	 * // working with array
	 * $username = ArrayHelper::getValue($_POST, 'username');
	 * // working with object
	 * $username = ArrayHelper::getValue($user, 'username');
	 * // working with anonymous function
	 * $fullName = ArrayHelper::getValue($user, function ($user, $defaultValue) {
	 *     return $user->firstName . ' ' . $user->lastName;
	 * });
	 * // using dot format to retrieve the property of embedded object
	 * $street = ArrayHelper::getValue($users, 'address.street');
	 * // using an array of keys to retrieve the value
	 * $value = ArrayHelper::getValue($versions, ['1.0', 'date']);
	 *
	 * @param array|object $array
	 * @param string|\Closure|array $key
	 * @param mixed $default
	 * @return mixed
	 */
	public static function getValue($array, $key, $default = null)
	{
		if ($key instanceof \Closure) {
			return $key($array, $default);
		}

		if (is_array($key)) {
			$lastKey = array_pop($key);
			foreach ($key as $keyPart) {
				$array = static::getValue($array, $keyPart);
			}
			$key = $lastKey;
		}

		if (is_array($array) && array_key_exists($key, $array)) {
			return $array[$key];
		}

		if (($pos = strrpos($key, '.')) !== false) {
			$array = static::getValue($array, substr($key, 0, $pos), $default);
			$key = substr($key, $pos + 1);
		}

		if (is_object($array)) {
			return $array->$key;
		} elseif (is_array($array)) {
			return array_key_exists($key, $array) ? $array[$key] : $default;
		} else {
			return $default;
		}
	}

	/**
	 *
	 * ~~~
	 * // $array = ['type' => 'A', 'options' => [1, 2]];
	 * // working with array
	 * $type = ArrayHelper::remove($array, 'type');
	 * // $array content
	 * // $array = ['options' => [1, 2]];
	 * ~~~
	 *
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed|null
	 */
	public static function remove(array &$array, $key, $default = null)
	{
		if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
			$value = $array[$key];
			unset($array[$key]);

			return $value;
		}

		return $default;
	}

	/**
	 * Строит карту массива
	 *
	 * ~~~
	 * $array = [
	 *     ['id' => '123', 'name' => 'aaa', 'class' => 'x'],
	 *     ['id' => '124', 'name' => 'bbb', 'class' => 'x'],
	 *     ['id' => '345', 'name' => 'ccc', 'class' => 'y'],
	 * ];
	 *
	 * $result = ArrayHelper::map($array, 'id', 'name');
	 * // the result is:
	 * // [
	 * //     '123' => 'aaa',
	 * //     '124' => 'bbb',
	 * //     '345' => 'ccc',
	 * // ]
	 *
	 * $result = ArrayHelper::map($array, 'id', 'name', 'class');
	 * // the result is:
	 * // [
	 * //     'x' => [
	 * //         '123' => 'aaa',
	 * //         '124' => 'bbb',
	 * //     ],
	 * //     'y' => [
	 * //         '345' => 'ccc',
	 * //     ],
	 * // ]
	 * ~~~
	 *
	 * @param array $array
	 * @param string|\Closure $from
	 * @param string|\Closure $to
	 * @param string|\Closure $group
	 * @return array
	 */
	public static function map($array, $from, $to, $group = null)
	{
		$result = array();
		foreach ($array as $element) {
			$key = static::getValue($element, $from);
			$value = static::getValue($element, $to);
			if ($group !== null) {
				$result[static::getValue($element, $group)][$key] = $value;
			} else {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Проверяет, ассоциативный ли массив
	 *
	 * @param array $array
	 * @param bool|true $allStrings
	 * @return bool
	 */
	public static function isAssociative(array $array, $allStrings = true)
	{
		if (empty($array)) {
			return false;
		}

		if ($allStrings) {
			foreach ($array as $key => $value) {
				if (!is_string($key)) {
					return false;
				}
			}
			return true;
		} else {
			foreach ($array as $key => $value) {
				if (is_string($key)) {
					return true;
				}
			}
			return false;
		}
	}
}
