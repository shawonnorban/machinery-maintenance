/**
 * Budget List (the enterprise list, user 2026-11-27): every Pre-Budget this
 * person reaches. On a phone, one card each — number, buyer and brand, how
 * many styles and items, made when and by whom, its state — a page at a time
 * as it scrolls; on a tablet or a phone on its side (720dp and more), a table
 * with a header that stays, a page at a time with its size chosen. Search
 * (budget no, buyer, brand, style, job no), the state chips and the
 * factory, year and department together, each budget's own figures (Net
 * FOB, Total Cost, margin), its actions by its state and this person's
 * rights, and every state of the page: loading, nothing yet, nothing found,
 * no network, refused, another error — each with its retry.
 *
 * The data and the actions live in lib/budgets.js; these are the pieces.
 */
import { memo, useCallback, useState } from "react";
import { ActivityIndicator, Alert as Confirm, FlatList, Pressable, RefreshControl, ScrollView, TextInput, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import {
  Award,
  Building2,
  CalendarDays,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CopyPlus,
  Ellipsis,
  Eye,
  FileText,
  Landmark,
  LayoutGrid,
  Lock,
  Pencil,
  RefreshCw,
  Search,
  SearchX,
  Send,
  Shirt,
  Stamp,
  Trash2,
  Undo2,
  WifiOff,
  X,
} from "lucide-react-native";
import { Alert, Button, Dropdown, Popup, PopupChoice, Text } from "./ui";
import { STATE_ICON, StatusBadge, money, qty, stateLabel } from "./budget";
import { NO_FILTERS, PAGE_SIZES, checklistDone, useBudgetActions, useBudgetList } from "../lib/budgets";
import { timeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { fonts, space } from "../theme/tokens";
import { useBudgetStates, useColors } from "../theme/theme";

const bl = (key, params) => t("mobile", `blist.${key}`, params);
/** From this width the list is a table (a tablet, or a phone on its side). */
export const TABLE_FROM = 720;
/** The table's least width; on a narrower screen it scrolls sideways, header and rows together. */
const TABLE_MIN = 960;
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
/** An ISO time → its day in Dhaka: "24 Nov 2026". */
export const dayOf = (iso) => {
  if (!iso) return "—";
  const d = new Date(Date.parse(iso) + 6 * 3600 * 1000);

  return `${String(d.getUTCDate()).padStart(2, "0")} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
};
const subtle = (colors) => ({ shadowColor: colors.shadow, shadowOffset: { width: 0, height: 1 }, shadowRadius: 3, shadowOpacity: 0.05, elevation: 1 });

/* ── The list ─────────────────────────────────────────────────────── */

export function BudgetList() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const wide = width >= TABLE_FROM;
  const [filters, setFilters] = useState(NO_FILTERS);
  const [search, setSearch] = useState("");
  const [pageSize, setPageSize] = useState(20);
  const [menuFor, setMenuFor] = useState(null);
  const list = useBudgetList({ filters, search, pageSize, append: !wide });
  const set = useCallback((key, value) => setFilters((f) => ({ ...f, [key]: value })), []);
  const narrowed = Boolean(list.q) || Object.values(filters).some(Boolean);
  const open = useCallback((b) => router.push({ pathname: "/budget/[no]", params: { no: b.budget_no } }), []);
  const rows = list.rows ?? [];
  const firstLoad = list.rows === null && !list.failure;

  const controls = (
    <View style={{ gap: space[3], paddingBottom: space[3] }}>
      <BudgetStatusTabs value={filters.status} counts={list.counts} onChange={(v) => set("status", v)} />
      <BudgetSearch value={search} onChange={setSearch} />
      <BudgetFilters filters={filters} options={list.options} onChange={set} />
      {list.counts?.MINE ? (
        <Pressable accessibilityRole="button" onPress={() => set("status", filters.status === "MINE" ? "" : "MINE")}>
          <Alert tone="info">{filters.status === "MINE" ? bl("showing_mine") : bl("your_turn", { n: list.counts.MINE })}</Alert>
        </Pressable>
      ) : null}
      {list.failure && list.rows ? <FailureBar failure={list.failure} onRetry={list.reload} /> : null}
      {list.loading && list.rows ? <ActivityIndicator color={colors.primary} /> : null}
    </View>
  );

  const renderItem = useCallback(
    ({ item, index }) => (wide ? <BudgetTableRow b={item} last={index === rows.length - 1} onOpen={open} onActions={setMenuFor} /> : <BudgetCard b={item} onOpen={open} onActions={setMenuFor} />),
    [wide, rows.length, open],
  );

  const state = firstLoad ? (
    <BudgetLoadingSkeleton table={wide} />
  ) : list.failure && !list.rows ? (
    <BudgetEmptyState kind={list.failure.kind} message={list.failure.message} onRetry={list.reload} />
  ) : rows.length === 0 ? (
    <BudgetEmptyState kind={narrowed ? "nothing_found" : "nothing_yet"} onClear={narrowed ? () => {
      setFilters(NO_FILTERS);
      setSearch("");
    } : null} />
  ) : null;

  const side = { paddingLeft: space[4] + insets.left, paddingRight: space[4] + insets.right };

  // A tablet keeps its toolbar and the table's header in place while the rows scroll under them
  // (FlatList's own sticky headers drop every row on this React Native); a phone scrolls it all.
  return (
    <View style={{ flex: 1, backgroundColor: colors.page }}>
      {wide ? <View style={{ ...side, paddingTop: space[3] }}>{controls}</View> : null}
      <TableFrame wide={wide && rows.length > 0} width={width - side.paddingLeft - side.paddingRight} side={side}>
      <FlatList
        data={rows}
        keyExtractor={(item) => item.budget_no}
        renderItem={renderItem}
        ListHeaderComponent={wide ? null : controls}
        ListEmptyComponent={state}
        ItemSeparatorComponent={wide ? null : CardGap}
        ListFooterComponent={
          wide ? null : list.loadingMore ? (
            <ActivityIndicator color={colors.primary} style={{ marginVertical: space[4] }} />
          ) : rows.length && list.page && list.page.current >= list.page.last ? (
            <Text style={{ textAlign: "center", color: colors.secondary, fontSize: 11, marginVertical: space[4] }}>{bl("end", { n: list.page.total })}</Text>
          ) : null
        }
        onEndReached={list.loadMore ?? undefined}
        onEndReachedThreshold={0.4}
        refreshControl={<RefreshControl refreshing={Boolean(list.loading && list.rows)} onRefresh={list.reload} colors={[colors.primary]} />}
        contentContainerStyle={{ paddingTop: wide ? 0 : space[3], paddingBottom: space[6], ...(wide ? {} : side) }}
        keyboardShouldPersistTaps="handled"
        initialNumToRender={8}
        windowSize={7}
      />
      </TableFrame>
      {wide && rows.length ? (
        <View style={{ ...side, paddingBottom: space[2], borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: colors.page }}>
          <BudgetPagination page={list.page} size={pageSize} onPage={list.goTo} onSize={setPageSize} />
        </View>
      ) : null}
      <BudgetActionMenu b={menuFor} onClose={() => setMenuFor(null)} onDone={list.reload} />
    </View>
  );
}

const CardGap = () => <View style={{ height: space[3] }} />;

/**
 * The table's frame: its header above the rows, both as wide as the table
 * (at least TABLE_MIN) and scrolled sideways together when the screen is
 * narrower. A phone's cards pass straight through.
 */
function TableFrame({ wide, width, side, children }) {
  if (!wide) return children;
  const tableWidth = Math.max(width, TABLE_MIN);
  const inner = (
    <View style={{ width: tableWidth, flex: 1 }}>
      <BudgetTableHeader />
      {children}
    </View>
  );

  return tableWidth > width ? (
    <ScrollView horizontal style={{ flex: 1 }} contentContainerStyle={{ ...side }} showsHorizontalScrollIndicator>
      {inner}
    </ScrollView>
  ) : (
    <View style={{ flex: 1, ...side }}>{inner}</View>
  );
}

/* ── Search and filters ───────────────────────────────────────────── */

/** The search field: budget no, buyer, brand, style, job no (the server looks in each). */
export function BudgetSearch({ value, onChange }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], height: 46, paddingHorizontal: space[3], borderRadius: 12, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.line }}>
      <Search size={20} color={colors.secondary} />
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={bl("search")}
        placeholderTextColor={colors.foregroundSubtle}
        autoCorrect={false}
        returnKeyType="search"
        accessibilityLabel={bl("search")}
        style={{ flex: 1, color: colors.body, fontFamily: fonts.regular, fontSize: 13, paddingVertical: 0 }}
      />
      {value ? (
        <Pressable accessibilityRole="button" accessibilityLabel={bl("clear_search")} onPress={() => onChange("")} hitSlop={10}>
          <X size={18} color={colors.secondary} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** The reference screen's three filters, side by side: factory, financial year, department. */
const FILTERS = [
  { key: "factory", icon: Building2, all: "all_factories", from: "factories" },
  { key: "fy", icon: CalendarDays, all: "all_years", from: "fys", label: (v) => `FY ${v}` },
  { key: "dept", icon: Landmark, all: "all_departments", from: "departments" },
];

export function BudgetFilters({ filters, options, onChange }) {
  return (
    <View style={{ flexDirection: "row", gap: space[2] }}>
      {FILTERS.map((f) => (
        <Dropdown
          key={f.key}
          field
          icon={f.icon}
          label={bl(`f_${f.key}`)}
          value={filters[f.key]}
          onChange={(v) => onChange(f.key, v)}
          style={{ flex: f.key === "dept" ? 1.15 : 1 }}
          options={[{ value: "", label: bl(f.all) }, ...(options?.[f.from] ?? []).map((v) => ({ value: v, label: f.label ? f.label(v) : v }))]}
        />
      ))}
    </View>
  );
}

/* ── State tabs ───────────────────────────────────────────────────── */

/**
 * The state chips (the reference screen): All, Draft, Pending, Approved, and
 * More ▾ holding Returned, Revised, Cancelled and To approve with their
 * counts. The chosen one filled blue; the row scrolls sideways if it must.
 */
export function BudgetStatusTabs({ value, counts, onChange }) {
  const colors = useColors();
  const looks = useBudgetStates();
  const [more, setMore] = useState(false);
  const tabs = [
    { key: "", label: bl("all"), icon: LayoutGrid },
    ...["DRAFT", "PENDING", "APPROVED"].map((s) => ({ key: s, label: stateLabel(s), icon: s === "DRAFT" ? Pencil : STATE_ICON[s] })),
  ];
  const extra = ["RETURNED", "REVISED", "CANCELLED", "MINE"];
  const inMore = extra.includes(value);
  const chip = (key, on, label, Icon, tint, onPress, trailing) => (
    <Pressable
      key={key}
      accessibilityRole="tab"
      accessibilityState={{ selected: on }}
      onPress={onPress}
      style={({ pressed }) => [{ flexDirection: "row", alignItems: "center", gap: 4, height: 34, paddingHorizontal: 9, borderRadius: 17, backgroundColor: on ? colors.primary : colors.surface, borderWidth: 1, borderColor: on ? colors.primary : colors.line, opacity: pressed ? 0.85 : 1 }, subtle(colors)]}
    >
      {Icon ? <Icon size={15} color={on ? "#ffffff" : tint} strokeWidth={2.3} /> : null}
      <Text style={{ fontFamily: fonts.semibold, fontSize: 12, color: on ? "#ffffff" : colors.body }}>{label}</Text>
      {trailing ? <ChevronDown size={15} color={on ? "#ffffff" : colors.body} /> : null}
    </Pressable>
  );

  return (
    <>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -space[4] }} contentContainerStyle={{ gap: 5, paddingVertical: 2, paddingHorizontal: space[4] }}>
        {tabs.map((tab) => chip(tab.key || "ALL", value === tab.key, tab.label, tab.icon, tab.key ? looks[tab.key].fg : colors.primary, () => onChange(tab.key)))}
        {chip("MORE", inMore, inMore ? (value === "MINE" ? bl("to_approve") : stateLabel(value)) : bl("more"), null, null, () => setMore(true), true)}
      </ScrollView>
      <Popup open={more} onClose={() => setMore(false)} title={bl("more_states")} icon={LayoutGrid} width={420} closeLabel={t("mobile", "act.close")}>
        {extra.map((s) => (
          <PopupChoice
            key={s}
            icon={s === "MINE" ? Stamp : STATE_ICON[s]}
            tone={s === "MINE" ? colors.primary : looks[s].fg}
            label={s === "MINE" ? bl("to_approve") : stateLabel(s)}
            sub={counts ? bl("n_budgets", { n: counts[s] ?? 0 }) : null}
            on={value === s}
            onPress={() => {
              onChange(s);
              setMore(false);
            }}
          />
        ))}
      </Popup>
    </>
  );
}

/* ── A budget as a card (phone) ───────────────────────────────────── */

/**
 * One budget, as the reference screen: the state's icon in its soft circle,
 * the number and the state, buyer • brand, Knit/Woven • quantity, the date of
 * its state — then its own figures: Net FOB, Total Cost and the margin (in
 * red, as a variance, when the cost is over). The whole card opens it; a long
 * press, its actions.
 */
export const BudgetCard = memo(function BudgetCard({ b, onOpen, onActions }) {
  const colors = useColors();
  const looks = useBudgetStates();
  const look = looks[b.status] ?? looks.CANCELLED;
  const Icon = b.status === "APPROVED" ? Award : STATE_ICON[b.status] ?? FileText;
  const brand = (b.brands ?? []).join(", ");
  const kinds = (b.kinds ?? []).map((k) => bl(`kind_${k}`)).join(" + ");
  const over = b.margin < 0;
  const figure = (label, value, color, first) => (
    <View style={{ flex: 1, alignItems: "center", gap: 2, borderLeftWidth: first ? 0 : 1, borderLeftColor: colors.line }}>
      <Text style={{ fontSize: 11, color: colors.secondary }} numberOfLines={1}>
        {label}
      </Text>
      <Text style={{ fontFamily: fonts.bold, fontSize: 15, color: color ?? colors.heading }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7}>
        {`$ ${money(value)}`}
      </Text>
    </View>
  );

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${b.budget_no}, ${b.buyer ?? ""}, ${stateLabel(b.status)}`}
      accessibilityHint={bl("card_hint")}
      onPress={() => onOpen(b)}
      onLongPress={() => onActions(b)}
      style={({ pressed }) => [{ borderRadius: 16, borderWidth: b.can_act ? 1.5 : 1, borderColor: b.can_act ? colors.primary : colors.line, backgroundColor: pressed ? colors.tableHead : colors.surface, paddingHorizontal: 14, paddingTop: 14, paddingBottom: 12, gap: 12 }, subtle(colors)]}
    >
      <View style={{ flexDirection: "row", gap: space[3] }}>
        <View style={{ width: 48, height: 48, borderRadius: 24, backgroundColor: look.bg, alignItems: "center", justifyContent: "center" }}>
          <Icon size={22} color={look.fg} strokeWidth={2.2} />
        </View>
        <View style={{ flex: 1, minWidth: 0, gap: 3 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 16, lineHeight: 21, color: colors.heading }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.85}>
              {b.budget_no}
            </Text>
            <StatusBadge status={b.status} dot label={b.can_act ? bl("to_approve") : undefined} />
          </View>
          <Text style={{ fontFamily: fonts.medium, fontSize: 13, color: colors.body }} numberOfLines={1}>
            {[b.buyer, brand].filter(Boolean).join("  •  ") || "—"}
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            <Shirt size={14} color={colors.primary} />
            <Text style={{ fontSize: 12, color: colors.body }} numberOfLines={1}>{`${kinds || "—"}  •  ${qty(b.order_qty)} ${bl("pcs")}`}</Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            <CalendarDays size={14} color={colors.secondary} />
            <Text style={{ fontSize: 12, color: colors.secondary }} numberOfLines={1}>
              {`${bl(`event_${b.event ?? "created"}`)}: ${dayOf(b.event_at ?? b.created_at)}  ${timeOf(b.event_at ?? b.created_at)}`}
            </Text>
          </View>
          {b.waiting_at?.current ? (
            <Text style={{ fontSize: 11, color: colors.warning }} numberOfLines={1}>
              {bl("waiting_at", { step: b.waiting_at.current, n: b.waiting_at.step, of: b.waiting_at.steps })}
            </Text>
          ) : null}
        </View>
        <ChevronRight size={19} color={colors.secondary} style={{ alignSelf: "center" }} />
      </View>
      <View style={{ flexDirection: "row", borderTopWidth: 1, borderTopColor: colors.line, paddingTop: 10 }}>
        {figure(bl("net_fob"), b.net_fob, null, true)}
        {figure(bl("total_cost"), b.total_cost)}
        {figure(over ? bl("variance") : bl("margin"), b.margin, over ? colors.danger : colors.success)}
      </View>
    </Pressable>
  );
});

/* ── The table (tablet, phone on its side) ────────────────────────── */

const COLUMNS = [
  { key: "no", flex: 1.5 },
  { key: "buyer", flex: 1.8 },
  { key: "styles", flex: 0.75, align: "right" },
  { key: "items", flex: 0.75, align: "right" },
  { key: "created", flex: 1.05 },
  { key: "by", flex: 1.15 },
  { key: "status", flex: 1.15 },
  { key: "action", flex: 0.6, align: "center" },
];
const cell = (c) => ({ flex: c.flex, paddingHorizontal: 11, alignItems: c.align === "right" ? "flex-end" : c.align === "center" ? "center" : "flex-start" });

/** The table's header: it stays above while the rows scroll under it. */
export function BudgetTableHeader() {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", height: 44, backgroundColor: colors.tableHead, borderWidth: 1, borderColor: colors.line, borderTopLeftRadius: 12, borderTopRightRadius: 12 }}>
      {COLUMNS.map((c) => (
        <View key={c.key} style={cell(c)}>
          <Text style={{ fontSize: 12, fontFamily: fonts.semibold, color: colors.body }} numberOfLines={1}>
            {bl(`col_${c.key}`)}
          </Text>
        </View>
      ))}
    </View>
  );
}

/** One budget as a row: the row opens it, the last cell its actions. */
export const BudgetTableRow = memo(function BudgetTableRow({ b, last, onOpen, onActions }) {
  const colors = useColors();
  const text = { fontSize: 12, color: colors.body };

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${b.budget_no}, ${stateLabel(b.status)}`}
      onPress={() => onOpen(b)}
      style={({ pressed, hovered }) => ({
        flexDirection: "row",
        alignItems: "center",
        minHeight: 54,
        backgroundColor: pressed || hovered ? colors.tableHead : colors.surface,
        borderLeftWidth: 1,
        borderRightWidth: 1,
        borderBottomWidth: 1,
        borderColor: colors.line,
        borderBottomLeftRadius: last ? 12 : 0,
        borderBottomRightRadius: last ? 12 : 0,
      })}
    >
      <View style={cell(COLUMNS[0])}>
        <Text style={{ fontSize: 13, fontFamily: fonts.bold, color: colors.heading }} numberOfLines={1}>
          {b.budget_no}
        </Text>
      </View>
      <View style={cell(COLUMNS[1])}>
        <Text style={{ ...text, fontFamily: fonts.medium }} numberOfLines={1}>
          {b.buyer ?? "—"}
        </Text>
        <Text style={{ fontSize: 11, color: colors.secondary }} numberOfLines={1}>
          {(b.brands ?? []).join(", ") || "—"}
        </Text>
      </View>
      <View style={cell(COLUMNS[2])}>
        <Text style={text}>{b.styles}</Text>
      </View>
      <View style={cell(COLUMNS[3])}>
        <Text style={text}>{b.items}</Text>
      </View>
      <View style={cell(COLUMNS[4])}>
        <Text style={text} numberOfLines={1}>
          {dayOf(b.created_at)}
        </Text>
      </View>
      <View style={cell(COLUMNS[5])}>
        <Text style={text} numberOfLines={1}>
          {b.created_by ?? "—"}
        </Text>
      </View>
      <View style={cell(COLUMNS[6])}>
        <StatusBadge status={b.status} label={b.can_act ? bl("to_approve") : undefined} />
      </View>
      <View style={cell(COLUMNS[7])}>
        <Pressable accessibilityRole="button" accessibilityLabel={`${bl("actions")} ${b.budget_no}`} onPress={() => onActions(b)} hitSlop={8} style={({ pressed }) => ({ width: 34, height: 34, borderRadius: 17, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? colors.line : "transparent" })}>
          <Ellipsis size={19} color={colors.body} />
        </Pressable>
      </View>
    </Pressable>
  );
});

/** Rows per page (10, 20, 50), where we are, and the way to the pages before and after. */
export function BudgetPagination({ page, size, onPage, onSize }) {
  const colors = useColors();
  if (!page) return null;
  const from = page.total ? (page.current - 1) * page.per_page + 1 : 0;
  const to = Math.min(page.total, page.current * page.per_page);
  const arrow = (Icon, label, target, off) => (
    <Pressable accessibilityRole="button" accessibilityLabel={label} disabled={off} onPress={() => onPage(target)} style={{ width: 36, height: 36, borderRadius: 10, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, alignItems: "center", justifyContent: "center", opacity: off ? 0.4 : 1 }}>
      <Icon size={18} color={colors.body} />
    </Pressable>
  );

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], paddingTop: space[3], flexWrap: "wrap" }}>
      <Text style={{ fontSize: 12, color: colors.secondary }}>{bl("rows_per_page")}</Text>
      <Dropdown value={size} onChange={onSize} label={bl("rows_per_page")} options={PAGE_SIZES.map((n) => ({ value: n, label: String(n) }))} />
      <Text style={{ flex: 1, textAlign: "right", fontSize: 12, color: colors.secondary }}>{bl("range", { from, to, total: page.total })}</Text>
      {arrow(ChevronLeft, bl("prev"), page.current - 1, page.current <= 1)}
      <Text style={{ fontSize: 12, color: colors.body, fontFamily: fonts.semibold }}>{bl("page_of", { n: page.current, of: page.last })}</Text>
      {arrow(ChevronRight, bl("next"), page.current + 1, page.current >= page.last)}
    </View>
  );
}

/* ── Actions ──────────────────────────────────────────────────────── */

/**
 * What may be done with a budget here, by its state and this person's rights
 * (the server's `actions`): view (and track its approval) always; a draft or
 * one sent back finalized once its checklist is done (editing it is on the
 * web), a waiting one withdrawn, an approved one revised (a new -R copy), a
 * draft deleted. Each asks first; the server checks everything again.
 */
export function BudgetActionMenu({ b, onClose, onDone, view = true }) {
  const colors = useColors();
  const actions = useBudgetActions();
  const [busy, setBusy] = useState(null);
  if (!b) return null;
  const a = b.actions ?? {};
  const ready = checklistDone(b.check);
  const editable = b.status === "DRAFT" || b.status === "RETURNED";

  const run = (key, title, ask, send) =>
    Confirm.alert(title, ask, [
      { text: t("mobile", "act.close"), style: "cancel" },
      {
        text: title,
        style: key === "remove" ? "destructive" : "default",
        onPress: async () => {
          setBusy(key);
          try {
            const result = await send(b.budget_no);
            onClose();
            onDone?.(key, result);
            if (key === "revise" && result?.budget_no) router.push({ pathname: "/budget/[no]", params: { no: result.budget_no } });
          } catch (e) {
            Confirm.alert(title, e.message);
          } finally {
            setBusy(null);
          }
        },
      },
    ]);

  const items = [
    view ? { key: "view", icon: b.status === "PENDING" ? Stamp : Eye, label: b.status === "PENDING" ? bl("a_track") : b.status === "REVISED" ? bl("a_history") : bl("a_view"), go: () => {
      onClose();
      router.push({ pathname: "/budget/[no]", params: { no: b.budget_no } });
    } } : null,
    a.finalize && editable ? { key: "edit", icon: Pencil, label: bl("a_edit"), hint: bl("a_edit_hint"), off: true } : null,
    a.finalize ? { key: "finalize", icon: Send, label: bl("a_finalize"), hint: ready === false ? bl("a_finalize_hint") : null, off: ready === false, go: () => run("finalize", bl("a_finalize"), bl("a_finalize_ask", { no: b.budget_no }), actions.finalize) } : null,
    a.withdraw ? { key: "withdraw", icon: Undo2, label: bl("a_withdraw"), go: () => run("withdraw", bl("a_withdraw"), bl("a_withdraw_ask"), actions.withdraw) } : null,
    a.revise ? { key: "revise", icon: CopyPlus, label: bl("a_revise"), hint: bl("a_revise_hint"), go: () => run("revise", bl("a_revise"), bl("a_revise_ask", { no: b.budget_no }), actions.revise) } : null,
    a.delete ? { key: "remove", icon: Trash2, label: bl("a_delete"), danger: true, go: () => run("remove", bl("a_delete"), bl("a_delete_ask", { no: b.budget_no }), actions.remove) } : null,
  ].filter(Boolean);

  return (
    <Popup open onClose={onClose} title={b.budget_no} hint={b.buyer ?? undefined} icon={FileText} right={<StatusBadge status={b.status} />} width={460} closeLabel={t("mobile", "act.close")}>
      {items.length === (view ? 1 : 0) ? <Text style={{ fontSize: 12, color: colors.secondary }}>{bl(b.status === "CANCELLED" ? "a_cancelled" : "a_none")}</Text> : null}
      {items.map((it) => (
        <Pressable
          key={it.key}
          accessibilityRole="menuitem"
          accessibilityState={{ disabled: Boolean(it.off) }}
          disabled={it.off || Boolean(busy)}
          onPress={it.go}
          style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 48, paddingHorizontal: space[3], paddingVertical: space[2], borderRadius: 12, borderWidth: 1, borderColor: it.danger ? colors.dangerSubtle : colors.line, backgroundColor: pressed ? colors.tableHead : it.danger ? "#FFF5F5" : colors.surface, opacity: it.off ? 0.55 : 1 })}
        >
          <View style={{ width: 34, height: 34, borderRadius: 17, alignItems: "center", justifyContent: "center", backgroundColor: it.danger ? colors.dangerSubtle : colors.brandSubtle }}>
            {busy === it.key ? <ActivityIndicator color={colors.primary} /> : <it.icon size={17} color={it.danger ? colors.danger : colors.primary} />}
          </View>
          <View style={{ flex: 1 }}>
            <Text style={{ fontSize: 13, fontFamily: fonts.semibold, color: it.danger ? colors.danger : colors.body }}>{it.label}</Text>
            {it.hint ? <Text style={{ fontSize: 11, color: colors.secondary }}>{it.hint}</Text> : null}
          </View>
          {it.off ? <Lock size={15} color={colors.secondary} /> : <ChevronRight size={17} color={colors.secondary} />}
        </Pressable>
      ))}
    </Popup>
  );
}

/* ── States ───────────────────────────────────────────────────────── */

const STATE_LOOK = {
  nothing_yet: { icon: FileText },
  nothing_found: { icon: SearchX },
  network: { icon: WifiOff },
  denied: { icon: Lock },
  error: { icon: RefreshCw },
};

/** Nothing yet, nothing found, no network, refused, or another error — with its way on. */
export function BudgetEmptyState({ kind, message, onRetry, onClear }) {
  const colors = useColors();
  const look = STATE_LOOK[kind] ?? STATE_LOOK.error;
  const tone = kind === "denied" || kind === "error" || kind === "network" ? colors.danger : colors.primary;

  return (
    <View style={{ alignItems: "center", gap: space[2], paddingVertical: space[10], paddingHorizontal: space[6] }}>
      <View style={{ width: 64, height: 64, borderRadius: 32, backgroundColor: `${tone}14`, alignItems: "center", justifyContent: "center", marginBottom: space[1] }}>
        <look.icon size={28} color={tone} />
      </View>
      <Text style={{ fontFamily: fonts.semibold, fontSize: 17, color: colors.heading, textAlign: "center" }}>{bl(`s_${kind}`)}</Text>
      <Text style={{ fontSize: 12, color: colors.secondary, textAlign: "center" }}>{kind === "error" && message ? message : bl(`s_${kind}_hint`)}</Text>
      {onClear ? (
        <Button variant="outline" icon={X} onPress={onClear} style={{ marginTop: space[2] }}>
          {bl("clear_all")}
        </Button>
      ) : null}
      {onRetry ? (
        <Button variant="outline" icon={RefreshCw} onPress={onRetry} style={{ marginTop: space[2] }}>
          {bl("retry")}
        </Button>
      ) : null}
    </View>
  );
}

/** A failure while rows are already showing: a line above them, with retry. */
function FailureBar({ failure, onRetry }) {
  const colors = useColors();

  return (
    <Pressable accessibilityRole="button" onPress={onRetry} style={{ flexDirection: "row", alignItems: "center", gap: space[2], padding: space[3], borderRadius: 12, backgroundColor: colors.dangerSubtle }}>
      {failure.kind === "network" ? <WifiOff size={17} color={colors.danger} /> : <RefreshCw size={17} color={colors.danger} />}
      <Text style={{ flex: 1, fontSize: 12, color: colors.danger }} numberOfLines={2}>
        {failure.kind === "error" ? failure.message : bl(`s_${failure.kind}`)}
      </Text>
      <Text style={{ fontSize: 12, color: colors.danger, fontFamily: fonts.semibold }}>{bl("retry")}</Text>
    </Pressable>
  );
}

/** Grey shapes where the first page will be: cards, or table rows. */
export function BudgetLoadingSkeleton({ table = false }) {
  const colors = useColors();
  const bar = (w, h = 10) => <View style={{ width: w, height: h, borderRadius: h / 2, backgroundColor: colors.line }} />;

  return (
    <View style={{ gap: table ? 0 : space[3] }} accessibilityLabel={bl("loading")} accessible>
      {[0, 1, 2, 3, 4].map((i) =>
        table ? (
          <View key={i} style={{ flexDirection: "row", alignItems: "center", gap: space[4], height: 54, paddingHorizontal: space[3], backgroundColor: colors.surface, borderWidth: 1, borderTopWidth: i ? 0 : 1, borderColor: colors.line }}>
            {bar("16%")}
            {bar("20%")}
            {bar("8%")}
            {bar("8%")}
            {bar("12%")}
            {bar("12%")}
          </View>
        ) : (
          <View key={i} style={{ flexDirection: "row", gap: space[3], padding: 14, borderRadius: 16, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }}>
            <View style={{ width: 46, height: 46, borderRadius: 23, backgroundColor: colors.line }} />
            <View style={{ flex: 1, gap: 8, paddingTop: 2 }}>
              {bar("55%", 14)}
              {bar("40%")}
              {bar("70%")}
            </View>
          </View>
        ),
      )}
    </View>
  );
}
