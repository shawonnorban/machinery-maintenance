# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন, ২৬টা রিপোর্ট)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/07`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Financial Statement Reports | `/m/accounting/accounting-reports` | 3 | 35 | 1 |
| 2 | Ledger-Wise Cost Centre Summary | `/m/accounting/ledger-wise-cost-center-summary` | 2 | 10 | 1 |
| 3 | Cost Centre-Wise Ledger Summary | `/m/accounting/cost-center-wise-ledger-summary` | 2 | 10 | 1 |
| 4 | Payment Receipt Register | `/m/accounting/receive-payment` | 2 | 8 | 1 |
| 5 | Miscellaneous Finance Reports | `/m/accounting/accounting-misc-report` | 2 | 8 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Financial Statement Reports

`/m/accounting/accounting-reports`

**ট্যাব: Run History 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Run History 0, Selection of Parameters, New report run, Search account head..., Search, Excel, Refresh

**ট্যাব: Selection of Parameters**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill wise Ledger | radio |  |  |
| Bill Status | radio |  |  |
| Day Book by Draft Voucher | radio |  |  |
| Bank Reconcile | radio |  |  |
| Ledger Summery | radio |  |  |
| Monthly Ledger Summery | radio |  |  |
| Subsidairy Ledger | radio |  |  |
| Chart of Account | radio |  |  |
| Trial Balance | radio |  |  |
| Control Ledger | radio |  |  |
| Cash Book | radio |  |  |
| Bank Book | radio |  |  |
| Day Book Detail | radio |  |  |
| Day Book Summery | radio |  |  |
| Voucher Statement | radio |  |  |
| Voucher Statement Summery | radio |  |  |
| Income Statement Detail | radio |  |  |
| Income Statement Summery | radio |  |  |
| Balance Sheet | radio |  |  |
| Ledger Wise Voucher Summary | radio |  |  |
| Subsidiary Ledger Statement | radio |  |  |
| Stock Closing Statement | radio |  |  |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Voucher For Type | select |  | General, Supplier, Buyer, Employee, Party |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Run History 0, Selection of Parameters, New report run, Preview

**ট্যাব: New report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Bill No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New report run
- বাটন/অ্যাকশন: Run History 0, Selection of Parameters, New report run, Select…, Reset, Save report run

**ডায়ালগ: New report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Bill No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.

#### Ledger-Wise Cost Centre Summary

`/m/accounting/ledger-wise-cost-center-summary`

**ট্যাব: Ledger Cost Center Report 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Search Cost Center... |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Ledger Cost Center Report 0, New ledger cost centre run, Search account head..., Search, Excel, Refresh

**ট্যাব: New ledger cost centre run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report* | select | ✔ | Select…, Cost Centre Movement, Ledger wise Cost Centre |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Select… |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New ledger cost centre run
- বাটন/অ্যাকশন: Ledger Cost Center Report 0, New ledger cost centre run, Select…, Reset, Save ledger cost centre run

**ডায়ালগ: New ledger cost centre run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | select | ✔ | Select…, Cost Centre Movement, Ledger wise Cost Centre |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Select… |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.

#### Cost Centre-Wise Ledger Summary

`/m/accounting/cost-center-wise-ledger-summary`

**ট্যাব: Cost Center wise ledger summary Report 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Select Costcented... |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Cost Center wise ledger summary Report 0, New cost centre ledger run, Search, Excel, Refresh

**ট্যাব: New cost centre ledger run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report* | select | ✔ | Select…, Cost Centre Movement, Ledger wise Cost Centre |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Select… |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New cost centre ledger run
- বাটন/অ্যাকশন: Cost Center wise ledger summary Report 0, New cost centre ledger run, Reset, Save cost centre ledger run

**ডায়ালগ: New cost centre ledger run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | select | ✔ | Select…, Cost Centre Movement, Ledger wise Cost Centre |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| Cost Center | select |  | Select… |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.

#### Payment Receipt Register

`/m/accounting/receive-payment`

**ট্যাব: Receive Payment 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Receive Payment 0, New receive payment run, Search, Excel, Refresh

**ট্যাব: New receive payment run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report* | select | ✔ | Select…, Receive Payment |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New receive payment run
- বাটন/অ্যাকশন: Receive Payment 0, New receive payment run, Reset, Save receive payment run

**ডায়ালগ: New receive payment run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | select | ✔ | Select…, Receive Payment |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.

#### Miscellaneous Finance Reports

`/m/accounting/accounting-misc-report`

**ট্যাব: Acccounting Misc. Report 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Acccounting Misc. Report 0, New misc report run, Search, Excel, Refresh

**ট্যাব: New misc report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report* | select | ✔ | Select…, Voucher Audit Trail |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New misc report run
- বাটন/অ্যাকশন: Acccounting Misc. Report 0, New misc report run, Reset, Save misc report run

**ডায়ালগ: New misc report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | select | ✔ | Select…, Voucher Audit Trail |
| Company | select |  | Select…, Brickland Composite Ltd. (BCL) |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/02–23/03-এর ভাউচার:** `acc_vouchers` / `acc_voucher_lines` — পোস্ট হওয়া লাইনই সব রিপোর্টের উৎস (ড্রাফট ডে বুক, আনপোস্টেড ভাউচার বিবরণী আর অডিট ট্রেইল ছাড়া)।
- **23/01-এর চার্ট, কস্ট সেন্টার, অর্থবছর, Account Type Configuration:** স্তর (Main Class / Sub Class / Control / Ledger) চার্টের গভীরতা থেকে; Cash / Bank / … শ্রেণি `acc_account_classes` থেকে।
- **23/04:** ব্যাংক মিলানোর লাইন আর স্টক ক্লোজিং — এগুলো পড়ার কোড Period Close থেকে নতুন `Support/FinanceBook.php`-এ সরানো হয়েছে; Period Close এখন সেটাই ডাকে।
- **23/03-এর ভাউচার লগ:** Voucher Audit Trail রিপোর্ট।
- **22/06-এর রিপোর্ট স্ক্রিন:** রিপোর্টের তালিকা, ঘর, Preview আর ফলাফলের টেবিলের নকশা।

## ৪. ডাটা মডেল (Entity ও Field)

একটা নতুন টেবিল — `acc_report_runs`: কোম্পানি, স্ক্রিন, রিপোর্ট কোড, শুরু ও শেষ তারিখ, যে ঘর দিয়ে ছাঁকা (JSON), হিসাব খাত, কস্ট সেন্টার, কতটা সারি, Export to Excel?, কে, কখন।

রিপোর্টের সংখ্যা জমানো হয় না — প্রতিবার চালানোর সময় লেজার থেকে পড়া হয় (Brickland-এর নোট: "nothing is cached")। শুধু চালানোর রেকর্ড থাকে।

## ৫. Workflow / Status ও Business Rules

- **স্থিতি = ডেবিট − ক্রেডিট।** প্রারম্ভিক স্থিতি: ব্যালান্স শিটের খাত শুরু থেকে; আয় আর ব্যয়ের খাত শুরুর তারিখ যে অর্থবছরে পড়ে তার শুরু থেকে।
- **Financial Statement Reports (২২টা):** প্রতিটা রিপোর্ট Brickland-এর মতো শুধু নিজের ঘর চায় (Selection of Parameters-এ বাকি ঘর লুকানো; New report run-এ নিষ্ক্রিয়)।
  - Bill wise Ledger / Bill Status: হিসাব খাত + বিল নং ধরে বিল; বড় দিকটা বিল হয়েছে, ছোট দিকটা নিষ্পত্তি; Unpaid / Partial / Paid। বিলের অবস্থা শেষ তারিখ পর্যন্ত পুরো জীবন ধরে।
  - Day Book by Draft Voucher: ড্রাফট আর আনপোস্টেড ভাউচারের লাইন। Day Book Detail / Summary: পোস্ট হওয়া লাইন, দিন ধরে সারাংশ।
  - Bank Reconcile: 23/04-এর মিলানোর লাইন; বই, ব্যাংক আর "এখনো ব্যাংকে ওঠেনি"।
  - Ledger Summary, Trial Balance (সমাপনী ডেবিট/ক্রেডিট আলাদা), Monthly Ledger Summary (সর্বোচ্চ ২৪ মাস), Subsidiary Ledger আর Control Ledger (Map Class স্তরে গুটানো; Control Ledger Main Class শ্রেণির খাত), Ledger Wise Voucher Summary, Subsidiary Ledger Statement (চলমান স্থিতিসহ)।
  - Cash Book / Bank Book: প্রারম্ভিক স্থিতি, প্রাপ্তি, পরিশোধ, চলমান স্থিতি; "Particulars" = একই ভাউচারের অন্য খাত।
  - Voucher Statement (পোস্ট হয়েছে হ্যাঁ/না), Voucher Statement Summary (ধরন অনুযায়ী)।
  - Income Statement Detail / Summary (Summary উপ-শ্রেণিতে গুটানো) — মোট আয়, ব্যয়, নিট লাভ। শুরুর তারিখ না দিলে অর্থবছরের শুরু থেকে।
  - Balance Sheet: শেষ তারিখে সম্পদ বনাম দায় + মালিকানা স্বত্ব (উপ-শ্রেণিতে); বছরের লাভ/ক্ষতি মালিকানা স্বত্বে; পার্থক্য দেখায়।
  - Chart of Account (গাছ আকারে), Stock Closing Statement (23/04-এর ক্লোজিং)।
- **Ledger-Wise Cost Centre Summary:** Cost Centre Movement (লাইন ধরে) আর Ledger wise Cost Centre — হিসাব খাত → কস্ট সেন্টার। **Cost Centre-Wise Ledger Summary:** একই দুটো রিপোর্ট, কিন্তু কস্ট সেন্টার → হিসাব খাত।
- **Payment Receipt Register:** Cash ও Bank খাতের লাইন — প্রাপ্তি, পরিশোধ, কার জন্য; নিট।
- **Miscellaneous Finance Reports:** Voucher Audit Trail — সময়কালের প্রতিটা ভাউচারের কাজ।
- **Run History:** প্রতিটা Preview বা Save report run একটা চালানো হিসেবে থাকে। তারিখ দিয়ে খুঁজলে চালানোটা যে সময়কাল ধরেছিল তার সাথে মিলিয়ে ছাঁকে। PDF আর Excel। মোছা শুধু চালানোর রেকর্ড মোছে।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `accounting.report.view` (চালানো আর ইতিহাস দেখা) আর `accounting.report.manage` (চালানো মোছা)। FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পান; AUDITOR শুধু view।
- **API:** `GET /accounting/reports/options?screen=`, `POST /accounting/reports/run`, `GET /accounting/reports/runs?screen=`, `DELETE /accounting/reports/runs/{id}`। স্ক্রিন: `statements`, `ledger-cc`, `cc-ledger`, `receive-payment`, `misc`।
- **মেনু:** Finance & Ledger-এর নিচে নতুন ফোল্ডার REPORTS, ৫টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/reports.jsx` — `FinanceReportDesk` (পাঁচটা স্ক্রিনেই, `screen` দিয়ে): Run History, (শুধু প্রথমটায়) Selection of Parameters, New report run — পাতা রিলোড ছাড়া ট্যাব বদলায়। ফলাফলের টেবিলে মোট সারি, সারাংশ আর Export to Excel।
- **ঠিকানা:** `/finance-ledger/accounting-reports`, `/ledger-wise-cost-center-summary`, `/cost-center-wise-ledger-summary`, `/receive-payment`, `/accounting-misc-report`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Database/Migrations/2027_03_09_000100_create_acc_report_runs.php` (নতুন)
- `Support/FinanceBook.php` (নতুন)
- `Http/Controllers/Api/FinanceReportApiController.php` (নতুন)
- `Http/Controllers/Api/PeriodCloseApiController.php` (বদলানো — ব্যাংক মিলানোর পড়া FinanceBook-এ)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো)

**Frontend**
- `frontend/src/components/finance/reports.jsx` (নতুন)
- `app/(app)/finance-ledger/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/FinanceReportTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`FinanceReportTest.php`, ৩টা টেস্ট):** test2 DB-তে পাস; পুরো Accounting স্যুট ১৬টা টেস্ট / ৪৮৫ assertion, MenuAccess + RoleMatrix ১৩টা টেস্ট পাস।
- চারটা পোস্ট হওয়া ভাউচার (আগস্টে নগদ বিক্রি ১,০০০; সেপ্টেম্বরে রপ্তানি বিল B-1 ৫,০০০; অক্টোবরে ব্যাংকে ২,০০০ আদায়; কস্ট সেন্টার CC1-এ ৩০০ রক্ষণাবেক্ষণ নগদে) আর একটা ড্রাফট।
- Trial Balance: ডেবিট = ক্রেডিট = ৮,৩০০, সমাপনী দুই দিক সমান। Bill Status: B-1 — ৫,০০০ বিল, ২,০০০ নিষ্পত্তি, ৩,০০০ বাকি, Partial; Paid ছাঁকলে কিছু নেই। Cash Book অক্টোবর থেকে: প্রারম্ভিক ১,০০০ → ৭০০, Particulars "Factory Maintenance"। Monthly Ledger: Aug ১,০০০ ঢুকেছে, Oct ৩০০ বেরিয়েছে।
- Income Statement: আয় ৬,০০০, ব্যয় ৩০০, নিট ৫,৭০০; Balance Sheet: সম্পদ ৫,৭০০ = দায় + স্বত্ব ৫,৭০০, পার্থক্য ০। Subsidiary (Control স্তর): নগদ ও সমতুল্য ২,৭০০, প্রাপ্য ৩,০০০। ড্রাফট শুধু ড্রাফট ডে বুকে; Chart-এ সব খাত।
- Ledger wise Cost Centre দুই স্ক্রিনে দুই দিক থেকে; Receive Payment: প্রাপ্তি ৩,০০০, পরিশোধ ৩০০; Voucher Audit Trail।
- Run History: ছাঁকা ঘরের লেখা, সারি, সময়কাল, কে; সময়কাল দিয়ে খোঁজা; ভুল সময়কাল বা অন্য স্ক্রিনের রিপোর্ট আটকায়; AUDITOR চালাতে পারেন কিন্তু মুছতে পারেন না; STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/fl07`, `fl07b`; error 0; dev ডাটা রাখা আছে):** Selection of Parameters-এ Trial Balance (৫টা খাত, মোট ৭৬,২০৬ = ৭৬,২০৬), Cash Book (রিপোর্ট বদলালে ঘর বদলায় — Account Head যোগ হয়), Balance Sheet (পার্থক্য ০); New report run-এ Ledger Summary সেভ (Bill No নিষ্ক্রিয়, কারণ এই রিপোর্ট পড়ে না); Run History-তে চারটা চালানো; Ledger-Wise Cost Centre, Payment Receipt Register (CPV-2026-000001, JV-2026-000002 — পরিশোধ ৬২,৩২৮) আর Voucher Audit Trail (১০টা কাজ) সেভ হয়ে নিজ নিজ ইতিহাসে।

## ১০. খোলা প্রশ্ন

১. **রিপোর্টের নামের বানান:** Brickland-এ "Ledger Summery", "Subsidairy Ledger", "Acccounting Misc. Report" — আমরা বানান ঠিক করে দেখাচ্ছি (Ledger Summary, Subsidiary Ledger, Accounting Misc. Report)। Brickland-এর হুবহু বানান চাইলে জানাবেন।

২. **বিলের অবস্থা:** বিল চেনা হয় হিসাব খাত + বিল নং দিয়ে (লাইনের বিল নং, না থাকলে ভাউচারের)। বড় দিকটা বিল হয়েছে, ছোট দিকটা নিষ্পত্তি ধরা হয়েছে — প্রাপ্য আর প্রদেয় দুই খাতেই খাটে। অন্য নিয়ম চাইলে জানাবেন।

৩. **ব্যালান্স শিটের স্তর:** উপ-শ্রেণি (চার্টের দ্বিতীয় স্তর) পর্যন্ত গুটানো, আর বছরের লাভ/ক্ষতি মালিকানা স্বত্বে একটা লাইন হিসেবে। বিস্তারিত (Control বা Ledger স্তরে) চাইলে জানাবেন।

৪. **Preview-ও ইতিহাসে:** Brickland-এর নোট অনুযায়ী প্রতিটা চালানোই রেকর্ড, তাই Selection of Parameters-এর Preview-ও Run History-তে ওঠে।

## ১১. As-built নোট

- **Brickland প্রোব:** পাঁচটা স্ক্রিনের স্পেক RSC payload থেকে — Run History কলাম, খোঁজার ঘর, PDF/Excel, New report run-এর ঘর, আর প্রথম স্ক্রিনের worksheet-এর ২২টা রিপোর্ট ও প্রতিটার visibleWhen নিয়ম। Brickland শুধু প্যারামিটার স্ক্রিন আঁকে; প্রতিটা রিপোর্টের ভেতরের অংশ আমাদের নিজের লেজার থেকে বানানো।
- **FinanceBook:** চার্টের গাছ, স্তর, শ্রেণি, পোস্ট হওয়া লাইন, প্রারম্ভিক স্থিতি আর ব্যাংক মিলানো এক জায়গায় — Period Close আর Reports দুটোই এটা ব্যবহার করে।
- **UI চেকে ধরা পড়া:** New report run-এর "Report" ড্রপডাউন আর Selection of Parameters-এর লুকানো রিপোর্ট তালিকার একই লেবেল ছিল — তালিকার লেবেল বদলানো হয়েছে।
