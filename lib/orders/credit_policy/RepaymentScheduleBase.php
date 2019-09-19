<?php

namespace admin\lib\orders\credit_policy;

/**
 * Description of RepaymentScheduleBase
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentScheduleBase {
    
    /**
     * Рассчитывает график погашения займа на основе предоставленного
     * графика погашения, начиная с переданной даты. 
     * Возвращает массив с датами погашения займа
     * 
     * @param $aRepaymentSchedule - график погашения
     * @param $aDate - дата, с которой надо рассчитать график погашения
     * 
     * @return array
     */
    public static function calcRepaymentScheduleFromDate(RepaymentScheduleBase $aRepaymentSchedule, \DateTime $aDate) {
        $rs_count = $aRepaymentSchedule->getCount();
        $rs_duration = $aRepaymentSchedule->getDuration();
        $lRepaymentDates = [];
        for ($i = 1; $i <= $rs_count; $i++) {
            $lRepaymentDates[] = $aDate->modify("+{$rs_duration} days")->format("Y-m-d");
        }
        return $lRepaymentDates;
    }
    
    protected $requestedDate; //дата получения займа
    protected $duration; //продолжительность периода погашения
    protected $count; //количество погашаемых периодов
    protected $requestedSum; //сумма, необходимая к погашению займа
    protected $percents; //процентов в день
    protected $cRepaymentSchedule; // график погашений. Массив, ключами которого являются расчетные даты погашений, а значениями суммы погашений.
    
    /**
     * @param $aDuration int - продолжительность периода погашения в днях
     * @param $aCount int - количество периодов погашения в днях
     * @param $aRequestedDate \DateTime - дата получения займа
     * @param $aRequestedSum float - сумма, необходимая к погашению займа
     * @param $aPercents float - процент займа в день. от 0 до 100
     * 
     * @return array
     */
    public function __construct($aDuration, $aCount, \DateTime $aRequestedDate, $aRequestedSum, $aPercents) {
        $this->duration = (int)$aDuration;
        $this->count = (int)$aCount;
        $this->requestedDate = $aRequestedDate;
        $this->requestedSum = $aRequestedSum;
        $this->percents = $aPercents;
    }
    
    /**
     * Рассчитывает график погашения займа на основе 
     * текущего графика погашения.
     * Возвращает массив с датами погашения займа
     * 
     * @return array
     */
    public function getRepaymentSchedule() {
        $rs_count = $this->count;
        $rs_duration = $this->duration;
        $lRepaymentDatesSums = [];
        if ($this->isAnnuitet()) {
            $rs_sums = $this->calcAnnuitetCreditRepaymentSums();
            for ($i = 1; $i <= $rs_count; $i++) {
                $lRepaymentDate = $this->requestedDate->modify("+{$rs_duration} days")->format("Y-m-d");
                $lRepaymentDatesSums[$lRepaymentDate] = $rs_sums[$i - 1];
            }
        } else {
            $lRepaymentDate = $this->requestedDate->modify("+{$rs_duration} days")->format("Y-m-d");
            $lRepaymentSum = (int)($this->requestedSum + $this->requestedSum * $this->duration * $this->percents / 100);
            $lRepaymentDatesSums[$lRepaymentDate] = $lRepaymentSum;
        }
        return $lRepaymentDatesSums;
    }
    
    public function getDuration() {
        return $this->duration;
    }
    
    public function getCount() {
        return $this->count;
    }
    
    /**
     * Определяет, является ли график погашения аннуитетным или нет
     * 
     * @return bool
     */
    public function isAnnuitet() {
        return $this->count > 1; 
    }
    
    private function calcAnnuitetCreditRepaymentSums() {
        $percentsInPeriod_fl = $this->percents * $this->duration / 100;
        $neededExp_fl = pow(1 + $percentsInPeriod_fl, $this->count);
        $annuitetKoefficient_fl = ($percentsInPeriod_fl * $neededExp_fl)/($neededExp_fl - 1);
        $repayment_fl = $annuitetKoefficient_fl * $this->requestedSum;
        $repayment_int = (int)$repayment_fl;
        $repaymentDiff_fl = 0;
        $repayments = array();
        for($i = 0; $i < $this->count - 1; $i++) {
            $repayments[] = $repayment_int;
            $repaymentDiff_fl += $repayment_fl - $repayment_int;
        }
        $repayments[] = $repayment_int + (int)$repaymentDiff_fl;
        return $repayments;
    }
}