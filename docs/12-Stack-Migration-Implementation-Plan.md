# 12-Stack-Migration-Implementation-Plan.md
# Stack Migration Status & Roadmap: Blade/MySQL → Next.js/PostgreSQL (Annotech RMG Stack)

Rewritten 2026-09-13 to replace the previous version of this document, which
had drifted out of sync with the repository in both directions — some things
it called "not started" were already built (the role-based dashboard), and
some things it implied were finished are not (the offline-queue test suite
cannot currently run). Every line below was checked directly against the
code, not carried forward from the old document's own narrative.

Two other documents were retired alongside this rewrite:
- `docs/05-Gap-Analysis-and-Traceability.md` — tracked gaps against the
  original v1.0 SRS/ERD/API spec, from before ADR-066's reversal to Blade
  and the current Next.js effort. Not a useful reference for this migration.
- `docs/openapi.yaml` — documented 4 paths against a real surface of dozens
  of controllers. Misleading as a "the API is documented here" pointer.
  `docs/03-API-Specification.md` is the maintained API reference until (or
  unless) a real generated OpenAPI file replaces it — see Step 3 below.

---

## Recommended order of work

Numbered because the phases below are not independent — several later
items are blocked on an earlier one, and doing them out of order means
redoing work.

1. ~~**Install the missing offline-queue test tooling** (Step 1).~~ **Done**
   — `vitest`/`jsdom`/`fake-indexeddb` installed, `npm run test` passes
   4/4 against `frontend/src/lib/offline/queue.test.js`.
2. ~~**Resolve the Platform module's fake-data stub** (Step 2).~~ **Done**
   (removed) — see Phase D for why "wire it up instead" turned out to be
   a much bigger, separate initiative (a platform-staff auth bridge
   doesn't exist yet), not a quick alternative to deletion.
3. ~~**Close the Billing API gap, then build its Next.js screens** (Step
   3).~~ **Done** — the "API gap" turned out not to exist (see Phase B);
   built the Next.js screens directly against the already-complete API,
   adding three small missing read fields along the way.
4. ~~**Build the reverse-proxy path-based coexistence routing** (Phase
   F).~~ **Done** — `docker/nginx/default.conf` now has the mechanism
   (verified live); no module's pages are actually switched over yet,
   which is correctly Phase H's decision, not this step's.
5. **Now unblocked — begin Phase H**: dark-launch one module at a time
   (uncomment/add its `location` block in `docker/nginx/default.conf`),
   soak, then decommission its Blade views. Nothing else on this list
   blocks it anymore.
6. ~~Echo/Reverb wiring (first consumer), Redis-as-default, Horizon,
   Playwright scaffolding, and the `/metering`-vs-`/meters` decision
   (resolved: redirect).~~ **Done** — see Phase F/G/H. What's left
   (soak-time/error-rate tracking, the tenant-isolation/offline-retry
   Playwright tests themselves, further module cutovers, eventual Blade
   decommission) is real but genuinely needs either external
   infrastructure this environment doesn't have, or scenario design work,
   not just more code — see each phase's own notes.

---

## Phase A — PostgreSQL Migration

**Done.**

- [x] No MySQL-only SQL outside `app/Shared/Support/Sql.php`'s own
      driver-matched branches (verified by grep for `DATE_FORMAT(`,
      `FIELD(`, `MODIFY COLUMN` across `app/`).
- [x] Docker's Postgres service is wired and is what this project's own
      dev workflow actually runs against (per this project's own memory
      notes on Docker/Postgres issues encountered and fixed).
- [x] `config/database.php`'s default connection is now `pgsql` (was
      `sqlite`), and both `.env` and `.env.example` now set
      `DB_CONNECTION=pgsql` with `DB_HOST=127.0.0.1`/`DB_PORT=5432` — the
      address Docker Compose already publishes Postgres on for anyone
      running `artisan` from the host outside a container. Confirmed
      MySQL/WAMP-native execution is no longer part of this project's
      workflow (Docker is the only way it's run) before making this
      change — see `docker-compose.yml`'s own comment on the backend
      service, which documented (and now correctly describes) why
      `DB_HOST` still needs a container-side override to `postgres`
      even with `.env` itself naming Postgres.

## Phase B — REST API Buildout

**Directory-level: done (all 20 modules have an `Api/` controller
directory). Depth: uneven.**

- [x] Every module under `app/Modules/` has both `Http/Controllers/Api/`
      and `Http/Controllers/Web/` — no module is Web-only.
- [x] **The earlier claim here was wrong — corrected after actually reading
      both controllers side by side.** `SubscriptionApiController` already
      has full parity with the tenant-facing `BillingController` (web):
      `index`/`show`/`pay` ↔ `show`/`invoices`/`showInvoice`/
      `storePayment`, plus its own extra `payments` ledger endpoint the
      web page doesn't separately expose. Neither controller offers
      invoice/payment *creation* — that's deliberately a platform-staff-only
      operation (`SubscriptionApiController`'s own docblock explains why:
      a tenant granting itself the power to alter its own billing contract
      is a capability this product doesn't intend to offer, not a gap).
      Three small, genuinely-missing read fields were found and added
      instead while building the Next.js screens: `grace_period_days` and
      `void_reason` on the contract/invoice summaries, `period_start`/
      `period_end` on invoice lines — all real columns the API simply
      hadn't exposed yet. Covered by
      `tests/Feature/Billing/SubscriptionApiTest.php` (8/8 passing).
- [x] **Decided: OpenAPI is dropped, not regenerated.** No `openapi:generate`
      artisan command exists (the old plan document's claim that one did
      was itself part of the drift this rewrite corrected), and building
      one accurately enough to be trustworthy — across 20 modules' worth
      of controllers, request classes, and resources — is a real generator
      project in its own right, not a quick regeneration. `docs/
      03-API-Specification.md` is the maintained, hand-written reference
      and stays the single source of truth; nothing in the repo's tooling
      or CI referenced `openapi.yaml` (confirmed by grep before it was
      deleted), so there's nothing else to update for this decision to
      take effect.
- [x] Platform's superadmin API (10 controllers — tenant CRUD, finance,
      support tickets, support grants, domains) is comprehensive. Its gap
      is entirely on the consuming side — see Phase D.

## Phase C — Next.js Frontend Foundation

**~95% done.**

- [x] `frontend/src/components/ui/` has every component
      `UI-DESIGN-SYSTEM.md` §4 specifies (alert, avatar-stack, badge,
      button, card, checkbox, confirm-dialog, data-table, date-picker,
      dropdown, empty-state, error-state, file-input, form-field, input,
      modal, select, skeleton, stat-card, status-badge, switch, tabs,
      textarea, toast). `PageHeader` lives in `components/layout/` instead
      of `components/ui/` — an organizational difference, not a missing
      component.
- [x] `globals.css` implements the token set and three-state dark mode
      exactly as specified.
- [x] i18n is real and working: `frontend/messages/{en,bn}.json`,
      `frontend/src/i18n/`, `next-intl`.
- [x] `vitest`, `jsdom`, and `fake-indexeddb` are installed
      (`vitest@4.1.11`, not the just-released `5.0.0` — that version's
      expanded optional peer set, e.g. `@vitest/browser-playwright`,
      triggers a known npm 10 arborist bug, "Cannot read properties of
      null (reading 'edgesOut')", on install; `jsdom@26.1.0`, not the
      latest `30.x`, which requires Node ≥22 and this project runs Node
      20). `npm run test` now runs `frontend/src/lib/offline/queue.test.js`
      for real: 4/4 passing, covering the exact behaviour this phase
      names as its verification gate (idempotency key held across
      retries, a replayed 409 treated as success, a non-replay 409 kept
      pending, concurrent flush calls collapsed to one).

## Phase D — Module-by-Module Screen Migration

Present under `frontend/src/app/(app)/`: account, approvals, assets
(+create/edit/transfers/labels), audit-logs, breakdowns, inventory
(parts/stock/transfers/low-stock/issue/requests), maintenance
(plans/schedule/templates), metering, notifications, reports, settings
(api-clients/approval-workflows/calendar/company/escalations/factories/
locations/master-data/numbering/roles/users/webhooks), support, teams,
technicians, vendors (+warranties/service-contracts), work-orders. A real
role-based dashboard exists at `frontend/src/app/(app)/page.js` (three
panels: management/maintenance/store).

- [x] **Billing screens built**: `frontend/src/app/(app)/billing/page.js`
      (contract status/outstanding/amount/grace-period, usage, invoices
      table) and `.../billing/invoices/[invoiceId]/page.js` (lines,
      payments, credit notes, a record-payment form gated on
      `billing.payment.manage` and shown only while the invoice is open).
      Added to the sidebar behind `["billing.subscription.manage",
      "billing.payment.manage"]` — the first nav item needing "any of
      these," so `layout.js`'s `can()` helper now accepts an array, not
      only a single permission string.
- [x] **Platform's fake-data stub removed** (`frontend/src/app/platform/
      page.js` deleted — it was an untracked file, never committed).
      `PlatformShell` (`frontend/src/components/layout/platform-shell.jsx`)
      was kept: it's real, correct design-system work (the dark-chrome/
      amber-accent superadmin shell per UI-DESIGN-SYSTEM.md §2), just
      currently unused, not fake.
      **Not done, and bigger than this step's original sizing implied:**
      a real Platform console needs its own auth bridge first — platform
      staff authenticate through `PlatformAuthApiController` on a
      completely separate track from tenant users (`getSessionToken()`'s
      cookie), and nothing in the Next.js app establishes a platform
      staff session today. Building the actual console (tenant list,
      finance, support tickets — the 10 Platform API controllers already
      exist) is realistically its own initiative, comparable in size to
      Billing's, not a follow-on to deleting the stub.
- [x] **Asset detail page's QR token card built** (user-reported: "Asset
      view doesn't show a QR code"). Mirrors `asset::assets.show.blade.php`'s
      QR card exactly — the scannable SVG, the raw code, a print-label
      link, and a regenerate action — genuinely missing, not just a
      display bug: the page's own docblock already flagged "QR... still
      deferred." The read side (`GET /api/v1/assets/{asset}/qr`) already
      existed; the *regenerate* side didn't — added
      `AssetLabelApiController::regenerate()` (reuses `RegenerateQrToken`
      unchanged, ADR-003) and its route (`POST /api/v1/assets/{asset}/qr/
      regenerate`, gated on the same `asset.qr.regenerate` permission
      Blade uses). New `frontend/src/components/assets/qr-card.jsx` (SVG
      rendered the same trusted-`dangerouslySetInnerHTML` way
      `label-sheet.jsx` already does) + a `regenerateQr` action in the
      asset detail page's `actions.js`. Verified live end-to-end: the
      SVG's actual `<path>` data round-trips into the rendered page, and a
      real regenerate call changed the stored `qr_code` value.
- [x] **Asset Documents tab built** — the manual, the wiring diagram, the
      calibration certificate. Upload already existed at the API
      (`AssetDocumentApiController::store`); delete runs through the
      generic `DELETE /api/v1/files/{file}` (`FileApiController::destroy`)
      rather than a new asset-specific endpoint — that one already
      permission-checks per `attachable_type` and guards the one real
      foreign-key case (a file still named by a work-order checklist
      result) correctly, so adding a second delete path would only
      duplicate it. New `frontend/src/components/assets/documents-tab.jsx`
      (upload form + delete, mirrors `work-orders/attachments-tab.jsx`'s
      already-working pattern) wired as a new tab in `AssetDetailTabs`.
      Verified live: the tab label renders, no console/compile errors.
- [x] **Breakdown's three deferred features built**: `record_arrival`,
      `raise_work_order`, and the failure-analysis timestamp correction.
      The first two needed real API endpoints that never existed
      (`POST /breakdowns/{id}/arrive`, `POST /breakdowns/{id}/work-order`
      — both thin wrappers around `TransitionBreakdown::recordArrival`/
      `RaiseBreakdownWorkOrder::handle`, ADR-003); the timestamp correction
      **already had one** (`PATCH /breakdowns/{id}` → `update()`) — only
      the frontend was missing, a stale doc comment claiming otherwise.
      `BreakdownApiController::detail()` now also returns a `timestamps`
      map (all seven `Breakdown::TIMESTAMP_CHAIN` fields) and `is_terminal`,
      so the frontend doesn't need a second round trip to the `downtime`
      endpoint to know whether arrival is already recorded.
      New `frontend/src/components/breakdowns/breakdown-timeline-tab.jsx`
      (mirrors `_chain.blade.php`'s per-field edit-pencil pattern exactly)
      as a new "Timeline" tab; "Record arrival" and "Raise work order"
      buttons added to `breakdown-actions.jsx`, status/permission-gated
      the same way the four already-built transitions are (a 403 on
      submit enforces the actual permission, not a client-side guess).
      Covered by 3 new tests in `tests/Feature/Api/BreakdownApiTest.php`
      (arrival recorded separately from repair start; a second work order
      can be raised against an already-in-repair breakdown; raising one
      against a closed breakdown is refused with 409) — 3/3 passing.
      Verified live end-to-end via real API calls: arrival recorded,
      a second work order raised (`WO-DHK-202609-00011`), and a
      timestamp backdated, all through the actual running stack.
      **Also found and removed while here**: `frontend/src/lib/
      breakdown-transitions.js` was dead code (zero imports anywhere) with
      a docblock claiming assign/hold/resume/cancel/close were "deferred"
      for lack of an API — false; `breakdown-actions.jsx` already has all
      of them fully wired and working. Deleted rather than left to mislead
      the next person who greps for what's missing.
- [x] **Work Order create form's Template field built.** The API already
      accepted `template_version_id` (`CreateWorkOrder`'s own handling);
      `WorkOrderApiController::formOptions()` just never returned the
      template list Blade's create form uses, mirrored in now exactly
      (`MaintenanceTemplate::availableTo()`, published versions only via
      `currentVersion()`). Field added to `work-order-form.jsx`, sent
      through `actions.js`'s existing generic `Object.fromEntries(formData)`
      unchanged. Covered by 3 new tests in `tests/Feature/Api/
      WorkOrderApiTest.php` (form-options lists only templates with a
      published version, a draft-only template is excluded, a work order
      created from a template actually inherits its checklist) — 3/3
      passing.
      **Also found while here, fixed as a one-line doc comment**: the
      Inventory Spare Part detail page's own docblock claimed "create/edit
      and compatibility management are deferred" — false; both are fully
      built (`inventory/parts/create/`, `[partId]/edit/`, and
      `addCompatibility`/`deleteCompatibility` wired into the detail page
      already). Comment corrected so it stops misleading the next person
      auditing this doc.
- [x] **Five user-reported bugs fixed in one pass** (screenshots of the
      live app on a mobile viewport):
      1. **Mobile action-bar layout.** 8 detail pages built their header
         actions as `<div className="flex items-center gap-2">` (no wrap)
         around an already-wrapping inner action group plus a "Back"
         link — on narrow screens the inner group wrapped to multiple
         rows while "Back" stayed pinned beside it, reading as
         disconnected/floating. Added `flex-wrap` to all 8. Separately,
         Breakdown's Cancel button used a bare `ml-auto` (pushes to the
         far right even on a single-column mobile layout, matching the
         screenshot exactly) — changed to `sm:ml-auto`, the same
         responsive pattern `company-logo-form.jsx` already used elsewhere
         in this codebase for the identical problem.
      2. **"Raise work order" created a new one on every click.**
         `RaiseBreakdownWorkOrder` only ever checked `isTerminal()` on the
         *breakdown* — nothing stopped a second (or third) work order
         while the first was still open. Added a guard refusing a new one
         while any non-terminal work order already exists (409, the
         existing one's number in the message); the frontend button now
         hides itself in that state instead of surfacing the 409. Real
         work over a breakdown's lifetime (a second repair pass after the
         first closes) is still allowed — only *concurrent* opens are
         refused. 2 new tests + `tests/Feature/Api/BreakdownApiTest.php`'s
         existing "second work order" test rewritten to close the first
         before raising a second; 82/82 breakdown tests passing.
      3. **Breakdown/Work Order "look like duplicates."** Not a code bug —
         confirmed by reading both: a breakdown's linked work order is
         embedded (checklist/labor/parts tabs) so a technician works from
         one screen instead of being sent to a separate Work Order page,
         by design (see the `show()` docblock in `BreakdownApiController`).
         The actual duplication the user was seeing was #2 above — fixed.
      4. **Request-a-part / Issue-a-part flow.** A `REQUESTED` line had no
         action of its own — the store had to separately re-key the same
         part/bin/quantity into the standalone Issue form, disconnected
         from the request it was answering. The API already had a
         purpose-built endpoint for this (`POST /work-orders/{wo}/parts/
         {line}/issue`) that no UI anywhere called. Added an inline
         bin+quantity+Issue control on `REQUESTED` rows in
         `parts-tab.jsx` (shared by both Breakdown and Work Order, so one
         fix covers both) wired to a new `issueRequestedPart` action.
         Verified live: request → issue-against-that-line → status flips
         REQUESTED → ISSUED with the correct quantities, through the real
         API.
      5. **Raw decimal values ("22.0000 PCS" instead of "22 PCS").**
         Quantity/currency columns are `decimal:4`-cast in Laravel and
         were printed verbatim. New `frontend/src/lib/format.js`
         (`formatNumber`/`formatQuantity`/`formatCurrency` —
         `maximumFractionDigits` without a minimum is what drops trailing
         zeros while still showing a real fraction like "1.5 M"; money
         always keeps 2 decimals) applied across Inventory (stock
         balances, low-stock, part requests, the parts tab), Metering
         (current value, readings), and Costs (replacing three different
         local, inconsistent one-off formatters). Verified with a real
         Playwright browser test (not just SSR/API inspection — see
         Phase H's Playwright entry for why that distinction mattered
         here): a spare part's "On hand" figure renders without
         `.0000`.
      **Also found and fixed while building that Playwright verification**
      (unrelated to the 5 reported bugs, but blocking any real browser
      check of them): see Phase H's Playwright entry — Next.js's
      `allowedDevOrigins` was silently breaking hydration for every
      Docker-network origin, so no automated browser test could have ever
      passed before this session, regardless of what the app itself did.
- [x] Blade views for every module above are still fully present and
      untouched, as expected — Phase H (decommission) hasn't started and
      shouldn't have yet.

## Phase E — Offline PWA (Technician-Facing)

**Foundation real and present; verification currently broken.**

- [x] `frontend/public/sw.js`, `frontend/public/offline.html`,
      `frontend/src/lib/offline/{db.js,queue.js,resize-image.js,
      use-offline-queue.js}`, `frontend/src/app/api/offline-relay/route.js`,
      and `frontend/src/components/offline/` all exist and match the
      intended architecture (httpOnly-cookie BFF, idempotency keys, an
      allowlisted relay).
- [x] Same fix as Phase C — `npm run test` proves three retries of a
      dead-connection submit produce exactly one record (see Phase C for
      the tooling detail).

## Phase F — Realtime and Coexistence

**Coexistence routing mechanism: done. Realtime wiring: done for its first consumer.**

- [x] **First real Echo/Reverb consumer wired**: `frontend/src/components/
      breakdowns/breakdowns-live-updates.jsx` subscribes to `company.
      {companyId}` for `.breakdown.reported` (toasts + `router.refresh()`),
      mounted from `frontend/src/app/(app)/breakdowns/page.js`. Chosen
      first because `BreakdownReported`'s own docblock calls it "the event
      that most justifies having websockets at all."
      **Real infrastructure bug found and fixed along the way**: the dev
      `queue` service in `docker-compose.yml` ran plain `queue:work` with
      no `--queue` flag, so it only ever drained the `default` queue —
      never `broadcasts`, the dedicated queue `AdvisoryBroadcast` puts
      every `ShouldBroadcast` event on (by design, so a down websocket
      server can't delay real work). Every broadcast event in the app,
      not just this one, was being queued and silently never delivered.
      `docs/11-Deployment.md` §5 already documented the correct production
      command (`--queue=default,broadcasts`) — the dev Compose file had
      just never matched it. Fixed by adding the flag; confirmed via
      `docker compose logs queue` that the stuck backlog (thousands of
      jobs going back to 2026-09-08/09, unrelated to this fix — see
      below) flushed immediately, and that fresh `BreakdownReported` jobs
      now run and complete (`DONE`, 0 added to `failed_jobs`). Reverb's
      own container has been up throughout; it logs connections, not
      individual REST-API publishes, so no Reverb log line is expected
      from a backend-only test — the job's `DONE` status is what confirms
      the publish call to Reverb succeeded.
      **Separately noticed, not caused by this fix**: `failed_jobs` holds
      4,513 pre-existing rows, all dated 2026-09-08 09:39 to 2026-09-09
      04:12 — old debt from before this session, untouched here since
      clearing it wasn't asked for.
      Other screens (e.g. a live asset-status or work-order feed) can
      reuse the same `useEcho` hook the same way; only Breakdown got a
      consumer this pass.
- [x] **Reverse-proxy coexistence routing built** in
      `docker/nginx/default.conf`. `resolver 127.0.0.11 valid=10s` +
      `set $frontend_upstream http://frontend:3000` defer hostname
      resolution to request time (needed because the dependency runs the
      other way — `frontend` depends_on `nginx` for its own server-side
      API calls — so nginx routinely starts before the frontend container
      exists; a bare `proxy_pass http://frontend:3000` resolves once at
      nginx's own startup and would crash-loop until frontend is up).
      Live now: `/_next/` (every cut-over page's own JS/CSS bundle) and
      Next's own Route Handlers (`/api/auth/*`, `/api/broadcast-auth`,
      `/api/offline-relay`, `/api/files/*`, `/api/report-jobs/*` —
      distinct from Laravel's `/api/v1/*`, which keeps working unchanged
      via nginx's longest-prefix-match). Verified directly: `/api/v1/
      health` still 200s from Laravel, `/_next/static/...` and `/api/
      auth/me` now reach the Next.js container (404/401 respectively —
      real answers from Next, not Blade's fallback), `/login` (Blade)
      still 200s.
      **Deliberately not done here**: no actual module's page path
      (`/assets`, `/breakdowns`, etc.) is proxied yet — that's each
      module's own Phase H dark-launch decision, not something this
      infrastructure step should decide on its own. A commented example
      block in the same file shows the exact three-line addition each
      cutover needs.

## Phase G — Infrastructure

**Further along than the previous version of this document claimed —
Docker/Postgres/Redis/Reverb are all real, wired services.**

- [x] `docker-compose.yml` and `docker/{nginx,node,php,postgres}` define
      Postgres, Redis, nginx, the Laravel app (PHP-FPM), a queue worker, a
      scheduler, Reverb, and the Next.js frontend as services. The PHP
      image compiles the `redis` extension.
- [x] Redis is now the default application configuration —
      `.env`/`.env.example` set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`,
      `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379` (the host-reachable address
      Compose publishes; the container-side override to the `redis` service
      hostname is unaffected). `SESSION_DRIVER` deliberately left on
      `database` — Compose's own environment block never overrides it
      either, so that's an existing, intentional choice, not an oversight.
      Verified live: `config('cache.default')`/`config('queue.default')`
      both report `redis`, and a real `Cache::put`/`Cache::get` round-trip
      succeeds.
- [x] **No `predis` package needed — this was a false gap.** `config/
      database.php`'s redis client already defaults to `phpredis`
      (`env('REDIS_CLIENT', 'phpredis')`), and `docker/php/Dockerfile`
      compiles the native `redis` PECL extension into the image. Predis
      (a pure-PHP client) would be redundant, not missing.
- [x] **Laravel Horizon installed and running.** `docker-compose.yml`'s
      `queue` service now runs `php artisan horizon` instead of a plain
      `queue:work` — same Redis queues drained (`config/horizon.php`'s
      `queue` list is `['default', 'broadcasts']`, deliberately kept in
      sync with the exact flag the plain worker needed, see Phase F),
      plus a dashboard at `/horizon`. Access is gated on `is_platform_admin`
      via `HorizonServiceProvider::gate()` (`viewHorizon`) — the same
      tenant-less-staff boundary `EnsurePlatformAdmin` already uses,
      not a hand-maintained email list; the gate is bypassed in
      `local`/`testing` per Horizon's own default, which is why it's
      reachable now without a platform-admin session. `horizon:snapshot`
      added to `routes/console.php` on a 5-minute schedule so the metrics
      graph actually has data. Verified live: container logs "Horizon
      started successfully", `/horizon/api/stats` returns 200, and a
      real `BreakdownReported` broadcast job ran and completed under
      Horizon's supervisor.

## Phase H — Cutover and Decommission

**First dark-launch started: Metering.** `location /metering` added to
`docker/nginx/default.conf`, verified live (matches the direct Next.js
container's own response). Chosen for the same reason the plan's own
Progress section names it: smallest API gap, simple append-only-ledger
shape, not on the technician time-critical offline path.

- [x] `/metering` (Next.js) is reachable through the coexistence proxy.
- [x] **`/metering` vs. Blade's `/app/meters` resolved: redirect, decided
      by the user.** Parity was confirmed first (record reading,
      replace/reset, reading history, and the `asset_id` filter used from
      an asset's own detail page — the last one was a real, small gap:
      Next's `/metering` page didn't accept it yet, fixed in
      `frontend/src/app/(app)/metering/page.js` and `meters-table.jsx`
      alongside this).
      `app/Modules/Metering/Routes/web.php`'s `GET /app/meters` and
      `GET /app/meters/{meter}` now redirect to `/metering` and
      `/metering/{id}` (query string, i.e. `?asset_id=`, preserved) instead
      of rendering Blade views — the POST action routes (`readings`,
      `reset`, `toggle`, `attach`) are untouched since nothing links to
      them directly. The sidebar's "Meters" item now points straight at
      `/metering` (`app/Shared/Navigation/SidebarMenu.php` gained a `url`
      key alongside the existing `route` key, resolved in
      `resources/views/components/layout/sidebar.blade.php` — a small,
      reusable addition for any future dark-launched module's nav item,
      not a `/metering`-only hack) rather than bouncing through the
      redirect. Verified live end-to-end via a real login + cookie-jar
      curl session: `/app/meters`, `/app/meters/{id}`, and `/app/meters?
      asset_id=...` all 302 to the correct `/metering` URL, `asset_id`
      intact.
      `tests/Feature/Metering/MeterReadingTest.php`'s two Blade-rendering
      tests (formerly `test_another_companys_meter_is_not_reachable`,
      `test_the_list_shows_a_meter_nobody_has_read`) broke as a direct,
      expected consequence — they asserted content the route no longer
      renders. Renamed and rewritten to assert the redirect itself instead
      of being deleted; the tenant-isolation and listing/scoping
      guarantees they used to check are still covered, now at the layer
      that actually enforces them (`tests/Feature/Api/MeterApiTest.php`'s
      `test_show_404s_for_a_meter_outside_reachable_factories` and
      `test_all_meters_lists_across_the_callers_reachable_factories`).
      `vendor/bin/phpunit --filter=Meter tests/Feature`: 29/29 passing.
- [x] **Playwright scaffolded and verified working** — not a full suite,
      the harness proven end-to-end so the real tenant-isolation/offline-
      retry tests the plan calls for have something to be added to.
      `frontend/playwright.config.js` + `frontend/e2e/login.spec.js` (a
      real login against a seeded `DemoTenantSeeder` account). Runs via
      `docker compose --profile test run --rm playwright` — a dedicated
      service in `docker-compose.yml` (profile `test`, never part of the
      default stack) on the official `mcr.microsoft.com/playwright` image,
      because Playwright's browsers need glibc and `frontend`'s own image
      is Alpine/musl. Points at the Next.js container directly (port
      3000 in-network), not the coexistence nginx port — `/login` itself
      isn't dark-launched yet, only `/metering` and a few Route Handlers
      are, so testing through nginx would exercise Blade's login instead.
      Verified live: `1 passed (25.0s)`.
      **Real bug found running it for real the first time**: every
      Playwright run failed with the login form silently falling back to a
      native GET submission (password landing in the URL query string) —
      traced to Next.js 15+'s `allowedDevOrigins` dev-safety check
      blocking `/_next/hmr` (and enough of the rest of the dev bundle to
      break hydration) for any Host header other than `localhost`; neither
      the `frontend` container hostname nor the `host.docker.internal`
      alias Playwright reaches the host-published port through is on that
      list by default. Fixed in `frontend/next.config.mjs`
      (`allowedDevOrigins: ["frontend", "host.docker.internal"]`).
      Confirmed this was Docker-network-specific, not something a real
      browser at `http://localhost:3010` would hit, since "localhost" is
      allowed by default — still worth fixing so Playwright (and anyone
      else reaching the dev server through Docker) works at all. Also
      bumped the suite's per-test timeout from the 30s default to 45s:
      Turbopack dev-mode compiles each route on its first hit (~5-8s
      observed live for the dashboard), tight against the default once a
      test chains a login onto a second page visit.
      **Not done**: the tenant-isolation and offline-retry acceptance
      tests the plan actually calls for (ADR-059, Phase E) — those need
      someone to design the scenarios, not just working tooling.
- [ ] No soak-time/error-rate tracking exists yet, and nothing has been
      decommissioned — correctly so this early. This needs an external
      observability tool (Sentry, a hosted logs/metrics service, etc.)
      this environment doesn't have configured — a real infrastructure
      decision, not something to stub in silently. Horizon (Phase G) at
      least gives queue-side visibility in the meantime.
      The soak/rollback rehearsal itself, and dropping `/meters` entirely
      (now that it redirects rather than decommissioning it outright),
      both still come after this dark-launch has actually run for a while.
