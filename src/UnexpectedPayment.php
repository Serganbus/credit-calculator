<?php

namespace Credits;

/**
 * Параметры частичного досрочного погашения
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class UnexpectedPayment
{
    const LESS_PAYMENT = 0;
    const LESS_LOAN_PERIOD = 1;

    /** @var int Сумма платежа */
    private $amount;

    /** @var \DateTime Дата платежа */
    private $date;

    /** @var int Тип влияния на кредит */
    private $type;

    /**
     * @param int $amount Сумма платежа в копейках
     * @param \DateTime $date Дата платежа
     * @param int $type Тип влияния на пересчет кредита
     * @throws \InvalidArgumentException
     */
    public function __construct(int $amount, \DateTime $date, int $type = self::LESS_LOAN_PERIOD)
    {
        if ($type !== self::LESS_LOAN_PERIOD && $type !== self::LESS_PAYMENT) {
            throw new \InvalidArgumentException('Unexpected payment invalid type');
        }
        $this->amount = $amount;
        $this->date = $date;
        $this->type = $type;
    }

    /**
     * Вернуть дату платежа
     *
     * @return \DateTime
     */
    public function getDate(): \DateTime
    {
        return $this->date;
    }

    /**
     * Вернуть сумму платежа
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Вернуть тип влияния на кредит
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }
}