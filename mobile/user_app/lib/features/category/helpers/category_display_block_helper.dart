import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_display_block_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';

class CategoryDisplayBlockHelper {
  static String titleForBlock(DisplayBlock block, BuildContext context, {String categoryName = ''}) {
    final custom = block.settings?['title'];
    if (custom is String && custom.trim().isNotEmpty) {
      return custom.trim();
    }

    final type = block.blockType ?? '';

    if (type == 'mixed_products' && categoryName.isNotEmpty) {
      return '${getTranslated('all', context) ?? 'All'} $categoryName ${getTranslated('products', context) ?? 'Products'}';
    }

    if (type == 'vendors_list' && categoryName.isNotEmpty) {
      return '${getTranslated('shops_in', context) ?? 'Shops in'} $categoryName';
    }

    return getTranslated(type, context) ?? switch (type) {
      'sub_categories' => 'Sub Categories',
      'sub_category_products' => 'Products in Sub Categories',
      'sub_sub_categories' => 'Sub Sub Categories',
      'sub_sub_category_products' => 'Products in Sub Sub Categories',
      'mixed_products' => 'Mixed Products',
      'vendors_list' => 'Vendors List',
      'location_pipeline' => 'Discover Local',
      _ => type,
    };
  }
}
