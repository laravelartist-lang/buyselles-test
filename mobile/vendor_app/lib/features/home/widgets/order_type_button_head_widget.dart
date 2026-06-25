import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_asset_image_widget.dart';
import 'package:sixvalley_vendor_app/localization/controllers/localization_controller.dart';
import 'package:sixvalley_vendor_app/features/order/controllers/order_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class OrderTypeButtonHeadWidget extends StatelessWidget {
  final String? text;
  final String? subText;
  final Color? color;
  final Color? circleColor;
  final int index;
  final Function? callback;
  final int? numberOfOrder;
  final String? image;
  const OrderTypeButtonHeadWidget({
    super.key, required this.text,this.subText,this.color, required this.index, required this.callback,
    required this.numberOfOrder, required this.circleColor, required this.image
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () {
        Provider.of<OrderController>(context, listen: false).setIndex(context, index);
        callback!();
      },
      child: Container(
        margin: EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
        ),
        child: Stack(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Container(
                  margin : EdgeInsets.all(Dimensions.paddingSizeSmall),
                  padding : EdgeInsets.all(Dimensions.paddingSizeSmall),
                  height: 35, width: 35,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(50),
                    color: circleColor
                  ),
                  child: CustomAssetImageWidget(image!, color: Colors.white,),
                )
              ],
            ),

            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
              child: Container(alignment: Alignment.center,
                child: Center(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(numberOfOrder.toString(),
                        style: robotoBold.copyWith(color: Theme.of(context).textTheme.bodyLarge?.color,
                        fontSize: Dimensions.fontSizeHeaderLarge)
                      ),

                      Row(children: [
                        Text(text!, style: robotoMedium.copyWith(color: Theme.of(context).textTheme.headlineLarge?.color,
                          fontSize: Dimensions.fontSizeSmall)
                        ),
                        const SizedBox(width: Dimensions.paddingSizeExtraSmall),

                        Text(subText!, style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).textTheme.headlineLarge?.color)),
                        ],
                      ),

                    ],
                  )
                )
              ),
            ),

            Row(
              children: [
                Provider.of<LocalizationController>(context,listen: false).isLtr?const SizedBox.shrink():const Spacer(),
                Container(width: MediaQuery.of(context).size.width/4,
                  height:MediaQuery.of(context).size.width/4,
                  decoration: BoxDecoration(
                      color: Theme.of(context).cardColor.withValues(alpha:.10),
                      borderRadius: const BorderRadius.only(bottomRight: Radius.circular(100))
                  ),),
                Provider.of<LocalizationController>(context,listen: false).isLtr?const Spacer():const SizedBox.shrink(),
              ],
            )

          ],
        ),
      ),
    );
  }
}
