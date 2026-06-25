import 'dart:io';
import 'package:dio/dio.dart';
import 'package:http/http.dart' as http;
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/repositories/dispute_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class DisputeRepository implements DisputeRepositoryInterface {
  final DioClient? dioClient;

  DisputeRepository({required this.dioClient});

  @override
  Future<ApiResponse> getDisputes({String? status, int page = 1}) async {
    try {
      final response = await dioClient!.get(
        '${AppConstants.disputeListUri}?page=$page${status != null && status != 'all' ? '&status=$status' : ''}',
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> getDisputeDetail(int id) async {
    try {
      final response = await dioClient!.get('${AppConstants.disputeDetailUri}$id');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> sendMessage(int id, String message) async {
    try {
      final response = await dioClient!.post(
        '${AppConstants.disputeMessageUri}$id/message',
        data: {'message': message},
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> uploadEvidence(int id, List<File> files) async {
    try {
      final uri = '${AppConstants.disputeEvidenceUri}$id/evidence';
      final token = dioClient!.token;

      final request = http.MultipartRequest('POST', Uri.parse('${AppConstants.baseUrl}$uri'));
      request.headers.addAll({'Authorization': 'Bearer $token'});

      for (final file in files) {
        request.files.add(await http.MultipartFile.fromPath('files[]', file.path));
      }

      final streamedResponse = await request.send();
      final responseBody = await http.Response.fromStream(streamedResponse);

      return ApiResponse.withSuccess(
        Response(
          statusCode: streamedResponse.statusCode,
          requestOptions: RequestOptions(path: uri),
          statusMessage: streamedResponse.reasonPhrase,
          data: responseBody.body.isNotEmpty ? responseBody.body : null,
        ),
      );
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> escalate(int id) async {
    try {
      final response = await dioClient!.post('${AppConstants.disputeEscalateUri}$id/escalate');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
