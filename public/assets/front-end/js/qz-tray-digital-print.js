(function (window) {
    'use strict';

    var STORAGE_KEY = 'buyselles_qz_printer';
    var securityInitialized = false;
    var connectPromise = null;
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

    function verifySigningEndpoints() {
        var config = getConfig();

        return fetchText(config.certificateUrl)
            .then(function (certificate) {
                if ((certificate || '').indexOf('BEGIN CERTIFICATE') < 0) {
                    throw new Error('Invalid QZ Tray certificate response from server.');
                }
            })
            .then(function () {
                return signRequest('qz-tray-setup-test');
            })
            .then(function (signature) {
                if (!signature) {
                    throw new Error('Empty signature response from server.');
                }
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
                || 'Connection timed out. Click Connect again, approve the QZ Tray popup with Allow (try without Remember first), and wait up to 2 minutes.';
        }

        if (lower.indexOf('failed to sign') >= 0 || lower.indexOf('signing failed') >= 0) {
            return config.messages?.signFailed
                || 'QZ Tray signing failed on the server. Check /qz-tray/sign returns 200, then try again.';
        }

        if (lower.indexOf('unable to establish connection') >= 0) {
            return config.messages?.unreachable
                || 'Browser cannot reach QZ Tray. Confirm the QZ Tray icon is in your system tray, then click Connect again.';
        }

        if (lower.indexOf('denied') >= 0 || lower.indexOf('blocked') >= 0 || lower.indexOf('untrusted') >= 0) {
            return config.messages?.trustDenied
                || 'QZ Tray blocked this website. Open QZ Tray, reset allowed sites if needed, then click Connect and choose Allow / Remember.';
        }

        if (message) {
            return message;
        }

        return config.messages?.connectFailed || 'Could not connect to QZ Tray.';
    }

    function getConnectOptions() {
        var usingSecure = window.location.protocol === 'https:';

        return {
            host: ['localhost', '127.0.0.1'],
            port: {
                secure: [8181],
                insecure: [8182],
            },
            usingSecure: usingSecure,
            retries: 0,
            delay: 0,
            keepAlive: 30,
        };
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

            qz.security.setSignaturePromise(function (toSign) {
                return function (resolve, reject) {
                    signRequest(toSign)
                        .then(function (signature) {
                            resolve((signature || '').trim());
                        })
                        .catch(reject);
                };
            });

            securityInitialized = true;

            return qz;
        });
    }

    function isConnectionReady(qz) {
        if (!qz || !qz.websocket || !qz.websocket.connection) {
            return false;
        }

        var connection = qz.websocket.connection;

        return connection.established === true
            && typeof connection.sendData === 'function'
            && connection.readyState === 1;
    }

    function resetConnection(qz) {
        if (!qz.websocket.connection || isConnectionReady(qz)) {
            return Promise.resolve();
        }

        return qz.websocket.disconnect().catch(function () {
            // Ignore disconnect errors for half-open connections.
        });
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

    function connectOnce() {
        var hostnameWarning = getHostnameMismatchMessage();

        if (hostnameWarning) {
            return Promise.reject(new Error(hostnameWarning));
        }

        return initSecurity()
            .then(function (qz) {
                return verifySigningEndpoints().then(function () {
                    return qz;
                });
            })
            .then(function (qz) {
                if (isConnectionReady(qz)) {
                    return qz;
                }

                return resetConnection(qz).then(function () {
                    return qz.websocket.connect(getConnectOptions()).then(function () {
                        if (!isConnectionReady(qz)) {
                            throw new Error(getConfig().messages?.connectFailed || 'Could not connect to QZ Tray.');
                        }

                        return qz;
                    });
                });
            });
    }

    function connect() {
        var config = getConfig();
        var timeoutMs = config.connectTimeoutMs || 120000;

        if (!connectPromise) {
            connectPromise = connectOnce().finally(function () {
                connectPromise = null;
            });
        }

        return withTimeout(connectPromise, timeoutMs);
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

    function listPrinters() {
        return connect().then(function (qz) {
            return qz.printers.find();
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

    function printHexJobs(printerName, hexJobs) {
        return connect().then(function (qz) {
            var config = qz.configs.create(printerName);

            return hexJobs.reduce(function (chain, hexData) {
                return chain.then(function () {
                    return qz.print(config, [{
                        type: 'raw',
                        format: 'command',
                        flavor: 'hex',
                        data: hexData,
                    }]);
                });
            }, Promise.resolve());
        });
    }

    function modalShow(modalId) {
        var el = document.getElementById(modalId);

        if (!el) {
            return;
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery(el).modal('show');
            return;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var Modal = bootstrap.Modal;
            var instance = typeof Modal.getOrCreateInstance === 'function'
                ? Modal.getOrCreateInstance(el)
                : new Modal(el);
            instance.show();
        }
    }

    function modalHide(modalId) {
        var el = document.getElementById(modalId);

        if (!el) {
            return;
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery(el).modal('hide');
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

        if (hintEl) {
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

        return listPrinters()
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

        modalShow('qzTrayWizardModal');

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
                || 'QZ Tray is installed? Click "Connect to QZ Tray" below. If a security popup appears, choose Allow / Remember.'
        );

        return Promise.resolve();
    }

    function openThermalChoiceModal(orderIds, onPreviewFallback) {
        var choiceEl = document.getElementById('qzTrayThermalChoiceModal');

        if (!choiceEl) {
            openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
            return;
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
                modalHide('qzTrayThermalChoiceModal');
                if (typeof onPreviewFallback === 'function') {
                    onPreviewFallback();
                }
            };
        }

        if (directBtn) {
            directBtn.onclick = function () {
                modalHide('qzTrayThermalChoiceModal');
                runThermalPrint(orderIds, onPreviewFallback);
            };
        }

        if (setupBtn) {
            setupBtn.onclick = function () {
                modalHide('qzTrayThermalChoiceModal');
                openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback });
            };
        }

        modalShow('qzTrayThermalChoiceModal');
    }

    function runThermalPrint(orderIds, onPreviewFallback) {
        var savedPrinter = getSavedPrinter();

        if (savedPrinter) {
            return printWithSavedPrinter(orderIds)
                .then(function () {
                    showToast(getConfig().messages?.printSuccess || 'Sent to thermal printer.');
                })
                .catch(function () {
                    return openWizard({ orderIds: orderIds, onPreviewFallback: onPreviewFallback, startStep: 1 });
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
            return printHexJobs(savedPrinter, payload.jobs || []);
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
            .then(function () {
                showToast(getConfig().messages?.printSuccess || 'Sent to thermal printer.');
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
        var selectEl = document.getElementById('qzWizardPrinterSelect');

        if (connectBtn && connectBtn.dataset.bound !== '1') {
            connectBtn.dataset.bound = '1';
            connectBtn.addEventListener('click', function () {
                var errorEl = document.getElementById('qzWizardConnectError');
                connectBtn.disabled = true;
                setWizardStatus('idle', getConfig().messages?.connecting || 'Connecting to QZ Tray...');

                connect()
                    .then(function () {
                        if (errorEl) {
                            errorEl.classList.add('d-none');
                        }

                        return loadWizardPrinters();
                    })
                    .catch(function (error) {
                        var friendlyError = formatConnectionError(error);

                        if (errorEl) {
                            errorEl.classList.remove('d-none');
                            errorEl.textContent = friendlyError;

                            if ((friendlyError.toLowerCase().indexOf('timed out') >= 0 || friendlyError.toLowerCase().indexOf('timeout') >= 0)
                                && getConfig().messages?.resetSiteManager) {
                                errorEl.textContent = friendlyError + ' ' + getConfig().messages.resetSiteManager;
                            }
                        }

                        setWizardStatus('error', friendlyError);
                    })
                    .finally(function () {
                        connectBtn.disabled = false;
                    });
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
                    loadWizardPrinters();
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
                if (printButton) {
                    printButton.disabled = !selectEl.value;
                }
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
                    .then(function () {
                        modalHide('qzTrayWizardModal');

                        showToast(config.messages?.printSuccess || 'Sent to thermal printer.');
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
    }

    function injectSetupLinks() {
        if (!getConfig().enabled) {
            return;
        }

        document.querySelectorAll('.digital-code-delivery-actions').forEach(function (wrapper) {
            if (wrapper.querySelector('.digital-code-qz-setup-link')) {
                return;
            }

            var thermalBtn = wrapper.querySelector('.digital-code-action-thermal');
            if (!thermalBtn) {
                return;
            }

            var content = thermalBtn.querySelector('.digital-code-delivery-option__content');
            if (!content) {
                return;
            }

            var link = document.createElement('button');
            link.type = 'button';
            link.className = 'btn btn-link p-0 digital-code-qz-setup-link digital-code-action-qz-setup';
            link.textContent = getConfig().messages?.setupLink || 'Setup thermal printer (QZ Tray)';
            link.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openWizard({ orderIds: [], onPreviewFallback: null });
            });

            content.appendChild(link);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindWizardEvents();
        injectSetupLinks();
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
