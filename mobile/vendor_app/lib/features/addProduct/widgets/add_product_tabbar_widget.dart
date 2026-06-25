import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import '../../../main.dart';

List<Tab> _productTabs = <Tab>[
  Tab(text: getTranslated('general_info', Get.context!) ?? 'General Info'),
  Tab(text: getTranslated('variations', Get.context!) ?? 'Variations'),
  Tab(text: getTranslated('seo', Get.context!) ??  'SEO'),
];


class AddProductTitleBar extends StatefulWidget {
  final TabController tabController;
  const AddProductTitleBar({super.key, required this.tabController});

  @override
  State<AddProductTitleBar> createState() => _AddProductTitleBarState();
}

class _AddProductTitleBarState extends State<AddProductTitleBar> with SingleTickerProviderStateMixin {


  @override
  void initState() {
    super.initState();
  }


  @override
  Widget build(BuildContext context) {

    return SizedBox(
      height: 50,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 0),
        child: TabBar(
          labelPadding: EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
          controller: widget.tabController,
          tabs: _productTabs,
          labelColor: Theme.of(context).cardColor,
          unselectedLabelColor: Colors.grey,
          indicatorColor: Theme.of(context).primaryColor,
          dividerColor: Colors.transparent,
          indicatorSize: TabBarIndicatorSize.tab,
          indicatorPadding: EdgeInsets.zero,
          indicator: BoxDecoration(
            borderRadius: BorderRadius.circular(Dimensions.radiusSmall), // Match radiusSmall
            border: Border.all(color: Theme.of(context).primaryColor),
            color: Theme.of(context).primaryColor,
          ),
        ),
      ),
    );
  }
}