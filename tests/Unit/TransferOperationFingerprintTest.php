<?php

namespace Tests\Unit;

use App\Services\TransferOperationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fingerprint decides whether a replay is "the same operation": equivalent payloads must hash the same on every
 * PHP worker, any meaningful change must hash differently, and a quantity the database cannot store exactly must be
 * refused rather than rounded into a different valid-looking one.
 */
class TransferOperationFingerprintTest extends TestCase
{
    private const FROM = ['type' => 'warehouse', 'id' => 3];

    private const TO = ['type' => 'outlet', 'id' => 7];

    private function hash(?array $items = null, array $overrides = []): string
    {
        $o = $overrides + ['from' => self::FROM, 'to' => self::TO, 'sender' => 'Budi', 'receiver' => null, 'note' => null];

        return TransferOperationService::fingerprint($o['from'], $o['to'], $o['sender'], $o['receiver'], $o['note'], $items ?? [['ingredient_id' => 1, 'qty' => '5.00']]);
    }

    public static function equivalentQuantities(): array
    {
        return [
            'integer string' => ['5', '5.00'],
            'one decimal' => ['5.0', '5.00'],
            'two decimals' => ['5.00', '5.00'],
            'int' => [5, '5.00'],
            'float' => [5.0, '5.00'],
            'explicit plus and leading zeros' => ['+05.00', '5.00'],
            'trailing dot' => ['5.', '5.00'],
            'fraction' => ['0.5', '0.50'],
            'no integer part' => ['.5', '0.50'],
            'float noise (0.1 + 0.2)' => [0.1 + 0.2, '0.30'],
            'trailing zeros beyond the scale are the same number' => ['5.500', '5.50'],
            'large' => ['1234567.80', '1234567.80'],
            'zero is never negative' => ['-0.00', '0.00'],
        ];
    }

    #[DataProvider('equivalentQuantities')]
    public function test_equivalent_quantities_canonicalize_the_same_way(mixed $input, string $expected): void
    {
        $this->assertSame($expected, TransferOperationService::canonicalQty($input));
    }

    public static function unstorableQuantities(): array
    {
        return [
            'three significant decimals' => ['1.239'],
            'sub-cent' => ['0.005'],
            'float beyond scale' => [1.005],
            'exponent' => ['1e1'],
            'text' => ['abc'],
            'empty' => [''],
            'dot only' => ['.'],
            'thousands separator' => ['1,000.00'],
            'array' => [[5]],
            'null' => [null],
            'bool' => [true],
        ];
    }

    #[DataProvider('unstorableQuantities')]
    public function test_a_quantity_the_database_cannot_store_exactly_is_refused_not_rounded(mixed $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TransferOperationService::canonicalQty($input);
    }

    public function test_equivalent_quantity_spellings_produce_the_same_fingerprint(): void
    {
        $reference = $this->hash([['ingredient_id' => 1, 'qty' => '5.00']]);

        foreach (['5', '5.0', 5, 5.0, '+05.00'] as $spelling) {
            $this->assertSame($reference, $this->hash([['ingredient_id' => '1', 'qty' => $spelling]]), json_encode($spelling));
        }
    }

    public function test_the_hash_is_a_stable_sha256_that_does_not_depend_on_the_process(): void
    {
        $hash = $this->hash([['ingredient_id' => 1, 'qty' => '5.00'], ['ingredient_id' => 2, 'qty' => '3.50']], ['note' => 'Kirim pagi']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertSame($hash, $this->hash([['ingredient_id' => 1, 'qty' => '5.00'], ['ingredient_id' => 2, 'qty' => '3.50']], ['note' => 'Kirim pagi']));

        // Pinned value: if the canonical form ever changes by accident, replay detection across a deploy would break.
        $this->assertSame('24ad334b6fc160de543f074cc0784f568ebddc9f1218359729a1bfa70a6c7b7d', $hash);
    }

    public function test_every_meaningful_change_produces_a_different_fingerprint(): void
    {
        $base = $this->hash();
        $changes = [
            'other source' => $this->hash(null, ['from' => ['type' => 'warehouse', 'id' => 4]]),
            'other source type' => $this->hash(null, ['from' => ['type' => 'outlet', 'id' => 3]]),
            'other destination' => $this->hash(null, ['to' => ['type' => 'outlet', 'id' => 8]]),
            'swapped direction' => $this->hash(null, ['from' => self::TO, 'to' => self::FROM]),
            'other sender' => $this->hash(null, ['sender' => 'Siti']),
            'receiver added' => $this->hash(null, ['receiver' => 'Andi']),
            'note added' => $this->hash(null, ['note' => 'x']),
            'other ingredient' => $this->hash([['ingredient_id' => 2, 'qty' => '5.00']]),
            'other quantity' => $this->hash([['ingredient_id' => 1, 'qty' => '5.01']]),
            'extra item' => $this->hash([['ingredient_id' => 1, 'qty' => '5.00'], ['ingredient_id' => 2, 'qty' => '1.00']]),
        ];

        foreach ($changes as $label => $hash) {
            $this->assertNotSame($base, $hash, $label);
        }
        $this->assertSame(count($changes), count(array_unique($changes)), 'no two different changes collide');
    }

    public function test_optional_empty_values_are_normalized_consistently(): void
    {
        $this->assertSame($this->hash(null, ['receiver' => null, 'note' => null]), $this->hash(null, ['receiver' => '', 'note' => '']));
        $this->assertSame($this->hash(null, ['receiver' => null, 'note' => null]), $this->hash(null, ['receiver' => '  ', 'note' => "\t"]));
        $this->assertSame($this->hash(null, ['note' => 'Kirim']), $this->hash(null, ['note' => '  Kirim ']));
        $this->assertSame($this->hash(null, ['sender' => 'Budi']), $this->hash(null, ['sender' => ' Budi ']));
    }

    public function test_a_repeated_ingredient_is_kept_as_its_own_line_never_merged_or_dropped(): void
    {
        $twoLines = $this->hash([['ingredient_id' => 1, 'qty' => '2.00'], ['ingredient_id' => 1, 'qty' => '3.00']]);

        $this->assertNotSame($twoLines, $this->hash([['ingredient_id' => 1, 'qty' => '5.00']]), 'two lines are not one merged line');
        $this->assertNotSame($twoLines, $this->hash([['ingredient_id' => 1, 'qty' => '2.00']]), 'no line is silently dropped');
        $this->assertNotSame($this->hash([['ingredient_id' => 1, 'qty' => '5.00']]), $this->hash([['ingredient_id' => 1, 'qty' => '5.00'], ['ingredient_id' => 1, 'qty' => '5.00']]));
    }

    public function test_item_order_is_part_of_the_operation(): void
    {
        // Rows are created and posted in the submitted order, so the same lines in another order are another request.
        $this->assertNotSame(
            $this->hash([['ingredient_id' => 1, 'qty' => '2.00'], ['ingredient_id' => 2, 'qty' => '3.00']]),
            $this->hash([['ingredient_id' => 2, 'qty' => '3.00'], ['ingredient_id' => 1, 'qty' => '2.00']])
        );
    }

    public function test_array_keys_of_the_submitted_items_do_not_matter_only_their_order(): void
    {
        // The browser may submit items[2], items[5] after rows were removed; the keys are not part of the operation.
        $this->assertSame(
            $this->hash([0 => ['ingredient_id' => 1, 'qty' => '2.00'], 1 => ['ingredient_id' => 2, 'qty' => '3.00']]),
            $this->hash([2 => ['ingredient_id' => 1, 'qty' => '2.00'], 5 => ['ingredient_id' => 2, 'qty' => '3.00']])
        );
    }

    public function test_unicode_text_is_hashed_as_is(): void
    {
        $this->assertNotSame($this->hash(null, ['note' => 'Kirim ☕']), $this->hash(null, ['note' => 'Kirim']));
        $this->assertSame($this->hash(null, ['note' => 'Kirim ☕']), $this->hash(null, ['note' => 'Kirim ☕']));
    }
}
