import 'package:flutter_sixvalley_ecommerce/data/model/image_full_url.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/shop/domain/models/seller_model.dart';
class CartModel {
  int? id;
  int? productId;
  String? image;
  String? name;
  String? thumbnail;
  ImageFullUrl? thumbnailFullUrl;
  int? sellerId;
  String? sellerIs;
  String? seller;
  double? price;
  double? discountedPrice;
  int? quantity;
  int? maxQuantity;
  String? variant;
  String? color;
  Variation? variation;
  double? discount;
  String? discountType;
  double? tax;
  String? taxModel;
  String? taxType;
  int? shippingMethodId;
  String? cartGroupId;
  String? shopInfo;
  List<ChoiceOptions>? choiceOptions;
  List<int>? variationIndexes;
  double?  shippingCost;
  String? shippingType;
  int? minimumOrderQuantity;
  ProductInfo? productInfo;
  String? productType;
  String? slug;
  double? minimumOrderAmountInfo;
  FreeDeliveryOrderAmount? freeDeliveryOrderAmount;
  bool? increment;
  bool? decrement;
  Shop? shop;
  int? isProductAvailable;
  int? hasActiveSupplierMapping;
  bool? isChecked;
  bool? isGroupChecked;
  bool? isGroupItemChecked;
  double? appliedTax;
  String? appliedTaxType;
  double? shippingCostTax;

  static int? _parseInt(dynamic value, {int? defaultValue}) {
    if (value == null) {
      return defaultValue;
    }
    if (value is int) {
      return value;
    }
    if (value is bool) {
      return value ? 1 : 0;
    }
    return int.tryParse(value.toString()) ?? defaultValue;
  }

  static double? _parseDouble(dynamic value, {double? defaultValue}) {
    if (value == null) {
      return defaultValue;
    }
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse(value.toString()) ?? defaultValue;
  }


  CartModel(
      this.id,
      this.productId,
      this.thumbnail,
      this.thumbnailFullUrl,
      this.name,
      this.seller,
      this.price,
      this.discountedPrice,
      this.quantity,
      this.maxQuantity,
      this.variant,
      this.color,
      this.variation,
      this.discount,
      this.discountType,
      this.tax,
      this.taxModel,
      this.taxType,
      this.shippingMethodId,
      this.cartGroupId,
      this.sellerId,
      this.sellerIs,
      this.image,
      this.shopInfo,
      this.choiceOptions,
      this.variationIndexes,
      this.shippingCost,
      this.minimumOrderQuantity,
      this.productType,
      this.slug,
      this.minimumOrderAmountInfo,
      this.freeDeliveryOrderAmount,
      this.increment,
      this.decrement,
      this.shop,
      this.isProductAvailable,
      this.hasActiveSupplierMapping,
      this.isChecked,
      this.isGroupChecked,
      this.isGroupItemChecked,
      this.appliedTax,
      this.appliedTaxType,
      this.shippingCostTax
      );


  CartModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    productId = _parseInt(json['product_id']);
    name = json['name'];
    seller = json['seller'];
    thumbnail = json['thumbnail'];
    sellerId = _parseInt(json['seller_id']);
    sellerIs = json['seller_is'];
    image = json['image'];
    price = _parseDouble(json['price'], defaultValue: 0.0);
    discountedPrice = _parseDouble(json['discounted_price']);
    quantity = _parseInt(json['quantity'], defaultValue: 1);
    maxQuantity = _parseInt(json['max_quantity']);
    variant = json['variant'];
    color = json['color'];
    variation = json['variation'] != null ? Variation.fromJson(json['variation']) : null;
    discount = _parseDouble(json['discount'], defaultValue: 0.0);
    discountType = json['discount_type'];
    tax = _parseDouble(json['tax'], defaultValue: 0.0);
    taxModel = json['tax_model'];
    taxType = json['tax_type'];
    shippingMethodId = _parseInt(json['shipping_method_id']);
    cartGroupId = json['cart_group_id'];
    shopInfo = json['shop_info'];
    if (json['choice_options'] != null) {
      choiceOptions = [];
      json['choice_options'].forEach((v) {choiceOptions!.add(ChoiceOptions.fromJson(v));
      });
    }
    if (json['variation_indexes'] is List) {
      variationIndexes = (json['variation_indexes'] as List)
          .map((e) => _parseInt(e))
          .whereType<int>()
          .toList();
    } else {
      variationIndexes = [];
    }
    if(json['shipping_cost'] != null){
      shippingCost = _parseDouble(json['shipping_cost']);
    }
    if(json['shipping_type'] != null){
      shippingType = json['shipping_type'];
    }
    productInfo = json['product'] != null ? ProductInfo.fromJson(json['product']) : null;
    productType = json['product_type'];
    slug = json['slug'];
    if(json['minimum_order_amount_info'] != null){
      minimumOrderAmountInfo = _parseDouble(json['minimum_order_amount_info']);
    }
    increment = false;
    decrement = false;
    freeDeliveryOrderAmount = json['free_delivery_order_amount'] != null ? FreeDeliveryOrderAmount.fromJson(json['free_delivery_order_amount']) : null;
    shop = json['shop'] != null ? Shop.fromJson(json['shop'], isAdminProduct: json['seller_is'] == 'admin') : null;
    if(json["is_product_available"] != null){
      isProductAvailable = _parseInt(json['is_product_available'], defaultValue: 1);
    }else{
      isProductAvailable = 1;
    }
    hasActiveSupplierMapping = _parseInt(json['has_active_supplier_mapping']);

    if(json['is_checked'] != null) {
      isChecked = _parseInt(json['is_checked'], defaultValue: 0) == 1;
    } else {
      isChecked = false;
    }
    thumbnailFullUrl = json['thumbnail_full_url'] != null
        ? ImageFullUrl.fromJson(json['thumbnail_full_url'])
        : null;
    isGroupChecked = false;
    isGroupItemChecked = false;
    appliedTax = _parseDouble(json['applied_tax']);
    appliedTaxType = json['applied_tax_type'];
    shippingCostTax = _parseDouble(json['shipping_cost_tax']);
  }

  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{};
    data['id'] = id;
    data['product_id'] = productId;
    data['name'] = name;
    data['seller'] = seller;
    data['thumbnail'] = thumbnail;
    data['seller_id'] = sellerId;
    data['seller_is'] = sellerIs;
    data['image'] = image;
    data['price'] = price;
    data['discounted_price'] = discountedPrice;
    data['quantity'] = quantity;
    data['max_quantity'] = maxQuantity;
    data['variant'] = variant;
    data['color'] = color;
    if (variation != null) data['variation'] = variation!.toJson();
    data['discount'] = discount;
    data['discount_type'] = discountType;
    data['tax'] = tax;
    data['tax_model'] = taxModel;
    data['tax_type'] = taxType;
    data['shipping_method_id'] = shippingMethodId;
    data['cart_group_id'] = cartGroupId;
    data['shop_info'] = shopInfo;
    if (choiceOptions != null) {
      data['choice_options'] = choiceOptions!.map((v) => v.toJson()).toList();
    }
    data['variation_indexes'] = variationIndexes;
    data['shipping_cost'] = shippingCost;
    data['shipping_type'] = shippingType;
    data['minimum_order_quantity'] = minimumOrderQuantity;
    if (productInfo != null) data['product'] = productInfo!.toJson();
    data['product_type'] = productType;
    data['slug'] = slug;
    data['minimum_order_amount_info'] = minimumOrderAmountInfo;
    if (freeDeliveryOrderAmount != null) {
      data['free_delivery_order_amount'] = freeDeliveryOrderAmount!.toJson();
    }
    data['increment'] = increment;
    data['decrement'] = decrement;
    if (shop != null) data['shop'] = shop!.toJson();
    data['is_product_available'] = isProductAvailable;
    data['is_checked'] = isChecked;
    if (thumbnailFullUrl != null) data['thumbnail_full_url'] = thumbnailFullUrl!.toJson();
    data['is_group_checked'] = isGroupChecked;
    data['is_group_item_checked'] = isGroupItemChecked;
    data['applied_tax'] = appliedTax;
    data['applied_tax_type'] = appliedTaxType;
    data['shipping_cost_tax'] = shippingCostTax;
    return data;
  }
}

class ProductInfo {
  int? minimumOrderQty;
  int? totalCurrentStock;
  ImageFullUrl? thumbnailFullUrl;

  ProductInfo({ this.minimumOrderQty, this.totalCurrentStock});

  ProductInfo.fromJson(Map<String, dynamic> json) {
    if(json['minimum_order_qty'] != null) {
      try{
        minimumOrderQty = json['minimum_order_qty'];
      }catch(e){
        minimumOrderQty = int.parse(json['minimum_order_qty'].toString());
      }
    }
    totalCurrentStock = CartModel._parseInt(json['total_current_stock']);
    thumbnailFullUrl = json['thumbnail_full_url'] != null
        ? ImageFullUrl.fromJson(json['thumbnail_full_url'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{};
    data['minimum_order_qty'] = minimumOrderQty;
    data['total_current_stock'] = totalCurrentStock;
    if (thumbnailFullUrl != null) data['thumbnail_full_url'] = thumbnailFullUrl!.toJson();
    return data;
  }
}

class FreeDeliveryOrderAmount {
  int? status;
  double? amount;
  int? percentage;
  double? shippingCostSaved;
  double? amountNeed;


  FreeDeliveryOrderAmount(
      {this.status,
        this.amount,
        this.percentage,
        this.shippingCostSaved,
        this.amountNeed,
        });

  FreeDeliveryOrderAmount.fromJson(Map<String, dynamic> json) {
    status = CartModel._parseInt(json['status']);
    if(json['amount'] != null){
      amount = CartModel._parseDouble(json['amount']);
    }

    if(json['percentage'] != null){
      percentage = CartModel._parseInt(json['percentage']);
    }

    if(json['shipping_cost_saved'] != null){
      shippingCostSaved = CartModel._parseDouble(json['shipping_cost_saved']);
    }

    if(json['amount_need'] != null){
      amountNeed = CartModel._parseDouble(json['amount_need']);
    }
  }

  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{};
    data['status'] = status;
    data['amount'] = amount;
    data['percentage'] = percentage;
    data['shipping_cost_saved'] = shippingCostSaved;
    data['amount_need'] = amountNeed;
    return data;
  }
}



class CartModelBody{
  int? productId;
  String? variant;
  String? color;
  Variation? variation;
  int? quantity;
  String? variantKey;
  double? digitalVariantPrice;
  String? productType;
  String? directTopupAccountId;
  double? directTopupQuantity;

  CartModelBody(
    {this.productId,
      this.variant,
      this.color,
      this.variation,
      this.quantity,
      this.variantKey,
      this.digitalVariantPrice,
      this.productType,
      this.directTopupAccountId,
      this.directTopupQuantity});


  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{};
    data['product_id'] = productId;
    data['variant'] = variant;
    data['color'] = color;
    if (variation != null) data['variation'] = variation!.toJson();
    data['quantity'] = quantity;
    data['variant_key'] = variantKey;
    data['digital_variant_price'] = digitalVariantPrice;
    data['product_type'] = productType;
    if (directTopupAccountId != null && directTopupAccountId!.isNotEmpty) {
      data['direct_topup_account_id'] = directTopupAccountId;
    }
    if (directTopupQuantity != null) {
      data['direct_topup_quantity'] = directTopupQuantity;
    }
    return data;
  }

}

class ReferralAmount {
  double? amount;

  ReferralAmount({this.amount});

  ReferralAmount.fromJson(Map<String, dynamic> json) {
    amount = double.tryParse(json['amount'].toString());
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['amount'] = amount;
    return data;
  }
}
