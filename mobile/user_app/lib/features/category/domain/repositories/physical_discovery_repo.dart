import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/exception/api_error_handler.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';

class PhysicalDiscoveryRepository {
  final DioClient dioClient;
  PhysicalDiscoveryRepository({required this.dioClient});

  Future<ApiResponseModel> getCountries({String? search}) async {
    try {
      final response = await dioClient.get('${AppConstants.getCountriesUri}${search != null ? '?search=$search' : ''}');
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  Future<ApiResponseModel> getCities(int countryId, {String? search}) async {
    try {
      final response = await dioClient.get('${AppConstants.getCitiesUri}$countryId${search != null ? '?search=$search' : ''}');
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  Future<ApiResponseModel> getAreas(int cityId, {String? search}) async {
    try {
      final response = await dioClient.get('${AppConstants.getAreasUri}$cityId${search != null ? '?search=$search' : ''}');
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  Future<ApiResponseModel> getDiscoveryVendors({int? countryId, int? cityId, int? areaId, int limit = 10, int offset = 1}) async {
    try {
      Map<String, dynamic> queryParameters = {
        'limit': limit,
        'offset': offset,
      };
      if (countryId != null) queryParameters['country_id'] = countryId;
      if (cityId != null) queryParameters['city_id'] = cityId;
      if (areaId != null) queryParameters['area_id'] = areaId;

      final response = await dioClient.get(AppConstants.discoveryVendorsUri, queryParameters: queryParameters);
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  Future<ApiResponseModel> getDiscoveryProducts({int? countryId, int? cityId, int? areaId, int? categoryId, int limit = 10, int offset = 1}) async {
    try {
      Map<String, dynamic> queryParameters = {
        'limit': limit,
        'offset': offset,
      };
      if (countryId != null) queryParameters['country_id'] = countryId;
      if (cityId != null) queryParameters['city_id'] = cityId;
      if (areaId != null) queryParameters['area_id'] = areaId;
      if (categoryId != null) queryParameters['category_id'] = categoryId;

      final response = await dioClient.get(AppConstants.discoveryProductsUri, queryParameters: queryParameters);
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
