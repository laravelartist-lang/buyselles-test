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

    public function test_from_throwable_prefers_full_response_body_over_truncated_exception_message(): void
    {
        $response = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'message' => 'Account does not have enough funds available to buy the card(s) requested. Balance: 0.3624.',
                ], JSON_THROW_ON_ERROR)
            )
        );

        $exception = new \Illuminate\Http\Client\RequestException($response);

        $formatter = new OrderFailureNoteFormatter;

        $plain = $formatter->fromThrowable($exception);

        $this->assertStringContainsString('enough funds', $plain);
        $this->assertStringNotContainsString('{', $plain);
    }
}
