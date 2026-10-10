<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement #02: the compact Stock Summary table.
 *
 * Presentation only. What must not move: the nine columns and their order, every figure and how it is
 * formatted (VOID restore stays inside Sales, Adjustment only holds adjustments), the filters, and the
 * stored stock. The layout itself (widths, scrolling) is measured in a real browser during QA; here we check
 * what the HTML and CSS promise.
 */
class StockSummaryCompactLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outletA;

    private Outlet $outletB;

    private Warehouse $warehouse;

    private User $owner;

    private Ingredient $gula;

    private Ingredient $sirup;

    private Ingredient $boba;

    private Ingredient $susu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->outletB = Outlet::create(['name' => 'Outlet B', 'code' => 'OB', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang Pusat', 'code' => 'GP', 'is_active' => true]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->owner = User::create([
            'name' => 'owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $this->outletA->id,
            'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        $powder = IngredientCategory::create(['name' => 'Powder', 'code' => 'POWDER', 'is_active' => true]);
        $liquid = IngredientCategory::create(['name' => 'Liquid', 'code' => 'LIQUID', 'is_active' => true]);

        $this->gula = $this->makeIngredient($powder, 'Gula Pasir', 'gram');
        $this->sirup = $this->makeIngredient($liquid, 'Sirup Karamel', 'ml');
        $this->boba = $this->makeIngredient($powder, 'Boba Tapioka', 'gram');
        $this->susu = $this->makeIngredient($liquid, 'Susu Evaporasi Kental Manis Premium Rasa Vanila Ekstra Creamy Kemasan Sachet 500ml', 'ml');

        // Gula @A: every movement type once. Ending 1000+200+50-20-100+30+10-5 = 1165.
        $this->ledger($this->gula, 'outlet', $this->outletA->id, [
            ['opening_balance', 1000, 0], ['purchase', 200, 0], ['transfer_in', 50, 0], ['transfer_out', 0, 20],
            ['sales_usage', 0, 100], ['sales_void_restore', 30, 0], ['stock_adjustment', 10, 0], ['stock_adjustment', 0, 5],
        ], 1165);
        // Sirup @A: sold without any stock (a negative balance is allowed by the system and left as is).
        $this->ledger($this->sirup, 'outlet', $this->outletA->id, [['sales_usage', 0, 9876.25]], -9876.25);
        // Boba @A: decimals.
        $this->ledger($this->boba, 'outlet', $this->outletA->id, [['opening_balance', 12.35, 0], ['sales_usage', 0, 0.01]], 12.34);
        // Susu: a long ingredient name, in the warehouse; Gula also at B.
        $this->ledger($this->susu, 'warehouse', $this->warehouse->id, [['opening_balance', 1234567.5, 0]], 1234567.5);
        $this->ledger($this->gula, 'outlet', $this->outletB->id, [['opening_balance', 40, 0], ['sales_usage', 0, 15]], 25);
    }

    public function test_all_nine_columns_remain_in_their_original_order(): void
    {
        $xpath = $this->summaryPage();

        $this->assertSame(
            ['Name', 'Category', 'Lokasi', 'Beginning', 'Purchase', 'Transfer', 'Sales', 'Adjustment', 'Ending'],
            $this->headers($xpath)
        );

        foreach ($xpath->query('//table[contains(@class,"stock-summary-table")]//tbody/tr') as $row) {
            $this->assertSame(9, $xpath->query('./td', $row)->length);
        }

        // The three text headers keep their plain markup (other tests and tools look for it verbatim);
        // they are aligned by position in the CSS instead.
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->getContent();
        foreach (['Name', 'Category', 'Lokasi'] as $label) {
            $this->assertStringContainsString('<th>'.$label.'</th>', $html);
        }
        $this->assertStringContainsString('stock-summary-table thead th:nth-child(-n+3)', $html);
    }

    public function test_existing_figures_and_their_formatting_are_unchanged(): void
    {
        $rows = $this->rowsByNameAndLocation($this->summaryPage());

        // Category | Beginning | Purchase | Transfer (in - out) | Sales (usage - void restore, shown negative) | Adjustment | Ending
        $this->assertSame(['Powder', '1.000,00', '200,00', '30,00', '-70,00', '5,00', '1.165,00'], $rows['Gula Pasir|Outlet A']);
        $this->assertSame(['Liquid', '0,00', '0,00', '0,00', '-9.876,25', '0,00', '-9.876,25'], $rows['Sirup Karamel|Outlet A']);
        $this->assertSame(['Powder', '12,35', '0,00', '0,00', '-0,01', '0,00', '12,34'], $rows['Boba Tapioka|Outlet A']);
        $this->assertSame(['Powder', '40,00', '0,00', '0,00', '-15,00', '0,00', '25,00'], $rows['Gula Pasir|Outlet B']);
        $this->assertSame(
            ['Liquid', '1.234.567,50', '0,00', '0,00', '0,00', '0,00', '1.234.567,50'],
            $rows['Susu Evaporasi Kental Manis Premium Rasa Vanila Ekstra Creamy Kemasan Sachet 500ml|Gudang Pusat']
        );
        $this->assertCount(5, $rows);
    }

    public function test_void_restore_is_still_part_of_sales_and_never_counted_as_adjustment(): void
    {
        $gula = $this->rowsByNameAndLocation($this->summaryPage())['Gula Pasir|Outlet A'];

        // usage 100 - void restore 30 = 70 in Sales; Adjustment is only the +10 / -5 adjustments (= 5).
        $this->assertSame('-70,00', $gula[4], 'Sales');
        $this->assertSame('5,00', $gula[5], 'Adjustment');
    }

    public function test_figure_cells_are_marked_numeric_and_text_cells_are_marked_text(): void
    {
        $xpath = $this->summaryPage();

        $this->assertSame(6, $xpath->query('//table[contains(@class,"stock-summary-table")]//thead//th[contains(@class,"col-num")]')->length);

        foreach ($xpath->query('//table[contains(@class,"stock-summary-table")]//tbody/tr') as $row) {
            $cells = $xpath->query('./td', $row);

            $this->assertStringContainsString('col-name', $cells->item(0)->getAttribute('class'));
            $this->assertStringContainsString('col-cat', $cells->item(1)->getAttribute('class'));
            $this->assertStringContainsString('col-loc', $cells->item(2)->getAttribute('class'));

            for ($i = 3; $i < 9; $i++) {
                $this->assertStringContainsString('col-num', $cells->item($i)->getAttribute('class'), 'column '.($i + 1));
            }
        }
    }

    public function test_existing_colour_semantics_and_badges_are_kept(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->getContent();

        $this->assertStringContainsString('class="col-num movement-neutral"', $html);
        $this->assertStringContainsString('class="col-num movement-plus"', $html);
        $this->assertStringContainsString('movement-minus', $html);
        // A zero or negative Ending keeps its red badge; a positive one keeps the blue value.
        $this->assertStringContainsString('<span class="qty-zero">-9.876,25</span>', $html);
        $this->assertStringContainsString('<span class="qty-value">1.165,00</span>', $html);
    }

    public function test_location_and_ingredient_filters_still_work(): void
    {
        $this->actingAs($this->owner);

        $atB = $this->rowsByNameAndLocation($this->xpath(
            $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->outletB->id]))->assertOk()->getContent()
        ));
        $this->assertSame('25,00', $atB['Gula Pasir|Outlet B'][6]);
        $this->assertArrayNotHasKey('Gula Pasir|Outlet A', $atB);

        $warehouse = $this->rowsByNameAndLocation($this->xpath(
            $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'warehouse', 'summary_location_id' => $this->warehouse->id]))->assertOk()->getContent()
        ));
        $this->assertSame(['Gudang Pusat'], array_values(array_unique(array_map(fn ($key) => explode('|', $key)[1], array_keys($warehouse)))));

        $oneIngredient = $this->rowsByNameAndLocation($this->xpath(
            $this->get(route('backoffice.stock-balances.index', ['ingredient_id' => $this->sirup->id]))->assertOk()->getContent()
        ));
        $this->assertSame(['Sirup Karamel|Outlet A'], array_keys($oneIngredient));
    }

    public function test_location_select_and_actions_are_still_on_the_page(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $options = [];
        foreach ($xpath->query('//select[@id="summary_location_combined"]/option') as $option) {
            $options[] = $option->getAttribute('value');
        }
        $this->assertContains('warehouse:'.$this->warehouse->id, $options);
        $this->assertContains('outlet:'.$this->outletA->id, $options);
        $this->assertContains('outlet:'.$this->outletB->id, $options);

        $this->assertSame(1, $xpath->query('//input[@name="summary_date_from"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="summary_date_to"]')->length);
        $this->assertSame(1, $xpath->query('//a[contains(@href,"stock-balances/export/csv")]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class,"inventory-actions")]//a[@href="'.route('backoffice.index').'"]')->length);
    }

    public function test_viewing_the_summary_changes_no_inventory_data(): void
    {
        $snapshot = fn () => [
            StockMovement::count(), StockBalance::count(),
            (string) StockMovement::sum('qty_in'), (string) StockMovement::sum('qty_out'),
            (string) StockBalance::sum('qty_on_hand'),
        ];
        $before = $snapshot();

        $this->actingAs($this->owner);
        $this->get(route('backoffice.stock-balances.index'))->assertOk();
        $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->outletA->id]))->assertOk();
        $this->get(route('backoffice.stock-balances.index', ['summary_date_from' => '2020-01-01']))->assertOk();

        $this->assertSame($before, $snapshot());
        $this->assertSame(-9876.25, (float) StockBalance::where('ingredient_id', $this->sirup->id)->value('qty_on_hand'), 'negative balances are left as they are');
    }

    public function test_compact_styles_are_scoped_to_the_stock_summary_table_only(): void
    {
        $block = $this->compactBlock($this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent());

        preg_match_all('/([^{}]+)\{[^{}]*\}/', $block, $rules, PREG_SET_ORDER);
        $this->assertNotEmpty($rules);

        foreach ($rules as [, $selectorList]) {
            foreach (explode(',', $selectorList) as $selector) {
                $this->assertMatchesRegularExpression(
                    '/^\.(stock-summary-wrap|stock-summary-table|inventory-table-center\.stock-summary-table)\b/',
                    trim($selector),
                    'unscoped selector in the compact block: '.trim($selector)
                );
            }
        }

        // No bare element rule, and the only !important is the text alignment that must beat the old forced centring.
        $this->assertDoesNotMatchRegularExpression('/(^|\})\s*(table|th|td|tr)\s*[,{]/', $block);
        $this->assertStringNotContainsString('!important', preg_replace('/text-align:\s*(left|right)\s*!important/', '', $block));
    }

    public function test_responsive_and_numeric_rules_are_present(): void
    {
        $block = $this->compactBlock($this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent());

        // A smaller minimum than the old 1180px; narrow screens still scroll inside the wrapper (.table-wrap).
        $this->assertMatchesRegularExpression('/\.stock-summary-table\s*\{[^}]*min-width:\s*720px/', $block);
        $this->assertMatchesRegularExpression('/\.stock-summary-wrap\s*\{[^}]*padding:\s*0 14px 16px/', $block);

        // Figures: right aligned, never wrapped, tabular digits; text columns may wrap.
        $this->assertMatchesRegularExpression('/\.col-num\s*\{[^}]*text-align:\s*right !important[^}]*white-space:\s*nowrap/s', $block);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $block);
        $this->assertMatchesRegularExpression('/\.col-name,[^{]*\.col-cat,[^{]*\.col-loc\s*\{[^}]*text-align:\s*left !important[^}]*overflow-wrap:\s*break-word/s', $block);

        // Readable sizes: figures stay at 13px.
        $this->assertMatchesRegularExpression('/stock-summary-table td\s*\{[^}]*font-size:\s*13px/s', $block);
    }

    public function test_the_page_has_one_table_and_the_hidden_tables_and_old_rules_are_untouched(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent();

        // The other two tables (All Stock Balances, Need Action) stay hidden inside Blade comments.
        $this->assertSame(1, substr_count($html, '<table'));
        $this->assertSame(1, $this->xpath($html)->query('//table')->length);

        // Rules that other markup on this page relies on were not edited.
        $this->assertStringContainsString("min-width: 1180px;\n            border-collapse: collapse;", $html);
        $this->assertStringContainsString('.need-action-table {', $html);
        $this->assertStringContainsString('INVENTORY_CONTROL_FORCE_CENTER_ALIGN', $html);
        $this->assertStringContainsString('STOCK_SUMMARY_COMPACT', $html);
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function summaryPage(): DOMXPath
    {
        return $this->xpath($this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'))->assertOk()->getContent());
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function headers(DOMXPath $xpath): array
    {
        $headers = [];
        foreach ($xpath->query('//table[contains(@class,"stock-summary-table")]//thead//th') as $th) {
            $headers[] = trim($th->textContent);
        }

        return $headers;
    }

    /** @return array<string, string[]> "Name|Location" => [Category, Beginning .. Ending] */
    private function rowsByNameAndLocation(DOMXPath $xpath): array
    {
        $rows = [];

        foreach ($xpath->query('//table[contains(@class,"stock-summary-table")]//tbody/tr') as $row) {
            $cells = [];
            foreach ($xpath->query('./td', $row) as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }

            if (count($cells) === 9) {
                $rows[$cells[0].'|'.$cells[2]] = array_merge([$cells[1]], array_slice($cells, 3));
            }
        }

        return $rows;
    }

    /** The CSS rules of the STOCK_SUMMARY_COMPACT block (comments removed). */
    private function compactBlock(string $html): string
    {
        $start = strpos($html, 'STOCK_SUMMARY_COMPACT');
        $this->assertNotFalse($start);
        $end = strpos($html, '</style>', $start);

        // The marker sits inside a comment; start after that comment closes.
        $afterComment = strpos($html, '*/', $start) + 2;

        return preg_replace('#/\*.*?\*/#s', '', substr($html, $afterComment, $end - $afterComment));
    }

    private function makeIngredient(IngredientCategory $category, string $name, string $unit): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 10)),
            'unit' => $unit,
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        return $ingredient;
    }

    private function ledger(Ingredient $ingredient, string $type, int $locationId, array $movements, float $ending): void
    {
        foreach ($movements as [$movementType, $in, $out]) {
            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'location_type' => $type,
                'location_id' => $locationId,
                'movement_type' => $movementType,
                'qty_in' => $in,
                'qty_out' => $out,
                'note' => 'fixture',
            ]);
        }

        StockBalance::create([
            'ingredient_id' => $ingredient->id,
            'location_type' => $type,
            'location_id' => $locationId,
            'qty_on_hand' => $ending,
        ]);
    }
}
