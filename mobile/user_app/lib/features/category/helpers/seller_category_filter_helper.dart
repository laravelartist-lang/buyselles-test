class SellerCategoryFilterHelper {
  static int _parseCount(dynamic value) {
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static List<dynamic> filterCategoryData(List<dynamic> data) {
    return data.map((category) {
      final map = Map<String, dynamic>.from(category as Map);

      if (map['childes'] is List) {
        map['childes'] = (map['childes'] as List)
            .map((sub) {
              final subMap = Map<String, dynamic>.from(sub as Map);

              if (subMap['childes'] is List) {
                subMap['childes'] = (subMap['childes'] as List)
                    .where((subSub) => _parseCount(
                          (subSub as Map)['sub_sub_category_product_count'],
                        ) >
                        0)
                    .toList();
              }

              return subMap;
            })
            .where((sub) {
              final subMap = sub as Map;
              final subCount = _parseCount(subMap['sub_category_product_count']);
              final childes = subMap['childes'];

              return subCount > 0 || (childes is List && childes.isNotEmpty);
            })
            .toList();
      }

      return map;
    }).toList();
  }
}
