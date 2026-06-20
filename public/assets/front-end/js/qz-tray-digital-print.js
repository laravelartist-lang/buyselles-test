(function (window) {
    'use strict';

    var STORAGE_KEY = 'buyselles_qz_printer';
    var securityInitialized = false;
    var pendingThermalContext = null;

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
                reject(new Error('Connection timed out.'));
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

    function fetchText(url) {
        return fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/plain',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed: ' + response.status);
            }

            return response.text();
        });
    }

    function ensureQzLoaded() {
        if (typeof window.qz === 'undefined') {
            return Promise.reject(new Error('QZ Tray library not loaded.'));
        }

        return Promise.resolve(window.qz);
    }

    function initSecurity() {
        if (securityInitialized) {
            return ensureQzLoaded();
        }

        var config = getConfig();

        return ensureQzLoaded().then(function (qz) {
            qz.security.setSignatureAlgorithm('SHA512');

            qz.security.setCertificatePromise(function (resolve, reject) {
                fetchText(config.certificateUrl).then(resolve).catch(reject);
            });

            qz.security.setSignaturePromise(function (toSign) {
                return function (resolve, reject) {
                    fetchText(config.signUrl + '?request=' + encodeURIComponent(toSign))
                        .then(resolve)
                        .catch(reject);
                };
            });

            securityInitialized = true;

            return qz;
        });
    }

    function connect() {
        var config = getConfig();
        var timeoutMs = config.connectTimeoutMs || 4000;

        return withTimeout(
            initSecurity().then(function (qz) {
                if (qz.websocket.isActive()) {
                    return qz;
                }

                return qz.websocket.connect({ retries: 1, delay: 1 }).then(function () {
                    return qz;
                });
            }),
            timeoutMs
        );
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
                throw new Error('Unable to load print data.');
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

    function showModal(modalId) {
        var el = document.getElementById(modalId);

        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function openSetupModal(onPreviewFallback) {
        var modal = showModal('qzTraySetupModal');

        if (!modal) {
            if (typeof onPreviewFallback === 'function') {
                onPreviewFallback();
            }

            return;
        }

        var fallbackBtn = document.getElementById('qzTrayPreviewFallbackBtn');

        if (fallbackBtn) {
            fallbackBtn.onclick = function () {
                modal.hide();

                if (typeof onPreviewFallback === 'function') {
                    onPreviewFallback();
                }
            };
        }

        modal.show();
    }

    function openPrinterModal(orderIds, onPreviewFallback) {
        var modal = showModal('qzTrayPrinterModal');

        if (!modal) {
            if (typeof onPreviewFallback === 'function') {
                onPreviewFallback();
            }

            return Promise.reject(new Error('Printer modal unavailable.'));
        }

        pendingThermalContext = {
            orderIds: orderIds,
            onPreviewFallback: onPreviewFallback,
        };

        var loadingEl = document.getElementById('qzTrayPrinterLoading');
        var selectEl = document.getElementById('qzTrayPrinterSelect');
        var errorEl = document.getElementById('qzTrayPrinterError');
        var confirmBtn = document.getElementById('qzTrayPrinterConfirmBtn');

        if (loadingEl) {
            loadingEl.style.display = '';
        }

        if (selectEl) {
            selectEl.style.display = 'none';
            selectEl.innerHTML = '';
        }

        if (errorEl) {
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }

        if (confirmBtn) {
            confirmBtn.disabled = true;
        }

        modal.show();

        return listPrinters().then(function (printers) {
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }

            if (!printers || !printers.length) {
                if (errorEl) {
                    errorEl.style.display = '';
                    errorEl.textContent = getConfig().messages?.noPrinters || 'No printers found.';
                }

                throw new Error('No printers found.');
            }

            if (selectEl) {
                selectEl.style.display = '';
                printers.forEach(function (printer) {
                    var option = document.createElement('option');
                    option.value = printer;
                    option.textContent = printer;
                    selectEl.appendChild(option);
                });

                var saved = getSavedPrinter();
                var defaultPrinter = getConfig().defaultPrinter || '';

                if (saved && printers.indexOf(saved) >= 0) {
                    selectEl.value = saved;
                } else if (defaultPrinter && printers.indexOf(defaultPrinter) >= 0) {
                    selectEl.value = defaultPrinter;
                }

                if (confirmBtn) {
                    confirmBtn.disabled = false;
                }
            }
        }).catch(function () {
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }

            modal.hide();

            if (getConfig().mode === 'qz') {
                openSetupModal(onPreviewFallback);
            } else if (typeof onPreviewFallback === 'function') {
                onPreviewFallback();
            }

            return Promise.reject(new Error('QZ Tray unavailable.'));
        });
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

    function runThermalPrint(orderIds, onPreviewFallback) {
        var savedPrinter = getSavedPrinter();

        if (savedPrinter) {
            return printWithSavedPrinter(orderIds)
                .then(function () {
                    showToast(getConfig().messages?.printSuccess || 'Sent to thermal printer.');
                })
                .catch(function () {
                    return openPrinterModal(orderIds, onPreviewFallback);
                });
        }

        return openPrinterModal(orderIds, onPreviewFallback);
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
                if (typeof onPreviewFallback === 'function') {
                    onPreviewFallback();
                }
            });
    }

    function bindPrinterModalConfirm() {
        var confirmBtn = document.getElementById('qzTrayPrinterConfirmBtn');
        var selectEl = document.getElementById('qzTrayPrinterSelect');

        if (!confirmBtn || confirmBtn.dataset.bound === '1') {
            return;
        }

        confirmBtn.dataset.bound = '1';

        confirmBtn.addEventListener('click', function () {
            if (!pendingThermalContext || !selectEl || !selectEl.value) {
                return;
            }

            var printerName = selectEl.value;
            var orderIds = pendingThermalContext.orderIds;
            var config = getConfig();

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (config.messages?.printing || 'Printing...');

            savePrinter(printerName);

            fetchEscPosJobs(orderIds)
                .then(function (payload) {
                    return printHexJobs(printerName, payload.jobs || []);
                })
                .then(function () {
                    var modal = showModal('qzTrayPrinterModal');

                    if (modal) {
                        modal.hide();
                    }

                    showToast(config.messages?.printSuccess || 'Sent to thermal printer.');
                })
                .catch(function (error) {
                    showToast(error.message || 'Printing failed.', 'error');
                })
                .finally(function () {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = config.messages?.printNow || 'Print Now';
                });
        });
    }

    document.addEventListener('DOMContentLoaded', bindPrinterModalConfirm);

    window.BuysellesQzTray = {
        connect: connect,
        listPrinters: listPrinters,
        printDigitalCodes: runThermalPrint,
        tryAutoPrint: tryAutoPrint,
        openPrinterPicker: openPrinterModal,
    };
}(window));
