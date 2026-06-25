import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_image_widget.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/product_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/physical_discovery_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/location_filter_header_widget.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:flutter_staggered_grid_view/flutter_staggered_grid_view.dart';
import 'package:provider/provider.dart';

class PhysicalCategoryDiscoveryScreen extends StatefulWidget {
  final CategoryModel categoryModel;

  const PhysicalCategoryDiscoveryScreen({super.key, required this.categoryModel});

  @override
  State<PhysicalCategoryDiscoveryScreen> createState() => _PhysicalCategoryDiscoveryScreenState();
}

class _PhysicalCategoryDiscoveryScreenState extends State<PhysicalCategoryDiscoveryScreen> {

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final controller = Provider.of<PhysicalDiscoveryController>(context, listen: false);
      controller.clearFilters();
      controller.fetchCountries();
      controller.fetchDiscoveryProducts(categoryId: widget.categoryModel.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(
        title: '${widget.categoryModel.name ?? 'Category'} Discovery',
      ),
      body: Column(
        children: [
          const LocationFilterHeaderWidget(),

          Expanded(
            child: Consumer<PhysicalDiscoveryController>(
              builder: (context, controller, child) {
                if (controller.isLoading && controller.products.isEmpty && controller.vendors.isEmpty) {
                  return const Center(child: CircularProgressIndicator());
                }

                final isVendorStage = controller.currentStage == DiscoveryStage.city ||
                    controller.currentStage == DiscoveryStage.area;

                return CustomScrollView(
                  physics: const BouncingScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        child: Container(
                          padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                          decoration: BoxDecoration(
                            color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
                            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                            border: Border.all(
                              color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                            ),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                isVendorStage ? Icons.storefront_rounded : Icons.trending_up_rounded,
                                color: Theme.of(context).primaryColor,
                              ),
                              const SizedBox(width: Dimensions.paddingSizeSmall),
                              Expanded(
                                child: Text(
                                  _getSectionHeader(controller),
                                  style: textMedium.copyWith(
                                    fontSize: Dimensions.fontSizeDefault,
                                    color: Theme.of(context).textTheme.bodyLarge?.color,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    if (controller.isLoading)
                      const SliverToBoxAdapter(child: Center(child: Padding(
                        padding: EdgeInsets.all(8.0),
                        child: CircularProgressIndicator(),
                      ))),

                    if (!isVendorStage)
                      _buildProductGrid(context, controller)
                    else
                      _buildVendorList(context, controller),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  String _getSectionHeader(PhysicalDiscoveryController controller) {
    switch (controller.currentStage) {
      case DiscoveryStage.global:
        return 'Best Selling Products Globally';
      case DiscoveryStage.country:
        return 'Best Selling Products in ${controller.selectedCountry?.name ?? 'Country'}';
      case DiscoveryStage.city:
        return 'Verified Merchants Operating in ${controller.selectedCity?.name ?? 'City'}';
      case DiscoveryStage.area:
        return 'Merchants Servicing ${controller.selectedArea?.name ?? 'Area'}, ${controller.selectedCity?.name ?? 'City'}';
    }
  }

  Widget _buildProductGrid(BuildContext context, PhysicalDiscoveryController controller) {
    final products = controller.products;

    if (products.isEmpty && !controller.isLoading) {
      return SliverFillRemaining(
        child: Center(
          child: Text(
            'No products available in this region.',
            style: titilliumRegular.copyWith(color: Theme.of(context).hintColor),
          ),
        ),
      );
    }

    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
      sliver: SliverMasonryGrid.count(
        crossAxisCount: MediaQuery.of(context).size.width > 480 ? 3 : 2,
        mainAxisSpacing: Dimensions.paddingSizeSmall,
        crossAxisSpacing: Dimensions.paddingSizeSmall,
        childCount: products.length,
        itemBuilder: (context, index) {
          return ProductWidget(
            productModel: products[index],
            productNameLine: 2,
          );
        },
      ),
    );
  }

  Widget _buildVendorList(BuildContext context, PhysicalDiscoveryController controller) {
    final vendors = controller.vendors;

    if (vendors.isEmpty && !controller.isLoading) {
      return SliverFillRemaining(
        child: Center(
          child: Text(
            'No local vendors found in this area.',
            style: titilliumRegular.copyWith(color: Theme.of(context).hintColor),
          ),
        ),
      );
    }

    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
      sliver: SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            final vendor = vendors[index];
            final shop = vendor.shop;

            return Container(
              margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
                boxShadow: [
                  BoxShadow(
                    color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  )
                ],
                border: Border.all(
                  color: Theme.of(context).primaryColor.withValues(alpha: 0.05),
                ),
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () => controller.navigateToVendorInventory(context, vendor, widget.categoryModel),
                  borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      ClipRRect(
                        borderRadius: const BorderRadius.vertical(
                          top: Radius.circular(Dimensions.radiusLarge),
                        ),
                        child: SizedBox(
                          height: 120,
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              CustomImageWidget(
                                image: shop?.bannerFullUrl?.path ?? '',
                                fit: BoxFit.cover,
                              ),
                              Container(
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [
                                      Colors.transparent,
                                      Colors.black.withValues(alpha: 0.6),
                                    ],
                                  ),
                                ),
                              ),
                              Positioned(
                                bottom: Dimensions.paddingSizeSmall,
                                right: Dimensions.paddingSizeSmall,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: Dimensions.paddingSizeSmall,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).primaryColor,
                                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                                  ),
                                  child: Text(
                                    'Explore Stock',
                                    style: textBold.copyWith(
                                      color: Colors.white,
                                      fontSize: Dimensions.fontSizeExtraSmall,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        child: Row(
                          children: [
                            Container(
                              width: 60,
                              height: 60,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: Theme.of(context).primaryColor,
                                  width: 2,
                                ),
                              ),
                              child: ClipOval(
                                child: CustomImageWidget(
                                  image: shop?.imageFullUrl?.path ?? '',
                                  fit: BoxFit.cover,
                                ),
                              ),
                            ),
                            const SizedBox(width: Dimensions.paddingSizeDefault),

                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    shop?.name ?? vendor.fName ?? 'Store',
                                    style: titleHeader.copyWith(
                                      fontSize: Dimensions.fontSizeLarge,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 4),

                                  Row(
                                    children: [
                                      Icon(
                                        Icons.location_on,
                                        color: Theme.of(context).hintColor,
                                        size: Dimensions.iconSizeSmall,
                                      ),
                                      const SizedBox(width: 4),
                                      Expanded(
                                        child: Text(
                                          shop?.address ?? 'Local Area',
                                          style: textRegular.copyWith(
                                            color: Theme.of(context).hintColor,
                                            fontSize: Dimensions.fontSizeSmall,
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 6),

                                  Row(
                                    children: [
                                      Icon(
                                        Icons.star_rounded,
                                        color: Colors.amber,
                                        size: Dimensions.iconSizeDefault,
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        '${vendor.averageRating ?? 4.5}',
                                        style: textBold.copyWith(
                                          fontSize: Dimensions.fontSizeSmall,
                                        ),
                                      ),
                                      Text(
                                        ' (${vendor.ratingCount ?? 0} reviews)',
                                        style: textRegular.copyWith(
                                          color: Theme.of(context).hintColor,
                                          fontSize: Dimensions.fontSizeSmall,
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
          childCount: vendors.length,
        ),
      ),
    );
  }
}
