import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/features/reports/controllers/report_controller.dart';
import 'package:sixvalley_vendor_app/features/reports/domain/models/report_models.dart';
import 'package:sixvalley_vendor_app/features/vat_management/screens/vat_report_screen.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/theme/controllers/theme_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  final List<String> _filterLabels = ['this_week', 'this_month', 'this_year'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final controller = Provider.of<ReportController>(context, listen: false);
      controller.loadEarningsReport(ReportController.filterTypes.first);
      controller.loadOrderStatistics(ReportController.filterTypes.first);
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Provider.of<ThemeController>(context).darkTheme;

    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('reports', context) ?? 'Reports',
        isBackButtonExist: true,
        isAction: false,
      ),
      body: Column(
        children: [
          Container(
            color: Theme.of(context).cardColor,
            child: TabBar(
              controller: _tabController,
              labelStyle: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeSmall),
              unselectedLabelStyle: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
              labelColor: Theme.of(context).primaryColor,
              unselectedLabelColor: Theme.of(context).hintColor,
              indicatorColor: Theme.of(context).primaryColor,
              tabs: [
                Tab(text: getTranslated('earnings', context) ?? 'Earnings'),
                Tab(text: getTranslated('order_report', context) ?? 'Orders'),
                Tab(text: getTranslated('vat_report', context) ?? 'VAT'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _EarningsTab(filterLabels: _filterLabels, isDark: isDark),
                _OrdersTab(filterLabels: _filterLabels, isDark: isDark),
                const VatReportScreen(embedded: true),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EarningsTab extends StatelessWidget {
  final List<String> filterLabels;
  final bool isDark;

  const _EarningsTab({
    required this.filterLabels,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Consumer<ReportController>(
      builder: (context, controller, _) {
        final report = controller.earningsReport;

        return SingleChildScrollView(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _FilterChips(
                labels: filterLabels,
                selectedIndex: controller.earningsFilterIndex,
                onSelected: (index) {
                  controller.setEarningsFilterIndex(index);
                  controller.loadEarningsReport(ReportController.filterTypes[index]);
                },
              ),
              const SizedBox(height: Dimensions.paddingSizeLarge),
              if (controller.isEarningsLoading || report == null)
                const Center(child: CircularProgressIndicator())
              else ...[
                Row(
                  children: [
                    Expanded(
                      child: _SummaryCard(
                        title: getTranslated('seller_earning', context) ?? 'My Earnings',
                        value: PriceConverter.convertPrice(
                          context,
                          report.sellerEarnings.fold<double>(0, (a, b) => a + b),
                        ),
                        icon: Icons.trending_up,
                        iconColor: Colors.green,
                      ),
                    ),
                    const SizedBox(width: Dimensions.paddingSizeSmall),
                    Expanded(
                      child: _SummaryCard(
                        title: getTranslated('commission', context) ?? 'Commission',
                        value: PriceConverter.convertPrice(
                          context,
                          report.commissions.fold<double>(0, (a, b) => a + b),
                        ),
                        icon: Icons.percent,
                        iconColor: Colors.orange,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),
                Text(
                  getTranslated('earning_trend', context) ?? 'Earning Trend',
                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                _SimpleBarChart(
                  earningsData: report.sellerEarnings,
                  commissionData: report.commissions,
                  maxValue: report.chartMax,
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _OrdersTab extends StatelessWidget {
  final List<String> filterLabels;
  final bool isDark;

  const _OrdersTab({
    required this.filterLabels,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Consumer<ReportController>(
      builder: (context, controller, _) {
        final OrderStatisticsModel? data = controller.orderStatistics;

        return SingleChildScrollView(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _FilterChips(
                labels: filterLabels,
                selectedIndex: controller.ordersFilterIndex,
                onSelected: (index) {
                  controller.setOrdersFilterIndex(index);
                  controller.loadOrderStatistics(ReportController.filterTypes[index]);
                },
              ),
              const SizedBox(height: Dimensions.paddingSizeLarge),
              if (controller.isOrdersLoading)
                const Center(child: CircularProgressIndicator())
              else if (data == null)
                Center(
                  child: Text(
                    getTranslated('no_data_found', context) ?? 'No data found',
                    style: robotoRegular.copyWith(color: Theme.of(context).hintColor),
                  ),
                )
              else ...[
                Text(
                  getTranslated('order_statistics', context) ?? 'Order Statistics',
                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: Dimensions.paddingSizeSmall,
                  crossAxisSpacing: Dimensions.paddingSizeSmall,
                  childAspectRatio: 2,
                  children: [
                    _OrderStatCard(label: getTranslated('pending', context) ?? 'Pending', count: data.pending ?? 0, color: Colors.orange),
                    _OrderStatCard(label: getTranslated('confirmed', context) ?? 'Confirmed', count: data.confirmed ?? 0, color: Colors.blue),
                    _OrderStatCard(label: getTranslated('processing', context) ?? 'Processing', count: data.processing ?? 0, color: Colors.purple),
                    _OrderStatCard(label: getTranslated('out_for_delivery', context) ?? 'Out for Delivery', count: data.outForDelivery ?? 0, color: Colors.teal),
                    _OrderStatCard(label: getTranslated('delivered', context) ?? 'Delivered', count: data.delivered ?? 0, color: Colors.green),
                    _OrderStatCard(label: getTranslated('cancelled', context) ?? 'Cancelled', count: data.canceled ?? 0, color: Colors.red),
                    _OrderStatCard(label: getTranslated('returned', context) ?? 'Returned', count: data.returned ?? 0, color: Colors.brown),
                    _OrderStatCard(label: getTranslated('failed', context) ?? 'Failed', count: data.failed ?? 0, color: Colors.grey),
                  ],
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _FilterChips extends StatelessWidget {
  final List<String> labels;
  final int selectedIndex;
  final void Function(int) onSelected;

  const _FilterChips({
    required this.labels,
    required this.selectedIndex,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: Dimensions.paddingSizeSmall,
      children: List.generate(
        labels.length,
        (index) => ChoiceChip(
          label: Text(getTranslated(labels[index], context) ?? labels[index]),
          selected: selectedIndex == index,
          selectedColor: Theme.of(context).primaryColor,
          labelStyle: TextStyle(
            color: selectedIndex == index ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color,
          ),
          onSelected: (_) => onSelected(index),
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color iconColor;

  const _SummaryCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: iconColor, size: 20),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: Theme.of(context).hintColor),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(value, style: titilliumBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderStatCard extends StatelessWidget {
  final String label;
  final int count;
  final Color color;

  const _OrderStatCard({required this.label, required this.count, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeSmall,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: Theme.of(context).hintColor),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text('$count', style: titilliumBold.copyWith(fontSize: Dimensions.fontSizeDefault, color: color)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SimpleBarChart extends StatelessWidget {
  final List<double> earningsData;
  final List<double> commissionData;
  final double maxValue;

  const _SimpleBarChart({
    required this.earningsData,
    required this.commissionData,
    required this.maxValue,
  });

  @override
  Widget build(BuildContext context) {
    const barCount = 7;
    final displayEarnings = earningsData.length > barCount
        ? earningsData.sublist(earningsData.length - barCount)
        : earningsData;
    final displayCommission = commissionData.length > barCount
        ? commissionData.sublist(commissionData.length - barCount)
        : commissionData;

    return Container(
      height: 200,
      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: List.generate(displayEarnings.length, (i) {
          final earningVal = displayEarnings[i];
          final commissionVal = i < displayCommission.length ? displayCommission[i] : 0.0;
          final max = maxValue > 0 ? maxValue : 1;

          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Container(
                    height: (earningVal / max * 140).clamp(4, 140),
                    decoration: BoxDecoration(
                      color: Theme.of(context).primaryColor,
                      borderRadius: const BorderRadius.only(
                        topLeft: Radius.circular(3),
                        topRight: Radius.circular(3),
                      ),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Container(
                    height: (commissionVal / max * 40).clamp(2, 40),
                    decoration: BoxDecoration(
                      color: Colors.orange.withValues(alpha: 0.7),
                      borderRadius: const BorderRadius.only(
                        topLeft: Radius.circular(2),
                        topRight: Radius.circular(2),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}
