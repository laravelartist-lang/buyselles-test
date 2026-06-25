class AuthorModel {
  int? id;
  String? name;
  String? createdAt;
  String? updatedAt;
  bool? isChecked;
  int? productsCount;

  AuthorModel({this.id, this.name, this.createdAt, this.updatedAt, this.productsCount});

  AuthorModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    isChecked = false;
    productsCount = json['publishing_house_products_count'] ?? json['digital_product_author_count'] ?? json['products_count'] ?? 0;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['products_count'] = productsCount;
    return data;
  }
}
