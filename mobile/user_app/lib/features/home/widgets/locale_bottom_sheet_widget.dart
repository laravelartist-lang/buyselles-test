import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/brand/controllers/brand_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/deal/controllers/featured_deal_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/deal/controllers/flash_deal_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/controllers/product_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/shop/controllers/shop_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/controllers/localization_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/localization/models/language_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:provider/provider.dart';

class LocaleBottomSheetWidget extends StatefulWidget {
  const LocaleBottomSheetWidget({super.key});

  @override
  State<LocaleBottomSheetWidget> createState() => _LocaleBottomSheetWidgetState();
}

class _LocaleBottomSheetWidgetState extends State<LocaleBottomSheetWidget> {
  int _selectedLanguageIndex = 0;
  int _selectedCurrencyIndex = 0;

  @override
  void initState() {
    super.initState();
    final localizationController = Provider.of<LocalizationController>(context, listen: false);
    final splashController = Provider.of<SplashController>(context, listen: false);

    final languageList = splashController.configModel?.language ?? [];
    if (languageList.isNotEmpty) {
      final currentLangCode = localizationController.locale.languageCode;
      for (int i = 0; i < languageList.length; i++) {
        final lang = languageList[i];
        final String code = lang.code ?? '';
        if (code == currentLangCode) {
          _selectedLanguageIndex = i;
          break;
        }
      }
    } else {
      _selectedLanguageIndex = localizationController.languageIndex ?? 0;
    }

    _selectedCurrencyIndex = splashController.currencyIndex ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final splashController = Provider.of<SplashController>(context);
    final localizationController = Provider.of<LocalizationController>(context);

    final languageList = splashController.configModel?.language ?? [];
    final currencyList = splashController.configModel?.currencyList ?? [];

    return SingleChildScrollView(
      child: Container(
        padding: const EdgeInsets.only(bottom: 40, top: 15),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: const BorderRadius.vertical(
            top: Radius.circular(Dimensions.paddingSizeDefault),
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 5,
              decoration: BoxDecoration(
                color: Theme.of(context).hintColor.withValues(alpha: .5),
                borderRadius: BorderRadius.circular(20),
              ),
            ),
            const SizedBox(height: 20),

            Text(
              getTranslated('language_&_currency', context) ?? 'Language & Currency',
              style: textBold.copyWith(
                fontSize: Dimensions.fontSizeLarge,
                color: Theme.of(context).textTheme.bodyLarge?.color,
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(
                top: Dimensions.paddingSizeSmall,
                bottom: Dimensions.paddingSizeDefault,
              ),
              child: Text(
                getTranslated('choose_your_language_and_currency', context) ??
                    'Choose your language and currency to proceed',
                textAlign: TextAlign.center,
                style: textRegular.copyWith(
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
              ),
            ),

            _buildSectionTitle(context, getTranslated('language', context) ?? 'Language'),
            if (languageList.isNotEmpty)
              _buildLanguageList(context, languageList, localizationController)
            else
              _buildLanguageList(context, AppConstants.languages, localizationController),

            const SizedBox(height: Dimensions.paddingSizeSmall),
            _buildSectionTitle(context, getTranslated('currency', context) ?? 'Currency'),
            if (currencyList.isNotEmpty)
              _buildCurrencyList(context, currencyList, splashController)
            else
              const Padding(
                padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Text('No currencies available'),
              ),

            Padding(
              padding: const EdgeInsets.fromLTRB(
                Dimensions.paddingSizeSmall,
                Dimensions.paddingSizeDefault,
                Dimensions.paddingSizeSmall,
                0,
              ),
              child: CustomButton(
                buttonText: getTranslated('save', context) ?? 'Save',
                onTap: () => _saveSelection(context),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(BuildContext context, String title) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
        vertical: Dimensions.paddingSizeExtraSmall,
      ),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(
          title,
          style: titilliumSemiBold.copyWith(
            fontSize: Dimensions.fontSizeDefault,
            color: Theme.of(context).primaryColor,
          ),
        ),
      ),
    );
  }

  Widget _buildLanguageList(
    BuildContext context,
    List<dynamic> languageList,
    LocalizationController localizationController,
  ) {
    final bool isAdminList = languageList.isNotEmpty &&
        !(languageList.first is LanguageModel);

    return ListView.builder(
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: languageList.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        final lang = languageList[index];
        final String langCode;
        final String langName;

        if (isAdminList) {
          if (lang is Map) {
            langCode = (lang['code'] ?? '').toString();
            langName = (lang['name'] ?? '').toString();
          } else {
            langCode = lang.code ?? '';
            langName = lang.name ?? '';
          }
        } else if (lang is LanguageModel) {
          langCode = lang.languageCode ?? '';
          langName = lang.languageName ?? '';
        } else {
          langCode = '';
          langName = '';
        }

        final currentLangCode = localizationController.locale.languageCode;
        final isSelected = langCode == currentLangCode;

        return _buildSelectableRow(
          context,
          leading: Text(
            langCode.toUpperCase(),
            style: textRegular.copyWith(
              color: isSelected ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color,
              fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
          title: langName,
          isSelected: isSelected,
          onTap: () {
            setState(() {
              _selectedLanguageIndex = index;
            });
          },
        );
      },
    );
  }

  Widget _buildCurrencyList(
    BuildContext context,
    List<dynamic> currencyList,
    SplashController splashController,
  ) {
    return ListView.builder(
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: currencyList.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        final currency = currencyList[index];
        final bool isActive = currency.status != false;
        if (!isActive) return const SizedBox();

        final String symbol = currency.symbol ?? '';
        final String name = currency.name ?? '';

        final isSelected = index == _selectedCurrencyIndex;

        return _buildSelectableRow(
          context,
          leading: Container(
            width: 40,
            height: 40,
            padding: const EdgeInsets.all(Dimensions.paddingSizeEight),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isSelected
                  ? Theme.of(context).primaryColor
                  : Theme.of(context).primaryColor.withValues(alpha: .5),
            ),
            child: Center(
              child: Text(
                symbol,
                style: textRegular.copyWith(color: Colors.white, fontSize: 10),
              ),
            ),
          ),
          title: name,
          isSelected: isSelected,
          onTap: () {
            setState(() {
              _selectedCurrencyIndex = index;
            });
          },
        );
      },
    );
  }

  Widget _buildSelectableRow(
    BuildContext context, {
    required Widget leading,
    required String title,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          Dimensions.paddingSizeDefault,
          0,
          Dimensions.paddingSizeDefault,
          Dimensions.paddingSizeExtraSmall,
        ),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
            color: isSelected
                ? Theme.of(context).primaryColor.withValues(alpha: .1)
                : Theme.of(context).cardColor,
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeSmall,
            ),
            child: Row(
              children: [
                leading,
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeSmall,
                  ),
                  child: Text(
                    title,
                    style: textRegular.copyWith(
                      color: Theme.of(context).textTheme.bodyLarge?.color,
                    ),
                  ),
                ),
                const Spacer(),
                if (isSelected)
                  Icon(
                    Icons.check_circle,
                    size: 20,
                    color: Theme.of(context).primaryColor,
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _saveSelection(BuildContext context) {
    final splashController = Provider.of<SplashController>(context, listen: false);
    final localizationController = Provider.of<LocalizationController>(context, listen: false);

    final adminLanguageList = splashController.configModel?.language ?? [];
    final currencyList = splashController.configModel?.currencyList ?? [];

    if (adminLanguageList.isNotEmpty) {
      final selectedLang = adminLanguageList[_selectedLanguageIndex];
      final String langCode;
      String countryCode;

      langCode = selectedLang.code ?? '';
      countryCode = langCode.toUpperCase();

      localizationController.setLanguage(Locale(langCode, countryCode));
    } else if (AppConstants.languages.isNotEmpty && _selectedLanguageIndex < AppConstants.languages.length) {
      final hardcoded = AppConstants.languages[_selectedLanguageIndex];
      localizationController.setLanguage(Locale(
        hardcoded.languageCode!,
        hardcoded.countryCode!,
      ));
    }

    _refreshDataAfterLanguageChange(context, splashController);

    if (currencyList.isNotEmpty && _selectedCurrencyIndex < currencyList.length) {
      splashController.setCurrency(_selectedCurrencyIndex);
    }

    Navigator.pop(context);
  }

  void _refreshDataAfterLanguageChange(BuildContext context, SplashController splashController) {
    final productController = Provider.of<ProductController>(context, listen: false);
    Provider.of<CategoryController>(context, listen: false).getCategoryList(true);
    productController.getHomeCategoryProductList(true);
    Provider.of<ShopController>(context, listen: false).getTopSellerList(offset: 1);
    Provider.of<BrandController>(context, listen: false).getBrandList(offset: 1);
    productController.getLatestProductList(1);
    productController.getFeaturedProductModel(1, isUpdate: true);
    Provider.of<FeaturedDealController>(context, listen: false).getFeaturedDealList();
    Provider.of<FlashDealController>(context, listen: false).getFlashDealList(true, true);
    productController.getRecommendedProduct();
    productController.getJustForYouProduct(1);

    if (splashController.configModel?.activeTheme == "theme_fashion") {
      productController.getMostSearchingProduct(1);
    }
  }
}
