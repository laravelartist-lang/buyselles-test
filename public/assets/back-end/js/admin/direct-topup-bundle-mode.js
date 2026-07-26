(function () {
    function syncDirectTopUpBundleModeFields() {
        const customizableRadio = document.getElementById('direct-topup-bundle-customizable');
        const quantityWrap = document.querySelector('.direct-topup-bundle-quantity-wrap');
        const quantityInput = quantityWrap ? quantityWrap.querySelector('input[name="direct_topup_bundle_quantity"]') : null;

        if (!customizableRadio || !quantityWrap) {
            return;
        }

        const isCustomizable = customizableRadio.checked;
        quantityWrap.style.display = isCustomizable ? '' : 'none';

        if (quantityInput) {
            quantityInput.required = isCustomizable;
            if (!isCustomizable) {
                quantityInput.value = '';
            }
        }
    }

    document.querySelectorAll('.direct-topup-bundle-mode').forEach(function (radio) {
        radio.addEventListener('change', syncDirectTopUpBundleModeFields);
    });

    syncDirectTopUpBundleModeFields();
    window.syncDirectTopUpBundleModeFields = syncDirectTopUpBundleModeFields;
})();
