import 'dart:io';

import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/features/kyc/controllers/kyc_controller.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/models/kyc_status_model.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';
import 'package:url_launcher/url_launcher.dart';

/// Identity verification is mandatory before a vendor can use the app.
///
/// The backend mints a signed Sumsub launch URL for this vendor and the flow
/// runs in the system browser, where the camera based liveness check has the
/// permissions it needs. The status is shared with the web dashboard, so the
/// vendor is unblocked everywhere the moment Sumsub approves them.
class KycVerificationScreen extends StatefulWidget {
  const KycVerificationScreen({super.key, this.showAppBar = true});

  final bool showAppBar;

  @override
  State<KycVerificationScreen> createState() => _KycVerificationScreenState();
}

class _KycVerificationScreenState extends State<KycVerificationScreen>
    with WidgetsBindingObserver {
  bool _isOpening = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // The verification happens in the browser - refresh as soon as the vendor
    // comes back so an approved result unlocks the app immediately.
    if (state == AppLifecycleState.resumed && mounted) {
      _refreshStatus();
    }
  }

  Future<void> _load() async {
    if (!mounted) {
      return;
    }

    await Provider.of<KycController>(Get.context!, listen: false)
        .getKycStatus();
  }

  Future<void> _refreshStatus() async {
    if (!mounted) {
      return;
    }

    final KycStatusModel? status =
        await Provider.of<KycController>(Get.context!, listen: false)
            .refreshAfterVerification();

    if (!mounted) {
      return;
    }

    if (status?.isApproved == true && status?.awaitsAdminApproval != true) {
      Navigator.of(Get.context!).maybePop(true);
    }
  }

  Future<void> _startVerification() async {
    if (_isOpening) {
      return;
    }

    setState(() => _isOpening = true);

    await _requestCameraPermission();

    if (!mounted) {
      return;
    }

    final KycController kycController =
        Provider.of<KycController>(Get.context!, listen: false);
    final String? launchUrl = await kycController.requestLaunchUrl();

    if (!mounted) {
      return;
    }

    setState(() => _isOpening = false);

    if (launchUrl == null || launchUrl.isEmpty) {
      return;
    }

    final bool opened = await launchUrlInBrowser(launchUrl);

    if (!opened && mounted) {
      ScaffoldMessenger.of(Get.context!).showSnackBar(
        SnackBar(
          content: Text(
            getTranslated('kyc_could_not_be_started', Get.context!) ?? '',
          ),
        ),
      );
    }
  }

  Future<void> _requestCameraPermission() async {
    try {
      if (Platform.isAndroid || Platform.isIOS) {
        await Permission.camera.request();
      }
    } catch (e) {
      debugPrint('KYC camera permission error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.showAppBar
          ? CustomAppBarWidget(
              title: getTranslated('kyc_verification', context),
              isBackButtonExist: Navigator.of(context).canPop(),
            )
          : null,
      body: Consumer<KycController>(
        builder: (context, kycController, _) {
          if (kycController.isLoading && kycController.kycStatus == null) {
            return const Center(child: CircularProgressIndicator());
          }

          final KycStatusModel? status = kycController.kycStatus;

          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const SizedBox(height: Dimensions.paddingSizeLarge),
                Icon(
                  status?.isApproved == true
                      ? Icons.verified_user
                      : Icons.verified_user_outlined,
                  size: 72,
                  color: status?.isApproved == true
                      ? Colors.green
                      : Theme.of(context).primaryColor,
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                Text(
                  _statusTitle(status),
                  textAlign: TextAlign.center,
                  style: titilliumSemiBold.copyWith(
                      fontSize: Dimensions.fontSizeExtraLarge,
                      color: Theme.of(context).textTheme.titleLarge?.color),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Text(
                  _statusDescription(status),
                  textAlign: TextAlign.center,
                  style: titilliumRegular.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      height: 1.5,
                      color: Theme.of(context).hintColor),
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),
                if (status?.isApproved != true)
                  CustomButtonWidget(
                    isLoading: _isOpening,
                    btnTxt: getTranslated(
                        status?.needsResubmission == true
                            ? 'start_verification'
                            : 'verify_now',
                        context),
                    onTap: status?.isPending == true
                        ? _refreshStatus
                        : _startVerification,
                  ),
                if (status?.isPending == true) ...[
                  const SizedBox(height: Dimensions.paddingSizeSmall),
                  TextButton(
                    onPressed: _refreshStatus,
                    child: Text(
                      getTranslated('try_again', context) ?? '',
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  String _statusTitle(KycStatusModel? status) {
    if (status?.isApproved == true) {
      return getTranslated('kyc_verified', Get.context!) ?? '';
    }


    if (status?.isPending == true) {
      return getTranslated('kyc_under_review', Get.context!) ?? '';
    }

    return getTranslated('complete_identity_verification', Get.context!) ?? '';
  }

  String _statusDescription(KycStatusModel? status) {
    if (status?.awaitsAdminApproval == true) {
      return getTranslated(
              'your_account_is_in_review_process', Get.context!) ??
          '';
    }

    if (status?.isApproved == true) {
      return getTranslated('your_account_is_verified', Get.context!) ?? '';
    }

    if (status?.isPending == true) {
      return getTranslated(
              'your_kyc_verification_is_under_review', Get.context!) ??
          '';
    }

    if (status?.needsResubmission == true) {
      return status?.rejectionReason ??
          getTranslated(
              'your_kyc_verification_was_rejected_please_submit_again',
              Get.context!) ??
          '';
    }

    return getTranslated(
            'please_complete_your_kyc_verification_to_continue',
            Get.context!) ??
        '';
  }
}

/// Opens [url] outside the app so the KYC camera step has the browser's
/// permissions. Returns false when no browser could handle the URL.
Future<bool> launchUrlInBrowser(String url) async {
  final Uri? uri = Uri.tryParse(url);

  if (uri == null) {
    return false;
  }

  return launchUrl(uri, mode: LaunchMode.externalApplication);
}
