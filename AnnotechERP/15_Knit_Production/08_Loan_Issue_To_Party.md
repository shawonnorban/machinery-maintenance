# Loan Issue to Party

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/08`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Needle Loan Issue Indent | `/m/knitting/needle-req-for-loan-issue` | 2 | 13 | 1 |
| 2 | Needle Loan Return from Party | `/m/knitting/needle-loan-return-by-party` | 2 | 16 | 1 |
| 3 | Oil Loan Issue Indent | `/m/knitting/oil-req-for-loan-issue` | 2 | 14 | 1 |
| 4 | Oil Loan Return from Party | `/m/knitting/oil-loan-return-by-party` | 2 | 16 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Needle Loan Issue Indent

`/m/knitting/needle-req-for-loan-issue`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req No | text |  | REQ-MN-… |
| Store | select |  | Maintenance Store |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Issue, -- Party --, Search, Add New, Reset

**ট্যাব: Requisition for Loan Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Req No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Maintenance Store |
| Reason | text |  | Why the needles are being lent or sent back |
| Remarks | textarea |  |  |
- সেকশন: Requisition for Loan Issue, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Issue, -- Select --, -- Select Department --, -- Select Party --, Add Item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Req No | text |  |  |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Maintenance Store |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A document with no needles on it moves nothing. Add a line before saving.

#### Needle Loan Return from Party

`/m/knitting/needle-loan-return-by-party`

**ট্যাব: Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| RET Challan No | text |  | Search challan |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Receiving Store | select |  | Maintenance Store |
| Finalized? | select |  | Finalised, Still open |
| Status | select |  | Draft, Received, Finalized, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Return List 0, Knit MC Needle Loan Return, -- Supplier --, Search, Add New, Reset

**ট্যাব: Knit MC Needle Loan Return**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Ret Challan No* | text | ✔ | The party's own challan number |
| Ret Challan Date* | date | ✔ |  |
| Against Requisition | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Maintenance Store |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Knit MC Needle Loan Return, Return Challan, From, Gate, Item List
- বাটন/অ্যাকশন: Return List 0, Knit MC Needle Loan Return, -- Select Supplier --, -- Select --, Add Item, Back To List, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| RET Challan No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Receiving Store | select |  | Maintenance Store |
| Finalized? | select |  | Finalised, Still open |
| Status | select |  | Draft, Received, Finalized, Cancelled |

#### Oil Loan Issue Indent

`/m/knitting/oil-req-for-loan-issue`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req No | text |  | REQ/OIL/ST/… |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Status | select |  | Draft Created, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Issue, -- Supplier --, Search, Add New, Reset

**ট্যাব: Requisition for Loan Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Reason | text |  | Why the oil is being asked for |
| Remarks | textarea |  |  |
- সেকশন: Requisition for Loan Issue, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Issue, -- Select --, -- Select Department --, -- Select Party --, Add Item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition No | text |  |  |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requisition with no oil on it asks for nothing. Add a line before saving.

#### Oil Loan Return from Party

`/m/knitting/oil-loan-return-by-party`

**ট্যাব: Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| RET Challan No | text |  | Search challan |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Receiving Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Finalized? | select |  | Yes, No |
| Status | select |  | Draft, Received, Finalized, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Return List 0, Knit MC Oil Loan Return, -- Supplier --, Search, Add New, Reset

**ট্যাব: Knit MC Oil Loan Return**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Ret Challan No* | text | ✔ | The party's own challan number |
| Ret Challan Date* | date | ✔ |  |
| Against Requisition | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Knit MC Oil Loan Return, Return Challan, From, Gate, Item List
- বাটন/অ্যাকশন: Return List 0, Knit MC Oil Loan Return, -- Select Supplier --, -- Select --, Add Item, Back To List, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Ret Challan No* | text | ✔ |  |
| Ret Challan Date* | date | ✔ |  |
| Against Requisition | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A document with no oil on it moves nothing. Add a line before saving.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **15/07-এর needle store:**
  - কাগজ আর লাইন: `knit_needle_loan_reqs` + লাইন।
  - ledger: `knit_needle_moves` আর `NeedleStore` (`post`, `balance`, `assertOpen`, `exceptions`)।
- **ফ্রন্টএন্ড:** needle.jsx-এর `LoanList` আর `LoanEntry`। এখন এরা `book`, `fixedKind` আর `base` prop নেয়। 15/07-এর Needle Loan Indent স্ক্রিন আগের মতোই চলে।
- **মাস্টার:** General Store-এর item, category আর store। Supplier/Party আসে `mp_suppliers` থেকে, "Item Receive By" আসে `hr_employees` থেকে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_20_000100_create_knitting_store_loans.php`।

**দুই খাতার ledger**
- `knit_needle_moves`, `knit_needle_closings` আর `knit_needle_loan_reqs`-এ নতুন কলাম `book`: NEEDLE / OIL (ডিফল্ট NEEDLE)।
- একই General Store item আর store-এর ওপর দুটো খাতা চলে। তাই knitting store-এর ledger এখন দুই খাতার।
- ক্লোজিং এখন খাতা আর store দুটো ধরে আলাদা।
- 15/10 Oil Store OIL খাতায় যোগ করবে।

**`knit_needle_loan_returns` + `knit_needle_loan_return_lines` (NLT- নিডল / OLT- তেল)**
- হেডার:
  - book, return_no।
  - ret_challan_no আর ret_challan_date: পার্টির নিজের চালান। প্রতি book + party-তে চালান নম্বর একটাই।
  - req_id: Against Requisition, ঐচ্ছিক।
  - party, store, received_by (কর্মী), gate pass, vehicle, remarks।
  - status: DRAFT / RECEIVED / FINALIZED / CANCELLED। rcv_date বসে Receive-এর দিন।
- লাইন:
  - req_line_id: indent-এর একই item-এর লাইন।
  - item, uom, return_qty, received_qty, rate, remarks।
- ledger kind নতুন: `LOAN_BACK` (পার্টির ফেরত, স্টকে যোগ হয়)।

## ৫. Workflow / Status ও Business Rules

**Needle / Oil Loan Issue Indent**
- 15/07-এর loan indent-এর একই কাগজ। দিক সবসময় LOAN_ISSUE, তাই Req Type ঘর নেই।
- নিডল NLR- (15/07-এর Needle Loan Indent-এর সাথে একই সিরিজ, Brickland-এর REQ-MN- এর মতো), তেল OLR-।
- ধাপ একই: Submit বা Submit & Verify। তারপর এডিট ডেস্কে শুধু Issue Qty।
  - সেভ করলে store থেকে LOAN_OUT post হয়, নিজের খাতায়।
  - চাহিদা বা স্টকের বেশি ইস্যু আটকায়।
- তালিকায় বাড়তি দুটো কলাম:
  - **Returned**: গৃহীত রিটার্ন চালানে যা ফিরেছে।
  - **Party Owes** = ইস্যু − ফেরত।
- পার্টি যা ইতিমধ্যে ফেরত দিয়েছে, Issue Qty তার নিচে নামানো যায় না।

**Needle / Oil Loan Return from Party**
- পার্টির চালান নম্বর বাধ্যতামূলক। একই পার্টির একই চালান দুবার নেওয়া যায় না।
- **Against Requisition** (ঐচ্ছিক):
  - তালিকায় আসে ওই খাতার সেই পার্টির ইস্যু হওয়া loan indent-গুলো, যাদের পাওনা আছে।
  - বাছলে পার্টি আর store বসে। grid খালি থাকলে পাওনা লাইনগুলোও আসে।
  - indent-এ যায়নি এমন item, বা পাওনার চেয়ে বেশি ফেরত আটকায়।
- **Receive at Store**:
  - "Item Receive By" (কর্মী) না দিলে আটকায়।
  - শূন্য রাখা Received Qty চালানের পরিমাণ দিয়ে ভরে।
  - LOAN_BACK post হয় (স্টকে যোগ), rcv_date আজকের দিন হয়, পার্টির পাওনা কমে।
- তারপর Finalize।
- Cancel করা যায় Draft বা Received অবস্থায়। Received হলে post উঠে যায়।
- বন্ধ মাসে Receive বা Cancel হয় না, প্রতিটা খাতার নিজের ক্লোজিং ধরে।
- খাতার ক্লোজিং exception-এ Draft রিটার্ন চালানও ধরা হয়।

## ৬. API ও পারমিশন

**Loan indent** (আগের route, এখন খাতা অনুযায়ী)
- `GET /api/v1/knitting/needle/loan-reqs?book=NEEDLE|OIL&kind=LOAN_ISSUE`।
- সেভে `book` যায়। কাগজ একবার তৈরি হলে খাতা বদলায় না।

**Loan return** (`KnitLoanReturnApiController`, মোট ৬টা route)
- `loan-returns/{needle|oil}`
- `loan-returns/{book}/owing`
- `loan-returns/{book}/{no}`
- `loan-returns/{book}/{no}/{receive|finalize|cancel}`

**পারমিশন:** `knitting.needle.view` আর `knitting.needle.manage`, দুই খাতাতেই। নিডল আর তেল একই maintenance store-এর কাজ।

**মেনু**
- Brickland-এর মতো প্রতিটা store-এর নিজের "Loan Issue to Party" সাব-ফোল্ডার:
  - Needle Store-এর ভেতরে `KNITTING.LOAN_ISSUE`: LO_NEEDLE_REQ আর LO_NEEDLE_BACK।
  - নতুন ফোল্ডার `KNITTING.OIL_STORE` (Oil Store), তার ভেতরে `KNITTING.OIL_LOAN_ISSUE`: LO_OIL_REQ আর LO_OIL_BACK। 15/10-এর স্ক্রিনগুলো এই Oil Store ফোল্ডারেই যাবে।
- Knit Production-এর সব স্ক্রিন এখন ক্রলারের Brickland sidebar-এর ক্রমে সাজানো। ফোল্ডারের ক্রম: Master Data → Yarn Procurement → Yarn Store → Production → Fabric Store → Collar/Cuff → Needle Store → Oil Store।

## ৭. Frontend পেজ (লাইট থিম)

- স্ক্রিন রেজিস্ট্রি: `needle-screens.js`-এ `loanIssue(book)` আর `loanBack(book)`।
- **Loan Issue Indent:** `LoanList`/`LoanEntry` চলে `fixedKind="LOAN_ISSUE"` দিয়ে।
  - Req Type ঘর নেই। "Party" ঘরের নাম "Supplier", Brickland-এর মতো।
  - তালিকায় Returned আর Party Owes কলাম।
- **Loan Return:** নতুন `LoanReturnList` আর `LoanReturnEntry`।
  - ফর্মের অংশগুলো Brickland-এর মতো: Return Challan, From, Gate, Item List।
  - তালিকার ফিল্টার: RET Challan, তারিখ, Receiving Store, Supplier, Finalized?, Status।
- **প্রিন্ট:**
  - তেলের indent: "Oil Requisition for Loan Issue"।
  - রিটার্ন চালান: পার্টির প্রতিনিধি / Security / Received By / Store In-charge।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_20_000100_create_knitting_store_loans.php`
- `app/Modules/Knitting/Models/KnitNeedleLoanReturn.php`, `KnitNeedleLoanReturnLine.php` (নতুন); `KnitNeedleMove.php`, `KnitNeedleClosing.php`, `KnitNeedleLoanReq.php` (`book`)
- `app/Modules/Knitting/Support/NeedleStore.php` (`BOOKS`, খাতাভিত্তিক `post` / `assertOpen` / `exceptions`, `loanReturned`, কর্মী তালিকা)
- `app/Modules/Knitting/Http/Controllers/Api/KnitLoanReturnApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleFloorApiController.php` (loan indent: খাতা, Returned / Owes, ফেরতের নিচে Issue Qty নামানো আটকায়)
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleStoreApiController.php` (ক্লোজিং আর consumption NEEDLE খাতায়)
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/knitting/needle.jsx` (`LoanList`/`LoanEntry`-এ `book` / `fixedKind` / `base`; `LoanReturnList`, `LoanReturnEntry`, `printLoanReturn`)
- `frontend/src/app/(app)/knit-production/[screen]/needle-screens.js`

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php`: `nd.*`-এ নতুন কী, আর `prod.screens` / `prod.descriptions`-এ ৪টা স্ক্রিন

**Test**
- `tests/Feature/Knitting/KnittingStoreLoanTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingStoreLoanTest`, ১টা টেস্ট, ৫৩টা assertion, test2 DB-তে পাস।
- নিডল আর তেলের loan issue: নিজের নম্বর (NLR / OLR), নিজের খাতা, নিজের তালিকা।
- owing তালিকা ঠিক পাওনা দেখায়।
- রিটার্ন:
  - লাইন ছাড়া, অন্য পার্টির indent, আর পাওনার বেশি ফেরত আটকায়। একই চালান দুবার নেওয়াও আটকায়।
  - receiver ছাড়া Receive আটকায়।
  - Receive করলে Received Qty কাগজ থেকে বসে, স্টক ৯০ হয়। indent-এ ফেরত ২০ আর পাওনা ১০ দেখায়।
- ফেরতের নিচে Issue Qty নামানো আটকায়।
- Received রিটার্ন Cancel করলে স্টক ৭০-এ ফেরে।
- তেলের রিটার্ন: OLT নম্বর, নিডলের পথে খুঁজলে পাওয়া যায় না।
  - ৪৮ লিটার গ্রহণ হলে স্টক ১৯৮। post হয় OIL খাতায়।
  - Finalize-এর পর Cancel আটকায়।

**Regression:** `KnittingNeedleStoreTest` (১২৪টা assertion) আর `MenuAccessTest` আবার পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/lo01`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| General Store মাস্টার | category "Lubricants (LUB)"; item OIL-KN-22, OIL-KN-32 | — |
| NLR-2026110002 | Coats Bangladesh, ৪০টা ND-VO-7152 লোন ইস্যু | Issued |
| NLT-2026110001 | Square Textiles, চালান SQT/RET/0912, NLR-2026110001-এর বিপরীতে ১০টা সিঙ্কার ফেরত | Finalized; NLR-2026110001-এ ফেরত ১০, পাওনা ২০ |
| OLR-2026110001 | Square Textiles, ২০ লিটার OIL-KN-22 | Verified; ইস্যু আটকেছে, কারণ OIL খাতায় এখনো স্টক নেই |

**প্রিন্ট:** নিডল লোন রিটার্ন আর তেলের লোন indent, দুটোর preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **দুই খাতার ledger:** নিডল আর তেলের জন্য আলাদা টেবিল না বানিয়ে একই ledger-এ `book` কলাম দিয়েছি। টেবিলের নাম এখনো `knit_needle_*`। ক্লোজিং খাতা ধরে আলাদা।

২. **তেলের স্টক:** তেলের রিসিপ্ট আর ওপেনিং আসবে 15/10-এ। ততদিন OIL খাতায় স্টক নেই, তাই তেলের loan ইস্যু আটকায় (UI চেকে এটাই দেখা গেছে)।

৩. **একই কাগজ দুই স্ক্রিনে:** Needle Loan Issue Indent আর 15/07-এর Needle Loan Indent একই কাগজ দেখায়। Brickland-এ নম্বরের সিরিজও একই।

৪. **Against Requisition ঐচ্ছিক:** না দিলে রিটার্ন কোনো indent-এর পাওনা কমায় না, শুধু স্টকে যোগ হয়।

৫. **লাইন মেলানো:** রিটার্নের লাইন indent-এর একই item-এর প্রথম লাইনের সাথে মেলে।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/lo01`):** চারটা স্ক্রিনের ফর্ম, গ্রিড আর ব্যাখ্যা পড়া হয়েছে। "Item Receive By" বাধ্যতামূলক, আর Received Qty শূন্য রাখলে কাগজ থেকে বসে।
- **তালিকার Received Qty:** Receive করার পর তালিকার এই কলাম পুরোনো দেখাচ্ছিল। এখন সারির সাথে সাথে বদলায়।
