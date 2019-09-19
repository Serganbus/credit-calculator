<?php

namespace admin\lib\orders;

use admin\lib\orders\credit_policy\RepaymentScheduleBase;

/**
 * Класс хранит информацию по конкретному займу.
 * Позволяет рассчитать полную сумму долга по займу на текущую дату.
 *
 * @author Ivanov Sergey<sivanovkz@gmail.com>
 */
class UserOrderStandart {

    static function getCfgParams(){
        return array(
            'fine'=>array(
                'pday'=>'Процент штрафа в день',//Процент штрафа в день
                'pyear'=>'Процент штрафа годовых',//Процент штрафа годовых
            )
        );
    }
    
    /* Дата погашения договора. Если договор не погашен, хранит null */
    private $maturityDate = null;
    
    protected $db;
    protected $oId = 0;

    /* Хранит информацию по займу, полученную из БД */
    protected $oInfo;

    /* Хранит информацию по займу, полученную из БД */
    protected $oRequestReceivingDate;

    /* Дата, когда был выдан займ */
    protected $oTotalDebtAtDate = [];

    /* Информация из БД о совершенных платежах по займу */
    protected $oPayments;

    /* Величина основного долга */
    protected $oMainDebtAtDate = [];

    /* Величина долга по процентам */
    protected $oPercentsDebtAtDate = [];

    /* Сумма штрафа, начисляемого за каждый день просрочки */
    protected $oFinePerDayAtDate = [];

    /* Величина долга по штрафам и пени */
    protected $oFineDebtAtDate = [];
    
    /* Список платежей сумм платежей по датам */
    protected $_paymentList = array();
    
    /* Кредитная политика начисления штрафа */
    protected $creditPolicy = null;
    
    /* График погашения. Экземпляр admin\lib\orders\credit_policy\RepaymentScheduleBase */
    protected $repaymentSchedule = null;

    public function __construct($params) {
        $this->db = new \MySql();
        if (is_array($params)) {
            $this->oInfo = $params;
            if (!empty($params['id'])) {
                $this->oId = intval($params['id']);
            }
        } else {
            $this->oId = $params;
            $this->oInfo = $this->db->GetRow("
                SELECT 
                    o.*,
                    adddate(o.`date`, o.days) AS back_date,
                    adddate(o.`date`, o.days) AS back_date_without_prolong,
                    IFNULL(`p`.quantity, 1) as quantity,
                    adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days) AS back_date_with_prolong,
                    p.date AS prolongation_date
                FROM 
                    `orders` AS `o`
                    LEFT OUTER JOIN prolongation p ON (o.id = p.order_id)
                WHERE
                    `o`.`id`='%s'
            ", array(
                $this->oId
            ));
            if ($this->oInfo == null) {
                throw new \Exception('Записи в таблице с таким номером не существует! $aOrderId_int=' . $params);
            }
        }
    }
    
    /**
     * График погашения займа
     * 
     * @return admin\lib\orders\credit_policy\RepaymentScheduleBase;
     */
    public function getRepaymentSchedule() {
        return $this->_getRepaymentSchedule();
    }

    /**
     * Кредитная политика, действующая на момент заключения договора
     * @param type $date
     * @return array
     */
    public function getCreditPolicy() {
        if (is_null($this->creditPolicy)) {
            $this->creditPolicy = UserOrder::getCreditPolicy($this->oInfo['date']);
        }
        return $this->creditPolicy;
    }
    
    public function getAmnestyDays() {
        $amnesty = 0;
        $credit_policy = $this->getCreditPolicy();
        if (!empty($credit_policy['amnesty'])) {
            $amnesty = (int) $credit_policy['amnesty'];
        }
        return $amnesty;
    }

    /**
     * Возвращает полную стоимость кредита в % годовых
     * ПСК рассчитывается в соответствии с алгоритмом
     * ЦБ РФ, описанным в законе №353-ФЗ(ред. от 21.07.2014), статья 5, п.20 и п.21
     * http://www.consultant.ru/document/cons_doc_LAW_166040/.
     * 
     * @return float
     */
    public function calculatePSK() {
        $psk = 0;
        
        $lRepaymentSchedule = $this->_getRepaymentSchedule();
        if (!$lRepaymentSchedule->isAnnuitet()) {
            $psk = ($this->oInfo['back_sum'] / $this->oInfo['sum_request'] - 1) * 365 * 100 / $this->oInfo['days'];
        } else {
            /* http://habrahabr.ru/post/233987/ */
            $dates = array();
            $sums = array();
            $days = array();
            $e = array();
            $q = array();
            $dates[] = new DateTime($this->oInfo['date']);
            $sums[] = -$this->oInfo['sum_request'];
            $schedule = $lRepaymentSchedule->getRepaymentSchedule();
            $basePeriod = $lRepaymentSchedule->getDuration();
            foreach ($schedule as $repaymentDate => $repaymentSum) {
                $dates[] = new DateTime($repaymentDate);
                $sums[] = $repaymentSum;
            }
            $dates_count = count($dates);
            
            for ($i = 0; $i < $dates_count; $i++) {
                $daysDiff = ($dates[$i]->format("U") - $dates[0]->format("U"))/(24*60*60);
                $days[] = $daysDiff;
                $e[] = ($daysDiff % $basePeriod) / $basePeriod;
                $q[] = floor($daysDiff / $basePeriod);
            }
            
            $basePeriodsCount = round(365 / $basePeriod);
            $i = 0; 
            $x = 1; 
            $x_m = 0; 
            $s = 0.000001;
            while($x > 0) {
                $x_m = $x;
                $x = 0;
                for ($k = 0; $k < $dates_count; $k++) {
                    $x = $x + $sums[$k] / ((1 + $e[$k] * $i) * pow(1 + $i, $q[$k]));
                }
                $i = $i + $s;
            }
            if ($x > $x_m) {
                $i = $i - $s;
            }
            
            $psk = round($i * $basePeriodsCount * 100, 2);
        }
        return $psk;
    }

    /**
     * Метод для совместимости с предыдущей неклассовой реализацией
     */
    public function getInfo() {
        $this->oInfo['give_date'] = $this->_execRequestReceivingDate();
        $this->oInfo['fine_days'] = $this->calculateFineDaysCount();
        $this->oInfo['current_total_debt'] = $this->_getTotalDebtAtDate();
        $this->oInfo['all_paysum'] = $this->_calcTotalPayments();
        $this->oInfo['main_debt'] = $this->_getMainDebtAtDate();
        $this->oInfo['percents_debt'] = $this->_getPercentsDebtAtDate();
        $this->oInfo['fine_debt'] = $this->_getFineDebtAtDate();
        
        return $this->oInfo;
    }

    public function getRequestReceivingDate() {
        return $this->_getRequestReceivingDate();
    }

    public function calcTotalPayments() {
        return $this->_calcTotalPayments();
    }

    public function getPayments() {
        return $this->_getPayments();
    }

    /**
     * Штраф в день.
     * 
     * @return float
     */
    public function calculateFinePerDay() {
        return $this->_getFinePerDayAtTodaysDate();
    }

    /**
     * Возвращает общую сумму начисленных штрафов. 
     * 
     * @return float
     */
    public function calculateAccuredFine() {
        return $this->_getFineDebtAtDate();
    }

    /**
     * Общий долг заемщика
     * включает основной долг, проценты по основному долгу, штрафы/пени/неустойки
     * учитывает все частичные платежи заемщика
     * Рассчитывается на текущую дату
     * 
     * @return float
     */
    public function getTotalDebtAtTodaysDate() {
        return $this->_getTotalDebtAtDate();
    }

    /**
     * Общий долг заемщика
     * включает основной долг, проценты по основному долгу, штрафы/пени/неустойки
     * учитывает все частичные платежи заемщика
     * 
     * @param $param_date - дата, на которую необходимо рассчитать параметр
     * @return float
     */
    public function getTotalDebtAtDate($param_date) {
        return $this->_getTotalDebtAtDate($param_date);
    }

    /**
     * Возвращает сумму процентов, необходимых к погашению
     * 
     * @param $param_date - дата, на которую необходимо рассчитать параметр
     * @return float
     */
    public function calcTotalProcentstToDates($param_date) {
        return $this->_getPercentsDebtAtDate($param_date);
    }

    /**
     * Возвращает сумму основного долга, необходимого к погашению
     * 
     * @param $param_date - дата, на которую необходимо рассчитать параметр
     * @return float
     */
    public function getDebtAtDate($param_date) {
        return $this->_getMainDebtAtDate($param_date);
    }
    
    public function getPaymentsList() {
        if (is_null($this->_paymentList)) {
            $p = [];
            $rs = \DB::select("SELECT * FROM payments WHERE order_id={$this->oId} ORDER BY date, time");
            while ($rs->next()) {
                $p[$rs->get('date')][$rs->get('time')] = $rs->getRow();
            }
            $this->_paymentList = $p;
        }
        
        return $this->_paymentList;
    }

    /**
     * Количество дней просрочки
     * @param string $now_date
     * @return integer
     */
    public function calculateFineDaysCount($now_date = '') {
        $lFineDays = 0;

        $refundDate = strtotime($this->oInfo['back_date_with_prolong']);
        //Дата, до которой можно вернуть долг без дополнительных процентов и штрафов
        $todayDate = strtotime(date('Y-m-d'));
        if ($now_date) {
            $todayDate = strtotime($now_date);
        }
        if ($is_paid_date = $this->getMaturityDate()) {
            if ($todayDate > strtotime($is_paid_date)) {
                $todayDate = strtotime($is_paid_date);
            }
        }
        if ($todayDate > $refundDate) {
            $lFineDays = (int) (($todayDate - $refundDate) / 86400);
        }
        return $lFineDays;
    }

    //receiving date execution...
    private function _getRequestReceivingDate() {
        return $this->oRequestReceivingDate ? $this->oRequestReceivingDate : $this->_execRequestReceivingDate();
    }

    private function _execRequestReceivingDate() {
        $lRet = '';
        $orderdate = $this->db->GetRow("SELECT send_time FROM sent_sms WHERE order_id = '%s'", [$this->oId]);
        if ($orderdate['send_time'] != '') {
            $give_date_array = explode(' ', $orderdate['send_time']);
            $lRet = $give_date_array[0];
        }

        $this->oRequestReceivingDate = $lRet;
        return $lRet;
    }
    //...receiving date execution
    
    private function _getRepaymentSchedule() {
        if (is_null($this->repaymentSchedule)) {
            $duration = strlen(trim($this->oInfo['r_duration'])) === 0 ? $this->oInfo['r_days'] : $this->oInfo['r_duration'];
            $count = strlen(trim($this->oInfo['r_count'])) === 0 ? 1 : $this->oInfo['r_count'];
            $this->repaymentSchedule = new RepaymentScheduleBase($duration, $count, \DateTime::createFromFormat('Y-m-d', $this->oInfo['date']), $this->oInfo['sum_request'], $this->oInfo['persents']);
        }
        
        return $this->repaymentSchedule;
    }

    /**
     * Возвращает дату погашения договора, если договор погашен.
     * В противном случае возвращает null
     * 
     * @return null|string
     */
    public function getMaturityDate() {
        if (!is_null($this->maturityDate)) {
            return $this->maturityDate;
        }
        
        $is_paid_data = null;
        if ($this->oInfo['is_paid'] == 1) {//
            $q = "SELECT MAX(pd.date) AS LastDate 
                FROM payments AS pd 
                WHERE pd.order_id = {$this->oId}";
            $rs = \DB::select($q);
            if ($rs->next()) {
                $is_paid_data = $rs->get('LastDate');
            }
        }
        return $this->maturityDate = $is_paid_data;
    }

    private function _calcTotalPayments() {
        $lTotalPayments = 0;
        $payments = $this->_getPayments();
        if ($payments && $payments != null) {
            foreach ($payments as $k => $payment) {
                if ($payment['collector'] > 0) {
                    $lTotalPayments += $payment['paysum'];
                }
            }
        }
        return $lTotalPayments;
    }

    private function _getPayments() {
        return $this->oPayments ? $this->oPayments : $this->_execPayments();
    }

    private function _execPayments() {
        $payments = $this->db->GetTable("
            SELECT 
                *
            FROM 
                `payments`
            WHERE
                order_id = '%s' 
             ORDER BY date, time
        ", array(
            $this->oId
        ));
        $this->oPayments = $payments;
        return $payments;
    }

    private function _getFinePerDayAtTodaysDate($date) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        if (!isset($this->oFinePerDayAtDate[$date]) || is_null($this->oFinePerDayAtDate[$date])) {
            $this->_calcTotalDebtAtDate($date);
        }
        
        return $this->oFinePerDayAtDate[$date];
    }
    
    private function _getFineDebtAtDate($date = null) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        if (!isset($this->oFineDebtAtDate[$date]) || is_null($this->oFineDebtAtDate[$date])) {
            $this->_calcTotalDebtAtDate($date);
        }
        
        return $this->oFineDebtAtDate[$date];
    }
    
    private function _getPercentsDebtAtDate($date = null) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        if (!isset($this->oPercentsDebtAtDate[$date]) || is_null($this->oPercentsDebtAtDate[$date])) {
            $this->_calcTotalDebtAtDate($date);
        }
        
        return $this->oPercentsDebtAtDate[$date];
    }
    
    private function _getMainDebtAtDate($date = null) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        if (!isset($this->oMainDebtAtDate[$date]) || is_null($this->oMainDebtAtDate[$date])) {
            $this->_calcTotalDebtAtDate($date);
        }
        
        return $this->oMainDebtAtDate[$date];
    }

    private function _getTotalDebtAtDate($date = null) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        
        if (isset($this->oTotalDebtAtDate[$date]) && !is_null($this->oTotalDebtAtDate[$date])) {
            return $this->oTotalDebtAtDate[$date];
        }
        return $this->_calcTotalDebtAtDate($date);
    }

    protected function _calcTotalDebtAtDate($date) {
        /*Расчет зависит от типа погашения: 
         * разовый платеж или аннуитетное погашение.
         * Для каждого типа выполняется свой алгоритм
         */
        if ($this->_getRepaymentSchedule()->isAnnuitet()) {
            //Расчет параметров при аннуитетном погашении
            return $this->_calcMultipleRepaymentedTotalDebtAtTodaysDate($date);
        } else {
            //Расчет параметров при единоразовом погашении
            return $this->_calcSingleRepaymentedTotalDebtAtDate($date);
        }
    }
    
    protected function _calcMultipleRepaymentedTotalDebtAtTodaysDate($date) {
        $requestedSum = $this->oInfo['sum_request'];
        $orderPercent = $this->oInfo['persents'];
        $requestDate = $this->oInfo['date'];
        $refundDate = $this->oInfo['back_date_with_prolong'];
        $amnesteyDaysCount = $this->getAmnestyDays();
        $refundDateWithAmnestey = date('Y-m-d', strtotime("$refundDate +$amnesteyDaysCount day"));
        $credit_policy = $this->getCreditPolicy();
        $fine = $credit_policy['fine'];
        
        //Основная задолженность
        $lMainDebtAtDate = $requestedSum;
        //по процентам  
        $lPercentsDebtAtDate = 0;
        //пени+штрафы
        $lFineDebtAtDate = 0;
        //За весь период общий начисленный штраф (связано с ограничением общего начисления 20 годовых)
        $commonFineDebtInPercents = 0;
        //Платежи
        $paymentList = $this->getPaymentsList();

        $cur_date = date('Y-m-d', strtotime($requestDate));
        while ($cur_date <= $date) {
            $lPercentsDebtAtDate += $lMainDebtAtDate * $orderPercent / 100;
            
            if ($cur_date > $refundDateWithAmnestey) {
                $PercentsDebt = $lMainDebtAtDate * $orderPercent / 100;

                $lPercentsDebtAtDate += $PercentsDebt;
                //Штрафчики
                //Единовременный штраф за просрочку
                if (!empty($fine['single_pay'])) {
                    $lFineDebtAtDate += $fine['single_pay'];
                    $fine['single_pay'] = 0;
                }
                //пени+штраф
                if ($cur_date == date('Y-m-d', strtotime("$refundDateWithAmnestey +1 day"))) {//Ну теперь точно чувак попал
                    $lPercentsDebtAtDate += $PercentsDebt * $amnesteyDaysCount;
                    
                    $prevCommonFineDebtInPercents = $commonFineDebtInPercents;
                    $currentFinePercents = $fine['pday'] * $amnesteyDaysCount;
                    $commonFineDebtInPercents += $currentFinePercents;
                    if ($commonFineDebtInPercents > $fine['pyear']) {
                        $commonFineDebtInPercents = $fine['pyear'];
                        $currentFinePercents = $fine['pyear'] - $prevCommonFineDebtInPercents;
                    }
                    $lFineDebtAtDate += $currentFinePercents * $lMainDebtAtDate;
                }
                if (empty($fine['pyear']) || $fine['pyear'] >= $commonFineDebtInPercents) {
                    $prevCommonFineDebtInPercents = $commonFineDebtInPercents;
                    $currentFinePercents = $fine['pday'] * $amnesteyDaysCount;
                    $commonFineDebtInPercents += $currentFinePercents;
                    if ($commonFineDebtInPercents > $fine['pyear']) {
                        $commonFineDebtInPercents = $fine['pyear'];
                        $currentFinePercents = $fine['pyear'] - $prevCommonFineDebtInPercents;
                    }
                    $lFineDebtAtDate += $currentFinePercents * $lMainDebtAtDate;
                    if ($cur_date == $date) {
                        $lFinePerDayAtDate = $currentFinePercents * $lMainDebtAtDate;
                    }
                }
            }
            //Если есть платёж то
            if (isset($paymentList[$cur_date]) && $paymentListDate = $paymentList[$cur_date]) {
                foreach ($paymentListDate as $time => $paymentRow) {
                    $payment = $paymentRow['paysum'];
                    //гасим проценты
                    if ($lPercentsDebtAtDate > $payment) {
                        $lPercentsDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lPercentsDebtAtDate;
                        $lPercentsDebtAtDate = 0;
                    }
                    //гасим основной долг
                    if ($lMainDebtAtDate > $payment) {
                        $lMainDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lMainDebtAtDate;
                        $lMainDebtAtDate = 0;
                    }
                    //гасим штрафы
                    if ($lFineDebtAtDate > $payment) {
                        $lFineDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lFineDebtAtDate;
                        $lFineDebtAtDate = 0;
                    }
                }
            }
            if ($lMainDebtAtDate + $lPercentsDebtAtDate + $lFineDebtAtDate <= 0) {
                break;
            }
            $cur_date = date('Y-m-d', strtotime("$cur_date +1 day"));
        }
        $this->oMainDebtAtDate[$date] = $lMainDebtAtDate;
        $this->oPercentsDebtAtDate[$date] = $lPercentsDebtAtDate;
        $this->oFineDebtAtDate[$date] = $lFineDebtAtDate;
        $this->oFinePerDayAtDate[$date] = lFinePerDayAtDate;
        $this->oTotalDebtAtDate[$date] = $this->oMainDebtAtDate[$date] 
                + $this->oPercentsDebtAtDate[$date] 
                + $this->oFineDebtAtDate[$date];
        return $this->oTotalDebtAtDate[$date];
    }
    
    protected function _calcSingleRepaymentedTotalDebtAtDate($date) {
        $isPereschetProcentovEnabled = \Cfg::get('is_pereschet_procentov_enabled');
        
        $requestedSum = $this->oInfo['sum_request'];
        $refundSum = $this->oInfo['back_sum'];
        $orderPercent = $this->oInfo['persents'];
        $requestDate = $this->oInfo['date'];
        $refundDate = $this->oInfo['back_date_with_prolong'];
        $amnesteyDaysCount = $this->getAmnestyDays();
        $refundDateWithAmnestey = date('Y-m-d', strtotime("$refundDate +$amnesteyDaysCount day"));
        $credit_policy = $this->getCreditPolicy();
        $fine = $credit_policy['fine'];
        
        //Основная задолженность
        $lMainDebtAtDate = round($requestedSum, 2);
        //по процентам  
        $lPercentsDebtAtDate = 0;
        //пени+штрафы
        $lFineDebtAtDate = 0;
        //начисление штрафов в день
        $lFinePerDayAtDate = 0;
        //За весь период общий начисленный штраф (связано с ограничением общего начисления 20 годовых)
        $commonFineDebtInPercents = 0;
        //Платежи
        $paymentList = $this->getPaymentsList();

        $cur_date = date('Y-m-d', strtotime("$requestDate +1 day"));
        while ($cur_date <= $date) {
            //сначало начисляем
            if ($cur_date <= $refundDate) {
                if ($isPereschetProcentovEnabled) {
                    //пересчитываем проценты за фактическое время пользования займом
                    $lPercentsDebtAtDate += $lMainDebtAtDate * $orderPercent / 100;
                } else {
                    $lPercentsDebtAtDate = $refundSum - $requestedSum;
                }
            }
            if ($cur_date > $refundDateWithAmnestey) {
                $PercentsDebt = $lMainDebtAtDate * $orderPercent / 100;

                $lPercentsDebtAtDate += $PercentsDebt;
                //Штрафчики
                //Единовременный штраф за просрочку
                if (!empty($fine['single_pay'])) {
                    $lFineDebtAtDate += $fine['single_pay'];
                    $fine['single_pay'] = 0;
                }
                //пени+штраф
                if ($cur_date == date('Y-m-d', strtotime("$refundDateWithAmnestey +1 day"))) {//Ну теперь точно чувак попал
                    $lPercentsDebtAtDate += $PercentsDebt * $amnesteyDaysCount;
                    
                    $prevCommonFineDebtInPercents = $commonFineDebtInPercents;
                    $currentFinePercents = $fine['pday'] * $amnesteyDaysCount;
                    $commonFineDebtInPercents += $currentFinePercents;
                    if ($commonFineDebtInPercents > $fine['pyear']) {
                        $commonFineDebtInPercents = $fine['pyear'];
                        $currentFinePercents = $fine['pyear'] - $prevCommonFineDebtInPercents;
                    }
                    $lFineDebtAtDate += $currentFinePercents * $lMainDebtAtDate;
                }
                if (empty($fine['pyear']) || $fine['pyear'] >= $commonFineDebtInPercents) {
                    $prevCommonFineDebtInPercents = $commonFineDebtInPercents;
                    $currentFinePercents = $fine['pday'] * $amnesteyDaysCount;
                    $commonFineDebtInPercents += $currentFinePercents;
                    if ($commonFineDebtInPercents > $fine['pyear']) {
                        $commonFineDebtInPercents = $fine['pyear'];
                        $currentFinePercents = $fine['pyear'] - $prevCommonFineDebtInPercents;
                    }
                    $lFineDebtAtDate += $currentFinePercents * $lMainDebtAtDate;
                    if ($cur_date == $date) {
                        $lFinePerDayAtDate = $currentFinePercents * $lMainDebtAtDate;
                    }
                }
            }
            //Если есть платёж то
            if (isset($paymentList[$cur_date]) && $paymentListDate = $paymentList[$cur_date]) {
                foreach ($paymentListDate as $time => $paymentRow) {
                    $payment = $paymentRow['paysum'];
                    //гасим проценты
                    if ($lPercentsDebtAtDate > $payment) {
                        $lPercentsDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lPercentsDebtAtDate;
                        $lPercentsDebtAtDate = 0;
                    }
                    //гасим основной долг
                    if ($lMainDebtAtDate > $payment) {
                        $lMainDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lMainDebtAtDate;
                        $lMainDebtAtDate = 0;
                    }
                    //гасим штрафы
                    if ($lFineDebtAtDate > $payment) {
                        $lFineDebtAtDate -= $payment;
                        $payment = 0;
                    } else {
                        $payment -= $lFineDebtAtDate;
                        $lFineDebtAtDate = 0;
                    }
                }
            }
            if ($lMainDebtAtDate + $lPercentsDebtAtDate + $lFineDebtAtDate <= 0) {
                break;
            }
            $cur_date = date('Y-m-d', strtotime("$cur_date +1 day"));
        }
        $this->oMainDebtAtDate[$date] = $lMainDebtAtDate;
        $this->oPercentsDebtAtDate[$date] = $lPercentsDebtAtDate;
        $this->oFineDebtAtDate[$date] = $lFineDebtAtDate;
        $this->oFinePerDayAtDate[$date] = lFinePerDayAtDate;
        $this->oTotalDebtAtDate[$date] = $this->oMainDebtAtDate[$date] 
                + $this->oPercentsDebtAtDate[$date] 
                + $this->oFineDebtAtDate[$date];
        return $this->oTotalDebtAtDate[$date];
    }
}