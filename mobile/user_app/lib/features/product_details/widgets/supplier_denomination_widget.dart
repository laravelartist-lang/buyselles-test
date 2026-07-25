import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/price_converter.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class SupplierDenominationWidget extends StatelessWidget {
  final ProductDetailsModel product;

  const SupplierDenominationWidget({super.key, required this.product});

  @override
  Widget build(BuildContext context) {
    if (!product.requiresDenominationSelection) {
      return const SizedBox.shrink();
    }

    return Consumer<ProductDetailsController>(
      builder: (context, controller, _) {
        final fixedDenoms = (product.denominations ?? [])
            .where((d) => d.type == 'fixed')
            .toList();
        final variable = product.variableDenomination;

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.homePagePadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (fixedDenoms.isNotEmpty) ...[
                Text(
                  getTranslated('denomination', context) ?? 'Denomination',
                  style: textMedium.copyWith(
                    fontSize: Dimensions.fontSizeLarge,
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: fixedDenoms.map((denom) {
                    final selected = controller.selectedSupplierDenominationId == denom.id;
                    final label = denom.faceValueCurrency != null && denom.faceValueCurrency!.isNotEmpty
                        ? '${denom.faceValueCurrency} ${denom.faceValue.toStringAsFixed(0)}'
                        : PriceConverter.convertPrice(context, denom.sellPrice);

                    return ChoiceChip(
                      label: Text(label),
                      selected: selected,
                      onSelected: (_) => controller.selectSupplierDenomination(denom.id),
                    );
                  }).toList(),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
              ],
              if (variable != null) ...[
                Text(
                  getTranslated('amount', context) ?? 'Amount',
                  style: textMedium.copyWith(
                    fontSize: Dimensions.fontSizeLarge,
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                TextField(
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(
                    hintText: '${variable.minFaceValue} - ${variable.maxFaceValue}',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                    isDense: true,
                  ),
                  onChanged: controller.setSupplierCustomAmount,
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
              ],
            ],
          ),
        );
      },
    );
  }
}
