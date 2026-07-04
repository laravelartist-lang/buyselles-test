@php
    use App\Utils\BrandManager;
    use App\Utils\CategoryManager;
@endphp
@extends('theme-views.layouts.app')

@section('title', $pageTitleContent)

@push('css_or_js')
    <meta property="og:image" content="{{ $web_config['web_logo']['path'] }}" />
    <meta property="og:title" content="Products of {{ $web_config['company_name'] }} " />
    <meta property="og:url" content="{{ env('APP_URL') }}">
    <meta property="og:description" content="{{ $web_config['meta_description'] }}">
    <meta property="twitter:card" content="{{ $web_config['web_logo']['path'] }}" />
    <meta property="twitter:title" content="Products of {{ $web_config['company_name'] }}" />
    <meta property="twitter:url" content="{{ env('APP_URL') }}">
    <meta property="twitter:description" content="{{ $web_config['meta_description'] }}">
@endpush

@section('content')
    <main class="main-content d-flex flex-column gap-3 pt-3">
        <section>
            <div class="container">

                <form method="POST" action="{{ route('products') }}" class="product-list-filter">
                    @csrf
                    <input hidden name="offer_type" value="{{ $data['offer_type'] }}">
                    <input hidden name="data_from"
                        value="{{ request()->is('flash-deals*') ? 'flash-deals' : request('data_from') }}">

                    @include('theme-views.product.partials._product-list-header', [
                        'pageTitleContent' => $pageTitleContent,
                        'pageProductsCount' => $products->total(),
                        'searchBarSection' => true,
                        'sortBySection' => true,
                        'showProductsFilter' => true,
                    ])

                    <div class="flexible-grid lg-down-1 gap-3 width--16rem">
                        <div class="card filter-toggle-aside mb-5">
                            <div class="d-flex d-lg-none pb-0 p-3 justify-content-end">
                                <button class="filter-aside-close border-0 bg-transparent">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="card-body d-flex flex-column gap-4">
                                @include('theme-views.product.partials._filter-location')
                                @include('theme-views.product.partials._filter-product-type')
                                @include('theme-views.product.partials._filter-direct-topup')
                                @include('theme-views.product.partials._filter-product-price')
                                @include('theme-views.product.partials._filter-product-categories', [
                                    'productCategories' => $categories,
                                    'dataFrom' => 'flash-deals',
                                ])
                                @include('theme-views.product.partials._filter-product-brands', [
                                    'productBrands' => $activeBrands,
                                    'dataFrom' => 'flash-deals',
                                ])
                                @include('theme-views.product.partials._filter-publishing-houses', [
                                    'productPublishingHouses' => $web_config['publishing_houses'],
                                    'dataFrom' => 'flash-deals',
                                ])
                                @include('theme-views.product.partials._filter-product-authors', [
                                    'productAuthors' => $web_config['digital_product_authors'],
                                    'dataFrom' => 'flash-deals',
                                ])

                                @include('theme-views.product.partials._filter-product-reviews', [
                                    'productRatings' => $ratings,
                                    'selectedRatings' => $selectedRatings,
                                ])
                            </div>
                        </div>

                        <div class="">
                            @php
                                $hasSubCats = isset($subCategories) && $subCategories->isNotEmpty();
                                $showProducts = !$hasSubCats;
                            @endphp
                            @if ($hasSubCats)
                                @php
                                    $breadcrumbItems = [];
                                    $selCatId = $data['category_id'] ?? ($data['sub_category_id'] ?? null);
                                    if ($selCatId) {
                                        $currentCat = App\Models\Category::find($selCatId);
                                        if ($currentCat) {
                                            array_unshift($breadcrumbItems, $currentCat->name);
                                            if ($currentCat->parent_id) {
                                                $parentCat = App\Models\Category::find($currentCat->parent_id);
                                                if ($parentCat) {
                                                    array_unshift($breadcrumbItems, $parentCat->name);
                                                    if ($parentCat->parent_id) {
                                                        $grandParent = App\Models\Category::find($parentCat->parent_id);
                                                        if ($grandParent) {
                                                            array_unshift($breadcrumbItems, $grandParent->name);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                <div class="mt-3 mb-4">
                                    @if (!empty($breadcrumbItems))
                                        <p class="text-muted fs-12 mb-1">
                                            {{ translate('category') }}@foreach ($breadcrumbItems as $crumb) <span class="mx-1">&rarr;</span>{{ $crumb }}@endforeach
                                        </p>
                                    @endif
                                    <h5 class="fw-bold mb-3">{{ empty($breadcrumbItems) ? translate('Categories') : $pageTitleContent }}</h5>
                                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3">
                                        @foreach ($subCategories as $sub)
                                            <div class="col">
                                                <a href="{{ route('category-products', ['slug' => $sub->slug]) }}"
                                                    class="card text-center text-decoration-none border rounded-3 overflow-hidden h-100 sub-cat-card-aster"
                                                    style="transition: box-shadow .2s, transform .2s;">
                                                    <div style="aspect-ratio: 1/1; overflow: hidden;">
                                                        <img src="{{ getStorageImages(path: $sub->icon_full_url, type: 'category') }}"
                                                            alt="{{ $sub->name }}" class="w-100 h-100" style="object-fit: cover;">
                                                    </div>
                                                    <div class="p-2">
                                                        <span class="fs-13 text-dark fw-semibold">{{ $sub->name }}</span>
                                                    </div>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <style>
                                    .sub-cat-card-aster:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); transform: translateY(-2px); }
                                    .sub-cat-card-aster:hover span { color: var(--bs-primary) !important; }
                                </style>
                            @endif
                            @if ($showProducts)
                            <div
                                class="d-flex flex-wrap flex-lg-nowrap align-items-start justify-content-between gap-3 mb-2">

                                <div class="d-flex gap-10 align-items-center overflow-hidden product-list-selected-tags">
                                    @include('theme-views.product._selected_filter_tags')
                                </div>

                                <div class="d-flex align-items-center mb-3 mb-md-0 flex-wrap flex-md-nowrap gap-3">
                                    <ul class="product-view-option option-select-btn gap-3">
                                        <li>
                                            <label>
                                                <input type="radio" name="product_view" value="grid-view" hidden=""
                                                    {{ !session('product_view_style') || session('product_view_style') == 'grid-view' ? 'checked' : '' }}>
                                                <span class="py-2 d-flex align-items-center gap-2 text-capitalize">
                                                    <i class="bi bi-grid-fill"></i>
                                                    <span class="text-nowrap">{{ translate('grid_view') }}</span>
                                                </span>
                                            </label>
                                        </li>
                                        <li>
                                            <label>
                                                <input type="radio" name="product_view" value="list-view" hidden=""
                                                    {{ session('product_view_style') == 'list-view' ? 'checked' : '' }}>
                                                <span class="py-2 d-flex align-items-center gap-2 text-capitalize">
                                                    <i class="bi bi-list-ul"></i>
                                                    <span class="text-nowrap">{{ translate('list_view') }}</span>
                                                </span>
                                            </label>
                                        </li>
                                    </ul>
                                    <button class="toggle-filter square-btn btn btn-outline-primary rounded d-lg-none">
                                        <i class="bi bi-funnel"></i>
                                    </button>
                                </div>
                            </div>
                            @php $decimal_point_settings = getWebConfig(name: 'decimal_point_settings'); @endphp
                            <div id="ajax-products-view">
                                @include('theme-views.product._ajax-products', [
                                    'products' => $products,
                                    'decimal_point_settings' => $decimal_point_settings,
                                ])
                            </div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <span id="filter-url" data-url="{{ url('/') . '/products' }}"></span>
    <span id="product-view-style-url" data-url="{{ route('product_view_style') }}"></span>

@endsection

@push('script')
    <script src="{{ theme_asset(path: 'assets/js/product-view.js') }}"></script>
@endpush
