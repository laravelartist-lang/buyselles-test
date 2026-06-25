import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/theme/controllers/theme_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class DisputeAttachmentBottomSheet extends StatelessWidget {
  final VoidCallback onCameraTap;
  final VoidCallback onGalleryTap;

  const DisputeAttachmentBottomSheet({
    super.key,
    required this.onCameraTap,
    required this.onGalleryTap,
  });

  @override
  Widget build(BuildContext context) {
    final bool isDark = Provider.of<ThemeController>(context, listen: false).darkTheme;
    final Color backgroundColor = isDark
        ? Theme.of(context).colorScheme.surface
        : Theme.of(context).cardColor;
    final Color textColor = Theme.of(context).textTheme.bodyLarge?.color ?? Colors.black;

    return Container(
      width: MediaQuery.sizeOf(context).width,
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(Dimensions.paddingSizeExtraLarge)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.15),
            blurRadius: 12,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            Dimensions.paddingSizeDefault,
            Dimensions.paddingSizeDefault,
            Dimensions.paddingSizeDefault,
            Dimensions.paddingSizeSmall,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
                decoration: BoxDecoration(
                  color: Theme.of(context).hintColor.withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              Text(
                getTranslated('attach_files', context) ?? 'Attach Files',
                style: robotoBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: textColor),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              ListTile(
                leading: CircleAvatar(
                  backgroundColor: Theme.of(context).primaryColor.withValues(alpha: 0.12),
                  child: Icon(Icons.camera_alt, color: Theme.of(context).primaryColor),
                ),
                title: Text(
                  getTranslated('camera', context) ?? 'Camera',
                  style: robotoMedium.copyWith(color: textColor),
                ),
                subtitle: Text(
                  getTranslated('take_a_photo', context) ?? 'Take a photo',
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(context).hintColor,
                  ),
                ),
                onTap: onCameraTap,
              ),
              const Divider(height: 1),
              ListTile(
                leading: CircleAvatar(
                  backgroundColor: Theme.of(context).primaryColor.withValues(alpha: 0.12),
                  child: Icon(Icons.photo_library, color: Theme.of(context).primaryColor),
                ),
                title: Text(
                  getTranslated('gallery', context) ?? 'Gallery',
                  style: robotoMedium.copyWith(color: textColor),
                ),
                subtitle: Text(
                  getTranslated('from_gallery', context) ?? 'From gallery',
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(context).hintColor,
                  ),
                ),
                onTap: onGalleryTap,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
