import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_image_widget.dart';
import 'package:sixvalley_vendor_app/features/dispute/controllers/dispute_controller.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_evidence_model.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_message_model.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_model.dart';
import 'package:sixvalley_vendor_app/features/dispute/widgets/dispute_attachment_bottom_sheet.dart';
import 'package:sixvalley_vendor_app/features/dispute/widgets/dispute_evidence_bubble.dart';
import 'package:sixvalley_vendor_app/features/dispute/widgets/dispute_message_bubble.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class _DisputeTimelineItem {
  final DateTime? sortDate;
  final DisputeMessageModel? message;
  final DisputeEvidenceModel? evidence;

  const _DisputeTimelineItem({
    this.sortDate,
    this.message,
    this.evidence,
  });
}

class DisputeDetailScreen extends StatefulWidget {
  final int disputeId;
  const DisputeDetailScreen({super.key, required this.disputeId});

  @override
  State<DisputeDetailScreen> createState() => _DisputeDetailScreenState();
}

class _DisputeDetailScreenState extends State<DisputeDetailScreen> {
  final ImagePicker _picker = ImagePicker();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    await Provider.of<DisputeController>(context, listen: false).getDisputeDetail(widget.disputeId);
    _scrollToBottom();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  void dispose() {
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

  List<_DisputeTimelineItem> _buildTimeline(DisputeModel dispute) {
    final items = <_DisputeTimelineItem>[];

    for (final message in dispute.messages) {
      items.add(_DisputeTimelineItem(
        sortDate: message.createdAt != null ? DateTime.tryParse(message.createdAt!) : null,
        message: message,
      ));
    }

    for (final evidence in dispute.evidence) {
      items.add(_DisputeTimelineItem(
        sortDate: evidence.createdAt != null ? DateTime.tryParse(evidence.createdAt!) : null,
        evidence: evidence,
      ));
    }

    items.sort((a, b) {
      if (a.sortDate == null && b.sortDate == null) {
        return 0;
      }
      if (a.sortDate == null) {
        return 1;
      }
      if (b.sortDate == null) {
        return -1;
      }

      return a.sortDate!.compareTo(b.sortDate!);
    });

    return items;
  }

  void _openImageViewer(String imageUrl) {
    showDialog(
      context: context,
      barrierColor: Colors.black87,
      builder: (dialogContext) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
        child: Stack(
          children: [
            InteractiveViewer(
              child: CustomImageWidget(
                image: imageUrl,
                fit: BoxFit.contain,
                height: MediaQuery.of(context).size.height * 0.8,
                width: MediaQuery.of(context).size.width,
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                onPressed: () => Navigator.pop(dialogContext),
                icon: const Icon(Icons.close, color: Colors.white, size: 28),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickFiles() async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) => DisputeAttachmentBottomSheet(
        onCameraTap: () async {
          Navigator.pop(sheetContext);
          final file = await _picker.pickImage(source: ImageSource.camera);
          if (file != null && mounted) {
            Provider.of<DisputeController>(context, listen: false).selectFile(file);
          }
        },
        onGalleryTap: () async {
          Navigator.pop(sheetContext);
          final files = await _picker.pickMultiImage();
          if (!mounted) {
            return;
          }
          final controller = Provider.of<DisputeController>(context, listen: false);
          for (final file in files) {
            controller.selectFile(file);
          }
        },
      ),
    );
  }

  Widget _buildPendingFilesPreview(DisputeController controller, DisputeModel dispute) {
    if (controller.selectedFiles.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.fromLTRB(
        Dimensions.paddingSizeSmall,
        Dimensions.paddingSizeSmall,
        Dimensions.paddingSizeSmall,
        0,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        border: Border(top: BorderSide(color: Theme.of(context).hintColor.withValues(alpha: 0.15))),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                '${controller.selectedFiles.length} ${getTranslated('files_selected', context) ?? 'files selected'}',
                style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
              ),
              const Spacer(),
              TextButton(
                onPressed: controller.isUploading ? null : () => controller.clearFiles(),
                child: Text(getTranslated('clear', context) ?? 'Clear'),
              ),
            ],
          ),
          SizedBox(
            height: 72,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: controller.selectedFiles.length,
              separatorBuilder: (_, __) => const SizedBox(width: Dimensions.paddingSizeExtraSmall),
              itemBuilder: (context, index) {
                final file = controller.selectedFiles[index];

                return Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                      child: Image.file(
                        File(file.path),
                        width: 72,
                        height: 72,
                        fit: BoxFit.cover,
                      ),
                    ),
                    Positioned(
                      top: 2,
                      right: 2,
                      child: InkWell(
                        onTap: controller.isUploading ? null : () => controller.removeFile(index),
                        child: Container(
                          padding: const EdgeInsets.all(2),
                          decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                          child: const Icon(Icons.close, color: Colors.white, size: 14),
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          CustomButtonWidget(
            btnTxt: getTranslated('submit_evidence', context) ?? 'Submit Evidence',
            isLoading: controller.isUploading,
            onTap: () async {
              await controller.uploadEvidence(dispute.id);
              _scrollToBottom();
            },
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(getTranslated('dispute_details', context) ?? 'Dispute Details'),
      ),
      body: Consumer<DisputeController>(
        builder: (context, controller, _) {
          if (controller.isDetailLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          final dispute = controller.selectedDispute;
          if (dispute == null) {
            return Center(child: Text(getTranslated('dispute_not_found', context) ?? 'Dispute not found'));
          }

          final color = _statusColor(dispute.status);
          final timeline = _buildTimeline(dispute);
          final int extraItems = dispute.adminDecision != null ? 1 : 0;

          return Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.06),
                  border: Border(bottom: BorderSide(color: color.withValues(alpha: 0.15))),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          '${getTranslated('dispute', context) ?? 'Dispute'} #${dispute.id}',
                          style: robotoBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                        ),
                        const SizedBox(width: Dimensions.paddingSizeSmall),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: 2),
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            dispute.statusLabel,
                            style: robotoBold.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: color),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Dimensions.paddingSizeSmall),
                    Text(
                      '${getTranslated('order', context) ?? 'Order'} #${dispute.orderId}  |  ${dispute.reason?.title ?? ''}',
                      style: robotoRegular.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).hintColor,
                      ),
                    ),
                    if (dispute.description.isNotEmpty) ...[
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      Text(
                        dispute.description,
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                      ),
                    ],
                    if (dispute.isUnderReview || dispute.isResolved) ...[
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      Container(
                        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: dispute.isUnderReview ? Colors.blue.withValues(alpha: 0.06) : Colors.green.withValues(alpha: 0.06),
                          borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                        ),
                        child: Text(
                          dispute.isUnderReview
                              ? (getTranslated('under_admin_review', context) ?? 'Under admin review — all actions are disabled.')
                              : (getTranslated('dispute_resolved', context) ?? 'This dispute has been resolved.'),
                          style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                        ),
                      ),
                    ],
                  ],
                ),
              ),

              Expanded(
                child: ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeSmall,
                    vertical: Dimensions.paddingSizeSmall,
                  ),
                  itemCount: timeline.length + extraItems,
                  itemBuilder: (context, index) {
                    if (index < timeline.length) {
                      final item = timeline[index];

                      if (item.message != null) {
                        return DisputeMessageBubble(message: item.message!);
                      }

                      if (item.evidence != null) {
                        return DisputeEvidenceBubble(
                          evidence: item.evidence!,
                          onTap: item.evidence!.isImage
                              ? () => _openImageViewer(item.evidence!.fullFileUrl)
                              : null,
                        );
                      }
                    }

                    if (dispute.adminDecision != null) {
                      return Container(
                        margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
                        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: Colors.green.withValues(alpha: 0.06),
                          borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                          border: Border.all(color: Colors.green.withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              getTranslated('admin_decision', context) ?? 'Admin Decision',
                              style: robotoBold.copyWith(fontSize: Dimensions.fontSizeSmall, color: Colors.green),
                            ),
                            const SizedBox(height: 4),
                            Text(dispute.adminDecision!, style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
                          ],
                        ),
                      );
                    }

                    return const SizedBox.shrink();
                  },
                ),
              ),

              _buildPendingFilesPreview(controller, dispute),

              if (dispute.canRespond)
                Container(
                  padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    boxShadow: [
                      BoxShadow(
                        color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                        blurRadius: 3,
                        offset: const Offset(0, -1),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: controller.messageController,
                              decoration: InputDecoration(
                                hintText: getTranslated('type_your_message', context) ?? 'Type your message...',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                ),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                isDense: true,
                              ),
                              maxLines: 3,
                              minLines: 1,
                            ),
                          ),
                          const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                          InkWell(
                            onTap: controller.isSubmitting
                                ? null
                                : () async {
                                    await controller.sendMessage(dispute.id);
                                    _scrollToBottom();
                                  },
                            child: Container(
                              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                              decoration: BoxDecoration(
                                color: Theme.of(context).primaryColor,
                                borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                              ),
                              child: controller.isSubmitting
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Icon(Icons.send, color: Colors.white, size: 20),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _pickFiles,
                              icon: const Icon(Icons.attach_file, size: 18),
                              label: Text(getTranslated('attach_files', context) ?? 'Attach Files'),
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
                              ),
                            ),
                          ),
                          const SizedBox(width: Dimensions.paddingSizeSmall),
                          Expanded(
                            child: CustomButtonWidget(
                              btnTxt: getTranslated('escalate_to_admin', context) ?? 'Escalate to Admin',
                              isLoading: controller.isSubmitting,
                              onTap: () => controller.escalateDispute(dispute.id),
                              backgroundColor: Colors.red.shade600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
