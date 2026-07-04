@php
    $groupedProducts = $groupedProducts ?? [];
    $isDirectTopup = request('direct_topup') == '1';
@endphp

@if (count($groupedProducts) > 0)
    @if(getWebConfig(name: 'digital_product_setting'))
        <div class="d-flex justify-content-end mb-3">
            <div class="d-flex align-items-center gap-2">
                <label class="switcher cursor-pointer user-select-none mb-0">
                    <input class="switcher_input" type="checkbox"
                           onchange="window.location.href=this.checked?'{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('direct_topup'), ['direct_topup' => '1'])) }}':'{{ url()->current() }}?{{ http_build_query(request()->except('direct_topup')) }}'"
                           {{ $isDirectTopup ? 'checked' : '' }}>
                    <span class="switcher_control"></span>
                </label>
                <span class="fs-13 opacity-75">{{ translate('Direct_Top_Up') }}</span>
            </div>
        </div>
    @endif

    @foreach ($groupedProducts as $group)
        <div class="mb-4">
            <h5 class="fw-bold mb-3">{{ ($group['category'] ?? $group['sub_category'])->name }}</h5>
            @include('category-display-blocks._products-grid', [
                'products' => $group['products'],
                'themeKey' => $themeKey ?? theme_root_path(),
            ])
        </div>
    @endforeach
@else
    @include('category-display-blocks._empty-placeholder', [
        'message' => translate('no_product_found'),
        'icon' => 'product',
    ])
@endif
