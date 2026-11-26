# Packing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/04`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Packing Dashboard | `/m/garments/packing-dashboard` | 1 | 1 | 0 |
| 2 | Packing In | `/m/garments/packing-in` | 1 | 4 | 0 |
| 3 | Packing Out | `/m/garments/packing-out` | 1 | 1 | 0 |
| 4 | Packing Reports | `/m/garments/packing-reports` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Packing Dashboard

`/m/garments/packing-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Barcode | text |  | Scan or enter barcode |
- সেকশন: Packing on the Floor, Ready for Shipment, Shipment, Barcode Traceability
- বাটন/অ্যাকশন: Trace

#### Packing In

`/m/garments/packing-in`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section Select… Finishing | select |  | Select…, Finishing |
| Floor Select… | select |  | Select… |
| Packing Line / Location Select… | select |  | Select… |
| Barcode | text |  | Select Section, Floor and Line first |
- সেকশন: Packing In, Open Packing Batches, Today's Packing In
- বাটন/অ্যাকশন: Scan

#### Packing Out

`/m/garments/packing-out`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Packing Out barcode | text |  | Scan or enter barcode |
- সেকশন: Packing Out, Waiting for Packing Out, Packed Out Today
- বাটন/অ্যাকশন: Scan

#### Packing Reports

`/m/garments/packing-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report Daily Packing Report Packing In Report Packing Out Report Buyer-wise Pack | select |  | Daily Packing Report, Packing In Report, Packing Out Report, Buyer-wise Packing Report, PO-wise Packing Report, Style-wi |
| From | date |  |  |
| To | date |  |  |
| Buyer / PO / Style | text |  |  |
- সেকশন: Daily Packing Report
- বাটন/অ্যাকশন: Run, Print, CSV
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

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

- **প্যাকিং ব্যাচ:** এক PO আর এক রঙের সব বান্ডেল এক ব্যাচে। প্রথম স্ক্যানে ব্যাচ খোলে; Folding শুরু হওয়া পর্যন্ত একই PO-রঙের বান্ডেল সেই ব্যাচে যোগ হয়। Folding শুরু হলে ব্যাচ বন্ধ, পরের স্ক্যান নতুন ব্যাচ খোলে।
- **Packing In:**
  1. Section → Floor → Packing Line / Location বাছাই (ডেস্ক শেষ বাছাইটা মনে রাখে)।
  2. বান্ডেল স্ক্যান → বায়ার, অর্ডার, PO, স্টাইল, রং, সাইজ, অর্ডার পরিমাণ, QC Pass, আগেই প্যাক, বাকি, শিপমেন্টের তারিখ, ফ্যাক্টরি, QC রেফারেন্স, বারকোড আর কোন ব্যাচে যাবে (বা "A new batch opens")। কিছু সেভ হয় না।
  3. Packing In Qty (আগে থেকে বাকিটা বসানো, কমানো যায়) → Confirm Packing In।
  - আটকায়: বাতিল কার্ড, Quality-তে না যাওয়া, Quality OUT না হওয়া, পাস ০, পুরো প্যাক হয়ে যাওয়া, বাকির চেয়ে বেশি পরিমাণ।
  - সুপারভাইজার ভুল স্ক্যান সরাতে পারেন, শুধু ব্যাচ Packing In অবস্থায় থাকলে। ব্যাচের শেষ স্ক্যান সরালে ব্যাচ বাতিল।
- **সাতটা প্রসেস:** 20/05 দেখুন। একটার পর একটা, আগেরটা সম্পন্ন না হলে পরেরটা শুরু হয় না।
- **Packing Out:**
  - প্যাকিং নম্বর, কার্টন বা বান্ডেল স্ক্যান → ব্যাচের সব তথ্য আর ৯টা যাচাই: ৭টা প্রসেস সম্পন্ন, সব কার্টন সিল, সব পলি লেবেল যাচাই।
  - সব সবুজ হলে Final Packed (আগে থেকে সিল করা কার্টনের পরিমাণ) আর Balance = Packing In − Final → Confirm Packing Out।
  - Final > সিল করা কার্টনের পরিমাণ হলে আটকায়। একবারই প্যাকিং আউট।
  - প্যাকিং আউট হলেই পরিমাণ নিজে থেকে Ready for Shipment-এ যায় (হাতে ট্রান্সফার নেই)।
- **Dashboard:** QC Pass → Packing In → ৭টা প্রসেস → Packing Out → Ready for Shipment → Shipment প্রবাহ (প্রতিটায় পরিমাণ আর অবস্থা, ক্লিক করলে সেই স্ক্রিন); ৯টা হিসাব; Packing on the Floor (প্রতিটা ব্যাচ, ৭টা প্রসেসের বার আর অগ্রগতি); Ready for Shipment (PO-রং অনুযায়ী); Shipment; Barcode Traceability (বান্ডেল, প্যাকিং নম্বর, কার্টন বা লেবেল)।
- **রিপোর্ট:** Daily, Packing In, Packing Out, Buyer-wise, PO-wise, Style-wise, Barcode, Carton, Assortment, Pending (QC Pass, প্যাক হয়নি), Completed (৭টা প্রসেস শেষ)। তারিখের সীমা আর Buyer / PO / Style দিয়ে খোঁজা; Total সারি, Print আর CSV।

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

- **কোড:** `components/assembly/packing.jsx` (`PackingNav`, `PackingDashboard`, `PackingInDesk`, `ProcessDesk`, `PackingOutDesk`, `PackingReports`)। একই `assembly-floor/[screen]` রুট।
- **বাটনের সারি:** Brickland-এর মতো ১১টা স্ক্রিনের ওপরে একই সারি: Dashboard, Packing In, Folding, Poly, Barcode & Label, Assortment, Carton, Marking, Sealing, Packing Out, Reports।
- **Packing In:** বাঁয়ে Packing In কার্ড (তিনটা সিলেক্ট, স্ক্যান, তথ্যের গ্রিড, Packing In Qty, Confirm); ডানে Open Packing Batches আর Today's Packing In (সুপারভাইজারের জন্য মুছে ফেলার বাটন, নিচে "Supervisor may remove…" নোট)।
- **Packing Out:** স্ক্যান, সবুজ/লাল ফলাফল, ব্যাচের ১৫টা তথ্য, যাচাইয়ের তালিকা, Packing In / Final Packed / Balance, Confirm; ডানে Auto Transfer কার্ড; নিচে Waiting for Packing Out (Open বাটন) আর Packed Out Today।
- **Dashboard:** প্রবাহের সারি, ৯টা টাইল, Packing on the Floor, Ready for Shipment, Shipment, Barcode Traceability।
- **Reports:** রিপোর্ট বাছাই, From, To, Buyer / PO / Style, Run (খুললেই গত ৩০ দিন চলে); ফলাফলে সারির সংখ্যা, Print (A4, Total সারিসহ) আর CSV।

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

১. **প্যাকিং লোকেশন:** Brickland-এর Packing In-এ Section-এ শুধু "Finishing" আসে, আর আমাদের লাইন মাস্টারে PACKING টাইপ নেই। তাই এখন প্যাকিং লোকেশন = Finishing সেকশনের FINISHING লাইন (dev-এ "Packing Line1"-কে FINISHING টাইপ করা হয়েছে, তাই সেটা Finishing ডেস্কেও দেখায়)। লাইন মাস্টারে আলাদা PACKING টাইপ লাগবে কিনা জানাবেন।

২. **ব্যাচ আর লোকেশন:** ব্যাচ খোলে PO + রং অনুযায়ী। অন্য লোকেশনে একই PO-রঙের বান্ডেল স্ক্যান করলেও সেটা প্রথম লোকেশনের খোলা ব্যাচে যোগ হয়। লোকেশন অনুযায়ী আলাদা ব্যাচ লাগবে কিনা জানাবেন।

৩. **বান্ডেলের ভাগে প্যাকিং ইন:** একটা বান্ডেল ভাগে ভাগে আসতে পারে (বাকি পরিমাণ পরে, দরকার হলে পরের ব্যাচে)। এটা ঠিক আছে, নাকি প্রতিটা বান্ডেল একবারেই পুরোটা আসবে, জানাবেন।

৪. **Final Packed:** এখন Final Packed সিল করা কার্টনের মোট পরিমাণের বেশি হতে পারে না, কিন্তু কম হতে পারে (তখন Balance দেখায়)। কম হলে বাকি পিসের কী হবে (আবার প্যাকিং, নাকি লেফটওভার), জানাবেন।

৫. **QC Reference:** Brickland Packing In-এ "QC Reference · সময়" দেখায়। আমাদের Quality OUT-এর আলাদা ডকুমেন্ট নম্বর নেই, তাই এখন "QC-বান্ডেল নম্বর" আর Quality OUT-এর সময় দেখানো হচ্ছে। আলাদা QC নম্বর লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/pk01`, `pk02`, আর প্যাকিং স্ক্রিনগুলোর JS চাঙ্ক, `jsgrep.mjs`):**
  - ডেমোতে কোনো প্যাকিং ব্যাচ নেই, তাই কলাম, বার্তা আর নিয়ম চাঙ্ক থেকে নেওয়া।
  - পাওয়া নিয়ম: প্রতি PO আর রঙে একটা ব্যাচ, Folding শুরু পর্যন্ত খোলা; সুপারভাইজার শুধু Packing In অবস্থায় স্ক্যান সরাতে পারেন; Packing Out-এর যাচাইয়ের তালিকা আর Final Packed / Balance; "Final packed qty is added automatically — no manual transfer (Rule 08–09)"।
  - Packing Dashboard আর Packing Process একই বাটনের সারির নিচে, তাই 20/04 আর 20/05 একসাথে বানানো।
- **20/06 Ready For Shipment আর 20/07 Shipment পরে:**
  - ড্যাশবোর্ডের Ready for Shipment তালিকা এখন প্যাকড আউট ব্যাচ থেকে আসে।
  - Shipment তালিকা আর "Total Shipped" এখন ফাঁকা বা ০।
  - Ready for Shipment প্যানেলের "Open" লিংক আর প্রবাহের "Ready for Shipment" ধাপ এখন [20/06](06_Ready_For_Shipment.md)-এর ড্যাশবোর্ডে যায়। Shipment প্যানেল এখন সাম্প্রতিক ১০টা শিপমেন্ট দেখায়, তার "Open" যায় [20/07](07_Shipment.md)-এর ড্যাশবোর্ডে; "Total Shipped" আর প্রবাহের Shipment ধাপ আসল শিপড পরিমাণ দেখায়।
  - Packing Out এখন ব্যাচের সিল করা কার্টন সাইজ ধরে Finished Goods Stock-এর লেজারে তোলে (20/06 দেখুন)।
- **প্রিন্ট:** রিপোর্টের Print = `lib/print-document` (A4, কোম্পানির নাম, তারিখের সীমা, Total সারি)। প্রিন্ট দেখে নেওয়া হয়েছে (`app_check/out/pk04/rep-carton-print`)।
