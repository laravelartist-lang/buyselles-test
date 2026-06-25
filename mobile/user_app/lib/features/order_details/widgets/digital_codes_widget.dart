import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/domain/models/digital_code_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

/// Reusable widget that displays the digital product codes for an order.
///
/// Used in [OrderDetailsScreen] after purchase. Pass [codes] from
/// [OrderDetailsController.digitalCodes].
class DigitalCodesWidget extends StatelessWidget {
  final List<DigitalCodeModel> codes;
  final VoidCallback? onViewAll;

  const DigitalCodesWidget({super.key, required this.codes, this.onViewAll});

  @override
  Widget build(BuildContext context) {
    if (codes.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).hintColor.withValues(alpha: 0.2),
            spreadRadius: 1.5,
            blurRadius: 3,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Section header ──────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeSmall,
            ),
            child: Row(
              children: [
                Icon(
                  Icons.key_rounded,
                  size: 18,
                  color: Theme.of(context).primaryColor,
                ),
                const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                Text(
                  getTranslated('digital_product_codes', context) ??
                      'Digital Product Codes',
                  style: titilliumBold.copyWith(
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                  ),
                ),
                const Spacer(),
                if (onViewAll != null)
                  TextButton(
                    onPressed: onViewAll,
                    child: Text(
                      getTranslated('VIEW_ALL', context) ?? 'View All',
                      style: titilliumSemiBold.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).primaryColor,
                      ),
                    ),
                  ),
              ],
            ),
          ),

          Divider(
            thickness: 0.2,
            color: Theme.of(context).hintColor.withValues(alpha: 0.45),
          ),

          // ── Notice ──────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            child: Container(
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              decoration: BoxDecoration(
                color: Theme.of(context)
                    .colorScheme
                    .tertiaryContainer
                    .withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    size: 14,
                    color: Theme.of(context).primaryColor,
                  ),
                  const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                  Expanded(
                    child: Text(
                      getTranslated('do_not_share_digital_codes', context) ??
                          'Keep these codes safe. Do not share them with anyone.',
                      style: titilliumRegular.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).textTheme.titleMedium?.color,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // ── Code items ──────────────────────────────────────────────────
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(
              Dimensions.paddingSizeDefault,
              0,
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeDefault,
            ),
            itemCount: codes.length,
            separatorBuilder: (_, __) => Divider(
              thickness: 0.2,
              color: Theme.of(context).hintColor.withValues(alpha: 0.3),
            ),
            itemBuilder: (context, index) {
              return _DigitalCodeItem(code: codes[index]);
            },
          ),
        ],
      ),
    );
  }
}

// ── Single code row ────────────────────────────────────────────────────────

class _DigitalCodeItem extends StatefulWidget {
  final DigitalCodeModel code;

  const _DigitalCodeItem({required this.code});

  @override
  State<_DigitalCodeItem> createState() => _DigitalCodeItemState();
}

class _DigitalCodeItemState extends State<_DigitalCodeItem> {
  bool _codeVisible = false;
  bool _pinVisible = false;

  void _copyToClipboard(BuildContext context, String value, String label) {
    Clipboard.setData(ClipboardData(text: value));
    showCustomSnackBarWidget(
      '$label ${getTranslated('copied_to_clipboard', context) ?? 'copied to clipboard'}',
      context,
      snackBarType: SnackBarType.success,
    );
  }

  @override
  Widget build(BuildContext context) {
    final DigitalCodeModel c = widget.code;

    return Padding(
      padding:
          const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Product name
          Text(
            c.productName,
            style: titilliumSemiBold.copyWith(
              fontSize: Dimensions.fontSizeDefault,
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: Dimensions.paddingSizeExtraSmall),

          // Code row
          _CodeRow(
            label: getTranslated('code', context) ?? 'Code',
            value: c.code,
            visible: _codeVisible,
            onToggleVisibility: () =>
                setState(() => _codeVisible = !_codeVisible),
            onCopy: () => _copyToClipboard(
                context, c.code, getTranslated('code', context) ?? 'Code'),
          ),

          // PIN row (optional)
          if (c.pin != null && c.pin!.isNotEmpty) ...[
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            _CodeRow(
              label: getTranslated('pin', context) ?? 'PIN',
              value: c.pin!,
              visible: _pinVisible,
              onToggleVisibility: () =>
                  setState(() => _pinVisible = !_pinVisible),
              onCopy: () => _copyToClipboard(
                  context, c.pin!, getTranslated('pin', context) ?? 'PIN'),
            ),
          ],

          // Meta: serial & expiry
          if ((c.serial != null && c.serial!.isNotEmpty) ||
              (c.expiry != null && c.expiry!.isNotEmpty)) ...[
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Wrap(
              spacing: Dimensions.paddingSizeDefault,
              children: [
                if (c.serial != null && c.serial!.isNotEmpty)
                  _MetaChip(
                    label:
                        '${getTranslated('serial', context) ?? 'S/N'}: ${c.serial!}',
                  ),
                if (c.expiry != null && c.expiry!.isNotEmpty)
                  _MetaChip(
                    label:
                        '${getTranslated('exp', context) ?? 'Exp'}: ${c.expiry!}',
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

// ── Masked code row with visibility toggle + copy ──────────────────────────

class _CodeRow extends StatelessWidget {
  final String label;
  final String value;
  final bool visible;
  final VoidCallback onToggleVisibility;
  final VoidCallback onCopy;

  const _CodeRow({
    required this.label,
    required this.value,
    required this.visible,
    required this.onToggleVisibility,
    required this.onCopy,
  });

  String get _maskedValue {
    if (value.length <= 4) return '••••';
    return '${value.substring(0, 4)}${'•' * (value.length - 4)}';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeSmall,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
        border: Border.all(
          color: Theme.of(context).primaryColor.withValues(alpha: 0.2),
        ),
      ),
      child: Row(
        children: [
          // Label
          Text(
            '$label: ',
            style: titilliumRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).textTheme.titleMedium?.color,
            ),
          ),

          // Value
          Expanded(
            child: Text(
              visible ? value : _maskedValue,
              style: titilliumBold.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                letterSpacing: visible ? 2.0 : 1.0,
                color: Theme.of(context).primaryColor,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),

          // Toggle visibility
          GestureDetector(
            onTap: onToggleVisibility,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeExtraSmall),
              child: Icon(
                visible
                    ? Icons.visibility_off_outlined
                    : Icons.visibility_outlined,
                size: 18,
                color: Theme.of(context).hintColor,
              ),
            ),
          ),

          // Copy button
          GestureDetector(
            onTap: onCopy,
            child: Padding(
              padding:
                  const EdgeInsets.only(left: Dimensions.paddingSizeExtraSmall),
              child: Icon(
                Icons.copy_rounded,
                size: 18,
                color: Theme.of(context).primaryColor,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Small label chip ───────────────────────────────────────────────────────

class _MetaChip extends StatelessWidget {
  final String label;

  const _MetaChip({required this.label});

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: titilliumRegular.copyWith(
        fontSize: Dimensions.fontSizeExtraSmall,
        color: Theme.of(context).hintColor,
      ),
    );
  }
}
