# Nemu Android

This is the Capacitor Android wrapper for the NEMU Vue web app.

The Android display name is **Nemu**. The current package id is `com.mdl.nemu`; confirm it is the final unique Google Play application id before the first production upload.

## Local Android development

```bash
cd nemu/mobile
npm install
npm run sync
npm run open:android
```

`npm run sync` builds the Vue app from `../web`, copies it into the Android project, and synchronizes Capacitor.

Open `android/` in Android Studio to run on an emulator/device or produce a signed release bundle. A debug APK generated locally is intentionally ignored by Git.

Google Play Billing must only grant a plan after the NEMU API verifies a purchase token with the Google Play Developer API. Do not store service-account credentials, Play Console keys, or billing secrets in this directory.
