import 'package:dio/dio.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/kyc/controllers/kyc_controller.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/models/kyc_status_model.dart';
import 'package:sixvalley_vendor_app/main.dart';

/// Tracks vendor KYC lock state. Background API 403s stay silent; user actions
/// call [ensureKycAllowsVendorAction] to show the popup once per attempt.
class KycGateHelper {
  static const String routeName = 'KycVerificationScreen';

  static bool _gateActive = false;

  static bool get isGateActive => _gateActive;

  static void markGateActive() {
    _gateActive = true;
  }

  static void reset() {
    _gateActive = false;
  }

  static bool isKycRequiredResponse(Response<dynamic>? response) {
    if (response == null) {
      return false;
    }

    if (response.statusCode != 403) {
      return false;
    }

    final dynamic data = response.data;
    if (data is Map) {
      if (data['kyc_required'] == true) {
        return true;
      }

      final String? message = data['message']?.toString().toLowerCase();
      if (message != null && message.contains('kyc')) {
        return true;
      }
    }

    return false;
  }

  static bool isKycErrorMessage(dynamic error) {
    if (error is! String) {
      return false;
    }

    final String lower = error.toLowerCase();

    return lower.contains('kyc') ||
        lower.contains('verification to continue');
  }

  /// Returns true when this API failure should stay silent (no toast).
  static bool shouldSuppressFeedback(ApiResponse apiResponse) {
    if (isGateActive) {
      return true;
    }

    if (isKycRequiredResponse(apiResponse.response)) {
      return true;
    }

    return isKycErrorMessage(apiResponse.error);
  }

  /// Returns true when the response was handled as a KYC lock (no snackbar).
  static bool handleIfRequired(ApiResponse apiResponse) {
    if (isKycRequiredResponse(apiResponse.response)) {
      markGateActive();
      _syncKycController(apiResponse.response?.data);

      return true;
    }

    if (isKycErrorMessage(apiResponse.error)) {
      markGateActive();

      return true;
    }

    return false;
  }

  static void applyStatusIfBlocked(KycStatusModel? status) {
    if (status != null && status.isBlocked) {
      markGateActive();
    } else if (status?.isApproved == true) {
      reset();
    }
  }

  static void _syncKycController(dynamic data) {
    if (data is! Map || Get.context == null) {
      return;
    }

    try {
      Provider.of<KycController>(Get.context!, listen: false)
          .applyStatusFromApiData(Map<dynamic, dynamic>.from(data));
    } catch (_) {}
  }
}
