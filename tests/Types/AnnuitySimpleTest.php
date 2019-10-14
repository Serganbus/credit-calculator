<?php

namespace Credits\Types;

use PHPUnit\Framework\TestCase;
use Credits\CreditParams;
use Credits\RepaymentParams;
use Credits\UnexpectedPayment;
use DateTime;

/**
 * Description of AnnuitySimpleTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class AnnuitySimpleTest extends TestCase
{
    /**
     * @var AnnuitySimple
     */
    private $credit;

    public function setUp(): void
    {
        $this->credit = new AnnuitySimple();
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
            ['date' => '30.11.2019', 'payment' => 8786938, 'percents' => 825000, 'body' => 7961938, 'comission' => 0, 'balance' => 92038062],
            ['date' => '31.12.2019', 'payment' => 8786938, 'percents' => 759314, 'body' => 8027624, 'comission' => 0, 'balance' => 84010438],
            ['date' => '31.01.2020', 'payment' => 8786938, 'percents' => 693086, 'body' => 8093852, 'comission' => 0, 'balance' => 75916586],
            ['date' => '29.02.2020', 'payment' => 8786938, 'percents' => 626312, 'body' => 8160626, 'comission' => 0, 'balance' => 67755960],
            ['date' => '31.03.2020', 'payment' => 8786938, 'percents' => 558987, 'body' => 8227951, 'comission' => 0, 'balance' => 59528009],
            ['date' => '30.04.2020', 'payment' => 8786938, 'percents' => 491106, 'body' => 8295832, 'comission' => 0, 'balance' => 51232177],
            ['date' => '31.05.2020', 'payment' => 8786938, 'percents' => 422665, 'body' => 8364273, 'comission' => 0, 'balance' => 42867904],
            ['date' => '30.06.2020', 'payment' => 8786938, 'percents' => 353660, 'body' => 8433278, 'comission' => 0, 'balance' => 34434626],
            ['date' => '31.07.2020', 'payment' => 8786938, 'percents' => 284086, 'body' => 8502852, 'comission' => 0, 'balance' => 25931774],
            ['date' => '31.08.2020', 'payment' => 8786938, 'percents' => 213937, 'body' => 8573001, 'comission' => 0, 'balance' => 17358773],
            ['date' => '30.09.2020', 'payment' => 8786938, 'percents' => 143210, 'body' => 8643728, 'comission' => 0, 'balance' => 8715045],
            ['date' => '31.10.2020', 'payment' => 8786944, 'percents' => 71899,  'body' => 8715045, 'comission' => 0, 'balance' => 0],
        ];

        // график платежей со всякими там досрочными частичными погашениями и без комиссий
        $expectedRepayments2 = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'comission' => 0, 'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 8786938, 'percents' => 825000, 'body' => 7961938, 'comission' => 0, 'balance' => 92038062],
            ['date' => '15.12.2019', 'payment' => 10000000,'percents' => 0,      'body' => 10000000,'comission' => 0, 'balance' => 82038062],
            ['date' => '31.12.2019', 'payment' => 7832232, 'percents' => 676814, 'body' => 7155418, 'comission' => 0, 'balance' => 74882644],
            ['date' => '13.01.2020', 'payment' => 3565411, 'percents' => 0,      'body' => 3565411, 'comission' => 0, 'balance' => 71317233],
            ['date' => '31.01.2020', 'payment' => 7832232, 'percents' => 617782, 'body' => 7214450, 'comission' => 0, 'balance' => 64102783],
            ['date' => '29.02.2020', 'payment' => 7832232, 'percents' => 528848, 'body' => 7303384, 'comission' => 0, 'balance' => 56799399],
            ['date' => '29.02.2020', 'payment' => 15000000,'percents' => 0,      'body' => 15000000,'comission' => 0, 'balance' => 41799399],
            ['date' => '31.03.2020', 'payment' => 7832232, 'percents' => 468595, 'body' => 7363637, 'comission' => 0, 'balance' => 34435762],
            ['date' => '30.04.2020', 'payment' => 7832232, 'percents' => 284095, 'body' => 7548137, 'comission' => 0, 'balance' => 26887625],
            ['date' => '31.05.2020', 'payment' => 7832232, 'percents' => 221823, 'body' => 7610409, 'comission' => 0, 'balance' => 19277216],
            ['date' => '30.06.2020', 'payment' => 7832232, 'percents' => 159037, 'body' => 7673195, 'comission' => 0, 'balance' => 11604021],
            ['date' => '31.07.2020', 'payment' => 7832232, 'percents' => 95733,  'body' => 7736499, 'comission' => 0, 'balance' => 3867522],
            ['date' => '31.08.2020', 'payment' => 3899429, 'percents' => 31907,  'body' => 3867522, 'comission' => 0, 'balance' => 0],
        ];

        // график платежей со всякими там досрочными частичными погашениями и с комиссиями
        $creditParams3 = clone $creditParams;
        $creditParams3->setOneTimeComission(50000);
        $creditParams3->setPeriodicComission(5000);
        $expectedRepayments3 = [
            ['date' => '31.10.2019', 'payment' => 0,       'percents' => 0,      'body' => 0,       'comission' => 0,     'balance' => 100000000],
            ['date' => '30.11.2019', 'payment' => 8841938, 'percents' => 825000, 'body' => 7961938, 'comission' => 55000, 'balance' => 92038062],
            ['date' => '15.12.2019', 'payment' => 10000000,'percents' => 0,      'body' => 10000000,'comission' => 0,     'balance' => 82038062],
            ['date' => '31.12.2019', 'payment' => 7837232, 'percents' => 676814, 'body' => 7155418, 'comission' => 5000,  'balance' => 74882644],
            ['date' => '13.01.2020', 'payment' => 3565411, 'percents' => 0,      'body' => 3565411, 'comission' => 0,     'balance' => 71317233],
            ['date' => '31.01.2020', 'payment' => 7837232, 'percents' => 617782, 'body' => 7214450, 'comission' => 5000,  'balance' => 64102783],
            ['date' => '29.02.2020', 'payment' => 7837232, 'percents' => 528848, 'body' => 7303384, 'comission' => 5000,  'balance' => 56799399],
            ['date' => '29.02.2020', 'payment' => 15000000,'percents' => 0,      'body' => 15000000,'comission' => 0,     'balance' => 41799399],
            ['date' => '31.03.2020', 'payment' => 7837232, 'percents' => 468595, 'body' => 7363637, 'comission' => 5000,  'balance' => 34435762],
            ['date' => '30.04.2020', 'payment' => 7837232, 'percents' => 284095, 'body' => 7548137, 'comission' => 5000,  'balance' => 26887625],
            ['date' => '31.05.2020', 'payment' => 7837232, 'percents' => 221823, 'body' => 7610409, 'comission' => 5000,  'balance' => 19277216],
            ['date' => '30.06.2020', 'payment' => 7837232, 'percents' => 159037, 'body' => 7673195, 'comission' => 5000,  'balance' => 11604021],
            ['date' => '31.07.2020', 'payment' => 7837232, 'percents' => 95733,  'body' => 7736499, 'comission' => 5000,  'balance' => 3867522],
            ['date' => '31.08.2020', 'payment' => 3904429, 'percents' => 31907,  'body' => 3867522, 'comission' => 5000,  'balance' => 0],
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