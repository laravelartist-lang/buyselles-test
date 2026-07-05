<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class CartItem
 *
 * @property int $id Primary
 * @property int $customer_id
 * @property string $cart_group_id
 * @property int $product_id
 * @property string $product_type
 * @property string $digital_product_type
 * @property string $color
 * @property array $choices
 * @property array $variations
 * @property array $variant
 * @property int $quantity
 * @property float $price
 * @property float|null $custom_amount
 * @property string|null $direct_topup_account_id
 * @property float|null $direct_topup_quantity
 * @property float $tax
 * @property int $is_checked
 * @property float $discount
 * @property string $tax_model
 * @property string $slug
 * @property string $name
 * @property string $thumbnail
 * @property int $seller_id
 * @property string $seller_is
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $shop_info
 * @property float $shipping_cost
 * @property string $shipping_type
 * @property int $is_guest
 */
class Cart extends Model
{
    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'product_id' => 'integer',
        //        'choices' => 'array',
        //        'variations' => 'array',
        //        'variant' => 'array',
        'quantity' => 'integer',
        'price' => 'float',
        'custom_amount' => 'float',
        'direct_topup_account_id' => 'encrypted',
        'direct_topup_quantity' => 'float',
        'tax' => 'float',
        'is_checked' => 'integer',
        'discount' => 'float',
        'seller_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'shipping_cost' => 'float',
        'is_guest' => 'integer',
    ];

    protected $fillable = [
        'customer_id',
        'cart_group_id',
        'product_id',
        'product_type',
        'digital_product_type',
        'color',
        'choices',
        'variations',
        'variant',
        'quantity',
        'price',
        'custom_amount',
        'direct_topup_account_id',
        'direct_topup_quantity',
        'tax',
        'discount',
        'tax_model',
        'is_checked',
        'slug',
        'name',
        'thumbnail',
        'seller_id',
        'seller_is',
        'shop_info',
        'shipping_cost',
        'shipping_type',
        'is_guest',
    ];

    public function cartShipping(): HasOne
    {
        return $this->hasOne(CartShipping::class, 'cart_group_id', 'cart_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->where('status', 1);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'seller_id', 'seller_id');
    }

    public function allProducts(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isDirectTopUp(): bool
    {
        return $this->direct_topup_quantity !== null;
    }

    public function getDisplayQuantity(): float
    {
        if ($this->isDirectTopUp()) {
            return (float) $this->direct_topup_quantity;
        }

        return (float) $this->quantity;
    }

    public function getGrossPrice(): float
    {
        if (! $this->isDirectTopUp()) {
            return (float) $this->price;
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->relationLoaded('allProducts') ? $this->allProducts : $this->product()->first());

        if (! $product) {
            return (float) $this->price;
        }

        return app(\App\Services\DirectTopUp\DirectTopUpService::class)->calculateTotalPrice(
            $product,
            (float) $this->direct_topup_quantity
        );
    }

    public function getTotalDiscountAmount(): float
    {
        if ($this->isDirectTopUp()) {
            $product = $this->relationLoaded('product')
                ? $this->product
                : ($this->relationLoaded('allProducts') ? $this->allProducts : $this->product()->first());

            if ($product) {
                return getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $this->getGrossPrice());
            }
        }

        return (float) $this->discount * (float) $this->quantity;
    }

    public function getLineTotal(): float
    {
        if ($this->isDirectTopUp()) {
            return $this->getGrossPrice() - $this->getTotalDiscountAmount();
        }

        return ((float) $this->price - (float) $this->discount) * (float) $this->quantity;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            cacheRemoveByType(type: 'carts');
        });

        static::deleted(function ($model) {
            cacheRemoveByType(type: 'carts');
        });
    }
}
