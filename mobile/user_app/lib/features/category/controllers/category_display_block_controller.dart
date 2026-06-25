import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_display_block_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/repositories/category_display_block_repository.dart';

class CategoryDisplayBlockController extends ChangeNotifier {
  final CategoryDisplayBlockRepository repository;

  CategoryDisplayBlockController({required this.repository});

  CategoryDisplayBlockResponse? _response;
  CategoryDisplayBlockResponse? get response => _response;

  List<DisplayBlock>? get blocks => _response?.blocks;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  String? _error;
  String? get error => _error;

  String? _lastCategoryId;

  Future<void> loadBlocks(String categoryId) async {
    debugPrint('CategoryDisplayBlockController: loadBlocks called for categoryId=$categoryId');
    _lastCategoryId = categoryId;
    _response = null;
    _error = null;
    _isLoading = true;
    notifyListeners();

    try {
      final apiResponse = await repository.getDisplayBlocks(categoryId);
      debugPrint('CategoryDisplayBlockController: API response received, isSuccess=${apiResponse.isSuccess}, hasResponse=${apiResponse.response != null}');

      if (_lastCategoryId != categoryId) {
        debugPrint('CategoryDisplayBlockController: stale response for $categoryId, current=$_lastCategoryId — discarding');
        return;
      }

      if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
        final rawData = apiResponse.response!.data;
        debugPrint('CategoryDisplayBlockController: rawData type=${rawData.runtimeType}, keys=${rawData is Map ? rawData.keys.toList() : "N/A"}');

        _response = CategoryDisplayBlockResponse.fromJson(rawData);
        debugPrint('CategoryDisplayBlockController: parsed ${_response?.blocks?.length ?? 0} blocks');
        _response?.blocks?.sort((a, b) => (a.position ?? 0).compareTo(b.position ?? 0));
        if (_response?.blocks == null || _response!.blocks!.isEmpty) {
          debugPrint('CategoryDisplayBlockController: no blocks found for category $categoryId');
          _error = 'no_blocks_configured';
        }
      } else {
        final statusCode = apiResponse.response?.statusCode;
        debugPrint('CategoryDisplayBlockController: non-200 response for category $categoryId, statusCode=$statusCode, error=${apiResponse.error}');
        _error = 'server_error';
      }
    } catch (e, stackTrace) {
      if (_lastCategoryId != categoryId) return;
      debugPrint('CategoryDisplayBlockController error for category $categoryId: $e');
      debugPrint('Stack trace: $stackTrace');
      _error = 'network_error';
    }

    if (_lastCategoryId != categoryId) return;

    _isLoading = false;
    debugPrint('CategoryDisplayBlockController: loadBlocks complete for categoryId=$categoryId, blocks=${_response?.blocks?.length ?? 0}, error=$_error');
    notifyListeners();
  }

  void clear() {
    _response = null;
    _error = null;
    _isLoading = false;
    _lastCategoryId = null;
  }
}
