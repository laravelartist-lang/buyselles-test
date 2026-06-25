import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/not_loggedin_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/controllers/dispute_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/widgets/dispute_card_widget.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class DisputeListScreen extends StatefulWidget {
  const DisputeListScreen({super.key});

  @override
  State<DisputeListScreen> createState() => _DisputeListScreenState();
}

class _DisputeListScreenState extends State<DisputeListScreen> {
  @override
  void initState() {
    super.initState();
    if (Provider.of<AuthController>(context, listen: false).isLoggedIn()) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Provider.of<DisputeController>(context, listen: false).loadDisputes();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool isLoggedIn = Provider.of<AuthController>(context, listen: false).isLoggedIn();

    return Scaffold(
      appBar: CustomAppBar(title: getTranslated('my_disputes', context) ?? 'My Disputes'),
      body: isLoggedIn
          ? Consumer<DisputeController>(
              builder: (context, ctrl, _) {
                if (ctrl.isLoading) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (ctrl.disputes == null || ctrl.disputes!.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.balance_outlined, size: 64, color: Colors.grey.shade400),
                        const SizedBox(height: Dimensions.paddingSizeDefault),
                        Text(
                          getTranslated('no_disputes_found', context) ?? 'No disputes found',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.grey),
                        ),
                      ],
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () => ctrl.loadDisputes(),
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
                    itemCount: ctrl.disputes!.length,
                    itemBuilder: (context, index) {
                      final dispute = ctrl.disputes![index];
                      return DisputeCardWidget(
                        dispute: dispute,
                        onTap: () {
                          RouterHelper.getDisputeDetailRoute(
                            disputeId: dispute.id,
                            action: RouteAction.push,
                          );
                        },
                      );
                    },
                  ),
                );
              },
            )
          : NotLoggedInWidget(
              fromPage: RouterHelper.disputeListScreen,
              onLoginSuccess: () {
                Provider.of<DisputeController>(context, listen: false).loadDisputes();
              },
            ),
    );
  }
}
