import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/controllers/wallet_transfer_controller.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/models/wallet_transfer_model.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class CustomerSearchField extends StatefulWidget {
  const CustomerSearchField({super.key});

  @override
  State<CustomerSearchField> createState() => _CustomerSearchFieldState();
}

class _CustomerSearchFieldState extends State<CustomerSearchField> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<WalletTransferController>(
      builder: (context, controller, _) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              getTranslated('search_customer', context)!,
              style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeSmall),
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            if (controller.selectedCustomer != null)
              _SelectedCustomerBadge(customer: controller.selectedCustomer!)
            else ...[
              TextField(
                controller: _searchController,
                decoration: InputDecoration(
                  hintText: getTranslated('search_by_name_email_or_phone', context),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                  ),
                  suffixIcon: controller.isSearching
                      ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      : null,
                ),
                onChanged: (value) {
                  setState(() {});
                  controller.searchCustomers(value);
                },
              ),
              if (_searchController.text.isNotEmpty && controller.searchResults.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(top: Dimensions.paddingSizeExtraSmall),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    border: Border.all(color: Theme.of(context).dividerColor),
                    borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                  ),
                  child: ListView.separated(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: controller.searchResults.length,
                    separatorBuilder: (_, __) => Divider(height: 1, color: Theme.of(context).dividerColor),
                    itemBuilder: (context, index) {
                      final customer = controller.searchResults[index];
                      return ListTile(
                        dense: true,
                        title: Text(customer.name ?? '', style: robotoMedium),
                        subtitle: Text(
                          [customer.email, customer.phone].where((e) => e != null && e.isNotEmpty).join(' • '),
                          style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                        ),
                        onTap: () {
                          controller.selectCustomer(customer);
                          _searchController.clear();
                        },
                      );
                    },
                  ),
                ),
            ],
          ],
        );
      },
    );
  }
}

class _SelectedCustomerBadge extends StatelessWidget {
  const _SelectedCustomerBadge({required this.customer});

  final WalletTransferCustomerModel customer;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeSmall,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).primaryColor.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
        border: Border.all(color: Theme.of(context).primaryColor.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(customer.name ?? '', style: robotoBold),
                if (customer.email?.isNotEmpty == true)
                  Text(customer.email!, style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
                if (customer.walletBalance != null)
                  Text(
                    '${getTranslated('wallet', context)}: ${PriceConverter.convertPrice(context, customer.walletBalance!)}',
                    style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall, color: Colors.green),
                  ),
              ],
            ),
          ),
          IconButton(
            onPressed: () => context.read<WalletTransferController>().clearSelectedCustomer(),
            icon: Icon(Icons.close, color: Theme.of(context).colorScheme.error),
          ),
        ],
      ),
    );
  }
}
