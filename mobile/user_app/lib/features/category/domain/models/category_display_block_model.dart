import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';

class CategoryDisplayBlockResponse {
  CategoryModel? _category;
  List<DisplayBlock>? _blocks;

  CategoryDisplayBlockResponse({CategoryModel? category, List<DisplayBlock>? blocks}) {
    _category = category;
    _blocks = blocks;
  }

  CategoryModel? get category => _category;
  List<DisplayBlock>? get blocks => _blocks;

  CategoryDisplayBlockResponse.fromJson(Map<String, dynamic> json) {
    _category = json['category'] != null ? CategoryModel.fromJson(json['category']) : null;
    if (json['blocks'] != null) {
      _blocks = [];
      json['blocks'].forEach((v) {
        _blocks!.add(DisplayBlock.fromJson(v));
      });
    }
  }
}

class DisplayBlock {
  int? _id;
  String? _blockType;
  int? _position;
  bool? _isActive;
  Map<String, dynamic>? _settings;

  DisplayBlock({
    int? id,
    String? blockType,
    int? position,
    bool? isActive,
    Map<String, dynamic>? settings,
  }) {
    _id = id;
    _blockType = blockType;
    _position = position;
    _isActive = isActive;
    _settings = settings;
  }

  int? get id => _id;
  String? get blockType => _blockType;
  int? get position => _position;
  bool? get isActive => _isActive;
  Map<String, dynamic>? get settings => _settings;

  DisplayBlock.fromJson(Map<String, dynamic> json) {
    _id = json['id'];
    _blockType = json['block_type'];
    _position = json['position'];
    _isActive = json['is_active'];
    _settings = json['settings'] != null ? Map<String, dynamic>.from(json['settings']) : null;
  }
}
