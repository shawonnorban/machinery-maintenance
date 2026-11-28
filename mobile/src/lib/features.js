/**
 * What a person may open, as Home's coloured tiles: each with the permission
 * its screen's API checks, never one that opens on a refusal. The Quality
 * Tally tiles are the chosen section's only — a section manager has just
 * their own (user, 2026-11-27); Machinery's follow the person's machinery
 * permissions (phase 4).
 */
import { Boxes, ClipboardCheck, ClipboardList, FileClock, FlaskConical, History, LineChart, ListChecks, QrCode, ScanLine, Siren, Stamp, Target, Wrench } from "lucide-react-native";
import { mm } from "../components/manage";
import { t } from "./i18n";
import { qcOf } from "./session";
import { accents } from "../theme/tokens";

const mt = (key) => t("mobile", `mach.${key}`);

export function tallyFeatures({ session, section }) {
  const list = [];
  const s = section?.section;

  if (section) {
    list.push({ key: "dashboard", label: mm("dashboard"), icon: LineChart, image: "production", color: accents.green, href: "/production" });
    if (section.manages) list.push({ key: "target", label: mm("target"), icon: Target, image: "line-target", color: accents.blue, href: { pathname: "/manage/targets", params: { section: s } } });
    if (section.manages && s === "CUTTING") list.push({ key: "input", label: mm("input"), icon: ScanLine, image: "scan-bundle", color: accents.teal, href: { pathname: "/manage/cut-input", params: { section: s } } });
    list.push({ key: "history", label: mm("history"), icon: History, image: "history", color: accents.orange, href: { pathname: "/manage/history", params: { section: s } } });
    list.push({ key: "review", label: mm("review"), icon: ListChecks, image: "review", color: accents.purple, href: { pathname: "/manage/review", params: { section: s } } });
  }
  if (qcOf(session)) list.push({ key: "tally", label: t("mobile", "nav.tally"), icon: ClipboardCheck, image: "quality", color: accents.teal, href: "/qc" });

  return list;
}

export function machineFeatures(access) {
  const list = [];
  if (access.sees) list.push({ key: "machines", label: mt("machines"), icon: Boxes, image: "machines", color: accents.blue, href: { pathname: "/machines" } });
  if (access.sees) list.push({ key: "scan", label: mt("scan"), icon: QrCode, image: "scan-machine", color: accents.teal, href: { pathname: "/machine/scan" } });
  if (access.report) list.push({ key: "report", label: mt("report"), icon: Siren, image: "breakdown", color: accents.rose, href: { pathname: "/breakdown/new" } });
  if (access.breakdowns) list.push({ key: "breakdowns", label: mt("breakdowns"), icon: Wrench, image: "maintenance", color: accents.orange, href: { pathname: "/breakdowns" } });
  if (access.workOrders) list.push({ key: "work", label: mt("work_orders"), icon: ClipboardList, image: "work-order", color: accents.purple, href: { pathname: "/work-orders" } });

  return list;
}

const ot = (key) => t("mobile", `ord.${key}`);

/** Phase 5: the two change logs and the budgets waiting for approval — each with the permission its API checks. */
export function orderFeatures(permissions = []) {
  const has = (p) => permissions.includes(p);
  const list = [];
  if (has("ordersummary.view")) list.push({ key: "os-log", label: ot("os_log"), icon: FileClock, image: "order", color: accents.blue, href: { pathname: "/logs/[kind]", params: { kind: "order-summary" } } });
  if (has("devsample.view")) list.push({ key: "ds-log", label: ot("ds_log"), icon: FlaskConical, image: "order", color: accents.teal, href: { pathname: "/logs/[kind]", params: { kind: "dev-sample" } } });
  if (has("prebudget.view") || has("prebudget.update") || has("approval.ticket.create")) list.push({ key: "budgets", label: ot("budgets"), icon: Stamp, image: "budget", color: accents.purple, href: { pathname: "/budgets" } });

  return list;
}


const ht = (key) => t("mobile", `home.${key}`);

/**
 * Home's quick access (user, 2026-11-28): Chat, Order, Budget, Production,
 * then Machines, Breakdown, Work Order, Maintenance — each drawn with the
 * user's own icon picture (components/tile-icon; every feature names one) and shown only with the permission
 * its screen's API checks; everything else is under More. Breakdown reports
 * one; Maintenance is the breakdowns being repaired.
 */
export function quickAccess({ session, access, machines }) {
  const email = session?.kind === "EMAIL";
  const perms = email ? (session.permissions ?? []) : [];
  const has = (p) => perms.includes(p);
  const list = [
    email ? { key: "chat", label: ht("q_chat"), image: "chat", color: accents.green, href: "/chat" } : null,
    has("ordersummary.view") || has("devsample.view") ? { key: "orders", label: ht("q_order"), image: "order", color: accents.blue, href: { pathname: "/order" } } : null,
    has("prebudget.view") || has("prebudget.update") || has("approval.ticket.create") ? { key: "budgets", label: ht("q_budget"), image: "budget", color: accents.orange, href: { pathname: "/budgets" } } : null,
    access.length ? { key: "production", label: ht("q_production"), image: "production", color: accents.purple, href: "/production" } : null,
    machines.sees ? { key: "machines", label: ht("q_machines"), image: "machines", color: accents.blue, href: "/machines" } : null,
    machines.report ? { key: "breakdown", label: ht("q_breakdown"), image: "breakdown", color: accents.rose, href: { pathname: "/breakdown/new" } } : null,
    machines.workOrders ? { key: "work", label: ht("q_work_order"), image: "work-order", color: accents.blue, href: { pathname: "/work-orders" } } : null,
    machines.breakdowns ? { key: "maintenance", label: ht("q_maintenance"), image: "maintenance", color: accents.green, href: { pathname: "/breakdowns" } } : null,
  ].filter(Boolean);

  return list.slice(0, 8);
}

