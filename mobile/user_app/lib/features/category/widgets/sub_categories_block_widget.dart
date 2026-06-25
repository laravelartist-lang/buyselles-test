import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_api_cache.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_tile.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';

class SubCategoriesBlock extends StatefulWidget {
  final int categoryId;
  final bool hideTitle;
  final int? vendorId;
  final void Function(int categoryId, String categoryName)? onCategoryTap;
  final VoidCallback? onEmpty;

  const SubCategoriesBlock({
    super.key,
    required this.categoryId,
    this.hideTitle = false,
    this.vendorId,
    this.onCategoryTap,
    this.onEmpty,
  });

  @override
  State<SubCategoriesBlock> createState() => _SubCategoriesBlockState();
}

class _SubCategoriesBlockState extends State<SubCategoriesBlock> {
  List<CategoryModel> _subCategories = [];
  List<CategoryModel> _filteredCategories = [];
  bool _isLoading = true;
  bool _hasError = false;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadSubCategories();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query.toLowerCase();
      _applyFilter();
    });
  }

  void _clearSearch() {
    _searchController.clear();
    _onSearchChanged('');
  }

  void _applyFilter() {
    if (_searchQuery.isEmpty) {
      _filteredCategories = List.from(_subCategories);
    } else {
      _filteredCategories = _subCategories
          .where((c) =>
              (c.name ?? '').toLowerCase().contains(_searchQuery))
          .toList();
    }
  }

  Future<void> _loadSubCategories() async {
    setState(() { _isLoading = true; _hasError = false; });
    try {
      final subCategories = await CategoryApiCache.instance.categoriesForParent(
        widget.categoryId,
        vendorId: widget.vendorId,
      );
      if (mounted) {
        setState(() {
          _subCategories = subCategories;
          _applyFilter();
          _isLoading = false;
        });
        if (subCategories.isEmpty) {
          widget.onEmpty?.call();
        }
      }
    } catch (e) {
      debugPrint('SubCategoriesBlock error: $e');
      if (mounted) setState(() { _isLoading = false; _hasError = true; });
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

    final itemsToShow = _filteredCategories;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Search bar replacing the static title
        _buildSearchBar(),
        if (_hasError)
          _buildRetryWidget()
        else if (itemsToShow.isEmpty)
          const CategoryBlockEmptyWidget(messageKey: 'no_data_found', icon: Images.noData)
        else
        Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          child: LayoutBuilder(
            builder: (context, constraints) {
              const crossAxisCount = 3;
              const double mainAxisSpacing = Dimensions.paddingSizeExtraSmall;
              const double crossAxisSpacing = Dimensions.paddingSizeExtraSmall;
              final itemWidth = (constraints.maxWidth - (crossAxisCount - 1) * crossAxisSpacing) / crossAxisCount;

              return Column(
                children: [
                  for (int i = 0; i < itemsToShow.length; i += crossAxisCount)
                    Padding(
                      padding: EdgeInsets.only(top: i > 0 ? mainAxisSpacing : 0),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          for (int j = 0; j < crossAxisCount && i + j < itemsToShow.length; j++)
                            Padding(
                              padding: EdgeInsets.only(left: j > 0 ? crossAxisSpacing : 0),
                              child: SizedBox(
                                width: itemWidth,
                                child: _buildCategoryItem(itemsToShow[i + j]),
                              ),
                            ),
                        ],
                      ),
                    ),
                ],
              );
            },
          ),
        ),
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
          hintText: getTranslated('search_sub_categories', context) ?? 'Search sub categories...',
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

  Widget _buildCategoryItem(CategoryModel sub) {
    return InkWell(
      onTap: () {
        if (widget.onCategoryTap != null && sub.id != null && sub.name != null) {
          widget.onCategoryTap!(sub.id!, sub.name!);
        } else {
          RouterHelper.getBrandCategoryRoute(
            isBrand: false,
            id: sub.id,
            name: sub.name,
            categoryModel: sub,
          );
        }
      },
      child: CategoryBlockTile(
        name: sub.name,
        image: sub.imageFullUrl?.path,
        productCount: sub.totalProductCount,
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
          Text(getTranslated('failed_to_load_sub_categories', context) ?? 'Failed to load sub categories',
            textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ElevatedButton.icon(
            onPressed: _loadSubCategories,
            icon: const Icon(Icons.refresh, size: 18),
            label: Text(getTranslated('retry', context) ?? 'Retry'),
          ),
        ],
      ),
    );
  }
}
