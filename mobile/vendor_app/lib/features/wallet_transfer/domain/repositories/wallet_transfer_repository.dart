import 'package:sixvalley_vendor_app/data/datasource/remote/dio/dio_client.dart';
import 'package:sixvalley_vendor_app/data/datasource/remote/exception/api_error_handler.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/repositories/wallet_transfer_repository_interface.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class WalletTransferRepository implements WalletTransferRepositoryInterface {
  final DioClient dioClient;

  WalletTransferRepository({required this.dioClient});

  @override
  Future<ApiResponse> getTransferList({int? limit}) async {
    try {
      final response = await dioClient.get(
        '${AppConstants.walletTransferUri}${limit != null ? '?limit=$limit' : ''}',
      );

      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> searchCustomers(String term) async {
    try {
      final response = await dioClient.get(
        '${AppConstants.walletTransferSearchUri}?term=$term',
      );

      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }

  @override
  Future<ApiResponse> transfer(int customerId, String amount, {String? reference}) async {
    try {
      final response = await dioClient.post(
        AppConstants.walletTransferSubmitUri,
        data: {
          'customer_id': customerId,
          'amount': amount,
          if (reference != null && reference.isNotEmpty) 'reference': reference,
        },
      );

      return ApiResponse.withSuccess(response);
    } catch (e) {
      return ApiResponse.withError(ApiErrorHandler.getMessage(e));
    }
  }
}
