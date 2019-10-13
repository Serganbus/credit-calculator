<?php

namespace Credits;

/**
 * Description of HelpersTest
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class HelpersTest
{
    public function prettifyNumberDataProvider()
    {
        return [
            0, '0.00',
            1234567, '12 345.67',
            1234567890, '12 345 678.90',
            -1234567, '-12 345.67',
            -1234567890, '-12 345 678.90',
        ];
    }
    
    /**
     * @dataProvider prettifyNumberDataProvider
     */
    public function testPrettifyNumber(int $initial, string $expected)
    {
        $this->assertEquals($expected, \Credits\prettifyNumber($initial));
    }
}
