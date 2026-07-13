import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';

class DirectTopUpHelper {
  static bool isTruthy(dynamic value) {
    if (value == null) {
      return false;
    }

    if (value is bool) {
      return value;
    }

    if (value is num) {
      return value == 1;
    }

    final normalized = value.toString().trim().toLowerCase();

    return normalized == '1' || normalized == 'true';
  }

  /// True only when the API marks this product as a direct top-up product.
  static bool isDirectTopUpProduct(ProductDetailsModel? product) {
    if (product == null || product.productType != 'digital') {
      return false;
    }

    if (product.isDirectTopup) {
      return true;
    }

    return isTruthy(product.directTopup?.enabled);
  }

  /// Whether the purchase flow must collect a direct top-up account before checkout.
  static bool shouldPromptDirectTopUpSheet(ProductDetailsModel? product) {
    return isDirectTopUpProduct(product);
  }

  static DirectTopUpConfig? resolveDirectTopUpConfig(ProductDetailsModel? product) {
    if (!isDirectTopUpProduct(product)) {
      return null;
    }

    final DirectTopUpConfig? existing = product?.directTopup;
    if (existing == null) {
      return null;
    }

    return DirectTopUpConfig(
      enabled: true,
      accountLabel: existing.accountLabel?.trim().isNotEmpty == true
          ? existing.accountLabel
          : 'Player ID',
      minQuantity: product?.minimumOrderQty?.toDouble() ?? 1,
      maxQuantity: product?.minimumOrderQty?.toDouble() ?? 1,
      pricePerUnit: product?.unitPrice,
      requiresAccountVerification: existing.requiresAccountVerification ?? false,
    );
  }

  static bool isDirectTopUpCartItem(CartModel cart) {
    if (cart.isDirectTopup == true) {
      return true;
    }

    return cart.directTopupQuantity != null && cart.directTopupQuantity! > 0;
  }

  static bool cartListHasDirectTopUp(List<CartModel> cartList) {
    return cartList.any(isDirectTopUpCartItem);
  }

  static bool cartListIsDigitalOnly(List<CartModel> cartList) {
    if (cartList.isEmpty) {
      return false;
    }

    return cartList.every((cart) => cart.productType != 'physical');
  }
}
