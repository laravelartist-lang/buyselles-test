<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashDeal;
use App\Models\PublishingHouse;
use App\Models\RobotsMetaContent;
use App\Models\StockClearanceSetup;
use App\Services\CategoryDisplayBlockWebService;
use App\Utils\BrandManager;
use App\Utils\CategoryManager;
use App\Utils\ProductManager;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ProductListController extends Controller
{
    public function __construct(
        private readonly CategoryDisplayBlockWebService $categoryDisplayBlockWebService,
    ) {}

    public function products(Request $request)
    {
        $pageTitle = translate('Products');
        if ($request->has('publishing_house_id')) {
            $pageTitle = PublishingHouse::firstWhere('id', $request['publishing_house_id'])?->name.' '.translate('Products');
        }
        if ($request->has('author_id')) {
            $pageTitle = Author::firstWhere('id', $request['author_id'])?->name.' '.translate('Products');
        }

        return match (theme_root_path()) {
            'default' => self::default_theme(request: $request, pageType: 'default', pageTitle: $pageTitle),
            'theme_aster' => self::theme_aster(request: $request, pageType: 'default', pageTitle: $pageTitle),
        };
    }

    public function getBrandProductsView(Request $request, $slug)
    {
        $dataForm = 'brand';
        $brand = Brand::active()->where('slug', $slug)->with(['seo'])->first();
        if (! $brand) {
            Toastr::warning(translate('brand_not_found'));

            return back();
        }

        $request->merge(['data_from' => $dataForm]);
        $request->merge(['brand_id' => $brand['id']]);

        return self::getProductsListPage(
            request: $request,
            pageType: $dataForm,
            pageTitle: ucwords(str_replace(['-', '_'], ' ', $brand['name'])).' '.translate('products'),
            metaData: $brand?->seo
        );
    }

    public function getCategoryProductsView(Request $request, $slug)
    {
        $cacheKey = 'category_slug_'.$slug.'_'.(getDefaultLanguage() ?? 'en');
        $cacheKeys = Cache::get(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, []);
        if (! in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
        }

        $category = Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () use ($slug) {
            return Category::where('slug', $slug)->with(['seo'])->first();
        });
        if (! $category) {
            Toastr::warning(translate('category_not_found'));

            return back();
        }

        $dataForm = 'category';
        $request->merge(['data_from' => $dataForm]);

        if ($category['position'] == 0) {
            return $this->getDynamicCategoryView($request, $category);
        }

        $subCategories = collect();

        if ($category['position'] == 0) {
            $cacheKey = 'category_childes_'.$category['id'].'_'.(getDefaultLanguage() ?? 'en');
            $cacheKeys = Cache::get(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, []);
            if (! in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
            }
            $subCategories = Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () use ($category) {
                return $category->childes()->orderBy('priority')->get();
            });
            $request->merge(['category_id' => $category['id']]);
        } elseif ($category['position'] == 1) {
            $cacheKey = 'category_childes_'.$category['id'].'_'.(getDefaultLanguage() ?? 'en');
            $cacheKeys = Cache::get(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, []);
            if (! in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
            }
            $subCategories = Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () use ($category) {
                return $category->childes()->orderBy('priority')->get();
            });
            $request->merge(['sub_category_id' => $category['id']]);
        } elseif ($category['position'] == 2) {
            $request->merge(['sub_sub_category_id' => $category['id']]);
        }

        $request->merge(['_sub_categories' => $subCategories]);

        return self::getProductsListPage(
            request: $request,
            pageType: $dataForm,
            pageTitle: ucwords(str_replace(['-', '_'], ' ', $category['name'])).' '.translate('products'),
            metaData: $category?->seo
        );
    }

    public function getFeaturedProductsView(Request $request)
    {
        $request->merge(['data_from' => 'featured']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'featured',
            pageTitle: translate('Featured_Products'),
            metaData: RobotsMetaContent::where('page_name', 'featured-products')->first()
        );
    }

    public function getFeaturedDealProductsView(Request $request)
    {
        $featuredDeal = FlashDeal::where(['deal_type' => 'feature_deal', 'status' => 1])->whereDate('start_date', '<=', date('Y-m-d'))
            ->whereDate('end_date', '>=', date('Y-m-d'))->with(['seo'])->first();
        $request->merge(['offer_type' => 'featured_deal']);

        return self::getProductsListPage(
            request: $request,
            offerType: 'featured_deal',
            pageTitle: translate('Featured_Deal_Products'),
            metaData: $featuredDeal?->seo
        );
    }

    public function getLatestProductsView(Request $request)
    {
        $request->merge(['data_from' => 'latest']);

        if (! $request->has('category_id') && ! $request->has('sub_category_id') && ! $request->has('sub_sub_category_id')) {
            $cacheKey = 'top_level_categories_'.(getDefaultLanguage() ?? 'en');
            $cacheKeys = Cache::get(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, []);
            if (! in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put(CACHE_CONTAINER_FOR_LANGUAGE_WISE_CACHE_KEYS, $cacheKeys, CACHE_FOR_3_HOURS);
            }
            $request->merge(['_sub_categories' => Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () {
                return Category::where('position', 0)->orderBy('priority')->get();
            })]);
        }

        return self::getProductsListPage(
            request: $request,
            pageType: 'latest',
            pageTitle: translate('Latest_Products'),
            metaData: RobotsMetaContent::where('page_name', 'latest-products')->first()
        );
    }

    public function getBestSellingProductsView(Request $request)
    {
        $request->merge(['data_from' => 'best-selling']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'best-selling',
            pageTitle: translate('Best_Selling_Products'),
            metaData: RobotsMetaContent::where('page_name', 'best-selling-products')->first()
        );
    }

    public function getTopRatedProductsView(Request $request)
    {
        $request->merge(['data_from' => 'top-rated']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'top-rated',
            pageTitle: translate('Top_Rated_Products'),
            metaData: RobotsMetaContent::where('page_name', 'top-rated-products')->first()
        );
    }

    public function getMostFavoriteProductsView(Request $request)
    {
        $request->merge(['data_from' => 'most-favorite']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'most-favorite',
            pageTitle: translate('Most_Favorite_Products'),
            metaData: RobotsMetaContent::where('page_name', 'most-favorite-products')->first()
        );
    }

    public function getDiscountedProductsView(Request $request)
    {
        $request->merge(['offer_type' => 'discounted']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'discounted',
            pageTitle: translate('Discounted_Products'),
            metaData: RobotsMetaContent::where('page_name', 'discounted-products')->first()
        );
    }

    public function getClearanceSaleProductsView(Request $request)
    {
        $clearanceConfig = StockClearanceSetup::where(['setup_by' => 'admin'])->with(['seo'])->first();
        $request->merge(['offer_type' => 'clearance_sale']);

        return self::getProductsListPage(
            request: $request,
            pageType: 'clearance_sale',
            pageTitle: translate('Clearance_Sale_Products'),
            metaData: $clearanceConfig?->seo
        );
    }

    protected function getDynamicCategoryView(Request $request, Category $category): View|RedirectResponse
    {
        $step = max(0, $request->integer('step', 0));
        $direction = $request->string('direction', 'next');
        $context = [];

        if ($request->filled('parent_id')) {
            $context['parent_id'] = $request->integer('parent_id');
        }
        if ($request->filled('parent_name')) {
            $context['parent_name'] = $request->string('parent_name');
        }
        if ($request->filled('vendor_id')) {
            $context['vendor_id'] = $request->integer('vendor_id');
        }
        if ($request->filled('vendor_name')) {
            $context['vendor_name'] = $request->string('vendor_name');
        }

        $blocks = $this->categoryDisplayBlockWebService->getActiveBlocks($category->id);
        $hasNoContent = $blocks->isEmpty();

        if (! $hasNoContent && ! $request->has('step')) {
            if (isset($context['parent_id'])) {
                $step = $this->categoryDisplayBlockWebService->resolveStepAfterCategorySelection(
                    $category->id,
                    (int) $context['parent_id'],
                );
            } elseif (isset($context['vendor_id'])) {
                $step = $this->categoryDisplayBlockWebService->resolveStepAfterVendorSelection($category->id);
            }
        }

        if (! $hasNoContent && $step === 0 && $direction !== 'back' && ! isset($context['parent_id']) && ! isset($context['vendor_id']) && ! $request->has('page')) {
            $initialStep = $this->categoryDisplayBlockWebService->resolveInitialStep($blocks, $category, $context);

            if ($initialStep['shouldExitToCategories']) {
                $hasNoContent = true;
            } else {
                $step = $initialStep['stepIndex'] ?? 0;
            }
        }

        if (! $hasNoContent) {
            $request->merge(array_filter([
                'step' => $step,
                'parent_id' => $context['parent_id'] ?? null,
                'parent_name' => $context['parent_name'] ?? null,
                'vendor_id' => $context['vendor_id'] ?? null,
                'vendor_name' => $context['vendor_name'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''));
        }

        $stepData = $hasNoContent
            ? [
                'block' => null,
                'stepIndex' => 0,
                'hasNext' => false,
                'hasPrev' => false,
                'totalSteps' => $blocks->count(),
                'displayStepNumber' => 0,
                'displayTotalSteps' => 0,
                'dataBlockIndices' => [],
                'previousStepIndex' => null,
                'nextStepIndex' => null,
                'backContext' => [],
                'title' => '',
                'data' => [],
            ]
            : $this->categoryDisplayBlockWebService->getActiveBlockForStep($category, $step, $request, $context);

        if (! $hasNoContent && $stepData['block'] === null) {
            $hasNoContent = true;
        }

        $pageTitle = ucwords(str_replace(['-', '_'], ' ', $category['name']));
        if (! empty($context['vendor_name'])) {
            $pageTitle = $category->name.' — '.$context['vendor_name'];
        } elseif (! empty($context['parent_name'])) {
            $pageTitle = $category->name.' — '.$context['parent_name'];
        }

        $breadcrumbs = $this->buildStepBreadcrumbs($category, $context);

        return view(VIEW_FILE_NAMES['dynamic_category_page'], [
            'category' => $category,
            'currentBlock' => $stepData['block'],
            'currentStepData' => $stepData,
            'currentStepIndex' => $stepData['stepIndex'],
            'hasNext' => $stepData['hasNext'],
            'hasPrev' => $stepData['hasPrev'],
            'totalSteps' => $stepData['totalSteps'],
            'displayStepNumber' => $stepData['displayStepNumber'],
            'displayTotalSteps' => $stepData['displayTotalSteps'],
            'dataBlockIndices' => $stepData['dataBlockIndices'],
            'previousStepIndex' => $stepData['previousStepIndex'],
            'nextStepIndex' => $stepData['nextStepIndex'],
            'backContext' => $stepData['backContext'],
            'hasNoContent' => $hasNoContent,
            'context' => $context,
            'breadcrumbs' => $breadcrumbs,
            'pageTitleContent' => $pageTitle,
            'robotsMetaContentData' => $category->seo,
            'themeKey' => theme_root_path(),
        ]);
    }

    /**
     * @param  array{parent_id?: int, parent_name?: string}  $context
     * @return array<int, array{label: string, url: string|null}>
     */
    private function buildStepBreadcrumbs(Category $category, array $context): array
    {
        $breadcrumbs = [
            ['label' => translate('Home'), 'url' => route('home')],
            ['label' => translate('categories'), 'url' => route('categories')],
            ['label' => $category->name, 'url' => null],
        ];

        if (! empty($context['vendor_name'])) {
            $breadcrumbs[] = ['label' => $context['vendor_name'], 'url' => null];
        }

        if (! empty($context['parent_name'])) {
            $breadcrumbs[] = ['label' => $context['parent_name'], 'url' => null];
        }

        return $breadcrumbs;
    }

    public function getProductsListPage(object|array $request, string $pageType = 'default', string $offerType = '', string $pageTitle = '', object|array|null $metaData = null)
    {
        return match (theme_root_path()) {
            'default' => self::default_theme(request: $request, pageType: $pageType, pageTitle: $pageTitle, metaData: $metaData),
            'theme_aster' => self::theme_aster(request: $request, pageType: $pageType, pageTitle: $pageTitle, metaData: $metaData),
        };
    }

    public function default_theme(object|array $request, string $pageType = 'default', string $pageTitle = '', object|array|null $metaData = null): View|JsonResponse|Redirector|RedirectResponse
    {
        if ($request->has('min_price') && $request['min_price'] != '' && $request->has('max_price') && $request['max_price'] != '' && $request['min_price'] > $request['max_price']) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'message' => translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'),
                ]);
            }
            Toastr::error(translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'));
            redirect()->back();
        }

        $categories = CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting();
        $activeBrands = BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting();

        $data = self::getProductListRequestData(request: $request);
        $productListData = ProductManager::getProductListData(request: $request);
        $products = $productListData->paginate(20)->appends($data);

        if ($request->ajax()) {
            return response()->json([
                'total_product' => $products->total(),
                'html_products' => view('web-views.products._ajax-products', compact('products'))->render(),
            ], 200);
        }

        $subCategories = $request['_sub_categories'] ?? collect();

        return view(VIEW_FILE_NAMES['products_view_page'], [
            'pageTitleContent' => $pageTitle ?? translate('products'),
            'products' => $products,
            'data' => $data,
            'activeBrands' => $activeBrands,
            'categories' => $categories,
            'robotsMetaContentData' => $metaData,
            'subCategories' => $subCategories,
        ]);
    }

    public function theme_aster(object|array $request, string $pageType = 'default', string $pageTitle = '', object|array|null $metaData = null): View|JsonResponse|Redirector|RedirectResponse
    {
        if ($request->has('min_price') && $request['min_price'] != '' && $request->has('max_price') && $request['max_price'] != '' && $request['min_price'] > $request['max_price']) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'message' => translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'),
                ]);
            }
            Toastr::error(translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'));
            redirect()->back();
        }

        $categories = CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting();
        $activeBrands = BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting();
        $singlePageProductCount = 20;

        $data = self::getProductListRequestData(request: $request);
        $productListData = ProductManager::getProductListData(request: $request);
        $ratings = self::getProductsRatingOneToFiveAsArray(productQuery: $productListData);
        $products = $productListData->paginate(20)->appends($data);
        $getProductIds = $products->pluck('id')->toArray();

        $category = $request['category_ids'] ? Category::whereIn('id', $request['category_ids'])->get() : [];
        $brands = $request['brand_ids'] ? Brand::whereIn('id', $request['brand_ids'])->get() : [];
        $publishingHouse = $request['publishing_house_ids'] ? PublishingHouse::whereIn('id', $request['publishing_house_ids'])->select('id', 'name')->get() : [];
        $productAuthors = $request['author_ids'] ? Author::whereIn('id', $request['author_ids'])->select('id', 'name')->get() : [];
        $selectedRatings = $request['rating'] ?? [];

        if ($request->ajax()) {
            return response()->json([
                'total_product' => $products->total(),
                'html_products' => view(VIEW_FILE_NAMES['products__ajax_partials'], [
                    'products' => $products,
                    'product_ids' => $getProductIds,
                    'singlePageProductCount' => $singlePageProductCount,
                    'page' => $request['page'] ?? 1,
                ])->render(),
                'html_tags' => view('theme-views.product._selected_filter_tags', [
                    'tags_category' => $category,
                    'tags_brands' => $brands,
                    'selectedRatings' => $selectedRatings,
                    'publishingHouse' => $publishingHouse,
                    'productAuthors' => $productAuthors,
                    'sort_by' => $request['sort_by'],
                ])->render(),
            ], 200);
        }

        return view(VIEW_FILE_NAMES['products_view_page'], [
            'pageTitleContent' => $pageTitle ?? translate('Products'),
            'products' => $products,
            'data' => $data,
            'ratings' => $ratings,
            'selectedRatings' => $selectedRatings,
            'product_ids' => $getProductIds,
            'activeBrands' => $activeBrands,
            'categories' => $categories,
            'singlePageProductCount' => $singlePageProductCount,
            'page' => $request['page'] ?? 1,
            'tags_category' => $category,
            'tags_brands' => $brands,
            'publishingHouse' => $publishingHouse,
            'productAuthors' => $productAuthors,
            'sort_by' => $request['sort_by'],
            'robotsMetaContentData' => $metaData,
            'subCategories' => $request['_sub_categories'] ?? collect(),
        ]);
    }

    public function getPageSelectedDataByType(Request $request, string $type)
    {
        $resultArray = [];
        if ($type == 'tag' && $request->has('category_ids') && ! empty($request['category_ids'])) {
            $resultArray = Category::whereIn('id', $request['category_ids'])->select('id', 'name')->get();
        }

        if ($type == 'publishing_house' && $request->has('publishing_house_id') && ! empty($request['publishing_house_id'])) {
            $resultArray = PublishingHouse::where('id', $request['publishing_house_id'])->select('id', 'name')->get();
        }

        if ($type == 'author' && $request->has('author_id') && ! empty($request['author_id'])) {
            $resultArray = Author::where('id', $request['author_id'])->select('id', 'name')->get();
        }

        if ($type == 'brand' && $request['data_from'] == 'brand') {
            $resultArray = Brand::where('id', $request['brand_id'])->select('id', 'name')->get();
        }

        return $resultArray;
    }

    public function getProductsRatingOneToFiveAsArray($productQuery): array
    {
        $rating_1 = 0;
        $rating_2 = 0;
        $rating_3 = 0;
        $rating_4 = 0;
        $rating_5 = 0;

        foreach ($productQuery as $rating) {
            if (isset($rating->rating[0]['average']) && ($rating->rating[0]['average'] > 0 && $rating->rating[0]['average'] < 2)) {
                $rating_1 += 1;
            } elseif (isset($rating->rating[0]['average']) && ($rating->rating[0]['average'] >= 2 && $rating->rating[0]['average'] < 3)) {
                $rating_2 += 1;
            } elseif (isset($rating->rating[0]['average']) && ($rating->rating[0]['average'] >= 3 && $rating->rating[0]['average'] < 4)) {
                $rating_3 += 1;
            } elseif (isset($rating->rating[0]['average']) && ($rating->rating[0]['average'] >= 4 && $rating->rating[0]['average'] < 5)) {
                $rating_4 += 1;
            } elseif (isset($rating->rating[0]['average']) && ($rating->rating[0]['average'] == 5)) {
                $rating_5 += 1;
            }
        }

        return [
            'rating_1' => $rating_1,
            'rating_2' => $rating_2,
            'rating_3' => $rating_3,
            'rating_4' => $rating_4,
            'rating_5' => $rating_5,
        ];
    }

    public static function getProductListRequestData($request): array
    {
        if ($request->has('product_view') && in_array($request['product_view'], ['grid-view', 'list-view'])) {
            session()->put('product_view_style', $request['product_view']);
        }

        return [
            'id' => $request['id'],
            'name' => $request['name'],
            'brand_id' => $request['brand_id'],
            'category_id' => $request['category_id'],
            'sub_category_id' => $request['sub_category_id'],
            'sub_sub_category_id' => $request['sub_sub_category_id'],
            'data_from' => $request['data_from'],
            'offer_type' => $request['offer_type'],
            'sort_by' => $request['sort_by'],
            'page_no' => $request['page'],
            'min_price' => $request['min_price'],
            'max_price' => $request['max_price'],
            'product_type' => $request['product_type'],
            'shop_id' => $request['shop_id'],
            'author_id' => $request['author_id'],
            'publishing_house_id' => $request['publishing_house_id'],
            'search_category_value' => $request['search_category_value'],
            'product_name' => $request['product_name'],
            'area_id' => $request['area_id'],
            'city_id' => $request['city_id'],
            'country_id' => $request['country_id'],
            'page' => $request['page'] ?? 1,
        ];
    }

    public function getFlashDealsView(Request $request, $id): View|RedirectResponse|JsonResponse
    {
        $request->merge(['offer_type' => 'flash-deals']);
        $request->merge(['flash_deals_id' => $id]);

        if ($request->has('product_name') && $request['product_name'] != '') {
            $request->merge(['data_from' => 'search']);
            $request->merge(['search' => $request['product_name']]);
        }

        $singlePageProductCount = 20;
        $userId = Auth::guard('customer')->user() ? Auth::guard('customer')->id() : 0;
        $flashDeal = ProductManager::getPriorityWiseFlashDealsProductsQuery(id: $id, userId: $userId);

        if (! isset($flashDeal['flashDeal']) || $flashDeal['flashDeal'] == null) {
            Toastr::warning(translate('not_found'));

            return back();
        }

        $data = self::getProductListRequestData(request: $request);
        $categories = CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataForm: 'flash-deals');
        $activeBrands = BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting();

        $productListData = ProductManager::getProductListData(request: $request, type: 'flash-deals');
        $ratings = self::getProductsRatingOneToFiveAsArray(productQuery: $productListData);
        $products = $productListData->paginate(20)->appends($data);
        $getProductIds = $products->pluck('id')->toArray();

        if ($request['ratings'] != null) {
            $products = $products->map(function ($product) {
                $product->rating = $product->rating->pluck('average')[0];

                return $product;
            });
            $products = $products->where('rating', '>=', $request['ratings'])
                ->where('rating', '<', $request['ratings'] + 1)
                ->paginate(20)->appends($data);
        }

        $allProductsColorList = ProductManager::getProductsColorsArray();
        $tagCategory = $this->getPageSelectedDataByType(request: $request, type: 'tag');
        $tagPublishingHouse = $this->getPageSelectedDataByType(request: $request, type: 'publishing_house');
        $tagProductAuthors = $this->getPageSelectedDataByType(request: $request, type: 'author');
        $tagBrand = $this->getPageSelectedDataByType(request: $request, type: 'brand');
        $paginateCount = ceil($products->count() / $singlePageProductCount);

        if ($request->ajax()) {
            return response()->json([
                'total_product' => $products->total(),
                'html_products' => view(VIEW_FILE_NAMES['products__ajax_partials'], ['products' => $products, 'product_ids' => $getProductIds])->render(),
            ], 200);
        }

        $selectedRatings = $request['rating'] ?? [];

        return view(VIEW_FILE_NAMES['flash_deals'], [
            'pageTitleContent' => translate('Flash_Deal_Products'),
            'products' => $products,
            'paginate_count' => $paginateCount,
            'data' => $data,
            'ratings' => $ratings,
            'selectedRatings' => $selectedRatings,
            'product_ids' => $getProductIds,
            'activeBrands' => $activeBrands,
            'productCategories' => $categories,
            'allProductsColorList' => $allProductsColorList,
            'deal' => $flashDeal['flashDeal'],
            'tag_category' => $tagCategory,
            'tagPublishingHouse' => $tagPublishingHouse,
            'tagProductAuthors' => $tagProductAuthors,
            'tag_brand' => $tagBrand,
            'singlePageProductCount' => $singlePageProductCount,
            'robotsMetaContentData' => $flashDeal['flashDeal']?->seo,
        ]);
    }

    public function getFlashDealsProducts(Request $request): JsonResponse
    {
        if ($request->has('min_price') && $request['min_price'] != '' && $request->has('max_price') && $request['max_price'] != '' && $request['min_price'] > $request['max_price']) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => 0,
                    'message' => translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'),
                ]);
            }
            Toastr::error(translate('Minimum_price_should_be_less_than_or_equal_to_maximum_price.'));
            redirect()->back();
        }

        if ($request->has('product_name') && $request['product_name'] != '') {
            $request->merge(['data_from' => 'search']);
            $request->merge(['search' => $request['product_name']]);
        }

        $singlePageProductCount = 20;
        $productListData = ProductManager::getProductListData($request);

        $category = [];
        if ($request['category_ids']) {
            $category = Category::whereIn('id', $request['category_ids'])->get();
        }

        $brands = [];
        if ($request['brand_ids']) {
            $brands = Brand::whereIn('id', $request['brand_ids'])->get();
        }

        $publishingHouse = [];
        if ($request['publishing_house_ids']) {
            $publishingHouse = PublishingHouse::whereIn('id', $request['publishing_house_ids'])->select('id', 'name')->get();
        }

        $productAuthors = [];
        if ($request['author_ids']) {
            $productAuthors = Author::whereIn('id', $request['author_ids'])->select('id', 'name')->get();
        }

        $rating = $request->rating ?? [];
        $productsCount = $productListData->count();
        $paginateCount = ceil($productsCount / $singlePageProductCount);
        $currentPage = $offset ?? Paginator::resolveCurrentPage('page');
        $results = $productListData->forPage($currentPage, $singlePageProductCount);
        $products = new LengthAwarePaginator(items: $results, total: $productsCount, perPage: $singlePageProductCount, currentPage: $currentPage, options: [
            'path' => Paginator::resolveCurrentPath(),
            'appends' => $request->all(),
        ]);

        $data = [
            'id' => $request['id'],
            'name' => $request['name'],
            'data_from' => $request['data_from'],
            'sort_by' => $request['sort_by'],
            'page_no' => $request['page'],
            'min_price' => $request['min_price'],
            'max_price' => $request['max_price'],
            'product_type' => $request['product_type'],
            'search_category_value' => $request['search_category_value'],
        ];
        if ($request->has('shop_id')) {
            $data['shop_id'] = $request['shop_id'];
        }

        return response()->json([
            'html_products' => view('theme-views.product._ajax-products', [
                'products' => $products,
                'paginate_count' => $paginateCount,
                'page' => $request['page'] ?? 1,
                'request_data' => $request->all(),
                'singlePageProductCount' => $singlePageProductCount,
                'data' => $data,
            ])->render(),
            'html_tags' => view('theme-views.product._selected_filter_tags', [
                'tags_category' => $category,
                'tags_brands' => $brands,
                'rating' => $rating,
                'publishingHouse' => $publishingHouse,
                'productAuthors' => $productAuthors,
                'sort_by' => $request['sort_by'],
            ])->render(),
            'products_count' => $productsCount,
            'products' => $products,
            'singlePageProductCount' => $singlePageProductCount,
        ]);
    }
}
