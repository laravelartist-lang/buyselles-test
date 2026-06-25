import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/filter_icon_widget.dart';
import 'package:sixvalley_vendor_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/filter_model.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/product_model.dart';
import 'package:sixvalley_vendor_app/features/product/widgets/product_filter_bottomsheet_widget.dart';
import 'package:sixvalley_vendor_app/features/product/widgets/status_filter_widget.dart';
import 'package:sixvalley_vendor_app/helper/debounce_helper.dart';
import 'package:sixvalley_vendor_app/localization/controllers/localization_controller.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/features/product/controllers/product_controller.dart';
import 'package:sixvalley_vendor_app/features/profile/controllers/profile_controller.dart';
import 'package:sixvalley_vendor_app/main.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/images.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_search_field_widget.dart';
import 'package:sixvalley_vendor_app/features/product/widgets/product_widget.dart';

class ProductListMenuScreen extends StatefulWidget {
  final bool fromNotification;
  const ProductListMenuScreen({super.key,  this.fromNotification = false});
  @override
  State<ProductListMenuScreen> createState() => _ProductListMenuScreenState();
}

class _ProductListMenuScreenState extends State<ProductListMenuScreen> {
  final DebounceHelper _debounce = DebounceHelper(milliseconds: 500);
  TextEditingController searchController = TextEditingController();
  int? userId;




  void _getBrandList() {
    String languageCode = Provider.of<LocalizationController>(context, listen: false).locale.countryCode == 'US' ?
    'en' : Provider.of<LocalizationController>(context, listen: false).locale.countryCode!.toLowerCase();
    Provider.of<ProductController>(Get.context!,listen: false).getBrandList(Get.context!, languageCode);
  }


  @override
  void initState() {
    userId = Provider.of<ProfileController>(context, listen: false).userId;
    Provider.of<ProductController>(context, listen: false).clearFilterData();
    _getBrandList();
    _ensureSellerId();
    super.initState();
  }

  Future<void> _ensureSellerId() async {
    if (userId != null) {
      return;
    }

    final profileController = Provider.of<ProfileController>(context, listen: false);
    final response = await profileController.getSellerInfo();
    if (!mounted || !response.isSuccess) {
      return;
    }

    setState(() {
      userId = profileController.userId;
    });
  }

  @override
  void dispose() {
    _debounce.dispose();
    super.dispose();
  }


  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if(widget.fromNotification) {
          Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(
            builder: (BuildContext context) => const DashboardScreen(),
          ), (route) => false);
        } else {
          if(!didPop) {
            Navigator.of(context).pop();
          }
        }
      },

      child: Scaffold(
        appBar: CustomAppBarWidget(
          title: getTranslated('product_list', context),
          onBackPressed: () {
            if(widget.fromNotification) {
              Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (BuildContext context) => const DashboardScreen()), (route) => false);
            } else {
              Navigator.of(context).pop();
            }
          },
        ),
        body: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            padding: const EdgeInsets.only(top : Dimensions.paddingSizeMedium),
            color: Theme.of(context).cardColor,
            child: StatusFilterWidget(
              onFilterChanged: (index) {},
            ),
          ),

          SizedBox(height: 80,
            child: Consumer<ProductController>(
              builder: (context, productController, _) {
                return Container(
                  color: Theme.of(context).cardColor,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeMedium, vertical: Dimensions.paddingSizeExtraSmall),
                    child: Row(
                      children: [
                        Expanded(
                          child: CustomSearchFieldWidget(
                            controller: searchController,
                            hint: getTranslated('search_by_product_name', context),
                            prefix: Images.iconsSearch,
                            iconPressed: () => (){},
                            onSubmit: (text) => (){},
                            onChanged: (value)=> _debounce.run(() async {
                              if (userId == null) {
                                await _ensureSellerId();
                              }
                              if (userId == null) {
                                return;
                              }
                              productController.getSellerProductList(
                                userId.toString(), 1, 'en', value,
                                filterSearchModel:  FilterModel(
                                  reload: true
                                ),
                              );
                            }),
                          ),
                        ),
                        const SizedBox(width: Dimensions.paddingSize),

                        ///filter Icon
                        FilterIconWidget(
                          filterCount: _getFilterCount(productController.sellerProductModel),
                          onTap: productController.sellerProductModel == null ? null : () {
                            showModalBottomSheet(
                              context: context,
                              isScrollControlled: true,
                              backgroundColor: Colors.transparent,
                              builder: (builder) => const ProductFilterBottomSheet(),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                );
              }
            )
          ),
          const SizedBox(height: Dimensions.paddingSize),

          Expanded(child: ProductViewWidget(
             sellerId: userId,
             fromNotification: widget.fromNotification,
             keyboardHeight: MediaQuery.of(context).viewInsets.bottom,
          ))
          ],
        ),
      ),
    );
  }

  int _getFilterCount(ProductModel? sellerProductModel) {
    if (sellerProductModel == null) return 0;
    final int nonNullFilterCount = [
      sellerProductModel.productType,
      sellerProductModel.maxPrice,
      sellerProductModel.endDate,
      sellerProductModel.status,
      sellerProductModel.isApproved,
      sellerProductModel.sorting,
      sellerProductModel.offerType,
    ].whereType<Object>().length;


    final int categoryCount = sellerProductModel.categoryIds?.length ?? 0;
    final int subCategoryCount = sellerProductModel.filterSubCategoryIds?.length ?? 0;
    final int subSubCategoryCount = sellerProductModel.filterSubSubCategoryIds?.length ?? 0;
    final int brandCount = sellerProductModel.brandIds?.length ?? 0;
    final int publisherCount = sellerProductModel.publishHouseIds?.length ?? 0;
    final int authorCount = sellerProductModel.authorIds?.length ?? 0;

    return nonNullFilterCount +
        categoryCount +
        subCategoryCount +
        subSubCategoryCount +
        brandCount +
        publisherCount +
        authorCount;
  }

}
