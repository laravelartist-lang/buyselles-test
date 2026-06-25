import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_message_model.dart';
import 'package:sixvalley_vendor_app/helper/date_converter.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DisputeMessageBubble extends StatelessWidget {
  final DisputeMessageModel message;
  const DisputeMessageBubble({super.key, required this.message});

  bool get _isFromVendor => message.senderType == 'vendor';
  bool get _isFromSystem => message.senderType == 'system';

  Color _bubbleColor(BuildContext context) {
    if (_isFromVendor) return Theme.of(context).primaryColor.withValues(alpha: 0.1);
    if (_isFromSystem) return Colors.grey.withValues(alpha: 0.1);
    if (message.senderType == 'admin') return Colors.blue.withValues(alpha: 0.1);
    return Theme.of(context).cardColor;
  }

  String _senderLabel() {
    if (message.senderName != null && message.senderName!.isNotEmpty) return message.senderName!;
    switch (message.senderType) {
      case 'vendor':
        return 'You';
      case 'buyer':
        return 'Buyer';
      case 'admin':
        return 'Admin';
      case 'system':
        return 'System';
      default:
        return message.senderType;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
      child: Row(
        mainAxisAlignment: _isFromVendor ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Flexible(
            child: Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              decoration: BoxDecoration(
                color: _bubbleColor(context),
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(Dimensions.paddingSizeSmall),
                  topRight: const Radius.circular(Dimensions.paddingSizeSmall),
                  bottomLeft: _isFromVendor ? const Radius.circular(Dimensions.paddingSizeSmall) : Radius.zero,
                  bottomRight: _isFromVendor ? Radius.zero : const Radius.circular(Dimensions.paddingSizeSmall),
                ),
                border: _isFromSystem
                    ? Border.all(color: Colors.grey.withValues(alpha: 0.3))
                    : null,
              ),
              child: Column(
                crossAxisAlignment: _isFromVendor ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                children: [
                  Text(
                    _senderLabel(),
                    style: robotoBold.copyWith(
                      fontSize: Dimensions.fontSizeExtraSmall,
                      color: _isFromSystem ? Colors.grey : Theme.of(context).textTheme.bodyLarge?.color,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message.message,
                    style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                  ),
                  if (message.createdAt != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      DateConverter.localDateToIsoStringAMPMOrder(DateTime.parse(message.createdAt!)),
                      style: robotoRegular.copyWith(
                        fontSize: 10,
                        color: Theme.of(context).hintColor,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
