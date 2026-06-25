import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_evidence_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_message_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/models/dispute_reason_model.dart';

class DisputeModel {
  final int id;
  final int orderId;
  final int? orderDetailId;
  final int buyerId;
  final int vendorId;
  final String initiatedBy;
  final int? reasonId;
  final String description;
  final String status;
  final String priority;
  final String? adminDecision;
  final String? adminNote;
  final String? resolvedAt;
  final String? escalatedAt;
  final String? vendorDeadlineAt;
  final String? createdAt;
  final DisputeReasonModel? reason;
  final List<DisputeMessageModel> messages;
  final List<DisputeEvidenceModel> evidence;

  const DisputeModel({
    required this.id,
    required this.orderId,
    this.orderDetailId,
    required this.buyerId,
    required this.vendorId,
    required this.initiatedBy,
    this.reasonId,
    required this.description,
    required this.status,
    required this.priority,
    this.adminDecision,
    this.adminNote,
    this.resolvedAt,
    this.escalatedAt,
    this.vendorDeadlineAt,
    this.createdAt,
    this.reason,
    this.messages = const [],
    this.evidence = const [],
  });

  bool get isActive => !['resolved_refund', 'resolved_release', 'closed', 'auto_closed'].contains(status);
  bool get isPendingClosure => status == 'pending_closure';
  bool get canEscalate => status == 'open' || status == 'vendor_response';
  bool get isResolved => status == 'resolved_refund' || status == 'resolved_release';

  factory DisputeModel.fromJson(Map<String, dynamic> json) {
    return DisputeModel(
      id: int.tryParse('${json['id']}') ?? 0,
      orderId: int.tryParse('${json['order_id']}') ?? 0,
      orderDetailId: json['order_detail_id'] == null ? null : int.tryParse('${json['order_detail_id']}'),
      buyerId: int.tryParse('${json['buyer_id']}') ?? 0,
      vendorId: int.tryParse('${json['vendor_id']}') ?? 0,
      initiatedBy: json['initiated_by'] as String? ?? 'buyer',
      reasonId: json['reason_id'] == null ? null : int.tryParse('${json['reason_id']}'),
      description: json['description'] as String? ?? '',
      status: json['status'] as String? ?? 'open',
      priority: json['priority'] as String? ?? 'medium',
      adminDecision: json['admin_decision'] as String?,
      adminNote: json['admin_note'] as String?,
      resolvedAt: json['resolved_at'] as String?,
      escalatedAt: json['escalated_at'] as String?,
      vendorDeadlineAt: json['vendor_deadline_at'] as String?,
      createdAt: json['created_at'] as String?,
      reason: json['reason'] != null
          ? DisputeReasonModel.fromJson(json['reason'] as Map<String, dynamic>)
          : null,
      messages: (json['messages'] as List<dynamic>?)
              ?.map((m) => DisputeMessageModel.fromJson(m as Map<String, dynamic>))
              .toList() ??
          [],
      evidence: (json['evidence'] as List<dynamic>?)
              ?.map((e) => DisputeEvidenceModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}
