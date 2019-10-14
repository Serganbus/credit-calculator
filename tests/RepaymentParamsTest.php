<?php

namespace Credits;

use PHPUnit\Framework\TestCase;

/**
 * Description of RepaymentParamsTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentParamsTest extends TestCase
{
    /**
     * @var RepaymentParams
     */
    private $params;

    public function setUp(): void
    {
        $this->params = new RepaymentParams(new \DateTime('2019-10-31 00:00:00'), 1, 2, 100000000);
    }

    public function testGetDate()
    {
        $date = $this->params->getDate();

        $this->assertInstanceOf(\DateTime::class, $date);
        $this->assertEquals('2019-10-31 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testGetPayment()
    {
        $this->assertEquals(3, $this->params->getPayment());
    }

    public function testGetPercents()
    {
        $this->assertEquals(1, $this->params->getPercents());
    }

    public function testGetBody()
    {
        $this->assertEquals(2, $this->params->getBody());
    }

    public function testGetBalance()
    {
        $this->assertEquals(100000000, $this->params->getBalance());
    }

    public function tearDown(): void
    {
        $this->params = null;
    }
}