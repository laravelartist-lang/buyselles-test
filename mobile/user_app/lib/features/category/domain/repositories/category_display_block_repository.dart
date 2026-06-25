import 'package:flutter/foundation.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/exception/api_error_handler.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';

class CategoryDisplayBlockRepository {
  final DioClient dioClient;
  CategoryDisplayBlockRepository({required this.dioClient});

  Future<ApiResponseModel> getDisplayBlocks(String categoryId) async {
    try {
      final uri = '${AppConstants.categoryDisplayBlocksUri}$categoryId/display-blocks';
      debugPrint('CategoryDisplayBlockRepo: GET $uri?guest_id=1');
      final response = await dioClient.get(uri, queryParameters: {'guest_id': 1});
      debugPrint('CategoryDisplayBlockRepo: response statusCode=${response.statusCode}, data type=${response.data.runtimeType}');
      if (response.data is Map) {
        final data = response.data as Map;
        debugPrint('CategoryDisplayBlockRepo: data keys=${data.keys.toList()}');
        debugPrint('CategoryDisplayBlockRepo: blocks count=${(data['blocks'] as List?)?.length ?? 0}');
      }
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      debugPrint('CategoryDisplayBlockRepo: caught error: $e');
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
