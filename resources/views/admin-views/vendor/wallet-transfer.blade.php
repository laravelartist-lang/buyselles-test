@extends('layouts.admin.app')
@section('title', translate('transfer_to_vendor_wallet'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2 text-capitalize">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" width="28" alt="">
                {{ translate('transfer_to_vendor_wallet') }}
            </h2>
        </div>

        {{-- Transfer Form --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('send_balance_to_vendor') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.vendors.wallet-transfer.transfer') }}" method="POST">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="vendor_id" class="form-label">{{ translate('select_vendor') }}</label>
                        <select name="vendor_id" id="vendor_id" class="form-control js-select2-custom" required>
                            <option value="">{{ translate('select_vendor') }}</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" data-balance="{{ $vendor->wallet?->total_earning ?? 0 }}">
                                    {{ $vendor->f_name }} {{ $vendor->l_name }} 
                                    @if ($vendor->shop)
                                        ({{ $vendor->shop->name }})
                                    @endif
                                    — {{ $vendor->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label for="amount" class="form-label">{{ translate('amount') }} ({{ getCurrencySymbol(type: 'default') }})</label>
                        <input type="number" name="amount" id="amount" class="form-control" 
                            step="0.01" min="0.01" required
                            placeholder="{{ translate('enter_amount') }}">
                    </div>

                    <div class="form-group mb-4">
                        <label for="reference" class="form-label">{{ translate('reference') }} ({{ translate('optional') }})</label>
                        <input type="text" name="reference" id="reference" class="form-control"
                            placeholder="{{ translate('add_a_note') }}" maxlength="255">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fi fi-rr-transfer me-1"></i>
                        {{ translate('transfer_balance') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Transfer History --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('transfer_history') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered align-middle">
                        <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('vendor') }}</th>
                                <th>{{ translate('amount') }}</th>
                                <th>{{ translate('reference') }}</th>
                                <th>{{ translate('date') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transfers as $key => $transfer)
                                <tr>
                                    <td>{{ $transfers->firstItem() + $key }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-semibold">
                                                {{ $transfer->toUser?->f_name ?? translate('N/A') }}
                                                {{ $transfer->toUser?->l_name ?? '' }}
                                            </span>
                                            @if ($transfer->toUser?->shop)
                                                <span class="badge text-bg-info text-capitalize fs-12">
                                                    {{ $transfer->toUser->shop->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            +{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $transfer->amount), currencyCode: getCurrencyCode(type: 'default')) }}
                                        </span>
                                    </td>
                                    <td>{{ $transfer->reference ?? translate('N/A') }}</td>
                                    <td>{{ $transfer->created_at->format('d M Y, h:i A') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.vendors.wallet-transfer.financial-log', $transfer->to_user_id) }}"
                                           class="btn btn-outline-primary btn-sm"
                                           title="{{ translate('view_financial_log') }}">
                                            <i class="fi fi-rr-stats"></i>
                                            {{ translate('log') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        @include('layouts.admin.partials._empty-state', ['text' => 'no_data_found', 'image' => 'default'])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $transfers->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
