<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The printed receipt header is the brand and the transaction outlet's saved address. The outlet name is
 * no longer printed (the address already says where the outlet is), but stays in the receipt payload.
 * Presentation only: every renderer reads the same payload, and nothing stored or financial changes.
 */
class ReceiptHeaderIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $owner;

    private SalesTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'name' => "Lee Ong's Tea x Waspffle BXC Bazaar",
            'code' => 'BXC',
            'address' => "Jl. Bintaro Utama 3A No. 1\n  Tangerang Selatan ",
            'is_active' => true,
        ]);
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

        $this->transaction = $this->sale($this->outlet, 'ATG-001');
    }

    public function test_the_payload_keeps_outlet_name_and_the_header_fields_are_brand_and_address(): void
    {
        $payload = $this->receiptPayload($this->transaction);

        $this->assertSame("Lee Ong's Tea x Waspffle", $payload['brand_name']);
        $this->assertSame("Lee Ong's Tea x Waspffle BXC Bazaar", $payload['outlet_name']);
        $this->assertSame('Jl. Bintaro Utama 3A No. 1 Tangerang Selatan', $payload['address']);
    }

    public function test_no_renderer_prints_the_outlet_name(): void
    {
        $receiptPage = $this->receiptHtml($this->transaction);
        $cashierPage = file_get_contents(resource_path('views/cashier/index.blade.php'));

        // The only mention of outlet_name left in the page scripts is the payload key itself.
        $this->assertSame(0, substr_count($this->scripts($receiptPage), 'receipt.outlet_name'));
        $this->assertSame(0, substr_count($cashierPage, 'receipt.outlet_name'));

        // Each renderer's header block: brand, then address, then the date; no outlet name in between.
        foreach ([
            'canvas / PNG' => [$this->scripts($receiptPage), 'pushWrapped(receipt.brand_name', 'pushWrapped(receipt.address', 'formatAtgDateTime(receipt.created_at)'],
            'receipt Bluetooth' => [$this->scripts($receiptPage), 'wrapBluetoothText(receipt.brand_name', 'wrapBluetoothText(receipt.address', 'formatAtgDateTime(receipt.created_at)'],
            'cashier Bluetooth / Android' => [$cashierPage, 'cashierWrapText(receipt.brand_name', 'cashierWrapText(receipt.address', 'cashierFormatDateTime(receipt.created_at)'],
        ] as $renderer => [$source, $brand, $address, $date]) {
            $this->assertSame(1, substr_count($source, $brand), "$renderer prints the brand once");
            $this->assertSame(1, substr_count($source, $address), "$renderer prints the address once");

            $header = substr($source, strpos($source, $brand), strpos($source, $date, strpos($source, $brand)) - strpos($source, $brand));
            $this->assertStringNotContainsString('outlet', $header, "$renderer header has no outlet name");
            $this->assertLessThan(strpos($header, $address), strpos($header, $brand), "$renderer: brand comes before address");
            $this->assertStringContainsString('if (receipt.address)', $header, "$renderer skips an empty address");
        }
    }

    public function test_the_cashier_android_path_builds_the_same_bytes_as_cashier_bluetooth(): void
    {
        $cashier = file_get_contents(resource_path('views/cashier/index.blade.php'));

        // One builder (header included) feeds the Bluetooth write and AndroidPrinter.printBase64.
        $this->assertSame(1, substr_count($cashier, 'function buildCashierReceiptEscposBytes('));
        $this->assertStringContainsString('cashierBytesToBase64(receiptBytes)', $cashier);
        $this->assertSame(0, preg_match('/AndroidPrinter[^;]*outlet/i', $cashier));
        $this->assertStringContainsString('buildCashierReceiptEscposBytes(', substr($cashier, strpos($cashier, 'function hasAndroidNativePrinter()'), 1500));
    }

    public function test_an_empty_address_leaves_only_the_brand_without_placeholder_or_outlet_name(): void
    {
        foreach ([null, '', "  \n "] as $empty) {
            $this->outlet->update(['address' => $empty]);

            $payload = $this->receiptPayload($this->transaction);

            $this->assertNull($payload['address']);
            // still in the payload, never restored in the header
            $this->assertSame("Lee Ong's Tea x Waspffle BXC Bazaar", $payload['outlet_name']);
        }

        $html = $this->receiptHtml($this->transaction);
        $this->assertStringNotContainsString('Alamat outlet / cabang', $html);
        $this->assertStringNotContainsString('receipt.outlet_name', $this->scripts($html));
    }

    public function test_the_address_and_outlet_come_from_the_transaction_outlet_not_the_logged_in_outlet(): void
    {
        $other = Outlet::create(['name' => 'Tea Bar Kemang', 'code' => 'TBK', 'address' => 'Jl. Kemang Raya 8', 'is_active' => true]);
        $this->owner->update(['outlet_id' => $other->id]);
        $this->owner->outlets()->sync([$other->id, $this->outlet->id]);

        $own = $this->receiptPayload($this->transaction->fresh());
        $this->assertSame('Jl. Bintaro Utama 3A No. 1 Tangerang Selatan', $own['address']);

        $theirs = $this->receiptPayload($this->sale($other, 'ATG-002'));
        $this->assertSame('Jl. Kemang Raya 8', $theirs['address']);
        $this->assertSame('Tea Bar Kemang', $theirs['outlet_name']);

        // an outlet with no address next to one with an address: no leak between receipts
        $bare = Outlet::create(['name' => 'Kiosk Tanpa Alamat', 'code' => 'KTA', 'is_active' => true]);
        $this->assertNull($this->receiptPayload($this->sale($bare, 'ATG-003'))['address']);
    }

    public function test_reprint_historical_and_void_receipts_keep_their_indicators_and_outlet_address(): void
    {
        // historical: an old sale whose outlet address was filled in later still prints the saved address
        DB::table('sales_transactions')->where('id', $this->transaction->id)->update(['created_at' => '2024-01-05 10:00:00', 'updated_at' => '2024-01-05 10:00:00']);
        $historical = $this->receiptPayload($this->transaction->fresh());
        $this->assertSame('Jl. Bintaro Utama 3A No. 1 Tangerang Selatan', $historical['address']);
        $this->assertSame('05-01-2024 10:00', $historical['created_at']);

        // reprint (client side flag via ?reprint=1) and the reprint indicators in every renderer
        $reprint = $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $this->transaction).'?reprint=1')->assertOk()->getContent();
        $this->assertStringContainsString("reprintUrlParams.get('reprint') === '1'", $reprint);
        $this->assertStringContainsString("push('text', '#REPRINT'", $reprint);
        $this->assertStringContainsString("line('#REPRINT')", $reprint);
        $this->assertStringContainsString("line('*** REPRINT ***')", file_get_contents(resource_path('views/cashier/index.blade.php')));

        // void
        $this->transaction->update(['status' => 'void', 'void_at' => now(), 'void_reason' => 'salah input']);
        $void = $this->receiptPayload($this->transaction->fresh());
        $this->assertTrue($void['is_void']);
        $this->assertSame('salah input', $void['void_reason']);
        $this->assertSame('Jl. Bintaro Utama 3A No. 1 Tangerang Selatan', $void['address']);
        $voidHtml = $this->receiptHtml($this->transaction->fresh());
        $this->assertStringContainsString('*** VOID ***', $voidHtml);
        $this->assertStringContainsString('*** VOID ***', file_get_contents(resource_path('views/cashier/index.blade.php')));
    }

    public function test_financial_data_is_unchanged_and_viewing_a_receipt_writes_nothing(): void
    {
        $this->transaction->items()->create(['product_name' => 'Teh', 'variant_name' => 'Large', 'qty' => 2, 'price' => 7500, 'line_total' => 15000, 'final_line_total' => 15000]);
        $this->transaction->update(['subtotal' => 15000, 'grand_total' => 15000, 'amount_paid' => 20000, 'change_amount' => 5000]);
        $before = $this->fingerprint();

        $payload = $this->receiptPayload($this->transaction->fresh());

        $this->assertEquals(15000.0, $payload['subtotal']);
        $this->assertEquals(15000.0, $payload['grand_total']);
        $this->assertEquals(20000.0, $payload['amount_paid']);
        $this->assertEquals(5000.0, $payload['change_amount']);
        $this->assertSame('ATG 001', $payload['transaction_number']);
        $this->assertSame('Meli', $payload['cashier_name']);
        $this->assertSame('Teh Large', $payload['items'][0]['display_name']);
        $this->assertEquals(2.0, $payload['items'][0]['qty']);

        // viewing (twice, plus a reprint view) changes no row, and the outlet name in the database stays as is
        $this->receiptHtml($this->transaction);
        $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $this->transaction).'?reprint=1')->assertOk();
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame("Lee Ong's Tea x Waspffle BXC Bazaar", $this->outlet->fresh()->name);
    }

    private function sale(Outlet $outlet, string $number): SalesTransaction
    {
        return SalesTransaction::create([
            'transaction_number' => $number,
            'user_id' => $this->owner->id,
            'outlet_id' => $outlet->id,
            'subtotal' => 500,
            'grand_total' => 500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'amount_paid' => 500,
            'change_amount' => 0,
            'status' => 'completed',
        ]);
    }

    private function fingerprint(): array
    {
        return json_decode(json_encode(collect(['sales_transactions', 'sales_transaction_items', 'outlets', 'stock_balances', 'stock_movements'])
            ->filter(fn (string $table) => \Illuminate\Support\Facades\Schema::hasTable($table))
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()])->all()), true);
    }

    private function receiptHtml(SalesTransaction $transaction): string
    {
        return $this->actingAs($this->owner)->get(route('backoffice.transactions.receipt', $transaction))->assertOk()->getContent();
    }

    /** The page's inline <script> code, without the JSON payload block. */
    private function scripts(string $html): string
    {
        $this->assertSame(1, preg_match('#<script>(.*?)</script>#s', $html, $match));

        return $match[1];
    }

    private function receiptPayload(SalesTransaction $transaction): array
    {
        $html = $this->receiptHtml($transaction);

        $this->assertSame(1, preg_match('#<script type="application/json" id="receipt-payload-json">(.*?)</script>#s', $html, $match));

        return json_decode(html_entity_decode($match[1]), true, flags: JSON_THROW_ON_ERROR);
    }
}
