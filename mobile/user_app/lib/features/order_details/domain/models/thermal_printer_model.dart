import 'package:flutter_thermal_printer/utils/printer.dart';

class ThermalPrinterModel {
  final String name;
  final String address;
  final ConnectionType type;
  final dynamic vendorId; // For USB
  final dynamic productId; // For USB

  ThermalPrinterModel({
    required this.name,
    required this.address,
    required this.type,
    this.vendorId,
    this.productId,
  });
}
