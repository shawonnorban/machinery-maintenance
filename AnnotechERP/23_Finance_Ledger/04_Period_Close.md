# Period Close

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/04`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Stock Value Closing | `/m/accounting/stock-closing` | 3 | 10 | 1 |
| 2 | Bank Statement Reconciliation | `/m/accounting/bank-reconciliation` | 3 | 12 | 1 |
| 3 | Fiscal Year Closing | `/m/accounting/fiscal-year-close` | 2 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Stock Value Closing

`/m/accounting/stock-closing`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Year | select |  |  |
- সেকশন: Stock Closing
- বাটন/অ্যাকশন: Stock Closing, New stock closing, -- Month --, Search, Refresh

**ট্যাব: Stock Closing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Year | select |  | 2027, 2026 |
- সেকশন: Stock Closing
- বাটন/অ্যাকশন: Stock Closing, New stock closing, -- Month --, Show, Save Closing

**ট্যাব: New stock closing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Year* | number | ✔ |  |
| Month* | number | ✔ |  |
| Code* | text | ✔ |  |
| Particular* | text | ✔ |  |
| Closing* | number | ✔ |  |
- সেকশন: New stock closing
- বাটন/অ্যাকশন: Stock Closing, New stock closing, Reset, Save stock closing

**ডায়ালগ: New stock closing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Year* | number | ✔ |  |
| Month* | number | ✔ |  |
| Code* | text | ✔ |  |
| Particular* | text | ✔ |  |
| Closing* | number | ✔ |  |
- খালি Save-এ মেসেজ: Closing value is required.

#### Bank Statement Reconciliation

`/m/accounting/bank-reconciliation`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| From | date |  |  |
| To | date |  |  |
| Bank | select |  |  |
| Status | select |  | Cleared, Not in bank |
- সেকশন: Search
- বাটন/অ্যাকশন: Bank Reconcilations, New bank line, Show, Refresh

**ট্যাব: Bank Reconcilations**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search accounts | search |  | Search… |
- সেকশন: Bank Account, Voucher Lines
- বাটন/অ্যাকশন: Bank Reconcilations, New bank line

**ট্যাব: New bank line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Voucher Line* | select | ✔ | Select… |
| Bank Account* | select | ✔ | Select… |
| Cheque No | text |  |  |
| Bank Date | date |  |  |
| Cleared | checkbox |  |  |
| Remarks | text |  |  |
- সেকশন: New bank line
- বাটন/অ্যাকশন: Bank Reconcilations, New bank line, Reset, Save bank line

**ডায়ালগ: New bank line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Voucher Line* | select | ✔ | Select… |
| Bank Account* | select | ✔ | Select… |
| Cheque No | text |  |  |
| Bank Date | date |  |  |
| Cleared | checkbox |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Voucher Line is required.

#### Fiscal Year Closing

`/m/accounting/fiscal-year-close`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Closing Process

**ট্যাব: Closing Process**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Period* | select | ✔ | Select…, Brickland Composite Ltd. — FY2026-27 (01 Jul 2026 to 30 Jun 2027), Brickland Composite Ltd. — FY2027-28 (01 Jul |
| Remarks | textarea |  |  |
- সেকশন: New fiscal year closing
- বাটন/অ্যাকশন: Closing Process, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/01:** Chart of Accounts, Account Type Configuration (INVENTORY / BANK শ্রেণি), Bank Account Master (`acc_bank_accounts`, linked ledger), Fiscal Year Setup (`closed` ঘর)।
- **23/02–23/03:** পোস্ট হওয়া ভাউচার আর তাদের লাইন; `assertOpenYear` আগে থেকেই বন্ধ অর্থবছরে পোস্টিং আটকায়।
- **শেয়ার্ড:** `Tabs`, `Field` (23/02), `Filters`, `Register`, `Box`, `printDocument`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_07_000100_create_acc_period_close.php`:

- **`acc_stock_closings`:** কোম্পানি, বছর, মাস, কোড, বিবরণ, ক্লোজিং মূল্য; কোম্পানি-বছর-মাস-কোডে একটাই।
- **`acc_bank_lines`:** একটা পোস্ট হওয়া ভাউচার লাইন (একবারই), ব্যাংক হিসাব, চেক নং, ব্যাংকের তারিখ, ক্লিয়ার, মন্তব্য।
- **`acc_fiscal_year_closings`:** অর্থবছর (একবারই), পোস্ট হওয়া ভাউচারের সংখ্যা, ক্লোজিং ব্যালান্স (রাজস্ব − খরচ), মন্তব্য, কে আর কখন বন্ধ করেছেন।

## ৫. Workflow / Status ও Business Rules

- **Stock Value Closing (Records / Stock Closing / New stock closing):**
  - Records: কোম্পানি, বছর, মাস দিয়ে ছাঁকা; উপরে কোডের সংখ্যা আর মোট ক্লোজিং; সারিতে Edit (বিবরণ আর মূল্য) আর Delete।
  - Stock Closing worksheet: কোম্পানি, বছর, মাস বেছে Show → প্রতিটা স্টক হেড আর তার মূল্য; লিখে Save Closing। শুধু বদলানো সংখ্যা লেখা হয়, একই মাস দুবার সেভেও একবার।
  - **স্টক হেড:** Account Type Configuration-এ INVENTORY শ্রেণির হিসাব আর তাদের নিচের হিসাব; কোনোটা শ্রেণিভুক্ত না থাকলে "Inventories" গ্রুপের নিচের হিসাব; সাথে হাতে যোগ করা কোড।
  - New stock closing: বছর, মাস, কোড, বিবরণ, মূল্য; একই কোম্পানি-বছর-মাস-কোড আবার দিলে আগেরটা বদলায়।
  - বন্ধ অর্থবছরের মাসে কোনো সংখ্যা যোগ, বদল বা মোছা যায় না।
- **Bank Statement Reconciliation (Records / Bank Reconcilations / New bank line):**
  - **কোন লাইন:** ব্যাংক হিসাবের linked ledger-এ পোস্ট হওয়া লাইন নিজে থেকে আসে; অন্যগুলো (BANK শ্রেণির হিসাবের লাইন) New bank line দিয়ে হাতে একটা ব্যাংক হিসাবে আনা হয়। একটা লাইন একটাই মিলানোতে।
  - **Bank Reconcilations:** বাঁয়ে ব্যাংক হিসাব (খোঁজা যায়), ডানে তার লাইন — চেক নং, ব্যাংকের তারিখ, ক্লিয়ার টিক, মন্তব্য — একসাথে সেভ। চেক নং না দিলে লাইনের Ref থেকে আসে।
  - **ব্যালান্স:** Book = সব লাইন (Dr − Cr), Bank = শুধু ক্লিয়ার হওয়াগুলো, পার্থক্য = এখনো ব্যাংকে না পৌঁছানো।
  - ক্লিয়ার করতে ব্যাংকের তারিখ লাগে; সেই তারিখ ভাউচারের তারিখের আগে নয়।
  - Records: কোম্পানি, তারিখ, ব্যাংক আর অবস্থা (Cleared / Not in bank) দিয়ে ছাঁকা, তিনটা ব্যালান্স, Print (A4 আড়াআড়ি); হাতে আনা আর ক্লিয়ার না হওয়া লাইন সরানো যায়।
  - **ক্লিয়ার হওয়া লাইনের ভাউচার আর আনপোস্ট হয় না** (23/02-এ যে নিয়ম বাকি ছিল)।
- **Fiscal Year Closing (Records / Closing Process):**
  - খোলা অর্থবছর বেছে, মন্তব্যসহ "Close fiscal year" (নিশ্চিত করে)।
  - বছরে ড্রাফট বা আনপোস্টেড ভাউচার থাকলে বন্ধ হয় না (সংখ্যাসহ বার্তা)।
  - পোস্ট হওয়া ভাউচার গোনা হয়, ক্লোজিং ব্যালান্স = রাজস্ব আর খরচ হিসাবের (Cr − Dr) যোগফল; তারপর বছর বন্ধ। বন্ধ বছরে আর কোনো ভাউচার, পেছনের তারিখেরও, ওঠে না বা পোস্ট হয় না; স্টক ক্লোজিংও না।
  - Records-এ "Reopen" ক্লোজিং মুছে বছরটা আবার খোলে।

## ৬. API ও পারমিশন

- **পারমিশন (নতুন):** `accounting.period.view` / `manage`। FACTORY_ADMIN আর FACTORY_MANAGER দুটোই; AUDITOR শুধু view; STOREKEEPER কিছু না।
- **API (`/accounting/period/…`):**
  - `stock` (তালিকা, POST, `{id}` PUT / DELETE), `stock/options`, `stock/worksheet` (GET `?year=&month=`, PUT)
  - `bank` (তালিকা, POST নতুন লাইন, `{line}` DELETE), `bank/options`, `bank/board`, `bank/candidates`, `bank/accounts/{account}` (PUT)
  - `fiscal-years` (তালিকা, POST বন্ধ, `{id}` DELETE আবার খোলা), `fiscal-years/options`
- **মেনু:** Finance & Ledger-এর নিচে নতুন ফোল্ডার PERIOD CLOSE, ৩টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/period-close.jsx` — `StockClosingDesk`, `BankRecDesk`, `FyCloseDesk`; প্রতিটার ট্যাব কম্পোনেন্টের ভেতরে, রিলোড ছাড়া।
- **ঠিকানা:** `/finance-ledger/stock-closing`, `/bank-reconciliation`, `/fiscal-year-close`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Database/Migrations/2027_03_07_000100_create_acc_period_close.php` (নতুন)
- `Http/Controllers/Api/PeriodCloseApiController.php` (নতুন)
- `Http/Controllers/Api/VoucherApiController.php` (বদলানো: ক্লিয়ার হওয়া লাইনের ভাউচার আনপোস্ট নয়)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো)

**Frontend**
- `frontend/src/components/finance/period-close.jsx` (নতুন)
- `app/(app)/finance-ledger/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/PeriodCloseTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`PeriodCloseTest.php`, ২টা টেস্ট):** test2 DB-তে পাস। পুরো Accounting (১০টা টেস্ট, ২৯৯টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **স্টক:** worksheet-এ Inventories গ্রুপের হেড (১০২০১০১ Raw Materials - Yarn …); দুটো সংখ্যা দিলে ২টা বদল, আবার একই সেভে ০, একটা বদলালে ১। New stock closing-এ মূল্য ছাড়া আটকায়, মাস ১৩ আটকায়; একই কোড দুবার দিলে একটাই থাকে (৭০০); Edit, Delete; AUDITOR যোগ করতে পারেন না।
- **ব্যাংক:** BANK শ্রেণির লাইন দুটো candidate; লাইন ছাড়া, তারিখ ছাড়া ক্লিয়ার, ভাউচারের আগের তারিখ আটকায়; একই লাইন দুবার আটকায়। ledger যুক্ত করলে বাকি লাইন নিজে আসে: Book ৭০০, Bank ০, বাকি ৭০০; ক্লিয়ারের পর Bank ১০০০, বাকি −৩০০। ক্লিয়ার হওয়া ভাউচার আনপোস্ট আটকায়, ক্লিয়ার লাইন সরানো আটকায়।
- **অর্থবছর:** আনপোস্টেড ভাউচার থাকলে বন্ধ আটকায় (১টা); মুছে দিলে বন্ধ — ২টা পোস্ট, ব্যালান্স ৭০০; খোলা তালিকা থেকে সরে; আবার বন্ধ আটকায়; বন্ধ বছরে নতুন ভাউচার আর স্টক ক্লোজিং আটকায়; Reopen-এ আবার খোলে। STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/fl04`, `fl04b`, `fl04c`; error 0; dev ডাটা রাখা আছে):**
- **ব্যাংক:** JV-2026-000002 (KOYO-কে চেকে পরিশোধ, Accounts Payable DR / Cash At Bank CR ১৩,৮২৮, চেক CHQ-4471) তুলে পোস্ট → New bank line-এ ABN AMRO 0111-7654321-এ আনা → ব্যাংকের তারিখ ১১ নভেম্বর, ক্লিয়ার → Book = Bank = −১৩,৮২৮, বাকি ০। Posting Control-এ এর Unpost চাপলে "ব্যাংক ক্লিয়ার করেছে" বার্তায় আটকায়।
- **স্টক:** অক্টোবর ২০২৬ worksheet — Yarn ১৮,৫০,০০০, Accessories ১৩,৮৭৮, Finished Goods ৯,২৫,০০০ (মোট ২৭,৮৮,৮৭৮)।
- **অর্থবছর:** Closing Process ফর্ম দেখা হয়েছে; dev-এর কোনো বছর বন্ধ করা হয়নি — বন্ধ করলে dev-এর পরের সব পোস্টিং আটকে যেত।

## ১০. খোলা প্রশ্ন

১. **স্টক হেড কোনগুলো:** Brickland-এর demo-তে তালিকা খালি। আমরা ধরেছি Account Type Configuration-এ INVENTORY শ্রেণির হিসাব, আর কোনোটা না থাকলে "Inventories" গ্রুপের নিচের হিসাব। ইনভেন্টরি মডিউলের স্টক থেকে নিজে থেকে মূল্য আনতে চাইলে (যেমন Store / Yarn Store-এর মাসশেষের মূল্য) জানাবেন।

২. **অর্থবছর বন্ধের শর্ত:** বছরে ড্রাফট বা আনপোস্টেড ভাউচার থাকলে বন্ধ হতে দিইনি। Brickland-এ এটা দেখা যায়নি — ঠিক আছে কিনা?

৩. **ক্লোজিং ব্যালান্স কী:** আমরা বছরের লাভ/ক্ষতি (রাজস্ব − খরচ) রেখেছি। আপনারা অন্য কিছু (যেমন বছরশেষের মোট ব্যালান্স) চাইলে জানাবেন।

৪. **লাভ/ক্ষতি পরের বছরে নেওয়া:** বন্ধের সময় Retained Earnings-এ closing ভাউচার বানানো হয় না। দরকার হলে কোন হিসাবে যাবে জানাবেন।

৫. **আবার খোলা:** ক্লোজিং মুছলে বছরটা খোলে। শুধু সর্বশেষ বন্ধ বছর খোলা যাবে, বা আলাদা পারমিশন লাগবে কিনা?

## ১১. As-built নোট

- **Brickland প্রোব:** তিনটা স্ক্রিনের স্পেক RSC payload থেকে — worksheet প্যানেল ("Only the figures that changed are written"), master-detail মিলানো ("the book balance is every posted line, the bank balance only the cleared ones"), আর ফর্মের নোট ("A closed year refuses further postings, including back-dated ones")। demo-তে কোনো রেকর্ড নেই।
- **dev ডাটা:** dev-এর ব্যাংক হিসাবগুলোয় ledger যুক্ত নেই, তাই লাইন New bank line দিয়ে আনা হয়েছে। BPV-তে dev-এ অন্য ব্যবহারকারীর Maker grant আছে, তাই পরীক্ষার ভাউচার JV।
- **UI চেকে ধরা পড়া:** কিছু নয়; Cleared চেকবক্স আর নিশ্চিতকরণ ডায়ালগের সিলেক্টর শুধু চেক প্ল্যানে ঠিক করতে হয়েছে।
