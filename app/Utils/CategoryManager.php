<?php

namespace App\Utils;

use App\Models\Category;
use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryManager
{
    public static function parents(): Collection|array
    {
        return Category::with(['childes.childes'])->where('position', 0)->orderBy('priority', 'asc')->get();
    }

    public static function child($parent_id)
    {
        return Category::where(['parent_id' => $parent_id])->get();
    }

    public static function products($category_id, $request = null, $dataLimit = null)
    {
        $user = Helpers::getCustomerInformation($request);
        $products = Product::with(['flashDealProducts.flashDeal', 'rating', 'seller.shop', 'tags', 'clearanceSale' => function ($query) {
            return $query->active();
        }])
            ->withCount(['reviews', 'wishList' => function ($query) use ($user) {
                $query->where('customer_id', $user != 'offline' ? $user['id'] : '0');
            }])
            ->active();

        self::applyCategoryProductScope(
            query: $products,
            categoryId: (int) $category_id,
            filterBy: $request['filter_by'] ?? null,
        );

        $products->when($request->has('vendor_id'), function (Builder $query) use ($request) {
            self::applyVendorScopeFromVendorId($query, (int) $request['vendor_id']);
        });

        if (($request['filter_by'] ?? null) === 'mixed_all') {
            self::applyShopLocationFilter($products, $request);
        }

        $products->when($request->has('search') && ! empty($request['search']), function ($query) use ($request) {
            $searchKey = $request['search'];
            $productsIDArray = [];
            $searchProducts = ProductManager::search_products($request, $searchKey);
            if ($searchProducts['products'] == null || getDefaultLanguage() != 'en') {
                $searchProducts = ProductManager::translated_product_search(base64_encode($searchKey));
            }
            if ($searchProducts['products']) {
                foreach ($searchProducts['products'] as $product) {
                    $productsIDArray[] = $product->id;
                }
            }

            $searchName = str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $searchKey));

            return $query->when(! empty($productsIDArray), function ($query) use ($productsIDArray) {
                return $query->whereIn('id', $productsIDArray);
            })->when(empty($productsIDArray), function ($query) {
                return $query->whereIn('id', [0]);
            })->orderByRaw("CASE WHEN name LIKE '%{$searchName}%' THEN 1 ELSE 2 END, LOCATE('{$searchName}', name), name");
        });

        $products = ProductManager::getPriorityWiseCategoryWiseProductsQuery(query: $products, dataLimit: $dataLimit ?? 'all', offset: $request['offset'] ?? 1);

        $currentDate = date('Y-m-d H:i:s');
        $products?->map(function ($product) use ($currentDate) {
            $flashDealStatus = 0;
            $flashDealEndDate = 0;
            if (count($product->flashDealProducts) > 0) {
                $flashDeal = null;
                foreach ($product->flashDealProducts as $flashDealData) {
                    if ($flashDealData->flashDeal) {
                        $flashDeal = $flashDealData->flashDeal;
                    }
                }
                if ($flashDeal) {
                    $startDate = date('Y-m-d H:i:s', strtotime($flashDeal->start_date));
                    $endDate = date('Y-m-d H:i:s', strtotime($flashDeal->end_date));
                    $flashDealStatus = $flashDeal->status == 1 && (($currentDate >= $startDate) && ($currentDate <= $endDate)) ? 1 : 0;
                    $flashDealEndDate = $flashDeal->end_date;
                }
            }
            $product['flash_deal_status'] = $flashDealStatus;
            $product['flash_deal_end_date'] = $flashDealEndDate;

            return $product;
        });

        return $products;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applyCategoryProductScope(Builder $query, int $categoryId, ?string $filterBy = null): Builder
    {
        return match ($filterBy) {
            'mixed_all' => self::applyMainCategoryAllProductsScope($query, $categoryId),
            'sub_categories' => self::applyMainCategorySubCategoryProductsScope($query, $categoryId),
            'sub_sub_categories' => self::applyMainCategorySubSubCategoryProductsScope($query, $categoryId),
            'direct_sub_category' => $query->where('sub_category_id', $categoryId),
            'direct_sub_sub_category' => $query->where('sub_sub_category_id', $categoryId),
            default => $query->where('category_ids', 'like', '%"'.$categoryId.'"%'),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applyWithoutSubSubCategoryScope(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery) {
            $subQuery->whereNull('sub_sub_category_id')
                ->orWhere('sub_sub_category_id', 0)
                ->orWhere('sub_sub_category_id', '');
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applyVendorProductScope(Builder $query, string $productAddedBy, int $productUserId): Builder
    {
        return $query->active()
            ->when($productAddedBy === 'admin', function (Builder $subQuery) {
                return $subQuery->where('added_by', 'admin');
            })
            ->when($productAddedBy === 'seller', function (Builder $subQuery) use ($productUserId) {
                return $subQuery->where('added_by', 'seller')->where('user_id', $productUserId);
            });
    }

    /**
     * @return array{0: string, 1: int}
     */
    public static function resolveVendorContextFromVendorId(int $vendorId): array
    {
        if ($vendorId === 0) {
            return ['admin', 0];
        }

        return ['seller', $vendorId];
    }

    public static function applyVendorScopeFromVendorId(Builder $query, int $vendorId): Builder
    {
        [$productAddedBy, $productUserId] = self::resolveVendorContextFromVendorId($vendorId);

        return self::applyVendorProductScope($query, $productAddedBy, $productUserId);
    }

    /**
     * @return array{0: string, 1: int}
     */
    public static function resolveShopVendorContext(Shop $shop): array
    {
        return [
            $shop->author_type === 'admin' ? 'admin' : 'seller',
            (int) $shop->seller_id,
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function getVendorMainCategoryIds(string $productAddedBy, int $productUserId): array
    {
        $query = Product::query()->select('category_id');
        self::applyVendorProductScope($query, $productAddedBy, $productUserId);

        return $query->pluck('category_id')
            ->filter(fn ($id) => ! empty($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function applyMainCategoryAllProductsScope(Builder $query, int $mainCategoryId): Builder
    {
        $subCategoryIds = Category::query()
            ->where('parent_id', $mainCategoryId)
            ->where('position', 1)
            ->pluck('id');

        $subSubCategoryIds = Category::query()
            ->whereIn('parent_id', $subCategoryIds)
            ->where('position', 2)
            ->pluck('id');

        return $query->where(function (Builder $subQuery) use ($mainCategoryId, $subCategoryIds, $subSubCategoryIds) {
            $subQuery->where('category_id', $mainCategoryId)
                ->orWhere('category_ids', 'like', '%"'.$mainCategoryId.'"%');

            if ($subCategoryIds->isNotEmpty()) {
                $subQuery->orWhereIn('sub_category_id', $subCategoryIds);
            }

            if ($subSubCategoryIds->isNotEmpty()) {
                $subQuery->orWhereIn('sub_sub_category_id', $subSubCategoryIds);
            }
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applyShopLocationFilter(Builder $query, object|array $request): void
    {
        $countryId = $request['country_id'] ?? $request['location_country_id'] ?? null;
        $cityId = $request['city_id'] ?? $request['location_city_id'] ?? null;
        $areaId = $request['area_id'] ?? $request['location_area_id'] ?? null;

        if (empty($countryId) && empty($cityId) && empty($areaId)) {
            return;
        }

        $inhouseShop = getInHouseShopConfig();
        $inhouseMatchesLocation = self::inhouseShopMatchesLocation($inhouseShop, $countryId, $cityId, $areaId);

        $query->where(function (Builder $subQuery) use ($countryId, $cityId, $areaId, $inhouseMatchesLocation) {
            $subQuery->whereHas('shop', function (Builder $shopQuery) use ($countryId, $cityId, $areaId) {
                if (! empty($countryId) && $countryId !== 'global') {
                    $shopQuery->where('store_country_id', (int) $countryId);
                }
                if (! empty($cityId)) {
                    $shopQuery->where('store_city_id', (int) $cityId);
                }
                if (! empty($areaId)) {
                    $shopQuery->where('store_area_id', (int) $areaId);
                }
            })
                ->when($inhouseMatchesLocation, function (Builder $adminQuery) {
                    $adminQuery->orWhere('added_by', 'admin');
                })
                ->orWhere('product_type', 'digital')
                ->orWhereHas('category', function (Builder $categoryQuery) {
                    $categoryQuery->where('category_type', 'digital');
                });
        });
    }

    public static function inhouseShopMatchesLocation(object $shop, mixed $countryId, mixed $cityId, mixed $areaId): bool
    {
        if (! empty($countryId) && $countryId !== 'global' && (int) ($shop->store_country_id ?? 0) !== (int) $countryId) {
            return false;
        }

        if (! empty($cityId) && (int) ($shop->store_city_id ?? 0) !== (int) $cityId) {
            return false;
        }

        if (! empty($areaId) && (int) ($shop->store_area_id ?? 0) !== (int) $areaId) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function applyMainCategorySubCategoryProductsScope(Builder $query, int $mainCategoryId): Builder
    {
        $subCategoryIds = Category::query()
            ->where('parent_id', $mainCategoryId)
            ->where('position', 1)
            ->pluck('id');

        return $query
            ->where('category_id', $mainCategoryId)
            ->whereIn('sub_category_id', $subCategoryIds)
            ->where(fn (Builder $subQuery) => self::applyWithoutSubSubCategoryScope($subQuery));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function applyMainCategorySubSubCategoryProductsScope(Builder $query, int $mainCategoryId): Builder
    {
        $subCategoryIds = Category::query()
            ->where('parent_id', $mainCategoryId)
            ->where('position', 1)
            ->pluck('id');

        $subSubCategoryIds = Category::query()
            ->whereIn('parent_id', $subCategoryIds)
            ->where('position', 2)
            ->pluck('id');

        return $query
            ->where('category_id', $mainCategoryId)
            ->whereIn('sub_sub_category_id', $subSubCategoryIds);
    }

    public static function getCategoriesWithCountingAndPriorityWiseSorting($dataLimit = null, $dataForm = null)
    {
        $cacheKey = 'cache_main_categories_list_'.(getDefaultLanguage() ?? 'en').'_'.(request('offer_type') ?? 'default').'_'.($dataForm ?? 'default');
        $cacheKeys = Cache::get(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, []);

        if (! in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
        }

        $featuredDealProducts = [];
        if (request('offer_type') == 'featured_deal') {
            $featuredDealID = FlashDeal::where(['deal_type' => 'feature_deal', 'status' => 1])->whereDate('start_date', '<=', date('Y-m-d'))
                ->whereDate('end_date', '>=', date('Y-m-d'))->pluck('id')->first();
            $featuredDealProductIDs = $featuredDealID ? FlashDealProduct::where('flash_deal_id', $featuredDealID)->pluck('product_id')->toArray() : [];
            $featuredDealProducts = Product::whereIn('id', $featuredDealProductIDs)->get();
        }

        $categories = Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () use ($dataForm, $featuredDealProducts) {
            return Category::with(['product' => function ($query) {
                return $query->active()->withCount(['orderDetails'])->with(['clearanceSale' => function ($query) {
                    return $query->active();
                }]);
            }])
                ->when($dataForm == 'flash-deals', function ($query) {
                    return $query->whereHas('product.flashDealProducts.flashDeal');
                })
                ->withCount(['product' => function ($query) use ($dataForm, $featuredDealProducts) {
                    return $query->active()->when(request('offer_type') == 'clearance_sale', function ($query) {
                        return $query->whereHas('clearanceSale', function ($query) {
                            return $query->active();
                        });
                    })
                        ->when(request('offer_type') == 'discounted', function ($query) {
                            return $query->where('discount', '>', 0);
                        })
                        ->when(request('offer_type') == 'featured_deal', function ($query) use ($featuredDealProducts) {
                            return $query->whereIn('id', $featuredDealProducts?->pluck('id')?->toArray() ?? [0]);
                        })
                        ->when($dataForm == 'flash-deals', function ($query) {
                            return $query->whereHas('flashDealProducts.flashDeal');
                        });
                }])
                ->with(['childes' => function ($query) use ($dataForm, $featuredDealProducts) {
                    return $query->orderBy('priority', 'asc')
                        ->with(['childes' => function ($query) use ($featuredDealProducts) {
                            return $query->orderBy('priority', 'asc')
                                ->withCount(['subSubCategoryProduct' => function ($query) use ($featuredDealProducts) {
                                    return $query->active()->when(request('offer_type') == 'clearance_sale', function ($query) {
                                        return $query->whereHas('clearanceSale', function ($query) {
                                            return $query->active();
                                        });
                                    })
                                        ->when(request('offer_type') == 'discounted', function ($query) {
                                            return $query->where('discount', '>', 0);
                                        })
                                        ->when(request('offer_type') == 'featured_deal', function ($query) use ($featuredDealProducts) {
                                            return $query->whereIn('id', $featuredDealProducts?->pluck('id')?->toArray() ?? [0]);
                                        });
                                }])->where('position', 2);
                        }])->withCount(['subCategoryProduct' => function ($query) use ($dataForm, $featuredDealProducts) {
                            return $query->active()->when(request('offer_type') == 'clearance_sale', function ($query) {
                                return $query->whereHas('clearanceSale', function ($query) {
                                    return $query->active();
                                });
                            })
                                ->when(request('offer_type') == 'discounted', function ($query) {
                                    return $query->where('discount', '>', 0);
                                })
                                ->when(request('offer_type') == 'featured_deal', function ($query) use ($featuredDealProducts) {
                                    return $query->whereIn('id', $featuredDealProducts?->pluck('id')?->toArray() ?? [0]);
                                })
                                ->when($dataForm == 'flash-deals', function ($query) {
                                    return $query->whereHas('flashDealProducts.flashDeal');
                                });
                        }])
                        ->where('position', 1);
                }])->where('position', 0)->get();
        });

        $categoriesProcessed = self::getPriorityWiseCategorySortQuery(query: $categories);
        if ($dataLimit) {
            $categoriesProcessed = $categoriesProcessed->paginate($dataLimit);
        }

        return $categoriesProcessed;
    }

    public static function getPriorityWiseCategorySortQuery($query)
    {
        $categoryProductSortBy = getWebConfig(name: 'category_list_priority');
        $customSorting = $categoryProductSortBy && ($categoryProductSortBy['custom_sorting_status'] == 1);

        $sortCollection = function ($collection) use (&$sortCollection, $customSorting, $categoryProductSortBy) {
            // Sub-categories and sub-sub-categories (position > 0) always follow the
            // admin-defined priority regardless of the global custom-sort setting.
            // Custom sort only affects the top-level (position = 0) list.
            $isSubLevel = ($collection->first()?->position ?? 0) > 0;

            if ($customSorting && ! $isSubLevel) {
                if ($categoryProductSortBy['sort_by'] == 'most_order') {
                    $collection = $collection->map(function ($category) {
                        $category->order_count = $category?->product?->sum('order_details_count') ?? 0;

                        return $category;
                    })->sortByDesc('order_count');
                } elseif ($categoryProductSortBy['sort_by'] == 'latest_created') {
                    $collection = $collection->sortByDesc('id');
                } elseif ($categoryProductSortBy['sort_by'] == 'first_created') {
                    $collection = $collection->sortBy('id');
                } elseif ($categoryProductSortBy['sort_by'] == 'a_to_z') {
                    $collection = $collection->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
                } elseif ($categoryProductSortBy['sort_by'] == 'z_to_a') {
                    $collection = $collection->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE);
                }
            } else {
                // Sub-categories (all types) and top-level when custom sort is off:
                // always honour the admin-set priority field.
                $collection = $collection->sortBy('priority');
            }

            foreach ($collection as $item) {
                if ($item->relationLoaded('childes') && $item->childes) {
                    $item->setRelation('childes', $sortCollection($item->childes)->values());
                }
            }

            return $collection->values();
        };

        return $sortCollection($query);
    }
}
