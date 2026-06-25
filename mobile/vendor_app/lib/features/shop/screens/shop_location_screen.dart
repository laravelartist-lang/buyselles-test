import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/features/shop/controllers/shop_controller.dart';
import 'package:sixvalley_vendor_app/features/shop/domain/models/location_item_model.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';
import 'package:sixvalley_vendor_app/utill/styles.dart';

class ShopLocationScreen extends StatefulWidget {
  const ShopLocationScreen({super.key});

  @override
  State<ShopLocationScreen> createState() => _ShopLocationScreenState();
}

class _ShopLocationScreenState extends State<ShopLocationScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final shopController = Provider.of<ShopController>(context, listen: false);
      shopController.initShopLocationFromModel();
      await shopController.fetchCountries();
      if (shopController.selectedCountryId != null) {
        await shopController.fetchCities(shopController.selectedCountryId!);
      }
      if (shopController.selectedCityId != null) {
        await shopController.fetchAreas(shopController.selectedCityId!);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('shop_location', context) ?? 'Shop Location',
        isBackButtonExist: true,
        isAction: false,
      ),
      body: Consumer<ShopController>(
        builder: (context, shopController, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  getTranslated('store_location', context) ?? 'Store Location',
                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Text(
                  getTranslated('shop_location_hint', context) ??
                      'Select the country, city, and area where your shop operates.',
                  style: robotoRegular.copyWith(
                    color: Theme.of(context).hintColor,
                    fontSize: Dimensions.fontSizeSmall,
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),
                _LocationDropdown(
                  label: getTranslated('country', context) ?? 'Country',
                  value: shopController.selectedCountryId,
                  items: shopController.countries,
                  isLoading: shopController.isLocationLoading && shopController.countries.isEmpty,
                  onChanged: shopController.selectCountry,
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                _LocationDropdown(
                  label: getTranslated('city', context) ?? 'City',
                  value: shopController.selectedCityId,
                  items: shopController.cities,
                  enabled: shopController.selectedCountryId != null,
                  isLoading: shopController.isLocationLoading &&
                      shopController.selectedCountryId != null &&
                      shopController.cities.isEmpty,
                  onChanged: shopController.selectCity,
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                _LocationDropdown(
                  label: getTranslated('area', context) ?? 'Area',
                  value: shopController.selectedAreaId,
                  items: shopController.areas,
                  enabled: shopController.selectedCityId != null,
                  isLoading: shopController.isLocationLoading &&
                      shopController.selectedCityId != null &&
                      shopController.areas.isEmpty,
                  onChanged: shopController.selectArea,
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),
                shopController.isLocationSaving
                    ? const Center(child: CircularProgressIndicator())
                    : CustomButtonWidget(
                        btnTxt: getTranslated('save', context) ?? 'Save',
                        onTap: () async {
                          final saved = await shopController.saveShopLocation();
                          if (saved && context.mounted) {
                            Navigator.pop(context);
                          }
                        },
                      ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _LocationDropdown extends StatelessWidget {
  final String label;
  final int? value;
  final List<LocationItemModel> items;
  final bool enabled;
  final bool isLoading;
  final ValueChanged<int?> onChanged;

  const _LocationDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
    this.enabled = true,
    this.isLoading = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
        const SizedBox(height: Dimensions.paddingSizeExtraSmall),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
          decoration: BoxDecoration(
            color: enabled ? Theme.of(context).cardColor : Theme.of(context).disabledColor.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
            border: Border.all(color: Theme.of(context).dividerColor),
          ),
          child: isLoading
              ? const Padding(
                  padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
                  child: Center(child: CircularProgressIndicator()),
                )
              : DropdownButtonHideUnderline(
                  child: DropdownButton<int?>(
                    isExpanded: true,
                    value: items.any((item) => item.id == value) ? value : null,
                    hint: Text(
                      '${getTranslated('select', context) ?? 'Select'} $label',
                      style: robotoRegular.copyWith(color: Theme.of(context).hintColor),
                    ),
                    items: items
                        .map(
                          (item) => DropdownMenuItem<int?>(
                            value: item.id,
                            child: Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                          ),
                        )
                        .toList(),
                    onChanged: enabled ? onChanged : null,
                  ),
                ),
        ),
      ],
    );
  }
}
