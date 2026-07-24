import 'dart:convert';

/// Parses API `order_note` values for display (customer notes vs system failure text).
class OrderNoteDisplay {
  const OrderNoteDisplay({this.customerNote, this.failureMessage});

  final String? customerNote;
  final String? failureMessage;

  bool get isEmpty =>
      (customerNote == null || customerNote!.trim().isEmpty) &&
      (failureMessage == null || failureMessage!.trim().isEmpty);
}

class OrderNoteHelper {
  OrderNoteHelper._();

  static const List<String> _failurePrefixes = [
    'Supplier fulfillment failed:',
    'Direct top-up fulfillment failed:',
    'Direct top-up failed:',
  ];

  static const List<String> _messageKeys = [
    'message',
    'error',
    'error_message',
    'description',
    'detail',
    'reason',
    'msg',
  ];

  /// Coerces JSON `order_note` (string, map, or list) into a plain string.
  static String? orderNoteFromJson(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is String) {
      return toPlainText(value);
    }
    if (value is Map) {
      return _mapToPlainText(value);
    }
    if (value is List) {
      return _humanizeDynamic(value);
    }
    return toPlainText(value.toString());
  }

  /// Best-effort conversion of any stored note / API error shape to readable text.
  static String toPlainText(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return trimmed;
    }

    final embeddedMessage = _extractEmbeddedApiMessage(trimmed);
    if (embeddedMessage != null && embeddedMessage.isNotEmpty) {
      return embeddedMessage;
    }

    final humanized = _humanize(trimmed);
    if (humanized != null &&
        humanized.isNotEmpty &&
        !_stillShowsRawJson(humanized)) {
      return humanized;
    }

    final loose = _parseLooseBraceObject(trimmed);
    if (loose != null && loose.isNotEmpty) {
      return loose;
    }

    if (humanized != null &&
        humanized.isNotEmpty &&
        !_stillShowsRawJson(humanized)) {
      return humanized;
    }

    return trimmed;
  }

  static bool _stillShowsRawJson(String value) {
    final t = value.trim();
    if (t.contains('"message"') && t.contains('{')) {
      return true;
    }
    return _isMapLikeLiteral(t);
  }

  static OrderNoteDisplay parse(String? raw, {required bool isFailedOrder}) {
    if (raw == null || raw.trim().isEmpty) {
      return const OrderNoteDisplay();
    }

    final trimmed = raw.trim();

    for (final prefix in _failurePrefixes) {
      if (trimmed.startsWith(prefix)) {
        final tail = trimmed.substring(prefix.length).trim();
        return OrderNoteDisplay(
          failureMessage: toPlainText(tail),
        );
      }
    }

    if (_looksLikeJson(trimmed) ||
        _isMapLikeLiteral(trimmed) ||
        (isFailedOrder && _containsJsonObject(trimmed))) {
      final human = toPlainText(trimmed);
      if (human.isNotEmpty) {
        if (isFailedOrder) {
          return OrderNoteDisplay(failureMessage: human);
        }
        return OrderNoteDisplay(customerNote: human);
      }
    }

    if (isFailedOrder) {
      return OrderNoteDisplay(failureMessage: toPlainText(trimmed));
    }

    return OrderNoteDisplay(customerNote: toPlainText(trimmed));
  }

  static bool _isMapLikeLiteral(String value) {
    final t = value.trim();
    return t.startsWith('{') && t.endsWith('}') && t.contains(':');
  }

  static String? _mapToPlainText(Map<dynamic, dynamic> map) {
    final normalized = <String, dynamic>{};
    map.forEach((key, value) {
      normalized[key.toString()] = value;
    });

    final fromMap = _humanizeMap(normalized);
    if (fromMap != null && fromMap.trim().isNotEmpty) {
      return fromMap.trim();
    }

    return _parseLooseBraceObject(map.toString());
  }

  static bool _looksLikeJson(String value) {
    final t = value.trim();
    return (t.startsWith('{') && t.endsWith('}')) ||
        (t.startsWith('[') && t.endsWith(']'));
  }

  static bool _containsJsonObject(String value) {
    final start = value.indexOf('{');
    final end = value.lastIndexOf('}');
    return start != -1 && end > start;
  }

  static String? _humanize(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return null;
    }

    if (_looksLikeJson(trimmed)) {
      try {
        return _humanizeDynamic(jsonDecode(trimmed));
      } catch (_) {
        return _parseLooseBraceObject(trimmed) ?? trimmed;
      }
    }

    if (_containsJsonObject(trimmed) || trimmed.contains('"message"')) {
      final embedded = _extractEmbeddedApiMessage(trimmed);
      if (embedded != null && embedded.isNotEmpty) {
        return embedded;
      }

      final braceIndex = trimmed.indexOf('{');
      if (braceIndex != -1) {
        final jsonPart = trimmed.substring(braceIndex);
        final fromPart = _extractEmbeddedApiMessage(jsonPart);
        if (fromPart != null && fromPart.isNotEmpty) {
          return fromPart;
        }
      }

      final jsonPart = trimmed.contains('}')
          ? trimmed.substring(
              trimmed.indexOf('{'),
              trimmed.lastIndexOf('}') + 1,
            )
          : trimmed.substring(trimmed.indexOf('{'));
      try {
        final human = _humanizeDynamic(jsonDecode(jsonPart));
        if (human != null && human.isNotEmpty) {
          return human;
        }
      } catch (_) {
        final loose = _parseLooseBraceObject(jsonPart);
        if (loose != null && loose.isNotEmpty) {
          return loose;
        }
        final partial = _extractEmbeddedApiMessage(jsonPart);
        if (partial != null && partial.isNotEmpty) {
          return partial;
        }
      }
    }

    if (_isMapLikeLiteral(trimmed)) {
      return _parseLooseBraceObject(trimmed) ?? trimmed;
    }

    return trimmed;
  }

  /// Parses `{message: text}` / `{message : text}` (invalid JSON, Dart [Map.toString]).
  static String? _parseLooseBraceObject(String raw) {
    final t = raw.trim();
    if (!_isMapLikeLiteral(t)) {
      return null;
    }

    final inner = t.substring(1, t.length - 1).trim();
    if (inner.isEmpty) {
      return null;
    }

    for (final key in _messageKeys) {
      final match = RegExp(
        '(?:^|,)\\s*${RegExp.escape(key)}\\s*:\\s*(.+)\$',
        caseSensitive: false,
        dotAll: true,
      ).firstMatch(inner);
      if (match != null) {
        return _stripOptionalQuotes(match.group(1)!.trim());
      }
    }

    final generic = RegExp(
      '^\\s*["\']?[\\w.-]+["\']?\\s*:\\s*(.+)\\s*\$',
      dotAll: true,
    ).firstMatch(inner);
    if (generic != null) {
      return _stripOptionalQuotes(generic.group(1)!.trim());
    }

    return null;
  }

  static String _stripOptionalQuotes(String value) {
    if (value.length >= 2) {
      if ((value.startsWith('"') && value.endsWith('"')) ||
          (value.startsWith("'") && value.endsWith("'"))) {
        return value.substring(1, value.length - 1).trim();
      }
    }
    return value;
  }

  /// Extracts `"message":"..."` even when JSON is truncated (live order #100013 shape).
  static String? _extractEmbeddedApiMessage(String raw) {
    final closed = RegExp(
      r'"message"\s*:\s*"((?:\\.|[^"\\])*)"',
      dotAll: true,
    ).firstMatch(raw);
    if (closed != null) {
      return _cleanTruncatedSuffix(_unescapeJsonString(closed.group(1)!));
    }

    final open = RegExp(r'"message"\s*:\s*"(.+)', dotAll: true).firstMatch(raw);
    if (open != null) {
      return _cleanTruncatedSuffix(open.group(1)!);
    }

    return null;
  }

  static String _unescapeJsonString(String value) {
    return value
        .replaceAll(r'\"', '"')
        .replaceAll(r'\\', '\\')
        .replaceAll(r'\n', '\n')
        .replaceAll(r'\r', '\r')
        .replaceAll(r'\t', '\t');
  }

  static String _cleanTruncatedSuffix(String value) {
    return value
        .replaceAll(RegExp(r'\s*\(truncated\.\.\.\)\s*$'), '')
        .replaceAll(RegExp(r'"\s*$'), '')
        .trim();
  }

  static String? _humanizeDynamic(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is String) {
      final t = value.trim();
      return t.isEmpty ? null : _humanize(t) ?? t;
    }
    if (value is num || value is bool) {
      return value.toString();
    }
    if (value is List) {
      final parts = value
          .map(_humanizeDynamic)
          .whereType<String>()
          .where((s) => s.trim().isNotEmpty)
          .toList();
      if (parts.isEmpty) {
        return null;
      }
      return parts.join('\n');
    }
    if (value is Map) {
      return _humanizeMap(value.cast<String, dynamic>());
    }
    return value.toString();
  }

  static String? _humanizeMap(Map<String, dynamic> map) {
    for (final key in _messageKeys) {
      if (map.containsKey(key) && map[key] != null) {
        final text = _humanizeDynamic(map[key]);
        if (text != null && text.trim().isNotEmpty) {
          return text;
        }
      }
    }

    final errors = map['errors'];
    if (errors != null) {
      final text = _humanizeValidationErrors(errors);
      if (text != null && text.trim().isNotEmpty) {
        return text;
      }
    }

    final data = map['data'];
    if (data is Map) {
      final text = _humanizeMap(data.cast<String, dynamic>());
      if (text != null && text.trim().isNotEmpty) {
        return text;
      }
    }

    final lines = <String>[];
    map.forEach((key, val) {
      if (val is String && val.trim().isNotEmpty) {
        lines.add(val.trim());
      } else if (val is num || val is bool) {
        lines.add('$key: $val');
      }
    });
    if (lines.isNotEmpty) {
      return lines.join('\n');
    }

    return null;
  }

  static String? _humanizeValidationErrors(dynamic errors) {
    if (errors is Map) {
      final lines = <String>[];
      errors.forEach((_, value) {
        final text = _humanizeDynamic(value);
        if (text != null && text.trim().isNotEmpty) {
          lines.add(text);
        }
      });
      if (lines.isNotEmpty) {
        return lines.join('\n');
      }
    }
    return _humanizeDynamic(errors);
  }
}
