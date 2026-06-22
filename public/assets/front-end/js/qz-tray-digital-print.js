(function (window) {
    'use strict';

    var STORAGE_KEY = 'buyselles_qz_printer';
    var QZ_MODAL_IDS = ['qzTrayWizardModal', 'qzTrayThermalChoiceModal'];
    var securityInitialized = false;
    var connectPromise = null;
    var connectionCallbacksRegistered = false;
    var wizardContext = null;

    function getConfig() {
        return window.BuysellesQzTrayConfig || {};
    }

    function showToast(message, type) {
        if (typeof toastr !== 'undefined') {
            if (type === 'error' && typeof toastr.error === 'function') {
                toastr.error(message);
                return;
            }

            if (type === 'success' && typeof toastr.success === 'function') {
                toastr.success(message);
                return;
            }

            toastr.info(message);
            return;
        }

        alert(message);
    }

    function withTimeout(promise, ms) {
        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () {
                reject(new Error(getConfig().messages?.connectionTimeout || 'Connection timed out.'));
            }, ms);

            promise.then(function (value) {
                clearTimeout(timer);
                resolve(value);
            }).catch(function (error) {
                clearTimeout(timer);
                reject(error);
            });
        });
    }

    function fetchText(url, options) {
        options = options || {};

        return fetch(url, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: Object.assign({
                Accept: 'text/plain',
                'X-Requested-With': 'XMLHttpRequest',
            }, options.headers || {}),
            body: options.body,
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed: ' + response.status);
            }

            return response.text();
        });
    }

    function signRequest(toSign) {
        var config = getConfig();

        return fetchText(config.signUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'request=' + encodeURIComponent(toSign),
        });
    }

    function ensureQzLoaded() {
        if (typeof window.qz === 'undefined') {
            return Promise.reject(new Error(getConfig().messages?.libraryMissing || 'QZ Tray library not loaded.'));
        }

        return Promise.resolve(window.qz);
    }

    function formatConnectionError(error) {
        var config = getConfig();
        var message = (error && error.message) ? String(error.message) : '';
        var lower = message.toLowerCase();

        if (lower.indexOf('timed out') >= 0 || lower.indexOf('timeout') >= 0) {
            return config.messages?.trustTimeout
                || 'Connection timed out. Click Connect again, approve the QZ Tray popup with Allow and Remember (check behind this window).';
        }

        if (lower.indexOf('failed to sign') >= 0 || lower.indexOf('signing failed') >= 0) {
            return config.messages?.signFailed
                || 'QZ Tray signing failed on the server. Check /qz-tray/sign returns 200, then try again.';
        }

        if (lower.indexOf('unable to establish connection') >= 0) {
            var unreachableMessage = config.messages?.unreachable
                || 'Browser cannot reach QZ Tray. Confirm the QZ Tray icon is in your system tray, then click Connect again.';
            var blockedHint = config.messages?.trustBlockedRetry || '';

            return blockedHint ? unreachableMessage + ' ' + blockedHint : unreachableMessage;
        }

        if (lower.indexOf('already exists') >= 0) {
            return config.messages?.alreadyConnected
                || 'Already connected to QZ Tray. Continue to select your printer.';
        }

        if (lower.indexOf('denied') >= 0 || lower.indexOf('blocked') >= 0 || lower.indexOf('untrusted') >= 0) {
            return config.messages?.trustDenied
                || 'QZ Tray blocked this website. Open QZ Tray, reset allowed sites if needed, then click Connect and choose Allow / Remember.';
        }

        if (lower.indexOf('refused') >= 0 || lower.indexOf('canceled') >= 0 || lower.indexOf('cancelled') >= 0 || lower.indexOf('rejected') >= 0) {
            return config.messages?.trustBlockedRetry
                || config.messages?.trustDenied
                || 'QZ Tray access was not allowed. Click Connect again and choose Allow in the popup.';
        }

        if (lower.indexOf('connect failed') >= 0 || lower.indexOf('could not connect') >= 0) {
            return config.messages?.connectFailed || 'Could not connect to QZ Tray.';
        }

        if (message) {
            return message;
        }

        return config.messages?.connectFailed || 'Could not connect to QZ Tray.';
    }

    function getConnectOptions(usingSecure) {
        if (typeof usingSecure === 'undefined') {
            usingSecure = window.location.protocol === 'https:';
        }

        return {
            host: ['localhost', '127.0.0.1'],
            port: {
                secure: [8181],
                insecure: [8182],
            },
            usingSecure: usingSecure,
            retries: 1,
            delay: 0.5,
            keepAlive: 60,
        };
    }

    function isConnectionReady(qz) {
        if (!qz || !qz.websocket || typeof qz.websocket.isActive !== 'function') {
            return false;
        }

        return qz.websocket.isActive();
    }

    function isExistingConnectionError(error) {
        var message = error && error.message ? String(error.message) : '';

        return message.toLowerCase().indexOf('already exists') >= 0;
    }

    function reuseExistingConnection(qz, error) {
        if (isExistingConnectionError(error) && qz.websocket.isActive && qz.websocket.isActive()) {
            return qz;
        }

        throw error;
    }

    function disconnectQz(qz) {
        if (!qz || !qz.websocket || typeof qz.websocket.disconnect !== 'function') {
            return Promise.resolve();
        }

        return qz.websocket.disconnect().catch(function () {});
    }

    function resetConnectState(qz) {
        connectPromise = null;

        return disconnectQz(qz || (typeof window.qz !== 'undefined' ? window.qz : null));
    }

    function connectToQz(qz) {
        var preferSecure = window.location.protocol === 'https:';
        var primaryOptions = getConnectOptions(preferSecure);
        var fallbackOptions = getConnectOptions(!preferSecure);

        function attemptConnect(options) {
            return qz.websocket.connect(options).then(function () {
                return qz;
            });
        }

        return attemptConnect(primaryOptions)
            .catch(function (primaryError) {
                try {
                    return reuseExistingConnection(qz, primaryError);
                } catch (ignored) {
                    // Try alternate socket port.
                }

                if (isConnectionReady(qz)) {
                    return qz;
                }

                if (typeof console !== 'undefined' && getConfig().debug) {
                    console.warn('[BuysellesQzTray] Primary connect failed, retrying alternate socket', primaryError);
                }

                return disconnectQz(qz).then(function () {
                    return attemptConnect(fallbackOptions);
                }).catch(function (fallbackError) {
                    try {
                        return reuseExistingConnection(qz, fallbackError);
                    } catch (ignoredRetry) {
                        if (typeof console !== 'undefined' && getConfig().debug) {
                            console.error('[BuysellesQzTray] Connect failed', fallbackError);
                        }

                        throw fallbackError;
                    }
                });
            });
    }

    function appendTestPrinter(printers) {
        var config = getConfig();
        var list = printers ? printers.slice() : [];

        if (config.testMode && config.testPrinterName && list.indexOf(config.testPrinterName) < 0) {
            list.unshift(config.testPrinterName);
        }

        return list;
    }

    function createPrintConfig(qz, printerName) {
        return qz.configs.create(printerName);
    }

    function isTestPrinter(printerName) {
        var config = getConfig();

        return !!(config.testMode && printerName === config.testPrinterName);
    }

    function getTestOutputPath() {
        var config = getConfig();
        var path = (config.testOutputFile || 'buyselles-thermal-test.raw').trim();

        if (path.indexOf('/') >= 0 || path.indexOf('\\') >= 0) {
            var parts = path.split(/[\\/]/);

            return parts[parts.length - 1] || 'buyselles-thermal-test.raw';
        }

        return path;
    }

    function validateHexJobs(hexJobs) {
        if (!hexJobs || !hexJobs.length) {
            throw new Error('No print jobs were returned from the server.');
        }

        hexJobs.forEach(function (hex, index) {
            var normalized = (hex || '').toLowerCase();

            if (!normalized) {
                throw new Error('Receipt ' + (index + 1) + ' is empty.');
            }

            if (normalized.slice(-8) !== '1d564200') {
                throw new Error('Receipt ' + (index + 1) + ' is missing the paper cut command.');
            }
        });
    }

    function printTestJobs(qz, hexJobs) {
        validateHexJobs(hexJobs);

        var outputPath = getTestOutputPath();
        var config = getConfig();

        if (!qz.file || typeof qz.file.write !== 'function') {
            if (config.debug && typeof console !== 'undefined') {
                console.info('[BuysellesQzTray] Test validation passed for ' + hexJobs.length + ' receipt(s).', hexJobs);
            }

            return Promise.resolve({
                testMode: true,
                dryRun: true,
                outputFile: outputPath,
                jobCount: hexJobs.length,
            });
        }

        return hexJobs.reduce(function (chain, hexData, index) {
            return chain.then(function () {
                return qz.file.write(outputPath, {
                    data: hexData,
                    flavor: 'hex',
                    append: index > 0,
                    sandbox: true,
                });
            });
        }, Promise.resolve()).then(function () {
            return {
                testMode: true,
                dryRun: false,
                outputFile: '~/.qz/sandbox/' + outputPath,
                jobCount: hexJobs.length,
            };
        }).catch(function (fileError) {
            if (config.debug && typeof console !== 'undefined') {
                console.warn('[BuysellesQzTray] Sandbox file write failed, falling back to validation only.', fileError);
                console.info('[BuysellesQzTray] Validated jobs:', hexJobs);
            }

            return {
                testMode: true,
                dryRun: true,
                outputFile: outputPath,
                jobCount: hexJobs.length,
            };
        });
    }

    function initSecurity() {
        if (securityInitialized) {
            return ensureQzLoaded();
        }

        var config = getConfig();

        return ensureQzLoaded().then(function (qz) {
            if (config.debug && typeof qz.api !== 'undefined' && typeof qz.api.showDebug === 'function') {
                qz.api.showDebug(true);
            }

            if (typeof qz.websocket.setUsingSurf === 'function') {
                qz.websocket.setUsingSurf(false);
            }

            qz.security.setSignatureAlgorithm('SHA512');

            qz.security.setCertificatePromise(function (resolve, reject) {
                fetchText(config.certificateUrl)
                    .then(function (certificate) {
                        resolve((certificate || '').trim());
                    })
                    .catch(reject);
            });

            qz.security.setSignaturePromise(async function (toSign) {
                var signature = await signRequest(toSign);

                return (signature || '').trim();
            });

            registerConnectionCallbacks(qz);
            securityInitialized = true;

            return qz;
        });
    }

    function registerConnectionCallbacks(qz) {
        if (connectionCallbacksRegistered || !qz || !qz.websocket) {
            return;
        }

        connectionCallbacksRegistered = true;

        if (typeof qz.websocket.setClosedCallbacks === 'function') {
            qz.websocket.setClosedCallbacks(function () {
                connectPromise = null;
            });
        }

        if (typeof qz.websocket.setErrorCallbacks === 'function') {
            qz.websocket.setErrorCallbacks(function () {
                connectPromise = null;
            });
        }
    }

    function getHostnameMismatchMessage() {
        var config = getConfig();
        var expectedHost = (config.certificateHost || '').toLowerCase();
        var actualHost = (window.location.hostname || '').toLowerCase();

        if (!expectedHost || !actualHost || expectedHost === actualHost) {
            return '';
        }

        var template = config.messages?.hostnameMismatch
            || 'Open this site at :host so QZ Tray can trust the connection.';

        return template.replace(':host', expectedHost);
    }

    function connectOnce(options) {
        options = options || {};
        var hostnameWarning = getHostnameMismatchMessage();

        if (hostnameWarning) {
            return Promise.reject(new Error(hostnameWarning));
        }

        return initSecurity()
            .then(function (qz) {
                var start = options.forceReconnect
                    ? resetConnectState(qz)
                    : Promise.resolve();

                return start.then(function () {
                    if (!options.forceReconnect && isConnectionReady(qz)) {
                        return qz;
                    }

                    return connectToQz(qz);
                });
            });
    }

    function connect(options) {
        options = options || {};
        var config = getConfig();
        var timeoutMs = options.timeoutMs || config.connectTimeoutMs || 30000;

        if (!options.forceReconnect && typeof window.qz !== 'undefined' && isConnectionReady(window.qz)) {
            return Promise.resolve(window.qz);
        }

        if (options.forceReconnect) {
            connectPromise = null;
        }

        if (!connectPromise) {
            connectPromise = connectOnce(options).catch(function (error) {
                connectPromise = null;
                throw error;
            });
        }

        return withTimeout(connectPromise, timeoutMs).catch(function (error) {
            connectPromise = null;
            throw error;
        });
    }

    function isConnected() {
        return typeof window.qz !== 'undefined' && isConnectionReady(window.qz);
    }

    function getSavedPrinter() {
        try {
            return localStorage.getItem(STORAGE_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function savePrinter(name) {
        try {
            localStorage.setItem(STORAGE_KEY, name);
        } catch (e) {
            // Ignore storage failures.
        }
    }

    function clearSavedPrinter() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // Ignore storage failures.
        }
    }

    function listPrinters(options) {
        return connect(options).then(function (qz) {
            return qz.printers.find().then(function (printers) {
                return appendTestPrinter(printers || []);
            });
        });
    }

    function fetchEscPosJobs(orderIds) {
        var config = getConfig();
        var params = (orderIds || []).map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        }).join('&');

        return fetch(config.escPosUrl + '?' + params, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(getConfig().messages?.loadPrintDataFailed || 'Unable to load print data.');
            }

            return response.json();
        });
    }

    function printHexJobs(printerName, hexJobs, options) {
        return connect(options).then(function (qz) {
            if (isTestPrinter(printerName)) {
                return printTestJobs(qz, hexJobs);
            }

            var config = createPrintConfig(qz, printerName);

            return hexJobs.reduce(function (chain, hexData) {
                return chain.then(function () {
                    return qz.print(config, [{
                        type: 'raw',
                        format: 'command',
                        flavor: 'hex',
                        data: hexData,
                    }]);
                });
            }, Promise.resolve()).then(function () {
                return { testMode: false, jobCount: hexJobs.length };
            });
        });
    }

    function showPrintResult(result) {
        var config = getConfig();
        result = result || {};

        if (result.testMode) {
            var template = result.dryRun
                ? (config.messages?.testValidateSuccess || 'Test OK: :count receipt(s) validated (cut command present). ESC/POS was not sent to a printer.')
                : (config.messages?.testPrintSuccess || 'Test print saved :count receipt(s) to :file');

            showToast(
                template
                    .replace(':count', String(result.jobCount || 0))
                    .replace(':file', result.outputFile || ''),
                'success'
            );

            return;
        }

        showToast(config.messages?.printSuccess || 'Sent to thermal printer.', 'success');
    }

    function showSetupCompleteMessage(printerName) {
        var config = getConfig();
        var template = config.messages?.setupComplete
            || 'Thermal printer saved: :printer. You can print receipts directly next time.';

        showToast(template.replace(':printer', printerName), 'success');
    }

    function updateTestPrinterHint(printerName) {
        var hintEl = document.getElementById('qzWizardSavedPrinterHint');
        var config = getConfig();

        if (!hintEl || !isTestPrinter(printerName)) {
            return;
        }

        var template = config.messages?.testPrinterHint
            || 'Test mode validates ESC/POS output (one cut per code). Optional sandbox file: ~/.qz/sandbox/:file';

        hintEl.textContent = template.replace(':file', getTestOutputPath());
    }

    function ensureModalInBody(modalId) {
        var el = document.getElementById(modalId);

        if (el && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }

        return el;
    }

    function isModalVisible(modalId) {
        var el = document.getElementById(modalId);

        if (!el) {
            return false;
        }

        return el.classList.contains('show') || el.getAttribute('aria-hidden') === 'false';
    }

    function cleanupOrphanModalState() {
        if (typeof jQuery === 'undefined') {
            return;
        }

        var visibleCount = QZ_MODAL_IDS.filter(isModalVisible).length;

        if (visibleCount > 0) {
            return;
        }

        jQuery('.modal-backdrop').remove();
        jQuery('body').removeClass('modal-open').css({
            paddingRight: '',
            overflow: '',
        });
    }

    function modalShow(modalId) {
        return new Promise(function (resolve) {
            var el = ensureModalInBody(modalId);
            var settled = false;

            function finish() {
                if (settled) {
                    return;
                }

                settled = true;
                resolve();
            }

            if (!el) {
                finish();
                return;
            }

            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
                var $el = jQuery(el);

                if ($el.hasClass('show')) {
                    finish();
                    return;
                }

                $el.off('shown.bs.modal.buysellesQz').one('shown.bs.modal.buysellesQz', finish);
                $el.modal({
                    backdrop: 'static',
                    keyboard: true,
                    show: true,
                });
                window.setTimeout(finish, 450);

                return;
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var Modal = bootstrap.Modal;
                var instance = typeof Modal.getOrCreateInstance === 'function'
                    ? Modal.getOrCreateInstance(el)
                    : new Modal(el);
                instance.show();
            }

            finish();
        });
    }

    function modalHide(modalId) {
        return new Promise(function (resolve) {
            var el = ensureModalInBody(modalId);
            var settled = false;

            function finish() {
                if (settled) {
                    return;
                }

                settled = true;
                cleanupOrphanModalState();
                resolve();
            }

            if (!el) {
                finish();
                return;
            }

            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
                var $el = jQuery(el);

                if (!$el.hasClass('show') && $el.attr('aria-hidden') !== 'false') {
                    finish();
                    return;
                }

                $el.off('hidden.bs.modal.buysellesQz').one('hidden.bs.modal.buysellesQz', finish);
                $el.modal('hide');
                window.setTimeout(finish, 450);

                return;
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var Modal = bootstrap.Modal;
                var instance = typeof Modal.getInstance === 'function'
                    ? Modal.getInstance(el)
                    : null;

                if (instance) {
                    instance.hide();
                }
            }

            finish();
        });
    }

    function setWizardStep(step) {
        document.querySelectorAll('[data-wizard-step-indicator]').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-wizard-step-indicator'), 10);
            el.classList.remove('qz-wizard-step--active', 'qz-wizard-step--done');
            if (n < step) {
                el.classList.add('qz-wizard-step--done');
            } else if (n === step) {
                el.classList.add('qz-wizard-step--active');
            }
        });

        document.querySelectorAll('[data-wizard-panel]').forEach(function (panel) {
            var n = parseInt(panel.getAttribute('data-wizard-panel'), 10);
            panel.classList.toggle('d-none', n !== step);
        });

        var backBtn = document.getElementById('qzWizardBackBtn');
        var nextBtn = document.getElementById('qzWizardNextBtn');
        var printBtn = document.getElementById('qzWizardPrintBtn');
        var doneBtn = document.getElementById('qzWizardDoneBtn');
        var previewBtn = document.getElementById('qzWizardPreviewBtn');

        if (backBtn) {
            backBtn.classList.toggle('d-none', step <= 1);
        }

        if (nextBtn) {
            nextBtn.classList.toggle('d-none', step >= 3);
        }

        if (printBtn) {
            printBtn.classList.toggle('d-none', step !== 3 || !wizardContext || !wizardContext.orderIds || !wizardContext.orderIds.length);
        }

        if (doneBtn) {
            doneBtn.classList.toggle('d-none', !(step === 3 && (!wizardContext || !wizardContext.orderIds || !wizardContext.orderIds.length)));
        }

        if (previewBtn) {
            previewBtn.classList.toggle('d-none', false);
        }
    }

    function setWizardStatus(type, text) {
        var dot = document.getElementById('qzWizardStatusDot');
        var label = document.getElementById('qzWizardStatusText');

        if (dot) {
            dot.className = 'qz-wizard-status__dot qz-wizard-status__dot--' + (type || 'idle');
        }

        if (label) {
            label.textContent = text || '';
        }
    }

    function populatePrinterSelect(printers) {
        var selectEl = document.getElementById('qzWizardPrinterSelect');
        var errorEl = document.getElementById('qzWizardPrinterError');
        var printBtn = document.getElementById('qzWizardPrintBtn');
        var hintEl = document.getElementById('qzWizardSavedPrinterHint');
        var config = getConfig();

        if (!selectEl) {
            return;
        }

        selectEl.innerHTML = '';

        if (!printers || !printers.length) {
            if (errorEl) {
                errorEl.classList.remove('d-none');
                errorEl.textContent = config.messages?.noPrinters || 'No printers found on this computer.';
            }

            if (printBtn) {
                printBtn.disabled = true;
            }

            return;
        }

        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }

        printers.forEach(function (printer) {
            var option = document.createElement('option');
            option.value = printer;
            option.textContent = printer;
            selectEl.appendChild(option);
        });

        var saved = getSavedPrinter();
        var defaultPrinter = config.defaultPrinter || '';

        if (saved && printers.indexOf(saved) >= 0) {
            selectEl.value = saved;
        } else if (defaultPrinter && printers.indexOf(defaultPrinter) >= 0) {
            selectEl.value = defaultPrinter;
        }

        if (printBtn) {
            printBtn.disabled = !selectEl.value;
        }

        var doneBtn = document.getElementById('qzWizardDoneBtn');
        if (doneBtn) {
            doneBtn.disabled = !selectEl.value;
        }

        updateTestPrinterHint(selectEl.value);

        if (hintEl && !isTestPrinter(selectEl.value)) {
            hintEl.textContent = saved
                ? (config.messages?.savedPrinterHint || 'Saved printer on this browser: ') + saved
                : (config.messages?.savePrinterHint || 'Your printer choice is saved in this browser for next time.');
        }
    }

    function loadWizardPrinters() {
        var loadingEl = document.getElementById('qzWizardPrinterLoading');
        var config = getConfig();

        if (loadingEl) {
            loadingEl.classList.remove('d-none');
        }

        setWizardStatus('idle', config.messages?.loadingPrinters || 'Loading printers...');

        return listPrinters({ forceReconnect: false })
            .then(function (printers) {
                populatePrinterSelect(printers);
                setWizardStatus('ok', config.messages?.connectedReady || 'Connected — choose your thermal printer.');
                setWizardStep(3);
            })
            .catch(function (error) {
                setWizardStatus('error', formatConnectionError(error));
                setWizardStep(2);
                throw error;
            })
            .finally(function () {
                if (loadingEl) {
                    loadingEl.classList.add('d-none');
                }
            });
    }

    function handleWizardConnect() {
        var connectBtn = document.getElementById('qzWizardConnectBtn');
        var errorEl = document.getElementById('qzWizardConnectError');
        var config = getConfig();

        if (connectBtn) {
            connectBtn.disabled = true;
        }

        setWizardStatus('idle', config.messages?.connecting || 'Connecting to QZ Tray...');

        return connect({
            timeoutMs: config.connectTimeoutMs || 60000,
            forceReconnect: true,
        })
            .then(function () {
                if (errorEl) {
                    errorEl.classList.add('d-none');
                    errorEl.textContent = '';
                }

                return loadWizardPrinters();
            })
            .catch(function (error) {
                var friendlyError = formatConnectionError(error);

                if (errorEl) {
                    errorEl.classList.remove('d-none');
                    errorEl.textContent = friendlyError;

                    if ((friendlyError.toLowerCase().indexOf('timed out') >= 0 || friendlyError.toLowerCase().indexOf('timeout') >= 0)
                        && config.messages?.resetSiteManager) {
                        errorEl.textContent = friendlyError + ' ' + config.messages.resetSiteManager;
                    }
                }

                setWizardStatus('error', friendlyError);
                setWizardStep(2);
            })
            .finally(function () {
                if (connectBtn) {
                    connectBtn.disabled = false;
                }
            });
    }

    function openWizard(options) {
        options = options || {};
        wizardContext = {
            orderIds: options.orderIds || [],
            onPreviewFallback: options.onPreviewFallback || null,
        };

        var modalEl = document.getElementById('qzTrayWizardModal');

        if (!modalEl) {
            if (typeof options.onPreviewFallback === 'function') {
                options.onPreviewFallback();
            }

            return Promise.reject(new Error('Wizard unavailable.'));
        }

        var connectError = document.getElementById('qzWizardConnectError');
        if (connectError) {
            connectError.classList.add('d-none');
            connectError.textContent = '';
        }

        return modalShow('qzTrayWizardModal').then(function () {
            if (options.startStep === 3) {
                setWizardStep(3);

                return loadWizardPrinters();
            }

            if (isConnected()) {
                setWizardStatus('ok', getConfig().messages?.connectedContinue || 'QZ Tray connected. Continue to select your printer.');

                if (getSavedPrinter()) {
                    setWizardStatus('ok', getConfig().messages?.connectedSaved || 'Connected — saved printer ready.');
                    setWizardStep(3);

                    return loadWizardPrinters();
                }

                setWizardStep(2);

                return Promise.resolve();
            }

            setWizardStep(options.startStep || 2);
            setWizardStatus(
                'warn',
                getConfig().messages?.clickConnect
                    || 'QZ Tray is installed? Click "Connect to QZ Tray" below. If a security popup appears, choose Allow and check Remember.'
            );

            return Promise.resolve();
        });
    }

    function openThermalChoiceModal(orderIds, onPreviewFallback) {
        var choiceEl = document.getElementById('qzTrayThermalChoiceModal');

        if (!choiceEl) {
            return openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
        }

        wizardContext = {
            orderIds: orderIds,
            onPreviewFallback: onPreviewFallback,
        };

        var previewBtn = document.getElementById('qzChoicePreviewBtn');
        var directBtn = document.getElementById('qzChoiceDirectBtn');
        var setupBtn = document.getElementById('qzChoiceSetupBtn');

        if (previewBtn) {
            previewBtn.onclick = function () {
                modalHide('qzTrayThermalChoiceModal').then(function () {
                    if (typeof onPreviewFallback === 'function') {
                        onPreviewFallback();
                    }
                });
            };
        }

        if (directBtn) {
            directBtn.onclick = function () {
                modalHide('qzTrayThermalChoiceModal').then(function () {
                    runThermalPrint(orderIds, onPreviewFallback);
                });
            };
        }

        if (setupBtn) {
            setupBtn.onclick = function () {
                modalHide('qzTrayThermalChoiceModal').then(function () {
                    openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
                });
            };
        }

        return modalShow('qzTrayThermalChoiceModal');
    }

    function runThermalPrint(orderIds, onPreviewFallback) {
        var savedPrinter = getSavedPrinter();

        if (savedPrinter) {
            return printWithSavedPrinter(orderIds)
                .then(function (result) {
                    showPrintResult(result);
                })
                .catch(function () {
                    return openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback, startStep: 2 });
                });
        }

        return openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
    }

    function printWithSavedPrinter(orderIds) {
        var savedPrinter = getSavedPrinter();

        if (!savedPrinter) {
            return Promise.reject(new Error('No saved printer.'));
        }

        return fetchEscPosJobs(orderIds).then(function (payload) {
            return printHexJobs(savedPrinter, payload.jobs || [], { timeoutMs: 20000 });
        });
    }

    function tryAutoPrint(orderIds, onPreviewFallback) {
        var savedPrinter = getSavedPrinter();

        if (!savedPrinter) {
            if (typeof onPreviewFallback === 'function') {
                onPreviewFallback();
            }

            return Promise.resolve();
        }

        return printWithSavedPrinter(orderIds)
            .then(function (result) {
                showPrintResult(result);
            })
            .catch(function () {
                openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
            });
    }

    function bindWizardEvents() {
        var connectBtn = document.getElementById('qzWizardConnectBtn');
        var nextBtn = document.getElementById('qzWizardNextBtn');
        var backBtn = document.getElementById('qzWizardBackBtn');
        var refreshBtn = document.getElementById('qzWizardRefreshPrintersBtn');
        var printBtn = document.getElementById('qzWizardPrintBtn');
        var previewBtn = document.getElementById('qzWizardPreviewBtn');
        var doneBtn = document.getElementById('qzWizardDoneBtn');
        var selectEl = document.getElementById('qzWizardPrinterSelect');

        if (connectBtn && connectBtn.dataset.bound !== '1') {
            connectBtn.dataset.bound = '1';
            connectBtn.addEventListener('click', function () {
                handleWizardConnect();
            });
        }

        if (nextBtn && nextBtn.dataset.bound !== '1') {
            nextBtn.dataset.bound = '1';
            nextBtn.addEventListener('click', function () {
                var active = document.querySelector('.qz-wizard-step--active');
                var step = active ? parseInt(active.getAttribute('data-wizard-step-indicator'), 10) : 1;

                if (step === 1) {
                    setWizardStep(2);
                    return;
                }

                if (step === 2) {
                    handleWizardConnect();
                }
            });
        }

        if (backBtn && backBtn.dataset.bound !== '1') {
            backBtn.dataset.bound = '1';
            backBtn.addEventListener('click', function () {
                var active = document.querySelector('.qz-wizard-step--active');
                var step = active ? parseInt(active.getAttribute('data-wizard-step-indicator'), 10) : 1;
                setWizardStep(Math.max(1, step - 1));
            });
        }

        if (refreshBtn && refreshBtn.dataset.bound !== '1') {
            refreshBtn.dataset.bound = '1';
            refreshBtn.addEventListener('click', function () {
                loadWizardPrinters();
            });
        }

        if (selectEl && selectEl.dataset.bound !== '1') {
            selectEl.dataset.bound = '1';
            selectEl.addEventListener('change', function () {
                var printButton = document.getElementById('qzWizardPrintBtn');
                var doneButton = document.getElementById('qzWizardDoneBtn');

                if (printButton) {
                    printButton.disabled = !selectEl.value;
                }

                if (doneButton) {
                    doneButton.disabled = !selectEl.value;
                }

                updateTestPrinterHint(selectEl.value);
            });
        }

        if (printBtn && printBtn.dataset.bound !== '1') {
            printBtn.dataset.bound = '1';
            printBtn.addEventListener('click', function () {
                if (!wizardContext || !selectEl || !selectEl.value) {
                    return;
                }

                var printerName = selectEl.value;
                var orderIds = wizardContext.orderIds || [];
                var config = getConfig();

                printBtn.disabled = true;
                printBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i>' + (config.messages?.printing || 'Printing...');

                savePrinter(printerName);

                fetchEscPosJobs(orderIds)
                    .then(function (payload) {
                        return printHexJobs(printerName, payload.jobs || []);
                    })
                    .then(function (result) {
                        return modalHide('qzTrayWizardModal').then(function () {
                            showPrintResult(result);
                        });
                    })
                    .catch(function (error) {
                        showToast(error.message || (config.messages?.printFailed || 'Printing failed.'), 'error');
                    })
                    .finally(function () {
                        printBtn.disabled = false;
                        printBtn.textContent = config.messages?.printNow || 'Print Now';
                    });
            });
        }

        if (previewBtn && previewBtn.dataset.bound !== '1') {
            previewBtn.dataset.bound = '1';
            previewBtn.addEventListener('click', function () {
                modalHide('qzTrayWizardModal');

                if (wizardContext && typeof wizardContext.onPreviewFallback === 'function') {
                    wizardContext.onPreviewFallback();
                }
            });
        }

        if (doneBtn && doneBtn.dataset.bound !== '1') {
            doneBtn.dataset.bound = '1';
            doneBtn.addEventListener('click', function (event) {
                event.preventDefault();

                var config = getConfig();
                var printerName = selectEl ? selectEl.value : '';

                if (!printerName) {
                    showToast(config.messages?.selectPrinterFirst || 'Select a thermal printer before clicking Done.', 'error');

                    return;
                }

                if (!isConnected()) {
                    showToast(config.messages?.notConnectedSetup || config.messages?.notConnected || 'QZ Tray is not connected.', 'error');
                    setWizardStatus('error', config.messages?.notConnectedSetup || config.messages?.notConnected || 'QZ Tray is not connected.');
                    setWizardStep(2);

                    return;
                }

                savePrinter(printerName);

                modalHide('qzTrayWizardModal').then(function () {
                    showSetupCompleteMessage(printerName);
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindWizardEvents();
    });

    window.BuysellesQzTray = {
        connect: connect,
        isConnected: isConnected,
        listPrinters: listPrinters,
        printDigitalCodes: runThermalPrint,
        tryAutoPrint: tryAutoPrint,
        openSetupWizard: function (orderIds, onPreviewFallback) {
            return openWizard({ orderIds: orderIds || [], onPreviewFallback: onPreviewFallback || null });
        },
        openThermalChoice: openThermalChoiceModal,
        clearSavedPrinter: clearSavedPrinter,
        getSavedPrinter: getSavedPrinter,
    };
}(window));
