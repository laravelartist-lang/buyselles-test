import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/location_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/repositories/physical_discovery_repo.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/shop/domain/models/seller_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';

enum DiscoveryStage { global, country, city, area }

class PhysicalDiscoveryController extends ChangeNotifier {
  final PhysicalDiscoveryRepository physicalDiscoveryRepo;
  PhysicalDiscoveryController({required this.physicalDiscoveryRepo});

  DiscoveryStage _currentStage = DiscoveryStage.global;
  DiscoveryStage get currentStage => _currentStage;

  LocationCountry? _selectedCountry;
  LocationCountry? get selectedCountry => _selectedCountry;

  LocationCity? _selectedCity;
  LocationCity? get selectedCity => _selectedCity;

  LocationArea? _selectedArea;
  LocationArea? get selectedArea => _selectedArea;

  List<LocationCountry> _countries = [];
  List<LocationCountry> get countries => _countries;

  List<LocationCity> _cities = [];
  List<LocationCity> get cities => _cities;

  List<LocationArea> _areas = [];
  List<LocationArea> get areas => _areas;

  List<Seller> _vendors = [];
  List<Seller> get vendors => _vendors;

  List<Product> _products = [];
  List<Product> get products => _products;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  int? _currentCategoryId;

  Future<void> fetchCountries({String? search}) async {
    // For a full-list reload (no search term), clear immediately so the dialog
    // shows a spinner right away instead of stale search-filtered results.
    final isFullReload = search == null || search.isEmpty;
    if (isFullReload) _countries = [];
    _isLoading = true;
    notifyListeners();
    final response =
        await physicalDiscoveryRepo.getCountries(search: isFullReload ? null : search);
    if (response.response != null && response.response!.statusCode == 200) {
      _countries = [];
      response.response!.data.forEach((v) {
        _countries.add(LocationCountry.fromJson(v));
      });
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchCities(int countryId, {String? search}) async {
    final isFullReload = search == null || search.isEmpty;
    if (isFullReload) _cities = [];
    _isLoading = true;
    notifyListeners();
    final response = await physicalDiscoveryRepo.getCities(countryId,
        search: isFullReload ? null : search);
    if (response.response != null && response.response!.statusCode == 200) {
      _cities = [];
      response.response!.data.forEach((v) {
        _cities.add(LocationCity.fromJson(v));
      });
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchAreas(int cityId, {String? search}) async {
    final isFullReload = search == null || search.isEmpty;
    if (isFullReload) _areas = [];
    _isLoading = true;
    notifyListeners();
    final response = await physicalDiscoveryRepo.getAreas(cityId,
        search: isFullReload ? null : search);
    if (response.response != null && response.response!.statusCode == 200) {
      _areas = [];
      response.response!.data.forEach((v) {
        _areas.add(LocationArea.fromJson(v));
      });
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchDiscoveryVendors() async {
    _isLoading = true;
    notifyListeners();
    final response = await physicalDiscoveryRepo.getDiscoveryVendors(
      countryId: _selectedCountry?.id,
      cityId: _selectedCity?.id,
      areaId: _selectedArea?.id,
    );
    if (response.response != null && response.response!.statusCode == 200) {
      _vendors = [];
      response.response!.data['sellers'].forEach((v) {
        _vendors.add(Seller.fromJson(v));
      });
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchDiscoveryProducts({int? categoryId}) async {
    if (categoryId != null) _currentCategoryId = categoryId;
    _isLoading = true;
    notifyListeners();
    final response = await physicalDiscoveryRepo.getDiscoveryProducts(
      countryId: _selectedCountry?.id,
      cityId: _selectedCity?.id,
      areaId: _selectedArea?.id,
      categoryId: _currentCategoryId,
    );
    if (response.response != null && response.response!.statusCode == 200) {
      _products = [];
      response.response!.data['products'].forEach((v) {
        _products.add(Product.fromJson(v));
      });
    }
    _isLoading = false;
    notifyListeners();
  }

  void setCountry(LocationCountry? country) {
    _selectedCountry = country;
    _selectedCity = null;
    _selectedArea = null;
    _cities = [];
    _areas = [];
    if (country == null) {
      _currentStage = DiscoveryStage.global;
    } else {
      _currentStage = DiscoveryStage.country;
      fetchCities(country.id!);
    }
    fetchDiscoveryProducts();
    notifyListeners();
  }

  void setCity(LocationCity city) {
    _selectedCity = city;
    _selectedArea = null;
    _areas = [];
    _currentStage = DiscoveryStage.city;
    fetchAreas(city.id!);
    fetchDiscoveryVendors();
    notifyListeners();
  }

  void setArea(LocationArea area) {
    _selectedArea = area;
    _currentStage = DiscoveryStage.area;
    fetchDiscoveryVendors();
    notifyListeners();
  }

  void resetToGlobal() {
    _selectedCountry = null;
    _selectedCity = null;
    _selectedArea = null;
    _cities = [];
    _areas = [];
    _currentStage = DiscoveryStage.global;
    fetchDiscoveryProducts();
    notifyListeners();
  }

  void clearFilters() {
    _selectedCountry = null;
    _selectedCity = null;
    _selectedArea = null;
    _cities = [];
    _areas = [];
    _vendors = [];
    _products = [];
    _currentStage = DiscoveryStage.global;
    _currentCategoryId = null;
  }

  void navigateToVendorInventory(BuildContext context, Seller vendor, CategoryModel mainCategory) {
    final customCategory = CategoryModel(
      id: mainCategory.id ?? 1,
      name: '${vendor.shop?.name ?? vendor.fName ?? 'Vendor'} Stock',
      slug: mainCategory.slug,
      icon: mainCategory.icon,
      imageFullUrl: vendor.shop?.imageFullUrl ?? mainCategory.imageFullUrl,
      subCategories: mainCategory.subCategories,
    );

    RouterHelper.getBrandCategoryRoute(
      action: RouteAction.push,
      isBrand: false,
      id: customCategory.id,
      name: customCategory.name,
      categoryModel: customCategory,
      sellerId: vendor.id.toString(),
    );
  }
}
