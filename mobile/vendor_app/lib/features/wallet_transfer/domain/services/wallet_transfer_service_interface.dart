abstract class WalletTransferServiceInterface {
  Future<dynamic> getTransferList({int? limit});
  Future<dynamic> searchCustomers(String term);
  Future<dynamic> transfer(int customerId, String amount, {String? reference});
}
