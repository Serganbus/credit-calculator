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
            $daysInYear = $currentRepaymentDate->format('L') == 1 ? 366: 365;

            // Выплачено процентов
            $percentsRepayed = round($remainingAmount * $percents * $daysDiff / ($daysInYear * 10000));

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