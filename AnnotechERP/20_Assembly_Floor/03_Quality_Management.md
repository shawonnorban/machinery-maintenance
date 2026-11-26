# Quality Management

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/03`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Quality Management Dashboard | `/m/garments/quality-dashboard` | 1 | 8 | 0 |
| 2 | Quality IN | `/m/garments/quality-in` | 1 | 4 | 0 |
| 3 | Quality Inspection | `/m/garments/quality-inspection` | 1 | 9 | 0 |
| 4 | Quality OUT | `/m/garments/quality-out` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Quality Management Dashboard

`/m/garments/quality-dashboard` — মেনুতে: Quality Dashboard

**ট্যাব: Quality IN 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Shift | select |  | All shifts |
| Section | select |  | All sections |
| Floor | select |  | All floors |
| Line | select |  | All lines |
| View By | select |  | Line Wise, Floor Wise, Section Wise, Unit Wise |
| Bundle number | text |  | BND-00125 |
- সেকশন: Quality Status (Last 7 Days), Inspection Result, Quality Target vs Actual — Line Wise, Bundle Quality History
- বাটন/অ্যাকশন: Search, Today, Last 7 days, Last 30 days, 7d, 30d, CSV, Quality IN 0, Inspection 0, Quality OUT 0, Pass 0, Reject 0, Rework 0, Pending Inspection 0, Quality WIP 0, Ready for Quality 0, Daily Quality, Trace

#### Quality IN

`/m/garments/quality-in`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section * | select | ✔ | No quality sections |
| Floor * | select | ✔ | Select section first |
| Line * | select | ✔ | Select floor first |
| Scan Bundle Barcode * | text | ✔ | Select Section, Floor and Line first |
- সেকশন: Bundle Information (Auto Fill), Today's Quality IN · 0
- বাটন/অ্যাকশন: Load Bundle, Clear, Confirm Quality IN, Refresh

#### Quality Inspection

`/m/garments/quality-inspection`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section * | select | ✔ | No quality sections |
| Floor * | select | ✔ | Select section first |
| Line * | select | ✔ | Select floor first |
| Scan Bundle Barcode * | text | ✔ | Select Section, Floor and Line first |
| Pass Qty * | text | ✔ | 0 |
| Reject Qty * | text | ✔ | 0 |
| Rework Qty * | text | ✔ | 0 |
| Defect Type | select |  | No reject or rework, Stitching Defect, Measurement Defect, Fabric Defect, Sewing Defect, Printing Defect, Embroidery Def |
| Remark | text |  | Optional |
- সেকশন: Bundle Information (Auto Fill), Inspection Result, Today's Inspections · 0
- বাটন/অ্যাকশন: Load Bundle, Clear, Save Inspection, Refresh

#### Quality OUT

`/m/garments/quality-out`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section * | select | ✔ | No quality sections |
| Floor * | select | ✔ | Select section first |
| Line * | select | ✔ | Select floor first |
| Scan Bundle Barcode * | text | ✔ | Select Section, Floor and Line first |
| Remarks | textarea |  | Optional remarks… |
- সেকশন: Bundle Information (Auto Fill), Remarks, Today's Quality OUT · 0
- বাটন/অ্যাকশন: Load Bundle, Clear, Confirm Quality OUT, Refresh
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **20/02 Finishing-এর `asm_finishing_outs`:** Finishing থেকে যে বান্ডেল বের হয়েছে, সেটাই Quality-তে ঢোকে। Quality যে পরিমাণ পায় = Finishing-এর Good।
- **19 Cutting Floor-এর `cut_bundles`:** কার্ডের তথ্য। বান্ডেল নম্বর বা বারকোড, দুটোই চলে (`SewingFloor::card`)।
- **Company Management-এর লাইন মাস্টার (`production_lines`):** `line_type` = QUALITY লাইনগুলোই Quality লাইন। Section, Floor, দৈনিক টার্গেট, শিফট প্ল্যান থাকে। `FinishingFloor::lines()` এখন লাইনের টাইপ নেয়, তাই Finishing আর Quality একই কোড ব্যবহার করে।
- **20/02-এর শেয়ার করা UI:** `finishing.jsx` থেকে Panel, Facts, Chip, Table (CSV + Total) আর তারিখের ফরম্যাট export করা হয়েছে।
- **চার্ট:** `recharts` (trend line আর Target vs Actual bar) আর `components/dashboard/status-donut.jsx` (Inspection Result)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_10_000100_create_asm_quality.php` (মডিউল `app/Modules/Assembly`)।

- **`asm_quality_ins`** (Quality IN): `bundle_id` (unique), `finishing_out_id` (unique), `in_date`, `section_id`, `floor_id`, `line_id` (→ `production_lines`), `qty` (= Finishing-এর Good), `created_by`।
- **`asm_quality_inspections`** (প্রতিটা ইন্সপেকশন রাউন্ড):
  - `quality_in_id`, `round` (১ = প্রথম, ২.. = রিওয়ার্ক আবার; `quality_in_id + round` unique), `inspected_on`, `inspected_qty`।
  - `pass_qty`, `reject_qty`, `rework_qty`, `defect_type` (শুধু রিজেক্ট বা রিওয়ার্ক থাকলে), `remark`।
  - রিওয়ার্ক থাকলে `rework_status` (PENDING → PROCESSING → COMPLETED), `rework_started_at/by`, `rework_completed_at/by`।
- **`asm_quality_outs`** (Quality OUT): `quality_in_id` (unique), `out_date`, সব রাউন্ড মিলিয়ে `pass_qty` / `reject_qty` / `rework_qty`, `final_status` (PASS / REJECT), `remarks`।
- **ত্রুটির ধরন (Brickland-এর তালিকা, একই ক্রমে):** Stitching, Measurement, Fabric, Sewing, Printing, Embroidery, Washing, Finishing, Other।

## ৫. Workflow / Status ও Business Rules

- **ডেস্ক (তিনটাতেই):**
  1. Section → Floor → Line বাছাই। শুধু চালু QUALITY লাইন যেগুলো Section আর Floor-এর নিচে রাখা। প্রতিটা ডেস্ক শেষ বাছাইটা মনে রাখে (Brickland-এর মতো, ব্রাউজারে)।
  2. লাইন বাছলে ওপরে লাইনের আজকের হিসাব: Today IN, Inspected, Passed, Reject, Rework, Quality OUT, লাইনে কত বান্ডেল আর কত পিস যাচাই বাকি, দৈনিক টার্গেট।
  3. স্ক্যান বা নম্বর লিখে Enter / Load Bundle। কিছু সেভ হয় না। নেওয়া না গেলে কারণসহ "Scan Failed"।
  4. Confirm চাপলে সেভ। স্ক্যানের দিন = আজ। কোনো Withdraw বা Undo নেই (Brickland-এর মতো)।
- **বান্ডেলের অবস্থা:** Not Ready → Ready for Quality → In Quality → (Rework Pending → Rework Processing → Ready for Re-Inspection) → Inspected → Quality OUT।
- **Quality IN:**
  - Finishing থেকে আউট হয়ে থাকতে হয়, আর Good ১-এর বেশি হতে হয়। বাতিল কার্ড আটকায়। একবারই রিসিভ হয়।
  - পরিমাণ = Finishing-এর Good।
- **Quality Inspection:**
  - প্রথম রাউন্ডে পুরো বান্ডেল, পরের রাউন্ডে আগের রাউন্ডের রিওয়ার্ক। ডেস্কে "Inspecting: ৩৩ পিস · First inspection" বা "২ পিস · Re-inspection · round 2" দেখায়, আর Pass-এর ঘরে পুরো পরিমাণ আগে থেকে বসানো থাকে।
  - Pass + Reject + Rework = যত পিস যাচাই হচ্ছে; ডেস্কে সাথে সাথে "Total ✓" বা "Quantity mismatch"।
  - রিজেক্ট বা রিওয়ার্ক থাকলে Defect Type লাগে; না থাকলে দেওয়া যায় না। Other হলে Remark লাগে।
  - রিওয়ার্ক > ০ হলে রিওয়ার্ক PENDING। রিওয়ার্ক চলাকালে আবার ইন্সপেকশন বা OUT হয় না। সুপারভাইজার Completed করলে পিসগুলো "Ready for Re-Inspection" হয়ে পরের রাউন্ডে আসে।
  - যে লাইনে রিসিভ, সেই লাইনেই ইন্সপেকশন আর OUT।
- **রিওয়ার্ক বদলানো:** শুধু সুপারভাইজার। দুই জায়গা থেকে: ইন্সপেকশন বা OUT ডেস্কে বান্ডেল স্ক্যান করলে "Start Rework / Rework Completed" স্ট্রিপ, আর ড্যাশবোর্ডের Rework ট্যাব। পেছনে ফেরা যায় না।
- **Quality OUT:**
  - আটকায়: ইন্সপেকশন হয়নি; রিওয়ার্ক চলছে; রিওয়ার্ক ফিরে এসেছে কিন্তু আবার ইন্সপেকশন হয়নি; আগেই আউট।
  - সব রাউন্ডের Pass / Reject / Rework যোগ করে রাখা হয়। Final Status: Pass থাকলে "Pass / Ready for Next Process", সব রিজেক্ট হলে "Reject"।
- **Quality Management Dashboard (From–To, Shift, Section, Floor, Line, View By):**
  - তারিখের সীমা সর্বোচ্চ ৯২ দিন। Today / Last 7 days / Last 30 days।
  - ১২টা টাইল: Total Quality IN, Inspected Qty (টার্গেটসহ), Passed, Reject, Rework, Pending Inspection, Pass %, Reject %, Rework %, Quality OUT, Rework Open (সব তারিখ), Ready for Quality।
  - Quality Status (Last 7 / 30 Days): Quality IN, Passed, Reject, Rework-এর দৈনিক লাইন চার্ট; চার্ট/টেবিল বদলানো যায়।
  - Inspection Result: Pass / Reject / Rework-এর ভাগ (donut), চার্ট/টেবিল।
  - Quality Target vs Actual: লাইন, ফ্লোর, সেকশন বা ইউনিট অনুযায়ী bar চার্ট আর টেবিল। Target = দৈনিক টার্গেট × দিন; Pending = এখন লাইনে যাচাই বাকি; Pass % ≥৯০ সবুজ, ≥৭৫ হলুদ।
  - ১০টা ট্যাব: Quality IN, Inspection (প্রতিটা রাউন্ড), Quality OUT, Pass, Reject, Rework, Pending Inspection, Quality WIP, Ready for Quality, Daily Quality। প্রতিটায় CSV।
- **Bundle Quality History:** Cut → Card printed → Sewing IN/OUT → Finishing IN/OUT (রিওয়ার্কসহ) → Quality IN → প্রতিটা Inspection রাউন্ড → Quality rework → Quality OUT। প্রতিটা ধাপে সময় আর কে করেছেন।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `assembly.quality.view`
  - `assembly.quality.manage` (রিসিভ, ইন্সপেকশন, আউট)
  - `assembly.quality.supervise` (রিওয়ার্কের অবস্থা)
  - FACTORY_ADMIN আর FACTORY_MANAGER তিনটাই পায়। LINE_CHIEF পায় view আর manage।
- **API (`QualityApiController`):**
  - `GET /assembly/quality/options` — সেকশন, ফ্লোর, লাইন, শিফট, unfiled লাইন, ত্রুটির ধরন
  - `GET /assembly/quality/line?line_id=` — লাইনের আজকের হিসাব
  - `GET /assembly/quality/resolve?code=&line_id=&kind=in|inspect|out` — সেভের আগে বান্ডেল, কত পিস কোন রাউন্ডে, চলমান রিওয়ার্ক আর বাধা
  - `GET /assembly/quality/recent?kind=in|inspect|out` — আজকের স্ক্যান
  - `POST /assembly/quality/ins` · `POST /assembly/quality/inspections` · `POST /assembly/quality/outs`
  - `POST /assembly/quality/rework` — `{inspection_id, status}`
  - `GET /assembly/quality/dashboard?from=&to=&shift_id=&section_id=&floor_id=&line_id=&view=&trend=7|30`
  - `GET /assembly/quality/trace?code=`
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার QUALITY MANAGEMENT, ৪টা স্ক্রিন: `/assembly-floor/quality-dashboard`, `/quality-in`, `/quality-inspection`, `/quality-out`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/quality.jsx` (`QualityNav`, `QualityDesk`, `QualityBoard`, ইতিহাসের ডায়ালগ)। একই `assembly-floor/[screen]` রুট।
- **ডেস্ক (Brickland-এর মতো):**
  - ওপরে Dashboard / Quality IN / Inspection / Quality OUT বাটন।
  - ৪ ধাপের স্ট্রিপ (IN: Check Bundle, Inspection: Enter Result, OUT: Final Status), শেষ হওয়া ধাপে টিক।
  - Section / Floor / Line আর লোকেশনের বক্স; Quality লাইন না থাকলে, unfiled লাইন থাকলে আর শুধু দেখার অনুমতি থাকলে সতর্কবার্তা।
  - লাইনের আজকের হিসাবের স্ট্রিপ।
  - বাঁয়ে স্ক্যান বক্স। ডানে সেভের বার্তা, স্ক্যানের বার্তা, (সুপারভাইজারের জন্য) রিওয়ার্ক স্ট্রিপ, Bundle Information (Auto Fill), Inspection-এ Inspection Result, OUT-এ Final Status আর Remarks।
  - Clear আর Confirm (তৈরি হলে সবুজ), নিচে Today's Quality IN / Inspections / Quality OUT, Refresh আর View (ইতিহাস)।
- **Dashboard:** ফিল্টারের সারি, ১২টা টাইল, trend চার্ট (7d / 30d, চার্ট/টেবিল), Inspection Result donut, Target vs Actual চার্ট + টেবিল (Total সারিসহ), ১০টা ট্যাব সংখ্যাসহ (রিলোড ছাড়া বদলায়), Bundle Quality History কার্ড।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_10_000100_create_asm_quality.php`
- `app/Modules/Assembly/Models/AsmQualityIn.php`, `AsmQualityInspection.php`, `AsmQualityOut.php`
- `app/Modules/Assembly/Http/Controllers/Api/QualityApiController.php`
- `app/Modules/Assembly/Support/FinishingFloor.php` (বদলানো: `lines()` লাইনের টাইপ নেয়)
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/quality.jsx` (নতুন)
- `frontend/src/components/assembly/finishing.jsx` (বদলানো: শেয়ার করা অংশ export)
- `frontend/src/app/(app)/assembly-floor/[screen]/page.js`, `screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyQualityTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyQualityTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ১৮৭টা assertion। Assembly-র ১২টা টেস্টই একসাথে পাস (৪৪৯টা assertion)।
- `test_a_bundle_out_of_finishing_is_received_into_quality_once`:
  - options: ফাইল করা চালু QUALITY লাইন (QC01, QC02), Floor ছাড়া QC03 unfiled, বন্ধ লাইন বাদ, ৯টা ত্রুটির ধরন।
  - Finishing থেকে বের না হওয়া কার্ড (Not Ready) আর Good ০ বান্ডেল আটকায়।
  - Good ৩০ বান্ডেল: Ready, পরিমাণ ৩০, আগের প্রসেস FIN_OUT, কিছু সেভ হয় না।
  - FINISHING লাইনে রিসিভ আটকায়। রিসিভ → ৩০, লাইন/সেকশন/ফ্লোর। দ্বিতীয়বার আটকায়। লাইনের আজকের হিসাব।
- `test_inspection_rounds_the_rework_and_quality_out`:
  - রিসিভ না হওয়া, অন্য লাইন, ইন্সপেকশন ছাড়া OUT — আটকায়।
  - যোগফল ভুল, Defect ছাড়া রিজেক্ট, Other-এ Remark ছাড়া, রিজেক্ট ছাড়া Defect — আটকায়।
  - রাউন্ড ১: ২৫ / ২ / ৩ (Stitching) → রিওয়ার্ক PENDING; রিওয়ার্ক চলাকালে ইন্সপেকশন আর OUT আটকায়।
  - LINE_CHIEF রিওয়ার্ক বদলাতে পারে না; PROCESSING → COMPLETED; আবার একই অবস্থা আটকায়। তারপর OUT আটকায় ("re-inspection")।
  - রাউন্ড ২: ৩ পিস → ২ / ১ (Measurement)। আর কিছু বাকি নেই, ইন্সপেকশন আটকায়।
  - OUT: অন্য লাইন আটকায়; Pass ২৭ / Reject ৩ / Rework ৩, PASS, মন্তব্য trim হয়। দ্বিতীয়বার OUT আর ইন্সপেকশন আটকায়।
  - পুরো রিজেক্ট বান্ডেল → Final Status REJECT।
  - Trace: ১৩টা ধাপ (Finishing রিওয়ার্ক থেকে Quality OUT পর্যন্ত)।
- `test_the_dashboard_reads_a_range_a_shift_a_view_and_the_trend`:
  - আজ: ইন ৫০ / ২ বান্ডেল, ইন্সপেকশন ৩০, টার্গেট ৭০০, পাস ২৭ (90%), রিজেক্ট ৩, Pending ২০, OUT ১, Rework Open ১, Ready ১ / ১২, লাইন ২।
  - Pending, WIP (অবস্থাসহ), Ready, Rework (গতকালের খোলা রিওয়ার্ক) ট্যাব; লাইন অনুযায়ী পারফরম্যান্স।
  - Trend: To পর্যন্ত ৭ দিন; ৩০ দিন; অন্য দিন সংখ্যা আটকায়।
  - দুই দিন, Day shift (রাতের স্ক্যান বাদ), Section / Unit view, লাইন ফিল্টার, উল্টো তারিখ আটকায়।
  - রিওয়ার্ক শেষ হলে Rework Open ০ আর পিসগুলো Pending Inspection-এ ফেরে।
- `test_who_may_scan_and_who_may_supervise`: LINE_CHIEF রিসিভ আর ইন্সপেকশন করতে পারে, রিওয়ার্ক বদলাতে পারে না; FACTORY_MANAGER পারে; STORE_MANAGER আর TECHNICIAN পায় না।

**Regression:** `MenuAccessTest` + `RoleMatrixTest` (১৩টা টেস্ট, ৮৪টা assertion) পাস।

**dev মাস্টার:** Company Management-এ নতুন "Quality Line1" (টার্গেট ৪০০, ম্যানপাওয়ার ১২, Day shift প্ল্যান) আর "Quality Line2" (টার্গেট ৩৫০)। দুটোই QUALITY টাইপ, Section "Final Inspection", Floor "Ground Floor · B-GF"।

**UI চেক (`AnnotechERP/tools/app_check/plans/qm01`, `qm02`, `qm03`), dev অ্যাপে (ডাটা রেখে দেওয়া হয়েছে):**
- Quality IN: BND-20261106-0001 (৩৩ পিস) আর 0002 (৩০ পিস) Quality Line1-এ রিসিভ।
- Inspection: 0001 → Pass ৩০ / Reject ১ / Rework ২, Stitching Defect, "Open seam at side" ("Total: 33 = 30 + 1 + 2 ✓")। 0002 → Pass ৩০।
- OUT ডেস্কে 0001: "rework still open" বলে আটকাল; স্ট্রিপ থেকে Start Rework → Rework Completed; তারপর "back for re-inspection" বলে আটকাল।
- Inspection রাউন্ড ২: "2 pcs · Re-inspection · round 2", Pass ২।
- Quality OUT: 0001 → Pass ৩২ / Reject ১ / Rework ২, "Pass / Ready for Next Process", মন্তব্য "Released to packing"। আবার খুললে ডেস্ক লোকেশন মনে রেখেছে।
- Dashboard: ইন ৬৩, ইন্সপেকশন ৬৫ (টার্গেট ৭৫০), পাস ৬২ (95.4%), রিজেক্ট ১, রিওয়ার্ক ২; Inspection ট্যাবে ৩টা রাউন্ড; Rework ট্যাবে Completed; Daily Quality; 30d trend।
- Trace (0001): কাটা থেকে রাউন্ড ২ পর্যন্ত ১২টা ধাপ।
- 0002 ইন্সপেকশন হয়ে Quality-তে আছে (WIP দেখানোর জন্য, OUT করা হয়নি)।
- **error:** ০।

**প্রিন্ট:** Brickland-এর এই চার স্ক্রিনে Print বাটন নেই, প্রতিটা টেবিলে CSV আছে।

## ১০. খোলা প্রশ্ন

১. **পুরো রিজেক্ট বান্ডেলের OUT:** কোনো পিস পাস না হলে এখন বান্ডেলটা "Reject" চূড়ান্ত অবস্থা নিয়ে Quality OUT হতে পারে (Brickland-এর অবস্থার তালিকায় Reject আছে)। এমন বান্ডেল OUT করা যাবে, নাকি Quality-তেই আটকে থাকবে, জানাবেন।

২. **রিওয়ার্কের সংখ্যা:** OUT-এ "Rework" = সব রাউন্ডের রিওয়ার্ক যোগ করে (একই পিস দুবার রিওয়ার্ক হলে দুবার গোনা হয়)। ড্যাশবোর্ডের Inspected-ও প্রতিটা রাউন্ড গোনে, তাই Inspected বান্ডেলের পরিমাণের চেয়ে বেশি হতে পারে (dev-এ ৬৩ পিস ইন, ৬৫ ইন্সপেকশন)। এই গোনার নিয়ম ঠিক আছে কিনা জানাবেন।

৩. **লাইনের সুপারভাইজার:** Brickland-এর ডেস্কে লাইনের আজকের হিসাবের পাশে "Supervisor" নাম দেখায়, কিন্তু Company Management-এর লাইন মাস্টারে সুপারভাইজারের ঘর নেই। তাই এখন নাম দেখানো হচ্ছে না। লাইন মাস্টারে সুপারভাইজার যোগ করা হবে কিনা জানাবেন।

৪. **ভুল স্ক্যান শোধরানো:** Finishing-এর মতোই Quality-র ডেস্কে Withdraw বা Undo নেই। ভুল ইন্সপেকশন কনফার্ম হলে আর শোধরানো যায় না। সুপারভাইজারের জন্য কোনো পথ লাগবে কিনা জানাবেন।

৫. **একই লাইনে ইন্সপেকশন আর OUT:** এখন যে Quality লাইনে রিসিভ, সেই লাইনেই ইন্সপেকশন আর OUT করতে হয়। ইন্সপেকশন আর রিলিজ আলাদা টেবিল বা লাইনে হয় কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/qm01` আর চার স্ক্রিনের JS চাঙ্ক, `jsgrep.mjs`):**
  - ডেমোতে কোনো Quality লাইন নেই, তাই সারি, অবস্থা আর বার্তা চাঙ্ক থেকে নেওয়া।
  - পাওয়া নিয়ম: রাউন্ড ("Round" কলাম), "Ready for Re-Inspection", "Quality OUT — refused while rework is still pending", রিজেক্ট/রিওয়ার্কে Defect Type বাধ্যতামূলক, Other-এ Remark বাধ্যতামূলক, ডেস্কের লোকেশন মনে রাখা (`quality-desk-location`), রিওয়ার্ক স্ট্রিপ শুধু সুপারভাইজারের জন্য।
  - ড্যাশবোর্ডের ১০টা ট্যাবের কলাম, ১২টা টাইলের লেখা, trend-এর 7d / 30d, Target vs Actual-এর bar চার্ট।
- **Finishing রিওয়ার্ক:** Finishing-এর রিওয়ার্ক শেষ না হলেও বান্ডেলের Good পিস Quality-তে যেতে পারে (dev-এ 0001-এর Finishing রিওয়ার্ক এখনো Processing)।
- **20/04 Packing:** "QC Pass quantity" এখান থেকে নেবে: Quality OUT হওয়া বান্ডেলের `asm_quality_outs.pass_qty`।
