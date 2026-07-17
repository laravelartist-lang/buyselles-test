@extends('layouts.admin.app')

@section('title', (translate('partner_api_catalog') ?: 'Partner API Catalog') . ' — ' . $key->name)

@section('content')
<div class="content container-fluid">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.reseller-keys.edit', $key->id) }}" class="text-muted text-decoration-none">
            <i class="fi fi-rr-arrow-left me-1"></i>{{ translate('edit_api_key') }}
        </a>
        <span class="text-muted">/</span>
        <span class="fw-semibold">{{ translate('partner_api_catalog') ?: 'Partner API Catalog' }}</span>
    </div>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h3 class="mb-1">{{ translate('partner_api_catalog') ?: 'Partner API Catalog' }}</h3>
            <div class="text-muted">
                #{{ $key->id }} — {{ $key->name }}
                @if($key->user)
                    · {{ $key->user->name }}
                @elseif($key->seller)
                    · {{ $key->seller->f_name }} {{ $key->seller->l_name }}
                @endif
            </div>
            <p class="text-muted small mb-0 mt-2">
                {{ translate('partner_api_catalog_help') ?: 'Pick products from the Global Catalog (storefront products with partner-specific pricing) or add partner-exclusive SKUs from a supplier catalog.' }}
            </p>
        </div>
        <a href="{{ route('admin.reseller-keys.edit', $key->id) }}" class="btn btn-outline-secondary">
            {{ translate('back') }}
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fi fi-rr-apps me-2"></i>
                {{ translate('add_from_global_catalog') ?: 'Add from global catalog' }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">{{ translate('search_product') }}</label>
                    <input type="search" id="global-catalog-search" class="form-control"
                           placeholder="{{ translate('search_product') }}…">
                    <div id="global-catalog-results" class="list-group mt-2 d-none"></div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-light border mb-0 py-2 small">
                        {{ translate('global_catalog_partner_note') ?: 'These products stay on the storefront. Partner API uses separate pricing from this page.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fi fi-rr-search me-2"></i>
                {{ translate('add_from_supplier_catalog') ?: 'Add from supplier catalog' }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">{{ translate('supplier') }}</label>
                    <select id="partner-supplier-select" class="form-select">
                        <option value="">{{ translate('select') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                    data-supports-topup="{{ $supplier->supports_direct_top_up ? '1' : '0' }}">
                                {{ $supplier->name }} ({{ $supplier->driver }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="partner-browse-catalog-btn" class="btn btn-primary w-100" disabled>
                        <i class="fi fi-rr-apps me-1"></i>
                        {{ translate('browse_supplier_catalog') ?: 'Browse catalog' }}
                    </button>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-light border mb-0 py-2 small">
                        {{ translate('partner_catalog_supplier_note') ?: 'Partner-exclusive SKUs only. These never appear on the storefront or Product Mappings.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ translate('allowed_products') ?: 'Allowed products for this partner' }}</h5>
            <span class="badge bg-primary">{{ $items->total() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ translate('product') }}</th>
                            <th>{{ translate('supplier') }}</th>
                            <th>{{ translate('supplier_product_id') ?: 'Supplier SKU' }}</th>
                            <th class="text-end" style="min-width:200px;">{{ translate('partner_price') ?: 'Partner price' }}</th>
                            <th>{{ translate('type') }}</th>
                            <th class="text-center">{{ translate('active') }}</th>
                            <th class="text-end">{{ translate('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php
                                $product = $item->product;
                                $mapping = $product?->supplierMapping;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $product?->name ?: '—' }}</div>
                                    <div class="text-muted small">#{{ $item->product_id }}</div>
                                </td>
                                <td>{{ $mapping?->supplierApi?->name ?: '—' }}</td>
                                <td><code class="small">{{ $mapping?->supplier_product_id ?: '—' }}</code></td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center gap-1 justify-content-end">
                                        <input type="number" min="0.0001" step="any"
                                               class="form-control form-control-sm partner-item-price-input"
                                               value="{{ (float) $item->partner_price }}"
                                               style="width:110px;">
                                        <span class="text-muted small">{{ $item->currency }}</span>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary partner-item-save-btn ms-1"
                                                data-item-id="{{ $item->id }}">
                                            {{ translate('save') }}
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    @if($mapping?->is_direct_topup)
                                        <span class="badge bg-info text-dark">direct_topup</span>
                                    @elseif(($mapping?->activeDenominations?->count() ?? 0) > 0)
                                        <span class="badge bg-secondary">denominations</span>
                                    @else
                                        <span class="badge bg-light text-dark border">fixed</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input partner-item-toggle"
                                               type="checkbox"
                                               data-item-id="{{ $item->id }}"
                                               @checked($item->is_active)>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger partner-item-remove-btn"
                                            data-item-id="{{ $item->id }}">
                                        {{ translate('remove') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    {{ translate('no_partner_catalog_items') ?: 'No products assigned yet. Browse a supplier catalog to add items.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</div>

{{-- Reuses the same supplier catalog browse/sync endpoints as mapping-add --}}
<div class="modal fade" id="partnerCatalogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fi fi-rr-search me-2"></i>{{ translate('browse_supplier_catalog') }}
                </h5>
                <div class="d-flex gap-2 align-items-center me-2">
                    <div id="catalog-sync-controls" class="d-flex gap-2 align-items-center" style="display:none;">
                        <button type="button" id="catalog-pause-btn" class="btn btn-sm btn-outline-warning" style="display:none;">
                            {{ translate('pause') }}
                        </button>
                        <button type="button" id="catalog-resume-btn" class="btn btn-sm btn-outline-success" style="display:none;">
                            {{ translate('resume') }}
                        </button>
                        <button type="button" id="catalog-cancel-btn" class="btn btn-sm btn-outline-danger" style="display:none;">
                            {{ translate('cancel') }}
                        </button>
                        <button type="button" id="catalog-fresh-btn" class="btn btn-sm btn-outline-secondary" style="display:none;">
                            {{ translate('start_fresh') }}
                        </button>
                    </div>
                    <button type="button" id="catalog-refresh-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fi fi-rr-rotate-right"></i>
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="catalog-search" class="form-control"
                           placeholder="{{ translate('search_by_product_name') }}…">
                    <button type="button" id="catalog-search-btn" class="btn btn-primary px-4">
                        <i class="fi fi-rr-search"></i>
                    </button>
                </div>

                <div id="catalog-loading" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2" id="catalog-status-text">{{ translate('loading_catalog') }}…</p>
                    <div class="progress mt-2 mx-auto" id="catalog-progress-wrap" style="display:none;max-width:400px;height:20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             id="catalog-progress-bar" style="width:0%">0%</div>
                    </div>
                </div>

                <div id="catalog-error" class="alert alert-danger" style="display:none;"></div>

                <div id="catalog-table-wrap" style="display:none;">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('id_SKU') }}</th>
                                <th>{{ translate('name') }}</th>
                                <th class="text-end">{{ translate('cost') ?: 'Cost' }}</th>
                                <th class="text-end" style="min-width:140px;">{{ translate('partner_price') ?: 'Partner price' }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="catalog-tbody"></tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small id="catalog-count-text" class="text-muted"></small>
                        <div class="d-flex gap-2">
                            <button type="button" id="catalog-prev" class="btn btn-sm btn-outline-secondary" disabled>
                                ← {{ translate('previous') }}
                            </button>
                            <button type="button" id="catalog-next" class="btn btn-sm btn-outline-secondary" disabled>
                                {{ translate('next') }} →
                            </button>
                        </div>
                    </div>
                </div>

                <div id="catalog-empty" class="text-center py-5" style="display:none;">
                    <p class="text-muted mb-0">{{ translate('no_products_found') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    'use strict';

    const catalogUrl = "{{ rtrim(url('admin/supplier'), '/') }}";
    const assignUrl = "{{ route('admin.reseller-keys.catalog.assign', $key->id) }}";
    const assignExistingUrl = "{{ route('admin.reseller-keys.catalog.assign-existing', $key->id) }}";
    const globalSearchUrl = "{{ route('admin.partner.global-catalog.search') }}";
    const toggleUrlTemplate = "{{ route('admin.reseller-keys.catalog.toggle', [$key->id, '__ID__']) }}";
    const updatePriceUrlTemplate = "{{ route('admin.reseller-keys.catalog.update-price', [$key->id, '__ID__']) }}";
    const destroyUrlTemplate = "{{ route('admin.reseller-keys.catalog.destroy', [$key->id, '__ID__']) }}";
    const csrfToken = "{{ csrf_token() }}";

    const supplierSel = document.getElementById('partner-supplier-select');
    const browseBtn = document.getElementById('partner-browse-catalog-btn');
    const searchInput = document.getElementById('catalog-search');
    const searchBtn = document.getElementById('catalog-search-btn');
    const loadingEl = document.getElementById('catalog-loading');
    const statusTextEl = document.getElementById('catalog-status-text');
    const progressWrap = document.getElementById('catalog-progress-wrap');
    const progressBar = document.getElementById('catalog-progress-bar');
    const errorEl = document.getElementById('catalog-error');
    const tableWrapEl = document.getElementById('catalog-table-wrap');
    const tbodyEl = document.getElementById('catalog-tbody');
    const emptyEl = document.getElementById('catalog-empty');
    const prevBtn = document.getElementById('catalog-prev');
    const nextBtn = document.getElementById('catalog-next');
    const countTextEl = document.getElementById('catalog-count-text');
    const refreshBtn = document.getElementById('catalog-refresh-btn');
    const syncControls = document.getElementById('catalog-sync-controls');
    const pauseBtn = document.getElementById('catalog-pause-btn');
    const resumeBtn = document.getElementById('catalog-resume-btn');
    const cancelBtn = document.getElementById('catalog-cancel-btn');
    const freshBtn = document.getElementById('catalog-fresh-btn');
    const modalEl = document.getElementById('partnerCatalogModal');
    const globalSearchInput = document.getElementById('global-catalog-search');
    const globalResultsEl = document.getElementById('global-catalog-results');

    let currentPage = 0;
    let pollTimer = null;
    let activeSupplierId = null;
    let globalSearchTimer = null;
    const pageSize = 50;

    function showToast(type, message) {
        const text = message || '';
        if (typeof toastMagic !== 'undefined') {
            toastMagic[type](text, '', true);
            return;
        }
        alert(text);
    }

    function showSuccess(message) {
        showToast('success', message);
    }

    function showError(message) {
        showToast('error', message);
    }

    function confirmDelete(callback) {
        const getText = document.getElementById('get-confirm-and-cancel-button-text-for-delete');
        Swal.fire({
            title: getText?.dataset.sure || '{{ translate('are_you_sure') }}',
            text: getText?.dataset.text || '{{ translate('you_will_not_be_able_to_revert_this') }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText: getText?.dataset.cancel || '{{ translate('cancel') }}',
            confirmButtonText: getText?.dataset.confirm || '{{ translate('yes_delete_it') }}',
            reverseButtons: true,
        }).then(function (result) {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    }

    function setVisibility(loading, error, table, empty) {
        loadingEl.style.display = loading ? '' : 'none';
        errorEl.style.display = error ? '' : 'none';
        tableWrapEl.style.display = table ? '' : 'none';
        emptyEl.style.display = empty ? '' : 'none';
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

    function ajaxPost(url, body) {
        var options = {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        };
        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(url, options).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.message || data.error || ('HTTP ' + res.status));
                }
                return data;
            });
        });
    }

    function updateBrowseButton() {
        browseBtn.disabled = !supplierSel.value;
    }

    supplierSel.addEventListener('change', updateBrowseButton);

    browseBtn.addEventListener('click', function () {
        if (!supplierSel.value) return;
        activeSupplierId = supplierSel.value;
        currentPage = 0;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        openCatalog(activeSupplierId);
    });

    function openCatalog(supplierId) {
        stopPolling();
        setVisibility(true, false, false, false);
        statusTextEl.textContent = '{{ translate('loading_catalog') }}…';
        ajaxGet(catalogUrl + '/' + supplierId + '/catalog?page=0&size=' + pageSize)
            .then(function (data) {
                if (data.success) {
                    renderRows(data);
                    return;
                }
                if (data.message === 'no_cache') {
                    startSync(supplierId, {});
                    return;
                }
                setVisibility(false, true, false, false);
                errorEl.textContent = data.message || 'Failed to load catalog';
            })
            .catch(function () {
                startSync(supplierId, {});
            });
    }

    function startSync(supplierId, options) {
        stopPolling();
        setVisibility(true, false, false, false);
        statusTextEl.textContent = '{{ translate('starting_catalog_sync') }}…';
        var url = catalogUrl + '/' + supplierId + '/catalog/sync';
        if (options.fresh) url += '?fresh=1';
        else if (options.resume) url += '?resume=1';
        ajaxPost(url)
            .then(function () { pollStatus(supplierId); })
            .catch(function (err) {
                setVisibility(false, true, false, false);
                errorEl.textContent = err.message;
            });
    }

    function pollStatus(supplierId) {
        stopPolling();
        var tick = function () {
            ajaxGet(catalogUrl + '/' + supplierId + '/catalog/status')
                .then(function (data) {
                    var st = data.status || {};
                    var state = st.state || 'idle';
                    updateSyncControls(state, !!st.can_resume);
                    if (state === 'running') {
                        var pct = st.progress || 0;
                        progressWrap.style.display = '';
                        progressBar.style.width = pct + '%';
                        progressBar.textContent = Math.round(pct) + '%';
                        statusTextEl.textContent = '{{ translate('syncing_catalog') }}…';
                        return;
                    }
                    if (state === 'done' || state === 'completed') {
                        stopPolling();
                        hideSyncControls();
                        loadCatalog(supplierId);
                        return;
                    }
                    if (state === 'failed' || state === 'paused' || state === 'cancelled') {
                        stopPolling();
                        statusTextEl.textContent = state;
                    }
                })
                .catch(function () {});
        };
        tick();
        pollTimer = setInterval(tick, 2000);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function updateSyncControls(state, canResume) {
        syncControls.style.display = '';
        pauseBtn.style.display = state === 'running' ? '' : 'none';
        cancelBtn.style.display = (state === 'running' || state === 'paused' || state === 'failed') ? '' : 'none';
        resumeBtn.style.display = ((state === 'paused' || state === 'failed') && canResume) ? '' : 'none';
        freshBtn.style.display = (state === 'paused' || state === 'failed' || state === 'cancelled') ? '' : 'none';
    }

    function hideSyncControls() {
        syncControls.style.display = 'none';
    }

    function loadCatalog(supplierId) {
        var params = 'page=' + currentPage + '&size=' + pageSize;
        if (searchInput.value.trim()) {
            params += '&search=' + encodeURIComponent(searchInput.value.trim());
        }
        setVisibility(true, false, false, false);
        ajaxGet(catalogUrl + '/' + supplierId + '/catalog?' + params)
            .then(function (data) {
                if (!data.success) {
                    setVisibility(false, true, false, false);
                    errorEl.textContent = data.message || 'Failed';
                    return;
                }
                renderRows(data);
            })
            .catch(function (err) {
                setVisibility(false, true, false, false);
                errorEl.textContent = err.message;
            });
    }

    function renderRows(data) {
        var products = data.products || [];
        if (!products.length) {
            setVisibility(false, false, false, true);
            return;
        }
        setVisibility(false, false, true, false);
        tbodyEl.innerHTML = products.map(function (p) {
            var id = p.id || '';
            var name = p.name || '';
            var price = p.price ?? p.usd_price ?? p.min_price ?? 0;
            var currency = p.currency || 'USD';
            return '<tr>' +
                '<td><code title="' + escHtml(id) + '">' + escHtml(String(id).slice(0, 12)) + '</code></td>' +
                '<td>' + escHtml(name) + '</td>' +
                '<td class="text-end">' + escHtml(currency) + ' ' + escHtml(price) + '</td>' +
                '<td><input type="number" min="0.0001" step="any" class="form-control form-control-sm partner-price-input" value="' + escHtml(price) + '"></td>' +
                '<td class="text-center">' +
                    '<button type="button" class="btn btn-sm btn-primary partner-assign-btn"' +
                        ' data-id="' + escHtml(id) + '"' +
                        ' data-name="' + escHtml(name) + '"' +
                        ' data-cost="' + escHtml(price) + '"' +
                        ' data-currency="' + escHtml(currency) + '">' +
                        '{{ translate('allow') ?: 'Allow' }}' +
                    '</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        countTextEl.textContent = (data.total || products.length) + ' products';
        prevBtn.disabled = currentPage <= 0;
        nextBtn.disabled = ((currentPage + 1) * pageSize) >= (data.total || 0);
    }

    tbodyEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.partner-assign-btn');
        if (!btn) return;
        var row = btn.closest('tr');
        var priceInput = row.querySelector('.partner-price-input');
        var partnerPrice = parseFloat(priceInput.value || '0');
        if (!(partnerPrice > 0)) {
            showError('{{ translate('enter_a_valid_partner_price') ?: 'Enter a partner price greater than 0.' }}');
            return;
        }

        btn.disabled = true;
        var supportsTopup = supplierSel.options[supplierSel.selectedIndex].dataset.supportsTopup === '1';

        ajaxPost(assignUrl, {
            supplier_api_id: parseInt(activeSupplierId, 10),
            supplier_product_id: btn.dataset.id,
            supplier_product_name: btn.dataset.name,
            cost_price: parseFloat(btn.dataset.cost || '0') || 0,
            cost_currency: btn.dataset.currency || 'USD',
            partner_price: partnerPrice,
            currency: 'USD',
            is_direct_topup: supportsTopup ? 1 : 0,
        }).then(function (res) {
            showSuccess(res.message);
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            btn.textContent = '{{ translate('allowed') ?: 'Allowed' }}';
        }).catch(function (err) {
            btn.disabled = false;
            showError(err.message);
        });
    });

    searchBtn.addEventListener('click', function () {
        currentPage = 0;
        loadCatalog(activeSupplierId);
    });
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            currentPage = 0;
            loadCatalog(activeSupplierId);
        }
    });
    prevBtn.addEventListener('click', function () {
        if (currentPage > 0) { currentPage--; loadCatalog(activeSupplierId); }
    });
    nextBtn.addEventListener('click', function () {
        currentPage++; loadCatalog(activeSupplierId);
    });
    refreshBtn.addEventListener('click', function () {
        startSync(activeSupplierId, { fresh: true });
    });
    pauseBtn.addEventListener('click', function () {
        ajaxPost(catalogUrl + '/' + activeSupplierId + '/catalog/pause').then(function () {
            pollStatus(activeSupplierId);
        });
    });
    resumeBtn.addEventListener('click', function () {
        startSync(activeSupplierId, { resume: true });
    });
    cancelBtn.addEventListener('click', function () {
        ajaxPost(catalogUrl + '/' + activeSupplierId + '/catalog/cancel').then(function () {
            pollStatus(activeSupplierId);
        });
    });
    freshBtn.addEventListener('click', function () {
        startSync(activeSupplierId, { fresh: true });
    });

    function renderGlobalResults(products) {
        if (!products.length) {
            globalResultsEl.innerHTML = '<div class="list-group-item text-muted">{{ translate('no_products_found') }}</div>';
            globalResultsEl.classList.remove('d-none');
            return;
        }

        globalResultsEl.innerHTML = products.map(function (product) {
            return '<div class="list-group-item">' +
                '<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">' +
                    '<div>' +
                        '<div class="fw-semibold">' + escHtml(product.name) + '</div>' +
                        '<div class="text-muted small">#' + product.id + ' · ' + escHtml(product.fulfillment_type) + '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<input type="number" min="0.0001" step="any" class="form-control form-control-sm global-partner-price" style="width:120px;" value="' + escHtml(product.reference_price) + '">' +
                        '<button type="button" class="btn btn-sm btn-primary global-assign-btn" data-id="' + product.id + '">{{ translate('allow') ?: 'Allow' }}</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
        globalResultsEl.classList.remove('d-none');
    }

    globalSearchInput.addEventListener('input', function () {
        clearTimeout(globalSearchTimer);
        var q = globalSearchInput.value.trim();
        if (q.length < 2) {
            globalResultsEl.classList.add('d-none');
            return;
        }
        globalSearchTimer = setTimeout(function () {
            fetch(globalSearchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    renderGlobalResults(data.products || []);
                })
                .catch(function () {
                    globalResultsEl.classList.add('d-none');
                });
        }, 300);
    });

    globalResultsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.global-assign-btn');
        if (!btn) return;
        var row = btn.closest('.list-group-item');
        var priceInput = row.querySelector('.global-partner-price');
        var partnerPrice = parseFloat(priceInput.value || '0');
        if (!(partnerPrice > 0)) {
            showError('{{ translate('enter_a_valid_partner_price') ?: 'Enter a partner price greater than 0.' }}');
            return;
        }
        btn.disabled = true;
        ajaxPost(assignExistingUrl, {
            product_id: parseInt(btn.dataset.id, 10),
            partner_price: partnerPrice,
            currency: 'USD',
            is_active: true,
        }).then(function (res) {
            showSuccess(res.message);
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            btn.textContent = '{{ translate('allowed') ?: 'Allowed' }}';
        }).catch(function (err) {
            btn.disabled = false;
            showError(err.message);
        });
    });

    document.addEventListener('change', function (e) {
        var toggle = e.target.closest('.partner-item-toggle');
        if (!toggle) return;

        var itemId = toggle.dataset.itemId;
        var url = toggleUrlTemplate.replace('__ID__', itemId);
        var checked = toggle.checked;
        toggle.disabled = true;

        ajaxPost(url, { is_active: checked }).then(function (res) {
            showSuccess(res.message);
        }).catch(function (err) {
            toggle.checked = !checked;
            showError(err.message);
        }).finally(function () {
            toggle.disabled = false;
        });
    });

    document.addEventListener('click', function (e) {
        var saveBtn = e.target.closest('.partner-item-save-btn');
        if (saveBtn) {
            var itemId = saveBtn.dataset.itemId;
            var row = saveBtn.closest('tr');
            var priceInput = row.querySelector('.partner-item-price-input');
            var price = parseFloat(priceInput.value || '0');
            if (!(price > 0)) {
                showError('{{ translate('enter_a_valid_partner_price') ?: 'Enter a partner price greater than 0.' }}');
                return;
            }
            var url = updatePriceUrlTemplate.replace('__ID__', itemId);
            saveBtn.disabled = true;
            ajaxPost(url, { partner_price: price }).then(function (res) {
                showSuccess(res.message);
            }).catch(function (err) {
                showError(err.message);
            }).finally(function () {
                saveBtn.disabled = false;
            });
            return;
        }

        var removeBtn = e.target.closest('.partner-item-remove-btn');
        if (!removeBtn) return;

        var itemId = removeBtn.dataset.itemId;
        var url = destroyUrlTemplate.replace('__ID__', itemId);

        confirmDelete(function () {
            removeBtn.disabled = true;
            ajaxPost(url).then(function (res) {
                var deletedMsg = document.getElementById('get-deleted-message');
                showSuccess(res.message || deletedMsg?.dataset.text || '{{ translate('deleted_successfully') }}');
                removeBtn.closest('tr').remove();
            }).catch(function (err) {
                removeBtn.disabled = false;
                showError(err.message);
            });
        });
    });

    updateBrowseButton();
})();
</script>
@endpush
