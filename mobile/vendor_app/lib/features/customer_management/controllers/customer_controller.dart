import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/customer_management/domain/models/customer_model.dart';
import 'package:sixvalley_vendor_app/features/customer_management/domain/services/customer_service_interface.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';

class CustomerManagementController extends ChangeNotifier {
  final CustomerServiceInterface customerServiceInterface;

  CustomerManagementController({required this.customerServiceInterface});

  List<CustomerModel>? _customers;
  List<CustomerModel>? get customers => _customers;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isSaving = false;
  bool get isSaving => _isSaving;

  Future<void> loadCustomers({String query = ''}) async {
    _isLoading = true;
    _customers = null;
    notifyListeners();

    final ApiResponse response = query.isEmpty
        ? await customerServiceInterface.getCustomerList('')
        : await customerServiceInterface.searchCustomers(query);

    if (response.response?.statusCode == 200) {
      _customers = CustomerListModel.fromJson(response.response!.data).customers ?? [];
    } else {
      _customers = [];
      ApiChecker.checkApi(response);
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> addCustomer(BuildContext context, CustomerBody customerBody) async {
    _isSaving = true;
    notifyListeners();

    final ApiResponse response = await customerServiceInterface.addCustomer(customerBody);
    _isSaving = false;

    if (response.response?.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('customer_added_successfully', context) ?? 'Customer added successfully',
        context,
        isError: false,
      );
      notifyListeners();
      return true;
    }

    ApiChecker.checkApi(response);
    notifyListeners();
    return false;
  }
}
