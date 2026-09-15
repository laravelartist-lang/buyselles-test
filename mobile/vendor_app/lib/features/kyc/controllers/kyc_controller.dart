import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/models/kyc_status_model.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/services/kyc_service.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';

class KycController with ChangeNotifier {
  final KycServiceInterface kycServiceInterface;

  KycController({required this.kycServiceInterface});

  KycStatusModel? _kycStatus;
  KycStatusModel? get kycStatus => _kycStatus;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  String? _launchUrl;
  String? get launchUrl => _launchUrl;

  bool get isVerified => _kycStatus?.isApproved ?? false;

  Future<KycStatusModel?> getKycStatus({bool notify = true}) async {
    _isLoading = true;
    if (notify) {
      notifyListeners();
    }

    final ApiResponse apiResponse = await kycServiceInterface.getStatus();

    if (apiResponse.response != null &&
        apiResponse.response!.statusCode == 200) {
      _kycStatus = _parseStatus(apiResponse.response!.data);
    } else if (notify) {
      showCustomSnackBarWidget(
        apiResponse.error?.toString() ??
            getTranslated('kyc_verification_is_currently_unavailable',
                Get.context!),
        Get.context!,
        sanckBarType: SnackBarType.error,
      );
    }

    _isLoading = false;
    notifyListeners();

    return _kycStatus;
  }

  /// Asks the backend for a signed Sumsub launch URL the app opens in the
  /// system browser, since the vendor app ships no in-app WebView.
  Future<String?> requestLaunchUrl({bool notify = true}) async {
    _isLoading = true;
    if (notify) {
      notifyListeners();
    }

    final ApiResponse apiResponse = await kycServiceInterface.getLaunchUrl();

    if (apiResponse.response != null &&
        apiResponse.response!.statusCode == 200) {
      _launchUrl = apiResponse.response!.data['launch_url'] as String?;
      if (apiResponse.response!.data['kyc'] != null) {
        _kycStatus = _parseStatus(apiResponse.response!.data);
      }
    } else {
      _launchUrl = null;
      if (notify) {
        showCustomSnackBarWidget(
          apiResponse.response?.data != null &&
                  apiResponse.response!.data is Map &&
                  apiResponse.response!.data['message'] != null
              ? apiResponse.response!.data['message'].toString()
              : getTranslated('kyc_could_not_be_started', Get.context!),
          Get.context!,
          sanckBarType: SnackBarType.error,
        );
      }
    }

    _isLoading = false;
    notifyListeners();

    return _launchUrl;
  }

  /// The API returns the KYC block plus the separate shop approval state.
  KycStatusModel _parseStatus(dynamic data) {
    final Map<String, dynamic> payload =
        Map<String, dynamic>.from(data['kyc'] ?? <String, dynamic>{});

    if (data['seller_status'] != null) {
      payload['account_status'] = data['seller_status'];
    }

    return KycStatusModel.fromJson(payload);
  }

  Future<KycStatusModel?> refreshAfterVerification() => getKycStatus();

  void clearLaunchUrl() {
    _launchUrl = null;
  }
}
