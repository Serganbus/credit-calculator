<?php

/**
 * Class Calculate
 *
 */
class Calculate
{

	/** @var */
	private static $creditPolicy;

	/**
	 * Кредитная политика
	 *
	 * @param DateTime|string|null $date
	 * @return CreditPolicy
	 */
	public static function creditPolicy($date = null)
	{
		if (static::$creditPolicy)
			return static::$creditPolicy;

		return static::$creditPolicy = new CreditPolicy(['date' => $date]);
	}

	/**
	 * Высчитывает количество штрафных процентов по количеству дней
	 *
	 * @param $days
	 * @param null $date
	 * @return int
	 */
	public static function getFinePercentOnDays($days, $date = null)
	{
		return static::creditPolicy($date)->getFinePerDay() * $days;
	}

	/**
	 * Высчитывает количество штрафных процентов по количеству дней на дату
	 * Если сумма процентов больше годовых процентов, то возвращает годовые проценты
	 *
	 * @param $days
	 * @param null $date
	 * @return int
	 */
	public static function getFinePercent($days, $date = null)
	{
		$percents = static::getFinePercentOnDays($days, $date);

		if ($percents > ($ppy = static::creditPolicy($date)->getFinePerYear()))
			$percents = $ppy;

		return $percents;
	}

	/**
	 * Рассчитывает сумму процентов на базовую сумму по количеству дней
	 *
	 * @param $sum
	 * @param $percentPerDay
	 * @param $days
	 * @return float
	 */
	public static function getPercentSum($sum, $percentPerDay, $days)
	{
		$percents = $sum * $percentPerDay * $days / 100;
		return round($percents, 2);
	}

	/**
	 * Возвращает полную стоимость кредита в % годовых
	 *
	 * @param integer $sum Выданная сумма
	 * @param integer $backSum Сумма к возврату
	 * @param integer $days	Количество дней кредита
	 * @return float
	 */
	public static function getFullCreditPercent($sum, $backSum, $days)
	{
		return ($backSum / $sum - 1) * floor(365 / $days) * 100;
	}

}
