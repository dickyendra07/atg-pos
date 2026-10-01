{{-- Hidden return_to for forms that should send the user back to the list they came from.
     Pass $returnTo to override (list pages pass their own URL: $listReturnTo). --}}
@php
    $boReturnTo = \App\Support\BackofficeReturnUrl::sanitize($returnTo ?? old('return_to', request('return_to')));
@endphp
@if($boReturnTo)
    <input type="hidden" name="return_to" value="{{ $boReturnTo }}">
@endif
