{{-- Outlets: Product outlet assignment, saved on its own. The consequence preview below the checkboxes
     is computed by the server (ProductWriter::planOutletChange), the same code the save uses. --}}
@php
    $pwOutlets = $workspace['outlets'];
    $pwSelected = collect(old('outlet_ids', collect($pwOutlets['accessible'])->where('assigned', true)->pluck('id')->all()))
        ->map(fn ($id) => (int) $id)->all();
@endphp
<form method="POST"
      action="{{ route('backoffice.products.workspace.outlets', $product) }}"
      class="pw-card"
      data-pw-form="outlets"
      data-pw-preview-url="{{ route('backoffice.products.workspace.outlets.preview', $product) }}"
      novalidate>
    @csrf
    @method('PUT')
    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Outlets</h2>
            <p class="pw-card-sub">Outlet tempat Product tersedia. Outlet Variant harus berada di dalam outlet Product.</p>
        </div>
        <div class="pw-card-actions">
            <button type="submit" class="btn btn-green" data-pw-save>Simpan Outlets</button>
        </div>
    </div>

    <div class="pw-outlet-grid">
        @foreach($pwOutlets['accessible'] as $pwOutlet)
            <label class="pw-outlet-card">
                <input type="checkbox" name="outlet_ids[]" value="{{ $pwOutlet['id'] }}" data-pw-outlet-checkbox
                       @checked(in_array($pwOutlet['id'], $pwSelected, true))>
                <span>
                    <span class="pw-outlet-name">{{ $pwOutlet['name'] }}</span>
                    <span class="pw-outlet-desc">{{ $pwOutlet['assigned'] ? 'Saat ini tersedia' : 'Belum tersedia' }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <div class="pw-field-error" data-pw-error="outlet_ids" @unless($errors->has('outlet_ids')) hidden @endunless>{{ $errors->first('outlet_ids') }}</div>

    @if(count($pwOutlets['locked']))
        <div class="pw-alert pw-alert-info pw-mt">
            Juga tersedia di outlet di luar akses kamu (tetap dipertahankan saat disimpan):
            <div class="pw-chip-row pw-mt-sm">
                @foreach($pwOutlets['locked'] as $pwOutlet)
                    <span class="pw-chip pw-chip-locked">{{ $pwOutlet['name'] }}@unless($pwOutlet['is_active']) (nonaktif)@endunless</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pw-preview" data-pw-outlet-preview aria-live="polite"></div>

    @include('backoffice.products.workspace._variant-outlet-matrix')
</form>
