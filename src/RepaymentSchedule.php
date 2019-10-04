<?php

namespace Credits;

/**
 * Description of RepaymentSchedule
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentSchedule implements \Iterator, \Countable
{
    /** @var int */
    private $position;

    /** @var array */
    private $repayments;

    /** @var CreditParams */
    private $creditParmas;

    /** @var int */
    private $creditTotalCost;

    /**
     * @param array $repayments Массив графика погашений
     */
    public function __construct(array $repayments, CreditParams $params)
    {
        foreach ($repayments as $repayment) {
            if (!($repayment instanceof RepaymentParams)) {
                throw \InvalidArgumentException('Element of array does not instance of \Credits\RepaymentParams class');
            }
        }

        $this->position = 0;
        $this->repayments = $repayments;
        $this->creditParmas = $params;
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
     * Вернуть текущий элемент из графика погашения
     *
     * @return RepaymentParams
     */
    public function current(): RepaymentParams
    {
        if (!$this->valid()) {
            $count = count($this->repayments);
            throw new \RangeException("Invalid position. Position: {$this->position}, elements count: {$count}");
        }
        return $this->repayments[$this->position];
    }

    /**
     * Вернуть текущий номер погашения
     *
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Перейти к следующему элементу погашения
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Перейти к первоначальному элементу из графика погашения
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Текущий элемент всегда валиден
     *
     * @return void
     */
    public function valid(): bool
    {
        return isset($this->repayments[$this->position]);
    }

    /**
     * Вернуть количество элементов в графике погашения
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->repayments);
    }

    /**
     * Вернуть полную стоимость кредита
     *
     * @return int
     */
    public function calculateTotalCost(): int
    {
        if (!is_null($this->creditTotalCost)) {
            return $this->creditTotalCost;
        }
        
        $dates = [];
        $payments = [];
        $e = [];
        $q = [];
        $creditDuration = $this->creditParmas->getDurationType();
        $daysInBasePeriod = CreditParams::$daysInPaymentPeriod[$creditDuration];

        /** @var \DateTime $initialDate */
        $initialDate = $this->repayments[0]->getDate();
        for ($i = 0; $i < count($this->repayments); $i++) {
            /** @var RepaymentParams $repayment */
            $repayment = $this->repayments[$i];

            $dates[] = $repayment->getDate();
            if ($i === 0) {
                $payments[] = -$repayment->getBalance();
            } else {
                $payments[] = $repayment->getPayment();
            }

            $interval = $repayment->getDate()->diff($initialDate);
            $daysDiff = $interval->days;
            $e[] = ($daysDiff % $daysInBasePeriod) / $daysInBasePeriod;
            $q[] = floor($daysDiff / $daysInBasePeriod);
        }

        $basePeriodsCount = 365 / $daysInBasePeriod;
        $i = 0;
        $x = 1;
        $x_m = 0;
        $s = 0.000001;
        while($x > 0) {
            $x_m = $x;
            $x = 0;
            for ($k = 0; $k < count($dates); $k++) {
                $x = $x + $payments[$k] / ((1 + $e[$k] * $i) * pow(1 + $i, $q[$k]));
            }
            $i = $i + $s;
        }
        if ($x > $x_m) {
            $i = $i - $s;
        }

        $this->creditTotalCost = (int)($i * $basePeriodsCount * 10000);

        return $this->creditTotalCost;
    }

    /**
     * Вернуть сумму всех платежей по кредиту
     *
     * @return int
     */
    public function calculateTotalPayments(): int
    {
        return array_reduce($this->repayments, function (int $carry, $repayment) {
            $carry += $repayment->getPayment();
            return $carry;
        }, 0);
    }

    /**
     * Вернуть сумму переплаты по кредиту
     *
     * @return int
     */
    public function calculateOverpayment(): int
    {
        return array_reduce($this->repayments, function (int $carry, $repayment) {
            $carry += $repayment->getPercents();
            return $carry;
        }, 0);
    }
}