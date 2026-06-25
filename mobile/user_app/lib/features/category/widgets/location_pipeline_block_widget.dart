import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/product_widget.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/location_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_horizontal_scroller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_location_chip.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/searchable_location_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/shop/domain/models/seller_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/di_container.dart' as di;

class LocationPipelineBlock extends StatefulWidget {
  final int categoryId;
  final Map<String, dynamic>? settings;
  final bool hideTitle;

  const LocationPipelineBlock({
    super.key,
    required this.categoryId,
    this.settings,
    this.hideTitle = false,
  });

  @override
  State<LocationPipelineBlock> createState() => _LocationPipelineBlockState();
}

class _LocationPipelineBlockState extends State<LocationPipelineBlock> {
  LocationCountry? _selectedCountry;
  LocationCity? _selectedCity;
  LocationArea? _selectedArea;

  List<LocationCountry> _countries = [];
  List<LocationCity> _cities = [];
  List<LocationArea> _areas = [];
  bool _locationsLoading = false;

  List<Product> _bestSellingProducts = [];
  List<Seller> _verifiedVendors = [];
  bool _dataLoading = false;
  bool _hasLoadedData = false;
  bool _hasError = false;

  @override
  Widget build(BuildContext context) {
    final title = widget.settings?['title'] as String? ?? 'Discover Local';

    final locationName = _selectedArea?.name ?? _selectedCity?.name ?? _selectedCountry?.name ?? '';

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
                              _bestSellingProducts = [];
                              _verifiedVendors = [];
                              _hasLoadedData = false;
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
                                    _bestSellingProducts = [];
                                    _verifiedVendors = [];
                                    _hasLoadedData = false;
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
                                  setState(() {
                                    _selectedArea = area;
                                    _bestSellingProducts = [];
                                    _verifiedVendors = [];
                                    _hasLoadedData = false;
                                  });
                                },
                              ),
                            );
                          }
                        },
              ),
              const SizedBox(width: Dimensions.paddingSizeExtraSmall),
              _buildApplyButton(
                onPressed: _dataLoading || !_hasAnyLocationSelected ? null : _loadPipelineData,
              ),
            ],
          ),
        ),
        ),

        const SizedBox(height: Dimensions.paddingSizeDefault),

        // Pipeline data sections
        if (_dataLoading)
          const Center(child: CircularProgressIndicator())
        else if (_hasError)
          _buildRetryWidget()
        else if (!_hasLoadedData)
          const CategoryBlockEmptyWidget(
            messageText: 'Select country, city, or area to see local results',
          )
        else if (_hasLoadedData)
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (locationName.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(
                    left: Dimensions.paddingSizeDefault,
                    right: Dimensions.paddingSizeDefault,
                    bottom: Dimensions.paddingSizeSmall,
                  ),
                  child: Container(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                    decoration: BoxDecoration(
                      color: Theme.of(context).primaryColor.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.location_on, size: 18, color: Theme.of(context).primaryColor),
                        const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                        Text(
                          'Showing results for $locationName',
                          style: textRegular.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).primaryColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

              // Section: Best Selling Products
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeDefault,
                ),
                child: Text(
                  'Best Selling Products in $locationName',
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),
              if (_bestSellingProducts.isEmpty)
                const CategoryBlockEmptyWidget()
              else
                SizedBox(
                  height: 240,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    primary: false,
                    physics: const ClampingScrollPhysics(),
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
                    itemCount: _bestSellingProducts.length,
                    itemBuilder: (context, index) {
                      return SizedBox(
                        width: 160,
                        child: ProductWidget(productModel: _bestSellingProducts[index]),
                      );
                    },
                  ),
                ),

              const SizedBox(height: Dimensions.paddingSizeDefault),

              // Section: Verified Merchants
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeDefault,
                ),
                child: Text(
                  'Verified Merchants Operating in $locationName',
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),
              if (_verifiedVendors.isEmpty)
                const CategoryBlockEmptyWidget(messageKey: 'no_vendor_found')
              else
                ..._verifiedVendors.take(5).map((vendor) => _buildVendorCard(vendor)),
            ],
          ),
      ],
    );
  }

  bool get _hasAnyLocationSelected =>
      _selectedCountry != null || _selectedCity != null || _selectedArea != null;

  Future<void> _fetchCountries({String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> queryParams = {'guest_id': 1};
      if (search != null) queryParams['search'] = search;
      final response = await dioClient.get(AppConstants.getCountriesUri, queryParameters: queryParams);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _countries = data.map((v) => LocationCountry.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('LocationPipelineBlock _fetchCountries error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  Future<void> _fetchCities(int countryId, {String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> queryParams = {'guest_id': 1};
      if (search != null) queryParams['search'] = search;
      final response = await dioClient.get('${AppConstants.getCitiesUri}$countryId', queryParameters: queryParams);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _cities = data.map((v) => LocationCity.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('LocationPipelineBlock _fetchCities error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  Future<void> _fetchAreas(int cityId, {String? search}) async {
    setState(() => _locationsLoading = true);
    try {
      final dioClient = di.sl<DioClient>();
      final Map<String, dynamic> queryParams = {'guest_id': 1};
      if (search != null) queryParams['search'] = search;
      final response = await dioClient.get('${AppConstants.getAreasUri}$cityId', queryParameters: queryParams);
      if (response.statusCode == 200 && mounted) {
        final List<dynamic> data = response.data;
        setState(() => _areas = data.map((v) => LocationArea.fromJson(v)).toList());
      }
    } catch (e) {
      debugPrint('LocationPipelineBlock _fetchAreas error: $e');
    }
    if (mounted) setState(() => _locationsLoading = false);
  }

  Future<void> _loadPipelineData() async {
    setState(() {
      _dataLoading = true;
      _hasLoadedData = false;
      _hasError = false;
    });

    try {
      final dioClient = di.sl<DioClient>();

      // Load best selling products
      final Map<String, dynamic> productQuery = {
        'category_id': widget.categoryId,
        'limit': 10,
        'offset': 1,
        'guest_id': 1,
      };
      if (_selectedCountry != null) productQuery['country_id'] = _selectedCountry!.id;
      if (_selectedCity != null) productQuery['city_id'] = _selectedCity!.id;
      if (_selectedArea != null) productQuery['area_id'] = _selectedArea!.id;

      final productResponse = await dioClient.get(
        AppConstants.discoveryProductsUri,
        queryParameters: productQuery,
      );
      if (productResponse.statusCode == 200 && mounted) {
        final List<dynamic> products = productResponse.data['products'] ?? [];
        setState(() {
          _bestSellingProducts = products.map((v) => Product.fromJson(v)).toList();
        });
      }

      // Load verified vendors
      final Map<String, dynamic> vendorQuery = {
        'category_id': widget.categoryId,
        'limit': 10,
        'offset': 1,
        'guest_id': 1,
      };
      if (_selectedCountry != null) vendorQuery['country_id'] = _selectedCountry!.id;
      if (_selectedCity != null) vendorQuery['city_id'] = _selectedCity!.id;
      if (_selectedArea != null) vendorQuery['area_id'] = _selectedArea!.id;

      final vendorResponse = await dioClient.get(
        AppConstants.discoveryVendorsUri,
        queryParameters: vendorQuery,
      );
      if (vendorResponse.statusCode == 200 && mounted) {
        final List<dynamic> sellers = vendorResponse.data['sellers'] ?? [];
        setState(() {
          _verifiedVendors = sellers.map((v) => Seller.fromJson(v)).toList();
        });
      }
    } catch (e) {
      debugPrint('LocationPipelineBlock _loadPipelineData error: $e');
      if (mounted) {
        setState(() {
          _dataLoading = false;
          _hasError = _bestSellingProducts.isEmpty && _verifiedVendors.isEmpty;
          _hasLoadedData = !_hasError;
        });
      }
      return;
    }

    if (mounted) {
      setState(() {
        _dataLoading = false;
        _hasLoadedData = true;
      });
    }
  }

  Widget _buildVendorCard(Seller vendor) {
    return InkWell(
      onTap: () {
        if (vendor.shop?.slug != null) {
          RouterHelper.getTopSellerRoute(
            action: RouteAction.push,
            slug: vendor.shop!.slug!,
            sellerId: vendor.id,
            name: vendor.shop?.name ?? vendor.fName,
            image: vendor.shop?.imageFullUrl?.path ?? vendor.imageFullUrl?.path,
          );
        }
      },
      child: Container(
        margin: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
          vertical: Dimensions.paddingSizeExtraSmall,
        ),
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
                height: 50,
                width: 50,
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
                  if (vendor.averageRating != null && vendor.averageRating! > 0)
                    Row(
                      children: [
                        const Icon(Icons.star, size: 14, color: Colors.orange),
                        const SizedBox(width: 2),
                        Text(
                          vendor.averageRating!.toStringAsFixed(1),
                          style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, size: 20),
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
          Text('Failed to load pipeline data',
            textAlign: TextAlign.center,
            style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ElevatedButton.icon(
            onPressed: _loadPipelineData,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}
