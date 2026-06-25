import 'package:flutter_sixvalley_ecommerce/features/search_product/domain/repositories/search_product_repository_interface.dart';
import 'package:flutter_sixvalley_ecommerce/features/search_product/domain/services/search_product_service_interface.dart';

class SearchProductService implements SearchProductServiceInterface{
  SearchProductRepositoryInterface searchProductRepositoryInterface;
  SearchProductService({required this.searchProductRepositoryInterface});

  @override
  Future<bool> clearSavedSearchProductName() async{
    return searchProductRepositoryInterface.clearSavedSearchProductName();
  }

  @override
  List<String> getSavedSearchProductName(){
    return searchProductRepositoryInterface.getSavedSearchProductName();
  }

  @override
  Future getSearchProductList(String query, String? categoryIds, String? brandIds, String? authorIds, String? publishingIds, String? sort, String? priceMin, String? priceMax, int offset, String? productType, {int? countryId, int? cityId, int? areaId}) async{
    return await searchProductRepositoryInterface.getSearchProductList(query, categoryIds, brandIds, authorIds, publishingIds, sort, priceMin, priceMax, offset, productType, countryId: countryId, cityId: cityId, areaId: areaId);
  }

  @override
  Future getSearchProductName(String name) async{
    return searchProductRepositoryInterface.getSearchProductName(name);
  }

  @override
  Future saveSearchProductName(String searchAddress) async{
    return await searchProductRepositoryInterface.saveSearchProductName(searchAddress);
  }

  @override
  Future getAuthorList(String? slug){
    return searchProductRepositoryInterface.getAuthorList(slug);
  }

  @override
  Future getPublishingHouse(String? slug) {
    return searchProductRepositoryInterface.getPublishingHouse(slug);
  }

  @override
  Future getCountries({String? search}) {
    return searchProductRepositoryInterface.getCountries(search: search);
  }

  @override
  Future getCities(int countryId, {String? search}) {
    return searchProductRepositoryInterface.getCities(countryId, search: search);
  }

  @override
  Future getAreas(int cityId, {String? search}) {
    return searchProductRepositoryInterface.getAreas(cityId, search: search);
  }
}