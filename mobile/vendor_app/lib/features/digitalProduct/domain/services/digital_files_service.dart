import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/repositories/digital_files_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/digitalProduct/domain/services/digital_files_service_interface.dart';
import 'package:sixvalley_vendor_app/helper/api_checker.dart';

class DigitalFilesService implements DigitalFilesServiceInterface {
  final DigitalFilesRepositoryInterface digitalFilesRepositoryInterface;

  DigitalFilesService({required this.digitalFilesRepositoryInterface});

  @override
  Future<dynamic> getProductDetails(int productId) async {
    final ApiResponse apiResponse = await digitalFilesRepositoryInterface.getProductDetails(productId);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    } else {
      ApiChecker.checkApi(apiResponse);
      return null;
    }
  }

  @override
  Future<dynamic> uploadDigitalFile(int productId, String filePath) async {
    final ApiResponse apiResponse = await digitalFilesRepositoryInterface.uploadDigitalFile(productId, filePath);
    if (apiResponse.response?.statusCode == 200) {
      return apiResponse;
    } else {
      ApiChecker.checkApi(apiResponse);
      return null;
    }
  }

  @override
  Future<dynamic> deleteDigitalFile(int productId) async {
    return digitalFilesRepositoryInterface.deleteDigitalFile(productId);
  }
}
