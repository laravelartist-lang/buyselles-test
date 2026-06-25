import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/no_data_screen.dart';
import 'package:sixvalley_vendor_app/features/dispute/controllers/dispute_controller.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_model.dart';
import 'package:sixvalley_vendor_app/features/dispute/screens/dispute_detail_screen.dart';
import 'package:sixvalley_vendor_app/helper/date_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DisputeListScreen extends StatefulWidget {
  const DisputeListScreen({super.key});

  @override
  State<DisputeListScreen> createState() => _DisputeListScreenState();
}

class _DisputeListScreenState extends State<DisputeListScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    Provider.of<DisputeController>(context, listen: false).getDisputes();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      Provider.of<DisputeController>(context, listen: false).loadMoreDisputes();
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'open':
        return Colors.orange;
      case 'vendor_response':
        return Colors.blue;
      case 'under_review':
        return Colors.purple;
      case 'resolved_refund':
      case 'resolved_release':
        return Colors.green;
      case 'closed':
      case 'auto_closed':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }

  String _statusLabel(DisputeModel dispute) {
    return dispute.statusLabel;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(title: getTranslated('disputes', context)),
      body: Column(
        children: [
          Consumer<DisputeController>(
            builder: (context, controller, _) {
              final counts = controller.statusCounts;
              final tabs = [
                {'key': 'all', 'label': getTranslated('all', context) ?? 'All'},
                {'key': 'open', 'label': getTranslated('open', context) ?? 'Open'},
                {'key': 'vendor_response', 'label': getTranslated('action_needed', context) ?? 'Action Needed'},
                {'key': 'under_review', 'label': getTranslated('under_review', context) ?? 'Under Review'},
                {'key': 'resolved', 'label': getTranslated('resolved', context) ?? 'Resolved'},
                {'key': 'closed', 'label': getTranslated('closed', context) ?? 'Closed'},
              ];

              return Container(
                height: 45,
                margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                  itemCount: tabs.length,
                  separatorBuilder: (_, __) => const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                  itemBuilder: (context, index) {
                    final tab = tabs[index];
                    final key = tab['key']!;
                    final count = counts != null ? (counts[key] ?? 0) : 0;
                    final isSelected = controller.currentStatus == key;

                    return InkWell(
                      onTap: () => controller.setStatusFilter(key),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: Dimensions.paddingSizeExtraSmall),
                        decoration: BoxDecoration(
                          color: isSelected ? Theme.of(context).primaryColor : Theme.of(context).cardColor,
                          borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                          border: Border.all(
                            color: isSelected ? Theme.of(context).primaryColor : Theme.of(context).hintColor.withValues(alpha: 0.3),
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Row(
                          children: [
                            Text(
                              tab['label']!,
                              style: robotoRegular.copyWith(
                                color: isSelected ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color,
                                fontSize: Dimensions.fontSizeSmall,
                              ),
                            ),
                            if (count > 0) ...[
                              const SizedBox(width: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                decoration: BoxDecoration(
                                  color: isSelected ? Colors.white.withValues(alpha: 0.3) : Theme.of(context).primaryColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  '$count',
                                  style: robotoBold.copyWith(
                                    fontSize: Dimensions.fontSizeExtraSmall,
                                    color: isSelected ? Colors.white : Theme.of(context).primaryColor,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    );
                  },
                ),
              );
            },
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          Expanded(
            child: Consumer<DisputeController>(
              builder: (context, controller, _) {
                if (controller.isLoading && controller.disputes == null) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (controller.disputes == null || controller.disputes!.isEmpty) {
                  return const NoDataScreen();
                }

                return RefreshIndicator(
                  onRefresh: () => controller.getDisputes(),
                  child: ListView.separated(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                    itemCount: controller.disputes!.length + (controller.isPaginationLoading ? 1 : 0),
                    separatorBuilder: (_, __) => const SizedBox(height: Dimensions.paddingSizeSmall),
                    itemBuilder: (context, index) {
                      if (index >= controller.disputes!.length) {
                        return const Padding(
                          padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
                          child: Center(child: CircularProgressIndicator()),
                        );
                      }

                      final dispute = controller.disputes![index];
                      final color = _statusColor(dispute.status);

                      return InkWell(
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => DisputeDetailScreen(disputeId: dispute.id),
                            ),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                            boxShadow: [BoxShadow(color: Theme.of(context).hintColor.withValues(alpha: 0.1), blurRadius: 3, spreadRadius: 0.5)],
                            border: Border(left: BorderSide(color: color, width: 3)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    '${getTranslated('dispute', context)} #${dispute.id}',
                                    style: robotoBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: color.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(
                                      _statusLabel(dispute),
                                      style: robotoBold.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: color),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                              Row(
                                children: [
                                  Text(
                                    '${getTranslated('order', context)} #${dispute.orderId}',
                                    style: robotoRegular.copyWith(
                                      fontSize: Dimensions.fontSizeSmall,
                                      color: Theme.of(context).hintColor,
                                    ),
                                  ),
                                  const Spacer(),
                                  if (dispute.reason != null)
                                    Flexible(
                                      child: Text(
                                        dispute.reason!.title,
                                        style: robotoRegular.copyWith(
                                          fontSize: Dimensions.fontSizeExtraSmall,
                                          color: Theme.of(context).hintColor,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                ],
                              ),
                              if (dispute.createdAt != null) ...[
                                const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                                Text(
                                  DateConverter.localDateToIsoStringAMPMOrder(DateTime.parse(dispute.createdAt!)),
                                  style: robotoRegular.copyWith(
                                    fontSize: Dimensions.fontSizeExtraSmall,
                                    color: Theme.of(context).hintColor,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
