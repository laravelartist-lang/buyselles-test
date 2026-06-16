<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Shop;
use App\Services\CategoryDisplayBlockWebService;
use App\Utils\CategoryManager;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function get_categories(Request $request): JsonResponse
    {
        $shop = $request->has('shop_slug') && ! empty($request['shop_slug'])
            ? Shop::where('slug', $request['shop_slug'])->first()
            : null;

        if ($shop) {
            [$productAddedBy, $productUserId] = CategoryManager::resolveShopVendorContext($shop);
        } elseif ($request->has('vendor_id')) {
            [$productAddedBy, $productUserId] = CategoryManager::resolveVendorContextFromVendorId((int) $request['vendor_id']);
        } else {
            $productAddedBy = 'admin';
            $productUserId = 0;
        }

        $hasVendorScope = $shop !== null || $request->has('vendor_id');

        $mainCategoryIds = $shop
            ? CategoryManager::getVendorMainCategoryIds($productAddedBy, $productUserId)
            : [];

        $parentId = $request->has('parent_id') ? (int) $request['parent_id'] : null;
        $parentCategory = $parentId ? Category::query()->find($parentId) : null;

        $categories = Category::query()
            ->when($shop && ! $parentId, function (Builder $query) use ($mainCategoryIds) {
                $query->whereIn('id', $mainCategoryIds)->where('position', 0);
            })
            ->when($hasVendorScope && $parentId, function (Builder $query) use ($parentId, $parentCategory, $productAddedBy, $productUserId) {
                $query->where('parent_id', $parentId);

                if ($parentCategory?->position === 0) {
                    $query->whereHas('subCategoryProduct', function (Builder $productQuery) use ($productAddedBy, $productUserId) {
                        CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                    });
                } elseif ($parentCategory?->position === 1) {
                    $query->whereHas('subSubCategoryProduct', function (Builder $productQuery) use ($productAddedBy, $productUserId) {
                        CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                    });
                }
            })
            ->when(! $hasVendorScope && $parentId, function (Builder $query) use ($parentId) {
                $query->where('parent_id', $parentId);
            })
            ->when(! $hasVendorScope && ! $parentId, function (Builder $query) {
                $query->where('position', 0);
            })
            ->with(['product' => function ($query) {
                return $query->active()->withCount(['orderDetails'])->latest()->limit(10);
            }])
            ->withCount(['product' => function ($query) use ($hasVendorScope, $productAddedBy, $productUserId) {
                $query->active()
                    ->when($hasVendorScope, function ($productQuery) use ($productAddedBy, $productUserId) {
                        CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                    });
            }])
            ->with(['childes' => function ($query) use ($hasVendorScope, $productAddedBy, $productUserId) {
                $query->orderBy('priority', 'asc')->where('position', 1);

                if ($hasVendorScope) {
                    $query->whereHas('subCategoryProduct', function (Builder $productQuery) use ($productAddedBy, $productUserId) {
                        CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                    });
                }

                $query->with(['childes' => function ($query) use ($hasVendorScope, $productAddedBy, $productUserId) {
                    $query->orderBy('priority', 'asc')->where('position', 2);

                    if ($hasVendorScope) {
                        $query->whereHas('subSubCategoryProduct', function (Builder $productQuery) use ($productAddedBy, $productUserId) {
                            CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                        });
                    }

                    $query->withCount(['subSubCategoryProduct' => function ($query) use ($hasVendorScope, $productAddedBy, $productUserId) {
                        $query->active()
                            ->when($hasVendorScope, function ($productQuery) use ($productAddedBy, $productUserId) {
                                CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                            });
                    }]);
                }])
                    ->withCount(['subCategoryProduct' => function ($query) use ($hasVendorScope, $productAddedBy, $productUserId) {
                        $query->active()
                            ->when($hasVendorScope, function ($productQuery) use ($productAddedBy, $productUserId) {
                                CategoryManager::applyVendorProductScope($productQuery, $productAddedBy, $productUserId);
                            });
                    }]);
            }])
            ->orderBy('priority', 'asc')
            ->get();

        $categories = CategoryManager::getPriorityWiseCategorySortQuery(query: $categories);

        return response()->json($categories->values());
    }

    public function get_products(Request $request, $id): JsonResponse
    {
        $dataLimit = $request['limit'] ?? 'all';
        $products = CategoryManager::products($id, $request, $dataLimit);
        $productFinal = Helpers::product_data_formatting($products, true);

        if ($dataLimit == 'all') {
            return response()->json($productFinal, 200);
        }

        return response()->json([
            'total_size' => $products->total(),
            'limit' => (int) $request['limit'],
            'offset' => (int) $request['offset'],
            'products' => $productFinal,
        ], 200);
    }

    public function getMixedProducts(Request $request, $id, CategoryDisplayBlockWebService $categoryDisplayBlockWebService): JsonResponse
    {
        $category = Category::query()
            ->whereKey($id)
            ->where('position', 0)
            ->firstOrFail();

        $limit = max(1, min((int) ($request['limit'] ?? CategoryDisplayBlockWebService::PREVIEW_LIMIT), 50));
        $context = [];
        if ($request->filled('vendor_id')) {
            $context['vendor_id'] = $request->integer('vendor_id');
        }
        $products = $categoryDisplayBlockWebService->getMixedProducts($category->id, $request, $limit, $context);
        $productFinal = Helpers::product_data_formatting($products->items(), true);

        return response()->json([
            'total_size' => $products->total(),
            'limit' => $limit,
            'offset' => (int) ($request['offset'] ?? 1),
            'products' => $productFinal,
        ], 200);
    }

    public function getGroupedProducts(Request $request, $id, CategoryDisplayBlockWebService $categoryDisplayBlockWebService): JsonResponse
    {
        $category = Category::query()->findOrFail($id);
        $groupLevel = (string) $request->input('group_level', 'sub_category');

        if (! in_array($groupLevel, ['sub_category', 'sub_sub_category'], true)) {
            return response()->json([
                'errors' => [
                    ['code' => 'group_level', 'message' => 'The group_level must be sub_category or sub_sub_category.'],
                ],
            ], 422);
        }

        $limit = max(1, min((int) ($request['limit'] ?? CategoryDisplayBlockWebService::PREVIEW_LIMIT), 50));
        $offset = max(1, (int) ($request['offset'] ?? $request['page'] ?? 1));

        $context = [];
        if ($request->filled('parent_id')) {
            $context['parent_id'] = $request->integer('parent_id');
        }
        if ($request->filled('vendor_id')) {
            $context['vendor_id'] = $request->integer('vendor_id');
        }

        $groupedProducts = $groupLevel === 'sub_sub_category'
            ? $categoryDisplayBlockWebService->getSubSubCategoryGroupedProducts($category, $request, $context, $limit)
            : $categoryDisplayBlockWebService->getSubCategoryGroupedProducts($category, $request, $context, $limit);

        $groups = [];
        foreach ($groupedProducts as $groupedProduct) {
            $products = $groupedProduct['products'];
            $items = $products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                ? $products->items()
                : $products;
            $formattedProducts = Helpers::product_data_formatting($items, true);

            $groupPayload = [
                'category_id' => $groupedProduct['category']->id,
                'category_name' => $groupedProduct['category']->name,
                'products' => $formattedProducts,
                'limit' => $limit,
                'offset' => $offset,
            ];

            if ($products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                $groupPayload['total_size'] = $products->total();
            } else {
                $groupPayload['total_size'] = count($formattedProducts);
            }

            $groups[] = $groupPayload;
        }

        return response()->json([
            'group_level' => $groupLevel,
            'main_category_id' => (int) $category->id,
            'parent_id' => $context['parent_id'] ?? null,
            'limit' => $limit,
            'offset' => $offset,
            'groups' => $groups,
        ]);
    }

    public function find_what_you_need(): JsonResponse
    {
        $find_what_you_need_categories = Category::where('position', 0)
            ->with(['childes' => function ($query) {
                $query->orderBy('priority', 'asc')
                    ->withCount(['subCategoryProduct' => function ($query) {
                        return $query->active();
                    }]);
            }])
            ->withCount(['product' => function ($query) {
                return $query->active();
            }])
            ->orderBy('priority', 'asc')
            ->get();

        $find_what_you_need_categories = CategoryManager::getPriorityWiseCategorySortQuery(query: $find_what_you_need_categories);

        $getCategories = [];
        foreach ($find_what_you_need_categories as $category) {
            $categoryArray = $category->toArray();
            $categoryArray['childes'] = array_slice($categoryArray['childes'], 0, 4);
            $getCategories[] = $categoryArray;
        }

        $final_category = [];
        foreach ($getCategories as $category) {
            if (count($category['childes']) > 0) {
                $final_category[] = $category;
            }
        }

        return response()->json(['find_what_you_need' => $final_category], 200);
    }

    public function getDisplayBlocks(string $id): JsonResponse
    {
        $category = Category::with('displayBlocks')->findOrFail($id);

        return response()->json([
            'category' => $category,
            'blocks' => $category->displayBlocks,
        ]);
    }
}
