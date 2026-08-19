import 'package:dio/dio.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/dio/dio_client.dart';
import 'package:flutter_sixvalley_ecommerce/data/datasource/remote/exception/api_error_handler.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/iap/domain/models/iap_cart_product_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';

class IapRepository {
  IapRepository({required this.dioClient});

  final DioClient dioClient;

  Future<ApiResponseModel> getCartProducts() async {
    try {
      final response = await dioClient.get(AppConstants.iapCartProductsUri);
      return ApiResponseModel.withSuccess(response);
    } catch (error) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(error));
    }
  }

  Future<ApiResponseModel> verifyPurchase({
    required List<Map<String, dynamic>> transactions,
    String? couponCode,
    String? orderNote,
    String? addressId,
    String? billingAddressId,
  }) async {
    try {
      final response = await dioClient.post(
        AppConstants.iapVerifyPurchaseUri,
        data: {
          'transactions': transactions,
          'coupon_code': couponCode,
          'order_note': orderNote,
          'address_id': addressId,
          'billing_address_id': billingAddressId,
        },
      );

      return ApiResponseModel.withSuccess(response);
    } catch (error) {
      return ApiResponseModel.withError(ApiErrorHandler.getMessage(error));
    }
  }

  List<IapCartProductModel> parseCartProducts(Response? response) {
    final products = response?.data['products'] as List<dynamic>? ?? [];

    return products
        .map((item) => IapCartProductModel.fromJson(item as Map<String, dynamic>))
        .where((item) => item.appleProductId.isNotEmpty)
        .toList();
  }
}
