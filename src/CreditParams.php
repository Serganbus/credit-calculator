<?php

namespace Credits;

use DateTime;

/**
 * Хранит и возвращает параметры кредита
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class CreditParams
{
    const DURATION_WEEK = 1;
    const DURATION_TWO_WEEKS = 2;
    const DURATION_MONTH = 3;
    const DURATION_QUARTER = 4;

    public static $daysInPaymentPeriod = [
        self::DURATION_WEEK => 7,
        self::DURATION_TWO_WEEKS => 14,
        self::DURATION_MONTH => 30,
        self::DURATION_QUARTER => 91,
    ];

    /**
     * @var DateTime Дата получения займа
     */
    protected $initialDate;

    /**
     * @var int Тип продолжительности платежного периода: дни, недели, месяцы и т.д.
     */
    protected $durationType;

    /**
     * @var int Количество платежей по графику
     */
    protected $repaymentPeriodsCount;

    /**
     * @var int Сумма кредита(в копейках)
     */
    protected $requestedSum;

    /**
     * @var int Процентная ставка(в сотых долях)
     */
    protected $percents;

    /**
     * @var int Единовременная комиссия(в сотых долях)
     */
    protected $oneTimeComission = 0;

    /**
     * @var int Периодическая комиссия(в сотых долях)
     */
    protected $periodicComission = 0;

    public function __construct(DateTime $initialDate, int $requestedSum, int $percents, int $repaymentPeriodsCount, int $durationType)
    {
        if (!in_array($durationType, array_keys(static::$daysInPaymentPeriod))) {
            throw new \InvalidArgumentException("Invalid duration type. Actual: {$durationType}");
        }

        $this->repaymentPeriodsCount = $repaymentPeriodsCount;
        $this->initialDate = $initialDate;
        $this->requestedSum = $requestedSum;
        $this->percents = $percents;
        $this->durationType = $durationType;
    }

    /**
     * Установка единовременной комиссии по кредиту
     *
     * @param int $comission
     * @return CreditParams
     */
    public function setOneTimeComission(int $comission): CreditParams
    {
        $this->oneTimeComission = $comission;
        return $this;
    }

    /**
     * Вернуть сумму разовой комиссии
     *
     * @return int
     */
    public function getOneTimeComission(): int
    {
        return $this->oneTimeComission;
    }

    /**
     * Установка периодической комиссии по кредиту
     *
     * @param int $comission
     * @return CreditParams
     */
    public function setPeriodicComission(int $comission): CreditParams
    {
        $this->periodicComission = $comission;
        return $this;
    }

    /**
     * Вернуть сумму периодической комиссии
     *
     * @return int
     */
    public function getPeriodicComission(): int
    {
        return $this->periodicComission;
    }

    /**
     * Вернуть сумму кредита
     *
     * @return int
     */
    public function getRequestedSum(): int
    {
        return $this->requestedSum;
    }

    /**
     * Вернуть тип продолжительности платежного периода
     *
     * @return int
     */
    public function getDurationType(): int
    {
        return $this->durationType;
    }

    /**
     * Вернуть дату получения займа
     *
     * @return DateTime
     */
    public function getInitialDate(): DateTime
    {
        return $this->initialDate;
    }

    /**
     * Вернуть количество платежных периодов в кредите
     *
     * @return int
     */
    public function getRepaymentPeriodsCount(): int
    {
        return $this->repaymentPeriodsCount;
    }

    /**
     * Вернуть проценты годовых по кредиту(не ПСК)
     *
     * @return int
     */
    public function getPercents(): int
    {
        return $this->percents;
    }
}