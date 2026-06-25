import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';

abstract class ReportRepositoryInterface {
  Future<ApiResponse> getEarningsData(String filterType);

  Future<ApiResponse> getOrderStatistics(String filterType);
}
