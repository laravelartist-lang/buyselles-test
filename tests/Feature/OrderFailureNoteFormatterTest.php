<?php

namespace Tests\Feature;

use App\Services\Order\OrderFailureNoteFormatter;
use Tests\TestCase;

class OrderFailureNoteFormatterTest extends TestCase
{
    public function test_extracts_message_from_truncated_bamboo_http_error_like_order_100013(): void
    {
        $error = "Product 'PUBG 60 UC | Global': Supplier 'Bamboo Acoount 2' error: HTTP request returned status code 400:\n"
            .'{"message":"Account doesn\'t have enough funds available to buy the card(s) requested. Balance: 0.3624. Reserved balanc (truncated...)';

        $formatter = new OrderFailureNoteFormatter;

        $plain = $formatter->toPlainText($error);

        $this->assertStringContainsString('enough funds', $plain);
        $this->assertStringNotContainsString('"message"', $plain);
        $this->assertStringNotContainsString('{', $plain);
    }

    public function test_supplier_failure_note_prefixes_plain_message(): void
    {
        $formatter = new OrderFailureNoteFormatter;

        $note = $formatter->supplierFailureNote('{"message":"Out of stock"}');

        $this->assertSame('Supplier fulfillment failed: Out of stock', $note);
    }
}
