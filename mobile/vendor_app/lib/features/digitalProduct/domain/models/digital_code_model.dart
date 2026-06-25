class DigitalCodeModel {
  int? totalSize;
  int? limit;
  int? offset;
  List<DigitalCodeItem>? codes;
  DigitalCodeStats? stats;
  int? expiringCount;
  DigitalCodeProductInfo? product;

  DigitalCodeModel.fromJson(Map<String, dynamic> json) {
    totalSize = int.tryParse('${json['total_size']}');
    limit = int.tryParse('${json['limit']}');
    offset = int.tryParse('${json['offset']}');
    if (json['codes'] != null) {
      codes = (json['codes'] as List)
          .map((e) => DigitalCodeItem.fromJson(e))
          .toList();
    }
    stats = json['stats'] != null
        ? DigitalCodeStats.fromJson(json['stats'])
        : null;
    expiringCount = json['expiring_count'];
    product = json['product'] != null
        ? DigitalCodeProductInfo.fromJson(json['product'])
        : null;
  }
}

class DigitalCodeItem {
  int? id;
  String? serialNumber;
  String? expiryDate;
  String? status;
  bool? isActive;
  String? source;
  String? createdAt;

  DigitalCodeItem.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    serialNumber = json['serial_number'];
    expiryDate = json['expiry_date'];
    status = json['status'];
    isActive = json['is_active'];
    source = json['source'];
    createdAt = json['created_at'];
  }
}

class DigitalCodeStats {
  int available;
  int inactive;
  int reserved;
  int sold;
  int expired;
  int total;

  DigitalCodeStats({
    this.available = 0,
    this.inactive = 0,
    this.reserved = 0,
    this.sold = 0,
    this.expired = 0,
    this.total = 0,
  });

  factory DigitalCodeStats.fromJson(Map<String, dynamic> json) {
    return DigitalCodeStats(
      available: int.tryParse('${json['available']}') ?? 0,
      inactive: int.tryParse('${json['inactive']}') ?? 0,
      reserved: int.tryParse('${json['reserved']}') ?? 0,
      sold: int.tryParse('${json['sold']}') ?? 0,
      expired: int.tryParse('${json['expired']}') ?? 0,
      total: int.tryParse('${json['total']}') ?? 0,
    );
  }
}

class DigitalCodeProductInfo {
  int? id;
  String? name;

  DigitalCodeProductInfo.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
  }
}

class DigitalCodeImportSummary {
  int processed;
  int duplicates;
  int skipped;

  DigitalCodeImportSummary({
    this.processed = 0,
    this.duplicates = 0,
    this.skipped = 0,
  });

  factory DigitalCodeImportSummary.fromJson(Map<String, dynamic> json) {
    return DigitalCodeImportSummary(
      processed: int.tryParse('${json['processed']}') ?? 0,
      duplicates: int.tryParse('${json['duplicates']}') ?? 0,
      skipped: int.tryParse('${json['skipped']}') ?? 0,
    );
  }
}
