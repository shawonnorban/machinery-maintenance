# Dashboard

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টা স্ক্রিনই চালু: Prototype Dashboard, Sample 360° View, Notifications & Alerts)
> **মডিউল:** [Prototype Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `07/02`

**⬅ ফিরে যান:** [Prototype Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Prototype Dashboard | `/m/sample/prototype-dashboard` | 1 | 0 | 0 |
| 2 | Sample 360° View | `/m/sample/sample-360-view` | 2 | 20 | 0 |
| 3 | Notifications & Alerts | `/m/sample/sample-alerts` | 1 | 7 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Prototype Dashboard

`/m/sample/prototype-dashboard`

**ভিউ**
> LIVE 01 of 23
- সেকশন: Sample Status, Approval & Revision Rate, Sample Room Capacity, Daily Target vs Achievement, Department-wise Pending, Buyer-wise Samples, Style-wise Samples, Sample Type-wise, Sample Stage-wise, Delay Analysis, Sample Performance, Performance Indicators, Top Open Alerts, Recent Timeline Activity, Delayed Samples
- বাটন/অ্যাকশন: Status, Stage, Reason

#### Sample 360° View

`/m/sample/sample-360-view`

**ট্যাব: Sample 360°**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition Month -- All Months -- | select |  |  |
| Sample Type -- All Sample Types -- Proto Sample (PROTO) Fit Sample (FIT) Size Se | select |  | Proto Sample (PROTO), Fit Sample (FIT), Size Set Sample (SIZESET), PP Sample (PP), TOP Sample (TOP), Shipment Sample (SH |
| Stage -- All Stages -- Requested Development Material Production Quality Approva | select |  | Requested, Development, Material, Production, Quality, Approval, Revision, Handover, Delivery, Completed |
| Status -- All Statuses -- Draft Pending Approved In Progress On Hold Completed R | select |  | Draft, Pending, Approved, In Progress, On Hold, Completed, Rejected, Cancelled, Delayed |
| Priority -- All Priorities -- Low Normal High Urgent Critical | select |  | Low, Normal, High, Urgent, Critical |
| Company -- All Companies -- Brickland Composite Ltd. | select |  | Brickland Composite Ltd. |
| All | radio |  |  |
| Open | radio |  |  |
| Overdue | radio |  |  |
| Completed | radio |  |  |
| My Samples | search |  | Sample / Style / Order / Buyer / Bundle / Req. / MR / Handover No… |
| My Samples | checkbox |  |  |
| Material Pending | checkbox |  |  |
- সেকশন: Order nature
- বাটন/অ্যাকশন: Sample Register 0, Sample 360°, Sample Documents, Search, All

**ট্যাব: Sample Documents**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing document | select |  |  |
| Sample* | select | ✔ | Select… |
| Version | text |  |  |
| Title* | text | ✔ |  |
| File* | text | ✔ | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: Sample Documents, Versions
- বাটন/অ্যাকশন: Sample Register 0, Sample 360°, Sample Documents, Select…, Clear, Save Document

#### Notifications & Alerts

`/m/sample/sample-alerts`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Severity | select |  | All, Critical, Warning, Info, Success |
| Kind | select |  | All, Sample Assigned, Sample Overdue, Material Required, Material Shortage, Material Issued, Production Ready, Qc Requir |
| State | select |  | All, Open, Resolved |
| Raised from | date |  |  |
| Raised to | date |  |  |
| Search Prototype Desk alerts with severity, kind, sample, the person they are fo | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Raised, Kind, Title, Message, Sample No, For, Doc No, Read, Resolved, ACTION
- বাটন/অ্যাকশন: Clear, Export, Raised, Kind, Title, Message, Sample No, For, Doc No, Read, Resolved
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **07/03-এর সব document:** requisition আর তার সিদ্ধান্ত, development, revision, material requisition আর issue, bundle card, day sheet, QC, approval, feedback, handover। Dashboard আর 360° নতুন কোনো কাজের ডাটা রাখে না; এগুলো থেকে পড়ে।
- **ফাইল:** `StoreFileAttachment` (ছবি বা PDF, সর্বোচ্চ ১০ MB) আর `/api/files/{id}/download`। ডাউনলোডের অনুমতির তালিকায় `proto_document` যোগ হয়েছে (`prototype.sample360.view` / `.manage`)।
- **Chart:** recharts আর ai-kit-এর `Panel`, `RankList`। রঙ আসে নতুন `--series-1..3` token থেকে (dataviz palette-এর প্রথম তিনটা slot, validator-এ সব জোড়ায় পাস)।

## ৪. ডাটা মডেল (Entity ও Field)

- **`proto_events`** (ঘটনাক্রম আর audit trail): sample_id (desk-এর নিজের ঘটনায় খালি), action, params (json), doc_no, stage আর status (ঘটনার পরে স্যাম্পল যে ধাপ আর অবস্থায় দাঁড়াল), by_name, by_user, happened_at। শিরোনাম আর বিবরণ সংরক্ষিত হয় না; action আর params থেকে পড়ার সময় পাঠকের ভাষায় লেখা হয় (`prototype.event.*`, `prototype.event_text.*`)।
- **`proto_alerts`:** kind (১৬টা), severity (CRITICAL/WARNING/INFO/SUCCESS), sample_id, params, for_desk (DESK/MERCHANDISING/SAMPLE_ROOM/STORE/PRODUCTION/QC), doc_no, raised_at, read_at, resolved_at, resolved_by।
- **`proto_documents`:** doc_no (`SDC-2026-00001`), sample_id, doc_type (১২টা), version, title, file (পাথ বা লিংক) অথবা file_id + file_name + file_size, remarks, uploaded_by। unique (sample_id, doc_type, version)।
- **Lifecycle** (`SampleLifecycle`, কোনো টেবিল নয়):
  - **Stage:** Requested → Development → Material → Production → Quality → Approval → Revision → Handover → Delivery → Completed। phase/state, material requisition আর bundle card থেকে বের হয়।
  - **Status:** Draft, Pending, Approved, In Progress, On Hold, Completed, Rejected, Cancelled, Delayed। দেরিতে থাকা খোলা স্যাম্পল Delayed দেখায়; On Hold আর Rejected যেমন আছে তেমন থাকে।
  - **Progress:** ধাপের index ÷ ৯। সাথে overdue, days_late, material_pending আর flag (Overdue / Material / Urgent / Critical)।

## ৫. Workflow / Status ও Business Rules

- **Ledger (`SampleLedger`):** 07/03-এর প্রতিটা controller প্রতিটা ধাপ লিখে রাখে: requisition তৈরি, জমা, প্রতিটা স্তরের অনুমোদন বা প্রত্যাখ্যান, SMP তৈরি, assign, development, revision, QC, approval, feedback, handover, MR-এর প্রতিটা ধাপ, issue, bundle card, স্ক্যান আর document।
  - From → To দেখায় শুধু ধাপ বা অবস্থা বদলালে, যেমন "Quality · Pending → Approval · Pending"।
  - Sample Overdue ঘটনায় দেখায় "On time → Overdue"।
- **Alert একবারই ওঠে:** একই kind আর একই document-এর একটা খোলা থাকলে নতুন ওঠে না। কাজ হয়ে গেলে নিজে থেকে মীমাংসা হয়:
  - Development শুরু হলে SAMPLE_ASSIGNED মীমাংসা হয়।
  - QC পাস করলে QC_REQUIRED মীমাংসা হয়, আর APPROVAL_PENDING ওঠে।
  - সিদ্ধান্ত হলে APPROVAL_PENDING মীমাংসা হয়। Reject বা Revision Required-এ REVISION_REQUIRED ওঠে; buyer অনুমোদন দিলে SAMPLE_APPROVED আর HANDOVER_READY ওঠে।
  - Handover-এ HANDOVER_READY মীমাংসা হয়; Delivered-এ SAMPLE_DELIVERED, Received-এ SAMPLE_COMPLETED ওঠে।
  - MR জমা হলে MATERIAL_REQUIRED ওঠে, ঘাটতি থাকলে MATERIAL_SHORTAGE। পুরো ইস্যু হলে দুটোই মীমাংসা হয়, আর MATERIAL_ISSUED ওঠে।
  - Bundle card ইস্যু হলে PRODUCTION_READY ওঠে। Day sheet সক্ষমতার বেশি হলে CAPACITY_OVERLOAD ওঠে।
- **Sweep:** Alerts বা Dashboard খুললে চলে।
  - প্রয়োজনের তারিখ পেরোনো খোলা স্যাম্পলে SAMPLE_OVERDUE (Critical) একবারই ওঠে, আর একটা "Sample Overdue" ঘটনা লেখা হয়। স্যাম্পল বন্ধ হলে alert মীমাংসা হয়।
  - আগের দিনের day sheet লক্ষ্যের কম হলে TARGET_MISSED ওঠে, doc `TGT-2026-10-22`।
- **হাতে কাজ:** Mark Read; Resolve (মীমাংসিত alert-এ আটকায়); Reopen (শুধু মীমাংসিতে চলে)।
- **Document:** নতুন document সেই স্যাম্পল আর ধরনের পরের ভার্সন হয় (Tech Pack V1, V2…), আগেরগুলো থেকে যায়।
  - পাথ বা লিংক, অথবা ফাইল, দুটোর একটা বাধ্যতামূলক।
  - সেভ হওয়া document-এ শুধু শিরোনাম আর মন্তব্য বদলায়।
  - একই সময়ে দুটো আপলোড হলে স্যাম্পলের সারি lock থাকে, তাই একই ভার্সন দুবার হয় না।
- **Dashboard-এর সময়সীমা:** Last 7 / 30 / 90 days, This year, All time (ডিফল্ট ৩০ দিন)।
  - অনুরোধ, অনুমোদন, ডেলিভারি, বিভাজন আর KPI এই সময় ধরে হিসাব হয়।
  - "Open now" ট্যাইল ডেস্কের বর্তমান অবস্থা দেখায়। "Completed Today" দেখায় আজকের।
  - Sample Room Efficiency = অর্জিত ঘণ্টা ÷ প্রকৃত ঘণ্টা ("19.2 earned of 24 actual hours")।

## ৬. API ও পারমিশন

পারমিশন: `prototype.dashboard.view`, `prototype.sample360.view|manage`, `prototype.alerts.view|manage`। FACTORY_ADMIN সব পান; FACTORY_MANAGER শুধু view পান।

| Method | Path |
|---|---|
| GET | `/prototype/dashboard?range=…` (7d / 30d / 90d / year / all) |
| GET | `/prototype/samples` (Register), `/prototype/samples/{SMP-…}` (পুরো 360°) |
| GET | `/prototype/documents/options`, `/prototype/documents`, `/prototype/documents/{SDC-…}` (তার সব ভার্সনসহ) |
| POST | `/prototype/documents` (multipart: sample_id, doc_type, title, file অথবা upload, remarks) |
| PUT | `/prototype/documents/{SDC-…}` (title, remarks) |
| GET | `/prototype/alerts` (sweep সহ; alerts, kinds, severities) |
| POST | `/prototype/alerts/{id}/read`, `/resolve`, `/reopen` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Prototype Desk → **DASHBOARD** (৩), MASTER DATA-র আগে।

- **Prototype Dashboard:**
  - **উপরে:** সময়সীমার বোতাম; বদলালে পাতা রিলোড হয় না, শুধু URL-এ `?range=7d` বসে। কী হিসাব হচ্ছে তার এক লাইনের নোট। ১১টা স্ক্রিনের লিংকের সারি।
  - **ট্যাইল:** ১৬টা, প্রতিটায় icon আর "Last 30 days / Open now / আজকের তারিখ"। Delayed থাকলে "Late" ব্যাজ।
  - **প্যানেল:**
    - Sample Status (Status/Stage আর Chart/Table toggle); Approval & Revision Rate; Sample Room Capacity (Within/Over ব্যাজ, Draft · Day)।
    - Daily Target vs Achievement (Target আর Achieved-এর grouped bar, legend আর tooltip)।
    - Department-wise Pending, Buyer-wise, Style-wise, Sample Type-wise, Sample Stage-wise।
    - Delay Analysis (Reason/Stage); Sample Performance (১২ মাসের তিনটা লাইন: Requested, Completed, Approved)।
    - Performance Indicators ১২টা ("Higher/Lower is better" সহ)।
    - Top Open Alerts (severity-র icon আর লেখাসহ); Recent Timeline Activity; Delayed Samples টেবিল (স্যাম্পলে ক্লিক করলে তার 360° খোলে)।
- **Sample 360° View:** ট্যাব Sample Register · Sample 360° · Sample Documents।
  - **Register:**
    - **ফিল্টার:** Requisition Month, Sample Type, Stage, Status, Priority, Company।
    - **radio আর অন্যান্য:** All / Open / Overdue / Completed; খোঁজা (স্যাম্পল, স্টাইল, অর্ডার, বায়ার, বান্ডেল, রিকু., MR, হ্যান্ডওভার নং); My Samples; Material Pending; বায়ারের সারি।
    - **কলাম:** Status, Sample No / Req No, Ver., Style, Buyer, Order, Sample Type, Stage, Progress, Required, Late ("13 d overdue"), Responsible, Flags। সাথে Print।
  - **সারি খোলা:** সারি খুললে নিচেই Sample 360° আসে: সারাংশ (২১টা ঘর) আর ১৭টা সেকশন (Timeline, Requisition Approvals, Development Checklist, Development Versions, Revision History, Material Requirement, Material Issues, Bundle Cards & Bundles, Daily Target Lines, Bundle Tracking, Quality Checks, Approvals, Buyer Feedback, Handover / Delivery, Documents, Alerts, Audit Trail)। URL-এ বসে `?tab=view&sample=SMP-2026-00001`।
  - **Sample Documents:** Existing document; Sample*, Document Type*, Version (সেভে বসে), Title*, File* (পাথ বা লিংক), Attach (ছবি বা PDF), Remarks। নিচে Versions টেবিল; নোট, Clear আর Save Document। ফাইল যায় `/api/prototype/documents` route দিয়ে stream হয়ে (server action-এর ১ MB সীমা এড়াতে)।
- **Notifications & Alerts:**
  - **pill:** Critical / Warning / Open / Unread / Resolved Today।
  - **ফিল্টার:** Severity, Kind, State, Raised from–to।
  - **কলাম:** Raised, Kind, Title, Message, Sample No, For, Doc No, Read, Resolved, Status।
  - **সারির বাটন:** Mark Read · Resolve · Reopen, পাতা রিলোড ছাড়া।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Prototype/Database/Migrations/2026_12_15_000100_create_prototype_ledger.php`, `Models/SampleEvent.php`, `SampleAlert.php`, `SampleDocument.php`, `Support/SampleLifecycle.php`, `SampleLedger.php`, `SampleWording.php`, `Http/Controllers/Api/PrototypeDashboardApiController.php`, `Sample360ApiController.php`, `SampleDocumentApiController.php`, `SampleAlertApiController.php`, `Routes/api.php`; ledger কল 07/03-এর সব controller-এ; `SampleDaySheetApiController::figures()`-এ earned/actual ঘণ্টা |
| ফাইল | `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (`proto_document`) |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`PROTOTYPE.DASHBOARD`, তিনটা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/prototype-desk/[screen]/sample-dashboard.js`, `page.js`, `actions.js` (`deskProto`), `frontend/src/app/api/prototype/documents/route.js`, `frontend/src/components/prototype/desk-dashboard.jsx`, `sample-360.jsx`, `alerts.jsx`, `frontend/src/app/globals.css` (`--series-1..3`) |
| ভাষা | `lang/{en,bn}/prototype.php` (event, alert, lifecycle, desk, doc_type, `ui.dash`, `ui.s360`, `ui.docs`, `ui.al`) |
| টেস্ট | `tests/Feature/Prototype/SampleDashboardLedgerTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ:** Prototype Reports (07/04, 07/05)।

## ৯. টেস্ট ও যাচাই

- **`SampleDashboardLedgerTest`:** ৪টা টেস্ট।
  - **Ledger আর alert:**
    - Development শেষে QC_REQUIRED (QC desk, Info); QC পাসে সেটা মীমাংসা হয়ে APPROVAL_PENDING ওঠে; buyer অনুমোদনে SAMPLE_APPROVED আর HANDOVER_READY ওঠে।
    - Handover Delivered আর Received-এ SAMPLE_DELIVERED আর SAMPLE_COMPLETED ওঠে।
    - Timeline-এর ক্রম, শেষ ধাপ COMPLETED, কে করেছেন; QC ঘটনায় "QUALITY|PENDING → APPROVAL|PENDING"; বাংলায় "রিকুইজিশন তৈরি"।
    - Read/Resolve/Reopen আর তাদের বাধা; অনুমতি (Technician দেখেন না; Factory Manager দেখেন কিন্তু মীমাংসা করতে পারেন না)।
  - **Document:**
    - ফাঁকা Save-এর বার্তা; পাথ বা ফাইল ছাড়া আটকায়।
    - লিংক দিয়ে V1; PDF আপলোডে V2; অন্য ধরন আবার V1 থেকে শুরু; ভার্সনের তালিকা।
    - Retitle-এ ভার্সন আর ফাইল বদলায় না।
    - ফাইল ডাউনলোড desk-এর অনুমতিতে (Technician পান না); 360°-এ document আর "attached as SDC-2026-00002 (V2)"।
  - **360°:** Register-এর সারি (Quality · Pending, My Samples, মাস); পুরো view-এ development, তিনটা সিদ্ধান্ত, alert; অচেনা স্যাম্পলে 404।
  - **Dashboard আর sweep:**
    - ট্যাইল, by_desk {QC: 1}, buyer, ১২ মাস, alert।
    - প্রয়োজনের তারিখ পেরোলে Delayed ১, ৩ দিন দেরি, Critical ১।
    - তিনবার খুললেও SAMPLE_OVERDUE আর তার ঘটনা একবারই; Register-এ DELAYED।
- **রিগ্রেশন:** Prototype-এর ১৭টা টেস্ট সবুজ (৭৩৯টা assertion)। Prototype, ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, IconCatalogue, ErpMasterDataSeeder আর File মিলিয়ে ৯২টা টেস্ট, ১০৯১টা assertion। ৭টা error সবই FileApiTest আর FileAttachmentTest-এর `UploadedFile::fake()->image()` থেকে: container-এর GD-তে JPEG সমর্থন নেই (`imagejpeg` নেই)। এটা পরিবেশের সমস্যা, এই কাজের বদল নয়।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner লগইন):
  - সাইডবারে DASHBOARD (৩) ✔
  - **Dashboard:** ১৬টা ট্যাইল, সব প্যানেল, KPI, alert ✔। Last 7 days আর All time-এ পাতা রিলোড ছাড়া লেবেল আর সংখ্যা বদলায় ✔। Console-এ কোনো error নেই।
  - **Sample Documents:**
    - SMP-2026-00001, Tech Pack, "Summer Set tech pack", PDF আপলোড → SDC-2026-00001 V1, ফাইলের ডাউনলোড লিংক আর Versions ✔
    - Clear করে লিংক দিয়ে "…sleeve shortened" → SDC-2026-00002 V2 ✔
  - **Sample 360°:** SMP-2026-00001 খুললে সারাংশ আর সব সেকশন (MR, Issue, Bundle, Tracking, QC, Approvals, Feedback, Handover, দুটো Document, Timeline আর Audit Trail-এ "Document Attached") ✔
  - **Notifications & Alerts:** TARGET_MISSED (TGT-2026-10-22, Critical); Mark Read → Read Yes, Unread ০ ✔
  - রেকর্ডগুলো dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/proto21.json` (তিনটা স্ক্রিনের লেখা আর স্ক্রিনশট), `proto22.json` (খোলা সারির পুরো 360°)।
- **ledger শুরুর আগের রেকর্ড:** ledger চালু হওয়ার আগের dev রেকর্ডগুলোর ঘটনা নেই, তাই সেগুলোর timeline-এ শুধু পরের ঘটনা (যেমন document) দেখায়। নতুন রেকর্ডে শুরু থেকে সব লেখা হয়।
- **Sweep-এর সময়:** Sweep চলে Alerts বা Dashboard খোলার সময়। আলাদা scheduler এখনো নেই।
- **UI যাচাই আর টেস্টে ধরা পড়েছে:**
  - Postgres-এ `max()`-এর সাথে `FOR UPDATE` চলে না, তাই document-এর ভার্সন নেওয়ার সময় এখন স্যাম্পলের সারি lock হয়।
  - DELAYED অবস্থা কখনো বসত না, এখন বসে।
  - ফাইল ডাউনলোডে `proto_document` ছিল না। থাকলে work order-এর অনুমতি লাগত।
  - Chart-এর legend বর্ণানুক্রমে সাজাচ্ছিল, এখন series-এর ক্রমে।
- **Brickland-এর সাথে মেলানো:** Brickland-এর Sample Register আর Sample 360° দুই ট্যাবেই একই তালিকা দেখায়, সারি খুললে 360° আসে। এখানেও তাই।
