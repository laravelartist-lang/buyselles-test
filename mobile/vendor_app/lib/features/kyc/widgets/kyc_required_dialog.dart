import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/kyc/controllers/kyc_controller.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/models/kyc_status_model.dart';
import 'package:sixvalley_vendor_app/features/kyc/screens/kyc_verification_screen.dart';
import 'package:sixvalley_vendor_app/helper/kyc_gate_helper.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

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
          Icon(
            Icons.verified_user_outlined,
            color: Theme.of(dialogContext).primaryColor,
          ),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          Expanded(
            child: Text(
              getTranslated('verify_your_account', dialogContext) ??
                  'Verify your account',
              style: titilliumSemiBold.copyWith(
                fontSize: Dimensions.fontSizeLarge,
              ),
            ),
          ),
        ],
      ),
      content: Text(
        message ??
            getTranslated(
              'please_complete_your_kyc_verification_to_continue',
              dialogContext,
            ) ??
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
            style: titilliumRegular.copyWith(
              color: Theme.of(dialogContext).hintColor,
            ),
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

  final bool verified = await openKycVerification(context);

  if (verified && context.mounted) {
    KycGateHelper.reset();
  }

  return verified;
}

/// Returns false when the vendor must complete KYC before continuing.
Future<bool> ensureKycAllowsVendorAction(BuildContext context) async {
  final KycController kycController =
      Provider.of<KycController>(context, listen: false);

  KycStatusModel? status = kycController.kycStatus;
  status ??= await kycController.getKycStatus(notify: false);

  if (!context.mounted) {
    return false;
  }

  if (status == null || !status.isBlocked) {
    return true;
  }

  KycGateHelper.markGateActive();

  return showKycRequiredDialog(
    context,
    canStartVerification: status.canStartVerification,
  );
}

String? kycBlockedMessage(dynamic response) {
  final dynamic data = response?.data;

  if (data is Map && data['message'] != null) {
    return data['message'].toString();
  }

  return null;
}
