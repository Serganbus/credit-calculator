<?php

namespace Credits;

/**
 * Расчет графика платежей при аннуитетных платежах.
 * Частичные/досрочные платежи платятся с расчетом на том,
 * что проценты по кредиту начисляются в конце расчетного периода
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class TypeAnnuity extends AbstractCreditType
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

        $fullPeriodsCount = floor(365 / CreditParams::$daysInPaymentPeriod[$durationType]);
        $repaymentRounded = $this->calcPaymentAmount($requestedSum, $percents, $repaymentsCount, $fullPeriodsCount);

        $previousRepaymentDate = clone $initialDate;
        $remainingAmount = $requestedSum;
        for($i = 1; $i <= $repaymentsCount; $i++) {
            $currentRepaymentDate = $this->addDurationToDate($durationType, $i, $initialDate);
            $interval = $currentRepaymentDate->diff($previousRepaymentDate);

            // Выплачено процентов
            $percentsRepayed = round($remainingAmount * $percents / ($fullPeriodsCount * 10000));

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
                    $repaymentRounded = $this->calcPaymentAmount($remainingAmount, $percents, $repaymentsCount - $i + 1, $fullPeriodsCount);
                    $percentsRepayed  = round($remainingAmount * $percents / ($fullPeriodsCount * 10000));
                }
                if ($recalcType === UnexpectedPayment::LESS_LOAN_PERIOD) {
                    $percentsInPeriod = $percents / ($fullPeriodsCount * 10000);
                    $log = $percentsInPeriod / ($repaymentRounded / $remainingAmount - $percentsInPeriod) + 1;
                    $newRepaymentsCount = (int)round(log($log, 1 + $percentsInPeriod) + 0.5);
                    $repaymentsCount = $i + $newRepaymentsCount - 1;
                }

                $schedule[] = new RepaymentParams($unexpectedPaymentDate, $unexpectedAmount, 0, $unexpectedAmount, $remainingAmount);

                $prevUnexpectedRepaymentDate = $unexpectedPaymentDate;
            }

            // Выплачено всего
            $currentRepayment = $repaymentRounded;
            if ($i === $repaymentsCount) {
                $currentRepayment = $remainingAmount + $percentsRepayed;
            }

            // Выплачено тело долга
            $bodyRepayed = $currentRepayment - $percentsRepayed;

            $previousRepaymentDate = $currentRepaymentDate;
            $remainingAmount -= $bodyRepayed;

            $schedule[] = new RepaymentParams($currentRepaymentDate, $currentRepayment, $percentsRepayed, $bodyRepayed, $remainingAmount);
        }

        return new RepaymentSchedule($schedule, $this->creditParams);
    }

    private function calcPaymentAmount(int $creditAmount, int $percents, int $repaymentsCount, int $fullPeriodsCount)
    {
        $percentsInPeriod = $percents / ($fullPeriodsCount * 10000);
        $neededExp = pow(1 + $percentsInPeriod, $repaymentsCount);
        $annuitetKoefficient = ($percentsInPeriod * $neededExp)/($neededExp - 1);
        $repayment = $annuitetKoefficient * $creditAmount;
        $repaymentRounded = round($repayment);

        return $repaymentRounded;
    }
}