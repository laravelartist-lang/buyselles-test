import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/physical_discovery_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/location_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/searchable_location_dialog.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class LocationFilterHeaderWidget extends StatelessWidget {
  const LocationFilterHeaderWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<PhysicalDiscoveryController>(
      builder: (context, controller, child) {
        final currentStage = controller.currentStage;

        return Container(
          padding: const EdgeInsets.symmetric(
            horizontal: Dimensions.paddingSizeDefault,
            vertical: Dimensions.paddingSizeSmall,
          ),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            boxShadow: [
              BoxShadow(
                color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
                blurRadius: 10,
                offset: const Offset(0, 4),
              )
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Row(
                      children: [
                        Icon(
                          Icons.location_on_outlined,
                          color: Theme.of(context).primaryColor,
                          size: Dimensions.iconSizeDefault,
                        ),
                        const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                        Expanded(
                          child: Text(
                            _getStageTitle(controller),
                            style: textBold.copyWith(
                              fontSize: Dimensions.fontSizeDefault,
                              color: Theme.of(context).primaryColor,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (currentStage != DiscoveryStage.global)
                    InkWell(
                      onTap: () => controller.resetToGlobal(),
                      borderRadius: BorderRadius.circular(Dimensions.paddingSizeExtraSmall),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Dimensions.paddingSizeSmall,
                          vertical: Dimensions.paddingSizeExtraSmall,
                        ),
                        child: Text(
                          'Reset',
                          style: titilliumRegular.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),

              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                child: Row(
                  children: [
                    _buildSelector(
                      context,
                      label: controller.selectedCountry?.name ?? 'Global',
                      icon: Icons.public,
                      onTap: () {
                        showDialog(
                          context: context,
                          // Consumer keeps the dialog alive-reactive: when
                          // fetchCountries() updates the list, the dialog
                          // rebuilds and SearchableLocationDialog.didUpdateWidget
                          // refreshes the filtered items.
                          builder: (ctx) => Consumer<PhysicalDiscoveryController>(
                            builder: (ctx, ctrl, _) => SearchableLocationDialog<LocationCountry>(
                              title: 'Select Country',
                              items: ctrl.countries,
                              itemLabel: (item) => item.name ?? '',
                              onSelect: (val) => ctrl.setCountry(val),
                              onSearch: (query) => ctrl.fetchCountries(
                                search: query.isEmpty ? null : query,
                              ),
                              isLoading: ctrl.isLoading,
                            ),
                          ),
                        );
                      },
                    ),

                    if (controller.selectedCountry != null) ...[
                      const SizedBox(width: Dimensions.paddingSizeSmall),
                      _buildSelector(
                        context,
                        label: controller.selectedCity?.name ?? 'Select City',
                        icon: Icons.location_city,
                        onTap: () {
                          showDialog(
                            context: context,
                            builder: (ctx) => Consumer<PhysicalDiscoveryController>(
                              builder: (ctx, ctrl, _) => SearchableLocationDialog<LocationCity>(
                                title: 'Select City',
                                items: ctrl.cities,
                                itemLabel: (item) => item.name ?? '',
                                onSelect: (val) {
                                  if (val != null) ctrl.setCity(val);
                                },
                                onSearch: (query) => ctrl.fetchCities(
                                  ctrl.selectedCountry!.id!,
                                  search: query.isEmpty ? null : query,
                                ),
                                isLoading: ctrl.isLoading,
                              ),
                            ),
                          );
                        },
                      ),
                    ],

                    if (controller.selectedCity != null) ...[
                      const SizedBox(width: Dimensions.paddingSizeSmall),
                      _buildSelector(
                        context,
                        label: controller.selectedArea?.name ?? 'Select Area',
                        icon: Icons.map_outlined,
                        onTap: () {
                          showDialog(
                            context: context,
                            builder: (ctx) => Consumer<PhysicalDiscoveryController>(
                              builder: (ctx, ctrl, _) => SearchableLocationDialog<LocationArea>(
                                title: 'Select Area',
                                items: ctrl.areas,
                                itemLabel: (item) => item.name ?? '',
                                onSelect: (val) {
                                  if (val != null) ctrl.setArea(val);
                                },
                                onSearch: (query) => ctrl.fetchAreas(
                                  ctrl.selectedCity!.id!,
                                  search: query.isEmpty ? null : query,
                                ),
                                isLoading: ctrl.isLoading,
                              ),
                            ),
                          );
                        },
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _getStageTitle(PhysicalDiscoveryController controller) {
    switch (controller.currentStage) {
      case DiscoveryStage.global:
        return 'Global Discovery';
      case DiscoveryStage.country:
        return controller.selectedCountry?.name ?? 'Global';
      case DiscoveryStage.city:
        return '${controller.selectedCity?.name}, ${controller.selectedCountry?.name}';
      case DiscoveryStage.area:
        return '${controller.selectedArea?.name}, ${controller.selectedCity?.name}';
    }
  }

  Widget _buildSelector(BuildContext context, {required String label, required IconData icon, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: 8),
        decoration: BoxDecoration(
          color: Theme.of(context).hintColor.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(Dimensions.paddingSizeSmall),
          border: Border.all(
            color: Theme.of(context).hintColor.withValues(alpha: 0.1),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: Theme.of(context).primaryColor),
            const SizedBox(width: 8),
            Text(
              label,
              style: titilliumSemiBold.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: Theme.of(context).textTheme.bodyLarge?.color,
              ),
            ),
            const SizedBox(width: 4),
            Icon(Icons.keyboard_arrow_down_rounded, size: 16, color: Theme.of(context).hintColor),
          ],
        ),
      ),
    );
  }
}
