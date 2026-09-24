import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/textfeild/custom_text_feild_widget.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/controllers/wallet_transfer_controller.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/widgets/customer_search_field.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class TransferFormWidget extends StatefulWidget {
  const TransferFormWidget({super.key});

  @override
  State<TransferFormWidget> createState() => _TransferFormWidgetState();
}

class _TransferFormWidgetState extends State<TransferFormWidget> {
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _referenceController = TextEditingController();

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<WalletTransferController>(
      builder: (context, controller, _) {
        final maxAmount = controller.withdrawableBalance ?? controller.totalEarning ?? 0;

        return Container(
          margin: const EdgeInsets.all(Dimensions.paddingSizeSmall),
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
            boxShadow: [
              BoxShadow(
                color: Theme.of(context).hintColor.withValues(alpha: 0.08),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                getTranslated('send_balance_to_customer', context)!,
                style: robotoBold.copyWith(fontSize: Dimensions.fontSizeDefault),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              const CustomerSearchField(),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              CustomTextFieldWidget(
                hintText: getTranslated('enter_amount', context),
                textInputType: TextInputType.number,
                controller: _amountController,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),
              Text(
                '${getTranslated('withdrawable_balance', context)}: ${PriceConverter.convertPrice(context, maxAmount)}',
                style: robotoRegular.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).hintColor,
                ),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              CustomTextFieldWidget(
                hintText: '${getTranslated('reference', context)} (${getTranslated('optional', context)})',
                controller: _referenceController,
                textInputAction: TextInputAction.done,
              ),
              const SizedBox(height: Dimensions.paddingSizeLarge),
              CustomButtonWidget(
                btnTxt: getTranslated('transfer_balance', context)!,
                isLoading: controller.isSubmitting,
                onTap: controller.selectedCustomer == null || controller.isSubmitting
                    ? null
                    : () async {
                        final amount = double.tryParse(_amountController.text.trim());
                        if (amount == null || amount < 0.01) {
                          showCustomSnackBarWidget(
                            getTranslated('enter_amount', context),
                            context,
                            isToaster: true,
                          );
                          return;
                        }
                        if (amount > maxAmount) {
                          showCustomSnackBarWidget(
                            getTranslated('insufficient_balance', context),
                            context,
                            isToaster: true,
                          );
                          return;
                        }

                        final success = await controller.submitTransfer(
                          _amountController.text.trim(),
                          reference: _referenceController.text.trim(),
                        );

                        if (success && context.mounted) {
                          _amountController.clear();
                          _referenceController.clear();
                        }
                      },
              ),
            ],
          ),
        );
      },
    );
  }
}
