{{-- One new Recipe item row (also used as the <template> with index "__INDEX__"). --}}
<div class="pw-recipe-row" data-pw-recipe-new-row>
    <div class="pw-recipe-row-main">
        <div class="pw-recipe-ingredient">
            <label class="pw-sr-only" for="pw-recipe-new-{{ $index }}">Ingredient</label>
            <select id="pw-recipe-new-{{ $index }}" name="new_items[{{ $index }}][ingredient_id]" data-pw-recipe-ingredient-select>
                <option value="">Pilih Ingredient</option>
                @foreach($ingredientOptions as $pwOption)
                    <option value="{{ $pwOption['id'] }}" data-unit="{{ $pwOption['unit'] }}">{{ $pwOption['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="pw-recipe-qty">
            <label class="pw-sr-only" for="pw-recipe-new-qty-{{ $index }}">Qty</label>
            <input id="pw-recipe-new-qty-{{ $index }}" type="text" inputmode="decimal" autocomplete="off" name="new_items[{{ $index }}][qty]" value="" placeholder="Qty">
            <span class="pw-recipe-unit" data-pw-recipe-unit>-</span>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-pw-recipe-remove-new aria-label="Hapus baris">&times;</button>
    </div>
    <div class="pw-field-error" data-pw-drawer-error="new_items.{{ $index }}.ingredient_id" hidden></div>
    <div class="pw-field-error" data-pw-drawer-error="new_items.{{ $index }}.qty" hidden></div>
</div>
