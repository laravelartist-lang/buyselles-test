import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/no_internet_screen_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/controllers/checkout_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/change_amount_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/offline_payment/domain/models/offline_payment_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/domain/models/config_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/controllers/localization_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/custom_check_box_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/helper/apple_iap_payment_helper.dart';
import 'package:provider/provider.dart';

class PaymentMethodBottomSheetWidget extends StatefulWidget {
  final bool onlyDigital;
  final bool embedded;
  const PaymentMethodBottomSheetWidget({
    super.key,
    required this.onlyDigital,
    this.embedded = false,
  });
  @override
  PaymentMethodBottomSheetWidgetState createState() =>
      PaymentMethodBottomSheetWidgetState();
}

class PaymentMethodBottomSheetWidgetState
    extends State<PaymentMethodBottomSheetWidget> {
  final TextEditingController changeAmountTextController =
      TextEditingController();
  final ConfigModel? configModel =
      Provider.of<SplashController>(Get.context!, listen: false).configModel;
  CheckoutController checkoutController =
      Provider.of<CheckoutController>(Get.context!, listen: false);

  @override
  void initState() {
    changeAmountTextController.text =
        '${Provider.of<CheckoutController>(context, listen: false).cashChangesAmount ?? ''}';
    super.initState();
  }

  void _closeSheet() {
    if (widget.embedded) {
      return;
    }

    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    }
  }

  void _selectAndClose(void Function() onSelect) {
    onSelect();
    _closeSheet();
  }

  @override
  Widget build(BuildContext context) {
    final ConfigModel? configModel =
        Provider.of<SplashController>(context, listen: false).configModel;
    final bool isLtr =
        Provider.of<LocalizationController>(context, listen: false).isLtr;

    return Consumer<CheckoutController>(
        builder: (context, checkoutController, _) {
      final Widget selectionContent = SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (!widget.embedded)
            const SizedBox(height: Dimensions.paddingSizeSmall),
          if (!widget.embedded)
            Padding(
                padding: const EdgeInsets.only(
                    bottom: Dimensions.paddingSizeDefault),
                child: Center(
                    child: Container(
                        width: 35,
                        height: 4,
                        decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(
                                Dimensions.paddingSizeDefault),
                            color: Theme.of(context)
                                .hintColor
                                .withValues(alpha: .5))))),
          Padding(
            padding: const EdgeInsets.symmetric(
                vertical: Dimensions.paddingSizeSmall),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                getTranslated('choose_payment_method', context) ?? '',
                style: titilliumSemiBold.copyWith(
                    fontSize: Dimensions.fontSizeDefault,
                    color: Theme.of(context).textTheme.bodyLarge?.color),
              ),
              Expanded(
                  child: Padding(
                padding: const EdgeInsets.only(
                    left: Dimensions.paddingSizeExtraSmall),
                child: Text(
                  '${getTranslated('click_one_of_the_option_below', context)}',
                  style: textRegular.copyWith(
                      color: Theme.of(context).hintColor,
                      fontSize: Dimensions.fontSizeSmall),
                ),
              )),
            ]),
          ),
          _isPaymentMethodsAvailable(
                  Get.context!,
                  checkoutController.offlinePaymentModel?.offlineMethods,
                  onlyDigital: widget.onlyDigital)
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(children: [
                      if ((configModel?.cashOnDelivery ?? false) &&
                          !widget.onlyDigital)
                        Expanded(
                            child: CustomButton(
                          isBorder: true,
                          leftIcon: Images.cod,
                          backgroundColor: checkoutController.isCODChecked
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).cardColor,
                          textColor: checkoutController.isCODChecked
                              ? Colors.white
                              : Theme.of(context).textTheme.bodyLarge?.color,
                          fontSize: Dimensions.fontSizeSmall,
                          onTap: () => widget.embedded
                              ? checkoutController.setOfflineChecked('cod')
                              : _selectAndClose(() =>
                                  checkoutController.setOfflineChecked('cod')),
                          buttonText:
                              '${getTranslated('cash_on_delivery', context)}',
                        )),
                      const SizedBox(width: Dimensions.paddingSizeDefault),
                      if (configModel?.walletStatus == 1 &&
                          Provider.of<AuthController>(context, listen: false)
                              .isLoggedIn())
                        Expanded(
                            child: CustomButton(
                          onTap: () => widget.embedded
                              ? checkoutController.setOfflineChecked('wallet')
                              : _selectAndClose(() => checkoutController
                                  .setOfflineChecked('wallet')),
                          isBorder: true,
                          leftIcon: Images.payWallet,
                          backgroundColor: checkoutController.isWalletChecked
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).cardColor,
                          textColor: checkoutController.isWalletChecked
                              ? Colors.white
                              : Theme.of(context).textTheme.bodyLarge?.color,
                          fontSize: Dimensions.fontSizeSmall,
                          buttonText:
                              '${getTranslated('pay_via_wallet', context)}',
                        )),
                    ]),

                    if (shouldUseAppleIapOnDigitalCheckout(
                        context, widget.onlyDigital))
                      Padding(
                        padding: const EdgeInsets.only(top: Dimensions.paddingSizeDefault),
                        child: CustomButton(
                          onTap: () => widget.embedded
                              ? checkoutController.setOfflineChecked('apple_iap')
                              : _selectAndClose(() => checkoutController.setOfflineChecked('apple_iap')),
                          isBorder: true,
                          leftIcon: Images.appleLogo,
                          backgroundColor: checkoutController.isAppleIapChecked
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).cardColor,
                          textColor: checkoutController.isAppleIapChecked
                              ? Colors.white
                              : Theme.of(context).textTheme.bodyLarge?.color,
                          fontSize: Dimensions.fontSizeSmall,
                          buttonText: 'App Store',
                        ),
                      ),

                    ///change amount
                    ChangeAmountWidget(
                        changeAmountTextController: changeAmountTextController),

                    if ((configModel?.digitalPayment ?? false) &&
                        (configModel?.paymentMethods?.isNotEmpty ?? false) &&
                        !checkoutController.isCODChecked &&
                        !shouldUseAppleIapOnDigitalCheckout(
                            context, widget.onlyDigital))
                      SizedBox(height: Dimensions.paddingSizeSmall),

                    if ((configModel?.digitalPayment ?? false) &&
                        (configModel?.paymentMethods?.isNotEmpty ?? false) &&
                        !shouldUseAppleIapOnDigitalCheckout(
                            context, widget.onlyDigital))
                      Container(
                        padding:
                            const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        decoration: BoxDecoration(
                          color: Theme.of(context).cardColor,
                          border: Border.all(
                            width: 1,
                            color: Theme.of(context)
                                .hintColor
                                .withValues(alpha: .125),
                          ),
                          borderRadius: BorderRadius.circular(
                              Dimensions.paddingSizeExtraSmall),
                        ),
                        child: Column(
                          children: [
                            if ((configModel?.digitalPayment ?? false) &&
                                (configModel?.paymentMethods?.isNotEmpty ??
                                    false))
                              Padding(
                                padding: const EdgeInsets.only(
                                    bottom: Dimensions.paddingSizeSmall,
                                    top: Dimensions.paddingSizeDefault),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                        '${getTranslated('pay_via_online', context)}',
                                        style: titilliumBold.copyWith(
                                            color: Theme.of(context)
                                                .textTheme
                                                .bodyLarge
                                                ?.color)),
                                  ],
                                ),
                              ),
                            if ((configModel?.digitalPayment ?? false) &&
                                (configModel?.paymentMethods?.isNotEmpty ??
                                    false))
                              Consumer<SplashController>(
                                  builder: (context, configProvider, _) {
                                return ListView.separated(
                                  padding: EdgeInsets.zero,
                                  itemCount: configProvider.configModel
                                          ?.paymentMethods?.length ??
                                      0,
                                  shrinkWrap: true,
                                  physics: const NeverScrollableScrollPhysics(),
                                  itemBuilder: (context, index) {
                                    return CustomCheckBoxWidget(
                                      index: index,
                                      padding: 0,
                                      icon:
                                          '${configProvider.configModel?.paymentMethodImagePath}/'
                                          '${configProvider.configModel?.paymentMethods?[index].additionalDatas?.gatewayImage ?? ''}',
                                      name: configProvider.configModel!
                                          .paymentMethods![index].keyName!,
                                      title: configProvider
                                              .configModel!
                                              .paymentMethods![index]
                                              .additionalDatas
                                              ?.gatewayTitle ??
                                          '',
                                      onSelected:
                                          widget.embedded ? null : _closeSheet,
                                    );
                                  },
                                  separatorBuilder: (context, index) {
                                    return SizedBox(
                                        height: Dimensions.paddingSizeSmall);
                                  },
                                );
                              }),
                          ],
                        ),
                      ),

                    if ((configModel?.digitalPayment ?? false) &&
                        (configModel?.paymentMethods?.isNotEmpty ?? false) &&
                        !shouldUseAppleIapOnDigitalCheckout(
                            context, widget.onlyDigital))
                      SizedBox(height: Dimensions.paddingSizeSmall),

                    if (!widget.onlyDigital &&
                        configModel?.offlinePayment != null &&
                        (checkoutController.offlinePaymentModel?.offlineMethods
                                ?.isNotEmpty ??
                            false))
                      Container(
                          decoration: BoxDecoration(
                            color: checkoutController.isOfflineChecked
                                ? Theme.of(context)
                                    .primaryColor
                                    .withValues(alpha: .15)
                                : null,
                            borderRadius: BorderRadius.circular(
                                Dimensions.paddingSizeSmall),
                          ),
                          child: Column(children: [
                            InkWell(
                              onTap: () {
                                if (checkoutController.offlinePaymentModel
                                            ?.offlineMethods !=
                                        null &&
                                    checkoutController.offlinePaymentModel!
                                        .offlineMethods!.isNotEmpty) {
                                  widget.embedded
                                      ? checkoutController
                                          .setOfflineChecked('offline')
                                      : _selectAndClose(() => checkoutController
                                          .setOfflineChecked('offline'));
                                }
                              },
                              child: Padding(
                                  padding: const EdgeInsets.all(8.0),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal:
                                            Dimensions.paddingSizeSmall),
                                    decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(
                                            Dimensions.paddingSizeExtraSmall)),
                                    child: Row(children: [
                                      Theme(
                                        data: Theme.of(context).copyWith(
                                            unselectedWidgetColor:
                                                Theme.of(context)
                                                    .primaryColor
                                                    .withValues(alpha: .25)),
                                        child: Checkbox(
                                          visualDensity: VisualDensity.compact,
                                          shape: RoundedRectangleBorder(
                                              borderRadius: BorderRadius
                                                  .circular(Dimensions
                                                      .paddingSizeExtraLarge)),
                                          checkColor: Colors.white,
                                          value: checkoutController
                                              .isOfflineChecked,
                                          activeColor: Colors.green,
                                          onChanged: (bool? isChecked) {
                                            if (checkoutController
                                                    .offlinePaymentModel
                                                    ?.offlineMethods
                                                    ?.isNotEmpty ??
                                                false) {
                                              widget.embedded
                                                  ? checkoutController
                                                      .setOfflineChecked(
                                                          'offline')
                                                  : _selectAndClose(() =>
                                                      checkoutController
                                                          .setOfflineChecked(
                                                              'offline'));
                                            }
                                          },
                                        ),
                                      ),
                                      Text(
                                          '${getTranslated('pay_offline', context)}',
                                          style: textBold.copyWith(
                                              color: Theme.of(context)
                                                  .textTheme
                                                  .bodyLarge
                                                  ?.color)),
                                    ]),
                                  )),
                            ),
                            if ((checkoutController.offlinePaymentModel
                                        ?.offlineMethods?.isNotEmpty ??
                                    false) &&
                                checkoutController.isOfflineChecked)
                              Padding(
                                padding: EdgeInsets.only(
                                  left:
                                      isLtr ? Dimensions.paddingSizeDefault : 0,
                                  bottom: Dimensions.paddingSizeDefault,
                                  right:
                                      isLtr ? 0 : Dimensions.paddingSizeDefault,
                                  top: Dimensions.paddingSizeSmall,
                                ),
                                child: SizedBox(
                                    height: 40,
                                    child: ListView.builder(
                                      padding: EdgeInsets.zero,
                                      shrinkWrap: true,
                                      scrollDirection: Axis.horizontal,
                                      itemCount: checkoutController
                                          .offlinePaymentModel!
                                          .offlineMethods!
                                          .length,
                                      itemBuilder: (context, index) {
                                        return InkWell(
                                          onTap: () {
                                            if (checkoutController
                                                    .offlinePaymentModel
                                                    ?.offlineMethods
                                                    ?.isNotEmpty ??
                                                false) {
                                              checkoutController
                                                  .setOfflinePaymentMethodSelectedIndex(
                                                      index);
                                            }
                                          },
                                          child: Padding(
                                            padding: const EdgeInsets.symmetric(
                                                horizontal: Dimensions
                                                    .paddingSizeExtraSmall),
                                            child: Container(
                                              decoration: BoxDecoration(
                                                color:
                                                    Theme.of(context).cardColor,
                                                borderRadius: BorderRadius
                                                    .circular(Dimensions
                                                        .paddingSizeExtraSmall),
                                                border: checkoutController
                                                            .offlineMethodSelectedIndex ==
                                                        index
                                                    ? Border.all(
                                                        color: Theme.of(context)
                                                            .primaryColor,
                                                        width: 1)
                                                    : Border.all(
                                                        color: Theme.of(context)
                                                            .primaryColor
                                                            .withValues(
                                                                alpha: .5),
                                                        width: .25),
                                              ),
                                              child: Padding(
                                                padding: const EdgeInsets
                                                    .symmetric(
                                                    horizontal: Dimensions
                                                        .paddingSizeDefault),
                                                child: Center(
                                                    child: Text(checkoutController
                                                            .offlinePaymentModel
                                                            ?.offlineMethods?[
                                                                index]
                                                            .methodName ??
                                                        '')),
                                              ),
                                            ),
                                          ),
                                        );
                                      },
                                    )),
                              ),
                          ])),
                  ],
                )
              : const NoInternetOrDataScreenWidget(
                  isNoInternet: false,
                  message: 'no_payment_method_available_right_now',
                ),
        ]),
      );

      if (widget.embedded) {
        return Container(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          decoration: BoxDecoration(
            color: Theme.of(context).highlightColor,
            boxShadow: [
              BoxShadow(
                  color: Theme.of(context).hintColor.withValues(alpha: 0.2),
                  spreadRadius: 3,
                  blurRadius: 3)
            ],
          ),
          child: selectionContent,
        );
      }

      return PopScope(
        onPopInvokedWithResult: (_, __) {
          if (checkoutController.isCODChecked) {
            checkoutController.onChangeCashChangesAmount(
                double.tryParse(changeAmountTextController.text));
          } else {
            checkoutController.onChangeCashChangesAmount(null);
          }
        },
        child: Container(
          constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.7,
              minHeight: MediaQuery.of(context).size.height * 0.5),
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          decoration: BoxDecoration(
              color: Theme.of(context).highlightColor,
              borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(20), topRight: Radius.circular(20))),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Expanded(child: selectionContent),
              CustomButton(
                buttonText: '${getTranslated('save', context)}',
                onTap: () {
                  if (!_hasSelectedPaymentMethod(checkoutController)) {
                    showCustomSnackBarWidget(
                      getTranslated('choose_payment_method', context) ??
                          'Payment method is not selected',
                      context,
                      snackBarType: SnackBarType.warning,
                    );
                    return;
                  }
                  Navigator.of(context).pop();
                  if ((configModel?.cashOnDelivery ?? false) &&
                      !widget.onlyDigital) {
                    checkoutController.updatePaymentSelection();
                  }
                },
              ),
            ],
          ),
        ),
      );
    });
  }
}

bool _isPaymentMethodsAvailable(
    BuildContext context, List<OfflineMethods>? offlineMethods,
    {bool onlyDigital = false}) {
  final ConfigModel? configModel =
      Provider.of<SplashController>(context, listen: false).configModel;

  bool isCashOnDeliveryOn =
      !onlyDigital && (configModel?.cashOnDelivery ?? false);
  bool isWalletOn = configModel?.walletStatus == 1 &&
      Provider.of<AuthController>(context, listen: false).isLoggedIn();
  bool isOnlinePaymentMethodsOn =
      (configModel?.paymentMethods?.isNotEmpty ?? false) &&
      !shouldUseAppleIapOnDigitalCheckout(context, onlyDigital);
  bool isAppleIapOn = shouldUseAppleIapOnDigitalCheckout(context, onlyDigital) &&
      Provider.of<AuthController>(context, listen: false).isLoggedIn();
  bool isOfflinePaymentMethodsOn =
      !onlyDigital && (offlineMethods?.isNotEmpty ?? false);

  return isCashOnDeliveryOn ||
      isWalletOn ||
      isOnlinePaymentMethodsOn ||
      isOfflinePaymentMethodsOn ||
      isAppleIapOn;
}

bool _hasSelectedPaymentMethod(CheckoutController checkoutController) {
  return checkoutController.paymentMethodIndex != -1 ||
      checkoutController.isCODChecked ||
      checkoutController.isOfflineChecked ||
      checkoutController.isWalletChecked ||
      checkoutController.isAppleIapChecked;
}
