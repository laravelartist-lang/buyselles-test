import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';

abstract class WalletTransferRepositoryInterface {
  Future<ApiResponse> getTransferList({int? limit});
  Future<ApiResponse> searchCustomers(String term);
  Future<ApiResponse> transfer(int customerId, String amount, {String? reference});
}
