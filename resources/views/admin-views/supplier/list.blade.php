@extends('layouts.admin.app')

@section('title', translate('supplier_list'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body d-flex flex-column gap-20">
            <div class="d-flex justify-content-between align-items-center gap-20 flex-wrap">
                <h3 class="mb-0">
                    {{ translate('supplier_list') }}
                    <span class="badge text-dark bg-body-secondary fw-semibold rounded-50">{{ $suppliers->total() }}</span>
                </h3>

                <div class="d-flex flex-wrap gap-3 align-items-stretch">
                    <form action="{{ url()->current() }}" method="GET">
                        <div class="input-group flex-grow-1 max-w-280">
                            <input type="search" name="searchValue" class="form-control"
                                placeholder="{{ translate('search_by_name') }}"
                                value="{{ $searchValue }}">
                            <div class="input-group-append search-submit">
                                <button type="submit"><i class="fi fi-rr-search"></i></button>
                            </div>
                        </div>
                    </form>

                    <a href="{{ route('admin.supplier.add') }}" class="btn btn-primary">
                        + {{ translate('add_supplier') }}
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle">
                    <thead class="text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('name') }}</th>
                            <th>{{ translate('driver') }}</th>
                            <th class="text-center">{{ translate('health') }}</th>
                            <th class="text-center">{{ translate('balance') }}</th>
                            <th class="text-center">{{ translate('priority') }}</th>
                            <th class="text-center">{{ translate('rate_limit') }}</th>
                            <th class="text-center">{{ translate('status') }}</th>
                            <th class="text-center">{{ translate('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($suppliers as $key => $supplier)
                        <tr>
                            <td>{{ $suppliers->firstItem() + $key }}</td>
                            <td>
                                <div class="fw-semibold">{{ $supplier->name }}</div>
                                <small class="text-muted text-truncate d-block max-w-200">{{ $supplier->base_url }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info text-white">{{ $supplier->driver }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    $healthBadge = match($supplier->health_status) {
                                        'healthy' => 'bg-success',
                                        'degraded' => 'bg-warning text-dark',
                                        'down' => 'bg-danger',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $healthBadge }}">{{ $supplier->health_status }}</span>
                                @if($supplier->health_checked_at)
                                    <br><small class="text-muted">{{ $supplier->health_checked_at->diffForHumans() }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $bal = $balances[$supplier->id]['balance'] ?? null;
                                @endphp
                                @if($bal && $bal->supported)
                                    <span class="fw-semibold {{ $bal->balance > 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($bal->balance, 2) }}
                                    </span>
                                    <br><small class="text-muted">{{ $bal->currency }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $supplier->priority }}</td>
                            <td class="text-center">{{ $supplier->rate_limit_per_minute }}/min</td>
                            <td>
                                <form action="{{ route('admin.supplier.status') }}" method="post"
                                      id="supplier-status{{ $supplier->id }}-form"
                                      class="no-reload-form">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $supplier->id }}">
                                    <label class="switcher mx-auto" for="supplier-status{{ $supplier->id }}">
                                        <input class="switcher_input custom-modal-plugin" type="checkbox" value="1"
                                               name="status" id="supplier-status{{ $supplier->id }}"
                                               {{ $supplier->is_active ? 'checked' : '' }}
                                               data-modal-type="input-change-form" data-reload="true"
                                               data-modal-form="#supplier-status{{ $supplier->id }}-form"
                                               data-on-title="{{ translate('Want_to_Turn_ON_Supplier_Status') . '?' }}"
                                               data-off-title="{{ translate('Want_to_Turn_OFF_Supplier_Status') . '?' }}"
                                               data-on-message="<p>{{ translate('If_enabled_this_supplier_will_be_used_for_automatic_code_fulfillment') }}</p>"
                                               data-off-message="<p>{{ translate('If_disabled_this_supplier_will_not_be_used_for_any_order_fulfillment') }}</p>"
                                               data-on-button-text="{{ translate('turn_on') }}"
                                               data-off-button-text="{{ translate('turn_off') }}">
                                        <span class="switcher_control"></span>
                                    </label>
                                </form>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="#" class="btn-icon test-connection-btn"
                                       data-url="{{ route('admin.supplier.test-connection', $supplier->id) }}"
                                       title="{{ translate('test_connection') }}">
                                        <i class="fi fi-rr-signal-alt-2"></i>
                                    </a>
                                    <a href="{{ route('admin.supplier.edit', $supplier->id) }}" class="btn-icon"
                                       title="{{ translate('edit') }}">
                                        <i class="fi fi-sr-edit"></i>
                                    </a>
                                    <form method="post" action="{{ route('admin.supplier.delete') }}" class="d-inline delete-supplier-form">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $supplier->id }}">
                                        <button type="button" class="btn-icon btn-danger-icon delete-supplier-btn">
                                            <i class="fi fi-rr-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <i class="fi fi-sr-inbox-in fs-1 text-muted"></i>
                                    <span class="text-muted">{{ translate('no_suppliers_found') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-sm-end">
                {{ $suppliers->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    'use strict';

    $(document).on('click', '.test-connection-btn', function(e) {
        e.preventDefault();
        let btn = $(this);
        let url = btn.data('url');

        btn.html('<i class="fi fi-rr-spinner spinner-spin"></i>');

        $.get(url, function(response) {
            let icon = response.success ? 'fi fi-sr-check text-success' : 'fi fi-sr-cross text-danger';
            btn.html('<i class="' + icon + '"></i>');

            Swal.fire({
                icon: response.success ? 'success' : 'error',
                title: response.status.charAt(0).toUpperCase() + response.status.slice(1),
                text: response.message + (response.latency_ms ? ' (' + response.latency_ms + 'ms)' : ''),
                timer: 3000,
                showConfirmButton: false,
            });

            setTimeout(function() {
                btn.html('<i class="fi fi-rr-signal-alt-2"></i>');
            }, 5000);
        }).fail(function() {
            btn.html('<i class="fi fi-sr-cross text-danger"></i>');
        });
    });

    $(document).on('click', '.delete-supplier-btn', function() {
        let form = $(this).closest('form');
        const getText = document.getElementById('get-confirm-and-cancel-button-text-for-delete');
        Swal.fire({
            title: getText?.dataset.sure || 'Are you sure?',
            text: getText?.dataset.text || 'You will not be able to revert this!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText: getText?.dataset.cancel || 'Cancel',
            confirmButtonText: getText?.dataset.confirm || 'Yes, delete it!',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
</script>
@endpush
