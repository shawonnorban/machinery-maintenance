# Needle Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (১০টার ১০টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/07`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Needle Loan Opening Balance | `/m/knitting/mc-needle-loan-opening` | 1 | 10 | 0 |
| 2 | Needle Loan Indent | `/m/knitting/mc-needle-loan-requisition` | 2 | 14 | 1 |
| 3 | Machine Needle Receipt | `/m/knitting/mc-needle-receive` | 3 | 28 | 1 |
| 4 | Needle Opening Balance | `/m/knitting/mc-needle-opening-balance` | 1 | 4 | 0 |
| 5 | Machine-Wise Needle Assignment | `/m/knitting/mc-wise-needle-assign` | 1 | 2 | 0 |
| 6 | Machine-Wise Broken Needle | `/m/knitting/mc-wise-needle-broken` | 2 | 16 | 1 |
| 7 | Machine-Wise Needle Indent | `/m/knitting/mc-wise-needle-requisition` | 2 | 19 | 1 |
| 8 | Machine-Wise Needle Issue | `/m/knitting/mc-wise-needle-issue` | 2 | 17 | 1 |
| 9 | Needle Stock Closing | `/m/knitting/mc-needle-closing` | 1 | 13 | 0 |
| 10 | Needle Receipt Return Register | `/m/knitting/mc-needle-receive-return-list` | 2 | 18 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Needle Loan Opening Balance

`/m/knitting/mc-needle-loan-opening`

**ট্যাব: Master Data**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| OB Date* | date | ✔ |  |
| Category* | select | ✔ | Machine Spares (IC-SPR), Needle & Sinker (IC-NDL), Electric General Item (IC-ELC), Maintenance Tools (IC-MNT), Mechanica |
| Item Name* | select | ✔ |  |
| Loan Received | number |  |  |
| Loan Issued | number |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| Cost Price | number |  |  |
| Is Final Both sides have agreed the figure. A finalised position stops being edi | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New loan position
- বাটন/অ্যাকশন: Details Data 0, Master Data, -- Select Supplier --, --UoM--, Clear, Save

#### Needle Loan Indent

`/m/knitting/mc-needle-loan-requisition`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req No | text |  | REQ-MN-… |
| Store | select |  | Maintenance Store |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, M/C Needle Loan Requisition, -- Party --, Search, Add New, Reset

**ট্যাব: M/C Needle Loan Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Req No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Req Type* | select | ✔ | Requisition for Loan Issue, Requisition for Loan Return to Party |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Maintenance Store |
| Reason | text |  | Why the needles are being lent or sent back |
| Remarks | textarea |  |  |
- সেকশন: M/C Needle Loan Requisition, Requisition Info, Raised By, Against, Item List
- বাটন/অ্যাকশন: Requisition List 0, M/C Needle Loan Requisition, -- Select --, -- Select Department --, -- Select Party --, Add Item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Req No | text |  |  |
| Req Date* | date | ✔ |  |
| Req Type* | select | ✔ | Requisition for Loan Issue, Requisition for Loan Return to Party |
| Comapny* | select | ✔ | Brickland Composite Ltd. |
| Office | select |  | Brickland Composite Ltd. Head Office |
| Store | select |  | Maintenance Store |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A document with no needles on it moves nothing. Add a line before saving.

#### Machine Needle Receipt

`/m/knitting/mc-needle-receive`

**ট্যাব: M/C Needle Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | text |  | MRR-ND-… |
| MRR From Date | date |  |  |
| MRR To Date | date |  |  |
| Challan No# | text |  | Supplier challan |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| With Finalized? | select |  | Yes, No |
| With Accounting? | select |  | Yes, No |
| Item Name | text |  | Needle code or name |
| PR | select |  |  |
| PO | select |  |  |
| Status | select |  | Draft, Waiting for Verification, Verified, Wait for Accounts Approval, Approved, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Needle Receive List 0, M/C Needle Receive, Item Wise Consumptions, -- Select Req Type --, -- Select Supplier --, Search, Add New, Reset

**ট্যাব: M/C Needle Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | text |  | MRR-ND-… |
| MRR From Date | date |  |  |
| MRR To Date | date |  |  |
| Challan No# | text |  | Supplier challan |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| With Finalized? | select |  | Yes, No |
| With Accounting? | select |  | Yes, No |
| Item Name | text |  | Needle code or name |
| PR | select |  |  |
| PO | select |  |  |
| Status | select |  | Draft, Waiting for Verification, Verified, Wait for Accounts Approval, Approved, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Needle Receive List 0, M/C Needle Receive, Item Wise Consumptions, -- Select Req Type --, -- Select Supplier --, Search, Add New, Reset

**ট্যাব: Item Wise Consumptions**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Item Wise Consumptions
- বাটন/অ্যাকশন: M/C Needle Receive List 0, M/C Needle Receive, Item Wise Consumptions, Show

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receipt | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Maintenance Store |
| Challan No | text |  |  |
| Challan Date | date |  |  |
| PR | select |  |  |
| PO | select |  |  |
| Source | text |  |  |
| Payment Method | select |  | Select…, ADJUSTMENT, CASH, CREDIT, L/C |
| LC # | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A receipt with no needles on it takes nothing in. Add a line before saving.

#### Needle Opening Balance

`/m/knitting/mc-needle-opening-balance`

**ট্যাব: Opening Balance for Knit M/C Needle & Sinker**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| OB Date | date |  |  |
| Category | select |  | Machine Spares (IC-SPR), Needle & Sinker (IC-NDL), Electric General Item (IC-ELC), Maintenance Tools (IC-MNT), Mechanica |
- সেকশন: Opening Balance for Knit M/C Needle & Sinker
- বাটন/অ্যাকশন: Opening Balance List 0, Load, Save, Save & Finalize

#### Machine-Wise Needle Assignment

`/m/knitting/mc-wise-needle-assign`

**ট্যাব: Assign Knit M/C Needle & Sinker**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| Category | select |  | Machine Spares (IC-SPR), Needle & Sinker (IC-NDL), Electric General Item (IC-ELC), Maintenance Tools (IC-MNT), Mechanica |
- সেকশন: Assign Knit M/C Needle & Sinker
- বাটন/অ্যাকশন: Assigned List 0, Assign Knit M/C Needle & Sinker, Load, Save, Save & Finalize

#### Machine-Wise Broken Needle

`/m/knitting/mc-wise-needle-broken`

**ট্যাব: Needle Broken List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Reference No# | text |  | NBR-… |
| Company | select |  | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| Shift | select |  | A (Morning), B (Evening), C (Night) |
| Machine Type | select |  | Single Jersey (SJ), Rib (RIB), Interlock (INTL), Fleece / Terry (FLEECE), Jacquard (JCQ), Flat Bed (Collar/Cuff) (FLAT) |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Confirmed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Needle Broken List 0, Daily Shift and M/C Wise Needle Broken, Search, Add New, Reset

**ট্যাব: Daily Shift and M/C Wise Needle Broken**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing breakage record | select |  |  |
| Reference No# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Shift* | text | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| Machine Type | select |  | Single Jersey (SJ), Rib (RIB), Interlock (INTL), Fleece / Terry (FLEECE), Jacquard (JCQ), Flat Bed (Collar/Cuff) (FLAT) |
| Remarks | textarea |  |  |
- সেকশন: Daily Shift and M/C Wise Needle Broken, Shift, Where, Notes, Machine Wise Broken
- বাটন/অ্যাকশন: Needle Broken List 0, Daily Shift and M/C Wise Needle Broken, Add Machine, Back To List, Clear, Save breakage record

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing breakage record | select |  |  |
| Reference No# | text |  |  |
| Date* | date | ✔ |  |
| Shift* | text | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| Machine Type | select |  | Single Jersey (SJ), Rib (RIB), Interlock (INTL), Fleece / Terry (FLEECE), Jacquard (JCQ), Flat Bed (Collar/Cuff) (FLAT) |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A shift with no machines on it records no breakage. Add a line before saving.

#### Machine-Wise Needle Indent

`/m/knitting/mc-wise-needle-requisition`

**ট্যাব: Requisition List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requition No# | text |  | REQ-MCN-… |
| From Date | date |  |  |
| To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| From Store | select |  | Maintenance Store |
| To Store | select |  | Maintenance Store |
| Reason | select |  | Needle Broken, Servicing, Set Change, New Set, Damaged, Others |
| Status | select |  | Draft, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Requisition List 0, M/C wise Needle Requisition, Search, Add New, Reset

**ট্যাব: M/C wise Needle Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition# | text |  | [Auto Gen] |
| Requisition Date* | date | ✔ |  |
| Reason* | select | ✔ | Select…, Needle Broken, Servicing, Set Change, New Set, Damaged, Others |
| Against Breakage | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| From Store* | select | ✔ | Maintenance Store |
| To Store* | select | ✔ | Maintenance Store |
| Remarks | textarea |  |  |
- সেকশন: M/C wise Needle Requisition, Requisition Info, Raised By, Stores, Item List
- বাটন/অ্যাকশন: Requisition List 0, M/C wise Needle Requisition, Add Item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition# | text |  |  |
| Requisition Date* | date | ✔ |  |
| Reason* | select | ✔ | Select…, Needle Broken, Servicing, Set Change, New Set, Damaged, Others |
| Against Breakage | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Office/Unit | select |  | Brickland Composite Ltd. Head Office (HO) |
| From Store* | select | ✔ | Maintenance Store |
| To Store* | select | ✔ | Maintenance Store |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requisition with no needles on it asks for nothing. Add a line before saving.

#### Machine-Wise Needle Issue

`/m/knitting/mc-wise-needle-issue`

**ট্যাব: Issue List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requition No# | text |  | REQ-MCN-… |
| Issue No# | text |  | ISS-MCN-… |
| From Date | date |  |  |
| To Date | date |  |  |
| From Store | select |  | Maintenance Store |
| To Store | select |  | Maintenance Store |
| Closed | select |  | Yes, No |
| Status | select |  | Draft, Waiting for Verification, Verified, Partly Issued, Issued, Closed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Issue List 0, M/C wise Needle Issue, Search, Add New, Reset

**ট্যাব: M/C wise Needle Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Store | select |  | Maintenance Store |
| Search Requisition List | search |  | Requisition number |
| Existing issue | select |  |  |
| Issue No# | text |  | [Auto Gen] |
| Issue Date* | date | ✔ |  |
| Requisition# | text |  | Pick one from the Requisition List |
| From Store* | select | ✔ | Maintenance Store |
| To Store* | select | ✔ | Maintenance Store |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Requisition#, Date, From Store, To Store, Reason, Owing, Answer This
- সেকশন: Requisition List, M/C wise Needle Issue, Issue Info, Stores, Item List
- বাটন/অ্যাকশন: Issue List 0, M/C wise Needle Issue, Add Item, Back To List, Clear, Save issue

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| From Store | select |  | Maintenance Store |
| Search Requisition List | search |  |  |
| Existing issue | select |  |  |
| Issue No# | text |  |  |
| Issue Date* | date | ✔ |  |
| Requisition# | text |  |  |
| From Store* | select | ✔ | Maintenance Store |
| To Store* | select | ✔ | Maintenance Store |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Requisition is required.

#### Needle Stock Closing

`/m/knitting/mc-needle-closing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| Closing Date | date |  |  |
| Search Every needle store's book — who last closed it, the month it closed at, a | search |  | Search 1 records… |
| Filter by Company | search |  |  |
| Filter by Store | search |  |  |
| Filter by Last Closed By | search |  |  |
| Filter by Last Closing Date | search |  |  |
| Filter by Next Closing Date | search |  |  |
| Filter by Lines Struck | search |  |  |
| Filter by Closed At | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Company, Store, Last Closed By, Last Closing Date, Next Closing Date, Lines Struck, Closed At, Status, ACTION
- সেকশন: M/C Knit Needle Closing Data
- বাটন/অ্যাকশন: Search, Start Closing, Show Exception, Reset, Export, Company, Store, Last Closed By, Last Closing Date, Next Closing Date, Lines Struck, Closed At, Status

#### Needle Receipt Return Register

`/m/knitting/mc-needle-receive-return-list`

**ট্যাব: M/C Needle Receive Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  | Return challan |
| MRR No | text |  | MRR-ND-… |
| Return Chalan Type | select |  | Returnable, Non-Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
| Is Finalized | select |  | Yes, No |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Needle Receive Return List 0, M/C Needle Receive Return, -- Select Supplier --, Search, Add New, Reset

**ট্যাব: M/C Needle Receive Return**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  | Return challan |
| MRR No | text |  | MRR-ND-… |
| Return Chalan Type | select |  | Returnable, Non-Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Maintenance Store |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
| Is Finalized | select |  | Yes, No |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: M/C Needle Receive Return List 0, M/C Needle Receive Return, -- Select Supplier --, Search, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Receive List | search |  |  |
| Existing return challan | select |  |  |
| MRR No | text |  |  |
| Rtn Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Return Chalan Type* | select | ✔ | Select…, Returnable, Non-Returnable |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Maintenance Store |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Reason | textarea |  |  |
- খালি Save-এ মেসেজ: Receipt is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **General Store-এর মাস্টার:** item, category, store আর unit (`gs_items`, `gs_categories`, `gs_stores`, `gs_units`)। Brickland-এর needle store-ও general store-এর item master থেকে পড়ে (Category "Needle & Sinker (IC-NDL)", Store "Maintenance Store")। আলাদা মাস্টার বানাইনি।
- **Supplier/Party:** `mp_suppliers` (MaterialSupplier)। Brickland-এ Supplier আর Party-র তালিকা প্রোগ্রামের supplier তালিকার সাথে একই।
- **Office/Unit:** `factories`।
- **মেশিন আর তার ইউনিট:** `knit_machines`। কোন ইউনিটের মেশিন সেটা floor → building → factory ধরে বের হয় (`NeedleStore::machineOffices()`)।
- **Machine Type:** `knit_ref_entries`-এর MC_TYPE তালিকা।
- **Shift:** `shifts`। **Department:** `departments`।
- **Requisition Type:** `YarnStore::REQ_TYPES` আর `knitting.ystore.req_type.*` লেবেল।
- **UoM:** `UomCodes`, মাস্টার থেকে।
- **ফ্রন্টএন্ড:** prod-cc.jsx-এর shared অংশ export করা হয়েছে, যেমন `useDoc` (এখন নিজস্ব translator নেয়), `GridCard`, `PickPanel`, `LineSelect`, `docCol`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_19_000100_create_knitting_needle_store.php`।

**Ledger আর ক্লোজিং**
- `knit_needle_moves`: store, item, move_date, kind, doc_no, doc_line_id, qty (চিহ্নসহ), value।
  - kind: OPENING / RECEIPT / RETURN / ISSUE_OUT / ISSUE_IN / LOAN_OUT।
  - একটা store আর item-এর balance = তার moves-এর যোগফল।
- `knit_needle_closings`: store, period (YYYY-MM), lines_struck, closed_by, closed_at।

**Opening**
- `knit_needle_openings`: store, ob_date, item, qty, rate, finalized। প্রতি store + তারিখ + item-এ একটা সারি।
- `knit_needle_loan_openings`: ob_date, party, item, uom, loan_received, loan_issued, store, cost_price, is_final, remarks। প্রতি party + item-এ একটাই নিট পজিশন।

**কাগজপত্র (হেডার + লাইন)**
- `knit_needle_loan_reqs` + লাইন (NLR-…)
  - হেডার: kind (LOAN_ISSUE / LOAN_RETURN), req_type, office, department, party, store, reason।
  - লাইন: item, uom, required_qty, issue_qty, rate।
  - status: DRAFT / WAITING / VERIFIED / PARTLY_ISSUED / ISSUED / CLOSED / CANCELLED।
- `knit_needle_receipts` + লাইন (NMR-…)
  - হেডার: store, req_type, supplier, challan_no/date, source, pay_method (ADJUSTMENT / CASH / CREDIT / LC), lc_no।
  - লাইন: challan_qty, received_qty, rate, amount।
  - status: DRAFT / WAITING / VERIFIED / WAIT_ACCOUNTS / APPROVED / CANCELLED।
- `knit_needle_returns` + লাইন (NMR-…-R01)
  - হেডার: receipt, challan_type (RETURNABLE / NON_RETURNABLE), store, supplier, gate pass, vehicle, reason।
  - লাইন: receipt_line, qty, rate, amount।
  - status: DRAFT / ISSUED / FINALIZED / CANCELLED।
- `knit_needle_assignments`: office, category, machine, item, qty, finalized। প্রতি মেশিন + category-তে একটা।
- `knit_needle_brokens` + লাইন (NBR-…)
  - হেডার: date, shift, office, machine type।
  - লাইন: machine, item, qty, cost_price, amount।
  - status: DRAFT / CONFIRMED / CANCELLED।
- `knit_needle_reqs` + লাইন (NRQ-…)
  - হেডার: reason (NEEDLE_BROKEN / SERVICING / SET_CHANGE / NEW_SET / DAMAGED / OTHERS), broken, office, from_store (অনুরোধকারী), to_store (যে ছাড়বে)।
  - লাইন: machine, item, req_qty, rate।
- `knit_needle_issues` + লাইন (NIS-…)
  - হেডার: req, from/to store (indent থেকে আসে)।
  - লাইন: req_line, qty।
  - status: ISSUED / WITHDRAWN।

## ৫. Workflow / Status ও Business Rules

**Needle Opening Balance**
- Store, তারিখ আর category দিয়ে Load করলে আগে লেখা পরিমাণসহ item-গুলো আসে।
- শূন্য লাইন সেভ হয় না।
- Save & Finalize চাপলে OPENING হিসেবে বইয়ে ওঠে আর শিট লক হয়।

**Needle Loan Opening Balance**
- Loan Received বা Loan Issued, অন্তত একটা দিতে হয়।
- একই party আর item-এ দ্বিতীয় পজিশন আটকায়।
- Is Final দিলে আর এডিট করা যায় না।
- stock ledger-এ কিছু post হয় না।

**Machine Needle Receipt**
- লাইন বাধ্যতামূলক। LC # শুধু L/C পেমেন্টে দেওয়া যায়।
- প্রবাহ:
  - Draft → Submit → Waiting for Verification।
  - **Verify**: store গুনে নিল, RECEIPT post হয়।
  - Send to Accounts → Wait for Accounts Approval → Accounts Approve → Approved।
- Cancel শুধু Verify-এর আগে।
- Verify হওয়া রিসিপ্টের বাকি থাকলে তালিকায় **Raise Return** দেখায়।

**Item Wise Consumptions**
- store আর দুই তারিখ ধরে, item অনুযায়ী দেখায়: Opening, Received, Returned, Issued In, Issued Out, Loan Out, Closing।

**Needle Receipt Return**
- বাঁ দিকে Receive List থেকে "Include Challan" চাপলে বাকি লাইনগুলো আসে।
- রিসিপ্টের বাকির বেশি ফেরত আটকায়।
- Rtn Challan# ফাঁকা রাখলে নম্বর হয় `NMR-…-R01`।
- **Issue at Gate**: স্টক থাকলে RETURN post হয়।
- তারপর Finalize। Cancel করলে (Issued অবস্থায়) post উঠে যায়।

**Machine-Wise Needle Assignment**
- Unit আর category দিয়ে Load করলে unit-এর সব মেশিন আসে।
- item খালি বা qty শূন্য রাখলে সেই মেশিনের ফিট সরে যায়।
- category-র বাইরের item আটকায়। Save & Finalize চাপলে লক হয়।

**Machine-Wise Broken Needle**
- মেশিন বাধ্যতামূলক। unit দিলে সেই unit-এর মেশিনই নিতে হবে।
- Draft → Confirm। Cancel আটকায় যদি কোনো রিকুইজিশন এর বিপরীতে তোলা থাকে।

**Machine-Wise Needle Indent**
- Against Breakage-এ শুধু Confirmed রেকর্ড নেওয়া যায়। grid খালি থাকলে রেকর্ডটা বাছলে তার মেশিন আর নিডল লাইনে চলে আসে।
- প্রবাহ: Submit → Verify, অথবা সরাসরি Submit & Verify। তারপর issue চলতে থাকলে Partly Issued, শেষে Issued।
- Close: Verified বা Partly Issued অবস্থায়।
- Cancel: কিছু ইস্যু না হলে।

**Machine-Wise Needle Issue**
- বাঁ দিকে যাচাই হওয়া indent-এর তালিকা, যাতে বাকি আছে। "Answer This" চাপলে বাকি লাইনগুলো আসে।
- indent-এর বাকি বা ছাড়ার store-এর স্টকের বেশি নেওয়া আটকায়।
- সেভ করলেই post হয়:
  - to_store (যে ছাড়ে) থেকে ISSUE_OUT।
  - from_store আলাদা হলে সেখানে ISSUE_IN।
  - indent-এর অবস্থাও একই transaction-এ বদলায়।
- Withdraw দুটো post-ই তুলে দেয়, indent-এর অবস্থা আবার হিসাব হয়।

**Needle Loan Indent**
- প্রবাহ: Draft → Submit / Submit & Verify।
- যাচাইয়ের পর এডিট ডেস্কে শুধু Issue Qty বদলানো যায়।
  - সেভ করলে store থেকে LOAN_OUT post হয়।
  - চাহিদা বা স্টকের বেশি ইস্যু আটকায়।
  - অবস্থা: Partly Issued, তারপর Issued।
- Req Type (Loan Issue / Return to Party) সেভের পর আর বদলানো যায় না।

**Needle Stock Closing**
- প্রতি store-এর তালিকা: শেষ যে বন্ধ করেছে, শেষ মাস, পরের মাস, কত লাইন লক হয়েছে, অবস্থা।
- Show Exception দেখায় অসমাপ্ত কাগজ:
  - Draft বা Waiting রিসিপ্ট / indent।
  - Draft রিটার্ন।
  - চূড়ান্ত না হওয়া ওপেনিং শিট।
- Start Closing মাস লক করে। অসমাপ্ত কাগজ থাকলে সেই store বাদ যায়।
- বন্ধ মাসে আর কোনো post হয় না (`NeedleStore::assertOpen`)।

## ৬. API ও পারমিশন

সব route `/api/v1/knitting/needle/...`-এর নিচে, মোট ৪৬টা।

**Store-এর দিক (KnitNeedleStoreApiController)**
- `store/options`
- `openings`, `openings/sheet` (GET / PUT)
- `loan-openings` (index / store / update)
- `receipts`, `receipts/{no}`, `receipts/{no}/source`, `receipts/{no}/{submit|verify|accounts|approve|cancel}`
- `consumption`
- `returns`, `returns/{no}`, `returns/{no}/{issue|finalize|cancel}`
- `closings`, `closings/exceptions`, POST `closings`

**ফ্লোরের দিক (KnitNeedleFloorApiController)**
- `floor/options`
- `assignments`, `assignments/sheet` (GET / PUT)
- `brokens`, `brokens/{no}`, `brokens/{no}/{confirm|cancel}`
- `reqs`, `reqs/{no}`, `reqs/{no}/{submit|verify|submit-verify|close|cancel}`
- `issues`, `issues/owing`, `issues/{no}`, `issues/{no}/withdraw`
- `loan-reqs`, `loan-reqs/{no}`, `loan-reqs/{no}/{...}`

**পারমিশন**
- নতুন: `knitting.needle.view` আর `knitting.needle.manage`। PermissionSeeder-এ যোগ হয়েছে।
- RoleSeeder-এ FACTORY_ADMIN আর FACTORY_MANAGER এই দুটো পায়।

**মেনু**
- Knit Production-এর নিচে নতুন ফোল্ডার `KNITTING.NEEDLE_STORE` (Needle Store)।
- তাতে ১০টা স্ক্রিন: ND_LOAN_OPENING, ND_LOAN_REQ, ND_RECEIPT, ND_OPENING, ND_ASSIGN, ND_BROKEN, ND_REQ, ND_ISSUE, ND_CLOSING, ND_RETURN।

## ৭. Frontend পেজ (লাইট থিম)

- কম্পোনেন্ট: `frontend/src/components/knitting/needle.jsx`।
- স্ক্রিন রেজিস্ট্রি: `needle-screens.js`।
- page.js-এ যোগ হয়েছে `nd` translator আর `can.needle`। actions.js-এ `needleAction`।
- **স্ক্রিন অনুযায়ী ট্যাব:**
  - Loan Opening: Details Data / Master Data।
  - Loan Indent: Requisition List / M/C Needle Loan Requisition।
  - Receipt: List / Receive / Item Wise Consumptions।
  - Opening: Opening Balance List / Opening Balance (Load-এর শিট)।
  - Assignment: Assigned List / Assign (Load-এর শিট)।
  - Broken: List / Daily Shift and M/C Wise।
  - Machine-wise Indent: List / Requisition।
  - Issue: Issue List / M/C wise Needle Issue (বাঁ দিকে Requisition List)।
  - Closing: এক পাতা, সাথে Show Exception আর Start Closing।
  - Return: List / Receive Return (বাঁ দিকে Receive List)।
- **URL:** সবসময় পড়ার মতো, কোথাও ULID নেই।
  - কাগজ খোলে নম্বর দিয়ে।
  - ওপেনিং শিট খোলে store code আর তারিখ দিয়ে (`?tab=sheet&store=MAINT&date=…`)।
  - লোন পজিশন খোলে party code আর item code দিয়ে।
- **প্রিন্ট:** রিসিপ্ট (MRR), রিটার্ন চালান, লোন indent, মেশিনভিত্তিক indent, ইস্যু, ভাঙার রেকর্ড।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১০টার ১০টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_19_000100_create_knitting_needle_store.php`
- `app/Modules/Knitting/Models/KnitNeedleMove.php`, `KnitNeedleClosing.php`, `KnitNeedleOpening.php`, `KnitNeedleLoanOpening.php`, `KnitNeedleLoanReq.php`, `KnitNeedleLoanReqLine.php`, `KnitNeedleReceipt.php`, `KnitNeedleReceiptLine.php`, `KnitNeedleReturn.php`, `KnitNeedleReturnLine.php`, `KnitNeedleAssignment.php`, `KnitNeedleBroken.php`, `KnitNeedleBrokenLine.php`, `KnitNeedleReq.php`, `KnitNeedleReqLine.php`, `KnitNeedleIssue.php`, `KnitNeedleIssueLine.php`
- `app/Modules/Knitting/Support/NeedleStore.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleStoreApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitNeedleFloorApiController.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/components/knitting/needle.jsx`
- `frontend/src/components/knitting/prod-cc.jsx` (shared অংশ export, `useDoc`-এ `c`)
- `frontend/src/app/(app)/knit-production/[screen]/needle-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js` (`needleAction`)

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php`: `nd.*` গ্রুপ, আর `prod.screens` / `prod.descriptions`-এ ১০টা স্ক্রিন

**Test**
- `tests/Feature/Knitting/KnittingNeedleStoreTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingNeedleStoreTest`, ২টা টেস্ট, ১২৪টা assertion, test2 DB-তে পাস।
- **Opening, receipt, return আর closing:**
  - ওপেনিং শিটে শূন্য লাইন সেভ হয় না। Finalize করলে post হয় (৫০০), তারপর লক।
  - রিসিপ্ট:
    - লাইন ছাড়া আর LC ছাড়া LC # আটকায়।
    - Draft অবস্থায় Verify আটকায়।
    - Submit → Verify-তে post হয় (১৪৮০)। তারপর Accounts → Approve।
  - রিটার্ন:
    - বাকির বেশি আটকায়।
    - নম্বর হয় `…-R01`।
    - Issue at Gate-এ ১৪৫০ হয়।
  - Consumption বোর্ডের হিসাব মেলে।
  - Closing:
    - Draft রিসিপ্ট ব্যতিক্রম হিসেবে আসে, ক্লোজ আটকায়।
    - Cancel করার পর ক্লোজ হয় (৩টা লাইন লক)।
    - পরের মাস ঠিক দেখায়। বন্ধ মাসে Verify আটকায়।
- **Breakage, indent, issue আর loan:**
  - Assignment: Finalize-এর পর আর বদলানো যায় না।
  - Broken: লাইন ছাড়া আটকায়। Confirm ছাড়া indent-এ বাছা আটকায়।
  - Indent: Submit & Verify।
  - Issue:
    - স্টকের বেশি আর বাকির বেশি আটকায়।
    - ৩০ ইস্যু: Maintenance ১০, Floor ৩০, indent Partly Issued।
    - indent Cancel আটকায়।
    - Withdraw করলে সব আগের মতো ফেরে।
  - Loan:
    - kind বদলানো আটকায়। Verify-এর পর চাহিদার বেশি Issue Qty আটকায়।
    - ১৫ ইস্যুতে Partly, ২৫-এ Issued। ledger মেলে।
  - Loan opening: দুই দিকই শূন্য হলে আর একই party/item-এ দ্বিতীয়টা আটকায়। নিট ৬০।

**MenuAccessTest:** ৬টা টেস্ট পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/nd01`–`nd03`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| General Store মাস্টার | category "Needle & Sinker (NDL)"; item ND-VO-7152, ND-VO-7441, SK-SJ-24; store "Knitting Floor Store (KNT)" | — |
| ওপেনিং | Maintenance Store, আজকের তারিখ: ৬০০ / ৩০০ / ৪০০ | Finalized |
| NMR-2026110001 | Coats Bangladesh, ৯৮০ নিডল + ৫০০ সিঙ্কার, ১২,৪৩৫ টাকা | Wait for Accounts Approval |
| NMR-2026110001-R01 | ২০টা নিডল ফেরত | Issued at gate |
| Assignment | Dhaka Unit 1: KM-01 ২৮৮০, KM-02 ২৬৪০ | Not finalized |
| NBR-2026110001 | Day shift: KM-01-এ ১২টা নিডল, KM-02-এ ৮টা সিঙ্কার | Confirmed |
| NRQ-2026110001 | Knitting Floor ← Maintenance, ২০টা | Partly Issued |
| NIS-2026110001 | ১৮টা ইস্যু | Issued |
| NLR-2026110001 | Square Textiles, ৫০টা সিঙ্কার লোন, ৩০টা ইস্যু | Partly Issued |
| লোন পজিশন | Coats, ND-VO-7441: রিসিভড ১০০, ইস্যুড ২০ | Open |

dev মাস বন্ধ করা হয়নি, শুধু Show Exception দেখা হয়েছে।

**প্রিন্ট:** রিসিপ্ট, রিটার্ন, ভাঙা, indent, ইস্যু আর লোন, সবগুলোর preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **Item মাস্টার:** General Store-এর item, category আর store-ই ব্যবহার করেছি। needle store-এর আলাদা মাস্টার নেই। Brickland-এর demo-তে needle item ছিল না, তাই dev-এ নমুনা item যোগ করেছি।

২. **PR / PO বাদ:** রিসিপ্ট ফর্মে PR আর PO রাখিনি। Brickland-এ তালিকা খালি ছিল, আর needle কেনার কোনো সোর্স মডিউল এখনো নেই।

৩. **Issue-র স্টোরের দিক:** Brickland-এর ব্যাখ্যা অনুযায়ী From Store হলো অনুরোধকারী (সাব-স্টোর), To Store হলো যে ছাড়ে (মেইন)। তাই ইস্যু To Store থেকে বিয়োগ হয়, From Store-এ যোগ হয়। দুটো একই হলে শুধু বিয়োগ হয়।

৪. **Issue List-এ কী দেখায়:** এখানে ইস্যুর কাগজগুলো দেখায় (প্রতিটার Withdraw আছে)। Brickland-এ তালিকা খালি ছিল, আর সেখানকার ব্যাখ্যায় যাচাই হওয়া রিকুইজিশন আর "Mark as Closed"-এর কথা আছে। রিকুইজিশন বন্ধ করা হয় indent তালিকা থেকে।

৫. **Loan opening স্টকে যায় না:** এটা শুধু রেজিস্টার। স্টকের ওপেনিং আলাদা স্ক্রিনে (Needle Opening Balance)। লোন ফেরত বা লোন রিসিভ 15/08 আর 15/09-এ আসবে।

৬. **Loan Issue Qty-র post তারিখ:** সেভ করার দিনের তারিখে post হয়। পুরো লাইনের post প্রতিবার নতুন করে লেখা হয়।

৭. **Receipt-এর ধাপ:** অবস্থাগুলো Brickland-এর ফিল্টার থেকে নেওয়া। তালিকার বোতাম (Submit, Verify, Send to Accounts, Accounts Approve, Cancel, Raise Return, Print) অনুমান করে রাখা, কারণ Brickland-এ তালিকা খালি ছিল।

৮. **Return নম্বর:** Brickland `MRR-ND-…/R01` লেখে। এখানে `NMR-…-R01`, কারণ নম্বর URL-এ যায় আর `/` সেখানে চলে না।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/nd01`, `nd02`):** দশটা স্ক্রিনের ভেতরের গ্রিড, ব্যাখ্যা আর ক্লোজিং তালিকা পড়া হয়েছে। লিখে দেখার চেষ্টা কাজ করেনি, কারণ demo-তে needle item নেই আর office-এর মেশিন তালিকা খালি।
- **Turbopack cache:** frontend আবার তোলার আগে cache volume মুছতে হয়েছে।
  - সঠিক volume `machinery-maintenance_frontend-next-cache`।
  - `annotech-rmg_…` অন্য প্রজেক্টের, সেটায় হাত দেওয়া যাবে না।
- **Consumption বোর্ড:** বিয়োগের কলামে "-0.000" দেখাচ্ছিল। ঠিক করা হয়েছে।
- **15/08 থেকে:** ledger, ক্লোজিং আর loan indent-এ `book` কলাম (NEEDLE / OIL) যোগ হয়েছে। এই স্ক্রিনগুলো NEEDLE খাতায় চলে।
