# blade-views
- Do not display existing digital product code tables (serial numbers, card codes) on product update/edit forms — keep forms focused on input fields only to avoid UI clutter with large code pools. Confidence: 0.70

# laravel
- When building Artisan commands that truncate multiple database tables, verify each table exists (e.g., via `Schema::hasTable()`) before calling `truncate()` to avoid SQLSTATE[42S02] runtime errors from tables that may not actually be in the schema. Confidence: 0.55
- When creating a new main category, always add the 7 display blocks defined in app/Enums/CategoryDisplayBlockType.php (seeded via CategoryDisplayBlockSeeder) to ensure consistent category display configuration. Confidence: 0.75

# flutter
- For multipart file uploads in repositories, use raw `http.MultipartRequest` rather than Dio's internal API (`dioClient!.dio`). Access the auth token via string interpolation from headers, send via `request.send()`, read the stream with `http.Response.fromStream()`, and wrap the result using `ApiResponse.withSuccess()` with a manually constructed `Response` object instead of calling unavailable methods like `.resolve()` on the nullable Dio instance. Confidence: 0.70

# workflow
- When applying multiple changes from a file, first make a plan then execute the changes one by one incrementally rather than all at once. Confidence: 0.70

# laravel
- When filtering Category::childes() by vendor product scope in ShopViewController, match the relationship to the child's position level: for children of level-0 categories (sub-categories), use `whereHas('subCategoryProduct', ...)` (maps to `Product.sub_category_id`); for children of level-1 categories (sub-sub-categories), use `whereHas('subSubCategoryProduct', ...)` (maps to `Product.sub_sub_category_id`). Never use `whereHas('product', ...)` for these — that maps to `Product.category_id` which is wrong for child categories. Confidence: 0.70
- When fixing stock/availability issues for supplier-mapped products, check both backend validation (CartManager, DigitalProductCodeService, ResellerApiService) AND frontend display logic (Blade templates, JS components that render "out of stock" badges/buttons) — the `has_active_supplier_mapping` model attribute may need to be consumed in the UI layer too. Confidence: 0.65
