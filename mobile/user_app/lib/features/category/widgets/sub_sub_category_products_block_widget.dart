import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_grouped_products_block.dart';

class SubSubCategoryProductsBlock extends StatelessWidget {
  final int categoryId;
  final String categoryName;
  final bool hideTitle;
  final int? parentId;
  final String? parentName;
  final Map<String, dynamic>? stepContext;
  final VoidCallback? onEmpty;
  final GlobalKey<CategoryGroupedProductsBlockState>? groupedProductsKey;

  const SubSubCategoryProductsBlock({
    super.key,
    required this.categoryId,
    this.categoryName = '',
    this.hideTitle = false,
    this.parentId,
    this.parentName,
    this.stepContext,
    this.onEmpty,
    this.groupedProductsKey,
  });

  @override
  Widget build(BuildContext context) {
    return CategoryGroupedProductsBlock(
      key: groupedProductsKey,
      categoryId: categoryId,
      isSubSubLevel: true,
      parentId: parentId,
      parentName: parentName,
      stepContext: stepContext,
      onEmpty: onEmpty,
    );
  }
}
