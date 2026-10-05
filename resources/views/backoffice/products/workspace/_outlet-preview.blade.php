{{-- Consequence preview of an outlet change ($plan from ProductWriter::planOutletChange). --}}
@php
    $pwName = fn ($id) => $plan['outlet_names'][$id] ?? ('#'.$id);
@endphp
@if($emptySelection)
    <div class="pw-alert pw-alert-danger" data-pw-preview-state="empty">Pilih minimal 1 outlet untuk Product ini.</div>
@elseif(! $plan['added'] && ! $plan['removed'])
    <div class="pw-alert pw-alert-info" data-pw-preview-state="unchanged">Tidak ada perubahan outlet.</div>
@else
    <div class="pw-alert {{ $plan['has_consequences'] ? 'pw-alert-warn' : 'pw-alert-info' }}" data-pw-preview-state="{{ $plan['has_consequences'] ? 'consequences' : 'safe' }}">
        <strong>Jika disimpan:</strong>
        <ul>
            @foreach($plan['added'] as $pwId)
                <li>Product ditambahkan ke {{ $pwName($pwId) }}.</li>
            @endforeach
            @foreach($plan['removed'] as $pwId)
                <li>Product dihapus dari {{ $pwName($pwId) }}.</li>
            @endforeach
            @foreach($plan['variants'] as $pwVariant)
                <li data-pw-preview-variant="{{ $pwVariant['id'] }}">
                    Variant <strong>{{ $pwVariant['name'] }}</strong> kehilangan outlet {{ collect($pwVariant['removed_outlet_ids'])->map($pwName)->implode(', ') }}.
                    @if($pwVariant['will_deactivate'])
                        <strong>{{ $pwVariant['was_active'] ? 'Variant akan dinonaktifkan' : 'Variant tetap nonaktif' }}</strong> karena tidak lagi memiliki outlet.
                    @endif
                </li>
            @endforeach
        </ul>
        @if(count($plan['promos']))
            <div class="pw-mt-sm"><strong>Perhatian Promo</strong> (tidak diubah otomatis, periksa di halaman Promo):</div>
            <ul>
                @foreach($plan['promos'] as $pwPromo)
                    @php
                        $pwPromoLine = $pwPromo['name'].($pwPromo['live'] ? '' : ' (tidak aktif)')
                            .' memakai '.implode(', ', $pwPromo['variant_names'])
                            .(count($pwPromo['conflict_outlet_ids']) ? ' dan berlaku di '.collect($pwPromo['conflict_outlet_ids'])->map($pwName)->implode(', ') : '')
                            .'.';
                    @endphp
                    <li data-pw-preview-promo="{{ $pwPromo['id'] }}">{{ $pwPromoLine }}</li>
                @endforeach
            </ul>
        @endif
        @if(! count($plan['variants']) && ! count($plan['promos']))
            <div class="pw-mt-sm">Tidak ada Variant atau Promo yang terdampak.</div>
        @endif
    </div>
@endif
