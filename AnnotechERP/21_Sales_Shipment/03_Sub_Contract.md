# Sub-Contract

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/03`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sub-Contract Delivery Register | `/m/sales-delivery/sc-delivery-list` | 2 | 18 | 0 |
| 2 | Sub-Contract Receipt Register | `/m/sales-delivery/sc-receive-list` | 2 | 16 | 0 |
| 3 | Sub-Contract Pending | `/m/sales-delivery/sc-pending` | 1 | 5 | 0 |
| 4 | Sub-Contract Return | `/m/sales-delivery/sc-return` | 2 | 14 | 0 |
| 5 | Sub-Contract Reconciliation | `/m/sales-delivery/sc-reconciliation` | 2 | 14 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sub-Contract Delivery Register

`/m/sales-delivery/sc-delivery-list`

**ট্যাব: Sales Contract Delivery List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Chalan No. | text |  |  |
| SC No. | text |  |  |
| Target From Date | date |  |  |
| Target To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Sales Contract Delivery List 0, Sales Contract Delivery Entry, All, Search, Reset

**ট্যাব: Sales Contract Delivery Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search SC List | search |  | Search SC No, SO No or party… |
| Existing S/C delivery chalan | select |  |  |
| Sales Contract* | select | ✔ | Select… |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| SC No | text |  |  |
| Chalan No | text |  | [Auto Gen] |
| Chalan Date* | date | ✔ |  |
| Delivery Target Date* | date | ✔ |  |
| Mode of Shipment* | select | ✔ | Select…, By Van, By Truck, By Courier, By Air, By Sea, By Hand |
| Gate Pass No | text |  | [Auto Gen] |
| Carrier Name | text |  |  |
| Carrier No | text |  |  |
| PR/PO Ref. | textarea |  | BUYER \| STYLE \| ORDER# |
| Remarks | textarea |  |  |
- গ্রিড কলাম: SC No, SO No, Party, Due Qty
- সেকশন: SC List, Sales Contract Delivery Entry, Master Data, Carrier & References, Details Data
- বাটন/অ্যাকশন: Sales Contract Delivery List 0, Sales Contract Delivery Entry, -- Select Supplier/Customer --, Select…, Add line, Back To List, Clear, Save S/C delivery chalan

#### Sub-Contract Receipt Register

`/m/sales-delivery/sc-receive-list`

**ট্যাব: Sales Contract Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | text |  |  |
| Challan No | text |  |  |
| SO No | text |  |  |
| Company | select |  | All, Brickland Composite Ltd. |
| With Finalized? | select |  | All, Yes, No |
| Receive Type | select |  | All, Finished Goods, Left Over, Rejection |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Sales Contract Receive List 0, SC Receive Entry, All, Search, Reset

**ট্যাব: SC Receive Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search S/C Challan List | search |  | Search chalan no, SC no or party… |
| Existing S/C receipt | select |  |  |
| S/C Challan* | select | ✔ | Select… |
| MRR No | text |  | [Auto Gen] |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Receive Type* | select | ✔ | Select…, Finished Goods, Left Over, Rejection |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Chalan No, SC No, Party, Still Out
- সেকশন: S/C Challan List, SC Receive Entry, Master Data, Details Data
- বাটন/অ্যাকশন: Sales Contract Receive List 0, SC Receive Entry, -- Select Supplier/Customer --, Select…, Add line, Back To List, Clear, Save S/C receipt

#### Sub-Contract Pending

`/m/sales-delivery/sc-pending`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| SC No. | text |  |  |
| Delivery From Date | date |  |  |
| Delivery To Date | date |  |  |
| Overdue only | checkbox |  |  |
| Show completed | checkbox |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: -- Select Supplier/Customer --, Search, Reset

#### Sub-Contract Return

`/m/sales-delivery/sc-return`

**ট্যাব: Sub-Contract Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Return No. | text |  |  |
| MRR No. | text |  |  |
| State | select |  | All, Draft, Confirmed, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Sub-Contract Return List 0, Sub-Contract Return Entry, All, Search, Reset

**ট্যাব: Sub-Contract Return Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search S/C Receive List | search |  | Search MRR no, chalan no or party… |
| Existing S/C return | select |  |  |
| S/C Receipt (MRR)* | select | ✔ | Select… |
| Return No | text |  | [Auto Gen] |
| S/C Chalan No | text |  |  |
| Party | text |  |  |
| Return Date* | date | ✔ |  |
| Reason* | select | ✔ | Select…, Rework, Rejection Return, Wrong Delivery, Other |
| Remarks | textarea |  |  |
- গ্রিড কলাম: MRR No, Chalan No, Party, Returnable
- সেকশন: S/C Receive List, Sub-Contract Return Entry, Master Data, Details Data
- বাটন/অ্যাকশন: Sub-Contract Return List 0, Sub-Contract Return Entry, -- Select Supplier/Customer --, Add line, Back To List, Clear, Save S/C return

#### Sub-Contract Reconciliation

`/m/sales-delivery/sc-reconciliation`

**ট্যাব: Sub-Contract Reconciliation List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Recon No. | text |  |  |
| S/C Chalan No. | text |  |  |
| State | select |  | All, Pending, Matched, Short, Excess, Reconciled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Sub-Contract Reconciliation List 0, Sub-Contract Reconciliation Entry, All, Search, Reset

**ট্যাব: Sub-Contract Reconciliation Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search S/C Challan List | search |  | Search chalan no, SC no or party… |
| Existing S/C reconciliation | select |  |  |
| S/C Challan* | select | ✔ | Select… |
| Recon No | text |  | [Auto Gen] |
| S/C Chalan No | text |  |  |
| Party | text |  |  |
| Recon Date* | date | ✔ |  |
| Status | text |  | [Worked out on save] |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Chalan No, SC No, Party, Still Out, Open Recon
- সেকশন: S/C Challan List, Sub-Contract Reconciliation Entry, Master Data, Details Data
- বাটন/অ্যাকশন: Sub-Contract Reconciliation List 0, Sub-Contract Reconciliation Entry, -- Select Supplier/Customer --, Add line, Back To List, Clear, Save S/C reconciliation
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/02-এর S/C Programme (`ss_sc_programmes`):** Brickland-এর "Sales Contract"। ডেলিভারি চালানের লাইন এর লাইন থেকে আসে, আর রেট প্রোগ্রামের রেট।
- **21/01-এর `SsSettings`:** S/C রিটার্নের নম্বর (SCRT-2026-000001) আর রিকনসিলিয়েশনের নম্বর (SCRC-2026-000001)।
- **21/02-এর `SalesBook`:** নাম, দিনভিত্তিক নম্বর (SCD-…, SGP-…, SCR-…) আর মাস বন্ধের লক। মাস বন্ধের ব্যতিক্রমে এখন ড্রাফট S/C রিটার্নও আসে (DRAFT_SC_RETURN)।
- **21/02-এর shared UI** (`shared.jsx`): ট্যাব, ফিল্টার, টাইল, রেজিস্টার, বাঁদিকের তালিকা, এডিট-গ্রিড। Filters-এ এখন checkbox ধরনের ফিল্টারও আছে (Pending স্ক্রিনের জন্য)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_22_000100_create_ss_subcontract.php` (৮টা টেবিল):

- **`ss_sc_deliveries`** (SCD-YYYYMMDD-####, গেট পাস SGP-একই নম্বর) + লাইন (programme line, qty, rate, remarks):
  - party, transfer_from (এক পার্টি থেকে আরেক পার্টিতে গেলে), store, তারিখ, লক্ষ্য তারিখ, পাঠানোর মাধ্যম, বাহক।
  - state (DRAFT / DELIVERED / CANCELLED), finalized, closed (রিকনসাইল হলে), is_revised।
- **`ss_sc_receipts`** (SCR-YYYYMMDD-####) + লাইন (delivery line, qty, rejected_qty, rate):
  - receive_type (FINISHED_GOODS / LEFT_OVER / REJECTION), store, party।
  - state (RECEIVED / CANCELLED), finalized।
- **`ss_sc_returns`** (SCRT-…) + লাইন (receipt line, qty): reason (Rework / Rejection Return / Wrong Delivery / Other), state (DRAFT / CONFIRMED / CANCELLED)।
- **`ss_sc_reconciliations`** (SCRC-…) + লাইন (delivery line, delivered, received, rejected, returned, short_qty, balance): state (PENDING / MATCHED / SHORT / EXCESS / RECONCILED)।

## ৫. Workflow / Status ও Business Rules

- **হিসাবের নিয়ম (`SubconBook`), চালান-লাইন অনুযায়ী:**
  - এখনো বাইরে = পাঠানো − রিসিভ − রিজেক্ট (ক্যানসেল ছাড়া রিসিভ) + কনফার্মড রিটার্ন (আবার পার্টির কাছে পাঠানো)।
  - কন্ট্র্যাক্টের বাকি = প্রোগ্রাম লাইন − ক্যানসেল ছাড়া সব চালানে ধরা (ড্রাফটসহ)।
- **Delivery Register:**
  - বাঁদিকের SC List থেকে কন্ট্র্যাক্ট বাছলে বাকি লাইন আসে। কন্ট্র্যাক্টের বাকির বেশি পাঠানো যায় না, আর অর্ডার বন্ধ হলে আর কিছু যায় না।
  - লক্ষ্য তারিখ চালানের আগে হতে পারে না।
  - Mark Delivered → Finalize (লক)। Cancel আর Delete শুধু ড্রাফটে।
  - চূড়ান্ত, বন্ধ বা রিসিভ হয়ে যাওয়া চালান আর বদলানো যায় না।
- **Receipt Register:**
  - শুধু ডেলিভারি হওয়া খোলা চালানে। তারিখ চালানের আগে হতে পারে না।
  - প্রতি লাইনে ভালো আর রিজেক্ট পরিমাণ; দুটো মিলিয়ে "এখনো বাইরে"-র বেশি নয়।
  - Finalize করলে লক। Cancel করা যায় না যদি চূড়ান্ত হয়ে যায় বা এর বিপরীতে রিটার্ন থাকে।
- **Return:**
  - রিসিভ থেকে; ফেরতযোগ্য = রিসিভ + রিজেক্ট − অন্য রিটার্ন (ক্যানসেল ছাড়া)।
  - তারিখ রিসিভের আগে হতে পারে না; কারণ বাধ্যতামূলক।
  - ড্রাফট কিছু বদলায় না। Confirm করলে পরিমাণ আবার পার্টির কাছে বাকি হয়; Cancel করলে কমে।
- **Pending:**
  - ডেলিভারি হওয়া চালানের প্রতি লাইনে পাঠানো / রিসিভ / রিজেক্ট / ফেরত / বাকি।
  - লক্ষ্য তারিখ পেরোলে আর কিছু বাকি থাকলে দেরি (দিনসহ, দেরিরগুলো আগে)। বন্ধ চালান = সম্পন্ন।
  - ফিল্টার: "Overdue only" আর "Show completed"।
- **Reconciliation:**
  - বাঁদিকে খোলা চালান, তাতে খোলা রিকন থাকলে সেটাও দেখায় (বাছলে সেটাই খোলে)।
  - সংখ্যাগুলো চালান থেকে পড়া হয়; শুধু শর্ট লেখা যায়, আর তা "এখনো বাইরে"-র বেশি নয়।
  - সেভে অবস্থা হিসাব হয়: কোনো লাইনে বাকি থাকলে PENDING; শর্ট থাকলে SHORT; বেশি ফেরত এলে EXCESS; না হলে MATCHED।
  - এক চালানে একবারে একটাই খোলা রিকন।
  - Reconcile (শুধু MATCHED / SHORT / EXCESS-এ; চালান আবার পড়ে মিলিয়ে নেয়) করলে চালান বন্ধ হয়। তারপর রিসিভ, রিটার্ন বা বদল আর হয় না।
  - Reopen শুধু অ্যাডমিন (`salesshipment.subcon.reopen`)।
- **মাস বন্ধের লক:** সব সেভ আর ধাপে মানা হয়।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `salesshipment.subcon.view` / `manage` / `reopen`।
  - FACTORY_ADMIN তিনটাই পায়। FACTORY_MANAGER, STORE_MANAGER আর STOREKEEPER পায় view + manage। AUDITOR আর VIEWER পায় view।
- **API (`/sales-shipment/…`):**
  - `sc-deliveries` (+ `contracts`, `outstanding`, `source/{sc}`, DELETE, POST {no}/{deliver|finalize|cancel})
  - `sc-receipts` (+ `source/{chalan}`, DELETE, POST {no}/{finalize|cancel})
  - `sc-pending`
  - `sc-returns` (+ `receipts`, `source/{mrr}`, DELETE, POST {no}/{confirm|cancel})
  - `sc-reconciliations` (+ `source/{chalan}`, DELETE, POST {no}/{reconcile|reopen})
- **মেনু:** OPERATION-এর ভেতরে নতুন উপ-ফোল্ডার "Sub-Contract" (Brickland-এর মতো Order & Delivery-র পাশে), ৫টা স্ক্রিন `/sales-shipment/{sc-delivery-list|sc-receive-list|sc-pending|sc-return|sc-reconciliation}`।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট `components/sales-shipment/subcon.jsx`:** ScDeliveryDesk, ScReceiptDesk, ScPendingDesk, ScReturnDesk, ScReconDesk। 21/02-এর shared অংশ দিয়ে বানানো।
- **Brickland-এর নাম আর লেখা হুবহু:** ট্যাবের নাম ("Sales Contract Delivery List / Entry", "SC Receive Entry" ইত্যাদি), কলাম, ফিল্টারের নোট, confirm-এর লেখা, Action Status (79-Create Draft, 80-Create S/C Receive)।
- **প্রিন্ট:** S/C ডেলিভারি চালান (গেট পাসসহ) আর S/C রিটার্ন।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_22_000100_create_ss_subcontract.php`
- `Models/` — SsScDelivery, SsScDeliveryLine, SsScReceipt, SsScReceiptLine, SsScReturn, SsScReturnLine, SsScReconciliation, SsScReconciliationLine
- `Support/SubconBook.php`
- `Http/Controllers/Api/SubconDeliveryApiController.php` (ডেলিভারি, রিসিভ, Pending), `SubconSettleApiController.php` (রিটার্ন, রিকনসিলিয়েশন)
- `Http/Controllers/Api/MonthCloseApiController.php`, `SalesOrderApiController.php`, `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/subcon.jsx` (নতুন)
- `frontend/src/components/sales-shipment/shared.jsx` (বদলানো: checkbox ফিল্টার)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentSubcontractTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentSubcontractTest.php`):** test2 DB-তে পাস। Sales & Shipment-এর তিন ফাইল মিলিয়ে ১৭টা টেস্ট, ৩৮২টা assertion। MenuAccess + RoleMatrix-ও পাস (১৩টা, ৮৪টা)।
- **ডেলিভারি:** কন্ট্র্যাক্টের বাকি ৬০০ আর প্রসেস দেখায়। আটকায় ৪০১ (সীমা ৪০০, বার্তায়) আর চালানের আগের লক্ষ্য তারিখ। SCD-20261107-0001 / SGP-20261107-0001, রেট প্রোগ্রাম থেকে। ড্রাফট ৩০০ ধরে রাখে, তাই পরের চালানে সর্বোচ্চ ১০০; ক্যানসেল করলে আবার পাওয়া যায়। ডেলিভারির আগে Finalize আটকায়। ডেলিভারির পর বদলানো যায় (Is Revised); Finalize করার পর বদল, Cancel আর Delete আটকায়।
- **রিসিভ ও রিটার্ন:** আটকায় ২৯০ + ১১ (সীমা ৩০০), চালানের আগের তারিখ আর শূন্য লাইন। SCR-20261108-0001 (২৪০ + ২০); বাইরে ৪০; বদলানো যায় (২৫০ + ২০ → বাইরে ৩০); রিসিভ থাকা চালান বদলানো যায় না। রিটার্নে ফেরতযোগ্য ২৭০; আটকায় ২৭১ আর কারণ ছাড়া; SCRT-2026-000001; ড্রাফটে বাইরে ৩০-ই থাকে; রিটার্ন থাকলে রিসিভ ক্যানসেল আটকায়; Confirm করলে বাইরে ৫০। Pending-এ ৩০০ / ২৫০ / ২০ / ২০ / ৫০ / PENDING; লক্ষ্য তারিখের ৩ দিন পরে OVERDUE / ৩ দিন। Finalize-এর পর রিসিভ বদলানো আটকায়।
- **রিকনসিলিয়েশন:** ২৮০ + ৫ রিসিভের পর বাইরে ১৫। শর্ট ১৬ আটকায়। SCRC-2026-000001 PENDING (বাকি ১৫); দ্বিতীয় খোলা রিকন আটকায়; PENDING-এ Reconcile আটকায়। শর্ট ১৫ দিলে SHORT / বাকি ০ → Reconcile, চালান বন্ধ। তারপর নতুন রিসিভ আটকায়, Pending-এ COMPLETED / ০, বদল আটকায়, outstanding তালিকা ফাঁকা। FACTORY_MANAGER Reopen পারে না; মালিক পারেন → SHORT, চালান খোলা।
- **মাস বন্ধ ও কে পারে:** ড্রাফট S/C রিটার্ন DRAFT_SC_RETURN ব্যতিক্রম হিসেবে আসে। STOREKEEPER Pending দেখে, রিটার্ন কনফার্ম করে, options পায়। AUDITOR পাঁচটাই দেখে, Finalize পারে না। TECHNICIAN পায় না।
- **টেস্টে ধরা পড়া বাগ:** দেরির দিন এক কম আসছিল, কারণ লক্ষ্য তারিখ UTC-তে পড়া হচ্ছিল আর "আজ" ঢাকার সময়ে। দুটোই এখন ঢাকার তারিখে। 21/02-এর Delivery Completion-এর "দিন"-এও একই সমস্যা ছিল (তারিখ পেরোলে ঋণাত্মক মান এক কম), সেটাও ঠিক করা হয়েছে।

**UI চেক (`app_check/plans/sc01`; error 0, dev ডাটা রাখা আছে):**
- SCD-20261107-0001: SC-20261107-0001 (Square Textiles, Printing), ২২০ পিস; ডেলিভারি → Finalize।
- SCR-20261107-0001: Finished Goods, ১১০ + ১০০ ভালো, ৫ রিজেক্ট।
- SCRT-2026-000001: Rework, ৫ পিস, কনফার্মড।
- Pending: ১২x১৬ লাইনে ১২০ / ১১০ / ৫ / ৫ / বাকি ১০; পুরো ফেরত আসা লাইন সম্পন্ন বলে লুকানো।
- SCRC-2026-000001: শর্ট ১০ → SHORT → Reconciled, চালান বন্ধ।

## ১০. খোলা প্রশ্ন

১. **S/C কন্ট্র্যাক্ট = S/C Programme:** Brickland-এর "Sales Contract" আমরা 21/02-এর S/C Programme ধরেছি (SC No একই)। আলাদা কোনো কন্ট্র্যাক্ট কাগজ লাগলে জানাবেন।

২. **রিসিভের বিল:** Brickland-এর Receive তালিকায় "Bill Status" কলাম আছে, কিন্তু সাব-কন্ট্রাক্টরের বিলের কোনো স্ক্রিন এই মডিউলে নেই। এখন "Not Billed" দেখায়। সাব-কন্ট্রাক্টরের বিল কোথায় হবে (Commercial / Accounts) জানাবেন।

৩. **ডেলিভারি চালানের ক্যানসেল:** Brickland-এ Cancel দেখায় DRAFT / SUBMITTED অবস্থায়, কিন্তু এমন কোনো ধাপ নেই যা SUBMITTED-এ নেয়। আমরা শুধু ড্রাফট ক্যানসেল রেখেছি; ডেলিভারি হয়ে যাওয়া চালান ঠিক করতে হয় রিসিভ, রিটার্ন বা রিকনসিলিয়েশন দিয়ে। ডেলিভারি হওয়া চালানও ক্যানসেল করা দরকার কিনা জানাবেন।

৪. **নম্বরের ধরন:** S/C চালান SCD-…, গেট পাস SGP-…, রিসিভ (MRR) SCR-…, দিনভিত্তিক। Brickland ডেমোতে দেখা যায়নি। রিটার্ন আর রিকন 21/01-এর settings থেকে আসে।

## ১১. As-built নোট

- **Brickland প্রোব:** নতুন RSC টুল দিয়ে (`erp_crawler/netjson.mjs` → `rsc_decode.py` → `rsc_view.py` → `rsc_brief.py`) ৫টা স্ক্রিনের পুরো স্পেক পাঁচ মিনিটে পাওয়া গেছে। এতে ছিল কলাম, ফিল্টার, অবস্থা অনুযায়ী সারির কাজ আর confirm-এর লেখা, প্যানেলের ঘর, গ্রিড, নোট, Action Status আর পেজের সাবটাইটেল।
- **Pending স্ক্রিন:** Brickland-এ এর কোনো সারির কাজ নেই, শুধু রিপোর্ট; আমাদেরও তাই।
- **রিকনসিলিয়েশনের গ্রিড:** চালানের লাইনগুলো নিজে থেকে আসে; লাইন যোগ বা সরানো যায় না, শুধু শর্ট লেখা যায়।
