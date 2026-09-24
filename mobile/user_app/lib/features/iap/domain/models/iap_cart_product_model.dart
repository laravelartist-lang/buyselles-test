class IapCartProductModel {
  final int cartId;
  final int productId;
  final int quantity;
  final String appleProductId;
  final String? name;

  IapCartProductModel({
    required this.cartId,
    required this.productId,
    required this.quantity,
    required this.appleProductId,
    this.name,
  });

  factory IapCartProductModel.fromJson(Map<String, dynamic> json) {
    return IapCartProductModel(
      cartId: json['cart_id'] ?? 0,
      productId: json['product_id'] ?? 0,
      quantity: json['quantity'] ?? 1,
      appleProductId: json['apple_product_id'] ?? '',
      name: json['name'],
    );
  }
}
