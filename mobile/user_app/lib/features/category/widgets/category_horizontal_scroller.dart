import 'package:flutter/material.dart';

/// Horizontal scroller safe to embed inside a parent vertical scroll view.
class CategoryHorizontalScroller extends StatelessWidget {
  final Widget child;

  const CategoryHorizontalScroller({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      primary: false,
      physics: const ClampingScrollPhysics(),
      child: child,
    );
  }
}
