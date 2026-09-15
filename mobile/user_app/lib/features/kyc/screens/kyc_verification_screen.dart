import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/controllers/kyc_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/kyc/domain/models/kyc_status_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';

/// The apps never talk to Sumsub directly: they open a signed launch URL that
/// the Laravel backend mints, so the same applicant and the same verification
/// state serve the storefront and both mobile apps.
class KycVerificationScreen extends StatefulWidget {
  const KycVerificationScreen({super.key});

  static const String route = '/kyc';

  @override
  State<KycVerificationScreen> createState() => _KycVerificationScreenState();
}

class _KycVerificationScreenState extends State<KycVerificationScreen> {
  bool _requestingLaunchUrl = true;
  bool _finished = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _prepare());
  }

  Future<void> _prepare() async {
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

    setState(() {
      _requestingLaunchUrl = false;
      _errorMessage = launchUrl == null
          ? getTranslated('kyc_could_not_be_started', Get.context!)
          : null;
    });
  }

  Future<void> _requestCameraPermission() async {
    if (kIsWeb) {
      return;
    }

    try {
      if (Platform.isAndroid || Platform.isIOS) {
        await Permission.camera.request();
      }
    } catch (e) {
      debugPrint('KYC camera permission error: $e');
    }
  }

  /// The verification page hands control back over a private URL scheme.
  bool _handleBridgeUrl(WebUri? uri) {
    if (uri == null || uri.scheme != 'buyselles-kyc') {
      return false;
    }

    final String status = uri.queryParameters['status'] ?? 'pending';
    final bool cancelled = uri.host == 'cancelled';

    if (_finished) {
      return true;
    }

    _finished = true;

    Future<void>.delayed(const Duration(milliseconds: 100), () async {
      if (!mounted) {
        return;
      }

      await Provider.of<KycController>(Get.context!, listen: false)
          .refreshAfterVerification();

      if (!mounted) {
        return;
      }

      Navigator.of(Get.context!).pop(
        KycVerificationResult(cancelled: cancelled, status: status),
      );
    });

    return true;
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: true,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) {
          Provider.of<KycController>(Get.context!, listen: false)
              .clearLaunchUrl();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            getTranslated('kyc_verification', Get.context!) ?? 'Verification',
            style: titilliumSemiBold.copyWith(
                fontSize: Dimensions.fontSizeLarge),
          ),
          backgroundColor: Theme.of(context).cardColor,
        ),
        body: _buildBody(context),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    final KycController kycController =
        Provider.of<KycController>(context, listen: true);
    final String? launchUrl = kycController.launchUrl;

    if (_requestingLaunchUrl) {
      return Center(
        child: CircularProgressIndicator(
          valueColor:
              AlwaysStoppedAnimation<Color>(Theme.of(context).primaryColor),
        ),
      );
    }

    if (_errorMessage != null || launchUrl == null || launchUrl.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.verified_user_outlined,
                  size: 56, color: Theme.of(context).colorScheme.error),
              const SizedBox(height: Dimensions.paddingSizeSmall),
              Text(
                _errorMessage ??
                    getTranslated('kyc_could_not_be_started', Get.context!) ??
                    '',
                textAlign: TextAlign.center,
                style: titilliumRegular.copyWith(
                    color: Theme.of(context).hintColor,
                    fontSize: Dimensions.fontSizeDefault),
              ),
              const SizedBox(height: Dimensions.paddingSizeLarge),
              OutlinedButton(
                onPressed: () async {
                  setState(() {
                    _requestingLaunchUrl = true;
                    _errorMessage = null;
                  });
                  await _prepare();
                },
                child:
                    Text(getTranslated('try_again', Get.context!) ?? 'Retry'),
              ),
            ],
          ),
        ),
      );
    }

    return InAppWebView(
      initialUrlRequest: URLRequest(url: WebUri(launchUrl)),
      initialSettings: InAppWebViewSettings(
        javaScriptEnabled: true,
        domStorageEnabled: true,
        mediaPlaybackRequiresUserGesture: false,
        useShouldOverrideUrlLoading: true,
        isInspectable: kDebugMode,
      ),
      onPermissionRequest: (controller, request) async {
        return PermissionResponse(
          resources: request.resources,
          action: PermissionResponseAction.GRANT,
        );
      },
      shouldOverrideUrlLoading: (controller, action) async {
        if (_handleBridgeUrl(action.request.url)) {
          return NavigationActionPolicy.CANCEL;
        }

        return NavigationActionPolicy.ALLOW;
      },
    );
  }
}

class KycVerificationResult {
  final bool cancelled;
  final String? status;

  KycVerificationResult({required this.cancelled, this.status});

  bool get isApproved => status == 'green' || status == 'approved';
}

/// Single entry point used by the checkout gate and the profile menu.
Future<bool> openKycVerification(BuildContext context) async {
  final KycController kycController =
      Provider.of<KycController>(context, listen: false);

  final KycVerificationResult? result =
      await Navigator.of(context).push<KycVerificationResult>(
    MaterialPageRoute(builder: (_) => const KycVerificationScreen()),
  );

  if (result == null) {
    return kycController.isVerified;
  }

  final KycStatusModel? status = await kycController.refreshAfterVerification();

  return status?.isApproved ?? false;
}
