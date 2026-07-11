import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/services/cart_service_interface.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/controllers/product_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/shipping/controllers/shipping_controller.dart';
import 'package:flutter_sixvalley_ecommerce/helper/api_checker.dart';
import 'package:flutter_sixvalley_ecommerce/helper/direct_topup_helper.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:provider/provider.dart';


class CartController extends ChangeNotifier {
  final CartServiceInterface? cartServiceInterface;
  CartController({required this.cartServiceInterface});

  List<CartModel> _cartList = [];
  List<bool> isSelectedList = [];
  double amount = 0.0;
  bool isSelectAll = true;
  bool _cartLoading = false;
  bool  get cartLoading => _cartLoading;
  CartModel? cart;
  String? _updateQuantityErrorText;
  String? get addOrderStatusErrorText => _updateQuantityErrorText;
  bool _getData = true;
  bool _addToCartLoading = false;
  bool get addToCartLoading => _addToCartLoading;
  List<CartModel> get cartList => _cartList;
  bool get getData => _getData;


  void setCartData(){
    _getData = true;
  }

  void getCartDataLoaded(){
    _getData = false;
  }

  Future<ApiResponseModel> getCartData(BuildContext context, {bool reload = true, String? couponCode}) async {
    if (reload) {
      _cartLoading = true;
      notifyListeners();
    }

    try {
      ApiResponseModel apiResponse = await cartServiceInterface!.getCartList(couponCode: couponCode);
      if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
        _cartList = [];

        final dynamic rawData = apiResponse.response!.data;
        if (rawData is List) {
          for (final item in rawData) {
            try {
              if (item is Map<String, dynamic>) {
                _cartList.add(CartModel.fromJson(item));
              } else if (item is Map) {
                _cartList.add(CartModel.fromJson(Map<String, dynamic>.from(item)));
              }
            } catch (e, st) {
              debugPrint('Cart item parse error: $e\n$st');
            }
          }
        }
      } else {
        ApiChecker.checkApi(apiResponse);
      }

      return apiResponse;
    } catch (e, st) {
      debugPrint('getCartData error: $e\n$st');
      return ApiResponseModel.withError(e.toString());
    } finally {
      _cartLoading = false;
      notifyListeners();
    }
  }


  void setIsCartLoading() {
    _cartLoading = true;
  }

  bool updatingIncrement = false;
  bool updatingDecrement = false;



  Future<ApiResponseModel> updateCartProductQuantity(int? key, int quantity, BuildContext context, bool increment, int index) async{
    if(increment){
      cartList[index].increment = true;
    }else{
      cartList[index].decrement = true;
    }
    notifyListeners();
    ApiResponseModel apiResponse;
    apiResponse = await cartServiceInterface!.updateQuantity(key, quantity);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      cartList[index].increment  = false;
      cartList[index].decrement = false;
      String message = apiResponse.response!.data['message'].toString();
      showCustomSnackBarWidget(message, Get.context!, snackBarType: SnackBarType.success);
      await getCartData(Get.context!);
    } else {
      cartList[index].increment  = false;
      cartList[index].decrement = false;
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
    return apiResponse;
  }




  Future<ApiResponseModel> addToCartAPI(CartModelBody cart, BuildContext context, List<ChoiceOptions> choices, List<int>? variationIndexes, {int buyNow = 0, int? shippingMethodExist, int? shippingMethodId, bool showBottomSheet = true}) async {
    if (cart.productType != null && _cartList.isNotEmpty) {
      bool hasPhysical = false;
      bool hasDigital = false;
      for (var item in _cartList) {
        if (item.productType == 'physical') {
          hasPhysical = true;
        } else if (item.productType == 'digital') {
          hasDigital = true;
        }
      }

      if ((cart.productType == 'physical' && hasDigital) || (cart.productType == 'digital' && hasPhysical)) {
        showCustomSnackBarWidget(
          getTranslated('cannot_mix_physical_and_digital', context),
          context,
          snackBarType: SnackBarType.warning,
        );
        return ApiResponseModel.withError('Cannot mix physical and digital products');
      }
    }

    _addToCartLoading = true;
    notifyListeners();
    ApiResponseModel apiResponse = await cartServiceInterface!.addToCartListData(cart, choices, variationIndexes, buyNow, shippingMethodExist, shippingMethodId);
    _addToCartLoading = false;
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      if(showBottomSheet) {
        Navigator.of(Get.context!).pop();
      }
      _addToCartLoading = false;
      showCustomSnackBarWidget(apiResponse.response!.data['message'], Get.context!, snackBarType: SnackBarType.success);
      getCartData(Get.context!);
    } else {
      _addToCartLoading = false;
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
    return apiResponse;
  }

  Future<ApiResponseModel> validateDirectTopUpAccount(int productId, String accountId) async {
    return await cartServiceInterface!.validateDirectTopUpAccount(productId, accountId);
  }

  Future<bool> ensureDirectTopUpPurchaseValid(
    BuildContext context,
    ProductDetailsController detailsController,
    ProductDetailsModel product, {
    bool showSuccessOnVerify = false,
  }) async {
    final clientError = detailsController.validateDirectTopUpClientSide(context);
    if (clientError != null) {
      showCustomSnackBarWidget(clientError, context, snackBarType: SnackBarType.warning);
      return false;
    }

    if (DirectTopUpHelper.resolveDirectTopUpConfig(product)?.requiresAccountVerification != true) {
      return true;
    }

    final ApiResponseModel apiResponse = await validateDirectTopUpAccount(
      product.id!,
      detailsController.directTopUpAccountId,
    );

    final int? statusCode = apiResponse.response?.statusCode;
    if (statusCode == 200 || statusCode == 422) {
      final dynamic rawData = apiResponse.response?.data;
      if (rawData is Map) {
        final result = DirectTopUpAccountValidationResult.fromJson(
          Map<String, dynamic>.from(rawData),
        );

        if (result.supported && !result.valid) {
          showCustomSnackBarWidget(
            result.message ?? getTranslated('direct_topup_account_invalid', context) ?? 'Account is invalid',
            context,
            snackBarType: SnackBarType.warning,
          );
          detailsController.markDirectTopUpAccountVerified(false);
          return false;
        }

        if (result.supported && result.valid) {
          detailsController.markDirectTopUpAccountVerified(true);
          if (showSuccessOnVerify) {
            final String successMessage = result.username != null && result.username!.isNotEmpty
                ? '${getTranslated('direct_topup_account_verified', context) ?? 'Verified'}: ${result.username}'
                : getTranslated('direct_topup_account_verified', context) ?? 'Account verified';
            showCustomSnackBarWidget(successMessage, context, snackBarType: SnackBarType.success);
          }
          return true;
        }
      }

      return true;
    }

    ApiChecker.checkApi(apiResponse);
    return false;
  }


  Future<ApiResponseModel> restockRequest(CartModelBody cart, BuildContext context, List<ChoiceOptions> choices, List<int>? variationIndexes, {int buyNow = 0, int? shippingMethodExist, int? shippingMethodId, String? variationType}) async {
    _addToCartLoading = true;
    notifyListeners();
    ApiResponseModel apiResponse = await cartServiceInterface!.restockRequest(cart, choices, variationIndexes, buyNow, shippingMethodExist, shippingMethodId);

    _addToCartLoading = false;
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      Navigator.of(Get.context!).pop();
      _addToCartLoading = false;
      if(context.mounted) {
        Provider.of<ProductDetailsController>(context, listen: false).updateProductRestock(variantKey: variationType);
      }
      await FirebaseMessaging.instance.subscribeToTopic(apiResponse.response!.data['topic']);
      showCustomSnackBarWidget(apiResponse.response!.data['message'], Get.context!, snackBarType: apiResponse.response!.data['status'] == 0 ? SnackBarType.error : SnackBarType.success);
    } else {
      _addToCartLoading = false;
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
    return apiResponse;
  }


  Future<void> removeFromCartAPI(int? key, int index) async{
    cartList[index].decrement = true;
    notifyListeners();
    ApiResponseModel apiResponse = await cartServiceInterface!.delete(key!);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      cartList[index].decrement = false;
      getCartData(Get.context!);
    } else {
      cartList[index].decrement = false;
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
  }


  Future<void> addRemoveCartSelectedItem(List<int> ids, bool action) async{
    notifyListeners();
    Map<String, dynamic> data = {
      'ids' : ids,
      'action' : action ? 'checked' : 'unchecked'
    };
    ApiResponseModel apiResponse = await cartServiceInterface!.addRemoveCartSelectedItem(data);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      await Future.wait([
        Provider.of<ShippingController>(Get.context!, listen: false).getChosenShippingMethod(Get.context!),
        getCartData(Get.context!, reload: false),
      ]);
    } else {
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
  }


  void resetCartList({bool isUpdate = true}) {
    _cartList = [];
    if(isUpdate){
      notifyListeners();
    }
  }


  Future<void> mergeGuestCart() async{
    ApiResponseModel apiResponse = await cartServiceInterface!.mergeGuestCart();
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {

    } else {
      ApiChecker.checkApi(apiResponse);
    }
    notifyListeners();
  }



}
