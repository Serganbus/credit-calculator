<?php

namespace Credits\Types;

use PHPUnit\Framework\TestCase;
use Credits\CreditParams;
use Credits\RepaymentParams;
use Credits\UnexpectedPayment;
use DateTime;

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

    public function getRepaymentScheduleDataProvider()
    {
        $creditParams = new CreditParams(new DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $unexpectedRepayments = [
            new UnexpectedPayment(10000000, new DateTime('2019-12-15 00:00:00'), UnexpectedPayment::LESS_PAYMENT),
            new UnexpectedPayment(3565411, new DateTime('2020-01-13 00:00:00'), UnexpectedPayment::LESS_LOAN_PERIOD),
            new UnexpectedPayment(15000000, new DateTime('2020-02-29 00:00:00'), UnexpectedPayment::LESS_LOAN_PERIOD),
        ];

        // график платежей без всяких там досрочных частичных погашений и каких-либо комиссий
        $expectedRepayments1 = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'comission' => 0, 'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 9147032, 'percents' => 813699, 'body' => 8333333, 'comission' => 0, 'balance' => 91666667],
            ['date' => '31.12.2019', 'payment' => 9104086, 'percents' => 770753, 'body' => 8333333, 'comission' => 0, 'balance' => 83333334],
            ['date' => '31.01.2020', 'payment' => 9034018, 'percents' => 700685, 'body' => 8333333, 'comission' => 0, 'balance' => 75000001],
            ['date' => '29.02.2020', 'payment' => 8921653, 'percents' => 588320, 'body' => 8333333, 'comission' => 0, 'balance' => 66666668],
            ['date' => '31.03.2020', 'payment' => 8892349, 'percents' => 559016, 'body' => 8333333, 'comission' => 0, 'balance' => 58333335],
            ['date' => '30.04.2020', 'payment' => 8806694, 'percents' => 473361, 'body' => 8333333, 'comission' => 0, 'balance' => 50000002],
            ['date' => '31.05.2020', 'payment' => 8752595, 'percents' => 419262, 'body' => 8333333, 'comission' => 0, 'balance' => 41666669],
            ['date' => '30.06.2020', 'payment' => 8671448, 'percents' => 338115, 'body' => 8333333, 'comission' => 0, 'balance' => 33333336],
            ['date' => '31.07.2020', 'payment' => 8612841, 'percents' => 279508, 'body' => 8333333, 'comission' => 0, 'balance' => 25000003],
            ['date' => '31.08.2020', 'payment' => 8542964, 'percents' => 209631, 'body' => 8333333, 'comission' => 0, 'balance' => 16666670],
            ['date' => '30.09.2020', 'payment' => 8468579, 'percents' => 135246, 'body' => 8333333, 'comission' => 0, 'balance' => 8333337],
            ['date' => '31.10.2020', 'payment' => 8403214, 'percents' => 69877,  'body' => 8333337, 'comission' => 0, 'balance' => 0],
        ];

        // график платежей со всякими там досрочными частичными погашениями и без комиссий
        $expectedRepayments2 = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'comission' => 0, 'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 9147032, 'percents' => 813699, 'body' => 8333333, 'comission' => 0, 'balance' => 91666667],
            ['date' => '15.12.2019', 'payment' => 10000000,'percents' => 0,      'body' => 10000000,'comission' => 0, 'balance' => 81666667],
            ['date' => '31.12.2019', 'payment' => 8194995, 'percents' => 770753, 'body' => 7424242, 'comission' => 0, 'balance' => 74242425],
            ['date' => '13.01.2020', 'payment' => 3565411, 'percents' => 0,      'body' => 3565411, 'comission' => 0, 'balance' => 70677014],
            ['date' => '31.01.2020', 'payment' => 8048489, 'percents' => 624247, 'body' => 7424242, 'comission' => 0, 'balance' => 63252772],
            ['date' => '29.02.2020', 'payment' => 7920413, 'percents' => 496171, 'body' => 7424242, 'comission' => 0, 'balance' => 55828530],
            ['date' => '29.02.2020', 'payment' => 15000000,'percents' => 0,      'body' => 15000000,'comission' => 0, 'balance' => 40828530],
            ['date' => '31.03.2020', 'payment' => 7892378, 'percents' => 468136, 'body' => 7424242, 'comission' => 0, 'balance' => 33404288],
            ['date' => '30.04.2020', 'payment' => 7695310, 'percents' => 271068, 'body' => 7424242, 'comission' => 0, 'balance' => 25980046],
            ['date' => '31.05.2020', 'payment' => 7642091, 'percents' => 217849, 'body' => 7424242, 'comission' => 0, 'balance' => 18555804],
            ['date' => '30.06.2020', 'payment' => 7574818, 'percents' => 150576, 'body' => 7424242, 'comission' => 0, 'balance' => 11131562],
            ['date' => '31.07.2020', 'payment' => 7517583, 'percents' => 93341,  'body' => 7424242, 'comission' => 0, 'balance' => 3707320],
            ['date' => '31.08.2020', 'payment' => 3738407, 'percents' => 31087,  'body' => 3707320, 'comission' => 0, 'balance' => 0],
        ];

        // график платежей со всякими там досрочными частичными погашениями и с комиссиями
        $creditParams3 = clone $creditParams;
        $creditParams3->setOneTimeComission(50000);
        $creditParams3->setPeriodicComission(5000);
        $expectedRepayments3 = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'comission' => 0,     'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 9202032, 'percents' => 813699, 'body' => 8333333, 'comission' => 55000, 'balance' => 91666667],
            ['date' => '15.12.2019', 'payment' => 10000000,'percents' => 0,      'body' => 10000000,'comission' => 0,     'balance' => 81666667],
            ['date' => '31.12.2019', 'payment' => 8199995, 'percents' => 770753, 'body' => 7424242, 'comission' => 5000,  'balance' => 74242425],
            ['date' => '13.01.2020', 'payment' => 3565411, 'percents' => 0,      'body' => 3565411, 'comission' => 0,     'balance' => 70677014],
            ['date' => '31.01.2020', 'payment' => 8053489, 'percents' => 624247, 'body' => 7424242, 'comission' => 5000,  'balance' => 63252772],
            ['date' => '29.02.2020', 'payment' => 7925413, 'percents' => 496171, 'body' => 7424242, 'comission' => 5000,  'balance' => 55828530],
            ['date' => '29.02.2020', 'payment' => 15000000,'percents' => 0,      'body' => 15000000,'comission' => 0,     'balance' => 40828530],
            ['date' => '31.03.2020', 'payment' => 7897378, 'percents' => 468136, 'body' => 7424242, 'comission' => 5000,  'balance' => 33404288],
            ['date' => '30.04.2020', 'payment' => 7700310, 'percents' => 271068, 'body' => 7424242, 'comission' => 5000,  'balance' => 25980046],
            ['date' => '31.05.2020', 'payment' => 7647091, 'percents' => 217849, 'body' => 7424242, 'comission' => 5000,  'balance' => 18555804],
            ['date' => '30.06.2020', 'payment' => 7579818, 'percents' => 150576, 'body' => 7424242, 'comission' => 5000,  'balance' => 11131562],
            ['date' => '31.07.2020', 'payment' => 7522583, 'percents' => 93341,  'body' => 7424242, 'comission' => 5000,  'balance' => 3707320],
            ['date' => '31.08.2020', 'payment' => 3743407, 'percents' => 31087,  'body' => 3707320, 'comission' => 5000,  'balance' => 0],
        ];

        return [
            [$creditParams, [], $expectedRepayments1],
            [$creditParams, $unexpectedRepayments, $expectedRepayments2],
            [$creditParams3, $unexpectedRepayments, $expectedRepayments3],
        ];
    }

    /**
     * @dataProvider getRepaymentScheduleDataProvider
     */
    public function testGetRepaymentSchedule(CreditParams $params, array $unexpectedRepayments, array $expectedRepayments)
    {
        $actualRepayments = $this->credit->getRepaymentSchedule($params, $unexpectedRepayments);

        $this->assertEquals(count($expectedRepayments), count($actualRepayments));

        $i = 0;
        foreach ($actualRepayments as $actualRepayment) {
            $this->assertInstanceOf(RepaymentParams::class, $actualRepayment);
            $this->assertEquals($expectedRepayments[$i]['date'], $actualRepayment->getDate()->format('d.m.Y'));
            $this->assertEquals($expectedRepayments[$i]['payment'], $actualRepayment->getPayment());
            $this->assertEquals($expectedRepayments[$i]['percents'], $actualRepayment->getPercents());
            $this->assertEquals($expectedRepayments[$i]['body'], $actualRepayment->getBody());
            $this->assertEquals($expectedRepayments[$i]['balance'], $actualRepayment->getBalance());
            $this->assertEquals($expectedRepayments[$i]['comission'], $actualRepayment->getComission());

            $i++;
        }
    }

    public function tearDown(): void
    {
        $this->credit = null;
    }
}