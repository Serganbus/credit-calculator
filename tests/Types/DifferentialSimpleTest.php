<?php

namespace Credits\Types;

use PHPUnit\Framework\TestCase;
use Credits\CreditParams;
use Credits\RepaymentParams;

/**
 * Description of DifferentialSimpleTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class DifferentialSimpleTest extends TestCase
{
    /**
     * @var DifferentialSimple
     */
    private $credit;

    public function setUp(): void
    {
        $this->credit = new DifferentialSimple();
    }

    public function testGetRepaymentSchedule()
    {
        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $actualRepayments = $this->credit->getRepaymentSchedule($params, []);

        $expectedRepayments = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 9147032, 'percents' => 813699, 'body' => 8333333, 'balance' => 91666667],
            ['date' => '31.12.2019', 'payment' => 9104086, 'percents' => 770753, 'body' => 8333333, 'balance' => 83333334],
            ['date' => '31.01.2020', 'payment' => 9034018, 'percents' => 700685, 'body' => 8333333, 'balance' => 75000001],
            ['date' => '29.02.2020', 'payment' => 8921653, 'percents' => 588320, 'body' => 8333333, 'balance' => 66666668],
            ['date' => '31.03.2020', 'payment' => 8892349, 'percents' => 559016, 'body' => 8333333, 'balance' => 58333335],
            ['date' => '30.04.2020', 'payment' => 8806694, 'percents' => 473361, 'body' => 8333333, 'balance' => 50000002],
            ['date' => '31.05.2020', 'payment' => 8752595, 'percents' => 419262, 'body' => 8333333, 'balance' => 41666669],
            ['date' => '30.06.2020', 'payment' => 8671448, 'percents' => 338115, 'body' => 8333333, 'balance' => 33333336],
            ['date' => '31.07.2020', 'payment' => 8612841, 'percents' => 279508, 'body' => 8333333, 'balance' => 25000003],
            ['date' => '31.08.2020', 'payment' => 8542964, 'percents' => 209631, 'body' => 8333333, 'balance' => 16666670],
            ['date' => '30.09.2020', 'payment' => 8468579, 'percents' => 135246, 'body' => 8333333, 'balance' => 8333337],
            ['date' => '31.10.2020', 'payment' => 8403214, 'percents' => 69877,  'body' => 8333337, 'balance' => 0],
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