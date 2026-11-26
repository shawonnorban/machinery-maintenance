# Command Center

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টা রেজিস্টার + Command Deck)
> **মডিউল:** [Command Center](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `27/01`

**⬅ ফিরে যান:** [Command Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Departmental Performance Boards | `/m/executive-dashboard/department-dashboards` | 1 | 3 | 1 |
| 2 | Metric Drill-down Explorer | `/m/executive-dashboard/drill-down-explorer` | 1 | 3 | 1 |
| 3 | Dashboard Composer | `/m/executive-dashboard/dashboard-builder` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Departmental Performance Boards

`/m/executive-dashboard/department-dashboards`

**ভিউ**
> 06 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Departmental performance boards | search |  | Search boards… |
| Published only | checkbox |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Board, Tiles, Refresh, Audience, Data health, State
- সেকশন: Departmental boards
- বাটন/অ্যাকশন: New board, Board, Tiles, Refresh, Audience, Data health, State, Unpublish, Publish

**ডায়ালগ: New board** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Board name* | text | ✔ |  |
| Owner | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain, Shamima Akter |
| Tiles | number |  |  |
| Refresh | select |  | Select…, Live, Hourly, Daily, Weekly |
| Audience | select |  | Select…, Board only, Department heads, All managers, Everyone |
| Data health | number |  |  |
- খালি Save-এ মেসেজ: Give the board a name.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Metric Drill-down Explorer

`/m/executive-dashboard/drill-down-explorer`

**ভিউ**
> 11 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Metric drill paths | search |  | Search metrics… |
| Filter by domain | select |  | All domains, Commercial, Production, Quality, People, Finance |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Metric, Domain, Level 1, Level 2, Level 3, Rows
- সেকশন: Drill paths
- বাটন/অ্যাকশন: New drill path, Metric, Domain, Level 1, Level 2, Level 3, Rows

**ডায়ালগ: New drill path** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Metric* | text | ✔ |  |
| Domain | select |  | Select…, Commercial, Production, Quality, People, Finance |
| First level | select |  | Select…, Division, Buyer, Season, Department, Cost centre |
| Second level | select |  | Select…, Style, Line, Order, Machine, Account |
| Third level | select |  | Select…, Shift, Operator, Colour, Size, Day |
| Source module | select |  | Select…, Merchandising, Knit Production, Assembly Floor, Cutting Floor, Accounting, HR & Payroll |
| Finest grain | select |  | Select…, Hourly, Daily, Weekly, Monthly |
| Rows behind it | number |  |  |
- খালি Save-এ মেসেজ: Name the metric.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Dashboard Composer

`/m/executive-dashboard/dashboard-builder`

**ভিউ**
> 12 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Dashboard compositions | search |  | Search compositions… |
| Filter by layout | select |  | All layouts, Two column, Three column, Single column, Grid 2×3, Full width |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Composition, Layout, Tiles, Charts, Shared with, State
- সেকশন: Compositions
- বাটন/অ্যাকশন: New composition, Composition, Layout, Tiles, Charts, Shared with, State

**ডায়ালগ: New composition** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Title* | text | ✔ |  |
| Layout | select |  | Select…, Two column, Three column, Single column, Grid 2×3, Full width |
| Author | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain |
| Tiles | number |  |  |
| Charts | number |  |  |
| Shared with | select |  | Select…, Board only, Division heads, All managers, Everyone |
| State | select |  | Select…, Draft, Released, Archived |
| Notes | textarea |  |  |
- খালি Save-এ মেসেজ: Give the composition a title.
- invalid চিহ্নিত ফিল্ড: 1টা
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **ERP resource kit** আর নতুন শেয়ার করা ছাঁচ:
  - backend `Erp/Support/DemoResource` — পারমিশন, স্টাফ আর বিভাগের তালিকা, শতাংশ, একবারের নাম।
  - frontend `lib/demo-screen.js` — চার টাইল, কার্ড, pick বা check ফিল্টার, row বোতাম।
- **Masters:** বিভাগ (`departments`), স্টাফ (`company_users`), মুদ্রার রেট (`currencies`)।
- **Command Deck-এর উৎস:**
  - Trade & Export-এর কমার্শিয়াল ইনভয়েস (`tx_commercial_invoices`)।
  - Merchandise-এর অর্ডার (`merch_orders`) আর AI সতর্কতা (`merch_ai_alerts`)।
  - Planning-এর দৈনিক প্ল্যান (`planning_daily_plans`, `planning_lines`, `floors`)।
  - Sales & Shipment-এর শিপমেন্ট (`ss_shipments`, `ss_shipment_lines`)।
  - Finance-এর ভাউচার (`acc_vouchers`, `acc_voucher_lines`, `acc_accounts`)।
  - Audit trail (`audit_logs`) আর Approval Center (`approval_requests`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_27_000100_create_command_center_boards`:
- **`cc_boards`:** নাম, বিভাগ, মালিক (স্টাফ), টাইল, হালনাগাদ (Live / Hourly / Daily / Weekly), দর্শক (Board only / Department heads / All managers / Everyone), ডাটার মান (%), প্রকাশিত।
- **`cc_drill_paths`:**
  - মেট্রিক, ক্ষেত্র (Commercial / Production / Quality / People / Finance)।
  - তিন স্তর, প্রতিটা নিজের তালিকা থেকে: ১ — Division / Buyer / Season / Department / Cost centre; ২ — Style / Line / Order / Machine / Account; ৩ — Shift / Operator / Colour / Size / Day।
  - উৎস মডিউল, সবচেয়ে সূক্ষ্ম স্তর, পেছনের সারি (হাজারে)।
- **`cc_compositions`:** শিরোনাম, লেআউট (Two / Three / Single column, Grid 2×3, Full width), লেখক, টাইল, চার্ট, যাদের সাথে শেয়ার, অবস্থা (Draft / Released / Archived), নোট।
- **Command Deck:** নিজের কোনো টেবিল নেই; প্রতিবার উৎস থেকে হিসাব হয়।

## ৫. Workflow / Status ও Business Rules

- **বোর্ড:**
  - প্রতি বিভাগে একটা বোর্ড।
  - টাইল ছাড়া বোর্ড প্রকাশ করা যায় না; প্রকাশিত বোর্ডের টাইল ০ করাও আটকায়।
  - নতুন বোর্ড খসড়া, দৈনিক, বিভাগীয় প্রধানদের জন্য, ডাটার মান ১০০%।
  - সারির বোতাম Publish / Unpublish উল্টে দেয়; খালি বোর্ডে চাপলে কারণসহ ত্রুটি দেখায়।
- **ড্রিল পাথ:** একটা মেট্রিকের একটাই পাথ (বড়-ছোট হাতের পার্থক্য ধরে না)। প্রতিটা স্তর শুধু নিজের তালিকা থেকে।
- **কম্পোজিশন:** শিরোনাম একবারই। খালি (টাইল আর চার্ট দুটোই ০) কম্পোজিশন রিলিজ হয় না; রিলিজড কম্পোজিশনকে খালি করাও আটকায়।
- **Command Deck-এর হিসাব:**
  - **এই মাসের রাজস্ব:** এই মাসের তারিখের কমার্শিয়াল ইনভয়েস (net, না থাকলে invoiced), টাকায়। তুলনা গত মাসের একই দিনগুলোর সাথে।
  - **অর্ডার বুক:** Confirmed / In production অর্ডার, শিপের তারিখ আজ বা পরে, projection নয় — পরিমাণ × দাম, টাকায়।
  - **উৎপাদন দক্ষতা:** এই মাসে (output × SMV) ÷ (জনবল × ঘণ্টা × ৬০)। শুধু output থাকা দিন ধরা হয়। লক্ষ্য = প্ল্যানের দক্ষতা, একই মিনিট দিয়ে ভার দেওয়া।
  - **সময়মতো ডেলিভারি:** গত ৯০ দিনের শিপমেন্টের মধ্যে কতগুলো অর্ডারের শিপ-তারিখে বা আগে গেছে।
  - **রাজস্ব, খরচ ও শিপমেন্ট (১২ মাস):** খরচ = পোস্ট হওয়া ভাউচারে EXPENSE হিসাবে debit − credit। শিপমেন্ট = shipped qty × rate।
  - **বায়ার অনুযায়ী অর্ডার বুক:** আগামী ছয় মাস; সাতজনের পরের সব "অন্যান্য"-তে।
  - **বিভাগ অনুযায়ী উৎপাদন:** শনিবার থেকে আজ পর্যন্ত, ফ্লোর অনুযায়ী দৈনিক লক্ষ্য বনাম output। ভবিষ্যতের দিনের প্ল্যান ধরা হয় না।
  - **সেলাই লাইনের দক্ষতা:** এই মাস, লাইন অনুযায়ী অর্জিত বনাম লক্ষ্য।
  - **সাম্প্রতিক কাজ:** মানুষের করা শেষ ৮টা পরিবর্তন (তৈরি / বদল / মোছা / অবস্থা / খরচ)। সাইন-ইন আর scheduler-এর নিজের কাজ বাদ।
  - **প্রধান সতর্কতা:** খোলা অর্ডার-সতর্কতা (উচ্চ আগে) আর অপেক্ষমাণ অনুমোদন (সময় পেরোলে উচ্চ) — সর্বোচ্চ ৬টা।
  - **মুদ্রা:** টাকায় রূপান্তর ডকুমেন্টের নিজের রেটে, না থাকলে মুদ্রা-মাস্টারের রেটে। কোনো রেট না থাকলে অঙ্কটা বাদ যায় আর সংখ্যা নিচে লেখা থাকে — অনুমান করা হয় না।

## ৬. API ও পারমিশন

পারমিশন: `commandcenter.command.view`, `commandcenter.command.manage` (Factory Admin / Factory Manager; Auditor শুধু দেখেন)।

API:
- `/api/v1/erp/cc-boards`, `cc-drill-paths`, `cc-compositions`।
- `GET /api/v1/command-center/options` — স্টাফ আর বিভাগ।
- `GET /api/v1/command-center/deck` — Command Deck-এর সব সংখ্যা।

## ৭. Frontend পেজ (লাইট থিম)

Route `/command-center/[screen]`; সাইডবারে Overview-এ সবার উপরে "Command Center"।
- **Chief Executive Command Deck** (`command-deck`):
  - চারটা টাইল।
  - চারটা প্যানেল (recharts):
    - রাজস্ব / খরচ / শিপমেন্ট — তিন লাইন, এক অক্ষ।
    - বায়ার অনুযায়ী — আড়াআড়ি বার।
    - বিভাগ অনুযায়ী — পরিকল্পনা বনাম আসল।
    - লাইন অনুযায়ী — অর্জিত বনাম লক্ষ্য।
  - প্রতিটা চার্টে "Table" বোতামে একই সংখ্যার টেবিল।
  - মডিউলের তালিকা (সাইডবারের মতো, প্রতিটার প্রথম স্ক্রিনে যায়), সাম্প্রতিক কাজ, প্রধান সতর্কতা।
  - ডাটা না থাকলে Brickland-এর মতো "No data recorded yet"।
- **Departmental Performance Boards** (`department-dashboards`):
  - কলাম: বোর্ড (নিচে বিভাগ · মালিক), টাইল, হালনাগাদ, দর্শক, ডাটার মান (৯০+ সবুজ, ৭৫+ হলুদ, নিচে লাল), অবস্থা, Publish / Unpublish বোতাম।
  - "Published only" টিক।
- **Metric Drill-down Explorer** (`drill-down-explorer`): মেট্রিক (নিচে উৎস), ক্ষেত্র, তিন স্তর, সারি ("480 k")। ক্ষেত্র দিয়ে ফিল্টার।
- **Dashboard Composer** (`dashboard-builder`): কম্পোজিশন (নিচে লেখক), লেআউট, টাইল, চার্ট, শেয়ার, অবস্থা। লেআউট দিয়ে ফিল্টার।
- **Kit-এ যোগ হলো:** grid-এ `header.check` (টিক ফিল্টার), `rowAction.labels` (সারি অনুযায়ী বোতামের লেখা), badge-এ `toneKey`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend (`app/Modules/CommandCenter`, নতুন মডিউল)**
- `Database/Migrations/2027_03_27_000100_create_command_center_boards.php`
- `Models/Board.php`, `DrillPath.php`, `Composition.php`
- `Resources/BoardResource.php`, `DrillPathResource.php`, `CompositionResource.php`
- `Support/CommandBook.php`, `Support/CommandDeck.php`, `Http/Controllers/Api/CommandCenterApiController.php`, `Routes/api.php`
- `app/Modules/Erp/Support/DemoResource.php` (নতুন, শেয়ার করা)
- `ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` (মডিউল `COMMAND_CENTER`, ফোল্ডার `COMMAND_CENTER.COMMAND`) (বদলানো)
- `lang/en/commandcenter.php`, `lang/bn/commandcenter.php` (নতুন); `lang/*/nav.php` (বদলানো)

**Frontend**
- `app/(app)/command-center/[screen]/page.js`, `actions.js`, `screens.js` (নতুন)
- `components/commandcenter/command-deck.jsx` (নতুন)
- `lib/demo-screen.js` (নতুন, শেয়ার করা)
- `components/buyergateway/tiles.jsx`, `components/erp/records-grid.jsx` (বদলানো)

**Test**
- `tests/Feature/CommandCenter/CommandCenterBoardsTest.php`, `CommandDeckTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** test2 DB-তে ৬টা টেস্ট / ৯২টা assertion পাস; MenuAccess ৬ / ৩৪ পাস।
- **`CommandCenterBoardsTest.php` (৩):**
  - বোর্ড:
    - picker-এ বিভাগ আর স্টাফ; নাম ছাড়া আটকায়; নাম ছেঁটে, বিভাগ আর মালিকের নাম দেখায়।
    - একই বিভাগে দ্বিতীয় বোর্ড আটকায়; নতুন বোর্ডের ডিফল্ট।
    - টাইল ছাড়া প্রকাশ, আর প্রকাশিতের টাইল ০ করা আটকায়; টাইল দিয়ে প্রকাশ চলে; প্রকাশ বন্ধ চলে।
    - অজানা হালনাগাদ আর ১২০% আটকায়; যোগ করার ক্রমে তালিকা।
  - ড্রিল পাথ: মেট্রিক ছাড়া আর একই মেট্রিক (অন্য হাতে) আটকায়; স্টাইল প্রথম স্তরে আর অজানা উৎস আটকায়।
  - কম্পোজিশন:
    - শিরোনাম ছাড়া আর একই শিরোনাম আটকায়; খালি রিলিজ আটকায়; রিলিজডকে খালি করা আটকায়।
    - চার্ট ০ (টাইল থাকায়) চলে; আর্কাইভ করলে খালি করা চলে; নতুনের ডিফল্ট; অজানা লেআউট আটকায়।
  - কে পারেন: Auditor দেখেন, যোগ করতে পারেন না; Manager পারেন; Storekeeper দেখেন না; অন্য কোম্পানি কিছু দেখে না, এই বিভাগ বা স্টাফ নিতে পারে না।
- **`CommandDeckTest.php` (৩):**
  - খালি কোম্পানিতে চার সংখ্যাই নেই, ১২ মাস, খালি প্যানেল, সপ্তাহ শনিবার ৭ নভেম্বর থেকে।
  - হাতে হিসাব করা ডাটা (সোর্স টেবিলে সরাসরি; টেস্ট সেশনে FK চেক বন্ধ):
    - রাজস্ব ১১,৮০,০০০ বনাম ৬,০০,০০০ (+৯৬.৭%)।
    - অর্ডার বুক ৮,৪৮,০০০ (বাতিল আর পুরোনো বাদ)।
    - দক্ষতা ৩১.৭% (লক্ষ্য ৫৫%)।
    - সময়মতো ৫০% (২টার ১টা)।
    - নভেম্বর: রাজস্ব ১১.৮ লাখ, খরচ ৪৮,০০০ (খসড়া আর রাজস্ব হিসাব বাদ), শিপমেন্ট ১,৫৬,০০০।
    - বায়ার: H&M ৬ লাখ, Zara ২ লাখ (ছয় মাসের পরেরটা বাদ)।
    - ফ্লোর: ৩৫০ বনাম ৩০০।
    - লাইন: A ৩০ / ৬০, B ৩৩.৩ / ৫০।
    - কাজের তালিকায় সাইন-ইন নেই; সতর্কতা উচ্চ আগে, অনুমোদন পরে।
  - Auditor দেখেন, Storekeeper দেখেন না।

**UI চেক (`app_check/plans/cmd01`, `cmd01b`–`d`, ২০২৬-১১-১২, owner; error 0; dev ডাটা রাখা আছে):**
- **Command Deck:** রাজস্ব ৳215.1K (১টা ইনভয়েস), অর্ডার বুক ৳957.8K (২টা অর্ডার)।
  - ১২ মাসের চার্ট আর তার টেবিল; বায়ারের বার (LPP)।
  - ২৭টা মডিউলের নামসহ তালিকা; মানুষের করা কাজ; একটা অর্ডার-সতর্কতা আর একটা অনুমোদন।
  - দক্ষতা আর সময়মতো ডেলিভারির জন্য dev-এ ডাটা নেই — "No data recorded yet" ✔
- **বোর্ড:**
  - Sewing Floor Live (Sewing / Production, ১২ টাইল, ৯৪% সবুজ) আর Spares & Downtime (Maintenance, ৬২% লাল) তৈরি হলো।
  - সারির Publish → Published, বোতাম হলো Unpublish।
  - খালি বোর্ডে Publish চাপলে "A board with no tiles cannot be published." দেখায়।
  - একই বিভাগে দ্বিতীয় বোর্ড দিলে বার্তা আসে।
  - এডিটে Weekly + ৬ টাইল + প্রকাশিত করা গেল।
  - "Published only" টিক কাজ করে ✔
- **ড্রিল পাথ:** Line efficiency (২,৪০০ k, ঘণ্টাভিত্তিক) আর Shipment value (৪৮০ k); টাইল ২ / ২ / ১ / ২,৮৮০ ✔
- **কম্পোজিশন:** খালি অবস্থায় Released দিলে বার্তা আসে → ১১ টাইল / ৪ চার্ট দিয়ে রিলিজ হলো; Attrition & overtime খসড়া ✔

## ১০. খোলা প্রশ্ন

১. **বোর্ড আর কম্পোজিশন কি সত্যি ড্যাশবোর্ড দেখাবে:** Brickland-এর মতো এখন এগুলো শুধু রেজিস্টার (নাম, টাইলের সংখ্যা, দর্শক)। 27/02-এর KPI লাইব্রেরি থেকে টাইল বেছে আসল বোর্ড বানানো আর দেখানো হবে কিনা জানাবেন। "ডাটার মান"-ও তখন হাতে না লিখে নিজে থেকে হিসাব করা যাবে।

২. **Command Deck-এর সংজ্ঞা ঠিক আছে কিনা:**
- রাজস্ব এখন শুধু রপ্তানির কমার্শিয়াল ইনভয়েস থেকে। Sales & Shipment-এর দেশীয় বিক্রি ধরা হবে কিনা জানাবেন।
- "বিভাগ অনুযায়ী উৎপাদন" এখন শুধু সেলাই ফ্লোর থেকে। নিটিং, ডাইং আর কাটিং-এর উৎপাদন যোগ হবে কিনা জানাবেন। তাদের একক আলাদা (কেজি, পিস), তাই শতাংশে দেখাতে হবে।

৩. **কোন সতর্কতা "প্রধান":** এখন অর্ডারের AI সতর্কতা আর অপেক্ষমাণ অনুমোদন দেখায়। মেশিন ব্রেকডাউন, স্টক শেষ হওয়া ইত্যাদিও যোগ হবে কিনা জানাবেন।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/cc01` (তিন রেজিস্টার) আর `out/cc00` (CEO Command Center)। Brickland-এর ক্রম 06, 11, 12 of 20।
- **Brickland থেকে পার্থক্য:**
  - বোর্ডের Publish বোতামে বিদ্যুতের আইকন (kit-এর `rowAction`)।
  - ডাটার মানের টাইলে Brickland-এর গোল রিং নেই, শুধু সংখ্যা।
  - Command Deck-এর প্রতিটা চার্টে টেবিল দেখার বোতাম আছে — হালকা থিমে সবুজের কনট্রাস্ট কম, তাই dataviz নিয়মে লাগে।
  - "বিভাগ অনুযায়ী উৎপাদন" ফ্লোর ধরে হিসাব।
- **UI চেকে ধরা পড়া, ঠিক করা হয়েছে:**
  - মডিউলের তালিকায় শুধু আইকন আসছিল (নেভিগেশনের `name` / `name_bn` না নিয়ে `label` নেওয়া হয়েছিল)।
  - সাম্প্রতিক কাজে scheduler-এর রক্ষণাবেক্ষণের আপডেট ভরে যাচ্ছিল; এখন শুধু মানুষের কাজ।
  - "1 commercial invoices" লেখা ঠিক করা হয়েছে।
- **টেস্টে ঠিক করা:** "এই সপ্তাহ" মানে আজ পর্যন্ত — আগামীকালের প্ল্যান ধরা হয় না। টেস্টের প্রত্যাশা ভুল ছিল, কোড ঠিক ছিল।
- **টুল নোট:** এই UI চেকের প্ল্যান প্রথমে ভুল করে `plans/cc01.json` নামে লেখা হয়েছিল, যা 15/06 Collar & Cuff-এর প্ল্যান মুছে দেয়। পুরোনো প্ল্যান সেশনের লগ থেকে ফিরিয়ে আনা হয়েছে। 15/06-এর স্ক্রিনশটগুলো অক্ষত; শুধু `out/cc01/report.json` বদলে গেছে। এই মডিউলের ফাইল এখন `cmd01*`।
