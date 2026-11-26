# S/C Dyeing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৭টার ৭টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/09`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Out-House Sub-Contract Programme | `/m/dyeing/sc-out-house-program` | 2 | 19 | 1 |
| 2 | Sub-Contract Dye Batch Programme | `/m/dyeing/dyeing-sc-program-batch` | 1 | 6 | 1 |
| 3 | Sub-Contract Grey Fabric Indent List | `/m/dyeing/grey-fab-requisition-list-dyeing-sc` | 1 | 6 | 0 |
| 4 | Out-House Delivery Challan | `/m/dyeing/sc-out-house-challan` | 2 | 13 | 1 |
| 5 | Out-House Challan Receipt | `/m/dyeing/sc-out-house-challan-receive` | 2 | 13 | 1 |
| 6 | Out-House Floor Receipt | `/m/dyeing/sc-out-house-floor-receive` | 2 | 13 | 1 |
| 7 | Out-House Process Bill | `/m/dyeing/sc-out-house-bill` | 2 | 12 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Out-House Sub-Contract Programme

`/m/dyeing/sc-out-house-program`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Issue Date from | date |  |  |
| Issue Date to | date |  |  |
| Supplier | select |  | All |
| Store | select |  | All |
| Status | select |  | All, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Program No | search |  | DF-SC-OH-… |
| Search Dyeing programmes placed with an outside dye house, and where each has go | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Issue Date, Exp. Delivery Date, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, ACTION
- বাটন/অ্যাকশন: S/C Out House Program, Clear, Add New, Export, Program No, Issue Date, Exp. Delivery Date, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No

**ট্যাব: S/C Out House Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing programme | select |  |  |
| Issue Date* | date | ✔ |  |
| Exp. Delivery Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass No | text |  |  |
| Status* | select | ✔ | Select…, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Remarks | textarea |  |  |
| Buyer A/C | select |  | LPP (LPP) |
| Order # | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: S/C Out House Program, Programme Shades
- বাটন/অ্যাকশন: S/C Out House Program, -- Select S/C Party --, -- Select Store --, Add Shade, Cancel, -- Style --, -- Color --, Back To List, Clear, Save programme

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| Issue Date* | date | ✔ |  |
| Exp. Delivery Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass No | text |  |  |
| Status* | select | ✔ | Select…, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Remarks | textarea |  |  |
| Buyer A/C | select |  | LPP (LPP) |
| Order # | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no shades on it sends nothing to the party.

#### Sub-Contract Dye Batch Programme

`/m/dyeing/dyeing-sc-program-batch`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| S/C Program | text |  | --Select Program-- |
| S/C Party | text |  |  |
| Issue Date | text |  |  |
| Shade | select |  |  |
| Req Now(Kg) | number |  |  |
- সেকশন: Dyeing S/C Program List, Programme Shades
- বাটন/অ্যাকশন: -- Select Store --, Add, Cancel, Clear, Save requisition

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| S/C Program | text |  |  |
| S/C Party | text |  |  |
| Issue Date | text |  |  |
| Shade | select |  |  |
| Req Now(Kg) | number |  |  |
- খালি Save-এ মেসেজ: Pick the programme to call fabric off first.

#### Sub-Contract Grey Fabric Indent List

`/m/dyeing/grey-fab-requisition-list-dyeing-sc`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Order/Style | select |  |  |
| Status | select |  | All, Waiting for Issue, Issued |
| S/C Factory | select |  | All |
| Search Grey fabric called off against subcontract dyeing programmes, and what th | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, S/C Factory, Req Date, Req(Kg), Issue(Kg), Batch No, Requisition Type, ACTION
- বাটন/অ্যাকশন: Clear, Export, Requisition No, S/C Factory, Req Date, Req(Kg), Issue(Kg), Batch No, Requisition Type

#### Out-House Delivery Challan

`/m/dyeing/sc-out-house-challan`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Company | select |  | All |
| Supplier | select |  | All |
| Store | select |  | All |
| Search Challans covering fabric despatched to an outside dye house, and the prog | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Programmes, ACTION
- বাটন/অ্যাকশন: S/C Out House Challan, Clear, Add New, Export, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Programmes

**ট্যাব: S/C Out House Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
- সেকশন: S/C Out House Challan, Programmes on this challan
- বাটন/অ্যাকশন: S/C Out House Challan, -- Select S/C Party --, -- Select Store --, Add Programme, Cancel, Back To List, Clear, Save challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
- খালি Save-এ মেসেজ: A document naming no programme carries nothing.

#### Out-House Challan Receipt

`/m/dyeing/sc-out-house-challan-receive`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| S/C Party | select |  |  |
| Transfer Party | select |  |  |
| Challan # | search |  |  |
| Program # | search |  |  |
| Search Dyed fabric booked back into the store against an out-house challan, with | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: MRR No, Issue Date, Batch No, Challan No, Challan Date, Supplier, Transfer Party, Store, ACTION
- বাটন/অ্যাকশন: S/C Out House Challan Receive, Clear, Add New, Export, MRR No, Issue Date, Batch No, Challan No, Challan Date, Supplier, Transfer Party, Store

**ট্যাব: S/C Out House Challan Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing receipt | select |  |  |
| Issue Date* | date | ✔ |  |
| Challan | select |  |  |
| Batch No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
| Qty(Kg) | number |  |  |
- সেকশন: S/C Out House Challan Receive, Programmes coming back
- বাটন/অ্যাকশন: S/C Out House Challan Receive, -- Select S/C Party --, -- Select S/C Transfer Party --, -- Select Store --, Add Programme, Cancel, Back To List, Clear, Save receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receipt | select |  |  |
| Issue Date* | date | ✔ |  |
| Challan | select |  |  |
| Batch No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
| Qty(Kg) | number |  |  |
- খালি Save-এ মেসেজ: A receipt naming no programme receives nothing.

#### Out-House Floor Receipt

`/m/dyeing/sc-out-house-floor-receive`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| S/C Party | select |  |  |
| Transfer Party | select |  |  |
| Challan # | search |  |  |
| Program # | search |  |  |
| Search The floor's own check of out-house fabric, taken before the store will bo | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: MRR No, Issue Date, QC No, Challan No, Challan Date, Supplier, Transfer Party, Store, Program List, ACTION
- বাটন/অ্যাকশন: S/C Out House Floor Receive, Clear, Add New, Export, MRR No, Issue Date, QC No, Challan No, Challan Date, Supplier, Transfer Party, Store, Program List

**ট্যাব: S/C Out House Floor Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing floor receipt | select |  |  |
| Issue Date* | date | ✔ |  |
| Challan | select |  |  |
| QC No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
| Qty(Kg) | number |  |  |
- সেকশন: S/C Out House Floor Receive, Programmes coming back
- বাটন/অ্যাকশন: S/C Out House Floor Receive, -- Select S/C Party --, -- Select S/C Transfer Party --, -- Select Store --, Add Programme, Cancel, Back To List, Clear, Save floor receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing floor receipt | select |  |  |
| Issue Date* | date | ✔ |  |
| Challan | select |  |  |
| QC No | text |  |  |
| Remarks | textarea |  |  |
| Programme | select |  |  |
| Qty(Kg) | number |  |  |
- খালি Save-এ মেসেজ: A receipt naming no programme receives nothing.

#### Out-House Process Bill

`/m/dyeing/sc-out-house-bill`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Party | select |  |  |
| Date from | date |  |  |
| Date to | date |  |  |
| Status | select |  | All, Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
| Search Subcontractor bills for out-house dyeing, with the revision trail behind  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Bill#, Ref.Bill#, Rev#, Rev.Descr, Supplier, Amount, ACTION
- বাটন/অ্যাকশন: New bill, Clear, Export, Date, Bill#, Ref.Bill#, Rev#, Rev.Descr, Supplier, Amount

**ট্যাব: New bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Amount* | number | ✔ |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Status* | select | ✔ | Select…, Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New bill
- বাটন/অ্যাকশন: New bill, -- Select --, Reset, Save bill

**ডায়ালগ: New bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Amount* | number | ✔ |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Status* | select | ✔ | Select…, Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Date is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/03-এর Pre-Treatment (PT) প্রোগ্রাম, চালান, রিসিপ্ট আর বিল:** ধারণা একই, কিন্তু PT-তে কোনো লাইন নেই। S/C Out-House-এ শেডের লাইন, গ্রে রিকুইজিশন আর দুই ধাপের রিসিপ্ট আছে, তাই এর নিজের টেবিল।
- **17/06-এর editor অংশ আর `ChemRegister`:** Grey Fabric Indent List-এর Issue sheet হলো `ChemRegister`-এর issue dialog। server action রিকুইজিশনকে ওই আকারে সাজিয়ে দেয়।
- **মাস্টার:** সাপ্লায়ার (`mp_suppliers`, S/C পার্টি), স্টোর, বায়ার, অর্ডার, কালার।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_01_000100_create_dye_sc_out_house.php`:
- **`dye_sc_programmes`:** program_no (DF-SC-OH-YYYYMM####), issue_date, exp_delivery_date, company, supplier (S/C পার্টি), store, gate_pass_no, status DRAFT/SUBMITTED/PARAMS_UPDATED/DESPATCHED/COMPLETED/CANCELLED, remarks। **শেড:** buyer, order, colour, req_kg, remarks।
- **`dye_sc_requisitions`:** req_no (DF-SC-RQ-…), batch_no (DF-SC-B-YYMM####), req_date, programme, store, status WAITING/PARTIAL/ISSUED। **লাইন:** shade, req_kg, issued_kg।
- **`dye_sc_challans`:** challan_no (DF-SC-CH-…), challan_date, company, supplier, store, gate_pass_no, remarks। **প্রোগ্রাম:** challan, programme। প্রতিটা প্রোগ্রাম একটাই চালানে (ইউনিক)।
- **`dye_sc_receipts`:** kind FLOOR (DF-SC-FR-…) বা STORE (DF-SC-MRR-…), issue_date, challan, supplier, transfer_party, store, batch_no (স্টোর), qc_no (ফ্লোর), remarks। **লাইন:** programme, qty_kg।
- **`dye_sc_bills`:** bill_no (DF-SC-BL-…), bill_date, supplier, amount, ref_bill (যে বিলের সংশোধন), revision_no, revision_descr, status DRAFT/WAITING_STORE/APPROVED/COMPLETED/CANCELLED, remarks।

## ৫. Workflow / Status ও Business Rules

- **প্রোগ্রাম:**
  - S/C পার্টি, স্টোর আর অবস্থা বাধ্যতামূলক, আর অন্তত একটা শেড (কালার আর কেজি) লাগে। সম্ভাব্য ডেলিভারির তারিখ ইস্যুর আগে হতে পারে না।
  - Completed বা Cancelled হলে শুধু দেখা যায়।
  - যে শেডের বিপরীতে গ্রে চাওয়া হয়েছে সেটা সরানো যায় না, আর তার কেজি চাওয়া পরিমাণের নিচে নামানো যায় না।
- **Dye Batch Programme:**
  - শুধু চালু প্রোগ্রাম (Submitted, Process Parameter Updated বা Despatched)।
  - প্রতিটা শেডে যতটা বাকি তার বেশি চাওয়া যায় না।
  - সেভ করলে রিকুইজিশন নিজের ব্যাচ নম্বর পায়। ইস্যুর আগ পর্যন্ত বদলানো যায়।
- **Grey Fabric Indent List:** Issue sheet-এ প্রতিটা লাইনে যতটা বাকি তার বেশি দেওয়া যায় না। কম দিলে Partial Issued, পুরোটা দিলে Issued।
- **চালান:**
  - পার্টির নিজের চালু প্রোগ্রাম, যেগুলো অন্য চালানে নেই। সেভ করলে প্রোগ্রাম Despatched হয়।
  - কিছু ফেরত আসার আগে চালান বদলানো যায়। চালান থেকে সরানো প্রোগ্রাম আবার Submitted হয়।
- **রিসিপ্ট:**
  - চালানের প্রোগ্রামগুলোই ফিরতে পারে। তারিখ চালানের আগে হতে পারে না।
  - আগে Floor Receipt (QC No সহ), তারপর Challan Receipt (Batch No সহ)। স্টোর কোনো প্রোগ্রামের যতটা ফ্লোরে এসেছে তার বেশি তুলতে পারে না।
  - স্টোরে পুরো চাওয়া কেজি উঠলে প্রোগ্রাম Completed হয়।
- **বিল:**
  - পরিমাণ শূন্যের বেশি হতে হবে।
  - Ref.Bill# থাকলে সেটা একই পার্টির অন্য বিল হতে হবে, আর নতুন বিল তার পরের সংশোধন নম্বর পায়।
  - Completed বা Cancelled হলে শুধু দেখা যায়।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.sc.view`: FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER।
  - `dyehouse.sc.manage`: FACTORY_ADMIN আর FACTORY_MANAGER।
  - `dyehouse.sc.issue`: FACTORY_ADMIN আর STORE_MANAGER।
- **Screen-kit resource:** `/api/v1/erp/dye-sc-bills`
- **API:**
  - `GET /dyehouse/sc/options`
  - `GET|POST /dyehouse/sc-programmes`, `GET|PUT /dyehouse/sc-programmes/{no}`
  - `GET|POST /dyehouse/sc-requisitions`, `GET|PUT /dyehouse/sc-requisitions/{no}`, `POST /dyehouse/sc-requisitions/{no}/issue`
  - `GET|POST /dyehouse/sc-challans`, `GET|PUT /dyehouse/sc-challans/{no}`
  - `GET|POST /dyehouse/sc-receipts?kind=`, `GET|PUT /dyehouse/sc-receipts/{no}`
- **মেনু:** Dye House-এর নতুন ফোল্ডার S/C DYEING, ৭টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `dye-house/[screen]`-এ, `sc-screens.js` থেকে। কম্পোনেন্ট `components/dyehouse/sc-out-house.jsx` (`ScProgrammeEditor`, `ScBatchProgramme`, `ScChallanEditor`, `ScReceiptEditor`)।
- **প্রোগ্রাম, চালান আর দুই রিসিপ্ট:** Records আর editor ট্যাব, "Existing …" select, সারিতে Edit (`?tab=entry&doc=…`)।
- **Dye Batch Programme:** উপরে "Dyeing S/C Program List" (চালু প্রোগ্রাম আর তার বাকি কেজি), ক্লিক করলে বাছা হয়। নিচে শেড আর এখন কত চাওয়া হবে।
- **Indent List:** ফিল্টার আর গ্রিড। প্রতিটা সারিতে Issue, যেটা sheet খোলে।
- **বিল:** Records আর New bill, Edit একই পাতায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৭টার ৭টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_02_01_000100_create_dye_sc_out_house.php`
- `app/Modules/DyeHouse/Models/`: `DyeScProgramme`, `DyeScProgrammeShade`, `DyeScRequisition`, `DyeScRequisitionLine`, `DyeScChallan`, `DyeScChallanProgramme`, `DyeScReceipt`, `DyeScReceiptLine`, `DyeScBill`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeScOutHouseApiController.php`
- `app/Modules/DyeHouse/Resources/DyeScBillResource.php`
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `sc` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/sc-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/sc-out-house.jsx` (নতুন)

**Test**
- `tests/Feature/DyeHouse/DyeHouseScOutHouseTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseScOutHouseTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ১০৭টা assertion।
- `test_programme_shades_are_called_off_and_issued_no_further_than_they_ask`:
  - শেড ছাড়া প্রোগ্রাম আর ইস্যুর আগের ডেলিভারি তারিখ আটকায়।
  - খসড়া প্রোগ্রামে রিকুইজিশন আটকায়। Submitted হলে চলে, নম্বর DF-SC-RQ- আর ব্যাচ DF-SC-B-।
  - বাকির বেশি চাইলে আটকায় ("only 180 kg")। চাওয়া শেড সরানো বা তার নিচে নামানো আটকায়।
  - Issue: বাকির বেশি দিলে আটকায়। কম দিলে Partial, পুরো দিলে Issued। ইস্যুর পরে বদলানো আটকায়।
- `test_challan_despatches_and_receipts_bring_back_no_more_than_the_floor_passed`:
  - অন্য পার্টির প্রোগ্রাম, আর একই প্রোগ্রাম দুই চালানে দিলে আটকায়। চালানে দিলে Despatched, সরালে আবার Submitted।
  - ফ্লোরের আগে স্টোরে তুললে আটকায়। চালানের আগের তারিখ আর চালানের বাইরের প্রোগ্রাম আটকায়।
  - ফ্লোর ২৯৮.৫ আর ১০০। ফেরত আসার পরে চালান বদলানো আটকায়। স্টোরে ফ্লোরের বেশি তুললে আটকায়।
  - পুরো উঠলে Completed, কম উঠলে Despatched থাকে।
- `test_bills_carry_their_revision_trail`: শূন্য টাকা আটকায়। অন্য পার্টির Ref আটকায়। সংশোধন নম্বর ০, ১, ২। Cancelled হলে শুধু দেখা যায়।
- `test_who_may_raise_and_issue`: STORE_MANAGER দেখতে আর ইস্যু করতে পারে, প্রোগ্রাম তুলতে পারে না। FACTORY_MANAGER দেখতে পারে। TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseQualityTest` (৫টা টেস্ট, ১০৭টা assertion) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/sc01`, `sc02`), dev অ্যাপে:**
- **প্রোগ্রাম:** DF-SC-OH-2026110001, Square Textiles, Fabric store (DHK-FAB), GP-SC-01। শেড: Black ২৫০ kg (SS27-TEE01) আর Burgundy ১৫০ kg। অবস্থা S/C OH Submitted।
- **Batch Programme:** তালিকা থেকে প্রোগ্রাম বাছা হয়েছে, Black থেকে ১২০ kg চাওয়া হয়েছে। নম্বর DF-SC-RQ-2026110001, ব্যাচ DF-SC-B-26110001।
- **Indent List:** Issue sheet থেকে ১২০ kg ইস্যু হয়ে Issued। Batch Programme-এর তালিকায় বাকি ২৮০ kg।
- **চালান:** DF-SC-CH-2026110001 (GP-SC-02), প্রোগ্রাম Despatched হয়েছে।
- **রিসিপ্ট:** ফ্লোর DF-SC-FR-2026110001 (QC-SC-1, ৩৯৮ kg), তারপর স্টোর DF-SC-MRR-2026110001 (SC-LOT-01, ৩৯৮ kg)। চাওয়া ৪০০-র কম, তাই প্রোগ্রাম Despatched থেকে গেছে।
- **বিল:** DF-SC-BL-2026110001, ৩৬,০০০ টাকা, Waiting For Store Approval।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।

**প্রিন্ট:** Brickland-এর এই সাতটা স্ক্রিনে কোনো Print বাটন নেই।

## ১০. খোলা প্রশ্ন

১. **নম্বরের ফরম্যাট:** Brickland থেকে শুধু প্রোগ্রামের DF-SC-OH- জানা গেছে। বাকিগুলো আমাদের দেওয়া: DF-SC-RQ-, DF-SC-B-, DF-SC-CH-, DF-SC-FR-, DF-SC-MRR- আর DF-SC-BL-। আসল ফরম্যাট জানাবেন।

২. **গ্রে ইস্যু আর স্টক:** Grey Fabric Indent List-এর Issue এখন শুধু ইস্যুর পরিমাণ লেখে। নিটিংয়ের গ্রে স্টোর লেজার (`knit_grey_lots`) থেকে স্টক কমায় না, কারণ সাব-কন্ট্রাক্টের ফেব্রিক কোনো ব্যাচ কার্ডে নেই। কোন লট থেকে ইস্যু হবে তা বাছতে হলে জানাবেন।

৩. **ফেরত আসা ফেব্রিক কোথায় যায়:** Challan Receipt এখন শুধু রেকর্ড রাখে। ফিনিশড ফেব্রিক স্টোরের স্টকে (সাব-কন্ট্রাক্ট হিসেবে) ঢোকানো লাগবে কিনা, আর কোন মডিউলে, জানাবেন।

৪. **Completed হওয়ার নিয়ম:** স্টোরে পুরো চাওয়া কেজি উঠলে প্রোগ্রাম Completed হয়। প্রসেস লস ধরে কম উঠলেও Completed করা লাগবে কিনা জানাবেন। এখন ডেস্ক অবস্থা হাতে বদলে দিতে পারে।

৫. **বিল আর রিসিপ্ট:** বিল এখন কোনো রিসিপ্ট বা প্রোগ্রামের সাথে জোড়া নয়, শুধু পার্টি আর টাকা থাকে (Brickland-এর ফর্মেও তাই)। রেট ধরে বিল হিসাব করা লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland probe:** ডকে আগে থেকে থাকা লাইভ বিবরণ (ফর্ম, গ্রিড আর অবস্থার তালিকা) থেকে বানানো হয়েছে। demo-তে কোনো রেকর্ড নেই।
- **Issue sheet:** নতুন dialog বানানো হয়নি। 17/06-এর `ChemRegister`-এর issue dialog-ই ব্যবহার হয়েছে, server action (`loadScIssue`) রিকুইজিশনকে ওই আকারে সাজায়।
- **migration-এ ঠিক করা:** বিলের নিজের টেবিলের দিকে foreign key একই create-এ দিলে Postgres আটকায়। তাই এটা আলাদা `Schema::table` দিয়ে পরে যোগ হয়।
