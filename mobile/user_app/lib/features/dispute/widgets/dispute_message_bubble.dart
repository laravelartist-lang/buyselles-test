import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_message_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class DisputeMessageBubble extends StatelessWidget {
  final DisputeMessageModel message;

  const DisputeMessageBubble({
    super.key,
    required this.message,
  });

  @override
  Widget build(BuildContext context) {
    final bool isMine = message.isFromBuyer;
    final bool isSystem = message.isFromSystem;
    final String resolvedMessage =
        isSystem ? _resolveSystemMessage(context) : message.message;

    if (isSystem) {
      return Center(
        child: Container(
          margin: const EdgeInsets.symmetric(
              vertical: Dimensions.paddingSizeExtraSmall),
          padding: const EdgeInsets.symmetric(
            horizontal: Dimensions.paddingSizeDefault,
            vertical: Dimensions.paddingSizeExtraSmall,
          ),
          decoration: BoxDecoration(
            color: Colors.grey.shade200,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            resolvedMessage,
            style: TextStyle(
                fontSize: 12,
                color: Colors.grey.shade700,
                fontStyle: FontStyle.italic),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    String senderLabel;
    if (isMine) {
      senderLabel = getTranslated('you', context) ?? 'You';
    } else if (message.senderType == 'admin') {
      senderLabel = getTranslated('admin', context) ?? 'Admin';
    } else {
      senderLabel = getTranslated('vendor', context) ?? 'Vendor';
    }

    return Padding(
      padding: const EdgeInsets.symmetric(
          vertical: Dimensions.paddingSizeExtraExtraSmall),
      child: Row(
        mainAxisAlignment:
            isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMine) ...[
            CircleAvatar(
              radius: 14,
              backgroundColor: Theme.of(context).primaryColor.withOpacity(0.15),
              child: Text(
                senderLabel.isNotEmpty ? senderLabel[0].toUpperCase() : '?',
                style: TextStyle(
                    fontSize: 12,
                    color: Theme.of(context).primaryColor,
                    fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(width: Dimensions.paddingSizeExtraSmall),
          ],
          Flexible(
            child: Column(
              crossAxisAlignment:
                  isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                Text(
                  senderLabel,
                  style: TextStyle(
                      fontSize: 11,
                      color: Colors.grey.shade600,
                      fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeDefault,
                    vertical: Dimensions.paddingSizeEight,
                  ),
                  decoration: BoxDecoration(
                    color: isMine
                        ? Theme.of(context).primaryColor
                        : Theme.of(context).cardColor,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(12),
                      topRight: const Radius.circular(12),
                      bottomLeft: Radius.circular(isMine ? 12 : 0),
                      bottomRight: Radius.circular(isMine ? 0 : 12),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.06),
                        blurRadius: 4,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Text(
                    resolvedMessage,
                    style: TextStyle(
                      color: isMine
                          ? Colors.white
                          : Theme.of(context).textTheme.bodyMedium?.color,
                      fontSize: 14,
                    ),
                  ),
                ),
                if (message.createdAt != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    message.createdAt!.length >= 16
                        ? message.createdAt!.substring(0, 16)
                        : message.createdAt!,
                    style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                  ),
                ],
              ],
            ),
          ),
          if (isMine) const SizedBox(width: Dimensions.paddingSizeExtraSmall),
        ],
      ),
    );
  }

  String _resolveSystemMessage(BuildContext context) {
    final String rawMessage = message.message.trim();

    if (rawMessage.startsWith('dispute_escalated_to_admin_for_review_by')) {
      final String actorKey = rawMessage
          .replaceFirst('dispute_escalated_to_admin_for_review_by', '')
          .trim()
          .toLowerCase();

      final String actorLabel = switch (actorKey) {
        'buyer' => getTranslated('buyer', context) ?? 'Buyer',
        'vendor' => getTranslated('vendor', context) ?? 'Vendor',
        'admin' => getTranslated('admin', context) ?? 'Admin',
        _ => _humanize(rawMessage: actorKey),
      };

      final String prefix =
          getTranslated('dispute_escalated_to_admin_for_review_by', context) ??
              'Dispute escalated to admin for review by';

      return '$prefix $actorLabel';
    }

    return _humanize(rawMessage: rawMessage);
  }

  String _humanize({required String rawMessage}) {
    final String normalized = rawMessage.replaceAll(RegExp(r'_+'), ' ').trim();
    if (normalized.isEmpty) {
      return rawMessage;
    }

    return normalized[0].toUpperCase() + normalized.substring(1);
  }
}
