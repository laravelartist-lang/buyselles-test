import 'dart:developer';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_downloader/flutter_downloader.dart';
import 'package:flutter_sixvalley_ecommerce/data/model/api_response.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/controllers/seller_product_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/models/product_details_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/domain/services/product_details_service_interface.dart';
import 'package:flutter_sixvalley_ecommerce/features/product_details/enums/preview_type.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/helper/api_checker.dart';
import 'package:flutter_sixvalley_ecommerce/helper/direct_topup_helper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/main.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:open_file_manager/open_file_manager.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';

class ProductDetailsController extends ChangeNotifier {
  final ProductDetailsServiceInterface productDetailsServiceInterface;
  ProductDetailsController({required this.productDetailsServiceInterface});


  int? _imageSliderIndex = 0;
  int? _quantity = 0;
  int? _variantIndex;
  List<int>? _variationIndex;
  int? _orderCount;
  int? _wishCount;
  String? _sharableLink;
  int? _digitalVariationIndex = 0;
  int? _digitalVariationSubindex = 0;
  bool _isDownloadLoading = false;

  bool _isDetails = false;
  bool get isDetails =>_isDetails;
  int? get imageSliderIndex => _imageSliderIndex;
  int? get quantity => _quantity;
  int? get variantIndex => _variantIndex;
  List<int>? get variationIndex => _variationIndex;
  int? get orderCount => _orderCount;
  int? get wishCount => _wishCount;
  String? get sharableLink => _sharableLink;
  ProductDetailsModel? _productDetailsModel;
  ProductDetailsModel? get productDetailsModel => _productDetailsModel;
  int? get digitalVariationIndex => _digitalVariationIndex;
  int? get digitalVariationSubindex => _digitalVariationSubindex;
  bool get isDownloadLoading => _isDownloadLoading;

  String _directTopUpAccountId = '';
  double _directTopUpQuantity = 0;
  bool _directTopUpAccountVerified = false;
  bool _directTopUpVerifyLoading = false;

  String get directTopUpAccountId => _directTopUpAccountId;
  double get directTopUpQuantity => _directTopUpQuantity;
  bool get directTopUpAccountVerified => _directTopUpAccountVerified;
  bool get directTopUpVerifyLoading => _directTopUpVerifyLoading;

  double get directTopUpTotalPrice {
    final config = DirectTopUpHelper.resolveDirectTopUpConfig(_productDetailsModel);
    if (config?.lineTotal != null) {
      return config!.lineTotal!;
    }

    final pricePerUnit = config?.pricePerUnit ?? 0;
    return _directTopUpQuantity * pricePerUnit;
  }

  void setDirectTopUpAccountId(String value) {
    _directTopUpAccountId = value.trim();
    _directTopUpAccountVerified = false;
    notifyListeners();
  }

  void setDirectTopUpQuantity(double value) {
    final config = DirectTopUpHelper.resolveDirectTopUpConfig(_productDetailsModel);
    final min = config?.minQuantity ?? value;
    final max = config?.maxQuantity ?? value;
    if (value < min) {
      value = min;
    }
    if (value > max) {
      value = max;
    }
    _directTopUpQuantity = value.floorToDouble();
    notifyListeners();
  }

  void setDirectTopUpQuantityFromPrice(double price) {
    final config = DirectTopUpHelper.resolveDirectTopUpConfig(_productDetailsModel);
    final pricePerUnit = config?.pricePerUnit ?? 0;
    if (pricePerUnit <= 0) {
      setDirectTopUpQuantity(config?.minQuantity ?? 0);
      return;
    }
    setDirectTopUpQuantity((price / pricePerUnit).floorToDouble());
  }

  void initializeDirectTopUpDefaults() {
    final config = DirectTopUpHelper.resolveDirectTopUpConfig(_productDetailsModel);
    if (config == null) {
      return;
    }

    _directTopUpQuantity = config.minQuantity ?? 1;
    _directTopUpAccountId = '';
    _directTopUpAccountVerified = false;
    notifyListeners();
  }

  int? _selectedSupplierDenominationId;
  double? _supplierCustomAmount;

  int? get selectedSupplierDenominationId => _selectedSupplierDenominationId;
  double? get supplierCustomAmount => _supplierCustomAmount;

  void initializeSupplierDenominationDefaults(ProductDetailsModel? product) {
    final model = product ?? _productDetailsModel;
    if (model == null) {
      return;
    }

    _selectedSupplierDenominationId = model.defaultSupplierDenominationId;
    _supplierCustomAmount = null;

    if (model.requiresDenominationSelection) {
      final fixed = (model.denominations ?? []).where((d) => d.type == 'fixed').toList();
      if (_selectedSupplierDenominationId == null && fixed.isNotEmpty) {
        _selectedSupplierDenominationId = fixed.first.id;
      } else if (model.variableDenomination != null && fixed.isEmpty) {
        _selectedSupplierDenominationId = model.variableDenomination!.id;
      }
    }

    notifyListeners();
  }

  void selectSupplierDenomination(int id) {
    _selectedSupplierDenominationId = id;
    _supplierCustomAmount = null;
    notifyListeners();
  }

  void setSupplierCustomAmount(String value) {
    _supplierCustomAmount = double.tryParse(value.trim());
    if (modelVariableDenomination != null) {
      _selectedSupplierDenominationId = modelVariableDenomination!.id;
    }
    notifyListeners();
  }

  VariableDenominationOption? get modelVariableDenomination =>
      _productDetailsModel?.variableDenomination;

  int? resolveSupplierDenominationIdForCart(ProductDetailsModel? product) {
    final model = product ?? _productDetailsModel;
    if (model == null) {
      return null;
    }

    if (_selectedSupplierDenominationId != null) {
      return _selectedSupplierDenominationId;
    }

    return model.defaultSupplierDenominationId;
  }

  double? resolveCustomAmountForCart(ProductDetailsModel? product) {
    final model = product ?? _productDetailsModel;
    if (model == null) {
      return null;
    }

    final selectedId = resolveSupplierDenominationIdForCart(model);
    if (selectedId == null) {
      return null;
    }

    final variable = model.variableDenomination;
    if (variable != null && variable.id == selectedId) {
      return _supplierCustomAmount;
    }

    for (final denom in model.denominations ?? <SupplierDenominationOption>[]) {
      if (denom.id == selectedId && denom.type == 'fixed') {
        return denom.faceValue;
      }
    }

    return _supplierCustomAmount;
  }

  String? validateSupplierDenomination(BuildContext context, ProductDetailsModel? product) {
    final model = product ?? _productDetailsModel;
    if (model == null || !model.requiresDenominationSelection) {
      return null;
    }

    final selectedId = _selectedSupplierDenominationId;
    if (selectedId == null) {
      return getTranslated('supplier_denomination_required', context)
          ?? 'Please select a denomination';
    }

    final variable = model.variableDenomination;
    if (variable != null && variable.id == selectedId) {
      final amount = _supplierCustomAmount;
      if (amount == null || amount <= 0) {
        return getTranslated('please_input_amount', context) ?? 'Please input amount';
      }
      if (amount < variable.minFaceValue || amount > variable.maxFaceValue) {
        return '${getTranslated('amount', context) ?? 'Amount'} ${variable.minFaceValue} - ${variable.maxFaceValue}';
      }
    }

    return null;
  }

  bool _isDirectTopUpActive() {
    return DirectTopUpHelper.shouldPromptDirectTopUpSheet(_productDetailsModel);
  }

  String? validateDirectTopUpClientSide(BuildContext context) {
    if (!_isDirectTopUpActive()) {
      return null;
    }

    final config = DirectTopUpHelper.resolveDirectTopUpConfig(_productDetailsModel);
    if (_directTopUpQuantity <= 0) {
      _directTopUpQuantity = config?.minQuantity ?? 1;
    }

    if (_directTopUpAccountId.isEmpty) {
      return getTranslated('direct_topup_account_id_required', context)
          ?? 'Account ID is required';
    }

    if (_directTopUpAccountId.length > 255) {
      return getTranslated('direct_topup_account_id_too_long', context)
          ?? 'Account ID is too long';
    }

    if (!RegExp(r'^[a-zA-Z0-9_\-\.@]+$').hasMatch(_directTopUpAccountId)) {
      return getTranslated('direct_topup_account_id_invalid_format', context)
          ?? 'Account ID format is invalid';
    }

    return null;
  }

  void setDirectTopUpVerifyLoading(bool value) {
    _directTopUpVerifyLoading = value;
    notifyListeners();
  }

  void markDirectTopUpAccountVerified(bool value) {
    _directTopUpAccountVerified = value;
    notifyListeners();
  }



  Future<void> getProductDetails(BuildContext context, String productId, String slug) async {
    _isDetails = true;
    log("=====slug===>$slug/ $productId");
    ApiResponseModel apiResponse = await productDetailsServiceInterface.get(slug);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      _isDetails = false;
      _productDetailsModel = ProductDetailsModel.fromJson(apiResponse.response!.data);
      if(_productDetailsModel != null){
        _quantity = _productDetailsModel!.minimumOrderQty ?? 1;
        initializeDirectTopUpDefaults();
        initializeSupplierDenominationDefaults(_productDetailsModel);
        log("=====slug===>$slug/ $productId");
        // Provider.of<SellerProductController>(Get.context!, listen: false).
        // getSellerProductList(_productDetailsModel?.addedBy == 'admin' ? '0' : productDetailsModel!.userId.toString(), 1, productId, reload: true);

        Provider.of<SellerProductController>(Get.context!, listen: false).
        getSellerMoreProductList(_productDetailsModel?.addedBy == 'admin' ?
          Provider.of<SplashController>(Get.context!, listen: false).configModel?.inHouseShop?.slug ?? ''
          : productDetailsModel!.seller!.shop!.slug.toString(), 1, productId);

      }
    } else {
      _isDetails = false;
      showCustomSnackBarWidget(apiResponse.error.toString(), Get.context!, snackBarType: SnackBarType.error);
    }
    _isDetails = false;
    notifyListeners();
  }




  void initData(ProductDetailsModel product, int? minimumOrderQuantity, BuildContext context) {
    _variantIndex = 0;
    _quantity = minimumOrderQuantity;
    _variationIndex = [];
    final choiceLength = product.choiceOptions?.length ?? 0;
    for (int i=0; i<= choiceLength; i++) {
      _variationIndex!.add(0);
    }
    initializeSupplierDenominationDefaults(product);
  }

  bool isReviewSelected = false;
  void selectReviewSection(bool review, {bool isUpdate = true}){
    isReviewSelected = review;

    if(isUpdate) {
      notifyListeners();

    }
  }



  void getCount(String productID, BuildContext context) async {
    ApiResponseModel apiResponse = await productDetailsServiceInterface.getCount(productID);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      _orderCount = apiResponse.response!.data['order_count'];
      _wishCount = apiResponse.response!.data['wishlist_count'];
    } else {
      ApiChecker.checkApi( apiResponse);
    }
    notifyListeners();
  }


  void getSharableLink(String productID, BuildContext context) async {
    ApiResponseModel apiResponse = await productDetailsServiceInterface.getSharableLink(productID);
    if (apiResponse.response != null && apiResponse.response!.statusCode == 200) {
      _sharableLink = apiResponse.response!.data;
    } else {
      ApiChecker.checkApi(apiResponse);
    }
  }



  void setImageSliderSelectedIndex(int selectedIndex, {bool isUpdate = true}) {
    _imageSliderIndex = selectedIndex;
    if(isUpdate) {
      notifyListeners();
    }
  }


  void setQuantity(int value) {
    _quantity = value;
    notifyListeners();
  }

  void setCartVariantIndex(int? minimumOrderQuantity,int index, BuildContext context) {
    _variantIndex = index;
    _quantity = minimumOrderQuantity;
    notifyListeners();
  }

  void setCartVariationIndex(int? minimumOrderQuantity, int index, int i, BuildContext context) {
    _variationIndex![index] = i;
    _quantity = minimumOrderQuantity;
    notifyListeners();
  }


  void removePrevLink() {
    _sharableLink = null;
  }

  bool isValidYouTubeUrl(String url) {
    RegExp regex = RegExp(
      r'^https?:\/\/(?:www\.)?(youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})',
    );

    return regex.hasMatch(url);
  }

  void setDigitalVariationIndex(int? minimumOrderQuantity, int index, int subIndex, BuildContext context) {
    _quantity = minimumOrderQuantity;
    _digitalVariationIndex = index;
    _digitalVariationSubindex = subIndex;
    notifyListeners();
  }

  void initDigitalVariationIndex() {
    _digitalVariationIndex = 0;
    _digitalVariationSubindex = 0;
  }


  PreviewType getFileType(String url) {
    if(url.contains('.pdf')) {
      return PreviewType.pdf;
    } else if(url.contains('.jpg') || url.contains('.jpeg') || url.contains('.png')) {
      return  PreviewType.image;
    } else if(url.contains('.mp4') || url.contains('.mkv') || url.contains('.avi') || url.contains('.flv') || url.contains('.mov') || url.contains('.wmv') || url.contains('.webm')) {
      return PreviewType.video;
    } else if ( url.contains('.mp3') || url.contains('.wav') || url.contains('.aac') || url.contains('.wma') || url.contains('.amr')) {
      return PreviewType.audio;
    }else {
      return PreviewType.others;
    }
  }



  void previewDownload({required String url, required String fileName, bool isIos = false}) async {
    _isDownloadLoading = true;
    notifyListeners();

    var status = await Permission.storage.status;
    if (!status.isGranted) {
      await Permission.storage.request();
    }

    var selectedFolderType = AndroidFolderType.download;
    final subFolderPathCtrl = TextEditingController();


    List<String> fileTypes = [ '.txt', '.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.mp3', '.wav', '.ogg', '.m4a', '.aac',
      '.mp4', '.avi', '.mkv', '.webm', '.3gp', '.pdf', '.doc'];

    if(isIos) {
      HttpClientResponse apiResponse = await productDetailsServiceInterface.previewDownload(url);
      if (apiResponse.statusCode == 200) {

        List<int> downloadData = [];
        Directory downloadDirectory;

        if (Platform.isIOS) {
          downloadDirectory = await getApplicationDocumentsDirectory();
        } else {
          downloadDirectory = Directory('/storage/emulated/0/Download');
          if (!await downloadDirectory.exists()) downloadDirectory = (await getExternalStorageDirectory())!;
        }

        String filePathName = "${downloadDirectory.path}/$fileName";
        File savedFile = File(filePathName);
        bool fileExists = await savedFile.exists();

        if (fileExists) {
          ScaffoldMessenger.of(Get.context!).showSnackBar(const SnackBar(content: Text("File already downloaded")));
          _isDownloadLoading = false;
        } else {
          apiResponse.listen((d) => downloadData.addAll(d), onDone: () {
            savedFile.writeAsBytes(downloadData);
          });
          showCustomSnackBarWidget(getTranslated('product_downloaded_successfully', Get.context!), Get.context!, snackBarType: SnackBarType.success);

          _isDownloadLoading = false;
          Navigator.of(Get.context!).pop();
        }
      } else {
        _isDownloadLoading = false;

        showCustomSnackBarWidget(getTranslated('product_download_failed', Get.context!), Get.context!, snackBarType: SnackBarType.error);
        Navigator.of(Get.context!).pop();
      }
    } else {
      String? task;
      Directory downloadDirectory = Directory('/storage/emulated/0/Download');
      String filePathName = "${downloadDirectory.path}/$fileName";
      File savedFile = File(filePathName);
      bool fileExists = await savedFile.exists();

      if(fileExists) {
        showCustomSnackBarWidget(getTranslated('file_already_downloaded', Get.context!), Get.context!, snackBarType: SnackBarType.warning);
      } else{
        task  = await FlutterDownloader.enqueue(
          url: url,
          savedDir: downloadDirectory.path,
          fileName: fileName,
          showNotification: true,
          saveInPublicStorage: true,
          openFileFromNotification: true,
        );

        if(task != null) {
          if(!fileTypes.contains(getFileExtension(fileName))) {
            showCustomSnackBarWidget(getTranslated('product_downloaded_successfully', Get.context!), Get.context!, snackBarType: SnackBarType.error);
            await openFileManager(
              androidConfig: AndroidConfig(
                folderType: selectedFolderType,
              ),
              iosConfig: IosConfig(
                folderPath: subFolderPathCtrl.text.trim(),
              ),
            );
          }else {
            Navigator.of(Get.context!).pop();
          }
        } else {
          showCustomSnackBarWidget(getTranslated('product_download_failed', Get.context!), Get.context!, snackBarType: SnackBarType.error);
          Navigator.of(Get.context!).pop();
        }
      }
      _isDownloadLoading = false;
    }
    notifyListeners();
  }


  String getFileExtension(String fileName) {
    if (fileName.contains('.')) {
      return '.${fileName.split('.').last}';
    }
    return '';
  }


  void updateProductRestock({String? variantKey}) {
    if(_productDetailsModel != null){
      _productDetailsModel?.isRestockRequested = 1;
      if(variantKey != null && variantKey.isNotEmpty) {
        _productDetailsModel?.restockRequestedList?.add(variantKey);
      }
    }
    notifyListeners();
  }


}
