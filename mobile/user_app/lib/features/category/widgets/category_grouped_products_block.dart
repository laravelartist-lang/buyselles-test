import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_product_group.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_api_cache.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/product_sort_option.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_product_grid.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

export 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_product_group.dart';

class CategoryGroupedProductsBlock extends StatefulWidget {
  final int categoryId;
  final bool isSubSubLevel;
  final int? parentId;
  final String? parentName;
  final Map<String, dynamic>? stepContext;
  final VoidCallback? onEmpty;

  const CategoryGroupedProductsBlock({
    super.key,
    required this.categoryId,
    this.isSubSubLevel = false,
    this.parentId,
    this.parentName,
    this.stepContext,
    this.onEmpty,
  });

  @override
  State<CategoryGroupedProductsBlock> createState() => CategoryGroupedProductsBlockState();
}

class CategoryGroupedProductsBlockState extends State<CategoryGroupedProductsBlock> {
  List<CategoryProductGroup> _groups = [];
  bool _isLoading = true;
  bool _hasError = false;
  final Map<int, bool> _loadingMoreByCategoryId = {};
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  ProductSortOption _sortOption = ProductSortOption.defaultSort;

  String get _filterBy =>
      widget.isSubSubLevel ? 'direct_sub_sub_category' : 'direct_sub_category';

  @override
  void initState() {
    super.initState();
    _loadGroupedProducts(reset: true);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(CategoryGroupedProductsBlock oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.parentId != widget.parentId ||
        oldWidget.categoryId != widget.categoryId ||
        oldWidget.isSubSubLevel != widget.isSubSubLevel ||
        oldWidget.stepContext?['vendor_id'] != widget.stepContext?['vendor_id']) {
      _loadGroupedProducts(reset: true);
    }
  }

  void loadMoreIfNeeded() {
    for (final group in _groups.reversed) {
      if (group.hasMore && _loadingMoreByCategoryId[group.categoryId] != true) {
        _loadMoreForGroup(group);
        return;
      }
    }
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query.toLowerCase();
    });
  }

  void _clearSearch() {
    _searchController.clear();
    _onSearchChanged('');
  }

  void _onSortChanged(ProductSortOption? option) {
    if (option != null) {
      setState(() {
        _sortOption = option;
      });
    }
  }

  bool _productMatches(Product product) {
    if (_searchQuery.isEmpty) return true;
    return (product.name ?? '').toLowerCase().contains(_searchQuery);
  }

  bool _groupMatches(CategoryProductGroup group) {
    if (_searchQuery.isEmpty) return true;
    if (group.title.toLowerCase().contains(_searchQuery)) return true;
    return group.products.any((p) => _productMatches(p));
  }

  List<CategoryProductGroup> get _sortedAndFilteredGroups {
    List<CategoryProductGroup> result;
    if (_searchQuery.isEmpty) {
      result = _groups;
    } else {
      result = _groups.where(_groupMatches).map((group) {
        if (group.title.toLowerCase().contains(_searchQuery)) {
          return group;
        }
        final filtered = group.products.where((p) => _productMatches(p)).toList();
        return group.copyWith(products: filtered);
      }).toList();
    }

    return result.map((group) {
      final sortedProducts = List<Product>.from(group.products);
      sortProducts(sortedProducts, _sortOption);
      return group.copyWith(products: sortedProducts);
    }).toList();
  }

  int? get _vendorId => widget.stepContext?['vendor_id'] as int?;

  String get _groupLevel => widget.isSubSubLevel ? 'sub_sub_category' : 'sub_category';

  String get _cacheKey =>
      '${widget.categoryId}|$_groupLevel|${widget.parentId ?? ''}|${_vendorId ?? ''}';

  Future<void> _loadGroupedProducts({required bool reset}) async {
    if (reset) {
      setState(() {
        _isLoading = true;
        _hasError = false;
        _loadingMoreByCategoryId.clear();
      });
    }

    try {
      if (reset) {
        CategoryApiCache.instance.invalidateGroupedProducts(_cacheKey);
      }

      final groups = await CategoryApiCache.instance.groupedProducts(
        categoryId: widget.categoryId,
        groupLevel: _groupLevel,
        parentId: widget.parentId,
        parentName: widget.parentName,
        vendorId: _vendorId,
        limit: CategoryApiCache.categoryProductsPageSize,
        offset: 1,
      );

      if (mounted) {
        setState(() {
          _groups = groups;
          _isLoading = false;
        });

        if (groups.isEmpty) {
          widget.onEmpty?.call();
        }
      }
    } catch (e) {
      debugPrint('CategoryGroupedProductsBlock error: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _hasError = true;
        });
      }
    }
  }

  Future<void> _loadMoreForGroup(CategoryProductGroup group) async {
    final categoryId = group.categoryId;
    if (categoryId == null || !group.hasMore) {
      return;
    }

    if (_loadingMoreByCategoryId[categoryId] == true) {
      return;
    }

    setState(() {
      _loadingMoreByCategoryId[categoryId] = true;
    });

    try {
      final nextOffset = group.offset + 1;
      final page = await CategoryApiCache.instance.fetchProductsPage(
        categoryId: categoryId,
        filterBy: _filterBy,
        limit: group.limit,
        offset: nextOffset,
        vendorId: _vendorId,
      );

      if (!mounted || page.products.isEmpty) {
        return;
      }

      setState(() {
        final index = _groups.indexWhere((item) => item.categoryId == categoryId);
        if (index == -1) {
          return;
        }

        final current = _groups[index];
        final existingIds = current.products.map((product) => product.id).toSet();
        final mergedProducts = List<Product>.from(current.products);

        for (final product in page.products) {
          if (!existingIds.contains(product.id)) {
            mergedProducts.add(product);
          }
        }

        _groups[index] = current.copyWith(
          products: mergedProducts,
          offset: page.offset,
          totalSize: page.totalSize,
          limit: page.limit,
        );
      });
    } catch (e) {
      debugPrint('CategoryGroupedProductsBlock._loadMoreForGroup error: $e');
    } finally {
      if (mounted) {
        setState(() {
          _loadingMoreByCategoryId[categoryId] = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Padding(
        padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (_hasError) {
      return Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            ElevatedButton.icon(
              onPressed: () => _loadGroupedProducts(reset: true),
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final groups = _sortedAndFilteredGroups;

    if (groups.isEmpty) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildSearchBar(),
          _buildSortDropdown(),
          const CategoryBlockEmptyWidget(),
        ],
      );
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSearchBar(),
        _buildSortDropdown(),
        for (final group in groups) ...[
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            child: Text(
              group.title,
              style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault),
            ),
          ),
          CategoryBlockProductGrid(
            products: group.products,
            totalSize: group.totalSize,
            isLoadingMore: _loadingMoreByCategoryId[group.categoryId] == true,
            onLoadMore: group.hasMore ? () => _loadMoreForGroup(group) : null,
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
        ],
      ],
    );
  }

  Widget _buildSortDropdown() {
    final textColor = Theme.of(context).textTheme.bodyLarge?.color ?? Colors.black;
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      child: Row(
        children: [
          Icon(Icons.sort, size: 18, color: textColor),
          const SizedBox(width: Dimensions.paddingSizeExtraSmall),
          Text(
            'Sort: ',
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: textColor,
            ),
          ),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<ProductSortOption>(
                value: _sortOption,
                isDense: true,
                isExpanded: true,
                style: textMedium.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: textColor,
                ),
                dropdownColor: Theme.of(context).cardColor,
                iconEnabledColor: textColor,
                items: ProductSortOption.values.map((option) {
                  return DropdownMenuItem<ProductSortOption>(
                    value: option,
                    child: Text(
                      option.label,
                      overflow: TextOverflow.ellipsis,
                      style: textMedium.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: textColor,
                      ),
                    ),
                  );
                }).toList(),
                onChanged: _onSortChanged,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeSmall,
      ),
      child: TextField(
        controller: _searchController,
        onChanged: _onSearchChanged,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: 'Search products...',
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: Dimensions.paddingSizeSmall,
            vertical: 10,
          ),
          prefixIcon: const Icon(Icons.search, size: 20),
          suffixIcon: _searchQuery.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear, size: 18),
                  onPressed: _clearSearch,
                )
              : null,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            borderSide: BorderSide(
              color: Theme.of(context).hintColor.withValues(alpha: 0.3),
            ),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            borderSide: BorderSide(
              color: Theme.of(context).hintColor.withValues(alpha: 0.15),
            ),
          ),
        ),
      ),
    );
  }
}
