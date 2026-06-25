import 'package:sixvalley_vendor_app/features/reports/domain/repositories/report_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/reports/domain/services/report_service_interface.dart';

class ReportService implements ReportServiceInterface {
  final ReportRepositoryInterface reportRepositoryInterface;

  ReportService({required this.reportRepositoryInterface});

  @override
  Future getEarningsData(String filterType) {
    return reportRepositoryInterface.getEarningsData(filterType);
  }

  @override
  Future getOrderStatistics(String filterType) {
    return reportRepositoryInterface.getOrderStatistics(filterType);
  }
}
