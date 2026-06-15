@extends('layouts.admin.app')
@section('title', translate('financial_log') . ' — ' . ($vendor->shop->name ?? $vendor->f_name . ' ' . $vendor->l_name))

@section('content')
    <div class="content container-fluid">
        {{-- Header --}}
        <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2 text-capitalize">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" width="28" alt="">
                {{ translate('financial_log') }}
            </h2>
            <a href="{{ route('admin.vendors.wallet-transfer.index') }}"
               class="btn btn-outline-primary">
                <i class="fi fi-rr-arrow-left me-1"></i>
                {{ translate('back_to_transfers') }}
            </a>
        </div>

        {{-- Vendor Info Card --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <div>
                        <img class="rounded-circle aspect-1" width="64" height="64"
                             src="{{ getStorageImages(path: $vendor?->shop?->image_full_url, type: 'shop') }}"
                             alt="{{ $vendor?->shop?->name ?? '' }}">
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1">
                            {{ $vendor?->shop?->name ?? translate('shop_Name') }}
                        </h4>
                        <p class="text-muted mb-0">
                            {{ $vendor->f_name }} {{ $vendor->l_name }}
                            &middot; {{ $vendor->email }}
                        </p>
                    </div>
                    <div class="d-flex gap-3 text-center">
                        <div>
                            <h5 class="mb-0 text-success">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $inboundTotal), currencyCode: getCurrencyCode(type: 'default')) }}</h5>
                            <small class="text-muted">{{ translate('total_inbound') }}</small>
                        </div>
                        <div class="border-start ps-3">
                            <h5 class="mb-0 text-danger">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $outboundTotal), currencyCode: getCurrencyCode(type: 'default')) }}</h5>
                            <small class="text-muted">{{ translate('total_outbound') }}</small>
                        </div>
                        <div class="border-start ps-3">
                            <h5 class="mb-0 text-primary">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $vendor?->wallet?->total_earning ?? 0), currencyCode: getCurrencyCode(type: 'default')) }}</h5>
                            <small class="text-muted">{{ translate('current_balance') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Inbound Transfers --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="fi fi-rr-arrow-down text-success"></i>
                        <h5 class="mb-0 flex-grow-1">{{ translate('inbound_transfers') }}</h5>
                        <span class="badge bg-soft-success text-success fs-12">
                            {{ $inboundTransfers->total() }} {{ translate('transactions') }}
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-thead-bordered align-middle mb-0">
                                <thead class="thead-light thead-50 text-capitalize">
                                    <tr>
                                        <th>{{ translate('SL') }}</th>
                                        <th>{{ translate('transaction_id') }}</th>
                                        <th>{{ translate('sender') }}</th>
                                        <th>{{ translate('receiver') }}</th>
                                        <th>{{ translate('amount') }}</th>
                                        <th>{{ translate('date') }}</th>
                                        <th>{{ translate('status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($inboundTransfers as $key => $transfer)
                                        <tr>
                                            <td>{{ $inboundTransfers->firstItem() + $key }}</td>
                                            <td>
                                                <span class="text-muted small">#{{ $transfer->id }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">{{ translate('admin') }}</span>
                                                @if ($transfer->reference)
                                                    <br><small class="text-muted fst-italic">"{{ $transfer->reference }}"</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div>
                                                    <span class="fw-semibold">{{ $vendor?->shop?->name ?? $vendor->f_name }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-success">
                                                    +{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $transfer->amount), currencyCode: getCurrencyCode(type: 'default')) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted small">{{ $transfer->created_at->format('d M Y, h:i A') }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success text-bg-success text-capitalize">
                                                    {{ translate('completed') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                @include('layouts.admin.partials._empty-state', ['text' => 'no_inbound_transfers_found', 'image' => 'default'])
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($inboundTransfers->hasPages())
                            <div class="d-flex justify-content-end p-3">
                                {{ $inboundTransfers->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Outbound Transfers --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="fi fi-rr-arrow-up text-danger"></i>
                        <h5 class="mb-0 flex-grow-1">{{ translate('outbound_transfers') }}</h5>
                        <span class="badge bg-soft-danger text-danger fs-12">
                            {{ $outboundTransfers->total() }} {{ translate('transactions') }}
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-thead-bordered align-middle mb-0">
                                <thead class="thead-light thead-50 text-capitalize">
                                    <tr>
                                        <th>{{ translate('SL') }}</th>
                                        <th>{{ translate('transaction_id') }}</th>
                                        <th>{{ translate('sender') }}</th>
                                        <th>{{ translate('receiver') }}</th>
                                        <th>{{ translate('amount') }}</th>
                                        <th>{{ translate('date') }}</th>
                                        <th>{{ translate('status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($outboundTransfers as $key => $transfer)
                                        <tr>
                                            <td>{{ $outboundTransfers->firstItem() + $key }}</td>
                                            <td>
                                                <span class="text-muted small">#{{ $transfer->id }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">{{ $vendor?->shop?->name ?? $vendor->f_name }}</span>
                                                @if ($transfer->reference)
                                                    <br><small class="text-muted fst-italic">"{{ $transfer->reference }}"</small>
                                                @endif
                                            </td>
                                            <td>
                                                @php $customer = $transfer->toUser; @endphp
                                                <div>
                                                    <span class="fw-semibold">
                                                        {{ $customer?->f_name ?? translate('N/A') }}
                                                        {{ $customer?->l_name ?? '' }}
                                                    </span>
                                                    @if ($customer?->email)
                                                        <br><small class="text-muted">{{ $customer->email }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-danger">
                                                    -{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $transfer->amount), currencyCode: getCurrencyCode(type: 'default')) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted small">{{ $transfer->created_at->format('d M Y, h:i A') }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success text-bg-success text-capitalize">
                                                    {{ translate('completed') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                @include('layouts.admin.partials._empty-state', ['text' => 'no_outbound_transfers_found', 'image' => 'default'])
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($outboundTransfers->hasPages())
                            <div class="d-flex justify-content-end p-3">
                                {{ $outboundTransfers->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
