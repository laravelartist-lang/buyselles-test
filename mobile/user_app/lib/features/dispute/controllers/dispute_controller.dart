import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_reason_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/services/dispute_service_interface.dart';
import 'package:flutter_sixvalley_ecommerce/helper/api_checker.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

class DisputeController with ChangeNotifier {
  final DisputeServiceInterface disputeServiceInterface;

  DisputeController({required this.disputeServiceInterface});

  // ── Reasons ──────────────────────────────────────────────────────────────
  List<DisputeReasonModel>? _reasons;
  List<DisputeReasonModel>? get reasons => _reasons;

  // ── Dispute list ─────────────────────────────────────────────────────────
  List<DisputeModel>? _disputes;
  List<DisputeModel>? get disputes => _disputes;

  // ── Active dispute detail ─────────────────────────────────────────────────
  DisputeModel? _selectedDispute;
  DisputeModel? get selectedDispute => _selectedDispute;

  // ── Loading flags ─────────────────────────────────────────────────────────
  bool _isLoading = false;
  bool get isLoading => _isLoading;
  bool _isDetailLoading = false;
  bool get isDetailLoading => _isDetailLoading;
  bool _isSubmitting = false;
  bool get isSubmitting => _isSubmitting;
  bool _isUploadingEvidence = false;
  bool get isUploadingEvidence => _isUploadingEvidence;

  // ── Open dispute form state ───────────────────────────────────────────────
  DisputeReasonModel? _selectedReason;
  DisputeReasonModel? get selectedReason => _selectedReason;
  List<XFile> _selectedFiles = [];
  List<XFile> get selectedFiles => _selectedFiles;

  final TextEditingController messageTextController = TextEditingController();

  void setSelectedReason(DisputeReasonModel? reason) {
    _selectedReason = reason;
    notifyListeners();
  }

  void addFile(XFile file) {
    if (_selectedFiles.length < 5) {
      _selectedFiles.add(file);
      notifyListeners();
    }
  }

  void removeFile(int index) {
    _selectedFiles.removeAt(index);
    notifyListeners();
  }

  void clearFiles() {
    _selectedFiles = [];
    notifyListeners();
  }

  // ── API calls ─────────────────────────────────────────────────────────────

  Future<void> loadReasons() async {
    final ApiResponseModel resp = await disputeServiceInterface.getReasons();
    if (resp.response != null && resp.response!.statusCode == 200) {
      final List<dynamic> raw = resp.response!.data['data'] ?? [];
      _reasons = [];
      for (final reason in raw) {
        if (reason is Map) {
          _reasons!
              .add(DisputeReasonModel.fromJson(reason.cast<String, dynamic>()));
        }
      }
      notifyListeners();
      return;
    }

    _reasons = [];
    ApiChecker.checkApi(resp);
    notifyListeners();
  }

  Future<void> loadDisputes() async {
    _isLoading = true;
    _disputes = null;
    notifyListeners();
    final ApiResponseModel resp = await disputeServiceInterface.getDisputes();
    if (resp.response != null && resp.response!.statusCode == 200) {
      final dynamic raw = resp.response!.data['data'];
      final List<dynamic> items = raw is Map
          ? (raw['data'] as List<dynamic>? ?? [])
          : (raw as List<dynamic>? ?? []);
      _disputes = [];
      for (final dispute in items) {
        if (dispute is Map) {
          _disputes!
              .add(DisputeModel.fromJson(dispute.cast<String, dynamic>()));
        }
      }
    } else {
      ApiChecker.checkApi(resp);
    }
    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadDispute(int id) async {
    _isDetailLoading = true;
    _selectedDispute = null;
    notifyListeners();
    final ApiResponseModel resp = await disputeServiceInterface.getDispute(id);
    if (resp.response != null && resp.response!.statusCode == 200) {
      final dynamic payload = resp.response!.data['data'];
      if (payload is Map) {
        _selectedDispute =
            DisputeModel.fromJson(payload.cast<String, dynamic>());
      }
    } else {
      ApiChecker.checkApi(resp);
    }
    _isDetailLoading = false;
    notifyListeners();
  }

  Future<int?> createDispute({
    required int orderId,
    required String description,
    List<XFile>? files,
  }) async {
    _isSubmitting = true;
    notifyListeners();
    final ApiResponseModel resp = await disputeServiceInterface.createDispute(
      orderId: orderId,
      reasonId: _selectedReason?.id,
      description: description,
      files: files,
    );
    _isSubmitting = false;
    notifyListeners();
    if (resp.response != null &&
        (resp.response!.statusCode == 200 ||
            resp.response!.statusCode == 201)) {
      final dynamic created = resp.response!.data['data'];
      final int? createdDisputeId =
          created is Map ? int.tryParse('${created['id']}') : null;

      _selectedReason = null;
      _selectedFiles = [];
      showCustomSnackBarWidget(
        getTranslated('dispute_opened_successfully', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.success,
      );
      loadDisputes();
      return createdDisputeId;
    }
    final String msg = resp.response?.data?['message'] as String? ??
        getTranslated('something_went_wrong', Get.context!) ??
        'Something went wrong';
    showCustomSnackBarWidget(msg, Get.context!,
        snackBarType: SnackBarType.error);
    return null;
  }

  Future<void> sendMessage(int disputeId) async {
    final String text = messageTextController.text.trim();
    if (text.isEmpty) return;
    _isSubmitting = true;
    notifyListeners();
    final ApiResponseModel resp =
        await disputeServiceInterface.addMessage(disputeId, text);
    _isSubmitting = false;
    if (resp.response != null &&
        (resp.response!.statusCode == 200 ||
            resp.response!.statusCode == 201)) {
      messageTextController.clear();
      await loadDispute(disputeId);
    } else {
      ApiChecker.checkApi(resp);
      notifyListeners();
    }
  }

  Future<void> sendEvidence(int disputeId) async {
    if (_selectedFiles.isEmpty) return;
    _isUploadingEvidence = true;
    notifyListeners();
    final http.StreamedResponse resp = await disputeServiceInterface
        .uploadEvidence(disputeId, _selectedFiles) as http.StreamedResponse;
    _isUploadingEvidence = false;
    if (resp.statusCode == 200 || resp.statusCode == 201) {
      _selectedFiles = [];
      showCustomSnackBarWidget(
        getTranslated('evidence_uploaded_successfully', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.success,
      );
      await loadDispute(disputeId);
    } else {
      showCustomSnackBarWidget(
        getTranslated('something_went_wrong', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.error,
      );
      notifyListeners();
    }
  }

  Future<void> escalateDispute(int disputeId) async {
    _isSubmitting = true;
    notifyListeners();
    final ApiResponseModel resp =
        await disputeServiceInterface.escalate(disputeId);
    _isSubmitting = false;
    if (resp.response != null && resp.response!.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('dispute_escalated_to_admin', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.success,
      );
      await loadDispute(disputeId);
    } else {
      ApiChecker.checkApi(resp);
      notifyListeners();
    }
  }

  Future<void> confirmClosure(int disputeId) async {
    _isSubmitting = true;
    notifyListeners();
    final ApiResponseModel resp =
        await disputeServiceInterface.confirmClosure(disputeId);
    _isSubmitting = false;
    if (resp.response != null && resp.response!.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('dispute_closed_successfully', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.success,
      );
      await loadDispute(disputeId);
    } else {
      ApiChecker.checkApi(resp);
      notifyListeners();
    }
  }

  Future<void> confirmReceipt(int orderId) async {
    _isSubmitting = true;
    notifyListeners();
    final ApiResponseModel resp =
        await disputeServiceInterface.confirmReceipt(orderId);
    _isSubmitting = false;
    if (resp.response != null && resp.response!.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated(
            'receipt_confirmed_funds_released_to_vendor', Get.context!),
        Get.context!,
        snackBarType: SnackBarType.success,
      );
    } else {
      ApiChecker.checkApi(resp);
    }
    notifyListeners();
  }

  @override
  void dispose() {
    messageTextController.dispose();
    super.dispose();
  }
}
