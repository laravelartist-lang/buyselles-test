import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/controllers/digital_code_controller.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/product_model.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DigitalCodeProductImportScreen extends StatefulWidget {
  final Product product;

  const DigitalCodeProductImportScreen({super.key, required this.product});

  @override
  State<DigitalCodeProductImportScreen> createState() => _DigitalCodeProductImportScreenState();
}

class _DigitalCodeProductImportScreenState extends State<DigitalCodeProductImportScreen> {
  final _codeController = TextEditingController();
  final _serialController = TextEditingController();
  final _expiryController = TextEditingController();

  @override
  void dispose() {
    _codeController.dispose();
    _serialController.dispose();
    _expiryController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: '${getTranslated('upload_codes', context) ?? 'Upload Codes'}',
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

                // Step 1: Download template
                _buildCard(
                  context: context,
                  title: getTranslated('download_template', context) ?? 'Download Template',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        getTranslated('download_product_template_desc', context) ??
                            'Download the pre-filled template, add codes, and upload back.',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      // Table format info
                      Container(
                        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: Theme.of(context).disabledColor.withOpacity(0.05),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Column(
                          children: [
                            _buildFormatRow(context, getTranslated('pin', context) ?? 'PIN / Code', true, 'ABCD-1234-EFGH'),
                            _buildFormatRow(context, getTranslated('serial_number', context) ?? 'Serial Number', false, 'SN-00123'),
                            _buildFormatRow(context, getTranslated('expiry_date', context) ?? 'Expiry Date', true, '2026-12-31'),
                          ],
                        ),
                      ),

                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: controller.isLoading
                              ? null
                              : () => controller.downloadProductTemplate(widget.product.id!),
                          icon: controller.isLoading
                              ? const SizedBox(
                                  width: 16, height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.download),
                          label: Text(
                            getTranslated('download_excel_template', context) ?? 'Download Excel Template',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),

                // Step 2: Upload file
                _buildCard(
                  context: context,
                  title: getTranslated('upload_filled_file', context) ?? 'Upload Filled File',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        getTranslated('select_excel_file', context) ?? 'Select Excel / CSV file.',
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).hintColor,
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
                                ? (getTranslated('importing', context) ?? 'Importing...')
                                : (getTranslated('upload_import_now', context) ?? 'Upload & Import Now'),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeLarge),

                // Step 3: Manual entry
                _buildCard(
                  context: context,
                  title: getTranslated('add_single_code', context) ?? 'Add Single Code Manually',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Code field
                      TextField(
                        controller: _codeController,
                        decoration: InputDecoration(
                          labelText: getTranslated('card_code', context) ?? 'Card Code *',
                          hintText: getTranslated('enter_code', context) ?? 'Enter digital code',
                          border: const OutlineInputBorder(),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: Dimensions.paddingSizeDefault,
                            vertical: Dimensions.paddingSizeSmall,
                          ),
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      // Serial number field
                      TextField(
                        controller: _serialController,
                        decoration: InputDecoration(
                          labelText: '${getTranslated('serial_number', context) ?? 'Serial Number'} (${getTranslated('optional', context) ?? 'Optional'})',
                          hintText: getTranslated('enter_serial', context) ?? 'Enter serial number',
                          border: const OutlineInputBorder(),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: Dimensions.paddingSizeDefault,
                            vertical: Dimensions.paddingSizeSmall,
                          ),
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      // Expiry date field
                      TextField(
                        controller: _expiryController,
                        readOnly: true,
                        decoration: InputDecoration(
                          labelText: '${getTranslated('expiry_date', context) ?? 'Expiry Date'} *',
                          hintText: getTranslated('select_date', context) ?? 'Select date',
                          border: const OutlineInputBorder(),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: Dimensions.paddingSizeDefault,
                            vertical: Dimensions.paddingSizeSmall,
                          ),
                          suffixIcon: const Icon(Icons.calendar_today),
                        ),
                        onTap: () => _selectDate(context),
                      ),

                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: controller.isLoading
                              ? null
                              : () => _addSingleCode(controller),
                          style: ElevatedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          icon: const Icon(Icons.add),
                          label: Text(
                            getTranslated('add_code_to_pool', context) ?? 'Add Code to Pool',
                          ),
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

  Widget _buildCard({
    required BuildContext context,
    required String title,
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
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            child: Text(
              title,
              style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: child,
          ),
        ],
      ),
    );
  }

  Widget _buildFormatRow(BuildContext context, String label, bool required, String example) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(
            flex: 2,
            child: Text(
              label,
              style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall),
            ),
          ),
          if (required)
            Text(
              getTranslated('required', context) ?? 'Required',
              style: const TextStyle(color: Colors.red, fontSize: 10),
            )
          else
            Text(
              getTranslated('optional', context) ?? 'Optional',
              style: TextStyle(color: Theme.of(context).hintColor, fontSize: 10),
            ),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          Expanded(
            flex: 2,
            child: Text(
              example,
              textAlign: TextAlign.right,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeExtraSmall,
                color: Theme.of(context).hintColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _selectDate(BuildContext context) async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365 * 5)),
    );
    if (date != null) {
      _expiryController.text = '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    }
  }

  Future<void> _pickAndUpload(DigitalCodeController controller) async {
    final result = await controller.pickExcelFile();
    if (result != null && result.files.isNotEmpty) {
      final filePath = result.files.first.path;
      if (filePath != null) {
        await controller.uploadProductImport(widget.product.id!, filePath);
      }
    }
  }

  Future<void> _addSingleCode(DigitalCodeController controller) async {
    final code = _codeController.text.trim();
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(getTranslated('enter_code', context) ?? 'Please enter a code'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final expiryDate = _expiryController.text.trim();
    if (expiryDate.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(getTranslated('select_expiry_date', context) ?? 'Please select an expiry date'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final success = await controller.addSingleCode(
      widget.product.id!,
      code,
      serialNumber: _serialController.text.trim().isNotEmpty ? _serialController.text.trim() : null,
      expiryDate: expiryDate,
    );

    if (success) {
      _codeController.clear();
      _serialController.clear();
      _expiryController.clear();
    }
  }
}
