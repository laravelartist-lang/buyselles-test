import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/order/controllers/order_controller.dart';
import 'package:sixvalley_vendor_app/features/order_details/controllers/order_details_controller.dart';
import 'package:sixvalley_vendor_app/features/order_details/widgets/order_product_list_item_widget.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';



class ProductListWidget extends StatefulWidget {
  final int orderId;
  const ProductListWidget({super.key, required this.orderId});

  @override
  State<ProductListWidget> createState() => _ProductListWidgetState();
}

class _ProductListWidgetState extends State<ProductListWidget> with SingleTickerProviderStateMixin {

  bool isExpand = true;

  @override
  Widget build(BuildContext context) {
    return Consumer<OrderController>(
      builder: (context, orderController, child) {
        return Consumer<OrderDetailsController>(
          builder: (context, orderDetailsController, child) {

            int count = orderDetailsController.orderDetails?.length ?? 0;

            return Container(
              decoration: BoxDecoration(
                boxShadow: [
                  BoxShadow(
                    color: Theme.of(context).hintColor.withValues(alpha: .2),
                    spreadRadius: 1.5,
                    blurRadius: 3,
                  )
                ],
                color: Theme.of(context).cardColor,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault, vertical: Dimensions.paddingSizeSmall),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Text(
                              getTranslated('product_list', context)!,
                              style: robotoBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color),
                            ),
                            SizedBox(width: Dimensions.paddingSizeSmall),

                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Dimensions.paddingSizeSmall,
                                vertical: Dimensions.paddingSizeExtraSmall,
                              ),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Theme.of(context).primaryColor.withValues(alpha: 0.15),
                              ),
                              child: Text('$count',
                                style: robotoBold.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: Theme.of(context).textTheme.bodyLarge?.color),
                              ),
                            ),
                          ],
                        ),


                        InkWell(
                          onTap: () {
                            setState(() => isExpand = !isExpand);
                          },
                          child: AnimatedRotation(
                            turns: isExpand ? 0 : 0.5,
                            duration: const Duration(milliseconds: 200),
                            child: const Icon(Icons.keyboard_arrow_down),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Divider(thickness: 0.2, height: 1, color: Theme.of(context).hintColor.withValues(alpha: .65)),


                  AnimatedSize(
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeInOut,
                    alignment: Alignment.topCenter,
                    child: isExpand ? Container(
                      padding: const EdgeInsets.fromLTRB(
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault,
                        Dimensions.paddingSizeDefault, 0
                      ),
                      child: ListView.builder(
                        padding: EdgeInsets.zero,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: count,
                        itemBuilder: (context, index) {
                          return OrderedProductListItemWidget(
                            orderDetailsModel: orderDetailsController.orderDetails![index],
                            paymentStatus:
                            orderController.paymentStatus,
                            orderId: widget.orderId,
                            index: index,
                            length: count,
                          );
                        },
                      ),
                    )
                        : const SizedBox(),
                  ),

                  isExpand ? const SizedBox(height: Dimensions.paddingSizeSmall) : const SizedBox(),

                ],
              ),
            );
          },
        );
      },
    );
  }
}

