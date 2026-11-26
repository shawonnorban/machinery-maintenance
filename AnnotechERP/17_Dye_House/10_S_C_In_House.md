# S/C In House

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/10`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sub-Contract Order Register | `/m/dyeing/sc-order-list` | 2 | 15 | 1 |
| 2 | In-House Sub-Contract Fabric Receipt | `/m/dyeing/sc-in-house-fabric-receive` | 2 | 12 | 1 |
| 3 | In-House Sub-Contract Party Rate | `/m/dyeing/dyeing-sc-party-rate-in-house` | 1 | 11 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sub-Contract Order Register

`/m/dyeing/sc-order-list`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  |  |
| Program No. | search |  | DFSCI-… |
| Order No. | search |  |  |
| Prog Date from | date |  |  |
| Prog Date to | date |  |  |
| Status | select |  | All, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Search Dyeing and finishing programmes taken in from another party, and what eac | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Supplier, Order No, Style No, Buyer Name, Process Name, Prog Date, Remarks, ACTION
- বাটন/অ্যাকশন: Add Program, Clear, Export, Program No, Supplier, Order No, Style No, Buyer Name, Process Name, Prog Date, Remarks

**ট্যাব: Add Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prog Date* | date | ✔ |  |
| Buyer Name | select |  | LPP (LPP) |
| Order No | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Dyeing | checkbox |  |  |
| Dyeing Finishing | checkbox |  |  |
| Status* | select | ✔ | Select…, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New programme
- বাটন/অ্যাকশন: Add Program, -- Select --, Reset

**ডায়ালগ: Add Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Prog Date* | date | ✔ |  |
| Buyer Name | select |  | LPP (LPP) |
| Order No | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Dyeing | checkbox |  |  |
| Dyeing Finishing | checkbox |  |  |
| Status* | select | ✔ | Select…, Draft, S/C OH Submitted, Process Parameter Updated, Despatched, Completed, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Tick at least one process. A programme that covers neither dyeing nor finishing is work nobody agreed to.

#### In-House Sub-Contract Fabric Receipt

`/m/dyeing/sc-in-house-fabric-receive`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No. | search |  |  |
| Supplier | select |  |  |
| Program No. | select |  |  |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Fabric received in from a party against an in-house programme, on their o | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Store Name, Supplier, Program No, Prog Date, Qty(Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Challan No, Challan Date, Store Name, Supplier, Program No, Prog Date, Qty(Kg), Remarks

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No.* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Program No.* | select | ✔ |  |
| Qty(Kg)* | number | ✔ |  |
| Remarks | textarea |  |  |
- সেকশন: New receipt
- বাটন/অ্যাকশন: Add New, -- Select Store --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Challan No.* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Program No.* | select | ✔ |  |
| Qty(Kg)* | number | ✔ |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Challan No. is required.

#### In-House Sub-Contract Party Rate

`/m/dyeing/dyeing-sc-party-rate-in-house`

**ট্যাব: S/C Party Wise Rate Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Rate Group List | search |  | search… |
| Existing quotation | select |  |  |
| Rate Group | text |  | Pick one on the left |
| Quatation# | text |  | Left blank, the server issues one |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  | Signed rate sheet, 14-Aug-2026 |
- গ্রিড কলাম: Rate Group
- সেকশন: Rate Group List, S/C Party Wise Rate Entry, Quatation Rate Detail, Selected Docs
- বাটন/অ্যাকশন: S/C Party Wise Rate Entry, Knitting, Yarn Dyeing, Dyeing, Dyeing Finishing, AOP, Yarn Twist, -- Select --, Add, Cancel, Attach, Clear, Save quotation

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Rate Group List | search |  |  |
| Existing quotation | select |  |  |
| Rate Group | text |  |  |
| Quatation# | text |  |  |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  |  |
- খালি Save-এ মেসেজ: Pick a rate group on the left before saving — a quotation is always for one group.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/01-এর S/C Party Rate:** `dye_sc_quotations` টেবিল, `DyeScQuotationApiController` আর `ScQuotationEditor`। In-House Party Rate এগুলোই ব্যবহার করে, শুধু নতুন `side` ঘরে IN লেখা থাকে।
- **মাস্টার:** সাপ্লায়ার (`mp_suppliers`, পার্টি), বায়ার, অর্ডার, স্টোর।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_02_000100_create_dye_sc_in_house.php`:
- **`dye_sc_quotations.side`:** OUT (17/01, পার্টি আমাদের কাছে যে রেট নেয়; ডিফল্ট) বা IN (আমরা পার্টির কাছে যে রেট নিই)।
- **`dye_sci_programmes`:** program_no (DFSCI-YYYYMM####), prog_date, supplier (পার্টি), buyer, order, dyeing, dyeing_finishing (boolean), status (S/C-এর মতোই ছয়টা), remarks।
- **`dye_sci_receipts`:** challan_no (পার্টির নিজের, প্রতি পার্টিতে ইউনিক), challan_date, programme, supplier (প্রোগ্রামেরটা), store, qty_kg, remarks।

## ৫. Workflow / Status ও Business Rules

- **Order Register:**
  - তারিখ, পার্টি আর অবস্থা বাধ্যতামূলক। Dyeing বা Dyeing Finishing, অন্তত একটা টিক লাগবে।
  - অর্ডার বাছলে তার বায়ার বসে। অন্য বায়ারের অর্ডার দিলে আটকায়।
  - নম্বর সার্ভার দেয়, আর পরে বদলায় না। যে প্রোগ্রামের বিপরীতে ফেব্রিক এসেছে সেটা মোছা যায় না।
  - Records-এ প্রতিটা প্রোগ্রামের মোট রিসিভ কেজি দেখায়।
- **Fabric Receipt:**
  - পার্টি হলো প্রোগ্রামেরটাই। চালান নম্বর বড় হাতের অক্ষরে রাখা হয়, প্রতি পার্টিতে একবার।
  - তারিখ প্রোগ্রামের আগে হতে পারে না। বাতিল বা সম্পন্ন প্রোগ্রামের বিপরীতে নতুন রিসিপ্ট আটকায়।
- **Party Rate (In-House):**
  - 17/01-এর মতোই রেট গ্রুপ, হেডার, রেটের লাইন আর ডকুমেন্ট।
  - নতুন কোটেশন IN হিসেবে রাখা হয়, আর পরে বদলানো যায় না।
  - 17/01-এর তালিকায় শুধু OUT দেখায়, এখানে শুধু IN।

## ৬. API ও পারমিশন

- **পারমিশন:** Order Register আর Fabric Receipt 17/09-এর `dyehouse.sc.view` আর `dyehouse.sc.manage` ব্যবহার করে। Party Rate 17/01-এর মতো Dye House বা Knit Production-এর master পারমিশন ব্যবহার করে।
- **Screen-kit resource:** `/api/v1/erp/dye-sci-programmes`, `/api/v1/erp/dye-sci-receipts`
- **API (বদলানো):**
  - `GET /dyehouse/sc-quotations?side=IN|OUT` (ডিফল্ট OUT)
  - `POST /dyehouse/sc-quotations` এখন `side` নেয়
  - `GET|PUT /dyehouse/sc-quotations/{no}` এখন কোটেশন নম্বর নেয় (পুরোনো লিংকের জন্য id-ও চলে)
- **মেনু:** Dye House-এর নতুন ফোল্ডার S/C IN-HOUSE, ৩টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `dye-house/[screen]`-এ, `sci-screens.js` থেকে।
- **Order Register আর Fabric Receipt:** Records আর ফর্ম ট্যাব (Add Program / Add New)। Edit আর Delete একই পাতায়।
- **Party Rate:** Brickland-এর মতো একটাই পাতা (S/C Party Wise Rate Entry), `ScQuotationEditor` দিয়ে, `side="IN"`।
- **17/01 আর Knit Production-এর ঠিক করা:** Party Rate-এর Records-এ "Open" লিংকে আগে কোটেশনের ULID থাকত। এখন কোটেশন নম্বর থাকে (`?quotation=DSQ-2026-0002`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_02_02_000100_create_dye_sc_in_house.php`
- `app/Modules/DyeHouse/Models/`: `DyeSciProgramme`, `DyeSciReceipt` (নতুন); `DyeScQuotation` (বদলানো: `side`, `SIDES`)
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeScQuotationApiController.php` (বদলানো: side, নম্বর দিয়ে খোঁজা)
- `app/Modules/DyeHouse/Resources/DyeSciProgrammeResource.php`, `DyeSciReceiptResource.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `sci` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/sci-screens.js` (নতুন), `page.js` (বদলানো), `screens.js` (বদলানো: Open লিংক)
- `frontend/src/app/(app)/knit-production/[screen]/screens.js` (বদলানো: Open লিংক)
- `frontend/src/components/dyehouse/sc-quotation-editor.jsx` (বদলানো: `side`)

**Test**
- `tests/Feature/DyeHouse/DyeHouseScInHouseTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseScInHouseTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ৫৯টা assertion।
- `test_a_programme_taken_in_names_its_processes_and_its_buyer_follows_the_order`:
  - প্রসেস ছাড়া প্রোগ্রাম আর অন্য বায়ারের অর্ডার আটকায়।
  - নম্বর DFSCI-…0001। অর্ডার থেকে বায়ার, স্টাইল আর অর্ডার নম্বর আসে।
  - দুটো প্রসেস খুলে দিলে আটকায়, আর নম্বর বদলানো যায় না। রিসিপ্টের পরে মোছা আটকায়।
- `test_fabric_is_received_on_the_partys_own_challan`:
  - শূন্য কেজি আর প্রোগ্রামের আগের তারিখ আটকায়।
  - চালান নম্বর HM-7701 (বড় হাতের অক্ষরে)। একই পার্টির একই চালান দুবার দিলে আটকায়, অন্য পার্টির চলে।
  - প্রোগ্রামের মোট রিসিভ ৫০০.৫। বাতিল প্রোগ্রামের বিপরীতে রিসিপ্ট আটকায়।
- `test_the_in_house_party_rate_is_kept_apart_from_out_house_rates`: IN আর OUT আলাদা তালিকায় থাকে। ভুল side আটকায়। নম্বর দিয়ে খোলা যায়। side পরে বদলানো যায় না।
- `test_who_may_take_programmes_in`: STORE_MANAGER দেখতে পারে, তুলতে পারে না। TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseMasterTest` (৬টা, ৯৪টা), `KnittingMasterTest` (৫টা, ১০০টা) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/sci01`–`sci04`), dev অ্যাপে:**
- **Order Register:** DFSCI-2026110001, Square Textiles, অর্ডার SS27-TEE01 (বায়ার LPP নিজে বসেছে), Dyeing Finishing, S/C OH Submitted।
- **Fabric Receipt:** SQ-CH-1105, Fabric store (DHK-FAB), ৪২০ kg। প্রোগ্রামের রিসিভ কেজি ০ থেকে ৪২০ হয়েছে।
- **Party Rate (In-House):** Yarn Twist গ্রুপে Square Textiles, BDT, Submitted। "Dyeing (dark shade)" ১৪৫ টাকা, একটা ডকুমেন্ট। নম্বর DSQ-2026-0003, side IN।
- **17/01-এর তালিকা:** শুধু দুটো OUT কোটেশন দেখায়। Open লিংক `?quotation=DSQ-2026-0002` দিয়ে ঠিকমতো খোলে।
- **প্ল্যানে ঠিক করা:** প্রথমবার Prog Date আর Challan Date দেওয়া হয়নি, তাই Brickland-এর মতোই "Prog Date is required." এসেছিল। তারিখ দিয়ে আবার চালানো হয়েছে।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।

**প্রিন্ট:** Brickland-এর এই তিনটা স্ক্রিনে কোনো Print বাটন নেই।

## ১০. খোলা প্রশ্ন

১. **প্রোগ্রামের অবস্থা:** Brickland-এর In-House প্রোগ্রামেও Out-House-এর অবস্থাগুলোই আছে ("S/C OH Submitted", "Despatched")। আমরাও তাই রেখেছি। In-House-এর আলাদা অবস্থা লাগবে কিনা জানাবেন।

২. **ইন-হাউস ফেব্রিক কোথায় যায়:** Fabric Receipt এখন শুধু রেকর্ড রাখে। পার্টির ফেব্রিক গ্রে স্টোরে (পার্টির নামে আলাদা) ঢুকবে কিনা, আর এর থেকে ব্যাচ তৈরি হবে কিনা, জানাবেন।

৩. **ইন-হাউস বিল:** পার্টিকে বিল করার স্ক্রিন Brickland-এর এই ফোল্ডারে নেই। In-House রেট থেকে বিল কোথায় হবে (Accounts নাকি এখানে), জানাবেন।

## ১১. As-built নোট

- **Brickland:** ডকে আগে থেকে থাকা লাইভ বিবরণ থেকে বানানো হয়েছে। Party Rate-এর গঠন 17/01-এর মতোই (Rate Group List, S/C Party Wise Rate Entry, Quatation Rate Detail, Selected Docs)।
- **একই টেবিল, দুই দিক:** In-House রেট আলাদা টেবিল নয়। 17/01-এর কোটেশনেই `side` যোগ হয়েছে, তাই নম্বরের ধারা (DSQ-) দুই দিকেই একটা।
- **পুরোনো নিয়ম-ভঙ্গ ঠিক করা:** 17/01 আর Knit Production-এর Party Rate-এ URL-এ ULID ছিল। এখন কোটেশন নম্বর থাকে।
