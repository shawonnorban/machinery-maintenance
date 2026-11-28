/**
 * Pieces Home and the other tabs share: the network dot, the greeting at the
 * top, the QC's lines (one press opens its tally), and the section choice.
 */
import { Pressable, View } from "react-native";
import { router } from "expo-router";
import { ChevronRight, Wifi, WifiOff } from "lucide-react-native";
import { Alert, Badge, Card, Segmented, Text } from "./ui";
import { q } from "./manage";
import { t } from "../lib/i18n";
import { chooseSection } from "../lib/section";
import { useSession } from "../lib/session";
import { radius, space } from "../theme/tokens";
import { useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);

export const SECTION_TONE = { CUTTING: "info", SEWING: "brand", QUALITY: "success" };

/** Online or not; offline it says so in words. */
export function Net() {
  const colors = useColors();
  const { online } = useSession();
  const Icon = online ? Wifi : WifiOff;

  return (
    <View accessibilityLabel={m(online ? "home.online" : "home.offline")} style={{ flexDirection: "row", alignItems: "center", gap: space[1.5], paddingHorizontal: space[3], height: 40, borderRadius: radius.full, backgroundColor: colors.surface }}>
      <Icon size={20} color={online ? colors.success : colors.danger} />
      {online ? null : (
        <Text variant="small" tone="danger">
          {m("home.offline")}
        </Text>
      )}
    </View>
  );
}

/** Good morning, afternoon or evening, by the phone's clock. */
export function greeting(name = "") {
  const hour = new Date().getHours();
  const part = hour < 12 ? "morning" : hour < 17 ? "afternoon" : "evening";

  return m(`home.greet_${part}`, { name: name.split(/\s+/)[0] ?? "" });
}

/** Each line given to the QC: one press opens its tally. */
export function Lines({ lines }) {
  const colors = useColors();

  return (
    <Card>
      <Text variant="sectionTitle">{m("home.lines")}</Text>
      {lines.length === 0 ? <Alert tone="warning">{m("home.no_lines")}</Alert> : null}
      {lines.map((l) => (
        <Pressable
          key={l.section + l.line_id}
          accessibilityRole="button"
          accessibilityLabel={`${m("tally.open")} ${l.label}`}
          onPress={() => router.push({ pathname: "/tally", params: { line: `${l.section}:${l.line_id}` } })}
          style={({ pressed }) => ({ minHeight: 64, flexDirection: "row", alignItems: "center", gap: space[3], paddingHorizontal: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: colors.brand, backgroundColor: pressed ? colors.brandSubtle : colors.surface })}
        >
          <Badge tone={SECTION_TONE[l.section]}>{q(`section.${l.section}`)}</Badge>
          <View style={{ flex: 1 }}>
            <Text variant="label">{l.label}</Text>
            <Text variant="small" tone="brand">
              {m("tally.open")}
            </Text>
          </View>
          <ChevronRight size={22} color={colors.brand} />
        </Pressable>
      ))}
    </Card>
  );
}

/**
 * Which section Home and Production show — only for somebody who sees more
 * than one. A section manager or QC sees their own alone, with no choice.
 */
export function SectionPicker({ access, value }) {
  if (access.length < 2) return null;

  return <Segmented value={value} onChange={chooseSection} options={access.map((a) => ({ value: a.section, label: q(`section.${a.section}`) }))} />;
}
