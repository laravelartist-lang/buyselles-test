import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/controllers/localization_controller.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:provider/provider.dart';

class CategoryWidget extends StatelessWidget {
  final CategoryModel category;
  final int index;
  final int length;
  const CategoryWidget({super.key, required this.category, required this.index, required this.length});

  @override
  Widget build(BuildContext context) {
    int homeLength = length >= 10 ? 10 : length;
    return Padding(
      padding: EdgeInsets.only(
        left: Provider.of<LocalizationController>(context, listen: false).isLtr
            ? index == 0 ? Dimensions.homePagePadding : Dimensions.paddingSizeTwelve
            : 0,
        right: index + 1 == homeLength ? Dimensions.paddingSizeSmall
            : Provider.of<LocalizationController>(context, listen: false).isLtr
                ? 0 : Dimensions.homePagePadding,
      ),
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeExtraSmall),
        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.08)),
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          color: Theme.of(context).highlightColor,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              height: 50,
              width: 50,
              decoration: BoxDecoration(
                borderRadius: const BorderRadius.all(Radius.circular(100)),
                border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.08)),
              ),
              child: ClipRRect(
                borderRadius: const BorderRadius.all(Radius.circular(100)),
                child: CustomImageWidget(image: '${category.imageFullUrl?.path}'),
              ),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            SizedBox(
              width: 80,
              child: Text(
                category.name ?? '',
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

