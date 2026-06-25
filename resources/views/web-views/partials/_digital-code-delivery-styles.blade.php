@once('digital-code-delivery-styles')
<style>
    .digital-code-delivery-actions__grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .digital-code-delivery-option {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        text-align: start;
        color: #212529;
        transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
    }

    .digital-code-delivery-option--thermal {
        padding: 0;
        border: 0;
        background: transparent;
        display: block;
        gap: 0;
    }

    .digital-code-delivery-option--thermal:hover {
        border-color: transparent;
        box-shadow: none;
        transform: none;
    }

    .digital-code-thermal-card {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
        transition: box-shadow .2s ease, border-color .2s ease;
    }

    .digital-code-thermal-card:hover {
        border-color: rgba(6, 60, 147, .25);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
    }

    .digital-code-thermal-card .digital-code-delivery-option__main {
        padding: 12px 14px;
        border-radius: 0;
    }

    .digital-code-thermal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        border-top: 1px solid #eef1f4;
        background: #f8f9fa;
    }

    .digital-code-bluetooth-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        max-width: 100%;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.3;
        color: #495057;
        background: #e9ecef;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .digital-code-bluetooth-status-pill i {
        flex-shrink: 0;
        font-size: 12px;
    }

    .digital-code-bluetooth-status-pill--compact {
        margin-top: 6px;
        max-width: 220px;
    }

    .digital-code-bluetooth-status-pill--ready {
        background: rgba(40, 167, 69, .12);
        color: #1e7e34;
    }

    .digital-code-bluetooth-status-pill--demo {
        background: rgba(23, 162, 184, .12);
        color: #117a8b;
    }

    .digital-code-bluetooth-status-pill--setup {
        background: #e9ecef;
        color: #6c757d;
    }

    .digital-code-bluetooth-status-pill--warn {
        background: rgba(255, 193, 7, .18);
        color: #856404;
    }

    .digital-code-thermal-toolbar__configure {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        padding: 5px 12px;
        border: 1px solid rgba(6, 60, 147, .25);
        border-radius: 999px;
        background: #fff;
        color: #063c93;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
    }

    .digital-code-thermal-toolbar__configure:hover {
        background: rgba(6, 60, 147, .06);
        border-color: rgba(6, 60, 147, .4);
    }

    .digital-code-thermal-toolbar__configure i {
        font-size: 13px;
    }

    .digital-code-thermal-compact {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .digital-code-thermal-compact__actions .btn {
        min-height: 32px;
    }

    .digital-code-delivery-actions--modal .digital-code-thermal-card {
        border-radius: 8px;
    }

    .digital-code-delivery-actions--modal .digital-code-thermal-toolbar {
        padding: 6px 10px;
    }

    .digital-code-delivery-actions--modal .digital-code-thermal-toolbar__configure span {
        display: none;
    }

    .digital-code-delivery-actions--modal .digital-code-thermal-toolbar__configure {
        padding: 5px 10px;
    }

    @media (max-width: 575px) {
        .digital-code-thermal-toolbar__configure span {
            display: none;
        }

        .digital-code-thermal-toolbar__configure {
            padding: 5px 10px;
        }
    }

    /* Thermal printer setup modal */
    #bluetoothThermalSetupModal,
    #bluetoothThermalTestResultModal {
        z-index: 1060;
    }

    .thermal-setup-notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 12px;
    }

    .thermal-setup-notice--info {
        background: rgba(23, 162, 184, .1);
        color: #0c5460;
        border: 1px solid rgba(23, 162, 184, .2);
    }

    .thermal-setup-notice--warn {
        background: rgba(255, 193, 7, .12);
        color: #856404;
        border: 1px solid rgba(255, 193, 7, .25);
    }

    .thermal-setup-section {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .thermal-setup-section--secondary {
        background: #fafbfc;
    }

    .thermal-setup-section__header {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px 0;
    }

    .thermal-setup-section__icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(6, 60, 147, .1);
        color: #063c93;
        flex-shrink: 0;
    }

    .thermal-setup-section__icon--qz {
        background: rgba(108, 117, 125, .12);
        color: #495057;
    }

    .thermal-setup-section__title {
        display: block;
        font-size: 14px;
        color: #212529;
    }

    .thermal-setup-section__desc {
        font-size: 12px;
        color: #6c757d;
        margin-top: 2px;
    }

    .thermal-setup-section__body {
        padding: 12px 16px 16px;
    }

    .thermal-setup-status {
        font-size: 12px;
        line-height: 1.45;
    }

    .thermal-setup-saved {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 10px 12px;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #eef1f4;
    }

    .thermal-setup-saved__label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6c757d;
    }

    .thermal-setup-saved__value {
        font-size: 13px;
        font-weight: 600;
        color: #212529;
        word-break: break-word;
    }

    .thermal-setup-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .thermal-setup-actions__primary {
        font-size: 14px;
        font-weight: 600;
        padding: 10px 16px;
    }

    .thermal-setup-actions__clear {
        align-self: center;
        padding: 0;
        font-size: 12px;
        text-decoration: none;
    }

    .thermal-setup-actions__clear:hover {
        text-decoration: underline;
    }

    .thermal-setup-preview {
        max-height: 280px;
        overflow: auto;
        white-space: pre-wrap;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 12px;
        font-size: 12px;
        margin-bottom: 0;
    }

    .digital-code-delivery-option__main {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 6px 8px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        text-align: start;
        cursor: pointer;
        color: inherit;
    }

    .digital-code-delivery-option__main:hover {
        background: rgba(6, 60, 147, .03);
    }

    .digital-code-delivery-option:not(.digital-code-delivery-option--thermal) {
        cursor: pointer;
    }

    .digital-code-delivery-option:not(.digital-code-delivery-option--thermal):hover,
    button.digital-code-delivery-option:hover {
        border-color: rgba(6, 60, 147, .25);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
        transform: translateY(-1px);
    }

    button.digital-code-delivery-option {
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
    }

    .digital-code-delivery-option__icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(6, 60, 147, .08);
        color: #063c93;
        font-size: 18px;
        flex-shrink: 0;
    }

    .digital-code-delivery-option__content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .digital-code-delivery-option__title {
        font-size: 15px;
        font-weight: 700;
        color: #212529;
        line-height: 1.3;
    }

    .digital-code-delivery-option__subtitle {
        font-size: 12px;
        color: #6c757d;
        line-height: 1.35;
    }

    .digital-code-delivery-option__arrow {
        color: #adb5bd;
        font-size: 12px;
        flex-shrink: 0;
    }

    .digital-code-delivery-actions--compact .btn i {
        margin-right: 5px;
    }

    .digital-code-action-qz-setup {
        white-space: nowrap;
        flex-shrink: 0;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-actions__heading {
        text-align: center;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-actions__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option--thermal {
        grid-column: 1 / -1;
        display: block;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option {
        padding: 10px 12px;
        gap: 10px;
        min-height: 100%;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option__icon {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option__title {
        font-size: 14px;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option__subtitle {
        font-size: 11px;
    }

    .digital-code-delivery-actions--modal .digital-code-delivery-option__arrow {
        display: none;
    }

    @media (max-width: 575px) {
        .digital-code-delivery-actions--modal .digital-code-delivery-actions__grid {
            grid-template-columns: 1fr;
        }
    }

    /* Theme resets `button { border-radius: 0 }` — re-apply inside modals */
    #orderSuccessModal .digital-code-delivery-option,
    #order_successfully .digital-code-delivery-option,
    .modal.show .digital-code-delivery-option {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
    }

    #orderSuccessModal button.digital-code-delivery-option,
    #order_successfully button.digital-code-delivery-option,
    .modal.show button.digital-code-delivery-option {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        color: #212529;
    }

    .digital-code-view-highlight {
        animation: digitalCodeViewPulse 1.8s ease;
    }

    @keyframes digitalCodeViewPulse {
        0%, 100% {
            box-shadow: none;
        }

        50% {
            box-shadow: 0 0 0 3px rgba(6, 60, 147, .28);
        }
    }

    #orderSuccessModal,
    #order_successfully {
        z-index: 1055;
    }
</style>
@endonce
