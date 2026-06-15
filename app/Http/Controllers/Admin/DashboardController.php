<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\AdminWalletRepositoryInterface;
use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Contracts\Repositories\ChattingRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\OrderTransactionRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\RestockProductRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Repositories\VendorWalletRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Models\CustomerWallet;
use App\Models\Product;
use App\Models\Seller;
use App\Services\DashboardService;
use App\Services\Supplier\SupplierManager;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly AdminWalletRepositoryInterface $adminWalletRepo,
        private readonly CustomerRepositoryInterface $customerRepo,
        private readonly OrderTransactionRepositoryInterface $orderTransactionRepo,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly DeliveryManRepositoryInterface $deliveryManRepo,
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly BrandRepositoryInterface $brandRepo,
        private readonly VendorRepositoryInterface $vendorRepo,
        private readonly VendorWalletRepositoryInterface $vendorWalletRepo,
        private readonly RestockProductRepositoryInterface $restockProductRepo,
        private readonly DashboardService $dashboardService,
        private readonly ChattingRepositoryInterface $chattingRepo,
        private readonly SupplierManager $supplierManager,
    ) {}

    /**
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     *                                                                             Index function is the starting point of a controller
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $mostRatedProducts = $this->productRepo->getTopRatedList(filters: ['added_by' => 'admin'])->take(DASHBOARD_DATA_LIMIT);
        $topSellProduct = $this->productRepo->getTopSellList(filters: ['added_by' => 'in_house'], relations: ['orderDetails', 'refundRequest'])->take(DASHBOARD_TOP_SELL_DATA_LIMIT);
        $vendorMostRatedProducts = $this->productRepo->getTopRatedList(filters: ['added_by' => 'seller', 'request_status' => 1])->take(DASHBOARD_DATA_LIMIT);
        $vendorTopSellProduct = $this->productRepo->getTopSellList(filters: ['added_by' => 'seller', 'request_status' => 1], relations: ['orderDetails', 'refundRequest'])->take(DASHBOARD_TOP_SELL_DATA_LIMIT);

        $topCustomer = $this->customerRepo->getListWhereBetween(
            filters: [
                'is_active' => 1,
                'sort_by' => 'order_amount',
                'avoid_walking_customer' => 1,
            ],
            relations: ['orders'],
            dataLimit: DASHBOARD_DATA_LIMIT,
        );
        $topRatedDeliveryMan = $this->deliveryManRepo->getTopRatedList(filters: ['seller_id' => 0, 'sort_by' => 'rating'], relations: ['deliveredOrders', 'rating', 'review'], dataLimit: 'all')->take(DASHBOARD_DATA_LIMIT);
        $topVendorByEarning = $this->vendorWalletRepo->getListWhere(orderBy: ['total_earning' => 'desc'], filters: [['column' => 'total_earning', 'operator' => '>', 'value' => 0]], relations: ['seller.shop'])->take(DASHBOARD_DATA_LIMIT);
        $topVendorListByWishlist = $this->vendorRepo->getTopVendorListByWishlist(relations: ['shop'], dataLimit: 'all')->take(DASHBOARD_DATA_LIMIT);

        if (empty(session('statistics_type'))) {
            session()->put('statistics_type', 'this_year');
        }

        $data = self::getOrderStatusData();
        $admin_wallet = $this->adminWalletRepo->getFirstWhere(params: ['admin_id' => 1]);

        $from = now()->startOfYear()->format('Y-m-d');
        $to = now()->endOfYear()->format('Y-m-d');
        $range = range(1, 12);
        $label = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $inHouseOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: 'month', userType: 'admin');
        $vendorOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: 'month', userType: 'seller');
        $inHouseEarning = $this->getEarning(from: $from, to: $to, range: $range, type: 'month', userType: 'admin');
        $vendorEarning = $this->getEarning(from: $from, to: $to, range: $range, type: 'month', userType: 'seller');
        $commissionEarn = $this->getAdminCommission(from: $from, to: $to, range: $range, type: 'month');
        $dateType = 'yearEarn';
        $getTotalCustomerCount = $this->customerRepo->getListWhereBetween(filters: ['avoid_walking_customer' => 1], dataLimit: 'all')->count();
        $data += [
            'order' => $this->orderRepo->getListWhere(dataLimit: 'all')->count(),
            'brand' => $this->brandRepo->getListWhere(dataLimit: 'all')->count(),
            'topSellProduct' => $topSellProduct,
            'mostRatedProducts' => $mostRatedProducts,
            'topVendorByEarning' => $topVendorByEarning,
            'vendorTopSellProduct' => $vendorTopSellProduct,
            'vendorMostRatedProducts' => $vendorMostRatedProducts,
            'top_customer' => $topCustomer,
            'topVendorListByWishlist' => $topVendorListByWishlist,
            'topRatedDeliveryMan' => $topRatedDeliveryMan,
            'inhouse_earning' => $admin_wallet['inhouse_earning'] ?? 0,
            'commission_earned' => $admin_wallet['commission_earned'] ?? 0,
            'delivery_charge_earned' => $admin_wallet['delivery_charge_earned'] ?? 0,
            'pending_amount' => $admin_wallet['pending_amount'] ?? 0,
            'total_tax_collected' => $admin_wallet['total_tax_collected'] ?? 0,
            'getTotalCustomerCount' => $getTotalCustomerCount,
            'getTotalVendorCount' => $this->vendorRepo->getListWhere(dataLimit: 'all')->count(),
            'getTotalDeliveryManCount' => $this->deliveryManRepo->getListWhere(filters: ['seller_id' => 0], dataLimit: 'all')->count(),
        ];

        // Total Inventory Stock Value per Vendor (active, approved products only)
        $vendorInventory = Product::select('user_id', DB::raw('SUM(current_stock * unit_price) as inventory_value'))
            ->where('added_by', 'seller')
            ->where('status', 1)
            ->where('request_status', 1)
            ->groupBy('user_id')
            ->pluck('inventory_value', 'user_id');

        $vendorInventoryDetails = [];
        if ($vendorInventory->isNotEmpty()) {
            $sellers = Seller::with('shop')->whereIn('id', $vendorInventory->keys())->get()->keyBy('id');
            foreach ($vendorInventory as $sellerId => $value) {
                if (isset($sellers[$sellerId])) {
                    $vendorInventoryDetails[] = [
                        'seller' => $sellers[$sellerId],
                        'inventory_value' => (float) $value,
                    ];
                }
            }
            // Sort descending by inventory value
            usort($vendorInventoryDetails, fn ($a, $b) => $b['inventory_value'] <=> $a['inventory_value']);
        }

        // Total Available Users' Wallet Balance
        $totalUsersWalletBalance = (float) CustomerWallet::sum('balance');

        $supplierBalances = $this->supplierManager->getSupplierBalances();

        return view('admin-views.system.dashboard', compact(
            'data', 'inHouseEarning', 'vendorEarning', 'commissionEarn',
            'inHouseOrderEarningArray', 'vendorOrderEarningArray', 'label', 'dateType',
            'supplierBalances', 'vendorInventoryDetails', 'totalUsersWalletBalance'
        ));
    }

    public function getOrderStatus(Request $request)
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::getOrderStatusData();

        return response()->json(['view' => view('admin-views.partials._dashboard-order-status', compact('data'))->render()], 200);
    }

    public function getOrderStatusData(): array
    {
        $orderQuery = $this->orderRepo->getListWhere(dataLimit: 'all');
        $storeQuery = $this->vendorRepo->getListWhere(dataLimit: 'all');
        $productQuery = $this->productRepo->getListWhere(dataLimit: 'all');
        $customerQuery = $this->customerRepo->getListWhere(filters: ['avoid_walking_customer' => 1], dataLimit: 'all');
        $failedQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'failed'], dataLimit: 'all');
        $pendingQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'pending'], dataLimit: 'all');
        $returnedQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'returned'], dataLimit: 'all');
        $canceledQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'canceled'], dataLimit: 'all');
        $confirmedQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'confirmed'], dataLimit: 'all');
        $deliveredQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'delivered'], dataLimit: 'all');
        $processingQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'processing'], dataLimit: 'all');
        $outForDeliveryQuery = $this->orderRepo->getListWhere(filters: ['order_status' => 'out_for_delivery'], dataLimit: 'all');

        return [
            'order' => self::getCommonQueryOrderStatus($orderQuery),
            'store' => self::getCommonQueryOrderStatus($storeQuery),
            'failed' => self::getCommonQueryOrderStatus($failedQuery),
            'pending' => self::getCommonQueryOrderStatus($pendingQuery),
            'product' => self::getCommonQueryOrderStatus($productQuery),
            'customer' => self::getCommonQueryOrderStatus($customerQuery),
            'returned' => self::getCommonQueryOrderStatus($returnedQuery),
            'canceled' => self::getCommonQueryOrderStatus($canceledQuery),
            'confirmed' => self::getCommonQueryOrderStatus($confirmedQuery),
            'delivered' => self::getCommonQueryOrderStatus($deliveredQuery),
            'processing' => self::getCommonQueryOrderStatus($processingQuery),
            'out_for_delivery' => self::getCommonQueryOrderStatus($outForDeliveryQuery),
        ];
    }

    public function getCommonQueryOrderStatus($query)
    {
        $today = session()->has('statistics_type') && session('statistics_type') == 'today' ? 1 : 0;
        $this_week = session()->has('statistics_type') && session('statistics_type') == 'this_week' ? 1 : 0;
        $this_month = session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 1 : 0;
        $this_year = session()->has('statistics_type') && session('statistics_type') == 'this_year' ? 1 : 0;

        return $query
            ->when($today, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
            })
            ->when($this_week, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            })
            ->when($this_month, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            })
            ->when($this_year, function ($query) {
                return $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
            })
            ->count();
    }

    public function getOrderStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorOrderEarningArray = $this->getOrderStatisticsData(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseOrderEarningArray = array_values($inHouseOrderEarningArray);
        $vendorOrderEarningArray = array_values($vendorOrderEarningArray);

        return response()->json([
            'view' => view('admin-views.system.partials.order-statistics', compact('inHouseOrderEarningArray', 'vendorOrderEarningArray', 'label', 'dateType'))->render(),
        ]);
    }

    public function getEarningStatistics(Request $request): JsonResponse
    {
        $dateType = $request['type'];
        $dateTypeArray = $this->dashboardService->getDateTypeData(dateType: $dateType);
        $from = $dateTypeArray['from'];
        $to = $dateTypeArray['to'];
        $type = $dateTypeArray['type'];
        $range = $dateTypeArray['range'];
        $inHouseEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'admin');
        $vendorEarning = $this->getEarning(from: $from, to: $to, range: $range, type: $type, userType: 'seller');
        $commissionEarn = $this->getAdminCommission(from: $from, to: $to, range: $range, type: $type);
        $label = $dateTypeArray['keyRange'] ?? [];
        $inHouseEarning = array_values($inHouseEarning);
        $vendorEarning = array_values($vendorEarning);
        $commissionEarn = array_values($commissionEarn);

        return response()->json([
            'view' => view('admin-views.system.partials.earning-statistics', compact('inHouseEarning', 'vendorEarning', 'commissionEarn', 'label', 'dateType'))->render(),
        ]);
    }

    protected function getOrderStatisticsData($from, $to, $range, $type, $userType): array
    {
        $orderEarnings = $this->orderRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'payment_status' => 'paid',
            ],
            selectColumn: 'order_amount',
            whereBetween: 'created_at',
            whereBetweenFilters: [$from, $to],
        );
        $orderEarningArray = [];
        foreach ($range as $value) {
            $matchingEarnings = $orderEarnings->where($type, $value);
            if ($matchingEarnings->count() > 0) {
                $orderEarningArray[$value] = usdToDefaultCurrency($matchingEarnings->sum('sums'));
            } else {
                $orderEarningArray[$value] = 0;
            }
        }

        return $orderEarningArray;
    }

    protected function getEarning(string|Carbon $from, string|Carbon $to, array $range, string $type, $userType): array
    {
        $earning = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => $userType,
                'status' => 'disburse',
            ],
            selectColumn: 'seller_amount',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );

        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $earning);
    }

    protected function getAdminCommission(string|Carbon $from, string|Carbon $to, array $range, string $type): array
    {
        $commissionGiven = $this->orderTransactionRepo->getListWhereBetween(
            filters: [
                'seller_is' => 'seller',
                'status' => 'disburse',
            ],
            selectColumn: 'admin_commission',
            whereBetween: 'created_at',
            groupBy: $type,
            whereBetweenFilters: [$from, $to],
        );

        return $this->dashboardService->getDateWiseAmount(range: $range, type: $type, amountArray: $commissionGiven);
    }

    public function getRealTimeActivities(): JsonResponse
    {
        $newOrder = $this->orderRepo->getListWhere(filters: ['checked' => 0], dataLimit: 'all')->count();
        $newDigitalOrder = $this->orderRepo->getListWhere(filters: ['checked' => 0, 'order_type' => 'digital'], dataLimit: 'all')->count();
        $newPhysicalOrder = $newOrder - $newDigitalOrder;
        $restockProductList = $this->restockProductRepo->getListWhere(filters: ['added_by' => 'in_house'], dataLimit: 'all')->groupBy('product_id');
        $restockProduct = [];
        if (count($restockProductList) == 1) {
            $products = $this->restockProductRepo->getListWhere(orderBy: ['updated_at' => 'desc'], filters: ['added_by' => 'in_house'], relations: ['product'], dataLimit: 'all');
            $firstProduct = $products->first();
            $count = $products?->sum('restock_product_customers_count') ?? 0;
            $restockProduct = [
                'title' => $firstProduct?->product?->name ?? '',
                'body' => $count < 100 ? translate('This_product_has').' '.$count.' '.translate('restock_request') : translate('This_product_has').' 99+ '.translate('restock_request'),
                'image' => getStorageImages(path: $firstProduct?->product?->thumbnail_full_url ?? '', type: 'product'),
                'route' => route('admin.products.request-restock-list'),
            ];
        } elseif (count($restockProductList) > 1) {
            $restockProduct = [
                'title' => translate('Restock_Request'),
                'body' => count($restockProductList) < 100 ? (count($restockProductList).' '.translate('products_have_restock_request')) : ('99 +'.' '.translate('more_products_have_restock_request')),
                'image' => dynamicAsset(path: 'public/assets/back-end/img/icons/restock-request-icon.svg'),
                'route' => route('admin.products.request-restock-list'),
            ];
        }
        $chatting = $this->chattingRepo->getListWhereNotNull(
            filters: ['admin_id' => 0, 'seen_by_admin' => 0, 'notification_receiver' => 'admin', 'seen_notification' => 0],
            whereNotNull: ['admin_id'],
        )->count();

        $this->chattingRepo->updateListWhereNotNull(
            filters: ['admin_id' => 0, 'seen_by_admin' => 0, 'notification_receiver' => 'admin', 'seen_notification' => 0],
            whereNotNull: ['admin_id'],
            data: ['seen_notification' => 1]
        );

        $pendingOrderCount = \App\Models\Order::where('order_status', 'pending')->count();
        $confirmedOrderCount = \App\Models\Order::where('order_status', 'confirmed')->count();
        $processingOrderCount = \App\Models\Order::where('order_status', 'processing')->count();
        $outForDeliveryOrderCount = \App\Models\Order::where('order_status', 'out_for_delivery')->count();
        $deliveredOrderCount = \App\Models\Order::where('order_status', 'delivered')->count();
        $canceledOrderCount = \App\Models\Order::where('order_status', 'canceled')->count();
        $returnedOrderCount = \App\Models\Order::where('order_status', 'returned')->count();
        $failedOrderCount = \App\Models\Order::where('order_status', 'failed')->count();
        $allOrderCount = \App\Models\Order::count();
        $unreadChatCount = \App\Models\Chatting::where('seen_by_admin', 0)
            ->whereRaw('(sent_by_customer = 1 OR sent_by_seller = 1 OR sent_by_delivery_man = 1)')
            ->count();
        $unreadContactCount = \App\Models\Contact::where('seen', 0)->count();

        $pendingLocationRequestCount = \App\Models\CityRequest::where('status', 'pending')->count()
            + \App\Models\AreaRequest::where('status', 'pending')->count();

        return response()->json([
            'success' => 1,
            'new_order_count' => $newOrder,
            'new_digital_order_count' => $newDigitalOrder,
            'new_physical_order_count' => $newPhysicalOrder,
            'restockProductCount' => $restockProductList->count(),
            'restockProduct' => $restockProduct,
            'newMessagesExist' => $chatting,
            'message' => $chatting > 1 ? $chatting.' '.translate('New_Message') : translate('New_Message'),
            'pending_order_count' => $pendingOrderCount,
            'confirmed_order_count' => $confirmedOrderCount,
            'processing_order_count' => $processingOrderCount,
            'out_for_delivery_order_count' => $outForDeliveryOrderCount,
            'delivered_order_count' => $deliveredOrderCount,
            'canceled_order_count' => $canceledOrderCount,
            'returned_order_count' => $returnedOrderCount,
            'failed_order_count' => $failedOrderCount,
            'all_order_count' => $allOrderCount,
            'unread_chat_count' => $unreadChatCount,
            'unread_contact_count' => $unreadContactCount,
            'pending_product_count' => getVendorProductsCount('new-product'),
            'open_support_ticket_count' => \App\Models\SupportTicket::where('status', 'open')->count(),
            'pending_location_request_count' => $pendingLocationRequestCount,
        ]);
    }
}
