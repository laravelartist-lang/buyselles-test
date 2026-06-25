import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/controllers/category_display_block_controller.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_display_block_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_block_step_helper.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_empty_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_block_scroll_view.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/location_pipeline_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/mixed_products_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/sub_categories_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/sub_category_products_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/sub_sub_categories_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/sub_sub_category_products_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/category_grouped_products_block.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/widgets/vendors_list_block_widget.dart';
import 'package:flutter_sixvalley_ecommerce/helper/route_healper.dart';
import 'package:flutter_sixvalley_ecommerce/localization/language_constrants.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';
import 'package:provider/provider.dart';

class DynamicCategoryScreen extends StatefulWidget {
  final CategoryModel categoryModel;
  final int initialStep;

  const DynamicCategoryScreen({super.key, required this.categoryModel, this.initialStep = 0});

  @override
  State<DynamicCategoryScreen> createState() => _DynamicCategoryScreenState();
}

class _DynamicCategoryScreenState extends State<DynamicCategoryScreen> {
  late final CategoryBlockStepResolver _stepResolver;
  final GlobalKey<CategoryGroupedProductsBlockState> _groupedProductsKey =
      GlobalKey<CategoryGroupedProductsBlockState>();

  bool _hasLoadedBlocks = false;
  int _currentStepIndex = 0;
  final Map<String, dynamic> _stepContext = {};
  bool _isResolvingStep = false;
  bool _initialStepResolved = false;
  bool _isResolvingInitial = false;
  bool _isSkippingEmpty = false;
  bool _isNavigatingBack = false;
  bool _hasNoContent = false;
  List<int> _dataBlockIndices = [];
  int? _nextStepIndex;

  @override
  void initState() {
    super.initState();
    _stepResolver = CategoryBlockStepResolver();
    _currentStepIndex = widget.initialStep;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadBlocks();
    });
  }

  void _loadBlocks() {
    if (widget.categoryModel.id != null && !_hasLoadedBlocks) {
      _hasLoadedBlocks = true;
      Provider.of<CategoryDisplayBlockController>(context, listen: false)
          .loadBlocks(widget.categoryModel.id!.toString());
    }
  }

  void _goBack() {
    if (Navigator.canPop(context)) {
      Navigator.pop(context);
      return;
    }

    RouterHelper.getCategoryScreenRoute(action: RouteAction.pushReplacement);
  }

  String _noContentMessage(BuildContext context) {
    return getTranslated('no_content_configured_for_this_category', context)
        ?? getTranslated('not_found_anything', context)
        ?? 'No content is available for this category right now.';
  }

  Widget _buildNoContentView(BuildContext context, {bool showRetry = false, VoidCallback? onRetry}) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.inbox_outlined,
              size: 64,
              color: Theme.of(context).hintColor,
            ),
            SizedBox(height: Dimensions.paddingSizeDefault),
            Text(
              _noContentMessage(context),
              textAlign: TextAlign.center,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: Theme.of(context).hintColor,
              ),
            ),
            SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              getTranslated('please_check_back_later', context)
                  ?? 'Please check back later or explore other categories.',
              textAlign: TextAlign.center,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: Theme.of(context).hintColor.withValues(alpha: 0.8),
              ),
            ),
            if (showRetry && onRetry != null) ...[
              SizedBox(height: Dimensions.paddingSizeDefault),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh, size: 18),
                label: Text(getTranslated('retry', context) ?? 'Retry'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _resolveInitialStep(List<DisplayBlock> blocks) async {
    if (_initialStepResolved || _isResolvingInitial) {
      return;
    }

    if (blocks.isEmpty || widget.categoryModel.id == null) {
      setState(() {
        _hasNoContent = true;
        _initialStepResolved = true;
      });
      return;
    }

    _isResolvingInitial = true;
    setState(() => _isResolvingStep = true);

    CategoryStepResolution resolution;

    try {
      resolution = await _stepResolver.resolveInitialStep(
        blocks: blocks,
        mainCategoryId: widget.categoryModel.id!,
        context: _stepContext,
      );
    } catch (e) {
      debugPrint('DynamicCategoryScreen._resolveInitialStep error: $e');
      if (!mounted) {
        return;
      }

      setState(() {
        _hasNoContent = false;
        _dataBlockIndices = List.generate(blocks.length, (index) => index);
        _currentStepIndex = 0;
        _nextStepIndex = blocks.length > 1 ? 1 : null;
        _isResolvingStep = false;
        _initialStepResolved = true;
        _isResolvingInitial = false;
      });
      return;
    }

    if (!mounted) {
      return;
    }

    if (resolution.hasNoDisplayableContent) {
      setState(() {
        _hasNoContent = true;
        _dataBlockIndices = resolution.dataBlockIndices;
        _nextStepIndex = null;
        _isResolvingStep = false;
        _initialStepResolved = true;
        _isResolvingInitial = false;
      });
      return;
    }

    _dataBlockIndices = resolution.dataBlockIndices;
    _nextStepIndex = await _stepResolver.findNextBlockWithData(
      blocks: blocks,
      currentStep: resolution.stepIndex!,
      mainCategoryId: widget.categoryModel.id!,
      context: _stepContext,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _currentStepIndex = resolution.stepIndex!;
      _isResolvingStep = false;
      _initialStepResolved = true;
      _isResolvingInitial = false;
    });
  }

  Future<void> _recomputeStepPlan({int? forStep, bool contextChanged = false}) async {
    if (widget.categoryModel.id == null) {
      return;
    }

    final controller = Provider.of<CategoryDisplayBlockController>(context, listen: false);
    final blocks = controller.blocks ?? [];
    final stepIndex = forStep ?? _currentStepIndex;

    if (blocks.isEmpty) {
      _dataBlockIndices = [];
      _nextStepIndex = null;
      return;
    }

    if (contextChanged) {
      _stepResolver.clearBlockCache();
      _dataBlockIndices = await _stepResolver.getDataBlockIndices(
        blocks: blocks,
        mainCategoryId: widget.categoryModel.id!,
        context: _stepContext,
      );
    }

    _nextStepIndex = await _stepResolver.findNextBlockWithData(
      blocks: blocks,
      currentStep: stepIndex,
      mainCategoryId: widget.categoryModel.id!,
      context: _stepContext,
    );
  }

  int? _stepAfterVendorBlock(List<DisplayBlock> blocks) {
    final vendorIndex = blocks.indexWhere((block) => block.blockType == 'vendors_list');
    if (vendorIndex == -1 || vendorIndex + 1 >= blocks.length) {
      return null;
    }

    return vendorIndex + 1;
  }

  int? _firstCategoryProductBlockIndex(List<DisplayBlock> blocks) {
    const productBlockTypes = <String>[
      'sub_category_products',
      'sub_sub_categories',
      'sub_sub_category_products',
    ];

    for (final blockType in productBlockTypes) {
      final index = blocks.indexWhere((block) => block.blockType == blockType);
      if (index != -1) {
        return index;
      }
    }

    return null;
  }

  int? _stepAfterCategorySelection(
    List<DisplayBlock> blocks, {
    required int parentPosition,
  }) {
    if (parentPosition == 2) {
      final productsIndex = blocks.indexWhere(
        (block) => block.blockType == 'sub_sub_category_products',
      );
      if (productsIndex != -1) {
        return productsIndex;
      }

      return _firstCategoryProductBlockIndex(blocks);
    }

    final subCategoriesIndex = blocks.indexWhere(
      (block) => block.blockType == 'sub_categories',
    );
    final startIndex = subCategoriesIndex >= 0 ? subCategoriesIndex + 1 : 0;
    const candidateTypes = <String>[
      'sub_sub_categories',
      'sub_category_products',
    ];

    for (int index = startIndex; index < blocks.length; index++) {
      if (candidateTypes.contains(blocks[index].blockType)) {
        return index;
      }
    }

    return _firstCategoryProductBlockIndex(blocks);
  }

  Future<void> _advanceToNextStep(
    Map<String, dynamic>? tapContext, {
    bool useLayoutOrder = false,
    int? layoutTargetStep,
  }) async {
    final contextChanged = tapContext != null && tapContext.isNotEmpty;
    if (contextChanged) {
      _stepContext.addAll(tapContext);
      _stepResolver.clearBlockCache();
    }

    final controller = Provider.of<CategoryDisplayBlockController>(context, listen: false);
    final blocks = controller.blocks ?? [];
    if (blocks.isEmpty || widget.categoryModel.id == null) {
      return;
    }

    setState(() => _isResolvingStep = true);

    final int? resolvedStep;
    if (layoutTargetStep != null) {
      resolvedStep = layoutTargetStep;
    } else if (tapContext?['parent_id'] != null) {
      resolvedStep = _stepAfterCategorySelection(
        blocks,
        parentPosition: 1,
      );
    } else if (tapContext?['vendor_id'] != null) {
      resolvedStep = _stepAfterVendorBlock(blocks)
          ?? (_currentStepIndex + 1 < blocks.length ? _currentStepIndex + 1 : null);
    } else if (useLayoutOrder && _currentStepIndex + 1 < blocks.length) {
      resolvedStep = _currentStepIndex + 1;
    } else {
      resolvedStep = await _stepResolver.findNextBlockWithData(
        blocks: blocks,
        currentStep: _currentStepIndex,
        mainCategoryId: widget.categoryModel.id!,
        context: _stepContext,
      );
    }

    if (!mounted) {
      return;
    }

    if (resolvedStep == null) {
      setState(() {
        _nextStepIndex = null;
        _isResolvingStep = false;
      });
      return;
    }

    final int nextStepIndex = resolvedStep!;

    await _recomputeStepPlan(forStep: nextStepIndex, contextChanged: contextChanged);

    if (!mounted) {
      return;
    }

    setState(() {
      _hasNoContent = false;
      _currentStepIndex = nextStepIndex;
      _isResolvingStep = false;
    });
  }

  Future<void> _handleNext() async {
    if (_isResolvingStep || _nextStepIndex == null) {
      return;
    }

    await _advanceToNextStep(null);
  }

  Future<void> _onBlockEmpty() async {
    if (!_initialStepResolved || _isSkippingEmpty || _isResolvingStep || _isNavigatingBack) {
      return;
    }

    final controller = Provider.of<CategoryDisplayBlockController>(context, listen: false);
    final blocks = controller.blocks ?? [];
    if (blocks.isEmpty) {
      setState(() => _hasNoContent = true);
      return;
    }

    _isSkippingEmpty = true;
    try {
      final nextStep = widget.categoryModel.id == null
          ? null
          : await _stepResolver.findNextBlockWithData(
              blocks: blocks,
              currentStep: _currentStepIndex,
              mainCategoryId: widget.categoryModel.id!,
              context: _stepContext,
            );

      if (nextStep == null) {
        if (mounted) {
          setState(() => _hasNoContent = true);
        }
        return;
      }

      await _advanceToNextStep(null);
    } finally {
      _isSkippingEmpty = false;
    }
  }

  Future<void> _handleBack() async {
    if (_hasNoContent) {
      _goBack();
      return;
    }

    final controller = Provider.of<CategoryDisplayBlockController>(context, listen: false);
    final blocks = controller.blocks ?? [];

    if (blocks.isEmpty || widget.categoryModel.id == null) {
      _goBack();
      return;
    }

    setState(() => _isResolvingStep = true);
    _isNavigatingBack = true;

    try {
      final previousStep = await _stepResolver.findPreviousBlockWithData(
        blocks: blocks,
        currentStep: _currentStepIndex,
        mainCategoryId: widget.categoryModel.id!,
        context: _stepContext,
      );

      if (!mounted) {
        return;
      }

      if (previousStep == null) {
        setState(() => _isResolvingStep = false);
        _goBack();
        return;
      }

      final updatedContext = CategoryBlockStepResolver.contextForStep(
        blocks: blocks,
        stepIndex: previousStep,
        currentContext: _stepContext,
      );

      _stepContext
        ..clear()
        ..addAll(updatedContext);

      await _recomputeStepPlan(forStep: previousStep, contextChanged: true);

      if (!mounted) {
        return;
      }

      setState(() {
        _hasNoContent = false;
        _currentStepIndex = previousStep;
        _isResolvingStep = false;
      });
    } catch (e) {
      debugPrint('DynamicCategoryScreen._handleBack error: $e');
      if (mounted) {
        setState(() => _isResolvingStep = false);
      }
    } finally {
      _isNavigatingBack = false;
    }
  }

  bool get _hasNextStep => _nextStepIndex != null;

  bool _isProductBlockStep(List<DisplayBlock> blocks, int stepIndex) {
    if (stepIndex < 0 || stepIndex >= blocks.length) {
      return false;
    }

    final blockType = blocks[stepIndex].blockType;
    return blockType == 'sub_category_products' || blockType == 'sub_sub_category_products';
  }

  void _loadMoreProductsOnScroll() {
    _groupedProductsKey.currentState?.loadMoreIfNeeded();
  }

  Widget _buildStepNavigationBar(BuildContext context) {
    final primaryColor = Theme.of(context).primaryColor;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        border: Border(
          bottom: BorderSide(
            color: Theme.of(context).hintColor.withValues(alpha: 0.12),
          ),
        ),
      ),
      padding: const EdgeInsets.fromLTRB(
        Dimensions.paddingSizeDefault,
        Dimensions.paddingSizeSmall,
        Dimensions.paddingSizeDefault,
        Dimensions.paddingSizeSmall,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_displayTotalSteps > 1) ...[
            Row(
              children: [
                for (int position = 0; position < _dataBlockIndices.length; position++)
                  Expanded(
                    child: Container(
                      height: 4,
                      margin: EdgeInsets.only(
                        right: position == _dataBlockIndices.length - 1 ? 0 : 6,
                      ),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        color: position == _displayStepNumber - 1
                            ? primaryColor
                            : position < _displayStepNumber - 1
                                ? primaryColor.withValues(alpha: 0.45)
                                : Theme.of(context).hintColor.withValues(alpha: 0.2),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            Text(
              '${getTranslated('step', context) ?? 'Step'} $_displayStepNumber ${getTranslated('of', context) ?? 'of'} $_displayTotalSteps',
              textAlign: TextAlign.center,
              style: textMedium.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: Theme.of(context).hintColor,
              ),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
          ],
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _isResolvingStep ? null : _handleBack,
                  icon: const Icon(Icons.arrow_back, size: 16),
                  label: Text(getTranslated('back', context) ?? 'Back'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: primaryColor,
                    side: BorderSide(color: primaryColor.withValues(alpha: 0.6)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _isResolvingStep
                      ? null
                      : _hasNextStep
                          ? _handleNext
                          : () {},
                  icon: Icon(
                    Icons.arrow_forward,
                    size: 16,
                    color: _hasNextStep
                        ? Theme.of(context).colorScheme.onPrimary
                        : primaryColor.withValues(alpha: 0.65),
                  ),
                  label: Text(
                    getTranslated('next', context) ?? 'Next',
                    style: textMedium.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: _hasNextStep
                          ? Theme.of(context).colorScheme.onPrimary
                          : primaryColor.withValues(alpha: 0.65),
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _hasNextStep
                        ? primaryColor
                        : primaryColor.withValues(alpha: 0.14),
                    foregroundColor: Theme.of(context).colorScheme.onPrimary,
                    disabledBackgroundColor: primaryColor.withValues(alpha: 0.14),
                    disabledForegroundColor: primaryColor.withValues(alpha: 0.65),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    elevation: _hasNextStep ? 1 : 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String get _appBarTitle {
    final parentName = _stepContext['parent_name'] as String?;
    if (parentName != null && parentName.isNotEmpty) {
      return parentName;
    }

    final vendorName = _stepContext['vendor_name'] as String?;
    if (vendorName != null && vendorName.isNotEmpty) {
      return vendorName;
    }

    return widget.categoryModel.name ?? (getTranslated('category', context) ?? 'Category');
  }

  int get _displayStepNumber {
    if (_dataBlockIndices.isEmpty) {
      return _currentStepIndex + 1;
    }

    final position = _dataBlockIndices.indexOf(_currentStepIndex);
    return position >= 0 ? position + 1 : _currentStepIndex + 1;
  }

  int get _displayTotalSteps {
    return _dataBlockIndices.isNotEmpty ? _dataBlockIndices.length : 0;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(
        title: _appBarTitle,
        onBackPressed: _handleBack,
      ),
      body: Consumer<CategoryDisplayBlockController>(
        builder: (context, controller, child) {
          if (!_hasLoadedBlocks && widget.categoryModel.id != null) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _loadBlocks();
            });
          }

          if (controller.isLoading || _isResolvingStep) {
            return const Center(child: CircularProgressIndicator());
          }

          final error = controller.error;
          final blocks = controller.blocks ?? [];

          if (!_initialStepResolved && blocks.isNotEmpty && widget.categoryModel.id != null) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _resolveInitialStep(blocks);
            });
          }

          if (error != null && blocks.isEmpty) {
            final isNoBlocks = error == 'no_blocks_configured';

            return _buildNoContentView(
              context,
              showRetry: !isNoBlocks,
              onRetry: !isNoBlocks
                  ? () {
                      _hasLoadedBlocks = false;
                      _initialStepResolved = false;
                      _hasNoContent = false;
                      _loadBlocks();
                    }
                  : null,
            );
          }

          if (blocks.isEmpty || _hasNoContent) {
            return _buildNoContentView(context);
          }

          final safeStep = _currentStepIndex.clamp(0, blocks.length - 1);
          if (safeStep != _currentStepIndex) {
            _currentStepIndex = safeStep;
          }

          return Column(
            children: [
              _buildStepNavigationBar(context),
              Expanded(
                child: CategoryBlockScrollView(
                  blocks: blocks,
                  categoryName: widget.categoryModel.name ?? '',
                  currentStepIndex: _currentStepIndex,
                  bottomPadding: 32,
                  onNearScrollEnd: _isProductBlockStep(blocks, safeStep)
                      ? _loadMoreProductsOnScroll
                      : null,
                  blockBuilder: (block, index) => _buildBlockContent(block),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildBlockContent(DisplayBlock block) {
    final categoryId = widget.categoryModel.id ?? 0;
    final categoryName = widget.categoryModel.name ?? '';
    final parentId = _stepContext['parent_id'] as int?;
    final vendorId = _stepContext['vendor_id'] as int?;
    final blocks = Provider.of<CategoryDisplayBlockController>(context, listen: false).blocks ?? [];

    switch (block.blockType) {
      case 'sub_categories':
        return SubCategoriesBlock(
          categoryId: categoryId,
          hideTitle: true,
          vendorId: vendorId,
          onEmpty: _onBlockEmpty,
          onCategoryTap: (id, name) {
            _advanceToNextStep({
              'parent_id': id,
              'parent_name': name,
              'sub_category_id': id,
              'sub_category_name': name,
            }, layoutTargetStep: _stepAfterCategorySelection(blocks, parentPosition: 1));
          },
        );
      case 'sub_category_products':
        return SubCategoryProductsBlock(
          categoryId: categoryId,
          categoryName: categoryName,
          hideTitle: true,
          parentId: parentId,
          parentName: _stepContext['parent_name'] as String?,
          stepContext: _stepContext,
          groupedProductsKey: _groupedProductsKey,
        );
      case 'sub_sub_categories':
        return SubSubCategoriesBlock(
          categoryId: categoryId,
          hideTitle: true,
          filterParentId: parentId,
          vendorId: vendorId,
          onEmpty: _onBlockEmpty,
          onCategoryTap: (id, name) {
            _advanceToNextStep({
              'parent_id': id,
              'parent_name': name,
            }, layoutTargetStep: _stepAfterCategorySelection(blocks, parentPosition: 2));
          },
        );
      case 'sub_sub_category_products':
        return SubSubCategoryProductsBlock(
          categoryId: categoryId,
          categoryName: categoryName,
          hideTitle: true,
          parentId: parentId,
          parentName: _stepContext['parent_name'] as String?,
          stepContext: _stepContext,
          groupedProductsKey: _groupedProductsKey,
        );
      case 'mixed_products':
        return MixedProductsBlock(
          categoryId: categoryId,
          settings: block.settings,
          vendorId: vendorId,
          hideTitle: true,
        );
      case 'vendors_list':
        return VendorsListBlock(
          categoryId: categoryId,
          settings: block.settings,
          hideTitle: true,
          onVendorTap: (id, name) {
            _advanceToNextStep({
              'vendor_id': id,
              'vendor_name': name,
            }, layoutTargetStep: _stepAfterVendorBlock(blocks));
          },
        );
      case 'location_pipeline':
        return LocationPipelineBlock(
          categoryId: categoryId,
          settings: block.settings,
          hideTitle: true,
        );
      default:
        return CategoryBlockEmptyWidget(messageText: _noContentMessage(context));
    }
  }
}
