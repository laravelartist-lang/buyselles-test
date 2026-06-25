import 'package:dio/dio.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/exception/api_error_handler.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/repositories/dispute_repository_interface.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as path;
import 'package:provider/provider.dart';

class DisputeRepository implements DisputeRepositoryInterface {
  final DioClient? dioClient;

  DisputeRepository({required this.dioClient});

  @override
  Future<dynamic> add(value) async {
    throw UnimplementedError();
  }

  @override
  Future<dynamic> update(Map<String, dynamic> body, int id) async {
    throw UnimplementedError();
  }

  @override
  Future<dynamic> delete(int id) async {
    throw UnimplementedError();
  }

  @override
  Future<ApiResponseModel> getList({int? offset = 1}) async {
    try {
      final response = await dioClient!.get(AppConstants.disputesUri);
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<dynamic> get(String id) async {
    try {
      final response = await dioClient!.get('${AppConstants.disputesUri}/$id');
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> getReasons() async {
    try {
      final response = await dioClient!.get(AppConstants.disputeReasonsUri);
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> getDisputes() async {
    return getList();
  }

  @override
  Future<ApiResponseModel> getDispute(int id) async {
    try {
      final response = await dioClient!.get('${AppConstants.disputesUri}/$id');
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> createDispute({
    required int orderId,
    int? reasonId,
    required String description,
    List<XFile>? files,
  }) async {
    try {
      final List<XFile> selectedFiles = files ?? [];
      final bool hasFiles = selectedFiles.isNotEmpty;

      final dynamic payload;
      if (hasFiles) {
        payload = FormData.fromMap({
          'order_id': orderId.toString(),
          'description': description,
          if (reasonId != null) 'reason_id': reasonId.toString(),
          'files[]': await Future.wait(
            selectedFiles.map(
              (file) => MultipartFile.fromFile(
                file.path,
                filename: path.basename(file.path),
              ),
            ),
          ),
        });
      } else {
        payload = {
          'order_id': orderId,
          'description': description,
          if (reasonId != null) 'reason_id': reasonId,
        };
      }

      final response =
          await dioClient!.post(AppConstants.disputesUri, data: payload);
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> addMessage(int disputeId, String message) async {
    try {
      final uri = AppConstants.disputeMessageUri
          .replaceFirst('{id}', disputeId.toString());
      final response = await dioClient!.post(uri, data: {'message': message});
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<http.StreamedResponse> uploadEvidence(
      int disputeId, List<XFile> files) async {
    final uri = AppConstants.disputeEvidenceUri
        .replaceFirst('{id}', disputeId.toString());
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConstants.baseUrl}$uri'),
    );
    request.headers['Authorization'] =
        'Bearer ${Provider.of<AuthController>(Get.context!, listen: false).getUserToken()}';

    for (final file in files) {
      final bytes = await file.readAsBytes();
      final lowerName = file.name.toLowerCase();
      final lowerPath = file.path.toLowerCase();
      final isVideo = lowerName.endsWith('.mp4') || lowerPath.endsWith('.mp4');
      final isPng = lowerName.endsWith('.png') || lowerPath.endsWith('.png');

      final part = http.MultipartFile(
        'files[]',
        Stream.value(bytes),
        bytes.length,
        filename: path.basename(file.path),
        contentType: isVideo
            ? MediaType('video', 'mp4')
            : (isPng ? MediaType('image', 'png') : MediaType('image', 'jpeg')),
      );
      request.files.add(part);
    }

    return request.send();
  }

  @override
  Future<ApiResponseModel> escalate(int disputeId) async {
    try {
      final uri = AppConstants.disputeEscalateUri
          .replaceFirst('{id}', disputeId.toString());
      final response = await dioClient!.post(uri, data: {});
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> confirmClosure(int disputeId) async {
    try {
      final uri = AppConstants.disputeConfirmClosureUri
          .replaceFirst('{id}', disputeId.toString());
      final response = await dioClient!.post(uri, data: {});
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponseModel> confirmReceipt(int orderId) async {
    try {
      final uri = AppConstants.confirmReceiptUri
          .replaceFirst('{orderId}', orderId.toString());
      final response = await dioClient!.post(uri, data: {});
      return ApiResponseModel.withSuccess(response);
    } catch (e) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
