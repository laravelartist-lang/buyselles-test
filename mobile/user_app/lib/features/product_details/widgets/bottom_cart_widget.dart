import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/direct_topup_helper.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/cart_bottom_sheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/direct_topup_bottom_sheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/helper/responsive_helper.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/helper/shop_helper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/controllers/cart_controller.dart';
import 'package:flutter_sixvalley_ecommerce/theme/controllers/theme_controller.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:provider/provider.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/shipping_method_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/shipping/domain/models/shipping_method_model.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/not_logged_in_bottom_sheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';

class BottomCartWidget extends StatefulWidget {
  final ProductDetailsModel? product;
  const BottomCartWidget({super.key, required this.product});

  @override
  State<BottomCartWidget> createState() => _BottomCartWidgetState();
}

class _BottomCartWidgetState extends State<BottomCartWidget> {
  bool vacationIsOn = false;
  bool temporaryClose = false;

  @override
  void initState() {
    super.initState();

    vacationIsOn = ShopHelper.isVacationActive(
      context,
      startDate: widget.product?.seller?.shop?.vacationStartDate,
      endDate: widget.product?.seller?.shop?.vacationEndDate,
      vacationDurationType: widget.product?.seller?.shop?.vacationDurationType,
      vacationStatus: widget.product?.seller?.shop?.vacationStatus,
      isInHouseSeller: widget.product?.addedBy == 'admin',
    );


    if(widget.product?.addedBy == 'admin') {
      if(widget.product != null && (Provider.of<SplashController>(context, listen: false).configModel?.inhouseTemporaryClose?.status ?? false)){
        temporaryClose = true;
      }else{
        temporaryClose = false;
      }
    } else {
      if(widget.product != null && widget.product!.seller != null && widget.product!.seller!.shop!.temporaryClose!){
        temporaryClose = true;
      }else{
        temporaryClose = false;
      }
    }
  }


  @override
  Widget build(BuildContext context) {
    return Container(
      height: 75,
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).highlightColor,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(10),
          topRight: Radius.circular(10),
        ),
        boxShadow: [BoxShadow(color: Theme.of(context).hintColor, blurRadius: .5, spreadRadius: .1)],
      ),
      child: Row(children: [
        Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
          child: Stack(children: [
            InkWell(
              onTap: () => RouterHelper.getCartScreenRoute(action: RouteAction.push),
              child: Image.asset(Images.cartArrowDownImage, color: Theme.of(context).textTheme.bodyMedium?.color),
            ),
            Positioned.fill(
              child: Container(
                transform: Matrix4.translationValues(10, -3, 0),
                child: Align(
                  alignment: Alignment.topRight,
                  child: Consumer<CartController>(builder: (context, cart, child) {
                    return Container(
                      height: ResponsiveHelper.isTab(context) ? 25 : 20,
                      width: ResponsiveHelper.isTab(context) ? 25 : 20,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context).textTheme.bodyMedium?.color,
                      ),
                      child: Center(
                        child: Text(
                          cart.cartList.length.toString(),
                          style: textRegular.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).highlightColor,
                          ),
                        ),
                      ),
                    );
                  }),
                ),
              ),
            ),
          ]),
        ),
        const SizedBox(width: Dimensions.paddingSizeExtraSmall),

        /// BUY NOW button
        Expanded(
          child: InkWell(
            onTap: () {
              if (vacationIsOn || temporaryClose) {
                showCustomSnackBarWidget(
                  getTranslated('this_shop_is_close_now', context),
                  context,
                  snackBarType: SnackBarType.error,
                );
              } else {
                _handlePurchaseAction(context, buyNow: true);
              }
            },
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Theme.of(context).primaryColor, width: 1.5),
                color: Colors.transparent,
              ),
              child: Text(
                getTranslated('buy_now', context)!,
                style: titilliumSemiBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(width: Dimensions.paddingSizeExtraSmall),

        /// ADD TO CART button
        Expanded(
          child: InkWell(
            onTap: () {
              if (vacationIsOn || temporaryClose) {
                showCustomSnackBarWidget(
                  getTranslated('this_shop_is_close_now', context),
                  context,
                  snackBarType: SnackBarType.error,
                );
              } else {
                _handlePurchaseAction(context, buyNow: false);
              }
            },
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(10),
                color: Theme.of(context).primaryColor,
              ),
              child: Text(
                getTranslated('add_to_cart', context)!,
                style: titilliumSemiBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Provider.of<ThemeController>(context, listen: false).darkTheme
                      ? Theme.of(context).hintColor
                      : Theme.of(context).highlightColor,
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }

  ProductDetailsModel? _resolveProduct(BuildContext context) {
    final ProductDetailsModel? controllerProduct =
        Provider.of<ProductDetailsController>(context, listen: false).productDetailsModel;

    return controllerProduct ?? widget.product;
  }

  void _handlePurchaseAction(BuildContext context, {required bool buyNow}) {
    final ProductDetailsModel? product = _resolveProduct(context);

    if (DirectTopUpHelper.shouldPromptDirectTopUpSheet(product)) {
      if (!_ensureCheckoutAllowed(context)) {
        return;
      }
      _showDirectTopUpPurchaseSheet(context, product);
      return;
    }

    if (product != null && _shouldUseCartBottomSheet(product)) {
      _openCartBottomSheetIfNeeded(context, product);
      return;
    }

    if (buyNow) {
      _buyNowDirectly(context);
    } else {
      _addToCartDirectly(context);
    }
  }

  BuildContext? _modalContext(BuildContext context) {
    if (context.mounted) {
      return context;
    }

    return Get.context ?? navigatorKey.currentContext;
  }

  bool _ensureCheckoutAllowed(BuildContext context) {
    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    final configProvider = Provider.of<SplashController>(context, listen: false);

    if (configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn) {
      final BuildContext? modalContext = _modalContext(context);
      if (modalContext == null) {
        return false;
      }

      _presentModalBottomSheet(
        modalContext,
        builder: (_) => const NotLoggedInBottomSheetWidget(fromPage: RouterHelper.productDetailsScreen),
      );
      return false;
    }

    return true;
  }

  bool _hasProductVariants(ProductDetailsModel product) {
    final bool hasColors = product.colors != null && product.colors!.isNotEmpty;
    final bool hasChoices = product.choiceOptions != null && product.choiceOptions!.isNotEmpty;

    if (DirectTopUpHelper.shouldPromptDirectTopUpSheet(product)) {
      return hasColors || hasChoices;
    }

    final bool hasDigitalExtensions = product.digitalProductExtensions != null
        && product.digitalProductExtensions!.isNotEmpty;

    return hasColors || hasChoices || hasDigitalExtensions || product.requiresDenominationSelection;
  }

  void _openCartBottomSheetIfNeeded(BuildContext context, ProductDetailsModel product) {
    if (_hasProductVariants(product)) {
      final BuildContext? modalContext = _modalContext(context);
      if (modalContext == null) {
        return;
      }
      Provider.of<ProductDetailsController>(modalContext, listen: false)
          .initializeSupplierDenominationDefaults(product);
      _presentModalBottomSheet(
        modalContext,
        builder: (_) => CartBottomSheetWidget(product: product),
      );
    }
  }

  bool _shouldUseCartBottomSheet(ProductDetailsModel product) {
    return _hasProductVariants(product);
  }

  void _presentModalBottomSheet(
    BuildContext context, {
    required WidgetBuilder builder,
  }) {
    if (!context.mounted) {
      return;
    }

    showModalBottomSheet<void>(
      context: context,
      useRootNavigator: true,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      isDismissible: true,
      enableDrag: true,
      builder: builder,
    );
  }

  void _showDirectTopUpPurchaseSheet(BuildContext context, ProductDetailsModel? product) {
    if (product == null) {
      return;
    }

    final BuildContext? modalContext = _modalContext(context);
    if (modalContext == null) {
      return;
    }

    final DirectTopUpConfig? config = DirectTopUpHelper.resolveDirectTopUpConfig(product);
    if (config == null) {
      return;
    }

    Provider.of<ProductDetailsController>(modalContext, listen: false)
        .initializeDirectTopUpDefaults();

    if (_hasProductVariants(product)) {
      _presentModalBottomSheet(
        modalContext,
        builder: (_) => CartBottomSheetWidget(product: product),
      );
      return;
    }

    _presentModalBottomSheet(
      modalContext,
      builder: (_) => DirectTopUpBottomSheetWidget(
        product: product,
        config: config,
        onNavigateToCheckout: _navigateToCheckoutScreen,
      ),
    );
  }

  CartModelBody _buildCartModelBody(ProductDetailsController productDetailsController) {
    String? variantKey;
    double? digitalVariantPrice;
    List<String> variationFileType = [];
    List<List<String>> extensions = [];

    Variation? variation;
    String? variantName = (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
    widget.product!.colors![productDetailsController.variantIndex ?? 0].name : null;
    List<String> variationList = [];
    if (widget.product!.choiceOptions != null) {
      for(int index=0; index < widget.product!.choiceOptions!.length; index++) {
        int variationIdx = (productDetailsController.variationIndex != null && productDetailsController.variationIndex!.length > index)
            ? productDetailsController.variationIndex![index]
            : 0;
        variationList.add(widget.product!.choiceOptions![index].options![variationIdx].trim());
      }
    }
    String variationType = '';
    if(variantName != null) {
      variationType = variantName;
      for (var variationItem in variationList) {
        variationType = '$variationType-$variationItem';
      }
    } else {
      bool isFirst = true;
      for (var variationItem in variationList) {
        if(isFirst) {
          variationType = '$variationType$variationItem';
          isFirst = false;
        }else {
          variationType = '$variationType-$variationItem';
        }
      }
    }

    if(widget.product?.digitalProductExtensions != null){
      widget.product?.digitalProductExtensions?.keys.forEach((key) {
        variationFileType.add(key);
        extensions.add(widget.product?.digitalProductExtensions?[key] ?? []);
      });
    }

    double? price = widget.product!.unitPrice;
    variationType = variationType.replaceAll(' ', '');
    if (widget.product!.variation != null) {
      for(Variation v in widget.product!.variation!) {
        if(v.type == variationType) {
          price = v.price;
          variation = v;
          break;
        }
      }
    }

    if(variationFileType.isNotEmpty && extensions.isNotEmpty) {
      int dIndex = productDetailsController.digitalVariationIndex ?? 0;
      int dSubIndex = productDetailsController.digitalVariationSubindex ?? 0;
      if (dIndex < variationFileType.length && dIndex < extensions.length && dSubIndex < extensions[dIndex].length) {
        variantKey = '${variationFileType[dIndex]}-${extensions[dIndex][dSubIndex]}';
        if (widget.product!.digitalVariation != null) {
          for (int i=0; i<widget.product!.digitalVariation!.length; i++) {
            if(widget.product!.digitalVariation?[i].variantKey == variantKey){
              price = double.tryParse(widget.product!.digitalVariation![i].price.toString());
            }
          }
        }
      }
    }
    digitalVariantPrice = variantKey != null ? price : null;

    int qty = (productDetailsController.quantity ?? 0) < (widget.product!.minimumOrderQty ?? 1)
        ? (widget.product!.minimumOrderQty ?? 1)
        : productDetailsController.quantity!;

    return CartModelBody(
      productId: widget.product!.id,
      variant: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
      widget.product!.colors![productDetailsController.variantIndex ?? 0].name : '',
      color: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
      widget.product!.colors![productDetailsController.variantIndex ?? 0].code : '',
      variation: variation,
      quantity: qty,
      variantKey: variantKey,
      digitalVariantPrice: digitalVariantPrice,
      productType: widget.product!.productType,
      supplierDenominationId: productDetailsController.resolveSupplierDenominationIdForCart(widget.product),
      customAmount: productDetailsController.resolveCustomAmountForCart(widget.product),
    );
  }

  void _addToCartDirectly(BuildContext context) async {
    final ProductDetailsModel? product = _resolveProduct(context);
    if (DirectTopUpHelper.shouldPromptDirectTopUpSheet(product)) {
      _showDirectTopUpPurchaseSheet(context, product);
      return;
    }

    var productDetailsController = Provider.of<ProductDetailsController>(context, listen: false);
    var cartController = Provider.of<CartController>(context, listen: false);

    final String? denomError = productDetailsController.validateSupplierDenomination(context, product);
    if (denomError != null) {
      showCustomSnackBarWidget(denomError, context, snackBarType: SnackBarType.warning);
      return;
    }

    CartModelBody cart = _buildCartModelBody(productDetailsController);
    int? stock = cart.variation?.qty ?? widget.product!.currentStock;

    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    var configProvider = Provider.of<SplashController>(context, listen: false);

    if(configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn){
      showModalBottomSheet(
        backgroundColor: Colors.transparent,
        context:context, builder: (_)=> const NotLoggedInBottomSheetWidget(fromPage: RouterHelper.productDetailsScreen),
      );
    } else if( (stock! < (widget.product!.minimumOrderQty ?? 1))  &&  widget.product!.productType == "physical" && (widget.product!.hasActiveSupplierMapping ?? 0) != 1 ) {
      showCustomSnackBarWidget(getTranslated('out_of_stock', context), context, snackBarType: SnackBarType.warning);
    } else if(stock >= (widget.product!.minimumOrderQty ?? 1) || widget.product!.productType == "digital" || DirectTopUpHelper.isTruthy(widget.product!.hasActiveSupplierMapping)) {
      await cartController.addToCartAPI(
        cart, context, widget.product!.choiceOptions ?? [],
        productDetailsController.variationIndex, buyNow: 0, showBottomSheet: false,
      );
    }
  }

  void _buyNowDirectly(BuildContext context) async {
    final ProductDetailsModel? product = _resolveProduct(context);
    if (DirectTopUpHelper.shouldPromptDirectTopUpSheet(product)) {
      _showDirectTopUpPurchaseSheet(context, product);
      return;
    }

    var productDetailsController = Provider.of<ProductDetailsController>(context, listen: false);
    var cartController = Provider.of<CartController>(context, listen: false);

    final String? denomError = productDetailsController.validateSupplierDenomination(context, product);
    if (denomError != null) {
      showCustomSnackBarWidget(denomError, context, snackBarType: SnackBarType.warning);
      return;
    }

    CartModelBody cart = _buildCartModelBody(productDetailsController);
    int? stock = cart.variation?.qty ?? widget.product!.currentStock;

    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    var configProvider = Provider.of<SplashController>(context, listen: false);

    if(configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn){
      showModalBottomSheet(
        backgroundColor: Colors.transparent,
        context:context, builder: (_)=> const NotLoggedInBottomSheetWidget(fromPage: RouterHelper.productDetailsScreen),
      );
    } else if( (stock! < (widget.product!.minimumOrderQty ?? 1))  &&  widget.product!.productType == "physical" && !DirectTopUpHelper.isTruthy(widget.product!.hasActiveSupplierMapping)) {
      showCustomSnackBarWidget(getTranslated('out_of_stock', context), context, snackBarType: SnackBarType.warning);
    } else if(stock >= (widget.product!.minimumOrderQty ?? 1) || widget.product!.productType == "digital" || DirectTopUpHelper.isTruthy(widget.product!.hasActiveSupplierMapping)) {
      final ApiResponseModel apiResponse = await cartController.addToCartAPI(
        cart, context, widget.product!.choiceOptions ?? [],
        productDetailsController.variationIndex, buyNow: 1, showBottomSheet: false,
      );

      if(apiResponse.response?.statusCode == 200){
        if(apiResponse.response?.data['status'] == 2){
          List<ShippingMethodModel>? shippingMethodList = [];
          apiResponse.response?.data['shipping_method_list'].forEach((element) {
            shippingMethodList.add(ShippingMethodModel.fromJson(element));
          });
          showDialog(context: Get.context!, builder: (context) => Dialog(
            backgroundColor: Colors.transparent,
            child: ChooseShippingMethodDialog(shippingMethodList, cart, widget.product!.choiceOptions ?? [], productDetailsController.variationIndex, _navigateToCheckoutScreen),
          ));
        } else {
          CartModel cartModel = CartModel.fromJson(apiResponse.response?.data['cart']);
          _navigateToCheckoutScreen(context, cartModel, 0);
        }
      }
    }
  }

  void _navigateToCheckoutScreen(BuildContext context, CartModel cart, double shippingCost) {
    final ProductDetailsModel? resolvedProduct = _resolveProduct(context);
    final bool isDirectTopUp = DirectTopUpHelper.shouldPromptDirectTopUpSheet(resolvedProduct)
        || DirectTopUpHelper.isDirectTopUpCartItem(cart);
    final double discount = cart.discount! * cart.quantity!;
    final double amount = (cart.price! - cart.discount!) * cart.quantity!;
    final int totalQuantity = cart.quantity ?? 0;
    final bool hasPhysical = !isDirectTopUp && cart.productType == "physical";
    double tax = 0.0;
    double shippingAmount = (shippingCost + cart.shippingCost!);

    if(cart.taxModel == "exclude") {
      tax += cart.tax! * cart.quantity!;
    }

    if(cart.freeDeliveryOrderAmount != null  ){
      shippingAmount = shippingAmount - cart.freeDeliveryOrderAmount!.shippingCostSaved!;
    }

    RouterHelper.getCheckoutScreenRoute(
      action: RouteAction.push,
      cartList: [cart],
      fromProductDetails: false,
      totalOrderAmount: amount,
      shippingFee: shippingAmount,
      discount: discount,
      tax: tax,
      sellerId: null,
      onlyDigital: isDirectTopUp || !hasPhysical,
      onlyDirectTopUp: isDirectTopUp,
      hasPhysical: hasPhysical,
      quantity: totalQuantity,
    );
  }
}
