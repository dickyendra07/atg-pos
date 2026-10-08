@extends('backoffice.layouts.app')

@php
    $pageTitle = $product->name.' - Product Workspace - Back Office ATG POS';
@endphp

@section('content')
    @include('backoffice.products.workspace._styles')

    <div class="pw"
         data-product-workspace
         data-pw-section="{{ $section }}"
         data-pw-product-id="{{ $product->id }}"
         @if(! empty($openRecipeDrawer)) data-pw-open-recipe-url="{{ $openRecipeDrawer['form_url'] }}" @endif
         @if(! empty($focusRecipeId)) data-pw-focus-recipe="{{ $focusRecipeId }}" @endif>

        <header class="pw-header" data-pw-header>
            @include('backoffice.products.workspace._header')
        </header>

        <div class="pw-body">
            <nav class="pw-nav" aria-label="Bagian Product Workspace">
                {{-- Only the visible sections get a tab; hidden ones (Recipe, Stock & Readiness, Promo) are still rendered below for deep links. --}}
                @foreach($navSections as $sectionKey => $sectionLabel)
                    <a href="{{ \App\Services\ProductWorkspace::url($product, $sectionKey, $workspaceReturnTo) }}"
                       class="pw-nav-link {{ $section === $sectionKey ? 'is-active' : '' }}"
                       data-pw-nav="{{ $sectionKey }}"
                       aria-controls="pw-section-{{ $sectionKey }}"
                       @if($section === $sectionKey) aria-current="page" @endif>
                        <span>{{ $sectionLabel }}</span>
                        <span class="pw-dirty-dot" data-pw-dirty-dot="{{ $sectionKey }}" hidden title="Belum disimpan" role="img" aria-label="Belum disimpan"></span>
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

    {{-- Variant / Ingredient drawers: their form is fetched from the server when opened. --}}
    <div class="pw-drawer-backdrop" data-pw-drawer-backdrop="variant" hidden></div>
    <aside class="pw-drawer" data-pw-drawer="variant" role="dialog" aria-modal="true" aria-labelledby="pw-variant-drawer-title" hidden>
        <div data-pw-drawer-content></div>
    </aside>

    {{-- Recipe drawer (per Variant). Ingredient drawers opened from it stack on top. --}}
    <div class="pw-drawer-backdrop" data-pw-drawer-backdrop="recipe" hidden></div>
    <aside class="pw-drawer pw-drawer-wide" data-pw-drawer="recipe" role="dialog" aria-modal="true" aria-labelledby="pw-recipe-drawer-title" hidden>
        <div data-pw-drawer-content></div>
    </aside>

    @if($workspace['links']['can_manage_ingredients'])
        <div class="pw-drawer-backdrop" data-pw-drawer-backdrop="ingredient" hidden></div>
        <aside class="pw-drawer" data-pw-drawer="ingredient" role="dialog" aria-modal="true" aria-labelledby="pw-ingredient-drawer-title" hidden>
            <div data-pw-drawer-content></div>
        </aside>

        @if($canCreateCategory)
            @include('backoffice.products.workspace._ingredient-category-drawer')
        @endif
    @endif

    @include('backoffice.products.workspace._script')
@endsection
