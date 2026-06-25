class DigitalFileModel {
  List<DigitalFile>? files;

  DigitalFileModel({this.files});

  DigitalFileModel.fromJson(dynamic json) {
    if (json is List) {
      files = json.map((e) => DigitalFile.fromJson(e)).toList();
    } else if (json is Map && json['digital_file_ready'] != null) {
      final raw = json['digital_file_ready'];
      if (raw is List) {
        files = raw.map((e) => DigitalFile.fromJson(e)).toList();
      }
    }
  }
}

class DigitalFile {
  int? id;
  String? fileName;
  String? fileExtension;
  String? fileSize;
  String? downloadUrl;
  String? createdAt;

  DigitalFile({
    this.id,
    this.fileName,
    this.fileExtension,
    this.fileSize,
    this.downloadUrl,
    this.createdAt,
  });

  DigitalFile.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    fileName = json['file_name'] ?? json['name'];
    fileExtension = json['file_extension'] ?? json['extension'];
    fileSize = json['file_size']?.toString();
    downloadUrl = json['download_url'] ?? json['url'];
    createdAt = json['created_at'];
  }
}
