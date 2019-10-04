<?php

namespace Credits;

use PHPUnit\Framework\TestCase;
use Credits\CreditParams;
use Credits\RepaymentParams;

/**
 * Description of TypeAnnuityTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class TypeAnnuityTest extends TestCase
{
    /**
     * @var TypeAnnuity
     */
    private $credit;

    public function setUp(): void
    {
        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $this->credit = new TypeAnnuity($params);
    }

    public function testGetCreditParams()
    {
        $params = $this->credit->getCreditParams();
        $this->assertInstanceOf(CreditParams::class, $params);
    }

    public function testGetRepaymentSchedule()
    {
        $actualRepayments = $this->credit->getRepaymentSchedule();

        $expectedRepayments = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 8786938, 'percents' => 825000, 'body' => 7961938, 'balance' => 92038062],
            ['date' => '31.12.2019', 'payment' => 8786938, 'percents' => 759314, 'body' => 8027624, 'balance' => 84010438],
            ['date' => '31.01.2020', 'payment' => 8786938, 'percents' => 693086, 'body' => 8093852, 'balance' => 75916586],
            ['date' => '29.02.2020', 'payment' => 8786938, 'percents' => 626312, 'body' => 8160626, 'balance' => 67755960],
            ['date' => '31.03.2020', 'payment' => 8786938, 'percents' => 558987, 'body' => 8227951, 'balance' => 59528009],
            ['date' => '30.04.2020', 'payment' => 8786938, 'percents' => 491106, 'body' => 8295832, 'balance' => 51232177],
            ['date' => '31.05.2020', 'payment' => 8786938, 'percents' => 422665, 'body' => 8364273, 'balance' => 42867904],
            ['date' => '30.06.2020', 'payment' => 8786938, 'percents' => 353660, 'body' => 8433278, 'balance' => 34434626],
            ['date' => '31.07.2020', 'payment' => 8786938, 'percents' => 284086, 'body' => 8502852, 'balance' => 25931774],
            ['date' => '31.08.2020', 'payment' => 8786938, 'percents' => 213937, 'body' => 8573001, 'balance' => 17358773],
            ['date' => '30.09.2020', 'payment' => 8786938, 'percents' => 143210, 'body' => 8643728, 'balance' => 8715045],
            ['date' => '31.10.2020', 'payment' => 8786944, 'percents' => 71899,  'body' => 8715045, 'balance' => 0],
        ];

        $this->assertEquals(count($expectedRepayments), count($actualRepayments));

        $i = 0;
        foreach ($actualRepayments as $actualRepayment) {
            $this->assertInstanceOf(RepaymentParams::class, $actualRepayment);
            $this->assertEquals($expectedRepayments[$i]['date'], $actualRepayment->getDate()->format('d.m.Y'));
            $this->assertEquals($expectedRepayments[$i]['payment'], $actualRepayment->getPayment());
            $this->assertEquals($expectedRepayments[$i]['percents'], $actualRepayment->getPercents());
            $this->assertEquals($expectedRepayments[$i]['body'], $actualRepayment->getBody());
            $this->assertEquals($expectedRepayments[$i]['balance'], $actualRepayment->getBalance());

            $i++;
        }
    }

    public function tearDown(): void
    {
        $this->credit = null;
    }
}