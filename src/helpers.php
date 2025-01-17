<?php

namespace Credits;

use DateTime;
use DateInterval;

/**
 * Считаем дату, на которую приходится следующее погашение
 *
 * @param DateTime $initial Начальная дата
 * @param int $durationType Продолжительность платежного периода
 * @param int $repaymentNumber Номер платежного периода
 * @return DateTime
 * @throws \InvalidArgumentException
 */
function addDurationToDate(DateTime $initial, int $durationType, int $repaymentNumber): DateTime
{
    if ($repaymentNumber <= 0) {
        throw new \InvalidArgumentException('Invalid argument repaymentNumber');
    }
    $newDate = clone $initial;
    if ($durationType === CreditParams::DURATION_WEEK
        || $durationType === CreditParams::DURATION_TWO_WEEKS
        || $durationType === CreditParams::DURATION_QUARTER) {
        $intervalDays = CreditParams::$daysInPaymentPeriod[$durationType];
        $days = $intervalDays * $repaymentNumber;
        $interval = new DateInterval("P{$days}D");
        return $newDate->add($interval);
    } elseif ($durationType === CreditParams::DURATION_MONTH) {
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
    throw new \InvalidArgumentException('Invalid argument durationType');
}

/**
 * Получить массив с частичными погашениями кредита между двумя датами,
 * при этом время не учитывается. Если имеется несколько платежей на одну дату,
 * то платежи сортируются по времени в порядке его увеличения.
 *
 * @param DateTime $currentDate
 * @param DateTime $previousDate
 * @return array<UnexpectedPayment>
 * @throws \InvalidArgumentException
 */
function getUnexpectedPaymentsBetweenDates(DateTime $currentDate, DateTime $nextDate, array $unexpectedPayments): array
{
    $payments = [];

    $currDateStr = $currentDate->format('Y-m-d');
    $nextDateStr = $nextDate->format('Y-m-d');
    foreach ($unexpectedPayments as $unexpectedPayment) {
        if (!($unexpectedPayment instanceof UnexpectedPayment)) {
            throw new \InvalidArgumentException('Array shuld contain instances of UnexpectedPayment');
        }
        $paymentDateStr = $unexpectedPayment->getDate()->format('Y-m-d');
        if ($paymentDateStr >= $currDateStr
            && $paymentDateStr < $nextDateStr) {
            $payments[] = $unexpectedPayment;
        }
    }

    uasort($payments, function($a, $b) {
        $aTs = (int)$a->getDate()->format('U');
        $bTs = (int)$b->getDate()->format('U');
        if ($aTs === $bTs) {
            return 0;
        }
        return ($aTs < $bTs) ? -1 : 1;
    });

    return $payments;
}

/**
 * Форматируем денежную сумму, выраженную в копейках
 * в красивый вид, например 1500000(15 тысяч) -> 15 000.00
 * 
 * @param int $number Число, которое нуждается форматировании
 * @return string
 */
function prettifyNumber(int $number)
{
    return number_format($number / 100, 2, '.', ' ');
}