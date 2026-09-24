import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/domain/models/kyc_status_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/domain/services/kyc_service.dart';
import 'package:flutter_sixvalley_ecommerce/helper/api_checker.dart';
import 'package:flutter/material.dart';

class KycController with ChangeNotifier {
  final KycServiceInterface kycServiceInterface;

  KycController({required this.kycServiceInterface});

  KycStatusModel? _kycStatus;
  KycStatusModel? get kycStatus => _kycStatus;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isLaunching = false;
  bool get isLaunching => _isLaunching;

  String? _launchUrl;
  String? get launchUrl => _launchUrl;

  double? _purchaseTotal;
  double? get purchaseTotal => _purchaseTotal;

  bool get isVerified => _kycStatus?.isApproved ?? false;

  bool get isBlocked => _kycStatus?.isBlocked ?? false;

  /// Fetch the shared verification state. Safe to call on every screen open.
  Future<KycStatusModel?> getKycStatus({bool notify = true}) async {
    _isLoading = true;
    if (notify) {
      notifyListeners();
    }

    final ApiResponseModel apiResponse =
        await kycServiceInterface.getStatus();

    if (apiResponse.response != null &&
        apiResponse.response!.statusCode == 200) {
      _kycStatus = KycStatusModel.fromJson(
          Map<String, dynamic>.from(apiResponse.response!.data['kyc'] ?? {}));
      _purchaseTotal =
          (apiResponse.response!.data['purchase_total'] as num?)?.toDouble();
    } else {
      ApiChecker.checkApi(apiResponse);
    }

    _isLoading = false;
    notifyListeners();

    return _kycStatus;
  }

  /// Ask the backend for a signed Sumsub WebSDK launch URL.
  Future<String?> requestLaunchUrl({bool notify = true}) async {
    _isLaunching = true;
    if (notify) {
      notifyListeners();
    }

    final ApiResponseModel apiResponse =
        await kycServiceInterface.getLaunchUrl();

    if (apiResponse.response != null &&
        apiResponse.response!.statusCode == 200) {
      _launchUrl = apiResponse.response!.data['launch_url'] as String?;
      if (apiResponse.response!.data['kyc'] != null) {
        _kycStatus = KycStatusModel.fromJson(Map<String, dynamic>.from(
            apiResponse.response!.data['kyc']));
      }
    } else if (apiResponse.response?.statusCode == 200 &&
        apiResponse.response!.data['already_verified'] == true) {
      _launchUrl = null;
    } else {
      ApiChecker.checkApi(apiResponse);
    }

    _isLaunching = false;
    notifyListeners();

    return _launchUrl;
  }

  Future<KycStatusModel?> refreshAfterVerification() => getKycStatus();

  void clearLaunchUrl() {
    _launchUrl = null;
  }
}
