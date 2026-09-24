import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/no_data_screen.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/controllers/wallet_transfer_controller.dart';
import 'package:sixvalley_vendor_app/helper/date_converter.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class TransferHistoryWidget extends StatelessWidget {
  const TransferHistoryWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<WalletTransferController>(
      builder: (context, controller, _) {
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeExtraSmall,
                  vertical: Dimensions.paddingSizeSmall,
                ),
                child: Text(
                  getTranslated('transfer_history', context)!,
                  style: robotoBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                ),
              ),
              if (controller.transfers.isEmpty)
                const NoDataScreen()
              else
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: controller.transfers.length,
                  separatorBuilder: (_, __) => const SizedBox(height: Dimensions.paddingSizeSmall),
                  itemBuilder: (context, index) {
                    final transfer = controller.transfers[index];

                    return Container(
                      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                        border: Border.all(color: Theme.of(context).dividerColor.withValues(alpha: 0.4)),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  transfer.customer?.name ?? getTranslated('customer', context)!,
                                  style: robotoBold,
                                ),
                                if (transfer.customer?.email?.isNotEmpty == true)
                                  Text(
                                    transfer.customer!.email!,
                                    style: robotoRegular.copyWith(
                                      fontSize: Dimensions.fontSizeSmall,
                                      color: Theme.of(context).hintColor,
                                    ),
                                  ),
                                if (transfer.reference?.isNotEmpty == true)
                                  Text(
                                    transfer.reference!,
                                    style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                                  ),
                                if (transfer.createdAt != null)
                                  Text(
                                    DateConverter.localDateToIsoStringAMPM(DateTime.tryParse(transfer.createdAt!) ?? DateTime.now()),
                                    style: robotoRegular.copyWith(
                                      fontSize: Dimensions.fontSizeExtraSmall,
                                      color: Theme.of(context).hintColor,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          Text(
                            '-${PriceConverter.convertPrice(context, transfer.amount ?? 0)}',
                            style: robotoBold.copyWith(color: Theme.of(context).colorScheme.error),
                          ),
                        ],
                      ),
                    );
                  },
                ),
            ],
          ),
        );
      },
    );
  }
}
