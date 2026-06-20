<div class="modal fade" id="qzTrayPrinterModal" tabindex="-1" aria-labelledby="qzTrayPrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qzTrayPrinterModalLabel">
                    {{ translate('select_thermal_printer') ?: 'Select Thermal Printer' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-13 mb-3">
                    {{ translate('qz_tray_printer_help') ?: 'Choose the thermal printer connected on this computer via QZ Tray.' }}
                </p>
                <div id="qzTrayPrinterLoading" class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 text-muted fs-13">{{ translate('Loading...') }}</span>
                </div>
                <select id="qzTrayPrinterSelect" class="form-select" style="display:none;"></select>
                <div id="qzTrayPrinterError" class="alert alert-warning py-2 px-3 fs-13 mb-0" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
                <button type="button" class="btn btn-primary" id="qzTrayPrinterConfirmBtn" disabled>
                    {{ translate('print_now') ?: 'Print Now' }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qzTraySetupModal" tabindex="-1" aria-labelledby="qzTraySetupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qzTraySetupModalLabel">
                    {{ translate('qz_tray_required') ?: 'QZ Tray Required' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    {{ translate('qz_tray_not_connected') ?: 'QZ Tray is not running on this computer. Install and start QZ Tray to print directly to your thermal printer.' }}
                </p>
                <ol class="fs-13 text-muted mb-3 ps-3">
                    <li>{{ translate('qz_tray_step_download') ?: 'Download and install QZ Tray' }}</li>
                    <li>{{ translate('qz_tray_step_start') ?: 'Start QZ Tray (icon in system tray)' }}</li>
                    <li>{{ translate('qz_tray_step_retry') ?: 'Return here and click Thermal Print again' }}</li>
                </ol>
                <a href="https://qz.io/download/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                    {{ translate('download_qz_tray') ?: 'Download QZ Tray' }}
                </a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="qzTrayPreviewFallbackBtn">
                    {{ translate('open_print_preview_instead') ?: 'Open Print Preview Instead' }}
                </button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    {{ translate('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>
