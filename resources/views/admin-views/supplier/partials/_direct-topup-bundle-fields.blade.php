@php
    $mappingModel = $mapping ?? null;
    $selectedBundleMode = old(
        'direct_topup_bundle_mode',
        $mappingModel
            ? $mappingModel->directTopUpBundleMode()
            : \App\Models\SupplierProductMapping::DIRECT_TOPUP_BUNDLE_CUSTOMIZABLE,
    );
    $isCustomizableBundle = $selectedBundleMode !== \App\Models\SupplierProductMapping::DIRECT_TOPUP_BUNDLE_FIXED;
    $directTopupEnabled = (bool) old('is_direct_topup', $mappingModel?->is_direct_topup ?? false);
@endphp

<div class="col-lg-12 direct-topup-fields direct-topup-section-content direct-topup-bundle-mode-wrap" style="{{ $directTopupEnabled ? '' : 'display:none;' }}">
    <div class="form-group">
        <label class="form-label d-block">{{ translate('direct_topup_bundle_type') ?: 'Bundle type' }}</label>
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input class="form-check-input direct-topup-bundle-mode" type="radio"
                       name="direct_topup_bundle_mode"
                       id="direct-topup-bundle-customizable"
                       value="{{ \App\Models\SupplierProductMapping::DIRECT_TOPUP_BUNDLE_CUSTOMIZABLE }}"
                       {{ $isCustomizableBundle ? 'checked' : '' }}>
                <label class="form-check-label" for="direct-topup-bundle-customizable">
                    {{ translate('direct_topup_customizable_bundle') ?: 'Customizable bundle' }}
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input direct-topup-bundle-mode" type="radio"
                       name="direct_topup_bundle_mode"
                       id="direct-topup-bundle-fixed"
                       value="{{ \App\Models\SupplierProductMapping::DIRECT_TOPUP_BUNDLE_FIXED }}"
                       {{ ! $isCustomizableBundle ? 'checked' : '' }}>
                <label class="form-check-label" for="direct-topup-bundle-fixed">
                    {{ translate('direct_topup_fixed_bundle') ?: 'Fixed bundle' }}
                </label>
            </div>
        </div>
        <small class="text-muted d-block mt-1">
            {{ translate('direct_topup_customizable_bundle_hint') ?: 'You define how many credits/coins are sent to the supplier per purchase.' }}
        </small>
        <small class="text-muted d-block">
            {{ translate('direct_topup_fixed_bundle_hint') ?: 'The supplier SKU is already a ready-made package. Link it without entering bundle quantity.' }}
        </small>
    </div>
</div>

<div class="col-lg-6 direct-topup-fields direct-topup-section-content direct-topup-bundle-quantity-wrap" style="{{ ($directTopupEnabled && $isCustomizableBundle) ? '' : 'display:none;' }}">
    <div class="form-group">
        <label class="form-label">{{ translate('direct_topup_bundle_quantity') ?: 'Bundle quantity' }} <span class="text-danger direct-topup-bundle-required">*</span></label>
        <input type="number" name="direct_topup_bundle_quantity" class="form-control"
               value="{{ old('direct_topup_bundle_quantity', $mappingModel?->direct_topup_bundle_quantity ?? '') }}"
               min="0.0001" step="any" placeholder="1000">
        <small class="text-muted">{{ translate('direct_topup_bundle_quantity_hint') ?: 'Credits/coins sent to the supplier per purchase (e.g. 1000, 5000, 10000).' }}</small>
    </div>
</div>
