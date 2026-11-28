/** The navy-to-blue band (the app bar's, the welcome panel's, Home's header card), drawn behind what sits on it. */
import { View } from "react-native";
import Svg, { Defs, LinearGradient, Rect, Stop } from "react-native-svg";
import { useColors } from "../theme/theme";

export function Band({ children, style, radiusBottom = 0 }) {
  const colors = useColors();

  return (
    <View style={[{ overflow: "hidden", borderBottomLeftRadius: radiusBottom, borderBottomRightRadius: radiusBottom }, style]}>
      <Svg style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0 }} width="100%" height="100%" preserveAspectRatio="none">
        <Defs>
          <LinearGradient id="band" x1="0" y1="0" x2="1" y2="1">
            <Stop offset="0" stopColor={colors.barFrom} />
            <Stop offset="1" stopColor={colors.barTo} />
          </LinearGradient>
        </Defs>
        <Rect x="0" y="0" width="100%" height="100%" fill="url(#band)" />
      </Svg>
      {children}
    </View>
  );
}
