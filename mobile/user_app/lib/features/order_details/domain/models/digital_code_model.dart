/// Represents a single decrypted digital product code returned by the API.
/// Used in both the order details screen and any future receipt/print widget.
class DigitalCodeModel {
  final String productName;
  final String code;
  final String? pin;
  final String? serial;
  final String? expiry;

  const DigitalCodeModel({
    required this.productName,
    required this.code,
    this.pin,
    this.serial,
    this.expiry,
  });

  factory DigitalCodeModel.fromJson(Map<String, dynamic> json) {
    return DigitalCodeModel(
      productName: json['product_name'] as String? ?? 'Digital Product',
      code: json['code'] as String? ?? '',
      pin: json['pin'] as String?,
      serial: json['serial'] as String?,
      expiry: json['expiry'] as String?,
    );
  }
}
