import 'dart:io';
import 'package:sixvalley_vendor_app/data/model/response/base/api_response.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/repositories/dispute_repository_interface.dart';
import 'package:sixvalley_vendor_app/features/dispute/domain/services/dispute_service_interface.dart';

class DisputeService implements DisputeServiceInterface {
  final DisputeRepositoryInterface disputeRepositoryInterface;

  DisputeService({required this.disputeRepositoryInterface});

  @override
  Future<ApiResponse> getDisputes({String? status, int page = 1}) =>
      disputeRepositoryInterface.getDisputes(status: status, page: page);

  @override
  Future<ApiResponse> getDisputeDetail(int id) =>
      disputeRepositoryInterface.getDisputeDetail(id);

  @override
  Future<ApiResponse> sendMessage(int id, String message) =>
      disputeRepositoryInterface.sendMessage(id, message);

  @override
  Future<ApiResponse> uploadEvidence(int id, List<File> files) =>
      disputeRepositoryInterface.uploadEvidence(id, files);

  @override
  Future<ApiResponse> escalate(int id) =>
      disputeRepositoryInterface.escalate(id);
}
