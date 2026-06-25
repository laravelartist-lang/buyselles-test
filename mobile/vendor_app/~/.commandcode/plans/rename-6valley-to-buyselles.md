# Rename 6Valley to Buyselles in Vendor App

## Changes Needed (4 source files)

### 1. `lib/utill/app_constants.dart` (line 8)
```
- static const String companyName = '6Valley';
+ static const String companyName = 'Buyselles';
```

### 2. `android/app/src/main/AndroidManifest.xml` (line 31)
```
- android:label="6Valley Vendor"
+ android:label="Buyselles Vendor"
```

### 3. `ios/Runner/Info.plist` (lines 10, 18)
```
- <string>6valley Seller</string>    → <string>Buyselles Vendor</string>
- <string>6Valley Seller</string>    → <string>Buyselles Vendor</string>
```

### 4. `ios/Runner.xcodeproj/project.pbxproj` (3 occurrences, 1 replace-all)
```
- INFOPLIST_KEY_CFBundleDisplayName = "6Valley Seller";
+ INFOPLIST_KEY_CFBundleDisplayName = "Buyselles Vendor";
```

## Skipped
- `build/` directory — auto-generated from source, will regenerate on next build.
- `lib/helper/notification_helper.dart` — commented-out code only.

## Verification
- Run `grep -r "6valley\|6Valley" --exclude-dir=build lib/ android/ ios/` — should return zero results.
