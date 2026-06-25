import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/dispute/screens/dispute_detail_screen.dart';
import 'package:sixvalley_vendor_app/features/order/domain/models/order_model.dart';
import 'package:sixvalley_vendor_app/features/order_details/controllers/order_details_controller.dart';
import 'package:sixvalley_vendor_app/helper/date_converter.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class EscrowInfoSectionWidget extends StatelessWidget {
  const EscrowInfoSectionWidget({super.key});

  Color _statusColor(String? status) {
    switch (status) {
      case 'held':
        return Colors.orange;
      case 'released':
        return Colors.green;
      case 'disputed':
        return Colors.red;
      case 'refunded':
        return Colors.blueGrey;
      default:
        return Colors.grey;
    }
  }

  String _statusLabel(String? status, BuildContext context) {
    switch (status) {
      case 'held':
        return getTranslated('held', context) ?? 'Held';
      case 'released':
        return getTranslated('released', context) ?? 'Released';
      case 'disputed':
        return getTranslated('disputed', context) ?? 'Disputed';
      case 'refunded':
        return getTranslated('refunded', context) ?? 'Refunded';
      default:
        return status?.toUpperCase() ?? '---';
    }
  }

  IconData _statusIcon(String? status) {
    switch (status) {
      case 'held':
        return Icons.lock_outline;
      case 'released':
        return Icons.check_circle_outline;
      case 'disputed':
        return Icons.gavel_outlined;
      case 'refunded':
        return Icons.replay_outlined;
      default:
        return Icons.help_outline;
    }
  }

  String _formatCountdown(String? dateTimeStr, BuildContext context) {
    if (dateTimeStr == null || dateTimeStr.isEmpty) return '---';
    try {
      final dt = DateTime.parse(dateTimeStr);
      final diff = dt.difference(DateTime.now());

      if (diff.isNegative) {
        return getTranslated('any_moment', context) ?? 'Any moment';
      }

      if (diff.inDays > 0) {
        return '${diff.inDays}d ${diff.inHours % 24}h';
      } else if (diff.inHours > 0) {
        return '${diff.inHours}h ${diff.inMinutes % 60}m';
      } else {
        return '${diff.inMinutes}m';
      }
    } catch (_) {
      return dateTimeStr;
    }
  }

  String _formatReleasedDate(String? dateTimeStr) {
    if (dateTimeStr == null || dateTimeStr.isEmpty) return '---';
    try {
      return DateConverter.localDateToIsoStringAMPMOrder(DateTime.parse(dateTimeStr));
    } catch (_) {
      return dateTimeStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<OrderDetailsController>(
      builder: (context, orderProvider, _) {
        final escrow = orderProvider.orderDetails?.first.order?.escrow;

        if (escrow == null) return const SizedBox.shrink();

        final color = _statusColor(escrow.status);

        return Container(
          width: double.infinity,
          margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          decoration: BoxDecoration(
            color: color.withOpacity(0.06),
            borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
            border: Border.all(color: color.withOpacity(0.25)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                children: [
                  Icon(_statusIcon(escrow.status), color: color, size: 20),
                  const SizedBox(width: Dimensions.paddingSizeSmall),
                  Text(
                    getTranslated('escrow_protection', context) ?? 'Escrow Protection',
                    style: titilliumSemiBold.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: color,
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Dimensions.paddingSizeSmall,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _statusLabel(escrow.status, context),
                      style: robotoBold.copyWith(
                        fontSize: Dimensions.fontSizeExtraSmall,
                        color: color,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),

              // Amount row
              Row(
                children: [
                  Text(
                    getTranslated('escrow_amount', context) ?? 'Escrow Amount',
                    style: robotoRegular.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Theme.of(context).hintColor,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    PriceConverter.convertPrice(context, escrow.amount ?? 0),
                    style: titilliumSemiBold.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),

              // Seller amount row
              Row(
                children: [
                  Text(
                    getTranslated('seller_amount', context) ?? 'Your Amount',
                    style: robotoRegular.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Theme.of(context).hintColor,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    PriceConverter.convertPrice(context, escrow.sellerAmount ?? 0),
                    style: robotoMedium.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Colors.green,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),

              // Auto-release countdown
              if (escrow.status == 'held' && escrow.autoReleaseAt != null) ...[
                const Divider(height: 1),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Row(
                  children: [
                    Icon(Icons.timer_outlined, color: color, size: 16),
                    const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                    Expanded(
                      child: Text(
                        '${getTranslated('auto_release_in', context) ?? 'Auto-release in'}: ${_formatCountdown(escrow.autoReleaseAt, context)}',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeExtraSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ],

              // Released info
              if (escrow.status == 'released' && escrow.releasedAt != null) ...[
                const Divider(height: 1),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Row(
                  children: [
                    Icon(Icons.check_circle, color: Colors.green, size: 16),
                    const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                    Expanded(
                      child: Text(
                        '${getTranslated('released_on', context) ?? 'Released on'}: ${_formatReleasedDate(escrow.releasedAt)}',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeExtraSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ],

              // Description
              if (escrow.status == 'held') ...[
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                  decoration: BoxDecoration(
                    color: Colors.amber.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.amber.withOpacity(0.2)),
                  ),
                  child: Text(
                    getTranslated('escrow_held_description', context) ??
                        'Funds are held in escrow and will be released to your available balance after the holding period.',
                    style: robotoRegular.copyWith(
                      fontSize: Dimensions.fontSizeExtraSmall,
                      color: Theme.of(context).hintColor,
                    ),
                  ),
                ),
              ],

              if (_hasActiveDispute(escrow)) ...[
                const Divider(height: 1),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.06),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.red.withOpacity(0.2)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.gavel, color: Colors.red, size: 18),
                          const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                          Expanded(
                            child: Text(
                              getTranslated('active_dispute_on_order', context) ?? 'This order has an active dispute.',
                              style: robotoMedium.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color: Colors.red.shade700,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (escrow.disputeId != null) ...[
                        const SizedBox(height: Dimensions.paddingSizeSmall),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => DisputeDetailScreen(disputeId: escrow.disputeId!),
                                ),
                              );
                            },
                            icon: const Icon(Icons.open_in_new, size: 18),
                            label: Text(getTranslated('view_dispute', context) ?? 'View Dispute'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.red.shade700,
                              side: BorderSide(color: Colors.red.shade300),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  bool _hasActiveDispute(Escrow escrow) {
    return escrow.status == 'disputed' || escrow.disputeId != null;
  }
}
