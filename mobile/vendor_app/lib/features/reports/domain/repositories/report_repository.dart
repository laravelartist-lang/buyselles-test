import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/reports/domain/repositories/report_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class ReportRepository implements ReportRepositoryInterface {
  final DioClient dioClient;

  ReportRepository({required this.dioClient});

  @override
  Future<ApiResponse> getEarningsData(String filterType) async {
    try {
      final response = await dioClient.get('${AppConstants.chartFilterData}$filterType');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> getOrderStatistics(String filterType) async {
    try {
      final response = await dioClient.get('${AppConstants.businessAnalytics}$filterType');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
