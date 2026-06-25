import 'package:sixvalley_vendor_app/utill/app_constants.dart';

class DisputeEvidenceModel {
  final int id;
  final int disputeId;
  final int? uploadedBy;
  final String userType;
  final String filePath;
  final String fileType;
  final String? originalName;
  final int? fileSize;
  final String? caption;
  final String? createdAt;

  const DisputeEvidenceModel({
    required this.id,
    required this.disputeId,
    this.uploadedBy,
    required this.userType,
    required this.filePath,
    required this.fileType,
    this.originalName,
    this.fileSize,
    this.caption,
    this.createdAt,
  });

  bool get isVideo => fileType == 'video';
  bool get isImage => fileType == 'image';
  bool get isFromBuyer => userType == 'buyer';
  bool get isFromVendor => userType == 'vendor';

  String get fullFileUrl {
    if (filePath.isEmpty) {
      return '';
    }
    if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
      return filePath;
    }

    return '${AppConstants.baseUrl}/storage/$filePath';
  }

  factory DisputeEvidenceModel.fromJson(Map<String, dynamic> json) {
    return DisputeEvidenceModel(
      id: int.tryParse('${json['id']}') ?? 0,
      disputeId: int.tryParse('${json['dispute_id']}') ?? 0,
      uploadedBy: json['uploaded_by'] == null ? null : int.tryParse('${json['uploaded_by']}'),
      userType: json['user_type'] as String? ?? 'buyer',
      filePath: json['file_path'] as String? ?? '',
      fileType: json['file_type'] as String? ?? 'image',
      originalName: json['original_name'] as String?,
      fileSize: json['file_size'] == null ? null : int.tryParse('${json['file_size']}'),
      caption: json['caption'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
