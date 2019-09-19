<?php

/**
 * Class Orders
 *
 * ~
 *
 * $order = Orders::one(14358);
 * echo $order->id;
 *
 * $order = Orders::one(14358);
 * echo $order->id;
 *
 * Orders::one(14358, 'id');
 *
 *
 * $order = Orders::one([
 *    'user_id = :userId',
 *    ['AND', 'date > :date']
 * ],[
 *    ":userId" => $this->id,
 *    ":date" => '2015-09-01'
 * ]);
 *
 *
 * $orders = Orders::all("date > :date",[':date' => '2015-10-22']);
 * dump($orders);
 *
 * $time = Orders::scalar('time', "id = :id",[':id' => 14357]);
 * echo $time;
 *
 * ~
 *
 * @property int $id
 * @property string $status
 * @property string $date Дата займа (и дата одобрения или не одобрения)
 * @property boolean $unload_in_nsv Флаг работы с коллектором (1 - работал, 0 - нет)
 * @property boolean $isTake Выдан ли займ
 * @property float $back_sum Сумма, к возврату
 * @property float $sum_request Сумма займа
 * @property int $is_paid Кредит погашен
 * @property bool $isClosed Кредит погашен
 * @property bool $isOpen Открытый Кредит
 * @property bool $hasFines Займ имеет задолженность или нет
 * @property int $take_loan Выдан ли займ
 * @property int $user_id
 * @property float $persents
 * @property int $days Количество дней
 * @property string $backDate Дата возвращения
 * @property int $paytype Способ получения платежа по займу
 * @property string $paytypeText Способ получения платежа по займу
 */
class Orders extends Model
{
	/** @var array|Payment[] */
	private $_payments = [];

	private $_user;

	private $_prolongation;

	public static function tableName()
	{
		return 'orders o';
	}

	/**
	 * Получение всех заказов по id пользователя
	 *
	 * @param $userId
	 * @return array|null
	 * @throws Exception
	 */
	public static function getOrdersByUserId($userId)
	{
		return self::all('user_id = :userId', array(":userId" => $userId), null, null, 'date DESC, time DESC');
	}

	/**
	 * @param $condition
	 * @param array $params
	 * @param null $select
	 * @param null $orderBy
	 * @return Orders
	 * @throws Exception
	 */
	public static function one($condition, $params = array(), $select = null, $orderBy = null)
	{
		return parent::one($condition, $params, $select, $orderBy);
	}

	/**
	 * @param OrdersCollection $collection
	 * @return array|null|Order[]
	 * @throws Exception
	 */
	public static function collection(OrdersCollection $collection)
	{
		return parent::all($collection->getSql('condition'), $collection->getSql('params'), $collection->getSql('select'), $collection->getSql('join'), $collection->getSql('orderBy'), $collection->getSql('limit'), true);
	}

	/**
	 * Займ закрыт
	 * @return bool
	 */
	public function getIsClosed()
	{
		return $this->getAttribute('is_paid') == 1 ? true : false;
	}

	/**
	 * Открытый кредит
	 *
	 * @return bool
	 */
	public function getIsOpen()
	{
		return !$this->isClosed;
	}

	/**
	 * Кредит выдан
	 * @return bool
	 */
	public function getIsTake()
	{
		return $this->getAttribute('take_loan') == 1 ? true : false;
	}

	/**
	 * Статус займа
	 *
	 * @return string
	 */
	public function statusText()
	{
		switch ($this->status) {
			case 'О':
				$type = 'Одобрен';
				break;
			case 'Н':
				$type = 'Не одобрен';
				break;
			case 'З':
				$type = 'Запрещено';
				break;
			case 'Т':
				$type = 'Требует рассмотрения';
				break;
			case 'К':
				$type = 'Клиент отказался';
				break;
			default :
				$type = 'не определено';
		}
		return $type;
	}

	public function paytypeText()
	{
		switch ($this->paytype) {
			case '1':
				$type = 'Банк. карта';
				break;
			case '2':
				$type = 'Яндекс.Деньги';
				break;
			case '3':
				$type = '"Контакт"';
				break;
			case '4':
				$type = '"Юнистрим"';
				break;
			case '5':
				$type = 'Банк. перевод';
				break;
			default :
				$type = 'не определено';
		}
		return $type;
	}

	/**
	 * Все платежи по Займу
	 *
	 * @return array
	 */
	public function getPayments()
	{
		if (!$this->_payments) {
			$payments = Payment::getListByOrderIds([$this->id]);
			if (!$payments)
				$payments = [];
			$this->setPayments($payments[$this->id]);
		}
		return $this->_payments;
	}

	/**
	 * Установить платежи в модель Orders
	 * @param array $payments
	 * @return $this
	 */
	public function setPayments(array $payments)
	{
		$this->_payments = $payments;
		return $this;
	}

	/**
	 * Возвращет количество платежей
	 * @return int
	 */
	public function getPaymentsCount()
	{
		return count($this->getPayments());
	}

	/**
	 * Возвращает последний платеж по займу
	 *
	 * @return Payment|null
	 */
	public function getLastPayment()
	{
		$last = $this->getPayments();
		return array_pop($last);
	}

	/**
	 * Возвращает первый платеж по займу
	 * @return Payment|null
	 */
	public function getFirstPayment()
	{
		$p = $this->getPayments();
		return array_shift($p);
	}

	/**
	 * Возвращает сумму всех платежей по кредиту
	 *
	 * @return float
	 */
	public function getPaymentsTotalSum()
	{
		$sum = 0;
		/** @var Payment $payment */
		foreach ($this->getPayments() as $payment) {
			$sum += $payment->paysum;
		}
		return $sum;
	}

	/**
	 * Сумма по займу, оставшаяся к погашению
	 *
	 * @return float
	 */
	public function getRemainingAmount()
	{
		return $this->back_sum - $this->getPaymentsTotalSum();
	}


	/**
	 * Возвращает массив Займов на дату
	 *
	 * @param string $conditions
	 * @param array $params
	 * @return array|null
	 * @throws Exception
	 */
	public static function getListPayments($conditions = null, $params = [], array $column = [])
	{
		$prolColumn = ['pro.id as prolongationId', 'pro.quantity as prolongationQuantity', 'pro.date as prolongationDate'];

		$column = array_merge($column, $prolColumn);
		/** @var Orders[] $orders */
		if (!$orders = self::indexedList('o.id', $column, $conditions, $params, [
			[
				'inner', 'users u', 'o.user_id = u.id'
			], [
				'inner', 'prolongation pro', 'pro.order_id = o.id'
			]
		]))
			return [];

		$orderIds = array_keys($orders);
		$listPayments = Payment::getListByOrderIds($orderIds);

		foreach ($orderIds as $orderId) {
			$orders[$orderId]->setPayments(isset($listPayments[$orderId]) ? $listPayments[$orderId] : []);
		}

		return $orders;
	}

	public static function getOrderIds($conditions = null, $params = [])
	{
		return self::column('id', $conditions, $params);
	}

	/**
	 * Заемщик
	 * @return User
	 * @throws Exception
	 */
	public function getUser()
	{
		if (!$this->_user)
			$this->_user = User::one($this->user_id);
		return $this->_user;
	}

	/**
	 * Пролонгация
	 *
	 * @return mixed|null
	 */
	public function getProlongation()
	{
		if ($this->_prolongation === null) {
			if ($this->prolongationId !== null) {
				$this->_prolongation = $this->prolongationQuantity;
			} else {
				$this->_prolongation = db()->scalar('SELECT quantity FROM prolongation WHERE order_id = :order_id', [':order_id' => $this->id]);
			}
			$this->_prolongation--;
		}

		if ($this->_prolongation == 0)
			$this->_prolongation = null;

		return $this->_prolongation;
	}


	/**
	 * Дата возвращения займа
	 *
	 * @param bool|false $withProlongation с пролонгацией
	 * @return DateTime
	 */
	public function getBackDate($withProlongation = false)
	{
		$date = new DateTime($this->date);
		$days = $this->days;

		if ($withProlongation) {
			$prolongation = $this->getProlongation();
			if ($prolongation === null)
				$prolongation = 1;

			$days += $this->days * $prolongation;
		}

		$interval = new DateInterval('P' . $days . 'D');
		$date->add($interval);

		return $date;
	}

	/**
	 * Дата закрытия Займа
	 *
	 * @param bool|true $onlyClosed Только закрытые кредиты
	 * @return DateTime
	 * @throws Exception
	 */
	public function getCloseDate($onlyClosed = false)
	{
		if ($this->isClosed)
			return new DateTime($this->getLastPayment()->date);

		if ($onlyClosed)
			throw new Exception('Кредит не закрыт');

		return $this->getBackDate(true);
	}


	/**
	 * Штрафы по займу
	 *
	 * `
	 * $this->getFine('2015-12-24')->daysCount();
	 * $this->fine->daysCount();
	 * `
	 * @param DateTime|string|null $date Дату на которую расчитываются штрафы
	 * @return OrdersFine
	 */
	public function getFine($date = null)
	{
		return new OrdersFine($this, $date);
	}


	/**
	 * Займ находиться в штрафном периоде
	 * Т.е. у займа есть штрафы
	 *
	 * `
	 * Проверка штрафов на сегодня >  if ($this->hasFines) {...}
	 * `
	 * @param DateTime|string|null $date
	 * @return bool
	 * @throws Exception
	 */
	public function getHasFines($date = null)
	{
		return $this->getFine($date)->daysCount() > 0;
	}


	/**
	 * Величина долга по процентам
	 *
	 * @param DateTime|string|null $onDate Дата, на которую считаются проценты
	 * @return float
	 * @throws Exception
	 */
	public function getPercentSum($onDate = null)
	{
		if (!$onDate) {
			$onDate = new DateTime();
		} elseif (is_string($onDate))
			$onDate = new DateTime($onDate);

		if ($this->isClosed) {
			$onDate = $this->getCloseDate(true);
		}
		$orderDate = new DateTime($this->date);

		if ($onDate < $orderDate)
			throw new Exception('Дата займа не может быть больше даты закрытия');

		$days = $onDate->diff($orderDate)->days;

		return Calculate::getPercentSum($this->sum_request, $this->persents, $days);
	}


	/**
	 * Возвращает полную стоимость кредита в % годовых
	 *
	 * @return float
	 */
	public function getFullCreditPercent()
	{
		return Calculate::getFullCreditPercent($this->sum_request, $this->back_sum, $this->days);
	}

}