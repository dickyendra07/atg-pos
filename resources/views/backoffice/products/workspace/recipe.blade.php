{{-- Recipe per Variant (global: one Recipe serves every outlet of the Variant). Edit / Buat Recipe open
     the Recipe drawer; without JS the same links open the classic Recipe pages. Aktifkan / Nonaktifkan
     are explicit actions with a confirmation. Ambiguous Variants (several active Recipes) are read-only
     here, and nothing is ever repaired, merged or picked automatically. No Recipe delete here. --}}
@php
    $pwRecipeLabels = [
        'none' => ['Belum ada Recipe', 'status-inactive'],
        'inactive' => ['Recipe nonaktif', 'status-inactive'],
        'empty' => ['Recipe tanpa bahan', 'status-warn'],
        'ambiguous' => ['Lebih dari satu Recipe aktif', 'status-inactive'],
        'valid' => ['Recipe valid', 'status-active'],
    ];
@endphp
<div class="pw-card">
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Recipe</h2>
            <p class="pw-card-sub">Recipe berlaku global per Variant: perubahan Recipe berlaku di semua outlet yang memakai Variant tersebut.</p>
        </div>
    </div>

    @forelse($workspace['recipes'] as $pwRecipeRow)
        @php([$pwLabel, $pwTone] = $pwRecipeLabels[$pwRecipeRow['status']])
        <div class="pw-row-card" data-pw-recipe-variant="{{ $pwRecipeRow['variant_id'] }}" data-pw-recipe-status="{{ $pwRecipeRow['status'] }}">
            <div class="pw-row-card-head">
                <div>
                    <strong>{{ $pwRecipeRow['variant_name'] }}</strong>
                    @unless($pwRecipeRow['variant_active'])<span class="status-badge status-inactive pw-badge-sm">Variant inactive</span>@endunless
                </div>
                <span class="status-badge {{ $pwTone }}">{{ $pwLabel }}</span>
            </div>

            @if($pwRecipeRow['status'] === 'ambiguous')
                <div class="pw-alert pw-alert-danger" data-pw-recipe-ambiguous>
                    <strong>Lebih dari satu Recipe aktif. Perlu review data terlebih dahulu.</strong>
                    <div>Recipe aktif: {{ collect($pwRecipeRow['active_recipe_ids'])->map(fn ($id) => '#'.$id)->implode(', ') }}.</div>
                    <div>Cashier menolak penjualan Variant ini. Sistem tidak memilih salah satu secara otomatis dan tidak menonaktifkan Recipe apa pun; Recipe Variant ini hanya dapat dilihat di Product Workspace. Tinjau datanya di halaman Recipe.</div>
                </div>
            @elseif($pwRecipeRow['status'] === 'inactive' && $pwRecipeRow['inactive_count'] > 1)
                <div class="pw-alert pw-alert-warn">Ada {{ $pwRecipeRow['inactive_count'] }} Recipe nonaktif untuk Variant ini. Pilih sendiri Recipe yang ingin diperiksa atau diaktifkan; sistem tidak memilih salah satu secara otomatis.</div>
            @elseif($pwRecipeRow['status'] === 'empty')
                <div class="pw-alert pw-alert-warn">Recipe aktif belum memiliki bahan, sehingga Variant ini belum dapat dijual. Tambahkan bahan lewat Edit Recipe.</div>
            @endif

            @foreach($pwRecipeRow['recipes'] as $pwRecipe)
                <div class="pw-recipe" data-pw-recipe="{{ $pwRecipe['id'] }}">
                    <div class="pw-recipe-head">
                        <div class="pw-recipe-title">
                            <span class="pw-code">#{{ $pwRecipe['id'] }}</span>
                            <strong>{{ $pwRecipe['name'] }}</strong>
                            <span class="status-badge pw-badge-sm {{ $pwRecipe['is_active'] ? 'status-active' : 'status-muted' }}">{{ $pwRecipe['is_active'] ? 'Active' : 'Inactive' }}</span>
                            <span class="pw-muted">{{ $pwRecipe['items_count'] }} bahan</span>
                        </div>
                        <div class="pw-row-buttons">
                            @if($pwRecipe['form_url'])
                                <a href="{{ $pwRecipe['edit_url'] }}"
                                   class="btn btn-blue btn-sm"
                                   data-pw-open-drawer="recipe"
                                   data-pw-form-url="{{ $pwRecipe['form_url'] }}">Edit Recipe</a>
                            @else
                                <a href="{{ $pwRecipe['edit_url'] }}" class="btn btn-light btn-sm">{{ $pwRecipeRow['can_mutate'] ? 'Kelola di halaman Recipe' : 'Lihat Recipe' }}</a>
                            @endif
                            @if($pwRecipe['activate_url'])
                                <form method="POST"
                                      action="{{ $pwRecipe['activate_url'] }}"
                                      data-pw-row-action
                                      data-bo-confirm-title="Aktifkan Recipe #{{ $pwRecipe['id'] }}?"
                                      data-bo-confirm-body="Recipe “{{ $pwRecipe['name'] }}” akan dipakai Cashier untuk Variant {{ $pwRecipeRow['variant_name'] }} di semua outlet Variant ini."
                                      data-bo-confirm-note="Server memeriksa ulang: bahan ada, qty valid, Ingredient aktif dan tersedia di semua outlet Variant, serta tidak ada Recipe aktif lain."
                                      data-bo-confirm-label="Aktifkan"
                                      data-bo-confirm-tone="primary">
                                    @csrf
                                    @method('PATCH')
                                    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])
                                    <button type="submit" class="btn btn-green btn-sm">Aktifkan</button>
                                </form>
                            @endif
                            @if($pwRecipe['deactivate_url'])
                                <form method="POST"
                                      action="{{ $pwRecipe['deactivate_url'] }}"
                                      data-pw-row-action
                                      data-bo-confirm-title="Nonaktifkan Recipe #{{ $pwRecipe['id'] }}?"
                                      data-bo-confirm-body="Variant ini dapat menjadi tidak dapat dijual karena tidak memiliki Recipe aktif."
                                      data-bo-confirm-note="Recipe dan bahannya tetap tersimpan dan bisa diaktifkan kembali."
                                      data-bo-confirm-label="Nonaktifkan"
                                      data-bo-confirm-tone="warning">
                                    @csrf
                                    @method('PATCH')
                                    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])
                                    <button type="submit" class="btn btn-orange btn-sm">Nonaktifkan</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if($pwRecipe['product_mismatch'])
                        <div class="pw-alert pw-alert-warn pw-mt-sm">Recipe ini tercatat pada Product lain (data lama). Tidak dapat diubah dari Product Workspace dan tidak diperbaiki otomatis.</div>
                    @endif

                    @if(count($pwRecipe['items']))
                        <div class="pw-table-wrap pw-mt-sm">
                            <table class="pw-table pw-recipe-table">
                                <thead>
                                    <tr>
                                        <th class="text-left">Ingredient</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pwRecipe['items'] as $pwItem)
                                        <tr>
                                            <td class="text-left">
                                                @if($pwItem['links'])
                                                    <a href="{{ $pwItem['links']['legacy_edit_url'] }}"
                                                       class="pw-chip pw-chip-link {{ $pwItem['is_active'] ? '' : 'pw-chip-warn' }}"
                                                       data-pw-open-drawer="ingredient"
                                                       data-pw-form-url="{{ $pwItem['links']['form_url'] }}"
                                                       title="Kelola Ingredient">{{ $pwItem['name'] }}</a>
                                                @else
                                                    <span class="pw-chip {{ $pwItem['is_active'] ? '' : 'pw-chip-warn' }}">{{ $pwItem['name'] }}</span>
                                                @endif
                                                @unless($pwItem['is_active'])<div class="pw-cell-note pw-warn-text">Ingredient nonaktif.</div>@endunless
                                                @if(count($pwItem['missing_outlets']))<div class="pw-cell-note pw-warn-text">Belum tersedia di: {{ implode(', ', $pwItem['missing_outlets']) }}.</div>@endif
                                                @if($pwItem['duplicate'])<div class="pw-cell-note pw-warn-text">Ingredient muncul lebih dari sekali (data lama, tidak digabung otomatis).</div>@endif
                                            </td>
                                            <td class="pw-num">{{ \App\Support\QuantityFormatter::twoDecimals($pwItem['qty']) }}</td>
                                            <td>
                                                {{ $pwItem['unit'] ?? '-' }}
                                                @if($pwItem['unit_differs'])<div class="pw-cell-note pw-warn-text">Unit Ingredient: {{ $pwItem['ingredient_unit'] }} (tidak dikonversi)</div>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="pw-note pw-mt-sm">Belum ada bahan.</p>
                    @endif

                    @if($pwRecipeRow['editable'] && ! $pwRecipe['is_active'] && count($pwRecipeRow['active_recipe_ids']) && ! $pwRecipe['product_mismatch'])
                        <p class="pw-note">Tidak dapat diaktifkan selama Recipe #{{ $pwRecipeRow['active_recipe_ids'][0] }} aktif. Recipe aktif tidak dinonaktifkan otomatis.</p>
                    @endif
                </div>
            @endforeach

            @if($pwRecipeRow['status'] === 'none')
                <p class="pw-note pw-mt-0">Variant ini belum memiliki Recipe sehingga belum dapat dijual. Recipe tidak dibuat otomatis.</p>
                @if($pwRecipeRow['create_url'])
                    <div class="pw-row-actions">
                        <a href="{{ $pwRecipeRow['create_url'] }}"
                           class="btn btn-green btn-sm"
                           @if($pwRecipeRow['create_form_url'])
                               data-pw-open-drawer="recipe"
                               data-pw-form-url="{{ $pwRecipeRow['create_form_url'] }}"
                           @endif>+ Buat Recipe</a>
                    </div>
                @endif
            @endif

            @unless($pwRecipeRow['can_mutate'])
                <p class="pw-note">{{ $pwRecipeRow['mutation_message'] }}</p>
            @endunless
        </div>
    @empty
        <div class="pw-empty">Belum ada Variant, jadi belum ada Recipe yang bisa dibuat.</div>
    @endforelse
</div>
