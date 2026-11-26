# Annotech ERP — Android app

React Native with Expo (SDK 58), JavaScript. The plan, its phases and what each
role sees: `AnnotechERP/37_Mobile_App/01_Mobile_App.md`. The server decides every
permission; the app only draws what the API allows.

## Run it on this computer

The backend runs in Docker (API on port 8010).

```bash
cd mobile
npm install            # once
cp .env.example .env   # once: where the app finds the API
npx expo start         # the JavaScript; the app on the emulator reads it
```

The server's address is typed in the app (Settings, or the first screen) and
kept on the phone; `.env` only fills it in for development. The emulator
reaches this computer at `10.0.2.2`, a phone on the same Wi-Fi at the
computer's own IP. Plain HTTP, so on the factory's own network only.
A native change (a package with native code, `app.json`, a plugin) needs a new
build; everything else is read from `expo start`.

## Build

From Git Bash:

```bash
bash scripts/build.sh debug                                                  # emulator: D:/m/a/android/app/build/outputs/apk/debug
bash scripts/build.sh release                                                # phones, signed; the server's IP and port are typed in the app
```

The script mirrors `mobile/` and the shared web files to `D:/m` and builds
THERE, never in place: the C++ libraries make paths longer than Windows' 260
characters from this folder, and a build leaves gigabytes. `android/` is
generated (`expo prebuild`) and is never edited or committed.

| Variable | Default |
|---|---|
| `ANNOTECH_WORK` | `D:/m`: the short folder (the app in `D:/m/a`). Keep it short: one letter too many breaks the release |
| `ANNOTECH_NATIVE_DIR` | `D:/b`: the C++ build, as short as can be |
| `JAVA_HOME` | Android Studio's Java |
| `ANDROID_HOME` | the Android SDK of this user |
| `GRADLE_USER_HOME` | `D:/m/g`: Gradle's downloads |
| `ANNOTECH_SIGNING` | `%USERPROFILE%/.annotech-erp/signing.properties` |
| `ARCH` | `x86_64` (debug), `arm64-v8a,armeabi-v7a` (release) |

- The first build downloads Gradle, the NDK and the libraries (about 15 GB
  across both drives) and takes 15-30 minutes.
- A build that was stopped leaves Gradle running and its lock held: end the
  `java.exe` processes whose command line says gradle, then build again.
- The emulator is x86_64, phones are ARM: an APK of one crashes on the other.
  `ARCH=x86_64,arm64-v8a,armeabi-v7a` builds one release for both.

## The emulator

`QC_Phone` (1080 x 2400, Android 14; the app is for phones) is on D:, beside
its own SDK, so the ones Android Studio made are untouched (`QC_Tablet` too):

```bash
export ANDROID_SDK_ROOT=D:/asdk ANDROID_HOME=D:/asdk ANDROID_AVD_HOME=D:/avd
/d/asdk/emulator/emulator.exe -avd QC_Phone -no-snapshot -no-audio &
/d/asdk/platform-tools/adb.exe install -r D:/m/a/android/app/build/outputs/apk/debug/app-debug.apk
/d/asdk/platform-tools/adb.exe reverse tcp:8081 tcp:8081
```

With Docker, the emulator and Metro together this computer (16 GB) is short of
memory and the API answers in 5-13 seconds: `.env` raises the request deadline
for development (`EXPO_PUBLIC_DEV_DEADLINE_MS`); a release is always 8 seconds.

## The release key

Outside the repository, in `C:\Users\shawo\.annotech-erp\` (`annotech-erp.jks`
and `signing.properties`). **Keep a copy somewhere safe**: without it, an
installed app can never be updated. A release built without `ANNOTECH_SIGNING`
is signed with the debug key and warns; it is for testing only.

## Where things are

| Path | What |
|---|---|
| `src/app/` | Screens: every file is one (Expo Router) |
| `src/components/ui.js` | The component kit; build screens from it only |
| `src/theme/tokens.js` | Colours, sizes, type: the only place they are written |
| `src/lib/api.js` | Requests, each with a deadline; status 0 = no answer, never a refusal |
| `src/lib/taps.js` | The taps: kept in SQLite first, sent behind as the phone; refused ones kept |
| `src/lib/tally.js` | What the tally shows, kept on the phone; the running count; undo |
| `src/lib/server.js` | The server's IP and port |
| `src/lib/session.js` | Who is signed in: PIN or email, the lock, offline sign-in, what waits for the server |
| `src/lib/qcs.js` | The QCs this phone knows, and checking a PIN on it |
| `src/lib/phone.js` | This phone's id and secret |
| `src/lib/i18n.js` | Words, read from `frontend/messages`; add a namespace there |
| `plugins/with-native-build.js` | Short native paths, the release key |
| `scripts/make-icons.mjs` | `npm run icons`: the icons from one mark |

Words come from `lang/{en,bn}/*.php` through `php scripts/extract-i18n.php`
(the app's own are `lang/*/mobile.php`); times are shown with
`frontend/src/lib/clock.js`. Never copy either.

## Check before handing over

```bash
npx expo install --check
npx expo export --platform android --output-dir %TEMP%/check
```
