import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/di_container.dart' as di;
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_product_group.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';

class CategoryApiCache {
  CategoryApiCache._();

  static final CategoryApiCache instance = CategoryApiCache._();

  static const int categoryProductsPageSize = 20;

  final Map<String, List<CategoryModel>> _categoriesByParent = {};
  final Map<String, bool> _productExistsByKey = {};
  final Map<String, List<CategoryProductGroup>> _groupedProductsByKey = {};
  final Map<int, Future<List<CategoryModel>>> _inflightCategories = {};
  final Map<String, Future<bool>> _inflightProductChecks = {};
  final Map<String, Future<List<CategoryProductGroup>>> _inflightGroupedProducts = {};

  bool _groupedEndpointUnavailable = false;

  void clear() {
    _categoriesByParent.clear();
    _productExistsByKey.clear();
    _groupedProductsByKey.clear();
    _inflightCategories.clear();
    _inflightProductChecks.clear();
    _inflightGroupedProducts.clear();
    _groupedEndpointUnavailable = false;
  }

  void invalidateGroupedProducts(String cacheKey) {
    _groupedProductsByKey.remove(cacheKey);
    _inflightGroupedProducts.remove(cacheKey);
  }

  Future<List<CategoryProductGroup>> groupedProducts({
    required int categoryId,
    required String groupLevel,
    int? parentId,
    String? parentName,
    int? vendorId,
    int limit = categoryProductsPageSize,
    int offset = 1,
  }) async {
    final cacheKey = '$categoryId|$groupLevel|${parentId ?? ''}|${vendorId ?? ''}|$limit|$offset';

    if (offset == 1 && _groupedProductsByKey.containsKey(cacheKey)) {
      return _groupedProductsByKey[cacheKey]!;
    }

    final inflight = _inflightGroupedProducts[cacheKey];
    if (inflight != null) {
      return inflight;
    }

    final future = _loadGroupedProducts(
      categoryId: categoryId,
      groupLevel: groupLevel,
      parentId: parentId,
      parentName: parentName,
      vendorId: vendorId,
      limit: limit,
      offset: offset,
    );
    _inflightGroupedProducts[cacheKey] = future;

    try {
      final groups = await future;
      if (offset == 1) {
        _groupedProductsByKey[cacheKey] = groups;
      }
      return groups;
    } finally {
      _inflightGroupedProducts.remove(cacheKey);
    }
  }

  Future<CategoryProductsPage> fetchProductsPage({
    required int categoryId,
    required String filterBy,
    int limit = categoryProductsPageSize,
    int offset = 1,
    int? vendorId,
  }) async {
    try {
      final dioClient = di.sl<DioClient>();
      final response = await dioClient.get(
        '${AppConstants.categoryProductUri}$categoryId',
        queryParameters: {
          'limit': limit,
          'offset': offset,
          'filter_by': filterBy,
          'guest_id': 1,
          if (vendorId != null) 'vendor_id': vendorId,
        },
      );

      if (response.statusCode != 200 || response.data is! Map) {
        return CategoryProductsPage(
          products: const [],
          totalSize: 0,
          offset: offset,
          limit: limit,
        );
      }

      final data = response.data as Map;
      final products = _parseProductsFromList(data['products'] ?? []);

      return CategoryProductsPage(
        products: products,
        totalSize: _nullableInt(data['total_size']) ?? products.length,
        offset: _nullableInt(data['offset']) ?? offset,
        limit: _nullableInt(data['limit']) ?? limit,
      );
    } catch (e) {
      debugPrint('CategoryApiCache.fetchProductsPage error: $e');
      return CategoryProductsPage(
        products: const [],
        totalSize: 0,
        offset: offset,
        limit: limit,
      );
    }
  }

  List<Product> _parseProductsFromList(List<dynamic> productList) {
    final products = <Product>[];
    for (final item in productList) {
      if (item is! Map) {
        continue;
      }

      try {
        products.add(Product.fromJson(Map<String, dynamic>.from(item)));
      } catch (e) {
        debugPrint('CategoryApiCache: skipped product id=${item['id']}: $e');
      }
    }

    return products;
  }

  int? _nullableInt(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    if (value is bool) {
      return value ? 1 : 0;
    }
    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value.toString());
  }

  Future<bool> hasGroupedProducts({
    required int categoryId,
    required String groupLevel,
    int? parentId,
    String? parentName,
    int? vendorId,
  }) async {
    final groups = await groupedProducts(
      categoryId: categoryId,
      groupLevel: groupLevel,
      parentId: parentId,
      parentName: parentName,
      vendorId: vendorId,
      limit: 1,
    );

    return groups.isNotEmpty;
  }

  Future<List<CategoryModel>> categoriesForParent(int parentId, {int? vendorId}) async {
    final cacheKey = vendorId != null ? '$parentId|$vendorId' : '$parentId';

    if (_categoriesByParent.containsKey(cacheKey)) {
      return _categoriesByParent[cacheKey]!;
    }

    final inflight = _inflightCategories[parentId];
    if (inflight != null && vendorId == null) {
      return inflight;
    }

    final future = _fetchCategories(parentId, vendorId: vendorId);
    if (vendorId == null) {
      _inflightCategories[parentId] = future;
    }

    try {
      final categories = await future;
      _categoriesByParent[cacheKey] = categories;
      return categories;
    } finally {
      if (vendorId == null) {
        _inflightCategories.remove(parentId);
      }
    }
  }

  /// Fetches children for multiple parent IDs in parallel using [Future.wait].
  /// Results are cached individually so subsequent lookups are instant.
  /// Returns a map from parent ID to its child categories.
  Future<Map<int, List<CategoryModel>>> categoriesForParents(
    List<int> parentIds, {
    int? vendorId,
  }) async {
    final results = await Future.wait(
      parentIds.map((id) => categoriesForParent(id, vendorId: vendorId)),
    );
    final map = <int, List<CategoryModel>>{};
    for (int i = 0; i < parentIds.length; i++) {
      map[parentIds[i]] = results[i];
    }
    return map;
  }

  Future<bool> categoryHasProducts(int categoryId, String filterBy, {int? vendorId}) async {
    final cacheKey = '$categoryId|$filterBy|${vendorId ?? ''}';

    if (_productExistsByKey.containsKey(cacheKey)) {
      return _productExistsByKey[cacheKey]!;
    }

    final inflight = _inflightProductChecks[cacheKey];
    if (inflight != null) {
      return inflight;
    }

    final future = _fetchProductExists(categoryId, filterBy, vendorId: vendorId);
    _inflightProductChecks[cacheKey] = future;

    try {
      final exists = await future;
      _productExistsByKey[cacheKey] = exists;
      return exists;
    } finally {
      _inflightProductChecks.remove(cacheKey);
    }
  }

  Future<List<CategoryModel>> _fetchCategories(int parentId, {int? vendorId}) async {
    try {
      final dioClient = di.sl<DioClient>();
      final response = await dioClient.get(
        AppConstants.categoriesUri,
        queryParameters: {
          'parent_id': parentId,
          'guest_id': 1,
          if (vendorId != null) 'vendor_id': vendorId,
        },
      );

      if (response.statusCode != 200) {
        return [];
      }

      final List<dynamic> data = response.data is List ? response.data : [];

      return data.map((item) => CategoryModel.fromJson(item)).toList();
    } catch (e) {
      debugPrint('CategoryApiCache._fetchCategories error: $e');
      return [];
    }
  }

  Future<bool> _fetchProductExists(int categoryId, String filterBy, {int? vendorId}) async {
    try {
      final dioClient = di.sl<DioClient>();
      final response = await dioClient.get(
        '${AppConstants.categoryProductUri}$categoryId',
        queryParameters: {
          'limit': 1,
          'offset': 1,
          'filter_by': filterBy,
          'guest_id': 1,
          if (vendorId != null) 'vendor_id': vendorId,
        },
      );

      if (response.statusCode != 200) {
        return false;
      }

      final List<dynamic> products = response.data['products'] ?? [];

      return products.isNotEmpty;
    } catch (e) {
      debugPrint('CategoryApiCache._fetchProductExists error: $e');
      return false;
    }
  }

  Future<List<CategoryProductGroup>> _loadGroupedProducts({
    required int categoryId,
    required String groupLevel,
    int? parentId,
    String? parentName,
    int? vendorId,
    required int limit,
    int offset = 1,
  }) async {
    if (!_groupedEndpointUnavailable) {
      try {
        return await _fetchGroupedProductsFromApi(
          categoryId: categoryId,
          groupLevel: groupLevel,
          parentId: parentId,
          vendorId: vendorId,
          limit: limit,
          offset: offset,
        );
      } on DioException catch (e) {
        final statusCode = e.response?.statusCode;
        if (statusCode == 404 || statusCode == 422 || statusCode == 405) {
          _groupedEndpointUnavailable = true;
          debugPrint('CategoryApiCache: grouped endpoint unavailable ($statusCode), using legacy fetch.');
        } else {
          debugPrint('CategoryApiCache._fetchGroupedProductsFromApi error: $e');
        }
      } catch (e) {
        debugPrint('CategoryApiCache._fetchGroupedProductsFromApi error: $e');
      }
    }

    return _fetchGroupedProductsLegacy(
      categoryId: categoryId,
      groupLevel: groupLevel,
      parentId: parentId,
      parentName: parentName,
      vendorId: vendorId,
      limit: limit,
      offset: offset,
    );
  }

  Future<int?> _resolveCategoryPosition(int categoryId, int mainCategoryId) async {
    for (final categories in _categoriesByParent.values) {
      for (final category in categories) {
        if (category.id == categoryId) {
          return category.position;
        }
      }
    }

    final subCategories = await categoriesForParent(mainCategoryId);
    for (final subCategory in subCategories) {
      if (subCategory.id == categoryId) {
        return subCategory.position ?? 1;
      }

      for (final subSub in subCategory.subCategories ?? <SubCategory>[]) {
        if (subSub.id == categoryId) {
          return subSub.position ?? 2;
        }
      }
    }

    final childCategories = await categoriesForParent(categoryId);
    if (childCategories.isNotEmpty) {
      return 1;
    }

    return 2;
  }

  Future<List<CategoryProductGroup>> _fetchGroupedProductsFromApi({
    required int categoryId,
    required String groupLevel,
    int? parentId,
    int? vendorId,
    required int limit,
    int offset = 1,
  }) async {
    final dioClient = di.sl<DioClient>();
    final response = await dioClient.get(
      '${AppConstants.categoryGroupedProductsUri}$categoryId/grouped',
      queryParameters: {
        'group_level': groupLevel,
        if (parentId != null) 'parent_id': parentId,
        if (vendorId != null) 'vendor_id': vendorId,
        'limit': limit,
        'offset': offset,
        'guest_id': 1,
      },
    );

    if (response.statusCode != 200) {
      throw DioException(
        requestOptions: response.requestOptions,
        response: response,
        type: DioExceptionType.badResponse,
      );
    }

    return _parseGroupedProductsResponse(response.data, defaultLimit: limit);
  }

  List<CategoryProductGroup> _parseGroupedProductsResponse(
    dynamic data, {
    int defaultLimit = categoryProductsPageSize,
  }) {
    if (data is! Map) {
      return [];
    }

    final List<dynamic> rawGroups = data['groups'] ?? [];
    final groups = <CategoryProductGroup>[];

    for (final rawGroup in rawGroups) {
      if (rawGroup is! Map) {
        continue;
      }

      final List<dynamic> productList = rawGroup['products'] ?? [];
      final products = _parseProductsFromList(productList);

      if (products.isEmpty) {
        continue;
      }

      groups.add(CategoryProductGroup(
        categoryId: _nullableInt(rawGroup['category_id']),
        title: (rawGroup['category_name'] ?? '').toString(),
        products: products,
        totalSize: _nullableInt(rawGroup['total_size']) ?? products.length,
        offset: _nullableInt(rawGroup['offset']) ?? _nullableInt(data['offset']) ?? 1,
        limit: _nullableInt(rawGroup['limit']) ?? _nullableInt(data['limit']) ?? defaultLimit,
      ));
    }

    return groups;
  }

  Future<List<CategoryProductGroup>> _fetchGroupedProductsLegacy({
    required int categoryId,
    required String groupLevel,
    int? parentId,
    String? parentName,
    int? vendorId,
    required int limit,
    int offset = 1,
  }) async {
    final groups = <CategoryProductGroup>[];

    try {
      if (groupLevel == 'sub_sub_category') {
        if (parentId != null) {
          final position = await _resolveCategoryPosition(parentId, categoryId);

          if (position == 2) {
            final products = await _fetchProductsForCategory(
              parentId,
              'direct_sub_sub_category',
              limit,
              vendorId: vendorId,
            );
            if (products.isNotEmpty) {
              groups.add(CategoryProductGroup(
                categoryId: parentId,
                title: parentName ?? '',
                products: products,
              ));
            }

            return groups;
          }

          final subSubCategories = await categoriesForParent(parentId, vendorId: vendorId);
          for (final subSub in subSubCategories) {
            if (subSub.id == null) {
              continue;
            }
            if (!await categoryHasProducts(subSub.id!, 'direct_sub_sub_category', vendorId: vendorId)) {
              continue;
            }
            final products = await _fetchProductsForCategory(
              subSub.id!,
              'direct_sub_sub_category',
              limit,
              vendorId: vendorId,
            );
            if (products.isNotEmpty) {
              groups.add(CategoryProductGroup(
                categoryId: subSub.id,
                title: subSub.name ?? '',
                products: products,
              ));
            }
          }
        } else {
          final subCategories = await categoriesForParent(categoryId, vendorId: vendorId);
          for (final subCategory in subCategories) {
            for (final subSub in subCategory.subCategories ?? <SubCategory>[]) {
              if (subSub.id == null) {
                continue;
              }
              if (!await categoryHasProducts(subSub.id!, 'direct_sub_sub_category', vendorId: vendorId)) {
                continue;
              }
              final products = await _fetchProductsForCategory(
                subSub.id!,
                'direct_sub_sub_category',
                limit,
                vendorId: vendorId,
              );
              if (products.isNotEmpty) {
                groups.add(CategoryProductGroup(
                  categoryId: subSub.id,
                  title: subSub.name ?? '',
                  products: products,
                ));
              }
            }
          }
        }
      } else if (parentId != null) {
        if (await categoryHasProducts(parentId, 'direct_sub_category', vendorId: vendorId)) {
          final products = await _fetchProductsForCategory(parentId, 'direct_sub_category', limit, vendorId: vendorId);
          if (products.isNotEmpty) {
            groups.add(CategoryProductGroup(
              categoryId: parentId,
              title: '',
              products: products,
            ));
          }
        }
      } else {
        final subCategories = await categoriesForParent(categoryId, vendorId: vendorId);
        for (final subCategory in subCategories) {
          if (subCategory.id == null) {
            continue;
          }
          if (!await categoryHasProducts(subCategory.id!, 'direct_sub_category', vendorId: vendorId)) {
            continue;
          }
          final products = await _fetchProductsForCategory(
            subCategory.id!,
            'direct_sub_category',
            limit,
            vendorId: vendorId,
          );
          if (products.isNotEmpty) {
            groups.add(CategoryProductGroup(
              categoryId: subCategory.id,
              title: subCategory.name ?? '',
              products: products,
            ));
          }
        }
      }
    } catch (e) {
      debugPrint('CategoryApiCache._fetchGroupedProductsLegacy error: $e');
    }

    return groups;
  }

  Future<List<Product>> _fetchProductsForCategory(
    int categoryId,
    String filterBy,
    int limit, {
    int offset = 1,
    int? vendorId,
  }) async {
    final page = await fetchProductsPage(
      categoryId: categoryId,
      filterBy: filterBy,
      limit: limit,
      offset: offset,
      vendorId: vendorId,
    );

    return page.products;
  }
}
