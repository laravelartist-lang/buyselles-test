<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\GenericRestDriver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SecretOrcaCatalogDemoController extends Controller
{
    public function __invoke(Request $request): View
    {
        $apiKey = (string) env('SECRETORCA_API_KEY', '');
        $baseUrl = rtrim((string) env('SECRETORCA_BASE_URL', 'https://api.secretorca.com'), '/');

        $result = [
            'connected' => false,
            'health_status' => 'unknown',
            'health_message' => '',
            'latency_ms' => null,
            'products' => [],
            'total_count' => 0,
            'pages_fetched' => 0,
            'error' => null,
        ];

        if ($apiKey === '') {
            $result['error'] = 'SECRETORCA_API_KEY is missing from .env';

            return view('demo.secretorca-catalog', $result);
        }

        try {
            $supplier = $this->buildSupplier($baseUrl, $apiKey);
            $driver = app(GenericRestDriver::class)->configure($supplier);

            $health = $driver->healthCheck();
            $result['health_status'] = $health->status;
            $result['health_message'] = $health->message;
            $result['latency_ms'] = $health->latencyMs;
            $result['connected'] = $health->status !== 'down';

            $page = 1;
            $pageSize = 100;
            $allProducts = [];

            do {
                $pageProducts = $driver->fetchProducts([
                    'page' => $page,
                    'page_size' => $pageSize,
                    'fetch_all' => false,
                ]);

                $countOnPage = count($pageProducts);
                $allProducts = array_merge($allProducts, $pageProducts);
                $result['pages_fetched'] = $page;

                if ($countOnPage === 0 || $countOnPage < $pageSize) {
                    break;
                }

                $page++;
            } while ($page <= 50);

            $result['products'] = collect($allProducts)->map(fn ($product): array => [
                'id' => $product->supplierProductId,
                'name' => $product->name,
                'category' => $product->category,
                'code' => $product->region,
                'price' => $product->price,
                'currency' => $product->currency,
                'stock' => $product->stockAvailable,
            ])->values()->all();

            $result['total_count'] = count($result['products']);
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return view('demo.secretorca-catalog', $result);
    }

    private function buildSupplier(string $baseUrl, string $apiKey): SupplierApi
    {
        $supplier = new SupplierApi([
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'base_url' => $baseUrl,
            'auth_type' => 'api_key',
            'settings' => (new \App\Console\Commands\ConnectSecretOrcaApiCommand)->secretOrcaSettings(),
            'is_active' => true,
        ]);
        $supplier->id = 1;
        $supplier->setEncryptedCredentials(['api_key' => $apiKey]);

        return $supplier;
    }
}
