import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/models/dispute_model.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/services/dispute_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/main.dart';

class DisputeController extends ChangeNotifier {
  final DisputeServiceInterface disputeServiceInterface;

  DisputeController({required this.disputeServiceInterface});

  List<DisputeModel>? _disputes;
  List<DisputeModel>? get disputes => _disputes;

  DisputeModel? _selectedDispute;
  DisputeModel? get selectedDispute => _selectedDispute;

  Map<String, int>? _statusCounts;
  Map<String, int>? get statusCounts => _statusCounts;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isDetailLoading = false;
  bool get isDetailLoading => _isDetailLoading;

  bool _isSubmitting = false;
  bool get isSubmitting => _isSubmitting;

  bool _isUploading = false;
  bool get isUploading => _isUploading;

  bool _isPaginationLoading = false;
  bool get isPaginationLoading => _isPaginationLoading;

  int _currentPage = 1;
  int? _totalSize;
  int? get totalSize => _totalSize;
  bool _hasMore = false;
  bool get hasMore => _hasMore;

  List<XFile> _selectedFiles = [];
  List<XFile> get selectedFiles => _selectedFiles;

  String _currentStatus = 'all';
  String get currentStatus => _currentStatus;

  final TextEditingController messageController = TextEditingController();

  void setStatusFilter(String status) {
    _currentStatus = status;
    notifyListeners();
    getDisputes();
  }

  void selectFile(XFile file) {
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

  Future<void> getDisputes({bool reload = true}) async {
    if (reload) {
      _isLoading = true;
      _disputes = null;
      _currentPage = 1;
      _hasMore = false;
    } else {
      if (_isPaginationLoading || !_hasMore) {
        return;
      }
      _isPaginationLoading = true;
    }
    notifyListeners();

    final ApiResponse resp = await disputeServiceInterface.getDisputes(
      status: _currentStatus,
      page: _currentPage,
    );

    if (resp.response != null && resp.response!.statusCode == 200) {
      final data = resp.response!.data;

      if (data['counts'] != null) {
        _statusCounts = Map<String, int>.from(
          (data['counts'] as Map).map((k, v) => MapEntry(k.toString(), int.tryParse(v.toString()) ?? 0)),
        );
      }

      final paginated = data['data'];
      final List<dynamic> items = paginated?['data'] ?? [];
      final int lastPage = int.tryParse('${paginated?['last_page']}') ?? 1;
      _totalSize = int.tryParse('${paginated?['total']}');
      _hasMore = _currentPage < lastPage;

      if (reload) {
        _disputes = [];
      } else {
        _disputes ??= [];
      }

      for (final item in items) {
        if (item is Map) {
          _disputes!.add(DisputeModel.fromJson(item.cast<String, dynamic>()));
        }
      }
    } else {
      ApiChecker.checkApi(resp);
    }

    _isLoading = false;
    _isPaginationLoading = false;
    notifyListeners();
  }

  Future<void> loadMoreDisputes() async {
    if (!_hasMore || _isPaginationLoading) {
      return;
    }

    _currentPage++;
    await getDisputes(reload: false);
  }

  Future<void> getDisputeDetail(int id) async {
    _isDetailLoading = true;
    _selectedDispute = null;
    notifyListeners();

    final ApiResponse resp = await disputeServiceInterface.getDisputeDetail(id);

    if (resp.response != null && resp.response!.statusCode == 200) {
      final data = resp.response!.data['data'];
      if (data is Map) {
        _selectedDispute = DisputeModel.fromJson(data.cast<String, dynamic>());
      }
    } else {
      ApiChecker.checkApi(resp);
    }

    _isDetailLoading = false;
    notifyListeners();
  }

  Future<void> sendMessage(int disputeId) async {
    final text = messageController.text.trim();
    if (text.isEmpty) return;

    _isSubmitting = true;
    notifyListeners();

    final ApiResponse resp = await disputeServiceInterface.sendMessage(disputeId, text);

    _isSubmitting = false;

    if (resp.response != null && resp.response!.statusCode == 200) {
      messageController.clear();
      showCustomSnackBarWidget(
        getTranslated('response_submitted_successfully', Get.context!),
        Get.context!,
        sanckBarType: SnackBarType.success,
      );
      await getDisputeDetail(disputeId);
    } else {
      ApiChecker.checkApi(resp);
      notifyListeners();
    }
  }

  Future<void> uploadEvidence(int disputeId) async {
    if (_selectedFiles.isEmpty) return;

    _isUploading = true;
    notifyListeners();

    final files = _selectedFiles.map((f) => File(f.path)).toList();

    final ApiResponse resp = await disputeServiceInterface.uploadEvidence(disputeId, files);

    _isUploading = false;

    if (resp.response != null && (resp.response!.statusCode == 200 || resp.response!.statusCode == 201)) {
      _selectedFiles = [];
      showCustomSnackBarWidget(
        getTranslated('evidence_uploaded_successfully', Get.context!),
        Get.context!,
        sanckBarType: SnackBarType.success,
      );
      await getDisputeDetail(disputeId);
    } else {
      showCustomSnackBarWidget(
        getTranslated('something_went_wrong', Get.context!),
        Get.context!,
        sanckBarType: SnackBarType.error,
      );
      notifyListeners();
    }
  }

  Future<void> escalateDispute(int disputeId) async {
    _isSubmitting = true;
    notifyListeners();

    final ApiResponse resp = await disputeServiceInterface.escalate(disputeId);

    _isSubmitting = false;

    if (resp.response != null && resp.response!.statusCode == 200) {
      showCustomSnackBarWidget(
        getTranslated('dispute_escalated_to_admin_for_review', Get.context!),
        Get.context!,
        sanckBarType: SnackBarType.success,
      );
      await getDisputeDetail(disputeId);
    } else {
      ApiChecker.checkApi(resp);
      notifyListeners();
    }
  }

  @override
  void dispose() {
    messageController.dispose();
    super.dispose();
  }
}
