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
            new RepaymentParams($initialDate, 0, 0, 0, $requestedSum)
        ];

        $bodyToRepay = $requestedSum / $repaymentsCount;
        $previousRepaymentDate = clone $initialDate;
        $remainingAmount = $requestedSum;
        for($i = 1; $i <= $repaymentsCount; $i++) {
            $currentRepaymentDate = \Credits\addDurationToDate($initialDate, $durationType, $i);

            $interval = $currentRepaymentDate->diff($previousRepaymentDate);
            $daysDiff = $interval->days;
            $daysInYear = $previousRepaymentDate->format('L') == 1 ? 366: 365;

            // Выплачено процентов
            $percentsRepayed = round($remainingAmount * $percents * $daysDiff / ($daysInYear * 10000));

            // Платежи досрочного погашения, которые есть в периоде
            $prevUnexpectedRepaymentDate = clone $previousRepaymentDate;
            $unexpectedPayments = \Credits\getUnexpectedPaymentsBetweenDates($currentRepaymentDate, $previousRepaymentDate, $unexpectedPayments);
            foreach ($unexpectedPayments as $unexpectedPayment) {
                $recalcType = $unexpectedPayment->getType();
                $unexpectedPaymentDate = $unexpectedPayment->getDate();
                $unexpectedAmount = $unexpectedPayment->getAmount();
                $interval = $unexpectedPaymentDate->diff($prevUnexpectedRepaymentDate);

                $remainingAmount -= $unexpectedAmount;
                if ($recalcType === UnexpectedPayment::LESS_PAYMENT) {
                    $bodyToRepay = $remainingAmount / ($repaymentsCount - $i + 1);
                }
                if ($recalcType === UnexpectedPayment::LESS_LOAN_PERIOD) {
                    $newRepaymentsCount = (int)round($remainingAmount / $bodyToRepay);
                    $repaymentsCount = $i + $newRepaymentsCount - 1;
                }

                $schedule[] = new RepaymentParams($unexpectedPaymentDate, $unexpectedAmount, 0, $unexpectedAmount, $remainingAmount);

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

            $schedule[] = new RepaymentParams($currentRepaymentDate, $currentRepayment, $percentsRepayed, $bodyRepayed, $remainingAmount);
        }

        return new RepaymentSchedule($schedule, $params);
    }
}