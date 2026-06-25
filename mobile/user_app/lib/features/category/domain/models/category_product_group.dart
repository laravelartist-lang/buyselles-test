import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';

class CategoryProductGroup {
  final int? categoryId;
  final String title;
  final List<Product> products;
  final int totalSize;
  final int offset;
  final int limit;

  const CategoryProductGroup({
    required this.title,
    required this.products,
    this.categoryId,
    this.totalSize = 0,
    this.offset = 1,
    this.limit = 20,
  });

  bool get hasMore => products.length < totalSize;

  CategoryProductGroup copyWith({
    int? categoryId,
    String? title,
    List<Product>? products,
    int? totalSize,
    int? offset,
    int? limit,
  }) {
    return CategoryProductGroup(
      categoryId: categoryId ?? this.categoryId,
      title: title ?? this.title,
      products: products ?? this.products,
      totalSize: totalSize ?? this.totalSize,
      offset: offset ?? this.offset,
      limit: limit ?? this.limit,
    );
  }
}

class CategoryProductsPage {
  final List<Product> products;
  final int totalSize;
  final int offset;
  final int limit;

  const CategoryProductsPage({
    required this.products,
    required this.totalSize,
    required this.offset,
    required this.limit,
  });

  bool get hasMore => products.isNotEmpty && (offset * limit) < totalSize;
}
