<?php

/**
 * Class OrdersFine
 *
 * Штрафы по Займу
 *
 */
class OrdersFine
{
	/** @var Orders */
	private $_order;

	private $_date;

	public function __construct(Orders $order, $date = null)
	{
		if (!$order->isTake)
			throw new Exception('Нельзя расчитать штрафы для не выданного кредита');

		$this->_order = $order;

		if ($date instanceof DateTime) {
			$this->_date = $date;
		} elseif (is_string($date))
			$this->_date = new DateTime($date);
		else
			$this->_date = new DateTime();
	}


	/**
	 * Количество дней просрочки на дату
	 *
	 * @param bool|true $amnisty Если true, то дни считаются за минусом амнистии
	 * @return int
	 * @throws Exception
	 */
	public function daysCount($amnisty = true)
	{
		$backDateTo = $this->_order->getBackDate(true);

		if ($this->_order->isClosed) {
			$days = $this->_order->getCloseDate(true)->diff($backDateTo);
		} else if ($this->_date > $backDateTo) {
			$date = clone $this->_date;

			if ($amnisty) {
				$amnisty = Calculate::creditPolicy($this->_date)->getAmnesty();
				$date->sub(new DateInterval('P' . $amnisty . 'D'));
			}

			$days = $date->diff($backDateTo);

		} else
			return 0;

		return $days->days;
	}


	/**
	 * Высчитывает количество штрафных процентов по количеству дней
	 *
	 * @return int
	 * @throws Exception
	 */
	public function percents()
	{
		return Calculate::getFinePercent($this->daysCount(), $this->_date);
	}

	/**
	 * Возвращает сумму Единичного Штрафа
	 *
	 * @return int
	 */
	public function single()
	{
		$singlePaySum = Calculate::creditPolicy($this->_date)->getFineSinglePay();
		if (!$singlePaySum)
			return 0;

		$dateBack = $this->_order->getBackDate(true);
		$firstPayment = $this->_order->getFirstPayment();


		if ($firstPayment) {
			if ($dateBack->format('Y-m-d') < $firstPayment->date)
				return $singlePaySum;
		} else {
			if ($dateBack->format('Y-m-d') < date('Y-m-d'))
				return $singlePaySum;
		}

		return 0;
	}

	public function sum()
	{
		//percents();
//		return Calculate::getFinePercent();
	}


}