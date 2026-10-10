<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement #08: one "Lokasi Adjustment" select on the Stock Adjustment form.
 *
 * The visible select is only a view onto the two original request fields: the form must still submit exactly one
 * location_type and one location_id, the original selects stay as the working no-JS fallback, and warehouse 1 and
 * outlet 1 must never be confused. The page's JavaScript is exercised in a real browser during QA; here we pin the
 * server-rendered markup and data, the script's safety contract, and that the unchanged server side still writes
 * exactly the right stock record.
 */
class AdjustmentCombinedLocationTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet1;

    private Outlet $outlet2;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Warehouse $inactiveWarehouse;

    private User $owner;

    private User $limited;

    private Ingredient $a;

    private Ingredient $b;

    protected function setUp(): void
    {
        parent::setUp();

        // Outlet id 1 and Warehouse id 1 on purpose: the same number in two different places.
        $this->outlet1 = Outlet::create(['name' => 'Outlet Satu', 'code' => 'O1', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['name' => 'Outlet Dua', 'code' => 'O2', 'is_active' => true]);
        $this->warehouse1 = Warehouse::create(['name' => 'Gudang Pusat', 'code' => 'W1', 'is_active' => true]);
        $this->warehouse2 = Warehouse::create(['name' => 'Gudang Cadangan', 'code' => 'W2', 'is_active' => true]);
        $this->inactiveWarehouse = Warehouse::create(['name' => 'Gudang Tutup', 'code' => 'W3', 'is_active' => false]);
        $this->assertSame(1, $this->outlet1->id);
        $this->assertSame(1, $this->warehouse1->id);

        $owner = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $admin = Role::create(['name' => 'Admin Outlet', 'code' => 'admin_outlet']);
        $this->owner = $this->makeUser('owner', $owner, [$this->outlet1, $this->outlet2]);
        $this->limited = $this->makeUser('limited', $admin, [$this->outlet2]);

        $category = IngredientCategory::create(['name' => 'Bahan', 'code' => 'BHN', 'is_active' => true]);
        $this->a = $this->ingredient($category, 'Ingredient A', 'gram');
        $this->b = $this->ingredient($category, 'Ingredient B', 'ml');

        // The same ingredient, at warehouse 1 and at outlet 1, with different stock.
        $this->stock($this->a, 'warehouse', $this->warehouse1->id, 100);
        $this->stock($this->a, 'outlet', $this->outlet1->id, 40);
        $this->stock($this->b, 'warehouse', $this->warehouse1->id, 250.5);
        $this->stock($this->b, 'outlet', $this->outlet1->id, 12.25);
        $this->stock($this->a, 'outlet', $this->outlet2->id, 7);
    }

    // ---- The one visible selector --------------------------------------------------------------------

    public function test_there_is_one_combined_location_select_that_is_never_submitted(): void
    {
        $xpath = $this->page();

        $this->assertSame(1, $xpath->query('//select[@id="location_combined"]')->length);
        $combined = $xpath->query('//select[@id="location_combined"]')->item(0);

        $this->assertFalse($combined->hasAttribute('name'), 'no name, so it can never be submitted');
        $this->assertFalse($combined->hasAttribute('required'), 'required only once JS has taken over');
        $this->assertSame('Lokasi Adjustment', trim($xpath->query('//label[@for="location_combined"]')->item(0)->textContent));
        $this->assertSame('Pilih lokasi', trim($xpath->query('.//option[@value=""]', $combined)->item(0)->textContent));

        // Nothing is pre-selected: no default warehouse or outlet.
        $this->assertSame(0, $xpath->query('.//option[@selected]', $combined)->length);
    }

    public function test_the_combined_select_offers_the_actual_warehouses_and_outlets_with_typed_values(): void
    {
        $xpath = $this->page();

        $options = $this->combinedOptions($xpath);

        // Same order as the original select (by name); compared as a map.
        $this->assertEquals([
            'warehouse:'.$this->warehouse1->id => 'Gudang – Gudang Pusat',
            'warehouse:'.$this->warehouse2->id => 'Gudang – Gudang Cadangan',
            'outlet:'.$this->outlet1->id => 'Outlet – Outlet Satu',
            'outlet:'.$this->outlet2->id => 'Outlet – Outlet Dua',
        ], $options);

        // Grouped for scanning, and the inactive warehouse is not offered (as in the original select).
        $this->assertSame(['Gudang', 'Outlet'], $this->groupLabels($xpath));
        $this->assertArrayNotHasKey('warehouse:'.$this->inactiveWarehouse->id, $options);
    }

    public function test_the_combined_select_offers_exactly_what_the_original_location_select_offers(): void
    {
        $xpath = $this->page();

        $legacy = [];
        foreach ($xpath->query('//select[@id="location_id"]/option[@data-type]') as $option) {
            $legacy[] = $option->getAttribute('data-type').':'.$option->getAttribute('value');
        }

        $this->assertEqualsCanonicalizing($legacy, array_keys($this->combinedOptions($xpath)));
    }

    // ---- Request contract ------------------------------------------------------------------------------

    public function test_the_original_request_fields_are_untouched_and_submitted_exactly_once(): void
    {
        $xpath = $this->page();
        $form = $xpath->query('//form[contains(@action,"stock-balances/adjustment")]')->item(0);

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(route('backoffice.stock-balances.adjustment.store'), $form->getAttribute('action'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);

        // One field of each name, both still required for the no-JS case; nothing else carries a location name.
        $this->assertSame(1, $xpath->query('.//select[@name="location_type"][@required]', $form)->length);
        $this->assertSame(1, $xpath->query('.//select[@name="location_id"][@required]', $form)->length);
        $this->assertSame(2, $xpath->query('.//*[starts-with(@name,"location")]', $form)->length);
        $this->assertSame(1, $xpath->query('.//textarea[@name="note"]', $form)->length);

        // The type select still offers exactly the two types the server accepts.
        $types = [];
        foreach ($xpath->query('.//select[@name="location_type"]/option', $form) as $option) {
            $types[] = $option->getAttribute('value');
        }
        $this->assertSame(['', 'warehouse', 'outlet'], $types);
    }

    public function test_warehouse_and_outlet_with_the_same_id_stay_distinct_in_markup_and_stock_data(): void
    {
        $xpath = $this->page();
        $options = $this->combinedOptions($xpath);

        $this->assertSame('Gudang – Gudang Pusat', $options['warehouse:1']);
        $this->assertSame('Outlet – Outlet Satu', $options['outlet:1']);

        // The original Lokasi select has two options with value "1", told apart only by data-type.
        $ones = $xpath->query('//select[@id="location_id"]/option[@value="1"]');
        $this->assertSame(2, $ones->length);
        $this->assertEqualsCanonicalizing(['warehouse', 'outlet'], [$ones->item(0)->getAttribute('data-type'), $ones->item(1)->getAttribute('data-type')]);

        // The stock the preview reads is keyed by type:id, so the same ingredient has two different figures.
        $map = $this->stockMap($this->html());
        $this->assertEquals(100, $map['warehouse:1'][$this->a->id]);
        $this->assertEquals(40, $map['outlet:1'][$this->a->id]);
        $this->assertEquals(250.5, $map['warehouse:1'][$this->b->id]);
        $this->assertEquals(12.25, $map['outlet:1'][$this->b->id]);

        // Actual 60: warehouse 100 -> -40, outlet 40 -> +20 (the page's formula is actual - current).
        $this->assertSame(-40.0, 60 - (float) $map['warehouse:1'][$this->a->id]);
        $this->assertSame(20.0, 60 - (float) $map['outlet:1'][$this->a->id]);
    }

    public function test_the_script_maps_by_type_and_id_together_and_never_parses_labels(): void
    {
        $html = $this->html();

        // Strict parse of "warehouse:12" / "outlet:12"; anything else is rejected.
        $this->assertStringContainsString('/^(warehouse|outlet):([0-9]+)$/', $html);
        // The original Lokasi option is found by value AND data-type (warehouse 1 and outlet 1 share the value).
        $this->assertMatchesRegularExpression("/option\\.value === location\\.id && option\\.getAttribute\\('data-type'\\) === location\\.type/", $html);
        // A mapping that does not check out clears both fields instead of leaving a half-set location.
        $this->assertStringContainsString('clearLegacyLocation();', $html);
        $this->assertMatchesRegularExpression("/const agrees = typeSelect\\.value === location\\.type/", $html);

        // No splitting or label reading, no numeric coercion of the id.
        $script = $this->script($html);
        $this->assertStringNotContainsString(".split(':')", $script);
        $this->assertStringNotContainsString('parseInt', $script);
        $this->assertStringNotContainsString('textContent.split', $script);
        $this->assertStringNotContainsString('data-label', $script);

        // The stock preview key is built from the validated type and id.
        $this->assertStringContainsString("mapKey = location ? location.type + ':' + location.id : null;", $html);
    }

    // ---- No location, JS-off and failure safety ---------------------------------------------------------------------

    public function test_nothing_is_preselected_and_the_script_blocks_a_submit_without_a_valid_location(): void
    {
        $html = $this->html();
        $xpath = $this->xpath($html);

        // No default in either the combined or the original selects.
        $this->assertSame(0, $xpath->query('//select[@id="location_type"]/option[@selected]')->length);
        $this->assertSame(0, $xpath->query('//select[@id="location_id"]/option[@selected]')->length);

        // Native required on the visible select, plus a last re-sync on submit that cancels the form without a location.
        $this->assertStringContainsString('combinedSelect.required = true;', $html);
        $this->assertMatchesRegularExpression("/form\\.addEventListener\\('submit'.*?if \\(!syncLegacyFromCombined\\(\\)\\) \\{\\s*event\\.preventDefault\\(\\);/s", $html);

        // Changing the location refreshes the preview and never submits anything.
        preg_match("/combinedSelect\\.addEventListener\\('change', function \\(\\) \\{(.*?)\\n                    \\}\\);/s", $html, $handler);
        $this->assertNotEmpty($handler);
        $this->assertStringContainsString('syncLegacyFromCombined();', $handler[1]);
        $this->assertStringContainsString('refreshAllIngredientLabels();', $handler[1]);
        $this->assertStringNotContainsString('submit', strtolower($handler[1]));
    }

    public function test_without_javascript_the_original_two_selects_are_the_visible_working_fields(): void
    {
        $html = $this->html();
        $xpath = $this->xpath($html);

        // Server markup: the legacy fields carry no hidden state and stay required; the combined field is not required.
        foreach (['location_type', 'location_id'] as $id) {
            $select = $xpath->query('//select[@id="'.$id.'"]')->item(0);
            $this->assertTrue($select->hasAttribute('required'), $id);
            $this->assertSame(1, $xpath->query('//select[@id="'.$id.'"]/ancestor::*[contains(@class,"loc-legacy")]')->length);
            $this->assertSame(0, $xpath->query('//select[@id="'.$id.'"]/ancestor-or-self::*[@hidden or contains(@style,"display")]')->length);
        }

        // The enhanced state exists only as a class JS adds, and is the last step of the setup.
        $this->assertSame(0, $xpath->query('//*[contains(@class,"is-location-enhanced")]')->length);
        $this->assertMatchesRegularExpression('/\.loc-combined\s*\{\s*display:\s*none;/', $html);
        $this->assertMatchesRegularExpression("/combinedSelect\\.required = true;\\s*topFields\\.classList\\.add\\('is-location-enhanced'\\);\\s*combinedActive = true;/s", $html);
    }

    public function test_a_failure_while_enabling_the_combined_select_restores_the_original_fields(): void
    {
        $html = $this->html();

        // Everything is wired inside a try; the catch puts the originals back (still required) and un-hides nothing.
        $this->assertMatchesRegularExpression(
            "/function enableCombinedLocation\\(\\) \\{.*?try \\{.*?\\} catch \\(error\\) \\{\\s*combinedActive = false;\\s*typeSelect\\.required = true;\\s*locationSelect\\.required = true;\\s*combinedSelect\\.required = false;\\s*topFields\\.classList\\.remove\\('is-location-enhanced'\\);/s",
            $html
        );
        // The original selects only stop being required right before the switch, after all listeners are attached.
        $this->assertLessThan(strpos($html, 'typeSelect.required = false;'), strpos($html, "form.addEventListener('submit'"));
    }

    public function test_compact_styles_are_scoped_to_the_location_fields(): void
    {
        $html = $this->html();
        $start = strpos($html, 'ADJUSTMENT_LOCATION_COMBINED');
        $this->assertNotFalse($start);
        $block = preg_replace('#/\*.*?\*/#s', '', substr($html, strpos($html, '*/', $start) + 2, 700));
        preg_match_all('/([^{}]+)\{[^{}]*\}/', $block, $rules, PREG_SET_ORDER);

        $selectors = [];
        foreach (array_slice($rules, 0, 4) as [, $list]) {
            foreach (explode(',', $list) as $selector) {
                $selectors[] = trim($selector);
            }
        }

        $this->assertSame([
            '.loc-combined',
            '.top-fields-grid.is-location-enhanced',
            '.top-fields-grid.is-location-enhanced .loc-legacy',
            '.top-fields-grid.is-location-enhanced .loc-combined',
        ], $selectors);
    }

    // ---- Restoring the form after a server-side failure --------------------------------------------------------------

    public function test_a_failed_warehouse_submission_restores_the_warehouse_not_the_outlet_with_the_same_id(): void
    {
        $html = $this->failedSubmission('warehouse', $this->warehouse1->id);

        $xpath = $this->xpath($html);
        $this->assertSame(['warehouse:1'], $this->selectedCombined($xpath));
        $this->assertSame('warehouse', $this->selectedValue($xpath, 'location_type'));
        $selected = $xpath->query('//select[@id="location_id"]/option[@selected]');
        $this->assertSame(1, $selected->length);
        $this->assertSame(['warehouse', '1'], [$selected->item(0)->getAttribute('data-type'), $selected->item(0)->getAttribute('value')]);

        $this->assertRestoredEntries($html);
    }

    public function test_a_failed_outlet_submission_restores_the_outlet_not_the_warehouse_with_the_same_id(): void
    {
        $html = $this->failedSubmission('outlet', $this->outlet1->id);

        $xpath = $this->xpath($html);
        $this->assertSame(['outlet:1'], $this->selectedCombined($xpath));
        $this->assertSame('outlet', $this->selectedValue($xpath, 'location_type'));
        $selected = $xpath->query('//select[@id="location_id"]/option[@selected]');
        $this->assertSame(1, $selected->length);
        $this->assertSame(['outlet', '1'], [$selected->item(0)->getAttribute('data-type'), $selected->item(0)->getAttribute('value')]);

        $this->assertRestoredEntries($html);
    }

    // ---- The unchanged server side: validation and exactly-right writes ------------------------------------------------

    public function test_a_warehouse_submission_updates_only_that_warehouses_stock(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'warehouse',
                'location_id' => 1,
                'note' => 'Opname gudang',
                'items' => [['ingredient_id' => $this->a->id, 'actual_qty' => '60']],
            ])
            ->assertSessionHasNoErrors();

        $adjustment = StockAdjustment::with('items')->firstOrFail();
        $this->assertSame(['warehouse', 1], [$adjustment->location_type, (int) $adjustment->location_id]);
        $this->assertEquals([100.0, 60.0, -40.0], [$adjustment->items[0]->system_qty, $adjustment->items[0]->actual_qty, $adjustment->items[0]->difference]);

        $this->assertSame(60.0, $this->balance($this->a, 'warehouse', 1));
        $this->assertSame(40.0, $this->balance($this->a, 'outlet', 1), 'the outlet with the same id is untouched');
        $this->assertSame(7.0, $this->balance($this->a, 'outlet', 2));

        $movement = StockMovement::firstOrFail();
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(['warehouse', 1, 'stock_adjustment'], [$movement->location_type, (int) $movement->location_id, $movement->movement_type]);
        $this->assertEquals([0.0, 40.0], [(float) $movement->qty_in, (float) $movement->qty_out]);
    }

    public function test_an_outlet_submission_updates_only_that_outlets_stock(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => 1,
                'items' => [['ingredient_id' => $this->a->id, 'actual_qty' => '60']],
            ])
            ->assertSessionHasNoErrors();

        $adjustment = StockAdjustment::with('items')->firstOrFail();
        $this->assertSame(['outlet', 1], [$adjustment->location_type, (int) $adjustment->location_id]);
        $this->assertEquals([40.0, 60.0, 20.0], [$adjustment->items[0]->system_qty, $adjustment->items[0]->actual_qty, $adjustment->items[0]->difference]);

        $this->assertSame(60.0, $this->balance($this->a, 'outlet', 1));
        $this->assertSame(100.0, $this->balance($this->a, 'warehouse', 1), 'the warehouse with the same id is untouched');

        $movement = StockMovement::firstOrFail();
        $this->assertSame(['outlet', 1], [$movement->location_type, (int) $movement->location_id]);
        $this->assertEquals([20.0, 0.0], [(float) $movement->qty_in, (float) $movement->qty_out]);
    }

    public function test_missing_malformed_and_stale_locations_create_nothing(): void
    {
        $this->actingAs($this->owner);
        $items = [['ingredient_id' => $this->a->id, 'actual_qty' => '60']];
        $from = route('backoffice.stock-balances.adjustment.create');
        $snapshot = $this->snapshot();

        $cases = [
            'no location at all' => [['items' => $items], ['location_type', 'location_id']],
            'type only' => [['location_type' => 'warehouse', 'items' => $items], ['location_id']],
            'id only' => [['location_id' => 1, 'items' => $items], ['location_type']],
            'unknown type' => [['location_type' => 'branch', 'location_id' => 1, 'items' => $items], ['location_type']],
            'the combined value sent as a type' => [['location_type' => 'warehouse:1', 'location_id' => 1, 'items' => $items], ['location_type']],
            'non-numeric id' => [['location_type' => 'outlet', 'location_id' => 'abc', 'items' => $items], ['location_id']],
            'zero id' => [['location_type' => 'outlet', 'location_id' => 0, 'items' => $items], ['location_id']],
            'outlet that does not exist' => [['location_type' => 'outlet', 'location_id' => 999, 'items' => $items], ['location_id']],
            'warehouse that does not exist' => [['location_type' => 'warehouse', 'location_id' => 999, 'items' => $items], ['location_id']],
            'inactive warehouse' => [['location_type' => 'warehouse', 'location_id' => $this->inactiveWarehouse->id, 'items' => $items], ['location_id']],
        ];

        foreach ($cases as $label => [$payload, $errors]) {
            $this->from($from)->post(route('backoffice.stock-balances.adjustment.store'), $payload)
                ->assertRedirect($from)
                ->assertSessionHasErrors($errors);
            $this->assertSame($snapshot, $this->snapshot(), $label);
        }

        // An id that only exists as the other kind of location is still refused.
        $this->from($from)->post(route('backoffice.stock-balances.adjustment.store'), ['location_type' => 'warehouse', 'location_id' => 3, 'items' => $items])
            ->assertSessionHasErrors('location_id');

        $this->assertSame($snapshot, $this->snapshot(), 'no adjustment, movement or balance change');
    }

    public function test_the_existing_item_validation_is_unchanged(): void
    {
        $this->actingAs($this->owner);
        $from = route('backoffice.stock-balances.adjustment.create');
        $snapshot = $this->snapshot();

        $this->from($from)->post(route('backoffice.stock-balances.adjustment.store'), ['location_type' => 'warehouse', 'location_id' => 1])->assertSessionHasErrors('items');
        $this->from($from)->post(route('backoffice.stock-balances.adjustment.store'), ['location_type' => 'warehouse', 'location_id' => 1, 'items' => [['ingredient_id' => $this->a->id, 'actual_qty' => '']]])->assertSessionHasErrors('items.0.actual_qty');
        $this->from($from)->post(route('backoffice.stock-balances.adjustment.store'), ['location_type' => 'warehouse', 'location_id' => 1, 'items' => [['ingredient_id' => $this->a->id, 'actual_qty' => '-1']]])->assertSessionHasErrors('items.0.actual_qty');

        $this->assertSame($snapshot, $this->snapshot());
    }

    // ---- Permissions: nothing new becomes available ------------------------------------------------------------------

    public function test_a_limited_user_is_offered_no_outlet_they_could_not_use_before(): void
    {
        $xpath = $this->page($this->limited);
        $options = $this->combinedOptions($xpath);

        $this->assertArrayHasKey('outlet:'.$this->outlet2->id, $options);
        $this->assertArrayNotHasKey('outlet:'.$this->outlet1->id, $options);

        // Same list as the original select (warehouses are offered exactly as they were before this change).
        $legacy = [];
        foreach ($xpath->query('//select[@id="location_id"]/option[@data-type]') as $option) {
            $legacy[] = $option->getAttribute('data-type').':'.$option->getAttribute('value');
        }
        $this->assertEqualsCanonicalizing($legacy, array_keys($options));

        // The stock data handed to the page covers no outlet the user cannot access.
        $map = $this->stockMap($this->html($this->limited));
        $this->assertArrayNotHasKey('outlet:'.$this->outlet1->id, $map);
    }

    public function test_a_limited_user_still_cannot_adjust_an_outlet_outside_their_access(): void
    {
        $snapshot = $this->snapshot();

        $this->actingAs($this->limited)
            ->from(route('backoffice.stock-balances.adjustment.create'))
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => $this->outlet1->id,
                'items' => [['ingredient_id' => $this->a->id, 'actual_qty' => '60']],
            ])
            ->assertSessionHasErrors('location_id');

        $this->assertSame($snapshot, $this->snapshot());
    }

    // ---- Reading changes nothing; earlier behaviour stays -------------------------------------------------------------

    public function test_opening_the_form_writes_no_inventory_data(): void
    {
        $snapshot = $this->snapshot();

        $this->page();
        $this->page($this->limited);

        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_the_add_row_and_preview_behaviour_from_the_earlier_change_is_intact(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('id="add-item-btn"', $html);
        $this->assertStringContainsString("addItemBtn.addEventListener('click'", $html);
        $this->assertStringContainsString('newSelect.focus();', $html);
        $this->assertStringContainsString('const delta = actualQty - currentStock;', $html);
        $this->assertStringContainsString("'items[' + index + '][ingredient_id]'", $html);
        $this->assertStringContainsString("'items[' + index + '][actual_qty]'", $html);

        // The button still sits under the list and before the submit actions.
        $this->assertTrue(strpos($html, 'id="items-wrapper"') < strpos($html, 'id="add-item-btn"'));
        $this->assertTrue(strpos($html, 'id="add-item-btn"') < strpos($html, 'Simpan Adjustment'));
    }

    // ---- helpers ------------------------------------------------------------------------------------------------------

    private function html(?User $user = null): string
    {
        return $this->actingAs($user ?? $this->owner)->get(route('backoffice.stock-balances.adjustment.create'))->assertOk()->getContent();
    }

    private function page(?User $user = null): DOMXPath
    {
        return $this->xpath($this->html($user));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    /** @return array<string, string> "type:id" => label */
    private function combinedOptions(DOMXPath $xpath): array
    {
        $options = [];
        foreach ($xpath->query('//select[@id="location_combined"]//option[@value!=""]') as $option) {
            $options[$option->getAttribute('value')] = trim(preg_replace('/\s+/', ' ', $option->textContent));
        }

        return $options;
    }

    /** @return string[] */
    private function groupLabels(DOMXPath $xpath): array
    {
        $labels = [];
        foreach ($xpath->query('//select[@id="location_combined"]//optgroup') as $group) {
            $labels[] = $group->getAttribute('label');
        }

        return $labels;
    }

    /** @return string[] */
    private function selectedCombined(DOMXPath $xpath): array
    {
        $selected = [];
        foreach ($xpath->query('//select[@id="location_combined"]//option[@selected]') as $option) {
            $selected[] = $option->getAttribute('value');
        }

        return $selected;
    }

    private function selectedValue(DOMXPath $xpath, string $selectId): ?string
    {
        $option = $xpath->query('//select[@id="'.$selectId.'"]/option[@selected]')->item(0);

        return $option?->getAttribute('value');
    }

    /** @return array<string, array<int|string, float>> */
    private function stockMap(string $html): array
    {
        preg_match('/const stockMap = (.*?);\n/', $html, $match);
        $this->assertNotEmpty($match, 'stock map present');

        return json_decode($match[1], true);
    }

    private function script(string $html): string
    {
        $start = strpos($html, 'const typeSelect');

        return substr($html, $start, strpos($html, '</script>', $start) - $start);
    }

    private function failedSubmission(string $type, int $id): string
    {
        $create = route('backoffice.stock-balances.adjustment.create');

        // The same ingredient twice: refused by the server. Follow the redirect like a browser does, so the form comes
        // back with its old input and its error message.
        $response = $this->actingAs($this->owner)
            ->from($create)
            ->followingRedirects()
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => $type,
                'location_id' => $id,
                'note' => 'Please keep this note',
                'items' => [
                    ['ingredient_id' => $this->a->id, 'actual_qty' => '60'],
                    ['ingredient_id' => $this->a->id, 'actual_qty' => '61.5'],
                ],
            ]);

        $this->assertSame(0, StockAdjustment::count());
        $this->assertSame(0, StockMovement::count());

        return $response->assertOk()->getContent();
    }

    private function assertRestoredEntries(string $html): void
    {
        $this->assertStringContainsString('Ingredient yang sama tidak boleh dipilih lebih dari satu kali', $html);
        $this->assertStringContainsString('Please keep this note', $html);
        $this->assertStringContainsString(
            'const oldItems = [{"ingredient_id":'.$this->a->id.',"actual_qty":"60"},{"ingredient_id":'.$this->a->id.',"actual_qty":"61.5"}];',
            $html
        );
    }

    /** @return array<int, mixed> */
    private function snapshot(): array
    {
        return [
            StockAdjustment::count(),
            StockMovement::count(),
            StockBalance::orderBy('id')->get(['ingredient_id', 'location_type', 'location_id', 'qty_on_hand'])->toArray(),
        ];
    }

    private function balance(Ingredient $ingredient, string $type, int $id): float
    {
        return (float) StockBalance::where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])->value('qty_on_hand');
    }

    private function stock(Ingredient $ingredient, string $type, int $id, float $qty): void
    {
        StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id, 'qty_on_hand' => $qty]);
    }

    private function makeUser(string $username, Role $role, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $outlets[0]->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    private function ingredient(IngredientCategory $category, string $name, string $unit): Ingredient
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
        $ingredient->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

        return $ingredient;
    }
}
