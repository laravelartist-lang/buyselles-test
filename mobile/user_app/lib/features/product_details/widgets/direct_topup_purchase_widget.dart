import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class DirectTopUpPurchaseWidget extends StatefulWidget {
  final DirectTopUpConfig config;

  const DirectTopUpPurchaseWidget({super.key, required this.config});

  @override
  State<DirectTopUpPurchaseWidget> createState() => _DirectTopUpPurchaseWidgetState();
}

class _DirectTopUpPurchaseWidgetState extends State<DirectTopUpPurchaseWidget> {
  final TextEditingController _accountController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final controller = Provider.of<ProductDetailsController>(context, listen: false);
    controller.initializeDirectTopUpDefaults();
    _accountController.addListener(() {
      controller.setDirectTopUpAccountId(_accountController.text);
    });
  }

  @override
  void dispose() {
    _accountController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.homePagePadding),
      child: TextField(
        controller: _accountController,
        decoration: InputDecoration(
          labelText: widget.config.accountLabel ?? getTranslated('account', context),
          border: const OutlineInputBorder(),
        ),
      ),
    );
  }
}
