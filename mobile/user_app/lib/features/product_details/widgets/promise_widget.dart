import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';

class PromiseWidget extends StatelessWidget {
  final bool isDigital;
  const PromiseWidget({super.key, this.isDigital = false});

  @override
  Widget build(BuildContext context) {
    return isDigital ? _buildDigitalPromise(context) : _buildPhysicalPromise(context);
  }

  Widget _buildPhysicalPromise(BuildContext context) {
    const double width = 30;
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(width: Dimensions.paddingSizeDefault),
      Expanded(child: Column(children: [
        SizedBox(width: width, child: Image.asset(Images.safePayment)),
        Padding(
          padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
          child: Text(
            getTranslated('safe_payment', context)!,
            maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).textTheme.bodyLarge?.color),
          ),
        ),
      ])),
      const SizedBox(width: Dimensions.paddingSizeDefault),
      Expanded(child: Column(children: [
        SizedBox(width: width, child: Image.asset(Images.hundredParAuthentic)),
        Padding(
          padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
          child: Text(
            getTranslated('authentic_product', context)!,
            maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).textTheme.bodyLarge?.color),
          ),
        ),
      ])),
      const SizedBox(width: Dimensions.paddingSizeDefault),
    ]);
  }

  Widget _buildDigitalPromise(BuildContext context) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _digitalItem(
        context,
        Icons.bolt,
        getTranslated('instant_digital_delivery', context) ?? 'Instant Digital\nDelivery',
        Theme.of(context).primaryColor,
      ),
      const SizedBox(width: Dimensions.paddingSizeDefault),
      _digitalItem(
        context,
        Icons.lock_outline,
        getTranslated('secure_encrypted_code', context) ?? 'Secure &\nEncrypted Code',
        Theme.of(context).primaryColor,
      ),
      const SizedBox(width: Dimensions.paddingSizeDefault),
      _digitalItem(
        context,
        Icons.info_outline,
        getTranslated('non_returnable_digital', context) ?? 'Non-\nReturnable',
        Colors.orange,
      ),
    ]);
  }

  Widget _digitalItem(BuildContext context, IconData icon, String label, Color iconColor) {
    return Expanded(child: Column(children: [
      SizedBox(
        width: 30, height: 30,
        child: Icon(icon, size: 24, color: iconColor),
      ),
      Padding(
        padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
        child: Text(
          label,
          maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center,
          style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).textTheme.bodyLarge?.color),
        ),
      ),
    ]));
  }
}

