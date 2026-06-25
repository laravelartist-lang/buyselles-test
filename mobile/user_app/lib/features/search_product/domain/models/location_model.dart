class CountryModel {
  int? id;
  String? name;
  String? code;
  bool? isActive;

  CountryModel({this.id, this.name, this.code, this.isActive});

  CountryModel.fromJson(Map<String, dynamic> json) {
    id = json['id'] != null ? int.tryParse(json['id'].toString()) ?? json['id'] : null;
    name = json['name']?.toString();
    code = json['code']?.toString();
    isActive = json['is_active'] == true || json['is_active'] == 1 || json['is_active'] == '1';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    data['code'] = code;
    data['is_active'] = isActive;
    return data;
  }
}

class CityModel {
  int? id;
  int? countryId;
  String? name;
  bool? isActive;

  CityModel({this.id, this.countryId, this.name, this.isActive});

  CityModel.fromJson(Map<String, dynamic> json) {
    id = json['id'] != null ? int.tryParse(json['id'].toString()) ?? json['id'] : null;
    countryId = json['country_id'] != null ? int.tryParse(json['country_id'].toString()) ?? json['country_id'] : null;
    name = json['name']?.toString();
    isActive = json['is_active'] == true || json['is_active'] == 1 || json['is_active'] == '1';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['country_id'] = countryId;
    data['name'] = name;
    data['is_active'] = isActive;
    return data;
  }
}

class AreaModel {
  int? id;
  int? cityId;
  String? name;
  bool? isActive;

  AreaModel({this.id, this.cityId, this.name, this.isActive});

  AreaModel.fromJson(Map<String, dynamic> json) {
    id = json['id'] != null ? int.tryParse(json['id'].toString()) ?? json['id'] : null;
    cityId = json['city_id'] != null ? int.tryParse(json['city_id'].toString()) ?? json['city_id'] : null;
    name = json['name']?.toString();
    isActive = json['is_active'] == true || json['is_active'] == 1 || json['is_active'] == '1';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['city_id'] = cityId;
    data['name'] = name;
    data['is_active'] = isActive;
    return data;
  }
}
