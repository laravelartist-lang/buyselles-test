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
        <select class="form-control mapping-category-cascade" id="mapping-category_id"
            data-target-id="mapping-sub-category-select">
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
        <select class="form-control mapping-category-cascade" name="mapping_sub_category_id"
            id="mapping-sub-category-select"
            data-selected-id="{{ old('mapping_sub_category_id', $selectedProduct?->sub_category_id) }}"
            data-target-id="mapping-sub-sub-category-select">
            <option value="">{{ translate('select_sub_category_first') ?: translate('select') }}</option>
        </select>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="form-group">
        <label class="form-label">{{ translate('sub_Sub_Category') }}</label>
        <select class="form-control mapping-category-cascade" id="mapping-sub-sub-category-select"
            data-selected-id="{{ old('mapping_sub_sub_category_id', $selectedProduct?->sub_sub_category_id) }}">
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
    var categoriesUrl = @json(route('admin.products.get-categories'));
    var selectCategoryText = @json(translate('select_category_to_load_products') ?: 'Select a category to load products');
    var noProductsText = @json(translate('no_products_found'));
    var selectPlaceholderText = @json(translate('select'));
    var subCategoryPlaceholderText = @json(translate('select_sub_category_first') ?: translate('select'));
    var failedToLoadText = @json(translate('failed_to_load') ?: 'Failed to load');
    var loadingText = @json(translate('loading') ?: 'Loading...');
    var selectProductText = @json(translate('select_product'));
    var productsFoundText = @json(translate('product(s)_found') ?: 'product(s) found');

    function initMappingProductPicker() {
        var $categorySelect = $('#mapping-category_id');
        var $subCategorySelect = $('#mapping-sub-category-select');
        var $subSubCategorySelect = $('#mapping-sub-sub-category-select');
        var $productSelect = $('#mapping-product_id');
        var $countEl = $('#mapping-product-count');

        if (!$productSelect.length || !$categorySelect.length) {
            return;
        }

        function selectedProductId() {
            return $productSelect.attr('data-selected-id') || '';
        }

        function clearProductSelect(message) {
            $productSelect.html('<option value="">' + (message || selectCategoryText) + '</option>');
            $countEl.text('');
        }

        function resetSelect($select, placeholderText) {
            if (!$select.length) {
                return;
            }

            $select.html('<option value="">' + (placeholderText || selectPlaceholderText) + '</option>');
        }

        function resetDownstreamFrom($select) {
            var nextId = $select.attr('data-target-id');
            if (!nextId) {
                return;
            }

            var $nextSelect = $('#' + nextId);
            resetSelect($nextSelect, subCategoryPlaceholderText);

            if ($nextSelect.length) {
                resetDownstreamFrom($nextSelect);
            }
        }

        function loadCategoryOptions(parentId, $targetSelect, selectedId) {
            if (!parentId || !$targetSelect.length) {
                return $.Deferred().resolve().promise();
            }

            var url = categoriesUrl
                + '?parent_id=' + encodeURIComponent(parentId)
                + '&sub_category=' + encodeURIComponent(selectedId || '');

            return $.get({
                url: url,
                dataType: 'json',
                beforeSend: function () {
                    $('#loading').fadeIn();
                },
            }).done(function (data) {
                $targetSelect.empty().append(data.select_tag || ('<option value="">' + selectPlaceholderText + '</option>'));

                var downstreamId = $targetSelect.attr('data-target-id');
                if (downstreamId && data.sub_categories) {
                    $('#' + downstreamId).empty().append(data.sub_categories);
                }
            }).fail(function () {
                resetSelect($targetSelect, failedToLoadText);
            }).always(function () {
                $('#loading').fadeOut();
            });
        }

        function loadInHouseProducts() {
            var categoryId = $categorySelect.val() || '';
            var subCategoryId = $subCategorySelect.val() || '';
            var subSubCategoryId = $subSubCategorySelect.val() || '';

            if (!categoryId) {
                clearProductSelect(selectCategoryText);
                $productSelect.prop('disabled', false);
                return $.Deferred().resolve().promise();
            }

            $productSelect.prop('disabled', true);
            clearProductSelect(loadingText);

            return $.get({
                url: productsUrl,
                dataType: 'json',
                data: {
                    category_id: categoryId,
                    sub_category_id: subCategoryId,
                    sub_sub_category_id: subSubCategoryId,
                },
            }).done(function (data) {
                var products = data.products || [];
                var keepId = selectedProductId();

                if (!products.length) {
                    clearProductSelect(noProductsText);
                    $productSelect.prop('disabled', false);
                    return;
                }

                var html = '<option value="">' + selectProductText + '</option>';
                products.forEach(function (product) {
                    var selected = String(product.id) === String(keepId) ? ' selected' : '';
                    html += '<option value="' + product.id + '"' + selected + '>' +
                        product.name + ' (#' + product.id + ')</option>';
                });

                $productSelect.html(html).prop('disabled', false);
                $countEl.text(products.length + ' ' + productsFoundText);
            }).fail(function () {
                clearProductSelect(failedToLoadText);
                $productSelect.prop('disabled', false);
            });
        }

        $categorySelect.off('change.mappingPicker').on('change.mappingPicker', function () {
            $productSelect.attr('data-selected-id', '');
            resetDownstreamFrom($categorySelect);

            if (!$categorySelect.val()) {
                clearProductSelect(selectCategoryText);
                return;
            }

            loadCategoryOptions($categorySelect.val(), $subCategorySelect, '')
                .then(loadInHouseProducts);
        });

        $subCategorySelect.off('change.mappingPicker').on('change.mappingPicker', function () {
            $productSelect.attr('data-selected-id', '');
            resetDownstreamFrom($subCategorySelect);

            if (!$subCategorySelect.val()) {
                loadInHouseProducts();
                return;
            }

            loadCategoryOptions($subCategorySelect.val(), $subSubCategorySelect, '')
                .then(loadInHouseProducts);
        });

        $subSubCategorySelect.off('change.mappingPicker').on('change.mappingPicker', function () {
            $productSelect.attr('data-selected-id', '');
            loadInHouseProducts();
        });

        var categoryId = $categorySelect.val();
        var subCategoryId = $subCategorySelect.attr('data-selected-id') || '';
        var subSubCategoryId = $subSubCategorySelect.attr('data-selected-id') || '';

        if (categoryId) {
            loadCategoryOptions(categoryId, $subCategorySelect, subCategoryId)
                .then(function () {
                    if (subCategoryId) {
                        return loadCategoryOptions(subCategoryId, $subSubCategorySelect, subSubCategoryId);
                    }
                })
                .then(loadInHouseProducts);
        }
    }

    if (typeof jQuery === 'undefined') {
        return;
    }

    $(initMappingProductPicker);
}());
</script>
@endpush
