import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/controllers/thermal_print_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/domain/models/digital_code_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';
import 'package:flutter_thermal_printer/utils/printer.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';

class PrinterSelectionDialog extends StatefulWidget {
  final List<DigitalCodeModel> codes;

  const PrinterSelectionDialog({super.key, required this.codes});

  @override
  State<PrinterSelectionDialog> createState() => _PrinterSelectionDialogState();
}

class _PrinterSelectionDialogState extends State<PrinterSelectionDialog> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final controller =
          Provider.of<ThermalPrintController>(context, listen: false);
      // Clear any stale in-memory "connected" state before showing the list.
      // Without this check a printer that was turned off between sessions
      // still appears as connected (green / "Print" button).
      controller.refreshConnectionState();
      controller.startScan();
    });
  }

  /// Connect, then immediately print if the connection succeeds.
  Future<void> _connectAndPrint(
    BuildContext context,
    ThermalPrintController controller,
    Printer printer,
  ) async {
    final connected = await controller.connect(printer);

    if (!context.mounted) return;

    if (!connected) {
      showCustomSnackBarWidget(
        getTranslated('failed_to_connect_printer', context) ??
            'Could not connect to printer. Make sure it is turned on and in range.',
        context,
        snackBarType: SnackBarType.error,
      );
      return;
    }

    showCustomSnackBarWidget(
      getTranslated('connected_successfully', context) ?? 'Connected successfully',
      context,
      snackBarType: SnackBarType.success,
    );
  }

  /// Print to the already-connected printer, verifying the hardware link first.
  Future<void> _print(
    BuildContext context,
    ThermalPrintController controller,
  ) async {
    try {
      await controller.printReceipt(widget.codes);
      if (!context.mounted) return;

      showCustomSnackBarWidget(
        getTranslated('receipt_printed_successfully', context) ??
            'Receipt printed successfully',
        context,
        snackBarType: SnackBarType.success,
      );
    } on PrintException catch (e) {
      if (!context.mounted) return;
      showCustomSnackBarWidget(
        e.message,
        context,
        snackBarType: SnackBarType.error,
      );
    } catch (e) {
      if (!context.mounted) return;
      showCustomSnackBarWidget(
        '${getTranslated('failed_to_print', context) ?? 'Failed to print'}: $e',
        context,
        snackBarType: SnackBarType.error,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      ),
      child: Consumer<ThermalPrintController>(
        builder: (context, controller, _) {
          final bool isBusy =
              controller.isConnecting || controller.isPrinting;

          return Container(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Title row ──────────────────────────────────────────────
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      getTranslated('select_printer', context) ??
                          'Select Printer',
                      style: titilliumSemiBold.copyWith(
                        fontSize: Dimensions.fontSizeLarge,
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed:
                          isBusy ? null : () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),

                // ── Paper width ────────────────────────────────────────────
                Row(
                  children: [
                    Text(
                      '${getTranslated('paper_size', context) ?? 'Paper Size'}:',
                      style: titilliumRegular,
                    ),
                    const SizedBox(width: Dimensions.paddingSizeSmall),
                    DropdownButton<int>(
                      value: controller.selectedPaperWidth,
                      items: const [
                        DropdownMenuItem(value: 58, child: Text('58 mm')),
                        DropdownMenuItem(value: 80, child: Text('80 mm')),
                      ],
                      onChanged: isBusy
                          ? null
                          : (value) {
                              if (value != null) {
                                controller.setPaperWidth(value);
                              }
                            },
                    ),
                  ],
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),

                // ── Busy indicator ─────────────────────────────────────────
                if (controller.isScanning || isBusy)
                  Center(
                    child: Column(
                      children: [
                        const Padding(
                          padding: EdgeInsets.all(Dimensions.paddingSizeSmall),
                          child: CircularProgressIndicator(),
                        ),
                        if (controller.isConnecting)
                          Text(
                            getTranslated('connecting', context) ??
                                'Connecting to printer…',
                            style: titilliumRegular.copyWith(
                              fontSize: Dimensions.fontSizeSmall,
                            ),
                          ),
                        if (controller.isPrinting)
                          Text(
                            getTranslated('printing', context) ??
                                'Sending print job…',
                            style: titilliumRegular.copyWith(
                              fontSize: Dimensions.fontSizeSmall,
                            ),
                          ),
                      ],
                    ),
                  ),

                // ── Empty state ────────────────────────────────────────────
                if (controller.printers.isEmpty && !controller.isScanning)
                  Center(
                    child: Padding(
                      padding:
                          const EdgeInsets.all(Dimensions.paddingSizeLarge),
                      child: Text(
                        getTranslated('no_printers_found', context) ??
                            'No printers found',
                      ),
                    ),
                  ),

                // ── Printer list ───────────────────────────────────────────
                if (controller.printers.isNotEmpty)
                  ConstrainedBox(
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.of(context).size.height * 0.4,
                    ),
                    child: ListView.builder(
                      shrinkWrap: true,
                      itemCount: controller.printers.length,
                      itemBuilder: (context, index) {
                        final printer = controller.printers[index];
                        final isConnected =
                            controller.connectedPrinter?.address ==
                                printer.address;

                        return ListTile(
                          leading: Icon(
                            printer.connectionType == ConnectionType.BLE
                                ? Icons.bluetooth
                                : Icons.usb,
                            color: isConnected
                                ? Colors.green
                                : Theme.of(context).primaryColor,
                          ),
                          title: Text(printer.name ?? 'Unknown Printer'),
                          subtitle: Text(printer.address ?? ''),
                          trailing: ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: isConnected
                                  ? Colors.green
                                  : Theme.of(context).primaryColor,
                            ),
                            // Disable buttons while any async op is in flight.
                            onPressed: isBusy
                                ? null
                                : () async {
                                    if (isConnected) {
                                      await _print(context, controller);
                                    } else {
                                      await _connectAndPrint(
                                        context,
                                        controller,
                                        printer,
                                      );
                                    }
                                  },
                            child: Text(
                              isConnected
                                  ? (getTranslated('print', context) ?? 'Print')
                                  : (getTranslated('connect', context) ??
                                      'Connect'),
                              style: const TextStyle(color: Colors.white),
                            ),
                          ),
                        );
                      },
                    ),
                  ),

                const SizedBox(height: Dimensions.paddingSizeSmall),

                // ── Rescan button ──────────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed:
                        isBusy ? null : () => controller.startScan(),
                    child: Text(
                      getTranslated('scan_for_printers', context) ??
                          'Scan for Printers',
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
