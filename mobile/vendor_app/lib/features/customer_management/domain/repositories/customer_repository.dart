import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/customer_management/domain/repositories/customer_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class CustomerRepository implements CustomerRepositoryInterface {
  final DioClient dioClient;

  CustomerRepository({required this.dioClient});

  @override
  Future<ApiResponse> getCustomerList(String type) async {
    try {
      final response = await dioClient.get('${AppConstants.customerSearchUri}?type=$type');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> searchCustomers(String name) async {
    try {
      final response = await dioClient.get('${AppConstants.customerSearchUri}?name=$name&type=all');
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> addCustomer(CustomerBody customerBody) async {
    try {
      final response = await dioClient.post(
        AppConstants.addNewCustomer,
        data: {
          'f_name': customerBody.fName,
          'l_name': customerBody.lName,
          'email': customerBody.email,
          'phone': customerBody.phone,
          'country': customerBody.country,
          'city': customerBody.city,
          'zip_code': customerBody.zipCode,
          'address': customerBody.address,
        },
      );
      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
