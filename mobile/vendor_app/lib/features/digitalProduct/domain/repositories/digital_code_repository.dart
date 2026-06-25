import 'dart:io';
import 'package:dio/dio.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/repositories/digital_code_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class DigitalCodeRepository implements DigitalCodeRepositoryInterface {
  final DioClient dioClient;

  DigitalCodeRepository({required this.dioClient});

  @override
  Future<ApiResponse> downloadBulkTemplate() async {
    try {
      final response = await dioClient.get(
        AppConstants.digitalCodeBulkTemplate,
        options: Options(responseType: ResponseType.bytes),
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> uploadBulkImport(String filePath) async {
    try {
      final file = File(filePath);
      final fileName = file.path.split('/').last;
      final formData = FormData.fromMap({
        'excel_file': await MultipartFile.fromFile(filePath, filename: fileName),
      });
      final response = await dioClient.post(AppConstants.digitalCodeBulkUpload, data: formData);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> getProductCodes(int productId, {int? limit, int? offset}) async {
    try {
      final uri = '${AppConstants.digitalCodeList}/$productId/digital-codes'
          '?limit=${limit ?? 50}&offset=${offset ?? 1}';
      final response = await dioClient.get(uri);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> downloadProductTemplate(int productId) async {
    try {
      final uri = '${AppConstants.digitalCodeProductTemplate}/$productId/digital-codes/template';
      final response = await dioClient.get(
        uri,
        options: Options(responseType: ResponseType.bytes),
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> uploadProductImport(int productId, String filePath) async {
    try {
      final file = File(filePath);
      final fileName = file.path.split('/').last;
      final formData = FormData.fromMap({
        'excel_file': await MultipartFile.fromFile(filePath, filename: fileName),
      });
      final uri = '${AppConstants.digitalCodeProductImport}/$productId/digital-codes/import';
      final response = await dioClient.post(uri, data: formData);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> addSingleCode(int productId, String code, {String? serialNumber, String? expiryDate}) async {
    try {
      final data = <String, dynamic>{
        'code': code,
      };
      if (serialNumber != null && serialNumber.isNotEmpty) {
        data['serial_number'] = serialNumber;
      }
      if (expiryDate != null && expiryDate.isNotEmpty) {
        data['expiry_date'] = expiryDate;
      }
      final uri = '${AppConstants.digitalCodeManualAdd}/$productId/digital-codes/manual';
      final response = await dioClient.post(uri, data: data);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> toggleCodeStatus(int codeId) async {
    try {
      final uri = '${AppConstants.digitalCodeToggleStatus}/$codeId/toggle-status';
      final response = await dioClient.post(uri);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> decryptCode(int codeId) async {
    try {
      final uri = '${AppConstants.digitalCodeDecrypt}/$codeId/decrypt';
      final response = await dioClient.get(uri);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> deleteCode(int codeId) async {
    try {
      final uri = '${AppConstants.digitalCodeDelete}/$codeId';
      final response = await dioClient.delete(uri);
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
