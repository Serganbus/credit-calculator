# Кредитный калькулятор
[![Build Status](https://travis-ci.com/Serganbus/credit-calculator.svg?branch=master)](https://travis-ci.com/Serganbus/credit-calculator)
[![codecov](https://codecov.io/gh/Serganbus/credit-calculator/branch/master/graph/badge.svg)](https://codecov.io/gh/Serganbus/credit-calculator)

Расчет графика погашения кредита с возможностью указания платежей досрочного погашения. Поддерживаются как аннуитетный порядок погашения, так дифференцированный.

**Важно!**
Все денежные значения в пакете являются целыми числами и представляют собой сумму с копейками.
То есть, значение 100.5(сто рублей, 50 копеек) в сервисе представляется в виде целого числа 100500.

## Установка
Через composer: `composer require serganbus/credit-calculator`

## Использование
Пример использования калькулятора:
```php
require __DIR__ . '/../vendor/autoload.php';

use Credits\Calculator;
use Credits\CreditParams;
use Credits\UnexpectedPayment;
use Credits\RepaymentSchedule;

$params = new CreditParams(new DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
$unexpectedPayments = [
    new UnexpectedPayment(10000000, new DateTime('2019-12-15 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
    new UnexpectedPayment(3565411, new DateTime('2020-01-13 00:00:00'), UnexpectedPayment::LESS_LOAN_PERIOD),
    new UnexpectedPayment(15000000, new DateTime('2020-02-29 00:00:00'), UnexpectedPayment::LESS_LOAN_PERIOD),
];

$calculator = new Calculator;

// считаем график погашений с аннуитетными платежами
// Чтобы были дифференцированные платежи, третьим параметром указываем Calculator::TYPE_TYPE_DIFFERENTIAL

/** @var RepaymentSchedule $schedule График платежей */
$schedule = $calculator->calculate($params, $unexpectedPayments, Calculator::TYPE_ANNUITY);
foreach ($schedule as $repayment) {
    /** @var DateTime $date Дата очередного платежного периода */
    $date = $repayment->getDate()->format('d.m.Y');

    /** @var int $payment Сумма очередного платежа */
    $payment = $repayment->getPayment();

    /** @var int $percents Проценты по очередному платежу */
    $percents = $repayment->getPercents();

    /** @var int $body Тело займа по очередному платежу */
    $body = $repayment->getBody();

    /** @var int $body Остаток по займу после платежа */
    $balance = $repayment->getBalance();
}

/** @var int $total Получить сумму всех платежей по займу */
$total = $schedule->calculateTotalPayments();

/** @var int $overpayment Получить сумму переплаты по займу */
$overpayment = $schedule->calculateOverpayment();

/** @var int $requestedSum Получить сумму займа */
$requestedSum = $schedule->getCreditParams()->getRequestedSum();

/** @var string psk Полная стоимость кредита в процентах годовых с округлением до 3х знаков */
$psk = $schedule->calculateTotalCost();
```

## Расширение
Можно написать свой алгоритм расчета графика платежей по заданным кредитным параметрам.
Для этого необходимо реализовать интерфейс `Credits\Types\Calculatable`.

Инициализация класса калькулятора параметризуется конфигом,
где ключом является тип погашения(аннуитетный или дифференцированный),
а значениями соответствующий алгоритм расчета графика платежей, реализующий интерфейс `Credits\Types\Calculatable`.