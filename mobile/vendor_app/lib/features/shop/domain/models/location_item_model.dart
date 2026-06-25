class LocationItemModel {
  final int id;
  final String name;

  LocationItemModel({required this.id, required this.name});

  factory LocationItemModel.fromJson(Map<String, dynamic> json) {
    return LocationItemModel(
      id: json['id'] as int,
      name: json['name']?.toString() ?? '',
    );
  }
}
