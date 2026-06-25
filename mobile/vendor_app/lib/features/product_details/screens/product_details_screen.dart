import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/product/controllers/category_controller.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/product_model.dart';
import 'package:sixvalley_vendor_app/features/product_details/controllers/product_details_controller.dart';
import 'package:sixvalley_vendor_app/features/review/controllers/product_review_controller.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/features/auth/controllers/auth_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/features/product_details/widgets/product_details_review_widget.dart';
import 'package:sixvalley_vendor_app/features/product_details/widgets/product_details_widget.dart';


class ProductDetailsScreen extends StatefulWidget {
  final Product? productModel;
  const ProductDetailsScreen({super.key, this.productModel});

  @override
  State<ProductDetailsScreen> createState() => _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends State<ProductDetailsScreen> with TickerProviderStateMixin {
  TabController? _tabController;
  int selectedIndex = 0;

  void load(BuildContext context) {
    Provider.of<ProductReviewController>(context, listen: false).getProductWiseReviewList(context, 1, widget.productModel!.id);
    Provider.of<ProductDetailsController>(context, listen: false).getProductDetails(widget.productModel!.id);
    Provider.of<CategoryController>(context,listen: false).getCategoryList(context,null, 'en');
  }

  @override
  void initState() {
    super.initState();
    load(context);
    _tabController = TabController(length: 2, initialIndex: 0, vsync: this);
    _tabController?.addListener((){
    });
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('product_details', context),
        titleTextStyle: titilliumSemiBold.copyWith(
          fontSize: Dimensions.fontSizeLarge,
          color: Theme.of(context).textTheme.bodyLarge!.color,
        ),
        isBackButtonExist: true,
        isSwitch: widget.productModel!.requestStatus == 1 ? true: false,
        isAction: true,
      productSwitch: true,
      switchAction: (value) {
       if(value) {
         Provider.of<ProductDetailsController>(context, listen: false).productStatusOnOff(context, widget.productModel!.id, 1);
       } else{
         Provider.of<ProductDetailsController>(context, listen: false).productStatusOnOff(context, widget.productModel!.id, 0);
       }}),
      body: RefreshIndicator(
        onRefresh: () async{
          load(context);
        },
        child: Consumer<AuthController>(
          builder: (authContext,authProvider, _) {
            return Column(mainAxisSize: MainAxisSize.min, children: [
              SizedBox(height: Dimensions.paddingSizeSmall),
              Row(
                children: [
                  SizedBox(width: Dimensions.paddingSizeSmall),

                  Align(
                    alignment: Alignment.centerLeft,
                    child: IntrinsicWidth(
                      child: TabBar(
                        controller: _tabController,
                        isScrollable: false,
                        padding: EdgeInsets.zero,
                        indicator: BoxDecoration(), // remove default indicator
                        labelPadding: EdgeInsets.zero,

                        onTap: (index) {
                          setState(() {}); // refresh to update selected/unselected colors
                        },
                        dividerColor: Colors.transparent,
                        tabs: [
                          Tab(
                            child: Container(
                              height: 36,
                              alignment: Alignment.center,
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              //margin: const EdgeInsets.only(right: 10),
                              decoration: BoxDecoration(
                                color: _tabController?.index == 0
                                    ? Theme.of(context).primaryColor
                                    : Colors.grey[300],
                                borderRadius: BorderRadius.circular(50),
                              ),
                              child: Text(
                                getTranslated("product_details", context)!,
                                style: TextStyle(
                                  fontWeight: _tabController?.index == 0
                                      ? FontWeight.bold
                                      : FontWeight.w400,
                                  color: _tabController?.index == 0
                                      ? Colors.white
                                      : Colors.black,
                                ),
                              ),
                            ),
                          ),

                          Tab(
                            child: Container(
                              height: 36,
                              width: 85,
                              alignment: Alignment.center,
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              decoration: BoxDecoration(
                                color: _tabController?.index == 1
                                    ? Theme.of(context).primaryColor
                                    : Colors.grey[300],
                                borderRadius: BorderRadius.circular(50),
                              ),
                              child: Text(
                                getTranslated("reviews", context)!,
                                style: TextStyle(
                                  fontWeight: _tabController?.index == 1
                                      ? FontWeight.bold
                                      : FontWeight.w400,
                                  color: _tabController?.index == 1
                                      ? Colors.white
                                      : Colors.black,
                                ),
                              ),
                            ),
                          ),
                        ],

                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: Dimensions.paddingSizeSmall),
              Expanded(child: TabBarView(
                physics: const NeverScrollableScrollPhysics(),
                controller: _tabController,
                children: [
                  ProductDetailsWidget(productModel: widget.productModel),
                  ProductReviewWidget(productModel: widget.productModel),
                ],
              )),
            ]);
          }
        ),
      )
    );
  }
}
