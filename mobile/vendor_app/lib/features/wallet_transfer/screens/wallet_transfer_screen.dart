import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/features/profile/controllers/profile_controller.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/controllers/wallet_transfer_controller.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/widgets/transfer_form_widget.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/widgets/transfer_history_widget.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class WalletTransferScreen extends StatefulWidget {
  const WalletTransferScreen({super.key});

  @override
  State<WalletTransferScreen> createState() => _WalletTransferScreenState();
}

class _WalletTransferScreenState extends State<WalletTransferScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadIfAllowed());
  }

  Future<void> _loadIfAllowed() async {
    if (!mounted) {
      return;
    }

    final profileController = Provider.of<ProfileController>(context, listen: false);
    if (profileController.userInfoModel == null) {
      await profileController.getSellerInfo();
    }

    if (!mounted) {
      return;
    }

    if (profileController.userInfoModel?.canUseWalletTransfer == false) {
      return;
    }

    await Provider.of<WalletTransferController>(context, listen: false).loadTransferData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('wallet_transfer_to_customer', context),
      ),
      body: Consumer<ProfileController>(
        builder: (context, profileController, _) {
          final profile = profileController.userInfoModel;

          if (profile != null && !profile.canUseWalletTransfer) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                child: Text(
                  getTranslated('wallet_transfer_not_available', context)!,
                  textAlign: TextAlign.center,
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeDefault,
                    color: Theme.of(context).hintColor,
                  ),
                ),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              final currentProfile = Provider.of<ProfileController>(context, listen: false);
              await currentProfile.getSellerInfo();

              if (!context.mounted) {
                return;
              }

              if (currentProfile.userInfoModel?.canUseWalletTransfer == false) {
                showCustomSnackBarWidget(
                  getTranslated('wallet_transfer_not_available', context),
                  context,
                  sanckBarType: SnackBarType.warning,
                );
                return;
              }

              await Provider.of<WalletTransferController>(context, listen: false).loadTransferData();
            },
            child: Consumer<WalletTransferController>(
              builder: (context, controller, _) {
                if (controller.isLoading) {
                  return Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Theme.of(context).primaryColor),
                    ),
                  );
                }

                return SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  child: Column(
                    children: [
                      _BalanceCard(
                        totalEarning: controller.totalEarning ?? 0,
                        withdrawableBalance: controller.withdrawableBalance ?? 0,
                      ),
                      const TransferFormWidget(),
                      const TransferHistoryWidget(),
                      const SizedBox(height: Dimensions.paddingSizeLarge),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({
    required this.totalEarning,
    required this.withdrawableBalance,
  });

  final double totalEarning;
  final double withdrawableBalance;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.all(Dimensions.paddingSizeSmall),
      padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
      decoration: BoxDecoration(
        color: Theme.of(context).primaryColor,
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      ),
      child: Column(
        children: [
          Text(
            getTranslated('your_wallet_balance', context)!,
            style: robotoRegular.copyWith(
              color: Theme.of(context).cardColor.withValues(alpha: 0.85),
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            PriceConverter.convertPrice(context, totalEarning),
            style: robotoBold.copyWith(
              fontSize: Dimensions.fontSizeMaxLarge,
              color: Theme.of(context).cardColor,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            '${getTranslated('withdrawable_balance', context)}: ${PriceConverter.convertPrice(context, withdrawableBalance)}',
            style: robotoRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).cardColor.withValues(alpha: 0.85),
            ),
          ),
        ],
      ),
    );
  }
}
