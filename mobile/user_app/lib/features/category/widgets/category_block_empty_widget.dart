import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_asset_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';

class CategoryBlockEmptyWidget extends StatelessWidget {
  final String messageKey;
  final String? messageText;
  final String? icon;

  const CategoryBlockEmptyWidget({
    super.key,
    this.messageKey = 'no_product_found',
    this.messageText,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeSmall,
      ),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
          vertical: Dimensions.paddingSizeLarge,
        ),
        decoration: BoxDecoration(
          color: Theme.of(context).hintColor.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
        ),
        child: Column(
          children: [
            CustomAssetImageWidget(
              icon ?? Images.noProduct,
              height: 56,
              width: 56,
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              messageText ?? getTranslated(messageKey, context) ?? messageKey,
              textAlign: TextAlign.center,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: Theme.of(context).hintColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
