import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/features/vat_management/screens/vat_report_screen.dart';
import 'package:sixvalley_vendor_app/features/vat_management/widgets/management_card_widget.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/images.dart';

class VatManagementScreen extends StatefulWidget {
  const VatManagementScreen({super.key});

  @override
  State<VatManagementScreen> createState() => _VatManagementScreenState();
}

class _VatManagementScreenState extends State<VatManagementScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(title: getTranslated('reports', context)),
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeMedium, vertical: Dimensions.paddingSizeLarge),
        child: Column(children: [
          // ManagementCardWidget(
          //   name: getTranslated('expense_report', context)!,
          //   /// todo - need proper description from the product team - zianur
          //   description: 'Don’t have cutlery? Restaurant will provide your',
          //   image: Images.expenseReportIcon,
          //   screenToRoute: ExpenseReportScreen(),
          // ),
          // SizedBox(height: Dimensions.paddingSizeLarge),

          ManagementCardWidget(
            name: getTranslated('vat_report', context)!,
            description: getTranslated('see_vat_collected_and_applied_on_transactions', context)!,
            image: Images.vatReportIcon,
            screenToRoute: VatReportScreen(),
          ),
        ]),
      ),
    );
  }
}
