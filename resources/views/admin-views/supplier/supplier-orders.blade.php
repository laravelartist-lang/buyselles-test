@extends('layouts.admin.app')

@section('title', translate('supplier_orders'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-header flex-wrap gap-3">
            <h5 class="mb-0"><i class="fi fi-rr-shopping-cart"></i> {{ translate('supplier_orders') }}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.supplier.orders') }}" method="GET" class="mb-4">
                <div class="row gy-2 gx-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('supplier') }}</label>
                        <select name="supplier_id" class="form-control form-control-sm">
                            <option value="">{{ translate('all') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('all') }}</option>
                            @foreach(['pending','processing','fulfilled','failed','cancelled'] as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fi fi-rr-search"></i> {{ translate('filter') }}
                        </button>
                        <a href="{{ route('admin.supplier.orders') }}" class="btn btn-secondary btn-sm">
                            {{ translate('reset') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('supplier') }}</th>
                            <th>{{ translate('product') }}</th>
                            <th>{{ translate('supplier_order_ID') }}</th>
                            <th>{{ translate('platform_order') }}</th>
                            <th>{{ translate('qty') }}</th>
                            <th>{{ translate('cost') }}</th>
                            <th>{{ translate('status') }}</th>
                            <th>{{ translate('codes_received') }}</th>
                            <th>{{ translate('date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $key => $order)
                            <tr>
                                <td>{{ $orders->firstItem() + $key }}</td>
                                <td>{{ $order->supplierApi?->name ?? translate('deleted') }}</td>
                                <td>{{ $order->productMapping?->product?->name ?? translate('N/A') }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $order->supplier_order_id ?? '-' }}</span>
                                </td>
                                <td>
                                    @if($order->order_id)
                                        <a href="{{ route('admin.orders.details', $order->order_id) }}" class="text-primary">
                                            #{{ $order->order_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ translate('pool_order') ?: 'Pool order' }}</span>
                                    @endif
                                </td>
                                <td>{{ $order->quantity }}</td>
                                <td>{{ $order->cost_currency }} {{ number_format($order->total_cost, 2) }}</td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-warning text-dark',
                                            'processing' => 'bg-info text-dark',
                                            'fulfilled' => 'bg-success',
                                            'failed' => 'bg-danger',
                                            'cancelled' => 'bg-secondary',
                                        ];
                                    @endphp
                                    <span class="badge {{ $statusColors[$order->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td>
                                    @php $codesCount = count($order->getDecryptedCodes()); @endphp
                                    @if($codesCount > 0)
                                        <span class="badge bg-success">{{ $codesCount }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fi fi-sr-inbox-in" style="font-size: 2rem;"></i>
                                    <p class="mt-2">{{ translate('no_supplier_orders_found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {!! $orders->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection
