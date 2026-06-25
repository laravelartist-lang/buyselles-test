import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/features/product/controllers/category_controller.dart';
import 'package:sixvalley_vendor_app/features/splash/controllers/splash_controller.dart';
import 'package:sixvalley_vendor_app/features/splash/domain/models/config_model.dart';
import 'package:sixvalley_vendor_app/localization/controllers/localization_controller.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/app_constants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class LocaleBottomSheetWidget extends StatefulWidget {
  const LocaleBottomSheetWidget({super.key});

  @override
  State<LocaleBottomSheetWidget> createState() => _LocaleBottomSheetWidgetState();
}

class _LocaleBottomSheetWidgetState extends State<LocaleBottomSheetWidget> {
  int _selectedLanguageIndex = 0;
  int _selectedCurrencyIndex = 0;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadConfigAndInitSelection();
  }

  Future<void> _loadConfigAndInitSelection() async {
    await Provider.of<SplashController>(context, listen: false).initConfig();
    if (!mounted) {
      return;
    }

    final localizationController = Provider.of<LocalizationController>(context, listen: false);
    final splashController = Provider.of<SplashController>(context, listen: false);
    final languageList = splashController.configModel?.languageList ?? [];

    if (languageList.isNotEmpty) {
      final currentLangCode = localizationController.locale.languageCode;
      for (int i = 0; i < languageList.length; i++) {
        if (languageList[i].code == currentLangCode) {
          _selectedLanguageIndex = i;
          break;
        }
      }
    } else {
      _selectedLanguageIndex = localizationController.languageIndex ?? 0;
    }

    _selectedCurrencyIndex = splashController.currencyIndex ?? 0;

    setState(() {
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Container(
        padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(Dimensions.paddingSizeDefault)),
        ),
        child: SizedBox(
          height: 120,
          child: Center(
            child: CircularProgressIndicator(color: Theme.of(context).primaryColor),
          ),
        ),
      );
    }

    return Consumer2<SplashController, LocalizationController>(
      builder: (context, splashController, localizationController, _) {
        final languageList = splashController.configModel?.languageList ?? [];
        final currencyList = splashController.configModel?.currencyList ?? [];
        final bool showCurrency = splashController.configModel?.currencyModel != 'single_currency';

        return SingleChildScrollView(
          child: Container(
            padding: const EdgeInsets.only(bottom: 40, top: 15),
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(Dimensions.paddingSizeDefault)),
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
                  style: titilliumSemiBold.copyWith(
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
                    style: titilliumRegular.copyWith(color: Theme.of(context).textTheme.bodyLarge?.color),
                  ),
                ),
                _buildSectionTitle(context, getTranslated('language', context) ?? 'Language'),
                if (languageList.isNotEmpty)
                  _buildAdminLanguageList(context, languageList, localizationController)
                else
                  _buildFallbackLanguageList(context, localizationController),
                if (showCurrency) ...[
                  const SizedBox(height: Dimensions.paddingSizeSmall),
                  _buildSectionTitle(context, getTranslated('currency', context) ?? 'Currency'),
                  if (currencyList.isNotEmpty)
                    _buildCurrencyList(context, currencyList)
                  else
                    Padding(
                      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                      child: Text(
                        getTranslated('no_data_found', context) ?? 'No currencies available',
                        style: titilliumRegular.copyWith(color: Theme.of(context).hintColor),
                      ),
                    ),
                ],
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Dimensions.paddingSizeSmall,
                    Dimensions.paddingSizeDefault,
                    Dimensions.paddingSizeSmall,
                    0,
                  ),
                  child: CustomButtonWidget(
                    btnTxt: getTranslated('save', context),
                    onTap: () => _saveSelection(context, showCurrency),
                  ),
                ),
              ],
            ),
          ),
        );
      },
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

  Widget _buildAdminLanguageList(
    BuildContext context,
    List<Language> languageList,
    LocalizationController localizationController,
  ) {
    return ListView.builder(
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: languageList.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        final lang = languageList[index];
        final langCode = lang.code ?? '';
        final langName = lang.name ?? '';
        final isSelected = index == _selectedLanguageIndex;

        return _buildSelectableRow(
          context,
          leading: Text(
            langCode.toUpperCase(),
            style: titilliumRegular.copyWith(
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

  Widget _buildFallbackLanguageList(
    BuildContext context,
    LocalizationController localizationController,
  ) {
    return ListView.builder(
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: AppConstants.languages.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        final lang = AppConstants.languages[index];
        final isSelected = index == _selectedLanguageIndex;

        return _buildSelectableRow(
          context,
          leading: SizedBox(
            width: 25,
            child: Image.asset(lang.imageUrl!),
          ),
          title: lang.languageName ?? '',
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

  Widget _buildCurrencyList(BuildContext context, List<CurrencyList> currencyList) {
    return ListView.builder(
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: currencyList.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        if (currencyList[index].status == false) {
          return const SizedBox();
        }

        final isSelected = index == _selectedCurrencyIndex;

        return _buildSelectableRow(
          context,
          leading: Container(
            width: 40,
            height: 40,
            padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isSelected
                  ? Theme.of(context).primaryColor
                  : Theme.of(context).primaryColor.withValues(alpha: .5),
            ),
            child: Center(
              child: Text(
                currencyList[index].symbol ?? '',
                style: titilliumRegular.copyWith(color: Colors.white, fontSize: 10),
              ),
            ),
          ),
          title: currencyList[index].name ?? '',
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
                  padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                  child: Text(
                    title,
                    style: titilliumRegular.copyWith(
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

  void _saveSelection(BuildContext context, bool showCurrency) {
    final splashController = Provider.of<SplashController>(context, listen: false);
    final localizationController = Provider.of<LocalizationController>(context, listen: false);
    final adminLanguageList = splashController.configModel?.languageList ?? [];
    final currencyList = splashController.configModel?.currencyList ?? [];

    String languageCode = localizationController.locale.languageCode;

    if (adminLanguageList.isNotEmpty && _selectedLanguageIndex < adminLanguageList.length) {
      final selectedLang = adminLanguageList[_selectedLanguageIndex];
      final langCode = selectedLang.code ?? 'en';
      final countryCode = langCode == 'en' ? 'US' : langCode.toUpperCase();
      final fallbackIndex = _resolveFallbackLanguageIndex(langCode);
      localizationController.setLanguage(Locale(langCode, countryCode), fallbackIndex);
      languageCode = langCode;
    } else if (_selectedLanguageIndex < AppConstants.languages.length) {
      final hardcoded = AppConstants.languages[_selectedLanguageIndex];
      localizationController.setLanguage(
        Locale(hardcoded.languageCode!, hardcoded.countryCode),
        _selectedLanguageIndex,
      );
      languageCode = hardcoded.languageCode!;
    }

    Provider.of<CategoryController>(context, listen: false).getCategoryList(
      context,
      null,
      languageCode == 'en' ? 'en' : languageCode.toLowerCase(),
    );

    if (showCurrency && currencyList.isNotEmpty && _selectedCurrencyIndex < currencyList.length) {
      splashController.setCurrency(_selectedCurrencyIndex);
    }

    Navigator.pop(context);
  }

  int _resolveFallbackLanguageIndex(String langCode) {
    for (int i = 0; i < AppConstants.languages.length; i++) {
      if (AppConstants.languages[i].languageCode == langCode) {
        return i;
      }
    }

    return 0;
  }
}
