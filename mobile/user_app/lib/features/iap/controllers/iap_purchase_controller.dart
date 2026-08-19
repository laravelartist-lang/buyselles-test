import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_sixvalley_ecommerce/features/iap/domain/repositories/iap_repository.dart';
import 'package:in_app_purchase/in_app_purchase.dart';

class IapPurchaseController with ChangeNotifier {
  IapPurchaseController({required this.iapRepository});

  final IapRepository iapRepository;
  final InAppPurchase _inAppPurchase = InAppPurchase.instance;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  StreamSubscription<List<PurchaseDetails>>? _purchaseSubscription;

  Future<bool> purchaseDigitalCart({
    required void Function(bool success, String message) callback,
    String? couponCode,
    String? orderNote,
    String? addressId,
    String? billingAddressId,
  }) async {
    if (!Platform.isIOS) {
      callback(false, 'Apple In-App Purchase is only available on iOS.');
      return false;
    }

    if (_isLoading) {
      return false;
    }

    _isLoading = true;
    notifyListeners();

    try {
      final available = await _inAppPurchase.isAvailable();
      if (!available) {
        callback(false, 'App Store purchases are not available on this device.');
        return false;
      }

      final cartResponse = await iapRepository.getCartProducts();
      if (cartResponse.response?.statusCode != 200) {
        callback(false, cartResponse.error ?? 'Unable to load App Store products.');
        return false;
      }

      final cartProducts = iapRepository.parseCartProducts(cartResponse.response);
      if (cartProducts.isEmpty) {
        callback(false, 'No App Store products are configured for this cart.');
        return false;
      }

      final productIds = cartProducts.map((item) => item.appleProductId).toSet();
      final productDetailsResponse = await _inAppPurchase.queryProductDetails(productIds);
      if (productDetailsResponse.error != null) {
        callback(false, productDetailsResponse.error!.message);
        return false;
      }

      if (productDetailsResponse.productDetails.isEmpty) {
        callback(false, 'App Store products were not found. Check App Store Connect product IDs.');
        return false;
      }

      final purchases = <PurchaseDetails>[];
      final completer = Completer<List<PurchaseDetails>>();
      await _purchaseSubscription?.cancel();
      _purchaseSubscription = _inAppPurchase.purchaseStream.listen((purchaseDetailsList) async {
        for (final purchaseDetails in purchaseDetailsList) {
          if (purchaseDetails.status == PurchaseStatus.error) {
            if (!completer.isCompleted) {
              completer.completeError(Exception(purchaseDetails.error?.message ?? 'Purchase failed'));
            }
            continue;
          }

          if (purchaseDetails.status == PurchaseStatus.purchased ||
              purchaseDetails.status == PurchaseStatus.restored) {
            purchases.add(purchaseDetails);
            if (purchases.length >= cartProducts.length && !completer.isCompleted) {
              completer.complete(purchases);
            }
          }

          if (purchaseDetails.pendingCompletePurchase) {
            await _inAppPurchase.completePurchase(purchaseDetails);
          }
        }
      });

      for (final cartProduct in cartProducts) {
        final productDetails = productDetailsResponse.productDetails
            .firstWhere((item) => item.id == cartProduct.appleProductId);
        final purchaseParam = PurchaseParam(productDetails: productDetails);
        final started = await _inAppPurchase.buyConsumable(purchaseParam: purchaseParam);
        if (!started) {
          throw Exception('Unable to start App Store purchase.');
        }
      }

      final completedPurchases = await completer.future.timeout(const Duration(minutes: 2));
      final transactions = <Map<String, dynamic>>[];

      for (final purchase in completedPurchases) {
        final cartProduct = cartProducts.firstWhere(
          (item) => item.appleProductId == purchase.productID,
          orElse: () => cartProducts.first,
        );

        transactions.add({
          'transaction_id': purchase.purchaseID ?? purchase.transactionDate ?? '',
          'apple_product_id': purchase.productID,
          'product_id': cartProduct.productId,
          'verification_data': purchase.verificationData.serverVerificationData,
        });
      }

      final verifyResponse = await iapRepository.verifyPurchase(
        transactions: transactions,
        couponCode: couponCode,
        orderNote: orderNote,
        addressId: addressId,
        billingAddressId: billingAddressId,
      );

      if (verifyResponse.response?.statusCode == 200) {
        callback(true, verifyResponse.response?.data['order_ids']?.toString() ?? '');
        return true;
      }

      callback(false, verifyResponse.error ?? 'Unable to verify App Store purchase.');
      return false;
    } catch (error) {
      callback(false, error.toString());
      return false;
    } finally {
      await _purchaseSubscription?.cancel();
      _purchaseSubscription = null;
      _isLoading = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _purchaseSubscription?.cancel();
    super.dispose();
  }
}
