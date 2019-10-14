<?php

namespace Credits;

use DateTime;

/**
 * Хранит и возвращает параметры одного платежа по кредиту
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentParams
{
    /**
     * @var DateTime Дата платежа
     */
    private $date;

    /**
     * @var int Сумма платежа
     */
    private $payment;

    /**
     * @var int Сумма процентов в платеже
     */
    private $percents;

    /**
     * @var int Сумма тела кредита в платеже
     */
    private $body;

    /**
     * @var int Остаток кредита
     */
    private $balance;

    /**
     * @var int Комиссия
     */
    private $comission;

    public function __construct(DateTime $date, int $percents, int $body, int $balance, int $comission = 0)
    {
        $this->date = $date;
        $this->percents = $percents;
        $this->body = $body;
        $this->balance = $balance;
        $this->comission = $comission;
        $this->payment = $percents + $body + $comission;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * Сумма платежа, всего
     *
     * @return int
     */
    public function getPayment(): int
    {
        return $this->payment;
    }

    /**
     * Проценты по кредиту в платеже
     *
     * @return int
     */
    public function getPercents(): int
    {
        return $this->percents;
    }

    /**
     * Тело кредита в платеже
     *
     * @return int
     */
    public function getBody(): int
    {
        return $this->body;
    }

    /**
     * Остаток по кредиту после проведения платежа
     *
     * @return int
     */
    public function getBalance(): int
    {
        return $this->balance;
    }

    /**
     * Комиссия в платеже
     *
     * @return int
     */
    public function getComission(): int
    {
        return $this->comission;
    }
}