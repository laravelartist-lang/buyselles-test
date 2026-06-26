(function (window) {
    'use strict';

    var STORAGE_KEY = 'buyselles_bluetooth_printer';
    var SETUP_MODAL_ID = 'bluetoothThermalSetupModal';

    var PRINTER_PROFILES = [
        {
            name: 'generic-ff00',
            serviceUuid: '0000ff00-0000-1000-8000-00805f9b34fb',
            writeUuid: '0000ff02-0000-1000-8000-00805f9b34fb',
        },
        {
            name: 'microchip-rn4678',
            serviceUuid: '49535343-fe7d-4ae5-8fa9-9faafdfd2080',
            writeUuid: '49535343-8841-43f4-a8d4-ecbe34729bf3',
        },
        {
            name: 'nordic-uart',
            serviceUuid: '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
            writeUuid: '6e400002-b5a3-f393-e0a9-e50e24dcca9e',
        },
        {
            name: 'printer-18f0',
            serviceUuid: '000018f0-0000-1000-8000-00805f9b34fb',
            writeUuid: '00002af1-0000-1000-8000-00805f9b34fb',
        },
    ];

    var activeConnection = null;

    function getConfig() {
        return window.BuysellesThermalConfig || {};
    }

    function getMessages() {
        return getConfig().messages || {};
    }

    function showToast(message, type) {
        if (typeof toastr !== 'undefined') {
            if (type === 'error') {
                toastr.error(message);
            } else if (type === 'success') {
                toastr.success(message);
            } else {
                toastr.info(message);
            }

            return;
        }

        alert(message);
    }

    function isTestMode() {
        return !!getConfig().bluetoothTestMode;
    }

    function isEnabled() {
        return getConfig().bluetoothEnabled !== false;
    }

    function isSupported() {
        return typeof navigator !== 'undefined'
            && typeof navigator.bluetooth !== 'undefined'
            && typeof navigator.bluetooth.requestDevice === 'function';
    }

    function isAvailable() {
        return isEnabled() && (isSupported() || isTestMode());
    }

    function getTestPrinterName() {
        return getConfig().testPrinterName || 'DEMO - Bluetooth Thermal Printer';
    }

    function isSavedTestPrinter(saved) {
        return !!(saved && (saved.testMode || saved.deviceId === 'test-demo-bluetooth-printer'));
    }

    function getOptionalServiceUuids() {
        var uuids = [];

        PRINTER_PROFILES.forEach(function (profile) {
            uuids.push(profile.serviceUuid);
        });

        return uuids;
    }

    function getSavedPrinter() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);

            if (!raw) {
                return null;
            }

            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function savePrinter(profile) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
        } catch (error) {
            // Ignore storage failures.
        }
    }

    function clearSavedPrinter() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            // Ignore storage failures.
        }

        activeConnection = null;
    }

    function hexToBytes(hex) {
        var normalized = String(hex || '').replace(/\s+/g, '');

        if (!normalized || normalized.length % 2 !== 0) {
            return new Uint8Array(0);
        }

        var bytes = new Uint8Array(normalized.length / 2);

        for (var i = 0; i < normalized.length; i += 2) {
            bytes[i / 2] = parseInt(normalized.substr(i, 2), 16);
        }

        return bytes;
    }

    function normalizeUuid(uuid) {
        return String(uuid || '').toLowerCase();
    }

    function findProfile(serviceUuid, writeUuid) {
        var service = normalizeUuid(serviceUuid);
        var write = normalizeUuid(writeUuid);

        for (var i = 0; i < PRINTER_PROFILES.length; i++) {
            var profile = PRINTER_PROFILES[i];

            if (normalizeUuid(profile.serviceUuid) === service && normalizeUuid(profile.writeUuid) === write) {
                return profile;
            }
        }

        return {
            name: 'custom',
            serviceUuid: serviceUuid,
            writeUuid: writeUuid,
        };
    }

    function resolveWritableCharacteristic(server, savedProfile) {
        if (savedProfile && savedProfile.serviceUuid && savedProfile.writeUuid) {
            return server.getPrimaryService(savedProfile.serviceUuid)
                .then(function (service) {
                    return service.getCharacteristic(savedProfile.writeUuid);
                })
                .then(function (characteristic) {
                    return {
                        profile: findProfile(savedProfile.serviceUuid, savedProfile.writeUuid),
                        characteristic: characteristic,
                    };
                });
        }

        return server.getPrimaryServices().then(function (services) {
            var attempts = [];

            services.forEach(function (service) {
                attempts.push(
                    service.getCharacteristics().then(function (characteristics) {
                        for (var i = 0; i < characteristics.length; i++) {
                            var characteristic = characteristics[i];
                            var props = characteristic.properties || {};

                            if (props.write || props.writeWithoutResponse) {
                                return {
                                    profile: findProfile(service.uuid, characteristic.uuid),
                                    characteristic: characteristic,
                                };
                            }
                        }

                        return null;
                    })
                );
            });

            return Promise.all(attempts).then(function (results) {
                for (var j = 0; j < results.length; j++) {
                    if (results[j]) {
                        return results[j];
                    }
                }

                throw new Error(getMessages().bluetoothNoWritableChar || 'No writable Bluetooth characteristic found on this printer.');
            });
        });
    }

    function rememberConnection(device, resolved) {
        activeConnection = {
            device: device,
            server: resolved.server,
            characteristic: resolved.characteristic,
            profile: resolved.profile,
        };

        savePrinter({
            deviceId: device.id,
            deviceName: device.name || getMessages().bluetoothUnknownDevice || 'Bluetooth printer',
            profileName: resolved.profile.name,
            serviceUuid: resolved.profile.serviceUuid,
            writeUuid: resolved.profile.writeUuid,
        });
    }

    function connectDevice(device, savedProfile) {
        if (!device || !device.gatt) {
            return Promise.reject(new Error(getMessages().bluetoothUnsupported || 'Bluetooth printing is not supported in this browser.'));
        }

        if (activeConnection
            && activeConnection.device
            && activeConnection.device.id === device.id
            && activeConnection.characteristic
            && device.gatt.connected) {
            return Promise.resolve(activeConnection);
        }

        return device.gatt.connect().then(function (server) {
            return resolveWritableCharacteristic(server, savedProfile).then(function (resolved) {
                resolved.server = server;
                rememberConnection(device, resolved);

                return activeConnection;
            });
        });
    }

    function pairTestPrinter() {
        var profile = {
            deviceId: 'test-demo-bluetooth-printer',
            deviceName: getTestPrinterName(),
            profileName: 'test',
            serviceUuid: PRINTER_PROFILES[0].serviceUuid,
            writeUuid: PRINTER_PROFILES[0].writeUuid,
            testMode: true,
        };

        savePrinter(profile);

        activeConnection = {
            device: { id: profile.deviceId, name: profile.deviceName },
            profile: profile,
            testMode: true,
        };

        updateDeliveryBadges();

        return Promise.resolve(activeConnection);
    }

    function getMockConnection(saved) {
        saved = saved || getSavedPrinter();

        return {
            device: { id: saved.deviceId, name: saved.deviceName },
            profile: saved,
            testMode: true,
        };
    }

    function bytesToPreviewText(bytes) {
        var text = '';

        for (var i = 0; i < bytes.length; i++) {
            var code = bytes[i];

            if (code === 0x0a || code === 0x0d) {
                text += '\n';
            } else if (code >= 0x20 && code <= 0x7e) {
                text += String.fromCharCode(code);
            }
        }

        return text.replace(/\n{3,}/g, '\n\n').trim();
    }

    function validateHexJobs(hexJobs) {
        var checks = [];
        var previews = [];

        if (!hexJobs || !hexJobs.length) {
            throw new Error(getMessages().bluetoothTestNoJobs || 'No print jobs were returned from the server.');
        }

        hexJobs.forEach(function (hex, index) {
            var normalized = String(hex || '').toLowerCase();
            var bytes = hexToBytes(normalized);
            var receiptNo = index + 1;

            if (!normalized) {
                throw new Error((getMessages().bluetoothTestEmptyReceipt || 'Receipt :n is empty.').replace(':n', String(receiptNo)));
            }

            if (normalized.indexOf('1b40') !== 0) {
                checks.push({
                    ok: false,
                    label: (getMessages().bluetoothTestCheckInit || 'Receipt :n starts with printer init (ESC @)').replace(':n', String(receiptNo)),
                });
            } else {
                checks.push({
                    ok: true,
                    label: (getMessages().bluetoothTestCheckInit || 'Receipt :n starts with printer init (ESC @)').replace(':n', String(receiptNo)),
                });
            }

            if (normalized.slice(-8) !== '1d564200') {
                throw new Error((getMessages().bluetoothTestMissingCut || 'Receipt :n is missing the paper cut command.').replace(':n', String(receiptNo)));
            }

            checks.push({
                ok: true,
                label: (getMessages().bluetoothTestCheckCut || 'Receipt :n ends with paper cut command').replace(':n', String(receiptNo)),
            });

            checks.push({
                ok: bytes.length > 40,
                label: (getMessages().bluetoothTestCheckSize || 'Receipt :n has :bytes bytes of print data')
                    .replace(':n', String(receiptNo))
                    .replace(':bytes', String(bytes.length)),
            });

            previews.push(bytesToPreviewText(bytes));
        });

        return {
            jobCount: hexJobs.length,
            totalBytes: hexJobs.reduce(function (sum, hex) {
                return sum + hexToBytes(hex).length;
            }, 0),
            checks: checks,
            previews: previews,
        };
    }

    function showTestResultModal(report) {
        var modalEl = document.getElementById('bluetoothThermalTestResultModal');
        var summaryEl = document.getElementById('bluetoothTestResultSummary');
        var checksEl = document.getElementById('bluetoothTestResultChecks');
        var previewEl = document.getElementById('bluetoothTestResultPreview');
        var messages = getMessages();

        if (!modalEl || !checksEl || !previewEl) {
            return;
        }

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        if (summaryEl) {
            summaryEl.textContent = (messages.bluetoothTestPassedSummary || 'Validated :count receipt(s), :bytes bytes total — same data a real Bluetooth printer would receive.')
                .replace(':count', String(report.jobCount))
                .replace(':bytes', String(report.totalBytes));
        }

        checksEl.innerHTML = '';

        report.checks.forEach(function (check) {
            var item = document.createElement('li');
            item.className = 'mb-2 ' + (check.ok ? 'text-success' : 'text-danger');
            item.innerHTML = '<i class="fa fa-' + (check.ok ? 'check' : 'times') + '-circle mr-1"></i>' + check.label;
            checksEl.appendChild(item);
        });

        previewEl.textContent = report.previews.map(function (preview, index) {
            return '--- Receipt ' + (index + 1) + ' ---\n' + preview;
        }).join('\n\n');

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var Modal = bootstrap.Modal;

            if (typeof Modal.getOrCreateInstance === 'function') {
                Modal.getOrCreateInstance(modalEl).show();
            } else {
                new Modal(modalEl).show();
            }

            return;
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery(modalEl).modal('show');
        }
    }

    function downloadTestEscPos(hexJobs) {
        var parts = [];
        (hexJobs || []).forEach(function (hexJob) {
            parts.push(hexToBytes(hexJob));
        });

        var totalLength = parts.reduce(function (sum, part) {
            return sum + part.length;
        }, 0);
        var combined = new Uint8Array(totalLength);
        var offset = 0;

        parts.forEach(function (part) {
            combined.set(part, offset);
            offset += part.length;
        });

        var blob = new Blob([combined], { type: 'application/octet-stream' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'thermal-bluetooth-test-' + Date.now() + '.raw';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        if (getConfig().debug) {
            console.info('[BuysellesBluetoothThermal] Test ESC/POS bytes:', combined);
        }
    }

    function printHexJobsTest(hexJobs) {
        var saved = getSavedPrinter();
        var report = validateHexJobs(hexJobs);

        downloadTestEscPos(hexJobs);
        showTestResultModal(report);

        if (typeof console !== 'undefined' && console.info) {
            console.info('[BuysellesBluetoothThermal] TEST PASSED', report);
        }

        return Promise.resolve({
            jobCount: report.jobCount,
            totalBytes: report.totalBytes,
            deviceName: (saved && saved.deviceName) || getTestPrinterName(),
            testMode: true,
            validated: true,
        });
    }

    function requestDevice() {
        return navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: getOptionalServiceUuids(),
        });
    }

    function reconnectSavedDevice() {
        var saved = getSavedPrinter();

        if (!saved || !saved.deviceId) {
            return Promise.reject(new Error(getMessages().bluetoothNotConfigured || 'No Bluetooth printer saved on this browser.'));
        }

        if (isSavedTestPrinter(saved)) {
            activeConnection = getMockConnection(saved);
            return Promise.resolve(activeConnection);
        }

        if (typeof navigator.bluetooth.getDevices === 'function') {
            return navigator.bluetooth.getDevices().then(function (devices) {
                for (var i = 0; i < devices.length; i++) {
                    if (devices[i].id === saved.deviceId) {
                        return connectDevice(devices[i], saved);
                    }
                }

                throw new Error(getMessages().bluetoothPermissionRequired || 'Select your Bluetooth printer again to grant access.');
            });
        }

        return requestDevice().then(function (device) {
            return connectDevice(device, saved);
        });
    }

    function pairPrinter() {
        if (isTestMode() && !isSupported()) {
            return pairTestPrinter();
        }

        return requestDevice().then(function (device) {
            return connectDevice(device, null);
        });
    }

    function getEscPosUrl() {
        var config = getConfig();

        return config.escPosUrl || (window.BuysellesQzTrayConfig && window.BuysellesQzTrayConfig.escPosUrl) || '';
    }

    function fetchEscPosJobs(orderIds) {
        var escPosUrl = getEscPosUrl();
        var params = (orderIds || []).map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        }).join('&');

        if (!escPosUrl) {
            return Promise.reject(new Error(getMessages().loadPrintDataFailed || 'Unable to load print data.'));
        }

        return fetch(escPosUrl + '?' + params, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(getMessages().loadPrintDataFailed || 'Unable to load print data.');
            }

            return response.json();
        });
    }

    function writeBytes(characteristic, bytes) {
        var chunkSize = 512;
        var offset = 0;
        var props = characteristic.properties || {};
        var writeFn = props.writeWithoutResponse && characteristic.writeValueWithoutResponse
            ? characteristic.writeValueWithoutResponse.bind(characteristic)
            : characteristic.writeValue.bind(characteristic);

        function writeNextChunk() {
            if (offset >= bytes.length) {
                return Promise.resolve();
            }

            var chunk = bytes.subarray(offset, offset + chunkSize);
            offset += chunk.length;

            return writeFn(chunk).then(writeNextChunk);
        }

        return writeNextChunk();
    }

    function printHexJobs(hexJobs) {
        var saved = getSavedPrinter();

        if (isSavedTestPrinter(saved) || (isTestMode() && saved && saved.testMode)) {
            return printHexJobsTest(hexJobs);
        }

        return reconnectSavedDevice().then(function (connection) {
            var jobs = hexJobs || [];

            return jobs.reduce(function (chain, hexJob) {
                return chain.then(function () {
                    return writeBytes(connection.characteristic, hexToBytes(hexJob));
                });
            }, Promise.resolve()).then(function () {
                return {
                    jobCount: jobs.length,
                    deviceName: connection.device.name || (getSavedPrinter() && getSavedPrinter().deviceName) || '',
                };
            });
        });
    }

    function printOrderIds(orderIds) {
        return fetchEscPosJobs(orderIds).then(function (payload) {
            return printHexJobs(payload.jobs || []);
        }).then(function (result) {
            var messages = getMessages();
            var template = result.testMode
                ? (messages.bluetoothTestPrintSuccess || 'Test passed: :count receipt(s), :bytes bytes — preview opened. .raw file downloaded.')
                : (messages.bluetoothPrintSuccess || 'Sent to Bluetooth thermal printer (:device).');

            showToast(
                template
                    .replace(':count', String(result.jobCount || 0))
                    .replace(':bytes', String(result.totalBytes || 0))
                    .replace(':device', result.deviceName || messages.bluetoothUnknownDevice || 'Bluetooth printer'),
                'success'
            );

            return result;
        });
    }

    function tryAutoPrint(orderIds) {
        if (!getSavedPrinter()) {
            return Promise.reject(new Error(getMessages().bluetoothNotConfigured || 'No Bluetooth printer saved on this browser.'));
        }

        return printOrderIds(orderIds);
    }

    function updateDeliveryBadges() {
        var messages = getMessages();
        var saved = getSavedPrinter();
        var label = messages.bluetoothStatusSetup || 'Not configured';
        var pillClass = 'digital-code-bluetooth-status-pill--setup';

        if (saved) {
            if (isSavedTestPrinter(saved)) {
                label = messages.bluetoothStatusDemo || 'Demo printer';
                pillClass = 'digital-code-bluetooth-status-pill--demo';
            } else {
                label = messages.bluetoothStatusReady || 'Ready';
                pillClass = 'digital-code-bluetooth-status-pill--ready';
            }
        } else if (!isSupported() && !isTestMode()) {
            label = messages.bluetoothStatusUnavailable || 'Use Chrome/HTTPS';
            pillClass = 'digital-code-bluetooth-status-pill--warn';
        }

        document.querySelectorAll('.digital-code-bluetooth-status').forEach(function (pill) {
            pill.classList.remove(
                'digital-code-bluetooth-status-pill--ready',
                'digital-code-bluetooth-status-pill--demo',
                'digital-code-bluetooth-status-pill--setup',
                'digital-code-bluetooth-status-pill--warn'
            );
            pill.classList.add(pillClass);
            pill.title = label;

            var textEl = pill.querySelector('.digital-code-bluetooth-status__text');

            if (textEl) {
                textEl.textContent = label;
            } else if (!pill.classList.contains('digital-code-bluetooth-status-pill--compact')) {
                pill.innerHTML = '<i class="fa fa-bluetooth-b"></i><span class="digital-code-bluetooth-status__text">' + label + '</span>';
            }
        });

        document.querySelectorAll('.digital-code-action-bluetooth-quick-print').forEach(function (btn) {
            btn.classList.toggle('d-none', !saved);
            btn.disabled = false;
        });
    }

    function updateSetupUi() {
        var savedEl = document.getElementById('bluetoothThermalSavedDevice');
        var clearBtn = document.getElementById('bluetoothThermalClearBtn');
        var connectBtn = document.getElementById('bluetoothThermalConnectBtn');
        var testConnectBtn = document.getElementById('bluetoothThermalTestConnectBtn');
        var testBanner = document.getElementById('bluetoothThermalTestBanner');
        var unsupportedNotice = document.getElementById('bluetoothThermalUnsupportedNotice');
        var statusEl = document.getElementById('bluetoothThermalStatus');
        var saved = getSavedPrinter();
        var messages = getMessages();

        if (testBanner) {
            testBanner.classList.toggle('d-none', !isTestMode());
        }

        if (unsupportedNotice) {
            unsupportedNotice.classList.toggle('d-none', isSupported() || isTestMode());
        }

        if (testConnectBtn) {
            var showTest = isTestMode();
            testConnectBtn.classList.toggle('d-none', !showTest);

            if (showTest && !isSupported()) {
                testConnectBtn.classList.add('btn-primary');
                testConnectBtn.classList.remove('btn-outline-info');
            } else if (showTest && isSupported()) {
                testConnectBtn.classList.add('btn-primary');
                testConnectBtn.classList.remove('btn-outline-info');
            }
        }

        if (connectBtn) {
            var showReal = isSupported();
            connectBtn.classList.toggle('d-none', !showReal);

            if (showReal && isTestMode()) {
                connectBtn.classList.remove('btn-primary');
                connectBtn.classList.add('btn-outline-primary');
            } else if (showReal) {
                connectBtn.classList.add('btn-primary');
                connectBtn.classList.remove('btn-outline-primary');
            }
        }

        if (clearBtn) {
            clearBtn.classList.toggle('d-none', !saved);
        }

        if (savedEl) {
            savedEl.textContent = saved
                ? saved.deviceName
                : (messages.bluetoothNoSavedDevice || 'None');
        }

        if (statusEl && !statusEl.dataset.busy) {
            if (saved) {
                statusEl.textContent = isSavedTestPrinter(saved)
                    ? (messages.bluetoothTestPaired || 'Demo Bluetooth printer paired for testing.')
                    : (messages.bluetoothSavedReady || 'Bluetooth printer ready on this browser.');
            } else if (isTestMode()) {
                statusEl.textContent = messages.bluetoothTestModeBanner || 'Test mode is on — click "Use demo Bluetooth printer" to simulate pairing.';
            } else if (!isSupported()) {
                statusEl.textContent = messages.bluetoothUnsupported || 'Bluetooth printing is not supported in this browser. Use Chrome or Edge on HTTPS.';
            } else {
                statusEl.textContent = messages.bluetoothSetupHint || 'Turn on your printer, enable Bluetooth pairing mode, then click Connect.';
            }
        }

        updateDeliveryBadges();
    }

    function setSetupStatus(message, type) {
        var statusEl = document.getElementById('bluetoothThermalStatus');

        if (!statusEl) {
            return;
        }

        statusEl.dataset.busy = type === 'busy' ? '1' : '';
        statusEl.classList.remove('text-success', 'text-danger', 'text-muted');

        if (type === 'success') {
            statusEl.classList.add('text-success');
        } else if (type === 'error') {
            statusEl.classList.add('text-danger');
        } else {
            statusEl.classList.add('text-muted');
        }

        statusEl.textContent = message;
    }

    function ensureSetupModalInBody() {
        var el = document.getElementById(SETUP_MODAL_ID);

        if (el && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }

        return el;
    }

    function showSetupModal() {
        var el = ensureSetupModalInBody();

        if (!el) {
            return Promise.resolve();
        }

        updateSetupUi();

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var Modal = bootstrap.Modal;

            if (typeof Modal.getOrCreateInstance === 'function') {
                Modal.getOrCreateInstance(el).show();
            } else {
                new Modal(el).show();
            }

            return Promise.resolve();
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery(el).modal('show');
        }

        return Promise.resolve();
    }

    function bindSetupEvents() {
        var connectBtn = document.getElementById('bluetoothThermalConnectBtn');
        var clearBtn = document.getElementById('bluetoothThermalClearBtn');
        var qzBtn = document.getElementById('bluetoothThermalQzSetupBtn');

        if (connectBtn && connectBtn.dataset.bound !== '1') {
            connectBtn.dataset.bound = '1';
            connectBtn.addEventListener('click', function () {
                var messages = getMessages();

                connectBtn.disabled = true;
                setSetupStatus(messages.bluetoothConnecting || 'Connecting to Bluetooth printer...', 'busy');

                pairPrinter()
                    .then(function (connection) {
                        setSetupStatus(
                            (messages.bluetoothConnected || 'Connected: :device').replace(
                                ':device',
                                connection.device.name || (getSavedPrinter() && getSavedPrinter().deviceName) || messages.bluetoothUnknownDevice || 'Bluetooth printer'
                            ),
                            'success'
                        );
                        updateSetupUi();
                        showToast(messages.bluetoothSetupComplete || 'Bluetooth thermal printer saved for this browser.', 'success');
                    })
                    .catch(function (error) {
                        setSetupStatus(error.message || messages.bluetoothConnectFailed || 'Could not connect to Bluetooth printer.', 'error');
                    })
                    .finally(function () {
                        connectBtn.disabled = false;
                    });
            });
        }

        var testConnectBtn = document.getElementById('bluetoothThermalTestConnectBtn');

        if (testConnectBtn && testConnectBtn.dataset.bound !== '1') {
            testConnectBtn.dataset.bound = '1';
            testConnectBtn.addEventListener('click', function () {
                var messages = getMessages();

                testConnectBtn.disabled = true;
                setSetupStatus(messages.bluetoothConnecting || 'Connecting to Bluetooth printer...', 'busy');

                pairTestPrinter()
                    .then(function (connection) {
                        setSetupStatus(
                            (messages.bluetoothConnected || 'Connected: :device').replace(':device', connection.device.name),
                            'success'
                        );
                        updateSetupUi();
                        showToast(messages.bluetoothTestPaired || 'Demo Bluetooth printer paired for testing.', 'success');
                    })
                    .finally(function () {
                        testConnectBtn.disabled = false;
                    });
            });
        }

        if (clearBtn && clearBtn.dataset.bound !== '1') {
            clearBtn.dataset.bound = '1';
            clearBtn.addEventListener('click', function () {
                clearSavedPrinter();
                updateSetupUi();
                setSetupStatus(getMessages().bluetoothCleared || 'Saved Bluetooth printer removed.', 'success');
            });
        }

        if (qzBtn && qzBtn.dataset.bound !== '1') {
            qzBtn.dataset.bound = '1';
            qzBtn.addEventListener('click', function () {
                if (window.BuysellesQzTray && typeof window.BuysellesQzTray.openSetupWizard === 'function') {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var el = document.getElementById(SETUP_MODAL_ID);
                        var instance = bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(el) : null;

                        if (instance) {
                            instance.hide();
                        }
                    }

                    window.BuysellesQzTray.openSetupWizard([], null);
                }
            });
        }
    }

    function openSetupModal() {
        bindSetupEvents();
        updateSetupUi();

        return showSetupModal();
    }

    function initBluetoothThermalUi() {
        bindSetupEvents();
        updateSetupUi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBluetoothThermalUi);
    } else {
        initBluetoothThermalUi();
    }

    window.BuysellesBluetoothThermal = {
        isSupported: isSupported,
        isAvailable: isAvailable,
        isTestMode: isTestMode,
        getSavedPrinter: getSavedPrinter,
        clearSavedPrinter: clearSavedPrinter,
        pairPrinter: pairPrinter,
        pairTestPrinter: pairTestPrinter,
        printOrderIds: printOrderIds,
        tryAutoPrint: tryAutoPrint,
        openSetupModal: openSetupModal,
        updateDeliveryBadges: updateDeliveryBadges,
    };
}(window));
