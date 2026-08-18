# 10-Frontend-Specification.md
# Frontend Specification
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 2.0
**Status:** Accepted
**Stack:** Laravel Blade (server-rendered) + Bootstrap 5 + CoreUI 5 Free + Vite
**Supersedes:** v1.0 (Next.js + TypeScript SPA)
**Companion to:** `07-Permissions-and-Module-Structure.md`, `08-API-Schemas.md`

---

## 1. Decision and Consequences

The frontend is server-rendered Blade inside the Laravel application, styled to match the CoreUI 5 Bootstrap admin template. There is no separate SPA and no Node application server. Rationale is recorded in ADR-066.

What this changes from v1.0:

| Concern | v1.0 (Next.js) | v2.0 (Blade + CoreUI) |
|---|---|---|
| Rendering | Client-side against REST | Server-rendered HTML, JS enhances |
| Auth | Sanctum bearer token | Laravel session + CSRF |
| Routing | Next.js App Router | Laravel routes |
| Localization | `next-intl` | Laravel `__()` and language files |
| Authorization in UI | `PermissionGate` component | `@can` Blade directives |
| Validation | Zod generated from OpenAPI | Laravel FormRequest, one definition |
| Build | Next.js build | Vite |
| Testing | Vitest, Playwright | Pest, Laravel Dusk |
| Deployment units | Two | One |

What does **not** change: the REST API is still built in full (Section 3), tenant isolation, permissions, KPI definitions, and every domain rule in the SRS.

---

## 2. Stack

| Layer | Choice | Note |
|---|---|---|
| Templating | Blade with components | `x-` components, not partial soup |
| CSS framework | Bootstrap 5.3 | Comes with CoreUI 5 |
| Admin template | CoreUI 5 Free (MIT) | Layout and look only; no commercial components |
| Build | Vite (Laravel default) | Versioned, hashed assets |
| HTTP | Axios, single instance | See 2.2 — one client, not two |
| DOM helpers | jQuery, optional | Not required by Bootstrap 5 or CoreUI 5 |
| Dates | Day.js + `utc` and `timezone` plugins | Replaces Moment.js; see 2.3 |
| Charts | Chart.js (CoreUI wrapper) | Dashboard and report routes only |
| Selects, autocomplete | Tom Select (Apache-2.0) | Remote search for asset, part, technician pickers |
| Date and time input | Native inputs, Flatpickr (MIT) where a range is needed | See 2.6 |
| Calendar view | FullCalendar standard plugins (MIT) | Maintenance schedule only |
| Toasts | CoreUI Toast | Replaces Toastr |
| Dialogs | CoreUI Modal | Replaces SweetAlert2 where possible |
| Tables | Server-rendered Bootstrap tables + Laravel pagination | See 2.5 |
| Progress | CoreUI Spinner / Placeholder | Replaces Pace and SpinKit |
| Icons | CoreUI Icons only | See 2.4 |
| Offline drafts | IndexedDB via `idb-keyval` | Technician screens only |
| Real-time | Laravel Echo + Reverb | Section 8 |

### 2.1 CoreUI Free Only

The requirement is the CoreUI admin **design** — its sidebar, header, cards, widget tiles, and general visual language. That is exactly what the free template provides, under MIT. No commercial CoreUI component is used, and no licence is required.

The reference demo showcases PRO components. Each one this product would have used has a free replacement:

| PRO component | Replacement | Trade-off |
|---|---|---|
| SmartTable | Server-rendered Bootstrap table + Laravel pagination (2.5) | None that matters; server-side is required at these volumes anyway |
| Date Picker | Native `<input type="date">`, Flatpickr for ranges (2.6) | Native mobile pickers are good; desktop styling is less uniform |
| Time Picker | Native `<input type="time">` | Same |
| Multi Select, Autocomplete | Tom Select | Equivalent; also handles remote search, which the pickers need |
| Stepper | Bootstrap nav-pills plus a progress bar | Built once as `x-stepper`, roughly 40 lines of Blade |
| Calendar | FullCalendar standard plugins (MIT) | Equivalent for a month and week schedule view |
| PRO widgets and charts | CoreUI Free widgets + Chart.js (MIT) | Equivalent |

Rules:

1. No CoreUI PRO package is added to `package.json`, and no PRO asset is copied into the repository. A build must never depend on a licensed file that a fresh clone cannot install.
2. If a PRO component is later judged necessary, a licence is purchased first. Copying assets out of a PRO archive is not an option.
3. The design target is visual, not structural. Matching CoreUI's look does not oblige the project to match its component API.

### 2.2 One HTTP Client

All AJAX goes through a single configured Axios instance in `resources/js/http.js`. jQuery `$.ajax` is not used for API calls.

The reason is correctness, not taste. Every request must carry `X-Company-Id`, `X-Request-Id`, the CSRF token, and, on critical writes, `Idempotency-Key`. Two HTTP stacks means two interceptor chains, and one of them will eventually be missed. A write that skips the idempotency header duplicates inventory or breakdown records under retry, which is exactly the failure ADR-024 exists to prevent.

```js
// resources/js/http.js
const http = axios.create({ baseURL: '/api/v1', timeout: 30000 });
http.interceptors.request.use(cfg => {
  cfg.headers['X-Request-Id']  = crypto.randomUUID();
  cfg.headers['X-Company-Id']  = window.App.companyId;
  cfg.headers['Accept-Language'] = window.App.locale;
  cfg.headers['X-CSRF-TOKEN']  = window.App.csrf;
  if (cfg.idempotent) cfg.headers['Idempotency-Key'] = cfg.idempotencyKey;
  return cfg;
});
```

### 2.3 Dates

Moment.js is in maintenance mode and its own documentation recommends alternatives. This system's correctness depends on timezone handling: UTC storage, factory-timezone scheduling, shift calendars, DST boundaries (SRS 47, ADR-026). Day.js with the `utc` and `timezone` plugins is the substitution, and it is roughly a tenth the size.

Rules:

1. The server sends ISO 8601 UTC. The client formats for display only.
2. Display timezone is the **factory** timezone, not the browser timezone, and the timezone abbreviation is always shown. A manager in Dhaka reviewing a Gazipur factory must not silently see shifted times.
3. Date arithmetic that affects business meaning — due dates, downtime, shift boundaries — happens on the server. The client never computes a due date.

### 2.4 Icon Fonts

One icon set: CoreUI Icons. Font Awesome and Simple Line Icons are not loaded.

Three icon fonts add 150-300 KB of font files for glyphs that overlap almost entirely. On the mid-range Android phone a technician actually uses, that is the single largest avoidable cost on the page, and it competes with the Bengali webfont, which is not optional.

### 2.5 Tables

Server-rendered Bootstrap tables with Laravel pagination. Sorting, filtering, and paging are query-string driven and handled server-side; the table body is re-fetched as a partial by AJAX so the user keeps their scroll position and filter state.

This is not a downgrade. A tenant with 20,000 assets cannot ship them all to the browser, so client-side table processing was never viable for the primary lists. What is genuinely lost is instant client-side filtering on small tables — noticeable on a 20-row master data list, irrelevant on the asset register.

Column visibility toggles and saved views are stored per user and applied server-side.

### 2.6 Date and Time Input

Native `<input type="date">` and `<input type="datetime-local">` by default. They are well supported, need no JavaScript, and give a technician the familiar Android picker, which is better than any web reimplementation on a phone.

Flatpickr is added only where a native input is genuinely insufficient: date-range filters on reports and dashboards, and the schedule view's quick-select. It is MIT, roughly 15 KB gzipped, and has a Bengali locale.

All values are submitted as ISO strings and interpreted server-side in the factory timezone (2.3).

---

## 3. Where the API Fits

Blade rendering does not remove the REST API. SRS 42-43 require it for ERP, HRM, production, accounting, and IoT integration, and for any future mobile client. ADR-001 and ADR-043 still stand.

The API is not duplicated logic, because business logic lives in Actions and Services (ADR-003). Both entry points call the same Action:

```text
POST /work-orders          (API controller)      ─┐
                                                  ├─→ CreateWorkOrderAction
POST /app/work-orders      (Blade controller)    ─┘
```

Rules:

1. A controller — web or API — contains no business logic. It validates, authorizes, delegates to an Action, and responds.
2. A rule enforced in an Action is enforced for both entry points automatically. A rule written in a controller is not, and is a defect.
3. The API is tested independently of the web UI. "The screen works" is not evidence the API works.
4. `openapi.yaml` continues to be generated and remains the integration contract.

### 3.1 Page Loads Versus AJAX

| Interaction | Mechanism |
|---|---|
| Navigation, list pages, detail pages, forms | Full server-rendered page load |
| Table sort, filter, paginate | AJAX to a JSON endpoint, SmartTable re-renders |
| Form submission | Standard POST with redirect, except where noted |
| Status transitions (start, hold, complete, verify, close) | AJAX to the API, partial re-render |
| Checklist answers | AJAX per answer, autosaved |
| Stock issue and return | AJAX, so focus stays in the scan field |
| Dashboard widgets | AJAX after initial paint, so a slow KPI never blocks the page |
| Notifications | Echo over WebSocket, AJAX fallback poll |

The default is a full page load. AJAX is used where a round trip would lose the user's place, their focus, or their unsaved input — not everywhere.

---

## 4. Layout

Follows the CoreUI 5 template structure.

```text
┌──────────────────────────────────────────────────────────┐
│ Sidebar    │ Header: company switcher | factory scope |   │
│ (collapse) │         search | notifications | locale |    │
│            │         connection status | user menu        │
│            ├──────────────────────────────────────────────┤
│            │ Breadcrumb                                   │
│            ├──────────────────────────────────────────────┤
│            │ Content                                      │
│            ├──────────────────────────────────────────────┤
│            │ Footer                                       │
└──────────────────────────────────────────────────────────┘
```

### 4.1 Sidebar Navigation

Rendered from a permission-filtered menu definition, not hard-coded in the Blade layout. A menu item whose permission the user lacks is not rendered.

```text
Dashboard
Assets            ├ All Assets ├ Hierarchy ├ Transfers ├ Print Labels
Maintenance       ├ Plans ├ Schedule ├ Templates
Work Orders       ├ All ├ My Work ├ Calendar ├ Approvals
Breakdowns        ├ Active ├ All ├ Report Breakdown
Inventory         ├ Parts ├ Stock ├ Issue/Return ├ Receipts
                  ├ Transfers ├ Adjustments ├ Low Stock
Technicians       ├ Technicians ├ Teams
Vendors           ├ Vendors ├ Warranties ├ Service Contracts
Costs             ├ Cost Entries ├ Lifecycle Cost
Reports
Settings          ├ Company ├ Factories ├ Locations ├ Calendar & Shifts
                  ├ Master Data ├ Numbering ├ Users ├ Roles
                  ├ Labor Grades ├ API Clients ├ Webhooks
Audit Log
Billing
```

Badge counts on Work Orders, Breakdowns, and Approvals refresh over WebSocket.

### 4.2 Header Controls

| Control | Behavior |
|---|---|
| Company switcher | Only for multi-company users. Switching reloads the page; it never merges state across tenants |
| Factory scope | A global scope, not a per-page filter. Set once, respected by every list. Persisted per user |
| Global search | Assets, work orders, breakdowns, parts by code or name |
| Notifications | CoreUI dropdown, unread count over WebSocket |
| Locale toggle | English / বাংলা, persisted to the user profile |
| Connection status | Live, reconnecting, or offline. A technician must know their screen is stale |
| Theme | Light default. Dark is CoreUI-supported and optional |

### 4.3 Blade Component Inventory

Built once in `resources/views/components`, used everywhere:

```text
x-layout.app            x-layout.auth          x-layout.mobile
x-page-header           x-breadcrumb           x-card
x-data-table            x-filter-bar           x-saved-views
x-status-pill           x-priority-badge       x-criticality-badge
x-empty-state           x-skeleton             x-confirm-dialog
x-form.input            x-form.select          x-form.multi-select
x-form.date             x-form.datetime        x-form.textarea
x-form.file             x-form.asset-picker    x-form.part-picker
x-form.technician-picker x-form.errors
x-kpi-tile              x-chart                x-timeline
x-checklist-item        x-attachment-grid      x-audit-trail
```

`x-status-pill` renders text plus color, never color alone. A red dot means nothing to a color-blind technician under factory lighting.

---

## 5. Screen Inventory

Routes are Laravel web routes under `/app`. The API keeps `/api/v1`.

### 5.1 Authentication

| Screen | Route | CoreUI reference |
|---|---|---|
| Login | `/login` | Login page |
| MFA challenge | `/login/mfa` | Login variant |
| Forgot password | `/forgot-password` | — |
| Reset password | `/reset-password/{token}` | — |
| Company selection | `/select-company` | Card list |

### 5.2 Dashboards

| Screen | Route | Widgets |
|---|---|---|
| Management | `/app/dashboard` | KPI tiles (assets by status, availability, MTBF, MTTR, overdue PM), cost trend chart, top failing assets, downtime by reason |
| Maintenance | `/app/dashboard/maintenance` | Today's tasks, due and overdue, open work orders, active breakdowns, technician workload |
| Store | `/app/dashboard/store` | Stock value, low stock, out of stock, reserved, recent movements |

KPI tiles use the CoreUI widget pattern from the reference demo. Each tile shows the value, the period, and `computed_at`. A tile whose value is `null` renders "N/A" with a reason tooltip, never `0` (SRS 31.2).

Widgets load by AJAX after first paint. A slow KPI query must never delay the page.

### 5.3 Assets

| Screen | Route |
|---|---|
| List | `/app/assets` — SmartTable, filter bar, saved views, bulk actions, export |
| Detail | `/app/assets/{id}` — tabs: Overview, Maintenance, Breakdowns, Costs, Documents, Meters, Transfers, History |
| Create / Edit | `/app/assets/create`, `/app/assets/{id}/edit` — CoreUI Stepper: identity, classification, location, financial, warranty |
| Transfer | `/app/assets/{id}/transfer` |
| Hierarchy | `/app/assets/{id}/hierarchy` — parent and child tree |
| Print labels | `/app/assets/print-labels` — QR label sheet, print stylesheet |

Detail Overview shows status, location, open breakdown, next PM due, and lifetime cost above the fold. Those five answer most reasons anyone opens an asset.

The financial block is wrapped in `@can('asset.financial.view')`. A technician needs the machine record, not its purchase price.

### 5.4 Maintenance

| Screen | Route |
|---|---|
| Plans | `/app/maintenance/plans`, `/app/maintenance/plans/{id}` |
| Plan builder | `/app/maintenance/plans/create` — rule builder with live preview of the next 5 due dates |
| Schedule | `/app/maintenance/schedule` — FullCalendar month/week view plus a list view; overdue highlighted |
| Templates | `/app/maintenance/templates`, `.../versions/{version}` |
| Checklist builder | Sortable items, input type, tolerance, attachment rules |

The due-date preview is an AJAX call to a server-side dry run. A combined `OR` rule with rolling mode and a non-working-day policy is genuinely hard to reason about, and the preview turns a guess into a check. The client never computes these dates itself (Section 2.3).

### 5.5 Work Orders

| Screen | Route |
|---|---|
| List | `/app/work-orders` — table and status-grouped board |
| Detail | `/app/work-orders/{id}` — tabs: Checklist, Parts, Labor, Costs, Attachments, History |
| Create | `/app/work-orders/create` |
| My Work | `/app/my-work` — landing page for the Technician role |
| Execution | `/app/work-orders/{id}/execute` — mobile screen, see Section 6 |
| Calendar | `/app/work-orders/calendar` |
| Approvals | `/app/approvals` — approver queue |

Transition buttons (start, hold, resume, complete, verify, close, cancel) post by AJAX and re-render the header partial. A button blocked by state is shown disabled with the reason in a tooltip: "Complete — 3 required checklist items remaining" is useful; a missing button is not.

### 5.6 Breakdowns

| Screen | Route |
|---|---|
| List | `/app/breakdowns` — active pinned at top, auto-refreshed over WebSocket |
| Detail | `/app/breakdowns/{id}` — timestamp timeline, downtime breakdown, linked work orders |
| Report | `/app/breakdowns/create`, and `/s/{code}` after a scan |
| Close | Root cause and failure code required |

**Report Breakdown** is the most time-critical screen in the product. A line has stopped. Four fields: asset (prefilled when scanned), what happened, severity, optional photo. Everything else is captured later by whoever attends.

The detail timeline renders the failure-to-resume chain with the gap at each stage labelled. That is where a manager sees that 40 minutes went to waiting for a technician and 12 to the actual repair.

### 5.7 Inventory

| Screen | Route |
|---|---|
| Parts | `/app/inventory/parts`, `/app/inventory/parts/{id}` |
| Stock by location | `/app/inventory/stock` |
| Issue / Return | `/app/inventory/issue` — scanner-first, see below |
| Receipts | `/app/inventory/receipts` |
| Adjustments | `/app/inventory/adjustments` — reason mandatory |
| Transfers | `/app/inventory/transfers`, `/app/inventory/transfers/{id}` |
| Low stock | `/app/inventory/low-stock` |
| Valuation | `/app/inventory/valuation` |

The issue screen is keyboard and barcode driven: scan part, scan bin, type quantity, submit by AJAX, focus returns to the part field, the running list appends above. A storekeeper issuing 40 parts in a shift must never touch the mouse.

### 5.8 Remaining

| Area | Routes |
|---|---|
| Technicians and teams | `/app/technicians`, `/app/teams` |
| Vendors | `/app/vendors`, `/app/warranties`, `/app/service-contracts` |
| Costs | `/app/costs`, `/app/assets/{id}/lifecycle-cost` |
| Reports | `/app/reports`, `/app/reports/{type}`, `/app/reports/jobs/{id}` |
| Notifications | `/app/notifications` |
| Imports | `/app/imports` — upload, validate, preview, error report, confirm |
| Settings | `/app/settings/*` per the sidebar tree |
| Audit | `/app/audit-logs` |
| Billing | `/app/billing/*` |
| Scan landing | `/s/{code}`, `/s/l/{code}` |

The import error report downloads as a spreadsheet with a reason per row. A 4,000-row asset import will fail on some rows, and the factory needs to fix them offline.

---

## 6. The Technician Mobile Screens

This is the part of the product that a server-rendered admin template does **not** solve for free, and it carries the highest usage volume. It needs deliberate design rather than a responsive version of a desktop page.

Three screens are affected: work order execution, breakdown reporting, and meter reading entry.

### 6.1 Context

| Factor | Consequence |
|---|---|
| Shared mid-range Android, cracked screen | Large targets, high contrast, no fine gestures |
| Oily hands, sometimes gloves | Minimum 44x44 px touch targets, generous spacing |
| Standing next to a running machine, one hand | One decision per screen, no dense tables |
| Patchy factory wifi | Every answer saved locally before it is sent |
| Bengali as working language | Bengali default, longer strings, layout must absorb 30-40% growth |

Where technician needs and manager needs conflict, the technician wins. The manager has a desk and a keyboard.

### 6.2 Execution Screen Design

Uses `x-layout.mobile`: no sidebar, no breadcrumb, no CoreUI header. A minimal top bar with the work order number, a progress bar, and a close action.

```text
┌──────────────────────────────────┐
│ WO-DHK-202608-00417      9 / 14  │
│ ████████████░░░░░░░              │
├──────────────────────────────────┤
│  Item 10 of 14                   │
│                                  │
│  থ্রেড টেনশন পরীক্ষা              │
│  Thread tension test on sample   │
│                                  │
│  ┌────────┐┌────────┐┌────────┐  │
│  │  PASS  ││  FAIL  ││   N/A  │  │
│  └────────┘└────────┘└────────┘  │
│                                  │
│  [ 📷 Add photo ]                │
│  [ Note (required on fail)    ]  │
│                                  │
│  ← Previous            Next →    │
├──────────────────────────────────┤
│  ✓ Saved on device · 3 queued    │
└──────────────────────────────────┘
```

Rules:

1. One checklist item per screen. No scrolling list of 14 items.
2. Pass / Fail / N/A are three full-width buttons, not a dropdown or radio group.
3. Answering advances automatically, except on `FAIL`, which stays put to collect the required note and photo.
4. Every answer writes to IndexedDB **before** the network call. The save indicator reflects local state, not the server.
5. The footer always shows sync state. A technician must never wonder whether their work was recorded.
6. Photos are resized client-side to a maximum 1600 px long edge before upload. A modern phone camera produces 4-8 MB files, which on factory wifi is a failed upload.

### 6.3 Offline Behaviour

Scope per ADR-034: draft persistence and retry-safe submission, not full offline sync.

| Capability | Included |
|---|---|
| Checklist answers, breakdown drafts, meter readings persisted to IndexedDB | Yes |
| Automatic retry queue, flushed on reconnect | Yes |
| Client-generated idempotency key per draft | Yes |
| Service worker caching the app shell and CoreUI assets | Yes |
| Reading previously loaded pages while offline | Read-only, marked stale |
| Creating arbitrary records offline | No |

**The idempotency key is generated when the draft is created, not when it is sent.** A technician who taps Submit three times on a dead connection must produce one breakdown, not three (API 32).

```js
const draft = {
  key: crypto.randomUUID(),        // fixed for the life of this draft
  endpoint: '/breakdowns',
  payload: { ... },
  created_at: Date.now(),
  attempts: 0
};
await idbSet(`draft:${draft.key}`, draft);
```

The queue flushes on `online`, on page load, and every 30 seconds while pending items exist. An item that fails with `4xx` other than `409` is moved to a failed state and surfaced to the user; it is not retried forever. A `409 IDEMPOTENCY_CONFLICT` or a successful replay clears the draft, because the server already has it.

### 6.4 Breakdown Reporting on Mobile

Same layout, four fields, one screen. The asset is prefilled when the user arrived from a QR scan, and locked so it cannot be changed by accident. Severity is a set of large buttons, not a select.

Submission is optimistic in presentation only: the UI confirms the draft is saved and queued, and shows the breakdown number once the server responds. It never claims the breakdown was created before the server said so.

---

## 7. Localization

Server-side, which is one of the genuine advantages of this stack: strings are resolved before the HTML leaves the server, so there is no client-side message bundle to download.

```text
lang/
├── en/  assets.php  work_orders.php  breakdowns.php  inventory.php
│       validation.php  enums.php  notifications.php
└── bn/  (same files)
```

Rules:

1. No literal user-facing string in a Blade template or a JS file. A CI grep fails the build on one.
2. Locale resolves from the user profile, then the company default, then `en`. Set by middleware on every request.
3. Enum labels are translated through `lang/{locale}/enums.php`, keyed by the stored uppercase code. The code never changes with locale.
4. The few JS strings that exist are passed from Blade into `window.App.i18n`, never hard-coded in a bundle.
5. Bengali strings run 20-40% longer than English. Every screen is checked at `bn` before it is considered done; buttons and table headers are where this breaks first.
6. A Bengali webfont with full glyph coverage is self-hosted, subset, and preloaded. PDF and Excel exports embed the same family (SRS 48.1 rule 7).

---

## 8. Real-Time

Laravel Echo with the Reverb driver, using session authentication against `/broadcasting/auth`.

Subscriptions: `private-user.{id}`, `private-company.{id}`, and each accessible `private-factory.{id}`.

Rules:

1. An event carries identifiers and a small summary, never the rendered data (API 40).
2. On an event, the page **re-fetches the affected partial** and replaces it, rather than mutating individual DOM nodes from the payload. Imperative row-by-row DOM patching is how this kind of codebase rots; one partial replacement is verifiable and idempotent.
3. Screens that subscribe: breakdown list, work order list and detail, notification bell, dashboard badge counts, stock levels on the issue screen.
4. Connection state is always visible in the header.
5. When the socket cannot connect, active lists poll every 60 seconds and the header says "reconnecting". No data is lost either way, because REST is the source of truth (ADR-008).
6. Channel authorization is server-side on every subscribe. A revoked role takes effect on the next subscription attempt (SRS 29).

---

## 9. Cross-Cutting UI Rules

### 9.1 Authorization in Views

```blade
@can('work_order.work_order.verify', $workOrder)
    <button class="btn btn-primary" data-action="verify">@lang('work_orders.verify')</button>
@endcan
```

Hiding a control is usability, never security. The server re-checks on every request (API 34). A hidden button and a `403` must agree, and a test asserts both.

### 9.2 Error Presentation

The UI branches on the response `code`, never on `message` (API 36).

| Code | Presentation |
|---|---|
| `VALIDATION_ERROR` | Inline per field from `errors`, Bootstrap `is-invalid` |
| `CONFLICT` (version) | Modal offering reload, showing what changed |
| `INSUFFICIENT_STOCK` | Inline, listing the alternative bins the API returned |
| `CHECKLIST_INCOMPLETE` | Scroll to and highlight the items named in `meta` |
| `PARTS_NOT_RECONCILED` | Link to the Parts tab with outstanding lines highlighted |
| `SUBSCRIPTION_READ_ONLY` | Persistent banner; write controls disabled application-wide |
| `RATE_LIMITED` | Toast with the retry time |
| `DEPENDENCY_UNAVAILABLE` | Retry affordance; drafts preserved |
| `500` | Generic message plus the copyable `request_id` for support |

### 9.3 Tables

Server-rendered Bootstrap tables with Laravel pagination (Section 2.5). Sorting, filtering, and paging are server-side; the table body re-renders as an AJAX partial. Client-side processing is not used; a tenant with 20,000 assets cannot ship them all to the browser.

Column visibility toggles, saved views per user, sticky header, sticky first column on horizontal scroll, row actions in an overflow menu.

Bulk selection distinguishes **select all on page** from **select all matching filter**, with the count stated. A user who selects the page and clicks Close expects 25 work orders closed, not 3,000.

### 9.4 Loading and Empty States

1. CoreUI placeholders matching the final layout, not a full-page spinner.
2. Every empty state names the reason and the next action. "No work orders" is unhelpful; "No open work orders in Dhaka Unit 1 this week — create one" is not.
3. Any action over 400 ms shows progress. Anything over 5 seconds becomes a queued job with a notification on completion (API 2).

### 9.5 Accessibility

WCAG 2.1 AA target. Keyboard navigable, visible focus rings, labelled controls, `aria-live` for AJAX results, contrast verified at 4.5:1, tested at 200% zoom.

Two known risks in this stack, to be checked rather than assumed: CoreUI modal and dropdown focus trapping, and SmartTable keyboard navigation. Both are verified with axe in CI.

---

## 10. Asset Pipeline and Performance

### 10.1 Vite Bundles

Three entry points, so a technician never downloads what only a manager sees:

| Bundle | Contents | Loaded on |
|---|---|---|
| `app` | Bootstrap 5, CoreUI Free, Axios, Day.js, Tom Select, shared components | Every authenticated page |
| `mobile` | Execution and reporting screens, IndexedDB queue, camera handling | Technician screens only |
| `analytics` | Chart.js, FullCalendar, Flatpickr | Dashboard, report, and schedule routes only |

Chart.js and FullCalendar are never in the shared bundle. Together they are roughly 130 KB gzipped that a technician on the execution screen would never use.

### 10.2 Budgets

Measured on a mid-range Android over a 3G-like connection.

| Metric | Budget | Note |
|---|---|---|
| Shared JS, gzipped | < 140 KB | Bootstrap 5 + CoreUI Free + Axios + Day.js + Tom Select fits; jQuery pushes it to ~170 KB |
| Mobile screen total JS | < 180 KB | `app` + `mobile`; no charts, no calendar, no Tom Select |
| Dashboard total JS | < 290 KB | `app` + `analytics` |
| CSS, gzipped | < 60 KB | Bootstrap + CoreUI Free, purged |
| Icon fonts | < 60 KB | CoreUI Icons subset only |
| Bengali webfont | < 120 KB | Subset, preloaded, `font-display: swap` |
| First Contentful Paint | < 1.5 s | Server-rendered, so achievable |
| Largest Contentful Paint | < 2.5 s | — |
| Cumulative Layout Shift | < 0.1 | — |

Server-rendered HTML makes first paint easier than the SPA it replaces. That advantage is lost entirely if plugins accumulate, which is the failure mode this stack is prone to. Every new JS dependency requires a stated reason and a bundle-size check in the pull request.

Explicitly not loaded: Font Awesome, Simple Line Icons, Moment.js, Toastr, SweetAlert2, Pace, SpinKit, Perfect Scrollbar, bootstrap-datepicker, and any CoreUI PRO package. Each is either replaced by a CoreUI 5 Free equivalent already in the bundle or by a named MIT alternative (Section 2.1). Loading both is paying twice for one capability.

### 10.3 Enforcement

CI runs a bundle-size check against these budgets and Lighthouse against five routes: login, `/app/my-work`, `/app/work-orders/{id}/execute`, `/app/breakdowns/create`, `/app/dashboard`. A budget breach fails the build.

---

## 11. Testing

| Layer | Tool | Covers |
|---|---|---|
| Feature | Pest / PHPUnit | Controllers, authorization, validation, rendered content |
| Browser | Laravel Dusk | Login and MFA, asset creation, PM execution, breakdown report to close, part issue, company switching |
| JS unit | Vitest | Offline queue, idempotency key lifecycle, formatters |
| Accessibility | axe in CI | Every route |
| Visual | Dusk screenshots | Both locales, mobile and desktop viewports |

Mandatory assertions, because these are the failures that would matter most:

1. Switching company shows no data from the previous company anywhere on the page.
2. A user without a permission sees no control for it, **and** receives `403` when the request is issued directly. Both halves are asserted; a hidden button alone proves nothing.
3. The offline queue submits exactly one record when a draft is retried three times.
4. Every page renders without a missing translation key in `bn`.

---

## 12. Build Order

The shell, authentication, Blade component library, and the CoreUI layout are built first, before any feature module. Every screen depends on them, and retrofitting a component library after ten screens exist means rewriting ten screens.

After that, frontend work follows the backend module order in the README, with the technician mobile screens (Section 6) treated as their own workstream rather than as a responsive afterthought on the desktop pages.

---

## 13. Open Items

1. Bengali webfont selection and licence.
2. Whether dark mode ships in v1. CoreUI Free supports it; it is a testing cost, not a build cost.
3. Barcode scanner hardware model, which determines whether the issue screen reads keyboard-wedge input or needs a camera-based fallback.
4. Desktop date-input styling: whether native inputs are acceptable throughout, or Flatpickr is applied to all date fields for visual consistency with the CoreUI look.
