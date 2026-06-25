<div class="modal fade qz-tray-modal" id="qzTrayWizardModal" tabindex="-1" role="dialog" aria-labelledby="qzTrayWizardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="pr-3">
                    <h5 class="modal-title mb-1" id="qzTrayWizardModalLabel">
                        {{ translate('thermal_printer_setup') ?: 'Thermal Printer Setup' }}
                    </h5>
                    <p class="text-muted fs-13 mb-0">
                        {{ translate('qz_tray_wizard_intro') ?: 'Install QZ Tray once on this computer, then connect your USB or network thermal printer.' }}
                    </p>
                </div>
                <button type="button" class="close" data-qz-modal-close="qzTrayWizardModal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pt-3">
                <div class="qz-wizard-status mb-3" id="qzWizardStatus">
                    <span class="qz-wizard-status__dot qz-wizard-status__dot--idle" id="qzWizardStatusDot"></span>
                    <span class="qz-wizard-status__text" id="qzWizardStatusText">
                        {{ translate('qz_tray_status_checking') ?: 'Checking connection...' }}
                    </span>
                </div>

                <div class="qz-wizard-steps mb-4">
                    <div class="qz-wizard-step qz-wizard-step--active" data-wizard-step-indicator="1">
                        <span class="qz-wizard-step__num">1</span>
                        <span class="qz-wizard-step__label">{{ translate('qz_tray_step_install') ?: 'Install' }}</span>
                    </div>
                    <div class="qz-wizard-step" data-wizard-step-indicator="2">
                        <span class="qz-wizard-step__num">2</span>
                        <span class="qz-wizard-step__label">{{ translate('qz_tray_step_connect') ?: 'Connect' }}</span>
                    </div>
                    <div class="qz-wizard-step" data-wizard-step-indicator="3">
                        <span class="qz-wizard-step__num">3</span>
                        <span class="qz-wizard-step__label">{{ translate('select_thermal_printer') ?: 'Select printer' }}</span>
                    </div>
                </div>

                <div class="qz-wizard-panel" data-wizard-panel="1">
                    <div class="alert alert-light border mb-3 fs-13">
                        <strong>{{ translate('qz_tray_step_download') ?: 'Download and install QZ Tray' }}</strong>
                        <p class="mb-2 mt-2 text-muted">
                            {{ translate('qz_tray_install_help') ?: 'QZ Tray is free desktop software that lets your browser send receipts to a local thermal printer. Install it once on each computer that prints receipts.' }}
                        </p>
                        <a href="https://qz.io/download/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-download mr-1"></i>{{ translate('download_qz_tray') ?: 'Download QZ Tray' }}
                        </a>
                    </div>
                    <ol class="fs-13 text-muted mb-0 pl-3">
                        <li class="mb-2">{{ translate('qz_tray_step_download') ?: 'Download and install QZ Tray' }}</li>
                        <li class="mb-2">{{ translate('qz_tray_step_start') ?: 'Start QZ Tray (look for the icon in your system tray / menu bar)' }}</li>
                        <li class="mb-2">{{ translate('qz_tray_step_trust') ?: 'When prompted, allow/trust this website in the QZ Tray popup' }}</li>
                        <li>{{ translate('qz_tray_step_plug_printer') ?: 'Connect your thermal printer via USB or network and install its driver in Windows/macOS' }}</li>
                    </ol>
                </div>

                <div class="qz-wizard-panel d-none" data-wizard-panel="2">
                    <p class="fs-13 text-muted mb-3">
                        {{ translate('qz_tray_connect_help') ?: 'Make sure QZ Tray is running (system tray icon), click Connect below, then approve the QZ Tray security popup. Choose Allow and check Remember. If you blocked access earlier, click Connect again — the popup will reappear (blocked sites are not listed in Site Manager until you allow once). The popup may appear behind this window.' }}
                    </p>
                    <p class="fs-12 text-muted mb-3">
                        {{ translate('qz_tray_reset_site_manager') ?: 'If connection still fails, right-click the QZ Tray icon → Advanced → Site Manager, remove this site, then connect again.' }}
                    </p>
                    <button type="button" class="btn btn-primary" id="qzWizardConnectBtn">
                        <i class="fa fa-plug mr-1"></i>{{ translate('connect_qz_tray') ?: 'Connect to QZ Tray' }}
                    </button>
                    <div id="qzWizardConnectError" class="alert alert-warning py-2 px-3 fs-13 mt-3 mb-0 d-none"></div>
                </div>

                <div class="qz-wizard-panel d-none" data-wizard-panel="3">
                    <p class="fs-13 text-muted mb-3">
                        {{ translate('qz_tray_printer_help') ?: 'Choose the thermal printer connected on this computer via QZ Tray.' }}
                    </p>
                    <div id="qzWizardPrinterLoading" class="text-center py-3 d-none">
                        <i class="fa fa-spinner fa-spin text-primary"></i>
                        <span class="ml-2 text-muted fs-13">{{ translate('Loading...') }}</span>
                    </div>
                    <select id="qzWizardPrinterSelect" class="form-control mb-2"></select>
                    <div id="qzWizardPrinterError" class="alert alert-warning py-2 px-3 fs-13 mb-2 d-none"></div>
                    <button type="button" class="btn btn-link btn-sm p-0 fs-13" id="qzWizardRefreshPrintersBtn">
                        <i class="fa fa-refresh mr-1"></i>{{ translate('refresh_printer_list') ?: 'Refresh printer list' }}
                    </button>
                    <p class="fs-12 text-muted mb-0 mt-2" id="qzWizardSavedPrinterHint"></p>
                </div>
            </div>
            <div class="modal-footer flex-wrap border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary mb-2 mr-2" id="qzWizardPreviewBtn">
                    {{ translate('open_print_preview_instead') ?: 'Open Print Preview Instead' }}
                </button>
                <button type="button" class="btn btn-outline-secondary mb-2 mr-2 d-none" id="qzWizardBackBtn">
                    {{ translate('Back') ?: 'Back' }}
                </button>
                <button type="button" class="btn btn-outline-primary mb-2 mr-2 d-none" id="qzWizardNextBtn">
                    {{ translate('next_step') ?: 'Next' }}
                </button>
                <button type="button" class="btn btn-primary mb-2 mr-2 d-none" id="qzWizardPrintBtn" disabled>
                    {{ translate('print_now') ?: 'Print Now' }}
                </button>
                <button type="button" class="btn btn-primary mb-2 d-none" id="qzWizardDoneBtn">
                    {{ translate('done') ?: 'Done' }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade qz-tray-modal" id="qzTrayThermalChoiceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('thermal_print') ?: 'Thermal Print' }}</h5>
                <button type="button" class="close" data-qz-modal-close="qzTrayThermalChoiceModal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-13 mb-3">
                    {{ translate('thermal_print_choice_help') ?: 'Choose how you want to print your receipt on this computer.' }}
                </p>
                <div class="qz-choice-actions">
                    <button type="button" class="btn btn-primary btn-block text-left p-3 mb-2" id="qzChoiceDirectBtn">
                        <strong class="d-block">{{ translate('print_to_thermal_printer') ?: 'Print to thermal printer' }}</strong>
                        <small class="text-white-50">{{ translate('print_to_thermal_printer_help') ?: 'Direct print via QZ Tray (one-time setup)' }}</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-block text-left p-3 mb-2" id="qzChoicePreviewBtn">
                        <strong class="d-block">{{ translate('open_print_preview') ?: 'Open print preview' }}</strong>
                        <small class="text-muted">{{ translate('open_print_preview_help') ?: '80mm receipt in browser — no extra software' }}</small>
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0" id="qzChoiceSetupBtn">
                        {{ translate('setup_thermal_printer') ?: 'Setup thermal printer (QZ Tray)' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .qz-tray-modal {
        z-index: 1060;
    }

    #orderSuccessModal,
    #order_successfully {
        z-index: 1055;
    }

    body.qz-tray-modal-open .modal-backdrop.show:last-of-type {
        z-index: 1055;
    }

    .qz-wizard-status {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
    }

    .qz-wizard-status__dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        background: #adb5bd;
    }

    .qz-wizard-status__dot--idle { background: #adb5bd; }
    .qz-wizard-status__dot--ok { background: #28a745; }
    .qz-wizard-status__dot--warn { background: #ffc107; }
    .qz-wizard-status__dot--error { background: #dc3545; }

    .qz-wizard-status__text {
        font-size: 13px;
        color: #495057;
    }

    .qz-wizard-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-gap: 8px;
    }

    .qz-wizard-step {
        text-align: center;
        padding: 10px 8px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        background: #fff;
        opacity: .65;
    }

    .qz-wizard-step--active {
        border-color: rgba(6, 60, 147, .35);
        background: rgba(6, 60, 147, .05);
        opacity: 1;
    }

    .qz-wizard-step--done {
        border-color: rgba(40, 167, 69, .35);
        background: rgba(40, 167, 69, .05);
        opacity: 1;
    }

    .qz-wizard-step__num {
        display: inline-flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #063c93;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .qz-wizard-step__label {
        display: block;
        font-size: 11px;
        color: #6c757d;
        line-height: 1.2;
    }
</style>
