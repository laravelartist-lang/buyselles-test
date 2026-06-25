import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/location_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/product_sort_option.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_product_grid.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_horizontal_scroller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_location_chip.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/searchable_location_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/di_container.dart' as di;


class MixedProductsBlock extends StatefulWidget {
  final int categoryId;
  final Map<String, dynamic>? settings;
  final int? vendorId;
  final bool hideTitle;

  const MixedProductsBlock({
    super.key,
    required this.categoryId,
    this.settings,
    this.vendorId,
    this.hideTitle = false,
  });

  @override
  State<MixedProductsBlock> createState() => _MixedProductsBlockState();
}

class _MixedProductsBlockState extends State<MixedProductsBlock> {
  final TextEditingController _searchController = TextEditingController();

  List<Product> _allProducts = [];
  int? _totalSize;
  int _offset = 1;
  bool _isLoading = false;
  bool _isInitialLoading = true;
  bool _hasError = false;
  String _searchQuery = '';
  ProductSortOption _sortOption = ProductSortOption.defaultSort;

  LocationCountry? _selectedCountry;
  LocationCity? _selectedCity;
  LocationArea? _selectedArea;

  List<LocationCountry> _countries = [];
  List<LocationCity> _cities = [];
  List<LocationArea> _areas = [];
  bool _countriesLoading = false;

  @override
  void initState() {
    super.initState();
    _loadProducts(1);
  }

  List<Product> get _sortedAndFilteredProducts {
    List<Product> result;
    if (_searchQuery.isEmpty) {
      result = List<Product>.from(_allProducts);
    } else {
      final query = _searchQuery.toLowerCase();
      result = _allProducts.where((p) {
        return (p.name ?? '').toLowerCase().contains(query);
      }).toList();
    }

    // Always apply sort (default sort uses sort_priority)
    sortProducts(result, _sortOption);

    return result;
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query;
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

  List<Product> _parseProducts(List<dynamic> productList) {
    final products = <Product>[];
    for (final item in productList) {
      if (item is! Map) {
        continue;
      }

      try {
        products.add(Product.fromJson(Map<String, dynamic>.from(item)));
      } catch (e) {
        debugPrint('MixedProductsBlock skipped product id=${item['id']}: $e');
      }
    }

    return products;
  }

  Future<void> _loadProducts(int offset) async {
    if (_isLoading) return;
    setState(() { _isLoading = true; if (offset == 1) _hasError = false; });

    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> queryParameters = {
        'limit': 10,
        'offset': offset,
        'guest_id': 1,
        if (widget.vendorId != null) 'vendor_id': widget.vendorId,
      };

      if (_selectedCountry != null) {
        queryParameters['location_country_id'] = _selectedCountry!.id;
      }
      if (_selectedCity != null) {
        queryParameters['location_city_id'] = _selectedCity!.id;
      }
      if (_selectedArea != null) {
        queryParameters['location_area_id'] = _selectedArea!.id;
      }

      final response = await dioClient.get(
        '${AppConstants.categoryMixedProductsUri}${widget.categoryId}/mixed',
        queryParameters: queryParameters,
      );

      if (response.statusCode == 200 && mounted) {
        final data = response.data;
        final List<dynamic> productList = data['products'] ?? [];
        setState(() {
          if (offset == 1) {
            _allProducts = _parseProducts(productList);
          } else {
            _allProducts.addAll(_parseProducts(productList));
          }
          _totalSize = data['total_size'];
          _offset = offset;
          _isInitialLoading = false;
        });
      } else {
        if (mounted) setState(() { _isInitialLoading = false; _hasError = offset == 1; });
      }
    } catch (e) {
      debugPrint('MixedProductsBlock error: $e');
      if (mounted) setState(() { _isInitialLoading = false; _hasError = offset == 1; });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _onApply() {
    setState(() {
      _allProducts = [];
      _totalSize = null;
      _offset = 1;
    });
    _loadProducts(1);
  }

  Future<void> _fetchCountries({String? search}) async {
    setState(() => _countriesLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get(AppConstants.getCountriesUri, queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() {
          _countries = data.map((v) => LocationCountry.fromJson(v)).toList();
        });
      }
    } catch (e) {
      debugPrint('MixedProductsBlock _fetchCountries error: $e');
    }
    if (mounted) setState(() => _countriesLoading = false);
  }

  Future<void> _fetchCities(int countryId, {String? search}) async {
    setState(() => _countriesLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get('${AppConstants.getCitiesUri}$countryId', queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() {
          _cities = data.map((v) => LocationCity.fromJson(v)).toList();
        });
      }
    } catch (e) {
      debugPrint('MixedProductsBlock _fetchCities error: $e');
    }
    if (mounted) setState(() => _countriesLoading = false);
  }

  Future<void> _fetchAreas(int cityId, {String? search}) async {
    setState(() => _countriesLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get('${AppConstants.getAreasUri}$cityId', queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() {
          _areas = data.map((v) => LocationArea.fromJson(v)).toList();
        });
      }
    } catch (e) {
      debugPrint('MixedProductsBlock _fetchAreas error: $e');
    }
    if (mounted) setState(() => _countriesLoading = false);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.settings?['title'] as String? ?? 'Mixed Products';

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (!widget.hideTitle)
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                  ),
                ),
              ],
            ),
          ),

        Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
          child: CategoryHorizontalScroller(
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                CategoryLocationChip(
                  label: _selectedCountry?.name ?? 'Country',
                  onTap: () async {
                    await _fetchCountries();
                    if (mounted) {
                      showDialog(
                        context: context,
                        builder: (_) => SearchableLocationDialog<LocationCountry>(
                          title: 'Select Country',
                          items: _countries,
                          itemLabel: (c) => c.name ?? '',
                          isLoading: _countriesLoading,
                          onSearch: (query) => _fetchCountries(search: query),
                          onSelect: (country) {
                            setState(() {
                              _selectedCountry = country;
                              _selectedCity = null;
                              _selectedArea = null;
                              _cities = [];
                              _areas = [];
                            });
                            if (country != null) _fetchCities(country.id!);
                          },
                        ),
                      );
                    }
                  },
                ),
                const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                CategoryLocationChip(
                  label: _selectedCity?.name ?? 'City',
                  onTap: _selectedCountry == null
                      ? null
                      : () async {
                          await _fetchCities(_selectedCountry!.id!);
                          if (mounted) {
                            showDialog(
                              context: context,
                              builder: (_) => SearchableLocationDialog<LocationCity>(
                                title: 'Select City',
                                items: _cities,
                                itemLabel: (c) => c.name ?? '',
                                isLoading: _countriesLoading,
                                onSearch: (query) => _fetchCities(_selectedCountry!.id!, search: query),
                                onSelect: (city) {
                                  setState(() {
                                    _selectedCity = city;
                                    _selectedArea = null;
                                    _areas = [];
                                  });
                                  if (city != null) _fetchAreas(city.id!);
                                },
                              ),
                            );
                          }
                        },
                ),
                const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                CategoryLocationChip(
                  label: _selectedArea?.name ?? 'Area',
                  onTap: _selectedCity == null
                      ? null
                      : () async {
                          await _fetchAreas(_selectedCity!.id!);
                          if (mounted) {
                            showDialog(
                              context: context,
                              builder: (_) => SearchableLocationDialog<LocationArea>(
                                title: 'Select Area',
                                items: _areas,
                                itemLabel: (a) => a.name ?? '',
                                isLoading: _countriesLoading,
                                onSearch: (query) => _fetchAreas(_selectedCity!.id!, search: query),
                                onSelect: (area) {
                                  setState(() => _selectedArea = area);
                                },
                              ),
                            );
                          }
                        },
                ),
                const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                _buildApplyButton(onPressed: _isLoading ? null : _onApply),
              ],
            ),
          ),
        ),

        const SizedBox(height: Dimensions.paddingSizeSmall),

        Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
          child: TextField(
            controller: _searchController,
            onChanged: _onSearchChanged,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              isDense: true,
              hintText: 'Search products...',
              contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: 10),
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
        ),

        _buildSortDropdown(),

        const SizedBox(height: Dimensions.paddingSizeDefault),

        if (_isInitialLoading)
          const Padding(
            padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_hasError)
          _buildRetryWidget()
        else if (_sortedAndFilteredProducts.isEmpty)
          const CategoryBlockEmptyWidget()
        else
          CategoryBlockProductGrid(
            products: _sortedAndFilteredProducts,
            totalSize: _totalSize,
            isLoadingMore: _isLoading,
            onLoadMore: () => _loadProducts(_offset + 1),
          ),
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

  Widget _buildApplyButton({required VoidCallback? onPressed}) {
    return SizedBox(
      height: 36,
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: Theme.of(context).primaryColor,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          minimumSize: const Size(56, 36),
        ),
        onPressed: onPressed,
        child: Text('Apply', style: textMedium.copyWith(fontSize: Dimensions.fontSizeSmall, color: Colors.white)),
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
          Text('Failed to load products',
            textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ElevatedButton.icon(
            onPressed: _onApply,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}
