abstract class DigitalFilesServiceInterface {
  Future<dynamic> getProductDetails(int productId);
  Future<dynamic> uploadDigitalFile(int productId, String filePath);
  Future<dynamic> deleteDigitalFile(int productId);
}
