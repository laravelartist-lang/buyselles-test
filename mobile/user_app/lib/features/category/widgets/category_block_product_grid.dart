import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/product_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/product/domain/models/product_model.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class CategoryBlockProductGrid extends StatelessWidget {
  final List<Product> products;
  final int? totalSize;
  final bool isLoadingMore;
  final VoidCallback? onLoadMore;

  const CategoryBlockProductGrid({
    super.key,
    required this.products,
    this.totalSize,
    this.isLoadingMore = false,
    this.onLoadMore,
  });

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final spacing = Dimensions.paddingSizeExtraSmall;
          final itemWidth = (constraints.maxWidth - spacing) / 2;

          return Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (int i = 0; i < products.length; i += 2)
                Padding(
                  padding: EdgeInsets.only(top: i > 0 ? spacing : 0),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: itemWidth,
                        child: ProductWidget(productModel: products[i]),
                      ),
                      if (i + 1 < products.length)
                        Padding(
                          padding: EdgeInsets.only(left: spacing),
                          child: SizedBox(
                            width: itemWidth,
                            child: ProductWidget(productModel: products[i + 1]),
                          ),
                        ),
                    ],
                  ),
                ),
              if (products.length < (totalSize ?? 0))
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeDefault),
                  child: isLoadingMore
                      ? const CircularProgressIndicator()
                      : TextButton(
                          onPressed: onLoadMore,
                          child: Text(
                            'Load More',
                            style: textBold.copyWith(color: Theme.of(context).primaryColor),
                          ),
                        ),
                ),
            ],
          );
        },
      ),
    );
  }
}
