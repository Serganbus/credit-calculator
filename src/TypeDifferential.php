<?php

namespace Credits;

/**
 * Расчет графика платежей при диффиренцированных платежах
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class TypeDifferential extends AbstractCreditType
{
    public function getRepaymentSchedule(): RepaymentSchedule
    {
        $initialDate = $this->creditParams->getInitialDate();
        $requestedSum = $this->creditParams->getRequestedSum();
        $percents = $this->creditParams->getPercents();
        $repaymentsCount = $this->creditParams->getRepaymentPeriodsCount();
        $durationType = $this->creditParams->getDurationType();

        $schedule = [
            new RepaymentParams($initialDate, 0, 0, 0, $requestedSum)
        ];

        $bodyToRepay = $requestedSum / $repaymentsCount;
        $previousRepaymentDate = clone $initialDate;
        $remainingAmount = $requestedSum;
        for($i = 1; $i <= $repaymentsCount; $i++) {
            $currentRepaymentDate = $this->addDurationToDate($durationType, $i, $initialDate);

            $interval = $currentRepaymentDate->diff($previousRepaymentDate);
            $daysDiff = $interval->days;
            $daysInYear = $previousRepaymentDate->format('L') == 1 ? 366: 365;

            // Выплачено процентов
            $percentsRepayed = round($remainingAmount * $percents * $daysDiff / ($daysInYear * 10000));

            // Платежи досрочного погашения, которые есть в периоде
            $prevUnexpectedRepaymentDate = clone $previousRepaymentDate;
            $unexpectedPayments = $this->getUnexpectedPaymentsBetweenDates($currentRepaymentDate, $previousRepaymentDate);
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

        return new RepaymentSchedule($schedule, $this->creditParams);
    }
}