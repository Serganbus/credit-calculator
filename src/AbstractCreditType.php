<?php

namespace Credits;

use DateTime;
use DateInterval;

/**
 * Абстрактный класс для генерации графика погашений
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
abstract class AbstractCreditType
{
    /**
     * @var CreditParams Параметры кредита
     */
    protected $creditParams;

    /**
     * @var array Массив с данными о частичных погашенияхы
     */
    protected $unexpectedPayments = [];

    /**
     * @param CreditParams $params Параметры кредиты
     */
    public function __construct(CreditParams $params)
    {
        $this->creditParams = $params;
    }

    /**
     * Вернуть график платежей,
     * где в качестве ключа массива лежит дата погашения,
     * а в качестве значения сумма пошашения
     *
     * @return RepaymentSchedule
     */
    abstract public function getRepaymentSchedule(): RepaymentSchedule;

    /**
     * Добавить частичное погашение к расчету графика погашений
     *
     * @param UnexpectedPayment $payment
     * @return AbstractCreditType
     */
    public function addUnexpectedPayment(UnexpectedPayment $payment)
    {
        $this->unexpectedPayments[] = $payment;

        return $this;
    }

    /**
     * Вернуть изначальные параметры кредиты
     *
     * @return CreditParams
     */
    public function getCreditParams(): CreditParams
    {
        return $this->creditParams;
    }

    /**
     * Считаем дату, на которую приходится следующее погашение
     *
     * @param DateTime $initial Начальная дата
     * @return DateTime
     */
    protected function addDurationToDate(int $durationType, $repaymentNumber, DateTime $initial): DateTime
    {
        $newDate = clone $initial;
        switch ($durationType)
        {
            case CreditParams::DURATION_WEEK:
                $days = 7 * $repaymentNumber;
                $interval = new DateInterval("P{$days}D");
                return $newDate->add($interval);
            case CreditParams::DURATION_TWO_WEEKS:
                $days = 14 * $repaymentNumber;
                $interval = new DateInterval("P{$days}D");
                return $newDate->add($interval);
            default:
                // расчет следующей даты платежа при ежемесячных платежах.
                // в php косяк: если к 29-31 января добавить интервал 1 месяц, то получится март.
                // эту ситуацию и обрабатывает код ниже
                $newDate->add(new DateInterval("P{$repaymentNumber}M"));

                $initialMonthsCount = (int)$initial->format('Y') * 12 + (int)$initial->format('n');
                $newMonthsCount = (int)$newDate->format('Y') * 12 + (int)$newDate->format('n');
                if ($newMonthsCount - $initialMonthsCount > $repaymentNumber) {
                    $newDate->modify('last day of previous month');
                }

                return $newDate;
        }
    }

    /**
     * Получить массив с частичными погашениями кредита между двумя датами
     *
     * @param DateTime $currentDate
     * @param DateTime $previousDate
     * @return array
     */
    protected function getUnexpectedPaymentsBetweenDates(DateTime $currentDate, DateTime $previousDate)
    {
        $payments = [];

        $prevDateStr = $previousDate->format('Y-m-d');
        $currDateStr = $currentDate->format('Y-m-d');
        foreach ($this->unexpectedPayments as $unexpectedPayment) {
            $paymentDateStr = $unexpectedPayment->getDate()->format('Y-m-d');
            if ($paymentDateStr > $prevDateStr
                && $paymentDateStr <= $currDateStr) {
                $payments[] = $unexpectedPayment;
            }
        }

        uasort($payments, function($a, $b) {
            $aTs = (int)$a->getDate()->format('U');
            $bTs = (int)$a->getDate()->format('U');
            if ($aTs === $bTs) {
                return 0;
            }
            return ($aTs < $bTs) ? -1 : 1;
        });

        return $payments;
    }
}