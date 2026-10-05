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
                <div class="pw-alert pw-alert-danger">Variant ini memiliki lebih dari satu Recipe aktif, sehingga Cashier menolak penjualannya. Sistem tidak memilih salah satu secara otomatis; nonaktifkan Recipe yang tidak dipakai di halaman Recipe.</div>
            @endif

            @if(count($pwRecipeRow['recipes']))
                <ul class="pw-list">
                    @foreach($pwRecipeRow['recipes'] as $pwRecipe)
                        <li>
                            <span>
                                {{ $pwRecipe['name'] }}
                                <span class="pw-muted">· {{ $pwRecipe['items_count'] }} bahan · {{ $pwRecipe['is_active'] ? 'Active' : 'Inactive' }}</span>
                            </span>
                            <a href="{{ $pwRecipe['edit_url'] }}" class="btn btn-blue btn-sm">{{ $pwRecipeRow['can_mutate'] ? 'Kelola Recipe' : 'Lihat Recipe' }}</a>
                        </li>
                    @endforeach
                </ul>
            @elseif($pwRecipeRow['create_url'])
                <div class="pw-row-actions"><a href="{{ $pwRecipeRow['create_url'] }}" class="btn btn-green btn-sm">Buat Recipe</a></div>
            @endif

            @unless($pwRecipeRow['can_mutate'])
                <p class="pw-note">{{ $pwRecipeRow['mutation_message'] }}</p>
            @endunless
        </div>
    @empty
        <div class="pw-empty">Belum ada Variant, jadi belum ada Recipe yang bisa dibuat.</div>
    @endforelse
</div>
