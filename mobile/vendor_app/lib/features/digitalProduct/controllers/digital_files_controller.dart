import 'package:flutter/material.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/services/digital_files_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';

class DigitalFilesController with ChangeNotifier {
  final DigitalFilesServiceInterface digitalFilesServiceInterface;

  DigitalFilesController({required this.digitalFilesServiceInterface});

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isUploading = false;
  bool get isUploading => _isUploading;

  bool _isDeleting = false;
  bool get isDeleting => _isDeleting;

  /// The currently loaded ready-file path (from product details digitalFileReady)
  String? _currentFileName;
  String? get currentFileName => _currentFileName;

  String? _currentFileUrl;
  String? get currentFileUrl => _currentFileUrl;

  bool _hasFile = false;
  bool get hasFile => _hasFile;

  void _setCurrentFile(dynamic productData) {
    final data = productData is Map ? productData : null;
    final filePath = data?['digital_file_ready'];
    final fileUrl = data?['digital_file_ready_full_url']?['path'];
    if (filePath != null && filePath.toString().isNotEmpty) {
      _hasFile = true;
      _currentFileName = filePath.toString().split('/').last;
      _currentFileUrl = fileUrl?.toString();
    } else {
      _hasFile = false;
      _currentFileName = null;
      _currentFileUrl = null;
    }
  }

  Future<void> loadProductFileInfo(int productId) async {
    _isLoading = true;
    notifyListeners();

    final dynamic result = await digitalFilesServiceInterface.getProductDetails(productId);
    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      _setCurrentFile(data);
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> uploadFile(int productId, String filePath) async {
    _isUploading = true;
    notifyListeners();

    final dynamic result = await digitalFilesServiceInterface.uploadDigitalFile(productId, filePath);
    _isUploading = false;

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('file_uploaded_successfully', Get.context!) ?? 'File uploaded successfully',
        Get.context!,
        isToaster: true,
        isError: false,
      );
      await loadProductFileInfo(productId);
      return true;
    } else {
      ApiChecker.checkApi(result as ApiResponse);
      notifyListeners();
      return false;
    }
  }

  Future<bool> deleteFile(int productId) async {
    _isDeleting = true;
    notifyListeners();

    final ApiResponse result = await digitalFilesServiceInterface.deleteDigitalFile(productId) as ApiResponse;
    _isDeleting = false;

    if (result.response?.statusCode == 200) {
      _hasFile = false;
      _currentFileName = null;
      _currentFileUrl = null;
      showCustomSnackBarWidget(
        getTranslated('file_deleted_successfully', Get.context!) ?? 'File deleted successfully',
        Get.context!,
        isToaster: true,
        isError: false,
      );
      notifyListeners();
      return true;
    } else {
      ApiChecker.checkApi(result);
      notifyListeners();
      return false;
    }
  }
}
