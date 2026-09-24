import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/features/chat/screens/inbox_screen.dart';
import 'package:sixvalley_vendor_app/features/maintenance/maintenance_screen.dart';
import 'package:sixvalley_vendor_app/features/notification/screens/notification_screen.dart';
import 'package:sixvalley_vendor_app/features/order_details/screens/order_details_screen.dart';
import 'package:sixvalley_vendor_app/features/product/screens/product_list_screen.dart';
import 'package:sixvalley_vendor_app/features/refund/domain/models/refund_model.dart';
import 'package:sixvalley_vendor_app/features/refund/screens/refund_details_screen.dart';
import 'package:sixvalley_vendor_app/features/splash/domain/models/config_model.dart';
import 'package:sixvalley_vendor_app/features/update/screen/update_screen.dart';
import 'package:sixvalley_vendor_app/features/wallet/screens/wallet_screen.dart';
import 'package:sixvalley_vendor_app/helper/network_info.dart';
import 'package:sixvalley_vendor_app/features/auth/controllers/auth_controller.dart';
import 'package:sixvalley_vendor_app/features/splash/controllers/splash_controller.dart';
import 'package:sixvalley_vendor_app/main.dart';
import 'package:sixvalley_vendor_app/notification/models/notification_body.dart';
import 'package:sixvalley_vendor_app/features/auth/screens/auth_screen.dart';
import 'package:sixvalley_vendor_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class SplashScreen extends StatefulWidget {
  final NotificationBody? body;
  const SplashScreen({super.key, this.body});
  @override
  SplashScreenState createState() => SplashScreenState();
}

class SplashScreenState extends State<SplashScreen> {

  @override
  void initState() {
    super.initState();
    Provider.of<AuthController>(Get.context!,listen: false).setUnAuthorize(false, update: false);
    initCall();
  }

  Future<void> initCall() async {
    NetworkInfo.checkConnectivity(context);
    Provider.of<SplashController>(context, listen: false).initConfig().then((bool isSuccess) {
      if(isSuccess) {
        Provider.of<SplashController>(Get.context!, listen: false).getBusinessPagesList('default');
        Provider.of<SplashController>(Get.context!, listen: false).initShippingTypeList(Get.context!,'');
        _routeAfterConfig();
      }
    });
  }

  Future<void> _routeAfterConfig() async {
    if (!mounted) {
      return;
    }

    final config = Provider.of<SplashController>(context, listen: false).configModel;
    final SellerAppVersionControl? appVersion =
        config?.sellerAppVersionControl;
    String minimumVersion = '0';

    if (Platform.isAndroid) {
      minimumVersion = appVersion?.forAndroid?.version ?? '0';
    } else if (Platform.isIOS) {
      minimumVersion = appVersion?.forIos?.version ?? '0';
    }

    if (compareVersions(minimumVersion, AppConstants.appVersion) == 1) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const UpdateScreen()),
      );

      return;
    }

    if (config?.maintenanceModeData?.maintenanceStatus == 1 &&
        config?.maintenanceModeData?.selectedMaintenanceSystem?.vendorApp == 1) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => const MaintenanceScreen(),
          settings: const RouteSettings(name: 'MaintenanceScreen'),
        ),
      );

      return;
    }

    if (widget.body != null) {
      final String notificationType = widget.body?.type ?? '';

      switch (notificationType.toLowerCase()) {
        case 'chatting':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => InboxScreen(
                fromNotification: true,
                initIndex: widget.body?.messageKey ==
                        'message_from_delivery_man'
                    ? 1
                    : 0,
              ),
            ),
          );
          break;

        case 'theme':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => const NotificationScreen(),
            ),
          );
          break;

        case 'order':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => OrderDetailsScreen(
                orderId: int.parse(widget.body!.orderId.toString()),
                fromNotification: true,
              ),
            ),
          );
          break;

        case 'wallet':
        case 'wallet_withdraw':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => const WalletScreen(fromNotification: true),
            ),
          );
          break;

        case 'product_request_approved_message':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) =>
                  const ProductListMenuScreen(fromNotification: true),
            ),
          );
          break;

        case 'refund':
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => RefundDetailsScreen(
                fromNotification: true,
                refundModel: RefundModel(id: widget.body!.refundId),
                orderDetailsId: widget.body!.orderDetailsId,
              ),
            ),
          );
          break;

        default:
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => const NotificationScreen(),
            ),
          );
          break;
      }

      return;
    }

    if (Provider.of<AuthController>(context, listen: false).isLoggedIn()) {
      await Provider.of<AuthController>(context, listen: false)
          .updateToken(context);

      if (!mounted) {
        return;
      }

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (context) => const DashboardScreen()),
      );
    } else {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (context) => const AuthScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Center(
        child: CircularProgressIndicator(
          valueColor: AlwaysStoppedAnimation<Color>(
            Theme.of(context).primaryColor,
          ),
        ),
      ),
    );
  }

  int compareVersions(String version1, String version2) {
    List<String> v1Components = version1.split('.');
    List<String> v2Components = version2.split('.');

    int maxLength = v1Components.length > v2Components.length
        ? v1Components.length
        : v2Components.length;

    for (int i = 0; i < maxLength; i++) {
      int v1Part = i < v1Components.length ? int.tryParse(v1Components[i]) ?? 0 : 0;
      int v2Part = i < v2Components.length ? int.tryParse(v2Components[i]) ?? 0 : 0;

      if (v1Part > v2Part) return 1;
      if (v1Part < v2Part) return -1;
    }

    return 0;
  }


}
