/**
 * Pre-Budget Details (AnnotechERP 37; the reference screen, user 2026-11-27):
 * the budget's card (number, buyer, brand, state), its five steps —
 * Requisition, Budget, Costing + Stock, Accessories BOM, Approval — the
 * requisitions, the budget sheet (opened: the delivery dates, Order Qty and
 * FOB Value, the Budget Breakdown, the yarn and woven tables, each style),
 * the yarn's supplier and PI, the stock used, the knit and dye costing, the
 * accessories BOM, and the checklist: Finalize only once Costing, Stock and
 * every style's BOM are complete (the server refuses it otherwise), then the
 * chain Merchandising Head → CFO → MD, Approve or Reject at one's own step.
 */
import { useCallback, useState } from "react";
import { Alert as Confirm, Pressable, View } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import {
  Calculator,
  Check,
  ChevronDown,
  ChevronRight,
  ChevronUp,
  CircleCheck,
  ClipboardList,
  Cog,
  Copy,
  DollarSign,
  EllipsisVertical,
  FileText,
  Factory,
  Globe,
  History,
  Info,
  Layers,
  Lock,
  Package,
  Puzzle,
  Send,
  ShieldCheck,
  Tag,
  UserRound,
  Warehouse,
  X,
} from "lucide-react-native";
import { Alert, Button, Screen, Text } from "../../components/ui";
import { HeroBell, IconDisc, PageHero, SectionCard, StatusPill } from "../../components/hero";
import { LoadState, useLoad } from "../../components/manage";
import { StepButtons } from "../../components/steps";
import { BudgetActionMenu } from "../../components/budget-list";
import { StatusBadge, money, pt, qty, stateLabel } from "../../components/budget";
import { dateTimeOf } from "../../lib/clock";
import { t } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { fonts, space } from "../../theme/tokens";
import { shadow, useColors } from "../../theme/theme";

const bd = (key, params) => t("mobile", `pbd.${key}`, params);
const LEVEL_TONE = { APPROVED: "success", REJECTED: "danger", RETURNED: "danger", CURRENT: "warning", WAITING: "foregroundSubtle" };
const UNIT = { YARN: "kg", WOVEN: "yds" };
const taka = (n) => (n === null || n === undefined ? "—" : `৳${Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 })}`);
const usd = (n) => (n === null || n === undefined ? "—" : `$${money(n)}`);

export default function Budget() {
  const { no } = useLocalSearchParams();
  const { call } = useSession();
  const path = `/pre-budget/budgets/${encodeURIComponent(no)}`;
  const body = useLoad(path, [no]);
  const b = body.data;
  const [menu, setMenu] = useState(false);
  const [error, setError] = useState(null);
  const reload = body.reload;

  useFocusEffect(
    useCallback(() => {
      reload();
    }, [reload]),
  );

  const act = (what, ask, send) =>
    Confirm.alert(what, ask, [
      { text: t("mobile", "act.close"), style: "cancel" },
      {
        text: what,
        onPress: () => {
          setError(null);
          send().then(reload, (e) => setError(e.message));
        },
      },
    ]);
  const finalize = () => act(bd("finalize"), bd("finalize_ask", { no: b.budget_no }), () => call(`${path}/finalize`, { method: "POST" }));
  // The ⋮ menu: the Budget List's own, with this budget's rights (finalizing stays with the checklist below).
  const menuFor = b ? { budget_no: b.budget_no, status: b.status, check: b.check, actions: { finalize: false, withdraw: b.can_withdraw, revise: b.can_revise, delete: b.can_delete } } : null;
  const hasMenu = Boolean(b && (b.can_withdraw || b.can_revise || b.can_delete));

  return (
    <Screen bleed header={<PageHero title={bd("title")} icon={FileText} image="budget" onBack={() => router.back()} right={<HeroBell />} />}>
      <LoadState {...body} />
      {error ? <Alert>{error}</Alert> : null}
      {b ? (
        <>
          <InfoCard b={b} onMenu={hasMenu ? () => setMenu(true) : null} />
          <Steps b={b} />
          <Requisitions b={b} />
          <BudgetSheet b={b} />
          <SupplierPi b={b} section="YARN" />
          <SupplierPi b={b} section="WOVEN" />
          <StockUse b={b} />
          <Costing b={b} />
          <Accessories b={b} />
          <Checklist b={b} onFinalize={finalize} />
          <Chain b={b} path={path} onDone={reload} />
          <Revisions b={b} />
        </>
      ) : null}
      {menu ? (
        <BudgetActionMenu
          b={menuFor}
          view={false}
          onClose={() => setMenu(false)}
          onDone={(key) => (key === "remove" ? router.back() : key === "revise" ? null : reload())}
        />
      ) : null}
    </Screen>
  );
}

/** The budget's card: its number, buyer, brand, and state. */
function InfoCard({ b, onMenu }) {
  const colors = useColors();
  const brands = [...new Set((b.requisitions ?? []).map((r) => r.brand).filter(Boolean))];
  const cell = (Icon, label, value, flex = 1, lines = 2) => (
    <View style={{ flex, minWidth: 0, flexDirection: "row", gap: 4 }}>
      <Icon size={15} color={colors.brand} style={{ marginTop: 1 }} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={{ fontSize: 11 }} tone="foregroundMuted" numberOfLines={1}>
          {label}
        </Text>
        <Text style={{ fontFamily: fonts.bold, fontSize: 12, color: colors.heading }} numberOfLines={lines} adjustsFontSizeToFit={lines === 1} minimumFontScale={0.75}>
          {value || "—"}
        </Text>
      </View>
    </View>
  );

  // One row, as the reference screen: Budget No | Buyer | Brand | state | ⋮.
  const rule = <View style={{ width: 1, alignSelf: "stretch", backgroundColor: colors.border }} />;

  return (
    <View style={[{ flexDirection: "row", alignItems: "center", gap: space[2], borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, paddingVertical: space[3], paddingHorizontal: space[3] }, shadow(colors)]}>
      {cell(FileText, bd("budget_no"), b.budget_no, 1.55, 1)}
      {rule}
      {cell(UserRound, bd("buyer"), b.buyer, 1, 1)}
      {rule}
      {cell(Tag, bd("brand"), brands.join(", "), 1.25, 1)}
      <StatusBadge status={b.status} icon={false} label={stateLabel(b.status).toUpperCase()} />
      {onMenu ? (
        <Pressable accessibilityRole="button" accessibilityLabel={bd("more")} onPress={onMenu} hitSlop={10}>
          <EllipsisVertical size={20} color={colors.brand} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** The five steps, done ones ticked green, the one at hand blue, the rest grey. */
function Steps({ b }) {
  const colors = useColors();
  const check = b.check ?? {};
  const sent = ["PENDING", "APPROVED", "REVISED"].includes(b.status);
  const done = [true, true, Boolean(check.costing && check.stock), Boolean(check.bom), b.status === "APPROVED" || b.status === "REVISED"];
  const current = done.findIndex((d) => !d);
  const labels = [bd("step_requisition"), bd("step_budget"), bd("step_costing"), bd("step_bom"), bd("step_approval")];

  return (
    <View style={{ flexDirection: "row", paddingHorizontal: space[1] }}>
      {labels.map((label, i) => {
        const isDone = done[i];
        const here = i === current || (sent && i === 4 && !isDone);
        const bg = isDone ? colors.success : here ? colors.brand : colors.foregroundSubtle;

        return (
          <View key={label} style={{ flex: 1, alignItems: "center", gap: space[1] }}>
            <View style={{ flexDirection: "row", alignItems: "center", alignSelf: "stretch" }}>
              <View style={{ flex: 1, height: 2, backgroundColor: i === 0 ? "transparent" : done[i - 1] ? colors.success : colors.border }} />
              <View style={{ width: 28, height: 28, borderRadius: 14, backgroundColor: bg, alignItems: "center", justifyContent: "center" }}>
                {isDone ? <Check size={16} color="#ffffff" strokeWidth={3} /> : <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 13 }}>{i + 1}</Text>}
              </View>
              <View style={{ flex: 1, height: 2, backgroundColor: i === labels.length - 1 ? "transparent" : isDone ? colors.success : colors.border }} />
            </View>
            <Text style={{ fontSize: 11, textAlign: "center", fontFamily: here ? fonts.semibold : fonts.regular, color: here ? colors.brand : colors.foreground }} numberOfLines={2}>
              {label}
            </Text>
          </View>
        );
      })}
    </View>
  );
}

/**
 * The order summary (user, 2026-11-27; 2026-11-28): one row per style —
 * however many requisitions (parts, colours) it has — with its item(s), its
 * pieces and its FOB per piece (its FOB value over its pieces); the total
 * under them. No Knit/Woven and no requisition count (user, 2026-11-28).
 */
function Requisitions({ b }) {
  const colors = useColors();
  const [all, setAll] = useState(false);
  const reqs = b.requisitions ?? [];
  const byStyle = [];
  for (const r of reqs) {
    const key = r.style ?? "—";
    let row = byStyle.find((x) => x.style === key);
    if (!row) {
      row = { style: key, items: new Set(), qty: 0, value: 0, valued: false };
      byStyle.push(row);
    }
    if (r.item) row.items.add(r.item);
    row.qty += Number(r.order_qty ?? 0);
    if (r.fob_value !== null && r.fob_value !== undefined) {
      row.value += Number(r.fob_value);
      row.valued = true;
    }
  }
  const total = byStyle.reduce((a, r) => a + r.qty, 0);
  const totalValue = byStyle.reduce((a, r) => a + r.value, 0);
  const anyValued = byStyle.some((r) => r.valued);
  const shown = all ? byStyle : byStyle.slice(0, 6);
  const fobOf = (value, pcs, valued) => (valued && pcs > 0 ? `$${(value / pcs).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}` : "—");
  const figures = (pcs, fob, big = false) => (
    <View style={{ alignItems: "flex-end" }}>
      <Text style={{ fontFamily: fonts.bold, fontSize: big ? 15 : 12, color: big ? colors.brand : colors.heading }}>{`${qty(pcs)} ${t("mobile", "blist.pcs")}`}</Text>
      <Text style={{ fontFamily: fonts.semibold, fontSize: big ? 12 : 11, color: colors.success }}>{`${bd("fob")} ${fob}`}</Text>
    </View>
  );

  return (
    <SectionCard icon={ClipboardList} title={bd("order_summary")} right={<StatusPill size="sm" tone="brand" icon={Layers} label={bd("styles_n", { s: byStyle.length })} />}>
      <View style={{ gap: space[2] }}>
        {shown.map((r, i) => (
          <View key={r.style} style={{ flexDirection: "row", alignItems: "center", gap: space[2], paddingTop: i ? space[2] : 0, borderTopWidth: i ? 1 : 0, borderTopColor: colors.divider ?? colors.border }}>
            <Text style={{ flex: 1, fontSize: 12 }} numberOfLines={2}>
              {`${bd("style")}: ${r.style}  |  ${bd("item")}: ${[...r.items].join(", ") || "—"}`}
            </Text>
            {figures(r.qty, fobOf(r.value, r.qty, r.valued))}
          </View>
        ))}
        {byStyle.length > 6 ? (
          <Pressable onPress={() => setAll((a) => !a)} hitSlop={8} style={{ alignSelf: "center", flexDirection: "row", alignItems: "center", gap: space[1] }}>
            <Text style={{ color: colors.brand, fontFamily: fonts.semibold, fontSize: 12 }}>{all ? bd("show_less") : bd("show_all", { n: byStyle.length })}</Text>
            {all ? <ChevronUp size={16} color={colors.brand} /> : <ChevronDown size={16} color={colors.brand} />}
          </Pressable>
        ) : null}
      </View>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], padding: space[2], paddingHorizontal: space[3], borderRadius: 12, backgroundColor: colors.brandSubtle }}>
        <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 13, color: colors.heading }} numberOfLines={1}>
          {bd("styles_total", { s: byStyle.length })}
        </Text>
        {/* The total's FOB is the whole FOB value (user, 2026-11-28); each style's is per piece. */}
        {figures(total, anyValued ? usd(totalValue) : "—", true)}
      </View>
    </SectionCard>
  );
}

/**
 * The budget sheet (user, 2026-11-27): the Budget Breakdown's lines — each
 * cost in dollars and its share of the net FOB, CM, all utilities, and CM +
 * other utilities as a share of the FOB; the average FOB in the header
 * (user, 2026-11-28).
 */
function BudgetSheet({ b }) {
  const colors = useColors();
  const fob = b.fob;
  const c = b.costs;
  if (!fob || !c) return null;
  const net = fob.net ?? 0;
  const share = (v) => (net ? `${((Number(v ?? 0) / net) * 100).toFixed(2)}%` : "—");
  const rows = [
    { label: pt("budget.c.yarn"), cost: usd(c.yarn), pct: share(c.yarn) },
    ...(c.woven ? [{ label: pt("budget.c.woven"), cost: usd(c.woven), pct: share(c.woven) }] : []),
    { label: pt("budget.c.knitting"), cost: usd(c.knitting), pct: share(c.knitting) },
    { label: pt("budget.c.dyeing"), cost: usd(c.dyeing), pct: share(c.dyeing) },
    { label: pt("budget.c.accessories"), cost: usd(c.accessories), pct: share(c.accessories) },
    { label: pt("budget.c.embellishment"), cost: usd(c.embellishment), pct: share(c.embellishment) },
    { label: pt("budget.c.badge"), cost: usd(c.badge), pct: share(c.badge) },
    { label: t("mobile", "ord.cm_cost"), cost: usd(c.cm), pct: share(c.cm), strong: true },
    { label: t("mobile", "ord.all_utilities"), cost: usd(c.others), pct: share(c.others) },
    { label: pt("budget.c.cm_others"), cost: "", pct: share((c.cm ?? 0) + (c.others ?? 0)), total: true },
  ];
  const cell = { fontSize: 12, color: colors.foreground };

  return (
    <SectionCard icon={ClipboardList} title={bd("budget_sheet")} bodyStyle={{ gap: 0 }} right={<HeadChip icon={Tag} tone="brand" label={t("mobile", "ord.avg_fob")} value={fob.avg === null || fob.avg === undefined ? "—" : `$${Number(fob.avg).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`} />}>
      <View style={{ borderRadius: 12, borderWidth: 1, borderColor: colors.border, overflow: "hidden" }}>
        <View style={{ flexDirection: "row", alignItems: "center", minHeight: 40, paddingHorizontal: 10, backgroundColor: colors.surfaceMuted }}>
          <Text style={{ flex: 2, fontSize: 12, fontFamily: fonts.semibold }}>{pt("budget.component")}</Text>
          <Text style={{ flex: 1.2, fontSize: 12, fontFamily: fonts.semibold, textAlign: "right" }}>{pt("budget.cost")}</Text>
          <Text style={{ flex: 0.9, fontSize: 12, fontFamily: fonts.semibold, textAlign: "right" }}>{pt("budget.of_fob")}</Text>
        </View>
        {rows.map((r) => (
          <View key={r.label} style={{ flexDirection: "row", alignItems: "center", minHeight: 40, paddingHorizontal: 10, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: r.total ? colors.brandSubtle : colors.surface }}>
            <Text style={{ ...cell, flex: 2, fontFamily: r.strong || r.total ? fonts.semibold : fonts.regular, color: r.strong || r.total ? colors.heading : colors.foreground }} numberOfLines={2}>
              {r.label}
            </Text>
            <Text style={{ ...cell, flex: 1.2, textAlign: "right", fontFamily: r.strong ? fonts.semibold : fonts.regular }}>{r.cost}</Text>
            <Text style={{ ...cell, flex: 0.9, textAlign: "right", fontFamily: r.total ? fonts.bold : fonts.regular, color: r.total ? colors.brand : colors.foreground }}>{r.pct}</Text>
          </View>
        ))}
      </View>
    </SectionCard>
  );
}

/** A row of figures on a soft blue band, split by thin rules. */
function FigureBand({ items }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", borderRadius: 12, backgroundColor: colors.brandSubtle, paddingVertical: space[3] }}>
      {items.map(([label, value], i) => (
        <View key={label} style={{ flex: 1, alignItems: "center", gap: 2, paddingHorizontal: 2, borderLeftWidth: i ? 1 : 0, borderLeftColor: colors.borderStrong }}>
          <Text style={{ fontSize: 11, textAlign: "center" }} tone="foregroundMuted" numberOfLines={2}>
            {label}
          </Text>
          <Text style={{ fontFamily: fonts.bold, fontSize: 14, textAlign: "center" }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
            {value}
          </Text>
        </View>
      ))}
    </View>
  );
}

/** The yarn (or woven fabric) bought: requirement, PI, the PI's value, what is short (no supplier rate — user, 2026-11-27). */
function SupplierPi({ b, section }) {
  const colors = useColors();
  const s = b.sections?.[section];
  if (!s?.lines?.length) return null;
  const unit = UNIT[section];
  const tot = s.totals;
  const shortage = Math.max(0, (tot.total_req ?? 0) - (tot.stock_qty ?? 0) - (tot.pi_qty ?? 0));
  const action = s.lines.find((l) => l.shortage_action)?.shortage_action ?? null;
  const radio = (on, label) => (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
      <View style={{ width: 20, height: 20, borderRadius: 10, borderWidth: 2, borderColor: on ? colors.brand : colors.borderStrong, alignItems: "center", justifyContent: "center" }}>
        {on ? <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: colors.brand }} /> : null}
      </View>
      <Text style={{ fontSize: 14, fontFamily: on ? fonts.semibold : fonts.regular }}>{label}</Text>
    </View>
  );

  return (
    <SectionCard icon={Globe} title={section === "YARN" ? bd("knit_yarn_pi") : bd("woven_pi")}>
      <FigureBand
        items={[
          [bd("total_req"), `${qty(Math.round(tot.total_req ?? 0))} ${unit}`],
          [bd("pi_qty"), `${qty(tot.pi_qty)} ${unit}`],
          [bd("pi_value"), usd(tot.value)],
          [bd("shortage"), `${qty(Math.round(shortage))} ${unit}`],
        ]}
      />
      {shortage > 0 ? (
        <View style={{ flexDirection: "row", gap: space[6] }}>
          {radio(action !== "NOT_PURCHASE", bd("purchase"))}
          {radio(action === "NOT_PURCHASE", bd("not_purchase"))}
        </View>
      ) : null}
    </SectionCard>
  );
}

/** Stock used for the budget (user, 2026-11-27): Local and Import kg (2026-11-28), what is allocated from stock and its value. */
function StockUse({ b }) {
  const colors = useColors();
  const y = b.sections?.YARN?.totals;
  const w = b.sections?.WOVEN?.totals;
  if (!y && !w) return null;
  const allocated = [y?.stock_qty ? `${qty(Math.round(y.stock_qty))} kg` : null, w?.stock_qty ? `${qty(Math.round(w.stock_qty))} yds` : null].filter(Boolean).join(" · ") || "0 kg";
  const value = (y?.stock_value ?? 0) + (w?.stock_value ?? 0);
  // The stock yarn by where it came from (user, 2026-11-28): its lots' origin, Local or Import.
  const lots = (b.sections?.YARN?.lines ?? []).flatMap((l) => l.lots ?? []);
  const kgOf = (origin) => lots.filter((l) => l.origin === origin).reduce((a, l) => a + Number(l.qty ?? 0), 0);
  const box = (label, figure, tone) => (
    <View style={{ flex: 1, alignItems: "center", gap: 2, paddingVertical: space[3], borderRadius: 12, backgroundColor: tone === "success" ? colors.successSubtle : colors.brandSubtle }}>
      <Text style={{ fontSize: 12, color: colors.foregroundMuted }}>{label}</Text>
      <Text style={{ fontFamily: fonts.bold, fontSize: 16, color: tone === "success" ? colors.success : colors.heading }} numberOfLines={1} adjustsFontSizeToFit>
        {figure}
      </Text>
    </View>
  );

  return (
    <SectionCard icon={Warehouse} title={bd("stock_use")}>
      <View style={{ flexDirection: "row", gap: space[2] }}>
        <HeadChip wide icon={Check} tone="success" label={bd("local")} value={`${qty(Math.round(kgOf("LOCAL")))} kg`} />
        <HeadChip wide icon={Globe} tone="brand" label={bd("import")} value={`${qty(Math.round(kgOf("IMPORT")))} kg`} />
      </View>
      <View style={{ flexDirection: "row", gap: space[2] }}>
        {box(bd("stock_allocated"), allocated, "brand")}
        {box(bd("stock_value"), usd(value), "success")}
      </View>
    </SectionCard>
  );
}

/**
 * Knit and dye (user, 2026-11-28): the dollar's rate in the header, then a
 * card for each costing the budget has — no knitting cost, no knitting card —
 * with its average rate per kg and what it costs in dollars.
 */
function Costing({ b }) {
  const colors = useColors();
  const c = b.costing;
  if (!c) return null;
  const tot = c.totals ?? {};
  const kinds = [
    { key: "knit", icon: Cog, rate: tot.avg_knit_per_kg, cost: tot.knit_usd },
    { key: "dye", icon: Factory, rate: tot.avg_dye_per_kg, cost: tot.dye_usd },
  ].filter((k) => Number(k.cost ?? 0) > 0);

  return (
    <SectionCard icon={Calculator} title={bd("costing")} right={<HeadChip icon={DollarSign} tone="success" label={bd("dollar_rate")} value={taka(c.dollar_rate)} />}>
      {kinds.length ? (
        <View style={{ flexDirection: "row", gap: space[2] }}>
          {kinds.map((k) => (
            <View key={k.key} style={{ flex: 1, gap: space[2] }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[1.5] }}>
                <k.icon size={15} color={colors.brand} />
                <Text style={{ fontFamily: fonts.semibold, fontSize: 13, color: colors.heading }}>{bd(`${k.key}_title`)}</Text>
              </View>
              <FigureBand items={[[bd(`${k.key}_rate`), taka(k.rate)], [bd(`${k.key}_cost`), usd(k.cost)]]} />
            </View>
          ))}
        </View>
      ) : (
        <Text tone="foregroundMuted" style={{ fontSize: 13 }}>
          {bd("no_costing")}
        </Text>
      )}
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[1.5] }}>
        <Info size={15} color={colors.brand} />
        <Text tone="foregroundMuted" style={{ fontSize: 13, flex: 1 }}>
          {bd("cm_note", { cm: tot.cm_per_dz ?? "—" })}
        </Text>
      </View>
    </SectionCard>
  );
}

/**
 * A small figure in its tone, as the reference (user, 2026-11-28): the icon on
 * a solid circle, the name above the value — in a card header's right, or two
 * across (`wide`).
 */
function HeadChip({ icon: Icon, tone = "brand", label, value, wide = false }) {
  const colors = useColors();
  const fg = colors[tone] ?? colors.brand;
  const bg = colors[`${tone}Subtle`] ?? colors.brandSubtle;

  return (
    <View style={{ flex: wide ? 1 : undefined, flexDirection: "row", alignItems: "center", gap: space[2], paddingVertical: wide ? space[2.5] ?? 10 : 6, paddingHorizontal: wide ? space[3] : 10, borderRadius: 12, backgroundColor: bg }}>
      <View style={{ width: wide ? 26 : 20, height: wide ? 26 : 20, borderRadius: 13, backgroundColor: fg, alignItems: "center", justifyContent: "center" }}>
        <Icon size={wide ? 15 : 12} color="#ffffff" strokeWidth={2.8} />
      </View>
      <View>
        <Text style={{ fontSize: wide ? 13 : 10.5, lineHeight: wide ? 17 : 13, color: fg, fontFamily: fonts.semibold }} numberOfLines={1}>
          {label}
        </Text>
        <Text style={{ fontSize: wide ? 16 : 13, lineHeight: wide ? 21 : 17, color: fg, fontFamily: fonts.bold }} numberOfLines={1}>
          {value}
        </Text>
      </View>
    </View>
  );
}

/** The accessories' cost (user, 2026-11-27: "Accessories Cost", not the BOM): what the BOMs come to, its share of the net FOB, and how many styles are budgeted. */
function Accessories({ b }) {
  const colors = useColors();
  const a = b.accessories;
  const cost = b.costs?.accessories ?? 0;
  const net = b.fob?.net ?? 0;
  if (!a) return null;
  const box = (label, figure, tone = "brand") => (
    <View style={{ flex: 1, alignItems: "center", gap: 2, paddingVertical: space[3], borderRadius: 12, backgroundColor: colors.brandSubtle }}>
      <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
        {label}
      </Text>
      <Text style={{ fontFamily: fonts.bold, fontSize: 16, color: tone === "brand" ? colors.heading : colors[tone] }} numberOfLines={1} adjustsFontSizeToFit>
        {figure}
      </Text>
    </View>
  );

  return (
    <SectionCard icon={Puzzle} title={bd("acc_cost")}>
      <View style={{ flexDirection: "row", gap: space[2] }}>
        {box(bd("acc_total"), usd(cost))}
        {box(pt("budget.of_fob"), net ? `${((cost / net) * 100).toFixed(2)}%` : "—")}
        {box(bd("acc_styles"), `${a.budgeted ?? 0} / ${a.count ?? 0}`, (a.budgeted ?? 0) >= (a.count ?? 0) && a.count ? "success" : "warning")}
      </View>
    </SectionCard>
  );
}

/** Costing, Stock, every BOM — then Finalize, locked until all three are done. */
function Checklist({ b, onFinalize }) {
  const colors = useColors();
  const check = b.check ?? {};
  const ready = Boolean(check.costing && check.stock && check.bom);
  const items = [
    [Cog, bd("c_costing"), check.costing, bd("pending")],
    [Package, bd("c_stock"), check.stock, bd("pending")],
    [Copy, bd("c_bom"), check.bom, bd("incomplete")],
  ];
  const draft = b.status === "DRAFT" || b.status === "RETURNED";

  return (
    <SectionCard icon={CircleCheck} title={bd("checklist")}>
      {items.map(([Icon, label, done, open]) => (
        <View key={label} style={{ flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: 2 }}>
          <Icon size={19} color={colors.foregroundMuted} />
          <Text style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 14 }}>{label}</Text>
          <StatusPill size="sm" tone={done ? "success" : "danger"} icon={done ? Check : undefined} label={done ? bd("done") : open} />
          <ChevronRight size={18} color={colors.foregroundSubtle} />
        </View>
      ))}
      {draft && b.can_finalize ? (
        <View style={{ gap: space[1.5] }}>
          <Button icon={ready ? Send : Lock} disabled={!ready} onPress={onFinalize}>
            {bd("finalize")}
          </Button>
          {ready ? null : (
            <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[1.5] }}>
              <Info size={15} color={colors.foregroundMuted} />
              <Text tone="foregroundMuted" style={{ fontSize: 13 }}>
                {bd("finalize_hint")}
              </Text>
            </View>
          )}
        </View>
      ) : null}
    </SectionCard>
  );
}

/** Merchandising Head → CFO → MD: each step's state, who signed and when; Approve or Reject at one's own. */
function Chain({ b, path, onDone }) {
  const colors = useColors();
  const { call } = useSession();
  const a = b.approval;
  if (!a?.levels?.length) return null;
  const decide = (action) => (values) => call(`${path}/decision`, { method: "POST", body: { action, ...values } });
  const steps = a.can_act
    ? [
        { key: "approve", label: pt("final.approve"), icon: Check, fields: [{ key: "comment", label: pt("final.comment"), kind: "text" }], send: decide("approve") },
        { key: "reject", label: pt("final.reject"), icon: X, variant: "danger", fields: [{ key: "comment", label: pt("final.comment"), kind: "text", required: true }], send: decide("reject") },
      ]
    : [];

  return (
    <SectionCard icon={ShieldCheck} title={bd("chain")}>
      <View style={{ flexDirection: "row", alignItems: "flex-start" }}>
        {a.levels.map((l, i) => {
          const tone = LEVEL_TONE[l.state] ?? "foregroundSubtle";

          return (
            <View key={l.level} style={{ flex: 1, flexDirection: "row", alignItems: "flex-start" }}>
              <View style={{ flex: 1, alignItems: "center", gap: 4 }}>
                <IconDisc icon={l.state === "APPROVED" ? Check : l.state === "REJECTED" || l.state === "RETURNED" ? X : UserRound} tone={tone} size={42} />
                <Text style={{ fontSize: 12, fontFamily: fonts.semibold, textAlign: "center" }} numberOfLines={2}>
                  {l.name}
                </Text>
              </View>
              {i < a.levels.length - 1 ? <ChevronRight size={18} color={colors.foregroundSubtle} style={{ marginTop: 12 }} /> : null}
            </View>
          );
        })}
      </View>
      {a.levels.map((l) => (
        <View key={l.level} style={{ gap: 2, paddingTop: space[2], borderTopWidth: 1, borderTopColor: colors.border }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Text style={{ flex: 1, fontFamily: fonts.semibold }}>{`${l.level}. ${l.name}`}</Text>
            <StatusPill size="sm" tone={LEVEL_TONE[l.state] ?? "foregroundSubtle"} label={pt(`final.state.${l.state}`)} />
          </View>
          <Text variant="small" tone="foregroundMuted">
            {l.by ? `${l.by} · ${dateTimeOf(l.at)}` : (l.approvers ?? []).join(", ") || pt("final.nobody")}
          </Text>
          {l.comment ? <Text variant="small">{`“${l.comment}”`}</Text> : null}
        </View>
      ))}
      <StepButtons steps={steps} onDone={onDone} />
    </SectionCard>
  );
}

/** Every version of this budget (the one approved first, then -R1, -R2 …): its state, and a tap opens it. */
function Revisions({ b }) {
  const colors = useColors();
  const list = b.revisions ?? [];
  if (list.length < 2) return null;

  return (
    <SectionCard icon={History} title={bd("revisions")}>
      {list.map((r) => {
        const here = r.budget_no === b.budget_no;

        return (
          <Pressable
            key={r.budget_no}
            accessibilityRole="button"
            disabled={here}
            onPress={() => router.push({ pathname: "/budget/[no]", params: { no: r.budget_no } })}
            style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[2], paddingVertical: space[2], borderTopWidth: 1, borderTopColor: colors.border, opacity: pressed ? 0.7 : 1 })}
          >
            <Text style={{ flex: 1, fontFamily: here ? fonts.bold : fonts.semibold, fontSize: 14 }}>{here ? `${r.budget_no} · ${bd("this_one")}` : r.budget_no}</Text>
            <StatusBadge status={r.status} />
            {here ? null : <ChevronRight size={18} color={colors.foregroundSubtle} />}
          </Pressable>
        );
      })}
    </SectionCard>
  );
}
