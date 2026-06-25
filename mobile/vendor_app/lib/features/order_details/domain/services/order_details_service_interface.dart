import 'dart:io';

import 'package:sixvalley_vendor_app/features/order_details/domain/models/order_setup_model.dart';

abstract class OrderDetailsServiceInterface{
  Future<dynamic> setUpOrder(OrderSetupModel orderSetUpModel);
  Future<dynamic> getOrderDetails(String orderID);
  Future<dynamic> getOrderStatusList(String type);
  Future<dynamic> uploadAfterSellDigitalProduct(File? filePath, String token, String orderId);
  Future<HttpClientResponse> productDownload(String url);

  Future <dynamic> getOrderInvoice(String orderID);

}