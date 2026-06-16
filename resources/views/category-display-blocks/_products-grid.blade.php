@php
    $decimalPointSettings = getWebConfig(name: 'decimal_point_settings');
    $themeKey = $themeKey ?? theme_root_path();
@endphp

@php
    $productCount = $products instanceof \Illuminate\Support\Collection
        ? $products->count()
        : (method_exists($products, 'count') ? $products->count() : 0);
@endphp

@if ($productCount > 0)
    @if ($themeKey === 'theme_aster')
        <div class="auto-col gap-3 mobile_two_items minWidth-12rem">
            @foreach ($products as $product)
                @include('theme-views.partials._product-small-card', ['product' => $product])
            @endforeach
        </div>
        @if ($products->hasPages())
            <div class="mt-3">{!! $products->withQueryString()->links() !!}</div>
        @endif
    @else
        <div class="row">
            @foreach ($products as $product)
                <div class="col-lg-3 col-md-4 col-sm-6 col-6 p-2 product-with-bg">
                    @include('web-views.partials._filter-single-product', [
                        'product' => $product,
                        'decimal_point_settings' => $decimalPointSettings,
                    ])
                </div>
            @endforeach
            @if ($products->hasPages())
                <div class="col-12">
                    <nav class="d-flex justify-content-center pt-3">{!! $products->withQueryString()->links() !!}</nav>
                </div>
            @endif
        </div>
    @endif
@else
    @include('category-display-blocks._empty-placeholder', [
        'message' => translate('no_product_found'),
        'icon' => 'product',
    ])
@endif
