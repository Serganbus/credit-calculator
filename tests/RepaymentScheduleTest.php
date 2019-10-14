<?php

namespace Credits;

use PHPUnit\Framework\TestCase;

/**
 * Description of RepaymentScheduleTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentScheduleTest extends TestCase
{
    /**
     * @var RepaymentSchedule
     */
    private $schedule;

    public function setUp(): void
    {
        $repayments = [
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

        $repaymentParams = [];
        foreach ($repayments as $repayment) {
            $date = \DateTime::createFromFormat('d.m.Y', $repayment['date']);
            $repaymentParams[] = new RepaymentParams($date, $repayment['percents'], $repayment['body'], $repayment['balance']);
        }

        $params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 990, 12, CreditParams::DURATION_MONTH);
        $this->schedule = new RepaymentSchedule($repaymentParams, $params);
    }

    public function testCurrentOk()
    {
        $current = $this->schedule->current();
        $this->assertInstanceOf(RepaymentParams::class, $current);
    }

    public function testCurrentException()
    {
        while ($this->schedule->valid()) {
            $this->schedule->next();
        }

        $this->expectException(\RangeException::class);
        $this->schedule->current();
    }

    public function testKey()
    {
        $i = 0;
        while ($this->schedule->valid()) {
            if ($i === 0) {
                // первый элемент должен быть 0
                $this->assertEquals(0, $this->schedule->key());
            } else {
                $this->assertTrue(0 < $this->schedule->key());
            }

            $this->schedule->next();
            $i++;
        }
    }

    public function testNext()
    {
        $prevKey = $this->schedule->key();
        $this->schedule->next();
        $nextKey = $this->schedule->key();
        $this->assertTrue($prevKey < $nextKey);
    }

    public function testRewind()
    {
        while ($this->schedule->valid()) {
            $this->schedule->next();
        }
        $lastKey = $this->schedule->key();
        $this->assertTrue($lastKey > 0);
        $this->schedule->rewind();
        $this->assertEquals(0, $this->schedule->key());
    }

    public function testValid()
    {
        while ($this->schedule->valid()) {
            $this->assertTrue($this->schedule->valid());
            $this->schedule->next();
        }
        $this->assertFalse($this->schedule->valid());
    }

    public function testCount()
    {
        $this->assertEquals(13, count($this->schedule));
    }

    public function testCalculateTotalCost()
    {
        $this->assertEquals(989, $this->schedule->calculateTotalCost());
    }

    public function testCalculateTotalPayments()
    {
        $this->assertEquals(105443262, $this->schedule->calculateTotalPayments());
    }

    public function testCalculateOverpayment()
    {
        $this->assertEquals(5443262, $this->schedule->calculateOverpayment());
    }

    public function tearDown(): void
    {
        $this->schedule = null;
    }
}