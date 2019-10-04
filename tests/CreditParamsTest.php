<?php

namespace Credits;

use PHPUnit\Framework\TestCase;

/**
 * Description of CreditParamsTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class CreditParamsTest extends TestCase
{
    /**
     * @var CreditParams
     */
    private $params;

    public function setUp(): void
    {
        $this->params = new CreditParams(new \DateTime('2019-10-31 00:00:00'), 100000000, 1000, 12, CreditParams::DURATION_MONTH);
    }

    public function testGetRequestedSum()
    {
        $this->assertEquals(100000000, $this->params->getRequestedSum());
    }

    public function testGetDurationType()
    {
        $this->assertEquals(CreditParams::DURATION_MONTH, $this->params->getDurationType());
    }

    public function testGetInitialDate()
    {
        $initialDate = $this->params->getInitialDate();

        $this->assertInstanceOf(\DateTime::class, $initialDate);
        $this->assertEquals('2019-10-31 00:00:00', $initialDate->format('Y-m-d H:i:s'));
    }

    public function testGetRepaymentPeriodsCount()
    {
        $this->assertEquals(12, $this->params->getRepaymentPeriodsCount());
    }

    public function testGetPercents()
    {
        $this->assertEquals(1000, $this->params->getPercents());
    }
    
    public function tearDown(): void
    {
        $this->params = null;
    }
}