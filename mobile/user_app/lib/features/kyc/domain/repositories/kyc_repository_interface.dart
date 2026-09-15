import 'package:flutter_sixvalley_ecommerce/interface/repo_interface.dart';

abstract class KycRepositoryInterface implements RepositoryInterface {
  Future<dynamic> getStatus();

  Future<dynamic> getLaunchUrl();

  Future<dynamic> getToken();
}
