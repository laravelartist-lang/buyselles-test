import 'package:flutter/material.dart';

/// Defers mounting heavy blocks so the first blocks render quickly.
/// All section headers still exist in the parent [ListView] scroll extent.
class CategoryDeferredBlockContent extends StatefulWidget {
  final int index;
  final Widget child;

  const CategoryDeferredBlockContent({
    super.key,
    required this.index,
    required this.child,
  });

  @override
  State<CategoryDeferredBlockContent> createState() => _CategoryDeferredBlockContentState();
}

class _CategoryDeferredBlockContentState extends State<CategoryDeferredBlockContent> {
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    if (widget.index < 4) {
      _ready = true;
      return;
    }

    Future<void>.delayed(Duration(milliseconds: 80 * (widget.index - 3)), () {
      if (mounted) {
        setState(() => _ready = true);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) {
      return const SizedBox(
        height: 100,
        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
      );
    }

    return widget.child;
  }
}
