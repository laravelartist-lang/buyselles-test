@php
    use App\Models\Brand;
    use App\Models\Category;
    use App\Utils\Helpers;
@endphp
@if (isset($web_config['announcement']) && $web_config['announcement']['status'] == 1)
    <div class="offer-bar py-2 py-sm-3 announcement-color d--none">
        <div class="d-flex gap-2 align-items-center">
            <div class="offer-bar-close">
                <i class="bi bi-x-lg"></i>
            </div>
            <div class="top-offer-text flex-grow-1 d-flex justify-content-center fw-semibold ">
                {{ $web_config['announcement']['announcement'] }}
            </div>
        </div>
    </div>
@endif

@php($categories = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 11))
@php($brands = \App\Utils\BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting())
<header class="header">
    <div class="header-top py-2">
        <div class="container">
            <div class="d-flex align-items-center flex-wrap justify-content-between gap-2">
                <a href="tel:+{{ $web_config['phone'] }}" class="d-flex gap-2 align-items-center direction-ltr">
                    <i class="bi bi-telephone text-primary"></i>
                    {{ $web_config['phone'] }}
                </a>

                <ul class="nav justify-content-center justify-content-sm-end align-items-center gap-4">
                    @php
                        $asterCurrentLang = session('local') ?? Helpers::default_lang();
                        $asterCurrentCurr = session('currency_code') ?? 'USD';
                        $asterLangLabel = strtoupper($asterCurrentLang);
                        $asterCurrLabel = $asterCurrentCurr;
                        $asterLangList = $web_config['language'] ?? [];
                        $asterCurrList = $web_config['currencies'] ?? \App\Models\Currency::where('status', 1)->get();
                    @endphp
                    <li>
                        <style>
                            .locale-pill {
                                display: inline-flex;
                                align-items: center;
                                gap: 3px;
                                padding: 5px 14px;
                                background: var(--bs-primary, #1B7FED);
                                color: white;
                                border: none;
                                border-radius: 22px;
                                cursor: pointer;
                                font-size: 12px;
                                font-weight: 500;
                                letter-spacing: 0.3px;
                                box-shadow: 0 2px 8px rgba(27, 127, 237, 0.3);
                                transition: all 0.25s;
                                white-space: nowrap;
                            }
                            .locale-pill:hover { opacity: 0.85; }
                            .locale-pill-lang { font-weight: 600; }
                            .locale-pill-sep { opacity: 0.5; }
                            .locale-pill-curr { opacity: 0.85; }
                            .locale-pill::after { display: none; }
                        </style>
                        <div class="dropdown locale-dropdown">
                            <button class="locale-pill" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <span class="locale-pill-lang">{{ $asterLangLabel }}</span>
                                <span class="locale-pill-sep">·</span>
                                <span class="locale-pill-curr">{{ $asterCurrLabel }}</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3 bs-dropdown-min-width--22-5rem">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold fs-12">{{ translate('Language') }}</label>
                                    <select class="form-select form-select-sm" id="localeLanguageSelect">
                                        @foreach ($asterLangList as $lang)
                                            @if (!empty($lang['status']) && $lang['status'] == 1)
                                                <option value="{{ $lang['code'] }}" {{ $asterCurrentLang === $lang['code'] ? 'selected' : '' }}>
                                                    {{ $lang['name'] }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold fs-12">{{ translate('Currency') }}</label>
                                    <select class="form-select form-select-sm" id="localeCurrencySelect">
                                        @foreach ($asterCurrList as $cur)
                                            <option value="{{ $cur['code'] }}" {{ $asterCurrentCurr === $cur['code'] ? 'selected' : '' }}>
                                                {{ $cur['name'] ?? $cur['code'] }} ({{ $cur['symbol'] ?? $cur['code'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary flex-grow-1 locale-dropdown-cancel">{{ translate('Cancel') }}</button>
                                    <button type="button" class="btn btn-sm btn-primary flex-grow-1" id="localeSaveBtn">{{ translate('Save') }}</button>
                                </div>
                            </div>
                        </div>
                    </li>
                    @php(
    $headerLocationCountries = \App\Models\LocationCountry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
)
                    @if ($headerLocationCountries->count() > 0)
                        <li>
                            <div class="language-dropdown">
                                <button type="button"
                                    class="border-0 bg-transparent d-flex gap-2 align-items-center dropdown-toggle text-dark p-0"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-geo-alt"></i>
                                    {{ session('location_label') ?: translate('select_location') }}
                                </button>
                                <div class="dropdown-menu p-3" style="min-width: 240px;"
                                    id="header-location-dropdown-aster">
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="header-location-country">
                                            <option value="">{{ translate('country') }}</option>
                                            @foreach ($headerLocationCountries as $hCountry)
                                                <option value="{{ $hCountry->id }}"
                                                    {{ session('location_country_id') == $hCountry->id ? 'selected' : '' }}>
                                                    {{ $hCountry->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="header-location-city"
                                            {{ session('location_country_id') ? '' : 'disabled' }}>
                                            <option value="">{{ translate('city') }}</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm" id="header-location-area"
                                            {{ session('location_city_id') ? '' : 'disabled' }}>
                                            <option value="">{{ translate('area') }}</option>
                                        </select>
                                    </div>
                                    @if (session('location_area_id'))
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger w-100 header-clear-location">
                                            {{ translate('clear_location') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endif
                    @if ($web_config['business_mode'] == 'multi' && $web_config['seller_registration'])
                        <li class="d-none d-xl-block">
                            <a href="{{ route('vendor.auth.registration.index') }}" class="d-flex">
                                <div class="fz-16 text-capitalize">{{ translate('become_a_vendor') }}</div>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
    <div class="header-middle border-bottom py-2 d-none d-xl-block">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <a class="logo" href="{{ route('home') }}">
                    <img class="dark-support svg h-45" alt="{{ translate('Logo') }}"
                        src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}">
                </a>
                <div class="search-box position-relative">
                    <form action="{{ route('products') }}" type="submit">
                        <div class="d-flex">
                            <div class="select-wrap focus-border border border-end-logical-0 d-flex align-items-center">
                                <div class="border-end">
                                    <div class="dropdown search_dropdown">
                                        <button type="button"
                                            class="border-0 px-3 bg-transparent dropdown-toggle text-dark py-0 text-capitalize header-search-dropdown-button"
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            data-default="{{ translate('all_categories') }}">
                                            @if ($categories && request('category_ids') && !empty(request('category_ids')))
                                                @foreach ($categories as $category)
                                                    @if (in_array($category->id, request('category_ids') ?? []))
                                                        {{ $category['name'] }}
                                                    @endif
                                                @endforeach
                                            @else
                                                {{ translate('all_categories') }}
                                            @endif
                                        </button>
                                        <input type="hidden" name="category_ids[]" id="search_category_value"
                                            @if ($categories && request('category_ids') && !empty(request('category_ids'))) @foreach ($categories as $category)
                                                       @if (in_array($category->id, request('category_ids') ?? []))
                                                           value="{{ $category->id }}" @endif
                                            @endforeach
                                    @else
                                        value="{{ 'all' }}"
                                        @endif
                                        >
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="d-flex text-capitalize" data-value="all" href="javascript:">
                                                    {{ translate('all_categories') }}
                                                </a>
                                            </li>
                                            @if ($categories)
                                                @foreach ($categories as $category)
                                                    <li>
                                                        <a class="d-flex" data-value="{{ $category->id }}"
                                                            href="javascript:">
                                                            {{ $category['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            @endif
                                        </ul>
                                    </div>
                                </div>

                                <input type="search" class="form-control border-0 focus-input search-bar-input"
                                    name="product_name" id="global-search" value="{{ request('product_name') }}"
                                    placeholder="{{ translate('search_for_items') . '...' }}" />
                            </div>
                            <input name="data_from" value="search" hidden>
                            <input type="hidden" name="global_search_input" value="1">
                            <input name="page" value="1" hidden>
                            <button type="submit" class="btn btn-primary" aria-label="{{ translate('Search') }}">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    <div
                        class="card search-card __inline-13 position-absolute z-99 w-100 bg-white top-100 start-0 search-result-box">
                    </div>
                </div>
                <div class="offer-btn">
                    @if ($web_config['header_banner'])
                        <a href="{{ $web_config['header_banner']['url'] }}">
                            <img width="180" loading="lazy"
                                class="dark-support h-70 img-fit max-height-60px max-w-280px"
                                alt="{{ translate('image') }}"
                                src="{{ getStorageImages(path: $web_config['header_banner']['photo_full_url'], type: 'wide-banner') }}">
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="header-main love-sticky py-2 py-lg-3 py-xl-0 shadow-sm">
        <div class="container">
            <aside class="aside d-flex flex-column d-xl-none">
                <div class="aside-close p-3 pb-2">
                    <i class="bi bi-x-lg"></i>
                </div>
                <div>
                    <div class="aside-body" data-trigger="scrollbar">
                        <form action="{{ route('products') }}" class="mb-3">
                            <div class="search-bar">
                                <input type="search" name="name" class="form-control search-bar-input-mobile"
                                    autocomplete="off" placeholder="{{ translate('search_for_items') . '...' }}">
                                <input name="data_from" value="search" hidden="">
                                <input name="page" value="1" hidden="">
                                <button type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div
                                class="card search-card __inline-13 position-absolute z-99 w-100 bg-white start-0 search-result-box d--none">
                            </div>
                        </form>
                        <ul class="main-nav nav">
                            <li>
                                <a href="{{ route('home') }}">{{ translate('home') }}</a>
                            </li>
                            <li>
                                <a href="{{ route('categories') }}">{{ translate('categories') }}</a>
                                <ul class="sub_menu">
                                    @php($categoryIndex = 0)
                                    @foreach ($categories as $category)
                                        @php($categoryIndex++)
                                        @if ($categoryIndex < 10)
                                            <li>
                                                <a href="javascript:">
                                                    <span class="get-view-by-onclick"
                                                        data-link="{{ route('category-products', ['slug' => $category['slug']]) }}">
                                                        {{ $category['name'] }}
                                                    </span>
                                                </a>
                                                @if ($category->childes->count() > 0)
                                                    <ul class="sub_menu">
                                                        @foreach ($category['childes'] as $subCategory)
                                                            <li>
                                                                <a href="javascript:">
                                                                    <span class="get-view-by-onclick"
                                                                        data-link="{{ route('category-products', ['slug' => $subCategory['slug']]) }}">{{ $subCategory['name'] }}</span>
                                                                </a>
                                                                @if ($subCategory->childes->count() > 0)
                                                                    <ul class="sub_menu">
                                                                        @foreach ($subCategory['childes'] as $subSubCategory)
                                                                            <li>
                                                                                <a
                                                                                    href="{{ route('category-products', ['slug' => $subSubCategory['slug']]) }}">
                                                                                    {{ $subSubCategory['name'] }}
                                                                                </a>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endif
                                    @endforeach
                                    <li>
                                        <a href="{{ route('products') }}" class="btn-link text-primary">
                                            {{ translate('view_all') }}
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            @if (getFeaturedDealsProductList()->count() > 0 ||
                                    ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) ||
                                    $web_config['discount_product'] > 0 ||
                                    $web_config['clearance_sale_product_count'] > 0)
                                <li>
                                    <a href="javascript:">{{ translate('offers') }}</a>
                                    <ul class="sub_menu">
                                        @if (getFeaturedDealsProductList()->count() > 0)
                                            <li>
                                                <a href="{{ route('featured-deal-products') }}">
                                                    {{ translate('featured_Deal') }}
                                                </a>
                                            </li>
                                        @endif

                                        @if ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0)
                                            <li>
                                                <a
                                                    href="{{ route('flash-deals', ['id' => $web_config['flash_deals']['id'] ?? 0]) }}">{{ translate('flash_deal') }}</a>
                                            </li>
                                        @endif
                                        @if ($web_config['discount_product'] > 0)
                                            <li>
                                                <a class="d-flex gap-2 align-items-center"
                                                    href="{{ route('discounted-products') }}">
                                                    <span>{{ translate('discounted_products') }}</span>
                                                    <span><i class="bi bi-patch-check-fill text-warning"></i></span>
                                                </a>
                                            </li>
                                        @endif
                                        @if ($web_config['clearance_sale_product_count'] > 0)
                                            <li>
                                                <a class="gap-2 align-items-center"
                                                    href="{{ route('clearance-sale-products') }}">
                                                    <span>{{ translate('clearance_sale') }}</span>
                                                    <span><i class="bi bi-patch-check-fill text-warning"></i></span>
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                </li>
                            @endif
                            @if ($web_config['business_mode'] == 'multi')
                                <li>
                                    <a href="javascript:">{{ translate('stores') }}</a>
                                    <ul class="sub_menu">
                                        <li>
                                            <a
                                                href="{{ route('vendor-shop', ['slug' => getInHouseShopConfig(key: 'slug')]) }}">
                                                {{ Str::limit(getInHouseShopConfig(key: 'name'), 14) }}
                                            </a>
                                        </li>
                                        @foreach ($web_config['shops'] as $shop)
                                            <li>
                                                <a
                                                    href="{{ route('vendor-shop', ['slug' => $shop['slug']]) }}">{{ Str::limit($shop->name, 14) }}</a>
                                            </li>
                                        @endforeach
                                        <li>
                                            <a href="{{ route('vendors') }}" class="btn-link text-primary">
                                                {{ translate('view_all') }}
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            @endif
                            @if ($web_config['brand_setting'])
                                <li>
                                    <a href="javascript:">{{ translate('brands') }}</a>
                                    <ul class="sub_menu">
                                        @php($brandIndex = 0)
                                        @foreach ($brands as $brand)
                                            @php($brandIndex++)
                                            @if ($brandIndex < 10)
                                                <li>
                                                    <a
                                                        href="{{ route('brand-products', ['slug' => $brand['slug']]) }}">
                                                        {{ $brand->name }}
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                        <li>
                                            <a href="{{ route('brands') }}" class="btn-link text-primary">
                                                {{ translate('view_all') }}
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            @endif

                            @if ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) == 1)
                                @php($firstPublisherID = is_array($web_config['publishing_houses']) && isset($web_config['publishing_houses']['id']) ? $web_config['publishing_houses']['id'] : $web_config['publishing_houses']?->first()?->id)
                                <li>
                                    <a class="d-flex gap-2 align-items-center text-capitalize"
                                        href="{{ route('products', ['publishing_house_id' => $firstPublisherID, 'product_type' => 'digital', 'page' => 1]) }}">
                                        {{ translate('Publication_House') }}
                                    </a>
                                </li>
                            @elseif ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) > 1)
                                <li>
                                    <a class="d-flex gap-2 align-items-center text-capitalize"
                                        href="{{ route('products', ['product_type' => 'digital', 'page' => 1]) }}">
                                        {{ translate('Publication_House') }}
                                    </a>
                                </li>
                            @endif
                            @if ($web_config['business_mode'] == 'multi' && $web_config['seller_registration'])
                                <li class="d-xl-none">
                                    <a href="{{ route('vendor.auth.registration.index') }}"
                                        class="d-flex text-capitalize">
                                        <div class="fz-16 text-capitalize">{{ translate('become_a_vendor') }}</div>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>

                    <div class="d-flex align-items-center gap-2 justify-content-between p-4">
                        <span class="text-dark">{{ translate('theme_mode') }}</span>
                        <div class="theme-bar p-1">
                            <button class="light_button active">
                                <img src="{{ theme_asset('assets/img/svg/light.svg') }}"
                                    alt="{{ translate('image') }}" class="svg">
                            </button>
                            <button class="dark_button">
                                <img src="{{ theme_asset('assets/img/svg/dark.svg') }}"
                                    alt="{{ translate('image') }}" class="svg">
                            </button>
                        </div>
                    </div>
                </div>

                @if (auth('customer')->check())
                    <div class="d-flex justify-content-center mb-5 pb-5 mt-auto px-4">
                        <a href="{{ route('customer.auth.logout') }}"
                            class="btn btn-primary w-100">{{ translate('logout') }}</a>
                    </div>
                @else
                    <div class="d-flex justify-content-center mb-5 pb-5 mt-auto px-4">
                        <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#loginModal"
                            class="btn btn-primary w-100"
                            aria-label="{{ translate('login') . '/' . translate('register') }}">
                            {{ translate('login') . '/' . translate('register') }}
                        </a>
                    </div>
                @endif
            </aside>
            <div class="aside-overlay"></div>

            <div class="d-flex justify-content-between gap-3 align-items-center position-relative">
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown d-none d-xl-block">
                        <button
                            class="btn btn-primary rounded-0 text-uppercase fw-bold fs-14 dropdown-toggle select-category-button text-capitalize"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-list fs-4"></i>
                            {{ translate('select_category') }}
                        </button>
                        <ul class="dropdown-menu dropdown--menu">
                            @php($categoryDropdownKeyIndex = 0)
                            @foreach ($categories as $category)
                                @if ($categoryDropdownKeyIndex < 11)
                                    @php($categoryDropdownKeyIndex++)
                                    <li class="{{ $category->childes->count() > 0 ? 'menu-item-has-children' : '' }}">
                                        <a href="{{ route('category-products', ['slug' => $category['slug']]) }}">
                                            {{ $category['name'] }}
                                        </a>
                                        @if ($category->childes->count() > 0)
                                            <ul class="sub-menu">
                                                @foreach ($category['childes'] as $subCategory)
                                                    <li
                                                        class="{{ $subCategory->childes->count() > 0 ? 'menu-item-has-children' : '' }}">
                                                        <a
                                                            href="{{ route('category-products', ['slug' => $subCategory['slug']]) }}">
                                                            {{ $subCategory['name'] }}
                                                        </a>
                                                        @if ($subCategory->childes->count() > 0)
                                                            <ul class="sub-menu">
                                                                @foreach ($subCategory['childes'] as $subSubCategory)
                                                                    <li>
                                                                        <a
                                                                            href="{{ route('category-products', ['slug' => $subSubCategory['slug']]) }}">
                                                                            {{ $subSubCategory['name'] }}
                                                                        </a>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endif
                            @endforeach
                            <li>
                                <a href="{{ route('products') }}" class="btn-link text-primary">
                                    {{ translate('view_all') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="nav-wrapper">
                        <div class="d-xl-none">
                            <a class="logo" href="{{ route('home') }}">
                                <img class="dark-support mobile-logo-cs" alt="{{ translate('logo') }}"
                                    src="{{ getStorageImages(path: $web_config['mob_logo'], type: 'logo') }}">
                            </a>
                        </div>
                        <ul class="nav main-menu align-items-center d-none d-xl-flex flex-nowrap">
                            <li class="{{ request()->is('/') ? 'active' : '' }}">
                                <a href="{{ route('home') }}">{{ translate('home') }}</a>
                            </li>
                            @if (getFeaturedDealsProductList()->count() > 0 ||
                                    ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) ||
                                    $web_config['discount_product'] > 0)
                                <li>
                                    <span class="cursor-pointer no-follow-link"
                                        ref="nofollow">{{ translate('offers') }}</span>
                                    <ul class="sub-menu">
                                        @if (getFeaturedDealsProductList()->count() > 0)
                                            <li>
                                                <a class="text-capitalize"
                                                    href="{{ route('featured-deal-products') }}">
                                                    {{ translate('featured_deal') }}
                                                </a>
                                            </li>
                                        @endif

                                        @if ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0)
                                            <li>
                                                <a class="text-capitalize"
                                                    href="{{ route('flash-deals', ['id' => $web_config['flash_deals']['id'] ?? 0]) }}">{{ translate('flash_deal') }}</a>
                                            </li>
                                        @endif
                                        @if ($web_config['discount_product'] > 0)
                                            <li>
                                                <a class="gap-2 align-items-center text-capitalize"
                                                    href="{{ route('discounted-products') }}">
                                                    <span>{{ translate('discounted_products') }}</span>
                                                    <span><i class="bi bi-patch-check-fill text-warning"></i></span>
                                                </a>
                                            </li>
                                        @endif

                                        @if ($web_config['clearance_sale_product_count'] > 0)
                                            <li>
                                                <a class="gap-2 align-items-center"
                                                    href="{{ route('clearance-sale-products') }}">
                                                    <span>{{ translate('clearance_sale') }}</span>
                                                    <span><i class="bi bi-patch-check-fill text-warning"></i></span>
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                </li>
                            @endif

                            @if ($web_config['business_mode'] == 'multi')
                                <li>
                                    <span class="cursor-pointer no-follow-link"
                                        ref="nofollow">{{ translate('stores') }}</span>
                                    <div class="sub-menu megamenu p-3 bs-dropdown-min-width--max-content">
                                        <div class="d-flex gap-1">
                                            <div>
                                                <div class="column-2 row-gap-3">
                                                    <a href="{{ route('vendor-shop', ['slug' => getInHouseShopConfig(key: 'slug')]) }}"
                                                        class="media gap-3 align-items-center border-bottom">
                                                        <div class="avatar rounded size-2-5rem">
                                                            <img loading="lazy" alt="{{ translate('image') }}"
                                                                src="{{ getStorageImages(path: getInHouseShopConfig(key: 'image_full_url'), type: 'shop') }}"
                                                                class="img-fit rounded dark-support overflow-hidden">
                                                        </div>
                                                        <div class="media-body text-truncate width--7rem"
                                                            title="{{ getInHouseShopConfig(key: 'name') }}">
                                                            {{ Str::limit(getInHouseShopConfig(key: 'name'), 14) }}
                                                        </div>
                                                    </a>
                                                    @foreach ($web_config['shops'] as $shop)
                                                        <a href="{{ route('vendor-shop', ['slug' => $shop['slug']]) }}"
                                                            class="media gap-3 align-items-center border-bottom">
                                                            <div class="avatar rounded size-2-5rem">
                                                                <img loading="lazy" alt="{{ translate('image') }}"
                                                                    src="{{ getStorageImages(path: $shop->image_full_url, type: 'shop') }}"
                                                                    class="img-fit rounded dark-support overflow-hidden">
                                                            </div>
                                                            <div class="media-body text-truncate width--7rem"
                                                                title="{{ $shop->name }}">
                                                                {{ Str::limit($shop->name, 14) }}
                                                            </div>
                                                        </a>
                                                    @endforeach
                                                </div>
                                                <div class="d-flex">
                                                    <a href="{{ route('vendors') }}"
                                                        class="fw-bold text-primary d-flex justify-content-center">
                                                        {{ translate('view_all') . '...' }}
                                                    </a>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="javascript:">
                                                    <img width="277"
                                                        src="{{ theme_asset('assets/img/media/super-market.webp') }}"
                                                        class="dark-support" alt="{{ translate('image') }}" />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif

                            @if ($web_config['brand_setting'])
                                <li>
                                    <span class="cursor-pointer no-follow-link"
                                        ref="nofollow">{{ translate('brands') }}</span>
                                    <div class="sub-menu megamenu p-3 bs-dropdown-min-width--max-content">
                                        <div class="d-flex gap-4">
                                            <div class="column-2">
                                                @php($brandSecondIndex = 0)
                                                @foreach ($brands as $brand)
                                                    @php($brandSecondIndex++)
                                                    @if ($brandSecondIndex < 10)
                                                        <a href="{{ route('brand-products', ['slug' => $brand['slug']]) }}"
                                                            class="media gap-3 align-items-center border-bottom">
                                                            <div class="avatar rounded-circle size-1-25rem">
                                                                <img class="img-fit rounded-circle dark-support"
                                                                    src="{{ getStorageImages(path: $brand->image_full_url, type: 'brand') }}"
                                                                    loading="lazy"
                                                                    alt="{{ $brand->image_alt_text }}" />
                                                            </div>
                                                            <div class="media-body text-truncate width--7rem">
                                                                {{ $brand->name }}
                                                            </div>
                                                        </a>
                                                    @endif
                                                @endforeach
                                                <div class="d-flex">
                                                    <a href="{{ route('brands') }}"
                                                        class="fw-bold text-primary d-flex justify-content-center">{{ translate('view_all') . '...' }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif

                            @if ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) == 1)
                                <li>
                                    <a
                                        href="{{ route('products', ['publishing_house_id' => 0, 'product_type' => 'digital', 'page' => 1]) }}">
                                        {{ translate('Publication_House') }}
                                    </a>
                                </li>
                            @elseif ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) > 1)
                                <li>
                                    <a class="cursor-pointer"
                                        href="{{ route('products', ['product_type' => 'digital', 'page' => 1]) }}">
                                        {{ translate('Publication_House') }}
                                    </a>
                                    <div class="sub-menu megamenu p-3 bs-dropdown-min-width--max-content">
                                        <div class="d-flex gap-4">
                                            <div class="column-2">
                                                @php($publishingHousesIndex = 0)
                                                @foreach ($web_config['publishing_houses'] as $publishingHouseItem)
                                                    @if ($publishingHousesIndex < 10 && $publishingHouseItem['name'] != 'Unknown')
                                                        @php($publishingHousesIndex++)
                                                        <a href="{{ route('products', ['publishing_house_id' => $publishingHouseItem['id'], 'product_type' => 'digital', 'page' => 1]) }}"
                                                            class="media gap-3 align-items-center border-bottom">
                                                            <div class="media-body text-truncate width--7rem">
                                                                {{ $publishingHouseItem['name'] }}
                                                            </div>
                                                        </a>
                                                    @endif
                                                @endforeach
                                                <div class="d-flex">
                                                    <a href="{{ route('products', ['product_type' => 'digital', 'page' => 1]) }}"
                                                        class="fw-bold text-primary d-flex justify-content-center">
                                                        {{ translate('view_all') . '...' }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
                <ul class="list-unstyled list-separator mb-0 pe-2">
                    @if (auth('customer')->check())
                        <li class="login-register d-flex align-items-center gap-4">
                            <div class="menu-btn d-xl-none search">
                                <i class="bi bi-search fs-18"></i>
                            </div>
                            <div class="profile-dropdown">
                                <button type="button"
                                    class="border-0 bg-transparent d-flex gap-2 align-items-center text-dark p-0 user"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <span
                                        class="avatar overflow-hidden header-avatar rounded-circle size-1-5rem border border-primary d-flex">
                                        @php($profileImg = getCustomerFromQuery() ? getCustomerFromQuery()->image_full_url : '')
                                        <img loading="lazy" class="img-fit" alt="{{ translate('image') }}"
                                            src="{{ getStorageImages(path: $profileImg, type: 'avatar') }}">
                                    </span>
                                </button>
                                <ul class="dropdown-menu bs-dropdown-min-width--10rem header-dropdown">
                                    <li><a href="{{ route('account-oder') }}">{{ translate('My_Order') }}</a></li>
                                    <li><a href="{{ route('user-profile') }}">{{ translate('My_Profile') }}</a>
                                    </li>
                                    <li><a href="{{ route('customer.auth.logout') }}">{{ translate('Logout') }}</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="menu-btn d-xl-none">
                                <i class="bi bi-list fs-30"></i>
                            </div>
                        </li>
                    @else
                        <li class="login-register d-flex align-items-center gap-4">
                            <div class="menu-btn d-xl-none search">
                                <i class="bi bi-search fs-18"></i>
                            </div>
                            <button type="button"
                                class="media gap-2 align-items-center text-uppercase fs-12 bg-transparent border-0 p-0"
                                data-bs-toggle="modal" data-bs-target="#loginModal">
                                <span class="avatar header-avatar rounded-circle d-xl-none size-1-5rem">
                                    <img loading="lazy" src="{{ theme_asset('assets/img/user.png') }}"
                                        class="img-fit rounded-circle" alt="{{ translate('image') }}" />
                                </span>
                                <span
                                    class="media-body d-none d-xl-block hover-primary">{{ translate('login') . '/' . translate('register') }}</span>
                            </button>
                            <div class="menu-btn d-xl-none">
                                <i class="bi bi-list fs-30"></i>
                            </div>
                        </li>
                    @endif
                    <li class="d-none d-xl-block">
                        @if (auth('customer')->check())
                            <a href="{{ route('product-compare.index') }}" class="position-relative">
                                <i class="bi bi-repeat fs-18"></i>
                                <span
                                    class="count compare_list_count_status">{{ session()->has('compare_list') ? count(session('compare_list')) : 0 }}</span>
                            </a>
                        @else
                            <a href="javascript:" class="position-relative" data-bs-toggle="modal"
                                data-bs-target="#loginModal">
                                <i class="bi bi-repeat fs-18"></i>
                            </a>
                        @endif
                    </li>
                    <li class="d-none d-xl-block">
                        @if (auth('customer')->check())
                            <a href="{{ route('wishlists') }}" class="position-relative">
                                <i class="bi bi-heart fs-18"></i>
                                <span
                                    class="count wishlist_count_status">{{ session()->has('wish_list') ? count(session('wish_list')) : 0 }}</span>
                            </a>
                        @else
                            <a href="javascript:" class="position-relative" data-bs-toggle="modal"
                                data-bs-target="#loginModal">
                                <i class="bi bi-heart fs-18"></i>
                            </a>
                        @endif
                    </li>
                    <li class="d-none d-xl-block" id="cart_items">
                        @include('theme-views.layouts.partials._cart')
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

@push('script')
    <script>
        "use strict";
        (function() {
            const hCountry = document.getElementById('header-location-country');
            const hCity = document.getElementById('header-location-city');
            const hArea = document.getElementById('header-location-area');
            if (!hCountry) return;

            const dropdown = document.getElementById('header-location-dropdown-aster');
            if (dropdown) {
                dropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            if (hCountry.value) {
                headerLoadCities(hCountry.value, '{{ session('location_city_id') }}');
            }

            hCountry.addEventListener('change', function() {
                hCity.innerHTML = '<option value="">{{ translate('city') }}</option>';
                hArea.innerHTML = '<option value="">{{ translate('area') }}</option>';
                hCity.disabled = true;
                hArea.disabled = true;
                if (this.value) headerLoadCities(this.value);
            });

            hCity.addEventListener('change', function() {
                hArea.innerHTML = '<option value="">{{ translate('area') }}</option>';
                hArea.disabled = true;
                if (this.value) headerLoadAreas(this.value);
            });

            hArea.addEventListener('change', function() {
                if (this.value) headerApplyLocation(this.value);
            });

            function headerLoadCities(countryId, selectedId) {
                fetch('{{ route('get-location-cities', ':id') }}'.replace(':id', countryId))
                    .then(r => r.json())
                    .then(cities => {
                        hCity.innerHTML = '<option value="">{{ translate('city') }}</option>';
                        cities.forEach(c => {
                            const sel = selectedId && c.id == selectedId ? 'selected' : '';
                            hCity.innerHTML += '<option value="' + c.id + '" ' + sel + '>' + c.name +
                                '</option>';
                        });
                        hCity.disabled = false;
                        if (selectedId && hCity.value) headerLoadAreas(selectedId,
                            '{{ session('location_area_id') }}');
                    });
            }

            function headerLoadAreas(cityId, selectedId) {
                fetch('{{ route('get-location-areas', ':id') }}'.replace(':id', cityId))
                    .then(r => r.json())
                    .then(areas => {
                        hArea.innerHTML = '<option value="">{{ translate('area') }}</option>';
                        areas.forEach(a => {
                            const sel = selectedId && a.id == selectedId ? 'selected' : '';
                            hArea.innerHTML += '<option value="' + a.id + '" ' + sel + '>' + a.name +
                                '</option>';
                        });
                        hArea.disabled = false;
                    });
            }

            function headerApplyLocation(areaId) {
                fetch('{{ route('set-location') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        area_id: areaId
                    })
                }).then(r => r.json()).then(() => window.location.reload());
            }

            const clearBtn = document.querySelector('.header-clear-location');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    fetch('{{ route('set-location') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            area_id: null
                        })
                    }).then(r => r.json()).then(() => window.location.reload());
                });
            }
        })();

        // Locale dropdown
        (function() {
            var saveBtn = document.getElementById('localeSaveBtn');
            if (!saveBtn) return;
            saveBtn.addEventListener('click', function () {
                var lang = document.getElementById('localeLanguageSelect').value;
                var curr = document.getElementById('localeCurrencySelect').value;
                var x = new XMLHttpRequest();
                x.open('POST', '{{ route('locale.switch') }}');
                x.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="_token"]').getAttribute('content'));
                x.setRequestHeader('Content-Type', 'application/json');
                x.onload = function () { if (x.status === 200) location.reload(); };
                x.send(JSON.stringify({ language_code: lang, currency_code: curr }));
            });
            var drop = document.querySelector('.locale-dropdown');
            if (!drop) return;
            var dd = bootstrap.Dropdown.getOrCreateInstance(drop.querySelector('[data-bs-toggle="dropdown"]'));
            document.querySelector('.locale-dropdown-cancel').addEventListener('click', function () { dd.hide(); });
            drop.addEventListener('mouseenter', function () { dd.show(); });
            drop.addEventListener('mouseleave', function () { dd.hide(); });
        })();
    </script>
@endpush
