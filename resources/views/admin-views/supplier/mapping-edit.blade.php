@extends('layouts.admin.app')

@section('title', translate('edit_product_mapping'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">{{ translate('edit_product_supplier_mapping') }}</h3>

            <form action="{{ route('admin.supplier.mapping.update', $mapping->id) }}" method="post">
                @csrf

                <div class="row gy-3">
                    @include('admin-views.supplier.partials._mapping-product-picker', [
                        'categories' => $categories,
                        'selectedProduct' => $mapping->product,
                    ])

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier') }} <span class="text-danger">*</span></label>
                            <select name="supplier_api_id" id="supplier-api-select" class="form-control" required>
                                <option value="">{{ translate('select_supplier') }}</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                            data-supports-direct-topup="{{ $supplier->supports_direct_top_up ? '1' : '0' }}"
                                            {{ old('supplier_api_id', $mapping->supplier_api_id) == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->name }} ({{ $supplier->driver }}){{ $supplier->is_active ? '' : ' — '.translate('inactive') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier_product_id_SKU') }} <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_product_id" class="form-control"
                                   value="{{ old('supplier_product_id', $mapping->supplier_product_id) }}" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier_product_name') }}</label>
                            <input type="text" name="supplier_product_name" class="form-control"
                                   value="{{ old('supplier_product_name', $mapping->supplier_product_name) }}">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('cost_price') }} <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" class="form-control" step="0.01" min="0"
                                   value="{{ old('cost_price', $mapping->cost_price) }}" required>
                        </div>
                    </div>

                    <div class="col-lg-2">
                        <div class="form-group">
                            <label class="form-label">{{ translate('currency') }}</label>
                            <input type="text" name="cost_currency" class="form-control" maxlength="3"
                                   value="{{ old('cost_currency', $mapping->cost_currency) }}">
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('markup_type') }} <span class="text-danger">*</span></label>
                            <select name="markup_type" class="form-control" required>
                                <option value="percent" {{ old('markup_type', $mapping->markup_type) == 'percent' ? 'selected' : '' }}>{{ translate('percent') }} (%)</option>
                                <option value="flat" {{ old('markup_type', $mapping->markup_type) == 'flat' ? 'selected' : '' }}>{{ translate('flat') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('markup_value') }}</label>
                            <input type="number" name="markup_value" class="form-control" step="0.01" min="0"
                                   value="{{ old('markup_value', $mapping->markup_value) }}">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('priority') }} <span class="text-danger">*</span></label>
                            <input type="number" name="priority" class="form-control" min="0"
                                   value="{{ old('priority', $mapping->priority) }}" required>
                            <small class="text-muted">{{ translate('lower_number_=_higher_priority') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('min_stock_threshold') }}</label>
                            <input type="number" name="min_stock_threshold" class="form-control" min="0"
                                   value="{{ old('min_stock_threshold', $mapping->min_stock_threshold) }}">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="form-label">{{ translate('max_restock_quantity') }}</label>
                            <input type="number" name="max_restock_qty" class="form-control" min="1"
                                   value="{{ old('max_restock_qty', $mapping->max_restock_qty) }}">
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_restock" value="1"
                                   id="auto-restock-toggle" {{ old('auto_restock', $mapping->auto_restock) ? 'checked' : '' }}>
                            <label class="form-check-label" for="auto-restock-toggle">
                                {{ translate('enable_auto_restock') }}
                            </label>
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
                                   id="customizable-toggle" {{ old('is_customizable', $mapping->is_customizable) ? 'checked' : '' }}>
                            <label class="form-check-label" for="customizable-toggle">
                                {{ translate('customizable_variable_amount') }}
                            </label>
                            <br>
                            <small class="text-muted">{{ translate('when_enabled_customer_can_enter_a_custom_amount_instead_of_a_fixed_denomination') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 customizable-fields" style="{{ old('is_customizable', $mapping->is_customizable) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('minimum_amount') }} <span class="text-danger">*</span></label>
                            <input type="number" name="min_amount" class="form-control" step="0.01" min="0"
                                   value="{{ old('min_amount', $mapping->min_amount) }}"
                                   placeholder="{{ translate('ex') }}: 5.00">
                            <small class="text-muted">{{ translate('minimum_value_the_customer_can_enter') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 customizable-fields" style="{{ old('is_customizable', $mapping->is_customizable) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('maximum_amount') }} <span class="text-danger">*</span></label>
                            <input type="number" name="max_amount" class="form-control" step="0.01" min="0"
                                   value="{{ old('max_amount', $mapping->max_amount) }}"
                                   placeholder="{{ translate('ex') }}: 500.00">
                            <small class="text-muted">{{ translate('maximum_value_the_customer_can_enter') }}</small>
                        </div>
                    </div>

                    {{-- ─── Direct Top-Up ──────────────────────────────────────── --}}
                    <div class="col-lg-12" id="direct-topup-section">
                        <hr class="my-2">
                        <h5 class="mb-3">{{ translate('direct_topup_settings') ?: 'Direct Top-Up Settings' }}</h5>
                    </div>

                    <div class="col-lg-12 direct-topup-section-content">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_direct_topup" value="1"
                                   id="direct-topup-toggle"
                                   data-mapping-id="{{ $mapping->id }}"
                                   data-validate-url="{{ route('admin.supplier.mapping.validate-direct-topup') }}"
                                   {{ old('is_direct_topup', $mapping->is_direct_topup) ? 'checked' : '' }}>
                            <label class="form-check-label" for="direct-topup-toggle">
                                {{ translate('enable_direct_topup') ?: 'Enable Direct Top-Up' }}
                            </label>
                            <br>
                            <small class="text-muted">{{ translate('direct_topup_mapping_hint') ?: 'When enabled, customers can enter an account ID and quantity. The supplier must support direct top-up to enable this.' }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6 direct-topup-fields direct-topup-section-content" style="{{ old('is_direct_topup', $mapping->is_direct_topup) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_account_label') ?: 'Account ID Input Label' }}</label>
                            <input type="text" name="direct_topup_account_label" class="form-control"
                                   value="{{ old('direct_topup_account_label', $mapping->direct_topup_account_label) }}"
                                   placeholder="{{ translate('direct_topup_account_label_placeholder') ?: 'Enter Account ID' }}">
                        </div>
                    </div>

                    <div class="col-lg-3 direct-topup-fields direct-topup-section-content" style="{{ old('is_direct_topup', $mapping->is_direct_topup) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_min_quantity') ?: 'Minimum Quantity' }}</label>
                            <input type="number" step="0.0001" min="0" name="direct_topup_min_quantity" class="form-control"
                                   value="{{ old('direct_topup_min_quantity', $mapping->direct_topup_min_quantity) }}">
                        </div>
                    </div>

                    <div class="col-lg-3 direct-topup-fields direct-topup-section-content" style="{{ old('is_direct_topup', $mapping->is_direct_topup) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_max_quantity') ?: 'Maximum Quantity' }}</label>
                            <input type="number" step="0.0001" min="0" name="direct_topup_max_quantity" class="form-control"
                                   value="{{ old('direct_topup_max_quantity', $mapping->direct_topup_max_quantity) }}">
                        </div>
                    </div>

                    <div class="col-lg-6 direct-topup-fields direct-topup-section-content" style="{{ old('is_direct_topup', $mapping->is_direct_topup) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_price_per_unit') ?: 'Price Per Unit' }}</label>
                            <input type="number" step="0.00000001" min="0" name="direct_topup_price_per_unit" class="form-control"
                                   value="{{ old('direct_topup_price_per_unit', $mapping->direct_topup_price_per_unit) }}">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fi fi-sr-check"></i> {{ translate('update') }}
                    </button>
                    <a href="{{ route('admin.supplier.mapping.list') }}" class="btn btn-secondary">
                        {{ translate('cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    const customizableToggle = document.getElementById('customizable-toggle');
    const customizableFields = document.querySelectorAll('.customizable-fields');
    if (customizableToggle) {
        customizableToggle.addEventListener('change', function () {
            customizableFields.forEach(el => {
                el.style.display = this.checked ? '' : 'none';
            });
        });
    }

    const supplierSelect = document.getElementById('supplier-api-select');
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
            const mappingId = this.getAttribute('data-mapping-id');
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
                    mapping_id: mappingId,
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
})();
</script>
@endpush
