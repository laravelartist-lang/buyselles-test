class DisputeMessageModel {
  final int id;
  final int disputeId;
  final int senderId;
  final String senderType;
  final String message;
  final String? createdAt;

  const DisputeMessageModel({
    required this.id,
    required this.disputeId,
    required this.senderId,
    required this.senderType,
    required this.message,
    this.createdAt,
  });

  bool get isFromBuyer => senderType == 'buyer';
  bool get isFromSystem => senderType == 'system';

  factory DisputeMessageModel.fromJson(Map<String, dynamic> json) {
    return DisputeMessageModel(
      id: int.tryParse('${json['id']}') ?? 0,
      disputeId: int.tryParse('${json['dispute_id']}') ?? 0,
      senderId: int.tryParse('${json['sender_id']}') ?? 0,
      senderType: json['sender_type'] as String? ?? 'buyer',
      message: json['message'] as String? ?? '',
      createdAt: json['created_at'] as String?,
    );
  }
}
