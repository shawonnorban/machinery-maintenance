# Loan Rcv from Party

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/09`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Borrowed Needle Receipt | `/m/knitting/needle-loan-receive-from-party` | 2 | 18 | 1 |
| 2 | Borrowed Needle Return Issue | `/m/knitting/needle-received-loan-issue-to-party` | 2 | 13 | 1 |
| 3 | Borrowed Oil Receipt | `/m/knitting/oil-loan-receive-from-party` | 2 | 18 | 1 |
| 4 | Borrowed Oil Return Issue | `/m/knitting/oil-received-loan-issue-to-party` | 2 | 14 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Borrowed Needle Receipt

`/m/knitting/needle-loan-receive-from-party`

**ট্যাব: Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No | text |  | MRR-NDL-… |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Receiving Store | select |  | Maintenance Store |
| Payment Method | select |  | ADJUSTMENT, CASH, CREDIT, L/C |
| Status | select |  | Draft, Waiting for Verification, Verified, Wait for Accounts Approval, Approved, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Receive List 0, Knit MC Needle Loan Receive, -- Supplier --, Search, Add New, Reset

**ট্যাব: Knit MC Needle Loan Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing loan receipt | select |  |  |
| MRR No | text |  | [Auto Gen] |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Maintenance Store |
| Payment Method* | select | ✔ | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| Source* | text | ✔ |  |
| Challan # | text |  | The party's own challan number |
| Challan Date | date |  |  |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Knit MC Needle Loan Receive, Receive Info, From, Against, Item List
- বাটন/অ্যাকশন: Receive List 0, Knit MC Needle Loan Receive, -- Select --, -- Select Supplier --, Add Item, Back To List, Clear, Save loan receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing loan receipt | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Maintenance Store |
| Payment Method* | select | ✔ | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| Source* | text | ✔ |  |
| Challan # | text |  |  |
| Challan Date | date |  |  |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A challan with no needles on it moves nothing. Add a line before saving.

#### Borrowed Needle Return Issue

`/m/knitting/needle-received-loan-issue-to-party`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req No | text |  | REQ-MN-… |
| Store | select |  | Maintenance Store |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Return to Party, -- Party --, Search, Add New, Reset

**ট্যাব: Requisition for Loan Return to Party**

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
- সেকশন: Requisition for Loan Return to Party, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Return to Party, -- Select --, -- Select Department --, -- Select Party --, Add Item, Back To List, Clear, Save requisition

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

#### Borrowed Oil Receipt

`/m/knitting/oil-loan-receive-from-party`

**ট্যাব: Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No | text |  | MRR-OIL-LN-… |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Receiving Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Payment Method | select |  | ADJUSTMENT, CASH, CREDIT, L/C |
| Status | select |  | Draft, Waiting for Verification, Verified, Wait for Accounts Approval, Approved, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Receive List 0, Knit MC Oil Loan Receive, -- Supplier --, Search, Add New, Reset

**ট্যাব: Knit MC Oil Loan Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing loan receipt | select |  |  |
| MRR No | text |  | [Auto Gen] |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Payment Method* | select | ✔ | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| Source* | text | ✔ |  |
| Challan # | text |  | The party's own challan number |
| Challan Date | date |  |  |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Knit MC Oil Loan Receive, Receive Info, From, Against, Item List
- বাটন/অ্যাকশন: Receive List 0, Knit MC Oil Loan Receive, -- Select --, -- Select Supplier --, Add Item, Back To List, Clear, Save loan receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing loan receipt | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Payment Method* | select | ✔ | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| Source* | text | ✔ |  |
| Challan # | text |  |  |
| Challan Date | date |  |  |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A document with no oil on it moves nothing. Add a line before saving.

#### Borrowed Oil Return Issue

`/m/knitting/oil-received-loan-issue-to-party`

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
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Return to Party, -- Supplier --, Search, Add New, Reset

**ট্যাব: Requisition for Loan Return to Party**

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
- সেকশন: Requisition for Loan Return to Party, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, Requisition for Loan Return to Party, -- Select --, -- Select Department --, -- Select Party --, Add Item, Back To List, Clear, Save requisition

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
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **15/07 আর 15/08-এর অংশ:**
  - দুই খাতার ledger: `knit_needle_moves`, `NeedleStore::post`, `assertOpen` আর `exceptions`, সবকিছুতে `book`।
  - loan indent (`knit_needle_loan_reqs`), দিক LOAN_RETURN।
  - ফ্রন্টএন্ডে `LoanList`/`LoanEntry`, `fixedKind` দিয়ে।
- **15/07-এর loan opening:** Loan Received ধার নেওয়ার হিসাবে ধরা হয়, তার নিজের খাতায় (15/10 থেকে OIL খাতাতেও)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_21_000100_create_knitting_loan_receipts.php`।

**`knit_needle_loan_receipts` + `knit_needle_loan_receipt_lines` (NLB- নিডল / OLB- তেল)**
- হেডার:
  - book, mrr_no, mrr_date, req_type।
  - party, store।
  - pay_method (বাধ্যতামূলক), source (বাধ্যতামূলক)।
  - challan_no আর challan_date (পার্টির চালান), lc_no, remarks।
  - status: DRAFT / WAITING / VERIFIED / WAIT_ACCOUNTS / APPROVED / CANCELLED।
- লাইন: item, uom, receive_qty, rate, remarks।
- Returned Qty কোথাও লেখা হয় না: পার্টিকে যে loan-return indent দেওয়া হয়েছে, সেখান থেকে হিসাব হয়।
- ledger-এ নতুন kind `LOAN_IN`: ধার নেওয়া মাল স্টকে যোগ।

## ৫. Workflow / Status ও Business Rules

**Borrowed Needle / Oil Receipt**
- পেমেন্টের ধরন আর Source বাধ্যতামূলক। পেমেন্টের ধরন ডিফল্ট Adjustment। LC # শুধু L/C-তে দেওয়া যায়।
- ধাপ: Draft → Submit → **Verify** (`LOAN_IN` post হয়) → Send to Accounts → Accounts Approve।
- Cancel শুধু Verify-এর আগে।
- **Returned**: পার্টিকে যা ফেরত গেছে। হিসাবের ক্রম:
  1. আগে opening-এর Loan Received-এর বিপরীতে ধরা হয়।
  2. তারপর পুরোনো রিসিপ্ট থেকে শুরু করে প্রতিটা রিসিপ্টে।
- **Outstanding** = গৃহীত − ফেরত।

**Borrowed Needle / Oil Return Issue**
- loan indent, দিক LOAN_RETURN। ধাপ একই: Submit বা Submit & Verify, তারপর শুধু Issue Qty।
  - সেভ করলে `LOAN_OUT` post হয়, নিজের খাতায়।
- **সীমা:** একই item-এ পার্টিকে মোট ফেরত (সব loan-return indent মিলিয়ে) তার বেশি হতে পারে না, যতটা সেই পার্টি থেকে ধার নেওয়া হয়েছে:
  - opening-এর Loan Received, যোগ
  - post হওয়া loan রিসিপ্ট।

## ৬. API ও পারমিশন

**Loan receipt** (`KnitLoanReceiptApiController`, মোট ৫টা route)
- `loan-receipts/{needle|oil}`
- `loan-receipts/{book}/{no}`
- `loan-receipts/{book}/{no}/{submit|verify|accounts|approve|cancel}`

**Return Issue:** আগের route, `loan-reqs?book=…&kind=LOAN_RETURN`।

**পারমিশন:** `knitting.needle.view` আর `knitting.needle.manage`।

**মেনু:** Brickland-এর মতো প্রতিটা store-এ "Loan Rcv from Party" সাব-ফোল্ডার।
- Needle Store-এ `KNITTING.LOAN_RCV`: LR_NEEDLE_RCV আর LR_NEEDLE_BACK।
- Oil Store-এ `KNITTING.OIL_LOAN_RCV`: LR_OIL_RCV আর LR_OIL_BACK।
- Knit Production-এর ক্রম আবার Brickland sidebar অনুযায়ী সাজানো হয়েছে।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** needle.jsx-এ নতুন `LoanReceiptList`, `LoanReceiptEntry` আর `printLoanReceipt`।
  - ফর্মের অংশ: Receive Info, From, Against, Item List।
  - Returned Qty কলাম শুধু পড়া যায়।
- **শব্দ:** `LoanList`/`LoanEntry` দিক অনুযায়ী শব্দ বাছে: lo = লোন দেওয়া, lr = ধার ফেরত।
- **স্ক্রিন রেজিস্ট্রি:** `needle-screens.js`-এ `loanReceipt(book)`, আর `loanIssue(book, "LOAN_RETURN")`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_21_000100_create_knitting_loan_receipts.php`
- `app/Modules/Knitting/Models/KnitNeedleLoanReceipt.php`, `KnitNeedleLoanReceiptLine.php`; `KnitNeedleMove.php` (`LOAN_IN`)
- `app/Modules/Knitting/Support/NeedleStore.php` (`borrowed`, `returnedTo`, exception-এ loan রিসিপ্ট)
- `app/Modules/Knitting/Http/Controllers/Api/KnitLoanReceiptApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleFloorApiController.php` (LOAN_RETURN-এ ধারের সীমা)
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/knitting/needle.jsx`
- `frontend/src/app/(app)/knit-production/[screen]/needle-screens.js`

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php`: `nd.*`-এ নতুন কী, আর ৪টা স্ক্রিন

**Test**
- `tests/Feature/Knitting/KnittingLoanRcvTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingLoanRcvTest`, ১টা টেস্ট, ৪৫টা assertion, test2 DB-তে পাস।
- **Borrowed Receipt:**
  - লাইন ছাড়া, আর পেমেন্টের ধরন বা Source ছাড়া সেভ আটকায়।
  - L/C ছাড়া LC # আটকায়।
  - Draft অবস্থায় Verify আটকায়।
  - দুটো রিসিপ্ট (৫০ + ৩০) Verify হলে স্টক ৮০। kind `LOAN_IN`।
  - Verify-এর পর Cancel আটকায়।
- **Return Issue:**
  - ৯০-এর বেশি ফেরত আটকায় (opening-এ ১০ + রিসিপ্টে ৮০)।
  - ৪০ ফেরত দিলে Partly Issued, স্টক ৪০।
- **Returned / Outstanding:** প্রথম রিসিপ্টে ফেরত ৩০, বাকি ২০। দ্বিতীয়টায় ফেরত ০, বাকি ৩০।
- **তেল:** OLB নম্বর, নিডলের পথে খুঁজলে পাওয়া যায় না। পুরো Approve পর্যন্ত চলে, post হয় OIL খাতায়।

**Regression:** `KnittingStoreLoanTest`, `KnittingNeedleStoreTest` আর `MenuAccessTest` আবার পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/lr01`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| NLB-2026110001 | Coats Bangladesh, ৬০টা ND-VO-7441 ধার, চালান CB/LN-07 | Wait for Accounts Approval; ফেরত ৫০, বাকি ১০ |
| NLR-2026110003 | Coats Bangladesh-কে ফেরত: চাহিদা ২০০, ইস্যু ১৫০ | Partly Issued |
| OLB-2026110001 | Square Textiles, ৩০ লিটার OIL-KN-22 ধার | Verified; ফেরত ১০, বাকি ২০ |
| OLR-2026110002 | Square Textiles-কে ১০ লিটার তেল ফেরত | Issued |

- NLR-2026110003-এ ২০০ দিলে আটকেছে, কারণ মোট ধার ১৬০ (opening-এ ১০০ + রিসিপ্টে ৬০)।

**প্রিন্ট:** নিডল লোন রিসিভ (MRR), preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **ফেরতের সাথে রিসিপ্টের যোগ:** Brickland-এর ফেরত ইস্যুতে কোন রিসিপ্টের বিপরীতে ফেরত, তা বলা নেই। তাই রিসিপ্টের Returned হিসাব করা হয় আগে opening ধরে, তারপর পুরোনো রিসিপ্ট থেকে শুরু করে।

২. **তেলের opening (মীমাংসিত, 15/10):** Oil Loan Opening Balance-এর Loan Received এখন তেলের ধারের হিসাবে ধরা হয়।

৩. **ফেরতের সীমা কীভাবে গোনা হয়:** সীমা মাপা হয় ফেরত indent-এর Issue Qty দিয়ে। অন্য পার্টি থেকে ধার নেওয়া একই item এই হিসাবে মেলে না।

৪. **একই কাগজ দুই স্ক্রিনে:** 15/07-এর Needle Loan Indent-এ "Loan Return to Party" দিকটা Borrowed Needle Return Issue-এর সাথে একই কাগজ।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/lr01`):** চারটা স্ক্রিনের ফর্ম, গ্রিড আর ব্যাখ্যা পড়া হয়েছে। রিসিপ্ট গ্রিডে Returned Qty আছে, আর তালিকার Outstanding = গৃহীত − ফেরত।
- **নতুন screen ফাংশনে ভুল:** প্রথমে base-এ `/knit-production/` অংশটা বাদ পড়েছিল। চালানোর আগেই ধরা পড়ে ঠিক করা হয়েছে।
