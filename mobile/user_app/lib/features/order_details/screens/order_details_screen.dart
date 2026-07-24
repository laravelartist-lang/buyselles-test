import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_asset_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/checkout/controllers/checkout_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/home/shimmers/order_details_shimmer.dart';
import 'package:flutter_sixvalley_ecommerce/features/order/controllers/order_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/controllers/order_details_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/cal_chat_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/cancel_and_support_center_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/delivery_man_review_dialog_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/digital_codes_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/order_amount_calculation.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/order_details_status_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/order_payment_info_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/ordered_product_list_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/seller_section_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/widgets/shipping_and_billing_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/review/controllers/review_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/helper/order_note_helper.dart';
import 'package:flutter_sixvalley_ecommerce/helper/price_converter.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';
import 'package:provider/provider.dart';

class OrderDetailsScreen extends StatefulWidget {
  final bool isNotification;
  final int? orderId;
  final String? phone;
  final bool fromTrack;

  const OrderDetailsScreen({
    super.key,
    required this.orderId,
    this.isNotification = false,
    this.phone,
    this.fromTrack = false,
  });

  @override
  State<OrderDetailsScreen> createState() => _OrderDetailsScreenState();
}

class _OrderDetailsScreenState extends State<OrderDetailsScreen> {
  void _loadData(BuildContext context) async {
    try {
      if (Provider.of<AuthController>(context, listen: false).isLoggedIn() &&
          !widget.fromTrack) {
        await Provider.of<OrderDetailsController>(context, listen: false)
            .getOrderDetails(widget.orderId.toString());

        try {
          await Provider.of<OrderController>(context, listen: false)
              .initTrackingInfo(widget.orderId.toString());
        } catch (_) {}

        await Provider.of<OrderDetailsController>(context, listen: false)
            .getOrderFromOrderId(widget.orderId.toString());

        Provider.of<OrderDetailsController>(context, listen: false)
            .fetchDigitalCodes(widget.orderId.toString());
      } else {
        try {
          await Provider.of<OrderDetailsController>(context, listen: false)
              .trackOrder(
            orderId: widget.orderId.toString(),
            phoneNumber: widget.phone,
            isUpdate: false,
          );
        } catch (_) {}

        await Provider.of<OrderDetailsController>(context, listen: false)
            .getOrderFromOrderId(widget.orderId.toString());
      }

      try {
        Provider.of<CheckoutController>(context, listen: false)
            .getOfflinePaymentList();
        await Provider.of<OrderDetailsController>(context, listen: false)
            .getTrackOrderDetailsId(orderId: widget.orderId.toString());
      } catch (_) {}
    } catch (e, st) {
      debugPrint('_loadData unexpected error: $e\n$st');
    }
  }

  @override
  void initState() {
    super.initState();
    if (Provider.of<SplashController>(context, listen: false).configModel ==
        null) {
      Provider.of<SplashController>(context, listen: false)
          .initConfig(context, null, null)
          .then((_) {
        if (!mounted) {
          return;
        }
        _loadData(context);
        Provider.of<OrderDetailsController>(context, listen: false)
            .digitalOnly(true);
      });
    } else {
      _loadData(context);
      Provider.of<OrderDetailsController>(context, listen: false)
          .digitalOnly(true);
    }
  }

  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.wifi_off_outlined,
              size: 48,
              color: Theme.of(context).hintColor,
            ),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Text(
              getTranslated('something_went_wrong', context) ??
                  'Something went wrong',
              textAlign: TextAlign.center,
              style: robotoBold.copyWith(
                color: Theme.of(context).textTheme.bodyLarge?.color,
              ),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            TextButton(
              onPressed: () => _loadData(context),
              child: Text(getTranslated('retry', context) ?? 'Retry'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.receipt_long_outlined,
              size: 48,
              color: Theme.of(context).hintColor,
            ),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Text(
              getTranslated('no_data_found', context) ?? 'No data found',
              textAlign: TextAlign.center,
              style: robotoBold.copyWith(
                color: Theme.of(context).textTheme.bodyLarge?.color,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderNoteSection(
    BuildContext context,
    OrderDetailsController orderProvider,
  ) {
    if (orderProvider.orders?.orderType == 'POS') {
      return const SizedBox.shrink();
    }

    final rawNote = orderProvider.orders?.orderNote ?? '';
    final isFailed = orderProvider.orders?.orderStatus == 'failed';
    final display = OrderNoteHelper.parse(rawNote, isFailedOrder: isFailed);

    if (display.isEmpty) {
      return const SizedBox.shrink();
    }

    final sections = <Widget>[];

    if (display.failureMessage != null &&
        display.failureMessage!.trim().isNotEmpty) {
      sections.add(
        _orderNoteBlock(
          context,
          title: getTranslated('order_failure_reason', context) ??
              'What went wrong',
          body: display.failureMessage!,
          emphasize: true,
        ),
      );
    }

    if (display.customerNote != null &&
        display.customerNote!.trim().isNotEmpty) {
      if (sections.isNotEmpty) {
        sections.add(const SizedBox(height: Dimensions.paddingSizeSmall));
      }
      sections.add(
        _orderNoteBlock(
          context,
          title: getTranslated('order_note', context) ?? 'Additional Note',
          body: display.customerNote!,
        ),
      );
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).hintColor.withValues(alpha: 0.2),
            spreadRadius: 2,
            blurRadius: 8,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: sections,
      ),
    );
  }

  Widget _orderNoteBlock(
    BuildContext context, {
    required String title,
    required String body,
    bool emphasize = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: robotoBold.copyWith(
            color: emphasize
                ? Theme.of(context).colorScheme.error
                : Theme.of(context).textTheme.bodyLarge?.color,
          ),
        ),
        const SizedBox(height: Dimensions.paddingSizeExtraSmall),
        Text(
          body,
          style: titilliumRegular.copyWith(
            color: Theme.of(context).textTheme.bodyMedium?.color,
            height: 1.4,
          ),
        ),
      ],
    );
  }

  Widget _buildDigitalCodesSection(
    BuildContext context,
    OrderDetailsController orderProvider,
    bool hasDigitalProducts,
  ) {
    if (orderProvider.digitalCodesLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (orderProvider.digitalCodes != null &&
        orderProvider.digitalCodes!.isNotEmpty) {
      return DigitalCodesWidget(
        codes: orderProvider.digitalCodes!,
        onViewAll: widget.orderId == null
            ? null
            : () => RouterHelper.getDigitalProductDeliveryScreenRoute(
                  action: RouteAction.push,
                  orderId: widget.orderId!,
                ),
      );
    }

    if (!hasDigitalProducts) {
      return const SizedBox.shrink();
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).hintColor.withValues(alpha: 0.2),
            spreadRadius: 1.5,
            blurRadius: 3,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            getTranslated('digital_product_codes', context) ??
                'Digital Product Codes',
            style: titilliumBold.copyWith(
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeExtraSmall),
          Text(
            getTranslated('no_digital_codes_available', context) ??
                'No digital codes available for this order yet',
            style: titilliumRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliverySection(
    BuildContext context,
    OrderDetailsController orderProvider,
  ) {
    if (orderProvider.orders?.deliveryMan == null) {
      return const SizedBox.shrink();
    }

    return Container(
      margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).highlightColor,
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).hintColor.withValues(alpha: 0.2),
            spreadRadius: 2,
            blurRadius: 10,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '${getTranslated('shipping_info', context)}',
                style: robotoBold.copyWith(
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
              ),
              orderProvider.orders?.orderStatus == 'delivered'
                  ? InkWell(
                      onTap: () {
                        Provider.of<ReviewController>(context, listen: false)
                            .removeData();

                        showDialog(
                          context: context,
                          builder: (context) => Dialog(
                            insetPadding: EdgeInsets.zero,
                            backgroundColor: Colors.transparent,
                            child: DeliveryManReviewDialogWidget(
                              deliverymanAssignedAt:
                                  orderProvider.orders?.deliverymanAssignedAt,
                              existingDeliveryManReview: orderProvider
                                  .orderDetails?[0].order?.deliveryManReview,
                              deliveryMan: orderProvider.orders!.deliveryMan,
                              orderId: orderProvider.orders?.id.toString(),
                              callback: () => showCustomSnackBarWidget(
                                getTranslated('review_submitted_successfully',
                                        context) ??
                                    'Review submitted successfully',
                                context,
                                snackBarType: SnackBarType.success,
                              ),
                            ),
                          ),
                        );
                      },
                      child: Container(
                        padding:
                            const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(
                              Dimensions.paddingSizeSmall),
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                        child: Row(
                          children: [
                            const CustomAssetImageWidget(
                              Images.myReviewIconWhite,
                              height: 20,
                              width: 20,
                            ),
                            const SizedBox(
                                width: Dimensions.paddingSizeExtraSmall),
                            Text(
                              orderProvider.orderDetails?[0].order
                                          ?.deliveryManReview !=
                                      null
                                  ? '${getTranslated('update_review', context)}'
                                  : '${getTranslated('review', context)}',
                              style: textBold.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color:
                                    Theme.of(context).scaffoldBackgroundColor,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  : CallAndChatWidget(
                      orderProvider: orderProvider,
                      orderModel: orderProvider.orders,
                    ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius:
                    BorderRadius.circular(Dimensions.paddingSizeLarge),
                child: FadeInImage.assetNetwork(
                  placeholder: Images.placeholder,
                  fit: BoxFit.scaleDown,
                  width: Dimensions.paddingSizeButton,
                  height: Dimensions.paddingSizeButton,
                  image:
                      '${orderProvider.orders!.deliveryMan?.imageFullUrl?.path}',
                  imageErrorBuilder: (c, o, s) => Image.asset(
                    Images.placeholder,
                    fit: BoxFit.cover,
                    width: Dimensions.paddingSizeButton,
                    height: Dimensions.paddingSizeButton,
                  ),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeDefault),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${orderProvider.orders?.deliveryMan?.fName ?? ''} ${orderProvider.orders?.deliveryMan?.lName ?? ''}'
                          .trim(),
                      style: titilliumRegular.copyWith(
                        fontSize: Dimensions.fontSizeDefault,
                        color: Theme.of(context).textTheme.bodyLarge?.color,
                      ),
                    ),
                    if ((orderProvider.orders?.deliveryMan?.phone ?? '')
                        .isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(
                            top: Dimensions.paddingSizeExtraSmall),
                        child: Text(
                          orderProvider.orders?.deliveryMan?.phone ?? '',
                          style: textRegular.copyWith(
                            color: Theme.of(context).hintColor,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildDisputeActions(
    BuildContext context,
    bool hasActiveDispute,
    int? activeDisputeId,
    bool canOpenDispute,
    int orderId,
  ) {
    if (!hasActiveDispute && !canOpenDispute) {
      return const SizedBox.shrink();
    }

    return Column(
      children: [
        if (hasActiveDispute)
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeEight,
            ),
            child: OutlinedButton.icon(
              onPressed: () {
                RouterHelper.getDisputeDetailRoute(
                  disputeId: activeDisputeId!,
                  action: RouteAction.push,
                );
              },
              icon: const Icon(Icons.gavel_outlined, size: 18),
              label: Text(
                getTranslated('view_dispute', context) ?? 'View Dispute',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.orange,
                side: const BorderSide(color: Colors.orange),
                minimumSize: const Size(double.infinity, 44),
                shape: RoundedRectangleBorder(
                  borderRadius:
                      BorderRadius.circular(Dimensions.paddingSizeEight),
                ),
              ),
            ),
          ),
        if (canOpenDispute)
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeEight,
            ),
            child: OutlinedButton.icon(
              onPressed: () {
                RouterHelper.getOpenDisputeRoute(
                  orderId: orderId,
                  action: RouteAction.push,
                );
              },
              icon: const Icon(Icons.gavel_outlined, size: 18),
              label: Text(
                getTranslated('open_dispute', context) ?? 'Open Dispute',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.orange,
                side: const BorderSide(color: Colors.orange),
                minimumSize: const Size(double.infinity, 44),
                shape: RoundedRectangleBorder(
                  borderRadius:
                      BorderRadius.circular(Dimensions.paddingSizeEight),
                ),
              ),
            ),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: Navigator.canPop(context),
      onPopInvokedWithResult: (didPop, result) async {
        Provider.of<OrderDetailsController>(context, listen: false)
            .emptyOrderDetails();
        if (widget.isNotification) {
          RouterHelper.getDashboardRoute(
              action: RouteAction.pushReplacement, page: 'home');
        }
      },
      child: Scaffold(
        appBar: AppBar(
          flexibleSpace: Material(
            color: Theme.of(context).cardColor,
            elevation: 3.0,
            shadowColor: Theme.of(context).cardColor,
            child: Container(),
          ),
          elevation: 0,
          backgroundColor: Theme.of(context).highlightColor,
          toolbarHeight: 80,
          leadingWidth: 0,
          automaticallyImplyLeading: false,
          title: Consumer<OrderDetailsController>(
            builder: (context, orderProvider, _) {
              return (orderProvider.orderDetails != null &&
                      orderProvider.orders != null)
                  ? OrderDetailsStatusWidget(
                      isNotification: widget.isNotification,
                    )
                  : const SizedBox();
            },
          ),
        ),
        body: RefreshIndicator(
          onRefresh: () async {
            _loadData(context);
          },
          child: Consumer<SplashController>(
            builder: (context, config, _) {
              if (config.configModel == null) {
                return const OrderDetailsShimmer();
              }

              return Consumer<OrderDetailsController>(
                builder: (context, orderProvider, child) {
                  double itemTotalAmount = 0;
                  double discount = 0;
                  double? eeDiscount = 0;
                  double tax = 0;
                  double shippingCost = 0;
                  double serviceFee = 0;
                  double referAndEarnDiscount = 0;

                  if (orderProvider.orderDetails != null &&
                      orderProvider.orderDetails!.isNotEmpty) {
                    shippingCost =
                        orderProvider.orderDetails?[0].order?.isShippingFree ==
                                1
                            ? 0
                            : (orderProvider.orders?.shippingCost ?? 0);

                    for (final orderDetails in orderProvider.orderDetails!) {
                      if (orderDetails.productDetails?.productType !=
                          'physical') {
                        orderProvider.digitalOnly(false, isUpdate: false);
                      }

                      itemTotalAmount = itemTotalAmount +
                          (orderDetails.price! * orderDetails.qty!);
                      discount = discount + orderDetails.discount!;
                    }

                    if (orderProvider.orders != null &&
                        orderProvider.orders!.orderType == 'POS') {
                      if (orderProvider.orders!.extraDiscountType ==
                          'percent') {
                        eeDiscount = (itemTotalAmount -
                                discount -
                                (orderProvider.orders!.discountAmount ?? 0)) *
                            ((orderProvider.orders!.extraDiscount)! / 100);
                      } else {
                        eeDiscount = orderProvider.orders!.extraDiscount;
                      }
                    }

                    if (orderProvider.orders?.orderType != 'POS') {
                      referAndEarnDiscount = orderProvider
                              .orderDetails?[0].order?.referAndEarnDiscount ??
                          0;
                    }

                    tax = orderProvider.orders?.totalTaxAmount ?? 0;
                    serviceFee = orderProvider.orders?.customerServiceFee ?? 0;
                  }

                  final bool isLoggedIn =
                      Provider.of<AuthController>(context, listen: false)
                          .isLoggedIn();
                  final bool hasDigitalProducts =
                      orderProvider.orderDetails?.any((orderDetails) {
                            return orderDetails.productDetails?.productType !=
                                'physical';
                          }) ??
                          false;
                  final String orderStatus =
                      (orderProvider.orders?.orderStatus ?? '').toLowerCase();
                  final bool isDeliveredOrder = orderStatus == 'delivered';
                  final int? activeDisputeId =
                      orderProvider.orders?.escrow?.disputeId ??
                          orderProvider.orders?.activeDispute?.id;
                  final bool hasActiveDispute = activeDisputeId != null;
                  final bool canOpenDispute = isLoggedIn &&
                      isDeliveredOrder &&
                      !hasActiveDispute &&
                      orderProvider.orders?.id != null;

                  if (orderProvider.hasLoadError &&
                      orderProvider.orders == null) {
                    return _buildErrorState(context);
                  }

                  if (orderProvider.orderDetails != null &&
                      orderProvider.orders != null &&
                      orderProvider.orderDetails!.isEmpty) {
                    return _buildEmptyState(context);
                  }

                  if (orderProvider.orderDetails == null ||
                      orderProvider.orders == null) {
                    return const OrderDetailsShimmer();
                  }

                  return SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Column(
                      children: [
                        const OrderPaymentInfoWidget(),
                        Container(
                          height: Dimensions.fontSizeSmall,
                          color: Theme.of(context)
                              .primaryColor
                              .withValues(alpha: 0.05),
                        ),
                        if (orderProvider.orders!.orderType != 'POS')
                          ShippingAndBillingWidget(
                              orderProvider: orderProvider),
                        _buildOrderNoteSection(context, orderProvider),
                        if (orderProvider.orders!.orderType != 'POS')
                          const SizedBox(height: Dimensions.paddingSizeSmall),
                        SellerSectionWidget(order: orderProvider),
                        OrderProductListWidget(
                          orderType: orderProvider.orders!.orderType,
                          fromTrack: widget.fromTrack,
                          isGuest: orderProvider.orders!.isGuest!,
                          orderId: orderProvider.orders!.id.toString(),
                        ),
                        const SizedBox(height: Dimensions.paddingSizeSmall),
                        _buildDigitalCodesSection(
                          context,
                          orderProvider,
                          hasDigitalProducts,
                        ),
                        OrderAmountCalculation(
                          orderProvider: orderProvider,
                          itemTotalAmount: itemTotalAmount,
                          discount: discount,
                          eeDiscount: eeDiscount,
                          shippingCost: shippingCost,
                          serviceFee: serviceFee,
                          tax: tax,
                          referAndEarnDiscount: referAndEarnDiscount,
                        ),
                        _buildDeliverySection(context, orderProvider),
                        _buildDisputeActions(
                          context,
                          hasActiveDispute,
                          activeDisputeId,
                          canOpenDispute,
                          orderProvider.orders!.id!,
                        ),
                        const SizedBox(height: Dimensions.paddingSizeSmall),
                        Container(
                          color: Theme.of(context).cardColor,
                          child: CancelAndSupportWidget(
                            orderModel: orderProvider.orders,
                            showSupport: false,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              );
            },
          ),
        ),
      ),
    );
  }
}
