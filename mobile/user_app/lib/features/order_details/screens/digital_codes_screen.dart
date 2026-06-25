import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/no_internet_screen_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/controllers/order_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/digital_codes_widget.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class DigitalCodesScreen extends StatefulWidget {
  final int orderId;

  const DigitalCodesScreen({super.key, required this.orderId});

  @override
  State<DigitalCodesScreen> createState() => _DigitalCodesScreenState();
}

class _DigitalCodesScreenState extends State<DigitalCodesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<OrderDetailsController>(context, listen: false)
          .fetchDigitalCodes(widget.orderId.toString());
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar:
          CustomAppBar(title: getTranslated('digital_product_codes', context)),
      body: Consumer<OrderDetailsController>(
        builder: (context, orderDetailsController, _) {
          if (orderDetailsController.digitalCodesLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (orderDetailsController.digitalCodes == null ||
              orderDetailsController.digitalCodes!.isEmpty) {
            return NoInternetOrDataScreenWidget(
              isNoInternet: false,
              message: getTranslated('no_digital_codes_available', context) ??
                  'No digital codes available',
            );
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
            child:
                DigitalCodesWidget(codes: orderDetailsController.digitalCodes!),
          );
        },
      ),
    );
  }
}
