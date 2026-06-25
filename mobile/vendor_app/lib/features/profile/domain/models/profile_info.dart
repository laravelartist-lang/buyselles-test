import 'package:sixvalley_vendor_app/data/model/image_full_url.dart';

double? _parseDouble(dynamic value, {double? defaultValue}) {
  if (value == null) {
    return defaultValue;
  }
  if (value is double) {
    return value;
  }
  if (value is int) {
    return value.toDouble();
  }
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString()) ?? defaultValue;
}

class ProfileInfoModel {
  int? id;
  String? fName;
  String? lName;
  String? phone;
  String? image;
  ImageFullUrl? imageFullUrl;
  String? email;
  String? password;
  String? status;
  String? rememberToken;
  String? createdAt;
  String? updatedAt;
  String? bankName;
  String? branch;
  String? accountNo;
  String? holderName;
  String? authToken;
  double? salesCommissionPercentage;
  String? gst;
  int? productCount;
  int? posActive;
  int? ordersCount;
  Wallet? wallet;
  double? minimumOrderAmount;
  double? freeOverDeliveryAmount;
  int? freeOverDeliveryAmountStatus;

  ProfileInfoModel(
      {this.id,
        this.fName,
        this.lName,
        this.phone,
        this.image,
        this.imageFullUrl,
        this.email,
        this.password,
        this.status,
        this.rememberToken,
        this.createdAt,
        this.updatedAt,
        this.bankName,
        this.branch,
        this.accountNo,
        this.holderName,
        this.authToken,
        this.salesCommissionPercentage,
        this.gst,
        this.posActive,
        this.productCount,
        this.ordersCount,
        this.wallet,
        this.minimumOrderAmount,
        this.freeOverDeliveryAmount,
        this.freeOverDeliveryAmountStatus
      });

  ProfileInfoModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    fName = json['f_name'];
    lName = json['l_name'];
    phone = json['phone'];
    image = json['image'];
    email = json['email'];
    password = json['password'];
    status = json['status'];
    rememberToken = json['remember_token'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    bankName = json['bank_name'];
    branch = json['branch'];
    accountNo = json['account_no'];
    holderName = json['holder_name'];
    authToken = json['auth_token'];
    if(json['sales_commission_percentage']!=null){
      salesCommissionPercentage = _parseDouble(json['sales_commission_percentage']);
    }
    if(json['gst']!=null){
      gst = json['gst'];
    }
    posActive = int.parse(json['pos_status'].toString());
    productCount = json['product_count'];
    ordersCount = json['orders_count'];
    wallet =
    json['wallet'] != null ? Wallet.fromJson(json['wallet']) : null;
    if(json['minimum_order_amount'] != null){
      minimumOrderAmount = _parseDouble(json['minimum_order_amount'], defaultValue: 0);
    }else{
      minimumOrderAmount = 0;
    }
    if(json['free_delivery_over_amount'] != null){
      freeOverDeliveryAmount = _parseDouble(json['free_delivery_over_amount'], defaultValue: 0);
    }else{
      freeOverDeliveryAmount = 0;
    }

    if(json['free_delivery_status'] != null){
      try{
        freeOverDeliveryAmountStatus = json['free_delivery_status'];
      }catch(e){
        freeOverDeliveryAmountStatus = int.parse(json['free_delivery_status'].toString());
      }
    }else{
      freeOverDeliveryAmountStatus = 0;
    }

    imageFullUrl = json['image_full_url'] != null
        ? ImageFullUrl.fromJson(json['image_full_url'])
        : null;
  }


}

class Wallet {
  int? id;
  double? totalEarning;
  double? withdrawn;
  String? createdAt;
  String? updatedAt;
  double? commissionGiven;
  double? pendingWithdraw;
  double? deliveryChargeEarned;
  double? collectedCash;
  double? totalTaxCollected;
  double? pendingBalance;

  Wallet(
      {this.id,
        this.totalEarning,
        this.withdrawn,
        this.createdAt,
        this.updatedAt,
        this.commissionGiven,
        this.pendingWithdraw,
        this.deliveryChargeEarned,
        this.collectedCash,
        this.totalTaxCollected,
        this.pendingBalance});

  Wallet.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    totalEarning = _parseDouble(json['total_earning'], defaultValue: 0);
    withdrawn = _parseDouble(json['withdrawn'], defaultValue: 0);
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    commissionGiven = _parseDouble(json['commission_given'], defaultValue: 0);
    pendingWithdraw = _parseDouble(json['pending_withdraw'], defaultValue: 0);
    deliveryChargeEarned = _parseDouble(json['delivery_charge_earned'], defaultValue: 0);
    collectedCash = _parseDouble(json['collected_cash'], defaultValue: 0);
    totalTaxCollected = _parseDouble(json['total_tax_collected'], defaultValue: 0);
    pendingBalance = _parseDouble(json['pending_balance'], defaultValue: 0);
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['total_earning'] = totalEarning;
    data['withdrawn'] = withdrawn;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['commission_given'] = commissionGiven;
    data['pending_withdraw'] = pendingWithdraw;
    data['delivery_charge_earned'] = deliveryChargeEarned;
    data['collected_cash'] = collectedCash;
    data['total_tax_collected'] = totalTaxCollected;
    data['pending_balance'] = pendingBalance;
    return data;
  }
}
