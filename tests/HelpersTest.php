<?php

namespace Credits;

use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Description of HelpersTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class HelpersTest extends TestCase
{
    public function prettifyNumberDataProvider()
    {
        return [
            [0, '0.00'],
            [1234567, '12 345.67'],
            [1234567890, '12 345 678.90'],
            [-1234567, '-12 345.67'],
            [-1234567890, '-12 345 678.90'],
        ];
    }
    
    /**
     * @dataProvider prettifyNumberDataProvider
     */
    public function testPrettifyNumber(int $initial, string $expected)
    {
        $this->assertEquals($expected, \Credits\prettifyNumber($initial));
    }
    
    public function addDurationToDateDataProvider()
    {
        return [
            [new DateTime('2019-01-31 00:00:00'), CreditParams::DURATION_WEEK, 4, new DateTime('2019-02-28 00:00:00')],
            [new DateTime('2020-01-15 00:00:00'), CreditParams::DURATION_WEEK, 4, new DateTime('2020-02-12 00:00:00')],
            [new DateTime('2020-01-15 00:00:00'), CreditParams::DURATION_WEEK, 7, new DateTime('2020-03-04 00:00:00')],
            [new DateTime('2019-01-31 00:00:00'), CreditParams::DURATION_TWO_WEEKS, 3, new DateTime('2019-03-14 00:00:00')],
            [new DateTime('2020-01-15 00:00:00'), CreditParams::DURATION_TWO_WEEKS, 1, new DateTime('2020-01-29 00:00:00')],
            [new DateTime('2020-01-15 00:00:00'), CreditParams::DURATION_TWO_WEEKS, 2, new DateTime('2020-02-12 00:00:00')],
            [new DateTime('2019-01-31 00:00:00'), CreditParams::DURATION_MONTH, 1, new DateTime('2019-02-28 00:00:00')],
            [new DateTime('2019-01-31 00:00:00'), CreditParams::DURATION_MONTH, 2, new DateTime('2019-03-31 00:00:00')],
            [new DateTime('2019-01-30 00:00:00'), CreditParams::DURATION_MONTH, 4, new DateTime('2019-05-30 00:00:00')],
            [new DateTime('2020-01-15 00:00:00'), CreditParams::DURATION_MONTH, 5, new DateTime('2020-06-15 00:00:00')],
            [new DateTime('2020-01-29 00:00:00'), CreditParams::DURATION_MONTH, 1, new DateTime('2020-02-29 00:00:00')],
            [new DateTime('2020-01-30 00:00:00'), CreditParams::DURATION_MONTH, 1, new DateTime('2020-02-29 00:00:00')],
            [new DateTime('2020-01-31 00:00:00'), CreditParams::DURATION_MONTH, 1, new DateTime('2020-02-29 00:00:00')],
            [new DateTime('2019-12-31 00:00:00'), CreditParams::DURATION_MONTH, 2, new DateTime('2020-02-29 00:00:00')],
            [new DateTime('2019-01-31 00:00:00'), CreditParams::DURATION_QUARTER, 1, new DateTime('2019-05-02 00:00:00')],
            [new DateTime('2020-01-31 00:00:00'), CreditParams::DURATION_QUARTER, 4, new DateTime('2021-01-29 00:00:00')],
        ];
    }

    /**
     * @dataProvider addDurationToDateDataProvider
     */
    public function testAddDurationToDateOk(DateTime $initial, int $durationType, int $repaymentNumber, DateTime $expected)
    {
        $actual = \Credits\addDurationToDate($initial, $durationType, $repaymentNumber);
        $this->assertEquals($expected->format('Y-m-d'), $actual->format('Y-m-d'));
    }

    public function testAddDurationToDateInvalidRepaymentNumber()
    {
        $this->expectException(\InvalidArgumentException::class);
        \Credits\addDurationToDate(new DateTime('2019-12-31 00:00:00'), CreditParams::DURATION_MONTH, 0);
    }

    public function testAddDurationToDateInvalidDurationType()
    {
        $this->expectException(\InvalidArgumentException::class);
        \Credits\addDurationToDate(new DateTime('2019-12-31 00:00:00'), -666, 3);
    }

    public function testGetUnexpectedPaymentsBetweenDatesOk()
    {
        $unexpectedPayments = [
            new UnexpectedPayment(10000, new DateTime('2018-12-31 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(20000, new DateTime('2019-01-01 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(30000, new DateTime('2019-01-01 01:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(40000, new DateTime('2019-01-01 02:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(50000, new DateTime('2019-01-01 03:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(60000, new DateTime('2019-01-02 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(70000, new DateTime('2019-01-05 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(80000, new DateTime('2019-02-01 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
        ];

        // дата платежа должна быть меньше, чем датаПО, а время нещитово, поэтому не учитываем платеж
        $payments = \Credits\getUnexpectedPaymentsBetweenDates(new DateTime('2018-12-31 00:00:00'), new DateTime('2018-12-31 23:59:59'), $unexpectedPayments);
        $this->assertEquals(0, count($payments));

        $payments = \Credits\getUnexpectedPaymentsBetweenDates(new DateTime('2019-01-01 00:00:00'), new DateTime('2019-01-02 00:00:00'), $unexpectedPayments);
        $this->assertEquals(4, count($payments));
        // Проверяем, что внутри одной даты платежи отсортированы по времени
        for ($i = 0; $i < count($payments); $i++) {
            $current = $payments[$i];
            $this->assertEquals("2019-01-01 0{$i}:00:00", $current->getDate()->format('Y-m-d H:i:s'));
        }

        $payments = \Credits\getUnexpectedPaymentsBetweenDates(new DateTime('2019-01-01 00:00:00'), new DateTime('2019-02-01 00:00:00'), $unexpectedPayments);
        $this->assertEquals(6, count($payments));

        // сравнение происходит только по датам, поэтому время нещитово
        $payments = \Credits\getUnexpectedPaymentsBetweenDates(new DateTime('2019-01-01 23:59:59'), new DateTime('2019-02-01 23:59:59'), $unexpectedPayments);
        $this->assertEquals(6, count($payments));
    }

    public function testGetUnexpectedPaymentsBetweenDatesInvalidArg()
    {
        $unexpectedPayments = [
            new UnexpectedPayment(10000, new DateTime('2018-12-31 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(20000, new DateTime('2019-01-01 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            'meow',
            new UnexpectedPayment(30000, new DateTime('2019-01-01 01:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(40000, new DateTime('2019-01-01 02:00:00'), UnexpectedPayment::LESS_PAYMENT),
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        \Credits\getUnexpectedPaymentsBetweenDates(new DateTime('2018-12-31 00:00:00'), new DateTime('2018-12-31 23:59:59'), $unexpectedPayments);
    }
}
