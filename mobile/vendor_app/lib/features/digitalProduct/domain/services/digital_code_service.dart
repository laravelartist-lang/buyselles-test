import 'dart:typed_data';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/repositories/digital_code_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/services/digital_code_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';

class DigitalCodeService implements DigitalCodeServiceInterface {
  final DigitalCodeRepositoryInterface digitalCodeRepositoryInterface;

  DigitalCodeService({required this.digitalCodeRepositoryInterface});

  @override
  Future<Uint8List?> downloadBulkTemplate() async {
    final apiResponse = await digitalCodeRepositoryInterface.downloadBulkTemplate();
    if (apiResponse.response?.statusCode == 200) {
      return Uint8List.fromList(apiResponse.response?.data ?? []);
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> uploadBulkImport(String filePath) async {
    final apiResponse = await digitalCodeRepositoryInterface.uploadBulkImport(filePath);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> getProductCodes(int productId, {int? limit, int? offset}) async {
    final apiResponse = await digitalCodeRepositoryInterface.getProductCodes(productId, limit: limit, offset: offset);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<Uint8List?> downloadProductTemplate(int productId) async {
    final apiResponse = await digitalCodeRepositoryInterface.downloadProductTemplate(productId);
    if (apiResponse.response?.statusCode == 200) {
      return Uint8List.fromList(apiResponse.response?.data ?? []);
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> uploadProductImport(int productId, String filePath) async {
    final apiResponse = await digitalCodeRepositoryInterface.uploadProductImport(productId, filePath);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> addSingleCode(int productId, String code, {String? serialNumber, String? expiryDate}) async {
    final apiResponse = await digitalCodeRepositoryInterface.addSingleCode(
      productId, code,
      serialNumber: serialNumber,
      expiryDate: expiryDate,
    );
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> toggleCodeStatus(int codeId) async {
    final apiResponse = await digitalCodeRepositoryInterface.toggleCodeStatus(codeId);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> decryptCode(int codeId) async {
    final apiResponse = await digitalCodeRepositoryInterface.decryptCode(codeId);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }

  @override
  Future<dynamic> deleteCode(int codeId) async {
    final apiResponse = await digitalCodeRepositoryInterface.deleteCode(codeId);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    }
    ApiChecker.checkApi(apiResponse);
    return null;
  }
}
