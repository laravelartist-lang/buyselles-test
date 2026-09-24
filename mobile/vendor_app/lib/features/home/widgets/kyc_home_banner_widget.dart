import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/kyc/controllers/kyc_controller.dart';
import 'package:sixvalley_vendor_app/features/kyc/widgets/kyc_required_dialog.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class KycHomeBannerWidget extends StatelessWidget {
  const KycHomeBannerWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<KycController>(
      builder: (context, kycController, _) {
        final status = kycController.kycStatus;

        if (status == null || !status.isBlocked) {
          return const SizedBox.shrink();
        }

        return Padding(
          padding: const EdgeInsets.fromLTRB(
            Dimensions.paddingSizeDefault,
            0,
            Dimensions.paddingSizeDefault,
            Dimensions.paddingSizeSmall,
          ),
          child: Material(
            color: Theme.of(context).primaryColor.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            child: InkWell(
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              onTap: () => showKycRequiredDialog(
                context,
                canStartVerification: status.canStartVerification,
              ),
              child: Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Row(
                  children: [
                    Icon(
                      Icons.verified_user_outlined,
                      color: Theme.of(context).primaryColor,
                    ),
                    const SizedBox(width: Dimensions.paddingSizeSmall),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            getTranslated('kyc_verification', context) ?? '',
                            style: titilliumSemiBold.copyWith(
                              fontSize: Dimensions.fontSizeDefault,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            getTranslated(
                              'please_complete_your_kyc_verification_to_continue',
                              context,
                            ) ??
                                '',
                            style: titilliumRegular.copyWith(
                              fontSize: Dimensions.fontSizeSmall,
                              color: Theme.of(context).hintColor,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Icon(
                      Icons.chevron_right,
                      color: Theme.of(context).primaryColor,
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
