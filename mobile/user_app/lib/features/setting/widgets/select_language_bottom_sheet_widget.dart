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
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:provider/provider.dart';

class SelectLanguageBottomSheetWidget extends StatefulWidget {
  const SelectLanguageBottomSheetWidget({super.key});

  @override
  State<SelectLanguageBottomSheetWidget> createState() => _SelectLanguageBottomSheetWidgetState();
}

class _SelectLanguageBottomSheetWidgetState extends State<SelectLanguageBottomSheetWidget> {
  int selectedIndex = 0;

  @override
  void initState() {
    selectedIndex = Provider.of<LocalizationController>(context, listen: false).languageIndex!;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    final SplashController splashController = Provider.of<SplashController>(context, listen: false);
    final ProductController productController = Provider.of<ProductController>(context, listen: false);

    final adminLanguages = splashController.configModel?.language ?? [];
    final useAdminLanguages = adminLanguages.isNotEmpty;

    return Consumer<LocalizationController>(
      builder: (context, localizationProvider, _) {
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
                const SizedBox(height: 40),

                Text(
                  getTranslated('select_language', context)!,
                  style: textBold.copyWith(
                    fontSize: Dimensions.fontSizeLarge,
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(
                    top: Dimensions.paddingSizeSmall,
                    bottom: Dimensions.paddingSizeLarge,
                  ),
                  child: Text(
                    '${getTranslated('choose_your_language_to_proceed', context)}',
                    style: textRegular.copyWith(
                      color: Theme.of(context).textTheme.bodyLarge?.color,
                    ),
                  ),
                ),

                if (useAdminLanguages)
                  _buildAdminLanguageList(context, adminLanguages, localizationProvider)
                else
                  _buildHardcodedLanguageList(context, localizationProvider, splashController, productController),

                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Dimensions.paddingSizeSmall,
                    Dimensions.paddingSizeSmall,
                    Dimensions.paddingSizeSmall,
                    0,
                  ),
                  child: CustomButton(
                    buttonText: '${getTranslated('select', context)}',
                    onTap: () {
                      if (useAdminLanguages) {
                        _saveAdminLanguage(context, adminLanguages);
                      } else {
                        _saveHardcodedLanguage(context, splashController, productController);
                      }
                    },
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildAdminLanguageList(
    BuildContext context,
    List<dynamic> adminLanguages,
    LocalizationController localizationProvider,
  ) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: adminLanguages.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        final lang = adminLanguages[index];
        final String langCode = (lang.code ?? '').toString();
        final String langName = (lang.name ?? '').toString();
        final bool isSelected = langCode == localizationProvider.locale.languageCode;

        return _buildRow(context, index, langCode.toUpperCase(), langName, isSelected);
      },
    );
  }

  Widget _buildHardcodedLanguageList(
    BuildContext context,
    LocalizationController localizationProvider,
    SplashController splashController,
    ProductController productController,
  ) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: AppConstants.languages.length,
      shrinkWrap: true,
      itemBuilder: (context, index) {
        return InkWell(
          onTap: () {
            setState(() {
              selectedIndex = index;
            });
          },
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              Dimensions.paddingSizeDefault,
              0,
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeSmall,
            ),
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                color: selectedIndex == index
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
                    SizedBox(
                      width: 25,
                      child: Image.asset(AppConstants.languages[index].imageUrl!),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Dimensions.paddingSizeSmall,
                      ),
                      child: Text(
                        AppConstants.languages[index].languageName!,
                        style: textRegular.copyWith(
                          color: Theme.of(context).textTheme.bodyLarge?.color,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildRow(
    BuildContext context,
    int index,
    String codeLabel,
    String name,
    bool isSelected,
  ) {
    return InkWell(
      onTap: () {
        setState(() {
          selectedIndex = index;
        });
      },
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          Dimensions.paddingSizeDefault,
          0,
          Dimensions.paddingSizeDefault,
          Dimensions.paddingSizeSmall,
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
                Container(
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
                      codeLabel,
                      style: textRegular.copyWith(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeSmall,
                  ),
                  child: Text(
                    name,
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

  void _saveAdminLanguage(BuildContext context, List<dynamic> adminLanguages) {
    final lang = adminLanguages[selectedIndex];
    final String langCode = (lang.code ?? '').toString();
    _applyLanguage(context, langCode, langCode.toUpperCase());
  }

  void _saveHardcodedLanguage(
    BuildContext context,
    SplashController splashController,
    ProductController productController,
  ) {
    final selected = AppConstants.languages[selectedIndex];
    _applyLanguage(context, selected.languageCode!, selected.countryCode!);
  }

  void _applyLanguage(BuildContext context, String langCode, String countryCode) {
    final localizationController = Provider.of<LocalizationController>(context, listen: false);
    localizationController.setLanguage(Locale(langCode, countryCode));

    final productController = Provider.of<ProductController>(context, listen: false);
    final splashController = Provider.of<SplashController>(context, listen: false);

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

    Navigator.pop(context);
  }
}
