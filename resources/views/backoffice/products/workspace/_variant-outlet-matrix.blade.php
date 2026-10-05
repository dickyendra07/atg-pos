{{-- Which Product outlets each Variant is assigned to (read-only context for the Outlets section). --}}
@if(count($workspace['outlets']['variant_matrix']) && count($workspace['outlets']['assigned']))
    <h3 class="pw-subtitle">Outlet per Variant</h3>
    <div class="pw-table-wrap">
        <table class="pw-table">
            <thead>
                <tr>
                    <th class="text-left">Variant</th>
                    @foreach($workspace['outlets']['assigned'] as $pwOutlet)
                        <th>{{ $pwOutlet['name'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($workspace['outlets']['variant_matrix'] as $pwRow)
                    <tr>
                        <td class="text-left">
                            <strong>{{ $pwRow['name'] }}</strong>
                            @unless($pwRow['is_active'])<span class="status-badge status-inactive pw-badge-sm">Inactive</span>@endunless
                        </td>
                        @foreach($workspace['outlets']['assigned'] as $pwOutlet)
                            <td>
                                @if(in_array($pwOutlet['id'], $pwRow['outlet_ids'], true))
                                    <span class="pw-yes" aria-label="Tersedia">✓</span>
                                @else
                                    <span class="pw-no" aria-label="Tidak tersedia">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
