# Finishing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/02`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Finishing Dashboard | `/m/garments/finishing-dashboard` | 2 | 16 | 0 |
| 2 | Finishing — Bundle IN | `/m/garments/finishing-bundle-in` | 1 | 4 | 0 |
| 3 | Finishing — Bundle OUT | `/m/garments/finishing-bundle-out` | 1 | 7 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Finishing Dashboard

`/m/garments/finishing-dashboard`

**ট্যাব: WIP 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Shift | select |  | All shifts |
| Section | select |  | All sections, Finishing |
| Floor | select |  | All floors, Finishing |
| Line | select |  | All lines, FINISHING — Finishing |
| View By | select |  | Line Wise, Floor Wise, Section Wise, Unit Wise |
| Bundle number | text |  | BND-00125 |
- গ্রিড কলাম: Line, Target, Actual, Achievement, Bundle IN, Bundle OUT, WIP, Reject, Rework
- সেকশন: Target vs Actual — Line Wise, Bundle History
- বাটন/অ্যাকশন: Apply, Today, Last 7 days, Last 30 days, CSV, WIP 0, Bundle IN 0, Bundle OUT 0, Reject 0, Rework 0, Pending Bundle 1, Daily Production, Trace

**ট্যাব: Daily Production**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Shift | select |  | All shifts |
| Section | select |  | All sections, Finishing |
| Floor | select |  | All floors, Finishing |
| Line | select |  | All lines, FINISHING — Finishing |
| View By | select |  | Line Wise, Floor Wise, Section Wise, Unit Wise |
| Bundle number | text |  | BND-00125 |
- গ্রিড কলাম: Line, Target, Actual, Achievement, Bundle IN, Bundle OUT, WIP, Reject, Rework
- গ্রিড কলাম: Date, Bundle IN, Bundle OUT, Good, Reject, Rework
- সেকশন: Target vs Actual — Line Wise, Bundle History
- বাটন/অ্যাকশন: Apply, Today, Last 7 days, Last 30 days, CSV, WIP 0, Bundle IN 0, Bundle OUT 0, Reject 0, Rework 0, Pending Bundle 1, Daily Production, Trace

#### Finishing — Bundle IN

`/m/garments/finishing-bundle-in` — মেনুতে: Bundle IN

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section * | select | ✔ | Select section, Finishing |
| Floor * | select | ✔ | Select section first |
| Line * | select | ✔ | Select floor first |
| Scan Bundle Barcode * | text | ✔ | Select location first |
- সেকশন: Bundle Information (From Barcode / Order), Planning & Target Information, Recently Scanned Bundles (Today) · 0
- বাটন/অ্যাকশন: Load Bundle, Refresh, Clear, Confirm Bundle IN

#### Finishing — Bundle OUT

`/m/garments/finishing-bundle-out` — মেনুতে: Bundle OUT

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section * | select | ✔ | Select section, Finishing |
| Floor * | select | ✔ | Select section first |
| Line * | select | ✔ | Select floor first |
| Scan Bundle Barcode * | text | ✔ | Select location first |
| Good Qty | text |  | 0 |
| Reject Qty | text |  | 0 |
| Rework Qty | text |  | 0 |
- সেকশন: Bundle Information (From Barcode / Order), Output Quantity, Planning & Target Information, Recently Scanned Bundles (Today) · 0
- বাটন/অ্যাকশন: Load Bundle, Refresh, Clear, Confirm Bundle OUT
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **20/01 Sewing-এর `asm_bundle_outs`:** সেলাই থেকে যে বান্ডেল বের হয়েছে, সেটাই Finishing-এ ঢোকে। Finishing যে পরিমাণ পায় = সেলাইয়ের আউটপুট।
- **19 Cutting Floor-এর `cut_bundles`:** কার্ডের তথ্য (অর্ডার, PO, স্টাইল, রং, সাইজ, কাট নং)। বান্ডেল নম্বর বা বারকোড, দুটোই চলে (`SewingFloor::card`)।
- **Company Management-এর লাইন মাস্টার (`production_lines`):** `line_type` = FINISHING লাইনগুলোই Finishing লাইন। প্রতিটা লাইন একটা Section আর Floor-এর নিচে থাকে, সাথে manpower আর দৈনিক টার্গেট (`target_per_day`)। Floor থেকে building → factory, সেটাই "Unit"।
- **লাইনের শিফট প্ল্যান (`production_line_shift_overrides` → `shifts`):** লাইনের কাজের ঘণ্টা (ঘণ্টায় টার্গেট বের করতে) আর ড্যাশবোর্ডের Shift ফিল্টার।
- **Capacity Planning-এর master plan (`planning_master_plans`, LOCKED):** অর্ডারের SMV / SAM, পরিকল্পিত শুরু ও শেষ, শিপের তারিখ, মোট পরিমাণ।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_09_000100_create_asm_finishing.php` (মডিউল `app/Modules/Assembly`)।

- **`asm_finishing_ins`** (Bundle IN): `bundle_id` (unique — একটা বান্ডেল একবারই ঢোকে), `sewing_out_id` (unique, সেলাইয়ের আউট), `in_date`, `section_id`, `floor_id`, `line_id` (→ `production_lines`), `qty` (= সেলাইয়ের আউটপুট), `created_by`।
- **`asm_finishing_outs`** (Bundle OUT): `finishing_in_id` (unique — একবারই আউট), `out_date`, `good_qty`, `reject_qty`, `rework_qty`.
  - রিওয়ার্ক থাকলে `rework_status` (PENDING → PROCESSING → COMPLETED), সাথে `rework_started_at/by` আর `rework_completed_at/by`।

## ৫. Workflow / Status ও Business Rules

- **ডেস্ক (দুটোতেই):**
  1. Section → Floor → Line বাছাই। শুধু চালু FINISHING লাইন যেগুলো একটা Section আর Floor-এর নিচে রাখা। লাইন বাছলে Planning & Target প্যানেলে লাইনের টার্গেট আর আজকের অগ্রগতি আসে।
  2. বারকোড স্ক্যান বা নম্বর লিখে Enter / Load Bundle। কিছু সেভ হয় না।
     - বান্ডেলের তথ্য, আগের প্রসেস (Sewing OUT ও সময়), বর্তমান সেকশন / ফ্লোর / লাইন আর অবস্থা আসে।
     - অর্ডারের locked master plan আসে।
     - এখানে নেওয়া না গেলে কারণসহ "Scan Failed" দেখায় আর Confirm বন্ধ থাকে।
  3. Confirm চাপলে সেভ হয়। স্ক্যানের দিন = আজ, ডেস্কে তারিখ বাছার ঘর নেই।
  - Brickland-এর মতোই ডেস্কে কোনো Withdraw বা Undo নেই, শুধু Clear (স্ক্রিন মোছে) আর Confirm।
- **বান্ডেলের অবস্থা:** Not Ready (সেলাই থেকে বের হয়নি) → Ready for Finishing → In Finishing → Finishing OUT।
- **Bundle IN:**
  - সেলাই থেকে বের হয়ে থাকতে হয়, আর বের হওয়া পরিমাণ ১-এর বেশি হতে হয়।
  - বাতিল কার্ড আটকায়। একবারই রিসিভ হয়; আগে হলে তারিখ আর লাইনসহ জানায়।
  - পরিমাণ = সেলাইয়ের আউটপুট।
  - Finishing রিসিভ করলে সেলাই আর তার Bundle OUT তুলতে পারে না। সেলাইয়ের গ্রিডে অবস্থা "In Finishing" দেখায়।
- **Bundle OUT:**
  - Good + Reject + Rework মিলে বান্ডেলের পরিমাণের সমান হতে হয়। ডেস্কে সাথে সাথে "✓ Valid" বা "✗ Invalid Quantity" দেখায়।
  - যে লাইনে রিসিভ হয়েছে, সেই লাইন থেকেই আউট করতে হয়।
  - একবারই আউট হয়। Rework > ০ হলে রিওয়ার্ক PENDING হিসেবে শুরু হয়।
- **রিওয়ার্ক (Dashboard → Rework ট্যাব):**
  - শুধু সুপারভাইজার বদলাতে পারেন। Start: PENDING → PROCESSING। Complete: PENDING বা PROCESSING → COMPLETED।
  - পেছনে ফেরা যায় না।
- **Finishing Dashboard (From–To, Shift, Section, Floor, Line, View By):**
  - তারিখের সীমা সর্বোচ্চ ৯২ দিন। Today / Last 7 days / Last 30 days বাটন আছে।
  - লাইন: চালু আর ফাইল করা FINISHING লাইন। Section বা Floor-এর নিচে না রাখা লাইন দেখানো হয় না, শুধু "Not filed under a section…" সতর্কবার্তায় নাম আসে।
  - Shift বাছলে স্ক্যানগুলো স্ক্যানের সময় (ঢাকা) দিয়ে ছাঁকা হয়। লাইন: যাদের শিফট প্ল্যানে ওই শিফট আছে, আর যাদের কোনো শিফট প্ল্যান নেই।
  - Target = লাইনের দৈনিক টার্গেট × সময়ের দিন। Actual = Bundle OUT-এর Good। Achievement = Good ÷ Target।
  - Current WIP = To তারিখ পর্যন্ত ঢুকেছে কিন্তু বের হয়নি।
  - Rework Pending = PENDING + PROCESSING, সব তারিখের।
  - Pending Bundle = সেলাই থেকে বের হয়েছে কিন্তু Finishing-এ ঢোকেনি, পুরো ফ্লোর জুড়ে।
  - Target vs Actual টেবিল লাইন, ফ্লোর, সেকশন বা ইউনিট অনুযায়ী।
  - ট্যাব: WIP, Bundle IN, Bundle OUT, Reject, Rework (খোলা সব + সময়ের মধ্যে শেষ হওয়া), Pending Bundle, Daily Production (প্রতিদিন + Total)।
  - প্রতিটা টেবিলে CSV।
- **Bundle History (Trace, আর ডেস্কের View):** Cut → Card printed → Sewing IN → Sewing OUT → Finishing IN → Finishing OUT → Rework started → Rework completed। প্রতিটা ধাপে সময় আর কে করেছেন।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `assembly.finishing.view`
  - `assembly.finishing.manage` (স্ক্যান)
  - `assembly.finishing.supervise` (রিওয়ার্কের অবস্থা)
  - FACTORY_ADMIN আর FACTORY_MANAGER তিনটাই পায়। LINE_CHIEF পায় view আর manage।
- **API (`FinishingApiController`):**
  - `GET /assembly/finishing/options` — সেকশন, ফ্লোর, লাইন, শিফট, unfiled লাইন
  - `GET /assembly/finishing/line?line_id=` — লাইনের টার্গেট আর আজকের অগ্রগতি
  - `GET /assembly/finishing/resolve?code=&line_id=&kind=in|out` — সেভের আগে বান্ডেল, প্ল্যান আর বাধা
  - `GET /assembly/finishing/recent?kind=in|out` — আজকের স্ক্যান
  - `POST /assembly/finishing/ins` · `POST /assembly/finishing/outs`
  - `POST /assembly/finishing/rework` — `{bundle_no, status}`
  - `GET /assembly/finishing/dashboard?from=&to=&shift_id=&section_id=&floor_id=&line_id=&view=`
  - `GET /assembly/finishing/trace?code=`
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার FINISHING, ৩টা স্ক্রিন: `/assembly-floor/finishing-dashboard`, `/assembly-floor/finishing-bundle-in`, `/assembly-floor/finishing-bundle-out`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/finishing.jsx` (`FinishingNav`, `FinishingDesk`, `FinishingBoard`, Bundle History ডায়ালগ)। একই `assembly-floor/[screen]` রুট, `screens.js` আর `actions.js`-এ যোগ করা।
- **ডেস্ক (Brickland-এর মতো):**
  - ওপরে Dashboard / Bundle IN / Bundle OUT বাটন।
  - ৪ ধাপের স্ট্রিপ: IN-এ Auto Fetch Bundle, OUT-এ Output Quantity।
  - Section / Floor / Line আর লোকেশনের বক্স। যার স্ক্যানের অনুমতি নেই, তাকে "Your role can view Finishing but not scan bundles" দেখায়।
  - বাঁয়ে স্ক্যান বক্স আর Load Bundle। ডানে বার্তার স্ট্রিপ, Bundle Information, OUT-এ Output Quantity (Good / Reject / Rework আর যাচাই), Planning & Target Information।
  - নিচে Recently Scanned Bundles (Today), Refresh আর View (ইতিহাস), তারপর Clear আর Confirm (তৈরি হলে সবুজ)।
- **Dashboard:**
  - ফিল্টারের সারি আর ১০টা টাইল।
  - Target vs Actual: রঙিন হেডার, অর্জন ≥৯০% সবুজ, ≥৭৫% হলুদ, তার নিচে লাল।
  - ৭টা ট্যাব সংখ্যাসহ। ট্যাব বদলাতে রিলোড হয় না।
  - Bundle History কার্ড।
- **20/01-এর "GO >> Finishing Bundle IN"** এখন চালু।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_09_000100_create_asm_finishing.php`
- `app/Modules/Assembly/Models/AsmFinishingIn.php`, `app/Modules/Assembly/Models/AsmFinishingOut.php`
- `app/Modules/Assembly/Support/FinishingFloor.php`
- `app/Modules/Assembly/Http/Controllers/Api/FinishingApiController.php`
- `app/Modules/Assembly/Http/Controllers/Api/SewingApiController.php` (বদলানো: Finishing রিসিভ করলে Sewing OUT তোলা যায় না)
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/finishing.jsx` (নতুন)
- `frontend/src/components/assembly/sewing.jsx` (বদলানো: GO >> Finishing Bundle IN)
- `frontend/src/app/(app)/assembly-floor/[screen]/page.js`, `screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyFinishingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyFinishingTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ১৪৬টা assertion। Assembly-র ৮টা টেস্টই একসাথে পাস (২৬২টা assertion)।
- `test_a_bundle_out_of_sewing_is_received_onto_a_finishing_line_once`:
  - options: শুধু ফাইল করা চালু FINISHING লাইন (FIN01, FIN02)। Section ছাড়া FIN03 unfiled তালিকায়। বন্ধ লাইন আর SEWING লাইন বাদ। শিফট দুটো।
  - লাইনের অগ্রগতি: ম্যানপাওয়ার ২০, টার্গেট ৩০০, ১২ ঘণ্টার শিফট → ঘণ্টায় ২৫।
  - সেলাই থেকে বের না হওয়া কার্ড: Not Ready, কারণসহ, রিসিভ আটকায়।
  - শর্ট আউট (৩০) বান্ডেল: Ready, পরিমাণ ৩০, অর্ডার নং, locked plan (SMV 10.5, ৬০০)। resolve-এ কিছু সেভ হয় না।
  - unfiled লাইন আর Section ছাড়া রিসিভ আটকায়। রিসিভ → ৩০, লাইন / সেকশন / ফ্লোর। দ্বিতীয়বার আটকায় (তারিখ ও লাইনসহ)।
  - সেলাইয়ের গ্রিডে In Finishing, withdraw বন্ধ, আর API-ও আটকায়।
- `test_a_bundle_goes_out_with_good_reject_and_rework_adding_up_and_the_rework_is_followed`:
  - রিসিভ না হওয়া বান্ডেল, অন্য লাইন, যোগফল ২৯ ≠ ৩০ — আটকায়। ২৫ / ২ / ৩ → রিওয়ার্ক PENDING। দ্বিতীয়বার আটকায়।
  - লাইনের অগ্রগতি: ইন ১, আউট ১, ভালো ২৫, অর্জন 8.3%। অর্ডারের সম্পন্ন ৪৫, বাকি ৫৫৫।
  - LINE_CHIEF রিওয়ার্ক বদলাতে পারে না। রিওয়ার্ক নেই এমন বান্ডেল আটকায়। PENDING → PROCESSING → COMPLETED। একই অবস্থায় আবার আর পেছনে যাওয়া আটকায়।
  - Trace: ৮টা ধাপ, সময়সহ। সেলাই পর্যন্ত যাওয়া বান্ডেলের ৪টা ধাপ।
- `test_the_dashboard_reads_a_range_a_shift_and_a_view`:
  - আজ: ইন ২ / ৫০ পিস, আউট ১, WIP ১ / ২০, ভালো ২৭, টার্গেট ৫০০, অর্জন 5.4%, রিজেক্ট ৩, রিওয়ার্ক বাকি ১, Pending Bundle ১ / ১২, লাইন ২।
  - দুই দিন: টার্গেট ১০০০, Daily Production-এ দুই দিনের সারি। গতকাল পর্যন্ত WIP ০।
  - Day shift: গতকাল রাত ৯টার স্ক্যান বাদ পড়ে, শিফট প্ল্যান ছাড়া লাইন থাকে।
  - Section / Unit / Floor view, লাইন ফিল্টার। উল্টো তারিখ আর ৯২ দিনের বেশি আটকায়।
  - শেষ হওয়া রিওয়ার্ক শুধু যে সময়ে শেষ হয়েছে সেই সময়ে দেখায়।
- `test_who_may_scan_and_who_may_supervise`: LINE_CHIEF স্ক্যান করতে পারে কিন্তু রিওয়ার্ক বদলাতে পারে না। FACTORY_MANAGER বদলাতে পারে। STORE_MANAGER আর TECHNICIAN পায় না।

**Regression:** `AssemblySewingTest` (৪টা, ১১৬টা), `MenuAccessTest` (৬টা, ৩৪টা), `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**dev মাস্টার:** Company Management-এ "Finishing Line1–3"-কে FINISHING টাইপ, Section "Finishing", Floor "Ground Floor · B-GF", ম্যানপাওয়ার ২৫ / ২২ / ২০, টার্গেট ৪০০ / ৩৫০ / ৩০০ করা হয়েছে। Line1-এ Day shift-এর শিফট প্ল্যান।

**UI চেক (`AnnotechERP/tools/app_check/plans/fn01`, `fn02`), dev অ্যাপে (ডাটা রেখে দেওয়া হয়েছে):**
- Bundle IN: Finishing / Ground Floor · B-GF / Finishing Line1। BND202611060001 (৩৫ পিস) আর BND-20261106-0002 (৩০ পিস) রিসিভ। প্ল্যানে SMV 9.2, ঘণ্টায় ২৯, দৈনিক ৪০০, master plan-এর তারিখ।
- Bundle OUT: 0001 → ভালো ৩৩ / রিজেক্ট ১ / রিওয়ার্ক ১ ("✓ Valid — 35 = 35")। 0002 → ভালো ৩০।
- Dashboard: ভালো ৬৩, টার্গেট ১,০৫০, Line1 অর্জন 15.8%, রিজেক্ট ১, রিওয়ার্ক বাকি ১, Pending Bundle ০।
- Rework ট্যাবে Start → Rework Processing। Daily Production: ১ / ২ / ৬৩ / ১ / ১।
- Trace (BND-20261106-0001): কাটা থেকে রিওয়ার্ক শুরু পর্যন্ত ৭টা ধাপ।
- Sewing Bundle OUT গ্রিডে দুটো বান্ডেলই "In Finishing", তোলার বাটন নেই। "GO >> Finishing Bundle IN" Finishing ডেস্কে নিয়ে যায়।
- **error:** ০।

**প্রিন্ট:** Brickland-এর এই তিন স্ক্রিনে Print বাটন নেই, প্রতিটা টেবিলে CSV আছে।

## ১০. খোলা প্রশ্ন

১. **ফিনিশিংয়ের পরিকল্পিত তারিখ:** Brickland-এর ডেস্কে "Planned Finishing Start / End" আছে। কিন্তু আমাদের master plan-এ শুধু সেলাইয়ের শুরু ও শেষের তারিখ থাকে, তাই এখন সেটাই দেখানো হচ্ছে। ফিনিশিংয়ের আলাদা তারিখ লাগবে কিনা (যেমন সেলাইয়ের পরে কতদিন), জানাবেন।

২. **শিফট অনুযায়ী টার্গেট:** Brickland বলে টার্গেট আসে লাইনের শিফট প্ল্যান বা দৈনিক টার্গেট থেকে। কিন্তু Company Management-এর শিফট প্ল্যানে কোনো টার্গেট রাখার জায়গা নেই। তাই এখন টার্গেট = লাইনের দৈনিক টার্গেট × দিন, একটা শিফট বাছলেও একই থাকে। শিফট অনুযায়ী আলাদা টার্গেট লাগবে কিনা জানাবেন।

৩. **ভুল স্ক্যান শোধরানো:** Brickland-এর Finishing ডেস্কে Withdraw বা Undo নেই, তাই আমরাও রাখিনি। একবার কনফার্ম হলে ভুল IN বা ভুল Good / Reject / Rework আর শোধরানো যায় না। সুপারভাইজারের জন্য শোধরানোর কোনো পথ লাগবে কিনা জানাবেন।

৪. **অন্য লাইনে আউট:** এখন যে লাইনে রিসিভ হয়েছে, সেই লাইন থেকেই আউট করতে হয়। বান্ডেল এক ফিনিশিং লাইন থেকে আরেক লাইনে সরানো হয় কিনা, আর হলে আউট অন্য লাইনে নেওয়া যাবে কিনা জানাবেন।

৫. **রিওয়ার্ক শেষ হলে:** রিওয়ার্ক Completed হলে পিসগুলো এখন Good-এ যোগ হয় না, আলাদাই থাকে। শেষ হওয়া রিওয়ার্ক ভালো আউটপুট (আর অর্জন) হিসেবে গোনা হবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/fn01` আর ডেস্ক ও ড্যাশবোর্ডের JS চাঙ্ক):**
  - ডেস্কের ফিল্ড, স্ট্যাটাস (Not Ready / Ready for Finishing / In Finishing / Finishing OUT), "Good + Reject + Rework must equal the Bundle Qty" আর Planning & Target-এর ১৬টা ঘর।
  - ড্যাশবোর্ডের ৭টা ট্যাবের কলাম, Rework-এর Start / Complete ("Only a supervisor can move its status") আর Target vs Actual-এর রঙের সীমা (৯০ / ৭৫)।
  - ডেমোতে আজকের কোনো রো ছিল না, তাই সারি কেমন দেখায় তা চাঙ্ক থেকে নেওয়া হয়েছে। Pending Bundle ৯৪ ছিল।
- **Section বা Floor-এ না রাখা লাইন:** স্ক্যান হতে পারে না, তাই টার্গেটে ধরা হয় না (নইলে টার্গেট বাড়ত কিন্তু আউটপুট কখনো আসত না)। শুধু সতর্কবার্তায় নাম দেখায়।
- **20/01-এর সাথে:** "GO >> Finishing Bundle IN" চালু হয়েছে। Finishing রিসিভ করা বান্ডেলের Sewing OUT আর তোলা যায় না।
