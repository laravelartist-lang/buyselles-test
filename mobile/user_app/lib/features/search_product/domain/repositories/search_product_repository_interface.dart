import 'package:flutter_sixvalley_ecommerce/interface/repo_interface.dart';

abstract class SearchProductRepositoryInterface implements RepositoryInterface{

  Future<dynamic> getSearchProductList(String query, String? categoryIds, String? brandIds, String? authorIds, String? publishingIds, String? sort, String? priceMin, String? priceMax, int offset, String? productType, {int? countryId, int? cityId, int? areaId});
  Future<dynamic> getSearchProductName(String name);
  Future<dynamic> saveSearchProductName(String searchAddress);
  List<String> getSavedSearchProductName();
  Future<bool> clearSavedSearchProductName();
  Future<dynamic> getAuthorList(String? slug);
  Future<dynamic> getPublishingHouse(String? slug);
  Future<dynamic> getCountries({String? search});
  Future<dynamic> getCities(int countryId, {String? search});
  Future<dynamic> getAreas(int cityId, {String? search});
}