class DisputeReasonModel {
  final int id;
  final String title;
  final String? description;
  final String applicableTo;
  final String priorityDefault;
  final bool isActive;

  const DisputeReasonModel({
    required this.id,
    required this.title,
    this.description,
    required this.applicableTo,
    required this.priorityDefault,
    this.isActive = true,
  });

  factory DisputeReasonModel.fromJson(Map<String, dynamic> json) {
    return DisputeReasonModel(
      id: int.tryParse('${json['id']}') ?? 0,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      applicableTo: json['applicable_to'] as String? ?? 'both',
      priorityDefault: json['priority_default'] as String? ?? 'medium',
      isActive: json['is_active'] is bool ? json['is_active'] : true,
    );
  }
}
