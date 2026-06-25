<div class="modal fade" id="bluetoothThermalSetupModal" tabindex="-1" role="dialog" aria-labelledby="bluetoothThermalSetupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="pr-3">
                    <h5 class="modal-title mb-1" id="bluetoothThermalSetupModalLabel">
                        {{ translate('configure_thermal_printer') ?: 'Configure printer' }}
                    </h5>
                    <p class="text-muted fs-13 mb-0">
                        {{ translate('bluetooth_thermal_setup_intro') ?: 'Pair a Bluetooth thermal printer, or set up QZ Tray for USB/network printers.' }}
                    </p>
                </div>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pt-3">
                <div id="bluetoothThermalTestBanner" class="thermal-setup-notice thermal-setup-notice--info d-none">
                    <i class="fa fa-flask"></i>
                    <span id="bluetoothThermalTestBannerText">{{ translate('bluetooth_test_mode_banner') ?: 'Test mode — use demo printer below to simulate without hardware.' }}</span>
                </div>

                <div id="bluetoothThermalUnsupportedNotice" class="thermal-setup-notice thermal-setup-notice--warn d-none">
                    <i class="fa fa-info-circle"></i>
                    {{ translate('bluetooth_unsupported') ?: 'Bluetooth requires Chrome or Edge on HTTPS.' }}
                </div>

                <div class="thermal-setup-section">
                    <div class="thermal-setup-section__header">
                        <span class="thermal-setup-section__icon"><i class="fa fa-bluetooth-b"></i></span>
                        <div>
                            <strong class="thermal-setup-section__title">{{ translate('bluetooth_thermal_print') ?: 'Bluetooth printer' }}</strong>
                            <p class="thermal-setup-section__desc mb-0">{{ translate('bluetooth_thermal_help') ?: 'BLE receipt printers — no QZ Tray needed.' }}</p>
                        </div>
                    </div>

                    <div class="thermal-setup-section__body">
                        <p class="thermal-setup-status text-muted mb-2" id="bluetoothThermalStatus">
                            {{ translate('bluetooth_setup_hint') ?: 'Turn on the printer and enable pairing mode.' }}
                        </p>

                        <div class="thermal-setup-saved mb-3">
                            <span class="thermal-setup-saved__label">{{ translate('saved_printer') ?: 'Saved printer' }}</span>
                            <span class="thermal-setup-saved__value" id="bluetoothThermalSavedDevice">{{ translate('bluetooth_no_saved_device') ?: 'None' }}</span>
                        </div>

                        <div class="thermal-setup-actions">
                            <button type="button" class="btn btn-primary btn-block thermal-setup-actions__primary" id="bluetoothThermalTestConnectBtn">
                                <i class="fa fa-flask mr-1"></i>{{ translate('bluetooth_test_connect') ?: 'Use demo Bluetooth printer' }}
                            </button>
                            <button type="button" class="btn btn-primary btn-block thermal-setup-actions__primary" id="bluetoothThermalConnectBtn">
                                <i class="fa fa-bluetooth-b mr-1"></i>{{ translate('connect_bluetooth_printer') ?: 'Connect Bluetooth printer' }}
                            </button>
                            <button type="button" class="btn btn-link btn-sm thermal-setup-actions__clear d-none" id="bluetoothThermalClearBtn">
                                {{ translate('clear_saved_printer') ?: 'Clear saved printer' }}
                            </button>
                        </div>
                    </div>
                </div>

                @if ($qzTrayAvailable ?? false)
                    <div class="thermal-setup-section thermal-setup-section--secondary">
                        <div class="thermal-setup-section__header">
                            <span class="thermal-setup-section__icon thermal-setup-section__icon--qz"><i class="fa fa-desktop"></i></span>
                            <div>
                                <strong class="thermal-setup-section__title">{{ translate('setup_qz_tray_printer') ?: 'QZ Tray printer' }}</strong>
                                <p class="thermal-setup-section__desc mb-0">{{ translate('qz_tray_usb_network_help') ?: 'USB or network printers on desktop.' }}</p>
                            </div>
                        </div>
                        <div class="thermal-setup-section__body">
                            <button type="button" class="btn btn-outline-primary btn-block" id="bluetoothThermalQzSetupBtn">
                                <i class="fa fa-cog mr-1"></i>{{ translate('open_qz_tray_setup') ?: 'Open QZ Tray setup' }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bluetoothThermalTestResultModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1 text-success">
                        <i class="fa fa-check-circle mr-1"></i>{{ translate('bluetooth_test_passed_title') ?: 'Bluetooth print test passed' }}
                    </h5>
                    <p class="text-muted fs-13 mb-0" id="bluetoothTestResultSummary">
                        {{ translate('bluetooth_test_passed_summary') ?: 'ESC/POS data is valid and ready for a real Bluetooth printer.' }}
                    </p>
                </div>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pt-3">
                <ul class="list-unstyled fs-13 mb-3" id="bluetoothTestResultChecks"></ul>
                <p class="fs-12 text-muted mb-2">
                    {{ translate('bluetooth_test_raw_file_help') ?: 'The downloaded .raw file contains the exact bytes that would be sent over Bluetooth.' }}
                </p>
                <p class="fs-13 mb-2 fw-semibold">{{ translate('bluetooth_test_receipt_preview') ?: 'Receipt preview:' }}</p>
                <pre class="thermal-setup-preview" id="bluetoothTestResultPreview"></pre>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary" data-dismiss="modal" data-bs-dismiss="modal">{{ translate('Close') }}</button>
            </div>
        </div>
    </div>
</div>
