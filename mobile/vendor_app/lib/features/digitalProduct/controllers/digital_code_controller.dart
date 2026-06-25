import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/models/digital_code_model.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/services/digital_code_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class DigitalCodeController extends ChangeNotifier {
  final DigitalCodeServiceInterface digitalCodeServiceInterface;

  DigitalCodeController({required this.digitalCodeServiceInterface});

  // ─── State ──────────────────────────────────────────────────────────────────
  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isUploading = false;
  bool get isUploading => _isUploading;

  bool _isToggling = false;
  bool get isToggling => _isToggling;

  bool _isRevealing = false;
  bool get isRevealing => _isRevealing;

  DigitalCodeModel? _codeModel;
  DigitalCodeModel? get codeModel => _codeModel;

  DigitalCodeImportSummary? _lastImportSummary;
  DigitalCodeImportSummary? get lastImportSummary => _lastImportSummary;

  final Map<int, String> _revealedCodes = {};
  Map<int, String> get revealedCodes => _revealedCodes;

  String? getRevealedCode(int codeId) => _revealedCodes[codeId];

  // ─── Bulk Import ────────────────────────────────────────────────────────────

  Future<bool> downloadBulkTemplate() async {
    _isLoading = true;
    notifyListeners();

    final bytes = await digitalCodeServiceInterface.downloadBulkTemplate();
    if (bytes != null) {
      await _saveFile(bytes, 'digital-code-bulk-template.xlsx');
      _isLoading = false;
      notifyListeners();
      return true;
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> uploadBulkImport(String filePath) async {
    _isUploading = true;
    notifyListeners();

    final result = await digitalCodeServiceInterface.uploadBulkImport(filePath);
    _isUploading = false;

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('your_file_has_been_queued_for_processing', Get.context!) ??
            'Your file has been queued for processing',
        Get.context!,
        isToaster: true,
        isError: false,
      );
      notifyListeners();
      return true;
    }

    ApiChecker.checkApi(result as ApiResponse);
    notifyListeners();
    return false;
  }

  // ─── Per-Product Code Management ────────────────────────────────────────────

  Future<void> loadProductCodes(int productId, {int? offset}) async {
    _isLoading = true;
    notifyListeners();

    final result = await digitalCodeServiceInterface.getProductCodes(
      productId,
      offset: offset ?? 1,
    );

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      if (data != null) {
        _codeModel = DigitalCodeModel.fromJson(data);
      }
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> downloadProductTemplate(int productId) async {
    _isLoading = true;
    notifyListeners();

    final bytes = await digitalCodeServiceInterface.downloadProductTemplate(productId);
    if (bytes != null) {
      await _saveFile(bytes, 'codes-template.xlsx');
      _isLoading = false;
      notifyListeners();
      return true;
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<DigitalCodeImportSummary?> uploadProductImport(int productId, String filePath) async {
    _isUploading = true;
    notifyListeners();

    final result = await digitalCodeServiceInterface.uploadProductImport(productId, filePath);
    _isUploading = false;

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      if (data != null) {
        _lastImportSummary = DigitalCodeImportSummary.fromJson(data['summary'] ?? {});
      }
      await loadProductCodes(productId);
      notifyListeners();
      return _lastImportSummary;
    }

    ApiChecker.checkApi(result as ApiResponse);
    notifyListeners();
    return null;
  }

  Future<bool> addSingleCode(int productId, String code, {String? serialNumber, String? expiryDate}) async {
    _isLoading = true;
    notifyListeners();

    final result = await digitalCodeServiceInterface.addSingleCode(
      productId, code,
      serialNumber: serialNumber,
      expiryDate: expiryDate,
    );

    _isLoading = false;

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('code_added_successfully', Get.context!) ?? 'Code added successfully',
        Get.context!,
        isToaster: true,
        isError: false,
      );
      await loadProductCodes(productId);
      notifyListeners();
      return true;
    }

    if (result is ApiResponse && result.response?.statusCode == 409) {
      showCustomSnackBarWidget(
        getTranslated('this_code_or_serial_number_already_exists', Get.context!) ??
            'This code or serial number already exists',
        Get.context!,
        isToaster: true,
        isError: true,
      );
    } else {
      ApiChecker.checkApi(result as ApiResponse);
    }

    notifyListeners();
    return false;
  }

  Future<bool> toggleCodeStatus(int codeId) async {
    final result = await digitalCodeServiceInterface.toggleCodeStatus(codeId);
    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      return data['success'] == true;
    }
    return false;
  }

  Future<Map<String, dynamic>?> decryptAndReveal(int codeId) async {
    _isRevealing = true;
    notifyListeners();

    final result = await digitalCodeServiceInterface.decryptCode(codeId);

    _isRevealing = false;

    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      if (data != null && (data['pin'] != null || data['code'] != null)) {
        _revealedCodes[codeId] = (data['pin'] ?? data['code']).toString();
        notifyListeners();
      }
      return data;
    }

    notifyListeners();
    return null;
  }

  void hideRevealedCode(int codeId) {
    _revealedCodes.remove(codeId);
    notifyListeners();
  }

  Future<bool> deleteCode(int codeId) async {
    final result = await digitalCodeServiceInterface.deleteCode(codeId);
    if (result != null && result is ApiResponse && result.response?.statusCode == 200) {
      final data = result.response?.data;
      return data['success'] == true;
    }
    return false;
  }

  Future<FilePickerResult?> pickExcelFile() async {
    return FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['xlsx', 'xls', 'csv'],
    );
  }

  Future<String> _saveFile(Uint8List bytes, String fileName) async {
    final dir = await getApplicationDocumentsDirectory();
    final file = File('${dir.path}/$fileName');
    await file.writeAsBytes(bytes);
    return file.path;
  }

  void clearImportSummary() {
    _lastImportSummary = null;
    notifyListeners();
  }
}
