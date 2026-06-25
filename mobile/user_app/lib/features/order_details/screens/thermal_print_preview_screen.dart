import 'package:dotted_border/dotted_border.dart';
import 'package:dotted_line/dotted_line.dart';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/domain/models/digital_code_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/printer_selection_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:intl/intl.dart';

class ThermalPrintPreviewScreen extends StatelessWidget {
  final List<DigitalCodeModel> codes;

  const ThermalPrintPreviewScreen({super.key, required this.codes});

  @override
  Widget build(BuildContext context) {
    final String formattedDate = DateFormat('EEE, MMM d, yyyy • h:mm:ss a').format(DateTime.now());

    return Scaffold(
      appBar: CustomAppBar(
        title: getTranslated('thermal_print_preview', context) ?? 'Print Preview',
        showResetIcon: true,
        reset: Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
          child: TextButton.icon(
            style: TextButton.styleFrom(
              foregroundColor: Theme.of(context).primaryColor,
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
            ),
            onPressed: () {
              showDialog(
                context: context,
                builder: (context) => PrinterSelectionDialog(codes: codes),
              );
            },
            icon: const Icon(Icons.bluetooth_searching_rounded, size: 18),
            label: Text(
              getTranslated('scan_printers', context) ?? 'Scan Printers',
              style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
            color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
            width: double.infinity,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.info_outline, size: 16),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                Flexible(
                  child: Text(
                    getTranslated('preview_thermal_receipt_help', context) ??
                        'Previewing thermal print format. Tap Scan Printers to connect.',
                    style: titilliumRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                    textAlign: TextAlign.center,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
              child: Center(
                child: PhysicalShape(
                  color: Colors.white,
                  elevation: 6,
                  shadowColor: Colors.black.withValues(alpha: 0.3),
                  clipper: const _ReceiptClipper(),
                  child: Container(
                    constraints: const BoxConstraints(maxWidth: 350),
                    padding: const EdgeInsets.only(
                      left: Dimensions.paddingSizeLarge,
                      right: Dimensions.paddingSizeLarge,
                      top: Dimensions.paddingSizeExtraLarge,
                      bottom: 40.0, // generous bottom padding to cleanly house the sawtooth edge
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Top Brand Logo
                        const Text(
                          "BUYSELLES",
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: 'Courier',
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 2.0,
                            color: Colors.black,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          formattedDate,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontFamily: 'Courier',
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Colors.black87,
                          ),
                        ),
                        const SizedBox(height: Dimensions.paddingSizeLarge),

                        // Render each digital code mapped beautifully to the screenshot design
                        for (int i = 0; i < codes.length; i++) ...[
                          if (i > 0) ...[
                            const SizedBox(height: Dimensions.paddingSizeDefault),
                            const DottedLine(dashColor: Colors.black38),
                            const SizedBox(height: Dimensions.paddingSizeDefault),
                          ],

                          // Dashed Border Token Code Box
                          Stack(
                            clipBehavior: Clip.none,
                            alignment: Alignment.topCenter,
                            children: [
                              Padding(
                                padding: const EdgeInsets.only(top: 10),
                                child: DottedBorder(
                                  options: const RoundedRectDottedBorderOptions(
                                    color: Colors.black87,
                                    strokeWidth: 1.5,
                                    dashPattern: [6, 4],
                                    radius: Radius.circular(8),
                                  ),
                                  child: Container(
                                    width: double.infinity,
                                    padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 12),
                                    child: Text(
                                      codes[i].code,
                                      style: const TextStyle(
                                        fontFamily: 'Courier',
                                        fontSize: 17,
                                        fontWeight: FontWeight.bold,
                                        letterSpacing: 1.5,
                                        color: Colors.black,
                                      ),
                                      textAlign: TextAlign.center,
                                    ),
                                  ),
                                ),
                              ),
                              Positioned(
                                top: 2,
                                child: Container(
                                  color: Colors.white,
                                  padding: const EdgeInsets.symmetric(horizontal: 8),
                                  child: Text(
                                    getTranslated('code', context) ?? "Token",
                                    style: const TextStyle(
                                      fontFamily: 'Courier',
                                      fontSize: 13,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.black,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: Dimensions.paddingSizeLarge),

                          // Key Value Table
                          _ReceiptRow(
                            label: getTranslated('token_type', context) ?? "Token Type",
                            value: getTranslated('digital', context) ?? "Digital",
                          ),
                          const SizedBox(height: Dimensions.paddingSizeSmall),
                          const DottedLine(dashColor: Colors.black26),
                          const SizedBox(height: Dimensions.paddingSizeSmall),

                          _ReceiptRow(
                            label: getTranslated('product', context) ?? "Product Name",
                            value: codes[i].productName,
                          ),
                          if (codes[i].pin != null && codes[i].pin!.isNotEmpty) ...[
                            _ReceiptRow(
                              label: getTranslated('pin', context) ?? "PIN",
                              value: codes[i].pin!,
                            ),
                          ],
                          if (codes[i].expiry != null && codes[i].expiry!.isNotEmpty) ...[
                            _ReceiptRow(
                              label: getTranslated('exp', context) ?? "Expiry",
                              value: codes[i].expiry!,
                            ),
                          ],
                        ],

                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        const DottedLine(dashColor: Colors.black38),
                        const SizedBox(height: Dimensions.paddingSizeDefault),

                        // Operator & Footer Branding
                        _ReceiptRow(
                          label: getTranslated('operator', context) ?? "Operator",
                          value: "System",
                        ),
                        const SizedBox(height: Dimensions.paddingSizeExtraLarge),

                        const Text(
                          "BUYSELLES",
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: 'Courier',
                            fontSize: 32,
                            fontWeight: FontWeight.w900,
                            fontStyle: FontStyle.italic,
                            letterSpacing: 3.0,
                            color: Colors.black,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).primaryColor,
              padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeDefault),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall)),
            ),
            onPressed: () {
              showDialog(
                context: context,
                builder: (context) => PrinterSelectionDialog(codes: codes),
              );
            },
            icon: const Icon(Icons.print_rounded, color: Colors.white),
            label: Text(
              getTranslated('scan_and_print', context) ?? 'Scan & Print',
              style: titilliumSemiBold.copyWith(color: Colors.white, fontSize: Dimensions.fontSizeLarge),
            ),
          ),
        ),
      ),
    );
  }
}

class _ReceiptRow extends StatelessWidget {
  final String label;
  final String value;

  const _ReceiptRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(
              label,
              style: const TextStyle(
                fontFamily: 'Courier',
                fontSize: 13,
                color: Colors.black54,
              ),
            ),
          ),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          Expanded(
            flex: 3,
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                fontFamily: 'Courier',
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: Colors.black87,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReceiptClipper extends CustomClipper<Path> {
  const _ReceiptClipper();

  @override
  Path getClip(Size size) {
    final path = Path();
    path.lineTo(0, size.height - 12);

    const toothWidth = 12.0;
    const toothHeight = 10.0;
    final int toothCount = (size.width / toothWidth).ceil();

    for (int i = 0; i < toothCount; i++) {
      final double x1 = i * toothWidth + (toothWidth / 2);
      final double y1 = size.height;
      final double x2 = (i + 1) * toothWidth;
      final double y2 = size.height - toothHeight;

      path.lineTo(x1.clamp(0.0, size.width), y1);
      path.lineTo(x2.clamp(0.0, size.width), y2);
    }

    path.lineTo(size.width, 0);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}
