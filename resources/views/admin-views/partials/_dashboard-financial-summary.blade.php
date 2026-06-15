<div class="card mb-3 remove-card-shadow">
    <div class="card-body">
        <h4 class="d-flex align-items-center text-capitalize gap-10 mb-3">
            <i class="fi fi-rr-stats fs-5 mb-1"></i>
            <span class="fw-bold fs-16">{{ translate('Platform_Financial_Summary') }}</span>
        </h4>

        <div class="row g-3">
            {{-- Total Users' Wallet Balance --}}
            <div class="col-lg-4">
                <div class="card border h-100 d-flex justify-content-center align-items-center">
                    <div class="card-body d-flex flex-column gap-10 align-items-center justify-content-center">
                        <img width="48" class="mb-2"
                             src="{{ dynamicAsset(path: 'public/assets/back-end/img/admin-wallet.png') }}"
                             alt="">
                        <h3 class="for-card-count mb-0 fz-24">
                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalUsersWalletBalance), currencyCode: getCurrencyCode()) }}
                        </h3>
                        <div class="text-capitalize fs-12 mb-30">
                            {{ translate('Total_Users_Wallet_Balance') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Vendor Inventory Values --}}
            <div class="col-lg-8">
                <div class="card border h-100">
                    <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
                        <i class="fi fi-rr-box fs-5"></i>
                        <h5 class="mb-0 flex-grow-1 fs-14">{{ translate('Total_Product_Balance_per_Vendor') }}</h5>
                        <span class="badge bg-soft-info text-info fs-12">
                            {{ count($vendorInventoryDetails) }} {{ translate('vendors') }}
                        </span>
                    </div>
                    <div class="card-body p-0" style="max-height: 260px; overflow-y: auto;">
                        @if(count($vendorInventoryDetails) > 0)
                            <table class="table table-sm table-borderless table-thead-bordered align-middle mb-0">
                                <thead class="thead-light thead-50 text-capitalize" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th class="px-3">#</th>
                                        <th>{{ translate('vendor') }}</th>
                                        <th class="text-end px-3">{{ translate('inventory_value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($vendorInventoryDetails as $index => $item)
                                        <tr>
                                            <td class="px-3 text-muted">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-semibold fs-12">
                                                        {{ $item['seller']->shop?->name ?? ($item['seller']->f_name . ' ' . $item['seller']->l_name) }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-end px-3">
                                                <span class="fw-bold text-primary fs-12">
                                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $item['inventory_value']), currencyCode: getCurrencyCode()) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="text-center p-4">
                                <p class="text-muted mb-0">{{ translate('no_vendor_inventory_data_found') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
