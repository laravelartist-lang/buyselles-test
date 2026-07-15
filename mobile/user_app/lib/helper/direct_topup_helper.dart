import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/price_converter.dart';

class DirectTopUpListingInfo {
  final double? quantity;
  final double? lineTotal;
  final String? formattedLineTotal;
  final String? quantityLabel;

  const DirectTopUpListingInfo({
    this.quantity,
    this.lineTotal,
    this.formattedLineTotal,
    this.quantityLabel,
  });

  factory DirectTopUpListingInfo.fromJson(Map<String, dynamic> json) {
    return DirectTopUpListingInfo(
      quantity: _parseDouble(json['quantity']),
      lineTotal: _parseDouble(json['line_total']),
      formattedLineTotal: json['formatted_line_total']?.toString(),
      quantityLabel: json['quantity_label']?.toString(),
    );
  }
}

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
    if (product == null || product.productType != 'digital') {
      return false;
    }

    if (product.isDirectTopup) {
      return true;
    }

    return isTruthy(product.directTopup?.enabled);
  }

  static bool isDirectTopUpListingProduct(Product? product) {
    if (product == null || product.productType != 'digital') {
      return false;
    }

    return product.isDirectTopup == true;
  }

  static bool shouldPromptDirectTopUpSheet(ProductDetailsModel? product) {
    return isDirectTopUpProduct(product);
  }

  static DirectTopUpConfig? resolveDirectTopUpConfig(ProductDetailsModel? product) {
    if (!isDirectTopUpProduct(product)) {
      return null;
    }

    return product?.directTopup;
  }

  static String? listingCreditsSubtitle(Product? product) {
    if (!isDirectTopUpListingProduct(product)) {
      return null;
    }

    final DirectTopUpListingInfo? listing = product?.directTopupListing;
    if (listing?.quantity == null) {
      return null;
    }

    final String label = listing?.quantityLabel?.trim().isNotEmpty == true
        ? listing!.quantityLabel!
        : 'Credits';

    return '${listing!.quantity!.toStringAsFixed(0)} $label';
  }

  static String? resolveListingPriceText(BuildContext context, Product? product) {
    if (!isDirectTopUpListingProduct(product)) {
      return null;
    }

    if (product?.formattedDisplayPrice?.trim().isNotEmpty == true) {
      return product!.formattedDisplayPrice;
    }

    if (product?.directTopupListing?.formattedLineTotal?.trim().isNotEmpty == true) {
      return product!.directTopupListing!.formattedLineTotal;
    }

    final double? displayPrice = product?.displayPrice ?? product?.directTopupListing?.lineTotal;
    if (displayPrice != null) {
      return PriceConverter.convertPrice(context, displayPrice);
    }

    return null;
  }

  static double resolveListingPriceAmount(Product? product) {
    if (!isDirectTopUpListingProduct(product)) {
      return product?.unitPrice ?? 0;
    }

    return product?.displayPrice
        ?? product?.directTopupListing?.lineTotal
        ?? product?.unitPrice
        ?? 0;
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

double? _parseDouble(dynamic value) {
  if (value == null) {
    return null;
  }

  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString());
}
