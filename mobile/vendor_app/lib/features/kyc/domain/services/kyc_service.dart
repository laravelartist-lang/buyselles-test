import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/kyc/domain/repositories/kyc_repository_interface.dart';

abstract class KycServiceInterface {
  Future<ApiResponse> getStatus();

  Future<ApiResponse> getLaunchUrl();
}

class KycService implements KycServiceInterface {
  final KycRepositoryInterface kycRepositoryInterface;

  KycService({required this.kycRepositoryInterface});

  @override
  Future<ApiResponse> getStatus() => kycRepositoryInterface.getStatus();

  @override
  Future<ApiResponse> getLaunchUrl() => kycRepositoryInterface.getLaunchUrl();
}
