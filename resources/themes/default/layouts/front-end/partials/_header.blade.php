@php($announcement = getWebConfig(name: 'announcement'))

@if (isset($announcement) && $announcement['status'] == 1)
    <div class="text-center position-relative px-4 py-1 d--none" id="announcement"
        style="background-color: {{ $announcement['color'] }};color:{{ $announcement['text_color'] }}">
        <span>{{ $announcement['announcement'] }} </span>
        <span class="__close-announcement web-announcement-slideUp">X</span>
    </div>
@endif
<style>
    /* Auth: JS-controlled hover — disable CSS hover to prevent flicker */
    @media (min-width: 768px) {
        .navbar-expand-md .__auth-hover:hover>.dropdown-menu {
            display: none !important;
            animation: none !important;
            -webkit-animation: none !important;
        }

        .navbar-expand-md .__auth-hover>.dropdown-menu.__hover-visible {
            display: block !important;
        }
    }

    @media (max-width: 767px) {
        .navbar-expand-md .__auth-hover {
            position: relative;
        }

        .navbar-expand-md .__auth-hover > .dropdown-menu.__hover-visible,
        .navbar-expand-md .__auth-hover .dropdown-menu.__hover-visible {
            display: block !important;
            position: absolute;
            top: calc(100% + 0.25rem);
            inset-inline-end: 0;
            z-index: 1050;
            min-width: 10rem;
        }
    }

    /* Cart: JS controls hover — disable all CSS hover for cart */
    @media (min-width: 768px) {
        #cart_items .navbar-tool.dropdown:hover>.dropdown-menu {
            display: none !important;
            animation: none !important;
            -webkit-animation: none !important;
        }

        #cart_items .navbar-tool.dropdown>.dropdown-menu.__cart-visible {
            display: block !important;
        }
    }
</style>
<header class="rtl __inline-10">
    <div class="topbar">
        <div class="container">
            <div>
                <div class="topbar-text dropdown d-md-none ms-auto">
                    <a class="topbar-link direction-ltr" href="tel: {{ $web_config['phone'] }}">
                        <i class="fa fa-phone"></i> {{ $web_config['phone'] }}
                    </a>
                </div>
                <div class="d-none d-md-block mr-2 text-nowrap">
                    <a class="topbar-link d-none d-md-inline-block direction-ltr" href="tel:{{ $web_config['phone'] }}">
                        <i class="fa fa-phone"></i> {{ $web_config['phone'] }}
                    </a>
                </div>
            </div>

            @php($currentLang = session('local') ?? getDefaultLanguage())
            @php($currentCurr = session('currency_code') ?? 'USD')
            @php($langLabel = strtoupper($currentLang))
            @php($currLabel = $currentCurr)
            @php($langList = $web_config['language'] ?? [])
            @php($currList = $web_config['currencies'] ?? \App\Models\Currency::where('status', 1)->get())
            <div>
                <style>
                    .locale-pill {
                        display: inline-flex;
                        align-items: center;
                        gap: 3px;
                        padding: 5px 14px;
                        background: white;
                        color: #1a1a2e;
                        border: none;
                        border-radius: 22px;
                        cursor: pointer;
                        font-size: 12px;
                        font-weight: 500;
                        letter-spacing: 0.3px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                        transition: all 0.25s;
                        white-space: nowrap;
                    }
                    .locale-pill:hover { opacity: 0.85; }
                    .locale-pill-lang { font-weight: 600; }
                    .locale-pill-sep { opacity: 0.4; }
                    .locale-pill-curr { opacity: 0.85; }
                    .locale-dropdown { position: relative; display: inline-block; }
                    .locale-dropdown-menu {
                        display: none; position: absolute; top: 100%; right: 0; z-index: 1000;
                        min-width: 260px; padding: 16px; background: #fff; border-radius: 8px;
                        box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-top: 8px;
                    }
                    .locale-dropdown-menu.show { display: block; }
                </style>
                <div class="locale-dropdown" id="localeDropdown">
                    <button class="locale-pill" type="button">
                        <span class="locale-pill-lang">{{ $langLabel }}</span>
                        <span class="locale-pill-sep">·</span>
                        <span class="locale-pill-curr">{{ $currLabel }}</span>
                    </button>
                    <div class="locale-dropdown-menu" id="localeDropdownMenu">
                        <div class="form-group mb-3">
                            <label class="font-weight-semibold">{{ translate('Language') }}</label>
                            <select class="form-control" id="localeLanguageSelect">
                                @foreach ($langList as $lang)
                                    @if (!empty($lang['status']) && $lang['status'] == 1)
                                        <option value="{{ $lang['code'] }}" {{ $currentLang === $lang['code'] ? 'selected' : '' }}>
                                            {{ $lang['name'] }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-semibold">{{ translate('Currency') }}</label>
                            <select class="form-control" id="localeCurrencySelect">
                                @foreach ($currList as $cur)
                                    <option value="{{ $cur['code'] }}" {{ $currentCurr === $cur['code'] ? 'selected' : '' }}>
                                        {{ $cur['name'] ?? $cur['code'] }} ({{ $cur['symbol'] ?? $cur['code'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary flex-grow-1 locale-dropdown-cancel" style="flex:1;">{{ translate('Cancel') }}</button>
                            <button type="button" class="btn flex-grow-1" style="background:{{ $web_config['primary_color'] }};color:#fff;flex:1;" id="localeSaveBtn">{{ translate('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="navbar-sticky bg-light mobile-head">
        <div class="navbar navbar-expand-md navbar-light">
            <div class="container ">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand d-none d-sm-block me-3 flex-shrink-0 __min-w-7rem" href="{{ route('home') }}">
                    <img class="__inline-11" src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}"
                        alt="{{ $web_config['company_name'] }}">
                </a>
                <a class="navbar-brand d-sm-none" href="{{ route('home') }}">
                    <img class="mobile-logo-img"
                        src="{{ getStorageImages(path: $web_config['mob_logo'], type: 'logo') }}"
                        alt="{{ $web_config['company_name'] }}" />
                </a>

                <div class="input-group-overlay mx-lg-4 search-form-mobile text-align-direction">
                    <form action="{{ route('products') }}" type="submit" class="search_form">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-control appended-form-control search-bar-input" type="search"
                                autocomplete="off" data-given-value=""
                                placeholder="{{ translate('search_for_items') }}..." name="name"
                                value="{{ request('name') }}">

                            <input type="hidden" name="global_search_input" value="1">

                            <button class="input-group-append-overlay search_button d-none d-md-block" type="submit">
                                <span class="input-group-text __text-20px">
                                    <i class="czi-search text-white"></i>
                                </span>
                            </button>

                            <span class="close-search-form-mobile fs-14 font-semibold text-muted d-md-none text-nowrap"
                                type="submit">
                                {{ translate('cancel') }}
                            </span>
                        </div>

                        <input name="data_from" value="search" hidden>
                        <input name="page" value="1" hidden>
                        <diV class="card search-card mobile-search-card">
                            <div class="card-body">
                                <div class="search-result-box __h-400px overflow-x-hidden overflow-y-auto"></div>
                            </div>
                        </diV>
                    </form>
                </div>

                <div class="navbar-toolbar d-flex flex-shrink-0 align-items-center">
                    <a class="navbar-tool navbar-stuck-toggler" href="#">
                        <span class="navbar-tool-tooltip">{{ translate('expand_Menu') }}</span>
                        <div class="navbar-tool-icon-box">
                            <i class="navbar-tool-icon czi-menu open-icon"></i>
                            <i class="navbar-tool-icon czi-close close-icon"></i>
                        </div>
                    </a>
                    <div
                        class="navbar-tool open-search-form-mobile d-lg-none {{ Session::get('direction') === 'rtl' ? 'mr-md-3' : 'ml-md-3' }}">
                        <a class="navbar-tool-icon-box bg-secondary" href="javascript:">
                            <i class="tio-search"></i>
                        </a>
                    </div>
                    <div
                        class="navbar-tool dropdown d-none d-md-block {{ Session::get('direction') === 'rtl' ? 'mr-md-3' : 'ml-md-3' }}">
                        <a class="navbar-tool-icon-box bg-secondary dropdown-toggle" href="{{ route('wishlists') }}">
                            <span class="navbar-tool-label">
                                <span class="countWishlist">
                                    {{ session()->has('wish_list') ? count(session('wish_list')) : 0 }}
                                </span>
                            </span>
                            <i class="navbar-tool-icon czi-heart"></i>
                        </a>
                    </div>
                    @if (auth('customer')->check())
                        <div class="navbar-tool dropdown __auth-hover">
                            <a class="navbar-tool ml-3" href="javascript:">
                                <div class="navbar-tool-icon-box bg-secondary">
                                    <div class="navbar-tool-icon-box bg-secondary">
                                        <img class="img-profile rounded-circle __inline-14" alt=""
                                            src="{{ getStorageImages(path: auth('customer')->user()->image_full_url, type: 'avatar') }}">
                                    </div>
                                </div>
                                <div class="navbar-tool-text">
                                    <small>
                                        {{ translate('hello') }},
                                        {{ Str::limit(auth('customer')->user()->f_name, 10) }}
                                    </small>
                                    {{ translate('dashboard') }}
                                </div>
                                @if (getWebConfig(name: 'wallet_status') == 1)
                                    <span class="badge badge-soft-primary {{ Session::get('direction') === 'rtl' ? 'mr-2' : 'ml-2' }} px-2 py-1" style="font-size: 20px" title="{{ translate('wallet_balance') }}">
                                        <i class="tio-wallet-outlined"></i>
                                        {{ webCurrencyConverter(amount: auth('customer')->user()->wallet_balance ?? 0) }}
                                    </span>
                                @endif
                            </a>
                            <div class="dropdown-menu dropdown-menu-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }}"
                                aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" href="{{ route('account-oder') }}">
                                    {{ translate('my_Order') }} </a>
                                <a class="dropdown-item" href="{{ route('user-account') }}">
                                    {{ translate('my_Profile') }}</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item"
                                    href="{{ route('customer.auth.logout') }}">{{ translate('logout') }}</a>
                            </div>
                        </div>
                    @else
                        <div
                            class="navbar-tool dropdown __auth-hover {{ Session::get('direction') === 'rtl' ? 'mr-md-3' : 'ml-md-3' }}">
                            <a class="navbar-tool {{ Session::get('direction') === 'rtl' ? 'mr-md-3' : 'ml-md-3' }}"
                                href="javascript:">
                                <div class="navbar-tool-icon-box bg-secondary">
                                    <div class="navbar-tool-icon-box bg-secondary">
                                        <svg width="16" height="17" viewBox="0 0 16 17" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M4.25 4.41675C4.25 6.48425 5.9325 8.16675 8 8.16675C10.0675 8.16675 11.75 6.48425 11.75 4.41675C11.75 2.34925 10.0675 0.666748 8 0.666748C5.9325 0.666748 4.25 2.34925 4.25 4.41675ZM14.6667 16.5001H15.5V15.6667C15.5 12.4509 12.8825 9.83341 9.66667 9.83341H6.33333C3.11667 9.83341 0.5 12.4509 0.5 15.6667V16.5001H14.6667Z"
                                                fill="{{ $web_config['primary_color'] ?? '#1B7FED' }}" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                            <div class="text-align-direction dropdown-menu __auth-dropdown dropdown-menu-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }}"
                                aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" href="{{ route('customer.auth.login') }}">
                                    <i class="fa fa-sign-in mr-2"></i> {{ translate('sign_in') }}
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('customer.auth.sign-up') }}">
                                    <i class="fa fa-user-circle mr-2"></i>{{ translate('sign_up') }}
                                </a>
                            </div>
                        </div>
                    @endif
                    <div id="cart_items">
                        @include('layouts.front-end.partials._cart')
                    </div>
                </div>
            </div>
        </div>

        <div class="navbar navbar-expand-md navbar-stuck-menu">
            <div class="container px-10px">
                <div class="collapse navbar-collapse text-align-direction" id="navbarCollapse">
                    <div class="w-100 d-md-none text-align-direction">
                        <button class="navbar-toggler p-0" type="button" data-toggle="collapse"
                            data-target="#navbarCollapse">
                            <i class="tio-clear __text-26px"></i>
                        </button>
                    </div>

                    <ul class="navbar-nav d-block d-md-none">
                        <li class="nav-item dropdown {{ request()->is('/') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('home') }}">{{ translate('home') }}</a>
                        </li>
                    </ul>

                    @php($categories = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 11))

                    <ul class="navbar-nav mega-nav pr-lg-2 pl-lg-2 mr-2 d-none d-md-block __mega-nav">
                        <li class="nav-item {{ !request()->is('/') ? 'dropdown' : '' }}">

                            <a class="nav-link dropdown-toggle category-menu-toggle-btn ps-0" href="javascript:">
                                <svg width="21" height="21" viewBox="0 0 21 21" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M9.875 12.9195C9.875 12.422 9.6775 11.9452 9.32563 11.5939C8.97438 11.242 8.4975 11.0445 8 11.0445C6.75875 11.0445 4.86625 11.0445 3.625 11.0445C3.1275 11.0445 2.65062 11.242 2.29937 11.5939C1.9475 11.9452 1.75 12.422 1.75 12.9195V17.2945C1.75 17.792 1.9475 18.2689 2.29937 18.6202C2.65062 18.972 3.1275 19.1695 3.625 19.1695H8C8.4975 19.1695 8.97438 18.972 9.32563 18.6202C9.6775 18.2689 9.875 17.792 9.875 17.2945V12.9195ZM19.25 12.9195C19.25 12.422 19.0525 11.9452 18.7006 11.5939C18.3494 11.242 17.8725 11.0445 17.375 11.0445C16.1337 11.0445 14.2413 11.0445 13 11.0445C12.5025 11.0445 12.0256 11.242 11.6744 11.5939C11.3225 11.9452 11.125 12.422 11.125 12.9195V17.2945C11.125 17.792 11.3225 18.2689 11.6744 18.6202C12.0256 18.972 12.5025 19.1695 13 19.1695H17.375C17.8725 19.1695 18.3494 18.972 18.7006 18.6202C19.0525 18.2689 19.25 17.792 19.25 17.2945V12.9195ZM16.5131 9.66516L19.1206 7.05766C19.8525 6.32578 19.8525 5.13828 19.1206 4.4064L16.5131 1.79891C15.7813 1.06703 14.5937 1.06703 13.8619 1.79891L11.2544 4.4064C10.5225 5.13828 10.5225 6.32578 11.2544 7.05766L13.8619 9.66516C14.5937 10.397 15.7813 10.397 16.5131 9.66516ZM9.875 3.54453C9.875 3.04703 9.6775 2.57015 9.32563 2.2189C8.97438 1.86703 8.4975 1.66953 8 1.66953C6.75875 1.66953 4.86625 1.66953 3.625 1.66953C3.1275 1.66953 2.65062 1.86703 2.29937 2.2189C1.9475 2.57015 1.75 3.04703 1.75 3.54453V7.91953C1.75 8.41703 1.9475 8.89391 2.29937 9.24516C2.65062 9.59703 3.1275 9.79453 3.625 9.79453H8C8.4975 9.79453 8.97438 9.59703 9.32563 9.24516C9.6775 8.89391 9.875 8.41703 9.875 7.91953V3.54453Z"
                                        fill="currentColor" />
                                </svg>
                                <span class="category-menu-toggle-btn-text">
                                    {{ translate('categories') }}
                                </span>
                            </a>
                        </li>
                    </ul>

                    <ul class="navbar-nav mega-nav1 pr-md-2 pl-md-2 d-block d-xl-none">
                        <li class="nav-item dropdown d-md-none">
                            <a class="nav-link dropdown-toggle ps-0" href="javascript:" data-toggle="dropdown">
                                <i class="czi-menu align-middle mt-n1 me-2"></i>
                                <span class="me-4">
                                    {{ translate('categories') }}
                                </span>
                            </a>
                            <ul class="dropdown-menu __dropdown-menu-2 text-align-direction">
                                @php($categoryIndex = 0)
                                @foreach ($categories as $category)
                                    @php($categoryIndex++)
                                    @if ($categoryIndex < 10)
                                        <li class="dropdown">

                                            <a href="{{ route('category-products', ['slug' => $category['slug']]) }}"
                                                class="d-flex gap-10px align-items-center">
                                                <img class="aspect-1 rounded-circle" width="20"
                                                    src="{{ getStorageImages(path: $category?->icon_full_url, type: 'category') }}"
                                                    alt="{{ $category['name'] }}">
                                                <span>{{ $category['name'] }}</span>
                                            </a>
                                            @if ($category->childes->count() > 0)
                                                <a data-toggle='dropdown' class='__ml-50px'>
                                                    <i
                                                        class="czi-arrow-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }} __inline-16"></i>
                                                </a>
                                            @endif

                                            @if ($category->childes->count() > 0)
                                                <ul class="dropdown-menu text-align-direction">
                                                    @foreach ($category['childes'] as $subCategory)
                                                        <li class="dropdown">
                                                            <a
                                                                href="{{ route('category-products', ['slug' => $subCategory['slug']]) }}">
                                                                <span>{{ $subCategory['name'] }}</span>
                                                            </a>

                                                            @if ($subCategory->childes->count() > 0)
                                                                <a class="header-subcategories-links"
                                                                    data-toggle='dropdown'>
                                                                    <i
                                                                        class="czi-arrow-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }} __inline-16"></i>
                                                                </a>
                                                                <ul class="dropdown-menu">
                                                                    @foreach ($subCategory['childes'] as $subSubCategory)
                                                                        <li>
                                                                            <a class="dropdown-item"
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
                                <li class="__inline-17">
                                    <div>
                                        <a class="dropdown-item web-text-primary" href="{{ route('categories') }}">
                                            {{ translate('view_more') }}
                                        </a>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown d-none d-md-block {{ request()->is('/') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('home') }}">{{ translate('home') }}</a>
                        </li>

                        @if (getWebConfig(name: 'product_brand'))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#"
                                    data-toggle="dropdown">{{ translate('brand') }}</a>
                                <ul
                                    class="text-align-direction dropdown-menu __dropdown-menu-sizing dropdown-menu-{{ Session::get('direction') === 'rtl' ? 'right' : 'left' }} scroll-bar">
                                    @php($brandIndex = 0)
                                    @foreach (\App\Utils\BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting() as $brand)
                                        @php($brandIndex++)
                                        @if ($brandIndex < 10 && !empty($brand['slug']))
                                            <li class="__inline-17">
                                                <div>
                                                    <a class="dropdown-item"
                                                        href="{{ route('brand-products', ['slug' => $brand['slug']]) }}">
                                                        {{ $brand['name'] }}
                                                    </a>
                                                </div>
                                                <div class="align-baseline">
                                                    @if ($brand['brand_products_count'] > 0)
                                                        <span class="count-value px-2">(
                                                            {{ $brand['brand_products_count'] }} )</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                    <li class="__inline-17">
                                        <div>
                                            <a class="dropdown-item web-text-primary" href="{{ route('brands') }}">
                                                {{ translate('view_more') }}
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        @if (count(getFeaturedDealsProductList()) > 0 &&
                                !(
                                    $web_config['flash_deals'] ||
                                    count($web_config['flash_deals_products']) > 0 ||
                                    $web_config['discount_product'] > 0 ||
                                    $web_config['clearance_sale_product_count'] > 0
                                ))
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize" href="{{ route('featured-deal-products') }}">
                                    {{ translate('featured_Deal') }}
                                </a>
                            </li>
                        @elseif(
                            $web_config['flash_deals'] &&
                                count($web_config['flash_deals_products']) > 0 &&
                                !(count(getFeaturedDealsProductList()) > 0 ||
                                    $web_config['discount_product'] > 0 ||
                                    $web_config['clearance_sale_product_count'] > 0
                                ))
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize"
                                    href="{{ route('flash-deals', ['id' => $web_config['flash_deals']['id'] ?? 0]) }}">
                                    {{ translate('flash_deal') }}
                                </a>
                            </li>
                        @elseif(
                            $web_config['discount_product'] > 0 &&
                                !(count(getFeaturedDealsProductList()) > 0 ||
                                    ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) ||
                                    $web_config['clearance_sale_product_count'] > 0
                                ))
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize" href="{{ route('discounted-products') }}">
                                    {{ translate('discounted_products') }}
                                </a>
                            </li>
                        @elseif(
                            $web_config['clearance_sale_product_count'] > 0 &&
                                !(count(getFeaturedDealsProductList()) > 0 ||
                                    ($web_config['flash_deals'] || count($web_config['flash_deals_products']) > 0) ||
                                    $web_config['discount_product'] > 0
                                ))
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize" href="{{ route('clearance-sale-products') }}">
                                    {{ translate('clearance_Sale') }}
                                </a>
                            </li>
                        @elseif(count(getFeaturedDealsProductList()) > 0 ||
                                ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) ||
                                $web_config['discount_product'] > 0 ||
                                $web_config['clearance_sale_product_count'] > 0)
                            <li class="nav-item">
                                <div class="dropdown">
                                    <button
                                        class="btn dropdown-toggle text-white text-max-md-dark text-capitalize ps-2"
                                        type="button" id="dropdownMenuButton" data-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        {{ translate('offers') }}
                                    </button>
                                    <div class="dropdown-menu __dropdown-menu-3 __min-w-165px text-align-direction"
                                        aria-labelledby="dropdownMenuButton">
                                        @if (count(getFeaturedDealsProductList()) > 0)
                                            <a class="dropdown-item text-nowrap text-capitalize"
                                                href="{{ route('featured-deal-products') }}">
                                                {{ translate('featured_Deal') }}
                                            </a>
                                        @endif

                                        @if ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0)
                                            @if (count(getFeaturedDealsProductList()) > 0)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            <a class="dropdown-item text-nowrap text-capitalize"
                                                href="{{ route('flash-deals', ['id' => $web_config['flash_deals']['id'] ?? 0]) }}">
                                                {{ translate('flash_deal') }}
                                            </a>
                                        @endif

                                        @if ($web_config['discount_product'] > 0)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap text-capitalize"
                                                href="{{ route('discounted-products') }}">
                                                {{ translate('discounted_products') }}
                                            </a>
                                        @endif

                                        @if ($web_config['clearance_sale_product_count'] > 0)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap"
                                                href="{{ route('clearance-sale-products') }}">
                                                {{ translate('clearance_Sale') }}
                                            </a>
                                        @endif

                                    </div>
                                </div>
                            </li>
                        @endif

                        @if ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) == 1)
                            @php($firstPublisherID = is_array($web_config['publishing_houses']) && isset($web_config['publishing_houses']['id']) ? $web_config['publishing_houses']['id'] : $web_config['publishing_houses']?->first()?->id)
                            <li class="nav-item dropdown d-none d-md-block {{ request()->is('/') ? 'active' : '' }}">
                                <a class="nav-link"
                                    href="{{ route('products', ['publishing_house_id' => $firstPublisherID, 'product_type' => 'digital', 'page' => 1]) }}">
                                    {{ translate('Publication_House') }}
                                </a>
                            </li>
                        @elseif ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) > 1)
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                    {{ translate('Publication_House') }}
                                </a>
                                <ul
                                    class="text-align-direction dropdown-menu __dropdown-menu-sizing dropdown-menu-{{ Session::get('direction') === 'rtl' ? 'right' : 'left' }} scroll-bar">
                                    @php($publishingHousesIndex = 0)
                                    @foreach ($web_config['publishing_houses'] as $publishingHouseItem)
                                        @if ($publishingHousesIndex < 10 && $publishingHouseItem['name'] != 'Unknown')
                                            @php($publishingHousesIndex++)
                                            <li class="__inline-17">
                                                <div>
                                                    <a class="dropdown-item"
                                                        href="{{ route('products', ['publishing_house_id' => $publishingHouseItem['id'], 'product_type' => 'digital', 'page' => 1]) }}">
                                                        {{ $publishingHouseItem['name'] }}
                                                    </a>
                                                </div>
                                                <div class="align-baseline">
                                                    @if ($publishingHouseItem['publishing_house_products_count'] > 0)
                                                        <span class="count-value px-2">(
                                                            {{ $publishingHouseItem['publishing_house_products_count'] }}
                                                            )</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                    <li class="__inline-17">
                                        <div>
                                            <a class="dropdown-item web-text-primary"
                                                href="{{ route('products', ['product_type' => 'digital', 'page' => 1]) }}">
                                                {{ translate('view_more') }}
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        @php($businessMode = getWebConfig(name: 'business_mode'))
                        @if ($businessMode == 'multi')
                            <li class="nav-item dropdown {{ request()->is('/') ? 'active' : '' }}">
                                <a class="nav-link text-capitalize"
                                    href="{{ route('vendors') }}">{{ translate('all_vendors') }}</a>
                            </li>
                        @endif

                        @if (auth('customer')->check())
                            <li class="nav-item d-md-none">
                                <a href="{{ route('user-account') }}" class="nav-link text-capitalize">
                                    {{ translate('user_profile') }}
                                </a>
                            </li>
                            <li class="nav-item d-md-none">
                                <a href="{{ route('wishlists') }}" class="nav-link">
                                    {{ translate('Wishlist') }}
                                </a>
                            </li>
                        @else
                            <li class="nav-item d-md-none">
                                <a class="dropdown-item pl-2" href="{{ route('customer.auth.login') }}">
                                    <i class="fa fa-sign-in mr-2"></i> {{ translate('sign_in') }}
                                </a>
                                <div class="dropdown-divider"></div>
                            </li>
                            <li class="nav-item d-md-none">
                                <a class="dropdown-item pl-2" href="{{ route('customer.auth.sign-up') }}">
                                    <i class="fa fa-user-circle mr-2"></i>{{ translate('sign_up') }}
                                </a>
                            </li>
                        @endif
                        @if ($businessMode == 'multi')
                            @if (getWebConfig(name: 'seller_registration'))
                                <li class="nav-item">
                                    <div class="dropdown">
                                        <button
                                            class="btn dropdown-toggle text-white text-max-md-dark text-capitalize ps-2"
                                            type="button" id="dropdownMenuButton" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            {{ translate('vendor_zone') }}
                                        </button>
                                        <div class="dropdown-menu __dropdown-menu-3 __min-w-165px text-align-direction"
                                            aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item text-nowrap text-capitalize"
                                                href="{{ route('vendor.auth.registration.index') }}">
                                                {{ translate('become_a_vendor') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap"
                                                href="{{ route('vendor.auth.login') }}">
                                                {{ translate('vendor_login') }}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endif
                        @endif
                    </ul>
                    @if (auth('customer')->check())
                        <div class="logout-btn mt-auto d-md-none">
                            <hr>
                            <a href="{{ route('customer.auth.logout') }}" class="nav-link">
                                <strong class="text-base">{{ translate('logout') }}</strong>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="megamenu-wrap">
            <div class="container">
                <div class="category-menu-wrap">
                    <ul class="category-menu">
                        @foreach ($categories as $key => $category)
                            <li>
                                <a href="{{ route('category-products', ['slug' => $category['slug']]) }}">
                                    <span class="d-flex gap-10px justify-content-start align-items-center">
                                        <img class="aspect-1 rounded-circle" width="20"
                                            src="{{ getStorageImages(path: $category?->icon_full_url, type: 'category') }}"
                                            alt="{{ $category['name'] }}">
                                        <span class="line--limit-2">{{ $category->name }}</span>
                                    </span>
                                </a>
                                @if ($category->childes->count() > 0)
                                    <div class="mega_menu z-2">
                                        @foreach ($category->childes as $sub_category)
                                            <div class="mega_menu_inner">
                                                <h6>
                                                    <a
                                                        href="{{ route('category-products', ['slug' => $sub_category['slug']]) }}">
                                                        {{ $sub_category->name }}
                                                    </a>
                                                </h6>
                                                @if ($sub_category->childes->count() > 0)
                                                    @foreach ($sub_category->childes as $sub_sub_category)
                                                        <div>
                                                            <a
                                                                href="{{ route('category-products', ['slug' => $sub_sub_category['slug']]) }}">
                                                                {{ $sub_sub_category->name }}
                                                            </a>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                        <li class="text-center">
                            <a href="{{ route('categories') }}"
                                class="text-primary font-weight-bold justify-content-center">
                                {{ translate('View_All') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

@push('script')
    <script>
        "use strict";

        // Auth dropdown — JS-controlled hover on desktop, tap toggle on mobile
        (function() {
            var $authWrappers = $('.__auth-hover');
            var mobileAuthQuery = window.matchMedia('(max-width: 767px)');

            $authWrappers.each(function() {
                var $wrapper = $(this);
                var $menu = $wrapper.find('.dropdown-menu');
                var $trigger = $wrapper.find('> a').first();
                var hideTimer;

                $wrapper.on('mouseenter', function() {
                    if (mobileAuthQuery.matches) {
                        return;
                    }

                    clearTimeout(hideTimer);
                    $menu.addClass('__hover-visible');
                }).on('mouseleave', function() {
                    if (mobileAuthQuery.matches) {
                        return;
                    }

                    hideTimer = setTimeout(function() {
                        $menu.removeClass('__hover-visible');
                    }, 300);
                });

                $menu.on('mouseenter', function() {
                    clearTimeout(hideTimer);
                }).on('mouseleave', function() {
                    if (mobileAuthQuery.matches) {
                        return;
                    }

                    hideTimer = setTimeout(function() {
                        $menu.removeClass('__hover-visible');
                    }, 300);
                });

                $trigger.on('click', function(event) {
                    if (!mobileAuthQuery.matches) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    var isOpen = $menu.hasClass('__hover-visible');
                    $('.__auth-hover .dropdown-menu').removeClass('__hover-visible');

                    if (!isOpen) {
                        $menu.addClass('__hover-visible');
                    }
                });
            });

            $(document).on('click touchstart', function(event) {
                if (!mobileAuthQuery.matches) {
                    return;
                }

                if (!$(event.target).closest('.__auth-hover').length) {
                    $('.__auth-hover .dropdown-menu').removeClass('__hover-visible');
                }
            });
        })();

        // Cart dropdown — fully JS-controlled hover, immune to CSS conflicts
        (function() {
            var $wrapper = $('#cart_items').find('.navbar-tool.dropdown');
            if (!$wrapper.length) return;
            var $menu = $wrapper.find('.dropdown-menu');
            var hideTimer;

            function openCart() {
                clearTimeout(hideTimer);
                $menu.addClass('__cart-visible');
            }

            function closeCart() {
                hideTimer = setTimeout(function() {
                    $menu.removeClass('__cart-visible');
                }, 300);
            }

            $wrapper.on('mouseenter', openCart).on('mouseleave', closeCart);
            $menu.on('mouseenter', function() {
                clearTimeout(hideTimer);
            }).on('mouseleave', closeCart);
        })();

        $(".category-menu").find(".mega_menu").parents("li")
            .addClass("has-sub-item").find("> a")
            .append("<i class='czi-arrow-{{ Session::get('direction') === 'rtl' ? 'left' : 'right' }}'></i>");

        // Locale dropdown
        (function() {
            var btn = document.querySelector('.locale-pill');
            var menu = document.getElementById('localeDropdownMenu');
            var cancel = document.querySelector('.locale-dropdown-cancel');
            var saveBtn = document.getElementById('localeSaveBtn');
            if (!btn || !menu) return;
            var hideTimer;

            function show() { clearTimeout(hideTimer); menu.classList.add('show'); }
            function hide() { hideTimer = setTimeout(function() { menu.classList.remove('show'); }, 200); }

            btn.addEventListener('mouseenter', show);
            btn.addEventListener('mouseleave', hide);
            menu.addEventListener('mouseenter', function() { clearTimeout(hideTimer); });
            menu.addEventListener('mouseleave', hide);

            if (cancel) cancel.addEventListener('click', function() { menu.classList.remove('show'); });

            if (saveBtn) {
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
            }
        })();

    </script>
@endpush
