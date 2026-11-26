# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/06`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Stock Report | `/m/general-store/gs-rpt-stock` | 1 | 3 | 0 |
| 2 | Receive Report | `/m/general-store/gs-rpt-receive` | 1 | 7 | 0 |
| 3 | Issue Report | `/m/general-store/gs-rpt-issue` | 1 | 6 | 0 |
| 4 | Return Report | `/m/general-store/gs-rpt-return` | 1 | 6 | 0 |
| 5 | Adjustment Report | `/m/general-store/gs-rpt-adjustment` | 1 | 6 | 0 |
| 6 | Transfer Report | `/m/general-store/gs-rpt-transfer` | 1 | 6 | 0 |
| 7 | Department-wise Report | `/m/general-store/gs-rpt-department` | 1 | 4 | 0 |
| 8 | Item-wise Consumption | `/m/general-store/gs-rpt-consumption` | 1 | 4 | 0 |
| 9 | Category-wise Stock | `/m/general-store/gs-rpt-category` | 1 | 1 | 0 |
| 10 | Stock Valuation | `/m/general-store/gs-rpt-valuation` | 1 | 2 | 0 |
| 11 | Low Stock Report | `/m/general-store/gs-rpt-low-stock` | 1 | 2 | 0 |
| 12 | Monthly Report | `/m/general-store/gs-rpt-monthly` | 1 | 4 | 0 |
| 13 | Transaction History | `/m/general-store/gs-rpt-history` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Stock Report

`/m/general-store/gs-rpt-stock`

**ভিউ**
> 20 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Stock Status | select |  | Any status, In Stock, Low, Out of Stock |
- গ্রিড কলাম: Item Code, Item Name, Category, Unit, Stock Qty, Price (৳), Stock Value (৳), Min. Stock, Rack, Status
- সেকশন: Current Stock Report (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Receive Report

`/m/general-store/gs-rpt-receive`

**ভিউ**
> 21 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Supplier | select |  | All suppliers |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: Date, Doc No., Store, Supplier, Item Code, Item Name, Category, Unit, Qty, Price, Value, Challan / Ref., User
- সেকশন: Receive Report (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Issue Report

`/m/general-store/gs-rpt-issue`

**ভিউ**
> 22 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: Date, Doc No., Store, Department, Item Code, Item Name, Category, Unit, Qty, Price, Value, Receiver / Req., User
- সেকশন: Issue Report (0)
- বাটন/অ্যাকশন: All categories, All departments, Search, Reset, Excel, Print / PDF

#### Return Report

`/m/general-store/gs-rpt-return`

**ভিউ**
> 23 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: Date, Doc No., Store, Department, Item Code, Item Name, Category, Unit, Qty, Price, Value, Against Issue, User
- সেকশন: Return Report (0)
- বাটন/অ্যাকশন: All categories, All departments, Search, Reset, Excel, Print / PDF

#### Adjustment Report

`/m/general-store/gs-rpt-adjustment`

**ভিউ**
> 24 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: Date, Doc No., Store, Adjustment Type, Item Code, Item Name, Category, Unit, Qty (+/−), Price, Value, Reference, User
- সেকশন: Adjustment Report (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Transfer Report

`/m/general-store/gs-rpt-transfer`

**ভিউ**
> 25 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: Date, Doc No., From Store, To Store, Item Code, Item Name, Category, Unit, Qty, Price, Value, Reference, User
- সেকশন: Transfer Report (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Department-wise Report

`/m/general-store/gs-rpt-department`

**ভিউ**
> 26 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
- গ্রিড কলাম: Department, Issued Qty, Returned Qty, Net Consumption, Value (৳)
- সেকশন: Department-wise Consumption (0)
- বাটন/অ্যাকশন: All categories, All departments, Search, Reset, Excel, Print / PDF

#### Item-wise Consumption

`/m/general-store/gs-rpt-consumption`

**ভিউ**
> 27 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
- গ্রিড কলাম: Item Code, Item Name, Category, Unit, Issued, Returned, Net Consumption, Value (৳)
- সেকশন: Item-wise Consumption (0)
- বাটন/অ্যাকশন: All categories, All departments, Search, Reset, Excel, Print / PDF

#### Category-wise Stock

`/m/general-store/gs-rpt-category`

**ভিউ**
> 28 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
- গ্রিড কলাম: Category, Items, Stock Qty, Priced Items, Stock Value (৳), Low, Out of Stock
- সেকশন: Category-wise Stock (0)
- বাটন/অ্যাকশন: Search, Reset, Excel, Print / PDF

#### Stock Valuation

`/m/general-store/gs-rpt-valuation`

**ভিউ**
> 29 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
- গ্রিড কলাম: Item Code, Item Name, Category, Unit, Stock Qty, Price (৳), Stock Value (৳)
- সেকশন: Stock Valuation (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Low Stock Report

`/m/general-store/gs-rpt-low-stock`

**ভিউ**
> 30 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
- গ্রিড কলাম: Item Code, Item Name, Category, Unit, Current Stock, Min. Stock, Reorder Level, Shortage, Status
- সেকশন: Low Stock Report (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Monthly Report

`/m/general-store/gs-rpt-monthly`

**ভিউ**
> 31 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
- গ্রিড কলাম: Month, Receive, Issue, Return, Adj. In, Adj. Out, Consumption
- সেকশন: Monthly Receive / Issue / Consumption (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Transaction History

`/m/general-store/gs-rpt-history`

**ভিউ**
> 32 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Transaction Type | select |  | All types, Opening, Receive, Issue, Return, Adjustment, Transfer |
- গ্রিড কলাম: Date, Doc No., Type, Store, Item Code, Item Name, Unit, In, Out, Store Balance, User
- সেকশন: Transaction History (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 10/03-এর `gs_documents`, `gs_document_lines` আর `gs_stock_movements`; `GsBook` (স্টক, শেষ ক্রয়মূল্য, ভাউচারের নাম, audit)।
- PDF-এর জন্য শেয়ার করা `App\Shared\Pdf\TablePdf` (mPDF, বাংলাসহ); Excel-এর জন্য OpenSpout।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই। সব report ডকুমেন্টের লাইন বা stock movement থেকে পড়া হয়।

## ৫. Workflow / Status ও Business Rules

- **সব report-এ:** নিজের filter (Brickland-এর ক্রমে), তারপর কার্ডে "শিরোনাম (n)", নিচে তারিখের নোট ("All dates", "2025-10-01 onward", "As of …"), Excel আর Print / PDF; টেবিল আর মোটের সারি (Transaction History ছাড়া)।
- **বাতিল ডকুমেন্ট:** "Any status"-এ আসে না, শুধু "Cancelled" বাছলে আসে (Brickland-এর আচরণ)। movement-ভিত্তিক হিসাবে (Stock, Monthly, History) বাতিল তার reversal দিয়ে কাটা পড়ে।
- **মূল্য:** পরিমাণ × দাম, যেখানে দাম জানা; Stock আর Valuation-এ শেষ ক্রয়মূল্য, লাইন report-এ লাইনের নিজের দাম।
- **১৩টা report:**
  1. **Stock Report** ("Item-wise Stock Report"): Opening, Received, Returned, Transfer In, Issued, Transfer Out, Adj. (±), Current Qty, Unit Cost, Current Stock Value, Status। Store বাছলে সেই স্টোরের হিসাব (Transfer-এর দুই দিক আলাদা)।
  2. **Receive Report:** লাইনভিত্তিক; Challan / Ref.; Supplier filter।
  3. **Issue Report:** Department, Unit Cost, Total Issue Value, Receiver / Req. (গ্রহীতা আর requisition নং), Issued By, Status; Department filter।
  4. **Return Report:** Department আর Against Issue।
  5. **Adjustment Report:** Adjustment Type আর Qty (+/−)।
  6. **Transfer Report:** From Store, To Store।
  7. **Department-wise Report:** প্রতিটা ডিপার্টমেন্টের Issued, Returned, Net Consumption আর Value (ইস্যুর মূল্য − ফেরতের মূল্য)।
  8. **Item-wise Consumption:** একই হিসাব আইটেম ধরে।
  9. **Category-wise Stock:** Items, Stock Qty, Priced Items, Stock Value, Low, Out of Stock; বেশি স্টক আগে।
  10. **Stock Valuation:** স্টক থাকা আইটেমের Qty × Price।
  11. **Low Stock Report:** Low আর Out of Stock আইটেম; Shortage = ন্যূনতম − বর্তমান (শূন্যের নিচে নয়)।
  12. **Monthly Report:** মাস ধরে Receive (Opening সহ), Issue, Return, Adj. In, Adj. Out, Consumption (= Issue − Return); Transfer বাদ; তারিখ না দিলে এক বছর আগের মাসের শুরু থেকে।
  13. **Transaction History:** প্রতিটা movement, নতুনটা আগে, সেই স্টোরে ওই আইটেমের পরের ব্যালান্সসহ; বাতিলের সারি কেটে দেখায়; Transaction Type filter।
- **Excel আর PDF:** একই সারি আর মোট। PDF landscape A4, ওপরে ভাউচারের নাম আর তারিখের নোট, নতুন ট্যাবে খোলে (সেখান থেকে প্রিন্ট বা সেভ)। দুটোই audit-এ REPORT_EXPORT লেখে।

## ৬. API ও পারমিশন

| Method | Path |
|---|---|
| GET | `/general-store/reports/options`, `/reports/{report}`, `/reports/{report}/excel`, `/reports/{report}/pdf` |

- report = stock, receive, issue, return, adjustment, transfer, department, consumption, category, valuation, low-stock, monthly, history।
- পারমিশন `store.inventory.view`।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: General Store → REPORTS (১৩টা স্ক্রিন, `/general-store/gs-rpt-…`)। General Store এখন Brickland-এর মতোই ৩৪টা স্ক্রিন ("22 of 34")।
- একটা component (`GsReport`), প্রতিটা report-এর নিজের filter-এর তালিকা; filter পাতার ভেতরেই কাজ করে। Category বাছলে Item-এর তালিকা ছোট হয়।
- Excel আর PDF route handler `/api/general-store/excel/rpt-<report>` দিয়ে (`?as=pdf`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Http/Controllers/Api/GsReportApiController.php`
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (GENERAL_STORE.REPORTS আর ১৩টা স্ক্রিন)

**Frontend**
- `frontend/src/components/generalstore/report.jsx`
- `frontend/src/app/(app)/general-store/[screen]/{page,screens,actions}.js`
- `frontend/src/app/api/general-store/excel/[kind]/route.js` (`rpt-*`, `?as=pdf`)
- `lang/en/store.php`, `lang/bn/store.php` (`rpt.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreReportTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- **`GeneralStoreReportTest`:** Opening, দুটো Receive (একটা বাতিল), দুটো Issue, Return, Damage আর Transfer দিয়ে ১৩টা report:
  - Stock-এর প্রতিটা কলাম (10 / 20 / 3 / 4 / 10 / 4 / −1 / 22) আর Main Store-এর আলাদা হিসাব।
  - Receive-এ শুধু চালু লাইন, Cancelled বাছলে বাতিলটা, খোঁজা; Issue-এ ডিপার্টমেন্ট আর গ্রহীতা; Return, Adjustment, Transfer।
  - Department আর Item-wise খরচ (8 − 3 = 5, মূল্য 2,100); Category, Valuation, Low Stock (ঘাটতি 20)।
  - Monthly (60 / 10 / 3 / 0 / 1 / 7); History-র ক্রম, ব্যালান্স, reversal আর ধরন filter।
  - Excel, PDF (%PDF), অচেনা report 404।
- পুরো General Store-এর ৯টা টেস্ট সবুজ (৩০৫টা assertion)।
- **ব্রাউজার (dev):** Stock (১৬টা আইটেম), Receive (150 / 26,800), Issue (29 / 11,432; Maintenance filter), Department (Maintenance 29 − 2 = 27, 10,552), Category, Monthly (160 / 29 / 2 / 0 / 2 / 27), History (১২টা সারি, Transfer reversal কেটে দেখায়); Issue-এর Excel আর Stock-এর PDF ডাউনলোড করে দেখা হয়েছে।
- **সংশোধন:** শূন্য Issued / Transfer Out "-0" দেখাচ্ছিল; তারিখ আর ডক নং দুই লাইনে ভাঙছিল।

## ১০. খোলা প্রশ্ন

- Brickland-এর Print / PDF browser-এর প্রিন্ট না সার্ভারের PDF, বোঝা যায়নি। এখানে সার্ভারের PDF (TablePdf), নতুন ট্যাবে খোলে।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs13.json`, `gs14.json` (শুধু পড়া)।
- **ধাপ:** General Store-এর ধাপ ৩, শেষ ধাপ। General Store মডিউল সম্পূর্ণ।
