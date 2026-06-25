class OrderStatisticsModel {
  int? pending;
  int? confirmed;
  int? processing;
  int? outForDelivery;
  int? delivered;
  int? canceled;
  int? returned;
  int? failed;

  OrderStatisticsModel({
    this.pending,
    this.confirmed,
    this.processing,
    this.outForDelivery,
    this.delivered,
    this.canceled,
    this.returned,
    this.failed,
  });

  OrderStatisticsModel.fromJson(Map<String, dynamic> json) {
    pending = json['pending'];
    confirmed = json['confirmed'];
    processing = json['processing'];
    outForDelivery = json['out_for_delivery'];
    delivered = json['delivered'];
    canceled = json['canceled'];
    returned = json['returned'];
    failed = json['failed'];
  }
}

class EarningsReportModel {
  final List<double> sellerEarnings;
  final List<double> commissions;
  final double chartMax;

  EarningsReportModel({
    required this.sellerEarnings,
    required this.commissions,
    required this.chartMax,
  });

  factory EarningsReportModel.fromApiData(Map<String, dynamic> data) {
    final earnings = <double>[];
    final commissions = <double>[];

    for (final item in data['seller_earn'] as List<dynamic>? ?? []) {
      earnings.add(_toDouble(item));
    }
    for (final item in data['commission_earn'] as List<dynamic>? ?? []) {
      commissions.add(_toDouble(item));
    }

    earnings.insert(0, 0);
    commissions.insert(0, 0);

    final maxEarning = earnings.isEmpty ? 0.0 : earnings.reduce((a, b) => a > b ? a : b);
    final maxCommission = commissions.isEmpty ? 0.0 : commissions.reduce((a, b) => a > b ? a : b);

    return EarningsReportModel(
      sellerEarnings: earnings,
      commissions: commissions,
      chartMax: maxEarning > maxCommission ? maxEarning : maxCommission,
    );
  }

  static double _toDouble(dynamic value) {
    try {
      return value.toDouble();
    } catch (_) {
      return double.tryParse(value.toString()) ?? 0;
    }
  }
}
