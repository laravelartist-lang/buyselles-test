(function () {
    'use strict';

    const GENERIC_REST_SETTING_GROUPS = [
        {
            id: 'connection',
            title: 'Connection & Auth',
            keys: [
                'auth_test_endpoint',
                'health_endpoint',
                'response_unwrap_path',
                'login_endpoint',
                'login_method',
                'login_token_response_path',
                'token_cache_minutes',
                'api_key_header',
                'custom_headers',
            ],
        },
        {
            id: 'catalog',
            title: 'Product Catalog',
            keys: [
                'products_endpoint',
                'products_response_path',
                'product_id_field',
                'product_name_field',
                'product_price_field',
                'product_stock_field',
                'product_stock_default',
                'product_category_field',
                'product_region_field',
                'price_decimal_places',
            ],
        },
        {
            id: 'pagination',
            title: 'Pagination',
            keys: [
                'pagination_enabled',
                'pagination_page_param',
                'pagination_per_page_param',
                'pagination_per_page_default',
                'pagination_data_path',
                'pagination_last_page_path',
                'pagination_total_path',
                'pagination_page_base',
            ],
        },
        {
            id: 'orders',
            title: 'Orders & Stock',
            keys: [
                'stock_endpoint',
                'order_endpoint',
                'order_product_id_field',
                'order_quantity_field',
                'order_id_response_path',
                'order_status_response_path',
                'order_codes_response_path',
                'order_codes_value_field',
                'order_codes_serial_field',
                'order_unit_price_field',
            ],
        },
        {
            id: 'topup',
            title: 'Direct Top-up',
            keys: [
                'topup_order_endpoint',
                'topup_product_id_field',
                'topup_quantity_field',
                'topup_account_field',
                'topup_region_field',
                'topup_idempotency_key_field',
                'topup_client_order_id_field',
                'topup_quantity_as_string',
                'topup_unit_price_field',
                'topup_status_response_path',
                'topup_order_id_response_path',
                'topup_pending_status_values',
            ],
        },
        {
            id: 'webhook',
            title: 'Webhook & Misc',
            keys: [
                'webhook_secret',
                'webhook_signature_header',
                'webhook_signature_prefix',
                'webhook_hash_algo',
                'webhook_type_path',
                'webhook_order_id_path',
                'webhook_status_path',
                'webhook_event_fulfilled_types',
                'webhook_event_failed_types',
                'source_currency',
                'status_map',
            ],
        },
    ];

    const AUTH_TYPE_LABELS = {
        api_key: 'API Key',
        bearer_token: 'Bearer Token',
        login_via: 'Login Via',
        oauth2: 'OAuth2',
        basic: 'Basic Auth',
        hmac: 'HMAC',
    };

    function parseConfig() {
        const node = document.getElementById('supplier-driver-config');

        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            console.error('Invalid supplier driver config JSON', error);

            return null;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function inputTypeForField(fieldConfig) {
        const type = fieldConfig?.type || 'text';

        if (type === 'password') {
            return 'password';
        }

        if (type === 'number') {
            return 'number';
        }

        if (type === 'email') {
            return 'email';
        }

        return 'text';
    }

    function buildFieldHtml({
        namePrefix,
        fieldKey,
        fieldConfig,
        value = '',
        placeholder = '',
        required = false,
        badgeHtml = '',
    }) {
        const label = fieldConfig?.label || fieldKey.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        const inputType = inputTypeForField(fieldConfig);
        const requiredAttr = required ? 'required' : '';
        const requiredMark = required ? '<span class="text-danger">*</span>' : '';

        return `
            <div class="col-lg-6" data-field-key="${escapeHtml(fieldKey)}">
                <div class="form-group">
                    <label class="form-label">
                        ${escapeHtml(label)} ${requiredMark}
                        ${badgeHtml}
                    </label>
                    <input type="${inputType}"
                           name="${escapeHtml(namePrefix)}[${escapeHtml(fieldKey)}]"
                           class="form-control"
                           value="${escapeHtml(value)}"
                           placeholder="${escapeHtml(placeholder)}"
                           autocomplete="new-password"
                           ${requiredAttr}>
                </div>
            </div>
        `;
    }

    function getVisibleCredentialKeys(driver, authType, config) {
        const preset = config.presets?.[driver] || {};
        const mapping = preset.credential_fields_by_auth || {};
        const keys = mapping[authType] || Object.keys(config.schemas?.[driver]?.credentials || {});

        return keys.filter((key) => config.schemas?.[driver]?.credentials?.[key]);
    }

    function renderAuthTypeOptions(driver, selectedAuthType, config) {
        const select = document.getElementById('auth-type-select');
        const form = document.getElementById('supplier-form');

        if (!select) {
            return '';
        }

        const preset = config.presets?.[driver] || {};
        const authTypes = preset.auth_types || ['api_key', 'bearer_token', 'login_via', 'oauth2', 'basic', 'hmac'];
        const resolvedAuthType = authTypes.includes(selectedAuthType) ? selectedAuthType : (authTypes[0] || 'api_key');

        select.innerHTML = authTypes
            .map((authType) => {
                const label = AUTH_TYPE_LABELS[authType] || authType;
                const selected = authType === resolvedAuthType ? 'selected' : '';

                return `<option value="${escapeHtml(authType)}" ${selected}>${escapeHtml(label)}</option>`;
            })
            .join('');

        const existingHidden = document.getElementById('auth-type-hidden');
        const lockAuthType = authTypes.length <= 1;

        if (lockAuthType) {
            select.disabled = true;
            select.removeAttribute('name');

            let hidden = existingHidden;
            if (!hidden && form) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'auth_type';
                hidden.id = 'auth-type-hidden';
                form.appendChild(hidden);
            }

            if (hidden) {
                hidden.value = resolvedAuthType;
            }
        } else {
            select.disabled = false;
            select.setAttribute('name', 'auth_type');
            existingHidden?.remove();
        }

        return resolvedAuthType;
    }

    function renderDriverDescription(driver, config) {
        const node = document.getElementById('driver-description');

        if (!node) {
            return;
        }

        const descriptions = {
            generic_rest: 'Configurable REST driver for custom supplier APIs (Secret Orca, Golf API, etc.). Fields below adapt to the selected auth type.',
            bamboo: 'Bamboo Card Portal API. Uses HTTP Basic Auth with Client ID and Client Secret. Catalog sync is supported.',
        };

        node.textContent = descriptions[driver] || '';
        node.classList.toggle('d-none', !descriptions[driver]);
    }

    function renderCredentials(driver, config) {
        const container = document.getElementById('credentials-section');

        if (!container) {
            return;
        }

        const schema = config.schemas?.[driver]?.credentials || {};
        const authType = document.getElementById('auth-type-select')?.value || config.presets?.[driver]?.auth_type;
        const visibleKeys = getVisibleCredentialKeys(driver, authType, config);
        const isEdit = config.mode === 'edit';
        const credentialStatus = config.credentialStatus || {};

        if (visibleKeys.length === 0) {
            container.innerHTML = `<div class="col-lg-12"><p class="text-muted mb-0">${escapeHtml(config.labels?.noCredentials || 'No credentials needed for this driver.')}</p></div>`;

            return;
        }

        container.innerHTML = visibleKeys
            .map((fieldKey) => {
                const fieldConfig = schema[fieldKey] || {};
                const badgeHtml = isEdit && credentialStatus[fieldKey]
                    ? `<span class="badge bg-success ms-1">${escapeHtml(config.labels?.set || 'Set')}</span>`
                    : '';

                return buildFieldHtml({
                    namePrefix: 'credentials',
                    fieldKey,
                    fieldConfig,
                    placeholder: isEdit
                        ? (config.labels?.enterNewOrBlank || 'Enter new value or leave blank')
                        : (config.labels?.enterValue || 'Enter value'),
                    required: !isEdit && Boolean(fieldConfig.required),
                    badgeHtml,
                });
            })
            .join('');
    }

    function renderSettingsGroup(title, settings, values, keys) {
        const fields = keys
            .filter((key) => settings[key])
            .map((key) => buildFieldHtml({
                namePrefix: 'settings',
                fieldKey: key,
                fieldConfig: settings[key],
                value: values[key] ?? settings[key].default ?? '',
            }))
            .join('');

        if (!fields) {
            return '';
        }

        return `
            <div class="col-12">
                <div class="border rounded p-3 mb-2 bg-light">
                    <h6 class="mb-3 text-capitalize">${escapeHtml(title)}</h6>
                    <div class="row gy-3">${fields}</div>
                </div>
            </div>
        `;
    }

    function renderSettings(driver, config) {
        const container = document.getElementById('settings-section');

        if (!container) {
            return;
        }

        const settings = config.schemas?.[driver]?.settings || {};
        const values = config.settingsValues || {};
        const keys = Object.keys(settings);

        if (keys.length === 0) {
            container.innerHTML = `<div class="col-lg-12"><p class="text-muted mb-0">${escapeHtml(config.labels?.noSettings || 'No settings available for this driver.')}</p></div>`;

            return;
        }

        if (driver === 'generic_rest') {
            const renderedKeys = new Set();
            let html = '';

            GENERIC_REST_SETTING_GROUPS.forEach((group) => {
                html += renderSettingsGroup(group.title, settings, values, group.keys);
                group.keys.forEach((key) => renderedKeys.add(key));
            });

            const remainingKeys = keys.filter((key) => !renderedKeys.has(key));

            if (remainingKeys.length > 0) {
                html += renderSettingsGroup('Other', settings, values, remainingKeys);
            }

            container.innerHTML = html;

            return;
        }

        container.innerHTML = `<div class="row gy-3 w-100 m-0">${keys
            .map((key) => buildFieldHtml({
                namePrefix: 'settings',
                fieldKey: key,
                fieldConfig: settings[key],
                value: values[key] ?? settings[key].default ?? '',
            }))
            .join('')}</div>`;
    }

    function applyConnectorPreset(presetKey, config) {
        const preset = config.connectorPresets?.[presetKey];

        if (!preset) {
            return;
        }

        const driverSelect = document.getElementById('driver-select');
        const nameInput = document.querySelector('input[name="name"]');
        const sandboxToggle = document.getElementById('sandbox-toggle');
        const baseUrlInput = document.getElementById('base-url-input');
        const rateLimitInput = document.getElementById('rate-limit-input');
        const topupToggle = document.getElementById('topup-toggle');

        if (driverSelect && preset.driver) {
            driverSelect.value = preset.driver;
        }

        if (nameInput && preset.name && !nameInput.value) {
            nameInput.value = preset.name;
        }

        if (baseUrlInput && preset.base_url) {
            baseUrlInput.value = preset.base_url;
        }

        if (rateLimitInput && preset.rate_limit_per_minute !== undefined) {
            rateLimitInput.value = preset.rate_limit_per_minute;
        }

        if (topupToggle && preset.supports_direct_top_up !== undefined) {
            topupToggle.checked = Boolean(preset.supports_direct_top_up);
        }

        if (sandboxToggle && preset.is_sandbox !== undefined) {
            sandboxToggle.checked = Boolean(preset.is_sandbox);
        }

        config.settingsValues = { ...(preset.settings || {}) };
        config.currentAuthType = preset.auth_type || config.currentAuthType;

        applyDriverPreset(preset.driver || driverSelect?.value, config, { preserveExisting: false });
    }

    function initTestTopUpPanel(config) {
        const placeBtn = document.getElementById('test-topup-place-btn');
        const pollBtn = document.getElementById('test-topup-poll-btn');
        const resultNode = document.getElementById('test-topup-result');

        if (!placeBtn || !config.routes?.testTopup) {
            return;
        }

        let lastOrderNumber = '';

        const renderResult = (payload) => {
            if (resultNode) {
                resultNode.textContent = JSON.stringify(payload, null, 2);
            }
        };

        placeBtn.addEventListener('click', async () => {
            placeBtn.disabled = true;

            try {
                const response = await fetch(config.routes.testTopup, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: document.getElementById('test-topup-product-id')?.value || '',
                        target_account: document.getElementById('test-topup-target-account')?.value || '',
                        quantity: document.getElementById('test-topup-quantity')?.value || '',
                        region: document.getElementById('test-topup-region')?.value || '',
                    }),
                });

                const data = await response.json();
                renderResult(data);

                if (data.success && data.order_number) {
                    lastOrderNumber = data.order_number;
                    if (pollBtn) {
                        pollBtn.disabled = false;
                    }
                }
            } catch (error) {
                renderResult({ success: false, message: error.message });
            } finally {
                placeBtn.disabled = false;
            }
        });

        pollBtn?.addEventListener('click', async () => {
            if (!lastOrderNumber || !config.routes?.pollTopup) {
                return;
            }

            pollBtn.disabled = true;

            try {
                const response = await fetch(config.routes.pollTopup, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ order_number: lastOrderNumber }),
                });

                renderResult(await response.json());
            } catch (error) {
                renderResult({ success: false, message: error.message });
            } finally {
                pollBtn.disabled = false;
            }
        });
    }

    function applyDriverPreset(driver, config, options = {}) {
        const preset = config.presets?.[driver] || {};
        const preserveExisting = Boolean(options.preserveExisting);

        const baseUrlInput = document.getElementById('base-url-input');
        const rateLimitInput = document.getElementById('rate-limit-input');
        const topupToggle = document.getElementById('topup-toggle');

        if (!preserveExisting) {
            if (baseUrlInput && preset.base_url !== undefined) {
                baseUrlInput.value = preset.base_url;
            }

            if (rateLimitInput && preset.rate_limit_per_minute !== undefined) {
                rateLimitInput.value = preset.rate_limit_per_minute;
            }

            if (topupToggle && preset.supports_direct_top_up !== undefined) {
                topupToggle.checked = Boolean(preset.supports_direct_top_up);
            }
        }

        const selectedAuthType = preserveExisting
            ? (document.getElementById('auth-type-select')?.value || config.currentAuthType || preset.auth_type)
            : (config.currentAuthType || preset.auth_type);

        const resolvedAuthType = renderAuthTypeOptions(driver, selectedAuthType, config);
        config.currentAuthType = resolvedAuthType;
        renderDriverDescription(driver, config);
        renderCredentials(driver, config);
        renderSettings(driver, config);
    }

    function initSupplierForm() {
        const config = parseConfig();

        if (!config) {
            return;
        }

        const driverSelect = document.getElementById('driver-select');
        const authTypeSelect = document.getElementById('auth-type-select');

        if (!driverSelect) {
            return;
        }

        const applyCurrentDriver = (preserveExisting = true) => {
            const driver = driverSelect.value || config.defaultDriver;

            if (!driver) {
                return;
            }

            applyDriverPreset(driver, config, { preserveExisting });
        };

        driverSelect.addEventListener('change', () => {
            applyDriverPreset(driverSelect.value, config, { preserveExisting: false });
        });

        if (authTypeSelect) {
            authTypeSelect.addEventListener('change', applyCurrentDriver);
        }

        const connectorPresetSelect = document.getElementById('connector-preset-select');
        if (connectorPresetSelect) {
            connectorPresetSelect.addEventListener('change', () => {
                const presetKey = connectorPresetSelect.value;

                if (!presetKey) {
                    return;
                }

                applyConnectorPreset(presetKey, config);
            });
        }

        applyCurrentDriver(true);
        initTestTopUpPanel(config);
    }

    document.addEventListener('DOMContentLoaded', initSupplierForm);
})();
