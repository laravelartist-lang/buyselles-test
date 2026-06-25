import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:url_launcher/url_launcher.dart';

class LoginChoiceDialogWidget extends StatelessWidget {
  final String? fromPage;
  final VoidCallback? onLoginSuccess;

  const LoginChoiceDialogWidget({
    super.key,
    this.fromPage,
    this.onLoginSuccess,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.only(bottom: 40, top: 15),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: const BorderRadius.vertical(
          top: Radius.circular(Dimensions.paddingSizeDefault),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Container(
            width: 40,
            height: 5,
            decoration: BoxDecoration(
              color: Theme.of(context).hintColor.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(20),
            ),
          ),
          const SizedBox(height: 30),

          // Title
          Text(
            getTranslated('login_as', context) ?? 'Login as',
            style: textBold.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            getTranslated('choose_login_option', context) ?? 'Choose how you want to login',
            style: titleRegular.copyWith(
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: 30),

          // Customer Login Option
          _LoginOptionTile(
            icon: Icons.person_outline,
            title: getTranslated('login_as_customer', context) ?? 'Login as Customer',
            subtitle: getTranslated('continue_as_customer', context) ?? 'Browse and shop products',
            onTap: () {
              Navigator.of(context).pop();
              RouterHelper.getLoginRoute(
                action: RouteAction.push,
                fromPage: fromPage,
                onLoginSuccess: onLoginSuccess,
              );
            },
          ),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeLarge),
            child: Divider(color: Theme.of(context).hintColor.withValues(alpha: 0.2)),
          ),

          // Vendor Login Option
          _LoginOptionTile(
            icon: Icons.store_outlined,
            title: getTranslated('login_as_vendor', context) ?? 'Login as Vendor',
            subtitle: getTranslated('open_vendor_app', context) ?? 'Manage your shop and products',
            onTap: () {
              final vendorUri = Uri.parse(AppConstants.vendorAppUrlScheme);
              _openVendorApp(context, vendorUri);
            },
          ),

          const SizedBox(height: 10),

          // Cancel button
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(
              getTranslated('cancel', context) ?? 'Cancel',
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: Theme.of(context).hintColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openVendorApp(BuildContext context, Uri vendorUri) async {
    Navigator.of(context).pop();
    if (await canLaunchUrl(vendorUri)) {
      if (context.mounted) {
        await launchUrl(vendorUri, mode: LaunchMode.externalApplication);
      }
    } else {
      if (context.mounted) {
        _showVendorAppNotInstalledSnackBar(context);
      }
    }
  }

  void _showVendorAppNotInstalledSnackBar(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          getTranslated('vendor_app_not_installed', context) ??
              'Vendor app is not installed on this device',
        ),
        backgroundColor: Theme.of(context).colorScheme.error,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}

class _LoginOptionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _LoginOptionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeLarge,
          vertical: Dimensions.paddingSizeDefault,
        ),
        child: Row(
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
              ),
              child: Icon(
                icon,
                color: Theme.of(context).primaryColor,
                size: 28,
              ),
            ),
            const SizedBox(width: Dimensions.paddingSizeDefault),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: textBold.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: Theme.of(context).textTheme.bodyLarge?.color,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: titleRegular.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Theme.of(context).hintColor,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.arrow_forward_ios,
              size: 16,
              color: Theme.of(context).hintColor,
            ),
          ],
        ),
      ),
    );
  }
}
