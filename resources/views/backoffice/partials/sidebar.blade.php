{{-- Rendered from App\Support\BackofficeNavigation (single menu definition). The group holding the
     current page is open server-side; closed groups are only hidden once JS is known to be running
     (html.bo-js), so without JS every link stays reachable. --}}
@php
    $boNavigation = \App\Support\BackofficeNavigation::resolve();
    $boDashboard = $boNavigation['dashboard'];
@endphp

<div class="sidebar-brand">
    <div class="sidebar-brand-logo">
        <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
    </div>
    <div>
        <div class="sidebar-brand-name">ATG POS</div>
        <div class="sidebar-brand-sub">Back Office Workspace</div>
    </div>
</div>

<nav class="sidebar-nav" aria-label="Back office navigation">
    <div class="sidebar-section">
        <div class="sidebar-menu">
            <a href="{{ route($boDashboard['route']) }}" class="sidebar-link {{ $boDashboard['active'] ? 'active' : '' }}" @if($boDashboard['active']) aria-current="page" @endif>
                <span class="sidebar-nav-icon {{ $boDashboard['tone'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $boDashboard['icon'] !!}</svg>
                </span>
                <span>{{ $boDashboard['label'] }}</span>
            </a>
        </div>
    </div>

    @foreach($boNavigation['groups'] as $boGroup)
        <div class="sidebar-section sidebar-group {{ $boGroup['open'] ? 'is-open' : '' }} {{ $boGroup['active'] ? 'has-active' : '' }}"
             data-sidebar-group
             data-sidebar-group-key="{{ $boGroup['key'] }}"
             @if($boGroup['active']) data-sidebar-group-active @endif>
            <button type="button"
                    class="sidebar-group-toggle"
                    id="sidebar-group-toggle-{{ $boGroup['key'] }}"
                    aria-expanded="{{ $boGroup['open'] ? 'true' : 'false' }}"
                    aria-controls="sidebar-group-{{ $boGroup['key'] }}"
                    data-sidebar-group-toggle>
                <span class="sidebar-title">{{ $boGroup['label'] }}</span>
                <svg class="sidebar-group-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>

            <div class="sidebar-menu sidebar-group-menu" id="sidebar-group-{{ $boGroup['key'] }}" role="group" aria-labelledby="sidebar-group-toggle-{{ $boGroup['key'] }}">
                @foreach($boGroup['items'] as $boItem)
                    <a href="{{ route($boItem['route']) }}" class="sidebar-link {{ $boItem['active'] ? 'active' : '' }}" @if($boItem['active']) aria-current="page" @endif>
                        <span class="sidebar-nav-icon {{ $boItem['tone'] }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $boItem['icon'] !!}</svg>
                        </span>
                        <span>{{ $boItem['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>

<div class="sidebar-footer">
    {{ $user->name ?? 'User' }} • {{ $user->role->name ?? '-' }}<br>
    {{ $activeOutletLabel ?? 'Back Office Access' }}
</div>
