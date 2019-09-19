<?php


/**
 * Class OrdersCollection
 *
 *
 * `
 *	$orders = OrdersCollection::getCollection(array(
 *		'sqlCondition' => 'o.`take_loan` = :take_loan AND o.`date` BETWEEN :date1 AND :date2',
 *		'sqlJoin' => array(
 * 			array('inner','users u','o.user_id = u.id'),
 *			array('left','prolongation p','o.id = p.order_id'),
 *		),
 *		'sqlLimit' => 5,
 *		'sqlSelect' => 'o.id AS id, o.sum_request, o.is_paid, o.back_sum, IFNULL(p.quantity, 1) AS quantity,
 *			IF((TO_DAYS(adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days)) < TO_DAYS(NOW()) AND TO_DAYS(NOW()) <= TO_DAYS(adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days+:amnesty))), 1, 0) AS in_lgot_period,
 *			IF(TO_DAYS(adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days+:amnesty)) < TO_DAYS(NOW()), 1, 0) AS in_shtraf_period,
 *			IF((IFNULL(p.quantity, 1)>1 AND TO_DAYS(NOW()) <= TO_DAYS(adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days))),1,0) AS in_prolong_period,
 *			IF((IFNULL(p.quantity, 1)=1 AND TO_DAYS(NOW()) <= TO_DAYS(adddate(o.`date`, o.days))), 1, 0) AS in_futurepay_period',
 *		'sqlParams' => array(
 *			':date1' => $date_1,
 *			':date2' => $date_2,
 *			':take_loan' => '1',
 *		),
 *	));
 *
 * `
 */
class OrdersCollection extends Collection
{
	protected $sqlCondition = null;
	protected $sqlParams = array();
	protected $sqlSelect = null;
	protected $sqlOrderBy = null;
	protected $sqlLimit = null;
	protected $sqlJoin = null;
	protected $sqlFrom = null;

	public static function getCollection(array $params = array())
	{
		$new = new self($params);

		$list = Orders::collection($new);

		$new->fromArray($list);

		return $new;
	}

	public function getSql($name = null)
	{
		if ($name === null)
			return array(
				'select' => $this->sqlSelect,
				'condition' => $this->sqlCondition,
				'join' => $this->sqlJoin,
				'orderBy' => $this->sqlOrderBy,
				'limit' => $this->sqlLimit,
				'from' => $this->sqlFrom,
				'params' => $this->sqlParams
			);

		$name = 'sql'.ucfirst($name);

		if (property_exists($this, $name))
			return $this->$name;

		return null;
	}

	public function fromArray(array $orders)
	{
		foreach ($orders as $order) {
			if ($order instanceof Orders) {
				if (!isset($order->id))
					throw new Exception('Отсуствует id');
				$this->_data[$order->id] = $order;
			} else if(is_array($order)) {
				if (!isset($order['id']))
					throw new Exception('Отсуствует id');
				$this->_data[$order['id']] = $order;
			}
		}
	}

}