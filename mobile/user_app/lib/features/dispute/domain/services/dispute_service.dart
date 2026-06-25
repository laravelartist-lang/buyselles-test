import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/repositories/dispute_repository_interface.dart';
import 'package:flutter_sixvalley_ecommerce/features/dispute/domain/services/dispute_service_interface.dart';
import 'package:image_picker/image_picker.dart';

class DisputeService implements DisputeServiceInterface {
  final DisputeRepositoryInterface disputeRepositoryInterface;

  DisputeService({required this.disputeRepositoryInterface});

  @override
  Future getReasons() => disputeRepositoryInterface.getReasons();

  @override
  Future getDisputes() => disputeRepositoryInterface.getDisputes();

  @override
  Future getDispute(int id) => disputeRepositoryInterface.getDispute(id);

  @override
  Future createDispute({
    required int orderId,
    int? reasonId,
    required String description,
    List<XFile>? files,
  }) =>
      disputeRepositoryInterface.createDispute(
        orderId: orderId,
        reasonId: reasonId,
        description: description,
        files: files,
      );

  @override
  Future addMessage(int disputeId, String message) =>
      disputeRepositoryInterface.addMessage(disputeId, message);

  @override
  Future uploadEvidence(int disputeId, List<XFile> files) =>
      disputeRepositoryInterface.uploadEvidence(disputeId, files);

  @override
  Future escalate(int disputeId) =>
      disputeRepositoryInterface.escalate(disputeId);

  @override
  Future confirmClosure(int disputeId) =>
      disputeRepositoryInterface.confirmClosure(disputeId);

  @override
  Future confirmReceipt(int orderId) =>
      disputeRepositoryInterface.confirmReceipt(orderId);
}
