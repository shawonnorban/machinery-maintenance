import { redirect } from "next/navigation";
import { LayoutDashboard, Gauge, Cog, Boxes, AlertTriangle, ClipboardList, Users, UsersRound, Database, Factory as FactoryIcon, Truck, PackageSearch, ArrowLeftRight, CheckCircle2, ShieldAlert, CalendarDays, CalendarClock, ListChecks, ShieldCheck, SlidersHorizontal, Hash, ShieldPlus, FileText, GitBranch, BellRing, MapPin, HardHat, ArrowRightLeft, TriangleAlert, FileBarChart, Webhook, LifeBuoy, ListTodo, QrCode, ClipboardCheck, KeyRound, CreditCard, ListPlus, Library, ShoppingCart, PackageCheck, BookmarkCheck, Ship, Calculator, Undo2, Scale, Banknote, BookOpen, FileSignature, Stamp, Receipt, Ruler } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";
import { AppShell } from "@/components/layout/app-shell";
import { RegisterServiceWorker } from "@/components/offline/register-service-worker";
import { switchCompany } from "./switch-company-action";

export default async function AppLayout({ children }) {
  let notifications, me, permissions, nav, asset, companies;

  try {
    [notifications, me, permissions, nav, asset, companies] = await Promise.all([
      // One call for both: `filter=ALL&per_page=5` gives the bell dropdown's
      // own 5 most recent (mirrors `AppShellComposer::recentNotifications()`)
      // and `meta.unread_count` for its badge, computed regardless of filter —
      // the topbar needs both everywhere, not a per-page fetch.
      apiFetch("/notifications?filter=ALL&per_page=5", { includeMeta: true }),
      apiFetch("/auth/me"),
      // What this caller may actually do (API 3) — the same list a 403
      // would eventually enforce per endpoint, read once here so the
      // sidebar can stop offering a link the backend would refuse anyway
      // rather than let someone find that out by clicking.
      apiFetch("/auth/permissions"),
      getT("nav"),
      getT("asset"),
      // Every company this login may switch to — the sidebar's own
      // workspace switcher hides itself entirely when this is just one.
      apiFetch("/auth/companies"),
    ]);
  } catch (error) {
    // An expired/revoked session token surfaces here as a 401 from the
    // first authenticated call this layout makes — every page under (app)
    // shares this layout, so this is the one place that needs to catch it
    // rather than each page crashing with a raw, uncaught ApiError.
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login");
    }

    throw error;
  }

  const held = new Set(permissions.permissions);
  // `null` marks a screen with no permission gate of its own on the API
  // side (the dashboard, and Support's own docblock-documented "no admin
  // gate" — every authenticated tenant user reaches both) — always shown,
  // never checked against `held`.
  // A screen usually gates on one permission, but Billing's own web
  // controller allows either `billing.subscription.manage` OR
  // `billing.payment.manage` — a plain string still means "just this
  // one," an array means "any of these."
  const can = (permission) =>
    permission === null || (Array.isArray(permission) ? permission.some((p) => held.has(p)) : held.has(permission));

  // UI-DESIGN-SYSTEM.md §3: filtered by the caller's own permissions (API
  // 3's `/auth/permissions`) — a role without a screen's own `.view_any`
  // (or narrower — some screens, like Approvals and Technicians, have no
  // separate read permission at all and gate on the same one their writes
  // use) never sees it offered, rather than reaching it and being told no
  // by the API. Each `permission` here is the exact string the screen's
  // own primary API endpoint checks — verified against `PermissionSeeder`
  // and each controller's own `$this->allow(...)` call directly, not
  // guessed from the route name. Labels are translated (lang/{locale}/
  // nav.php via scripts/extract-i18n.php) since this is one of the screens
  // every session sees regardless of module; "Compliance" has no
  // equivalent key in nav.php and stays English rather than force a
  // mismatched one.
  const navGroups = [
    {
      label: asset("overview"),
      items: [{ label: nav("dashboard"), href: "/", icon: <LayoutDashboard />, permission: null }],
    },
    {
      // Asset registry — the machines themselves, separated from the
      // maintenance work done on them below, so this group doesn't grow to
      // ten unrelated items under one generic "Maintenance" label.
      label: nav("assets"),
      items: [
        { label: nav("assets"), href: "/assets", icon: <Cog />, permission: "asset.asset.view_any" },
        { label: nav("asset_transfers"), href: "/assets/transfers", icon: <ArrowLeftRight />, permission: "asset.asset.view_any" },
        { label: nav("print_labels"), href: "/assets/labels", icon: <QrCode />, permission: "asset.asset.view_any" },
        { label: nav("meters"), href: "/metering", icon: <Gauge />, permission: "meter.reading.view_any" },
      ],
    },
    {
      // Planning (plans/templates/schedule) through to execution
      // (breakdowns/work orders) — the actual maintenance work, as opposed
      // to the asset records it's performed against.
      label: nav("maintenance"),
      items: [
        { label: nav("plans"), href: "/maintenance/plans", icon: <ListChecks />, permission: "maintenance.plan.view_any" },
        { label: nav("templates"), href: "/maintenance/templates", icon: <FileText />, permission: "maintenance.template.view_any" },
        { label: nav("schedule"), href: "/maintenance/schedule", icon: <CalendarClock />, permission: "maintenance.schedule.view_any" },
        { label: nav("breakdowns"), href: "/breakdowns", icon: <AlertTriangle />, permission: "breakdown.breakdown.view_any" },
        { label: nav("work_orders"), href: "/work-orders", icon: <ClipboardList />, permission: "work_order.work_order.view_any" },
        { label: nav("my_work"), href: "/work-orders/my-work", icon: <ListTodo />, permission: "work_order.work_order.view_any" },
      ],
    },
    {
      label: nav("inventory"),
      items: [
        { label: nav("parts"), href: "/inventory/parts", icon: <Boxes />, permission: "inventory.part.view_any" },
        { label: nav("stock"), href: "/inventory/stock", icon: <PackageSearch />, permission: "inventory.stock.view" },
        { label: nav("issue_return"), href: "/inventory/issue", icon: <ArrowRightLeft />, permission: "inventory.part.view_any" },
        { label: nav("part_requests"), href: "/inventory/requests", icon: <ClipboardCheck />, permission: "work_order.work_order.view" },
        { label: nav("transfers"), href: "/inventory/transfers", icon: <ArrowLeftRight />, permission: "inventory.transfer.create" },
        { label: nav("low_stock"), href: "/inventory/low-stock", icon: <TriangleAlert />, permission: "inventory.stock.view" },
      ],
    },
    {
      // Who does the work — Teams moved here from deep inside Settings,
      // next to Technicians rather than filed under general administration.
      label: nav("workforce"),
      items: [
        { label: nav("technicians"), href: "/technicians", icon: <HardHat />, permission: "technician.technician.manage" },
        { label: nav("teams"), href: "/teams", icon: <UsersRound />, permission: "admin.team.manage" },
      ],
    },
    {
      // The item library is its own module (Master Data spec §2): admins
      // maintain it, everyone who raises a requisition reads from it.
      label: nav("item_master"),
      items: [
        { label: nav("item_library"), href: "/item-master", icon: <Library />, permission: "itemmaster.item.view_any" },
        { label: nav("low_stock_items"), href: "/item-master/low-stock", icon: <TriangleAlert />, permission: "itemmaster.item.view_any" },
        // Cycle counts and full stock takes (Inventory spec §12).
        // The four-point sheet a delivered lot is judged on (spec §4.3).
        { label: nav("fabric_inspections"), href: "/item-master/inspections", icon: <Ruler />, permission: "itemmaster.roll.view_any" },
        { label: nav("stock_counts"), href: "/item-master/stock-counts", icon: <ClipboardList />, permission: "itemmaster.count.view_any" },
      ],
    },
    {
      // Requisition → order → receipt (Procurement spec §5, §7, §9).
      label: nav("procurement"),
      items: [
        { label: nav("purchase_requisitions"), href: "/procurement/requisitions", icon: <ClipboardList />, permission: "procurement.requisition.view_any" },
        { label: nav("purchase_orders"), href: "/procurement/orders", icon: <ShoppingCart />, permission: "procurement.order.view_any" },
        { label: nav("goods_receipts"), href: "/procurement/receipts", icon: <PackageCheck />, permission: "procurement.receipt.view_any" },
        // Fabric held with a mill before there is an order (spec §3).
        { label: nav("fabric_bookings"), href: "/procurement/fabric-bookings", icon: <BookmarkCheck />, permission: "procurement.booking.view_any" },
        // What is on the water, and the papers behind it (spec §10).
        { label: nav("shipments"), href: "/procurement/shipments", icon: <Ship />, permission: "procurement.import.view_any" },
        // What the goods actually cost once freight and duty arrive (§11).
        { label: nav("landed_costs"), href: "/procurement/landed-costs", icon: <Calculator />, permission: "procurement.landed_cost.view_any" },
        // Goods back out of the gate, and the money side of a bad
        // delivery that cannot go back (§12).
        { label: nav("purchase_returns"), href: "/procurement/returns", icon: <Undo2 />, permission: "procurement.return.view_any" },
        { label: nav("supplier_claims"), href: "/procurement/claims", icon: <Scale />, permission: "procurement.claim.view_any" },
        // Money paid to a mill before anything arrives (§13).
        { label: nav("supplier_advances"), href: "/procurement/advances", icon: <Banknote />, permission: "procurement.advance.view_any" },
        // A price agreed for a year, which orders then price from (§14).
        { label: nav("rate_contracts"), href: "/procurement/rate-contracts", icon: <FileSignature />, permission: "procurement.contract.view_any" },
        // How each mill has actually behaved (§15).
        { label: nav("supplier_scorecards"), href: "/procurement/scorecards", icon: <Gauge />, permission: "procurement.scorecard.view_any" },
      ],
    },
    {
      // Customs paperwork rather than buying (Shared Systems spec §3): the
      // licence a bonded factory imports under, the declarations filed
      // against it, and the duty that falls due when bonded stock leaves
      // for anything other than an export.
      label: nav("compliance"),
      items: [
        { label: nav("bond_licences"), href: "/compliance/bond-licences", icon: <Stamp />, permission: "compliance.bond.view_any" },
        { label: nav("duty_assessments"), href: "/compliance/duty-assessments", icon: <Receipt />, permission: "compliance.bond.view_any" },
      ],
    },
    {
      // A floor asking a store for items (Floor/Store Requisition spec §3).
      // Gated on `.create` rather than `.view_any`: a technician who can
      // raise one needs the screen, and the API narrows it to their own.
      label: nav("requisitions"),
      items: [
        { label: nav("floor_requisitions"), href: "/requisitions", icon: <ClipboardCheck />, permission: "requisition.floor.create" },
      ],
    },
    {
      label: nav("vendors"),
      items: [
        { label: nav("vendors"), href: "/vendors", icon: <Truck />, permission: "vendor.vendor.view_any" },
        { label: nav("warranties"), href: "/vendors/warranties", icon: <ShieldPlus />, permission: "asset.asset.view_any" },
        { label: nav("service_contracts"), href: "/vendors/service-contracts", icon: <FileText />, permission: "vendor.vendor.view_any" },
      ],
    },
    {
      // Audit Log folded in here rather than left as its own single-item
      // "Compliance" group (which also had no translated label of its own).
      // First of the "everything below is admin/reporting, not day-to-day
      // maintenance work" section — SidebarNav renders a divider above it.
      newSection: true,
      label: nav("reports"),
      items: [
        { label: nav("reports"), href: "/reports", icon: <FileBarChart />, permission: "report.report.view" },
        { label: nav("audit_log"), href: "/audit-logs", icon: <ShieldAlert />, permission: "audit.log.view" },
      ],
    },
    {
      // Two single-item groups merged into one, per request — neither is
      // day-to-day maintenance work, both are "somebody else needs to act
      // on this" screens.
      label: nav("approvals"),
      items: [
        { label: nav("approvals"), href: "/approvals", icon: <CheckCircle2 />, permission: "approval.request.approve" },
        { label: nav("support"), href: "/support/tickets", icon: <LifeBuoy />, permission: null },
      ],
    },
    {
      // Company-owner-only in practice: `billing.subscription.manage`/
      // `billing.payment.manage` are held only by COMPANY_OWNER in the
      // seeded role matrix (deliberately excluded from COMPANY_ADMIN).
      label: nav("billing"),
      items: [
        {
          label: nav("billing"),
          href: "/billing",
          icon: <CreditCard />,
          permission: ["billing.subscription.manage", "billing.payment.manage"],
        },
      ],
    },
    {
      // Administration, always last: nothing here is part of day-to-day
      // maintenance work.
      label: nav("settings"),
      items: [
        { label: nav("users"), href: "/settings/users", icon: <Users />, permission: "admin.user.manage" },
        { label: nav("roles"), href: "/settings/roles", icon: <ShieldCheck />, permission: "admin.role.manage" },
        { label: nav("master_data"), href: "/settings/master-data", icon: <Database />, permission: "masterdata.manage" },
        { label: nav("custom_fields"), href: "/settings/custom-fields", icon: <ListPlus />, permission: "custom_field.definition.manage" },
        { label: nav("factories"), href: "/settings/factories", icon: <FactoryIcon />, permission: "settings.factory.manage" },
        { label: nav("locations"), href: "/settings/locations", icon: <MapPin />, permission: "masterdata.manage" },
        { label: nav("calendar_shifts"), href: "/settings/calendar", icon: <CalendarDays />, permission: "settings.calendar.manage" },
        { label: nav("company"), href: "/settings/company", icon: <SlidersHorizontal />, permission: "settings.company.manage" },
        { label: nav("numbering"), href: "/settings/numbering", icon: <Hash />, permission: "settings.numbering.manage" },
        // Which accounts each business event moves (Shared Systems §2).
        { label: nav("gl_mapping"), href: "/settings/gl-mapping", icon: <BookOpen />, permission: "finance.gl.view_any" },
        { label: nav("approval_workflows"), href: "/settings/approval-workflows", icon: <GitBranch />, permission: "settings.company.manage" },
        { label: nav("escalations"), href: "/settings/escalations", icon: <BellRing />, permission: "settings.company.manage" },
        { label: nav("webhooks"), href: "/settings/webhooks", icon: <Webhook />, permission: "webhook.endpoint.manage" },
        { label: nav("api_clients"), href: "/settings/api-clients", icon: <KeyRound />, permission: "admin.api_client.manage" },
      ],
    },
  ]
    .map((group) => ({ ...group, items: group.items.filter((item) => can(item.permission)) }))
    .filter((group) => group.items.length > 0);

  return (
    <>
      <RegisterServiceWorker />
      <AppShell
        navGroups={navGroups}
        unreadNotifications={notifications.meta.unread_count}
        recentNotifications={notifications.data}
        locale={me.user?.locale ?? "en"}
        userName={me.user?.name ?? me.name}
        userEmail={me.user?.email}
        companies={companies}
        currentCompanyId={me.company_id}
        companyLogoUrl={me.company?.logo_url}
        switchCompanyAction={switchCompany}
        impersonatedBy={me.impersonated_by}
      >
        {children}
      </AppShell>
    </>
  );
}
