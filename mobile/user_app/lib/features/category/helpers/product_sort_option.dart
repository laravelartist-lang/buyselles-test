import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';

enum ProductSortOption {
  defaultSort,
  priceHighToLow,
  priceLowToHigh,
  ratingHighToLow,
  ratingLowToHigh,
  nameAZ,
  nameZA,
  soldMostToLeast,
  soldLeastToMost,
}

extension ProductSortOptionLabel on ProductSortOption {
  String get label {
    switch (this) {
      case ProductSortOption.defaultSort:
        return 'Default';
      case ProductSortOption.priceHighToLow:
        return 'Price \u2193';
      case ProductSortOption.priceLowToHigh:
        return 'Price \u2191';
      case ProductSortOption.ratingHighToLow:
        return 'Rating \u2193';
      case ProductSortOption.ratingLowToHigh:
        return 'Rating \u2191';
      case ProductSortOption.nameAZ:
        return 'Name A-Z';
      case ProductSortOption.nameZA:
        return 'Name Z-A';
      case ProductSortOption.soldMostToLeast:
        return 'Sold \u2193';
      case ProductSortOption.soldLeastToMost:
        return 'Sold \u2191';
    }
  }
}

/// Sorts a list of [Product]s in-place according to the given [sortOption].
/// Default sort orders by [Product.sortPriority] ascending (lowest number first).
void sortProducts(List<Product> products, ProductSortOption sortOption) {
  switch (sortOption) {
    case ProductSortOption.defaultSort:
      // Default: sort by sort_priority ascending, then by id descending for tie-breaking
      products.sort((a, b) {
        final priorityCompare = (a.sortPriority ?? 0).compareTo(b.sortPriority ?? 0);
        if (priorityCompare != 0) return priorityCompare;
        return (b.id ?? 0).compareTo(a.id ?? 0);
      });
      break;
    case ProductSortOption.priceHighToLow:
      products.sort((a, b) => (b.unitPrice ?? 0).compareTo(a.unitPrice ?? 0));
      break;
    case ProductSortOption.priceLowToHigh:
      products.sort((a, b) => (a.unitPrice ?? 0).compareTo(b.unitPrice ?? 0));
      break;
    case ProductSortOption.ratingHighToLow:
      products.sort((a, b) =>
          _avgRating(b).compareTo(_avgRating(a)));
      break;
    case ProductSortOption.ratingLowToHigh:
      products.sort((a, b) =>
          _avgRating(a).compareTo(_avgRating(b)));
      break;
    case ProductSortOption.nameAZ:
      products.sort((a, b) => (a.name ?? '').compareTo(b.name ?? ''));
      break;
    case ProductSortOption.nameZA:
      products.sort((a, b) => (b.name ?? '').compareTo(a.name ?? ''));
      break;
    case ProductSortOption.soldMostToLeast:
      products.sort((a, b) => (b.reviewCount ?? 0).compareTo(a.reviewCount ?? 0));
      break;
    case ProductSortOption.soldLeastToMost:
      products.sort((a, b) => (a.reviewCount ?? 0).compareTo(b.reviewCount ?? 0));
      break;
  }
}

double _avgRating(Product product) {
  final ratings = product.rating;
  if (ratings == null || ratings.isEmpty) return 0;
  return double.tryParse(ratings.first.average ?? '0') ?? 0;
}
