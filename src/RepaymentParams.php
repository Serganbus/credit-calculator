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

    public function __construct(DateTime $date, int $payment, int $percents, int $body, int $balance)
    {
        $this->date = $date;
        $this->payment = $payment;
        $this->percents = $percents;
        $this->body = $body;
        $this->balance = $balance;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getPayment(): int
    {
        return $this->payment;
    }

    public function getPercents(): int
    {
        return $this->percents;
    }

    public function getBody(): int
    {
        return $this->body;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }
}