@extends('layouts.admin.app')

@section('title', translate('add_product_mapping'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">{{ translate('add_product_supplier_mapping') }}</h3>

            <form action="{{ route('admin.supplier.mapping.store') }}" method="post">
                @csrf

                <div class="row gy-3">
                    @include('admin-views.supplier.partials._mapping-product-picker', [
                        'categories' => $categories,
                    ])

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier') }} <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <select name="supplier_api_id" id="supplier_api_id" class="form-control" required>
                                        <option value="">{{ translate('select_supplier') }}</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}"
                                                    data-supports-direct-topup="{{ $supplier->supports_direct_top_up ? '1' : '0' }}"
                                                    {{ old('supplier_api_id') == $supplier->id ? 'selected' : '' }}>
                                                {{ $supplier->name }} ({{ $supplier->driver }}){{ $supplier->is_active ? '' : ' — '.translate('inactive') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" id="browse-catalog-btn"
                                        class="btn btn-outline-primary text-nowrap"
                                        style="height:38px;display:none;"
                                        data-bs-toggle="modal" data-bs-target="#catalogModal">
                                    <i class="fi fi-rr-search"></i> {{ translate('browse_catalog') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier_product_id_SKU') }} <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_product_id" id="supplier_product_id" class="form-control"
                                   placeholder="{{ translate('ex') }}: PROD-12345"
                                   value="{{ old('supplier_product_id') }}" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier_product_name') }}</label>
                            <input type="text" name="supplier_product_name" id="supplier_product_name" class="form-control"
                                   placeholder="{{ translate('optional_cached_name') }}"
                                   value="{{ old('supplier_product_name') }}">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('cost_price') }} <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" id="cost_price" class="form-control" step="0.01" min="0"
                                   value="{{ old('cost_price', '0.00') }}" required>
                        </div>
                    </div>

                    <div class="col-lg-2">
                        <div class="form-group">
                            <label class="form-label">{{ translate('currency') }}</label>
                            <input type="text" name="cost_currency" id="cost_currency" class="form-control" maxlength="3"
                                   value="{{ old('cost_currency', 'USD') }}">
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('markup_type') }} <span class="text-danger">*</span></label>
                            <select name="markup_type" class="form-control" required>
                                <option value="percent" {{ old('markup_type', 'percent') == 'percent' ? 'selected' : '' }}>{{ translate('percent') }} (%)</option>
                                <option value="flat" {{ old('markup_type') == 'flat' ? 'selected' : '' }}>{{ translate('flat') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('markup_value') }}</label>
                            <input type="number" name="markup_value" class="form-control" step="0.01" min="0"
                                   value="{{ old('markup_value', '10') }}">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('priority') }} <span class="text-danger">*</span></label>
                            <input type="number" name="priority" class="form-control" min="0"
                                   value="{{ old('priority', 0) }}" required>
                            <small class="text-muted">{{ translate('lower_number_=_higher_priority') }}</small>
                        </div>
                    </div>

                    {{-- ─── Customizable / Variable Amount ──────────────────────────── --}}
                    <div class="col-lg-12">
                        <hr class="my-2">
                        <h5 class="mb-3">{{ translate('variable_amount_settings') }}</h5>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_customizable" value="1"
                                   id="customizable-toggle" {{ old('is_customizable') ? 'checked' : '' }}>
                            <label class="form-check-label" for="customizable-toggle">
                                {{ translate('customizable_variable_amount') }}
                            </label>
                            <br>
                            <small class="text-muted">{{ translate('when_enabled_customer_can_enter_a_custom_amount_instead_of_a_fixed_denomination') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 customizable-fields" style="{{ old('is_customizable') ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('minimum_amount') }} <span class="text-danger">*</span></label>
                            <input type="number" name="min_amount" class="form-control" step="0.01" min="0"
                                   value="{{ old('min_amount') }}"
                                   placeholder="{{ translate('ex') }}: 5.00">
                            <small class="text-muted">{{ translate('minimum_value_the_customer_can_enter') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 customizable-fields" style="{{ old('is_customizable') ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('maximum_amount') }} <span class="text-danger">*</span></label>
                            <input type="number" name="max_amount" class="form-control" step="0.01" min="0"
                                   value="{{ old('max_amount') }}"
                                   placeholder="{{ translate('ex') }}: 500.00">
                            <small class="text-muted">{{ translate('maximum_value_the_customer_can_enter') }}</small>
                        </div>
                    </div>

                    {{-- ─── Direct Top-Up ──────────────────────────────────────── --}}
                    <div class="col-lg-12" id="direct-topup-section" style="display:none;">
                        <hr class="my-2">
                        <h5 class="mb-3">{{ translate('direct_topup_settings') ?: 'Direct Top-Up Settings' }}</h5>
                    </div>

                    <div class="col-lg-12 direct-topup-section-content" style="display:none;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_direct_topup" value="1"
                                   id="direct-topup-toggle"
                                   data-validate-url="{{ route('admin.supplier.mapping.validate-direct-topup') }}"
                                   {{ old('is_direct_topup') ? 'checked' : '' }}>
                            <label class="form-check-label" for="direct-topup-toggle">
                                {{ translate('enable_direct_topup') ?: 'Enable Direct Top-Up' }}
                            </label>
                            <br>
                            <small class="text-muted">{{ translate('direct_topup_mapping_hint') ?: 'When enabled, customers can enter an account ID and quantity. The supplier must support direct top-up to enable this.' }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 direct-topup-fields direct-topup-section-content" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_account_label') ?: 'Account ID Input Label' }}</label>
                            <input type="text" name="direct_topup_account_label" class="form-control"
                                   value="{{ old('direct_topup_account_label') }}"
                                   placeholder="{{ translate('direct_topup_account_label_placeholder') ?: 'Enter Account ID' }}">
                        </div>
                    </div>

                    <div class="col-lg-3 direct-topup-fields direct-topup-section-content" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_min_quantity') ?: 'Minimum Quantity' }}</label>
                            <input type="number" step="0.0001" min="0" name="direct_topup_min_quantity" class="form-control"
                                   value="{{ old('direct_topup_min_quantity') }}">
                        </div>
                    </div>

                    <div class="col-lg-3 direct-topup-fields direct-topup-section-content" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_max_quantity') ?: 'Maximum Quantity' }}</label>
                            <input type="number" step="0.0001" min="0" name="direct_topup_max_quantity" class="form-control"
                                   value="{{ old('direct_topup_max_quantity') }}">
                        </div>
                    </div>

                    <div class="col-lg-6 direct-topup-fields direct-topup-section-content" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_price_per_unit') ?: 'Price Per Unit' }}</label>
                            <input type="number" step="0.00000001" min="0" name="direct_topup_price_per_unit" class="form-control"
                                   value="{{ old('direct_topup_price_per_unit') }}">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fi fi-sr-check"></i> {{ translate('save') }}
                    </button>
                    <a href="{{ route('admin.supplier.mapping.list') }}" class="btn btn-secondary">
                        {{ translate('cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ─── Catalog Browse Modal ──────────────────────────────────────────────── --}}
<div class="modal fade" id="catalogModal" tabindex="-1" aria-labelledby="catalogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catalogModalLabel">
                    <i class="fi fi-rr-search me-2"></i>{{ translate('browse_supplier_catalog') }}
                </h5>
                <div class="d-flex gap-2 align-items-center me-2">
                    <div id="catalog-sync-controls" class="d-flex gap-2 align-items-center" style="display:none;">
                        <button type="button" id="catalog-pause-btn" class="btn btn-sm btn-outline-warning" style="display:none;">
                            <i class="fi fi-rr-pause"></i> {{ translate('pause') }}
                        </button>
                        <button type="button" id="catalog-resume-btn" class="btn btn-sm btn-outline-success" style="display:none;">
                            <i class="fi fi-rr-play"></i> {{ translate('resume') }}
                        </button>
                        <button type="button" id="catalog-cancel-btn" class="btn btn-sm btn-outline-danger" style="display:none;">
                            <i class="fi fi-rr-cross-small"></i> {{ translate('cancel') }}
                        </button>
                        <button type="button" id="catalog-fresh-btn" class="btn btn-sm btn-outline-secondary" style="display:none;">
                            <i class="fi fi-rr-rotate-right"></i> {{ translate('start_fresh') }}
                        </button>
                    </div>
                    <button type="button" id="catalog-refresh-btn" class="btn btn-sm btn-outline-secondary"
                            title="{{ translate('refresh_catalog') }}">
                        <i class="fi fi-rr-rotate-right"></i>
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Search bar --}}
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="catalog-search" class="form-control"
                           placeholder="{{ translate('search_by_product_name') }}&hellip;">
                    <button type="button" id="catalog-search-btn" class="btn btn-primary px-4">
                        <i class="fi fi-rr-search"></i>
                    </button>
                </div>

                {{-- Loading / syncing state --}}
                <div id="catalog-loading" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2" id="catalog-status-text">{{ translate('loading_catalog') }}&hellip;</p>
                    <div class="progress mt-2 mx-auto" id="catalog-progress-wrap" style="display:none;max-width:400px;height:20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             id="catalog-progress-bar" role="progressbar"
                             style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>

                {{-- Error state --}}
                <div id="catalog-error" class="alert alert-danger" style="display:none;"></div>

                {{-- Products table --}}
                <div id="catalog-table-wrap" style="display:none;">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('id_SKU') }}</th>
                                <th>{{ translate('name') }}</th>
                                <th class="text-end">{{ translate('price') }} (USD)</th>
                                <th class="text-end">{{ translate('price') }} (JOD)</th>
                                <th class="text-center">{{ translate('qty_available') }}</th>
                                <th class="text-center">{{ translate('region') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="catalog-tbody"></tbody>
                    </table>

                    {{-- Pagination --}}
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small id="catalog-count-text" class="text-muted"></small>
                        <div class="d-flex gap-2">
                            <button type="button" id="catalog-prev" class="btn btn-sm btn-outline-secondary" disabled>
                                &larr; {{ translate('previous') }}
                            </button>
                            <button type="button" id="catalog-next" class="btn btn-sm btn-outline-secondary" disabled>
                                {{ translate('next') }} &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Empty state --}}
                <div id="catalog-empty" class="text-center py-5" style="display:none;">
                    <i class="fi fi-sr-inbox-in fs-1 text-muted"></i>
                    <p class="text-muted mt-2">{{ translate('no_products_found') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    // ─── Customizable toggle show/hide ───────────────────────────────
    const customizableToggle = document.getElementById('customizable-toggle');
    const customizableFields = document.querySelectorAll('.customizable-fields');
    if (customizableToggle) {
        customizableToggle.addEventListener('change', function () {
            customizableFields.forEach(el => {
                el.style.display = this.checked ? '' : 'none';
            });
        });
    }

    const supplierSelect = document.getElementById('supplier_api_id');
    const directTopupSection = document.getElementById('direct-topup-section');
    const directTopupSectionContent = document.querySelectorAll('.direct-topup-section-content');
    const directTopupToggle = document.getElementById('direct-topup-toggle');
    const directTopupFields = document.querySelectorAll('.direct-topup-fields');

    function selectedSupplierSupportsDirectTopup() {
        if (!supplierSelect || !supplierSelect.value) {
            return false;
        }

        const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];

        return selectedOption && selectedOption.getAttribute('data-supports-direct-topup') === '1';
    }

    function syncDirectTopupSectionVisibility() {
        const supported = selectedSupplierSupportsDirectTopup();

        if (directTopupSection) {
            directTopupSection.style.display = supported ? '' : 'none';
        }

        directTopupSectionContent.forEach(el => {
            if (!supported) {
                el.style.display = 'none';
            } else if (el.classList.contains('direct-topup-fields')) {
                el.style.display = (directTopupToggle && directTopupToggle.checked) ? '' : 'none';
            } else {
                el.style.display = '';
            }
        });

        if (!supported && directTopupToggle) {
            directTopupToggle.checked = false;
            directTopupFields.forEach(el => { el.style.display = 'none'; });
        }
    }

    if (supplierSelect) {
        supplierSelect.addEventListener('change', syncDirectTopupSectionVisibility);
        syncDirectTopupSectionVisibility();
    }

    if (directTopupToggle) {
        let ajaxCheckInProgress = false;

        directTopupToggle.addEventListener('change', function () {
            if (!this.checked) {
                directTopupFields.forEach(el => { el.style.display = 'none'; });
                return;
            }

            if (!selectedSupplierSupportsDirectTopup()) {
                toastr.error('{{ translate("supplier_does_not_support_direct_topup") ?: "This supplier does not support direct top-up." }}');
                this.checked = false;
                directTopupFields.forEach(el => { el.style.display = 'none'; });
                return;
            }

            if (ajaxCheckInProgress) return;

            ajaxCheckInProgress = true;
            const validateUrl = this.getAttribute('data-validate-url');
            const supplierId = supplierSelect ? supplierSelect.value : null;

            if (!supplierId) {
                toastr.error('{{ translate("please_select_a_supplier_first") ?: "Please select a supplier first." }}');
                this.checked = false;
                ajaxCheckInProgress = false;
                return;
            }

            fetch(validateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    supplier_api_id: supplierId,
                }),
            })
            .then(response => response.json())
            .then(data => {
                ajaxCheckInProgress = false;
                if (data.supported) {
                    directTopupFields.forEach(el => { el.style.display = ''; });
                } else {
                    toastr.error(data.message || '{{ translate("supplier_does_not_support_direct_topup") ?: "This supplier does not support direct top-up." }}');
                    this.checked = false;
                    directTopupFields.forEach(el => { el.style.display = 'none'; });
                }
            })
            .catch(() => {
                ajaxCheckInProgress = false;
                toastr.error('{{ translate("failed_to_check_direct_topup_support") ?: "Failed to check supplier capabilities." }}');
                this.checked = false;
                directTopupFields.forEach(el => { el.style.display = 'none'; });
            });
        });
    }

    const catalogUrl    = "{{ rtrim(url('admin/supplier'), '/') }}";
    const csrfToken     = "{{ csrf_token() }}";
    const searchInput   = document.getElementById('catalog-search');
    const searchBtn     = document.getElementById('catalog-search-btn');
    const loadingEl     = document.getElementById('catalog-loading');
    const statusTextEl  = document.getElementById('catalog-status-text');
    const progressWrap  = document.getElementById('catalog-progress-wrap');
    const progressBar   = document.getElementById('catalog-progress-bar');
    const errorEl       = document.getElementById('catalog-error');
    const tableWrapEl   = document.getElementById('catalog-table-wrap');
    const tbodyEl       = document.getElementById('catalog-tbody');
    const emptyEl       = document.getElementById('catalog-empty');
    const prevBtn       = document.getElementById('catalog-prev');
    const nextBtn       = document.getElementById('catalog-next');
    const countTextEl   = document.getElementById('catalog-count-text');
    const supplierSel   = document.getElementById('supplier_api_id');
    const browsBtn      = document.getElementById('browse-catalog-btn');
    const refreshBtn    = document.getElementById('catalog-refresh-btn');
    const syncControls  = document.getElementById('catalog-sync-controls');
    const pauseBtn      = document.getElementById('catalog-pause-btn');
    const resumeBtn     = document.getElementById('catalog-resume-btn');
    const cancelBtn     = document.getElementById('catalog-cancel-btn');
    const freshBtn      = document.getElementById('catalog-fresh-btn');

    let currentPage  = 0;
    let pollTimer    = null;
    let activeSupplierId = null;
    const pageSize   = 50;
    const POLL_INTERVAL = 2000;

    // ── Helpers ──────────────────────────────────────────────────────────

    function setVisibility(loading, error, table, empty) {
        loadingEl.style.display   = loading ? '' : 'none';
        errorEl.style.display     = error   ? '' : 'none';
        tableWrapEl.style.display = table   ? '' : 'none';
        emptyEl.style.display     = empty   ? '' : 'none';
    }

    function setProgress(pct, text) {
        progressWrap.style.display = '';
        const val = Math.min(Math.round(pct), 100);
        progressBar.style.width = val + '%';
        progressBar.textContent = val + '%';
        progressBar.setAttribute('aria-valuenow', val);
        if (text) statusTextEl.textContent = text;
    }

    function resetProgress() {
        progressWrap.style.display = 'none';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function updateSyncControls(state, canResume) {
        if (!syncControls) return;

        syncControls.style.display = '';
        pauseBtn.style.display = 'none';
        resumeBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        freshBtn.style.display = 'none';

        if (state === 'running') {
            pauseBtn.style.display = '';
            cancelBtn.style.display = '';
        } else if (state === 'paused' || state === 'failed') {
            if (canResume) {
                resumeBtn.style.display = '';
            }
            cancelBtn.style.display = '';
            freshBtn.style.display = '';
        } else if (state === 'cancelled') {
            freshBtn.style.display = '';
        }
    }

    function hideSyncControls() {
        if (syncControls) {
            syncControls.style.display = 'none';
        }
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ajaxGet(url) {
        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    function ajaxPost(url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    // ── Sync (dispatch + poll) ───────────────────────────────────────────

    function startSync(supplierId, options) {
        options = options || {};
        activeSupplierId = supplierId;
        stopPolling();
        setVisibility(true, false, false, false);
        resetProgress();
        hideSyncControls();
        statusTextEl.textContent = '{{ translate('starting_catalog_sync') }}\u2026';
        refreshBtn.disabled = true;

        var url = catalogUrl + '/' + supplierId + '/catalog/sync';
        if (options.fresh) {
            url += '?fresh=1';
        } else if (options.resume) {
            url += '?resume=1';
        }

        ajaxPost(url)
            .then(function (data) {
                if (data.message === 'already_running' || data.message === 'dispatched' || data.message === 'resumed') {
                    pollStatus(supplierId);
                } else {
                    setVisibility(false, true, false, false);
                    errorEl.textContent = data.message || '{{ translate('failed_to_start_sync') }}';
                    refreshBtn.disabled = false;
                }
            })
            .catch(function (err) {
                setVisibility(false, true, false, false);
                errorEl.textContent = '{{ translate('failed_to_start_sync') }}: ' + err.message;
                refreshBtn.disabled = false;
            });
    }

    function pauseSync(supplierId) {
        ajaxPost(catalogUrl + '/' + supplierId + '/catalog/pause')
            .then(function () { pollStatus(supplierId); })
            .catch(function (err) {
                errorEl.textContent = '{{ translate('failed_to_pause_sync') }}: ' + err.message;
            });
    }

    function cancelSync(supplierId) {
        ajaxPost(catalogUrl + '/' + supplierId + '/catalog/cancel')
            .then(function () { pollStatus(supplierId); })
            .catch(function (err) {
                errorEl.textContent = '{{ translate('failed_to_cancel_sync') }}: ' + err.message;
            });
    }

    function resumeSync(supplierId) {
        startSync(supplierId, { resume: true });
    }

    function startFreshSync(supplierId) {
        startSync(supplierId, { fresh: true });
    }

    function pollStatus(supplierId) {
        stopPolling();

        var tick = function () {
            ajaxGet(catalogUrl + '/' + supplierId + '/catalog/status')
                .then(function (data) {
                    var st = (data.status || {});
                    var state = st.state || 'idle';

                    if (state === 'running') {
                        var pct = st.progress || 0;
                        var fetched = st.pages_fetched || 0;
                        var total   = st.total_pages || '?';
                        setVisibility(true, false, false, false);
                        setProgress(pct, '{{ translate('syncing_catalog') }}: ' + fetched + '/' + total + ' {{ translate('pages') }}\u2026');
                        updateSyncControls('running', false);
                    } else if (state === 'paused') {
                        setVisibility(true, false, false, false);
                        setProgress(st.progress || 0, '{{ translate('catalog_sync_paused') }}');
                        updateSyncControls('paused', st.can_resume !== false);
                        refreshBtn.disabled = false;
                    } else if (state === 'done') {
                        stopPolling();
                        hideSyncControls();
                        setProgress(100, '{{ translate('sync_complete_loading') }}\u2026');
                        currentPage = 0;
                        loadCatalog(supplierId);
                    } else if (state === 'failed') {
                        stopPolling();
                        setVisibility(false, true, false, false);
                        errorEl.textContent = st.error || '{{ translate('catalog_sync_failed') }}';
                        updateSyncControls('failed', st.can_resume !== false);
                        refreshBtn.disabled = false;
                        resetProgress();
                    } else if (state === 'cancelled') {
                        stopPolling();
                        setVisibility(false, true, false, false);
                        errorEl.textContent = '{{ translate('catalog_sync_cancelled') }}';
                        updateSyncControls('cancelled', false);
                        refreshBtn.disabled = false;
                        resetProgress();
                    } else {
                        // idle — no sync running, no cache; stop polling and wait
                        stopPolling();
                        hideSyncControls();
                        setVisibility(false, false, false, true);
                        refreshBtn.disabled = false;
                    }
                })
                .catch(function () {
                    // Network glitch — keep polling, don't break
                });
        };

        tick(); // immediate first check
        pollTimer = setInterval(tick, POLL_INTERVAL);
    }

    // ── Check status on modal open ───────────────────────────────────────

    function checkAndLoad(supplierId) {
        activeSupplierId = supplierId;
        setVisibility(true, false, false, false);
        resetProgress();
        hideSyncControls();
        statusTextEl.textContent = '{{ translate('checking_catalog') }}\u2026';
        refreshBtn.disabled = true;

        ajaxGet(catalogUrl + '/' + supplierId + '/catalog/status')
            .then(function (data) {
                var st = (data.status || {});
                var state = st.state || 'idle';

                if (state === 'done') {
                    statusTextEl.textContent = '{{ translate('loading_catalog') }}\u2026';
                    currentPage = 0;
                    loadCatalog(supplierId);
                } else if (state === 'running' || state === 'paused') {
                    pollStatus(supplierId);
                } else if (state === 'failed' || state === 'cancelled') {
                    pollStatus(supplierId);
                } else {
                    startSync(supplierId);
                }
            })
            .catch(function (err) {
                setVisibility(false, true, false, false);
                errorEl.textContent = '{{ translate('failed_to_load_catalog') }}: ' + err.message;
                refreshBtn.disabled = false;
            });
    }

    // ── Load cached catalog (instant) ────────────────────────────────────

    function loadCatalog(supplierId) {
        if (!supplierId) supplierId = supplierSel.value;
        if (!supplierId) return;

        setVisibility(true, false, false, false);
        statusTextEl.textContent = '{{ translate('loading_catalog') }}\u2026';
        resetProgress();

        var params = new URLSearchParams({
            search: searchInput.value.trim(),
            page:   currentPage,
            size:   pageSize,
        });

        ajaxGet(catalogUrl + '/' + supplierId + '/catalog?' + params)
            .then(function (data) {
                if (!data.success) {
                    if (data.message === 'no_cache') {
                        // Cache expired — show empty state, user can click refresh
                        setVisibility(false, false, false, true);
                        refreshBtn.disabled = false;
                        return;
                    }
                    setVisibility(false, true, false, false);
                    errorEl.textContent = data.message || '{{ translate('failed_to_load_catalog') }}';
                    refreshBtn.disabled = false;
                    return;
                }

                var products = data.products || [];

                if (products.length === 0) {
                    setVisibility(false, false, false, true);
                    prevBtn.disabled = true;
                    nextBtn.disabled = true;
                    countTextEl.textContent = '';
                    refreshBtn.disabled = false;
                    return;
                }

                tbodyEl.innerHTML = products.map(function (p) {
                    var stockHtml;
                    if (p.stock === 0) {
                        stockHtml = '<span class="badge bg-danger">{{ translate('out_of_stock') }}</span>';
                    } else if (p.stock >= 999) {
                        stockHtml = '<span class="badge bg-success">999+</span>';
                    } else if (p.stock >= 50) {
                        stockHtml = '<span class="badge bg-success">' + p.stock + '</span>';
                    } else {
                        stockHtml = '<span class="badge bg-warning text-dark">' + p.stock + '</span>';
                    }

                    var region = p.region
                        ? '<span class="badge bg-secondary">' + escHtml(p.region) + '</span>'
                        : '<span class="text-muted">\u2014</span>';

                    var jodPriceHtml = p.source_price != null
                        ? escHtml(String(p.source_price)) + ' <small class="text-muted">' + escHtml(p.source_currency || 'JOD') + '</small>'
                        : '<span class="text-muted">\u2014</span>';

                    return '<tr>' +
                        '<td><code>' + escHtml(String(p.id)) + '</code></td>' +
                        '<td>' + escHtml(p.name) + '</td>' +
                        '<td class="text-end fw-semibold">' + escHtml(String(p.price)) + ' <small class="text-muted">USD</small></td>' +
                        '<td class="text-end">' + jodPriceHtml + '</td>' +
                        '<td class="text-center">' + stockHtml + '</td>' +
                        '<td class="text-center">' + region + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="btn btn-sm btn-primary select-product-btn"' +
                            ' data-id="' + escHtml(String(p.id)) + '"' +
                            ' data-name="' + escHtml(p.name) + '"' +
                            ' data-price="' + escHtml(String(p.price)) + '"' +
                            ' data-currency="' + escHtml(p.currency) + '">' +
                            '{{ translate('select') }}' +
                            '</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');

                // Attach select handlers
                tbodyEl.querySelectorAll('.select-product-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('supplier_product_id').value   = btn.dataset.id;
                        document.getElementById('supplier_product_name').value = btn.dataset.name;
                        document.getElementById('cost_price').value            = btn.dataset.price;
                        document.getElementById('cost_currency').value         = btn.dataset.currency;
                        bootstrap.Modal.getInstance(document.getElementById('catalogModal')).hide();
                    });
                });

                var total = data.total != null ? data.total : products.length;
                prevBtn.disabled = currentPage === 0;
                nextBtn.disabled = (currentPage + 1) * pageSize >= total;

                var from = currentPage * pageSize + 1;
                var to   = Math.min(from + products.length - 1, total);
                countTextEl.textContent = from + '\u2013' + to + ' {{ translate('of') }} ' + total + ' {{ translate('items') }}';

                setVisibility(false, false, true, false);
                hideSyncControls();
                refreshBtn.disabled = false;
            })
            .catch(function (err) {
                setVisibility(false, true, false, false);
                errorEl.textContent = '{{ translate('failed_to_load_catalog') }}: ' + err.message;
                refreshBtn.disabled = false;
            });
    }

    // ── Event listeners ──────────────────────────────────────────────────

    // Show/hide Browse Catalog button when supplier changes
    supplierSel.addEventListener('change', function () {
        browsBtn.style.display = this.value ? 'inline-flex' : 'none';
        currentPage = 0;
        tbodyEl.innerHTML = '';
        stopPolling();
        syncDirectTopupSectionVisibility();
    });

    // Modal open → check status → load or sync
    document.getElementById('catalogModal').addEventListener('show.bs.modal', function () {
        var supplierId = supplierSel.value;
        if (supplierId) checkAndLoad(supplierId);
    });

    // Stop polling when modal closes
    document.getElementById('catalogModal').addEventListener('hidden.bs.modal', function () {
        stopPolling();
    });

    // Search
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { currentPage = 0; loadCatalog(); }
    });
    searchBtn.addEventListener('click', function () { currentPage = 0; loadCatalog(); });

    // Pagination
    prevBtn.addEventListener('click', function () {
        if (currentPage > 0) { currentPage--; loadCatalog(); }
    });
    nextBtn.addEventListener('click', function () {
        currentPage++; loadCatalog();
    });

    // Refresh → force a fresh sync from supplier API
    refreshBtn.addEventListener('click', function () {
        var supplierId = supplierSel.value;
        if (supplierId) {
            currentPage = 0;
            tbodyEl.innerHTML = '';
            startSync(supplierId, { fresh: true });
        }
    });

    if (pauseBtn) {
        pauseBtn.addEventListener('click', function () {
            if (activeSupplierId) pauseSync(activeSupplierId);
        });
    }
    if (resumeBtn) {
        resumeBtn.addEventListener('click', function () {
            if (activeSupplierId) resumeSync(activeSupplierId);
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (activeSupplierId) cancelSync(activeSupplierId);
        });
    }
    if (freshBtn) {
        freshBtn.addEventListener('click', function () {
            if (activeSupplierId) startFreshSync(activeSupplierId);
        });
    }

    // If supplier was pre-selected (e.g. old value from validation), show button
    if (supplierSel.value) {
        browsBtn.style.display = 'inline-flex';
    }

    var costCurrencyEl = document.getElementById('cost_currency');
    var costPriceEl = document.getElementById('cost_price');
    var previousCurrency = costCurrencyEl ? costCurrencyEl.value.trim().toUpperCase() : 'USD';
    var convertCostUrl = '{{ route('admin.supplier.mapping.convert-cost') }}';
    var currencyConvertInFlight = false;

    if (costCurrencyEl && costPriceEl) {
        costCurrencyEl.addEventListener('change', function () {
            var newCurrency = this.value.trim().toUpperCase();
            var oldCurrency = previousCurrency;

            if (newCurrency === oldCurrency || currencyConvertInFlight) {
                previousCurrency = newCurrency;
                return;
            }

            currencyConvertInFlight = true;

            fetch(convertCostUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    amount: parseFloat(costPriceEl.value) || 0,
                    from_currency: oldCurrency,
                    to_currency: newCurrency,
                }),
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    costPriceEl.value = data.amount;
                }
            })
            .finally(function () {
                previousCurrency = newCurrency;
                currencyConvertInFlight = false;
            });
        });
    }
})();
</script>
@endpush
