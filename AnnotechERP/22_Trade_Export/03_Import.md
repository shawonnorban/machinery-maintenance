# Import

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `22/03`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Import L/C Proforma Invoice | `/m/commercial/import-lc-pi` | 1 | 11 | 0 |
| 2 | Import L/C Opening | `/m/commercial/import-lc` | 1 | 10 | 0 |
| 3 | Customs Bill of Entry | `/m/commercial/bill-of-entry` | 1 | 5 | 0 |
| 4 | Import Bill Acceptance | `/m/commercial/acceptance-of-import-bill` | 1 | 16 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Import L/C Proforma Invoice

`/m/commercial/import-lc-pi`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PI No | text |  |  |
| PI Tracking No | text |  |  |
| PO No | text |  |  |
| Approved From Date | date |  |  |
| Approved To Date | date |  |  |
| Creation From Date | date |  |  |
| Creation To Date | date |  |  |
| PI From Date | date |  |  |
| PI To Date | date |  |  |
| Supplier | select |  | ACCESSORIES VIEW, Arafah Knit Composite Ltd, BCL, BCL COMPOSITE, CREATIVE TOUCH, KOYO TRIMS SOLUTION JP, NASIR INTERNATI |
| Export LC | select |  |  |
- গ্রিড কলাম: Action, Status ▼, PI Info ▼, PO No ▼, PI Create Date ▼, PI Approved Date ▼, Supplier Name ▼, PI Date ▼, PI Amount ▼, L/C Info ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Import L/C Opening

`/m/commercial/import-lc`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Import L/C No | text |  |  |
| Exp. LC No | text |  |  |
| Expire Date | date |  |  |
| PI No | text |  |  |
| Applicant | select |  | Brickland Composite Ltd. |
| Supplier | select |  | ACCESSORIES VIEW, Arafah Knit Composite Ltd, BCL, BCL COMPOSITE, CREATIVE TOUCH, KOYO TRIMS SOLUTION JP, NASIR INTERNATI |
| LC Type | select |  | Irrevocable, Irrevocable Transferable, Back to Back, Sales Contract |
| Issue From Date | date |  |  |
| Issue To Date | date |  |  |
| Bank | select |  |  |
- গ্রিড কলাম: Action, Final? ▼, Status ▼, Imp. L/C No ▼, Exp. L/C No ▼, L/C Value ▼, Bank Ref No ▼, IFDBC AMT ▼, Issue Date ▼, Expire Date ▼, PI No ▼, Applicant Name ▼, Comodity Type Name ▼, Supplier ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Search

#### Customs Bill of Entry

`/m/commercial/bill-of-entry`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill of Entry No | text |  | Bill of Entry No... |
| From Date | date |  |  |
| To Date | date |  |  |
| Import Lc No. | select |  |  |
| Entry By | select |  | accountant, accview@bcl.com, admin, arafah@bcl.com, bcl@gmail.com, commercial, creative@bcl.com, dyeing, hr, ie, info@si |
- গ্রিড কলাম: Action, Final? ▼, Bill of Entry No ▼, Bill of Entry Date ▼, Amount ▼, Custom Officer ▼, Import LC ▼, Entry By ▼, Remarks ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Import Bill Acceptance

`/m/commercial/acceptance-of-import-bill`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BOE No | text |  |  |
| IFDBC No | text |  |  |
| Import LC No | text |  |  |
| Acceptance From Date | date |  |  |
| Acceptance To Date | date |  |  |
| IFDBC Maturty From Date | date |  |  |
| IFDBC Maturty To Date | date |  |  |
| IFDBC Paid From Date | date |  |  |
| IFDBC Paid To Date | date |  |  |
| IS IFDBC Paid? | select |  | Yes, No |
| IS IFDC ? | select |  | Yes, No |
| IS Payment Done? | select |  | Yes, No |
| Pad Finalized? | select |  | Yes, No |
| Accounting Done? | select |  | Yes, No |
| Payment Type | select |  | N/A, PMT, TT, ADJ |
| Bank | select |  | ABN AMRO BANK, AGRANI BANK LIMITED, AL ARAFAH ISLAMI BANK LIMITED, BASIC BANK LIMITED, BRAC Bank Ltd, Bank Asia Ltd, DUT |
- গ্রিড কলাম: Actions, Final? ▼, Bill of Exchange No ▼, IMP LC NO ▼, Accounting Done? ▼, Payment Type ▼, Bill of Exc Amt ▼, IFDBC No ▼, Maturity Date ▼, Ext. Maturity Date ▼, IFDBC Amt ▼, IFDBC Paid Amt ▼, IFDBC Open d... ▼, IFDBC Paid Date ▼, B2B Qty ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **22/02-এর `Desk`, `Box`, `ReadGrid`:** তালিকা আর এন্ট্রি একই পেজে, ঠিকানায় `?no=`।
- **22/01-এর মাস্টার:** Reference list (LC Type, Inco Term, Payment Term, Delivery Place, Shipment Mode), ব্যাংক শাখা, Exchange Rate।
- **Supplier-এর PO, তিন ধরনের:** `merch_acc_pos` (Accessories), `merch_pfab_pos` (Fabric), `knit_yarn_pos` (Yarn)। তিনটার লাইন `ImportBook` এক রকম করে পড়ে।
- **MRR, তিন ধরনের:** `merch_acc_mrrs`, `merch_pfab_mrrs`, `knit_yarn_receipts`। Bill of Entry-র লাইন এগুলো থেকে আসে।
- **22/02-এর Export L/C:** B2B খোলা হয় এর বিপরীতে; `% BB Limit` এখানে খাটে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_02_000100_create_tx_import_and_ud.php` (UD দুটোর টেবিলও এখানে, 22/02 দেখুন):

- **`tx_import_pis`** + `tx_import_pi_lines`: tracking (`IPI-2026-000001`, সেভে), supplier, PI নং (সাপ্লায়ার অনুযায়ী অনন্য), export L/C (ঐচ্ছিক), তারিখ, PI Amount, net, status DRAFT / APPROVED। লাইনে PO-র ধরন ও লাইন (হাতে লেখা লাইনে ফাঁকা), PO নং, item, পরিমাণ, UOM, দর।
- **`tx_import_lcs`** + `tx_import_lc_commodities`, `tx_import_lc_pis`: ref no (অনন্য), LC type, cash L/C, commodity, supplier, export buyer, export L/C, তিন ব্যাংক শাখা, তারিখগুলো, শর্ত, LCAF, BTMA, IRC, BIN, চার অংশের L/C নম্বর, মূল্য, tenor, % credit tolerance (ডিফল্ট ৫), L/C Status, status। এক PI একটাই L/C-তে।
- **`tx_bills_of_entry`** + lines: BE নং, তারিখ, L/C, Entry By, custom code ও name, bill amount, মুদ্রা, রেট, equivalent, ফাইল। লাইনে PI লাইন, MRR, চালান, receive qty ও amount।
- **`tx_import_acceptances`:** Bill of Exchange নং (`IBE-2026-000001`, সেভে), L/C, invoiced amount, acceptance date, IFDBC নং ও মূল্য, pending, open / maturity / ext. maturity, B2B qty, payment type, রেট, final।

## ৫. Workflow / Status ও Business Rules

- **Import L/C Proforma Invoice:**
  - Supplier বাছলে বাঁদিকে তার সব PO (তিন ধরনের)। PO-তে ক্লিক করলে তার বাকি লাইন PI-তে আসে; Add to PI দিয়ে হাতেও লাইন দেওয়া যায়।
  - লাইনের পরিমাণ PO-র বাকির বেশি নয় (অন্য PI বাদ দিয়ে)।
  - Approve-এ Net PI Amount আর PI Amount সমান লাগে। Approve হলে লক।
- **Import L/C Opening:**
  - Cash L/C ছাড়া Export (Master) LC বাধ্যতামূলক। মেয়াদ ইস্যুর আগে নয়; শেষ শিপমেন্ট মেয়াদের পরে নয়।
  - Export L/C-তে `% BB Limit` থাকলে সব B2B মিলিয়ে সেই সীমার ভেতরে।
  - Submit বা Complete-এ অন্তত একটা PI; PI সেই supplier-এর আর Approved; এক PI একটাই L/C-তে।
  - L/C নম্বর = চার অংশ জোড়া, না থাকলে Ref No। Complete হলে লক। Go to UD বাটন UD স্ক্রিনে নিয়ে যায়।
- **Customs Bill of Entry:**
  - B/B LC বাছলে তার PI-র লাইন আর সেগুলোর MRR আসে, প্রতিটা PI লাইন + MRR এক সারি।
  - Select দিয়ে Add to Grid; পরিমাণ বাকির বেশি নয় (অন্য এন্ট্রি বাদ)। Equivalent = Bill Amount × রেট।
  - ফাইল সংযুক্তি। Finalize করলে লক।
- **Import Bill Acceptance:**
  - L/C বাছলে তার ধরন, মূল্য, tolerance, ব্যাংক, PI আর আগের বিল আসে। Tenor দিন থাকলে Is Defered হ্যাঁ।
  - নম্বর সেভে। Invoiced Amount-এর মোট L/C + tolerance-এর ভেতরে; Maturity Open Date-এর আগে নয়।
  - Take Action: Create Draft / Finalize। Finalize হলে লক।
  - নিচে Bill List, Total Bill Amount আর Total LC Due। তালিকার IFDBC Paid Amt / Date এখনো ০ (খোলা প্রশ্ন ২)।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `tradeexport.import.view` / `manage`। FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়; AUDITOR পায় view (VIEWER সব `.view`); STOREKEEPER কিছু পায় না।
- **API (`/tradeexport/ops/…`):**
  - `import-options`
  - `imp-pis` (CRUD, `pos?supplier=`, `po-lines/{kind}/{po}`, `{id}/approve`, `{id}/row`)
  - `imp-lcs` (CRUD), `lcs/{id}/b2b`
  - `entries` (CRUD, `source/{lc}`, `{id}/attachment`, `{id}/final`)
  - `acceptances` (CRUD, `source/{lc}`)
- **মেনু:** Trade & Export-এর নিচে নতুন ফোল্ডার IMPORT, ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/tradeexport/imports.jsx`: `ImportPiDesk`, `ImportLcDesk`, `EntryDesk`, `AcceptanceDesk` (আর UD-র দুটো ডেস্ক), সবগুলো 22/02-এর `Desk`-এর ওপর।
- **আপলোড:** `app/api/trade-export/bill-attachment/route.js` (`?kind=entry`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/TradeExport`)**
- `Database/Migrations/2027_03_02_000100_create_tx_import_and_ud.php`
- `Models/` — TxImportPi, TxImportLc, TxBillOfEntry, TxImportAcceptance (নতুন)
- `Support/ImportBook.php` (নতুন)
- `Http/Controllers/Api/ImportCreditApiController.php`, `ImportBillApiController.php` (নতুন)
- `Routes/api.php`, `Support/ExportBook.php`, `ExportCreditApiController.php`, `ExportBillApiController.php` (বদলানো: UD নম্বর)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো: `tx_bill_of_entry`)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/tradeop.php`, `lang/bn/tradeop.php` (বদলানো)

**Frontend**
- `frontend/src/components/tradeexport/imports.jsx` (নতুন)
- `frontend/src/components/tradeexport/operations.jsx`, `app/api/trade-export/bill-attachment/route.js`, `app/(app)/trade-export/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/TradeExport/TradeExportImportTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`TradeExportImportTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ১০৪টা assertion। MenuAccess + RoleMatrix-ও পাস (১৩টা টেস্ট)।
- **PI:** PO তালিকা supplier অনুযায়ী; লাইনে Zipper, ১০০০, বাকি ১০০০। ১০০১ আটকায়; লাইন ছাড়া আটকায়; IPI-2026-000001, নিট ৩০০। পরে বাকি ৪০০; নিজের PI বাদ দিলে ১০০০। PI Amount না মিললে Approve আটকায়; Approve হলে লক।
- **L/C:** PI ছাড়া Submit, অন্য supplier-এর PI, BB limit-এর বেশি (২০০০-এর ৫০% = ১০০০), মেয়াদ ইস্যুর আগে, Export L/C ছাড়া আটকায়। চার অংশ জুড়ে `270126020091`। এক PI দুই L/C-তে আটকায়। B2B Summary: সীমা ১০০০, খোলা ৩০০, বাকি ৭০০।
- **Bill of Entry:** MRR-1 / CH-77: ৬০০ গৃহীত; ৬০১ আটকায়; লাইন ছাড়া আটকায়; ৫০০ নিলে equivalent ৩৫,৭০০, বাকি ১০০। ফাইল আপলোড, TECHNICIAN ডাউনলোড পান না। Finalize হলে লক।
- **Acceptance:** ৫% tolerance-এ ৩১৫ পর্যন্ত, ৩১৬ আটকায়; Maturity Open-এর আগে আটকায়; IBE-2026-000001; Tolerance ১৫, Total Bill ৩০০, LC Due ০; L/C-র তালিকায় IFDBC ৩০০।
- **কে পারেন:** AUDITOR দেখেন, বানাতে পারেন না; FACTORY_MANAGER পারেন; STOREKEEPER UD দেখতে পারেন না।
- **UD আর Transfer**-এর পরীক্ষা 22/02-এর ডকে।

**UI চেক (`app_check/plans/tx04`, `tx05`, `tx07`; error 0; dev ডাটা রাখা আছে):**
- **PI:** SQT-PI-1101 (Square Textiles, YPO-2026120001 থেকে দুই লাইন, ৩১৭০) Approved; KOYO-PI-2201 (KOYO TRIMS, Accessories PO, ১৩,৮৭৮) Approved।
- **L/C:** BBLC-SQ-0001 (`270126020091`, YARN, LC-0098812-এর বিপরীতে, ৩১৭০) আর BBLC-KY-0002 (ACCESSORIES, ১৩,৮৭৮); Submit।
- **Bill of Entry:** BE-CTG-77001: YMRR-2026100001 থেকে লাইন, PDF সংযুক্ত, Equivalent ৩,৭৮,৮১৫; Final।
- **Acceptance:** IBE-2026-000001: IFDBC-55120, ৩১৭০, ম্যাচিউরিটি ৮ জানুয়ারি ২০২৭; Finalize।

## ১০. খোলা প্রশ্ন

১. **সারির কাজ:** Brickland-এর demo-তে এখানেও কোনো রেকর্ড নেই। আমরা Edit, Approve / Finalize আর Delete রেখেছি।

২. **IFDBC Paid আর Payment:** Import Bill Acceptance-এর তালিকা আর ফিল্টারে IFDBC Paid Amt, Paid Date, Is Payment Done, Accounting Done আছে, কিন্তু ফর্মে সেগুলো লেখার ঘর নেই। Brickland-এ এগুলো কোন স্ক্রিন থেকে আসে (Finance-এর পেমেন্ট, না CM Payment Receive)? এখন ০ / No দেখায়।

৩. **"Show other information" / "Show Other Info":** Import L/C আর Acceptance ফর্মে এই টিক আছে; demo-তে কী খোলে তা দেখা যায়নি, তাই রাখা হয়নি।

৪. **Bill of Entry-র HS Code:** PO বা MRR-এ HS Code নেই, তাই ফাঁকা দেখায়। কোথা থেকে আসবে?

৫. **Insurance Company:** Brickland-এ ড্রপডাউন, কিন্তু কোনো মাস্টার পাইনি; এখন লিখে রাখার ঘর।

৬. **MRR-এর অবস্থা:** Accessories আর Fabric-এ শুধু Finalized MRR, Yarn-এ Draft বা Cancelled ছাড়া সব ধরা হয়েছে। ঠিক আছে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** `panel.mjs` দিয়ে ৪টা ফর্ম আর ৪টা তালিকার ঘর, গ্রিড আর লিংক; বাকি UD দুটোও একসাথে।
- **PO আর MRR:** Brickland-এ একটাই PO তালিকা; আমাদের তিন ধরনের PO আর MRR আলাদা টেবিলে, তাই `ImportBook` তিনটাকে এক আকারে পড়ে।
- **UI চেকে ধরা পড়া:** সেভ চলাকালে Back To List চাপলে, তালিকায় যাওয়ার পর সেভের ফল এসে ফর্ম আবার খুলে যেত। এখন সেভ চলাকালে Back আর Cancel নিষ্ক্রিয়।
