<div class="col-lg-12 code-source-priority-fields">
    <div class="form-group">
        <label class="form-label">{{ translate('code_fulfillment_source') }}</label>
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="code_source_priority"
                       id="code-source-local-first" value="local_first"
                       {{ old('code_source_priority', $selected ?? \App\Models\SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST) === \App\Models\SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST ? 'checked' : '' }}>
                <label class="form-check-label" for="code-source-local-first">
                    {{ translate('local_pool_first') }}
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="code_source_priority"
                       id="code-source-supplier-first" value="supplier_first"
                       {{ old('code_source_priority', $selected ?? \App\Models\SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST) === \App\Models\SupplierProductMapping::CODE_SOURCE_SUPPLIER_FIRST ? 'checked' : '' }}>
                <label class="form-check-label" for="code-source-supplier-first">
                    {{ translate('supplier_api_first') }}
                </label>
            </div>
        </div>
        <small class="text-muted d-block mt-1">{{ translate('code_fulfillment_source_help') }}</small>
    </div>
</div>
