@extends('backoffice.layouts.app')

@php
    $pageTitle = $product->name.' - Product Workspace - Back Office ATG POS';
@endphp

@section('content')
    @include('backoffice.products.workspace._styles')

    <div class="pw"
         data-product-workspace
         data-pw-section="{{ $section }}"
         data-pw-product-id="{{ $product->id }}">

        <header class="pw-header" data-pw-header>
            @include('backoffice.products.workspace._header')
        </header>

        <div class="pw-body">
            <nav class="pw-nav" aria-label="Bagian Product Workspace">
                @foreach($sections as $sectionKey => $sectionLabel)
                    <a href="{{ \App\Services\ProductWorkspace::url($product, $sectionKey, $workspaceReturnTo) }}"
                       class="pw-nav-link {{ $section === $sectionKey ? 'is-active' : '' }}"
                       data-pw-nav="{{ $sectionKey }}"
                       aria-controls="pw-section-{{ $sectionKey }}"
                       @if($section === $sectionKey) aria-current="page" @endif>
                        <span>{{ $sectionLabel }}</span>
                        <span class="pw-dirty-dot" data-pw-dirty-dot="{{ $sectionKey }}" hidden title="Belum disimpan"></span>
                    </a>
                @endforeach
            </nav>

            <div class="pw-panels">
                @foreach($sections as $sectionKey => $sectionLabel)
                    <section class="pw-panel"
                             id="pw-section-{{ $sectionKey }}"
                             data-pw-panel="{{ $sectionKey }}"
                             aria-label="{{ $sectionLabel }}"
                             @if($section !== $sectionKey) hidden @endif>
                        @include('backoffice.products.workspace.'.$sectionKey)
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    @if($canCreateCategory)
        @include('backoffice.products.workspace._category-drawer')
    @endif

    @include('backoffice.products.workspace._script')
@endsection
