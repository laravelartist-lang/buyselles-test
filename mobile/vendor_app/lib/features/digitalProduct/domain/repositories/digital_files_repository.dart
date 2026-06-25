import 'dart:io';
import 'package:dio/dio.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/repositories/digital_files_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class DigitalFilesRepository implements DigitalFilesRepositoryInterface {
  final DioClient dioClient;

  DigitalFilesRepository({required this.dioClient});

  @override
  Future<ApiResponse> getProductDetails(int productId) async {
    try {
      final response = await dioClient.get('${AppConstants.productDetails}$productId');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> uploadDigitalFile(int productId, String filePath) async {
    try {
      final file = File(filePath);
      final fileName = file.path.split('/').last;
      final formData = FormData.fromMap({
        'product_id': productId.toString(),
        'digital_file_ready': await MultipartFile.fromFile(filePath, filename: fileName),
      });
      final response = await dioClient.post(AppConstants.digitalProductUpload, data: formData);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> deleteDigitalFile(int productId) async {
    try {
      final response = await dioClient.post(
        AppConstants.deleteDigitalProductVariationFile,
        data: {'product_id': productId.toString()},
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future add(value) => throw UnimplementedError();

  @override
  Future delete(int id) => throw UnimplementedError();

  @override
  Future get(String id) => throw UnimplementedError();

  @override
  Future getList({int? offset = 1}) => throw UnimplementedError();

  @override
  Future update(Map<String, dynamic> body, int id) => throw UnimplementedError();
}
