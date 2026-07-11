import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/controllers/cart_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/direct_topup_purchase_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/shipping_method_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/features/shipping/domain/models/shipping_method_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class DirectTopUpBottomSheetWidget extends StatefulWidget {
  final ProductDetailsModel product;
  final DirectTopUpConfig config;
  final void Function(BuildContext context, CartModel cart, double shippingCost) onNavigateToCheckout;

  const DirectTopUpBottomSheetWidget({
    super.key,
    required this.product,
    required this.config,
    required this.onNavigateToCheckout,
  });

  @override
  State<DirectTopUpBottomSheetWidget> createState() => _DirectTopUpBottomSheetWidgetState();
}

class _DirectTopUpBottomSheetWidgetState extends State<DirectTopUpBottomSheetWidget> {
  @override
  void initState() {
    super.initState();
    Provider.of<ProductDetailsController>(context, listen: false).initializeDirectTopUpDefaults();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
            padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
            decoration: BoxDecoration(
              color: Theme.of(context).highlightColor,
              borderRadius: const BorderRadius.only(
                topRight: Radius.circular(20),
                topLeft: Radius.circular(20),
              ),
            ),
            child: Consumer<ProductDetailsController>(
              builder: (context, detailsController, _) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Align(
                      alignment: Alignment.centerRight,
                      child: InkWell(
                        onTap: () => Navigator.pop(context),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                          child: Icon(Icons.cancel, color: Theme.of(context).hintColor, size: 30),
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: Dimensions.homePagePadding),
                      child: Text(
                        widget.product.name ?? '',
                        style: titilliumSemiBold.copyWith(
                          fontSize: Dimensions.fontSizeLarge,
                          color: Theme.of(context).textTheme.bodyLarge?.color,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: Dimensions.paddingSizeSmall),
                    DirectTopUpPurchaseWidget(config: widget.config),
                    if (widget.config.requiresAccountVerification == true) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: Dimensions.homePagePadding),
                        child: CustomButton(
                          isLoading: detailsController.directTopUpVerifyLoading,
                          buttonText: getTranslated('verify', context),
                          onTap: detailsController.directTopUpVerifyLoading
                              ? null
                              : () => _verifyAccount(context),
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                    ],
                    Consumer<CartController>(
                      builder: (context, cartController, _) {
                        if (cartController.addToCartLoading) {
                          return const Center(
                            child: Padding(
                              padding: EdgeInsets.all(8.0),
                              child: CircularProgressIndicator(),
                            ),
                          );
                        }

                        return Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Dimensions.homePagePadding,
                            vertical: Dimensions.paddingSizeSmall,
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: CustomButton(
                                  isBuy: true,
                                  radius: 6,
                                  buttonText: getTranslated('buy_now', context),
                                  onTap: () => _handlePurchase(context, buyNow: 1),
                                ),
                              ),
                              const SizedBox(width: Dimensions.paddingSizeDefault),
                              Expanded(
                                child: CustomButton(
                                  radius: 6,
                                  buttonText: getTranslated('add_to_cart', context),
                                  onTap: () => _handlePurchase(context, buyNow: 0),
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ],
                );
              },
            ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _verifyAccount(BuildContext context) async {
    final detailsController = Provider.of<ProductDetailsController>(context, listen: false);
    final cartController = Provider.of<CartController>(context, listen: false);

    detailsController.setDirectTopUpVerifyLoading(true);
    await cartController.ensureDirectTopUpPurchaseValid(
      context,
      detailsController,
      widget.product,
      showSuccessOnVerify: true,
    );
    detailsController.setDirectTopUpVerifyLoading(false);
  }

  Future<void> _handlePurchase(BuildContext context, {required int buyNow}) async {
    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    final configProvider = Provider.of<SplashController>(context, listen: false);
    final detailsController = Provider.of<ProductDetailsController>(context, listen: false);
    final cartController = Provider.of<CartController>(context, listen: false);

    if (configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn) {
      showCustomSnackBarWidget(
        getTranslated('not_logged_in', context) ?? 'You are not logged in',
        context,
        snackBarType: SnackBarType.warning,
      );
      return;
    }

    final bool isValid = await cartController.ensureDirectTopUpPurchaseValid(
      context,
      detailsController,
      widget.product,
    );
    if (!isValid) {
      return;
    }

    final CartModelBody cart = CartModelBody(
      productId: widget.product.id,
      variant: '',
      color: '',
      variation: null,
      quantity: 1,
      variantKey: null,
      digitalVariantPrice: null,
      productType: widget.product.productType,
      directTopupAccountId: detailsController.directTopUpAccountId,
      directTopupQuantity: detailsController.directTopUpQuantity,
    );

    final ApiResponseModel apiResponse = await cartController.addToCartAPI(
      cart,
      context,
      widget.product.choiceOptions ?? [],
      detailsController.variationIndex,
      buyNow: buyNow,
    );

    if (buyNow == 1 && apiResponse.response?.statusCode == 200) {
      _onTapBuyNow(cart, context, apiResponse.response);
    }
  }

  void _onTapBuyNow(CartModelBody cart, BuildContext context, Response<dynamic>? response) {
    if (response?.data['status'] == 2) {
      final List<ShippingMethodModel> shippingMethodList = [];
      response?.data['shipping_method_list'].forEach((element) {
        shippingMethodList.add(ShippingMethodModel.fromJson(element));
      });

      showDialog(
        context: Get.context!,
        builder: (context) => Dialog(
          backgroundColor: Colors.transparent,
          child: ChooseShippingMethodDialog(
            shippingMethodList,
            cart,
            widget.product.choiceOptions ?? [],
            Provider.of<ProductDetailsController>(context, listen: false).variationIndex,
            widget.onNavigateToCheckout,
          ),
        ),
      );
    } else {
      final CartModel cartModel = CartModel.fromJson(response?.data['cart']);
      widget.onNavigateToCheckout(context, cartModel, 0);
    }
  }
}
