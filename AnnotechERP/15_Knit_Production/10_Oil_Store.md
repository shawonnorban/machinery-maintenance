# Oil Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (৬টার ৬টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/10`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Knit Machine Oil Indent | `/m/knitting/requisition-for-knitting-mc-oil` | 2 | 16 | 1 |
| 2 | Machine Oil Opening Balance | `/m/knitting/mc-oil-opening-blance` | 2 | 15 | 0 |
| 3 | Daily Machine Oil Consumption | `/m/knitting/daily-macine-wise-oil-consumption` | 2 | 18 | 1 |
| 4 | Oil Loan Opening Balance | `/m/knitting/mc-oil-loan-opening` | 1 | 9 | 0 |
| 5 | Machine Oil Receipt | `/m/knitting/mc-oil-receive` | 2 | 24 | 1 |
| 6 | Oil Receipt Return Register | `/m/knitting/mc-oil-receive-return-list` | 2 | 16 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Knit Machine Oil Indent

`/m/knitting/requisition-for-knitting-mc-oil`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | text |  | REQ/OIL/ST/… |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| To Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Status | select |  | Draft Created, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, Knit M/C Oil Requisition to Store, Search, Add New, Reset

**ট্যাব: Knit M/C Oil Requisition to Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| From Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| To Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Reason | text |  | Why the oil is being asked for |
| Remarks | textarea |  |  |
- সেকশন: Knit M/C Oil Requisition to Store, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, Knit M/C Oil Requisition to Store, -- Select --, -- Select Department --, Add Item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition No | text |  |  |
| Req Date* | date | ✔ |  |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| From Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| To Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requisition with no oil on it asks for nothing. Add a line before saving.

#### Machine Oil Opening Balance

`/m/knitting/mc-oil-opening-blance`

**ট্যাব: Company Data**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| OB Date* | date | ✔ |  |
| Item* | select | ✔ |  |
| Opening Qty* | number | ✔ |  |
| Unit Price | number |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Conv. Rate | number |  |  |
| Cost Price | text |  | [Unit Price × Conv. Rate] |
| Remarks | textarea |  |  |
- সেকশন: New opening line
- বাটন/অ্যাকশন: Details Data 0, M/C Oil Opening Blance, Company Data, -- Select Category --, --UoM--, Clear, Save

**ট্যাব: M/C Oil Opening Blance**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| OB Date | date |  |  |
| Item | text |  | Search item |
| Finalized? | select |  | Yes, No |
- সেকশন: M/C Oil Opening Blance
- বাটন/অ্যাকশন: Details Data 0, M/C Oil Opening Blance, Company Data, -- Select Category --, Load, Finalize All, Reset

#### Daily Machine Oil Consumption

`/m/knitting/daily-macine-wise-oil-consumption`

**ট্যাব: Daily Machine Oil ConsumptionList 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | text |  | M-ISU-… |
| Requisition Date | date |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Shift | select |  | A, B, C |
| Is Finalized | select |  | Yes, No |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Daily Machine Oil ConsumptionList 0, Daily Machine Oil Consumption, Search, Add New, Reset

**ট্যাব: Daily Machine Oil Consumption**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | text |  | M-ISU-… |
| Requisition Date | date |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Shift | select |  | A, B, C |
| Is Finalized | select |  | Yes, No |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Daily Machine Oil ConsumptionList 0, Daily Machine Oil Consumption, Search, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing consumption sheet | select |  |  |
| Requisition No | text |  |  |
| Requisition Date* | date | ✔ |  |
| Shift* | select | ✔ | A, B, C |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A consumption sheet with no machines on it records nothing. Add a line before saving.

#### Oil Loan Opening Balance

`/m/knitting/mc-oil-loan-opening`

**ট্যাব: Master Data**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| OB Date* | date | ✔ |  |
| Item Name* | select | ✔ |  |
| Loan Received | number |  |  |
| Loan Issued | number |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Cost Price | number |  |  |
| Is Final Both sides have agreed the figure. A finalised position stops being edi | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New loan position
- বাটন/অ্যাকশন: Details Data 0, Master Data, -- Select Supplier --, -- Select Category --, --UoM--, Clear, Save

#### Machine Oil Receipt

`/m/knitting/mc-oil-receive`

**ট্যাব: M/C Oil Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No# | text |  | MRR-OIL-… |
| MRR From Date | date |  |  |
| MRR To Date | date |  |  |
| Challan No# | text |  | Supplier's challan |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| With Finalized? | select |  | Yes, No |
| With Accounting? | select |  | Yes, No |
| Item Name | text |  | Any oil on the receipt |
| PR | text |  | PR number |
| PO | text |  | PO number |
| Status | select |  | Draft, Waiting For QC, Waiting for Varification, Wait for Accounts Approval, Approved, Complete, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Oil Receive List 0, M/C Oil Receive, -- Select Supplier --, -- Select Req Type --, Search, Add New, Reset

**ট্যাব: M/C Oil Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No# | text |  | MRR-OIL-… |
| MRR From Date | date |  |  |
| MRR To Date | date |  |  |
| Challan No# | text |  | Supplier's challan |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| With Finalized? | select |  | Yes, No |
| With Accounting? | select |  | Yes, No |
| Item Name | text |  | Any oil on the receipt |
| PR | text |  | PR number |
| PO | text |  | PO number |
| Status | select |  | Draft, Waiting For QC, Waiting for Varification, Wait for Accounts Approval, Approved, Complete, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Oil Receive List 0, M/C Oil Receive, -- Select Supplier --, -- Select Req Type --, Search, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing oil receipt | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| PR | select |  |  |
| PO | select |  |  |
| Challan No | text |  |  |
| Challan Date | date |  |  |
| Payment Method* | select | ✔ | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| Source* | text | ✔ |  |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A receipt with no oil on it receives nothing. Add a line before saving.

#### Oil Receipt Return Register

`/m/knitting/mc-oil-receive-return-list`

**ট্যাব: M/C Oil Receive Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  | Return challan |
| MRR No | text |  | MRR-OIL-… |
| Return Chalan Type | select |  | Returnable, Non Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Oil Receive Return List 0, M/C Oil Receive Return, -- Select Supplier --, Search, Add New, Reset

**ট্যাব: M/C Oil Receive Return**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  | Return challan |
| MRR No | text |  | MRR-OIL-… |
| Return Chalan Type | select |  | Returnable, Non Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store, Cutting Sub-Store, Dyeing Floor, GMT Sample Store, Knitting Floor, Maintenance Store, Packaging Store |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Oil Receive Return List 0, M/C Oil Receive Return, -- Select Supplier --, Search, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| MRR No* | select | ✔ |  |
| Rtn Challan#* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Return Chalan Type* | select | ✔ | Select…, Returnable, Non Returnable |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Reason | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines on it sends nothing back. Add a line before saving.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **15/07–09-এর store book:**
  - ledger `knit_needle_moves`-এ `book` = OIL।
  - `NeedleStore::post`, `unpost`, `balance`, `assertOpen` আর `exceptions`।
- **Oil Loan Opening Balance:** needle screen-এর `LoanOpeningList`/`LoanOpeningForm`-ই, `book` prop দিয়ে। Table-ও একই, `knit_needle_loan_openings`।
- **Frontend helper:** `needle.jsx` থেকে export করা হয়েছে:
  - `ItemCells`, `Select`, `Text`, `linesSection`
  - `PrintButton`, `Status`, `NoField`, `CompanyField`, `Remarks`
- **বাকি পাঁচটা স্ক্রিন:** Brickland-এর oil-এর ধাপ needle থেকে আলাদা, তাই এদের জন্য নতুন `knit_oil_*` table।
  - রিসিপ্টে Desk-চক্র আর Cond. Qty।
  - রিটার্নে Re-open।
  - Indent দুই store-এর মধ্যে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_22_000100_create_knitting_oil_store.php`।

**`knit_oil_reqs` + lines (ORQ-…): Knit Machine Oil Indent**
- হেডার:
  - req_date, req_type (ডিফল্ট KNIT_MC_OIL), office, department।
  - from_store (যে store চাইছে), to_store (যার কাছে চাওয়া)।
  - reason, remarks।
  - status: DRAFT / WAITING / VERIFIED / PARTLY_ISSUED / ISSUED / CLOSED / CANCELLED।
- লাইন: item, uom, required_qty, issue_qty, rate, remarks।

**`knit_oil_openings`: Machine Oil Opening Balance**
- প্রতি store, তারিখ আর item-এ এক লাইন।
- কলাম: qty, unit_price, currency (currencies master), conv_rate।
- cost_price = unit_price × conv_rate, সেভের সময় হিসাব করে রাখা হয়।
- remarks, finalized।

**`knit_oil_consumptions` + lines (OCS-…): Daily Machine Oil Consumption**
- হেডার:
  - প্রতি store, তারিখ আর shift-এ একটা শিট।
  - office, issued_by (hr_employees)।
  - status: DRAFT (Open) / FINALIZED।
- লাইন: machine, item, uom, qty, running_hrs, cost_price।

**`knit_oil_receipts` + lines (OMR-…): Machine Oil Receipt**
- হেডার:
  - supplier, store, req_type।
  - challan_no আর challan_date।
  - pay_method (বাধ্যতামূলক, ডিফল্ট CREDIT), source (বাধ্যতামূলক, ডিফল্ট LOCAL), lc_no।
  - status: DRAFT / WAIT_QC / WAITING / WAIT_ACCOUNTS / APPROVED / COMPLETE / CANCELLED।
  - finalized_at, accounted_at।
- লাইন: challan_qty, received_qty (RCV), cond_qty, rate, amount।

**`knit_oil_returns` + lines: Oil Receipt Return Register**
- হেডার:
  - challan_no (স্টোরের নিজের, বাধ্যতামূলক), receipt।
  - challan_type (RETURNABLE / NON_RETURNABLE)।
  - gate pass, vehicle, reason।
  - status: DRAFT / ISSUED / FINALIZED / CANCELLED।
- লাইন: receipt_line, qty, rate, amount।

**অন্যান্য:**
- `knit_needle_loan_openings.book`: unique এখন (company, book, party, item)।
- ledger-এ নতুন kind `CONSUME`।

## ৫. Workflow / Status ও Business Rules

**Knit Machine Oil Indent**
- **ধাপ:** 13-Submit → Verify, অথবা সরাসরি 14-Verify & Submit R।
- **Verify-এর পর:** শুধু Issue Qty বদলানো যায়।
  - সেভ করলে `ISSUE_OUT` (যার কাছে চাওয়া, সেই store) আর `ISSUE_IN` (যে চেয়েছে, সেই store) post হয়, OIL খাতায়।
  - Issue Qty চাহিদার বেশি হতে পারে না।
  - Store-এ যা আছে তার বেশিও না।
- **Status:** Partly Issued বা Issued আপনা থেকে হয়।
- **বাকি বাটন:**
  - Issue in Full: সব লাইন পুরো চাহিদায় ইস্যু করে।
  - Close: শুধু Partly Issued অবস্থায়।
  - Re-open: যা ইস্যু হয়েছিল তা ফেরত নেয়, তারপর Draft।
  - Cancel: Draft, Waiting বা Verified অবস্থায়।
- **তালিকার Returned আর Outstanding:** তেল ফ্লোর থেকে ফেরত আসার কোনো কাগজ নেই। তাই Returned = 0, Outstanding = Issued। Brickland-এও তা-ই দেখা গেছে।

**Machine Oil Opening Balance**
- প্রতি store, তারিখ আর item-এ একটাই লাইন।
- Cost Price = Unit Price × Conv. Rate। মুদ্রা বাছলে Conv. Rate master-এর exchange rate থেকে আসে।
- **Finalize:**
  - সারির বাটনে, অথবা শিট ট্যাবে টিক দিয়ে Finalize All।
  - Finalize হলে `OPENING` post হয়, আর লাইন আর বদলানো যায় না।

**Daily Machine Oil Consumption**
- প্রতি store, তারিখ আর shift-এ একটাই শিট।
- Office বাছলে শুধু সেই unit-এর মেশিন দেখায়।
- **Finalize:**
  - Issue By ছাড়া হয় না।
  - Store-এ যত তেল আছে তার বেশি খরচ দেখানো যায় না।
  - Finalize হলে `CONSUME` post হয় (store থেকে বিয়োগ), আর শিট আর বদলানো যায় না।

**Machine Oil Receipt**
- **Send to Next Desk:** Draft → Waiting For QC → Waiting for Verification → Wait for Accounts Approval → Approved → Complete।
- **Finalize:** শুধু Verification বা Accounts ডেস্কে। এটাই `RECEIPT` post করে।
- **Accounting Done:** শুধু Finalize হওয়ার পরে।
- **ডেস্ক ছাড়ার শর্ত:**
  - Finalize না হলে Accounts ডেস্ক ছাড়া যায় না।
  - Accounting Done না হলে Approved থেকে Complete হয় না।
- **Cancel:** Finalize-এর আগে পর্যন্ত।
- **লাইনের নিয়ম:**
  - Cond. Qty RCV Qty-র বেশি হতে পারে না।
  - LC # শুধু L/C পেমেন্টে।

**Oil Receipt Return**
- Finalize হওয়া রিসিপ্টের বিপরীতেই শুধু।
- প্রতি লাইনে যত ফেরত দেওয়া বাকি আছে, তার বেশি না।
- **ধাপ:**
  - Issue: `RETURN` post হয়।
  - Finalize।
  - Re-open এক ধাপ পেছায়: Finalized → Issued, আর Issued → Draft (posting উঠে যায়)।
  - Cancel: শুধু Draft অবস্থায়।

**Oil Loan Opening Balance**
- প্রতি party আর item-এ একটা net position, OIL খাতায়।
- Loan Received এখন তেলের "ধার নেওয়া" হিসাবেও ধরা হয়:
  - 15/09-এর ফেরতের সীমায়।
  - রিসিপ্টের Returned হিসাবে।
- **Month close:** তেলের অসমাপ্ত কাগজ OIL খাতার month close আটকায় (`NeedleStore::exceptions`)।

## ৬. API ও পারমিশন

**Oil Store** (`KnitOilStoreApiController`, ২৭টা route, সব `/api/v1/knitting/needle/oil/` দিয়ে শুরু)
- `options`
- `reqs`, `reqs/{no}`
  - `reqs/{no}/{submit|submit-verify|verify|issue-full|reopen|close|cancel}`
- `openings`
  - `openings/{id}`
  - `openings/{id}/finalize`
  - `openings/finalize` (`ids[]`)
- `consumptions`, `consumptions/{no}`
  - `consumptions/{no}/finalize`
- `receipts`, `receipts/{no}`
  - `receipts/{no}/source`
  - `receipts/{no}/{next|finalize|accounting|cancel}`
- `returns`, `returns/{no}`
  - `returns/{no}/{issue|finalize|reopen|cancel}`

**Loan opening:** আগের `loan-openings` route-ই, এখন `?book=OIL` বা body-তে `book`।

**পারমিশন:** `knitting.needle.view` আর `knitting.needle.manage`।

**মেনু:** Oil Store ফোল্ডারে সরাসরি ৬টা leaf, Brickland-এর ক্রমে:
- `KNITTING.OIL_INDENT`
- `OIL_OPENING`
- `OIL_CONSUMPTION`
- `OIL_LOAN_OPENING`
- `OIL_RECEIPT`
- `OIL_RETURN`

এদের পরে আগের Loan Issue to Party আর Loan Rcv from Party সাব-ফোল্ডার।

## ৭. Frontend পেজ (লাইট থিম)

**কম্পোনেন্ট:** নতুন ফাইল `frontend/src/components/knitting/oil.jsx`।
- Indent: `OilReqList` / `OilReqEntry`
- Opening:
  - `OilOpeningList`: Details Data
  - `OilOpeningSheet`: M/C Oil Opening Blance। Load, টিক, Finalize All, পাতার মোট আর সর্বমোট।
  - `OilOpeningForm`: Company Data
- Consumption: `OilConsList` / `OilConsEntry`
- Receipt: `OilReceiptList` / `OilReceiptEntry`
- Return: `OilReturnList` / `OilReturnEntry`। MRR বাছলে "Receipt Line" ড্রপডাউনে প্রতিটা লাইনে কত বাকি দেখায়।
- পাঁচটা print।

**স্ক্রিন রেজিস্ট্রি:** `needle-screens.js`-এ:
- `desk()`: `side: "oil"` দিয়ে।
- `oilOpening`, `oilReturns`।
- `loanOpening(book)`।

**URL:** নম্বর দিয়ে (`?ref=ORQ-…`, `OMR-…`, `OCS-…`, `RTN-…`)। Opening লাইন খোলে store, তারিখ আর item code দিয়ে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৬টার ৬টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_22_000100_create_knitting_oil_store.php`
- `app/Modules/Knitting/Models/KnitOilReq.php`, `KnitOilReqLine.php`, `KnitOilOpening.php`, `KnitOilConsumption.php`, `KnitOilConsumptionLine.php`, `KnitOilReceipt.php`, `KnitOilReceiptLine.php`, `KnitOilReturn.php`, `KnitOilReturnLine.php`
- `app/Modules/Knitting/Models/KnitNeedleLoanOpening.php` (`book`), `KnitNeedleMove.php` (`CONSUME`)
- `app/Modules/Knitting/Http/Controllers/Api/KnitOilStoreApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleStoreApiController.php` (loan opening-এ `book`)
- `app/Modules/Knitting/Http/Controllers/Api/KnitLoanReceiptApiController.php` (opening-এর Loan Received, book ধরে)
- `app/Modules/Knitting/Support/NeedleStore.php` (`borrowed` book ধরে, `oilExceptions`)
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/knitting/oil.jsx`
- `frontend/src/components/knitting/needle.jsx`
- `frontend/src/app/(app)/knit-production/[screen]/needle-screens.js`

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php`: `nd.*`-এ oil-এর কী, আর ৬টা স্ক্রিন

**Test**
- `tests/Feature/Knitting/KnittingOilStoreTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingOilStoreTest`, ২টা টেস্ট, ১৪৮টা assertion, test2 DB-তে পাস।
- **Opening:**
  - Cost Price 3 × 120 = 360, Value 14,400।
  - একই লাইন দুবার দেওয়া আটকায়।
  - কোনো লাইনে টিক ছাড়া Finalize All আটকায়।
  - Finalize হলে স্টক 40 (OIL খাতা)।
  - Finalize-এর পর edit আটকায়।
- **Receipt:**
  - Pay method বা Source ছাড়া সেভ আটকায়।
  - Cond. Qty RCV-র বেশি দিলে আটকায়।
  - L/C ছাড়া LC # আটকায়।
  - পুরো ডেস্ক-চক্র চলে।
  - Finalize ছাড়া Accounts ডেস্ক ছাড়া যায় না। Accounting ছাড়া Complete হয় না।
  - Finalize হলে স্টক 138।
  - Finalize-এর পর Cancel আটকায়।
- **Return:**
  - Draft রিসিপ্টের বিপরীতে আটকায়।
  - Challan # ছাড়া আটকায়।
  - বাকির বেশি (98) আটকায়।
  - Issue হলে স্টক 135। Re-open করলে 138। Finalize, তারপর Re-open করলে Issued।
  - রিসিপ্টের Returnable 95।
- **Indent:**
  - একই store দুই পাশে দিলে আটকায়।
  - চাহিদার বেশি ইস্যু আটকায়।
  - 5, তারপর 8 ইস্যু: দুই store-এর স্টক ঠিক থাকে।
  - Issue in Full হলে Issued। Re-open করলে সব আগের মতো।
  - store-এ যত আছে তার বেশি চাইলে "The store holds only 30 …"।
- **Consumption:**
  - একই store, তারিখ আর shift-এ দ্বিতীয় শিট আটকায়।
  - Issue By ছাড়া Finalize আটকায়।
  - Finalize হলে `CONSUME`, স্টক 27.5।
  - Finalize-এর পর edit আটকায়।
- **Oil Loan Opening:**
  - একই party আর item, NEEDLE আর OIL খাতায় আলাদাভাবে রাখা যায়।
  - `?book=OIL` শুধু তেলের position দেয়।
  - `borrowed('OIL')`-এ 12 আসে।

**Regression:** `KnittingNeedleStoreTest`, `KnittingStoreLoanTest`, `KnittingLoanRcvTest` আর `MenuAccessTest` আবার পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/oil01`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| Opening, Maintenance Store, 01 Nov 2026 | OIL-KN-32: 120 লিটার × 2.50 USD × 120 = 300.00 (36,000.00)। OIL-KN-22: 80 লিটার × 310 BDT (24,800.00) | দুটোই Finalized। একটা সারির বাটনে, একটা Finalize All দিয়ে |
| OMR-2026110001 | Square Textiles, চালান SQT/OIL-118, OIL-KN-22: RCV 198, Cond. 18, দর 305 | Approved, Finalized হ্যাঁ, Accounting না। ফেরতযোগ্য 193 |
| RTN-OIL-0001 | OMR-2026110001-এর 5 লিটার ফেরত, Non-Returnable | Issued |
| ORQ-2026110001 | Knitting Floor Store চেয়েছে Maintenance Store-এর কাছে: 40 লিটার, ইস্যু 25 | Partly Issued |
| OCS-2026110001 | Night Shift, Knitting Floor Store, KM-01 3 লিটার / 22 ঘণ্টা, KM-02 2.5 লিটার / 20 ঘণ্টা, Issue By Mohammad Sayem | Finalized |
| Oil Loan Opening | Coats Bangladesh, OIL-KN-32, Loan Received 15 | Open |

- **যে বাধাগুলো দেখা গেছে:**
  - খালি রিসিপ্ট সেভ করলে store আর supplier চায়।
  - Finalize ছাড়া পরের ডেস্কে পাঠালে আটকায়: "… has not been finalized, so it cannot leave the accounts desk."।
  - Issue By ছাড়া শিট Finalize করলে আটকায়।
- **Needle Loan Opening:** তালিকায় শুধু needle-এর position দেখায়। তেলেরটা আলাদা থাকে।
- **প্রিন্ট:** রিসিপ্ট (MRR), রিটার্ন চালান, indent আর consumption-এর preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **PR/PO:** Brickland-এর রিসিপ্টে PR আর PO বাছাই আছে, তালিকায় PR, PO, Is Exchanged আর Exchanged MRR কলামও আছে। আমাদের তেলের PR/PO উৎস এখনো নেই, তাই এগুলো রাখা হয়নি। Procurement-এর সাথে যুক্ত হলে যোগ হবে।

২. **রিটার্ন কোন রিসিপ্টের বিপরীতে:** Brickland Draft রিসিপ্টের বিপরীতেও রিটার্ন নিতে দেয়। আমরা শুধু Finalize হওয়া রিসিপ্টের বিপরীতে নিই, কারণ তার আগে স্টকে কিছু ওঠে না।

৩. **Indent-এর Re-open:** Brickland Issued অবস্থাতেও Re-open দেখায়। আমাদের Re-open যা ইস্যু হয়েছিল তা ফেরত নেয়, তারপর Draft করে। এই অর্থ ঠিক আছে কিনা মিলিয়ে দেখা দরকার।

৪. **Consumption-এ মেশিন:** Brickland demo-তে মেশিন নেই, তাই শিট সেভ করে row-এর বাটন দেখা যায়নি। Finalize আর Print রাখা হয়েছে।

৫. **Oil Stock Closing:** Brickland-এর Oil Store-এ closing স্ক্রিন নেই। OIL খাতার মাস বন্ধ করার কোনো পথ এখনো নেই, শুধু exception-এর হিসাব তৈরি রাখা আছে।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/oil01`–`oil05`, demo-তে লেখা):**
  - একটা indent, একটা রিসিপ্ট আর একটা রিটার্ন বানিয়ে প্রতিটা ধাপের বাটন দেখা হয়েছে।
  - রিসিপ্টের পরের ডেস্ক Finalize ছাড়া এগোয় না।
  - রিটার্নের Re-open এক ধাপ পেছায়।
  - Issue in Full করলে Outstanding = Issued।
- **নিশ্চিত করার বাক্স:** Brickland-এ নিশ্চিত করার বাক্সটা পেজের ভেতরের dialog। Probe-এ `[role=dialog]`-এর শেষ বাটন চাপতে হয়েছে।
- **Shift:** shift master থেকে নেওয়া হয়েছে (`shift_id`)। Brickland-এ A/B/C।
