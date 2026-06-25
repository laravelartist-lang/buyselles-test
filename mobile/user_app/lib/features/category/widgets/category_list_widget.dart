import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/title_row_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_tile.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';

import 'category_shimmer_widget.dart';

class CategoryListWidget extends StatelessWidget {
  final bool isHomePage;
  const CategoryListWidget({super.key, required this.isHomePage});

  @override
  Widget build(BuildContext context) {
    return Consumer<CategoryController>(
      builder: (context, categoryProvider, child) {
        return Column(children: [

          if (isHomePage) ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraExtraSmall),
              child: TitleRowWidget(
                title: getTranslated('CATEGORY', context),
                onTap: () {
                  if (categoryProvider.categoryList.isNotEmpty) {
                    RouterHelper.getCategoryScreenRoute(action: RouteAction.push);
                  }
                },
              ),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
          ],

          categoryProvider.categoryList.isNotEmpty
              ? GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
                  itemCount: categoryProvider.categoryList.length > 10 ? 10 : categoryProvider.categoryList.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 4,
                    childAspectRatio: 0.65,
                    mainAxisSpacing: Dimensions.paddingSizeExtraSmall,
                    crossAxisSpacing: Dimensions.paddingSizeExtraSmall,
                  ),
                  itemBuilder: (BuildContext context, int index) {
                    return InkWell(
                      splashColor: Colors.transparent,
                      highlightColor: Colors.transparent,
                      onTap: () {
                        RouterHelper.getDynamicCategoryRoute(
                          categoryModel: categoryProvider.categoryList[index],
                          action: RouteAction.push,
                        );
                      },
                      child: CategoryBlockTile.fromCategoryModel(
                        categoryProvider.categoryList[index],
                      ),
                    );
                  },
                )
              : const CategoryShimmerWidget(),
        ]);
      },
    );
  }
}

