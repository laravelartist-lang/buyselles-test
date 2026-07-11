@extends('layouts.admin.app')

@section('title', translate('reseller_api_keys'))

@section('content')
<div class="content container-fluid">

    {{-- One-time display of newly generated key --}}
    @if(session('raw_api_key'))
    <div class="alert alert-success border-0 mb-4">
        <h5 class="fw-bold mb-3"><i class="fi fi-sr-key me-2"></i>{{ translate('new_api_key_generated') }}</h5>
        <p class="mb-2 text-danger fw-semibold">{{ translate('copy_key_warning') }}</p>
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label fw-semibold mb-1">{{ translate('API_Key') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" readonly
                           value="{{ session('raw_api_key') }}" id="new-api-key">
                    <button class="btn btn-outline-secondary copy-btn" data-target="new-api-key"
                            title="{{ translate('copy') }}">
                        <i class="fi fi-rr-copy"></i>
                    </button>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold mb-1">{{ translate('API_Secret') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" readonly
                           value="{{ session('raw_api_secret') }}" id="new-api-secret">
                    <button class="btn btn-outline-secondary copy-btn" data-target="new-api-secret"
                            title="{{ translate('copy') }}">
                        <i class="fi fi-rr-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-body d-flex flex-column gap-20">
            <div class="d-flex justify-content-between align-items-center gap-20 flex-wrap">
                <h3 class="mb-0">
                    {{ translate('reseller_api_keys') }}
                    <span class="badge text-dark bg-body-secondary fw-semibold rounded-50">{{ $keys->total() }}</span>
                    @if($pendingCount > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $pendingCount }} {{ translate('pending') }}</span>
                    @endif
                </h3>

                <div class="d-flex flex-wrap gap-3 align-items-stretch">
                    <form action="{{ url()->current() }}" method="GET">
                        <div class="input-group flex-grow-1 max-w-280">
                            <input type="search" name="searchValue" class="form-control"
                                placeholder="{{ translate('search_by_name_or_user') }}"
                                value="{{ $search }}">
                            <div class="input-group-append search-submit">
                                <button type="submit"><i class="fi fi-rr-search"></i></button>
                            </div>
                        </div>
                    </form>

                    <a href="{{ route('partner-api.docs') }}" class="btn btn-outline-primary" target="_blank">
                        <i class="fi fi-rr-document me-1"></i> {{ translate('api_docs') }}
                    </a>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                        + {{ translate('generate_new_key') }}
                    </button>
                </div>
            </div>

            {{-- Status filter tabs --}}
            <ul class="nav nav-tabs border-0 gap-2 mb-2">
                <li class="nav-item">
                    <a class="nav-link {{ !$filterStatus ? 'active' : '' }}"
                       href="{{ route('admin.reseller-keys.list') }}">{{ translate('all') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $filterStatus === 'pending' ? 'active' : '' }}"
                       href="{{ route('admin.reseller-keys.list', ['status' => 'pending']) }}">
                        {{ translate('pending') }}
                        @if($pendingCount > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $filterStatus === 'active' ? 'active' : '' }}"
                       href="{{ route('admin.reseller-keys.list', ['status' => 'active']) }}">{{ translate('active') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $filterStatus === 'inactive' ? 'active' : '' }}"
                       href="{{ route('admin.reseller-keys.list', ['status' => 'inactive']) }}">{{ translate('inactive') }}</a>
                </li>
            </ul>

            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle">
                    <thead class="text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('name') }}</th>
                            <th>{{ translate('user') }}</th>
                            <th>{{ translate('rate_limit') }}</th>
                            <th class="text-center">{{ translate('requests') }}</th>
                            <th>{{ translate('last_used') }}</th>
                            <th class="text-center">{{ translate('status') }}</th>
                            <th class="text-center">{{ translate('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($keys as $i => $key)
                        <tr>
                            <td>{{ $keys->firstItem() + $i }}</td>
                            <td class="fw-semibold">{{ $key->name }}</td>
                            <td>
                                @if($key->user)
                                    <div class="fw-semibold">{{ $key->user->name }}</div>
                                    <small class="text-muted">{{ $key->user->email }}</small>
                                @elseif($key->seller)
                                    <div class="fw-semibold">{{ $key->seller->f_name }} {{ $key->seller->l_name }}</div>
                                    <small class="text-muted">{{ $key->seller->email }}</small>
                                    <small class="badge bg-info text-dark ms-1">{{ translate('vendor') }}</small>
                                @else
                                    <span class="text-muted fst-italic">—</span>
                                @endif
                            </td>
                            <td>{{ $key->rate_limit_per_minute }}/min</td>
                            <td class="text-center">{{ number_format($key->total_requests) }}</td>
                            <td>
                                @if($key->last_used_at)
                                    <span title="{{ $key->last_used_at }}">{{ $key->last_used_at->diffForHumans() }}</span>
                                    @if($key->last_used_ip)
                                        <br><small class="text-muted">{{ $key->last_used_ip }}</small>
                                    @endif
                                @else
                                    <span class="text-muted fst-italic">{{ translate('never') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php $s = $key->status ?? ($key->is_active ? 'active' : 'inactive'); @endphp
                                @if($s === 'active')
                                    <span class="badge bg-success">{{ translate('active') }}</span>
                                @elseif($s === 'pending')
                                    <span class="badge bg-warning text-dark">{{ translate('pending') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ translate('inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    @if(($key->status ?? '') === 'pending')
                                        <button type="button" class="btn btn-success btn-sm approve-key-btn"
                                                data-id="{{ $key->id }}" data-name="{{ $key->name }}"
                                                data-request-note="{{ $key->request_note ?? '' }}"
                                                title="{{ translate('approve') }}">
                                            <i class="fi fi-sr-check me-1"></i>{{ translate('approve') }}
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm reject-key-btn"
                                                data-id="{{ $key->id }}" data-name="{{ $key->name }}"
                                                data-request-note="{{ $key->request_note ?? '' }}"
                                                title="{{ translate('reject') }}">
                                            <i class="fi fi-rr-ban me-1"></i>{{ translate('reject') }}
                                        </button>
                                    @else
                                        <form method="post" action="{{ route('admin.reseller-keys.toggle-status') }}"
                                              class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $key->id }}">
                                            <button type="submit" class="btn-icon"
                                                    title="{{ ($key->status ?? '') === 'active' ? translate('deactivate') : translate('activate') }}">
                                                <i class="fi fi-rr-{{ ($key->status ?? '') === 'active' ? 'ban' : 'check' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.reseller-keys.edit', $key->id) }}"
                                       class="btn-icon" title="{{ translate('edit') }}">
                                        <i class="fi fi-rr-pencil"></i>
                                    </a>
                                    <a href="{{ route('admin.reseller-keys.logs', $key->id) }}"
                                       class="btn-icon" title="{{ translate('view_logs') }}">
                                        <i class="fi fi-rr-list"></i>
                                    </a>
                                    <form method="post" action="{{ route('admin.reseller-keys.delete') }}"
                                          class="d-inline delete-key-form">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $key->id }}">
                                        <button type="button" class="btn-icon btn-danger-icon delete-key-btn">
                                            <i class="fi fi-rr-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <i class="fi fi-sr-inbox-in fs-1 text-muted"></i>
                                    <span class="text-muted">{{ translate('no_api_keys_found') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-sm-end">
                {{ $keys->links() }}
            </div>
        </div>
    </div>
</div>

{{-- Generate Key Modal --}}
<div class="modal fade" id="generateKeyModal" tabindex="-1" aria-labelledby="generateKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="generateKeyModalLabel">
                    {{ translate('generate_new_api_key') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.reseller-keys.generate') }}" method="POST">
                @csrf
                <div class="modal-body d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-semibold">{{ translate('key_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="{{ translate('e_g_partner_name_api') }}" required>
                        <small class="text-muted">{{ translate('descriptive_name_for_this_key') }}</small>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">{{ translate('customer') }} <span class="text-danger">*</span></label>
                        <select id="reseller-key-user-select" name="user_id" class="form-control w-100" required
                                data-placeholder="{{ translate('search_by_name_email_or_phone') }}">
                        </select>
                        <small class="text-muted d-block mt-1">{{ translate('customer_account_that_owns_this_key') }}</small>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        {{ translate('cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fi fi-rr-key me-1"></i>{{ translate('generate') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

{{-- Approve Modal --}}
<div class="modal fade" id="approveKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fi fi-sr-check text-success me-2"></i>{{ translate('approve_api_key') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.reseller-keys.approve') }}">
                @csrf
                <input type="hidden" name="id" id="approve-key-id">
                <div class="modal-body d-flex flex-column gap-3">
                    <p class="mb-0">{{ translate('approving_key') }}: <strong id="approve-key-name"></strong></p>
                    <div id="approve-request-note-wrap" class="alert alert-info mb-0 d-none">
                        <p class="mb-1 fw-semibold small">{{ translate('vendor_request_note') }}:</p>
                        <p class="mb-0 fst-italic" id="approve-request-note-text"></p>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">{{ translate('admin_note') }} <span class="text-muted fw-normal">({{ translate('optional') }})</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" maxlength="500"
                                  placeholder="{{ translate('optional_note_to_vendor') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fi fi-sr-check me-1"></i>{{ translate('approve') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fi fi-rr-ban text-danger me-2"></i>{{ translate('reject_api_key') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.reseller-keys.reject') }}">
                @csrf
                <input type="hidden" name="id" id="reject-key-id">
                <div class="modal-body d-flex flex-column gap-3">
                    <p class="mb-0">{{ translate('rejecting_key') }}: <strong id="reject-key-name"></strong></p>
                    <div id="reject-request-note-wrap" class="alert alert-info mb-0 d-none">
                        <p class="mb-1 fw-semibold small">{{ translate('vendor_request_note') }}:</p>
                        <p class="mb-0 fst-italic" id="reject-request-note-text"></p>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">{{ translate('reason') }} <span class="text-muted fw-normal">({{ translate('optional') }})</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" maxlength="500"
                                  placeholder="{{ translate('reason_visible_to_vendor') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fi fi-rr-ban me-1"></i>{{ translate('reject') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('script')
<script>
    'use strict';

    // Customer search dropdown inside modal — must use dropdownParent to render above modal overlay
    $('#generateKeyModal').on('shown.bs.modal', function () {
        if (!$('#reseller-key-user-select').hasClass('select2-hidden-accessible')) {
            $('#reseller-key-user-select').select2({
                dropdownParent: $('#generateKeyModal'),
                placeholder: $('#reseller-key-user-select').data('placeholder'),
                allowClear: true,
                ajax: {
                    url: $('#get-customer-list-without-all-customer-route').data('action'),
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return { searchValue: params.term, page: params.page };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                }
            });
        }
    });

    // Approve modal
    $(document).on('click', '.approve-key-btn', function () {
        var note = $(this).data('request-note');
        $('#approve-key-id').val($(this).data('id'));
        $('#approve-key-name').text($(this).data('name'));
        if (note) {
            $('#approve-request-note-text').text(note);
            $('#approve-request-note-wrap').removeClass('d-none');
        } else {
            $('#approve-request-note-wrap').addClass('d-none');
        }
        new bootstrap.Modal(document.getElementById('approveKeyModal')).show();
    });

    // Reject modal
    $(document).on('click', '.reject-key-btn', function () {
        var note = $(this).data('request-note');
        $('#reject-key-id').val($(this).data('id'));
        $('#reject-key-name').text($(this).data('name'));
        if (note) {
            $('#reject-request-note-text').text(note);
            $('#reject-request-note-wrap').removeClass('d-none');
        } else {
            $('#reject-request-note-wrap').addClass('d-none');
        }
        new bootstrap.Modal(document.getElementById('rejectKeyModal')).show();
    });

    // Copy to clipboard
    $(document).on('click', '.copy-btn', function () {
        let targetId = $(this).data('target');
        let val = document.getElementById(targetId).value;
        navigator.clipboard.writeText(val).then(() => {
            let btn = $(this);
            btn.html('<i class="fi fi-sr-check text-success"></i>');
            setTimeout(() => btn.html('<i class="fi fi-rr-copy"></i>'), 2000);
        });
    });

    // Delete confirmation
    $(document).on('click', '.delete-key-btn', function () {
        const getText = document.getElementById('get-confirm-and-cancel-button-text-for-delete');
        let form = $(this).closest('.delete-key-form');
        Swal.fire({
            title: getText?.dataset.sure || 'Are you sure?',
            text: getText?.dataset.text || 'This action cannot be undone!',
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
