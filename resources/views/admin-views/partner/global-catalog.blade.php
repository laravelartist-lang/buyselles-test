@extends('layouts.admin.app')

@section('title', translate('global_catalog') ?: 'Global Catalog')

@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h3 class="mb-1">{{ translate('global_catalog') ?: 'Global Catalog' }}</h3>
            <p class="text-muted mb-0">
                {{ translate('global_catalog_help') ?: 'All in-house and supplier-mapped storefront products. Assign them to partners with custom pricing and visibility toggles.' }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex flex-column gap-3">
            <form action="{{ route('admin.partner.global-catalog') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <select name="supplier_id" class="form-select form-select-sm" style="min-width: 160px;">
                    <option value="">{{ translate('all_suppliers') }}</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                <select name="fulfillment_type" class="form-select form-select-sm" style="min-width: 160px;">
                    <option value="">{{ translate('all_types') ?: 'All types' }}</option>
                    <option value="local_codes" @selected(request('fulfillment_type') === 'local_codes')>local_codes</option>
                    <option value="supplier_mapped" @selected(request('fulfillment_type') === 'supplier_mapped')>supplier_mapped</option>
                    <option value="direct_topup" @selected(request('fulfillment_type') === 'direct_topup')>direct_topup</option>
                </select>
                <div class="input-group" style="min-width: 260px;">
                    <input type="search" name="searchValue" class="form-control form-control-sm"
                           placeholder="{{ translate('search_product') }}…"
                           value="{{ request('searchValue') }}">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fi fi-rr-search"></i>
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ translate('product') }}</th>
                            <th>{{ translate('type') }}</th>
                            <th>{{ translate('supplier') }}</th>
                            <th class="text-end">{{ translate('storefront_price') ?: 'Storefront price' }}</th>
                            <th class="text-center">{{ translate('partners') ?: 'Partners' }}</th>
                            <th class="text-end">{{ translate('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $mapping = $product->supplierMapping;
                                $fulfillmentType = app(\App\Services\Partner\GlobalPartnerCatalogQuery::class)->resolveFulfillmentType($product);
                                $referencePrice = app(\App\Services\Partner\GlobalPartnerCatalogQuery::class)->resolveReferencePrice($product);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $product->name }}</div>
                                    <div class="text-muted small">#{{ $product->id }} @if($product->code)· {{ $product->code }}@endif</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $fulfillmentType }}</span>
                                </td>
                                <td>{{ $mapping?->supplierApi?->name ?: '—' }}</td>
                                <td class="text-end">{{ number_format($referencePrice, 4) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary">{{ $product->assigned_partners_count ?? 0 }}</span>
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary manage-partners-btn"
                                            data-product-id="{{ $product->id }}"
                                            data-product-name="{{ $product->name }}">
                                        {{ translate('manage_partners') ?: 'Manage partners' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    {{ translate('no_products_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div>{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="managePartnersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('manage_partners') ?: 'Manage partners' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="manage-partners-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="manage-partners-error" class="alert alert-danger d-none"></div>
                <div id="manage-partners-content" class="d-none">
                    <div class="mb-3">
                        <div class="fw-semibold" id="manage-product-name"></div>
                        <div class="text-muted small" id="manage-product-meta"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ translate('partner') ?: 'Partner' }}</th>
                                    <th class="text-end">{{ translate('partner_price') ?: 'Partner price' }}</th>
                                    <th class="text-center">{{ translate('visible') ?: 'Visible' }}</th>
                                    <th class="text-end">{{ translate('action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="manage-partners-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    const csrfToken = "{{ csrf_token() }}";
    const partnersUrlTemplate = "{{ route('admin.partner.global-catalog.partners', ['productId' => '__ID__']) }}";
    const assignUrlTemplate = "{{ route('admin.partner.global-catalog.assign', ['productId' => '__ID__']) }}";
    const toggleUrlTemplate = "{{ route('admin.partner.global-catalog.toggle', ['productId' => '__ID__']) }}";
    const modalEl = document.getElementById('managePartnersModal');
    const loadingEl = document.getElementById('manage-partners-loading');
    const errorEl = document.getElementById('manage-partners-error');
    const contentEl = document.getElementById('manage-partners-content');
    const tbodyEl = document.getElementById('manage-partners-tbody');
    const productNameEl = document.getElementById('manage-product-name');
    const productMetaEl = document.getElementById('manage-product-meta');

    let activeProductId = null;

    function urlFromTemplate(template, productId) {
        return template.replace('__ID__', String(productId));
    }

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

    function ajaxPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.message || ('HTTP ' + res.status));
                }
                return data;
            });
        });
    }

    function renderPartners(data) {
        productNameEl.textContent = data.product.name;
        productMetaEl.textContent = '#' + data.product.id + ' · ' + data.product.fulfillment_type + ' · ref ' + data.product.reference_price;

        tbodyEl.innerHTML = (data.partners || []).map(function (row) {
            const assigned = !!row.assigned;
            const priceValue = row.partner_price !== null ? row.partner_price : data.product.reference_price;
            return '<tr data-key-id="' + row.reseller_key_id + '">' +
                '<td>' +
                    '<div class="fw-semibold">' + row.partner_name + '</div>' +
                    (row.account_name ? '<div class="text-muted small">' + row.account_name + '</div>' : '') +
                '</td>' +
                '<td class="text-end">' +
                    '<input type="number" min="0.0001" step="any" class="form-control form-control-sm partner-price-input" value="' + priceValue + '" ' + (assigned ? '' : 'disabled') + '>' +
                '</td>' +
                '<td class="text-center">' +
                    '<div class="form-check form-switch d-inline-block">' +
                        '<input class="form-check-input partner-visible-toggle" type="checkbox" ' +
                            (row.is_active ? 'checked' : '') + ' ' + (assigned ? '' : 'disabled') + '>' +
                    '</div>' +
                '</td>' +
                '<td class="text-end">' +
                    (assigned
                        ? '<button type="button" class="btn btn-sm btn-primary partner-save-btn">{{ translate('save') }}</button>'
                        : '<button type="button" class="btn btn-sm btn-outline-primary partner-assign-btn">{{ translate('assign') ?: 'Assign' }}</button>') +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function loadPartners(productId) {
        activeProductId = productId;
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        errorEl.classList.add('d-none');

        fetch(urlFromTemplate(partnersUrlTemplate, productId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load partners');
                }
                renderPartners(data);
                loadingEl.classList.add('d-none');
                contentEl.classList.remove('d-none');
            })
            .catch(function (err) {
                loadingEl.classList.add('d-none');
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
            });
    }

    document.querySelectorAll('.manage-partners-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            loadPartners(btn.dataset.productId);
        });
    });

    tbodyEl.addEventListener('click', function (e) {
        const assignBtn = e.target.closest('.partner-assign-btn');
        const saveBtn = e.target.closest('.partner-save-btn');
        const row = e.target.closest('tr');
        if (!row || !activeProductId) return;

        const keyId = parseInt(row.dataset.keyId, 10);
        const priceInput = row.querySelector('.partner-price-input');
        const price = parseFloat(priceInput.value || '0');

        if (assignBtn) {
            if (!(price > 0)) {
                showError('{{ translate('enter_a_valid_partner_price') ?: 'Enter a partner price greater than 0.' }}');
                return;
            }
            assignBtn.disabled = true;
            ajaxPost(urlFromTemplate(assignUrlTemplate, activeProductId), {
                reseller_key_id: keyId,
                partner_price: price,
                currency: 'USD',
                is_active: true,
            }).then(function (res) {
                showSuccess(res.message || '{{ translate('partner_catalog_item_saved') ?: 'Partner catalog updated.' }}');
                loadPartners(activeProductId);
            }).catch(function (err) {
                assignBtn.disabled = false;
                showError(err.message);
            });
        }

        if (saveBtn) {
            if (!(price > 0)) {
                showError('{{ translate('enter_a_valid_partner_price') ?: 'Enter a partner price greater than 0.' }}');
                return;
            }
            saveBtn.disabled = true;
            ajaxPost(urlFromTemplate(assignUrlTemplate, activeProductId), {
                reseller_key_id: keyId,
                partner_price: price,
                currency: 'USD',
                is_active: row.querySelector('.partner-visible-toggle').checked,
            }).then(function (res) {
                showSuccess(res.message || '{{ translate('partner_catalog_item_saved') ?: 'Partner catalog updated.' }}');
                saveBtn.disabled = false;
            }).catch(function (err) {
                saveBtn.disabled = false;
                showError(err.message);
            });
        }
    });

    tbodyEl.addEventListener('change', function (e) {
        const toggle = e.target.closest('.partner-visible-toggle');
        if (!toggle || toggle.disabled || !activeProductId) return;

        const row = toggle.closest('tr');
        const keyId = parseInt(row.dataset.keyId, 10);

        ajaxPost(urlFromTemplate(toggleUrlTemplate, activeProductId), {
            reseller_key_id: keyId,
            is_active: toggle.checked,
        }).then(function (res) {
            showSuccess(res.message || '{{ translate('status_updated_successfully') }}');
        }).catch(function (err) {
            toggle.checked = !toggle.checked;
            showError(err.message);
        });
    });
})();
</script>
@endpush
