<?php

namespace App\Models;

use App\Traits\CacheManagerTrait;
use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\TaxModule\app\Models\Taxable;

/**
 * @property int $user_id
 * @property int $shop_id
 * @property string $added_by
 * @property string $name
 * @property string $code
 * @property string $slug
 * @property int $category_id
 * @property int $sub_category_id
 * @property int $sub_sub_category_id
 * @property int $brand_id
 * @property string $unit
 * @property string $digital_product_type
 * @property string $product_type
 * @property string $details
 * @property int $min_qty
 * @property int $published
 * @property float $tax
 * @property string $tax_type
 * @property string $tax_model
 * @property float $unit_price
 * @property int $status
 * @property float $discount
 * @property int $current_stock
 * @property int $minimum_order_qty
 * @property int $free_shipping
 * @property int $request_status
 * @property int $featured_status
 * @property int $refundable
 * @property int $featured
 * @property int $flash_deal
 * @property int $seller_id
 * @property float $purchase_price
 * @property string $denied_note
 * @property float $shipping_cost
 * @property int $multiply_qty
 * @property float $temp_shipping_cost
 * @property string $thumbnail
 * @property string $thumbnail_storage_type
 * @property string $preview_file
 * @property string $preview_file_storage_type
 * @property string $digital_file_ready
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_image
 * @property int $is_shipping_cost_updated
 * @property int|null $location_country_id
 * @property int|null $location_city_id
 * @property int|null $location_area_id
 */
class Product extends Model
{
    use CacheManagerTrait, StorageTrait;

    protected $fillable = [
        'user_id',
        'shop_id',
        'added_by',
        'name',
        'code',
        'slug',
        'category_ids',
        'category_id',
        'sub_category_id',
        'sub_sub_category_id',
        'brand_id',
        'unit',
        'digital_product_type',
        'product_type',
        'details',
        'colors',
        'choice_options',
        'variation',
        'digital_product_file_types',
        'digital_product_extensions',
        'unit_price',
        'purchase_price',
        'tax',
        'tax_type',
        'tax_model',
        'discount',
        'discount_type',
        'attributes',
        'current_stock',
        'minimum_order_qty',
        'sort_priority',
        'video_provider',
        'video_url',
        'status',
        'featured_status',
        'featured',
        'request_status',
        'denied_note',
        'shipping_cost',
        'multiply_qty',
        'color_image',
        'images',
        'thumbnail',
        'thumbnail_storage_type',
        'preview_file',
        'preview_file_storage_type',
        'digital_file_ready',
        'meta_title',
        'meta_description',
        'meta_image',
        'digital_file_ready_storage_type',
        'location_country_id',
        'location_city_id',
        'location_area_id',
        'is_shipping_cost_updated',
        'temp_shipping_cost',
        'location_country_id',
        'location_city_id',
        'location_area_id',
        'pending_city_request_id',
        'pending_area_request_id',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'shop_id' => 'integer',
        'added_by' => 'string',
        'name' => 'string',
        'code' => 'string',
        'slug' => 'string',
        'category_id' => 'integer',
        'sub_category_id' => 'integer',
        'sub_sub_category_id' => 'integer',
        'brand_id' => 'integer',
        'unit' => 'string',
        'digital_product_type' => 'string',
        'product_type' => 'string',
        'details' => 'string',
        'min_qty' => 'integer',
        'published' => 'integer',
        'tax' => 'float',
        'tax_type' => 'string',
        'tax_model' => 'string',
        'unit_price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'current_stock' => 'integer',
        'minimum_order_qty' => 'integer',
        'free_shipping' => 'integer',
        'request_status' => 'integer',
        'featured_status' => 'integer',
        'refundable' => 'integer',
        'featured' => 'integer',
        'flash_deal' => 'integer',
        'seller_id' => 'integer',
        'sort_priority' => 'integer',
        'purchase_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'denied_note' => 'string',
        'shipping_cost' => 'float',
        'multiply_qty' => 'integer',
        'temp_shipping_cost' => 'float',
        'thumbnail' => 'string',
        'preview_file' => 'string',
        'digital_file_ready' => 'string',
        'meta_title' => 'string',
        'meta_description' => 'string',
        'meta_image' => 'string',
        'is_shipping_cost_updated' => 'integer',
        'location_country_id' => 'integer',
        'location_city_id' => 'integer',
        'location_area_id' => 'integer',
        'pending_city_request_id' => 'integer',
        'pending_area_request_id' => 'integer',
        'digital_product_file_types' => 'array',
        'digital_product_extensions' => 'array',
        'thumbnail_storage_type' => 'string',
        'digital_file_ready_storage_type' => 'string',
    ];

    protected $appends = ['is_shop_temporary_close', 'thumbnail_full_url', 'preview_file_full_url', 'color_images_full_url', 'meta_image_full_url', 'images_full_url', 'digital_file_ready_full_url', 'has_active_supplier_mapping', 'is_direct_topup'];

    public function getHasActiveSupplierMappingAttribute(): bool
    {
        if ($this->relationLoaded('supplierMapping')) {
            $mapping = $this->getRelation('supplierMapping');

            return $mapping !== null && $mapping->is_active === true;
        }

        return \App\Models\SupplierProductMapping::where('product_id', $this->id)->where('is_active', 1)->exists();
    }

    public function supplierMapping(): HasOne
    {
        return $this->hasOne(SupplierProductMapping::class)->where('is_active', true)->orderBy('priority', 'asc');
    }

    public function translations(): MorphMany
    {
        return $this->morphMany('App\Models\Translation', 'translationable');
    }

    /**
     * Scope: only products that have available stock.
     *
     * For digital `ready_product` items the code pool drives stock (current_stock
     * is synced via DigitalProductCodeService::syncStock). Every other digital
     * sub-type is always considered in-stock from a listing perspective because
     * fulfilment does not depend on a pre-loaded code pool.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $stockQuery) {
            $stockQuery->where('product_type', 'digital')
                ->orWhere('current_stock', '>', 0);
        });
    }

    /**
     * Convenience collection-level helper that mirrors scopeInStock for use
     * when you already have a hydrated Eloquent collection (not a query builder).
     */
    public static function isInStockItem(self $product): bool
    {
        return $product->product_type === 'digital' || $product->current_stock > 0;
    }

    public function scopeActive($query)
    {
        $brandSetting = getWebConfig(name: 'product_brand');
        $digitalProductSetting = getWebConfig(name: 'digital_product');
        $businessMode = getWebConfig(name: 'business_mode');
        $productType = $digitalProductSetting ? ['digital', 'physical'] : ['physical'];

        return $query->when($businessMode == 'single', function ($query) {
            $query->where(['added_by' => 'admin']);
        })
            ->when($brandSetting, function ($query) use ($productType) {
                if (! in_array('digital', $productType)) {
                    $query->whereHas('brand', function ($query) {
                        $query->where('status', 1);
                    })->orWhere(function ($query) {
                        $query->whereNull('brand_id')->where('status', 1);
                    });
                }
            })
            ->when(! $brandSetting, function ($query) {
                $query->whereNull('brand_id')->where('status', 1);
            })
            ->where(['status' => 1])
            ->where(['request_status' => 1])
            ->SellerApproved()
            ->whereIn('product_type', $productType);
    }

    public function scopeSellerApproved($query): void
    {
        $query->whereHas('seller', function ($query) {
            $query->where(['status' => 'approved']);
        })->orWhere(function ($query) {
            $query->where(['added_by' => 'admin', 'status' => 1]);
        });
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function clearanceSale(): HasOne
    {
        return $this->hasOne(StockClearanceProduct::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    // old relation: reviews_by_customer
    public function reviewsByCustomer(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id')->where('customer_id', auth('customer')->id())->whereNotNull('product_id')->whereNull('delivery_man_id');
    }

    public function digitalProductAuthors(): HasMany
    {
        return $this->hasMany(DigitalProductAuthor::class, 'product_id');
    }

    public function digitalProductPublishingHouse(): HasMany
    {
        return $this->hasMany(DigitalProductPublishingHouse::class, 'product_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function refundRequest(): HasMany
    {
        return $this->hasMany(RefundRequest::class, 'product_id', 'id');
    }

    public function scopeStatus($query): Builder
    {
        return $query->where('featured_status', 1);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'user_id');
    }

    public function locationCountry(): BelongsTo
    {
        return $this->belongsTo(LocationCountry::class, 'location_country_id');
    }

    public function locationCity(): BelongsTo
    {
        return $this->belongsTo(LocationCity::class, 'location_city_id');
    }

    public function locationArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class, 'location_area_id');
    }

    public function pendingCityRequest(): BelongsTo
    {
        return $this->belongsTo(CityRequest::class, 'pending_city_request_id');
    }

    public function pendingAreaRequest(): BelongsTo
    {
        return $this->belongsTo(AreaRequest::class, 'pending_area_request_id');
    }

    public function getIsShopTemporaryCloseAttribute($value): int
    {
        $inHouseTemporaryClose = Cache::get(IN_HOUSE_SHOP_TEMPORARY_CLOSE_STATUS) ?? 0;
        if ($this->added_by == 'admin') {
            return $inHouseTemporaryClose ?? 0;
        } elseif ($this->added_by == 'seller' && $this->user_id) {
            return Cache::remember('seller-shop-close-'.$this->user_id, 3600, function () {
                return \App\Models\Shop::where('seller_id', $this->user_id)->value('temporary_close') ?? 0;
            });
        }

        return 0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // old relation: sub_category
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    // old relation: sub_sub_category
    public function subSubCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_sub_category_id');
    }

    public function rating(): HasMany
    {
        return $this->hasMany(Review::class)
            ->select(DB::raw('avg(rating) average, product_id'))
            ->whereNull('delivery_man_id')
            ->groupBy('product_id');
    }

    // old relation: order_details
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id');
    }

    public function seoInfo(): BelongsTo
    {
        return $this->belongsTo(ProductSeo::class, 'id', 'product_id');
    }

    // old relation: order_delivered
    public function orderDelivered(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id')
            ->where('delivery_status', 'delivered');
    }

    // old relation: wish_list
    public function wishList(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'product_id');
    }

    public function digitalVariation(): HasMany
    {
        return $this->hasMany(DigitalProductVariation::class, 'product_id');
    }

    public function digitalProductCodes(): HasMany
    {
        return $this->hasMany(DigitalProductCode::class, 'product_id');
    }

    public function availableDigitalProductCodes(): HasMany
    {
        return $this->hasMany(DigitalProductCode::class, 'product_id')
            ->where('status', 'available')
            ->where('is_active', true);
    }

    public function tags(): BelongsToMany
    {
        if (strpos(url()->current(), '/api')) {
            return $this->belongsToMany(Tag::class)->limit(5);
        }

        return $this->belongsToMany(Tag::class);
    }

    public function taxVats(): MorphMany
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    // old relation: flash_deal_product
    public function flashDealProducts(): HasMany
    {
        return $this->hasMany(FlashDealProduct::class);
    }

    public function scopeFlashDeal($query, $flashDealID)
    {
        return $query->whereHas('flashDealProducts.flashDeal', function ($query) use ($flashDealID) {
            return $query->where('id', $flashDealID);
        });
    }

    // old relation: compare_list
    public function compareList(): HasMany
    {
        return $this->hasMany(ProductCompare::class);
    }

    public function getNameAttribute($name): ?string
    {
        $segment = request()->segment(1);
        if ($segment === 'api') {
            return $this->translations[0]->value ?? $name;
        }
        if (in_array($segment, ['admin', 'vendor', 'seller'], true)) {
            return $name;
        }

        return $this->translations[0]->value ?? $name;
    }

    public function getDetailsAttribute($detail): ?string
    {
        $segment = request()->segment(1);
        if ($segment === 'api') {
            return $this->translations[1]->value ?? $detail;
        }
        if (in_array($segment, ['admin', 'vendor', 'seller'], true)) {
            return $detail;
        }

        return $this->translations[1]->value ?? $detail;
    }

    public function getThumbnailFullUrlAttribute(): string|null|array
    {
        $value = $this->thumbnail;

        return $this->storageLink('product/thumbnail', $value, $this->thumbnail_storage_type ?? 'public');
    }

    public function getPreviewFileFullUrlAttribute(): string|null|array
    {
        $value = $this->preview_file;

        return $this->storageLink('product/preview', $value, $this->preview_file_storage_type ?? 'public');
    }

    public function getMetaImageFullUrlAttribute(): array
    {
        $value = $this->meta_image;

        return $this->storageLink('product/meta', $value, 'public');
    }

    public function getDigitalFileReadyFullUrlAttribute(): array
    {
        $value = $this->digital_file_ready;

        return $this->storageLink('product/digital-product', $value, $this->digital_file_ready_storage_type ?? 'public');
    }

    public function getColorImagesFullUrlAttribute(): array
    {
        $images = [];
        $value = is_array($this->color_image) ? $this->color_image : json_decode($this->color_image);
        if ($value) {
            foreach ($value as $item) {
                $item = (array) $item;
                $images[] = [
                    'color' => $item['color'],
                    'image_name' => $this->storageLink('product', $item['image_name'], $item['storage'] ?? 'public'),
                ];
            }
        }

        return $images;
    }

    public function getImagesFullUrlAttribute(): array
    {
        $images = [];
        $value = is_array($this->images) ? $this->images : json_decode($this->images);
        if ($value) {
            foreach ($value as $item) {
                $item = isset($item->image_name) ? (array) $item : ['image_name' => $item, 'storage' => 'public'];
                $images[] = $this->storageLink('product', $item['image_name'], $item['storage'] ?? 'public');
            }
        }

        return $images;
    }

    /**
     * Return the effective sell price for this product.
     *
     * When the product has an active supplier mapping, the price is determined
     * by the supplier's API price + markup. Otherwise, the product's local
     * unit_price is used as a fallback.
     *
     * This is the centralized single source of truth for product pricing
     * regardless of supplier driver — every supplier benefits from it.
     */
    public function getUnitPriceAttribute($value): float
    {
        if ($this->relationLoaded('supplierMapping')) {
            $mapping = $this->getRelation('supplierMapping');

            if ($mapping !== null && $mapping->is_active === true) {
                $sellPrice = $mapping->getStartingDisplayPrice();

                if ($sellPrice > 0) {
                    return $sellPrice;
                }
            }
        }

        return (float) $value;
    }

    public function getIsDirectTopupAttribute(): bool
    {
        if ($this->relationLoaded('supplierMapping')) {
            $mapping = $this->getRelation('supplierMapping');

            if ($mapping === null || ! $mapping->is_active || ! (bool) $mapping->is_direct_topup) {
                return false;
            }

            if (! (bool) ($mapping->supplierApi?->is_active ?? false)) {
                return false;
            }

            return (bool) ($mapping->supplierApi?->supports_direct_top_up ?? false)
                || trim((string) ($mapping->direct_topup_account_label ?? '')) !== '';
        }

        return false;
    }

    public function getLocalUnitPrice(): float
    {
        return (float) ($this->attributes['unit_price'] ?? 0);
    }

    public function getEffectiveSellPrice(): float
    {
        $mapping = SupplierProductMapping::query()
            ->where('product_id', $this->id)
            ->active()
            ->byPriority()
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($mapping) {
            $price = $mapping->getStartingDisplayPrice();

            if ($price > 0) {
                return $price;
            }
        }

        return (float) $this->attributes['unit_price'];
    }

    /**
     * Return the effective cost/price from the supplier for this product.
     * Falls back to the product's purchase_price when no mapping exists.
     */
    public function getEffectiveCostPrice(): float
    {
        $mapping = SupplierProductMapping::query()
            ->where('product_id', $this->id)
            ->active()
            ->byPriority()
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($mapping) {
            return (float) $mapping->cost_price;
        }

        return (float) ($this->attributes['purchase_price'] ?? 0);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            cacheRemoveByType(type: 'products');
        });

        static::deleted(function ($model) {
            cacheRemoveByType(type: 'products');
        });

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                if (strpos(url()->current(), '/api')) {
                    return $query->where('locale', App::getLocale());
                } else {
                    return $query->where('locale', getDefaultLanguage());
                }
            }, 'reviews' => function ($query) {
                $segment = request()->segment(1);
                $query->whereNull('delivery_man_id')->when(! in_array($segment, ['admin', 'vendor', 'seller'], true), function ($query) {
                    return $query->active();
                });
            }]);
        });
    }
}
