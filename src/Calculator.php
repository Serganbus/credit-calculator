<?php

namespace Credits;

use InvalidArgumentException;

/**
 * Конфигурирование кредитного калькулятора
 * и расчет параметров кредита с его помощью
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class Calculator
{
    const TYPE_ANNUITY = 0;
    const TYPE_DIFFERENTIAL = 1;

    /**
     * Настройки расчета графика погашения кредита
     *
     * @var array
     */
    private $config;

    /**
     * @param array $config Массив с конфигурацией кредитного калькулятора
     */
    public function __construct(array $config = null)
    {
        if (is_null($config)) {
            $config = [
                self::TYPE_ANNUITY => TypeAnnuity::class,
                self::TYPE_DIFFERENTIAL => TypeDifferential::class,
            ];
        }

        $this->config = $config;
    }

    /**
     * Расчет графика погашения кредита по заданным параметрам
     *
     * @param CreditParams $creditParams Параметры кредита
     * @param int $repaymentType Тип графика погашения, который необходимо рассчитать
     * @return RepaymentSchedule
     * @throws InvalidArgumentException
     */
    public function calculate(CreditParams $creditParams, int $repaymentType = self::TYPE_ANNUITY): RepaymentSchedule
    {
        foreach ($this->config as $configRepaymentType => $calculatorClass) {
            if ($repaymentType !== $configRepaymentType) {
                continue;
            }

            try {
                $concreteCreditCalculator = new $calculatorClass($creditParams);
            } catch (\Throwable $ex) {
                throw new \LogicException('Calculator for requested repayment type not found', 0, $ex);
            }
            
            return $concreteCreditCalculator->getRepaymentSchedule();
        }

        throw new InvalidArgumentException("Calculator for credit type '{$repaymentType}' not found");
    }
}