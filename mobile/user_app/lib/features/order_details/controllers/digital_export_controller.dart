import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/order_details/domain/models/digital_code_model.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:open_file_manager/open_file_manager.dart';
import 'package:share_plus/share_plus.dart';

// Excel
import 'package:excel/excel.dart';

// Word
import 'package:docx_dart/docx_dart.dart' as docx;

// PDF & Printing
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class DigitalExportController with ChangeNotifier {
  bool _isExporting = false;
  bool get isExporting => _isExporting;

  void _setExporting(bool value) {
    _isExporting = value;
    notifyListeners();
  }

  Future<void> _checkPermission() async {
    if (Platform.isAndroid) {
      var status = await Permission.storage.status;
      if (!status.isGranted) {
        await Permission.storage.request();
      }
    }
  }

  Future<String> _getDownloadDirectory() async {
    if (Platform.isIOS) {
      final dir = await getApplicationDocumentsDirectory();
      return dir.path;
    } else {
      final dir = Directory('/storage/emulated/0/Download');
      if (await dir.exists()) {
        return dir.path;
      } else {
        return (await getExternalStorageDirectory())!.path;
      }
    }
  }

  String _formatCodesForText(List<DigitalCodeModel> codes) {
    StringBuffer buffer = StringBuffer();
    buffer.writeln("Your Digital Products:\n");
    for (var code in codes) {
      buffer.writeln("Product: ${code.productName}");
      buffer.writeln("Code: ${code.code}");
      if (code.pin != null && code.pin!.isNotEmpty) {
        buffer.writeln("PIN: ${code.pin}");
      }
      if (code.expiry != null && code.expiry!.isNotEmpty) {
        buffer.writeln("Expiry: ${code.expiry}");
      }
      buffer.writeln("--------------------");
    }
    return buffer.toString();
  }

  /// Share via Social Media
  Future<void> shareCodes(BuildContext context, List<DigitalCodeModel> codes) async {
    final text = _formatCodesForText(codes);
    await Share.share(text);
  }

  /// Print directly from the device
  Future<void> printCodes(BuildContext context, List<DigitalCodeModel> codes) async {
    _setExporting(true);
    try {
      final pdf = pw.Document();

      pdf.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat.a4,
          build: (pw.Context context) {
            return [
              pw.Header(
                level: 0,
                child: pw.Text("Digital Product Codes", style: pw.TextStyle(fontSize: 24, fontWeight: pw.FontWeight.bold)),
              ),
              pw.SizedBox(height: 20),
              ...codes.map((c) {
                return pw.Container(
                  margin: const pw.EdgeInsets.only(bottom: 20),
                  padding: const pw.EdgeInsets.all(10),
                  decoration: pw.BoxDecoration(
                    border: pw.Border.all(color: PdfColors.grey400),
                    borderRadius: const pw.BorderRadius.all(pw.Radius.circular(5)),
                  ),
                  child: pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.start,
                    children: [
                      pw.Text(c.productName, style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold)),
                      pw.SizedBox(height: 10),
                      pw.Text("Code: ${c.code}", style: const pw.TextStyle(fontSize: 14)),
                      if (c.pin != null && c.pin!.isNotEmpty) pw.Text("PIN: ${c.pin}", style: const pw.TextStyle(fontSize: 14)),
                      if (c.expiry != null && c.expiry!.isNotEmpty) pw.Text("Expiry: ${c.expiry}", style: const pw.TextStyle(fontSize: 14)),
                    ],
                  ),
                );
              }),
            ];
          },
        ),
      );

      await Printing.layoutPdf(
        onLayout: (PdfPageFormat format) async => pdf.save(),
        name: 'Digital_Products_Receipt',
      );
    } catch (e) {
      showCustomSnackBarWidget("Failed to print: $e", context);
    } finally {
      _setExporting(false);
    }
  }

  /// Export as Excel
  Future<void> exportToExcel(BuildContext context, List<DigitalCodeModel> codes) async {
    _setExporting(true);
    try {
      await _checkPermission();

      var excel = Excel.createExcel();
      Sheet sheetObject = excel['Digital Products'];
      excel.setDefaultSheet('Digital Products');

      // Add Headers
      sheetObject.appendRow([
        TextCellValue('Product Name'),
        TextCellValue('Code'),
        TextCellValue('PIN'),
        TextCellValue('Expiry'),
      ]);

      // Add Data
      for (var c in codes) {
        sheetObject.appendRow([
          TextCellValue(c.productName),
          TextCellValue(c.code),
          TextCellValue(c.pin ?? ''),
          TextCellValue(c.expiry ?? ''),
        ]);
      }

      var fileBytes = excel.encode();
      if (fileBytes != null) {
        final dirPath = await _getDownloadDirectory();
        final timestamp = DateTime.now().millisecondsSinceEpoch;
        final file = File('$dirPath/digital_products_$timestamp.xlsx');
        
        await file.writeAsBytes(fileBytes);
        
        showCustomSnackBarWidget(getTranslated('file_downloaded_successfully', context) ?? 'File downloaded successfully', context, snackBarType: SnackBarType.success);
        
        await openFileManager();
      }
    } catch (e) {
      showCustomSnackBarWidget("Failed to export Excel: $e", context);
    } finally {
      _setExporting(false);
    }
  }

  /// Export as Word
  Future<void> exportToWord(BuildContext context, List<DigitalCodeModel> codes) async {
    _setExporting(true);
    try {
      await _checkPermission();

      final document = docx.loadDocxDocument();
      document.addHeading(text: 'Digital Product Codes', level: 1);

      for (var c in codes) {
        document.addHeading(text: c.productName, level: 2);
        
        final table = document.addTable(3, 2, style: 'Table Grid');
        table.cell(0, 0).text = 'Code';
        table.cell(0, 1).text = c.code;
        
        table.cell(1, 0).text = 'PIN';
        table.cell(1, 1).text = c.pin ?? 'N/A';
        
        table.cell(2, 0).text = 'Expiry';
        table.cell(2, 1).text = c.expiry ?? 'N/A';
        
        document.addParagraph(text: ''); // Spacing
      }

      final dirPath = await _getDownloadDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final file = File('$dirPath/digital_products_$timestamp.docx');

      document.save(file.path);

      showCustomSnackBarWidget(getTranslated('file_downloaded_successfully', context) ?? 'File downloaded successfully', context, snackBarType: SnackBarType.success);
      
      await openFileManager();
    } catch (e) {
      showCustomSnackBarWidget("Failed to export Word: $e", context);
    } finally {
      _setExporting(false);
    }
  }
}
