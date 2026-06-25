import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/controllers/digital_files_controller.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/product_model.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DigitalProductFilesScreen extends StatefulWidget {
  final Product product;

  const DigitalProductFilesScreen({super.key, required this.product});

  @override
  State<DigitalProductFilesScreen> createState() => _DigitalProductFilesScreenState();
}

class _DigitalProductFilesScreenState extends State<DigitalProductFilesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<DigitalFilesController>(context, listen: false)
          .loadProductFileInfo(widget.product.id!);
    });
  }

  Future<void> _pickAndUpload() async {
    final List<String> disallowed = AppConstants.disallowedExtensions;

    final result = await FilePicker.platform.pickFiles(
      allowMultiple: false,
      type: FileType.any,
    );

    if (result == null || result.files.isEmpty) return;

    final file = result.files.first;
    final ext = (file.extension ?? '').toLowerCase();

    if (disallowed.contains(ext)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${getTranslated('file_type_not_allowed', context) ?? 'File type not allowed'}: .$ext'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    if (!mounted) return;
    await Provider.of<DigitalFilesController>(context, listen: false)
        .uploadFile(widget.product.id!, file.path!);
  }

  Future<void> _confirmDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        title: Text(getTranslated('delete_file', context) ?? 'Delete File', style: titilliumSemiBold),
        content: Text(
          getTranslated('delete_file_confirmation', context) ??
              'Are you sure you want to delete this digital file? This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(getTranslated('cancel', context) ?? 'Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(
              getTranslated('delete', context) ?? 'Delete',
              style: const TextStyle(color: Colors.red),
            ),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      await Provider.of<DigitalFilesController>(context, listen: false)
          .deleteFile(widget.product.id!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('digital_files', context) ?? 'Digital Files',
        isBackButtonExist: true,
        isAction: false,
      ),
      body: Consumer<DigitalFilesController>(
        builder: (context, controller, _) {
          if (controller.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Product name header
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                  decoration: BoxDecoration(
                    color: Theme.of(context).primaryColor.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                    border: Border.all(color: Theme.of(context).primaryColor.withOpacity(0.2)),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.inventory_2_outlined, color: Theme.of(context).primaryColor, size: 20),
                      const SizedBox(width: Dimensions.paddingSizeSmall),
                      Expanded(
                        child: Text(
                          widget.product.name ?? '',
                          style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeLarge),

                Text(
                  getTranslated('downloadable_file', context) ?? 'Downloadable File',
                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),

                if (controller.hasFile) ...[
                  // File card
                  Container(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    decoration: BoxDecoration(
                      color: Theme.of(context).cardColor,
                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                      border: Border.all(color: Colors.green.withOpacity(0.4)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.04),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: Colors.green.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(Icons.insert_drive_file_outlined, color: Colors.green, size: 26),
                        ),
                        const SizedBox(width: Dimensions.paddingSizeDefault),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                controller.currentFileName ?? 'Digital file',
                                style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeSmall),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Icon(Icons.check_circle, color: Colors.green, size: 14),
                                  const SizedBox(width: 4),
                                  Text(
                                    getTranslated('active', context) ?? 'Active',
                                    style: robotoRegular.copyWith(
                                      color: Colors.green,
                                      fontSize: Dimensions.fontSizeExtraSmall,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        // Delete button
                        controller.isDeleting
                            ? const SizedBox(
                                width: 24,
                                height: 24,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : IconButton(
                                onPressed: _confirmDelete,
                                icon: const Icon(Icons.delete_outline, color: Colors.red),
                                tooltip: getTranslated('delete', context) ?? 'Delete',
                              ),
                      ],
                    ),
                  ),

                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  // Replace file button
                  Text(
                    getTranslated('replace_file', context) ?? 'Replace File',
                    style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),
                  Text(
                    getTranslated('replace_file_hint', context) ??
                        'Uploading a new file will replace the existing one.',
                    style: robotoRegular.copyWith(
                      color: Theme.of(context).hintColor,
                      fontSize: Dimensions.fontSizeSmall,
                    ),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                ] else ...[
                  // Empty state
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      vertical: Dimensions.paddingSizeExtraLarge,
                      horizontal: Dimensions.paddingSizeDefault,
                    ),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.secondaryContainer.withOpacity(0.5),
                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                      border: Border.all(
                        color: Theme.of(context).dividerColor,
                        style: BorderStyle.solid,
                      ),
                    ),
                    child: Column(
                      children: [
                        Icon(
                          Icons.cloud_upload_outlined,
                          size: 60,
                          color: Theme.of(context).hintColor,
                        ),
                        const SizedBox(height: Dimensions.paddingSizeSmall),
                        Text(
                          getTranslated('no_digital_file', context) ?? 'No digital file uploaded yet',
                          style: titilliumSemiBold.copyWith(
                            color: Theme.of(context).hintColor,
                            fontSize: Dimensions.fontSizeDefault,
                          ),
                        ),
                        const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                        Text(
                          getTranslated('upload_digital_file_hint', context) ??
                              'Upload a file that customers can download after purchase.',
                          textAlign: TextAlign.center,
                          style: robotoRegular.copyWith(
                            color: Theme.of(context).hintColor,
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeLarge),
                ],

                // Upload button
                controller.isUploading
                    ? const Center(child: CircularProgressIndicator())
                    : CustomButtonWidget(
                        btnTxt: controller.hasFile
                            ? (getTranslated('replace_file', context) ?? 'Replace File')
                            : (getTranslated('upload_file', context) ?? 'Upload File'),
                        onTap: _pickAndUpload,
                      ),

                const SizedBox(height: Dimensions.paddingSizeLarge),

                // Allowed formats info
                Container(
                  padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                  decoration: BoxDecoration(
                    color: Colors.amber.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.amber.withOpacity(0.3)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.info_outline, color: Colors.amber, size: 18),
                      const SizedBox(width: Dimensions.paddingSizeSmall),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              getTranslated('not_allowed_extensions', context) ?? 'Disallowed file types:',
                              style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeSmall),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              AppConstants.disallowedExtensions.map((e) => '.$e').join(', '),
                              style: robotoRegular.copyWith(
                                fontSize: Dimensions.fontSizeExtraSmall,
                                color: Theme.of(context).hintColor,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
