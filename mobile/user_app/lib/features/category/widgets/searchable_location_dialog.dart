import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_sixvalley_ecommerce/utill/custom_themes.dart';
import 'package:flutter_sixvalley_ecommerce/utill/dimensions.dart';

class SearchableLocationDialog<T> extends StatefulWidget {
  final String title;
  final List<T> items;
  final String Function(T) itemLabel;
  final Function(T?) onSelect;
  final Function(String)? onSearch;

  /// True while the parent controller is fetching data from the server.
  final bool isLoading;

  const SearchableLocationDialog({
    super.key,
    required this.title,
    required this.items,
    required this.itemLabel,
    required this.onSelect,
    this.onSearch,
    this.isLoading = false,
  });

  @override
  State<SearchableLocationDialog<T>> createState() => _SearchableLocationDialogState<T>();
}

class _SearchableLocationDialogState<T> extends State<SearchableLocationDialog<T>> {
  late List<T> _filteredItems;
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;

  /// True from the moment the user starts typing until the server responds
  /// with fresh results (or the query is cleared).  Drives the "Searching…"
  /// indicator so users are never shown "No results found" while the network
  /// call is still in flight.
  bool _isDebouncing = false;

  @override
  void initState() {
    super.initState();
    _filteredItems = widget.items;

    // On every open, ask the server for a fresh full list so that any
    // previously search-filtered state is discarded.
    if (widget.onSearch != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) widget.onSearch!('');
      });
    }
  }

  @override
  void didUpdateWidget(SearchableLocationDialog<T> oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.items != oldWidget.items) {
      // Server responded — no longer debouncing.
      if (!(_debounce?.isActive ?? false)) {
        _isDebouncing = false;
      }
      _updateLocalFilter(_searchController.text);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  /// Updates only the locally-filtered list without touching the debounce /
  /// server-search state.  Called from [didUpdateWidget] so that arriving
  /// server results re-apply the current search text.
  void _updateLocalFilter(String query) {
    setState(() {
      _filteredItems = query.isEmpty
          ? widget.items
          : widget.items
              .where((item) =>
                  widget.itemLabel(item).toLowerCase().contains(query.toLowerCase()))
              .toList();
    });
  }

  /// Called on every keystroke.  Updates local filter immediately and
  /// schedules a debounced server search for non-empty queries.
  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();

    if (widget.onSearch != null) {
      if (query.isEmpty) {
        // Immediately reload the full server list — no debounce for clear.
        setState(() => _isDebouncing = false);
        widget.onSearch!('');
      } else {
        // Mark as debouncing so the UI shows "Searching…" rather than
        // "No results found" during the 500 ms wait + API round-trip.
        setState(() => _isDebouncing = true);
        _debounce = Timer(const Duration(milliseconds: 500), () {
          if (mounted) widget.onSearch!(query);
          // _isDebouncing stays true until didUpdateWidget clears it when
          // the server results arrive.
        });
      }
    }

    _updateLocalFilter(query);
  }

  @override
  Widget build(BuildContext context) {
    final bool showSpinner =
        (_isDebouncing || widget.isLoading) && _filteredItems.isEmpty;
    final bool showSearching = _isDebouncing && _filteredItems.isEmpty;
    final bool showEmpty =
        _filteredItems.isEmpty && !widget.isLoading && !_isDebouncing;

    return Dialog(
      shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault)),
      child: Container(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        height: MediaQuery.of(context).size.height * 0.6,
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(widget.title,
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall)),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
              ),
              onChanged: _onSearchChanged,
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Expanded(
              child: showSpinner
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const CircularProgressIndicator(),
                          if (showSearching) ...[
                            const SizedBox(height: Dimensions.paddingSizeSmall),
                            Text('Searching…', style: textRegular),
                          ],
                        ],
                      ),
                    )
                  : showEmpty
                      ? Center(child: Text('No results found', style: textRegular))
                      : ListView.builder(
                          itemCount: _filteredItems.length +
                              (widget.title == 'Select Country' ? 1 : 0),
                          itemBuilder: (context, index) {
                            if (widget.title == 'Select Country' && index == 0) {
                              return ListTile(
                                title: const Text('Global'),
                                onTap: () {
                                  widget.onSelect(null);
                                  Navigator.pop(context);
                                },
                              );
                            }

                            final actualIndex =
                                widget.title == 'Select Country' ? index - 1 : index;
                            final item = _filteredItems[actualIndex];
                            return ListTile(
                              title: Text(widget.itemLabel(item)),
                              onTap: () {
                                widget.onSelect(item);
                                Navigator.pop(context);
                              },
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
