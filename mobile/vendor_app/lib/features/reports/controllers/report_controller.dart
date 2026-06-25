import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/reports/domain/models/report_models.dart';
import 'package:sixvalley_vendor_app/features/reports/domain/services/report_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';

class ReportController extends ChangeNotifier {
  final ReportServiceInterface reportServiceInterface;

  ReportController({required this.reportServiceInterface});

  static const List<String> filterTypes = ['this_week', 'this_month', 'this_year'];

  EarningsReportModel? _earningsReport;
  EarningsReportModel? get earningsReport => _earningsReport;

  OrderStatisticsModel? _orderStatistics;
  OrderStatisticsModel? get orderStatistics => _orderStatistics;

  int _earningsFilterIndex = 0;
  int get earningsFilterIndex => _earningsFilterIndex;

  int _ordersFilterIndex = 0;
  int get ordersFilterIndex => _ordersFilterIndex;

  bool _isEarningsLoading = false;
  bool get isEarningsLoading => _isEarningsLoading;

  bool _isOrdersLoading = false;
  bool get isOrdersLoading => _isOrdersLoading;

  String _earningsApiType(String filterName) {
    switch (filterName) {
      case 'this_year':
        return 'yearEarn';
      case 'this_month':
        return 'MonthEarn';
      default:
        return 'WeekEarn';
    }
  }

  void setEarningsFilterIndex(int index) {
    _earningsFilterIndex = index;
    notifyListeners();
  }

  void setOrdersFilterIndex(int index) {
    _ordersFilterIndex = index;
    _orderStatistics = null;
    notifyListeners();
  }

  Future<void> loadEarningsReport(String filterName) async {
    _isEarningsLoading = true;
    notifyListeners();

    final ApiResponse response = await reportServiceInterface.getEarningsData(_earningsApiType(filterName));
    if (response.response?.statusCode == 200 && response.response?.data != null) {
      _earningsReport = EarningsReportModel.fromApiData(response.response!.data);
    } else {
      ApiChecker.checkApi(response);
    }

    _isEarningsLoading = false;
    notifyListeners();
  }

  Future<void> loadOrderStatistics(String filterName) async {
    _isOrdersLoading = true;
    notifyListeners();

    final ApiResponse response = await reportServiceInterface.getOrderStatistics(filterName);
    if (response.response?.statusCode == 200) {
      _orderStatistics = OrderStatisticsModel.fromJson(response.response!.data);
    } else {
      ApiChecker.checkApi(response);
    }

    _isOrdersLoading = false;
    notifyListeners();
  }
}
