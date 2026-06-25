import 'package:flutter_sixvalley_ecommerce/features/category/domain/models/category_display_block_model.dart';
import 'package:flutter_sixvalley_ecommerce/features/category/helpers/category_api_cache.dart';

class CategoryStepResolution {
  final int? stepIndex;
  final List<int> dataBlockIndices;
  final bool hasNavigationData;

  const CategoryStepResolution({
    required this.stepIndex,
    required this.dataBlockIndices,
    required this.hasNavigationData,
  });

  bool get hasNoDisplayableContent => stepIndex == null;

  int get displayStepNumber {
    if (stepIndex == null || dataBlockIndices.isEmpty) {
      return 0;
    }
    final position = dataBlockIndices.indexOf(stepIndex!);
    return position >= 0 ? position + 1 : 1;
  }

  int get displayTotalSteps => dataBlockIndices.isEmpty ? 0 : dataBlockIndices.length;
}

class CategoryBlockStepResolver {
  CategoryBlockStepResolver({CategoryApiCache? cache})
      : _cache = cache ?? CategoryApiCache.instance;

  final CategoryApiCache _cache;
  final Map<String, bool> _blockDataCache = {};

  static const Set<String> navigationBlockTypes = {
    'sub_categories',
    'sub_sub_categories',
    'sub_category_products',
    'sub_sub_category_products',
  };

  static const Set<String> terminalBlockTypes = {
    'location_pipeline',
    'mixed_products',
    'vendors_list',
  };

  static bool isNavigationBlock(String blockType) => navigationBlockTypes.contains(blockType);

  static bool isTerminalBlock(String blockType) => terminalBlockTypes.contains(blockType);

  void clearBlockCache() {
    _blockDataCache.clear();
  }

  String _blockCacheKey(DisplayBlock block, int mainCategoryId, Map<String, dynamic> context) {
    return '${block.id}|$mainCategoryId|${context['parent_id'] ?? ''}|${context['vendor_id'] ?? ''}|${block.blockType ?? ''}';
  }

  Future<bool> blockHasData({
    required DisplayBlock block,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) async {
    final cacheKey = _blockCacheKey(block, mainCategoryId, context);
    if (_blockDataCache.containsKey(cacheKey)) {
      return _blockDataCache[cacheKey]!;
    }

    final blockType = block.blockType ?? '';
    bool hasData;

    if (terminalBlockTypes.contains(blockType)) {
      hasData = true;
    } else {
      hasData = await _resolveBlockHasData(
        blockType: blockType,
        mainCategoryId: mainCategoryId,
        context: context,
      );
    }

    _blockDataCache[cacheKey] = hasData;
    return hasData;
  }

  Future<bool> _resolveBlockHasData({
    required String blockType,
    required int mainCategoryId,
    required Map<String, dynamic> context,
  }) async {
    switch (blockType) {
      case 'sub_categories':
        return (await _cache.categoriesForParent(
          mainCategoryId,
          vendorId: context['vendor_id'] as int?,
        )).isNotEmpty;

      case 'sub_sub_categories':
        final parentId = context['parent_id'] as int?;
        if (parentId != null) {
          return (await _cache.categoriesForParent(parentId)).isNotEmpty;
        }

        // Check if any sub-category already has nested childes from the initial
        // API response (0 API calls needed).
        final subCategories = await _cache.categoriesForParent(mainCategoryId);
        if (subCategories.any((sub) => (sub.subCategories ?? []).isNotEmpty)) {
          return true;
        }

        // Nested data not available from initial fetch. Fetch children for all
        // sub-categories in a single parallel batch using Future.wait.
        // These results are cached so the widget reuses them with 0 extra calls.
        final parentIds = subCategories
            .where((s) => s.id != null)
            .map((s) => s.id!)
            .toList();
        if (parentIds.isEmpty) return false;

        final childrenByParent = await _cache.categoriesForParents(parentIds);
        return childrenByParent.values.any((list) => list.isNotEmpty);

      case 'sub_category_products':
        return _cache.hasGroupedProducts(
          categoryId: mainCategoryId,
          groupLevel: 'sub_category',
          parentId: context['parent_id'] as int?,
          parentName: context['parent_name'] as String?,
          vendorId: context['vendor_id'] as int?,
        );

      case 'sub_sub_category_products':
        return _cache.hasGroupedProducts(
          categoryId: mainCategoryId,
          groupLevel: 'sub_sub_category',
          parentId: context['parent_id'] as int?,
          parentName: context['parent_name'] as String?,
          vendorId: context['vendor_id'] as int?,
        );

      default:
        return false;
    }
  }

  Future<List<int>> getDataBlockIndices({
    required List<DisplayBlock> blocks,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) async {
    final indices = <int>[];

    for (int i = 0; i < blocks.length; i++) {
      if (await blockHasData(
        block: blocks[i],
        mainCategoryId: mainCategoryId,
        context: context,
      )) {
        indices.add(i);
      }
    }

    return indices;
  }

  Future<CategoryStepResolution> resolveInitialStep({
    required List<DisplayBlock> blocks,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) async {
    final dataBlockIndices = <int>[];
    var hasNavigationData = false;

    for (int i = 0; i < blocks.length; i++) {
      final block = blocks[i];
      final hasData = await blockHasData(
        block: block,
        mainCategoryId: mainCategoryId,
        context: context,
      );

      if (!hasData) {
        continue;
      }

      dataBlockIndices.add(i);

      if (isNavigationBlock(block.blockType ?? '')) {
        hasNavigationData = true;
      }
    }

    if (dataBlockIndices.isEmpty) {
      return CategoryStepResolution(
        stepIndex: null,
        dataBlockIndices: dataBlockIndices,
        hasNavigationData: false,
      );
    }

    return CategoryStepResolution(
      stepIndex: dataBlockIndices.first,
      dataBlockIndices: dataBlockIndices,
      hasNavigationData: hasNavigationData,
    );
  }

  Future<int?> findNextBlockWithData({
    required List<DisplayBlock> blocks,
    required int currentStep,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) async {
    for (int step = currentStep + 1; step < blocks.length; step++) {
      if (await blockHasData(
        block: blocks[step],
        mainCategoryId: mainCategoryId,
        context: context,
      )) {
        return step;
      }
    }

    return null;
  }

  Future<int?> findPreviousBlockWithData({
    required List<DisplayBlock> blocks,
    required int currentStep,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) async {
    for (int step = currentStep - 1; step >= 0; step--) {
      final stepContext = contextForStep(
        blocks: blocks,
        stepIndex: step,
        currentContext: context,
      );

      if (await blockHasData(
        block: blocks[step],
        mainCategoryId: mainCategoryId,
        context: stepContext,
      )) {
        return step;
      }
    }

    return null;
  }

  static Map<String, dynamic> contextForStep({
    required List<DisplayBlock> blocks,
    required int stepIndex,
    required Map<String, dynamic> currentContext,
  }) {
    final context = Map<String, dynamic>.from(currentContext);
    final blockType = blocks[stepIndex].blockType ?? '';

    if (blockType == 'vendors_list') {
      context.remove('vendor_id');
      context.remove('vendor_name');
    }

    if (blockType == 'sub_categories' || blockType == 'sub_category_products') {
      context.remove('parent_id');
      context.remove('parent_name');
      context.remove('sub_category_id');
      context.remove('sub_category_name');
    }

    if (blockType == 'sub_sub_categories') {
      context.remove('parent_id');
      context.remove('parent_name');

      if (context['sub_category_id'] != null) {
        context['parent_id'] = context['sub_category_id'];
        context['parent_name'] = context['sub_category_name'];
      }
    }

    return context;
  }
}

/// Backwards-compatible static helpers for tests or legacy callers.
class CategoryBlockStepHelper {
  static final CategoryBlockStepResolver _resolver = CategoryBlockStepResolver();

  static bool isNavigationBlock(String blockType) =>
      CategoryBlockStepResolver.isNavigationBlock(blockType);

  static bool isTerminalBlock(String blockType) =>
      CategoryBlockStepResolver.isTerminalBlock(blockType);

  static Future<CategoryStepResolution> resolveInitialStep({
    required List<DisplayBlock> blocks,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) {
    return _resolver.resolveInitialStep(
      blocks: blocks,
      mainCategoryId: mainCategoryId,
      context: context,
    );
  }

  static Future<int?> findNextBlockWithData({
    required List<DisplayBlock> blocks,
    required int currentStep,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) {
    return _resolver.findNextBlockWithData(
      blocks: blocks,
      currentStep: currentStep,
      mainCategoryId: mainCategoryId,
      context: context,
    );
  }

  static Future<int?> findPreviousBlockWithData({
    required List<DisplayBlock> blocks,
    required int currentStep,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) {
    return _resolver.findPreviousBlockWithData(
      blocks: blocks,
      currentStep: currentStep,
      mainCategoryId: mainCategoryId,
      context: context,
    );
  }

  static Future<List<int>> getDataBlockIndices({
    required List<DisplayBlock> blocks,
    required int mainCategoryId,
    Map<String, dynamic> context = const {},
  }) {
    return _resolver.getDataBlockIndices(
      blocks: blocks,
      mainCategoryId: mainCategoryId,
      context: context,
    );
  }

  static Map<String, dynamic> contextForStep({
    required List<DisplayBlock> blocks,
    required int stepIndex,
    required Map<String, dynamic> currentContext,
  }) {
    return CategoryBlockStepResolver.contextForStep(
      blocks: blocks,
      stepIndex: stepIndex,
      currentContext: currentContext,
    );
  }
}
