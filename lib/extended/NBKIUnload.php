<?php

/**
 * Class NBKIUnload
 * Выгрузка отчетов в формате TUTDF
 *
 *
 */
class NBKIUnload extends Object
{
	/** Статус договора Активный */
	const ORDER_STATUS_ACTIVE = '00';

	/** Статус договора Закрытый */
	const ORDER_STATUS_CLOSED = '13';

	/** Статус договора Просроченный */
	const ORDER_STATUS_FAIL = '52';

	public $dateFrom;
	public $dateTo;

	/** @var  bool Выгружать только выданные */
	public $takeLoanOnly;

	/** @var  int|null Колиичество выгружаемых записей. Если null - выгружаем все */
	public $limitOrderCount = null;

//	public $limitOrderCountInFile = 11000;
	public $limitOrderCountInFile = 0;

	public $ExpUserID;
	public $ExpPassword;

	private $orders;

	private $creditPolicy = array();

	private $errors = [];

	private $_data = [];

	private $countOrders = 0;

	/** @var DateTime */
	public $file_date_start;

	/** @var DateTime */
	public $file_date_end;

	public $test = false;

	private static $testData = [
		'name' => 'ХХХХХХ',
		'surname' => 'ХХХХХХ',
		'second_name' => 'ХХХХХХХХХХХ',
		'passportNo' => '123456',
		'passportSerial' => '1234',
	];


	private $tutdfElementsCount = [
		'TUTDF' => 8,
		'ID' => 8,
		'NA' => 13,
		'AD' => 16,
		'TR' => 42,
		'IP' => 19,
		'TRLR' => 2,
	];

	public static $logPath = '/admin/storage/flash';
	public static $rootLogPath;

	public function init()
	{
		parent::init();

		self::$rootLogPath = ROOT . self::$logPath;

		if (!file_exists(self::$rootLogPath) && !is_dir(self::$rootLogPath))
			mkdir(self::$rootLogPath, 0775);

		$this->creditPolicy = admin\lib\orders\UserOrder::getCreditPolicy($this->dateFrom);

		$this->orders = OrdersCollection::getCollection(array(
			'sqlCondition' => 'o.`date` BETWEEN :dateFrom AND :dateTo' . (!empty($this->takeLoanOnly) ? " AND o.take_loan = '1'" : ''),
			'sqlLimit' => $this->limitOrderCount,
			'sqlJoin' => array(
				array('inner', 'users u', 'o.user_id = u.id'),
				array('left', 'prolongation p', 'o.id = p.order_id'),
			),
			'sqlSelect' => '
			p.quantity,
			u.name,
			u.surname,
			u.second_name,
			u.birthday,
			u.region,
			u.city,
			u.country,
			u.status,
			u.pasport,
			u.pasportdate,
			u.address1,
			u.zip_code,
			u.obl_subj,
			u.prop_region,
			u.prop_city_type,
			u.prop_zip,
			u.prop_street_type_long,
			u.street,
			u.prop_street_type_short,
			u.building,
			u.building_add,
			u.flat_add,
			u.home_phone,
			u.home_metro,
			u.city_prog,
			u.prog_obl_subj,
			u.prog_region,
			u.prog_city_type,
			u.prog_zip,
			u.prog_street_type_long,
			u.street_prog,
			u.prog_street_type_short,
			u.building_prog,
			u.building_add_prog,
			u.flat_add_prog,
			u.prog_phone,
			u.prog_metro,
			u.rab_mesto,
			u.pred_mesto,
			u.stepen_zan_mesto,
			u.vid_deiat_mesto,
			u.dolgn_mesto,
			u.stag_mesto,
			u.phone_mesto,
			u.kolleg1_mesto,
			u.kolleg2_mesto,
			u.phone_otdel_mesto,
			u.city_mesto,
			u.mesto_obl_subj,
			u.mesto_region,
			u.mesto_city_type,
			u.mesto_zip,
			u.mesto_street_type_long,
			u.street_mesto,
			u.mesto_street_type_short,
			u.building_mesto,
			u.building_add_mesto,
			u.flat_add_mesto,
			u.dohod,
			u.semiapol,
			u.ijdivencev,
			u.accept,
			u.pasportissued,
			u.pasportdepartmentcode,
			u.external_uid,
			o.`time`,
			o.sum_response,
			o.persents,
			o.status,
			o.is_paid,
			o.take_loan,
			o.`date`,
			o.sum_request,
			o.days,
			o.back_sum,
			o.id as id,
			adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days) AS back_date',
			'sqlParams' => array(
				':dateFrom' => $this->dateFrom,
				':dateTo' => $this->dateTo
			),
		));

	}

	public function unload()
	{
		$this->generateData();
		$fileName = $this->save();
		$this->afterUnload();

		return $fileName;
	}

	/**
	 * Генерация данных
	 */
	private function generateData()
	{
		// льготный период, в течение которого просрочникам не начисляются штрафные проценты
		$lgot_period = !empty($this->creditPolicy['amnesty']) ? $this->creditPolicy['amnesty'] : 0;

		$nowDate = new DateTime();

		$this->_data[] = ['TUTDF', '4.0R', '20150701', $this->ExpUserID, '', date('Ymd'), $this->ExpPassword, ''];

		foreach ($this->orders as $order) {

			/*if ($this->test && $this->countOrders > 10 )
				break;*/

			$countProlongation = $order['quantity'] > 0 ? $order['quantity'] : 1;
			$in_lgot_period = false;

			// когда взял
			$orderDate = new DateTime($order['date']);
			$orderDateProlongation = clone $orderDate;

			$orderDateProlongation
				->add(new DateInterval('P' . $countProlongation * $order['days'] . 'D'));

			if ($order['is_paid'] == '1') {
				$order['now_status'] = self::ORDER_STATUS_CLOSED;
			} else {

				$nowDate->setTime(0, 0);

				$fromNowToProlongationInterval = $nowDate->diff($orderDateProlongation);

				if ($fromNowToProlongationInterval->invert == 0 && $fromNowToProlongationInterval->days >= 0) {
					$order['now_status'] = self::ORDER_STATUS_ACTIVE;
				} else {
					if ($lgot_period) {
						$lgotPeriodInerval = new DateInterval('P' . $lgot_period . 'D');
						if ($nowDate->diff($orderDateProlongation) < $lgotPeriodInerval) {
							$in_lgot_period = true;
						}
					}
					$order['now_status'] = self::ORDER_STATUS_FAIL;
				}
			}

			if (!$in_lgot_period) {

				//------------
				// segment ID
				//------------
				$passport = explode("-", $order['pasport']);
				$passportDateTxt = '';

				if (!empty($order['pasportdate'])) {
					$passportDate = new DateTime($order['pasportdate']);

					// если дата получения паспорта больше текущей
					if ($passportDate > $nowDate)
						continue;

					$passportDateTxt = $passportDate->format('Ymd');
				}

				if (!empty($order['pasportissued'])) {
					$passportKemVydan = trimmToStr($order['pasportissued']);
				} else {
					$city_prop = 'Москва';
					if (!empty($order['city'])) {
						$city_prop = trimmToStr($order['city']);
					}
					$passportKemVydan = $city_prop;
				}

				//------------
				// segment NA
				//------------

				$surname = empty($order['surname']) ? 'UNKNOWN' : trimmToStr($order['surname']);
				$name = empty($order['name']) ? 'UNKNOWN' : trimmToStr($order['name']);
				$second_name = empty($order['second_name']) ? '' : trimmToStr($order['second_name']);

				$adult18 = new DateInterval('P18Y');
				$birthday = clone $nowDate;
				$birthday = $birthday->sub($adult18);

				if (!empty($order['birthday'])) {
					$birthday = new DateTime($order['birthday']);
					$personFullYears = getFullYears($order['birthday']);

					if (empty($order['pasportdate'])) {
						$passportDate = clone $birthday;
						if ($personFullYears > 45) {
							$passportDate = $passportDate->sub(new DateInterval('P45Y'));
						} elseif ($personFullYears > 20) {
							$passportDate = $passportDate->sub(new DateInterval('P20Y'));
						} elseif ($personFullYears > 14) {
							$passportDate = $passportDate->sub(new DateInterval('P14Y'));
						}
						$passportDateTxt = $passportDate->format('Ymd');
					}
				}

				$birthday = $birthday->format('Ymd');

				$birthdayCity = 'Москва';
				if (!empty($order['city']))
					$birthdayCity = trimmToStr($order['city']);
				elseif (!empty($order['city_prog']))
					$birthdayCity = trimmToStr($order['city_prog']);


				//------------
				// segment AD
				//------------
				$AD_defaultCity = 'Москва';
				$AD_defaultStreet = 'Ленина';

				$city_prop = empty($order['city']) ? $AD_defaultCity : trimmToStr($order['city']);
				$street_prop = empty($order['street']) ? $AD_defaultStreet : trimmToStr($order['street']);
				$building_prop = empty($order['building']) ? '' : (int)$order['building'];
				$building_add_prop = empty($order['building_add']) ? '' : (int)$order['building_add'];
				$flat_add_prop = empty($order['flat_add']) ? '' : (int)$order['flat_add'];
				$postIndexReg = empty($order['zip_code']) || mb_strlen($order['zip_code']) < 6 ? '' : $order['zip_code'];

				$city_prog = empty($order['city_prog']) ? $city_prop : trimmToStr($order['city_prog']);
				$street_prog = empty($order['street_prog']) ? $street_prop : trimmToStr($order['street_prog']);
				$building_prog = empty($order['building_prog']) ? '' : (int)$order['building_prog'];
				$building_add_prog = empty($order['building_add_prog']) ? '' : (int)$order['building_add_prog'];
				$flat_add_prog = empty($order['flat_add_prog']) ? '' : (int)$order['flat_add_prog'];
				$postIndexProg = empty($order['prop_zip']) || mb_strlen($order['prop_zip']) < 6 ? '' : $order['prop_zip'];


				//------------
				// segment TR
				//------------
				$payments = db()->getList("SELECT * FROM payments WHERE order_id=:order_id ORDER BY id DESC", array(':order_id' => $order['id']));

				// последний платеж
				$lastPaymentDay = '19000102';
				$lastPayment = '';

				if (count($payments)) {
					$lastPayment = $payments[0];
					$lastPaymentDay = preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$1$2$3', $lastPayment['date']);
				}

				// дата состояния счета
				$stateAccountDate = '';
				if ($order['now_status'] == self::ORDER_STATUS_ACTIVE) {
					$stateAccountDate = '';
				} elseif ($order['now_status'] == self::ORDER_STATUS_CLOSED) {
					if ($lastPaymentDay != '19000102') {
						$stateAccountDate = $lastPaymentDay;
					} else {
						$stateAccountDate = preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$1$2$3', $order['date']);
					}
				} elseif ($order['now_status'] == self::ORDER_STATUS_FAIL) {
					$stateAccountDate = preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$1$2$3', $order['back_date']);
				}

				// баланс
				$balance = 0;
				if (count($payments)) {
					foreach ($payments as $payment) {
						$balance += $payment['paysum'];
					}
				} elseif ($order['now_status'] == self::ORDER_STATUS_CLOSED) {
					$balance = $order['back_sum'];
				}
//
				$uo = admin\lib\orders\UserOrder::getPrototype($order['id']);

				// просрочка
				$prosrochka = 0;
				if ($order['now_status'] == self::ORDER_STATUS_FAIL) {
					$prosrochka = $uo->getTotalDebtAtTodaysDate($this->dateTo);
				}

				// след.платеж
				$nextPayment = 0;
				if ($prosrochka > 0) {
					$nextPayment = $prosrochka;
				} elseif ($order['is_paid'] !== '1') {
					$nextPayment = $order['back_sum'];
				}

				// своевременность платежей
				if ($nowDate < $orderDateProlongation) {
					$duringPayments = '0';
				} else {
					if ($prosrochka === 0) {
						// 1 - оплата без просрочек
						$duringPayments = '1';
					} // просрочка
					else {
						$prosrochkaDays = $uo->calculateFineDaysCount($this->dateTo);
						if ($prosrochkaDays < 30) {
							$duringPayments = 'A';
						} elseif ($prosrochkaDays < 60) {
							$duringPayments = '2';
						} elseif ($prosrochkaDays < 90) {
							$duringPayments = '3';
						} elseif ($prosrochkaDays < 120) {
							$duringPayments = '4';
						} else {
							$duringPayments = '5';
						}
					}
				}

				// дата окончания срока договора
				$orderClosedDate = $order['is_paid'] == '1' ? preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$1$2$3', $lastPayment['date']) : '';

				// дата финального платежа
				$dataFinalPayment = $order['is_paid'] == '1' ? $orderClosedDate : $orderDateProlongation->format('Ymd');

				// дата финальной выплаты процентов
				$dataFinalPaymentPercent = $dataFinalPayment;

				// текущая задолжность
				$tekysaiaZadolzhnost = $prosrochka > 0 ? $prosrochka : 0;

				// полная стоимость кредита
				$cbp = round(365 / $order['days']);
				$i = ($order['back_sum'] - $order['sum_request']) / $order['sum_request'];
				$orderFullSum = floor($i * $cbp * 100000) / 1000;

				// Дата фактического исполнения обязательства (полное погашение заема)
				$orderFactClosedDate = $order['is_paid'] == '1' ? $lastPaymentDay : '';

				if ($this->test) {
					$passport[0] = self::$testData['passportSerial'];
					$passport[1] = self::$testData['passportNo'];
					$surname = self::$testData['surname'];
					$name = self::$testData['name'];
					$second_name = self::$testData['second_name'];
				}

				$tr = $this->validate('TR', array('TR01', $this->ExpUserID, $order['id'], 16, 1, $orderDate->format('Ymd'), $lastPaymentDay, $order['now_status'], $stateAccountDate, '', floor($order['sum_request']), floor($balance), floor($prosrochka), $nextPayment, 7, $duringPayments, 'RUB', '', $orderClosedDate, $dataFinalPayment, $dataFinalPaymentPercent, 7, '', '', floor($tekysaiaZadolzhnost), 'N', '', '', '', 'N', '', '', '', '', '', '', floor($orderFullSum), '', '', '', '', $orderFactClosedDate));

				$currentHash = $this->getHash($tr);
				$existUnloadOrder = db()->one("SELECT * FROM sent_orders WHERE order_id=:order_id", [':order_id' => $order['id']]);

				if (!$this->test && $existUnloadOrder['hash'] == $currentHash)
					continue;

				$this->file_date_start = $orderDate < $nowDate ? $orderDate : $nowDate;
				$this->file_date_end = !$this->file_date_end || $orderDate < $this->file_date_end ? $orderDate : $this->file_date_end;

				$this->_data[] = $this->validate('ID', array('ID01', '21', $passport[0], $passport[1], $passportDateTxt, static::toWin($passportKemVydan, $order), '', ''));
				$this->_data[] = $this->validate('NA', array('NA01', static::toWin($surname), static::toWin($second_name), static::toWin($name), '', $birthday, static::toWin($birthdayCity), '', '', '', '', '', ''));
				$this->_data[] = $this->validate('AD', array('AD01', 1, $postIndexReg, 'RU', '', '', '', static::toWin($city_prop), '', static::toWin($street_prop), static::toWin($building_prop), static::toWin($building_add_prop), '', static::toWin($flat_add_prop), '', ''));
				$this->_data[] = $this->validate('AD', array('AD02', 2, $postIndexProg, 'RU', '', '', '', static::toWin($city_prog), '', static::toWin($street_prog), static::toWin($building_prog), static::toWin($building_add_prog), '', static::toWin($flat_add_prog), '', ''));

				if ($order['take_loan'] == 1) {
					$this->_data[] = $tr;
				} elseif (empty($this->takeLoanOnly)) {// Не только выданные
					$this->_data[] = $this->validate('IP', array('IP01', $this->ExpUserID, $order['id'], $orderDate->format('Ymd'), 2, 1, 201, 1, '', '', '', '', '', '', '', '', '', '', ''));
				}

				$this->countOrders++;

				if (!$this->test) {
					if (empty($existUnloadOrder['hash'])) {
						db()->query('INSERT INTO sent_orders (`order_id`, `hash`, `date`) VALUES (:order_id,:hash,:date)', [
							':order_id' => $order['id'],
							':date' => time(),
							':hash' => $currentHash
						]);
					} else {
						db()->query('UPDATE `sent_orders` SET `hash`=:hash, `date`=:date WHERE `order_id`=:order_id', [
							':order_id' => $order['id'],
							':hash' => $currentHash,
							':date' => time(),
						]);
					}
				}

				if ($this->limitOrderCountInFile && $this->countOrders === $this->limitOrderCountInFile)
					break;
			}
		}
	}

	/**
	 * Save File
	 *
	 * @return null|string
	 * @throws Exception
	 */
	private function save()
	{
		if ($this->countOrders > 0) {
			$this->_data[] = $this->validate('TRLR', ['TRLR', '']);

			$fileName = $this->ExpUserID . '_' . date('Ymd_His');
			$fp = fopen(self::$rootLogPath . '/' . $fileName . '.txt', 'w');
			fwrite($fp, $this->dataToStr());
			fclose($fp);

			if (!$this->test) {
				db()->query('INSERT INTO sent_orders_files (`file_name`, `file_date`, `file_date_start`, `file_date_end`) VALUES (:file_name, :date,  :file_date_start, :file_date_end)', [
					':file_name' => $fileName,
					':date' => time(),
					':file_date_start' => $this->file_date_start->getTimestamp(),
					':file_date_end' => $this->file_date_end->getTimestamp(),
				]);
			}

			return self::$rootLogPath . '/' . $fileName . '.txt';
		}
		return null;
	}

	/**
	 * @return string
	 */
	private function dataToStr()
	{
		$str = '';
		foreach ($this->_data as $segment) {
			$str .= implode("\t", $segment) . PHP_EOL;
		}
		return $str;
	}

	private function afterUnload()
	{
		history::write(get_user_id(), "Выгрузка в НБКИ {$this->dateFrom} - {$this->dateTo} ");
	}

	private static function toWin($str, $order = null)
	{
		try {
			return iconv('UTF-8', 'cp1251', $str);
		} catch (Exception $e) {
			dump('строка с ошибкой: ' . $str, 2, 0);
			if ($order !== null)
				dump('orderId: ' . $order->id, 2, 0);
			throw $e;
		}
	}

	/**
	 * Возвращает хеш займа
	 * @param array $tr
	 * @return string
	 */
	private function getHash(array $tr)
	{
		return md5(implode('|', $tr));
	}

	/**
	 * @param string $key
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	private function validate($key, array $data)
	{
		if ($this->tutdfElementsCount[$key] === count($data))
			return $data;
		throw new Exception('Не правильно количество элементов в сегменте <' . $key . '>! Нужно ' . $this->tutdfElementsCount[$key] . ', а присутствует ' . count($data));
	}


	public function hasErrors()
	{
		return count($this->errors);
	}


}