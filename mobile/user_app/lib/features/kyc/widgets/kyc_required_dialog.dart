import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/controllers/kyc_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/domain/models/kyc_status_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/screens/kyc_verification_screen.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

/// Shown whenever the backend refuses an order because the account still has
/// to pass KYC. Returns true when the customer completed verification.
Future<bool> showKycRequiredDialog(
  BuildContext context, {
  String? message,
  bool canStartVerification = true,
}) async {
  final bool? verifyNow = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) => AlertDialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      ),
      title: Row(
        children: [
          Icon(Icons.verified_user_outlined,
              color: Theme.of(dialogContext).primaryColor),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          Expanded(
            child: Text(
              getTranslated('verify_your_account', dialogContext) ??
                  'Verify your account',
              style: titilliumSemiBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge),
            ),
          ),
        ],
      ),
      content: Text(
        message ??
            getTranslated(
                'you_need_to_verify_your_account_before_placing_more_orders',
                dialogContext) ??
            '',
        style: titilliumRegular.copyWith(
          fontSize: Dimensions.fontSizeDefault,
          color: Theme.of(dialogContext).hintColor,
          height: 1.5,
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(false),
          child: Text(
            getTranslated('later', dialogContext) ?? 'Later',
            style: titilliumRegular.copyWith(color: Theme.of(dialogContext).hintColor),
          ),
        ),
        if (canStartVerification)
          ElevatedButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              getTranslated('verify_now', dialogContext) ?? 'Verify now',
              style: titilliumSemiBold.copyWith(color: Colors.white),
            ),
          ),
      ],
    ),
  );

  if (verifyNow != true) {
    return false;
  }

  if (!context.mounted) {
    return false;
  }

  return openKycVerification(context);
}

/// Runs before an order is sent to the backend.
///
/// Returns false when the account is locked, after showing the popup so the
/// customer understands why the order was stopped and can verify right away.
///
/// The backend also enforces the threshold against the amount being spent, so
/// a stale client-side answer can never let an order through.
Future<bool> ensureKycAllowsCheckout(BuildContext context) async {
  final bool isLoggedIn =
      Provider.of<AuthController>(context, listen: false).isLoggedIn();

  if (!isLoggedIn) {
    return true;
  }

  final KycController kycController =
      Provider.of<KycController>(context, listen: false);

  final KycStatusModel? status = await kycController.getKycStatus();

  if (!context.mounted) {
    return true;
  }

  if (status == null || !status.isBlocked) {
    return true;
  }

  return showKycRequiredDialog(
    context,
    canStartVerification: status.canStartVerification,
  );
}

/// True when an API response means "this order needs KYC first".
bool isKycRequiredResponse(dynamic response) {
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

/// Message carried by a blocked checkout response, if any.
String? kycBlockedMessage(dynamic response) {
  final dynamic data = response?.data;

  if (data is Map && data['message'] != null) {
    return data['message'].toString();
  }

  return null;
}
