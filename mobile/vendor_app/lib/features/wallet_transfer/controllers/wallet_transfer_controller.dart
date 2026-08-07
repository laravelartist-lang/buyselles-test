import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/profile/controllers/profile_controller.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/models/wallet_transfer_model.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/services/wallet_transfer_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';
import 'package:sixvalley_vendor_app/helper/debounce_helper.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';

class WalletTransferController extends ChangeNotifier {
  WalletTransferController({required this.walletTransferServiceInterface});

  final WalletTransferServiceInterface walletTransferServiceInterface;
  final DebounceHelper _debounce = DebounceHelper(milliseconds: 400);

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isSearching = false;
  bool get isSearching => _isSearching;

  bool _isSubmitting = false;
  bool get isSubmitting => _isSubmitting;

  double? _totalEarning;
  double? get totalEarning => _totalEarning;

  double? _withdrawableBalance;
  double? get withdrawableBalance => _withdrawableBalance;

  List<WalletTransferItemModel> _transfers = [];
  List<WalletTransferItemModel> get transfers => _transfers;

  List<WalletTransferCustomerModel> _searchResults = [];
  List<WalletTransferCustomerModel> get searchResults => _searchResults;

  WalletTransferCustomerModel? _selectedCustomer;
  WalletTransferCustomerModel? get selectedCustomer => _selectedCustomer;

  Future<void> loadTransferData({bool reload = true}) async {
    if (reload) {
      _isLoading = true;
      notifyListeners();
    }

    final ApiResponse response = await walletTransferServiceInterface.getTransferList();
    if (response.response?.statusCode == 200) {
      final model = WalletTransferListModel.fromJson(response.response!.data);
      _totalEarning = model.totalEarning;
      _withdrawableBalance = model.withdrawableBalance;
      _transfers = model.transfers ?? [];
    } else {
      ApiChecker.checkApi(response);
    }

    _isLoading = false;
    notifyListeners();
  }

  void searchCustomers(String term) {
    _debounce.run(() async {
      if (term.trim().isEmpty) {
        _searchResults = [];
        _isSearching = false;
        notifyListeners();
        return;
      }

      _isSearching = true;
      notifyListeners();

      final ApiResponse response = await walletTransferServiceInterface.searchCustomers(term.trim());
      if (response.response?.statusCode == 200) {
        _searchResults = [];
        for (final item in response.response!.data['customers'] ?? []) {
          _searchResults.add(WalletTransferCustomerModel.fromJson(item));
        }
      } else {
        _searchResults = [];
        ApiChecker.checkApi(response);
      }

      _isSearching = false;
      notifyListeners();
    });
  }

  void selectCustomer(WalletTransferCustomerModel customer) {
    _selectedCustomer = customer;
    _searchResults = [];
    notifyListeners();
  }

  void clearSelectedCustomer() {
    _selectedCustomer = null;
    notifyListeners();
  }

  Future<bool> submitTransfer(String amount, {String? reference}) async {
    if (_selectedCustomer?.id == null) {
      return false;
    }

    _isSubmitting = true;
    notifyListeners();

    final ApiResponse response = await walletTransferServiceInterface.transfer(
      _selectedCustomer!.id!,
      amount,
      reference: reference,
    );

    _isSubmitting = false;

    if (response.response?.statusCode == 200) {
      final data = response.response!.data;
      if (data['total_earning'] != null) {
        _totalEarning = _parseDouble(data['total_earning']);
        _withdrawableBalance = _totalEarning;
      }
      _selectedCustomer = null;
      showCustomSnackBarWidget(
        data['message']?.toString() ?? getTranslated('balance_transferred_successfully', Get.context!),
        Get.context!,
        isToaster: true,
        isError: false,
      );
      await loadTransferData(reload: false);
      if (Get.context != null) {
        await Provider.of<ProfileController>(Get.context!, listen: false).getSellerInfo();
      }
      notifyListeners();
      return true;
    }

    ApiChecker.checkApi(response);
    notifyListeners();
    return false;
  }

  double? _parseDouble(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value.toString());
  }
}
