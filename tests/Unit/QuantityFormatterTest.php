<?php

namespace Tests\Unit;

use App\Support\QuantityFormatter;
use PHPUnit\Framework\TestCase;

class QuantityFormatterTest extends TestCase
{
    public function test_stock_summary_quantity_examples_are_formatted_consistently(): void
    {
        $examples = [
            0 => '0,00',
            '0.2' => '0,20',
            '0.25' => '0,25',
            1 => '1,00',
            '23.5' => '23,50',
            '-0.2' => '-0,20',
            -587 => '-587,00',
            '-0.004' => '0,00',
        ];

        foreach ($examples as $value => $expected) {
            $this->assertSame($expected, QuantityFormatter::twoDecimals($value));
        }
    }
}
