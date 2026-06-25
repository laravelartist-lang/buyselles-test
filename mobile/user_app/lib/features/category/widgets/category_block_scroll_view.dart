import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_display_block_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_display_block_helper.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_deferred_block_content.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_display_block_section.dart';

typedef CategoryBlockBuilder = Widget Function(DisplayBlock block, int index);

class CategoryBlockScrollView extends StatelessWidget {
  final List<DisplayBlock> blocks;
  final String categoryName;
  final CategoryBlockBuilder blockBuilder;
  final bool primary;
  final ScrollPhysics? physics;
  final int? currentStepIndex;
  final double bottomPadding;
  final VoidCallback? onNearScrollEnd;

  const CategoryBlockScrollView({
    super.key,
    required this.blocks,
    required this.categoryName,
    required this.blockBuilder,
    this.primary = true,
    this.physics,
    this.currentStepIndex,
    this.bottomPadding = 32,
    this.onNearScrollEnd,
  });

  @override
  Widget build(BuildContext context) {
    if (blocks.isEmpty) {
      return const SizedBox.shrink();
    }

    final int? step = currentStepIndex;
    final List<int> indices;

    if (step != null) {
      final safeStep = step.clamp(0, blocks.length - 1);
      indices = [safeStep];
    } else {
      indices = List.generate(blocks.length, (i) => i);
    }

    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (onNearScrollEnd == null) {
          return false;
        }

        if (notification.metrics.maxScrollExtent <= 0) {
          return false;
        }

        if (notification is ScrollUpdateNotification || notification is ScrollEndNotification) {
          final threshold = notification.metrics.maxScrollExtent - 320;
          if (notification.metrics.pixels >= threshold) {
            onNearScrollEnd!();
          }
        }

        return false;
      },
      child: ListView(
        primary: primary,
        physics: physics ?? const BouncingScrollPhysics(),
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: EdgeInsets.only(bottom: bottomPadding, top: 8),
        children: [
          for (final index in indices)
            CategoryDisplayBlockSection(
              key: ValueKey(blocks[index].id),
              title: CategoryDisplayBlockHelper.titleForBlock(
                blocks[index],
                context,
                categoryName: categoryName,
              ),
              child: CategoryDeferredBlockContent(
                index: index,
                child: blockBuilder(blocks[index], index),
              ),
            ),
        ],
      ),
    );
  }
}
