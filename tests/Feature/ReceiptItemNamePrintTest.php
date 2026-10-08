<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The receipt printed from a stored order (Back Office receipt page, which the Cashier's thermal/Bluetooth
 * and Android paths read as JSON) and the shift summary print show one clean label per item, without
 * touching quantities, prices, totals, payment or the stored order lines.
 */
class ReceiptItemNamePrintTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $owner;

    private SalesTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->owner = User::create([
            'name' => 'Meli',
            'username' => 'meli',
            'email' => 'meli@example.test',
            'password' => 'password',
            'role_id' => Role::create(['name' => 'Owner', 'code' => 'owner'])->id,
            'outlet_id' => $this->outlet->id,
            'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outlet->id]);

        $this->transaction = SalesTransaction::create([
            'transaction_number' => 'ATG-001',
            'user_id' => $this->owner->id,
            'outlet_id' => $this->outlet->id,
            'subtotal' => 1500,
            'grand_total' => 1500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 1500,
            'change_amount' => 0,
            'status' => 'completed',
        ]);

        // As stored at checkout: the order type is appended to the Variant name.
        foreach ([
            ['Sedotan', '*Sedotan Boba [DINE IN]', 1, 500],
            ['Sedotan', '*Sedotan Kopi [DINE IN]', 1, 500],
            ['Sedotan', '*Sedotan Suda [DINE IN]', 1, 500],
        ] as [$product, $variant, $qty, $price]) {
            $this->line($this->transaction, $product, $variant, $qty, $price);
        }
    }

    public function test_the_receipt_payload_carries_the_name_of_the_outlet_that_made_the_sale(): void
    {
        $this->assertSame('BXC', $this->receiptPayload($this->transaction)['outlet_name']);

        // Another outlet's cashier: that outlet's name, not a fixed one.
        $other = Outlet::create(['name' => 'Tea Bar Kemang', 'code' => 'TBK', 'is_active' => true]);
        $second = SalesTransaction::create([
            'transaction_number' => 'ATG-002',
            'user_id' => $this->owner->id,
            'outlet_id' => $other->id,
            'subtotal' => 500,
            'grand_total' => 500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 500,
            'change_amount' => 0,
            'status' => 'completed',
        ]);
        $this->line($second, 'Sedotan', '*Sedotan Boba [DINE IN]', 1, 500);

        $this->assertSame('Tea Bar Kemang', $this->receiptPayload($second)['outlet_name']);
    }

    public function test_the_receipt_payload_has_one_clean_label_per_cart_line_and_unchanged_amounts(): void
    {
        $payload = $this->receiptPayload($this->transaction);

        $this->assertSame(['Sedotan Boba', 'Sedotan Kopi', 'Sedotan Suda'], array_column($payload['items'], 'display_name'));

        // one stored line = one printed line, with the stored numbers
        $this->assertCount(3, $payload['items']);
        $this->assertEquals([1.0, 1.0, 1.0], array_column($payload['items'], 'qty'));
        $this->assertEquals([500.0, 500.0, 500.0], array_column($payload['items'], 'line_total'));
        $this->assertEquals(1500.0, $payload['subtotal']);
        $this->assertEquals(1500.0, $payload['grand_total']);
        $this->assertEquals(1500.0, $payload['amount_paid']);
        $this->assertEquals(0.0, $payload['change_amount']);
        $this->assertSame('CASH', $payload['payment_method']);
        $this->assertSame('COMPLETED', $payload['status']);
    }

    public function test_product_and_variant_fields_stay_available_and_the_stored_lines_are_not_rewritten(): void
    {
        $payload = $this->receiptPayload($this->transaction);

        // the raw fields older clients read are still there (the order type marker is stripped as before)
        $this->assertSame('Sedotan', $payload['items'][0]['product_name']);
        $this->assertSame('*Sedotan Boba', $payload['items'][0]['variant_name']);

        // nothing stored was touched
        $this->assertSame(
            ['*Sedotan Boba [DINE IN]', '*Sedotan Kopi [DINE IN]', '*Sedotan Suda [DINE IN]'],
            $this->transaction->items()->orderBy('id')->pluck('variant_name')->all()
        );
        $this->assertSame(['Sedotan', 'Sedotan', 'Sedotan'], $this->transaction->items()->orderBy('id')->pluck('product_name')->all());
    }

    public function test_other_item_shapes_print_as_before(): void
    {
        $second = SalesTransaction::create([
            'transaction_number' => 'ATG-002',
            'user_id' => $this->owner->id,
            'outlet_id' => $this->outlet->id,
            'subtotal' => 42000,
            'grand_total' => 42000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 42000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);
        $this->line($second, 'Waspffle Salty', 'Wsp salty cheesy [DINE IN]', 1, 42000);
        $this->line($second, 'Packaging', 'Packaging Dine In [DINE IN]', 1, 0);
        $this->line($second, 'Aquarius', '1L [DELIVERY]', 2, 0);
        $this->line($second, 'Teh', null, 1, 0);
        $this->line($second, 'Free Item', 'Free Item Cup [PROMO FREE ITEM] [DINE IN]', 1, 0);

        $payload = $this->receiptPayload($second);

        $this->assertSame(
            ['Waspffle Salty Wsp salty cheesy', 'Packaging Dine In', 'Aquarius 1L', 'Teh', 'Free Item Cup'],
            array_column($payload['items'], 'display_name')
        );
        $this->assertEquals([1.0, 1.0, 2.0, 1.0, 1.0], array_column($payload['items'], 'qty'));
        $this->assertEquals(42000.0, $payload['grand_total']);
    }

    public function test_the_receipt_scripts_read_the_label_from_the_payload_with_the_old_join_as_fallback(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $this->transaction))->assertOk()->getContent();

        // image/print renderer and Bluetooth (ESC/POS) renderer
        $this->assertSame(2, substr_count($html, 'item.display_name ||'));

        $cashier = file_get_contents(resource_path('views/cashier/index.blade.php'));
        $this->assertStringContainsString('item.display_name || (variantName ? productName', $cashier);
        $this->assertStringNotContainsString('const productNameWithVariant = variantName ?', $cashier);
    }

    public function test_the_shift_summary_does_not_repeat_the_product_name_either(): void
    {
        $shift = CashierShift::create(['user_id' => $this->owner->id, 'outlet_id' => $this->outlet->id, 'started_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        $this->transaction->update(['cashier_shift_id' => $shift->id]);

        $html = $this->actingAs($this->owner)
            ->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $this->outlet->id])
            ->get(route('cashier.shift.print', $shift))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sedotan Boba', $html);
        $this->assertStringNotContainsString('Sedotan *Sedotan', $html);
    }

    public function test_the_printable_sales_summary_does_not_repeat_the_product_name(): void
    {
        $second = SalesTransaction::create([
            'transaction_number' => 'ATG-002',
            'user_id' => $this->owner->id,
            'outlet_id' => $this->outlet->id,
            'subtotal' => 42000,
            'grand_total' => 42000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 42000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);
        $this->line($second, 'Waspffle Salty', 'Wsp salty cheesy [DINE IN]', 1, 42000);
        $this->line($second, 'Aquarius', '1L [DINE IN]', 2, 0);

        $html = $this->actingAs($this->owner)->get(route('backoffice.transactions.print'))->assertOk()->getContent();

        // 1. no repeated Product name, and no leftover "*" marker
        $this->assertStringContainsString('Sedotan Boba [DINE IN]', $html);
        $this->assertStringContainsString('Sedotan Kopi [DINE IN]', $html);
        $this->assertStringNotContainsString('Sedotan - *Sedotan', $html);
        $this->assertStringNotContainsString('*Sedotan', $html);

        // 2. unrelated Product / Variant names stay readable, with both identities
        $this->assertStringContainsString('Waspffle Salty - Wsp salty cheesy [DINE IN]', $html);
        $this->assertStringContainsString('Aquarius - 1L [DINE IN]', $html);

        // 3. amounts and quantities are the stored ones
        $this->assertStringContainsString('Rp42.000', $html);
        $this->assertStringContainsString('Rp500', $html);
        $this->assertStringContainsString('Qty terjual: 2', $html);   // Aquarius
        $this->assertStringContainsString('Qty terjual: 1', $html);
    }

    public function test_the_dashboard_print_summary_renders_and_does_not_repeat_the_product_name(): void
    {
        $second = SalesTransaction::create([
            'transaction_number' => 'ATG-002',
            'user_id' => $this->owner->id,
            'outlet_id' => $this->outlet->id,
            'subtotal' => 42000,
            'grand_total' => 42000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 42000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);
        $this->line($second, 'Waspffle Salty', 'Wsp salty cheesy [DINE IN]', 1, 42000);
        $this->line($second, 'Aquarius', '1L [DINE IN]', 2, 0);
        $before = $this->tableFingerprint();

        // 1 + 2. the real endpoint answers 200, i.e. the Blade view compiles and renders
        $response = $this->actingAs($this->owner)->get(route('backoffice.print-summary'))->assertOk();
        $response->assertViewIs('backoffice.print-summary');
        $html = $response->getContent();
        $this->assertStringContainsString('Dashboard Summary Print', $html);
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), 'balanced markup');

        // 3. no repeated Product name, no leftover "*" marker
        $this->assertStringContainsString('Sedotan Boba [DINE IN]', $html);
        $this->assertStringContainsString('Sedotan Kopi [DINE IN]', $html);
        $this->assertStringNotContainsString('Sedotan - *Sedotan', $html);
        $this->assertStringNotContainsString('*Sedotan', $html);

        // 4. unrelated names keep both identities
        $this->assertStringContainsString('Waspffle Salty - Wsp salty cheesy [DINE IN]', $html);
        $this->assertStringContainsString('Aquarius - 1L [DINE IN]', $html);

        // 5. amounts, quantities and totals are the stored ones
        $this->assertStringContainsString('Rp 42.000', $html);
        $this->assertStringContainsString('Rp 500', $html);
        $this->assertStringContainsString('Rp 43.500', $html);   // total sales: 1.500 + 42.000
        $this->assertSame(6.0, (float) $response->viewData('stats')['items_sold']);
        $this->assertSame([1.0, 1.0, 1.0, 1.0, 2.0], collect($response->viewData('topProducts'))->pluck('qty')->sort()->values()->all());

        // the page still shows what is left of its sections, nothing is brought back
        $this->assertStringContainsString('Transaction Summary', $html);
        $this->assertStringContainsString('Top Products Table', $html);
        $this->assertStringContainsString('Inventory Summary', $html);
        $this->assertStringNotContainsString('Low Stock Focus', $html);
        $this->assertStringNotContainsString('Payment Summary', $html);

        // 6. opening the page writes nothing
        $this->assertSame($before, $this->tableFingerprint());
    }

    public function test_the_summary_print_formatting_changes_nothing_stored_and_the_exports_keep_the_raw_names(): void
    {
        $before = $this->transaction->items()->orderBy('id')->get(['product_name', 'variant_name', 'qty', 'price', 'line_total'])->toArray();

        $this->actingAs($this->owner);
        $this->get(route('backoffice.transactions.print'))->assertOk();

        $this->assertSame($before, $this->transaction->items()->orderBy('id')->get(['product_name', 'variant_name', 'qty', 'price', 'line_total'])->toArray());
        $this->assertEquals(1500, $this->transaction->fresh()->grand_total);

        // CSV export of transactions is untouched: still the raw stored names
        $csv = $this->get(route('backoffice.transactions.export.csv'))->streamedContent();
        $this->assertStringContainsString('*Sedotan Boba', $csv);
    }

    private function tableFingerprint(): array
    {
        return json_decode(json_encode(collect(['sales_transactions', 'sales_transaction_items', 'stock_balances', 'stock_movements', 'recipes', 'recipe_items', 'products', 'product_variants', 'backoffice_notifications'])
            ->filter(fn (string $table) => \Illuminate\Support\Facades\Schema::hasTable($table))
            ->mapWithKeys(fn (string $table) => [$table => \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()])->all()), true);
    }

    private function line(SalesTransaction $transaction, string $product, ?string $variant, int $qty, int $price): void
    {
        $transaction->items()->create([
            'product_name' => $product,
            'variant_name' => $variant,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $qty * $price,
            'final_line_total' => $qty * $price,
        ]);
    }

    private function receiptPayload(SalesTransaction $transaction): array
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $transaction))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="receipt-payload-json">(.*?)</script>#s', $html, $match));

        return json_decode(html_entity_decode($match[1]), true, flags: JSON_THROW_ON_ERROR);
    }
}
