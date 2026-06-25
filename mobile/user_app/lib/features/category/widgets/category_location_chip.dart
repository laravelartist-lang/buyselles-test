import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

/// Compact location chip for horizontal filter bars.
/// Must not use [Flexible]/[Expanded] because parent row is inside a
/// horizontal scroll view with unbounded width.
class CategoryLocationChip extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const CategoryLocationChip({
    super.key,
    required this.label,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeSmall,
          vertical: Dimensions.paddingSizeExtraSmall,
        ),
        decoration: BoxDecoration(
          color: onTap == null
              ? Theme.of(context).disabledColor.withValues(alpha: 0.1)
              : Theme.of(context).primaryColor.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
          border: Border.all(
            color: onTap == null
                ? Theme.of(context).disabledColor.withValues(alpha: 0.3)
                : Theme.of(context).primaryColor.withValues(alpha: 0.3),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: onTap == null
                    ? Theme.of(context).disabledColor
                    : Theme.of(context).primaryColor,
              ),
            ),
            const SizedBox(width: 4),
            Icon(
              Icons.arrow_drop_down,
              size: 18,
              color: onTap == null
                  ? Theme.of(context).disabledColor
                  : Theme.of(context).primaryColor,
            ),
          ],
        ),
      ),
    );
  }
}
