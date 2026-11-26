# Operations

> **স্ট্যাটাস:** ✅ সম্পন্ন (৮টার ৮টা স্ক্রিন)
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `22/02`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Export L/C & Contract Entry | `/m/commercial/export-lc-sales-contract-entry` | 1 | 10 | 0 |
| 2 | Export L/C Proforma Invoice | `/m/commercial/export-lc-pi` | 1 | 5 | 0 |
| 3 | Utilisation Declaration | `/m/commercial/lc-ud` | 1 | 7 | 0 |
| 4 | Export Commercial Invoice | `/m/commercial/commercial-invoice` | 1 | 11 | 0 |
| 5 | Export Bill Lodgement | `/m/commercial/bill-of-export` | 1 | 4 | 0 |
| 6 | Bill of Exchange Draft | `/m/commercial/bill-of-exchange` | 1 | 12 | 0 |
| 7 | Bank Bill Realisation | `/m/commercial/bank-bill-collection` | 1 | 11 | 0 |
| 8 | Utilisation Declaration Transfer | `/m/commercial/ud-transfer` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Export L/C & Contract Entry

`/m/commercial/export-lc-sales-contract-entry`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| LC/Document No | text |  |  |
| Issue From Date | date |  |  |
| Issue To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Buyer | select |  | LPP |
| With Finalized? | select |  | YES, NO |
| Marge/Replace | select |  | N/A, Marged, Replaced |
| Payment Term | select |  | LC at Sight, LC at 30 days, LC at 60 days, LC at 120 days, TT AFTER SHIPMENT, TT in Advance |
| Receiving Bank | select |  |  |
| UD No | text |  |  |
- গ্রিড কলাম: Action, Final? ▼, Status ▼, LC No ▼, Issue Date ▼, L/C Value ▼, LC/SC? ▼, Marge/Replace? ▼, Company Name ▼, Buyer Name ▼, Export Country ▼, LC Payment Term ▼, Amdt. ▼, UD No ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Export L/C Proforma Invoice

`/m/commercial/export-lc-pi`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PI, No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Buyer | select |  | LPP |
- গ্রিড কলাম: Action, Final?, Status, PI No, Company Name, Buyer Name, Export Country, Total PI Amt, Advance Amt, Discount, Net PI Amt, PI Date
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Utilisation Declaration

`/m/commercial/lc-ud`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| UD No. | text |  |  |
| Export LC | text |  |  |
| B2B LC | text |  |  |
| Authority | select |  | BKMEA, BGMEA, EPB, CUSTOMS, OTHER |
| From Date | date |  |  |
| To Date | date |  |  |
| With Finalized? | select |  | YES, NO |
- গ্রিড কলাম: Action, Final? ▼, Exp. L/C No ▼, UD No ▼, UD Date ▼, Authority ▼, Amendment No ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Export Commercial Invoice

`/m/commercial/commercial-invoice`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Invoice No | text |  |  |
| L/C No | text |  |  |
| Inv. From Date | date |  |  |
| Inv. To Date | date |  |  |
| On Board From Date | date |  |  |
| On Board To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Order No | text |  |  |
| Buyer | select |  | LPP |
| With On Board Date? | select |  | With On Board Date, Without On Board Date |
| With Finalized? | select |  | YES, NO |
- গ্রিড কলাম: Action, Final? ▼, Bill Collected? ▼, Inv. No ▼, B/L No ▼, On Board. Date ▼, Buyer ▼, L/C No ▼, Order No ▼, EXP No. ▼, Bill of Exchange No ▼, Bank Fin... ▼, Bank ▼, Ctn ▼, Pcs ▼, Inv. Amt ▼, Freight ▼, Ex-Fact. Date ▼, CFS Deli Date ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Export Bill Lodgement

`/m/commercial/bill-of-export`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill No | text |  | Bill No... |
| Exp Invoice/UD No | text |  | Invoice / UD No... |
| From Date | date |  |  |
| To Date | date |  |  |
- গ্রিড কলাম: Action, Doc ▼, Final? ▼, Bill of Exp No ▼, Bill of Exp Date ▼, Invoices [Ud No] ▼, Remarks ▼, Charge ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Bill of Exchange Draft

`/m/commercial/bill-of-exchange`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| L/C No | text |  |  |
| Bill Of Exchange No | text |  |  |
| Bank Bill Ref. No | text |  |  |
| Exp Invoice No | text |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Bank | select |  | ABN AMRO BANK, AGRANI BANK LIMITED, AL ARAFAH ISLAMI BANK LIMITED, BASIC BANK LIMITED, BRAC Bank Ltd, Bank Asia Ltd, DUT |
| Buyer/Importer | select |  | LPP |
| Advance Agnst Exp. | select |  | YES, NO |
| With Finalized? | select |  | YES, NO |
| Bank Bill Type | select |  | FDBP, FDBC, DA, DP, IP (Advance) |
| BoE. From Date | date |  |  |
| BoE. To Date | date |  |  |
- গ্রিড কলাম: Actions, Final? ▼, Bill of Exchange No ▼, Bank Bill Type ▼, Bill Date ▼, Bill Submit Date At Bank ▼, Payment Recv Date at Bank ▼, Bill Ref. No ▼, L/C No ▼, L/C Value ▼, Bill Amount ▼, Negotiated Value ▼, Remaining Balance ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Bank Bill Realisation

`/m/commercial/bank-bill-collection`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Realized No | text |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Bank Bill No | text |  |  |
| BoE No | text |  |  |
| Bank Bill Type | select |  | FDBP, FDBC, DA, DP, IP |
| Invoice No | text |  |  |
| Bank | select |  | ABN AMRO BANK, AGRANI BANK LIMITED, AL ARAFAH ISLAMI BANK LIMITED, BASIC BANK LIMITED, BRAC Bank Ltd, Bank Asia Ltd, DUT |
| With Finalized? | select |  | YES, NO |
| Collection Type? | select |  | EARLY, FINAL |
| Realized From Date | date |  |  |
| Realized To Date | date |  |  |
- গ্রিড কলাম: Action, Compa... ▼, Realized No ▼, Type ▼, Final? ▼, Ref. Date ▼, Bill of Exchange ▼, Bank Bill No ▼, Collect Amt ▼, Bank Name ▼, Bank Bill Type ▼, FBC Amt ▼, Penalty Amt ▼, Invoices ▼, Invoice Amt ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Utilisation Declaration Transfer

`/m/commercial/ud-transfer`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From UD No. | text |  |  |
| To UD No. | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- গ্রিড কলাম: Action, From UD No ▼, To UD No ▼, Material ▼, Transfer QTY ▼, Status ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **22/01-এর মাস্টার:**
  - Reference list: Inco Term, Payment Term, Delivery Place, Transit Port, Shipment Mode, Forwarder।
  - ব্যাংক ও শাখা (`tx_bank_branches`), L/C Applicant, Exchange Rate (BoE আর ইনভয়েসের ডিফল্ট রেট)।
- **Merchandise:** `buyers`, `merch_orders` আর `merch_order_sizes` (Link With PO আর রং-সাইজ ব্রেকডাউন), `colours`, `sizes`।
- **Finance 23/01:** `acc_accounts` (Detail Distribution-এর Account Head)।
- **Inventory:** `UomCodes` (PI-এর MoU, মাস্টার থেকে)।
- **21/02-এর shared UI:** `Filters`, `Register`, `EditGrid` আবার ব্যবহার করা হয়েছে।
- **ফাইল আপলোড:** `StoreFileAttachment`, নতুন টাইপ `tx_export_bill`, FileApiController-এর দুই map-এ যোগ করা; route handler `/api/trade-export/bill-attachment`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_01_000100_create_tx_export_operations.php`:

- **`tx_export_pis`** + `tx_export_pi_lines`: PI নং (হাতে), বায়ার, তারিখ, PI Amount, মুদ্রা, শর্তাবলি, লাইন (group, type, description, SKU, রং, সাইজ, পরিমাণ, MoU, দর)। হিসাব: total, advance %/amount, discount (Percent / Amount), net। status DRAFT / SUBMITTED / COMPLETE / CANCELLED।
- **`tx_export_lcs`** + `tx_export_lc_pis`, `tx_export_lc_orders`: kind (LC / SC), L/C নং, বায়ার, ইস্যু, মেয়াদ, তিন ব্যাংক শাখা, inco term, payment term + দিন, % credit tolerance (ডিফল্ট ৩), % BB limit, বন্দর, শেষ শিপমেন্ট, notify party, LC Value, PO Value, Ignore Difference, LC Charge, merge_state, amendment_no, status।
- **`tx_commercial_invoices`** + lines: L/C, prefix + auto no (`BCL-EX-0001`), EXP নং ও অংশ (১–৪), তারিখগুলো, B/L, বন্দর, invoiced / penalty / commission / discount / net, freight, negotiated, container। লাইনে অর্ডার, HS, ইনভয়েস পরিমাণ, দর, কার্টন, ওজন, CBM আর রং-সাইজ ব্রেকডাউন (JSON)।
- **`tx_export_bills`** + `tx_export_bill_invoices`: Bill No, তারিখ, charge, পাস বই পৃষ্ঠা, ফাইল। একটা ইনভয়েস একটাই বিলে।
- **`tx_bills_of_exchange`** + `tx_boe_invoices`: is_advance, L/C (অগ্রিমে ঐচ্ছিক), বায়ার, Bill Amount, মুদ্রা ও স্থানীয় রেট, চার ব্যাংক শাখা, bank bill type (FDBP / FDBC / DA / DP / IP), রেফারেন্স, due date, PI / PO লিংক (অগ্রিম)।
- **`tx_bill_realisations`:** type (EARLY / FINAL), realized no, BoE, তারিখ, ODS ও TTC রেট, invoice amount (USD ও BDT), collection, FBC, penalty।
- **`tx_bill_distributions`:** BoE আর realisation-এর হিসাব বণ্টন (account, Dr/Cr, মুদ্রা, পরিমাণ, নোট)।
- **`tx_uds`** + `tx_ud_lcs`, **`tx_ud_transfers`** (মাইগ্রেশন `2027_03_02_000100_create_tx_import_and_ud.php`): UD নং, তারিখ, কর্তৃপক্ষ, B2B L/C; ট্রান্সফারে material, From ও To UD, receive, export, loss %, loss qty, balance, transfer, status।

## ৫. Workflow / Status ও Business Rules

- **Export L/C Proforma Invoice:**
  - Take Action: Create Draft → Submit → Complete LC, অথবা Cancelled। Complete বা Cancelled হলে লক।
  - Draft ছাড়া বাকিতে লাইনের যোগফল PI Amount-এর সমান হতে হবে।
  - Discount মোটের বেশি নয়। Net = Total − Discount; Advance = Total × Advance %।
  - L/C-তে যুক্ত PI বাতিল করা যায় না। শুধু অব্যবহৃত Draft মোছা যায়।
- **Export L/C & Contract Entry:**
  - Export LC বা Sales Contact। অন্তত একটা PI লাগে (Brickland-এর `*`)। PI সেই বায়ারের আর Submit হওয়া হতে হবে; এক PI একটাই L/C-তে।
  - মেয়াদ ইস্যুর আগে নয়; শেষ শিপমেন্ট মেয়াদের পরে নয়। অর্ডার সেই বায়ারের।
  - PO Value = অর্ডারের পরিমাণ × দরের যোগফল। Draft-এ পার্থক্য চলে; Submit বা Complete-এ LC Value আর PO Value সমান, অথবা Ignore Difference।
  - Complete হলে লক। ইনভয়েস থাকলে বায়ার বদলানো আর ইনভয়েস করা মূল্যের নিচে নামানো যায় না।
  - Same Receiver Bank টিক দিলে Receiving = Advising।
- **Export Commercial Invoice:**
  - Draft L/C-তে হয় না। L/C বাছলে বায়ার, ব্যাংক, মুদ্রা, পেমেন্ট, বন্দর, মাধ্যম, Applicant আর রেট বসে।
  - নম্বর = Prefix + প্রতি Prefix-এর আলাদা ক্রম (৪ অঙ্ক), সেভে তৈরি।
  - অর্ডার হাতে বা "Populate Orders From Export LC?" দিয়ে। অর্ডার L/C-এর বায়ারের।
  - পরিমাণ: অর্ডার × (১ + tolerance) − অন্য ইনভয়েস পর্যন্ত। মোট ইনভয়েস: LC Value × (১ + tolerance) পর্যন্ত।
  - রং-সাইজ ব্রেকডাউন দিলে যোগফল Inv. Qty-র সমান।
  - Net = Invoiced − Penalty − Agency Commission − Sales Discount।
  - নিচে L/C-র সব ইনভয়েস, Total Bill আর Total LC Due। Finalize করলে লক।
- **Export Bill Lodgement:** শুধু ফাইনাল ইনভয়েস, প্রতিটা একটাই বিলে। PDF বা ছবি সংযুক্ত করা যায়। Finalize করলে লক।
- **Bill of Exchange Draft:**
  - সাধারণ বিল: L/C-র ফাইনাল ইনভয়েস (অন্য BoE-তে নেই) টিক দিয়ে Add to Grid; Bill Amount নিট যোগফল, ⟳ দিয়ে আবার হিসাব।
  - Bill Amount, L/C-র বাকির (LC Value − অন্য BoE) বেশি নয়। Due date বিলের তারিখের আগে নয়।
  - Advance Collection: ধরন IP, L/C ঐচ্ছিক, PI বা PO লিংক বাধ্যতামূলক, Detail Distribution সারি থাকলে DR = CR।
  - তালিকা: Negotiated Value (Final থাকলে Collection, না হলে Early-র Invoice Amount), Remaining = Bill Amount − Negotiated; ব্যাংকে পেমেন্টের তারিখ = শেষ realisation।
- **Bank Bill Realisation:**
  - ফাইনাল BoE-তে। বিল বাছলে ব্যাংক, ধরন, মুদ্রা, রেট, পরিমাণ আর ইনভয়েস তালিকা বসে।
  - Early একটাই, আর Final-এর আগে; Final একবার।
  - Invoice Amount(BDT) = Invoice Amount × ODS। Penalty ইনভয়েসগুলোর penalty-র যোগফল।
  - Detail Distribution-এ DR = CR; Match শেষ সারি দিয়ে সমতা করে।
  - Final realisation ফাইনাল করলে ইনভয়েসে Bill Collected = YES।
- **Utilisation Declaration (22/03-এর পরে বানানো):**
  - B2B LC বেছে গ্রিডে যোগ; Export L/C তথ্য B2B থেকেই আসে। এক B2B একটাই UD-তে। Submit-এ সেভ, Finalize করলে লক।
  - Export L/C-র তালিকার UD No, Lodgement-এর "Invoices [Ud No]" আর বিলের UD কলাম এখন এখান থেকে পড়ে।
- **Utilisation Declaration Transfer:**
  - Commodity, From UD, To UD বাছলে Receive Qty আসে: From UD-র B2B-র মধ্যে সেই commodity-র Bill of Entry-তে গৃহীত পরিমাণ।
  - Export Qty আর Process-loss % হাতে; Loss Qty = Export × %; Balance = Receive − আগের ট্রান্সফার − Export − Loss।
  - Transfer Qty Balance-এর বেশি নয়; দুটো UD আলাদা। Approve হলে লক।
- **B2B Summary (Export L/C ফর্ম):** সেই L/C-র সব B2B, BB limit, খোলা আর বাকি।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `tradeexport.export.view` / `manage`। FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়; AUDITOR পায় view (VIEWER সব `.view` পায়); STOREKEEPER কিছু পায় না।
- **API (`/tradeexport/ops/…`):**
  - `options`
  - `pis`, `lcs` (CRUD), `pis/{id}/lines`
  - `invoices` (CRUD, `source/{lc}`, `order/{order}`, `{id}/final`)
  - `lodgements` (CRUD, `invoices`, `{id}/attachment`, `{id}/final`)
  - `boes` (CRUD, `source/{lc}`, `{id}/final`)
  - `realisations` (CRUD, `bills`, `{id}/final`)
- **মেনু:** Brickland-এর মতো EXPORT ফোল্ডার, তার ভেতরে Operations (৬টা স্ক্রিন) আর Master Data।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/tradeexport/operations.jsx`। একটা generic `Desk`: Searching Data, Add New, তালিকা; Add New বা Edit-এ পেজ রিলোড ছাড়া এন্ট্রি, ঠিকানায় `?no=<কাগজের নম্বর>`।
- **স্ক্রিন:** `LcDesk`, `PiDesk`, `InvoiceDesk`, `LodgementDesk`, `BoeDesk`, `RealisationDesk`; Brickland-এর titled box আর গ্রিড।
- **আপলোড:** `app/api/trade-export/bill-attachment/route.js`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৮টার ৮টা স্ক্রিন; UD দুটোর কোড 22/03-এর সাথে: `ImportBillApiController.php`, `components/tradeexport/imports.jsx`, `TxUd`, `TxUdTransfer`):

**Backend (`app/Modules/TradeExport`)**
- `Database/Migrations/2027_03_01_000100_create_tx_export_operations.php`
- `Models/` — TxExportPi, TxExportLc, TxCommercialInvoice, TxExportBill, TxBillOfExchange, TxBillRealisation (নতুন)
- `Support/ExportBook.php` (নতুন)
- `Http/Controllers/Api/ExportCreditApiController.php` (PI, L/C), `ExportBillApiController.php` (ইনভয়েস, লজমেন্ট, BoE, রিয়েলাইজেশন) (নতুন)
- `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো: `tx_export_bill`)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/tradeop.php`, `lang/bn/tradeop.php` (নতুন)

**Frontend**
- `frontend/src/components/tradeexport/operations.jsx` (নতুন)
- `frontend/src/app/api/trade-export/bill-attachment/route.js` (নতুন)
- `frontend/src/app/(app)/trade-export/[screen]/screens.js`, `actions.js`, `page.js` (বদলানো)

**Test**
- `tests/Feature/TradeExport/TradeExportOperationsTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`TradeExportOperationsTest.php`):** test2 DB-তে পাস, ৫টা টেস্ট। Trade & Export মিলিয়ে ১০টা টেস্ট, ১৯৫টা assertion। MenuAccess + RoleMatrix-ও পাস (১৩টা টেস্ট)।
- **PI:** PI Amount না মিললে Submit আটকায়; মোটের বেশি discount আটকায়; ২০০০ / অগ্রিম ২০০ / ৫% ছাড় ১০০ / নিট ১৯০০; একই নম্বর দুবার আটকায়; Complete হলে লক, মোছা আটকায়।
- **L/C:** PI ছাড়া, Draft PI, মেয়াদ ইস্যুর আগে, শেষ শিপমেন্ট মেয়াদের পরে, অন্য বায়ারের অর্ডার আটকায়। Draft-এ ১৫০০ বনাম PO ২০০০ চলে; Submit আটকায়; Ignore Difference দিলে চলে। এক PI দুই L/C-তে আটকায়।
- **পুরো ধারা:**
  - ইনভয়েস: ১০৩১ আটকায় (সর্বোচ্চ ১০৩০, ৩% tolerance); ভুল ব্রেকডাউন আটকায়; BCL-0001 (১২০০, নিট ১১০০), BCL-0002। Done Qty ৯০০, Total Bill ১৮০০।
  - ইনভয়েস থাকা L/C-তে বায়ার বদল আর ১৮০০-র নিচে মূল্য আটকায়; Complete করা যায়, তারপর লক।
  - লজমেন্ট: ফাইনাল ছাড়া আটকায়; এক ইনভয়েস দুই বিলে আটকায়; ফাইল আপলোড, মালিক ডাউনলোড পান, TECHNICIAN পান না।
  - BoE: ইনভয়েস ছাড়া, L/C-র বাকির বেশি, বিলের তারিখের আগের due date আটকায়।
  - Realisation: ফাইনাল BoE ছাড়া আটকায়; অসমান DR/CR আটকায়; Early: BDT ১,৩০,৯০০ (১১০০ × ১১৯), penalty ৫০; দ্বিতীয় Early আটকায়। Negotiated ১১০০, বাকি ০। Final (collection ১০৫০) ফাইনাল করলে BCL-0001 Bill Collected, BCL-0002 নয়; দ্বিতীয় Final আটকায়; Negotiated ১০৫০, বাকি ৫০।
- **Advance:** PI / PO লিংক ছাড়া আটকায়; IP ছাড়া ধরন আটকায়; অসমান বণ্টন আটকায়; L/C ছাড়া সেভ হয়।
- **কে পারেন:** AUDITOR দেখেন, বানাতে পারেন না; FACTORY_MANAGER পারেন; STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/tx01`, `tx02`, `tx03`; error 0, dev ডাটা রাখা আছে):** LPP Spolka Akcyjna-র পুরো ধারা।
- **PI:** BCL-PI-2026-0901: ১৯০০ পিস × ১.৮৫ = ৩৫১৫, অগ্রিম ১০% = ৩৫১.৫০, Submit।
- **L/C:** LC-0098812: PI আর অর্ডার LPP-2026-000001 যুক্ত, LC Value ৩৫১৫, পার্থক্য ০; পরে Complete LC।
- **ইনভয়েস:** BCL-EX-0001: L/C থেকে সব ঘর বসেছে, Populate দিয়ে অর্ডার, ব্রেকডাউন M ৬০০ + L ৪০০ = ১০০০, ১৮৫০, penalty ৫০, নিট ১৮০০; ফাইনাল।
- **লজমেন্ট:** EB-2026-1101, PDF সংযুক্ত।
- **BoE:** BE-2026-2201 (FDBP, BBR-77123, ১৮০০); ফাইনাল। Advance: ADV-2026-3301 (৩৫১.৫০, PI-এর সাথে, বণ্টন DR = CR)।
- **Realisation:** RZ-E-1101 (Early), RZ-F-1102 (Final; Match DR = CR ১০০০); Final ফাইনাল করার পর ইনভয়েসে Bill Collected = YES, BoE-তে Negotiated ১৮০০, বাকি ০।
- **ঠিকানা:** `?no=LC-0098812` দিয়ে খুললে সরাসরি সেই L/C।
- **UD (`plans/tx04`, `tx07`):** UD-BKMEA-2026-501 (BBLC-SQ-0001) আর UD-BKMEA-2026-502 (BBLC-KY-0002)। Export L/C-র তালিকায় UD No আর Lodgement-এ `[UD-BKMEA-2026-501]` দেখায়। ট্রান্সফার: Yarn, ৫০১ থেকে ৫০২-তে ৫০, Approved।
- **UD-র টেস্ট (`TradeExportImportTest`):** B2B ছাড়া আটকায়; এক B2B দুই UD-তে আটকায়; Receive ৫০০ (Accessories), Yarn-এ ০; একই UD-তে আটকায়; Export ৩০০ + ১০% loss-এ Balance ১৭০, ১৭১ আটকায়; ১০০ Approved-এর পর ৭১ আটকায় (বাকি ৭০); Approved বদলানো আটকায়; ট্রান্সফার থাকা UD মোছা আটকায়।

## ১০. খোলা প্রশ্ন

১. **সারির কাজ:** Brickland-এর demo-তে এই স্ক্রিনগুলোয় কোনো রেকর্ড নেই, তাই Action কলামে কী কী আছে দেখা যায়নি। আমরা Edit, Finalize আর Delete রেখেছি। ফাইনাল ফেরানোর (Undo) কোনো ব্যবস্থা নেই। লাগবে কিনা জানাবেন।

২. **Export Qty-র উৎস (UD Transfer):** Brickland-এ Export Qty স্ক্রিনে দেখায়, কিন্তু কীভাবে হিসাব হয় দেখা যায়নি; আমরা হাতে লেখার ঘর রেখেছি, আর Process-loss %-ও। কীভাবে আসবে জানাবেন।

৩. **L/C Amendment আর Marge / Replace:** তালিকায় "Amdt." আর "Marge/Replace?" কলাম আর ফিল্টার আছে, কিন্তু সেগুলো কীভাবে বসে তা দেখা যায়নি। এখন ০ আর N/A দেখায়। L/C সংশোধন আর মার্জের নিয়ম জানালে যোগ করব।

৪. **L/C-তে PI বাধ্যতামূলক:** Brickland-এর ফর্মে Export PI No.-এ `*` আছে, তাই অন্তত একটা PI লাগে। PI ছাড়া সরাসরি L/C বা Sales Contract খোলা কি চলবে?

৫. **Realisation-এর হিসাব পোস্টিং:** Detail Distribution শুধু রেকর্ড হয়; GL-এ কোনো ভাউচার পোস্ট হয় না। Finance & Ledger-এর ভাউচার বানানোর পর সংযোগ হবে। ধারাটা (কোন খাত DR, কোন খাত CR) জানালে আগে থেকে সারিগুলো ভরে দেওয়া যাবে।

৬. **Net Amount-এর সূত্র:** Net = Invoiced − Penalty − Agency Commission − Sales Discount ধরা হয়েছে; Freight আলাদা থাকে। ঠিক আছে কিনা জানাবেন।

৭. **Item Group ও Item Type:** Brickland-এর PI-তে Group-এর নিজস্ব তালিকা। আমরা সেই নামগুলো রেখেছি, আর Type আসে Material Procurement ও General Store-এর ক্যাটাগরি থেকে। Export PI-এর জন্য আলাদা মাস্টার লাগবে কিনা জানাবেন।

৮. **Sales & Shipment-এর সংযোগ:** 21/05-এর Shipment Invoice-এ "Commercial Invoice" এখনো লিখে রাখার ঘর। Export Commercial Invoice এখন আছে; ঘরটাকে বাছাইয়ের তালিকা করব, আর শিপমেন্ট কি ইনভয়েসের সাথে সরাসরি যুক্ত হবে?

## ১১. As-built নোট

- **Brickland প্রোব:** এই স্ক্রিনগুলোয় RSC `view` spec নেই (সার্ভার-রেন্ডার করা ফর্ম)। তাই `fields.mjs` দিয়ে `/new` ফর্মের ঘর আর ড্রপডাউন, `panel.mjs` দিয়ে গ্রিড, লিংক আর পূর্ণ দৈর্ঘ্যের screenshot নেওয়া হয়েছে।
- **UD আর UD Transfer:** এদের ফর্ম চলে B2B LC থেকে, তাই 22/03 Import L/C-র সাথে বানানো হয়েছে। Export L/C-র UD No কলাম আর B2B Summary বাটনও তখন এসেছে।
- **Add New (L/C ফর্মে Shipping Mode-এর নিচে):** কী করে তা বোঝা যায়নি (demo-তে ডায়ালগ খোলেনি), তাই রাখা হয়নি।
- **তালিকা ও এন্ট্রি:** Brickland-এ আলাদা পেজ; আমাদের নিয়ম অনুযায়ী একই পেজে রিলোড ছাড়া।
- **UI চেকে ধরা পড়া:**
  - সেভের পর তারিখের ঘর ফাঁকা হচ্ছিল (backend-এ `+` দিয়ে merge করায় তারিখ ISO আকারে যাচ্ছিল), আবার সেভ করলে তারিখ হারাত। এখন `array_merge`।
  - তালিকা রিলোডের পর Final, Status আর LC/SC-র লেবেল "—" হচ্ছিল। এখন প্রতিবার পড়ার সময় লেবেল বসে।
  - ইনভয়েস সেভের পর নিচের Invoice List আর Total Bill আপডেট হচ্ছিল না।
  - চওড়া গ্রিড বক্সের বাইরে যাচ্ছিল (fieldset-এর ডিফল্ট min-width)।
  - ইনভয়েস উঠলেই L/C পুরো লক হলে Complete LC আর করা যেত না। এখন Complete পর্যন্ত খোলা, শুধু বায়ার আর ইনভয়েস করা মূল্য ধরে রাখা হয়।
