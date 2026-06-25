import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/controllers/digital_code_controller.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/models/digital_code_model.dart';
import 'package:sixvalley_vendor_app/features/product/domain/models/product_model.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/screens/digital_code_product_import_screen.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DigitalCodePoolScreen extends StatefulWidget {
  final Product product;

  const DigitalCodePoolScreen({super.key, required this.product});

  @override
  State<DigitalCodePoolScreen> createState() => _DigitalCodePoolScreenState();
}

class _DigitalCodePoolScreenState extends State<DigitalCodePoolScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<DigitalCodeController>(context, listen: false)
          .loadProductCodes(widget.product.id!);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: '${getTranslated('digital_codes', context) ?? 'Digital Codes'}',
        isBackButtonExist: true,
        isAction: false,
      ),
      floatingActionButton: Consumer<DigitalCodeController>(
        builder: (context, controller, _) {
          return FloatingActionButton.extended(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ChangeNotifierProvider.value(
                    value: controller,
                    child: DigitalCodeProductImportScreen(product: widget.product),
                  ),
                ),
              );
            },
            icon: const Icon(Icons.add),
            label: Text(getTranslated('upload_codes', context) ?? 'Upload Codes'),
          );
        },
      ),
      body: Consumer<DigitalCodeController>(
        builder: (context, controller, _) {
          if (controller.isLoading && controller.codeModel == null) {
            return const Center(child: CircularProgressIndicator());
          }

          final model = controller.codeModel;
          final stats = model?.stats;
          final codes = model?.codes ?? [];

          return RefreshIndicator(
            onRefresh: () => controller.loadProductCodes(widget.product.id!),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
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
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                widget.product.name ?? '',
                                style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 2),
                              Text(
                                getTranslated('code_pool', context) ?? 'Code Pool',
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

                  const SizedBox(height: Dimensions.paddingSizeDefault),

                  // Import summary (if any)
                  if (controller.lastImportSummary != null)
                    _buildImportSummary(context, controller.lastImportSummary!),

                  // Expiring warning
                  if ((model?.expiringCount ?? 0) > 0)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                      margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
                      decoration: BoxDecoration(
                        color: Colors.amber.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.amber.withOpacity(0.4)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.warning_amber, color: Colors.amber, size: 20),
                          const SizedBox(width: Dimensions.paddingSizeSmall),
                          Expanded(
                            child: Text(
                              '${model?.expiringCount} ${getTranslated('codes_expiring_soon', context) ?? 'code(s) will expire within 7 days'}',
                              style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall),
                            ),
                          ),
                        ],
                      ),
                    ),

                  // Stats cards
                  if (stats != null) _buildStatsRow(context, stats),

                  const SizedBox(height: Dimensions.paddingSizeDefault),

                  // Codes list
                  if (codes.isEmpty)
                    _buildEmptyState(context)
                  else
                    ListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: codes.length,
                      itemBuilder: (context, index) => _buildCodeItem(context, codes[index], controller),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildImportSummary(BuildContext context, DigitalCodeImportSummary summary) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
      margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: summary.processed > 0 ? Colors.green.withOpacity(0.08) : Colors.amber.withOpacity(0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: summary.processed > 0 ? Colors.green.withOpacity(0.3) : Colors.amber.withOpacity(0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            getTranslated('import_complete', context) ?? 'Import Complete',
            style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeSmall),
          ),
          const SizedBox(height: 4),
          Text('${summary.processed} ${getTranslated('codes_imported', context) ?? 'code(s) imported successfully'}',
            style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall),
          ),
          if (summary.duplicates > 0)
            Text('${summary.duplicates} ${getTranslated('duplicates_skipped', context) ?? 'duplicate(s) skipped'}',
              style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: Colors.amber),
            ),
          if (summary.skipped > 0)
            Text('${summary.skipped} ${getTranslated('blank_rows_skipped', context) ?? 'blank/example row(s) skipped'}',
              style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeExtraSmall, color: Colors.grey),
            ),
        ],
      ),
    );
  }

  Widget _buildStatsRow(BuildContext context, DigitalCodeStats stats) {
    return Row(
      children: [
        _buildStatCard(context, stats.available.toString(), getTranslated('available', context) ?? 'Available', Colors.green),
        const SizedBox(width: 4),
        _buildStatCard(context, stats.inactive.toString(), getTranslated('inactive', context) ?? 'Inactive', Colors.grey),
        const SizedBox(width: 4),
        _buildStatCard(context, stats.reserved.toString(), getTranslated('reserved', context) ?? 'Reserved', Colors.orange),
        const SizedBox(width: 4),
        _buildStatCard(context, stats.sold.toString(), getTranslated('sold', context) ?? 'Sold', Colors.blue),
        const SizedBox(width: 4),
        _buildStatCard(context, stats.expired.toString(), getTranslated('expired', context) ?? 'Expired', Colors.red),
        const SizedBox(width: 4),
        _buildStatCard(context, stats.total.toString(), getTranslated('total', context) ?? 'Total', Colors.grey),
      ],
    );
  }

  Widget _buildStatCard(BuildContext context, String value, String label, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color.withOpacity(0.2)),
        ),
        child: Column(
          children: [
            Text(
              value,
              style: titilliumSemiBold.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: color,
              ),
            ),
            Text(
              label,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeExtraSmall,
                color: color,
              ),
              overflow: TextOverflow.ellipsis,
              maxLines: 1,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCodeItem(BuildContext context, DigitalCodeItem code, DigitalCodeController controller) {
    final isAvailable = code.status == 'available' || code.status == 'expired';
    final isExpired = code.status == 'expired';
    final isActive = code.isActive == true;
    final isRevealing = controller.isRevealing;
    final revealedCode = controller.getRevealedCode(code.id ?? 0);

    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                // Status indicator
                Container(
                  width: 4,
                  height: 40,
                  decoration: BoxDecoration(
                    color: isActive ? Colors.green : Colors.grey,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),

                // Info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Serial number
                      Text(
                        code.serialNumber ?? '---',
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                      ),
                      const SizedBox(height: 2),
                      // Status badge
                      Row(
                        children: [
                          _buildStatusBadge(context, code.status ?? ''),
                          if (!isActive)
                            _buildInactiveBadge(context),
                          const SizedBox(width: 6),
                          if (code.expiryDate != null)
                            Text(
                              code.expiryDate!,
                              style: robotoRegular.copyWith(
                                fontSize: 10,
                                color: isExpired ? Colors.red : Theme.of(context).hintColor,
                              ),
                            ),
                          if (code.createdAt != null) ...[
                            const SizedBox(width: 6),
                            Text(
                              code.createdAt!.substring(0, 10),
                              style: robotoRegular.copyWith(
                                fontSize: 10,
                                color: Theme.of(context).hintColor,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),

                // Actions
                if (isAvailable) ...[
                  // Reveal code
                  if (isActive)
                    IconButton(
                      onPressed: isRevealing
                          ? null
                          : () => _revealCode(context, code, controller),
                      icon: revealedCode != null
                          ? const Icon(Icons.visibility_off, size: 18)
                          : const Icon(Icons.visibility, size: 18),
                      color: revealedCode != null ? Colors.purple : Theme.of(context).hintColor,
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      tooltip: revealedCode != null
                          ? (getTranslated('hide_code', context) ?? 'Hide Code')
                          : (getTranslated('reveal_code', context) ?? 'Reveal Code'),
                    ),
                  // Toggle active
                  SizedBox(
                    height: 28,
                    child: Switch(
                      value: isActive,
                      onChanged: (_) => _toggleCode(context, code, controller),
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  ),
                  // Delete
                  IconButton(
                    onPressed: () => _confirmDelete(context, code, controller),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    color: Colors.red,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                ],
              ],
            ),
            // Revealed code row
            if (revealedCode != null && isActive)
              Padding(
                padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeSmall,
                    vertical: Dimensions.paddingSizeSmall,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.purple.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.purple.withOpacity(0.2)),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: SelectableText(
                          revealedCode,
                          style: TextStyle(
                            fontFamily: 'monospace',
                            fontSize: Dimensions.fontSizeDefault,
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).textTheme.bodyLarge?.color,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: revealedCode));
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(getTranslated('code_copied', context) ?? 'Code copied to clipboard'),
                              duration: const Duration(seconds: 1),
                            ),
                          );
                        },
                        icon: const Icon(Icons.copy, size: 16),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        tooltip: getTranslated('copy_code', context) ?? 'Copy Code',
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildInactiveBadge(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
      margin: const EdgeInsets.only(left: 4),
      decoration: BoxDecoration(
        color: Colors.red.withOpacity(0.1),
        borderRadius: BorderRadius.circular(3),
      ),
      child: Text(
        getTranslated('inactive', context) ?? 'Inactive',
        style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Colors.red),
      ),
    );
  }

  Future<void> _revealCode(BuildContext context, DigitalCodeItem code, DigitalCodeController controller) async {
    final alreadyRevealed = controller.getRevealedCode(code.id ?? 0);
    if (alreadyRevealed != null) {
      controller.hideRevealedCode(code.id!);
      return;
    }

    final result = await controller.decryptAndReveal(code.id!);
    if (result == null && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(getTranslated('failed_to_reveal_code', context) ?? 'Failed to reveal code'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Widget _buildStatusBadge(BuildContext context, String status) {
    Color color;
    switch (status) {
      case 'available':
        color = Colors.green;
        break;
      case 'reserved':
        color = Colors.orange;
        break;
      case 'sold':
        color = Colors.blue;
        break;
      case 'expired':
        color = Colors.red;
        break;
      default:
        color = Colors.grey;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        status.toUpperCase(),
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.bold,
          color: color,
        ),
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(
        children: [
          Icon(Icons.inbox_outlined, size: 60, color: Theme.of(context).hintColor),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text(
            getTranslated('no_codes_yet', context) ?? 'No codes in the pool yet',
            style: titilliumSemiBold.copyWith(color: Theme.of(context).hintColor),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            getTranslated('upload_codes_to_get_started', context) ?? 'Upload codes to get started',
            style: robotoRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _toggleCode(BuildContext context, DigitalCodeItem code, DigitalCodeController controller) async {
    final success = await controller.toggleCodeStatus(code.id!);
    if (success) {
      await controller.loadProductCodes(widget.product.id!);
    }
  }

  Future<void> _confirmDelete(BuildContext context, DigitalCodeItem code, DigitalCodeController controller) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(getTranslated('delete_code', context) ?? 'Delete Code'),
        content: Text(getTranslated('delete_code_confirmation', context) ?? 'Are you sure? This cannot be undone.'),
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

    if (confirmed == true) {
      final success = await controller.deleteCode(code.id!);
      if (success) {
        await controller.loadProductCodes(widget.product.id!);
      }
    }
  }
}
