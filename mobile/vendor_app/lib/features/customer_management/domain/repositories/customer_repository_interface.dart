import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';

abstract class CustomerRepositoryInterface {
  Future<ApiResponse> getCustomerList(String type);

  Future<ApiResponse> searchCustomers(String name);

  Future<ApiResponse> addCustomer(CustomerBody customerBody);
}
