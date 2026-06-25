import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/image_diaglog_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/controllers/dispute_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_evidence_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/widgets/dispute_card_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/widgets/dispute_message_bubble.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

class DisputeDetailScreen extends StatefulWidget {
  final int disputeId;

  const DisputeDetailScreen({super.key, required this.disputeId});

  @override
  State<DisputeDetailScreen> createState() => _DisputeDetailScreenState();
}

class _DisputeDetailScreenState extends State<DisputeDetailScreen> {
  final ScrollController _scrollController = ScrollController();
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<DisputeController>(context, listen: false)
          .loadDispute(widget.disputeId);
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    if (_scrollController.hasClients) {
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
  }

  Future<void> _pickEvidence(DisputeController ctrl) async {
    if (ctrl.selectedFiles.length >= 5) return;

    final String? mediaType = await showModalBottomSheet<String>(
      context: context,
      builder: (sheetContext) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.image_outlined),
                title: Text(
                    getTranslated('select_image', context) ?? 'Select Image'),
                onTap: () => Navigator.pop(sheetContext, 'image'),
              ),
              ListTile(
                leading: const Icon(Icons.videocam_outlined),
                title: Text(
                    getTranslated('select_video', context) ?? 'Select Video'),
                onTap: () => Navigator.pop(sheetContext, 'video'),
              ),
            ],
          ),
        );
      },
    );

    if (mediaType == null) {
      return;
    }

    final XFile? file = mediaType == 'video'
        ? await _picker.pickVideo(source: ImageSource.gallery)
        : await _picker.pickImage(source: ImageSource.gallery);

    if (file != null) {
      ctrl.addFile(file);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(
        title:
            '${getTranslated('dispute', context) ?? 'Dispute'} #${widget.disputeId}',
      ),
      body: Consumer<DisputeController>(
        builder: (context, ctrl, _) {
          if (ctrl.isDetailLoading) {
            return const Center(child: CircularProgressIndicator());
          }
          final dispute = ctrl.selectedDispute;
          if (dispute == null) {
            return Center(
              child: Text(getTranslated('dispute_not_found', context) ??
                  'Dispute not found'),
            );
          }

          WidgetsBinding.instance
              .addPostFrameCallback((_) => _scrollToBottom());

          return Column(
            children: [
              // ── Status & info bar ─────────────────────────────────────────
              _DisputeInfoBar(dispute: dispute, ctrl: ctrl),

              // ── Admin decision ─────────────────────────────────────────────
              if (dispute.adminDecision != null &&
                  dispute.adminDecision!.isNotEmpty)
                _AdminDecisionBanner(
                    decision: dispute.adminDecision!, context: context),

              // ── Pending closure confirmation ──────────────────────────────
              if (dispute.isPendingClosure)
                _PendingClosureBanner(ctrl: ctrl, disputeId: dispute.id),

              // ── Messages thread ───────────────────────────────────────────
              Expanded(
                child: dispute.messages.isEmpty
                    ? Center(
                        child: Text(
                          getTranslated('no_messages_yet', context) ??
                              'No messages yet',
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: Colors.grey),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding:
                            const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        itemCount: dispute.messages.length,
                        itemBuilder: (context, index) => DisputeMessageBubble(
                          message: dispute.messages[index],
                        ),
                      ),
              ),

              // ── Evidence files selected ───────────────────────────────────
              if (ctrl.selectedFiles.isNotEmpty) _SelectedFilesRow(ctrl: ctrl),

              // ── Evidence thumbnails if any ────────────────────────────────
              if (dispute.evidence.isNotEmpty)
                _EvidenceRow(evidence: dispute.evidence),

              // ── Input area (only if dispute is active) ────────────────────
              if (dispute.isActive)
                _MessageInputBar(
                  ctrl: ctrl,
                  disputeId: dispute.id,
                  onPickEvidence: () => _pickEvidence(ctrl),
                  onSendEvidence: () => ctrl.sendEvidence(dispute.id),
                  onEscalate: dispute.canEscalate
                      ? () => _confirmEscalate(ctrl, dispute.id)
                      : null,
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _confirmEscalate(DisputeController ctrl, int disputeId) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
            getTranslated('escalate_dispute', context) ?? 'Escalate Dispute'),
        content: Text(
          getTranslated('escalate_dispute_confirmation', context) ??
              'This will send the dispute to admin for review. Are you sure?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(getTranslated('cancel', context) ?? 'Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(getTranslated('escalate', context) ?? 'Escalate'),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await ctrl.escalateDispute(disputeId);
    }
  }
}

// ── Sub-widgets ─────────────────────────────────────────────────────────────

class _DisputeInfoBar extends StatelessWidget {
  final dynamic dispute;
  final DisputeController ctrl;

  const _DisputeInfoBar({required this.dispute, required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeEight,
      ),
      color: Theme.of(context).cardColor,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${getTranslated('order', context) ?? 'Order'} #${dispute.orderId}',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: Colors.grey),
                ),
                if (dispute.reason != null)
                  Text(
                    dispute.reason!.title,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: Theme.of(context).primaryColor,
                        ),
                  ),
              ],
            ),
          ),
          DisputeStatusBadge(status: dispute.status),
        ],
      ),
    );
  }
}

class _AdminDecisionBanner extends StatelessWidget {
  final String decision;
  final BuildContext context;

  const _AdminDecisionBanner({required this.decision, required this.context});

  @override
  Widget build(BuildContext ctx) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Colors.green.shade50,
        border: Border.all(color: Colors.green.shade300),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeEight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            getTranslated('admin_decision', ctx) ?? 'Admin Decision',
            style: const TextStyle(
                fontWeight: FontWeight.bold, color: Colors.green),
          ),
          const SizedBox(height: 4),
          Text(decision, style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }
}

class _PendingClosureBanner extends StatelessWidget {
  final DisputeController ctrl;
  final int disputeId;

  const _PendingClosureBanner({required this.ctrl, required this.disputeId});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Colors.amber.shade50,
        border: Border.all(color: Colors.amber.shade400),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeEight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            getTranslated('admin_requested_closure', context) ??
                'Admin has requested to close this dispute.',
            style: const TextStyle(fontSize: 13),
          ),
          const SizedBox(height: Dimensions.paddingSizeEight),
          ctrl.isSubmitting
              ? const Center(child: CircularProgressIndicator())
              : CustomButton(
                  buttonText: getTranslated('confirm_closure', context) ??
                      'Confirm Closure',
                  onTap: () => ctrl.confirmClosure(disputeId),
                  radius: Dimensions.paddingSizeExtraSmall,
                ),
        ],
      ),
    );
  }
}

class _SelectedFilesRow extends StatelessWidget {
  final DisputeController ctrl;

  const _SelectedFilesRow({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 80,
      color: Theme.of(context).cardColor,
      padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault, vertical: 8),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: ctrl.selectedFiles.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final selectedFile = ctrl.selectedFiles[index];
          final String lowerPath = selectedFile.path.toLowerCase();
          final String lowerName = selectedFile.name.toLowerCase();
          final bool isVideo =
              lowerPath.endsWith('.mp4') || lowerName.endsWith('.mp4');

          return Stack(
            children: [
              Container(
                width: 64,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(8),
                  color: Colors.grey.shade100,
                ),
                child: Icon(
                  isVideo ? Icons.videocam_outlined : Icons.image_outlined,
                  color: Colors.grey,
                ),
              ),
              Positioned(
                top: 0,
                right: 0,
                child: GestureDetector(
                  onTap: () => ctrl.removeFile(index),
                  child: const CircleAvatar(
                    radius: 10,
                    backgroundColor: Colors.red,
                    child: Icon(Icons.close, size: 12, color: Colors.white),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _EvidenceRow extends StatelessWidget {
  final List<DisputeEvidenceModel> evidence;

  const _EvidenceRow({required this.evidence});

  String _resolveEvidenceUrl(String filePath) {
    final String normalizedPath =
        filePath.trim().replaceFirst(RegExp(r'^/+'), '');

    if (normalizedPath.startsWith('http://') ||
        normalizedPath.startsWith('https://')) {
      return normalizedPath;
    }

    if (normalizedPath.startsWith('storage/')) {
      return '${AppConstants.baseUrl}/$normalizedPath';
    }

    return '${AppConstants.baseUrl}/storage/$normalizedPath';
  }

  Future<void> _openEvidence(
      BuildContext context, DisputeEvidenceModel evidenceItem) async {
    final String evidenceUrl = _resolveEvidenceUrl(evidenceItem.filePath);

    if (evidenceItem.isImage) {
      showDialog(
        context: context,
        builder: (_) => ImageDialog(imageUrl: evidenceUrl),
      );

      return;
    }

    await launchUrl(Uri.parse(evidenceUrl),
        mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 96,
      padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault, vertical: 8),
      child: Row(
        children: [
          Text(
            '${getTranslated('evidence', context) ?? 'Evidence'}: ',
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12),
          ),
          Expanded(
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: evidence.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final ev = evidence[index];
                final String evidenceUrl = _resolveEvidenceUrl(ev.filePath);

                return InkWell(
                  onTap: () => _openEvidence(context, ev),
                  borderRadius: BorderRadius.circular(8),
                  child: Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(8),
                      color: Colors.grey.shade100,
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: ev.isImage
                          ? CustomImageWidget(
                              image: evidenceUrl,
                              width: 64,
                              height: 64,
                              fit: BoxFit.cover,
                            )
                          : Stack(
                              fit: StackFit.expand,
                              children: [
                                Container(color: Colors.black12),
                                const Icon(
                                  Icons.play_circle_fill_rounded,
                                  color: Colors.black54,
                                  size: 28,
                                ),
                              ],
                            ),
                    ),
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

class _MessageInputBar extends StatelessWidget {
  final DisputeController ctrl;
  final int disputeId;
  final VoidCallback onPickEvidence;
  final VoidCallback onSendEvidence;
  final VoidCallback? onEscalate;

  const _MessageInputBar({
    required this.ctrl,
    required this.disputeId,
    required this.onPickEvidence,
    required this.onSendEvidence,
    this.onEscalate,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Theme.of(context).cardColor,
      padding: const EdgeInsets.only(
        left: Dimensions.paddingSizeDefault,
        right: Dimensions.paddingSizeDefault,
        bottom: Dimensions.paddingSizeDefault,
        top: Dimensions.paddingSizeEight,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (onEscalate != null)
            Padding(
              padding:
                  const EdgeInsets.only(bottom: Dimensions.paddingSizeEight),
              child: OutlinedButton.icon(
                onPressed: ctrl.isSubmitting ? null : onEscalate,
                icon: const Icon(Icons.escalator_warning_outlined, size: 16),
                label: Text(getTranslated('escalate_to_admin', context) ??
                    'Escalate to Admin'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.orange,
                  side: const BorderSide(color: Colors.orange),
                ),
              ),
            ),
          Row(
            children: [
              IconButton(
                onPressed: ctrl.isUploadingEvidence ? null : onPickEvidence,
                icon: Icon(
                  Icons.attach_file,
                  color: ctrl.selectedFiles.length >= 5
                      ? Colors.grey
                      : Theme.of(context).primaryColor,
                ),
                tooltip:
                    getTranslated('add_evidence', context) ?? 'Add Evidence',
              ),
              if (ctrl.selectedFiles.isNotEmpty)
                IconButton(
                  onPressed: ctrl.isUploadingEvidence ? null : onSendEvidence,
                  icon: ctrl.isUploadingEvidence
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : Icon(Icons.upload,
                          color: Theme.of(context).primaryColor),
                  tooltip: getTranslated('upload_evidence', context) ??
                      'Upload Evidence',
                ),
              Expanded(
                child: TextField(
                  controller: ctrl.messageTextController,
                  maxLines: 3,
                  minLines: 1,
                  textInputAction: TextInputAction.newline,
                  decoration: InputDecoration(
                    hintText: getTranslated('type_message', context) ??
                        'Type a message...',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: BorderSide(color: Colors.grey.shade300),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 10),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeEight),
              ctrl.isSubmitting
                  ? const SizedBox(
                      width: 40,
                      height: 40,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : CircleAvatar(
                      backgroundColor: Theme.of(context).primaryColor,
                      child: IconButton(
                        onPressed: () => ctrl.sendMessage(disputeId),
                        icon: const Icon(Icons.send,
                            color: Colors.white, size: 20),
                      ),
                    ),
            ],
          ),
        ],
      ),
    );
  }
}
