# Rename 6Valley → Buyselles in Vendor App

## What changes

Three source files (build artifacts are auto-generated):

### 1. `lib/utill/app_constants.dart` line 8
- `static const String companyName = '6Valley';` → `static const String companyName = 'Buyselles';`

### 2. `android/app/src/main/AndroidManifest.xml` line 31
- `android:label="6Valley Vendor"` → `android:label="Buyselles Vendor"`

### 3. `ios/Runner/Info.plist` lines 10 and 18
- `CFBundleDisplayName` → `Buyselles Vendor`
- `CFBundleName` → `Buyselles Vendor`

## Verification
```bash
grep -r "6valley\|6Valley" --exclude-dir=build --exclude-dir=.commandcode --exclude-dir=.agents lib/ android/ ios/
```
Should return zero results.
