import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/controllers/digital_code_controller.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DigitalCodeBulkImportScreen extends StatefulWidget {
  const DigitalCodeBulkImportScreen({super.key});

  @override
  State<DigitalCodeBulkImportScreen> createState() => _DigitalCodeBulkImportScreenState();
}

class _DigitalCodeBulkImportScreenState extends State<DigitalCodeBulkImportScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('digital_code_bulk_import', context) ?? 'Digital Code Bulk Import',
        isBackButtonExist: true,
        isAction: false,
      ),
      body: Consumer<DigitalCodeController>(
        builder: (context, controller, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Info header
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
                      Icon(Icons.info_outline, color: Theme.of(context).primaryColor),
                      const SizedBox(width: Dimensions.paddingSizeSmall),
                      Expanded(
                        child: Text(
                          getTranslated('bulk_import_info', context) ??
                              'Upload an Excel file with digital codes. Supports bulk import and per-product import.',
                          style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeLarge),

                // Step 1: Download Template
                _buildStepCard(
                  context: context,
                  stepNumber: '1',
                  title: getTranslated('download_template', context) ?? 'Download Template',
                  color: Colors.blue,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        getTranslated('download_template_desc', context) ??
                            'Download the Excel template, fill in your codes, and upload it back.',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: controller.isLoading
                              ? null
                              : () => controller.downloadBulkTemplate(),
                          icon: controller.isLoading
                              ? const SizedBox(
                                  width: 16, height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.download),
                          label: Text(
                            getTranslated('download_excel_template', context) ??
                                'Download Excel Template',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),

                // Step 2: Upload File
                _buildStepCard(
                  context: context,
                  stepNumber: '2',
                  title: getTranslated('upload_filled_file', context) ?? 'Upload Filled File',
                  color: Colors.green,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        getTranslated('upload_desc', context) ??
                            'Select the filled Excel file (xlsx, xls, csv). Max size: 10MB.',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: Colors.amber.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.amber.withOpacity(0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline, color: Colors.amber, size: 18),
                            const SizedBox(width: Dimensions.paddingSizeSmall),
                            Expanded(
                              child: Text(
                                getTranslated('import_background_info', context) ??
                                    'The import runs in the background. You will be notified when complete.',
                                style: robotoRegular.copyWith(
                                  fontSize: Dimensions.fontSizeExtraSmall,
                                  color: Theme.of(context).hintColor,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: controller.isUploading
                              ? null
                              : () => _pickAndUpload(controller),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          icon: controller.isUploading
                              ? const SizedBox(
                                  width: 18, height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white,
                                  ),
                                )
                              : const Icon(Icons.upload_file),
                          label: Text(
                            controller.isUploading
                                ? (getTranslated('uploading', context) ?? 'Uploading...')
                                : (getTranslated('upload_start_import', context) ?? 'Upload & Start Import'),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeLarge),

                // Format Reference
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                    border: Border.all(color: Theme.of(context).dividerColor),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.table_chart, color: Theme.of(context).primaryColor),
                          const SizedBox(width: Dimensions.paddingSizeSmall),
                          Text(
                            getTranslated('expected_file_format', context) ?? 'Expected File Format',
                            style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                          ),
                        ],
                      ),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      const Divider(),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      // Column headers table
                      _buildColumnHeader(context, 'product_id', 'Product ID', true),
                      _buildColumnHeader(context, 'product_name', 'Product Name', true),
                      _buildColumnHeader(context, 'price', 'Price', false),
                      _buildColumnHeader(context, 'category_id', 'Category ID', false),
                      _buildColumnHeader(context, 'pin', 'PIN / Code', true),
                      _buildColumnHeader(context, 'serial_number', 'Serial Number', false),
                      _buildColumnHeader(context, 'expiry_date', 'Expiry Date', false),

                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      const Divider(),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      Text(
                        getTranslated('format_note', context) ??
                            '• Existing product: fill product_name + pin\n'
                            '• New product: fill product_name + price + category_id + pin\n'
                            '• Serial number and expiry date are optional\n'
                            '• Expiry date format: YYYY-MM-DD',
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
          );
        },
      ),
    );
  }

  Widget _buildStepCard({
    required BuildContext context,
    required String stepNumber,
    required String title,
    required Color color,
    required Widget child,
  }) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            decoration: BoxDecoration(
              color: color.withOpacity(0.08),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(Dimensions.paddingSizeSmall - 1),
                topRight: Radius.circular(Dimensions.paddingSizeSmall - 1),
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 28, height: 28,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      stepNumber,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                Text(
                  title,
                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: child,
          ),
        ],
      ),
    );
  }

  Widget _buildColumnHeader(BuildContext context, String key, String label, bool required) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
            ),
          ),
          if (required)
            Text(
              getTranslated('required', context) ?? 'Required',
              style: const TextStyle(color: Colors.red, fontSize: 11),
            )
          else
            Text(
              getTranslated('optional', context) ?? 'Optional',
              style: TextStyle(color: Theme.of(context).hintColor, fontSize: 11),
            ),
        ],
      ),
    );
  }

  Future<void> _pickAndUpload(DigitalCodeController controller) async {
    final result = await controller.pickExcelFile();
    if (result != null && result.files.isNotEmpty) {
      final filePath = result.files.first.path;
      if (filePath != null) {
        await controller.uploadBulkImport(filePath);
      }
    }
  }
}
