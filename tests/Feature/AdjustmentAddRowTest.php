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
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement #06: "Tambah Baris" sits below the Stock Adjustment item list.
 *
 * Server-rendered contracts only: where the button is, that it keeps its id / type / label, that the script
 * still wires add / remove / difference preview / focus, and that the submission the page produces is still
 * accepted unchanged. Adding, removing and previewing rows runs in the browser and is exercised in real-browser
 * QA, not here.
 */
class AdjustmentAddRowTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $owner;

    private Ingredient $air;

    private Ingredient $boba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->owner = User::create([
            'name' => 'owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $this->outlet->id,
            'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outlet->id]);

        $category = IngredientCategory::create(['name' => 'Powder', 'code' => 'POWDER', 'is_active' => true]);
        $this->air = $this->makeIngredient($category, 'Air Mineral', 'ml');
        $this->boba = $this->makeIngredient($category, 'Boba', 'gram');
    }

    public function test_add_row_button_sits_below_the_item_list_and_before_the_submit_actions(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.adjustment.create'))->assertOk()->getContent();

        $title = strpos($html, 'Daftar Item Adjustment');
        $list = strpos($html, 'id="items-wrapper"');
        $button = strpos($html, 'id="add-item-btn"');
        $submit = strpos($html, 'Simpan Adjustment');

        $this->assertNotFalse($title);
        $this->assertNotFalse($list);
        $this->assertNotFalse($button);
        $this->assertTrue($title < $list && $list < $button && $button < $submit, 'title, list, Tambah Baris, then Simpan');

        // The button is not inside the heading row any more and exists exactly once.
        $this->assertSame(1, substr_count($html, 'id="add-item-btn"'));
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"items-head")]//*[@id="add-item-btn"]')->length);
        $this->assertSame(1, $xpath->query('//form//*[contains(@class,"items-add")]/button[@id="add-item-btn"]')->length);
    }

    public function test_add_row_button_keeps_its_id_type_and_label_so_it_cannot_submit_the_form(): void
    {
        $xpath = $this->xpath($this->actingAs($this->owner)->get(route('backoffice.stock-balances.adjustment.create'))->assertOk()->getContent());

        $button = $xpath->query('//*[@id="add-item-btn"]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame('button', $button->nodeName);
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('Tambah Baris', trim($button->textContent));
    }

    public function test_row_scripting_contract_is_unchanged(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.adjustment.create'))->assertOk()->getContent();

        // Add / remove wiring and the names the server reads.
        $this->assertStringContainsString("addItemBtn.addEventListener('click'", $html);
        $this->assertStringContainsString("removeBtn.addEventListener('click'", $html);
        $this->assertStringContainsString("'items[' + index + '][ingredient_id]'", $html);
        $this->assertStringContainsString("'items[' + index + '][actual_qty]'", $html);
        $this->assertStringContainsString('ensureAtLeastOneRow()', $html);

        // Difference preview: actual - current stock, same classes.
        $this->assertStringContainsString('const delta = actualQty - currentStock;', $html);
        $this->assertStringContainsString("'delta-up'", $html);
        $this->assertStringContainsString("'delta-down'", $html);

        // The new row takes focus only when the user pressed the button (not on load or after a removal).
        $this->assertMatchesRegularExpression('/addItemBtn\.addEventListener\(\'click\'.{0,400}newSelect\.focus\(\)/s', $html);
        $this->assertStringContainsString("newRow.querySelector('.ingredient-select')", $html);

        // Row template still has its remove button, selector and quantity input.
        $this->assertStringContainsString('class="btn btn-danger btn-sm remove-item-btn"', $html);
        $this->assertStringContainsString('class="ingredient-select"', $html);
        $this->assertStringContainsString('class="actual-qty-input"', $html);

        // Location fields and the CSRF token are untouched.
        $xpath = $this->xpath($html);
        foreach (['location_type', 'location_id', 'note'] as $name) {
            $this->assertSame(1, $xpath->query('//form//*[@name="'.$name.'"]')->length, $name);
        }
        $this->assertSame(1, $xpath->query('//form//input[@name="_token"]')->length);
    }

    public function test_multi_row_submission_still_records_one_adjustment_with_correct_differences(): void
    {
        $this->seedStock($this->air, 100);
        $this->seedStock($this->boba, 40);

        // Exactly the payload shape the page builds for three rows (indexes 0..2, one removed in the UI is gone).
        $this->actingAs($this->owner)
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => $this->outlet->id,
                'note' => 'Opname',
                'items' => [
                    ['ingredient_id' => $this->air->id, 'actual_qty' => '55.5'],
                    ['ingredient_id' => $this->boba->id, 'actual_qty' => '60'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $adjustment = StockAdjustment::with('items')->firstOrFail();
        $this->assertCount(2, $adjustment->items);

        $air = $adjustment->items->firstWhere('ingredient_id', $this->air->id);
        $this->assertEquals([100.0, 55.5, -44.5], [$air->system_qty, $air->actual_qty, $air->difference]);
        $boba = $adjustment->items->firstWhere('ingredient_id', $this->boba->id);
        $this->assertEquals([40.0, 60.0, 20.0], [$boba->system_qty, $boba->actual_qty, $boba->difference]);

        $this->assertSame(55.5, $this->balance($this->air));
        $this->assertSame(60.0, $this->balance($this->boba));
        $this->assertSame(2, StockMovement::where('movement_type', 'stock_adjustment')->count());
    }

    public function test_adjustment_validation_is_unchanged(): void
    {
        $this->actingAs($this->owner);
        $create = route('backoffice.stock-balances.adjustment.create');

        // No items.
        $this->from($create)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->outlet->id,
        ])->assertRedirect($create)->assertSessionHasErrors('items');

        // A row without an actual quantity.
        $this->from($create)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->outlet->id,
            'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => '']],
        ])->assertRedirect($create)->assertSessionHasErrors('items.0.actual_qty');

        // A row without an ingredient.
        $this->from($create)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->outlet->id,
            'items' => [['ingredient_id' => '', 'actual_qty' => '3']],
        ])->assertRedirect($create)->assertSessionHasErrors('items.0.ingredient_id');

        // A negative quantity and the same ingredient twice.
        $this->from($create)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->outlet->id,
            'items' => [['ingredient_id' => $this->air->id, 'actual_qty' => '-1']],
        ])->assertSessionHasErrors('items.0.actual_qty');
        $this->from($create)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'outlet', 'location_id' => $this->outlet->id,
            'items' => [
                ['ingredient_id' => $this->air->id, 'actual_qty' => '1'],
                ['ingredient_id' => $this->air->id, 'actual_qty' => '2'],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, StockAdjustment::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_entered_rows_come_back_into_the_form_after_a_validation_error(): void
    {
        $this->seedStock($this->air, 100);
        $create = route('backoffice.stock-balances.adjustment.create');

        $this->actingAs($this->owner)
            ->from($create)
            ->post(route('backoffice.stock-balances.adjustment.store'), [
                'location_type' => 'outlet',
                'location_id' => $this->outlet->id,
                'note' => 'Keep me',
                'items' => [
                    ['ingredient_id' => $this->air->id, 'actual_qty' => '42'],
                    ['ingredient_id' => $this->boba->id, 'actual_qty' => ''],
                ],
            ])
            ->assertRedirect($create)
            ->assertSessionHasErrors('items.1.actual_qty');

        $html = $this->get($create)->assertOk()->getContent();

        $this->assertStringContainsString('Keep me', $html);
        // Both rows come back (the empty quantity as null), in the order they were entered.
        $this->assertStringContainsString(
            'const oldItems = [{"ingredient_id":'.$this->air->id.',"actual_qty":"42"},{"ingredient_id":'.$this->boba->id.',"actual_qty":null}];',
            $html
        );
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function makeIngredient(IngredientCategory $category, string $name, string $unit): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => $unit,
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outlet->id]);

        return $ingredient;
    }

    private function seedStock(Ingredient $ingredient, float $qty): void
    {
        StockBalance::updateOrCreate(
            ['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id],
            ['qty_on_hand' => $qty]
        );
    }

    private function balance(Ingredient $ingredient): float
    {
        return (float) StockBalance::where(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet->id])->value('qty_on_hand');
    }
}
