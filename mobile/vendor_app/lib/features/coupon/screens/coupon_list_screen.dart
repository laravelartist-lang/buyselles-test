import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_dialog_widget.dart';
import 'package:sixvalley_vendor_app/features/coupon/widgets/coupon_details_dialog_widget.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/features/coupon/controllers/coupon_controller.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/no_data_screen.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/paginated_list_view_widget.dart';
import 'package:sixvalley_vendor_app/features/coupon/screens/add_new_coupon_screen.dart';
import 'package:sixvalley_vendor_app/features/coupon/widgets/coupon_card_widget.dart';
import 'package:sixvalley_vendor_app/features/order/screens/order_screen.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';



class CouponListScreen extends StatefulWidget {
  const CouponListScreen({super.key});
  @override
  State<CouponListScreen> createState() => _CouponListScreenState();
}

class _CouponListScreenState extends State<CouponListScreen> {

  ScrollController scrollController = ScrollController();

  bool isScrolledToEnd = false;


  void _onScroll() {
    if (scrollController.position.atEdge) {
      isScrolledToEnd = scrollController.position.pixels == scrollController.position.maxScrollExtent;
      setState(() {});
    }
  }


  @override
  void initState() {
    Provider.of<CouponController>(context, listen: false).getCouponList(context,1);
    scrollController.addListener(_onScroll);
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(title: getTranslated('coupon_list', context)),
      body: Consumer<CouponController>(
        builder: (context, couponProvider,_) {
          return couponProvider.couponModel != null? (couponProvider.couponModel!.coupons != null && couponProvider.couponModel!.coupons!.isNotEmpty) ?
          SingleChildScrollView(
            controller: scrollController,
            child: PaginatedListViewWidget(
              reverse: false,
              scrollController: scrollController,
              totalSize: couponProvider.couponModel!.totalSize,
              offset: couponProvider.couponModel != null ? int.parse(couponProvider.couponModel!.offset!) : null,
              onPaginate: (int? offset) async {
                await couponProvider.getCouponList(context, offset!, reload: false);
              },
              itemView:  ListView.builder(
                physics: const NeverScrollableScrollPhysics(),
                  itemCount: couponProvider.couponModel?.coupons?.length,
                    shrinkWrap: true,
                    itemBuilder: (context, index){
                  return InkWell(
                    onTap: () {
                      showAnimatedDialogWidget(context, CouponDetailsDialogWidget(coupons: couponProvider.couponModel?.coupons?[index]));
                    },
                   child: Padding(
                     padding: EdgeInsets.only(
                       bottom: (couponProvider.couponModel?.coupons?.length  == index+1) ? Dimensions.paddingSizeSmall : 0
                     ),
                     child: CouponCardWidget(coupons: couponProvider.couponModel?.coupons?[index], index: index))
                  );
                }
              ),
            ),
          ) : const NoDataScreen(): const OrderShimmer();

        }
      ),

      floatingActionButton: isScrolledToEnd ? null : FloatingActionButton(
        backgroundColor: Theme.of(context).cardColor,
        child: Icon(Icons.add,color: Theme.of(context).primaryColor,),
        onPressed: (){
          Navigator.push(context, MaterialPageRoute(builder: (_)=> const AddNewCouponScreen()));
        },
      ),
    );
  }
}
