import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/domain/models/config_model.dart';
import 'package:provider/provider.dart';

bool shouldUseAppleIapOnDigitalCheckout(BuildContext context, bool onlyDigital) {
  if (!Platform.isIOS || !onlyDigital) {
    return false;
  }

  final ConfigModel? configModel =
      Provider.of<SplashController>(context, listen: false).configModel;

  return configModel?.iosIapStatus == true;
}

bool shouldBlockExternalDigitalPaymentOnIos(BuildContext context, bool onlyDigital) {
  return shouldUseAppleIapOnDigitalCheckout(context, onlyDigital);
}

bool isWalletAddFundAllowedOnPlatform() {
  return !Platform.isIOS;
}
