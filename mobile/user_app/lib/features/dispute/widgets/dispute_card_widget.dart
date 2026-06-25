import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class DisputeStatusBadge extends StatelessWidget {
  final String status;

  const DisputeStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final (Color color, Color textColor, String label) = _resolve(status, context);
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeEight,
        vertical: Dimensions.paddingSizeExtraExtraSmall,
      ),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
      ),
      child: Text(
        label,
        style: TextStyle(color: textColor, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }

  (Color, Color, String) _resolve(String status, BuildContext context) {
    switch (status) {
      case 'open':
        return (Colors.blue.shade100, Colors.blue.shade800, getTranslated('open', context) ?? 'Open');
      case 'vendor_response':
        return (Colors.orange.shade100, Colors.orange.shade800, getTranslated('vendor_response', context) ?? 'Vendor Response');
      case 'under_review':
        return (Colors.purple.shade100, Colors.purple.shade800, getTranslated('under_review', context) ?? 'Under Review');
      case 'resolved_refund':
        return (Colors.green.shade100, Colors.green.shade800, getTranslated('resolved_refund', context) ?? 'Refunded');
      case 'resolved_release':
        return (Colors.green.shade100, Colors.green.shade800, getTranslated('resolved_release', context) ?? 'Resolved');
      case 'pending_closure':
        return (Colors.amber.shade100, Colors.amber.shade800, getTranslated('pending_closure', context) ?? 'Pending Closure');
      case 'closed':
      case 'auto_closed':
        return (Colors.grey.shade200, Colors.grey.shade700, getTranslated('closed', context) ?? 'Closed');
      default:
        return (Colors.grey.shade200, Colors.grey.shade700, status);
    }
  }
}

class DisputeCardWidget extends StatelessWidget {
  final DisputeModel dispute;
  final VoidCallback onTap;

  const DisputeCardWidget({super.key, required this.dispute, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
          vertical: Dimensions.paddingSizeExtraSmall,
        ),
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(Dimensions.paddingSizeEight),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${getTranslated('dispute', context) ?? 'Dispute'} #${dispute.id}',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                ),
                DisputeStatusBadge(status: dispute.status),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Text(
              '${getTranslated('order', context) ?? 'Order'} #${dispute.orderId}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey),
            ),
            if (dispute.reason != null) ...[
              const SizedBox(height: Dimensions.paddingSizeExtraExtraSmall),
              Text(
                dispute.reason!.title,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      fontWeight: FontWeight.w500,
                      color: Theme.of(context).primaryColor,
                    ),
              ),
            ],
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Text(
              dispute.description,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: Dimensions.paddingSizeEight),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  dispute.createdAt?.substring(0, 10) ?? '',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey, fontSize: 11),
                ),
                Row(
                  children: [
                    Icon(Icons.message_outlined, size: 14, color: Colors.grey.shade500),
                    const SizedBox(width: 4),
                    Text(
                      '${dispute.messages.length}',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey),
                    ),
                    const SizedBox(width: Dimensions.paddingSizeEight),
                    const Icon(Icons.chevron_right, size: 18, color: Colors.grey),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
