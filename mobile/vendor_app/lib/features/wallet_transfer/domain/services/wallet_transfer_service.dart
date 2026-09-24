import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/repositories/wallet_transfer_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/wallet_transfer/domain/services/wallet_transfer_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';

class WalletTransferService implements WalletTransferServiceInterface {
  final WalletTransferRepositoryInterface walletTransferRepositoryInterface;

  WalletTransferService({required this.walletTransferRepositoryInterface});

  @override
  Future getTransferList({int? limit}) async {
    final ApiResponse apiResponse = await walletTransferRepositoryInterface.getTransferList(limit: limit);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }

    ApiChecker.checkApi(apiResponse);

    return apiResponse;
  }

  @override
  Future searchCustomers(String term) async {
    final ApiResponse apiResponse = await walletTransferRepositoryInterface.searchCustomers(term);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }

    ApiChecker.checkApi(apiResponse);

    return apiResponse;
  }

  @override
  Future transfer(int customerId, String amount, {String? reference}) async {
    final ApiResponse apiResponse = await walletTransferRepositoryInterface.transfer(
      customerId,
      amount,
      reference: reference,
    );
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }

    ApiChecker.checkApi(apiResponse);

    return apiResponse;
  }
}
