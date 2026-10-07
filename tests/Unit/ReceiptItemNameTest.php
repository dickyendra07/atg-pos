<?php

namespace Tests\Unit;

use App\Support\ReceiptItemName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The printed item label (Product + Variant). Presentation only: the rule is generic, nothing about
 * "Sedotan" or "Waspffle" is special-cased.
 */
class ReceiptItemNameTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_the_printed_label(?string $product, ?string $variant, string $expected): void
    {
        $this->assertSame($expected, ReceiptItemName::combine($product, $variant));
    }

    public static function cases(): array
    {
        return [
            // CASE A / B: the Variant name already starts with the Product name (marker "*" included)
            'A sedotan boba' => ['Sedotan', '*Sedotan Boba', 'Sedotan Boba'],
            'B sedotan kopi' => ['Sedotan', '*Sedotan Kopi', 'Sedotan Kopi'],
            'B sedotan suda' => ['Sedotan', '*Sedotan Suda', 'Sedotan Suda'],
            'variant without any marker' => ['Sedotan', 'Sedotan Boba', 'Sedotan Boba'],
            'other markers and spacing' => ['Sedotan', '  - Sedotan   Boba ', 'Sedotan Boba'],
            'case and punctuation insensitive' => ['SEDOTAN', 'sedotan, boba', 'sedotan, boba'],
            'packaging dine in' => ['Packaging', 'Packaging Dine In', 'Packaging Dine In'],
            'multi word product' => ['Kopi Susu', 'Kopi Susu Gula Aren', 'Kopi Susu Gula Aren'],

            // CASE C: related but not a prefix repetition -> both stay, nothing disappears
            'C waspffle salty' => ['Waspffle Salty', 'Wsp salty cheesy', 'Waspffle Salty Wsp salty cheesy'],
            'C waspffle' => ['Waspffle', 'Wsp salty cheesy', 'Waspffle Wsp salty cheesy'],

            // CASE D: unrelated names
            'D size' => ['Aquarius', '1L', 'Aquarius 1L'],
            'D flavour' => ['Teh', 'Manis', 'Teh Manis'],
            'D product repeated later is NOT collapsed (only a leading repeat is)' => ['Aquarius', 'Mineral Aquarius 1L', 'Aquarius Mineral Aquarius 1L'],
            'whole words only: a longer first word is a different word' => ['Sedot', 'Sedotan Boba', 'Sedot Sedotan Boba'],
            'product is part of a longer variant word' => ['Es', 'Espresso', 'Es Espresso'],

            // CASE E: no meaningful Variant name
            'E null variant' => ['Sedotan', null, 'Sedotan'],
            'E empty variant' => ['Sedotan', '', 'Sedotan'],
            'E blank variant' => ['Sedotan', '   ', 'Sedotan'],
            'E dash variant' => ['Sedotan', '-', 'Sedotan'],
            'E marker only variant' => ['Sedotan', '*', 'Sedotan'],
            'E variant equals product' => ['Sedotan', 'Sedotan', 'Sedotan'],
            'E product without a usable name keeps the old output' => ['-', 'Boba', '- Boba'],
            'E no product' => [null, 'Boba', 'Boba'],

            // CASE F: long names are never truncated here (wrapping is the printer's job)
            'F long unrelated names' => [
                'Waspffle Salty Special Edition Anniversary',
                'Extra Large Cheese Overload With Sausage',
                'Waspffle Salty Special Edition Anniversary Extra Large Cheese Overload With Sausage',
            ],
            'F long repeated prefix' => [
                'Waspffle Salty Special Edition',
                '*Waspffle Salty Special Edition Extra Large Cheese Overload',
                'Waspffle Salty Special Edition Extra Large Cheese Overload',
            ],
        ];
    }

    public function test_it_never_produces_a_doubled_product_name_for_a_prefixed_variant(): void
    {
        foreach (['*Sedotan Boba', 'Sedotan Boba', '- Sedotan Boba'] as $variant) {
            $label = ReceiptItemName::combine('Sedotan', $variant);

            $this->assertSame(1, substr_count(strtolower($label), 'sedotan'), $label);
            $this->assertStringNotContainsString('*', $label);
        }
    }

    public function test_it_is_stable_when_applied_to_its_own_output(): void
    {
        foreach ([['Sedotan', '*Sedotan Boba'], ['Waspffle Salty', 'Wsp salty cheesy'], ['Aquarius', '1L']] as [$product, $variant]) {
            $once = ReceiptItemName::combine($product, $variant);

            $this->assertSame($once, ReceiptItemName::combine($product, $once), $once);
        }
    }
}
