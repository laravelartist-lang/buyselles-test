import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:flutter_thermal_printer/flutter_thermal_printer.dart';
import 'package:flutter_thermal_printer/utils/printer.dart';
import 'package:flutter_thermal_printer/printer_manager.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/domain/models/digital_code_model.dart';

// ─────────────────────────────────────────────────────────────────────────────
// SIMULATION MODE
// Set [_kSimulation] to [true] to test the entire print flow without a
// physical printer. When active:
//   • Scanning discovers a virtual "[SIM] Printer" automatically.
//   • Connect always succeeds instantly (no BLE call made).
//   • printReceipt() logs each receipt line to the debug console.
//   • All hardware-state checks return [true].
// To enable: change `kDebugMode && false` → `kDebugMode && true`
// MUST be `kDebugMode && false` before shipping to production.
// ─────────────────────────────────────────────────────────────────────────────
const bool _kSimulation = kDebugMode && false;

final Printer _simulatedPrinter = Printer(
  address: 'SIM:00:00:00:00:00:00',
  name: '[SIM] Virtual Printer',
  connectionType: ConnectionType.BLE,
);

class ThermalPrintController with ChangeNotifier {
  final _plugin = FlutterThermalPrinter.instance;

  List<Printer> _printers = [];
  List<Printer> get printers => _printers;

  bool _isScanning = false;
  bool get isScanning => _isScanning;

  bool _isConnecting = false;
  bool get isConnecting => _isConnecting;

  bool _isPrinting = false;
  bool get isPrinting => _isPrinting;

  /// The last successfully paired printer (address only — used for
  /// auto-reconnect). We keep the address separately because the in-memory
  /// [Printer] object does NOT reflect the real hardware state after the
  /// device is switched off.
  Printer? _pairedPrinter;
  Printer? get connectedPrinter => _pairedPrinter;

  int _selectedPaperWidth = 80;
  int get selectedPaperWidth => _selectedPaperWidth;

  StreamSubscription<List<Printer>>? _devicesSubscription;

  void setPaperWidth(int width) {
    _selectedPaperWidth = width;
    notifyListeners();
  }

  // ---------------------------------------------------------------------------
  // PERMISSIONS
  // ---------------------------------------------------------------------------

  Future<bool> requestPermissions() async {
    if (Platform.isAndroid) {
      final statuses = await [
        Permission.bluetoothScan,
        Permission.bluetoothConnect,
        Permission.location,
      ].request();
      return (statuses[Permission.bluetoothScan]?.isGranted ?? false) &&
          (statuses[Permission.bluetoothConnect]?.isGranted ?? false) &&
          (statuses[Permission.location]?.isGranted ?? false);
    } else if (Platform.isIOS) {
      return (await Permission.bluetooth.request()).isGranted;
    }
    return true;
  }

  // ---------------------------------------------------------------------------
  // SCANNING
  // ---------------------------------------------------------------------------

  Future<void> startScan() async {
    if (_kSimulation) {
      _isScanning = true;
      _printers = [];
      notifyListeners();
      // Fake discovery delay so the UI shows the spinner briefly.
      await Future<void>.delayed(const Duration(milliseconds: 700));
      _printers = [_simulatedPrinter];
      _isScanning = false;
      notifyListeners();
      debugPrint('[SIM] Scan complete — 1 virtual printer found.');
      return;
    }

    if (!await requestPermissions()) {
      _isScanning = false;
      _printers = [];
      notifyListeners();
      return;
    }

    _isScanning = true;
    _printers = [];
    notifyListeners();

    await _devicesSubscription?.cancel();

    _plugin.getPrinters(
      connectionTypes: [ConnectionType.BLE, ConnectionType.USB],
      androidUsesFineLocation: true,
    );

    _devicesSubscription = _plugin.devicesStream.listen((event) {
      _printers = event;
      notifyListeners();
    });

    // Auto-stop scan after 12 s
    Future.delayed(const Duration(seconds: 12), () {
      if (_isScanning) {
        _isScanning = false;
        notifyListeners();
      }
    });
  }

  Future<void> stopScan() async {
    if (_kSimulation) {
      _isScanning = false;
      notifyListeners();
      return;
    }
    await _devicesSubscription?.cancel();
    _devicesSubscription = null;
    await _plugin.stopScan();
    _isScanning = false;
    notifyListeners();
  }

  // ---------------------------------------------------------------------------
  // CONNECTION — active hardware handshake
  // ---------------------------------------------------------------------------

  /// Connects to [printer].
  ///
  /// Strategy:
  /// 1. If we already have a paired printer, explicitly disconnect it first so
  ///    the OS clears the stale BLE link before we attempt a fresh one.
  /// 2. Call [_plugin.connect()]. The library's `PrinterManager.connect()`
  ///    internally awaits the BLE `connectionStream` event — it will time-out
  ///    and return `false` if the hardware does not acknowledge.
  /// 3. After a successful OS-level connect, immediately query
  ///    [_plugin.isConnected()] — this calls `UniversalBle.getConnectionState`
  ///    which is a direct hardware state read. Only if BOTH steps agree do we
  ///    consider the connection valid.
  Future<bool> connect(Printer printer) async {
    if (_kSimulation) {
      _isConnecting = true;
      notifyListeners();
      await Future<void>.delayed(const Duration(milliseconds: 600));
      _pairedPrinter = printer;
      _isConnecting = false;
      notifyListeners();
      debugPrint('[SIM] Connected to ${printer.name}');
      return true;
    }

    _isConnecting = true;
    notifyListeners();

    try {
      // Step 1: tear down any stale connection so the OS can start fresh.
      if (_pairedPrinter != null) {
        try {
          await _plugin.disconnect(_pairedPrinter!);
        } catch (_) {
          // Ignore — hardware may already be off.
        }
        _pairedPrinter = null;
        notifyListeners();
      }

      // Small settling gap after disconnect before the next connect attempt.
      await Future.delayed(const Duration(milliseconds: 400));

      // Step 2: request the OS to establish the link. The library waits for
      // the real BLE connection-state event here.
      final osConnected = await _plugin.connect(printer);

      if (!osConnected) {
        _pairedPrinter = null;
        notifyListeners();
        return false;
      }

      // Step 3: double-check the actual hardware state to guard against
      // spurious true returns when the device is unreachable.
      final hardwareConnected = await _verifyHardwareConnected(printer);

      if (!hardwareConnected) {
        // The OS reported success but the hardware is not reachable — clean up.
        try {
          await _plugin.disconnect(printer);
        } catch (_) {}
        _pairedPrinter = null;
        notifyListeners();
        return false;
      }

      _pairedPrinter = printer;
      notifyListeners();
      return true;
    } catch (e) {
      debugPrint('ThermalPrintController.connect failed: $e');
      _pairedPrinter = null;
      notifyListeners();
      return false;
    } finally {
      _isConnecting = false;
      notifyListeners();
    }
  }

  Future<void> disconnect() async {
    if (_kSimulation) {
      _pairedPrinter = null;
      notifyListeners();
      return;
    }
    if (_pairedPrinter != null) {
      try {
        await _plugin.disconnect(_pairedPrinter!);
      } catch (_) {}
      _pairedPrinter = null;
      notifyListeners();
    }
  }

  // ---------------------------------------------------------------------------
  // HARDWARE STATE VERIFICATION
  // ---------------------------------------------------------------------------

  /// Asks the BLE stack whether [printer] is *actually* connected right now.
  /// Retries up to [maxRetries] times with a short gap to let the OS settle.
  Future<bool> _verifyHardwareConnected(
    Printer printer, {
    int maxRetries = 3,
    Duration retryDelay = const Duration(milliseconds: 300),
  }) async {
    if (_kSimulation) return true;

    for (int i = 0; i < maxRetries; i++) {
      try {
        final connected = await PrinterManager.instance
            .isConnected(printer)
            .timeout(const Duration(seconds: 4));
        if (connected) return true;
      } catch (_) {
        // Timeout or BLE stack error — treat as not connected.
      }
      if (i < maxRetries - 1) {
        await Future.delayed(retryDelay);
      }
    }
    return false;
  }

  /// Verifies whether the last paired printer is still physically reachable.
  ///
  /// Call this when re-opening the printer dialog so a stale in-memory
  /// "connected" state from a previous session is cleared before the user
  /// sees the UI. If the hardware link is gone, [_pairedPrinter] is set to
  /// null and listeners are notified, causing the "Print" button to revert
  /// to "Connect".
  Future<void> refreshConnectionState() async {
    if (_kSimulation) return; // Simulated connection is always considered live.
    if (_pairedPrinter == null) return;

    final bool alive = await _verifyHardwareConnected(
      _pairedPrinter!,
      maxRetries: 1,
      retryDelay: Duration.zero,
    );

    if (!alive) {
      try {
        await _plugin.disconnect(_pairedPrinter!);
      } catch (_) {}
      _pairedPrinter = null;
      notifyListeners();
    }
  }

  /// Checks the current hardware state and, if the link has dropped,
  /// attempts a fresh reconnect. Returns `true` only when hardware is live.
  Future<bool> _ensureConnected() async {
    if (_pairedPrinter == null) return false;

    final stillConnected = await _verifyHardwareConnected(_pairedPrinter!);
    if (stillConnected) return true;

    // Hardware link dropped — attempt a single reconnect.
    debugPrint('ThermalPrintController: link dropped, attempting reconnect…');
    final reconnected = await connect(_pairedPrinter!);
    return reconnected;
  }

  // ---------------------------------------------------------------------------
  // PRINTING
  // ---------------------------------------------------------------------------

  /// Prints [codes] to the paired printer.
  ///
  /// Guarantees:
  /// • Always verifies the hardware link before sending ESC/POS data.
  /// • If the link is down, attempts ONE automatic reconnect.
  /// • Throws a [PrintException] if the link cannot be restored, so the caller
  ///   (the dialog) can show a real error instead of a false success toast.
  Future<void> printReceipt(List<DigitalCodeModel> codes) async {
    if (_pairedPrinter == null) {
      throw PrintException('No printer selected. Please connect a printer first.');
    }

    _isPrinting = true;
    notifyListeners();

    try {
      // Verify (and optionally restore) the hardware connection before we send.
      final ready = await _ensureConnected();
      if (!ready) {
        throw PrintException(
          'Printer is not reachable. Make sure it is turned on and in range.',
        );
      }

      final profile = await CapabilityProfile.load();
      final generator = Generator(
        _selectedPaperWidth == 80 ? PaperSize.mm80 : PaperSize.mm58,
        profile,
      );

      for (int i = 0; i < codes.length; i++) {
        final code = codes[i];
        final List<int> bytes = _buildReceiptBytes(generator, code);

        if (_kSimulation) {
          // ── Simulation: log receipt to console instead of BLE ────────────
          await Future<void>.delayed(const Duration(milliseconds: 400));
          debugPrint('');
          debugPrint('╔══════════════════ [SIM] PRINT JOB ${i + 1}/${codes.length} ══════════════════╗');
          debugPrint('║  Printer  : ${_pairedPrinter?.name ?? 'n/a'}');
          debugPrint('║  Paper    : ${_selectedPaperWidth}mm');
          debugPrint('║  Bytes    : ${bytes.length} ESC/POS bytes');
          debugPrint('╠═══════════════════════════════════════════════════════╣');
          debugPrint('║  Product  : ${code.productName}');
          debugPrint('║  Code     : ${code.code}');
          if (code.pin != null && code.pin!.isNotEmpty) {
            debugPrint('║  PIN      : ${code.pin}');
          }
          if (code.serial != null && code.serial!.isNotEmpty) {
            debugPrint('║  Serial   : ${code.serial}');
          }
          if (code.expiry != null && code.expiry!.isNotEmpty) {
            debugPrint('║  Expiry   : ${code.expiry}');
          }
          debugPrint('╚═══════════════════════════════════════════════════════╝');
          debugPrint('[SIM] Receipt ${i + 1} delivered successfully ✓');
          // ─────────────────────────────────────────────────────────────────
        } else {
          // Send the data. The library's printData swallows BLE exceptions
          // internally, so we use a separate post-write verification below.
          await _plugin
              .printData(_pairedPrinter!, bytes, longData: true)
              .timeout(
                const Duration(seconds: 10),
                onTimeout: () => throw PrintException(
                  'Print timed out. The printer may have disconnected.',
                ),
              );

          // ── Post-write delivery verification ────────────────────────────
          // Give the OS BLE buffer time to flush to the hardware. If the
          // link drops during the transfer, the connection state will read
          // false here and we surface a real error instead of a ghost print.
          await Future.delayed(const Duration(milliseconds: 800));
          final bool delivered = await _verifyHardwareConnected(
            _pairedPrinter!,
            maxRetries: 1,
            retryDelay: Duration.zero,
          );
          if (!delivered) {
            try {
              await _plugin.disconnect(_pairedPrinter!);
            } catch (_) {}
            _pairedPrinter = null;
            notifyListeners();
            throw PrintException(
              'Printer disconnected mid-transfer. Please reconnect and try again.',
            );
          }
          // ─────────────────────────────────────────────────────────────────
        }

        // Brief inter-job pause so the printer can cut/reset between codes.
        await Future.delayed(const Duration(milliseconds: 900));
      }
    } on PrintException {
      rethrow;
    } catch (e) {
      debugPrint('ThermalPrintController.printReceipt error: $e');
      throw PrintException('Printing failed: ${e.toString()}');
    } finally {
      _isPrinting = false;
      notifyListeners();
    }
  }

  List<int> _buildReceiptBytes(Generator gen, DigitalCodeModel code) {
    var bytes = <int>[];

    bytes += gen.text(
      'Buyselles',
      styles: const PosStyles(
        align: PosAlign.center,
        bold: true,
        height: PosTextSize.size2,
        width: PosTextSize.size2,
      ),
    );
    bytes += gen.text(
      'E-Commerce Marketplace',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += gen.hr();
    bytes += gen.text(
      'Digital Product Receipt',
      styles: const PosStyles(align: PosAlign.center, bold: true),
    );
    bytes += gen.text(
      'Date: ${DateTime.now().toString().split('.')[0]}',
      styles: const PosStyles(align: PosAlign.center),
    );
    bytes += gen.hr();

    // Product details
    bytes += gen.text(code.productName, styles: const PosStyles(bold: true));
    bytes += gen.text('Code: ${code.code}');
    if (code.pin != null && code.pin!.isNotEmpty) {
      bytes += gen.text('PIN: ${code.pin}');
    }
    if (code.expiry != null && code.expiry!.isNotEmpty) {
      bytes += gen.text('Expiry: ${code.expiry}');
    }
    bytes += gen.feed(1);

    // Footer
    bytes += gen.hr();
    bytes +=
        gen.text('Thank you for shopping!', styles: const PosStyles(align: PosAlign.center));
    bytes += gen.feed(2);
    bytes += gen.cut();

    return bytes;
  }

  // ---------------------------------------------------------------------------
  // DISPOSE
  // ---------------------------------------------------------------------------

  @override
  void dispose() {
    _devicesSubscription?.cancel();
    super.dispose();
  }
}

/// Typed exception for print-layer errors so the UI can distinguish print
/// failures from unexpected runtime exceptions.
class PrintException implements Exception {
  final String message;
  const PrintException(this.message);

  @override
  String toString() => message;
}
