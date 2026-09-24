import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:sixvalley_vendor_app/helper/color_helper.dart';
import 'package:sixvalley_vendor_app/helper/price_converter.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/features/profile/controllers/profile_controller.dart';
import 'package:sixvalley_vendor_app/features/transaction/controllers/transaction_controller.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/no_data_screen.dart';
import 'package:sixvalley_vendor_app/features/transaction/screens/transaction_screen.dart';
import 'package:sixvalley_vendor_app/features/wallet/widgets/wallet_card_widget.dart';
import 'package:sixvalley_vendor_app/features/wallet/widgets/wallet_transaction_list_view_widget.dart';
import 'package:sixvalley_vendor_app/features/wallet/widgets/withdraw_balance_widget.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/screens/wallet_transfer_screen.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class WalletScreen extends StatefulWidget {
  final bool fromNotification;
  const WalletScreen({super.key, this.fromNotification = false});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {

  
  @override
  void initState() {
    if(widget.fromNotification && Provider.of<ProfileController>(context, listen: false).userInfoModel == null) {
      Provider.of<ProfileController>(context, listen: false).getSellerInfo();
    }

    super.initState();
  }
  final ScrollController _scrollController = ScrollController();


  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {

        if(widget.fromNotification) {
          Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(
            builder: (BuildContext context) => const DashboardScreen(),
          ), (route) => false);

        }else {
          if(!didPop) {
            Navigator.of(context).pop();
          }
        }
      },

      child: Scaffold(
        resizeToAvoidBottomInset: false,
        appBar: CustomAppBarWidget(
          title: getTranslated('wallet', context),
          onBackPressed: () {
            if(widget.fromNotification) {
              Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (BuildContext context) => const DashboardScreen()));
            } else {
              Navigator.of(context).pop();
            }
          },
        ),
        body: RefreshIndicator(
          onRefresh: () async {
            Provider.of<TransactionController>(context, listen: false).getTransactionList(context,'all','','');
          },
          backgroundColor: Theme.of(context).primaryColor,
          child: CustomScrollView(
            controller: _scrollController,
            slivers: [

              SliverToBoxAdapter(child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
                child: Column(children: [
                  Consumer<ProfileController>(
                    builder: (context, seller, child) {
                      return seller.userInfoModel == null ? const SizedBox() : Column(children: [

                        seller.userInfoModel == null ? const SizedBox() : const WithdrawBalanceWidget(),

                        if (seller.userInfoModel?.canUseWalletTransfer ?? true)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(
                              Dimensions.paddingSizeSmall,
                              Dimensions.paddingSizeDefault,
                              Dimensions.paddingSizeSmall,
                              0,
                            ),
                            child: InkWell(
                              onTap: () => Navigator.push(
                                context,
                                MaterialPageRoute(builder: (_) => const WalletTransferScreen()),
                              ),
                              child: Container(
                                width: double.infinity,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: Dimensions.paddingSizeDefault,
                                  vertical: Dimensions.paddingSizeSmall,
                                ),
                                decoration: BoxDecoration(
                                  color: Theme.of(context).cardColor,
                                  borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                                  border: Border.all(color: Theme.of(context).primaryColor.withValues(alpha: 0.2)),
                                ),
                                child: Row(
                                  children: [
                                    Icon(Icons.swap_horiz, color: Theme.of(context).primaryColor),
                                    const SizedBox(width: Dimensions.paddingSizeSmall),
                                    Expanded(
                                      child: Text(
                                        getTranslated('wallet_transfer_to_customer', context)!,
                                        style: robotoMedium.copyWith(color: Theme.of(context).primaryColor),
                                      ),
                                    ),
                                    Icon(Icons.arrow_forward_ios, size: 16, color: Theme.of(context).primaryColor),
                                  ],
                                ),
                              ),
                            ),
                          ),

                        Container(
                          margin: const EdgeInsets.all(Dimensions.fontSizeSmall).copyWith(right: 0, top: Dimensions.paddingSizeDefault),
                          height: 90,
                          child: ListView(
                            shrinkWrap: true,
                            scrollDirection: Axis.horizontal,
                            children: [

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.withdrawn.toString()) : 0.0))),
                                title: '${getTranslated('withdrawn', context)}',
                                color: Theme.of(context).colorScheme.onTertiaryContainer,
                              ),

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse((seller.userInfoModel!.wallet!.pendingBalance ?? 0).toString()) : 0.0))),
                                title: '${getTranslated('pending_balance', context)}',
                                color: Colors.amber.shade700,
                              ),

                              WalletCardWidget(
                                amount:PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.pendingWithdraw.toString()) : 0.0))),
                                title: '${getTranslated('pending_withdrawn', context)}',
                                color: Theme.of(context).colorScheme.secondary,
                              ),

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.commissionGiven.toString()) : 0.0))),
                                title: '${getTranslated('commission_given', context)}',
                                color: ColorHelper.darken(Theme.of(context).colorScheme.tertiary, 0.1),
                              ),

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.deliveryChargeEarned.toString()) : 0.0))),
                                title: '${getTranslated('delivery_charge_earned', context)}',
                                color: ColorHelper.darken(Theme.of(context).colorScheme.outline, 0.1),
                              ),

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.collectedCash.toString()) : 0.0))),
                                title: '${getTranslated('collected_cash', context)}',
                                color: ColorHelper.darken(Theme.of(context).colorScheme.outline, 0.1),
                              ),

                              WalletCardWidget(
                                amount: PriceConverter.convertPrice(context, double.parse(PriceConverter.longToShortPrice(seller.userInfoModel!.wallet != null ?
                                double.parse(seller.userInfoModel!.wallet!.totalTaxCollected.toString()) : 0.0))),
                                title: '${getTranslated('total_collected_tax', context)}',
                                color: ColorHelper.darken(Theme.of(context).colorScheme.tertiary, 0.1),
                              )


                            ],

                          ),
                        ),

                      ]);
                    }
                  ),

                  const _TransactionTitleRowWidget(),

                  Consumer<TransactionController>(
                    builder: (context, transactionProvider, child) {
                      return  Container(
                        child: transactionProvider.transactionList !=null ? transactionProvider.transactionList!.isNotEmpty ?
                        WalletTransactionListViewWidget(transactionProvider: transactionProvider) : const NoDataScreen()
                          : Center(child: CircularProgressIndicator(valueColor: AlwaysStoppedAnimation<Color>(Theme.of(context).primaryColor))),
                      );
                    }
                  ),
                ]),
              ))
            ],
          ),
        ),
      ),
    );
  }
}


class _TransactionTitleRowWidget extends StatelessWidget {
  const _TransactionTitleRowWidget();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(Dimensions.paddingSizeMedium, Dimensions.paddingSizeSmall, Dimensions.paddingSizeMedium, Dimensions.paddingSizeSmall),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween,children: [
        Text(getTranslated('withdraw_history', context)!, style: robotoBold.copyWith(
            color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha: 0.50),
            fontSize: Dimensions.fontSizeDefault
        )),

        InkWell(
          onTap: ()=> Navigator.push(context, MaterialPageRoute(builder: (_) => const TransactionScreen())),
          child: Row(children: [
            Text(getTranslated('view_all', context)!, style: robotoBold.copyWith(
              color: Theme.of(context).colorScheme.onSecondary,
              fontSize: Dimensions.fontSizeSmall,
            )),
            const SizedBox(width: Dimensions.paddingSizeVeryTiny),

            Icon(Icons.arrow_forward, color: Theme.of(context).colorScheme.onSecondary, size: Dimensions.paddingSize)
          ]),
        )
      ]),
    );
  }
}


