class DisputeReasonModel {
  final int id;
  final String title;
  final String? description;
  final String applicableTo;
  final String priorityDefault;

  const DisputeReasonModel({
    required this.id,
    required this.title,
    this.description,
    required this.applicableTo,
    required this.priorityDefault,
  });

  factory DisputeReasonModel.fromJson(Map<String, dynamic> json) {
    return DisputeReasonModel(
      id: int.tryParse('${json['id']}') ?? 0,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      applicableTo: json['applicable_to'] as String? ?? 'both',
      priorityDefault: json['priority_default'] as String? ?? 'medium',
    );
  }
}
