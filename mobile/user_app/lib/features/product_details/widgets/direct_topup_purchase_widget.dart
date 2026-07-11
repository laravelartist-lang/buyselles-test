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
  final TextEditingController _quantityController = TextEditingController();
  final TextEditingController _priceController = TextEditingController();
  bool _byQuantity = true;

  @override
  void initState() {
    super.initState();
    final controller = Provider.of<ProductDetailsController>(context, listen: false);
    controller.initializeDirectTopUpDefaults();
    _quantityController.text = '${widget.config.minQuantity ?? controller.directTopUpQuantity}';
    _priceController.text = controller.directTopUpTotalPrice.toStringAsFixed(2);
    _accountController.addListener(() {
      controller.setDirectTopUpAccountId(_accountController.text);
    });
  }

  @override
  void dispose() {
    _accountController.dispose();
    _quantityController.dispose();
    _priceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = context.watch<ProductDetailsController>();

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
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => setState(() => _byQuantity = true),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: _byQuantity ? Theme.of(context).primaryColor.withValues(alpha: 0.1) : null,
                  ),
                  child: Text(getTranslated('direct_topup_by_quantity', context) ?? 'By Quantity'),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => setState(() => _byQuantity = false),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: !_byQuantity ? Theme.of(context).primaryColor.withValues(alpha: 0.1) : null,
                  ),
                  child: Text(getTranslated('direct_topup_by_price', context) ?? 'By Price'),
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          if (_byQuantity) ...[
            TextField(
              controller: _quantityController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: getTranslated('quantity', context),
                border: const OutlineInputBorder(),
              ),
              onChanged: (value) {
                final qty = double.tryParse(value) ?? widget.config.minQuantity ?? 0;
                controller.setDirectTopUpQuantity(qty);
                _priceController.text = controller.directTopUpTotalPrice.toStringAsFixed(2);
              },
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Text('${getTranslated('direct_topup_total_price', context) ?? 'Total'}: ${controller.directTopUpTotalPrice.toStringAsFixed(2)}'),
          ] else ...[
            TextField(
              controller: _priceController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: getTranslated('amount', context),
                border: const OutlineInputBorder(),
              ),
              onChanged: (value) {
                final price = double.tryParse(value) ?? 0;
                controller.setDirectTopUpQuantityFromPrice(price);
                _quantityController.text = controller.directTopUpQuantity.toStringAsFixed(0);
              },
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Text('${getTranslated('direct_topup_calculated_quantity', context) ?? 'Quantity'}: ${controller.directTopUpQuantity.toStringAsFixed(0)}'),
          ],
        ],
      ),
    );
  }
}
