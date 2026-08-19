import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_sixvalley_ecommerce/helper/apple_iap_payment_helper.dart';

void main() {
  test('apple iap payment helper exposes platform guards', () {
    expect(isWalletAddFundAllowedOnPlatform(), isA<bool>());
  });
}
