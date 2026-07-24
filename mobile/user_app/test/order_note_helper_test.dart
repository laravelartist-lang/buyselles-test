import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_sixvalley_ecommerce/helper/order_note_helper.dart';

void main() {
  group('OrderNoteHelper.parse', () {
    test('extracts message from truncated Bamboo HTTP 400 note (order 100013)', () {
      const raw =
          "Product 'PUBG 60 UC | Global': Supplier 'Bamboo Acoount 2' error: HTTP request returned status code 400:\n"
          '{"message":"Account doesn\u2019t have enough funds available to buy the card(s) requested. Balance: 0.3624. Reserved balanc (truncated...)';
      final display = OrderNoteHelper.parse(
        'Supplier fulfillment failed: $raw',
        isFailedOrder: true,
      );
      expect(display.failureMessage, contains('enough funds'));
      expect(display.failureMessage, isNot(contains('"message"')));
      expect(display.failureMessage, isNot(contains('{')));
    });

    test('parses Dart-style map literal with spaces around colon', () {
      const raw = '{message : Account does not have enough balance}';
      expect(OrderNoteHelper.toPlainText(raw),
          'Account does not have enough balance');
      final display = OrderNoteHelper.parse(raw, isFailedOrder: true);
      expect(display.failureMessage, 'Account does not have enough balance');
    });

    test('parses map object from API json decode', () {
      final note = OrderNoteHelper.orderNoteFromJson({
        'message': 'Account does not have enough balance',
      });
      expect(note, 'Account does not have enough balance');
    });

    test('extracts message from JSON failure payload', () {
      const raw = r'{"message":"Insufficient supplier balance","status":"error"}';
      final display = OrderNoteHelper.parse(raw, isFailedOrder: true);
      expect(display.failureMessage, 'Insufficient supplier balance');
      expect(display.customerNote, isNull);
    });

    test('strips supplier fulfillment prefix and parses embedded JSON', () {
      const raw =
          'Supplier fulfillment failed: {"errors":{"product":["Out of stock"]}}';
      final display = OrderNoteHelper.parse(raw, isFailedOrder: true);
      expect(display.failureMessage, 'Out of stock');
    });

    test('keeps plain customer note for non-failed orders', () {
      const raw = 'Please deliver after 5 PM';
      final display = OrderNoteHelper.parse(raw, isFailedOrder: false);
      expect(display.customerNote, raw);
      expect(display.failureMessage, isNull);
    });

    test('orderNoteFromJson handles map from API', () {
      final note = OrderNoteHelper.orderNoteFromJson({
        'message': 'Payment declined',
      });
      expect(note, 'Payment declined');
    });
  });
}
