<?php

/**
 * Class Payment
 *
 * Модель оплат займа
 *
 * @property integer $pay_type
 * @property integer $pay_from
 * @property float $paysum
 * @property string $date
 */
class Payment extends Model
{

	public static function tableName()
	{
		return 'payments p';
	}

	/**
	 * @param array $orderIds
	 * @return array|null
	 */
	public static function getListByOrderIds(array $orderIds, $conditions = null, $params = [], $asModel = true)
	{
		$conditions = 'order_id IN (' . implode(',', $orderIds) . ')' . ($conditions ? ' AND '.$conditions :'');
		return self::listGroup('order_id', [], $conditions, $params, null, !$asModel);
	}

	public function getPayTypeText()
	{
		switch ($this->pay_type) {
			case '0':
				$type = 'Ручное погашение';
				break;
			case '1':
				$type = 'Полное погашение';
				break;
			case '2':
				$type = 'Частичная оплата';
				break;
			case '3':
				$type = 'Пролонгация';
				break;
			case '4':
				$type = 'Ануитентный платеж';
				break;
			default :
				$type = 'не определено';
		}
		return $type;
	}

	public function getPayFromText()
	{
		switch ($this->pay_from) {
			case '1':
				$type = 'Робокасса';
				break;
			case '2':
				$type = 'Киви';
				break;
			case '3':
				$type = 'Ариус';
				break;
			default :
				$type = 'не определено';
		}
		return $type;
	}

}