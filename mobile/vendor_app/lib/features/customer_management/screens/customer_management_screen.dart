import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/no_data_screen.dart';
import 'package:sixvalley_vendor_app/features/pos/controllers/customer_controller.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_model.dart';
import 'package:sixvalley_vendor_app/features/pos/screens/add_new_customer_screen.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class CustomerManagementScreen extends StatefulWidget {
  const CustomerManagementScreen({super.key});

  @override
  State<CustomerManagementScreen> createState() => _CustomerManagementScreenState();
}

class _CustomerManagementScreenState extends State<CustomerManagementScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<CustomerController>(context, listen: false).getCustomerList('');
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('customers', context) ?? 'Customers',
        isBackButtonExist: true,
        isAction: false,
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const AddNewCustomerScreen()),
          );
          if (mounted) {
            Provider.of<CustomerController>(context, listen: false)
                .getCustomerList(_searchController.text.trim());
          }
        },
        backgroundColor: Theme.of(context).primaryColor,
        child: const Icon(Icons.person_add_outlined, color: Colors.white),
      ),
      body: Column(
        children: [
          // Search bar
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: getTranslated('search_customer', context) ?? 'Search by name…',
                hintStyle: robotoRegular.copyWith(color: Theme.of(context).hintColor),
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          Provider.of<CustomerController>(context, listen: false).getCustomerList('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: Theme.of(context).cardColor,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                  borderSide: BorderSide(color: Theme.of(context).dividerColor),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                  borderSide: BorderSide(color: Theme.of(context).dividerColor),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
              ),
              onChanged: (value) {
                setState(() {});
                if (value.isEmpty) {
                  Provider.of<CustomerController>(context, listen: false).getCustomerList('');
                }
              },
              onSubmitted: (value) {
                Provider.of<CustomerController>(context, listen: false).searchCustomer(context, value.trim());
              },
            ),
          ),

          // Customer list
          Expanded(
            child: Consumer<CustomerController>(
              builder: (context, customerController, _) {
                final customers = customerController.searchedCustomerList;

                if (customers == null) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (customers.isEmpty) {
                  return const NoDataScreen();
                }

                return RefreshIndicator(
                  onRefresh: () async {
                    await customerController.getCustomerList(_searchController.text.trim());
                  },
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
                    itemCount: customers.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final customer = customers[index];
                      return _CustomerTile(customer: customer);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _CustomerTile extends StatelessWidget {
  final Customers customer;

  const _CustomerTile({required this.customer});

  @override
  Widget build(BuildContext context) {
    final fullName = '${customer.fName ?? ''} ${customer.lName ?? ''}'.trim();
    final initials = fullName.isNotEmpty
        ? fullName.split(' ').map((w) => w.isNotEmpty ? w[0] : '').take(2).join().toUpperCase()
        : '?';

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(
        vertical: Dimensions.paddingSizeExtraSmall,
        horizontal: 0,
      ),
      leading: CircleAvatar(
        backgroundColor: Theme.of(context).primaryColor.withOpacity(0.15),
        child: Text(
          initials,
          style: titilliumSemiBold.copyWith(
            color: Theme.of(context).primaryColor,
            fontSize: Dimensions.fontSizeSmall,
          ),
        ),
      ),
      title: Text(
        fullName.isEmpty ? (getTranslated('unnamed_customer', context) ?? 'Unnamed') : fullName,
        style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (customer.email?.isNotEmpty == true)
            Text(
              customer.email!,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeExtraSmall,
                color: Theme.of(context).hintColor,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          if (customer.phone?.isNotEmpty == true)
            Text(
              customer.phone!,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeExtraSmall,
                color: Theme.of(context).hintColor,
              ),
            ),
        ],
      ),
      trailing: customer.walletBalance != null && (customer.walletBalance ?? 0) > 0
          ? Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  getTranslated('wallet', context) ?? 'Wallet',
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeExtraSmall,
                    color: Theme.of(context).hintColor,
                  ),
                ),
                Text(
                  PriceConverter.convertPrice(context, customer.walletBalance ?? 0),
                  style: titilliumSemiBold.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Colors.green,
                  ),
                ),
              ],
            )
          : null,
    );
  }
}
