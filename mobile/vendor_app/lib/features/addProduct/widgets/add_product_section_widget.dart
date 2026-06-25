import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import 'package:sixvalley_vendor_app/features/splash/controllers/splash_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';


class AddProductSectionWidget extends StatelessWidget {
  final String? title;
  final String? subTitle;
  final List<Widget> childrens;
  final TextStyle? titleStyle;
  final bool isDecoration;
  final bool? isAiGenerating;
  final Widget? aiWidget;
  final Widget? button;
  final double? childrenPadding;
  const AddProductSectionWidget({super.key, this.title, required this.childrens, this.titleStyle, this.isDecoration = true, this.aiWidget, this.isAiGenerating, this.childrenPadding, this.subTitle, this.button});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
      decoration: isDecoration ? BoxDecoration(
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
        color: Theme.of(context).cardColor,
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D1B7FED),
            offset: Offset(0, 6),
            blurRadius: 12,
            spreadRadius: -3,
          ),
          BoxShadow(
            color: Color(0x0D1B7FED),
            offset: Offset(0, -6),
            blurRadius: 12,
            spreadRadius: -3,
          ),
        ]
      ) : null,

      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if(title != null)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall, horizontal: Dimensions.fontSizeExtraLarge),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  title ?? '',
                  style: robotoBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color),
                  overflow: TextOverflow.ellipsis,
                ),
              ),


              if(Provider.of<SplashController>(context,listen: false).configModel?.isAiFeatureActive == 1 && aiWidget != null)
              aiWidget ?? SizedBox(),
            ],
          ),
        ),


        if(subTitle != null)
        Row(
          children: [
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 0, horizontal: Dimensions.fontSizeExtraLarge),
                child: Text(
                  subTitle ?? '',
                  style: titleStyle ?? robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).textTheme.headlineLarge?.color),
                  maxLines: 4,
                  overflow: TextOverflow.ellipsis,
                )
              ),
            ),
            button ?? SizedBox(),
          ],
        ),



        ShimmerOverlayWrapper(
          isActive: (isAiGenerating ?? false),
          opacity: 0.3,
          baseColor: Theme.of(context).primaryColor.withValues(alpha: 0.7),
          highlightColor : Theme.of(context).primaryColor.withValues(alpha: 0.3),
          child: Container(
            // decoration: isDecoration ? BoxDecoration(
            //   color: Theme.of(context).cardColor,
            //   boxShadow: const [
            //     BoxShadow(
            //       color: Color(0x0D1B7FED), // 0x0D is the hex value for 5% opacity
            //       offset: Offset(0, 6),
            //       blurRadius: 12,
            //       spreadRadius: -3,
            //     ),
            //     BoxShadow(
            //       color: Color(0x0D1B7FED), // 0x0D is the hex value for 5% opacity
            //       offset: Offset(0, -6),
            //       blurRadius: 12,
            //       spreadRadius: -3,
            //     ),
            //   ]
            // ) : null,


            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: childrenPadding ?? 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: childrens,
              ),
            ),
          ),
        ),


        ],
      ),
    );
  }
}



class ShimmerOverlayWrapper extends StatelessWidget {
  final Widget child;
  final bool isActive;
  final double opacity;
  final Color? baseColor;
  final Color? highlightColor;

  const ShimmerOverlayWrapper({
    super.key,
    required this.child,
    this.isActive = false,
    this.opacity = 0.3,
    this.baseColor,
    this.highlightColor,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        child, // always show the child
        if (isActive)
          Positioned.fill(
            child: Opacity(
              opacity: opacity,
              child: Shimmer.fromColors(
                baseColor: baseColor ?? Theme.of(context).primaryColor,
                highlightColor: highlightColor ?? Colors.grey[100]!,
                child: Container(color: Colors.white), // shimmer overlay
              ),
            ),
          ),
      ],
    );
  }
}
