import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/interface/repository_interface.dart';

abstract class DigitalFilesRepositoryInterface implements RepositoryInterface {
  Future<ApiResponse> getProductDetails(int productId);
  Future<ApiResponse> uploadDigitalFile(int productId, String filePath);
  Future<ApiResponse> deleteDigitalFile(int productId);
}
