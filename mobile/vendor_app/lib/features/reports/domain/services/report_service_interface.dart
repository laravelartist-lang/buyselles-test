abstract class ReportServiceInterface {
  Future<dynamic> getEarningsData(String filterType);

  Future<dynamic> getOrderStatistics(String filterType);
}
