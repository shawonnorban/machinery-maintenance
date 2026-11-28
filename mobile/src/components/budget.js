/**
 * Pre-Budget's words, colours and figures on the phone (AnnotechERP 37,
 * phase 5), and a budget's state as the same badge on every page (user,
 * 2026-11-27: "never inconsistent status colours between pages").
 */
import { View } from "react-native";
import { Award, CircleCheck, Clock, FilePen, RefreshCw, RotateCcw, X } from "lucide-react-native";
import { Text } from "./ui";
import { t } from "../lib/i18n";
import { fonts } from "../theme/tokens";
import { useBudgetStates } from "../theme/theme";

export const pt = (key, params) => t("prebudget", key, params);
export const money = (n) => (n === null || n === undefined ? "—" : Number(n).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
export const qty = (n) => (n === null || n === undefined ? "—" : Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 }));

/** Where a budget waits: "Waiting for CFO (level 2 of 3)". */
export const waitingAt = (b) => (b.status === "PENDING" && b.current ? pt("final.waiting_at", { step: b.current, n: b.step, of: b.steps }) : null);

/** A budget's states, in the order the list's tabs show them (the server's PbBudget). */
export const BUDGET_STATES = ["DRAFT", "PENDING", "APPROVED", "RETURNED", "REVISED", "CANCELLED"];
/** Each state's icon, on its badge, its tab and its card. */
export const STATE_ICON = { DRAFT: FilePen, PENDING: Clock, APPROVED: CircleCheck, RETURNED: RotateCcw, REVISED: RefreshCw, CANCELLED: X };
/** A state's own word (short: "Pending", not "Waiting for approval"). */
export const stateLabel = (status) => t("mobile", `blist.state_${status}`);

/** A budget's state: its colours from the theme, a 13dp icon (or, `dot`, the list's dot), the word. */
export function StatusBadge({ status, label, icon = true, dot = false }) {
  const states = useBudgetStates();
  const look = states[status] ?? states.CANCELLED;
  const Icon = status === "APPROVED" ? Award : STATE_ICON[status];

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 4, alignSelf: "flex-start", height: 26, paddingHorizontal: 10, borderRadius: 20, backgroundColor: look.bg }}>
      {dot ? <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: look.fg }} /> : icon && Icon ? <Icon size={13} color={look.fg} strokeWidth={2.6} /> : null}
      <Text style={{ color: look.fg, fontFamily: fonts.semibold, fontSize: 11, lineHeight: 15 }} numberOfLines={1}>
        {label ?? stateLabel(status)}
      </Text>
    </View>
  );
}
