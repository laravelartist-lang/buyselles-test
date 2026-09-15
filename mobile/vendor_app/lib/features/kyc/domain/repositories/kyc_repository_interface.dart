import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/interface/repository_interface.dart';

abstract class KycRepositoryInterface implements RepositoryInterface {
  Future<ApiResponse> getStatus();

  Future<ApiResponse> getLaunchUrl();
}
