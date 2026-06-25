import 'dart:typed_data';

abstract class DigitalCodeServiceInterface {
  Future<Uint8List?> downloadBulkTemplate();
  Future<dynamic> uploadBulkImport(String filePath);
  Future<dynamic> getProductCodes(int productId, {int? limit, int? offset});
  Future<Uint8List?> downloadProductTemplate(int productId);
  Future<dynamic> uploadProductImport(int productId, String filePath);
  Future<dynamic> addSingleCode(int productId, String code, {String? serialNumber, String? expiryDate});
  Future<dynamic> toggleCodeStatus(int codeId);
  Future<dynamic> decryptCode(int codeId);
  Future<dynamic> deleteCode(int codeId);
}
