class CustomerListModel {
  List<CustomerModel>? customers;

  CustomerListModel({this.customers});

  CustomerListModel.fromJson(Map<String, dynamic> json) {
    if (json['customers'] != null) {
      customers = <CustomerModel>[];
      for (final v in json['customers']) {
        customers!.add(CustomerModel.fromJson(v));
      }
    }
  }
}

class CustomerModel {
  int? id;
  String? fName;
  String? lName;
  String? phone;
  String? image;
  String? email;
  double? walletBalance;

  CustomerModel({
    this.id,
    this.fName,
    this.lName,
    this.phone,
    this.image,
    this.email,
    this.walletBalance,
  });

  CustomerModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    fName = json['f_name'] ?? '';
    lName = json['l_name'] ?? '';
    phone = json['phone'];
    image = json['image'];
    email = json['email'];
    if (json['wallet_balance'] != null) {
      walletBalance = double.tryParse(json['wallet_balance'].toString());
    } else {
      walletBalance = 0;
    }
  }

  String get fullName => '${fName ?? ''} ${lName ?? ''}'.trim();
}
