@php
    $selectedProduct = $selectedProduct ?? null;
    $selectedProductId = old('product_id', $selectedProduct?->id ?? '');
@endphp

<div class="col-12">
    <div class="alert alert-soft-info py-2 px-3 mb-3 fs-12">
        <i class="fi fi-rr-info me-1"></i>
        <strong>{{ translate('note') ?: 'Note' }}:</strong>
        {{ translate('supplier_mapping_in_house_products_only') ?: 'Only in-house digital products are listed here (vendor products are excluded).' }}
        {{ translate('supplier_mapping_not_all_products_note') ?: 'Not every product from the main Products section appears here — only active in-house ready digital products.' }}
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="form-group">
        <label class="form-label">{{ translate('category') }}</label>
        <select class="custom-select action-get-request-onchange" id="mapping-category_id"
            data-url-prefix="{{ url('/admin/products/get-categories?parent_id=') }}"
            data-element-id="mapping-sub-category-select"
            data-element-type="select">
            <option value="">{{ translate('select_category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category['id'] }}"
                    {{ (int) old('mapping_category_id', $selectedProduct?->category_id) === (int) $category['id'] ? 'selected' : '' }}>
                    {{ $category['defaultName'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="form-group">
        <label class="form-label">{{ translate('sub_Category') }}</label>
        <select class="custom-select action-get-request-onchange" name="mapping_sub_category_id"
            id="mapping-sub-category-select"
            data-id="{{ old('mapping_sub_category_id', $selectedProduct?->sub_category_id) }}"
            data-url-prefix="{{ url('/admin/products/get-categories?parent_id=') }}"
            data-element-id="mapping-sub-sub-category-select"
            data-element-type="select">
            <option value="">{{ translate('select_sub_category_first') ?: translate('select') }}</option>
        </select>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="form-group">
        <label class="form-label">{{ translate('sub_Sub_Category') }}</label>
        <select class="custom-select" id="mapping-sub-sub-category-select"
            data-id="{{ old('mapping_sub_sub_category_id', $selectedProduct?->sub_sub_category_id) }}">
            <option value="">{{ translate('select_sub_category_first') ?: translate('select') }}</option>
        </select>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="form-group">
        <label class="form-label">{{ translate('product') }} <span class="text-danger">*</span></label>
        <select name="product_id" id="mapping-product_id" class="form-control" required
            data-selected-id="{{ $selectedProductId }}">
            <option value="">{{ translate('select_category_to_load_products') ?: 'Select a category to load products' }}</option>
        </select>
        <small class="text-muted" id="mapping-product-count"></small>
    </div>
</div>

@push('script')
<script>
(function () {
    'use strict';

    var productsUrl = @json(route('admin.supplier.mapping.in-house-products'));
    var selectCategoryText = @json(translate('select_category_to_load_products') ?: 'Select a category to load products');
    var noProductsText = @json(translate('no_products_found'));
    var productSelect = document.getElementById('mapping-product_id');
    var countEl = document.getElementById('mapping-product-count');

    if (!productSelect) {
        return;
    }

    function selectedProductId() {
        return productSelect.getAttribute('data-selected-id') || '';
    }

    function clearProductSelect(message) {
        productSelect.innerHTML = '<option value="">' + (message || selectCategoryText) + '</option>';
        if (countEl) {
            countEl.textContent = '';
        }
    }

    function loadInHouseProducts() {
        var categoryId = document.getElementById('mapping-category_id')?.value || '';
        var subCategoryId = document.getElementById('mapping-sub-category-select')?.value || '';
        var subSubCategoryId = document.getElementById('mapping-sub-sub-category-select')?.value || '';

        if (!categoryId) {
            clearProductSelect(selectCategoryText);
            return;
        }

        var params = new URLSearchParams({
            category_id: categoryId,
            sub_category_id: subCategoryId,
            sub_sub_category_id: subSubCategoryId,
        });

        productSelect.disabled = true;
        clearProductSelect(@json(translate('loading') ?: 'Loading...'));

        fetch(productsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var products = data.products || [];
                var keepId = selectedProductId();

                if (!products.length) {
                    clearProductSelect(noProductsText);
                    productSelect.disabled = false;
                    return;
                }

                var html = '<option value="">' + @json(translate('select_product')) + '</option>';
                products.forEach(function (product) {
                    var selected = String(product.id) === String(keepId) ? ' selected' : '';
                    html += '<option value="' + product.id + '"' + selected + '>' +
                        product.name + ' (#' + product.id + ')</option>';
                });

                productSelect.innerHTML = html;
                productSelect.disabled = false;

                if (countEl) {
                    countEl.textContent = products.length + ' ' + @json(translate('product(s)_found') ?: 'product(s) found');
                }
            })
            .catch(function () {
                clearProductSelect(@json(translate('failed_to_load') ?: 'Failed to load'));
                productSelect.disabled = false;
            });
    }

    $(document).on('change', '#mapping-category_id, #mapping-sub-category-select, #mapping-sub-sub-category-select', function () {
        productSelect.setAttribute('data-selected-id', '');
        loadInHouseProducts();
    });

    document.addEventListener('DOMContentLoaded', function () {
        var categoryId = document.getElementById('mapping-category_id')?.value;
        var subCategoryId = document.getElementById('mapping-sub-category-select')?.getAttribute('data-id');
        var subSubCategoryId = document.getElementById('mapping-sub-sub-category-select')?.getAttribute('data-id');

        if (categoryId && typeof getRequestFunctionality === 'function') {
            getRequestFunctionality(
                @json(route('admin.products.get-categories')) + '?parent_id=' + categoryId + '&sub_category=' + (subCategoryId || ''),
                'mapping-sub-category-select',
                'select'
            );

            if (subCategoryId) {
                setTimeout(function () {
                    getRequestFunctionality(
                        @json(route('admin.products.get-categories')) + '?parent_id=' + subCategoryId + '&sub_category=' + (subSubCategoryId || ''),
                        'mapping-sub-sub-category-select',
                        'select'
                    );
                    setTimeout(loadInHouseProducts, 200);
                }, 150);
            } else {
                setTimeout(loadInHouseProducts, 150);
            }
        }
    });
}());
</script>
@endpush
