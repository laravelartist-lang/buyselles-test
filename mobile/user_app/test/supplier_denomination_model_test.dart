import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';

void main() {
  test('parses denomination fields from product detail json', () {
    final model = ProductDetailsModel.fromJson({
      'variant_product': 0,
      'attributes': [],
      'choice_options': [],
      'variation': [],
      'reviews': [],
      'requires_denomination_selection': true,
      'default_supplier_denomination_id': 5,
      'has_denominations': true,
      'is_customizable': true,
      'denominations': [
        {
          'id': 5,
          'name': '60 UC',
          'type': 'fixed',
          'face_value': 1,
          'face_value_currency': 'USD',
          'sell_price': 1.12,
        },
      ],
      'variable_denomination': null,
      'minimum_order_qty': 1,
      'request_status': 0,
    });

    expect(model.requiresDenominationSelection, isTrue);
    expect(model.defaultSupplierDenominationId, 5);
    expect(model.denominations?.length, 1);
    expect(model.denominations!.first.faceValue, 1.0);
  });
}
