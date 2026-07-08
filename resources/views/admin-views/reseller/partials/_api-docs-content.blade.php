@php
    $partnerApiHost = $partnerApiHost ?? parse_url(config('app.url'), PHP_URL_HOST);
    $partnerApiBaseUrl = $partnerApiBaseUrl ?? url('/api/v1/partner');
    $forPdf = $forPdf ?? false;
@endphp

            {{-- Table of contents --}}
            @if ($forPdf)
                <div class="info-box mb-4">
                    <strong>Contents:</strong>
                    Overview, Authentication, Endpoints, Idempotency, Escrow, IP Whitelist, Rate Limiting, Error Codes
                </div>
            @else
            <div class="info-box mb-4">
                <div class="fw-semibold mb-2"><i class="fi fi-rr-list me-1 text-primary"></i> Contents</div>
                <div class="d-flex flex-wrap gap-3">
                    <a class="toc-link" href="#overview">Overview</a>
                    <a class="toc-link" href="#authentication">Authentication</a>
                    <a class="toc-link" href="#endpoints">Endpoints</a>
                    <a class="toc-link" href="#idempotency">Idempotency</a>
                    <a class="toc-link" href="#escrow">Escrow</a>
                    <a class="toc-link" href="#ip-whitelist">IP Whitelist</a>
                    <a class="toc-link" href="#rate-limiting">Rate Limiting</a>
                    <a class="toc-link" href="#error-codes">Error Codes</a>
                </div>
            </div>
            @endif

            {{-- ── Overview ───────────────────────────────────────────── --}}
            <div class="docs-section" id="overview">
                <h5 class="fw-bold mb-3">{{ translate('overview') }}</h5>
                <p>{{ translate('reseller_api_overview_text') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="fw-semibold small mb-1">{{ translate('base_url') }} <span class="version-badge">v1</span></div>
                            <code class="api-endpoint small">{{ $partnerApiBaseUrl }}</code>
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
Host: {{ $partnerApiHost }}
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
                            </tbody>
                        </table>
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span></div>
<pre class="code-block">{
  "data": [
    {
      "id": 14,
      "name": "PUBG UC 200",
      "slug": "pubg-uc-200",
      "category_id": 1,
      "unit_price": 20.00,
      "purchase_price": 0,
      "available_stock": 379,
      "thumbnail": "https://{{ $partnerApiHost }}/storage/product/thumbnail/example.webp"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 98
  }
}</pre>
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
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span></div>
<pre class="code-block">{
  "data": {
    "id": 14,
    "name": "PUBG UC 200",
    "slug": "pubg-uc-200",
    "category_id": 1,
    "sub_category_id": 2,
    "brand_id": null,
    "unit_price": 20.00,
    "purchase_price": 0,
    "available_stock": 379,
    "thumbnail": "https://{{ $partnerApiHost }}/storage/product/thumbnail/example.webp",
    "description": "&lt;p&gt;200 PUBG Mobile UC delivered instantly.&lt;/p&gt;"
  }
}</pre>
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
                            <strong>Idempotency recommended:</strong> always send <code>X-Idempotency-Key</code> to prevent double-charging on retries.@unless($forPdf) See the <a href="#idempotency">Idempotency</a> section below.@else See the Idempotency section below.@endunless
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
  "product_id": 14,
  "quantity": 1,
  "reference": "your-internal-ref-001"
}</pre>
                        <div class="fw-semibold mb-2 mt-3">{{ translate('response_example') }} <span class="badge bg-primary text-white">201</span></div>
<pre class="code-block">{
  "data": {
    "order_id": 100053,
    "product_id": 14,
    "product_name": "PUBG UC 200",
    "quantity_requested": 1,
    "quantity_fulfilled": 1,
    "total_cost": 20.00,
    "status": "fulfilled",
    "reference": "your-internal-ref-001",
    "codes": [
      {
        "code": "TEWB-3440-DXGQ-6688",
        "serial": "SN-0021",
        "expiry": "2026-12-31"
      }
    ]
  }
}

// If X-Idempotency-Key was already used — same response returned, no charge:
{
  "data": { ... },
  "idempotent_replay": true
}</pre>
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
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span></div>
<pre class="code-block">{
  "data": {
    "order_id": 100053,
    "status": "delivered",
    "payment_status": "paid",
    "total": 20.00,
    "created_at": "2026-04-09T12:34:56+00:00",
    "items": [
      {
        "product_id": 14,
        "quantity": 1,
        "price": 20.00
      }
    ],
    "codes": [
      {
        "code": "TEWB-3440-DXGQ-6688",
        "serial": "SN-0021",
        "product_id": 14,
        "expiry": "2026-12-31"
      }
    ]
  }
}</pre>
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
                        <div class="fw-semibold mb-2">{{ translate('response_example') }} <span class="badge bg-success text-white">200</span></div>
<pre class="code-block">{
  "data": {
    "balance": 80.00,
    "currency": "USD",
    "key_id": 1,
    "key_name": "My Integration Key"
  }
}</pre>
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
→ 201 Created  { "data": { "order_id": 100053, ... } }

// Retry with same key — no new order, no charge
POST /api/v1/partner/orders
X-Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
→ 201 Created  { "data": { "order_id": 100053, ... }, "idempotent_replay": true }</pre>
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
