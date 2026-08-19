import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/controllers/product_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/enums/product_type.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/theme/controllers/theme_controller.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:provider/provider.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_tile.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';

class ViewAllProductScreen extends StatefulWidget {
  final ProductType productType;
  const ViewAllProductScreen({super.key, required this.productType});

  @override
  State<ViewAllProductScreen> createState() => _ViewAllProductScreenState();
}

class _ViewAllProductScreenState extends State<ViewAllProductScreen> {
  @override
  void initState() {
    super.initState();

    // Still fetch in background if needed, but we will primarily show categories
    Provider.of<ProductController>(context, listen: false).getAllProductModelByType(
      offset: 1, type: widget.productType, isUpdate: false,
    );

  }


  @override
  Widget build(BuildContext context) {
    final bool isDarkTheme = Provider.of<ThemeController>(context).darkTheme;

    return Scaffold(
      backgroundColor: isDarkTheme ? Theme.of(context).scaffoldBackgroundColor : null,
      resizeToAvoidBottomInset: false,
      appBar: CustomAppBar(title: getTranslated(_getTitle(widget.productType), context)),

      body: Consumer<CategoryController>(
        builder: (context, categoryController, child) {
          if (categoryController.categoryList.isNotEmpty) {
            return GridView.builder(
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              itemCount: categoryController.categoryList.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 4,
                childAspectRatio: 0.65,
                mainAxisSpacing: Dimensions.paddingSizeExtraSmall,
                crossAxisSpacing: Dimensions.paddingSizeExtraSmall,
              ),
              itemBuilder: (context, index) {
                var category = categoryController.categoryList[index];
                return InkWell(
                  onTap: () {
                    RouterHelper.getDynamicCategoryRoute(
                      categoryModel: category,
                      action: RouteAction.push,
                    );
                  },
                  child: CategoryBlockTile(
                    name: category.name,
                    image: category.imageFullUrl?.path,
                    productCount: category.totalProductCount,
                  ),
                );
              },
            );
          } else {
            return const Center(child: CircularProgressIndicator());
          }
        },
      ),
    );
  }


  String _getTitle(ProductType productType) {
    switch (productType) {

      case ProductType.featuredProduct:
        return 'featured_product';

      case ProductType.justForYou:
        return 'just_for_you';

      default: return 'latest_product';
    }
  }
}
