import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_image_widget.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_evidence_model.dart';
import 'package:sixvalley_vendor_app/helper/date_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DisputeEvidenceBubble extends StatelessWidget {
  final DisputeEvidenceModel evidence;
  final VoidCallback? onTap;

  const DisputeEvidenceBubble({
    super.key,
    required this.evidence,
    this.onTap,
  });

  bool get _isFromVendor => evidence.isFromVendor;

  String _senderLabel(BuildContext context) {
    switch (evidence.userType) {
      case 'vendor':
        return getTranslated('you', context) ?? 'You';
      case 'buyer':
        return getTranslated('buyer', context) ?? 'Buyer';
      case 'admin':
        return getTranslated('admin', context) ?? 'Admin';
      default:
        return evidence.userType;
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
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
              decoration: BoxDecoration(
                color: _isFromVendor
                    ? Theme.of(context).primaryColor.withValues(alpha: 0.08)
                    : Theme.of(context).cardColor,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(Dimensions.paddingSizeSmall),
                  topRight: const Radius.circular(Dimensions.paddingSizeSmall),
                  bottomLeft: _isFromVendor ? const Radius.circular(Dimensions.paddingSizeSmall) : Radius.zero,
                  bottomRight: _isFromVendor ? Radius.zero : const Radius.circular(Dimensions.paddingSizeSmall),
                ),
                border: Border.all(
                  color: _isFromVendor ? Colors.blue.withValues(alpha: 0.25) : Colors.orange.withValues(alpha: 0.25),
                ),
              ),
              child: Column(
                crossAxisAlignment: _isFromVendor ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      Dimensions.paddingSizeSmall,
                      Dimensions.paddingSizeSmall,
                      Dimensions.paddingSizeSmall,
                      Dimensions.paddingSizeExtraSmall,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          evidence.isVideo ? Icons.videocam : Icons.image,
                          size: 14,
                          color: Theme.of(context).hintColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${_senderLabel(context)} • ${getTranslated('evidence', context) ?? 'Evidence'}',
                          style: robotoBold.copyWith(fontSize: Dimensions.fontSizeExtraSmall),
                        ),
                      ],
                    ),
                  ),
                  GestureDetector(
                    onTap: onTap,
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                      child: evidence.isVideo
                          ? Container(
                              width: double.infinity,
                              height: 160,
                              color: Colors.black12,
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.play_circle_outline, size: 48),
                                  const SizedBox(height: 4),
                                  Text(
                                    getTranslated('video_evidence', context) ?? 'Video evidence',
                                    style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                                  ),
                                ],
                              ),
                            )
                          : CustomImageWidget(
                              image: evidence.fullFileUrl,
                              height: 180,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            ),
                    ),
                  ),
                  if (evidence.caption != null && evidence.caption!.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                      child: Text(
                        evidence.caption!,
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                      ),
                    ),
                  if (evidence.createdAt != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(
                        Dimensions.paddingSizeSmall,
                        0,
                        Dimensions.paddingSizeSmall,
                        Dimensions.paddingSizeSmall,
                      ),
                      child: Text(
                        DateConverter.localDateToIsoStringAMPMOrder(DateTime.parse(evidence.createdAt!)),
                        style: robotoRegular.copyWith(fontSize: 10, color: Theme.of(context).hintColor),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
