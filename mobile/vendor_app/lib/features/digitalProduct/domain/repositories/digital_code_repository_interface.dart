import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/interface/repository_interface.dart';

abstract class DigitalCodeRepositoryInterface implements RepositoryInterface {
  Future<ApiResponse> downloadBulkTemplate();
  Future<ApiResponse> uploadBulkImport(String filePath);
  Future<ApiResponse> getProductCodes(int productId, {int? limit, int? offset});
  Future<ApiResponse> downloadProductTemplate(int productId);
  Future<ApiResponse> uploadProductImport(int productId, String filePath);
  Future<ApiResponse> addSingleCode(int productId, String code, {String? serialNumber, String? expiryDate});
  Future<ApiResponse> toggleCodeStatus(int codeId);
  Future<ApiResponse> decryptCode(int codeId);
  Future<ApiResponse> deleteCode(int codeId);
}
