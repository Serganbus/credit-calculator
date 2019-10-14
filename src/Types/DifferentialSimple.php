<?php

namespace Credits\Types;

use Credits\CreditParams;
use Credits\RepaymentParams;
use Credits\RepaymentSchedule;
use Credits\UnexpectedPayment;

/**
 * Расчет графика платежей при диффиренцированных платежах
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class DifferentialSimple implements Calculatable
{
    public function getRepaymentSchedule(CreditParams $params, array $unexpectedPayments): RepaymentSchedule
    {
        $initialDate = $params->getInitialDate();
        $requestedSum = $params->getRequestedSum();
        $percents = $params->getPercents();
        $repaymentsCount = $params->getRepaymentPeriodsCount();
        $durationType = $params->getDurationType();

        $schedule = [
            new RepaymentParams($initialDate, 0, 0, $requestedSum)
        ];

        $bodyToRepay = $requestedSum / $repaymentsCount;
        $previousRepaymentDate = clone $initialDate;
        $remainingAmount = $requestedSum;
        for($i = 1; $i <= $repaymentsCount; $i++) {
            $currentRepaymentDate = \Credits\addDurationToDate($initialDate, $durationType, $i);

            $interval = $currentRepaymentDate->diff($previousRepaymentDate);
            $daysDiff = $interval->days;
            $daysInYear = $previousRepaymentDate->format('L') == 1 ? 366: 365;

            // Комиссия
            $comission = $params->getPeriodicComission();
            if ($i === 1) {
                $comission += $params->getOneTimeComission();
            }

            // Выплачено процентов
            $percentsRepayed = round($remainingAmount * $percents * $daysDiff / ($daysInYear * 10000));

            // Платежи досрочного погашения, которые есть в периоде
            $prevUnexpectedRepaymentDate = clone $previousRepaymentDate;
            $unexpectedPaymentsInPeriod = \Credits\getUnexpectedPaymentsBetweenDates($previousRepaymentDate, $currentRepaymentDate, $unexpectedPayments);
            foreach ($unexpectedPaymentsInPeriod as $unexpectedPayment) {
                $recalcType = $unexpectedPayment->getType();
                $unexpectedPaymentDate = $unexpectedPayment->getDate();
                $unexpectedAmount = $unexpectedPayment->getAmount();
                $interval = $unexpectedPaymentDate->diff($prevUnexpectedRepaymentDate);

                $remainingAmount -= $unexpectedAmount;
                if ($recalcType === UnexpectedPayment::LESS_PAYMENT) {
                    $bodyToRepay = $remainingAmount / ($repaymentsCount - $i + 1);
                }
                if ($recalcType === UnexpectedPayment::LESS_LOAN_PERIOD) {
                    $newRepaymentsCount = (int)round($remainingAmount / $bodyToRepay + 0.5);
                    $repaymentsCount = $i + $newRepaymentsCount - 1;
                }

                $schedule[] = new RepaymentParams($unexpectedPaymentDate, 0, $unexpectedAmount, $remainingAmount);

                $prevUnexpectedRepaymentDate = $unexpectedPaymentDate;
            }

            // Выплачено тело кредита
            $bodyRepayed = round($bodyToRepay);
            if ($i === $repaymentsCount) {
                $bodyRepayed += ($remainingAmount - $bodyRepayed);
            }

            // Выплачено всего
            $currentRepayment = $percentsRepayed + $bodyRepayed;

            $previousRepaymentDate = $currentRepaymentDate;
            $remainingAmount -= $bodyRepayed;

            $schedule[] = new RepaymentParams($currentRepaymentDate, $percentsRepayed, $bodyRepayed, $remainingAmount, $comission);
        }

        return new RepaymentSchedule($schedule, $params);
    }
}