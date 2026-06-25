import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_loader_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/brand/domain/models/brand_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/controllers/seller_product_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/search_product/domain/models/author_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/features/brand/controllers/brand_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/search_product/controllers/search_product_controller.dart';
import 'package:flutter_sixvalley_ecommerce/theme/controllers/theme_controller.dart';
import 'package:flutter_sixvalley_ecommerce/utill/app_constants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_sixvalley_ecommerce/utill/images.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:provider/provider.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/features/search_product/domain/models/location_model.dart';

class ProductFilterDialog extends StatefulWidget {
  final String? slug;
  final bool fromShop;
  final bool fromHome;
  const ProductFilterDialog({super.key, this.slug,  this.fromShop = true, this.fromHome = false});

  @override
  ProductFilterDialogState createState() => ProductFilterDialogState();
}

class ProductFilterDialogState extends State<ProductFilterDialog> {
  List<int> authors = [];
  List<int> publishingHouses = [];


  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final searchController =
          Provider.of<SearchProductController>(context, listen: false);
      // Always reload the full country list (clears any previous search filter).
      searchController.getCountries();
      // Reload city list so stale search-filtered cities don't persist.
      if (searchController.selectedCountryId != null) {
        searchController.getCities(searchController.selectedCountryId!);
      }
      // Reload area list for the same reason.
      if (searchController.selectedCityId != null) {
        searchController.getAreas(searchController.selectedCityId!);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final Size size = MediaQuery.sizeOf(context);

    return Dismissible(
      key: const Key('key'),
      direction: DismissDirection.down,
      onDismissed: (_) => Navigator.pop(context),
      child: Consumer<SearchProductController>(builder: (context, searchProvider, child) {
        late List<AuthorModel>? authorList = widget.fromShop ? Provider.of<SearchProductController>(context, listen: false).sellerAuthorsList :
        Provider.of<SearchProductController>(context, listen: false).authorsList;

        late List<AuthorModel>? publishingHouse = widget.fromShop ? Provider.of<SearchProductController>(context, listen: false).sellerPublishingHouseList :
        Provider.of<SearchProductController>(context, listen: false).publishingHouseList;

        authors.clear();
        if(authorList != null && authorList.isNotEmpty) {
          for (int i =0; i< authorList.length; i++) {
            authors.add(i);
          }
        }

        publishingHouses.clear();
        if(publishingHouse != null && publishingHouse.isNotEmpty) {
          for (int i=0; i < publishingHouse.length; i++) {
            publishingHouses.add(i);
          }
        }

        return Consumer<CategoryController>(builder: (context, categoryProvider,_) {
          return Consumer<BrandController>(builder: (context, brandProvider,_) {
            return Consumer<SellerProductController>(builder: (context, productController,_) {
              List<int> brandsIndices = [];
              if(brandProvider.brandList.isNotEmpty) {
                for(int i = 0; i < brandProvider.brandList.length; i++) {
                  brandsIndices.add(i);
                }
              }

              return Container(
                constraints: BoxConstraints(maxHeight: size.height * 0.9),
                decoration: BoxDecoration(color: Theme.of(context).highlightColor,
                    borderRadius: const BorderRadius.only(topLeft: Radius.circular(20), topRight: Radius.circular(20))),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [

                    Column( mainAxisSize: MainAxisSize.min, children: [
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      Center(child: Container(width: 35,height: 4,decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(Dimensions.paddingSizeDefault),
                          color: Theme.of(context).hintColor.withValues(alpha:.5)))),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [

                        // Opacity(
                        //   opacity: 0,
                        //   child: Row(children: [
                        //     SizedBox(width: 20, child: Image.asset(Images.reset)),
                        //     Text('${getTranslated('reset', context)}', style: textRegular.copyWith(color: Theme.of(context).primaryColor)),
                        //     const SizedBox(width: Dimensions.paddingSizeDefault)
                        //   ]),
                        // ),

                        const SizedBox(width: 64,),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            Text(getTranslated('filter', context) ?? '', style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)),
                          ],
                        ),


                        (categoryProvider.selectedCategoryIds.isNotEmpty || brandProvider.selectedBrandIds.isNotEmpty
                         || (widget.fromShop ? searchProvider.sellerPublishingHouseIds.isNotEmpty : searchProvider.publishingHouseIds.isNotEmpty) ||
                         (widget.fromShop ? searchProvider.selectedSellerAuthorIds.isNotEmpty : searchProvider.selectedAuthorIds.isNotEmpty) ||
                         searchProvider.selectedCountryId != null || searchProvider.minPriceForFilter != AppConstants.minFilter || searchProvider.maxPriceForFilter != AppConstants.maxFilter
                        ) ? InkWell(
                          onTap: () async {
                            showDialog(context: context, builder: (ctx)  => const CustomLoaderWidget());
                            await categoryProvider.resetChecked(widget.fromShop ? widget.slug! : null, widget.fromShop);
                            searchProvider.setFilterApply(isFiltered: false);
                            categoryProvider.selectedCategoryIds.clear();
                            brandProvider.selectedBrandIds.clear();
                            searchProvider.selectedSellerAuthorIds.clear();
                            searchProvider.sellerPublishingHouseIds.clear();
                            searchProvider.publishingHouseIds.clear();
                             searchProvider.selectedAuthorIds.clear();
                            searchProvider.setSelectedCountry(null);
                            searchProvider.setMinMaxPriceForFilter(const RangeValues(AppConstants.minFilter, AppConstants.maxFilter));
                            searchProvider.resetChecked(widget.slug, widget.fromShop);
                            if(context.mounted) {
                              Provider.of<SearchProductController>(context, listen: false).setProductTypeIndex(0, false);
                              Navigator.of(context).pop();
                            }
                          },
                          child: Row(children: [
                            SizedBox(width: 20, child: Image.asset(Images.reset)),
                            Text('${getTranslated('reset', context)}', style: textRegular.copyWith(color: Theme.of(context).primaryColor)),
                            const SizedBox(width: Dimensions.paddingSizeDefault,)
                          ]),
                        ) : SizedBox(width: size.width * 0.19),

                      ]),

                    ]),

                    Flexible(
                      child: SingleChildScrollView(
                        padding: EdgeInsets.only(
                          left: Dimensions.paddingSizeDefault,
                          right: Dimensions.paddingSizeDefault,
                          bottom: MediaQuery.viewInsetsOf(context).bottom,
                        ),
                        child: Column( crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [

                              const SizedBox(height: Dimensions.paddingSizeSmall),
                              Text(getTranslated('price_range', context) ?? '',
                                style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)
                              ),
                              const SizedBox(height: Dimensions.paddingSizeSmall),
                              RangeSlider(
                                values: RangeValues(
                                  searchProvider.minPriceForFilter.clamp(AppConstants.minFilter, AppConstants.maxFilter),
                                  searchProvider.maxPriceForFilter.clamp(
                                    searchProvider.minPriceForFilter.clamp(AppConstants.minFilter, AppConstants.maxFilter),
                                    AppConstants.maxFilter,
                                  ),
                                ),
                                max: AppConstants.maxFilter,
                                min: AppConstants.minFilter,
                                divisions: 100,
                                labels: RangeLabels(
                                  searchProvider.minPriceForFilter.toStringAsFixed(0),
                                  searchProvider.maxPriceForFilter.toStringAsFixed(0),
                                ),
                                onChanged: (RangeValues values) {
                                  searchProvider.setMinMaxPriceForFilter(values);
                                },
                              ),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(searchProvider.minPriceForFilter.toStringAsFixed(0), style: textRegular),
                                  Text(searchProvider.maxPriceForFilter.toStringAsFixed(0), style: textRegular),
                                ],
                              ),
                              const SizedBox(height: Dimensions.paddingSizeDefault),

                              // Location
                              Text(getTranslated('location', context) ?? '',
                                style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)
                              ),
                              Divider(color: Theme.of(context).hintColor.withValues(alpha:.25), thickness: .5),

                              // Country
                              const SizedBox(height: Dimensions.paddingSizeSmall),
                              Text(getTranslated('country', context)!, style: textRegular),
                              const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                              Autocomplete<CountryModel>(
                                optionsBuilder: (TextEditingValue textEditingValue) {
                                  if (searchProvider.countries == null) return const Iterable<CountryModel>.empty();
                                  if (textEditingValue.text.isEmpty) {
                                    return searchProvider.countries!.take(10);
                                  } else {
                                    return searchProvider.countries!.where((country) =>
                                        (country.name ?? '').toLowerCase().contains(textEditingValue.text.toLowerCase()));
                                  }
                                },
                                displayStringForOption: (CountryModel option) => option.name ?? '',
                                onSelected: (CountryModel country) {
                                  searchProvider.setSelectedCountry(country.id);
                                },
                                fieldViewBuilder: (context, textEditingController, focusNode, onFieldSubmitted) {
                                  if (searchProvider.selectedCountryId != null && textEditingController.text.isEmpty) {
                                    final selected = searchProvider.countries?.where((c) => c.id == searchProvider.selectedCountryId).firstOrNull;
                                    if (selected?.name != null) {
                                      textEditingController.text = selected!.name!;
                                    }
                                  } else if (searchProvider.selectedCountryId == null) {
                                    textEditingController.clear();
                                  }

                                  return Container(
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).cardColor,
                                      border: Border.all(width: .7, color: Theme.of(context).hintColor.withValues(alpha: .3)),
                                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                                    ),
                                    child: TextField(
                                      controller: textEditingController,
                                      focusNode: focusNode,
                                      onChanged: (val) {
                                        if (val.isNotEmpty) {
                                          searchProvider.getCountries(search: val);
                                        }
                                      },
                                      decoration: InputDecoration(
                                        hintText: getTranslated('select_country', context),
                                        contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                                        border: InputBorder.none,
                                        suffixIcon: searchProvider.selectedCountryId != null ? IconButton(
                                          icon: const Icon(Icons.clear, size: 18),
                                          onPressed: () {
                                            textEditingController.clear();
                                            searchProvider.setSelectedCountry(null);
                                          },
                                        ) : null,
                                      ),
                                    ),
                                  );
                                },
                              ),

                              // City
                              if (searchProvider.selectedCountryId != null)...[
                                const SizedBox(height: Dimensions.paddingSizeSmall),
                                Text(getTranslated('city', context)!, style: textRegular),
                                const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                                // Key recreates the widget when:
                                //  (a) the selected country changes, OR
                                //  (b) cities transition null→loaded.
                                // This forces optionsBuilder to re-run and
                                // display cities immediately once they arrive,
                                // because Autocomplete only calls optionsBuilder
                                // on text change — not on parent widget rebuilds.
                                Autocomplete<CityModel>(
                                  key: ValueKey('city-ac-${searchProvider.selectedCountryId}-${searchProvider.cities != null}'),
                                  optionsBuilder: (TextEditingValue textEditingValue) {
                                    if (searchProvider.cities == null) return const Iterable<CityModel>.empty();
                                    if (textEditingValue.text.isEmpty) {
                                      return searchProvider.cities!.take(10);
                                    } else {
                                      return searchProvider.cities!.where((city) =>
                                          (city.name ?? '').toLowerCase().contains(textEditingValue.text.toLowerCase()));
                                    }
                                  },
                                  displayStringForOption: (CityModel option) => option.name ?? '',
                                  onSelected: (CityModel city) {
                                    searchProvider.setSelectedCity(city.id);
                                  },
                                  fieldViewBuilder: (context, textEditingController, focusNode, onFieldSubmitted) {
                                    if (searchProvider.selectedCityId != null && textEditingController.text.isEmpty) {
                                      final selected = searchProvider.cities?.where((c) => c.id == searchProvider.selectedCityId).firstOrNull;
                                      if (selected?.name != null) {
                                        textEditingController.text = selected!.name!;
                                      }
                                    } else if (searchProvider.selectedCityId == null) {
                                      textEditingController.clear();
                                    }

                                    return Container(
                                      decoration: BoxDecoration(
                                        color: Theme.of(context).cardColor,
                                        border: Border.all(width: .7, color: Theme.of(context).hintColor.withValues(alpha: .3)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                                      ),
                                      child: TextField(
                                        controller: textEditingController,
                                        focusNode: focusNode,
                                        autofocus: true,
                                        // No onChanged server call here: optionsBuilder
                                        // already filters the loaded city list locally.
                                        // A server-side debounced call wouldn't help
                                        // because Autocomplete only re-runs optionsBuilder
                                        // on text changes, not on widget rebuilds.
                                        decoration: InputDecoration(
                                          hintText: getTranslated('select_city', context),
                                          contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                                          border: InputBorder.none,
                                          suffixIcon: searchProvider.selectedCityId != null ? IconButton(
                                            icon: const Icon(Icons.clear, size: 18),
                                            onPressed: () {
                                              textEditingController.clear();
                                              searchProvider.setSelectedCity(null);
                                            },
                                          ) : null,
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ],

                              // Area
                              if (searchProvider.selectedCityId != null)...[
                                const SizedBox(height: Dimensions.paddingSizeSmall),
                                Text(getTranslated('area', context)!, style: textRegular),
                                const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                                // Key recreates when city changes OR areas become
                                // available (null→loaded), same logic as city above.
                                Autocomplete<AreaModel>(
                                  key: ValueKey('area-ac-${searchProvider.selectedCityId}-${searchProvider.areas != null}'),
                                  optionsBuilder: (TextEditingValue textEditingValue) {
                                    if (searchProvider.areas == null) return const Iterable<AreaModel>.empty();
                                    if (textEditingValue.text.isEmpty) {
                                      return searchProvider.areas!.take(10);
                                    } else {
                                      return searchProvider.areas!.where((area) =>
                                          (area.name ?? '').toLowerCase().contains(textEditingValue.text.toLowerCase()));
                                    }
                                  },
                                  displayStringForOption: (AreaModel option) => option.name ?? '',
                                  onSelected: (AreaModel area) {
                                    searchProvider.setSelectedArea(area.id);
                                  },
                                  fieldViewBuilder: (context, textEditingController, focusNode, onFieldSubmitted) {
                                    if (searchProvider.selectedAreaId != null && textEditingController.text.isEmpty) {
                                      final selected = searchProvider.areas?.where((a) => a.id == searchProvider.selectedAreaId).firstOrNull;
                                      if (selected?.name != null) {
                                        textEditingController.text = selected!.name!;
                                      }
                                    } else if (searchProvider.selectedAreaId == null) {
                                      textEditingController.clear();
                                    }

                                    return Container(
                                      decoration: BoxDecoration(
                                        color: Theme.of(context).cardColor,
                                        border: Border.all(width: .7, color: Theme.of(context).hintColor.withValues(alpha: .3)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                                      ),
                                      child: TextField(
                                        controller: textEditingController,
                                        focusNode: focusNode,
                                        autofocus: true,
                                        // Local optionsBuilder filtering handles search;
                                        // no server call needed here.
                                        decoration: InputDecoration(
                                          hintText: getTranslated('select_area', context),
                                          contentPadding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                                          border: InputBorder.none,
                                          suffixIcon: searchProvider.selectedAreaId != null ? IconButton(
                                            icon: const Icon(Icons.clear, size: 18),
                                            onPressed: () {
                                              textEditingController.clear();
                                              searchProvider.setSelectedArea(null);
                                            },
                                          ) : null,
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ],
                              const SizedBox(height: Dimensions.paddingSizeDefault),

                              if(Provider.of<SplashController>(context, listen: false).configModel?.digitalProductSetting == '1')...[
                                Padding(
                                  padding: const EdgeInsets.fromLTRB(0, 0, 0, 0),
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    const SizedBox(height: Dimensions.paddingSizeSmall),
                                    Text(getTranslated('product_type', context)!,
                                        style: titilliumRegular.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyLarge?.color)),
                                    const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                                      decoration: BoxDecoration(
                                        color: Theme.of(context).cardColor,
                                        border: Border.all(width: .7,color: Theme.of(context).hintColor.withValues(alpha:.3)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                                      ),
                                      child: DropdownButton<String>(
                                        value: searchProvider.productTypeIndex == 0 ? 'all_product_search' : searchProvider.productTypeIndex == 1 ? 'physical' : 'digital',
                                        items: <String>['all_product_search', 'physical', 'digital'].map((String value) {
                                          return DropdownMenuItem<String>(
                                            value: value,
                                            child: Text(getTranslated(value, context)!),
                                          );
                                        }).toList(),
                                        onChanged: (value) {
                                          searchProvider.setProductTypeIndex(value == 'all_product_search' ? 0 : value == 'physical' ? 1 : 2, true);
                                        },
                                        isExpanded: true,
                                        underline: const SizedBox(),
                                      ),
                                    ),
                                  ]),
                                ),
                                const SizedBox(height: Dimensions.paddingSizeSmall),
                              ],

                              // Category
                             Text(getTranslated('CATEGORY', context) ?? '',
                               style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)
                             ),

                             Divider(color: Theme.of(context).hintColor.withValues(alpha:.25), thickness: .5),

                              if(categoryProvider.filteredCategoryList.isNotEmpty)
                                ConstrainedBox(
                                   constraints: const BoxConstraints(
                                    minHeight: 40.0,
                                    maxHeight: 350.0,
                                  ),
                                  child: RepaintBoundary(
                                    child: ListView.builder(
                                      itemCount: categoryProvider.filteredCategoryList.length,
                                      shrinkWrap: true,
                                      itemBuilder: (context, index){
                                        return Column(children: [
                                          CategoryFilterItem(title: categoryProvider.filteredCategoryList[index].name,
                                            checked: categoryProvider.filteredCategoryList[index].isSelected ?? false,
                                            onTap: () => categoryProvider.checkedToggleCategory(index)),
                                          if(categoryProvider.filteredCategoryList[index].isSelected ?? false)
                                            Padding(padding: const EdgeInsets.only(left: Dimensions.paddingSizeLarge),
                                              child: RepaintBoundary(
                                                child: ListView.builder(
                                                  itemCount: categoryProvider.filteredCategoryList[index].subCategories?.length ?? 0,
                                                  shrinkWrap: true,
                                                  padding: EdgeInsets.zero,
                                                  physics: const NeverScrollableScrollPhysics(),
                                                  itemBuilder: (context, subIndex){
                                                    return CategoryFilterItem(title: categoryProvider.filteredCategoryList[index].subCategories?[subIndex].name,
                                                      checked: categoryProvider.filteredCategoryList[index].subCategories?[subIndex].isSelected ?? false,
                                                      onTap: () => categoryProvider.checkedToggleSubCategory(index, subIndex));
                                                  }),
                                              ),
                                            )
                                        ],
                                        );
                                      }),
                                  ),
                                ),

                              // Brand
                              if((searchProvider.productTypeIndex == 0 || searchProvider.productTypeIndex == 1) && brandProvider.brandList.isNotEmpty)...[
                                Padding(padding: const EdgeInsets.only(top: Dimensions.paddingSizeDefault),
                                  child: Text(getTranslated('brand', context) ?? '',
                                    style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color))),

                                Divider(color: Theme.of(context).hintColor.withValues(alpha:.25), thickness: .5),

                                const SizedBox(height: Dimensions.paddingSizeSmall),
                                Autocomplete<int> (
                                  optionsBuilder: (TextEditingValue value) {
                                    if (value.text.isEmpty) {
                                      return const Iterable<int>.empty();
                                    } else {
                                      return brandsIndices.where((bIndex) => brandProvider.brandList[bIndex].name!.toLowerCase().contains(value.text.toLowerCase()));
                                    }
                                  },
                                  fieldViewBuilder: (context, controller, node, onComplete) {
                                    return Container(
                                      height: 50,
                                      decoration: BoxDecoration(color: Theme.of(context).highlightColor,
                                        border: Border.all(width: 1, color: Theme.of(context).hintColor.withValues(alpha:.50)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                      ),
                                      child: TextField(
                                        controller: controller,
                                        focusNode: node,
                                        onEditingComplete: onComplete,
                                        decoration: InputDecoration(
                                          hintText: getTranslated('search_by_brand', context) ?? 'Search by brands',
                                          hintStyle: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).hintColor),
                                          border: OutlineInputBorder(
                                              borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                              borderSide: BorderSide.none
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                  displayStringForOption: (value) => brandProvider.brandList[value].name ?? '',
                                  onSelected: (int value) {
                                    brandProvider.checkedToggleBrand(value);
                                  },
                                ),
                                const SizedBox(height: Dimensions.paddingSizeSmall),

                                if(brandProvider.brandList.isNotEmpty)
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      minHeight: 40.0,
                                      maxHeight: 350.0,
                                    ),
                                    child: SizedBox(
                                      child: RepaintBoundary(
                                        child: ListView.builder(
                                          itemCount: brandProvider.brandList.length,
                                          shrinkWrap: true,
                                          itemBuilder: (context, index){
                                            return CategoryFilterItem(title: brandProvider.brandList[index].name,
                                                checked: brandProvider.brandList[index].checked ?? false,
                                                onTap: () => brandProvider.checkedToggleBrand(index));
                                          }),
                                      ),
                                    ),
                                  ),
                              ],

                              //Author
                              if(authorList != null && authorList.isNotEmpty && (searchProvider.productTypeIndex == 0 || searchProvider.productTypeIndex == 2))...[
                                const SizedBox(height: Dimensions.paddingSizeSmall),
                                Text(getTranslated('author_creator_artist', context) ?? '',
                                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)
                                ),
                                const SizedBox(height: Dimensions.paddingSizeSmall),

                                Autocomplete<int> (
                                  optionsBuilder: (TextEditingValue value) {
                                    if (value.text.isEmpty) {
                                      return const Iterable<int>.empty();
                                    } else {
                                      return authors.where((author) => (authorList[author].name ?? '').toLowerCase().contains(value.text.toLowerCase()));
                                    }
                                  },
                                  fieldViewBuilder: (context, controller, node, onComplete) {
                                    return Container(
                                      height: 50,
                                      decoration: BoxDecoration(color: Theme.of(context).highlightColor,
                                        border: Border.all(width: 1, color: Theme.of(context).hintColor.withValues(alpha:.50)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                      ),
                                      child: TextField(
                                        controller: controller,
                                        focusNode: node,
                                        onEditingComplete: onComplete,
                                        decoration: InputDecoration(
                                          hintText: getTranslated('search_by_author', context),
                                          hintStyle: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).hintColor),
                                          border: OutlineInputBorder(
                                              borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                              borderSide: BorderSide.none
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                  displayStringForOption: (value) =>  authorList[value].name ?? '',
                                  onSelected: (int value) {
                                    searchProvider.checkedToggleAuthors(value, widget.fromShop);
                                  },
                                ),

                                if(authorList.isNotEmpty)
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      minHeight: 40.0,
                                      maxHeight: 350.0,
                                    ),
                                    child: SizedBox(
                                      child: RepaintBoundary(
                                        child: ListView.builder(
                                          itemCount: authorList.length,
                                          shrinkWrap: true,
                                          itemBuilder: (context, index){
                                            return Column(children: [
                                              CategoryFilterItem(title: authorList[index].name,
                                                checked: authorList[index].isChecked ?? false,
                                                count: authorList[index].productsCount,
                                                onTap: () => searchProvider.checkedToggleAuthors(index, widget.fromShop)),
                                            ],
                                            );
                                          }),
                                      ),
                                    ),
                                  ),
                              ],

                              //Publishing House
                              if(publishingHouse != null && publishingHouse.isNotEmpty && (searchProvider.productTypeIndex == 0 || searchProvider.productTypeIndex == 2))...[
                                const SizedBox(height: Dimensions.paddingSizeSmall),
                                Text( getTranslated('publishing_house', context) ?? '',
                                  style: titilliumSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge, color: Theme.of(context).textTheme.bodyLarge?.color)
                                ),
                                const SizedBox(height: Dimensions.paddingSizeSmall),

                                Autocomplete<int> (
                                  optionsBuilder: (TextEditingValue value) {
                                    if (value.text.isEmpty) {
                                      return const Iterable<int>.empty();
                                    } else {
                                      return publishingHouses.where((author) => publishingHouse[author].name!.toLowerCase().contains(value.text.toLowerCase()));
                                    }
                                  },
                                  fieldViewBuilder: (context, controller, node, onComplete) {
                                    return Container(
                                      height: 50,
                                      decoration: BoxDecoration(color: Theme.of(context).highlightColor,
                                        border: Border.all(width: 1, color: Theme.of(context).hintColor.withValues(alpha:.50)),
                                        borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                      ),
                                      child: TextField(
                                        controller: controller,
                                        focusNode: node,
                                        onEditingComplete: onComplete,
                                        decoration: InputDecoration(
                                          hintText: getTranslated('search_by_publishing_house', context),
                                          hintStyle: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).hintColor),
                                          border: OutlineInputBorder(
                                              borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
                                              borderSide: BorderSide.none
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                  displayStringForOption: (value) =>  publishingHouse[value].name ?? '',
                                  onSelected: (int value) {
                                    searchProvider.checkedTogglePublishingHouse(value, widget.fromShop);
                                  },
                                ),

                                if(publishingHouse.isNotEmpty)
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      minHeight: 40.0,
                                      maxHeight: 350.0,
                                    ),
                                    child: SizedBox(
                                      child: RepaintBoundary(
                                        child: ListView.builder(
                                           itemCount: publishingHouse.length,
                                          shrinkWrap: true,
                                          itemBuilder: (context, index){
                                            return Column(children: [
                                              CategoryFilterItem(
                                                title: publishingHouse[index].name,
                                                checked: publishingHouse[index].isChecked ?? false,
                                                count: publishingHouse[index].productsCount,
                                                onTap: () => searchProvider.checkedTogglePublishingHouse(index, widget.fromShop)
                                              ),
                                            ],
                                            );
                                          }),
                                      ),
                                    ),
                                  ),
                              ],
                          ],
                        ),
                      ),
                    ),

                    Padding(padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                      child: CustomButton(
                        buttonText: getTranslated('apply', context),
                        onTap:

                        brandProvider.selectedBrandIds.isEmpty && categoryProvider.selectedCategoryIds.isEmpty && (widget.fromShop ? searchProvider.selectedSellerAuthorIds.isEmpty && searchProvider.sellerPublishingHouseIds.isEmpty : searchProvider.selectedAuthorIds.isEmpty && searchProvider.publishingHouseIds.isEmpty) && (searchProvider.productTypeIndex != 0 && searchProvider.productTypeIndex != 1 && searchProvider.productTypeIndex != 2) && searchProvider.selectedCountryId == null && searchProvider.minPriceForFilter == AppConstants.minFilter && searchProvider.maxPriceForFilter == AppConstants.maxFilter ? null : () {
                          searchProvider.setFilterApply(isFiltered: true);
                          List<int> selectedBrandIdsList =[];
                          List<int> selectedCategoryIdsList =[];

                          for(CategoryModel category in categoryProvider.filteredCategoryList) {
                            if(category.isSelected ?? false){
                              selectedCategoryIdsList.add(category.id!);
                            }
                          }

                          for(CategoryModel category in categoryProvider.filteredCategoryList) {
                            if(category.isSelected ?? false){
                              if(category.subCategories != null){
                                for(int i=0; i< category.subCategories!.length; i++){
                                  if(category.subCategories![i].isSelected ?? false){
                                    selectedCategoryIdsList.add(category.subCategories![i].id!);
                                  }
                                }
                              }

                            }
                          }
                          for(BrandModel brand in brandProvider.brandList){
                            if(brand.checked ?? false){
                              selectedBrandIdsList.add(brand.id!);
                            }
                          }

                          if(searchProvider.productTypeIndex == 1  && selectedCategoryIdsList.isEmpty && selectedBrandIdsList.isEmpty && searchProvider.productTypeIndex == 0) {
                            showCustomSnackBarWidget(getTranslated('select_brand_or_category_first', context), context, snackBarType: SnackBarType.warning);
                          } else if (searchProvider.productTypeIndex == 2 &&((searchProvider.selectedAuthorIds.isEmpty && searchProvider.publishingHouseIds.isEmpty) || (searchProvider.selectedSellerAuthorIds.isEmpty && searchProvider.sellerPublishingHouseIds.isEmpty)) && searchProvider.productTypeIndex == 0){
                            showCustomSnackBarWidget(getTranslated('select_author_or_publishing_first', context), context, snackBarType: SnackBarType.warning);
                          } else{
                            String selectedCategoryId = selectedCategoryIdsList.isNotEmpty? jsonEncode(selectedCategoryIdsList) : '[]';
                            String selectedBrandId = selectedBrandIdsList.isNotEmpty? jsonEncode(selectedBrandIdsList) : '[]';
                            String selectedAuthorId = widget.fromShop ?
                            searchProvider.selectedSellerAuthorIds.isNotEmpty? jsonEncode(searchProvider.selectedSellerAuthorIds) : '[]' :
                            searchProvider.selectedAuthorIds.isNotEmpty? jsonEncode(searchProvider.selectedAuthorIds) : '[]';
                            String selectedPublishingId =  widget.fromShop ?
                            searchProvider.sellerPublishingHouseIds.isNotEmpty? jsonEncode(searchProvider.sellerPublishingHouseIds) : '[]' :
                            searchProvider.publishingHouseIds.isNotEmpty? jsonEncode(searchProvider.publishingHouseIds) : '[]';

                            if(widget.fromShop) {
                              productController.getSellerProductList(widget.slug.toString(), 1, "", categoryIds: selectedCategoryId,
                                brandIds: selectedBrandId, authorIds: selectedAuthorId, publishingIds: selectedPublishingId,
                                productType:  searchProvider.productTypeIndex == 0 ? 'all' : searchProvider.productTypeIndex == 1 ? 'physical' : 'digital'
                              ).then((value) {
                                if(value.response?.statusCode == 200){
                                  if(context.mounted) {
                                    Provider.of<SellerProductController>(context, listen: false).setFilterApply(true);
                                    Navigator.pop(context);
                                  }
                                }
                              });
                            } else {
                              if (widget.fromHome) {
                                searchProvider.fromHomeFilter = true;
                              }
                              searchProvider.searchProduct(query : searchProvider.searchController.text.toString(),
                                offset: 1, brandIds: selectedBrandId, categoryIds: selectedCategoryId, authorIds: selectedAuthorId, publishingIds: selectedPublishingId,
                                sort: searchProvider.sortText, priceMin: searchProvider.minPriceForFilter.toString(), priceMax: searchProvider.maxPriceForFilter.toString(),
                                countryId: searchProvider.selectedCountryId, cityId: searchProvider.selectedCityId, areaId: searchProvider.selectedAreaId);
                              if (context.mounted) {
                                Navigator.pop(context);
                                if (widget.fromHome) {
                                  RouterHelper.getSearchRoute(action: RouteAction.push);
                                }
                              }
                            }
                          }
                        },

                      ),
                    ),
                  ],
                ),
              );
            });
          });
        });
      }),
    );
  }
}

class FilterItemWidget extends StatelessWidget {
  final String? title;
  final int index;
  const FilterItemWidget({super.key, required this.title, required this.index});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      child: Container(decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall)),
        child: Row(children: [
          Padding(padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
            child: InkWell(
                onTap: ()=> Provider.of<SearchProductController>(context, listen: false).setFilterIndex(index),
                child: Icon(Provider.of<SearchProductController>(context).filterIndex == index? Icons.check_box_rounded: Icons.check_box_outline_blank_rounded,
                    color: (Provider.of<SearchProductController>(context).filterIndex == index )? Theme.of(context).primaryColor: Theme.of(context).hintColor.withValues(alpha:.5))),
          ),
          Expanded(child: Text(title??'', style: textRegular.copyWith())),

        ],),),
    );
  }
}

class CategoryFilterItem extends StatelessWidget {
  final String? title;
  final bool checked;
  final Function()? onTap;
  final int? count;
  const CategoryFilterItem({super.key, required this.title, required this.checked, this.onTap, this.count});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
      child: InkWell(
        onTap: onTap,
        child: Container(decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall)),
          child: Row(children: [
            Padding(padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
              child: Icon(checked? Icons.check_box_rounded: Icons.check_box_outline_blank_rounded,
                  color: (checked && !Provider.of<ThemeController>(context, listen: false).darkTheme)?
                  Theme.of(context).primaryColor:(checked && Provider.of<ThemeController>(context, listen: false).darkTheme)?
                  Colors.white : Theme.of(context).hintColor.withValues(alpha:.5)),
            ),
            Expanded(child: Text(title??'', style: textRegular.copyWith(color: Theme.of(context).textTheme.bodyLarge?.color))),
            if(count != null && count! > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  count.toString(),
                  style: textRegular.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(context).primaryColor,
                  ),
                ),
              ),
          ],),),
      ),
    );
  }
}

