class KycStatusModel {
  final bool? required;
  final String? status;
  final bool? isVerified;
  final bool? isInProgress;
  final bool? needsResubmission;
  final String? applicantId;
  final String? levelName;
  final String? rejectionReason;
  final List<dynamic>? rejectLabels;
  final String? verifiedAt;
  final String? requiredAt;
  final num? threshold;
  final bool? verificationEnabled;
  final String? accountStatus;

  KycStatusModel({
    this.required,
    this.status,
    this.isVerified,
    this.isInProgress,
    this.needsResubmission,
    this.applicantId,
    this.levelName,
    this.rejectionReason,
    this.rejectLabels,
    this.verifiedAt,
    this.requiredAt,
    this.threshold,
    this.verificationEnabled,
    this.accountStatus,
  });

  factory KycStatusModel.fromJson(Map<String, dynamic>? json) {
    json ??= <String, dynamic>{};

    return KycStatusModel(
      required: json['required'] as bool?,
      status: json['status'] as String?,
      isVerified: json['is_verified'] as bool?,
      isInProgress: json['is_in_progress'] as bool?,
      needsResubmission: json['needs_resubmission'] as bool?,
      applicantId: json['applicant_id']?.toString(),
      levelName: json['level_name'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      rejectLabels: json['reject_labels'] as List<dynamic>?,
      verifiedAt: json['verified_at'] as String?,
      requiredAt: json['required_at'] as String?,
      threshold: json['threshold'] as num?,
      verificationEnabled: json['verification_enabled'] as bool?,
      accountStatus: json['account_status'] as String?,
    );
  }

  bool get isApproved => isVerified == true;

  bool get isPending => isInProgress == true;

  /// A vendor stays locked out of the dashboard until verification passes.
  bool get isBlocked => !isApproved;

  bool get canStartVerification =>
      verificationEnabled != false && !isApproved && !isPending;

  /// KYC passed but the marketplace admin has not activated the shop yet.
  bool get awaitsAdminApproval =>
      isApproved && accountStatus != null && accountStatus != 'approved';
}
