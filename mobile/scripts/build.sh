#!/usr/bin/env bash
# The native build, on Windows (Git Bash):
#
#   bash scripts/build.sh debug      the emulator (x86_64) -> app-debug.apk
#   bash scripts/build.sh release    phones (ARM), signed -> app-release.apk
#
# Built from a COPY in a short folder (D:/m/a, its C++ in D:/b), never in place: the C++ libraries
# make paths longer than Windows' 260 characters from where the repository is,
# and the few gigabytes a build leaves stay off C:.
set -euo pipefail

KIND="${1:-debug}"
REPO="$(cd "$(dirname "$0")/../.." && pwd -W)"
WORK="${ANNOTECH_WORK:-D:/m}"
export JAVA_HOME="${JAVA_HOME:-C:/Program Files/Android/Android Studio/jbr}"
export ANDROID_HOME="${ANDROID_HOME:-$(cygpath -m "$LOCALAPPDATA")/Android/Sdk}"
export GRADLE_USER_HOME="${GRADLE_USER_HOME:-$WORK/g}"
export ANNOTECH_NATIVE_DIR="${ANNOTECH_NATIVE_DIR:-D:/b}"
SIGNING="$(cygpath -m "$USERPROFILE")/.annotech-erp/signing.properties"
[ -f "$SIGNING" ] && export ANNOTECH_SIGNING="${ANNOTECH_SIGNING:-$SIGNING}"

mirror() { # source, target, robocopy's own options
  local from="$1" to="$2"; shift 2
  set +e
  MSYS_NO_PATHCONV=1 robocopy "$(cygpath -w "$from")" "$(cygpath -w "$to")" /MIR /NFL /NDL /NJH /NJS /NP "$@" >/dev/null
  local code=$?
  set -e
  # Robocopy's 0-7 are kinds of success.
  [ "$code" -lt 8 ] || { echo "copy failed ($code): $from"; exit 1; }
}

# /XD leaves the copy's own node_modules and android alone.
mirror "$REPO/mobile" "$WORK/a" /XD node_modules android .expo dist
mirror "$REPO/frontend/messages" "$WORK/frontend/messages"
mirror "$REPO/frontend/src/lib" "$WORK/frontend/src/lib"

cd "$WORK/a"
LOCK="$(sha256sum package-lock.json | cut -d' ' -f1)"
if [ ! -d node_modules ] || [ "$(cat node_modules/.lock-sum 2>/dev/null)" != "$LOCK" ]; then
  npm ci --no-audit --no-fund
  echo "$LOCK" > node_modules/.lock-sum
fi

npx expo prebuild --platform android --no-install
echo "sdk.dir=$ANDROID_HOME" > android/local.properties

cd android
OPTS=(--max-workers=2 "-Dorg.gradle.jvmargs=-Xmx2g -XX:MaxMetaspaceSize=768m" --console=plain)
if [ "$KIND" = "release" ]; then
  ./gradlew :app:createBundleReleaseJsAndAssets --rerun assembleRelease "-PreactNativeArchitectures=${ARCH:-arm64-v8a,armeabi-v7a}" "${OPTS[@]}"
  ls -la app/build/outputs/apk/release/*.apk
else
  ./gradlew assembleDebug "-PreactNativeArchitectures=${ARCH:-x86_64}" "${OPTS[@]}"
  ls -la app/build/outputs/apk/debug/*.apk
fi
