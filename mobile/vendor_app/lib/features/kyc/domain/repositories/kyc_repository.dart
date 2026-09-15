import 'package:dio/dio.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/repositories/kyc_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class KycRepository implements KycRepositoryInterface {
  final DioClient? dioClient;

  KycRepository({required this.dioClient});

  @override
  Future<ApiResponse> getStatus() async {
    try {
      final Response response = await dioClient!.get(AppConstants.kycStatusUri);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> getLaunchUrl() async {
    try {
      final Response response =
          await dioClient!.get(AppConstants.kycLaunchUrlUri);
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future add(value) {
    throw UnimplementedError();
  }

  @override
  Future delete(int id) {
    throw UnimplementedError();
  }

  @override
  Future get(String id) {
    throw UnimplementedError();
  }

  @override
  Future getList({int? offset = 1}) {
    throw UnimplementedError();
  }

  @override
  Future update(Map<String, dynamic> body, int id) {
    throw UnimplementedError();
  }
}
