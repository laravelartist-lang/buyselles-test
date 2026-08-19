import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/address/domain/models/address_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/address/controllers/address_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/controllers/checkout_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/iap/controllers/iap_purchase_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/checkout_condition_checkbox.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/order_place_bottomsheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/profile/controllers/profile_contrroller.dart';
import 'package:flutter_sixvalley_ecommerce/features/shipping/controllers/shipping_controller.dart';
import 'package:flutter_sixvalley_ecommerce/helper/cart_healper.dart';
import 'package:flutter_sixvalley_ecommerce/helper/debounce_helper.dart';
import 'package:flutter_sixvalley_ecommerce/helper/price_converter.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/controllers/cart_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/coupon/controllers/coupon_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/amount_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_textfield_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/choose_payment_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/coupon_apply_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/create_account_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/shipping_details_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/widgets/wallet_payment_widget.dart';
import 'package:provider/provider.dart';

class CheckoutScreen extends StatefulWidget {
  final List<CartModel> cartList;
  final bool fromProductDetails;
  final double totalOrderAmount;
  final double shippingFee;
  final double discount;
  final double tax;
  final int? sellerId;
  final bool onlyDigital;
  final bool onlyDirectTopUp;
  final bool hasPhysical;
  final int quantity;

  const CheckoutScreen(
      {super.key,
      required this.cartList,
      this.fromProductDetails = false,
      required this.discount,
      required this.tax,
      required this.totalOrderAmount,
      required this.shippingFee,
      this.sellerId,
      this.onlyDigital = false,
      this.onlyDirectTopUp = false,
      required this.quantity,
      required this.hasPhysical});

  @override
  CheckoutScreenState createState() => CheckoutScreenState();
}

class CheckoutScreenState extends State<CheckoutScreen> {
  final GlobalKey<ScaffoldMessengerState> _scaffoldKey =
      GlobalKey<ScaffoldMessengerState>();
  final TextEditingController _controller = TextEditingController();
  final GlobalKey<FormState> passwordFormKey = GlobalKey<FormState>();

  final FocusNode _orderNoteNode = FocusNode();
  double _order = 0;
  double _tax = 0;
  late bool _billingAddress;
  double _couponDiscount = 0;
  double _referralDiscount = 0;

  DebounceHelper debounceHelper = DebounceHelper(milliseconds: 500);
  SplashController splashController =
      Provider.of<SplashController>(Get.context!, listen: false);

  void _initializeSavedAddresses(List<AddressModel>? addresses) {
    if (!mounted || addresses == null || addresses.isEmpty) {
      return;
    }

    final checkoutController =
        Provider.of<CheckoutController>(context, listen: false);

    if (_requiresShippingAddress && checkoutController.addressIndex == null) {
      checkoutController.setAddressIndex(0);
    }

    if (_requiresShippingAddress && _billingAddress && checkoutController.billingAddressIndex == null) {
      checkoutController
          .setBillingAddressIndex(checkoutController.addressIndex ?? 0);
    }
  }

  bool get _requiresShippingAddress =>
      widget.hasPhysical && !widget.onlyDigital && !widget.onlyDirectTopUp;

  bool get _isDigitalOnlyCheckout =>
      widget.onlyDigital || widget.onlyDirectTopUp;

  String? _resolveCustomerId(
      AuthController authController, ProfileController profileController) {
    if (!authController.isLoggedIn()) {
      return authController.getGuestToken();
    }

    final int? userId = profileController.userInfoModel?.id
        ?? int.tryParse(profileController.userID);

    if (userId != null && userId > 0) {
      return userId.toString();
    }

    return null;
  }

  @override
  void initState() {
    super.initState();
    _billingAddress = Provider.of<SplashController>(Get.context!, listen: false)
            .configModel!
            .billingInputByCustomer ==
        1;
    Provider.of<AddressController>(context, listen: false)
        .getAddressList()
        .then(_initializeSavedAddresses);
    Provider.of<CheckoutController>(context, listen: false)
        .getReferralAmount('0');
    Provider.of<CouponController>(context, listen: false)
        .removePrevCouponData();
    Provider.of<CartController>(context, listen: false).getCartData(context);
    Provider.of<CheckoutController>(context, listen: false)
        .resetPaymentMethod();
    Provider.of<ShippingController>(context, listen: false)
        .getChosenShippingMethod(context);
    if (splashController.configModel != null &&
        splashController.configModel!.offlinePayment != null) {
      Provider.of<CheckoutController>(context, listen: false)
          .getOfflinePaymentList();
    }

    if (Provider.of<AuthController>(context, listen: false).isLoggedIn()) {
      Provider.of<CouponController>(context, listen: false)
          .getAvailableCouponList();
      Provider.of<ProfileController>(context, listen: false)
          .getUserInfo(context);
    }

    if (Provider.of<CheckoutController>(context, listen: false).isAcceptTerms) {
      Provider.of<CheckoutController>(context, listen: false)
          .toggleTermsCheck(isUpdate: false);
    }

    Provider.of<CheckoutController>(context, listen: false).clearData();
    Provider.of<CheckoutController>(context, listen: false)
        .digitalOnly(widget.onlyDigital, isUpdate: false);
    Provider.of<CheckoutController>(context, listen: false)
        .directTopUpOnly(widget.onlyDirectTopUp, isUpdate: false);

    if (splashController.configModel?.systemTaxIncludeStatus != 1) {
      _tax = widget.tax;
    }
  }

  double _roundAmount(double value) {
    return double.parse(value.toStringAsFixed(2));
  }

  double _amountBeforeServiceFee() {
    final double amount =
        _order + widget.shippingFee - widget.discount - _couponDiscount + _tax;
    return _roundAmount(amount < 0 ? 0 : amount);
  }

  double _serviceFeeAmount() {
    final config =
        Provider.of<SplashController>(context, listen: false).configModel;
    final serviceFeeStatus = config?.customerServiceFeeStatus ?? 0;
    final serviceFeeRate = config?.customerServiceFee ?? 0;
    final serviceFeeType =
        (config?.customerServiceFeeType ?? 'percent').toLowerCase();

    if (serviceFeeStatus != 1 || serviceFeeRate <= 0) {
      return 0;
    }

    if (serviceFeeType == 'flat') {
      return _roundAmount(serviceFeeRate);
    }

    return _roundAmount((_amountBeforeServiceFee() / 100) * serviceFeeRate);
  }

  double _payableAmount() {
    return _roundAmount(
        _amountBeforeServiceFee() + _serviceFeeAmount() - _referralDiscount);
  }

  @override
  Widget build(BuildContext context) {
    _order = widget.totalOrderAmount + widget.discount;
    return Scaffold(
      resizeToAvoidBottomInset: true,
      key: _scaffoldKey,
      bottomNavigationBar:
          Consumer<AddressController>(builder: (context, locationProvider, _) {
        return Consumer<CheckoutController>(
            builder: (context, orderProvider, child) {
          return Consumer<CouponController>(
              builder: (context, couponProvider, _) {
            if (splashController.configModel?.systemTaxIncludeStatus != 1) {
              _tax = CartHelper().calculateVatTax(
                  Provider.of<CartController>(context, listen: false).cartList);
            }
            return Consumer<CartController>(
                builder: (context, cartProvider, _) {
              return Consumer<ProfileController>(
                  builder: (context, profileProvider, _) {
                return orderProvider.isLoading
                    ? const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                            SizedBox(
                                width: 30,
                                height: 30,
                                child: CircularProgressIndicator())
                          ])
                    : Container(
                        padding:
                            const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        color: Theme.of(context).cardColor,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const CheckoutConditionCheckBox(),
                            const SizedBox(height: Dimensions.paddingSizeSmall),
                            CustomButton(
                              onTap: (orderProvider.isLoading ||
                                      !orderProvider.isAcceptTerms)
                                  ? null
                                  : () async {
                                      if (_requiresShippingAddress &&
                                          orderProvider.addressIndex == null) {
                                        RouterHelper.getSavedAddressListRoute(
                                            fromGuest:
                                                !Provider.of<AuthController>(
                                                        context,
                                                        listen: false)
                                                    .isLoggedIn());
                                        showCustomSnackBarWidget(
                                            getTranslated(
                                                'select_a_shipping_address',
                                                context),
                                            Get.context!,
                                            snackBarType: SnackBarType.warning);
                                      } else if (_requiresShippingAddress &&
                                          orderProvider.billingAddressIndex == null &&
                                          !_billingAddress) {
                                        showCustomSnackBarWidget(
                                            getTranslated(
                                                'you_cant_place_order_of_digital_product_without_billing_address',
                                                context),
                                            Get.context!,
                                            snackBarType: SnackBarType.warning);
                                      } else if (_requiresShippingAddress &&
                                          ((orderProvider.billingAddressIndex == null &&
                                                  !orderProvider.sameAsBilling &&
                                                  _billingAddress) ||
                                              (orderProvider.billingAddressIndex == null &&
                                                  _billingAddress &&
                                                  !orderProvider.sameAsBilling))) {
                                        RouterHelper
                                            .getSavedBillingAddressListRoute(
                                                fromGuest: !Provider.of<
                                                            AuthController>(
                                                        context,
                                                        listen: false)
                                                    .isLoggedIn());
                                        showCustomSnackBarWidget(
                                            getTranslated(
                                                'select_a_billing_address',
                                                context),
                                            Get.context!,
                                            snackBarType: SnackBarType.warning);
                                      } else {
                                        if (!orderProvider
                                                .isCheckCreateAccount ||
                                            (orderProvider
                                                    .isCheckCreateAccount &&
                                                (passwordFormKey.currentState
                                                        ?.validate() ??
                                                    false))) {
                                          String orderNote = orderProvider
                                              .orderNoteController.text
                                              .trim();
                                          _couponDiscount =
                                              couponProvider.discount ?? 0;
                                          _referralDiscount = orderProvider
                                                  .referralAmount?.amount ??
                                              0;
                                          String couponCode =
                                              couponProvider.discount != null &&
                                                      couponProvider.discount !=
                                                          0
                                                  ? couponProvider.couponCode
                                                  : '';
                                          String couponCodeAmount =
                                              couponProvider.discount != null &&
                                                      couponProvider.discount !=
                                                          0
                                                  ? couponProvider.discount
                                                      .toString()
                                                  : '0';

                                          String addressId =
                                              orderProvider.addressIndex != null
                                                  ? locationProvider
                                                      .addressList![
                                                          orderProvider
                                                              .addressIndex!]
                                                      .id
                                                      .toString()
                                                  : '';

                                          String billingAddressId = '';
                                          if (_billingAddress && locationProvider.addressList != null && locationProvider.addressList!.isNotEmpty) {
                                            if (!orderProvider.sameAsBilling && orderProvider.billingAddressIndex != null) {
                                              billingAddressId = locationProvider.addressList![orderProvider.billingAddressIndex!].id.toString();
                                            } else if (orderProvider.sameAsBilling && orderProvider.addressIndex != null) {
                                              billingAddressId = locationProvider.addressList![orderProvider.addressIndex!].id.toString();
                                            }
                                          }

                                          if (orderProvider
                                                  .paymentMethodIndex !=
                                              -1) {
                                            final AuthController authController =
                                                Provider.of<AuthController>(
                                                    context,
                                                    listen: false);
                                            final String? customerId =
                                                _resolveCustomerId(
                                                    authController,
                                                    profileProvider);

                                            if (customerId == null ||
                                                customerId.isEmpty) {
                                              showCustomSnackBarWidget(
                                                getTranslated(
                                                        'something_went_wrong',
                                                        context) ??
                                                    'Something went wrong',
                                                context,
                                                snackBarType:
                                                    SnackBarType.error,
                                              );
                                              return;
                                            }

                                            orderProvider.digitalPaymentPlaceOrder(
                                                orderNote: orderNote,
                                                customerId: customerId,
                                                addressId: addressId,
                                                billingAddressId:
                                                    billingAddressId,
                                                couponCode: couponCode,
                                                couponDiscount:
                                                    couponCodeAmount,
                                                paymentMethod: orderProvider
                                                    .selectedDigitalPaymentMethodName);
                                          } else if (orderProvider
                                                  .isCODChecked &&
                                              !_isDigitalOnlyCheckout) {
                                            orderProvider.placeOrder(
                                                callback: _callback,
                                                addressID: addressId,
                                                couponCode: couponCode,
                                                couponAmount: couponCodeAmount,
                                                billingAddressId:
                                                    billingAddressId,
                                                orderNote: orderNote);
                                          } else if (orderProvider
                                                  .isOfflineChecked &&
                                              !_isDigitalOnlyCheckout) {
                                            // Navigator.of(context).push(MaterialPageRoute(builder: (_)=> OfflinePaymentScreen(payableAmount: _payableAmount(), callback: _callback)));
                                            RouterHelper
                                                .getOfflinePaymentScreen(
                                                    payableAmount:
                                                        _payableAmount(),
                                                    callback: _callback);
                                          } else if (orderProvider
                                              .isWalletChecked) {
                                            if ((profileProvider.balance ?? 0) <
                                              _payableAmount()) {
                                              showCustomSnackBarWidget(
                                                getTranslated(
                                                  'insufficient_balance',
                                                  context),
                                                context,
                                                snackBarType:
                                                  SnackBarType.warning);
                                            } else {
                                              orderProvider.placeOrder(
                                                callback: _callback,
                                                wallet: true,
                                                addressID: addressId,
                                                couponCode: couponCode,
                                                couponAmount:
                                                  couponCodeAmount,
                                                billingAddressId:
                                                  billingAddressId,
                                                orderNote: orderNote);
                                            }
                                          } else if (orderProvider.isAppleIapChecked) {
                                            Provider.of<IapPurchaseController>(context, listen: false)
                                                .purchaseDigitalCart(
                                              couponCode: couponCode,
                                              orderNote: orderNote,
                                              addressId: addressId,
                                              billingAddressId: billingAddressId,
                                              callback: (success, message) {
                                                if (success) {
                                                  _callback(true, message, orderProvider.getFirstOrderId(message), false);
                                                } else {
                                                  showCustomSnackBarWidget(
                                                    message,
                                                    context,
                                                    snackBarType: SnackBarType.error,
                                                  );
                                                }
                                              },
                                            );
                                          } else {
                                            showCustomSnackBarWidget(
                                              getTranslated(
                                                      'choose_payment_method',
                                                      context) ??
                                                  'Payment method is not selected',
                                              context,
                                              snackBarType:
                                                  SnackBarType.warning,
                                            );
                                          }
                                        }
                                      }
                                    },
                              buttonText:
                                  '${getTranslated('proceed', context)}',
                            )
                          ],
                        ),
                      );
              });
            });
          });
        });
      }),
      appBar: CustomAppBar(title: getTranslated('checkout', context)),
      body: Consumer<AuthController>(builder: (context, authProvider, _) {
        return Consumer<CheckoutController>(
            builder: (context, orderProvider, _) {
          return Column(
            children: [
              Expanded(
                child: ListView(
                  physics: const BouncingScrollPhysics(),
                  padding: const EdgeInsets.all(0),
                  children: [
                    SizedBox(height: Dimensions.paddingSizeSmall),
                    if (_requiresShippingAddress)
                      Padding(
                        padding: const EdgeInsets.only(
                            bottom: Dimensions.paddingSizeDefault),
                        child: ShippingDetailsWidget(
                          hasPhysical: widget.hasPhysical,
                          billingAddress: _billingAddress,
                          passwordFormKey: passwordFormKey,
                        ),
                      ),
                    if (!_requiresShippingAddress &&
                        !Provider.of<AuthController>(context, listen: false).isLoggedIn())
                      Padding(
                        padding: const EdgeInsets.only(
                            bottom: Dimensions.paddingSizeDefault),
                        child: CreateAccountWidget(formKey: passwordFormKey),
                      ),
                    if (Provider.of<AuthController>(context, listen: false)
                        .isLoggedIn())
                      Padding(
                        padding: const EdgeInsets.only(
                            bottom: Dimensions.paddingSizeSmall),
                        child: CouponApplyWidget(
                          couponController: _controller,
                          orderAmount: _order,
                        ),
                      ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 0),
                      child:
                          ChoosePaymentWidget(
                              onlyDigital:
                                  widget.onlyDigital || widget.onlyDirectTopUp),
                    ),
                    if (orderProvider.isWalletChecked &&
                        Provider.of<AuthController>(context, listen: false)
                            .isLoggedIn())
                      Consumer<ProfileController>(
                        builder: (context, profileProvider, _) {
                          return WalletPaymentWidget(
                            embedded: true,
                            onTap: null,
                            currentBalance: profileProvider.balance ?? 0,
                            orderAmount: _payableAmount(),
                          );
                        },
                      ),
                    SizedBox(height: Dimensions.paddingSizeSmall),
                    Container(
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        boxShadow: [
                          BoxShadow(
                              color: Theme.of(context)
                                  .hintColor
                                  .withValues(alpha: 0.2),
                              spreadRadius: 3,
                              blurRadius: 3)
                        ],
                      ),
                      padding: const EdgeInsets.fromLTRB(
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeSmall,
                      ),
                      child: Text(
                        getTranslated('order_summary', context) ?? '',
                        style: textMedium.copyWith(
                          fontSize: Dimensions.fontSizeLarge,
                          color: Theme.of(context).textTheme.bodyLarge?.color,
                        ),
                      ),
                    ),
                    Container(
                      color: Theme.of(context).cardColor,
                      padding: const EdgeInsets.symmetric(
                          horizontal: Dimensions.paddingSizeDefault),
                      child: Consumer<CheckoutController>(
                        builder: (context, checkoutController, child) {
                          _couponDiscount =
                              Provider.of<CouponController>(context).discount ??
                                  0;
                          _referralDiscount =
                              Provider.of<CheckoutController>(context)
                                      .referralAmount
                                      ?.amount ??
                                  0;
                          final double serviceFeeAmount = _serviceFeeAmount();
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              widget.quantity > 1
                                  ? AmountWidget(
                                      title:
                                          '${getTranslated('sub_total', context)} ${' (${widget.quantity} ${getTranslated('items', context)}) '}',
                                      amount: PriceConverter.convertPrice(
                                          context, _order),
                                    )
                                  : AmountWidget(
                                      title:
                                          '${getTranslated('sub_total', context)} ${'(${widget.quantity} ${getTranslated('item', context)})'}',
                                      amount: PriceConverter.convertPrice(
                                          context, _order),
                                    ),
                              AmountWidget(
                                title: getTranslated('shipping_fee', context),
                                amount: PriceConverter.convertPrice(
                                    context, widget.shippingFee),
                              ),
                              AmountWidget(
                                title: getTranslated('discount', context),
                                amount: PriceConverter.convertPrice(
                                    context, widget.discount),
                              ),
                              AmountWidget(
                                title: getTranslated('coupon_voucher', context),
                                amount: PriceConverter.convertPrice(
                                    context, _couponDiscount),
                              ),
                              if (serviceFeeAmount > 0)
                                AmountWidget(
                                  title:
                                      getTranslated('service_fee', context) ??
                                          'Service fee',
                                  amount: PriceConverter.convertPrice(
                                      context, serviceFeeAmount),
                                ),
                              if (splashController
                                      .configModel?.systemTaxIncludeStatus !=
                                  1)
                                AmountWidget(
                                  title: getTranslated('tax', context),
                                  amount: PriceConverter.convertPrice(
                                      context, _tax),
                                ),
                              if (_referralDiscount > 0)
                                AmountWidget(
                                  title: getTranslated(
                                      'referral_discount', context),
                                  amount: PriceConverter.convertPrice(
                                      context, _referralDiscount),
                                ),
                              Divider(
                                  height: 5,
                                  color: Theme.of(context).hintColor),
                              AmountWidget(
                                fontSize: Dimensions.fontSizeLarge,
                                isTitleBlack: true,
                                title:
                                    '${getTranslated('total_payable', context)} ${Provider.of<SplashController>(Get.context!, listen: false).configModel?.systemTaxIncludeStatus == 1 ? getTranslated('inc_vat_tax', context) : ''} ',
                                amount: PriceConverter.convertPrice(
                                    context, _payableAmount()),
                              ),
                              SizedBox(height: Dimensions.paddingSizeSmall),
                            ],
                          );
                        },
                      ),
                    ),
                    SizedBox(height: Dimensions.paddingSizeSmall),
                    Container(
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        boxShadow: [
                          BoxShadow(
                              color: Theme.of(context)
                                  .hintColor
                                  .withValues(alpha: 0.2),
                              spreadRadius: 3,
                              blurRadius: 3)
                        ],
                      ),
                      padding: const EdgeInsets.fromLTRB(
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            Text(
                              '${getTranslated('order_note', context)}',
                              style: textRegular.copyWith(
                                fontSize: Dimensions.fontSizeLarge,
                                color: Theme.of(context)
                                    .textTheme
                                    .bodyLarge
                                    ?.color,
                              ),
                            ),
                          ]),
                          const SizedBox(height: Dimensions.paddingSizeSmall),
                          CustomTextFieldWidget(
                            hintText: getTranslated('enter_note', context),
                            inputType: TextInputType.multiline,
                            inputAction: TextInputAction.done,
                            maxLines: 3,
                            focusNode: _orderNoteNode,
                            controller: orderProvider.orderNoteController,
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: Dimensions.paddingSizeDefault),
                  ],
                ),
              ),
            ],
          );
        });
      }),
    );
  }

  void _callback(bool isSuccess, String message, String orderID,
      bool createAccount) async {
    if (isSuccess) {
      await _syncCartAfterOrderPlaced();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        bool isLoggedIn =
            Provider.of<AuthController>(context, listen: false).isLoggedIn();
        String? orderId =
            Provider.of<CheckoutController>(context, listen: false)
                .getFirstOrderId(orderID);

        if (widget.onlyDirectTopUp && orderId != null) {
          if (isLoggedIn) {
            RouterHelper.getOrderDetailsScreenRoute(
              orderId: int.parse(orderId),
              action: RouteAction.pushReplacement,
            );
          } else {
            RouterHelper.getDashboardRoute(
                action: RouteAction.pushReplacement, page: 'home');
          }

          Future.delayed(const Duration(milliseconds: 300), () {
            showModalBottomSheet(
              isDismissible: false,
              enableDrag: false,
              context: Get.context!,
              isScrollControlled: true,
              backgroundColor: Colors.transparent,
              shape: const RoundedRectangleBorder(
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              builder: (context) {
                return Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius:
                        const BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  child: OrderPlaceBottomSheetWidget(
                    orderID: orderID,
                    icon: Icons.check,
                    title: getTranslated('order_placed', Get.context!),
                    description: getTranslated('direct_topup_order_completed', Get.context!) ??
                        getTranslated('your_order_placed', Get.context!),
                    isFailed: false,
                  ),
                );
              },
            );
          });
        } else if (widget.onlyDigital && orderId != null) {
          RouterHelper.getDigitalProductDeliveryScreenRoute(
              orderId: int.parse(orderId), action: RouteAction.pushReplacement);
        } else {
          if (isLoggedIn && orderId != null) {
            RouterHelper.getOrderScreenRoute(
                isBackButtonExist: true,
                action: RouteAction.push,
                fromPlaceOrder: true);
          } else {
            RouterHelper.getDashboardRoute(
                action: RouteAction.pushReplacement, page: 'home');
          }

          Future.delayed(Duration(milliseconds: 300), () {
            showModalBottomSheet(
              isDismissible: false,
              enableDrag: false,
              context: Get.context!,
              isScrollControlled: true,
              backgroundColor: Colors.transparent,
              shape: const RoundedRectangleBorder(
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              builder: (context) {
                return Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius:
                        const BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  child: OrderPlaceBottomSheetWidget(
                    orderID: orderID,
                    icon: Icons.check,
                    title: getTranslated(
                      createAccount
                          ? 'order_placed_Account_Created'
                          : 'order_placed',
                      Get.context!,
                    ),
                    description: getTranslated('your_order_placed', Get.context!),
                    isFailed: false,
                  ),
                );
              },
            );
          });
        }
      });
    } else {
      showCustomSnackBarWidget(message, context,
          snackBarType: SnackBarType.error);
    }
  }

  Future<void> _syncCartAfterOrderPlaced() async {
    if (!mounted) {
      return;
    }
    final cartController =
        Provider.of<CartController>(context, listen: false);
    await cartController.refreshAfterOrderPlaced(context);
    Provider.of<CouponController>(context, listen: false).removeCoupon();
  }
}
