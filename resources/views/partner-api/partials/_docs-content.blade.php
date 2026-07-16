            {{-- Table of contents --}}
@php
    $apiExamples ??= app(\App\Services\Partner\PartnerApiDocumentationExamplesService::class)->build();
    $apiExampleFormatter ??= app(\App\Services\Partner\PartnerApiDocumentationExamplesService::class);
    $sampleProductId = $apiExamples['sample_product_id'] ?? 1;
    $sampleOrderId = $apiExamples['sample_order_id'] ?? 100001;
@endphp
            <div class="info-box mb-4">
                <div class="fw-semibold mb-2"><i class="fi fi-rr-list me-1 text-primary"></i> Contents</div>
                <div class="d-flex flex-wrap gap-3">
                    <a class="toc-link" href="#overview">Overview</a>
                    <a class="toc-link" href="#authentication">Authentication</a>
                    <a class="toc-link" href="#product-catalog">Product Catalog</a>
                    <a class="toc-link" href="#endpoints">Endpoints</a>
                    <a class="toc-link" href="#idempotency">Idempotency</a>
                    <a class="toc-link" href="#escrow">Escrow</a>
                    <a class="toc-link" href="#ip-whitelist">IP Whitelist</a>
                    <a class="toc-link" href="#rate-limiting">Rate Limiting</a>
                    <a class="toc-link" href="#error-codes">Error Codes</a>
                </div>
            </div>

            {{-- ── Overview ───────────────────────────────────────────── --}}
            <div class="docs-section" id="overview">
                <h5 class="fw-bold mb-3">{{ translate('overview') }}</h5>
                <p>{{ translate('reseller_api_overview_text') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="fw-semibold small mb-1">{{ translate('base_url') }} <span class="version-badge">v1</span></div>
                            <code class="api-endpoint small">{{ url('/api/v1/partner') }}</code>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="fw-semibold small mb-1">Content-Type</div>
                            <code class="small">application/json</code>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="fw-semibold small mb-1">{{ translate('authentication') }}</div>
                            <code class="small">X-API-KEY + X-API-SECRET</code>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Authentication ─────────────────────────────────────── --}}
            <div class="docs-section" id="authentication">
                <h5 class="fw-bold mb-3">{{ translate('authentication') }}</h5>
                <p>{{ translate('reseller_auth_description') }}</p>
                <p class="mb-2">Every request must include <strong>both</strong> headers:</p>
                <pre class="code-block">GET /api/v1/partner/products HTTP/1.1
Host: {{ parse_url(config('app.url'), PHP_URL_HOST) }}
X-API-KEY: rslr_your_api_key_here
X-API-SECRET: your_api_secret_here
Accept: application/json</pre>

                <div class="fw-semibold mt-3 mb-2">Auth error responses</div>
                <pre class="code-block">// 401 — Missing credentials
{ "error": "Missing API credentials. Provide X-API-KEY and X-API-SECRET headers." }

// 401 — Invalid key
{ "error": "Invalid API key." }

// 401 — Wrong secret
{ "error": "Invalid API secret." }

// 403 — Key awaiting admin approval
{ "error": "API key is awaiting admin approval." }

// 403 — Key deactivated by admin
{ "error": "API key is deactivated." }

// 403 — IP not in whitelist
{ "error": "IP address not allowed." }</pre>
            </div>

            {{-- ── Product Catalog ─────────────────────────────────────── --}}
            <div class="docs-section" id="product-catalog">
                <h5 class="fw-bold mb-3">Product Catalog</h5>
                <p>The Partner API exposes a <strong>curated, partner-specific catalog</strong>. Only products an admin assigns to your partner account appear. Each assignment has an <strong>exact partner price</strong> (not a global markup). Unassigned supplier catalog items are never listed.</p>
                <div class="info-box mb-3">
                    <strong>Admin workflow:</strong> Reseller API Keys → Partner API Catalog → choose a supplier → browse that supplier’s catalog (same sync UI as storefront mappings) → set an exact partner price → Allow. Storefront supplier mappings remain separate; removing a Partner API catalog item does not delete storefront mappings.
                </div>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light">
                        <tr><th>Query param</th><th>Values</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>fulfillment_type</code></td><td><code>local_codes</code> | <code>supplier_codes</code> | <code>direct_topup</code></td><td>Filter by fulfillment source</td></tr>
                        <tr><td><code>seller_type</code></td><td><code>in_house</code> | <code>vendor</code></td><td>Filter by product owner type</td></tr>
                    </tbody>
                </table>
                <div class="fw-semibold mb-2">Product response fields <span class="text-muted small">(sanitized example — structure matches live API)</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['catalog_field_sample'])) !!}</pre>
                <p class="text-muted small mt-2">Charge price is always <code>pricing.unit_price</code> (or denomination / top-up bundle price). Optional quote: <code>POST /products/{id}/quote</code>.</p>
            </div>

            {{-- ── Endpoints ───────────────────────────────────────────── --}}
            <div class="docs-section" id="endpoints">
                <h5 class="fw-bold mb-4">{{ translate('endpoints') }}</h5>

                {{-- Endpoints summary table --}}
                <table class="table table-bordered table-sm mb-4">
                    <thead class="table-light">
                        <tr>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>Permission required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><span class="badge method-badge-get text-white">GET</span></td><td><code>/api/v1/partner/products</code></td><td><code>products.list</code></td><td>{{ translate('list_available_products') }}</td></tr>
                        <tr><td><span class="badge method-badge-get text-white">GET</span></td><td><code>/api/v1/partner/products/{id}</code></td><td><code>products.list</code></td><td>{{ translate('get_product_details') }}</td></tr>
                        <tr><td><span class="badge method-badge-post text-white">POST</span></td><td><code>/api/v1/partner/orders</code></td><td><code>orders.create</code></td><td>{{ translate('create_order') }}</td></tr>
                        <tr><td><span class="badge method-badge-get text-white">GET</span></td><td><code>/api/v1/partner/orders/{id}</code></td><td><code>orders.view</code></td><td>{{ translate('get_order_status') }}</td></tr>
                        <tr><td><span class="badge method-badge-get text-white">GET</span></td><td><code>/api/v1/partner/balance</code></td><td><code>balance.view</code></td><td>{{ translate('check_wallet_balance') }}</td></tr>
                    </tbody>
                </table>

                {{-- GET /products --}}
                <div class="endpoint-card">
                    <div class="endpoint-header d-flex align-items-center gap-2">
                        <span class="badge method-badge-get text-white">GET</span>
                        <code class="api-endpoint">/api/v1/partner/products</code>
                        <span class="text-muted ms-2 small">— {{ translate('list_available_products') }}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="text-muted mb-3">{{ translate('products_endpoint_description') }}</p>
                        <div class="fw-semibold mb-2">{{ translate('query_parameters') }}</div>
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-light">
                                <tr><th>{{ translate('parameter') }}</th><th>{{ translate('type') }}</th><th>{{ translate('required') }}</th><th>{{ translate('description') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>search</code></td><td>string</td><td>No</td><td>{{ translate('filter_by_product_name') }}</td></tr>
                                <tr><td><code>category_id</code></td><td>integer</td><td>No</td><td>{{ translate('filter_by_category') }}</td></tr>
                                <tr><td><code>page</code></td><td>integer</td><td>No</td><td>{{ translate('page_number_default_1') }}</td></tr>
                                <tr><td><code>per_page</code></td><td>integer</td><td>No</td><td>{{ translate('items_per_page_default_20_max_100') }}</td></tr>
                                <tr><td><code>include_vendor</code></td><td>boolean</td><td>No</td><td>Pass <code>1</code> to include vendor products</td></tr>
                                <tr><td><code>fulfillment_type</code></td><td>string</td><td>No</td><td><code>local_codes</code> or <code>supplier_codes</code></td></tr>
                                <tr><td><code>seller_type</code></td><td>string</td><td>No</td><td><code>in_house</code> or <code>vendor</code></td></tr>
                            </tbody>
                        </table>
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span> <span class="text-muted small">— sanitized example response</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['products_list'])) !!}</pre>
                    </div>
                </div>

                {{-- GET /products/{id} --}}
                <div class="endpoint-card">
                    <div class="endpoint-header d-flex align-items-center gap-2">
                        <span class="badge method-badge-get text-white">GET</span>
                        <code class="api-endpoint">/api/v1/partner/products/{id}</code>
                        <span class="text-muted ms-2 small">— {{ translate('get_product_details') }}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="text-muted mb-3">{{ translate('product_detail_endpoint_description') }}</p>
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span> <span class="text-muted small">— sanitized example for GET /api/v1/partner/products/{{ $sampleProductId }}</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['product_detail'])) !!}</pre>
                        <div class="fw-semibold mb-2 mt-3">{{ translate('error_example') }} <span class="badge bg-danger text-white">404</span></div>
<pre class="code-block">{ "error": "Product not found." }</pre>
                    </div>
                </div>

                {{-- POST /orders --}}
                <div class="endpoint-card">
                    <div class="endpoint-header d-flex align-items-center gap-2">
                        <span class="badge method-badge-post text-white">POST</span>
                        <code class="api-endpoint">/api/v1/partner/orders</code>
                        <span class="text-muted ms-2 small">— {{ translate('create_order') }}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="text-muted mb-3">{{ translate('create_order_endpoint_description') }}</p>
                        <div class="warning-box mb-3">
                            <i class="fi fi-sr-triangle-warning text-warning me-1"></i>
                            <strong>Supplier-backed orders:</strong> If codes are fetched asynchronously from an upstream supplier, the response may return <code>status: pending_fulfillment</code> with partial or empty <code>codes</code>. Poll <code>GET /orders/{id}</code> until <code>fulfillment_status</code> is <code>fulfilled</code>.
                        </div>
                        <div class="warning-box mb-3">
                            <i class="fi fi-sr-triangle-warning text-warning me-1"></i>
                            <strong>Idempotency recommended:</strong> always send <code>X-Idempotency-Key</code> to prevent double-charging on retries. See the <a href="#idempotency">Idempotency</a> section below.
                        </div>
                        <div class="fw-semibold mb-2">Request headers</div>
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-light">
                                <tr><th>Header</th><th>Required</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>X-API-KEY</code></td><td>Yes</td><td>Your API key</td></tr>
                                <tr><td><code>X-API-SECRET</code></td><td>Yes</td><td>Your API secret</td></tr>
                                <tr><td><code>Content-Type</code></td><td>Yes</td><td><code>application/json</code></td></tr>
                                <tr><td><code>X-Idempotency-Key</code></td><td>Recommended</td><td>Any unique string per order attempt (UUID recommended)</td></tr>
                            </tbody>
                        </table>
                        <div class="fw-semibold mb-2">{{ translate('request_body') }} (JSON)</div>
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-light">
                                <tr><th>{{ translate('field') }}</th><th>{{ translate('type') }}</th><th>{{ translate('required') }}</th><th>{{ translate('description') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>product_id</code></td><td>integer</td><td>Yes</td><td>{{ translate('id_from_products_list') }}</td></tr>
                                <tr><td><code>quantity</code></td><td>integer</td><td>Yes</td><td>{{ translate('number_of_codes_1_to_100') }}</td></tr>
                                <tr><td><code>reference</code></td><td>string</td><td>No</td><td>{{ translate('your_internal_reference_for_tracking') }}</td></tr>
                            </tbody>
                        </table>
<pre class="code-block">POST /api/v1/partner/orders
X-API-KEY: rslr_your_key
X-API-SECRET: your_secret
X-Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
Content-Type: application/json

{
  "product_id": {{ $sampleProductId }},
  "quantity": 1,
  "reference": "your-internal-ref-001"
}</pre>
                        <div class="fw-semibold mb-2 mt-3">{{ translate('response_example') }} <span class="badge bg-primary text-white">201</span> <span class="text-muted small">— sanitized fulfilled order example</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['create_order_fulfilled'])) !!}</pre>
@if($apiExamples['create_order_pending'])
                        <div class="fw-semibold mb-2 mt-3">{{ translate('response_example') }} <span class="badge bg-warning text-dark">201</span> <span class="text-muted small">— supplier-backed order awaiting async fulfillment</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['create_order_pending'])) !!}</pre>
@else
                        <div class="fw-semibold mb-2 mt-3">{{ translate('response_example') }} <span class="badge bg-warning text-dark">201</span> <span class="text-muted small">— supplier-backed order awaiting async fulfillment</span></div>
<pre class="code-block">{
  "data": {
    "order_id": 100054,
    "product_id": {{ $sampleProductId }},
    "product_name": "Supplier-backed product",
    "quantity_requested": 1,
    "quantity_fulfilled": 0,
    "total_cost": 43.34,
    "status": "pending_fulfillment",
    "reference": "your-internal-ref-002",
    "codes": []
  }
}</pre>
@endif
@if($apiExamples['create_order_idempotent_replay'])
                        <div class="fw-semibold mb-2 mt-3">Idempotent replay <span class="badge bg-secondary text-white">201</span> <span class="text-muted small">— same key returns cached response, no new charge</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['create_order_idempotent_replay'])) !!}</pre>
@else
                        <div class="fw-semibold mb-2 mt-3">Idempotent replay <span class="badge bg-secondary text-white">201</span></div>
<pre class="code-block">{
  "data": { ... },
  "idempotent_replay": true
}</pre>
@endif
                        <div class="fw-semibold mb-2 mt-3">{{ translate('error_examples') }}</div>
<pre class="code-block">// 402 — Insufficient wallet balance
{ "error": "Insufficient wallet balance.", "balance": 5.00, "required": 20.00, "status": 402 }

// 409 — Insufficient stock
{ "error": "Insufficient stock.", "available": 0, "requested": 1, "status": 409 }

// 404 — Product not available or not approved
{ "error": "Product not found or not available.", "status": 404 }

// 422 — Validation error
{ "errors": { "product_id": ["The product id field is required."] } }

// 403 — Missing permission
{ "error": "Permission denied." }</pre>
                    </div>
                </div>

                {{-- GET /orders/{id} --}}
                <div class="endpoint-card">
                    <div class="endpoint-header d-flex align-items-center gap-2">
                        <span class="badge method-badge-get text-white">GET</span>
                        <code class="api-endpoint">/api/v1/partner/orders/{id}</code>
                        <span class="text-muted ms-2 small">— {{ translate('get_order_status') }}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="text-muted mb-3">{{ translate('order_status_endpoint_description') }}</p>
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span> <span class="text-muted small">— sanitized example for GET /api/v1/partner/orders/{{ $sampleOrderId }}</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['order_detail'])) !!}</pre>
                        <div class="fw-semibold mb-2 mt-3">{{ translate('error_example') }} <span class="badge bg-danger text-white">404</span></div>
<pre class="code-block">{ "error": "Order not found." }</pre>
                    </div>
                </div>

                {{-- GET /balance --}}
                <div class="endpoint-card">
                    <div class="endpoint-header d-flex align-items-center gap-2">
                        <span class="badge method-badge-get text-white">GET</span>
                        <code class="api-endpoint">/api/v1/partner/balance</code>
                        <span class="text-muted ms-2 small">— {{ translate('check_wallet_balance') }}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="text-muted mb-3">{{ translate('balance_endpoint_description') }}</p>
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span> <span class="text-muted small">— sanitized wallet example</span></div>
<pre class="code-block">{!! e($apiExampleFormatter->formatJson($apiExamples['balance'])) !!}</pre>
                    </div>
                </div>
            </div>

            {{-- ── Idempotency ─────────────────────────────────────────── --}}
            <div class="docs-section" id="idempotency">
                <h5 class="fw-bold mb-3">{{ translate('idempotency') }}</h5>
                <p>{{ translate('idempotency_description') }}</p>
                <div class="info-box mb-3">
                    <i class="fi fi-sr-info me-1 text-primary"></i>
                    Use a UUID v4 as your idempotency key. Store it alongside your internal order record before sending the request.
                </div>
<pre class="code-block">// First call — order created, balance deducted
POST /api/v1/partner/orders
X-Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
→ 201 Created
{!! e($apiExampleFormatter->formatJson($apiExamples['create_order_fulfilled'])) !!}

// Retry with same key — no new order, no charge
POST /api/v1/partner/orders
X-Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
→ 201 Created
{!! e($apiExampleFormatter->formatJson($apiExamples['create_order_idempotent_replay'] ?? array_merge($apiExamples['create_order_fulfilled'] ?? [], ['idempotent_replay' => true]))) !!}</pre>
            </div>

            {{-- ── Escrow ──────────────────────────────────────────────── --}}
            <div class="docs-section" id="escrow">
                <h5 class="fw-bold mb-3">{{ translate('escrow') }}</h5>
                <p>{{ translate('escrow_description') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-semibold mb-1">Step 1 — Order placed</div>
                            <div class="small text-muted">Partner wallet debited immediately. Vendor receives funds in <code>pending_balance</code>.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-semibold mb-1">Step 2 — 48-hour hold</div>
                            <div class="small text-muted">Funds stay in vendor <code>pending_balance</code>. Disputes can be raised in this window.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center">
                            <div class="fw-semibold mb-1">Step 3 — Auto release</div>
                            <div class="small text-muted">After 48 hours with no dispute, funds move to vendor <code>available_balance</code> automatically.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── IP Whitelist ─────────────────────────────────────────── --}}
            <div class="docs-section" id="ip-whitelist">
                <h5 class="fw-bold mb-3">{{ translate('ip_whitelist_behavior') }}</h5>
                <p>{{ translate('ip_whitelist_behavior_description') }}</p>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th>Whitelist state</th><th>Behaviour</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Empty (no IPs added)</td><td><span class="text-success fw-semibold">All IPs allowed</span> — open mode, useful for development</td></tr>
                        <tr><td>One or more IPs added</td><td><span class="text-danger fw-semibold">Only listed IPs allowed</span> — all others receive <code>403 IP address not allowed.</code></td></tr>
                    </tbody>
                </table>
                <p class="text-muted small mt-2">IPv4 (e.g. <code>203.0.113.10</code>) and IPv6 (e.g. <code>2001:db8::1</code>) are both supported. CIDR ranges are not supported.</p>
            </div>

            {{-- ── Rate Limiting ────────────────────────────────────────── --}}
            <div class="docs-section" id="rate-limiting">
                <h5 class="fw-bold mb-3">{{ translate('rate_limiting') }}</h5>
                <p>Each API key has its own rate limit (default: <strong>60 requests/minute</strong>, configurable per key). When the limit is exceeded, a <code>429</code> response is returned with a <code>Retry-After</code> header.</p>
<pre class="code-block">// 429 response
{
  "error": "Rate limit exceeded.",
  "retry_after_seconds": 34
}

// Response headers included on every request:
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58</pre>
            </div>

            {{-- ── Error Codes ─────────────────────────────────────────── --}}
            <div class="docs-section" id="error-codes">
                <h5 class="fw-bold mb-3">{{ translate('error_codes') }}</h5>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>{{ translate('http_status') }}</th>
                            <th>When it occurs</th>
                            <th>{{ translate('description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>401</code></td><td>Missing, invalid, or mismatched credentials</td><td>{{ translate('invalid_api_key_or_secret') }}</td></tr>
                        <tr><td><code>402</code></td><td>POST /orders</td><td>{{ translate('insufficient_wallet_balance') }}</td></tr>
                        <tr><td><code>403</code></td><td>Key inactive / IP blocked / no permission</td><td>{{ translate('api_key_disabled_or_ip_not_whitelisted') }}</td></tr>
                        <tr><td><code>404</code></td><td>Product / order not found</td><td>{{ translate('resource_not_found') }}</td></tr>
                        <tr><td><code>409</code></td><td>POST /orders</td><td>{{ translate('insufficient_stock_for_requested_quantity') }}</td></tr>
                        <tr><td><code>422</code></td><td>Invalid request body</td><td>{{ translate('validation_error_check_errors_field') }}</td></tr>
                        <tr><td><code>429</code></td><td>Rate limit exceeded</td><td>{{ translate('rate_limit_exceeded') }}</td></tr>
                        <tr><td><code>500</code></td><td>Unexpected server error</td><td>{{ translate('internal_server_error') }}</td></tr>
                    </tbody>
                </table>
            </div>
