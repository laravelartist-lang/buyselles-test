@php
    $resolvedDefaultDriver = $defaultDriver ?? (isset($supplier) ? $supplier->driver : null) ?? ($drivers[0] ?? 'generic_rest');
    $resolvedAuthType = old(
        'auth_type',
        isset($supplier) ? $supplier->auth_type : ($driverPresets[$resolvedDefaultDriver]['auth_type'] ?? 'bearer_token'),
    );

    $supplierDriverConfig = [
        'mode' => $formMode ?? 'add',
        'defaultDriver' => $resolvedDefaultDriver,
        'currentAuthType' => $resolvedAuthType,
        'schemas' => $driverSchemas ?? [],
        'presets' => $driverPresets ?? [],
        'settingsValues' => old('settings', isset($supplier) ? ($supplier->settings ?? []) : []),
        'credentialStatus' => $credentialStatus ?? [],
        'labels' => [
            'noCredentials' => translate('no_credentials_needed_for_this_driver'),
            'noSettings' => translate('no_settings_available_for_this_driver'),
            'enterValue' => translate('enter_value'),
            'enterNewOrBlank' => translate('enter_new_value_or_leave_blank'),
            'set' => translate('set'),
        ],
    ];
@endphp

<script type="application/json" id="supplier-driver-config">
    @json($supplierDriverConfig)
</script>
