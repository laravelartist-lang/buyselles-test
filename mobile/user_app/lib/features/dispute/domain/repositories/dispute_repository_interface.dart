import 'package:flutter_sixvalley_ecommerce/interface/repo_interface.dart';
import 'package:image_picker/image_picker.dart';

abstract class DisputeRepositoryInterface<T> extends RepositoryInterface {
  Future<dynamic> getReasons();

  Future<dynamic> getDisputes();

  Future<dynamic> getDispute(int id);

  Future<dynamic> createDispute({
    required int orderId,
    int? reasonId,
    required String description,
    List<XFile>? files,
  });

  Future<dynamic> addMessage(int disputeId, String message);

  Future<dynamic> uploadEvidence(int disputeId, List<XFile> files);

  Future<dynamic> escalate(int disputeId);

  Future<dynamic> confirmClosure(int disputeId);

  Future<dynamic> confirmReceipt(int orderId);
}
