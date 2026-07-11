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

  static bool isDirectTopUpProduct(ProductDetailsModel? product) {
    if (product == null) {
      return false;
    }

    if (isTruthy(product.directTopup?.enabled)) {
      return true;
    }

    if (product.isDirectTopup) {
      return true;
    }

    final DirectTopUpConfig? config = product.directTopup;
    if (product.productType == 'digital' && config != null) {
      if (isTruthy(config.enabled)) {
        return true;
      }

      if (config.accountLabel?.trim().isNotEmpty == true) {
        return true;
      }

      if (config.minQuantity != null || config.pricePerUnit != null) {
        return true;
      }
    }

    return false;
  }

  /// Whether the purchase flow must collect a direct top-up account before checkout.
  static bool shouldPromptDirectTopUpSheet(ProductDetailsModel? product) {
    if (product == null || product.productType != 'digital') {
      return false;
    }

    if (isDirectTopUpProduct(product)) {
      return true;
    }

    final bool hasDigitalFileVariants = product.digitalProductExtensions != null
        && product.digitalProductExtensions!.isNotEmpty;

    if (hasDigitalFileVariants) {
      return false;
    }

    return isTruthy(product.hasActiveSupplierMapping);
  }

  static DirectTopUpConfig? resolveDirectTopUpConfig(ProductDetailsModel? product) {
    if (product == null || !shouldPromptDirectTopUpSheet(product)) {
      return null;
    }

    final DirectTopUpConfig? existing = product.directTopup;
    if (existing != null) {
      return DirectTopUpConfig(
        enabled: true,
        accountLabel: existing.accountLabel?.trim().isNotEmpty == true
            ? existing.accountLabel
            : 'Player ID',
        minQuantity: existing.minQuantity ?? product.minimumOrderQty?.toDouble() ?? 1,
        maxQuantity: existing.maxQuantity ?? existing.minQuantity ?? 100,
        pricePerUnit: existing.pricePerUnit ?? product.unitPrice,
        currency: existing.currency,
        requiresAccountVerification: existing.requiresAccountVerification ?? false,
      );
    }

    return DirectTopUpConfig(
      enabled: true,
      accountLabel: 'Player ID',
      minQuantity: product.minimumOrderQty?.toDouble() ?? 1,
      maxQuantity: 100,
      pricePerUnit: product.unitPrice,
      currency: null,
      requiresAccountVerification: false,
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
