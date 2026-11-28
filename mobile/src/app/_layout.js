/** The root: fonts, the language, light or dark, who is signed in, and the stack of screens under the lock. */
import { useEffect, useState } from "react";
import { View } from "react-native";
import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";
import * as SplashScreen from "expo-splash-screen";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { useFonts } from "expo-font";
import { LockScreen } from "../components/lock";
import { UpdateGate } from "../components/update-gate";
import { currentLocale, onLocaleChange } from "../lib/i18n";
import { loadAppearance } from "../lib/appearance";
import { SessionProvider, useSession } from "../lib/session";
import { startTapSync } from "../lib/taps";
import { useColors } from "../theme/theme";
import { fonts } from "../theme/tokens";

SplashScreen.preventAutoHideAsync().catch(() => {});

// The web's own two files (frontend/src/app/fonts), each weight a name of its own (theme/tokens.js).
const SEGOE = require("../../../frontend/src/app/fonts/Segoe UI.ttf");
const ANEK = require("../../../frontend/src/app/fonts/AnekBangla-Regular.ttf");
const FACES = { [fonts.regular]: SEGOE, [fonts.medium]: SEGOE, [fonts.semibold]: SEGOE, [fonts.bold]: SEGOE, [fonts.bengali]: ANEK, [fonts.bengaliSemibold]: ANEK };

export default function RootLayout() {
  const [loaded, failed] = useFonts(FACES);

  useEffect(loadAppearance, []);
  // Taps waiting on the phone go out whoever is signed in, or nobody.
  useEffect(startTapSync, []);
  // A font that will not load must not leave a phone on the splash screen.
  const ready = loaded || Boolean(failed);
  useEffect(() => {
    if (ready) SplashScreen.hideAsync().catch(() => {});
  }, [ready]);

  if (!ready) return null;

  return (
    <SafeAreaProvider>
      <StatusBar style="auto" />
      <SessionProvider>
        <Screens />
      </SessionProvider>
    </SafeAreaProvider>
  );
}

function Screens() {
  const colors = useColors();
  const { session, locked, touch } = useSession();
  const [locale, setLocale] = useState(currentLocale());
  const shut = session?.kind === "PIN" && locked;

  useEffect(() => onLocaleChange(setLocale), []);

  return (
    // Every touch counts as use: the lock comes only when the phone is left alone.
    <View style={{ flex: 1, backgroundColor: colors.background }} onTouchStart={touch}>
      {/* Under the lock nothing is read out or pressed. */}
      <View style={{ flex: 1 }} pointerEvents={shut ? "none" : "auto"} importantForAccessibility={shut ? "no-hide-descendants" : "auto"}>
        {/* Too old for the server: what is on the phone is sent, then the new version is asked for. */}
        <UpdateGate>
          <Stack key={locale} screenOptions={{ headerShown: false, animation: "fade", contentStyle: { backgroundColor: colors.background } }} />
        </UpdateGate>
      </View>
      {shut ? <LockScreen /> : null}
    </View>
  );
}
