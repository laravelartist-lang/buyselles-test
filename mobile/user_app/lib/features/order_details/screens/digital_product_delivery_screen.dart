import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/controllers/order_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/controllers/digital_export_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/screens/thermal_print_preview_screen.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/order_place_bottomsheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:provider/provider.dart';

class DigitalProductDeliveryScreen extends StatefulWidget {
  final int orderId;

  const DigitalProductDeliveryScreen({super.key, required this.orderId});

  @override
  State<DigitalProductDeliveryScreen> createState() => _DigitalProductDeliveryScreenState();
}

class _DigitalProductDeliveryScreenState extends State<DigitalProductDeliveryScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<OrderDetailsController>(context, listen: false)
          .fetchDigitalCodes(widget.orderId.toString());
    });
  }

  void _onExit() {
    bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    
    if (isLoggedIn) {
      RouterHelper.getOrderScreenRoute(isBackButtonExist: true, action: RouteAction.pushReplacement, fromPlaceOrder: true);
    } else {
      RouterHelper.getDashboardRoute(action: RouteAction.pushReplacement, page: 'home');
    }

    Future.delayed(const Duration(milliseconds: 300), () {
      showModalBottomSheet(
        isDismissible: false,
        enableDrag: false,
        context: Get.context!,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (context) {
          return Container(
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: OrderPlaceBottomSheetWidget(
              orderID: widget.orderId.toString(),
              icon: Icons.check,
              title: getTranslated('order_placed', Get.context!),
              description: getTranslated('your_order_placed', Get.context!),
              isFailed: false,
            ),
          );
        },
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _onExit();
      },
      child: Scaffold(
        appBar: CustomAppBar(
          title: getTranslated('digital_delivery_options', context) ?? 'Delivery Options',
          onBackPressed: _onExit,
        ),
        body: Consumer2<OrderDetailsController, DigitalExportController>(
          builder: (context, orderDetailsController, digitalExportController, _) {
            if (orderDetailsController.digitalCodesLoading) {
              return const Center(child: CircularProgressIndicator());
            }

          final codes = orderDetailsController.digitalCodes;
          if (codes == null || codes.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.check_circle, color: Theme.of(context).primaryColor, size: 64),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                    Text(
                      getTranslated('direct_topup_order_completed', context) ??
                          getTranslated('order_placed', context) ??
                          'Order placed',
                      textAlign: TextAlign.center,
                      style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeSmall),
                    Text(
                      getTranslated('your_order_placed', context) ?? 'Your order has been placed successfully.',
                      textAlign: TextAlign.center,
                      style: titilliumRegular.copyWith(color: Theme.of(context).hintColor),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeLarge),
                    ElevatedButton(
                      onPressed: _onExit,
                      child: Text(getTranslated('continue', context) ?? 'Continue'),
                    ),
                  ],
                ),
              ),
            );
          }

          return Stack(
            children: [
              SingleChildScrollView(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      getTranslated('select_delivery_method', context) ?? 'How would you like to receive your digital products?',
                      style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeLarge),

                    // 1. Print (A4 PDF)
                    _OptionCard(
                      icon: Icons.picture_as_pdf,
                      title: getTranslated('print_receipt_a4', context) ?? 'Print (A4 PDF)',
                      subtitle: getTranslated('generate_a4_pdf_receipt', context) ?? 'Generate A4 PDF receipt',
                      onTap: () => digitalExportController.printCodes(context, codes),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    // Thermal Print
                    _OptionCard(
                      icon: Icons.print_rounded,
                      title: getTranslated('thermal_print', context) ?? 'Thermal Print',
                      subtitle: getTranslated('thermal_print_subtitle', context) ?? 'Print directly to thermal printer',
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => ThermalPrintPreviewScreen(codes: codes),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    // 2. View Code on Screen
                    _OptionCard(
                      icon: Icons.visibility,
                      title: getTranslated('view_code_on_screen', context) ?? 'View Code on Screen',
                      subtitle: getTranslated('view_code_immediately', context) ?? 'Immediate display',
                      onTap: () => RouterHelper.getDigitalCodesScreenRoute(orderId: widget.orderId, action: RouteAction.push),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    // 3. Share via Social Media
                    _OptionCard(
                      icon: Icons.share,
                      title: getTranslated('send_via_social_media', context) ?? 'Send via Social Media',
                      subtitle: getTranslated('whatsapp_telegram_etc', context) ?? 'WhatsApp, Telegram, etc.',
                      onTap: () => digitalExportController.shareCodes(context, codes),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    // 4. Download as Excel
                    _OptionCard(
                      icon: Icons.table_chart,
                      title: getTranslated('download_as_excel', context) ?? 'Download as Excel',
                      subtitle: '.xlsx file format',
                      onTap: () => digitalExportController.exportToExcel(context, codes),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    // 5. Download as Word
                    _OptionCard(
                      icon: Icons.description,
                      title: getTranslated('download_as_word', context) ?? 'Download as Word',
                      subtitle: '.docx file format',
                      onTap: () => digitalExportController.exportToWord(context, codes),
                    ),
                  ],
                ),
              ),
              if (digitalExportController.isExporting)
                Container(
                  color: Colors.black.withValues(alpha: 0.3),
                  child: const Center(child: CircularProgressIndicator()),
                ),
            ],
          );
        },
      ),
    ),
  );
}
}

class _OptionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _OptionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      child: Container(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
          boxShadow: [
            BoxShadow(
              color: Colors.grey.withValues(alpha: 0.1),
              spreadRadius: 1,
              blurRadius: 5,
              offset: const Offset(0, 1),
            )
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: Theme.of(context).primaryColor, size: 30),
            ),
            const SizedBox(width: Dimensions.paddingSizeDefault),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                  Text(subtitle, style: titilliumRegular.copyWith(color: Theme.of(context).hintColor)),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios, color: Theme.of(context).hintColor, size: 16),
          ],
        ),
      ),
    );
  }
}
