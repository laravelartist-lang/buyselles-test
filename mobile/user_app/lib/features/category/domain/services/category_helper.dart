class CategoryHelper {
  /// Determines if a category represents Physical Products.
  /// Any category that does not match digital-specific keywords is treated as Physical.
  static bool isPhysicalCategory(String? categoryName) {
    if (categoryName == null || categoryName.trim().isEmpty) {
      return true;
    }
    final name = categoryName.toLowerCase();

    final digitalKeywords = [
      'digital',
      'ebook',
      'e-book',
      'software',
      'gift card',
      'gift-card',
      'subscription',
      'license',
      'key',
    ];

    for (final keyword in digitalKeywords) {
      if (name.contains(keyword)) {
        return false;
      }
    }

    return true;
  }
}
