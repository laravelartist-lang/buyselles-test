<?php

namespace Tests\Unit;

use App\DTOs\Supplier\SupplierOrderResult;
use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\GolfApiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GolfApiDriverTest extends TestCase
{
    private GolfApiDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->driver = app(GolfApiDriver::class);

        // Mock SupplierApi for configure()
        $supplierMock = $this->createMock(SupplierApi::class);
        $supplierMock->method('getDecryptedCredentials')
            ->willReturn(['api_token' => 'test-token-123']);
        $supplierMock->method('__get')
            ->willReturnCallback(function (string $key): mixed {
                return match ($key) {
                    'id' => 1,
                    'base_url' => 'https://api.golf-test.com/api',
                    'settings' => [],
                    default => null,
                };
            });

        $configureMethod = new \ReflectionMethod($this->driver, 'configure');
        $configureMethod->invoke($this->driver, $supplierMock);
    }

    // ─── fetchProductCustomFields ───────────────────────────────────────

    public function test_fetch_product_custom_fields_returns_parsed_fields(): void
    {
        Http::fake([
            'api.golf-test.com/api/products/195' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'title' => '20000الماسة',
                        'product_type' => 'charge',
                        'custom_fields' => [
                            [
                                'id' => 67,
                                'name' => 'Id',
                                'desc' => 'Id',
                                'sort' => 1,
                            ],
                        ],
                    ],
                ],
                'message' => 'Product retrieved successfully',
            ]),
        ]);

        $fields = $this->invokeFetchCustomFields('195');

        $this->assertCount(1, $fields);
        $this->assertSame(67, $fields[0]['id']);
        $this->assertSame('Id', $fields[0]['name']);
        $this->assertSame('Id', $fields[0]['desc']);
        $this->assertSame(1, $fields[0]['sort']);
    }

    public function test_fetch_product_custom_fields_returns_empty_array_on_api_failure(): void
    {
        Http::fake([
            'api.golf-test.com/api/products/999' => Http::response([
                'status' => 'error',
                'message' => 'Product not found',
                'result' => null,
            ], 404),
        ]);

        $fields = $this->invokeFetchCustomFields('999');

        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    public function test_fetch_product_custom_fields_returns_empty_when_product_has_no_custom_fields(): void
    {
        Http::fake([
            'api.golf-test.com/api/products/190' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 190,
                        'title' => '3000 جوهرة',
                        'product_type' => 'charge',
                        'custom_fields' => null,
                    ],
                ],
                'message' => 'Product retrieved successfully',
            ]),
        ]);

        $fields = $this->invokeFetchCustomFields('190');

        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    public function test_fetch_product_custom_fields_caches_results_per_request(): void
    {
        $counter = 0;
        Http::fake(function (Request $request) use (&$counter) {
            $counter++;

            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'custom_fields' => [
                            ['id' => 67, 'name' => 'Id', 'desc' => 'Id', 'sort' => 1],
                        ],
                    ],
                ],
                'message' => 'Product retrieved successfully',
            ]);
        });

        // First call — makes HTTP request
        $fields1 = $this->invokeFetchCustomFields('195');
        $this->assertCount(1, $fields1);

        // Second call — cached, no HTTP request
        $fields2 = $this->invokeFetchCustomFields('195');
        $this->assertCount(1, $fields2);

        // Verify returned values are identical
        $this->assertSame($fields1[0]['id'], $fields2[0]['id']);
        $this->assertSame($fields1[0]['name'], $fields2[0]['name']);

        // Only one HTTP request should have been made
        $this->assertSame(1, $counter);
    }

    // ─── placeOrder ────────────────────────────────────────────────────────

    public function test_place_order_includes_custom_fields_with_empty_values(): void
    {
        $capturedRequestBody = null;

        Http::fake(function (Request $request) use (&$capturedRequestBody) {
            // Capture the POST /order request body
            if ($request->method() === 'POST' && str_contains($request->url(), '/order')) {
                $capturedRequestBody = $request->data();
            }

            // Product fetch
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/1')) {
                return Http::response([
                    'status' => 'success',
                    'result' => [
                        'data' => [
                            'id' => 1,
                            'product_type' => 'code',
                            'custom_fields' => [
                                ['id' => 3, 'name' => 'Player ID', 'desc' => 'Jawaker Player ID', 'sort' => 1],
                            ],
                        ],
                    ],
                    'message' => 'Product retrieved successfully',
                ]);
            }

            // Order placement with codes response
            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 600,
                        'ordernumber' => 87654321,
                        'status' => 'completed',
                        'order' => ['product_id' => 1, 'quantity' => 5],
                        'cards' => [
                            ['serial' => 'SER001', 'card' => 'CODE-ABC-123'],
                            ['serial' => 'SER002', 'card' => 'CODE-DEF-456'],
                        ],
                    ],
                ],
                'message' => 'Your order has been added successfully',
            ]);
        });

        $result = $this->driver->placeOrder(
            supplierProductId: '1',
            quantity: 5,
        );

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertSame('600', $result->supplierOrderId);
        $this->assertSame('fulfilled', $result->status);
        $this->assertCount(2, $result->codes);

        // Verify the POST body contained custom_fields with empty values
        $this->assertNotNull($capturedRequestBody);

        $body = $capturedRequestBody;
        $this->assertArrayHasKey('product_id', $body);
        $this->assertSame(1, $body['product_id']);
        $this->assertArrayHasKey('quantity', $body);
        $this->assertSame(5, $body['quantity']);
        $this->assertArrayHasKey('custom_fields', $body);
        $this->assertCount(1, $body['custom_fields']);
        $this->assertSame(3, $body['custom_fields'][0]['id']);
        $this->assertSame('', $body['custom_fields'][0]['value']);
    }

    public function test_place_order_works_without_custom_fields(): void
    {
        $capturedRequestBody = null;

        Http::fake(function (Request $request) use (&$capturedRequestBody) {
            // Capture the POST /order request body
            if ($request->method() === 'POST' && str_contains($request->url(), '/order')) {
                $capturedRequestBody = $request->data();
            }

            // Product fetch — no custom_fields
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/190')) {
                return Http::response([
                    'status' => 'success',
                    'result' => [
                        'data' => [
                            'id' => 190,
                            'product_type' => 'code',
                            'custom_fields' => null,
                        ],
                    ],
                    'message' => 'Product retrieved successfully',
                ]);
            }

            // Order placement
            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 601,
                        'status' => 'completed',
                        'cards' => [['serial' => 'SER003', 'card' => 'CODE-GHI-789']],
                    ],
                ],
                'message' => 'Your order has been added successfully',
            ]);
        });

        $result = $this->driver->placeOrder(
            supplierProductId: '190',
            quantity: 1,
        );

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertSame('fulfilled', $result->status);

        // Should not have custom_fields in the body
        $this->assertNotNull($capturedRequestBody);
        $body = $capturedRequestBody;
        $this->assertArrayHasKey('product_id', $body);
        $this->assertSame(190, $body['product_id']);
        $this->assertArrayHasKey('quantity', $body);
        $this->assertArrayNotHasKey('custom_fields', $body);
    }

    public function test_place_order_throws_on_api_error(): void
    {
        Http::fake([
            'api.golf-test.com/api/order' => Http::response([
                'status' => 'error',
                'message' => 'This product requires custom fields',
            ], 400),
            '*' => Http::response([
                'status' => 'success',
                'result' => ['data' => ['id' => 999, 'custom_fields' => []]],
                'message' => 'Product retrieved successfully',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This product requires custom fields');

        $this->driver->placeOrder(
            supplierProductId: '999',
            quantity: 1,
        );
    }

    // ─── placeTopUpOrder ─────────────────────────────────────────────────

    public function test_place_top_up_order_includes_custom_fields_with_account_id(): void
    {
        $capturedRequestBody = null;

        Http::fake(function (Request $request) use (&$capturedRequestBody) {
            // Capture the POST /order request body
            if ($request->method() === 'POST' && str_contains($request->url(), '/order')) {
                $capturedRequestBody = $request->data();
            }

            // Product fetch
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/195')) {
                return Http::response([
                    'status' => 'success',
                    'result' => [
                        'data' => [
                            'id' => 195,
                            'custom_fields' => [
                                ['id' => 67, 'name' => 'Id', 'desc' => 'Id', 'sort' => 1],
                            ],
                        ],
                    ],
                    'message' => 'Product retrieved successfully',
                ]);
            }

            // Order placement
            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 500,
                        'ordernumber' => 12345678,
                        'status' => 'completed',
                        'order' => ['product_id' => 195, 'quantity' => 1],
                    ],
                ],
                'message' => 'Your order has been added successfully',
            ]);
        });

        $result = $this->driver->placeTopUpOrder(
            supplierProductId: '195',
            quantity: 1,
            accountId: 'player-123',
        );

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertSame('500', $result->supplierOrderId);
        $this->assertSame('fulfilled', $result->status);

        // Verify the POST body contained custom_fields
        $this->assertNotNull($capturedRequestBody);

        $body = $capturedRequestBody;
        $this->assertArrayHasKey('product_id', $body);
        $this->assertSame(195, $body['product_id']);
        $this->assertArrayHasKey('quantity', $body);
        $this->assertSame(1, $body['quantity']);
        $this->assertArrayHasKey('custom_fields', $body);
        $this->assertCount(1, $body['custom_fields']);
        $this->assertSame(67, $body['custom_fields'][0]['id']);
        $this->assertSame('player-123', $body['custom_fields'][0]['value']);
    }

    public function test_place_top_up_order_works_without_custom_fields(): void
    {
        $capturedRequestBody = null;

        Http::fake(function (Request $request) use (&$capturedRequestBody) {
            // Capture the POST /order request body
            if ($request->method() === 'POST' && str_contains($request->url(), '/order')) {
                $capturedRequestBody = $request->data();
            }

            // Product fetch — no custom_fields
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/190')) {
                return Http::response([
                    'status' => 'success',
                    'result' => [
                        'data' => [
                            'id' => 190,
                            'title' => '3000 جوهرة',
                            'product_type' => 'charge',
                            'custom_fields' => null,
                        ],
                    ],
                    'message' => 'Product retrieved successfully',
                ]);
            }

            // Order placement
            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 501,
                        'status' => 'completed',
                    ],
                ],
                'message' => 'Your order has been added successfully',
            ]);
        });

        $result = $this->driver->placeTopUpOrder(
            supplierProductId: '190',
            quantity: 1,
            accountId: 'any-id',
        );

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertSame('fulfilled', $result->status);

        // Should not have custom_fields in the body
        $this->assertNotNull($capturedRequestBody);
        $body = $capturedRequestBody;
        $this->assertArrayHasKey('product_id', $body);
        $this->assertSame(190, $body['product_id']);
        $this->assertArrayHasKey('quantity', $body);
        $this->assertArrayNotHasKey('custom_fields', $body);
    }

    public function test_place_top_up_order_includes_all_custom_fields_for_multi_field_product(): void
    {
        $capturedRequestBody = null;

        Http::fake(function (Request $request) use (&$capturedRequestBody) {
            // Capture the POST /order request body
            if ($request->method() === 'POST' && str_contains($request->url(), '/order')) {
                $capturedRequestBody = $request->data();
            }

            // Product fetch — 2 custom_fields
            if ($request->method() === 'GET' && str_contains($request->url(), '/products/192')) {
                return Http::response([
                    'status' => 'success',
                    'result' => [
                        'data' => [
                            'id' => 192,
                            'custom_fields' => [
                                ['id' => 63, 'name' => 'Id', 'desc' => 'Id', 'sort' => 1],
                                ['id' => 64, 'name' => 'Id', 'desc' => 'Id', 'sort' => 2],
                            ],
                        ],
                    ],
                    'message' => 'Product retrieved successfully',
                ]);
            }

            // Order placement
            return Http::response([
                'status' => 'success',
                'result' => [
                    'data' => ['id' => 502, 'status' => 'completed'],
                ],
                'message' => 'Your order has been added successfully',
            ]);
        });

        $result = $this->driver->placeTopUpOrder(
            supplierProductId: '192',
            quantity: 1,
            accountId: 'multi-field-player',
        );

        $this->assertInstanceOf(SupplierOrderResult::class, $result);
        $this->assertSame('fulfilled', $result->status);

        $body = $capturedRequestBody;
        $this->assertArrayHasKey('custom_fields', $body);
        $this->assertCount(2, $body['custom_fields']);
        $this->assertSame(63, $body['custom_fields'][0]['id']);
        $this->assertSame('multi-field-player', $body['custom_fields'][0]['value']);
        $this->assertSame(64, $body['custom_fields'][1]['id']);
        $this->assertSame('multi-field-player', $body['custom_fields'][1]['value']);
    }

    public function test_place_top_up_order_throws_on_api_error(): void
    {
        Http::fake([
            'api.golf-test.com/api/order' => Http::response([
                'status' => 'error',
                'message' => 'Your balance is not enough',
            ], 400),
            '*' => Http::response([
                'status' => 'success',
                'result' => ['data' => ['id' => 195, 'custom_fields' => []]],
                'message' => 'Product retrieved successfully',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Your balance is not enough');

        $this->driver->placeTopUpOrder(
            supplierProductId: '195',
            quantity: 1,
            accountId: 'player-123',
        );
    }

    // ─── validatePlayerId ────────────────────────────────────────────────

    public function test_validate_player_id_returns_valid_for_valid_player(): void
    {
        Http::fake([
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'userId' => '12345',
                        'username' => 'GamerPro',
                    ],
                ],
                'message' => 'Player ID is valid',
            ]),
        ]);

        $result = $this->driver->validatePlayerId('12345');

        $this->assertTrue($result['valid']);
        $this->assertSame('12345', $result['playerId']);
        $this->assertSame('GamerPro', $result['username']);
        $this->assertNull($result['error']);
    }

    public function test_validate_player_id_returns_invalid_for_invalid_player(): void
    {
        Http::fake([
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'error',
                'message' => 'Player ID is invalid',
                'result' => null,
            ], 422),
        ]);

        $result = $this->driver->validatePlayerId('invalid-123');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['playerId']);
        $this->assertNull($result['username']);
        $this->assertSame('Player ID is invalid', $result['error']);
    }

    public function test_validate_player_id_handles_network_error(): void
    {
        Http::fake([
            'api.golf-test.com/api/order/jawaker/validate' => Http::response(null, 500),
        ]);

        $result = $this->driver->validatePlayerId('12345');

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Invoke the private fetchProductCustomFields method via reflection.
     *
     * @return array<int, array{id: int, name: string, desc: string|null, sort: int}>
     */
    private function invokeFetchCustomFields(string $supplierProductId): array
    {
        $method = new \ReflectionMethod($this->driver, 'fetchProductCustomFields');
        $method->setAccessible(true);

        return $method->invoke($this->driver, $supplierProductId);
    }
}
