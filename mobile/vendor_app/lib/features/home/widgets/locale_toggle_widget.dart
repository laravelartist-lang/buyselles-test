import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/features/home/widgets/locale_bottom_sheet_widget.dart';
import 'package:sixvalley_vendor_app/features/splash/controllers/splash_controller.dart';
import 'package:sixvalley_vendor_app/localization/controllers/localization_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:provider/provider.dart';

class LocaleToggleWidget extends StatelessWidget {
  const LocaleToggleWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final langCode = Provider.of<LocalizationController>(context).locale.languageCode.toUpperCase();
    final currCode = Provider.of<SplashController>(context).myCurrency?.code ?? 'USD';

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: () {
          showModalBottomSheet(
            context: context,
            isScrollControlled: true,
            backgroundColor: Colors.transparent,
            builder: (_) => const LocaleBottomSheetWidget(),
          );
        },
        child: Container(
          constraints: const BoxConstraints(minHeight: 32),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: Theme.of(context).primaryColor.withValues(alpha: 0.2),
            ),
            boxShadow: [
              BoxShadow(
                color: Theme.of(context).hintColor.withValues(alpha: 0.08),
                blurRadius: 4,
                offset: const Offset(0, 1),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
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
                    color: Theme.of(context).hintColor.withValues(alpha: 0.5),
                  ),
                ),
              ),
              Text(
                currCode,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.2,
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class HomeTopBarActionsWidget extends StatelessWidget {
  final Widget notificationAction;

  const HomeTopBarActionsWidget({
    super.key,
    required this.notificationAction,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: Dimensions.paddingSizeExtraSmall),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const LocaleToggleWidget(),
          const SizedBox(width: Dimensions.paddingSizeExtraSmall),
          notificationAction,
        ],
      ),
    );
  }
}
