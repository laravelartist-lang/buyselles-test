import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class CategoryDrillDownWidget extends StatelessWidget {
  final String name;
  final String? image;
  final VoidCallback onTap;
  const CategoryDrillDownWidget({super.key, required this.name, this.image, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          border: Border.all(color: Theme.of(context).primaryColor.withValues(alpha:.10), width: 1),
          color: Theme.of(context).highlightColor,
          boxShadow: [BoxShadow(
            color: Theme.of(context).textTheme.bodyLarge!.color!.withValues(alpha:0.06),
            spreadRadius: 0,
            blurRadius: 8,
            offset: const Offset(0, 2),
          )],
        ),
        child: Column(
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                  border: Border.all(color: Theme.of(context).textTheme.bodyLarge!.color!.withValues(alpha: 0.05), width: 1),
                  color: Theme.of(context).highlightColor,
                ),
                margin: const EdgeInsets.all(Dimensions.paddingSizeSmall).copyWith(bottom: 0),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                  child: CustomImageWidget(
                    image: image ?? '',
                    fit: BoxFit.cover,
                    width: double.infinity,
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: Dimensions.paddingSizeExtraSmall),
              child: Text(
                name,
                style: textMedium.copyWith(
                  fontSize: Dimensions.fontSizeDefault,
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
