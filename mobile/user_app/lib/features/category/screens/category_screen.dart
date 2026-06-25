import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class CategoryScreen extends StatefulWidget {
  final int? initialCategoryId;
  final String? initialCategoryName;

  const CategoryScreen({super.key, this.initialCategoryId, this.initialCategoryName});

  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen> {
  bool _handledInitialCategory = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialCategoryId != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _openInitialCategoryIfNeeded();
      });
    }
  }

  void _openCategory(CategoryModel category) {
    RouterHelper.getDynamicCategoryRoute(
      categoryModel: category,
      action: RouteAction.push,
    );
  }

  void _openInitialCategoryIfNeeded() {
    if (_handledInitialCategory || widget.initialCategoryId == null) {
      return;
    }

    final categoryProvider = Provider.of<CategoryController>(context, listen: false);
    if (categoryProvider.categoryList.isEmpty) {
      return;
    }

    _handledInitialCategory = true;

    CategoryModel category;
    try {
      category = categoryProvider.categoryList.firstWhere(
        (item) => item.id == widget.initialCategoryId,
      );
    } catch (_) {
      category = CategoryModel(
        id: widget.initialCategoryId,
        name: widget.initialCategoryName,
      );
    }

    RouterHelper.getDynamicCategoryRoute(
      categoryModel: category,
      action: RouteAction.pushReplacement,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(title: getTranslated('CATEGORY', context)),
      body: Consumer<CategoryController>(
        builder: (context, categoryProvider, child) {
          if (widget.initialCategoryId != null && !_handledInitialCategory) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _openInitialCategoryIfNeeded();
            });
          }

          if (categoryProvider.categoryList.isEmpty) {
            return Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(Theme.of(context).primaryColor),
              ),
            );
          }

          return GridView.builder(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisSpacing: Dimensions.paddingSizeSmall,
              crossAxisSpacing: Dimensions.paddingSizeSmall,
              childAspectRatio: 0.85,
            ),
            itemCount: categoryProvider.categoryList.length,
            itemBuilder: (context, index) {
              final category = categoryProvider.categoryList[index];
              return InkWell(
                onTap: () => _openCategory(category),
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                    color: Theme.of(context).highlightColor,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        offset: const Offset(1, 1),
                        spreadRadius: 0,
                        blurRadius: 4,
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(100),
                        child: CustomImageWidget(
                          fit: BoxFit.cover,
                          image: '${category.imageFullUrl?.path}',
                          height: 50,
                          width: 50,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
                        child: Text(
                          category.name ?? '',
                          maxLines: 2,
                          style: textBold.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            height: 1.0,
                            color: Theme.of(context).textTheme.bodyLarge?.color,
                          ),
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
