class LocationCountry {
  int? id;
  String? name;
  String? code;

  LocationCountry({this.id, this.name, this.code});

  LocationCountry.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
    code = json['code'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    data['code'] = code;
    return data;
  }
}

class LocationCity {
  int? id;
  int? countryId;
  String? name;

  LocationCity({this.id, this.countryId, this.name});

  LocationCity.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    countryId = json['country_id'];
    name = json['name'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['country_id'] = countryId;
    data['name'] = name;
    return data;
  }
}

class LocationArea {
  int? id;
  int? cityId;
  String? name;

  LocationArea({this.id, this.cityId, this.name});

  LocationArea.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    cityId = json['city_id'];
    name = json['name'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['city_id'] = cityId;
    data['name'] = name;
    return data;
  }
}
