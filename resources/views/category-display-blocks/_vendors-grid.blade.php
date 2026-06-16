@php
    $themeKey = $themeKey ?? theme_root_path();
    $categoryUrl = isset($category) ? route('category-products', ['slug' => $category->slug]) : url()->current();
@endphp

@if ($vendors->count() > 0)
    <div class="row mx-n2">
        @foreach ($vendors as $vendorItem)
            @php
                $vendorParams = array_filter([
                    'vendor_id' => $vendorItem->id,
                    'vendor_name' => $vendorItem->shop?->name ?? '',
                    'parent_id' => $context['parent_id'] ?? null,
                    'parent_name' => $context['parent_name'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');
                $vendorUrl = $categoryUrl.'?'.http_build_query($vendorParams);
            @endphp
            <div class="col-lg-4 col-md-6 col-sm-12 px-2 pb-4 text-center">
                @if ($themeKey === 'theme_aster')
                    <a href="{{ $vendorUrl }}"
                       class="store-item d-flex flex-column text-center">
                        <img class="rounded-circle mb-2 mx-auto" width="72" height="72"
                             src="{{ getStorageImages(path: $vendorItem->shop?->image_full_url ?? $vendorItem->image_full_url, type: 'shop') }}"
                             alt="{{ $vendorItem->shop?->name ?? '' }}">
                        <h6 class="mb-0">{{ $vendorItem->shop?->name ?? '' }}</h6>
                        <small class="text-muted">{{ number_format($vendorItem['average_rating'] ?? 0, 1) }} {{ translate('rating') }}</small>
                    </a>
                @else
                    <a href="{{ $vendorUrl }}"
                       class="others-store-card text-capitalize w-100">
                        <div class="overflow-hidden other-store-banner">
                            <img class="w-100 h-100 object-cover" alt=""
                                 src="{{ getStorageImages(path: $vendorItem->shop?->banner_full_url ?? $vendorItem->banner_full_url, type: 'shop-banner') }}">
                        </div>
                        <div class="name-area">
                            <div class="position-relative">
                                <div class="overflow-hidden other-store-logo rounded-full">
                                    <img class="rounded-full" alt="{{ translate('store') }}"
                                         src="{{ getStorageImages(path: $vendorItem->shop?->image_full_url ?? $vendorItem->image_full_url, type: 'shop') }}">
                                </div>
                            </div>
                            <div class="info pt-2">
                                <h5 class="text-start">{{ $vendorItem->shop?->name ?? '' }}</h5>
                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                    <div class="d-flex align-items-center">
                                        <h4 class="text-FF7D1E fs-14 mb-0 fw-bold">
                                            {{ number_format($vendorItem['average_rating'] ?? 0, 1) }}
                                        </h4>
                                        <i class="tio-star text-FDBC15 mx-1"></i>
                                        <small>{{ translate('rating') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
    @if ($vendors->hasPages())
        <div class="mt-3 d-flex justify-content-center">{!! $vendors->withQueryString()->links() !!}</div>
    @endif
@else
    @include('category-display-blocks._empty-placeholder', [
        'message' => translate('no_vendor_found'),
        'icon' => 'vendor',
    ])
@endif
