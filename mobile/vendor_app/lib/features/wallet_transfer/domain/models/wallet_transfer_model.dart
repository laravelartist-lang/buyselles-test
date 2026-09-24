class WalletTransferCustomerModel {
  int? id;
  String? name;
  String? email;
  String? phone;
  double? walletBalance;

  WalletTransferCustomerModel({
    this.id,
    this.name,
    this.email,
    this.phone,
    this.walletBalance,
  });

  WalletTransferCustomerModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
    email = json['email'];
    phone = json['phone'];
    walletBalance = _parseDouble(json['wallet_balance']);
  }
}

class WalletTransferItemModel {
  int? id;
  double? amount;
  String? reference;
  String? createdAt;
  WalletTransferCustomerModel? customer;

  WalletTransferItemModel({
    this.id,
    this.amount,
    this.reference,
    this.createdAt,
    this.customer,
  });

  WalletTransferItemModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    amount = _parseDouble(json['amount']);
    reference = json['reference'];
    createdAt = json['created_at'];
    customer = json['customer'] != null
        ? WalletTransferCustomerModel.fromJson(json['customer'])
        : null;
  }
}

class WalletTransferListModel {
  double? totalEarning;
  double? withdrawableBalance;
  List<WalletTransferItemModel>? transfers;
  int? totalSize;

  WalletTransferListModel({
    this.totalEarning,
    this.withdrawableBalance,
    this.transfers,
    this.totalSize,
  });

  WalletTransferListModel.fromJson(Map<String, dynamic> json) {
    totalEarning = _parseDouble(json['total_earning']);
    withdrawableBalance = _parseDouble(json['withdrawable_balance']);
    totalSize = json['transfers']?['total_size'];
    if (json['transfers']?['data'] != null) {
      transfers = <WalletTransferItemModel>[];
      for (final item in json['transfers']['data']) {
        transfers!.add(WalletTransferItemModel.fromJson(item));
      }
    }
  }
}

double? _parseDouble(dynamic value) {
  if (value == null) {
    return null;
  }
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString());
}
