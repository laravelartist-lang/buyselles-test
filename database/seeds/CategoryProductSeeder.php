<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\DigitalProductCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryProductSeeder extends Seeder
{
    private DigitalProductCodeService $codeService;

    public function __construct()
    {
        $this->codeService = app(DigitalProductCodeService::class);
    }

    public function run(): void
    {
        $this->seedBrands();
        $this->seedCategories();
        $this->seedDigitalProducts();
        $this->seedPhysicalProducts();
    }

    // ─── Brands ────────────────────────────────────────────────────────────

    private function seedBrands(): void
    {
        $brands = ['Apple', 'Samsung', 'Sony', 'Nike', 'Adidas'];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(
                ['name' => $brand],
                ['slug' => Str::slug($brand), 'status' => 1],
            );
        }

        $this->command?->info('Seeded '.count($brands).' brands.');
    }

    // ─── Categories ────────────────────────────────────────────────────────

    private function seedCategories(): void
    {
        // === Main Categories (position 0) ===

        $mainCategories = [
            ['name' => 'Gaming', 'category_type' => 'digital', 'priority' => 1],
            ['name' => 'Software & Apps', 'category_type' => 'digital', 'priority' => 2],
            ['name' => 'Gift Cards', 'category_type' => 'digital', 'priority' => 3],
            ['name' => 'Electronics', 'category_type' => 'physical', 'priority' => 4],
            ['name' => 'Clothing & Fashion', 'category_type' => 'physical', 'priority' => 5],
            ['name' => 'Home & Garden', 'category_type' => 'physical', 'priority' => 6],
        ];

        $mainCategoryIds = [];

        foreach ($mainCategories as $data) {
            $cat = Category::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'parent_id' => 0,
                    'position' => 0,
                    'priority' => $data['priority'],
                    'home_status' => 1,
                    'category_type' => $data['category_type'],
                ],
            );
            $mainCategoryIds[] = $cat->id;
        }

        $this->command?->info('Seeded '.count($mainCategories).' main categories.');

        // Seed display blocks for all main categories (taste requirement)
        app(CategoryDisplayBlockSeeder::class)->seedCategories(categoryIds: $mainCategoryIds);
        $this->command?->info('Seeded display blocks for all main categories.');

        // === Sub-categories (position 1) ===

        // Under Gaming (id: mainCategoryIds[0])
        $gamingSubs = [
            ['name' => 'PlayStation', 'parent' => $mainCategoryIds[0], 'priority' => 1],
            ['name' => 'Xbox', 'parent' => $mainCategoryIds[0], 'priority' => 2],
            ['name' => 'Nintendo', 'parent' => $mainCategoryIds[0], 'priority' => 3],
            ['name' => 'PC Gaming', 'parent' => $mainCategoryIds[0], 'priority' => 4],
        ];

        // Under Software & Apps (id: mainCategoryIds[1])
        $softwareSubs = [
            ['name' => 'Antivirus', 'parent' => $mainCategoryIds[1], 'priority' => 1],
            ['name' => 'VPN', 'parent' => $mainCategoryIds[1], 'priority' => 2],
            ['name' => 'Cloud Storage', 'parent' => $mainCategoryIds[1], 'priority' => 3],
            ['name' => 'Productivity', 'parent' => $mainCategoryIds[1], 'priority' => 4],
        ];

        // Under Gift Cards (id: mainCategoryIds[2])
        $giftCardSubs = [
            ['name' => 'Google Play', 'parent' => $mainCategoryIds[2], 'priority' => 1],
            ['name' => 'iTunes', 'parent' => $mainCategoryIds[2], 'priority' => 2],
            ['name' => 'Amazon', 'parent' => $mainCategoryIds[2], 'priority' => 3],
            ['name' => 'Steam', 'parent' => $mainCategoryIds[2], 'priority' => 4],
        ];

        // Under Electronics (id: mainCategoryIds[3])
        $electronicsSubs = [
            ['name' => 'Smartphones', 'parent' => $mainCategoryIds[3], 'priority' => 1],
            ['name' => 'Laptops', 'parent' => $mainCategoryIds[3], 'priority' => 2],
            ['name' => 'Audio', 'parent' => $mainCategoryIds[3], 'priority' => 3],
            ['name' => 'Accessories', 'parent' => $mainCategoryIds[3], 'priority' => 4],
        ];

        // Under Clothing & Fashion (id: mainCategoryIds[4])
        $clothingSubs = [
            ['name' => 'Men', 'parent' => $mainCategoryIds[4], 'priority' => 1],
            ['name' => 'Women', 'parent' => $mainCategoryIds[4], 'priority' => 2],
            ['name' => 'Kids', 'parent' => $mainCategoryIds[4], 'priority' => 3],
            ['name' => 'Footwear', 'parent' => $mainCategoryIds[4], 'priority' => 4],
        ];

        // Under Home & Garden (id: mainCategoryIds[5])
        $homeSubs = [
            ['name' => 'Furniture', 'parent' => $mainCategoryIds[5], 'priority' => 1],
            ['name' => 'Kitchen', 'parent' => $mainCategoryIds[5], 'priority' => 2],
            ['name' => 'Decor', 'parent' => $mainCategoryIds[5], 'priority' => 3],
            ['name' => 'Garden Tools', 'parent' => $mainCategoryIds[5], 'priority' => 4],
        ];

        $allSubs = array_merge($gamingSubs, $softwareSubs, $giftCardSubs, $electronicsSubs, $clothingSubs, $homeSubs);
        $subCategoryIds = [];

        foreach ($allSubs as $data) {
            $parent = Category::find($data['parent']);
            $cat = Category::firstOrCreate(
                ['slug' => Str::slug($data['name']).'-'.$data['parent']],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']).'-'.$data['parent'],
                    'parent_id' => $data['parent'],
                    'position' => 1,
                    'priority' => $data['priority'],
                    'home_status' => 1,
                    'category_type' => $parent?->category_type ?? 'physical',
                ],
            );
            $subCategoryIds[] = $cat->id;
        }

        $this->command?->info('Seeded '.count($allSubs).' sub-categories.');

        // === Sub-sub-categories (position 2) ===

        // Under PlayStation (subCategoryIds[0])
        $playstationSubSubs = [
            ['name' => 'PS5 Games', 'parent' => $subCategoryIds[0], 'priority' => 1],
            ['name' => 'PS4 Games', 'parent' => $subCategoryIds[0], 'priority' => 2],
            ['name' => 'PSN Cards', 'parent' => $subCategoryIds[0], 'priority' => 3],
        ];

        // Under Smartphones (subCategoryIds[12])
        $smartphoneSubSubs = [
            ['name' => 'iPhone', 'parent' => $subCategoryIds[12], 'priority' => 1],
            ['name' => 'Samsung Galaxy', 'parent' => $subCategoryIds[12], 'priority' => 2],
            ['name' => 'Google Pixel', 'parent' => $subCategoryIds[12], 'priority' => 3],
        ];

        // Under Men (subCategoryIds[16])
        $menSubSubs = [
            ['name' => 'Shirts', 'parent' => $subCategoryIds[16], 'priority' => 1],
            ['name' => 'Pants', 'parent' => $subCategoryIds[16], 'priority' => 2],
            ['name' => 'Jackets', 'parent' => $subCategoryIds[16], 'priority' => 3],
        ];

        // Under Kitchen (subCategoryIds[21])
        $kitchenSubSubs = [
            ['name' => 'Cookware', 'parent' => $subCategoryIds[21], 'priority' => 1],
            ['name' => 'Utensils', 'parent' => $subCategoryIds[21], 'priority' => 2],
            ['name' => 'Small Appliances', 'parent' => $subCategoryIds[21], 'priority' => 3],
        ];

        $allSubSubs = array_merge($playstationSubSubs, $smartphoneSubSubs, $menSubSubs, $kitchenSubSubs);

        foreach ($allSubSubs as $data) {
            $parent = Category::find($data['parent']);
            Category::firstOrCreate(
                ['slug' => Str::slug($data['name']).'-'.$data['parent']],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']).'-'.$data['parent'],
                    'parent_id' => $data['parent'],
                    'position' => 2,
                    'priority' => $data['priority'],
                    'home_status' => 1,
                    'category_type' => $parent?->category_type ?? 'physical',
                ],
            );
        }

        $this->command?->info('Seeded '.count($allSubSubs).' sub-sub-categories.');
    }

    // ─── Digital Products ──────────────────────────────────────────────────

    private function seedDigitalProducts(): void
    {
        $shop = \App\Models\Shop::first();
        $this->createDigitalProductWithCodes(
            'PlayStation Store Gift Card $50',
            'Buy a $50 PlayStation Store gift card',
            categoryId: $this->findCategory('PlayStation')?->id ?? 0,
            subCategoryId: $this->findCategory('PSN Cards')?->id ?? 0,
            unitPrice: 50.00,
            codeCount: 100,
            shopId: $shop?->id ?? 0,
        );

        $this->createDigitalProductWithCodes(
            'Google Play Gift Card $25',
            'Google Play $25 gift card for apps and games',
            categoryId: $this->findCategory('Gift Cards')?->id ?? 0,
            subCategoryId: $this->findCategory('Google Play')?->id ?? 0,
            unitPrice: 25.00,
            codeCount: 100,
            shopId: $shop?->id ?? 0,
        );

        $this->createDigitalProductWithCodes(
            'Norton Antivirus 1 Year',
            '1 year Norton Antivirus subscription key',
            categoryId: $this->findCategory('Software & Apps')?->id ?? 0,
            subCategoryId: $this->findCategory('Antivirus')?->id ?? 0,
            unitPrice: 39.99,
            codeCount: 100,
            shopId: $shop?->id ?? 0,
        );

        // Digital products WITHOUT codes (ready_after_sell)
        $this->createDigitalProductWithoutCodes(
            'Custom Software Development',
            'Bespoke software development service delivered after purchase',
            categoryId: $this->findCategory('Software & Apps')?->id ?? 0,
            subCategoryId: $this->findCategory('Productivity')?->id ?? 0,
            unitPrice: 500.00,
            shopId: $shop?->id ?? 0,
        );

        $this->createDigitalProductWithoutCodes(
            'Premium Design Template Pack',
            'Exclusive design templates delivered on purchase',
            categoryId: $this->findCategory('Software & Apps')?->id ?? 0,
            subCategoryId: $this->findCategory('Cloud Storage')?->id ?? 0,
            unitPrice: 29.99,
            shopId: $shop?->id ?? 0,
        );

        $this->command?->info('Seeded 5 digital products.');
    }

    private function createDigitalProductWithCodes(
        string $name,
        string $description,
        int $categoryId,
        int $subCategoryId,
        float $unitPrice,
        int $codeCount,
        int $shopId,
    ): Product {
        $slug = Str::slug($name);

        $product = Product::firstOrCreate(
            ['slug' => $slug],
            [
                'added_by' => 'admin',
                'user_id' => 1,
                'shop_id' => $shopId,
                'name' => $name,
                'code' => 'DP-'.strtoupper(Str::random(6)),
                'slug' => $slug,
                'category_id' => $categoryId,
                'sub_category_id' => $subCategoryId,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
                'details' => $description,
                'unit_price' => $unitPrice,
                'current_stock' => $codeCount,
                'minimum_order_qty' => 1,
                'status' => 1,
                'request_status' => 1,
                'multiply_qty' => 0,
                'tax' => 0,
                'tax_type' => 'percent',
                'tax_model' => 'exclude',
                'discount' => 0,
                'discount_type' => 'flat',
            ],
        );

        $codes = [];
        for ($i = 1; $i <= $codeCount; $i++) {
            $codes[] = [
                'code' => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
                'serial_number' => 'SN-'.strtoupper(Str::random(8)),
                'expiry_date' => now()->addYears(2)->toDateString(),
            ];
        }

        $result = $this->codeService->bulkAddToPool($product->id, $codes, 'manual');
        $this->command?->info("   {$name}: {$codeCount} codes (inserted {$result['inserted']}, skipped {$result['skipped']})");

        return $product;
    }

    private function createDigitalProductWithoutCodes(
        string $name,
        string $description,
        int $categoryId,
        int $subCategoryId,
        float $unitPrice,
        int $shopId,
    ): Product {
        $slug = Str::slug($name);

        return Product::firstOrCreate(
            ['slug' => $slug],
            [
                'added_by' => 'admin',
                'user_id' => 1,
                'shop_id' => $shopId,
                'name' => $name,
                'code' => 'DP-'.strtoupper(Str::random(6)),
                'slug' => $slug,
                'category_id' => $categoryId,
                'sub_category_id' => $subCategoryId,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_after_sell',
                'details' => $description,
                'unit_price' => $unitPrice,
                'current_stock' => 0,
                'minimum_order_qty' => 1,
                'status' => 1,
                'request_status' => 1,
                'multiply_qty' => 0,
                'tax' => 0,
                'tax_type' => 'percent',
                'tax_model' => 'exclude',
                'discount' => 0,
                'discount_type' => 'flat',
            ],
        );
    }

    // ─── Physical Products ─────────────────────────────────────────────────

    private function seedPhysicalProducts(): void
    {
        $shop = \App\Models\Shop::first();
        $electronicsId = $this->findCategory('Electronics')?->id ?? 0;
        $clothingId = $this->findCategory('Clothing & Fashion')?->id ?? 0;
        $homeId = $this->findCategory('Home & Garden')?->id ?? 0;

        // Electronics sub-category
        $smartphonesId = $this->findCategory('Smartphones')?->id ?? 0;
        $accessoriesId = $this->findCategory('Accessories')?->id ?? 0;
        $iphoneId = $this->findCategory('iPhone')?->id ?? 0;

        // Clothing sub-category
        $menId = $this->findCategory('Men')?->id ?? 0;
        $shirtsId = $this->findCategory('Shirts')?->id ?? 0;

        // Home sub-category
        $furnitureId = $this->findCategory('Furniture')?->id ?? 0;
        $kitchenId = $this->findCategory('Kitchen')?->id ?? 0;

        $products = [
            [
                'name' => 'iPhone 15 Pro Max 256GB',
                'description' => 'Apple iPhone 15 Pro Max with 256GB storage, Titanium finish',
                'category_id' => $electronicsId,
                'sub_category_id' => $smartphonesId,
                'sub_sub_category_id' => $iphoneId,
                'brand_id' => Brand::where('name', 'Apple')->value('id'),
                'unit_price' => 1199.99,
                'current_stock' => 50,
            ],
            [
                'name' => 'Wireless Bluetooth Earbuds',
                'description' => 'Noise-cancelling wireless earbuds with 30hr battery life',
                'category_id' => $electronicsId,
                'sub_category_id' => $accessoriesId,
                'sub_sub_category_id' => null,
                'brand_id' => Brand::where('name', 'Sony')->value('id'),
                'unit_price' => 89.99,
                'current_stock' => 200,
            ],
            [
                'name' => 'Premium Cotton Dress Shirt',
                'description' => 'Slim-fit premium cotton dress shirt, available in multiple colors',
                'category_id' => $clothingId,
                'sub_category_id' => $menId,
                'sub_sub_category_id' => $shirtsId,
                'brand_id' => Brand::where('name', 'Nike')->value('id'),
                'unit_price' => 59.99,
                'current_stock' => 150,
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'description' => 'Adjustable ergonomic office chair with lumbar support',
                'category_id' => $homeId,
                'sub_category_id' => $furnitureId,
                'sub_sub_category_id' => null,
                'brand_id' => null,
                'unit_price' => 249.99,
                'current_stock' => 25,
            ],
            [
                'name' => 'Stainless Steel Cookware Set',
                'description' => '10-piece stainless steel cookware set with non-stick coating',
                'category_id' => $homeId,
                'sub_category_id' => $kitchenId,
                'sub_sub_category_id' => null,
                'brand_id' => null,
                'unit_price' => 179.99,
                'current_stock' => 40,
            ],
        ];

        foreach ($products as $data) {
            $slug = Str::slug($data['name']);
            Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'added_by' => 'admin',
                    'user_id' => 1,
                    'shop_id' => $shop?->id ?? 0,
                    'name' => $data['name'],
                    'code' => 'PHY-'.strtoupper(Str::random(6)),
                    'slug' => $slug,
                    'category_id' => $data['category_id'],
                    'sub_category_id' => $data['sub_category_id'],
                    'sub_sub_category_id' => $data['sub_sub_category_id'],
                    'brand_id' => $data['brand_id'],
                    'product_type' => 'physical',
                    'unit' => 'pc',
                    'details' => $data['description'],
                    'unit_price' => $data['unit_price'],
                    'current_stock' => $data['current_stock'],
                    'minimum_order_qty' => 1,
                    'status' => 1,
                    'request_status' => 1,
                    'multiply_qty' => 1,
                    'shipping_cost' => 0,
                    'tax' => 0,
                    'tax_type' => 'percent',
                    'tax_model' => 'exclude',
                    'discount' => 0,
                    'discount_type' => 'flat',
                ],
            );
        }

        $this->command?->info('Seeded 5 physical products.');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function findCategory(string $name): ?Category
    {
        return Category::where('name', $name)->first();
    }
}
