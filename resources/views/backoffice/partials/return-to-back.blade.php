{{-- "Back" link for a page opened from the Product Workspace (or any list) with ?return_to=. Renders nothing
     without a valid return_to. Pass $label to name where it goes. --}}
@php
    $boBackTo = \App\Support\BackofficeReturnUrl::sanitize($returnTo ?? request('return_to'));
@endphp
@if($boBackTo)
    <a href="{{ $boBackTo }}" class="btn btn-brand" data-return-to-back>{{ $label ?? 'Kembali' }}</a>
@endif
