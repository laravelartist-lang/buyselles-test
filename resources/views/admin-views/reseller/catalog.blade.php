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
                {{ translate('partner_api_catalog_help') ?: 'Partner API only. Allowed items create hidden Partner-API products that never appear on the storefront. Storefront supplier mappings stay completely separate.' }}
            </p>
        </div>
        <a href="{{ route('admin.reseller-keys.edit', $key->id) }}" class="btn btn-outline-secondary">
            {{ translate('back') }}
        </a>
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
                        {{ translate('partner_catalog_does_not_affect_storefront') ?: 'These items never appear on the website. Removing one only removes Partner API access.' }}
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
                            <th class="text-end">{{ translate('partner_price') ?: 'Partner price' }}</th>
                            <th>{{ translate('type') }}</th>
                            <th class="text-center">{{ translate('status') }}</th>
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
                                <td class="text-end fw-semibold">
                                    {{ $item->currency }} {{ number_format((float) $item->partner_price, 4) }}
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
                                    @if($item->is_active)
                                        <span class="badge bg-success">{{ translate('active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ translate('inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST"
                                          action="{{ route('admin.reseller-keys.catalog.destroy', [$key->id, $item->id]) }}"
                                          class="d-inline partner-catalog-remove-form">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            {{ translate('remove') }}
                                        </button>
                                    </form>
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
    const catalogUrl = "{{ rtrim(url('admin/supplier'), '/') }}";
    const assignUrl = "{{ route('admin.reseller-keys.catalog.assign', $key->id) }}";
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

    let currentPage = 0;
    let pollTimer = null;
    let activeSupplierId = null;
    const pageSize = 50;

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
        if (body) {
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
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
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
            alert('Enter a partner price greater than 0.');
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
        }).then(function () {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            btn.textContent = '{{ translate('allowed') ?: 'Allowed' }}';
        }).catch(function (err) {
            btn.disabled = false;
            alert(err.message);
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

    document.querySelectorAll('.partner-catalog-remove-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('{{ translate('are_you_sure') }}')) {
                e.preventDefault();
            }
        });
    });

    updateBrowseButton();
})();
</script>
@endpush
