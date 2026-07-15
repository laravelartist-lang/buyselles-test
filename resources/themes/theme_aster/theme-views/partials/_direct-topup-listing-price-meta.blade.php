@if (! empty($product) && ($product->is_direct_topup ?? false))
    @php
        $directTopUpListing = app(\App\Services\DirectTopUp\DirectTopUpService::class)->buildListingPricePayload($product);
    @endphp
    @if (! empty($directTopUpListing))
        <div class="fs-11 text-muted direct-topup-listing-price-meta">
            {{ number_format((float) $directTopUpListing['quantity'], 0) }}
            {{ $directTopUpListing['quantity_label'] }}
        </div>
    @endif
@endif
