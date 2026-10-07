{{--
    Temporary cleanup-delete trigger. Renders NOTHING unless the feature flag is on AND the user is
    owner / admin_pusat (the server enforces the same on every request; this only hides the button).

        @include('backoffice.partials.cleanup-button', [
            'type' => 'product|variant|ingredient|recipe', 'id' => 12, 'name' => 'Es Kopi',
            'return' => '/backoffice/products?...',   // where to land afterwards (local path)
            'label' => 'Hapus dari Sistem', 'class' => 'btn btn-red btn-sm', 'testid' => 'optional',
        ])
--}}
@if (\App\Services\CleanupDeletionService::available(auth()->user()))
    <button type="button"
            class="{{ $class ?? 'btn btn-small btn-small-red' }}"
            data-cleanup-delete
            data-cleanup-type="{{ $type }}"
            data-cleanup-id="{{ $id }}"
            data-cleanup-name="{{ $name }}"
            data-cleanup-return="{{ $return ?? '' }}"
            @isset($testid) data-testid="{{ $testid }}" @endisset>{{ $label ?? 'Hapus dari Sistem' }}</button>
@endif
