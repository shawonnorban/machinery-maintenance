/**
 * What the generated Android project needs of us.
 *
 * - SHORT NATIVE PATHS: the C++ libraries build folders deeper than Windows'
 *   260 characters. With ANNOTECH_NATIVE_DIR set (a short folder, D:\b), each
 *   module's native build goes there instead; without it nothing changes.
 * - THE RELEASE KEY: read from the file ANNOTECH_SIGNING names (storeFile,
 *   storePassword, keyAlias, keyPassword) — never from the repository. Without
 *   it a release is signed with the debug key and can only be tested, never
 *   published; the build says so.
 * - PLAIN HTTP: the app connects straight to the server's IP and port (user,
 *   2026-11-25), which has no certificate; Android refuses that unless told.
 *   No backup either: the phone's id, its secret and the PIN hashes stay here.
 * - THE CAMERA'S SCANNER (phase 2): expo-camera finds ML Kit by class name,
 *   and a release build's R8 removed those classes — the scanner then read
 *   nothing while debug builds worked (annotech-rmg found this). Kept whole.
 */
const fs = require("fs");
const path = require("path");
const { AndroidConfig, withAndroidManifest, withProjectBuildGradle, withAppBuildGradle, withDangerousMod } = require("expo/config-plugins");

const KEEP = `
# with-native-build: ML Kit (the camera's scanner) is found by name; R8 must keep it whole.
-keep class com.google.mlkit.** { *; }
-keep class com.google.android.gms.internal.mlkit_** { *; }
-keep class com.google.android.gms.vision.** { *; }
-keep class com.google.firebase.components.** { *; }
-keep class * implements com.google.firebase.components.ComponentRegistrar { *; }
`;

const withKeepRules = (config) =>
  withDangerousMod(config, [
    "android",
    (c) => {
      const file = path.join(c.modRequest.platformProjectRoot, "app", "proguard-rules.pro");
      const now = fs.existsSync(file) ? fs.readFileSync(file, "utf8") : "";
      if (!now.includes("with-native-build: ML Kit")) fs.writeFileSync(file, `${now}
${KEEP}`);
      return c;
    },
  ]);

const NATIVE = `
// with-native-build: short native build folders (Windows' 260 characters).
def annotechNative = System.getenv('ANNOTECH_NATIVE_DIR')
if (annotechNative) {
  subprojects { p ->
    ['com.android.library', 'com.android.application'].each { id ->
      p.plugins.withId(id) {
        p.android.externalNativeBuild.cmake.buildStagingDirectory = new File(annotechNative, p.name == 'app' ? 'a' : p.name)
      }
    }
  }
}
`;

const SIGNING = `
// with-native-build: the release key, from outside the repository.
def annotechSigning = System.getenv('ANNOTECH_SIGNING')
if (annotechSigning && new File(annotechSigning).exists()) {
  def key = new Properties()
  new File(annotechSigning).withInputStream { key.load(it) }
  android.signingConfigs.create('annotech') {
    storeFile new File(key.storeFile)
    storePassword key.storePassword
    keyAlias key.keyAlias
    keyPassword key.keyPassword
  }
  android.buildTypes.release.signingConfig = android.signingConfigs.annotech
} else {
  logger.warn('Annotech ERP: no ANNOTECH_SIGNING - a release build is signed with the DEBUG key.')
}
`;

const once = (contents, block) => (contents.includes(block.trim().split("\n")[0]) ? contents : `${contents}\n${block}`);

const withManifest = (config) =>
  withAndroidManifest(config, (c) => {
    const app = AndroidConfig.Manifest.getMainApplicationOrThrow(c.modResults);
    app.$["android:usesCleartextTraffic"] = "true";
    app.$["android:allowBackup"] = "false";
    return c;
  });

module.exports = (config) =>
  withAppBuildGradle(
    withProjectBuildGradle(withKeepRules(withManifest(config)), (c) => {
      // Before Expo's and React Native's root plugins: they evaluate the modules, and then it is too late.
      const at = 'apply plugin: "expo-root-project"';
      if (!c.modResults.contents.includes("ANNOTECH_NATIVE_DIR")) c.modResults.contents = c.modResults.contents.replace(at, `${NATIVE.trim()}

${at}`);
      return c;
    }),
    (c) => {
      c.modResults.contents = once(c.modResults.contents, SIGNING);
      return c;
    },
  );
