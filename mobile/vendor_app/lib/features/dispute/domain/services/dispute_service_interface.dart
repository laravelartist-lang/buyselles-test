import 'dart:io';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';

abstract class DisputeServiceInterface {
  Future<ApiResponse> getDisputes({String? status, int page = 1});
  Future<ApiResponse> getDisputeDetail(int id);
  Future<ApiResponse> sendMessage(int id, String message);
  Future<ApiResponse> uploadEvidence(int id, List<File> files);
  Future<ApiResponse> escalate(int id);
}
