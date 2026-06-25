import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';

abstract class CustomerServiceInterface {
  Future<dynamic> getCustomerList(String type);

  Future<dynamic> searchCustomers(String name);

  Future<dynamic> addCustomer(CustomerBody customerBody);
}
