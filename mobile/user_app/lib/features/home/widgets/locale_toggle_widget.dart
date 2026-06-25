import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/home/widgets/locale_bottom_sheet_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/controllers/localization_controller.dart';
import 'package:provider/provider.dart';

class LocaleToggleWidget extends StatelessWidget {
  const LocaleToggleWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final langCode = Provider.of<LocalizationController>(context).locale.languageCode.toUpperCase();
    final currCode = Provider.of<SplashController>(context).myCurrency?.code ?? 'USD';

    return GestureDetector(
      onTap: () {
        showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          backgroundColor: Colors.transparent,
          builder: (_) => const LocaleBottomSheetWidget(),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).highlightColor,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: Theme.of(context).hintColor.withValues(alpha: 0.15),
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              langCode,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.3,
                color: Theme.of(context).primaryColor,
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 3),
              child: Text(
                '·',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w400,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.4),
                ),
              ),
            ),
            Text(
              currCode,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                letterSpacing: 0.2,
                color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha: 0.7),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
