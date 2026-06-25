import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/location_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_horizontal_scroller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_location_chip.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/searchable_location_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/features/shop/domain/models/seller_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/di_container.dart' as di;

class VendorsListBlock extends StatefulWidget {
  final int categoryId;
  final Map<String, dynamic>? settings;
  final bool hideTitle;
  final void Function(int vendorId, String vendorName)? onVendorTap;

  const VendorsListBlock({
    super.key,
    required this.categoryId,
    this.settings,
    this.hideTitle = false,
    this.onVendorTap,
  });

  @override
  State<VendorsListBlock> createState() => _VendorsListBlockState();
}

class _VendorsListBlockState extends State<VendorsListBlock> {
  final TextEditingController _searchController = TextEditingController();

  List<Seller> _vendors = [];
  int? _totalSize;
  int _offset = 1;
  bool _isLoading = false;
  bool _isInitialLoading = true;
  bool _hasError = false;

  LocationCountry? _selectedCountry;
  LocationCity? _selectedCity;
  LocationArea? _selectedArea;

  List<LocationCountry> _countries = [];
  List<LocationCity> _cities = [];
  List<LocationArea> _areas = [];
  bool _locationsLoading = false;

  @override
  void initState() {
    super.initState();
    _loadVendors(1);
  }

  Future<void> _loadVendors(int offset) async {
    if (_isLoading) return;
    setState(() { _isLoading = true; if (offset == 1) _hasError = false; });

    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> query = {
        'category_id': widget.categoryId,
        'limit': 10,
        'offset': offset,
        'guest_id': 1,
      };
      if (_selectedCountry != null) query['country_id'] = _selectedCountry!.id;
      if (_selectedCity != null) query['city_id'] = _selectedCity!.id;
      if (_selectedArea != null) query['area_id'] = _selectedArea!.id;
      if (_searchController.text.trim().isNotEmpty) query['search'] = _searchController.text.trim();

      final response = await dioClient.get(AppConstants.discoveryVendorsUri, queryParameters: query);
      if (response.statusCode == 200 && mounted) {
        final data = response.data;
        final List<dynamic> sellerList = data['sellers'] ?? [];
        setState(() {
          if (offset == 1) {
            _vendors = sellerList.map((v) => Seller.fromJson(v)).toList();
          } else {
            _vendors.addAll(sellerList.map((v) => Seller.fromJson(v)));
          }
          _totalSize = data['total_size'];
          _offset = offset;
          _isInitialLoading = false;
        });
      } else {
        if (mounted) setState(() { _isInitialLoading = false; _hasError = offset == 1; });
      }
    } catch (e) {
      debugPrint('VendorsListBlock _loadVendors error: $e');
      if (mounted) setState(() { _isInitialLoading = false; _hasError = offset == 1; });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _onSearch() {
    setState(() {
      _vendors = [];
      _totalSize = null;
      _offset = 1;
    });
    _loadVendors(1);
  }

  Future<void> _fetchCountries({String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get(AppConstants.getCountriesUri, queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _countries = data.map((v) => LocationCountry.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('VendorsListBlock _fetchCountries error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  Future<void> _fetchCities(int countryId, {String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get('${AppConstants.getCitiesUri}$countryId', queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _cities = data.map((v) => LocationCity.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('VendorsListBlock _fetchCities error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  Future<void> _fetchAreas(int cityId, {String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> params = {'guest_id': 1};
      if (search != null && search.isNotEmpty) params['search'] = search;
      final response = await dioClient.get('${AppConstants.getAreasUri}$cityId', queryParameters: params);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _areas = data.map((v) => LocationArea.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('VendorsListBlock _fetchAreas error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.settings?['title'] as String? ?? 'Vendors';

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
            child: Text(
              title,
              style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge),
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
                          isLoading: _locationsLoading,
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
                                isLoading: _locationsLoading,
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
                                isLoading: _locationsLoading,
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
              _buildApplyButton(onPressed: _isLoading ? null : _onSearch),
            ],
          ),
        ),
        ),

        const SizedBox(height: Dimensions.paddingSizeSmall),

        // Search bar
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
          child: TextFormField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            onFieldSubmitted: (_) => _onSearch(),
            decoration: InputDecoration(
              isDense: true,
              hintText: 'Search vendors...',
              contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: 10),
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searchController.text.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        _onSearch();
                      },
                    )
                  : null,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
              ),
            ),
          ),
        ),

        const SizedBox(height: Dimensions.paddingSizeDefault),

        // Vendors list
        if (_isInitialLoading)
          const Padding(
            padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_hasError)
          _buildRetryWidget()
        else if (_vendors.isEmpty)
          const CategoryBlockEmptyWidget(messageKey: 'no_vendor_found')
        else
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
            child: Column(
              children: [
                ..._vendors.map((vendor) => _buildVendorCard(vendor)),
                if (_vendors.length < (_totalSize ?? 0))
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeDefault),
                    child: TextButton(
                      onPressed: () => _loadVendors(_offset + 1),
                      child: Text(
                        'Load More',
                        style: textBold.copyWith(color: Theme.of(context).primaryColor),
                      ),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _buildVendorCard(Seller vendor) {
    final vendorId = vendor.id ?? vendor.shop?.sellerId;

    return InkWell(
      onTap: widget.onVendorTap != null && vendorId != null
          ? () {
              widget.onVendorTap!(
                vendorId,
                vendor.shop?.name ?? vendor.fName ?? '',
              );
            }
          : null,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeExtraSmall),
        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          border: Border.all(color: Theme.of(context).primaryColor.withValues(alpha: 0.1)),
          color: Theme.of(context).highlightColor,
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
              child: CustomImageWidget(
                image: vendor.shop?.imageFullUrl?.path ?? vendor.imageFullUrl?.path ?? '',
                height: 60,
                width: 60,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(width: Dimensions.paddingSizeDefault),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    vendor.shop?.name ?? vendor.fName ?? '',
                    style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  if (vendor.averageRating != null && vendor.averageRating! > 0)
                    Row(
                      children: [
                        const Icon(Icons.star, size: 14, color: Colors.orange),
                        const SizedBox(width: 2),
                        Text(
                          '${vendor.averageRating!.toStringAsFixed(1)} (${vendor.ratingCount ?? 0})',
                          style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                        ),
                      ],
                    ),
                  if (vendor.productCount != null)
                    Text(
                      '${vendor.productCount} products',
                      style: textRegular.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).hintColor,
                      ),
                    ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
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
          Text('Failed to load vendors',
            textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ElevatedButton.icon(
            onPressed: _onSearch,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}
