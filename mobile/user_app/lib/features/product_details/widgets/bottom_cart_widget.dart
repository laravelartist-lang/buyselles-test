import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/widgets/cart_bottom_sheet_widget.dart';
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
                _buyNowDirectly(context);
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
                _addToCartDirectly(context);
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

  void _addToCartDirectly(BuildContext context) async {
    var productDetailsController = Provider.of<ProductDetailsController>(context, listen: false);
    var cartController = Provider.of<CartController>(context, listen: false);
    
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
      for (var variation in variationList) {
        variationType = '$variationType-$variation';
      }
    } else {
      bool isFirst = true;
      for (var variation in variationList) {
        if(isFirst) {
          variationType = '$variationType$variation';
          isFirst = false;
        }else {
          variationType = '$variationType-$variation';
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
    int? stock = widget.product!.currentStock;
    variationType = variationType.replaceAll(' ', '');
    if (widget.product!.variation != null) {
      for(Variation v in widget.product!.variation!) {
        if(v.type == variationType) {
          price = v.price;
          variation = v;
          stock = v.qty;
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

    CartModelBody cart = CartModelBody(
        productId: widget.product!.id,
        variant: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
        widget.product!.colors![productDetailsController.variantIndex ?? 0].name : '',
        color: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
        widget.product!.colors![productDetailsController.variantIndex ?? 0].code : '',
        variation : variation,
        quantity: qty,
        variantKey: variantKey,
        digitalVariantPrice: digitalVariantPrice,
        productType: widget.product!.productType
    );

    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    var configProvider = Provider.of<SplashController>(context, listen: false);

    if(configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn){
      showModalBottomSheet(
        backgroundColor: Colors.transparent,
        context:context, builder: (_)=> const NotLoggedInBottomSheetWidget(fromPage: RouterHelper.productDetailsScreen),
      );
    } else if( (stock! < (widget.product!.minimumOrderQty ?? 1))  &&  widget.product!.productType == "physical" && (widget.product!.hasActiveSupplierMapping ?? 0) != 1 ) {
      showCustomSnackBarWidget(getTranslated('out_of_stock', context), context, snackBarType: SnackBarType.warning);
    } else if(stock >= (widget.product!.minimumOrderQty ?? 1) || widget.product!.productType == "digital" || (widget.product!.hasActiveSupplierMapping ?? 0) == 1) {
      await cartController.addToCartAPI(
        cart, context, widget.product!.choiceOptions ?? [],
        productDetailsController.variationIndex, buyNow: 0, showBottomSheet: false,
      );
    }
  }

  void _buyNowDirectly(BuildContext context) async {
    var productDetailsController = Provider.of<ProductDetailsController>(context, listen: false);
    var cartController = Provider.of<CartController>(context, listen: false);
    
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
      for (var variation in variationList) {
        variationType = '$variationType-$variation';
      }
    } else {
      bool isFirst = true;
      for (var variation in variationList) {
        if(isFirst) {
          variationType = '$variationType$variation';
          isFirst = false;
        }else {
          variationType = '$variationType-$variation';
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
    int? stock = widget.product!.currentStock;
    variationType = variationType.replaceAll(' ', '');
    if (widget.product!.variation != null) {
      for(Variation v in widget.product!.variation!) {
        if(v.type == variationType) {
          price = v.price;
          variation = v;
          stock = v.qty;
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

    CartModelBody cart = CartModelBody(
        productId: widget.product!.id,
        variant: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
        widget.product!.colors![productDetailsController.variantIndex ?? 0].name : '',
        color: (widget.product!.colors != null && widget.product!.colors!.isNotEmpty) ?
        widget.product!.colors![productDetailsController.variantIndex ?? 0].code : '',
        variation : variation,
        quantity: qty,
        variantKey: variantKey,
        digitalVariantPrice: digitalVariantPrice,
        productType: widget.product!.productType
    );

    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();
    var configProvider = Provider.of<SplashController>(context, listen: false);

    if(configProvider.configModel?.guestCheckOut == 0 && !isLoggedIn){
      showModalBottomSheet(
        backgroundColor: Colors.transparent,
        context:context, builder: (_)=> const NotLoggedInBottomSheetWidget(fromPage: RouterHelper.productDetailsScreen),
      );
    } else if( (stock! < (widget.product!.minimumOrderQty ?? 1))  &&  widget.product!.productType == "physical" && (widget.product!.hasActiveSupplierMapping ?? 0) != 1 ) {
      showCustomSnackBarWidget(getTranslated('out_of_stock', context), context, snackBarType: SnackBarType.warning);
    } else if(stock >= (widget.product!.minimumOrderQty ?? 1) || widget.product!.productType == "digital" || (widget.product!.hasActiveSupplierMapping ?? 0) == 1) {
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
    final double discount = cart.discount! * cart.quantity!;
    final double amount = (cart.price! - cart.discount!) * cart.quantity!;
    final int totalQuantity = cart.quantity ?? 0;
    final bool hasPhysical = cart.productType == "physical";
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
      onlyDigital: !hasPhysical,
      hasPhysical: hasPhysical,
      quantity: totalQuantity,
    );
  }
}
