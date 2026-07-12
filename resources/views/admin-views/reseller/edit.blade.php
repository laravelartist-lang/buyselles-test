@extends('layouts.admin.app')

@section('title', translate('edit_api_key') . ' — ' . $key->name)

@section('content')
<div class="content container-fluid">

    {{-- One-time display after generate or regenerate --}}
    @if(session('raw_api_key'))
    <div class="alert alert-success border-0 mb-4">
        <h5 class="fw-bold mb-3"><i class="fi fi-sr-key me-2"></i>{{ translate('new_api_key_generated') }}</h5>
        <p class="mb-2 text-danger fw-semibold">{{ translate('these_credentials_are_shown_only_once_and_cannot_be_recovered_store_them_securely') }}</p>
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

    {{-- Breadcrumb --}}
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.reseller-keys.list') }}" class="text-muted text-decoration-none">
            <i class="fi fi-rr-arrow-left me-1"></i>{{ translate('reseller_api_keys') }}
        </a>
        <span class="text-muted">/</span>
        <span class="fw-semibold">{{ $key->name }}</span>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="mb-1">
                {{ translate('edit_api_key') }}
            </h3>
            <span class="text-muted small">#{{ $key->id }} — {{ $key->name }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @php $s = $key->status ?? ($key->is_active ? 'active' : 'inactive'); @endphp
            @if($s === 'active')
                <span class="badge bg-success fs-6 px-3 py-2"><i class="fi fi-rr-check me-1"></i>{{ translate('active') }}</span>
            @elseif($s === 'pending')
                <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="fi fi-rr-time-half-past me-1"></i>{{ translate('pending') }}</span>
            @else
                <span class="badge bg-secondary fs-6 px-3 py-2"><i class="fi fi-rr-ban me-1"></i>{{ translate('inactive') }}</span>
            @endif
            <a href="{{ route('admin.reseller-keys.logs', $key->id) }}" class="btn btn-outline-secondary">
                <i class="fi fi-rr-list me-1"></i>{{ translate('view_logs') }}
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.reseller-keys.update', $key->id) }}">
        @csrf
        <div class="row g-4">

            {{-- ─── Left column ─── --}}
            <div class="col-lg-8 d-flex flex-column gap-4">

                {{-- General Settings --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fi fi-rr-settings me-2"></i>{{ translate('general_settings') }}</h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label class="form-label fw-semibold">{{ translate('key_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $key->name) }}" maxlength="100" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label fw-semibold">{{ translate('rate_limit_per_minute') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="rate_limit_per_minute"
                                       class="form-control @error('rate_limit_per_minute') is-invalid @enderror"
                                       value="{{ old('rate_limit_per_minute', $key->rate_limit_per_minute) }}"
                                       min="1" max="600" required>
                                <span class="input-group-text text-muted">{{ translate('req') }}/{{ translate('min') }}</span>
                            </div>
                            <small class="text-muted">{{ translate('rate_limit_help') }}</small>
                            @error('rate_limit_per_minute')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label fw-semibold">{{ translate('status') }} <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" {{ old('status', $key->status) === 'active' ? 'selected' : '' }}>{{ translate('active') }}</option>
                                <option value="inactive" {{ old('status', $key->status) === 'inactive' ? 'selected' : '' }}>{{ translate('inactive') }}</option>
                            </select>
                            @if(($key->status ?? '') === 'pending')
                                <small class="text-warning"><i class="fi fi-sr-triangle-warning me-1"></i>{{ translate('saving_pending_key_will_activate_it') }}</small>
                            @endif
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label fw-semibold">{{ translate('admin_note') }}</label>
                            <textarea name="admin_note" class="form-control" rows="3" maxlength="500"
                                      placeholder="{{ translate('internal_note_optional') }}">{{ old('admin_note', $key->admin_note) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fi fi-rr-lock me-2"></i>{{ translate('api_permissions') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">{{ translate('permissions_help') }}</p>
                        <div class="row g-3">
                            @foreach($allPermissions as $permKey => $permLabel)
                                @php $checked = in_array($permKey, old('permissions', $key->permissions ?? [])); @endphp
                                <div class="col-sm-6">
                                    <div class="form-check form-check-custom p-3 border rounded {{ $checked ? 'border-primary bg-primary text-white' : '' }}">
                                        <input class="form-check-input" type="checkbox"
                                               name="permissions[]" value="{{ $permKey }}"
                                               id="perm_{{ str_replace('.', '_', $permKey) }}"
                                               {{ $checked ? 'checked' : '' }}>
                                        <label class="form-check-label w-100 cursor-pointer"
                                               for="perm_{{ str_replace('.', '_', $permKey) }}">
                                            <span class="fw-semibold d-block">{{ $permLabel }}</span>
                                            <code class="{{ $checked ? 'small text-white opacity-75' : 'text-muted small' }}">{{ $permKey }}</code>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if(empty($key->permissions))
                            <div class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0 py-2">
                                <i class="fi fi-sr-triangle-warning"></i>
                                <span>{{ translate('no_permissions_warning') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- IP Whitelist --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fi fi-rr-network me-2"></i>{{ translate('ip_whitelist') }}</h5>
                        <span class="badge bg-primary text-white" id="ip-count-badge">
                            {{ count($key->allowed_ips ?? []) }} {{ translate('IPs') }}
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">

                        {{-- Format guide --}}
                        <div class="border rounded p-3 bg-light">
                            <div class="fw-semibold small mb-2">
                                <i class="fi fi-sr-info me-1 text-primary"></i>Accepted formats
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6 d-flex align-items-start gap-2 small">
                                    <i class="fi fi-sr-check text-success mt-1 flex-shrink-0"></i>
                                    <div><code>192.168.1.100</code><br><span class="text-muted">Standard IPv4</span></div>
                                </div>
                                <div class="col-sm-6 d-flex align-items-start gap-2 small">
                                    <i class="fi fi-sr-check text-success mt-1 flex-shrink-0"></i>
                                    <div><code>10.0.0.1</code> &nbsp;<code>172.16.0.5</code><br><span class="text-muted">Private IPv4</span></div>
                                </div>
                                <div class="col-sm-6 d-flex align-items-start gap-2 small">
                                    <i class="fi fi-sr-check text-success mt-1 flex-shrink-0"></i>
                                    <div><code>2001:db8::1</code><br><span class="text-muted">IPv6 (full or compressed)</span></div>
                                </div>
                                <div class="col-sm-6 d-flex align-items-start gap-2 small">
                                    <i class="fi fi-sr-cross text-danger mt-1 flex-shrink-0"></i>
                                    <div><code>192.168.1.0/24</code><br><span class="text-muted">CIDR — <strong>not supported</strong></span></div>
                                </div>
                            </div>
                            <div class="pt-2 border-top small text-muted">
                                Type an IP and press <kbd>Enter</kbd> or click <strong>Add</strong>.
                                You can paste multiple IPs separated by commas or newlines.
                                Leave the list empty to allow all IPs.
                            </div>
                        </div>

                        {{-- Current tag list (server-rendered so it always shows) --}}
                        <div>
                            <label class="form-label fw-semibold mb-2">Current whitelist</label>
                            <div id="ip-tag-list" class="d-flex flex-wrap gap-2 mb-1"
                                 style="min-height: 36px;">
                                @forelse($key->allowed_ips ?? [] as $ip)
                                    <span class="ip-tag d-inline-flex align-items-center gap-1
                                                 badge bg-secondary px-2 py-1 fw-normal"
                                          data-ip="{{ $ip }}"
                                          style="font-family:monospace; font-size:0.82rem;">
                                        <i class="fi fi-rr-globe-alt" style="font-size:0.75rem;"></i>
                                        <span>{{ $ip }}</span>
                                        <button type="button"
                                                class="ip-tag-remove"
                                                data-ip="{{ $ip }}"
                                                style="background:none; border:none; color:#fff;
                                                       font-size:1rem; line-height:1; padding:0 0 0 4px;
                                                       cursor:pointer; opacity:0.8;"
                                                title="Remove {{ $ip }}">&times;</button>
                                    </span>
                                @empty
                                    <span id="ip-empty-notice" class="text-muted small fst-italic">
                                        No IPs saved — all IPs are allowed.
                                    </span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Add new IP --}}
                        <div>
                            <label class="form-label fw-semibold">Add IP address</label>
                            <div class="input-group">
                                <input type="text" id="ip-text-input"
                                       class="form-control font-monospace"
                                       placeholder="e.g. 192.168.1.100"
                                       autocomplete="off" spellcheck="false">
                                <button type="button" class="btn btn-outline-primary" id="ip-add-btn">
                                    <i class="fi fi-rr-plus me-1"></i>Add
                                </button>
                            </div>
                            <div id="ip-error-msg" class="text-danger small mt-1" style="display:none;"></div>
                        </div>

                        {{-- Hidden field carries the final list on POST --}}
                        <textarea name="allowed_ips" id="ip-hidden-value"
                                  class="d-none" aria-hidden="true">{{ implode("\n", $key->allowed_ips ?? []) }}</textarea>

                    </div>
                </div>

            </div>

            {{-- ─── Right column: key info ─── --}}
            <div class="col-lg-4 d-flex flex-column gap-4">

                {{-- Save button --}}
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fi fi-rr-check me-1"></i>{{ translate('save_changes') }}
                </button>

                {{-- Owner info --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fi fi-rr-user me-2"></i>{{ translate('owner') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($key->user)
                            <div class="fw-semibold">{{ $key->user->name }}</div>
                            <div class="text-muted small">{{ $key->user->email }}</div>
                            <span class="badge bg-primary mt-1">{{ translate('customer') }}</span>
                        @elseif($key->seller)
                            <div class="fw-semibold">{{ $key->seller->f_name }} {{ $key->seller->l_name }}</div>
                            <div class="text-muted small">{{ $key->seller->email }}</div>
                            <span class="badge bg-info text-dark mt-1">{{ translate('vendor') }}</span>
                        @else
                            <span class="text-muted fst-italic">—</span>
                        @endif

                        @if($key->request_note)
                            <div class="mt-3 p-2 bg-warning bg-opacity-10 border border-warning rounded small">
                                <div class="fw-semibold text-warning-emphasis mb-1"><i class="fi fi-sr-info me-1"></i>{{ translate('vendor_request_note') }}</div>
                                <div>{{ $key->request_note }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Key info --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fi fi-rr-key me-2"></i>{{ translate('key_details') }}</h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        @unless(session('raw_api_key'))
                        <div class="p-3 rounded border bg-light">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fi fi-sr-lock text-muted"></i>
                                <span class="fw-semibold small">{{ translate('api_key') }}</span>
                            </div>
                            <div class="font-monospace text-muted small">rslr_••••••••••••••••••••••••••••••••••••••••</div>
                            <div class="mt-2 d-flex align-items-center gap-2 mb-1">
                                <i class="fi fi-sr-lock text-muted"></i>
                                <span class="fw-semibold small">{{ translate('api_secret') }}</span>
                            </div>
                            <div class="font-monospace text-muted small">••••••••••••••••••••••••••••••••••••••••••••••••</div>
                            <div class="mt-2 small text-muted fst-italic">
                                <i class="fi fi-sr-info me-1"></i>{{ translate('credentials_are_hidden_use_regenerate_to_get_new_ones') }}
                            </div>
                        </div>
                        @endunless
                        <hr class="my-1">
                        <div>
                            <div class="text-muted small">{{ translate('wallet_balance') }}</div>
                            <div class="fw-semibold text-success">${{ number_format((float) $accountWalletBalance, 2) }}</div>
                            <div class="text-muted small fst-italic mt-1">
                                {{ translate('partner_wallet_uses_linked_account_balance') }}
                            </div>
                        </div>
                        <hr class="my-1">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-muted small">{{ translate('total_requests') }}</div>
                                <div class="fw-semibold">{{ number_format($key->total_requests) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">{{ translate('created_at') }}</div>
                                <div class="fw-semibold" title="{{ $key->created_at }}">
                                    {{ $key->created_at ? $key->created_at->format('d M Y') : '—' }}
                                </div>
                            </div>
                        </div>
                        @if($key->approved_at)
                            <div>
                                <div class="text-muted small">{{ translate('approved_at') }}</div>
                                <div class="fw-semibold" title="{{ $key->approved_at }}">
                                    {{ $key->approved_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        @endif
                        @if($key->last_used_at)
                            <div>
                                <div class="text-muted small">{{ translate('last_used') }}</div>
                                <div class="fw-semibold" title="{{ $key->last_used_at }}">
                                    {{ $key->last_used_at->diffForHumans() }}
                                </div>
                                @if($key->last_used_ip)
                                    <div class="text-muted small">{{ $key->last_used_ip }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Quick actions --}}
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fi fi-rr-bolt me-2"></i>{{ translate('quick_actions') }}</h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-2">
                        <button type="button" class="btn btn-outline-warning w-100" id="regenerate-key-btn">
                            <i class="fi fi-rr-refresh me-1"></i>{{ translate('regenerate_key') }}
                        </button>
                        @if(($key->status ?? '') !== 'pending')
                            <button type="button"
                                    class="btn btn-outline-{{ ($key->status ?? '') === 'active' ? 'warning' : 'success' }} w-100"
                                    id="toggle-status-btn"
                                    data-current-status="{{ $key->status ?? '' }}">
                                <i class="fi fi-rr-{{ ($key->status ?? '') === 'active' ? 'ban' : 'check' }} me-1"></i>
                                {{ ($key->status ?? '') === 'active' ? translate('deactivate_key') : translate('activate_key') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline-danger w-100" id="delete-key-btn">
                            <i class="fi fi-rr-trash me-1"></i>{{ translate('delete_key') }}
                        </button>
                    </div>
                </div>

            </div>{{-- /right col --}}

        </div>{{-- /row --}}
    </form>

    {{-- Standalone forms placed OUTSIDE the main update-form to avoid nested-form HTML issue --}}
    <form method="POST" action="{{ route('admin.reseller-keys.toggle-status') }}" id="toggle-status-form" class="d-none">
        @csrf
        <input type="hidden" name="id" value="{{ $key->id }}">
    </form>
    <form method="POST" action="{{ route('admin.reseller-keys.delete') }}" id="delete-key-form" class="d-none">
        @csrf
        <input type="hidden" name="id" value="{{ $key->id }}">
    </form>
    <form method="POST" action="{{ route('admin.reseller-keys.regenerate', $key->id) }}" id="regenerate-key-form" class="d-none">
        @csrf
    </form>

</div>
@endsection

@push('script')
<span id="get-confirm-and-cancel-button-text-for-delete"
      data-sure="{{ translate('are_you_sure') }}"
      data-text="{{ translate('this_action_cannot_be_undone') }}"
      data-confirm="{{ translate('yes_delete_it') }}"
      data-cancel="{{ translate('cancel') }}"></span>

<script>
(function () {
    // ─── Copy credentials ─────────────────────────────────────────────
    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) { return; }
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).catch(function () {
                document.execCommand('copy');
            });
        });
    });

    // ─── Regenerate key confirmation ──────────────────────────────────
    var regenerateBtn = document.getElementById('regenerate-key-btn');
    if (regenerateBtn) {
        regenerateBtn.addEventListener('click', function () {
            Swal.fire({
                title:              '{{ translate('regenerate_api_key') }}?',
                text:               '{{ translate('the_current_credentials_will_stop_working_immediately') }}',
                icon:               'warning',
                showCancelButton:   true,
                confirmButtonColor: '#fd7e14',
                cancelButtonColor:  '#6c757d',
                cancelButtonText:   '{{ translate('cancel') }}',
                confirmButtonText:  '{{ translate('yes_regenerate') }}',
                reverseButtons:     true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('regenerate-key-form').submit();
                }
            });
        });
    }

    // ─── Toggle status confirmation ───────────────────────────────────
    var toggleBtn = document.getElementById('toggle-status-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var isActive = this.dataset.currentStatus === 'active';
            Swal.fire({
                title:              isActive ? 'Deactivate this key?' : 'Activate this key?',
                text:               isActive
                                        ? 'The vendor will no longer be able to use this API key.'
                                        : 'The vendor will regain access with this key.',
                icon:               'warning',
                showCancelButton:   true,
                confirmButtonColor: isActive ? '#d33' : '#28a745',
                cancelButtonColor:  '#6c757d',
                cancelButtonText:   'Cancel',
                confirmButtonText:  isActive ? 'Yes, deactivate' : 'Yes, activate',
                reverseButtons:     true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('toggle-status-form').submit();
                }
            });
        });
    }

    // ─── Delete key confirmation ──────────────────────────────────────
    var deleteBtn = document.getElementById('delete-key-btn');
    if (deleteBtn) {
        var t = document.getElementById('get-confirm-and-cancel-button-text-for-delete');
        deleteBtn.addEventListener('click', function () {
            Swal.fire({
                title:              t ? t.dataset.sure    : 'Are you sure?',
                text:               t ? t.dataset.text    : 'You will not be able to revert this!',
                icon:               'warning',
                showCancelButton:   true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor:  '#d33',
                cancelButtonText:   t ? t.dataset.cancel  : 'Cancel',
                confirmButtonText:  t ? t.dataset.confirm : 'Yes, delete it!',
                reverseButtons:     true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('delete-key-form').submit();
                }
            });
        });
    }

    // ─── Permission checkbox highlight ───────────────────────────────
    document.querySelectorAll('.form-check input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var box = this.closest('.form-check');
            if (this.checked) {
                box.classList.add('border-primary', 'bg-primary', 'text-white');
                var codeEl = box.querySelector('code');
                if (codeEl) { codeEl.classList.remove('text-muted'); codeEl.classList.add('text-white', 'opacity-75'); }
            } else {
                box.classList.remove('border-primary', 'bg-primary', 'text-white');
                var codeEl = box.querySelector('code');
                if (codeEl) { codeEl.classList.add('text-muted'); codeEl.classList.remove('text-white', 'opacity-75'); }
            }
        });
    });

    // ─── IP Tag Input ─────────────────────────────────────────────────
    var tagList    = document.getElementById('ip-tag-list');
    var textInput  = document.getElementById('ip-text-input');
    var addBtn     = document.getElementById('ip-add-btn');
    var hiddenField= document.getElementById('ip-hidden-value');
    var errorMsg   = document.getElementById('ip-error-msg');
    var countBadge = document.getElementById('ip-count-badge');
    var saveForm   = tagList ? tagList.closest('form') : null;

    if (!tagList || !textInput || !hiddenField) { return; } // guard if elements missing

    // IPv4 + IPv6 validation (no CIDR)
    function isValidIp(ip) {
        var ipv4 = /^(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)){3}$/;
        var ipv6 = /^[0-9a-fA-F:]{2,39}$/ ;
        // Simple but sufficient checks
        if (ipv4.test(ip)) { return true; }
        // IPv6: must contain at least one colon, valid hex groups
        if (ip.indexOf(':') !== -1) {
            var full = /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/;
            var comp = /^(([0-9a-fA-F]{1,4}:)*[0-9a-fA-F]{1,4})?::(([0-9a-fA-F]{1,4}:)*[0-9a-fA-F]{1,4})?$/;
            return full.test(ip) || comp.test(ip);
        }
        return false;
    }

    function showError(msg, isInfo) {
        errorMsg.textContent = msg;
        errorMsg.style.color = isInfo ? '#0dcaf0' : '#dc3545';
        errorMsg.style.display = 'block';
        setTimeout(function () { errorMsg.style.display = 'none'; }, 3500);
    }

    function getCurrentIps() {
        var tags = tagList.querySelectorAll('.ip-tag');
        var ips = [];
        tags.forEach(function (el) { ips.push(el.dataset.ip); });
        return ips;
    }

    function syncHidden() {
        hiddenField.value = getCurrentIps().join('\n');
        if (countBadge) { countBadge.textContent = getCurrentIps().length + ' IPs'; }
        // show/hide empty notice
        var notice = document.getElementById('ip-empty-notice');
        if (!notice) {
            notice = document.createElement('span');
            notice.id = 'ip-empty-notice';
            notice.className = 'text-muted small fst-italic';
            notice.textContent = 'No IPs saved — all IPs are allowed.';
            tagList.appendChild(notice);
        }
        notice.style.display = getCurrentIps().length === 0 ? '' : 'none';
    }

    function createTagEl(ip) {
        var span = document.createElement('span');
        span.className = 'ip-tag d-inline-flex align-items-center gap-1 badge bg-secondary px-2 py-1 fw-normal';
        span.dataset.ip = ip;
        span.style.fontFamily = 'monospace';
        span.style.fontSize = '0.82rem';

        var icon = document.createElement('i');
        icon.className = 'fi fi-rr-globe-alt';
        icon.style.fontSize = '0.75rem';

        var label = document.createElement('span');
        label.textContent = ip;

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'ip-tag-remove';
        removeBtn.dataset.ip = ip;
        removeBtn.setAttribute('title', 'Remove ' + ip);
        removeBtn.textContent = '×';
        removeBtn.style.cssText =
            'background:none; border:none; color:#fff; font-size:1.1rem; ' +
            'line-height:1; padding:0 0 0 4px; cursor:pointer; opacity:0.85;';

        span.appendChild(icon);
        span.appendChild(label);
        span.appendChild(removeBtn);
        return span;
    }

    function addIp(rawIp) {
        var ip = rawIp.trim();
        if (!ip) { return false; }

        if (!isValidIp(ip)) {
            showError('"' + ip + '" is not a valid IPv4 or IPv6 address.');
            return false;
        }

        var existing = getCurrentIps();
        if (existing.indexOf(ip) !== -1) {
            showError('"' + ip + '" is already in the whitelist.');
            // flash the duplicate tag orange
            var tags = tagList.querySelectorAll('.ip-tag');
            tags.forEach(function (el) {
                if (el.dataset.ip === ip) {
                    el.style.background = '#fd7e14';
                    setTimeout(function () { el.style.background = ''; }, 1200);
                }
            });
            return false;
        }

        var notice = document.getElementById('ip-empty-notice');
        if (notice) { notice.style.display = 'none'; }

        tagList.appendChild(createTagEl(ip));
        syncHidden();
        return true;
    }

    // Event delegation for remove buttons — confirm before removing
    tagList.addEventListener('click', function (e) {
        var btn = e.target.closest('.ip-tag-remove');
        if (!btn) { return; }
        var ip  = btn.dataset.ip || btn.closest('.ip-tag')?.dataset.ip || '';
        var tag = btn.closest('.ip-tag');
        if (!tag) { return; }
        Swal.fire({
            title:              'Remove IP?',
            html:               'Remove <code style="font-size:0.95em;">' + ip + '</code> from the whitelist?',
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonColor: '#d33',
            cancelButtonColor:  '#6c757d',
            cancelButtonText:   'Cancel',
            confirmButtonText:  'Yes, remove it',
            reverseButtons:     true,
        }).then(function (result) {
            if (result.isConfirmed) {
                tag.remove();
                syncHidden();
            }
        });
    });

    // Add button click
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            if (addIp(textInput.value)) {
                textInput.value = '';
                textInput.focus();
            }
        });
    }

    // Enter key in the text input
    textInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            if (addIp(textInput.value)) {
                textInput.value = '';
            }
        }
        // Comma also triggers add
        if (e.key === ',') {
            e.preventDefault();
            if (addIp(textInput.value)) { textInput.value = ''; }
        }
        // Backspace on empty removes last tag
        if (e.key === 'Backspace' && textInput.value === '') {
            var tags = tagList.querySelectorAll('.ip-tag');
            if (tags.length > 0) {
                tags[tags.length - 1].remove();
                syncHidden();
            }
        }
    });

    // Paste: bulk-add comma/newline separated IPs
    textInput.addEventListener('paste', function (e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        var parts = pasted.split(/[\r\n,;\s]+/);
        var added = 0, dupes = 0, invalid = 0;
        parts.forEach(function (p) {
            p = p.trim();
            if (!p) { return; }
            if (!isValidIp(p)) { invalid++; return; }
            if (getCurrentIps().indexOf(p) !== -1) { dupes++; return; }
            addIp(p);
            added++;
        });
        textInput.value = '';
        var msgs = [];
        if (added)   { msgs.push(added + ' IP(s) added'); }
        if (dupes)   { msgs.push(dupes + ' duplicate(s) skipped'); }
        if (invalid) { msgs.push(invalid + ' invalid entry(ies) ignored'); }
        if (msgs.length) { showError(msgs.join(' · '), true); }
    });

    // Sync hidden field before form submits (catch any leftover text)
    if (saveForm) {
        saveForm.addEventListener('submit', function () {
            if (textInput.value.trim()) {
                addIp(textInput.value.trim());
                textInput.value = '';
            }
            syncHidden();
        });
    }

    // Initial sync (server-rendered tags already in DOM — just update the count)
    syncHidden();

}());
</script>
@endpush
