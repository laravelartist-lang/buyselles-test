import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/controllers/dispute_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_reason_model.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

class OpenDisputeScreen extends StatefulWidget {
  final int orderId;

  const OpenDisputeScreen({super.key, required this.orderId});

  @override
  State<OpenDisputeScreen> createState() => _OpenDisputeScreenState();
}

class _OpenDisputeScreenState extends State<OpenDisputeScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _descriptionController = TextEditingController();
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctrl = Provider.of<DisputeController>(context, listen: false);
      ctrl.clearFiles();
      ctrl.setSelectedReason(null);
      ctrl.loadReasons();
    });
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final ctrl = Provider.of<DisputeController>(context, listen: false);
    final int? disputeId = await ctrl.createDispute(
      orderId: widget.orderId,
      description: _descriptionController.text.trim(),
      files: ctrl.selectedFiles,
    );
    if (!mounted) {
      return;
    }

    if (disputeId != null && disputeId > 0) {
      RouterHelper.getDisputeDetailRoute(
        disputeId: disputeId,
        action: RouteAction.pushReplacement,
      );
      return;
    }

    RouterHelper.getDisputeListRoute(action: RouteAction.pushReplacement);
  }

  Future<void> _pickEvidence(DisputeController ctrl) async {
    if (ctrl.selectedFiles.length >= 5) {
      return;
    }

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

  Future<void> _pickReason(DisputeController ctrl) async {
    final List<DisputeReasonModel> reasons = ctrl.reasons ?? [];
    if (reasons.isEmpty) {
      await ctrl.loadReasons();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              getTranslated('something_went_wrong', context) ??
                  'Unable to load dispute reasons',
            ),
          ),
        );
      }
      return;
    }

    final DisputeReasonModel? selected =
        await showModalBottomSheet<DisputeReasonModel>(
      context: context,
      builder: (sheetContext) {
        return SafeArea(
          child: ListView.separated(
            shrinkWrap: true,
            itemCount: reasons.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, index) {
              final reason = reasons[index];
              final bool isSelected = ctrl.selectedReason?.id == reason.id;

              return ListTile(
                title: Text(reason.title),
                trailing: isSelected
                    ? Icon(
                        Icons.check,
                        color: Theme.of(context).primaryColor,
                      )
                    : null,
                onTap: () => Navigator.pop(sheetContext, reason),
              );
            },
          ),
        );
      },
    );

    if (selected != null) {
      ctrl.setSelectedReason(selected);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(
        title: getTranslated('open_dispute', context) ?? 'Open Dispute',
      ),
      body: Consumer<DisputeController>(
        builder: (context, ctrl, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Order info
                  Container(
                    padding:
                        const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    decoration: BoxDecoration(
                      color: Theme.of(context)
                          .primaryColor
                          .withValues(alpha: 0.08),
                      borderRadius:
                          BorderRadius.circular(Dimensions.paddingSizeEight),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.receipt_outlined,
                            color: Theme.of(context).primaryColor),
                        const SizedBox(width: Dimensions.paddingSizeEight),
                        Text(
                          '${getTranslated('order', context) ?? 'Order'} #${widget.orderId}',
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.bold,
                                    color: Theme.of(context).primaryColor,
                                  ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Reason dropdown
                  Text(
                    getTranslated('dispute_reason', context) ??
                        'Reason (Optional)',
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeEight),
                  ctrl.reasons == null
                      ? const LinearProgressIndicator()
                      : InkWell(
                          onTap: () => _pickReason(ctrl),
                          borderRadius: BorderRadius.circular(
                              Dimensions.paddingSizeEight),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: Dimensions.paddingSizeDefault,
                              vertical: Dimensions.paddingSizeDefault,
                            ),
                            decoration: BoxDecoration(
                              border: Border.all(
                                color: Theme.of(context).dividerColor,
                              ),
                              borderRadius: BorderRadius.circular(
                                  Dimensions.paddingSizeEight),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    ctrl.selectedReason?.title ??
                                        (getTranslated(
                                                'select_reason', context) ??
                                            'Select a reason'),
                                    style:
                                        Theme.of(context).textTheme.bodySmall,
                                  ),
                                ),
                                const Icon(Icons.keyboard_arrow_down_rounded),
                              ],
                            ),
                          ),
                        ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Description
                  Text(
                    getTranslated('description', context) ?? 'Description',
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeEight),
                  TextFormField(
                    controller: _descriptionController,
                    maxLines: 6,
                    maxLength: 2000,
                    decoration: InputDecoration(
                      hintText: getTranslated('describe_your_issue', context) ??
                          'Describe your issue in detail (minimum 20 characters)...',
                      border: OutlineInputBorder(
                        borderRadius:
                            BorderRadius.circular(Dimensions.paddingSizeEight),
                      ),
                      alignLabelWithHint: true,
                    ),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return getTranslated(
                                'description_is_required', context) ??
                            'Description is required';
                      }
                      if (value.trim().length < 20) {
                        return getTranslated(
                                'description_must_be_at_least_20_characters',
                                context) ??
                            'Description must be at least 20 characters';
                      }
                      return null;
                    },
                  ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Evidence upload
                  Text(
                    getTranslated('upload_evidence', context) ??
                        'Upload Evidence',
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeEight),
                  OutlinedButton.icon(
                    onPressed: () => _pickEvidence(ctrl),
                    icon: const Icon(Icons.attach_file),
                    label: Text(
                      getTranslated('add_evidence', context) ?? 'Add Evidence',
                    ),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                  Text(
                    getTranslated('accepted_jpg_png_mp4', context) ??
                        'Accepted: JPG, PNG, MP4 (max 5 files)',
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: Theme.of(context).hintColor),
                  ),

                  if (ctrl.selectedFiles.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(
                          top: Dimensions.paddingSizeSmall),
                      child: _OpenDisputeSelectedFilesRow(ctrl: ctrl),
                    ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Warning note
                  Container(
                    padding:
                        const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    decoration: BoxDecoration(
                      color: Colors.amber.shade50,
                      border: Border.all(color: Colors.amber.shade300),
                      borderRadius:
                          BorderRadius.circular(Dimensions.paddingSizeEight),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.warning_amber_rounded,
                            color: Colors.amber.shade700, size: 18),
                        const SizedBox(width: Dimensions.paddingSizeEight),
                        Expanded(
                          child: Text(
                            getTranslated('dispute_warning_note', context) ??
                                'Opening a dispute will freeze the escrow funds until the dispute is resolved.',
                            style: TextStyle(
                                fontSize: 12, color: Colors.amber.shade800),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Submit button
                  ctrl.isSubmitting
                      ? const Center(child: CircularProgressIndicator())
                      : CustomButton(
                          buttonText: getTranslated('open_dispute', context) ??
                              'Open Dispute',
                          onTap: _submit,
                          radius: Dimensions.paddingSizeEight,
                        ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _OpenDisputeSelectedFilesRow extends StatelessWidget {
  final DisputeController ctrl;

  const _OpenDisputeSelectedFilesRow({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 80,
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
