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
                            <select name="supplier_api_id" class="form-control" required>
                                <option value="">{{ translate('select_supplier') }}</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_api_id', $mapping->supplier_api_id) == $supplier->id ? 'selected' : '' }}>
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
})();
</script>
@endpush
