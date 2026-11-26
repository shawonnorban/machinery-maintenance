# Packing Process

> **স্ট্যাটাস:** ✅ সম্পন্ন (৭টার ৭টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/05`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Folding | `/m/garments/packing-folding` | 1 | 1 | 0 |
| 2 | Poly Packing | `/m/garments/packing-poly` | 1 | 1 | 0 |
| 3 | Barcode & Label | `/m/garments/packing-barcode-label` | 1 | 1 | 0 |
| 4 | Assortment | `/m/garments/packing-assortment` | 1 | 1 | 0 |
| 5 | Carton Packing | `/m/garments/packing-carton` | 1 | 1 | 0 |
| 6 | Carton Marking | `/m/garments/packing-carton-marking` | 1 | 1 | 0 |
| 7 | Carton Sealing | `/m/garments/packing-carton-sealing` | 1 | 1 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Folding

`/m/garments/packing-folding`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Poly Packing

`/m/garments/packing-poly`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Barcode & Label

`/m/garments/packing-barcode-label`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Assortment

`/m/garments/packing-assortment`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Carton Packing

`/m/garments/packing-carton`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Carton Marking

`/m/garments/packing-carton-marking`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today

#### Carton Sealing

`/m/garments/packing-carton-sealing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter |
- সেকশন: Find a Batch, Waiting & In Progress, Completed Today
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **20/04 Packing:** একই ব্যাচ, একই টেবিল আর একই কোড ([Packing](04_Packing.md) দেখুন)। এই সাতটা স্ক্রিন সেই ব্যাচের ওপরেই চলে।
- **20/03 Quality-র `asm_quality_outs`:** Quality OUT হওয়া বান্ডেলের `pass_qty`-ই প্যাকিংয়ের "QC Pass Qty"।
- **19 Cutting Floor-এর `cut_bundles`:** কার্ডের তথ্য (PO, রং, সাইজ)। বান্ডেল নম্বর বা বারকোড, দুটোই চলে (`SewingFloor::card`)।
- **Merchandising-এর `merch_orders` / `merch_order_sizes`:** অর্ডার নম্বর, স্টাইলের নাম, শিপমেন্টের তারিখ, ফ্যাক্টরি আর রং-সাইজ অনুযায়ী অর্ডার পরিমাণ।
- **Company Management-এর লাইন মাস্টার:** প্যাকিং লোকেশন = Section আর Floor-এর নিচে রাখা FINISHING লাইন (`FinishingFloor::lines()`)।
- **শেয়ার করা UI আর প্রিন্ট:** `finishing.jsx`-এর Panel, Facts, Chip, CSV; `lib/bundle-label.js`-এর Code128 বারকোড; `lib/print-document.js` (রিপোর্টের প্রিন্ট)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_11_000100_create_asm_packing.php` (মডিউল `app/Modules/Assembly`), 20/04 আর 20/05 দুটোরই।

- **`asm_packing_batches`** (প্যাকিং ব্যাচ, এক PO + এক রং):
  - `packing_no` (PKG-YYYYMMDD-####, কোম্পানিতে unique), `order_id`, `po_no`, `buyer_id`, `style`, `colour`।
  - `section_id`, `floor_id`, `line_id` (প্রথম স্ক্যানের লোকেশন)।
  - `status`: PACKING_IN (বান্ডেল নিচ্ছে) → IN_PROGRESS (Folding শুরু) → PACKED_OUT; শেষ স্ক্যান সরালে CANCELLED।
  - প্রসেসের ঘর: `assort_ratio`, `destination`, `country`, `shipping_mark`, `special_instruction`, `marking_print_count`।
  - Packing Out: `final_packed_qty`, `out_date`, `packed_out_at`, `packed_out_by`।
- **`asm_packing_ins`** (প্যাকিং ইন স্ক্যান): `batch_id`, `bundle_id`, `quality_out_id`, `in_date`, `size`, `qty`। একই বান্ডেল একাধিকবার (ভাগে ভাগে) আসতে পারে, মোট QC Pass পর্যন্ত।
- **`asm_packing_steps`** (প্রতি ব্যাচে ৭টা, ব্যাচ খোলার সময় তৈরি): `step` (FOLDING, POLY, LABEL, ASSORTMENT, CARTON, MARKING, SEALING), `seq`, `status` (PENDING / IN_PROGRESS / COMPLETED), `qty`, `remarks`, `started_at/by`, `completed_at/by`, `reopen_reason`, `reopened_at/by`।
- **`asm_packing_labels`** (পলি লেবেল, প্রতি সাইজে একটা): `size`, `qty`, `barcode` (LBL + প্যাকিং নম্বরের অঙ্ক + ক্রমিক), `print_count`, `printed_at`, `verified_at/by`।
- **`asm_packing_cartons`**: `serial`, `carton_no` (CTN-YYYYMMDD-####; বারকোড = ড্যাশ ছাড়া নম্বর), `carton_type`, `carton_size`, `gross_weight`, `net_weight`, `qty`, `status` (OPEN → MARKED → SEALED), `sealed_at/by`, `unseal_reason`।
- **`asm_packing_carton_lines`**: কার্টনের সাইজ ভাগ (`size`, `qty`)।

## ৫. Workflow / Status ও Business Rules

- **প্রতিটা প্রসেস ডেস্ক:**
  - বাঁয়ে Find a Batch (প্যাকিং নম্বর, কার্টন, লেবেল বা বান্ডেলের বারকোড), Waiting & In Progress (এই ধাপে দাঁড়ানো ব্যাচ) আর Completed Today।
  - ডানে ব্যাচ: অর্ডার পরিমাণ, QC Pass, Packing In, কার্টন (কতটা সিল); সাইজ, বান্ডেল, লোকেশন; ৭টা প্রসেসের টেবিল (অবস্থা, পরিমাণ, কে, কখন)।
  - নিচে এই প্রসেসের কার্ড: Start → (প্রসেসের নিজের কাজ) → Remarks → Complete।
- **ক্রম:** Folding → Poly Packing → Barcode & Label → Assortment → Carton Packing → Carton Marking → Carton Sealing। আগেরটা সম্পন্ন না হলে Start আটকায়। প্যাকড আউট বা বাতিল ব্যাচে কিছু বদলানো যায় না।
- **Folding / Poly / Assortment:** Verified Qty (ফাঁকা = সর্বোচ্চ)। Folding-এর সর্বোচ্চ = Packing In, Poly-র = Folding, Assortment-এর = Poly। Folding শুরু হলে ব্যাচে আর বান্ডেল ঢোকে না।
- **Barcode & Label:**
  - শুরু হলে প্রতিটা সাইজের জন্য একটা পলি লেবেল (সেই সাইজের প্যাক পরিমাণসহ)।
  - Print / Reprint (প্রতিবার গোনা হয়)। প্রিন্টের আগে যাচাই আটকায়।
  - স্ক্যান করে যাচাই: অন্য ব্যাচের লেবেল আর একই লেবেল দ্বিতীয়বার ("Duplicate scan") আটকায়।
  - সব লেবেল যাচাই হলে Complete।
- **Assortment:** বায়ারের সাইজ রেশিও "S:1, M:2" (কমা বা সেমিকোলন), ফাঁকা = সলিড সাইজ। প্যাক হয়নি এমন সাইজ থাকলে "Assortment Ratio Not Matched", টাইপ করার সময়ই দেখায়। একই সাইজ দুবার বা ভুল ফরম্যাট আটকায়।
- **Carton Packing:**
  - সাইজের টেবিল: Packed In, In Cartons, Left, প্রতি কার্টনে পিস। সাথে Carton Type, Carton Size, Gross / Net ওজন, কার্টনের সংখ্যা → Create Carton(s)।
  - কোনো সাইজ বাকির চেয়ে বেশি দেওয়া যায় না। রেশিও থাকলে প্রতিটা কার্টনে রেশিওর পুরো সেট (S:1, M:1 হলে ৬/৬ চলে, ৬/৫ না)।
  - সিল না হওয়া কার্টন মুছে ফেলা যায়; পরেরগুলোর ক্রমিক এক ঘর এগোয়।
  - অন্তত একটা কার্টন থাকলে Complete, পরিমাণ = কার্টনের মোট।
- **Carton Marking:** Destination আর Shipping Mark বাধ্যতামূলক (শিপিং মার্ক আগে থেকে "বায়ার / PO … / স্টাইল"), Country আর Special Instruction ঐচ্ছিক → Generate Marking: সব কার্টন MARKED। Preview (প্রথম কার্টনের ট্যাগ) আর Print / Reprint (প্রতি কার্টনে একটা ট্যাগ, গোনা হয়)। সব কার্টন মার্ক হলে Complete।
- **Carton Sealing:**
  - প্রতিটা স্ক্যানে যাচাই: পরিমাণ → বারকোড (এই ব্যাচের কিনা) → গ্রস ওজন → নেট ওজন (≤ গ্রস) → মার্কিং → সিল।
  - কার্টনে ওজন না থাকলে এখানে লিখতে হয়; লিখলে সেটাই নেওয়া হয়।
  - দ্বিতীয়বার সিল আটকায়। সুপারভাইজার কারণ লিখে সিল খুলতে পারেন (Sealing চলাকালে)।
  - সব কার্টন সিল হলে Complete।
- **Reopen:** শুধু সুপারভাইজার, কারণসহ, সম্পন্ন প্রসেসের জন্য। পরের প্রসেস শুরু হয়ে গেলে বা ব্যাচ প্যাকড আউট হলে আটকায়।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `assembly.packing.view`
  - `assembly.packing.manage` (প্যাকিং ইন, প্রসেস চালানো, লেবেল, কার্টন, মার্কিং, সিল, প্যাকিং আউট)
  - `assembly.packing.supervise` (ভুল স্ক্যান সরানো, প্রসেস আবার খোলা, কার্টনের সিল খোলা)
  - FACTORY_ADMIN আর FACTORY_MANAGER তিনটাই পায়। LINE_CHIEF পায় view আর manage।
- **API (`PackingApiController`, `PackingReportApiController`):**
  - `GET /assembly/packing/options` — সেকশন, ফ্লোর, লোকেশন, unfiled, ৭টা প্রসেস, রিপোর্টের তালিকা
  - `GET /assembly/packing/in` — খোলা ব্যাচ আর আজকের স্ক্যান
  - `GET /assembly/packing/in/preview?code=&line_id=` · `POST /assembly/packing/ins` · `DELETE /assembly/packing/ins/{id}`
  - `GET /assembly/packing/process?step=` — প্রসেস ডেস্কের তালিকা · `GET /assembly/packing/find?code=` · `GET /assembly/packing/batches/{id}`
  - `POST /assembly/packing/batches/{id}/steps/{step}/start|complete|reopen`
  - `POST …/labels/print` · `POST …/labels/verify` · `POST …/cartons` · `DELETE /assembly/packing/cartons/{id}` · `POST …/marking` · `POST …/marking/print` · `POST …/seal` · `POST /assembly/packing/cartons/{id}/unseal`
  - `GET /assembly/packing/out` · `GET /assembly/packing/out/preview?code=` · `POST /assembly/packing/outs`
  - `GET /assembly/packing/dashboard` · `GET /assembly/packing/trace?code=` · `GET /assembly/packing/reports?kind=&from=&to=&q=`
- **মেনু:** Assembly Floor-এ ফোল্ডার PACKING (Dashboard, Packing In, Packing Out, Reports), তার ভেতরে সাব-ফোল্ডার PACKING PROCESS (৭টা স্ক্রিন), Brickland-এর breadcrumb "Packing › Folding"-এর মতো।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/packing.jsx`-এর `ProcessDesk` (একই কম্পোনেন্ট সাতটা স্ক্রিনে, `step` আলাদা), সাথে `LabelPanel`, `CartonPanel`, `MarkingPanel`, `SealPanel`, `CartonTable`; প্রিন্ট `lib/packing-print.js`।
- **লেআউট (Brickland-এর মতো):** ওপরে ১১টা বাটনের সারি; বাঁয়ে Find a Batch আর দুই তালিকা (প্রতিটা ব্যাচে ৭টা প্রসেসের ছোট বার); ডানে ব্যাচের কার্ড আর প্রসেসের কার্ড। ব্যাচ না বাছলে "Pick a batch from the queue, or scan it." আর প্রসেসের নির্দেশনা।
- **প্রিন্ট:**
  - পলি লেবেল: প্রতি সাইজে একটা ট্যাগ (বায়ার, PO, স্টাইল, রং, সাইজ, পরিমাণ, গন্তব্য, Code128 বারকোড)।
  - কার্টন মার্কিং: প্রতি কার্টনে একটা ট্যাগ (শিপিং মার্ক, বায়ার, PO, স্টাইল, রং, সাইজ / পরিমাণ, কার্টনের পরিমাণ, "n of m", গ্রস / নেট, গন্তব্য, দেশ, নির্দেশনা, বারকোড)।
  - দুটোই দেখে নেওয়া হয়েছে (`app_check/out/pk02/labels`, `pk03/marking`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (20/04 Packing আর 20/05 Packing Process একসাথে বানানো, কারণ Brickland-এ দুটো একই ব্যাচ আর একই বাটনের সারির ওপর চলে):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_11_000100_create_asm_packing.php`
- `app/Modules/Assembly/Models/AsmPackingBatch.php`, `AsmPackingIn.php`, `AsmPackingStep.php`, `AsmPackingLabel.php`, `AsmPackingCarton.php`, `AsmPackingCartonLine.php`
- `app/Modules/Assembly/Support/PackingBook.php` (নতুন: লোকেশন, ব্যাচ দেখানো, কোড থেকে ব্যাচ খোঁজা, Packing Out-এর যাচাই, নম্বর)
- `app/Modules/Assembly/Http/Controllers/Api/PackingApiController.php`
- `app/Modules/Assembly/Http/Controllers/Api/PackingReportApiController.php`
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/packing.jsx` (নতুন)
- `frontend/src/lib/packing-print.js` (নতুন: পলি লেবেল আর কার্টন মার্কিংয়ের প্রিন্ট)
- `frontend/src/app/(app)/assembly-floor/[screen]/page.js`, `screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyPackingTest.php`

**টুল**
- `AnnotechERP/tools/app_check/ui.mjs` (বদলানো: `dialog` ধাপ, confirm / prompt-এর জবাব দিতে)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyPackingTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ২৮৯টা assertion। Assembly-র ১৫টা টেস্টই একসাথে পাস (৭৩৮টা assertion)।
- `test_packing_in_opens_a_batch_per_po_and_colour_and_a_supervisor_may_remove_a_scan`:
  - options: Finishing সেকশনের ফাইল করা FINISHING লাইন, Floor ছাড়া লাইন unfiled, ৭টা প্রসেস, ১১টা রিপোর্ট।
  - Quality-তে না যাওয়া বান্ডেল আটকায়। Preview: বায়ার, অর্ডার, PO, রং, সাইজ, অর্ডার পরিমাণ ২০০, QC Pass ৩০, বাকি ৩০, শিপমেন্টের তারিখ; কিছু সেভ হয় না।
  - QUALITY লাইনে প্যাকিং ইন আটকায়। ২০ পিস → নতুন ব্যাচ PKG-20261106-0001। তারপর বাকি ১০; ১৫ দিলে আটকায়; বাকি ১০ একই ব্যাচে; পুরো প্যাক হলে আটকায়।
  - অন্য সাইজের বান্ডেল (একই PO আর রং) একই ব্যাচে। খোলা ব্যাচ: ৪৮ পিস, ২ বান্ডেল, S:30 · M:18।
  - LINE_CHIEF স্ক্যান সরাতে পারে না; মালিক পারে। Folding শুরু হলে সরানো আটকায়, আর একই PO-রঙের পরের স্ক্যানে নতুন ব্যাচ (0002)। শেষ স্ক্যান সরালে ব্যাচ বাতিল।
- `test_the_seven_processes_run_in_order_then_packing_out`:
  - ক্রম না মানলে আটকায় ("Folding must be completed first"), শুরু না করে Complete আটকায়, দুবার Start আটকায়। Verified Qty-র সর্বোচ্চ সীমা।
  - Process ডেস্কের Completed Today আর Waiting তালিকা।
  - লেবেল: প্রতি সাইজে একটা (LBL…01 S, LBL…02 M); প্রিন্টের আগে যাচাই আটকায়; ডুপ্লিকেট স্ক্যান আর অন্য ব্যাচের লেবেল আটকায়; সব যাচাই না হলে Complete আটকায়।
  - Assortment: প্যাক না হওয়া সাইজ আর ভুল ফরম্যাট আটকায়; রেশিও canonical হয় ("S:1, M:1")। LINE_CHIEF reopen পারে না; কারণ ছাড়া reopen আটকায়; পরের প্রসেস শুরু হলে reopen আটকায়।
  - Carton: পুরো সেট না হলে আটকায়; ৪টা কার্টন একসাথে (CTN-…-0001..0004); বাকির চেয়ে বেশি আটকায়; একটা মুছলে পরেরগুলোর ক্রমিক এক ঘর এগোয়।
  - Marking: মার্কিং ছাড়া Complete আটকায়; গন্তব্য বাধ্যতামূলক; সব কার্টন MARKED; প্রিন্ট গোনা।
  - Sealing: সিল; দ্বিতীয়বার, অন্য ব্যাচের কার্টন, ওজন ছাড়া, নেট > গ্রস আটকায়; সব সিল না হলে Complete আটকায়; LINE_CHIEF unseal পারে না, মালিক কারণসহ পারে।
  - Packing Out: সব যাচাই পাস না হলে আটকায়; ৯টা যাচাই; সিল করা পরিমাণের বেশি আটকায়; ৪৭ পিস প্যাকড আউট; দ্বিতীয়বার আটকায়; প্যাকড আউট ব্যাচে কিছু বদলানো আটকায়।
  - Dashboard: ১২টা ধাপের প্রবাহ, ৯টা হিসাব, Ready for Shipment-এ বায়ার, ৪৭ পিস, ৪ কার্টন, শিপ তারিখ।
  - Trace: কার্টন (তৈরি → সিল → প্যাকড আউট) আর বান্ডেল (QC Pass → Packing In); অচেনা কোড আটকায়।
- `test_the_eleven_reports_and_who_may`: ১১টা রিপোর্টের প্রতিটার কলাম আর সারি (Daily, In, Out, Buyer, PO-তে অর্ডার পরিমাণ, Style, Barcode, Carton, Assortment, Pending-এ বাকি ৪ + ৮, Completed); Buyer/PO/Style দিয়ে খোঁজা; উল্টো তারিখ আর অচেনা রিপোর্ট আটকায়। LINE_CHIEF প্যাকিং ইন করতে পারে; STORE_MANAGER আর TECHNICIAN পায় না; FACTORY_MANAGER স্ক্যান সরাতে পারে।

**Regression:** `MenuAccessTest` + `RoleMatrixTest` (১৩টা টেস্ট, ৮৪টা assertion) পাস।

**dev মাস্টার:** Company Management-এর "Packing Line1"-কে FINISHING টাইপ, Section "Finishing", Floor "Ground Floor · B-GF", টার্গেট ৫০০, ম্যানপাওয়ার ১০ করা হয়েছে (Brickland প্যাকিং লোকেশন Finishing সেকশন থেকে নেয়)।

**UI চেক (`AnnotechERP/tools/app_check/plans/pk01`–`pk04`), dev অ্যাপে (ডাটা রেখে দেওয়া হয়েছে):**
- Quality OUT ডেস্কে BND-20261106-0002 ছাড়া হলো ("Released to packing")।
- Packing In (Finishing › Ground Floor › Packing Line1): 0001 → ৩২ পিস, নতুন ব্যাচ PKG-20261107-0001; 0002 → ৩০ পিস একই ব্যাচে ("PKG-20261107-0001 (32 pcs, 1 bundles)")।
- Folding (মন্তব্য "Folded by size") → Poly → Barcode & Label (লেবেল প্রিন্টের PDF নেওয়া হয়েছে, LBL20261107000101 যাচাই) → Assortment (S:1, XL:1 দিলে "Ratio Not Matched", তারপর ফাঁকা = সলিড)।
- Carton: S ৩১ × ২ কার্টন, 5-ply, 60x40x40 cm, ১০.৫ / ৯.৮ কেজি → CTN-20261107-0001, 0002।
- Marking: Hamburg, Germany; Preview আর প্রিন্টের PDF নেওয়া হয়েছে (প্রতি কার্টনে একটা ট্যাগ, "1 of 2")।
- Sealing: দুটো কার্টন সিল; দ্বিতীয়বার স্ক্যানে "already sealed"।
- Packing Out: Waiting তালিকা থেকে Open → ৯টা যাচাই সবুজ → Final ৬২ → "62 pcs added automatically"।
- Dashboard: Ready for Shipment ৬২ পিস, ২ কার্টন, শিপ তারিখ ২০-১২-২০২৬; কার্টনের Trace।
- রিপোর্ট: Daily, Carton (প্রিন্টের PDF নেওয়া হয়েছে), Pending, PO-wise।
- **error:** ০।

## ১০. খোলা প্রশ্ন

১. **Reopen-এর সীমা:** Brickland প্রতিটা সম্পন্ন প্রসেসের পাশে Reopen দেখায়। আমরা শুধু তখনই Reopen দিচ্ছি যখন পরের প্রসেস এখনো শুরু হয়নি, যাতে পরের ধাপের কাজ (যেমন কার্টন) অর্থহীন না হয়। আগের প্রসেস খুললে পরেরগুলোও পেছনে যাবে কিনা জানাবেন।

২. **কার্টন নম্বরের ধরন:** এখন CTN-YYYYMMDD-#### (দিনভিত্তিক ক্রমিক), বারকোড = ড্যাশ ছাড়া নম্বর। Brickland-এর নমুনায় "SGI-CTN-000125" (কোম্পানির প্রিফিক্স + চলমান নম্বর) দেখায়। কোন ধরন লাগবে জানাবেন।

৩. **পলি লেবেল:** এখন প্রতিটা সাইজের জন্য একটা লেবেল (পরিমাণসহ)। প্রতিটা পিস বা প্রতিটা পলির জন্য আলাদা লেবেল লাগবে কিনা জানাবেন।

৪. **লেবেলে গন্তব্য:** Barcode & Label আগে, Carton Marking পরে, তাই পলি লেবেলের "Dest" ফাঁকা থাকে (Brickland-এর ক্রমও এটাই)। গন্তব্য আগে নেওয়া হবে কিনা জানাবেন।

৫. **সব পিস কার্টনে:** Carton Packing এখন যত পিস কার্টনে উঠেছে তা নিয়েই সম্পন্ন হয়, Assortment-এর সব পিস কার্টনে ওঠা বাধ্যতামূলক নয়। বাকি পিস থাকলে Complete আটকাবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** সাতটা স্ক্রিন একই JS চাঙ্ক থেকে চলে (`ProcessDesk`)। প্রতিটা প্রসেসের নির্দেশনা, যাচাইয়ের ক্রম ("quantity → barcode → gross weight → net weight → carton marking → seal"), পুরো রেশিও সেট, ডুপ্লিকেট লেবেল নিয়ন্ত্রণ, Reopen / Unseal-এর কারণ (prompt), কার্টনের টেবিলের কলাম আর মার্কিং ট্যাগের ঘর সেখান থেকে নেওয়া।
- **ui.mjs-এ `dialog` ধাপ:** Reopen, Unseal আর স্ক্যান সরানো ব্রাউজারের confirm / prompt চায়। দরকার হলে UI চেকে এখন সেগুলোর জবাব দেওয়া যায়।
- **dev ডাটা:** PKG-20261107-0001 পুরো পথ পেরিয়েছে (৬২ পিস, ২ কার্টন, Hamburg), এখন Ready for Shipment-এ।
