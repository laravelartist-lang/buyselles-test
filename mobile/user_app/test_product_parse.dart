import 'dart:convert';
import 'dart:io';
import 'lib/features/product/domain/models/product_model.dart';

void main() {
  final data = jsonDecode(File('/tmp/grouped.json').readAsStringSync());
  final groups = data['groups'] as List;
  int ok = 0, fail = 0;
  for (final g in groups) {
    for (final item in g['products'] as List) {
      try {
        Product.fromJson(Map<String, dynamic>.from(item));
        ok++;
      } catch (e) {
        fail++;
        print('FAIL id=${item['id']}: $e');
      }
    }
  }
  print('parsed ok=$ok fail=$fail');
}
