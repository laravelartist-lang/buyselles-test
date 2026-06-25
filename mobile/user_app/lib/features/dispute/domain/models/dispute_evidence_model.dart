class DisputeEvidenceModel {
  final int id;
  final int disputeId;
  final String filePath;
  final String fileType;
  final String userType;
  final String? createdAt;

  const DisputeEvidenceModel({
    required this.id,
    required this.disputeId,
    required this.filePath,
    required this.fileType,
    required this.userType,
    this.createdAt,
  });

  bool get isVideo => fileType == 'video';
  bool get isImage => fileType == 'image';

  factory DisputeEvidenceModel.fromJson(Map<String, dynamic> json) {
    return DisputeEvidenceModel(
      id: int.tryParse('${json['id']}') ?? 0,
      disputeId: int.tryParse('${json['dispute_id']}') ?? 0,
      filePath: json['file_path'] as String? ?? '',
      fileType: json['file_type'] as String? ?? 'image',
      userType: json['user_type'] as String? ?? 'buyer',
      createdAt: json['created_at'] as String?,
    );
  }
}
