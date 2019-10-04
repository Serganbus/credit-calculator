<?php

namespace Credits;

use PHPUnit\Framework\TestCase;

/**
 * Description of CalculatorTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class CalculatorTest extends TestCase
{
    public function testCalculateOk()
    {
        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $calculator = new Calculator();

        // при аннуитетном графике погашений -
        // одинаковые суммы погашения, кроме последнего платежа.
        // Последний платеж обычно меньше ежемесячного платежа.
        $annuityRepaymentSchedule = $calculator->calculate($params, Calculator::TYPE_ANNUITY);
        $repaymentsCount = count($annuityRepaymentSchedule);
        while($annuityRepaymentSchedule->valid()) {
            $repaymentParams = $annuityRepaymentSchedule->current();
            if ($annuityRepaymentSchedule->key() === 0) {
                $this->assertEquals(0, $repaymentParams->getPayment());
            } elseif ($annuityRepaymentSchedule->key() < $repaymentsCount - 1) {
                $this->assertEquals(8786938, $repaymentParams->getPayment());
            } else {
                $this->assertTrue(8786938 < $repaymentParams->getPayment());
            }

            $annuityRepaymentSchedule->next();
        }

        // при дифференциальном графике погашений -
        // одинаковые суммы погашения по телу кредита, кроме последнего платежа.
        $differentialRepaymentSchedule = $calculator->calculate($params, Calculator::TYPE_DIFFERENTIAL);
        $repaymentsCount = count($differentialRepaymentSchedule);
        while($differentialRepaymentSchedule->valid()) {
            $repaymentParams = $differentialRepaymentSchedule->current();
            if ($differentialRepaymentSchedule->key() === 0) {
                $this->assertEquals(0, $repaymentParams->getPayment());
            } elseif ($differentialRepaymentSchedule->key() < $repaymentsCount - 1) {
                $this->assertEquals(8333333, $repaymentParams->getBody());
            }

            $differentialRepaymentSchedule->next();
        }
    }

    public function testCalculateException()
    {
        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $calculator = new Calculator();

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate($params, 666);
    }

    public function testCalculateException2()
    {
        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $calculator = new Calculator([
            Calculator::TYPE_ANNUITY => '\Credits\NotFoundClass'
        ]);

        $this->expectException(\LogicException::class);
        $calculator->calculate($params, Calculator::TYPE_ANNUITY);
    }
}