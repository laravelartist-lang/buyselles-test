import 'package:flutter_sixvalley_ecommerce/features/kyc/domain/repositories/kyc_repository_interface.dart';

abstract class KycServiceInterface {
  Future<dynamic> getStatus();

  Future<dynamic> getLaunchUrl();

  Future<dynamic> getToken();
}

class KycService implements KycServiceInterface {
  final KycRepositoryInterface kycRepositoryInterface;

  KycService({required this.kycRepositoryInterface});

  @override
  Future getStatus() => kycRepositoryInterface.getStatus();

  @override
  Future getLaunchUrl() => kycRepositoryInterface.getLaunchUrl();

  @override
  Future getToken() => kycRepositoryInterface.getToken();
}
