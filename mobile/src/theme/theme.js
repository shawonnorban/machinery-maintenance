/** The palette for the light or dark the device (or the person) chose. */
import { useColorScheme } from "react-native";
import { palettes } from "./tokens";

export function useColors() {
  return palettes[useColorScheme() === "dark" ? "dark" : "light"];
}

export function shadow(colors, level = "sm") {
  const [offset, blur, opacity, elevation] = { sm: [1, 3, 0.08, 2], md: [4, 12, 0.1, 4] }[level];

  return { shadowColor: colors.shadow, shadowOffset: { width: 0, height: offset }, shadowRadius: blur, shadowOpacity: opacity, elevation };
}
