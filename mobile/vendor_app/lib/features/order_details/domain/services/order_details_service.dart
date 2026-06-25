import 'dart:io';

import 'package:sixvalley_vendor_app/features/order_details/domain/models/order_setup_model.dart';
import 'package:sixvalley_vendor_app/features/order_details/domain/repositories/order_details_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/order_details/domain/services/order_details_service_interface.dart';

class OrderDetailsService implements OrderDetailsServiceInterface {
  final OrderDetailsRepositoryInterface orderDetailsRepositoryInterface;
  OrderDetailsService({required this.orderDetailsRepositoryInterface});

  @override
  Future getOrderDetails(String orderID) {
    return orderDetailsRepositoryInterface.getOrderDetails(orderID);
  }

  @override
  Future getOrderStatusList(String type) {
    return orderDetailsRepositoryInterface.getOrderStatusList(type);
  }

  @override
  Future uploadAfterSellDigitalProduct(File? filePath, String token, String orderId) {
    return orderDetailsRepositoryInterface.uploadAfterSellDigitalProduct(filePath, token, orderId);
  }

  @override
  Future<HttpClientResponse> productDownload(String url) async{
    return await orderDetailsRepositoryInterface.productDownload(url);
  }

  @override
  Future setUpOrder(OrderSetupModel orderSetUpModel) {
    return orderDetailsRepositoryInterface.setUpOrder(orderSetUpModel);
  }

  @override
  Future getOrderInvoice(String orderID) async{
    return await orderDetailsRepositoryInterface.getOrderInvoice(orderID);
  }

}