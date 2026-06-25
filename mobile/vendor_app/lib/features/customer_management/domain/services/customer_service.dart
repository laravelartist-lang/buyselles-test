import 'package:sixvalley_vendor_app/features/customer_management/domain/repositories/customer_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/customer_management/domain/services/customer_service_interface.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';

class CustomerService implements CustomerServiceInterface {
  final CustomerRepositoryInterface customerRepositoryInterface;

  CustomerService({required this.customerRepositoryInterface});

  @override
  Future addCustomer(CustomerBody customerBody) {
    return customerRepositoryInterface.addCustomer(customerBody);
  }

  @override
  Future getCustomerList(String type) {
    return customerRepositoryInterface.getCustomerList(type);
  }

  @override
  Future searchCustomers(String name) {
    return customerRepositoryInterface.searchCustomers(name);
  }
}
