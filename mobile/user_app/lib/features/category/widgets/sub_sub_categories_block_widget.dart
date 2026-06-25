import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_api_cache.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_tile.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class SubSubCategoriesBlock extends StatefulWidget {
  final int categoryId;
  final bool hideTitle;
  final int? filterParentId;
  final int? vendorId;
  final void Function(int categoryId, String categoryName)? onCategoryTap;
  final VoidCallback? onEmpty;

  const SubSubCategoriesBlock({
    super.key,
    required this.categoryId,
    this.hideTitle = false,
    this.filterParentId,
    this.vendorId,
    this.onCategoryTap,
    this.onEmpty,
  });

  @override
  State<SubSubCategoriesBlock> createState() => _SubSubCategoriesBlockState();
}

class _SubSubCategoriesBlockState extends State<SubSubCategoriesBlock> {
  List<CategoryModel> _subCategories = [];
  List<SubCategory> _flatSubSubCategories = [];
  bool _isLoading = true;
  bool _hasError = false;
  bool _useFlatList = false;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
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

  bool _matchesSearch(String? name) {
    if (_searchQuery.isEmpty) return true;
    return (name ?? '').toLowerCase().contains(_searchQuery);
  }

  // For flat list: filter items by name
  List<SubCategory> _filteredFlatItems() {
    if (_searchQuery.isEmpty) return _flatSubSubCategories;
    return _flatSubSubCategories.where((item) => _matchesSearch(item.name)).toList();
  }

  // For grouped view: filter each sub-category's children, keep groups with matches
  List<CategoryModel> _filteredGroupedItems() {
    if (_searchQuery.isEmpty) return _subCategories;
    return _subCategories.map((sub) {
      final filteredChildren = (sub.subCategories ?? [])
          .where((child) => _matchesSearch(child.name))
          .toList();
      if (filteredChildren.isEmpty && _matchesSearch(sub.name)) {
        // Parent name matches; show all children
        return sub;
      }
      if (filteredChildren.isNotEmpty) {
        return CategoryModel(
          id: sub.id,
          name: sub.name,
          slug: sub.slug,
          icon: sub.icon,
          imageFullUrl: sub.imageFullUrl,
          subCategories: filteredChildren,
          totalProductCount: sub.totalProductCount,
        );
      }
      return null;
    }).whereType<CategoryModel>().toList();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _hasError = false;
      _flatSubSubCategories = [];
      _subCategories = [];
      _useFlatList = false;
    });

    try {
      final parentId = widget.filterParentId ?? widget.categoryId;
      final categories = await CategoryApiCache.instance.categoriesForParent(
        parentId,
        vendorId: widget.vendorId,
      );

      if (mounted) {
        if (widget.filterParentId != null) {
          final flatList = categories
              .where((category) => category.id != null)
              .map((category) => SubCategory(
                    id: category.id,
                    name: category.name,
                    slug: category.slug,
                    icon: category.icon,
                    imageFullUrl: category.imageFullUrl,
                  ))
              .toList();
          setState(() {
            _flatSubSubCategories = flatList;
            _useFlatList = true;
            _isLoading = false;
          });
          if (flatList.isEmpty) {
            widget.onEmpty?.call();
          }
          return;
        }

        final subCategories = categories;
        bool hasSubSubCategories = subCategories.any(
          (sub) => (sub.subCategories ?? []).isNotEmpty,
        );

        if (!hasSubSubCategories) {
          // Nested sub-sub-categories not available from API response.
          // Fetch all children in a single parallel batch using Future.wait.
          // The step resolver may have already cached some/all of these.
          final parentIds = subCategories
              .where((s) => s.id != null)
              .map((s) => s.id!)
              .toList();

          if (parentIds.isNotEmpty) {
            final childrenByParent = await CategoryApiCache.instance
                .categoriesForParents(parentIds);

            if (mounted) {
              final List<CategoryModel> rebuiltCategories = [];
              for (final sub in subCategories) {
                if (sub.id != null) {
                  final children = childrenByParent[sub.id] ?? [];
                  if (children.isNotEmpty) {
                    final subSubList = children
                        .where((c) => c.id != null)
                        .map((c) => SubCategory(
                              id: c.id,
                              name: c.name,
                              slug: c.slug,
                              icon: c.icon,
                              imageFullUrl: c.imageFullUrl,
                              totalProductCount: c.totalProductCount,
                              parentId: c.parentId,
                              position: c.position,
                            ))
                        .toList();
                    rebuiltCategories.add(CategoryModel(
                      id: sub.id,
                      name: sub.name,
                      slug: sub.slug,
                      icon: sub.icon,
                      imageFullUrl: sub.imageFullUrl,
                      subCategories: subSubList,
                      totalProductCount: sub.totalProductCount,
                    ));
                  } else {
                    rebuiltCategories.add(sub);
                  }
                } else {
                  rebuiltCategories.add(sub);
                }
              }

              hasSubSubCategories = rebuiltCategories
                  .any((c) => (c.subCategories ?? []).isNotEmpty);

              setState(() {
                _subCategories = rebuiltCategories;
                _useFlatList = false;
                _isLoading = false;
              });
            }
          } else {
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
          }
        } else {
          if (mounted) {
            setState(() {
              _subCategories = subCategories;
              _useFlatList = false;
              _isLoading = false;
            });
          }
        }

        if (!hasSubSubCategories && mounted) {
          widget.onEmpty?.call();
        }
      }
    } catch (e) {
      debugPrint('SubSubCategoriesBlock error: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _hasError = true;
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
      return _buildRetryWidget();
    }

    if (_useFlatList) {
      final items = _filteredFlatItems();
      if (items.isEmpty) {
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _buildSearchBar(),
            const CategoryBlockEmptyWidget(messageKey: 'no_data_found', icon: Images.noData),
          ],
        );
      }
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildSearchBar(),
          _buildFlatGrid(items),
        ],
      );
    }

    final items = _filteredGroupedItems();
    final bool hasContent = _searchQuery.isEmpty
        ? _subCategories.any((s) => (s.subCategories ?? []).isNotEmpty)
        : items.isNotEmpty;

    if (!hasContent) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildSearchBar(),
          const CategoryBlockEmptyWidget(messageKey: 'no_data_found', icon: Images.noData),
        ],
      );
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSearchBar(),
        ...items.map((sub) {
          final subSubList = sub.subCategories ?? [];
          if (subSubList.isEmpty) {
            return const SizedBox.shrink();
          }

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeDefault,
                  vertical: Dimensions.paddingSizeExtraSmall,
                ),
                child: Text(
                  sub.name ?? '',
                  style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
              ),
              _buildFlatGrid(subSubList),
            ],
          );
        }),
      ],
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
          hintText: getTranslated('search_sub_sub_categories', context) ?? 'Search sub-sub categories...',
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

  Widget _buildFlatGrid(List<SubCategory> items) {
    return Padding(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: LayoutBuilder(
        builder: (context, constraints) {
          const crossAxisCount = 3;
          const double crossAxisSpacing = Dimensions.paddingSizeExtraSmall;
          const double mainAxisSpacing = Dimensions.paddingSizeExtraSmall;
          final itemWidth =
              (constraints.maxWidth - (crossAxisCount - 1) * crossAxisSpacing) / crossAxisCount;

          return Column(
            children: [
              for (int i = 0; i < items.length; i += crossAxisCount)
                Padding(
                  padding: EdgeInsets.only(top: i > 0 ? mainAxisSpacing : 0),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (int j = 0; j < crossAxisCount && i + j < items.length; j++)
                        Padding(
                          padding: EdgeInsets.only(left: j > 0 ? crossAxisSpacing : 0),
                          child: SizedBox(
                            width: itemWidth,
                            child: _buildSubSubItem(items[i + j]),
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildSubSubItem(SubCategory subSub) {
    return InkWell(
      onTap: () {
        if (widget.onCategoryTap != null && subSub.id != null && subSub.name != null) {
          widget.onCategoryTap!(subSub.id!, subSub.name!);
        } else {
          RouterHelper.getBrandCategoryRoute(
            isBrand: false,
            id: subSub.id,
            name: subSub.name,
            categoryModel: CategoryModel(
              id: subSub.id,
              name: subSub.name,
              slug: subSub.slug,
              imageFullUrl: subSub.imageFullUrl,
            ),
            isAllProduct: true,
          );
        }
      },
      child: CategoryBlockTile(
        name: subSub.name,
        image: subSub.imageFullUrl?.path,
        productCount: subSub.totalProductCount,
      ),
    );
  }

  Widget _buildRetryWidget() {
    return Padding(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: Column(
        children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.red),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            getTranslated('failed_to_load_sub_sub_categories', context) ?? 'Failed to load sub-sub categories',
            textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ElevatedButton.icon(
            onPressed: _loadData,
            icon: const Icon(Icons.refresh, size: 18),
            label: Text(getTranslated('retry', context) ?? 'Retry'),
          ),
        ],
      ),
    );
  }
}
