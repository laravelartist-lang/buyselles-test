import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
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
    final String creditsLabel = widget.config.quantityLabel?.trim().isNotEmpty == true
        ? widget.config.quantityLabel!
        : getTranslated('credits', context) ?? 'Credits';
    final String quantityText = widget.config.minQuantity?.toStringAsFixed(0) ?? '0';
    final String totalPrice = widget.config.formattedLineTotal
        ?? widget.config.lineTotal?.toStringAsFixed(widget.config.decimalPoints ?? 2)
        ?? '0';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.homePagePadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _accountController,
            decoration: InputDecoration(
              labelText: widget.config.accountLabel ?? getTranslated('account', context),
              border: const OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            decoration: BoxDecoration(
              color: Theme.of(context).highlightColor,
              borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(creditsLabel, style: textRegular.copyWith(color: Theme.of(context).hintColor)),
                    Text(quantityText, style: textMedium),
                  ],
                ),
                const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      getTranslated('total_price', context) ?? 'Total Price',
                      style: textRegular.copyWith(color: Theme.of(context).hintColor),
                    ),
                    Text(totalPrice, style: titilliumSemiBold),
                  ],
                ),
                if (widget.config.region?.trim().isNotEmpty == true) ...[
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        getTranslated('region', context) ?? 'Region',
                        style: textRegular.copyWith(color: Theme.of(context).hintColor),
                      ),
                      Text(widget.config.region!, style: textMedium),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
        ],
      ),
    );
  }
}
