# Cheque Management

> **স্ট্যাটাস:** ✅ সম্পন্ন (২টার ২টা স্ক্রিন)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/05`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Cheque Issue Entry | `/m/accounting/cheque-issue` | 2 | 18 | 1 |
| 2 | Cheque Cancellation Entry | `/m/accounting/cheque-cancellation` | 1 | 6 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Cheque Issue Entry

`/m/accounting/cheque-issue`

**ট্যাব: Cheque Issue List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Branch Name | select |  |  |
| Cheque Issue Ref | text |  | Cheque Ref No... |
| Cheque No | text |  | Cheque No... |
| Account No | text |  | Account No... |
| With Finalized? | select |  | Yes, No |
| From Issue Date | date |  |  |
| To Issue Date | date |  |  |
| Issue Reference | text |  | Issue Ref... |
- সেকশন: Search
- বাটন/অ্যাকশন: Cheque Issue List 0, Cheque Issue, -- Select Bank --, Search, Add New, Refresh

**ট্যাব: Cheque Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Branch Name | select |  |  |
| Cheque Issue Ref | text |  | Cheque Ref No... |
| Cheque No | text |  | Cheque No... |
| Account No | text |  | Account No... |
| With Finalized? | select |  | Yes, No |
| From Issue Date | date |  |  |
| To Issue Date | date |  |  |
| Issue Reference | text |  | Issue Ref... |
- সেকশন: Search
- বাটন/অ্যাকশন: Cheque Issue List 0, Cheque Issue, -- Select Bank --, Search, Add New, Refresh

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing cheque issue | select |  |  |
| Account Number* | select | ✔ | Search account number... |
| Company* | select | ✔ | Brickland Composite Ltd. (BCL) |
| Cheque Issue Ref | text |  |  |
| Bank | text |  |  |
| Bank Branch | text |  |  |
| Cheque No* | select | ✔ | Search for Chq No |
| Issue Date* | date | ✔ |  |
| Place Date | date |  |  |
| Cheque Issue Type* | select | ✔ | Money Requisition, Bill Payment, Supplier Payment, Advance, Salary & Wages, Utility, Others |
| Pay To* | text | ✔ |  |
| Cheque Issue For | combobox |  |  |
| Charges | number |  |  |
| Delivered | checkbox |  |  |
| Amount (BDT)* | number | ✔ |  |
| Is Acc Payee? | checkbox |  |  |
| A/C Year | select |  | FY2027-28, FY2026-27 |
| Cheque Issue Purpose | textarea |  |  |
| Narration | textarea |  |  |
- খালি Save-এ মেসেজ: A cheque needs at least one detail line.

#### Cheque Cancellation Entry

`/m/accounting/cheque-cancellation`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company Name | select |  | Brickland Composite Ltd. (BCL) |
| Bank and Branch | select |  |  |
| Account Type | text |  | -- Select -- |
| Cheque Book No | text |  | Cheque Book No... |
| From Receive Date | date |  |  |
| To Receive Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: New cheque cancellation, -- Select Bank --, Search, Excel, Refresh

**ডায়ালগ: New cheque cancellation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Cheque No* | select | ✔ | Select… |
| Issue Ref | text |  |  |
| Remarks* | textarea | ✔ |  |
- খালি Save-এ মেসেজ: Cheque No is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/01:** Bank Account Master (`acc_bank_accounts`, ব্যাংক ও শাখা), Cheque Book Register (`acc_cheque_books`) আর তার পাতা (`acc_cheque_leaves`: UNUSED / ISSUED / CANCELLED, active), Chart of Accounts, Fiscal Year।
- **22/01:** ব্যাংক আর শাখার তালিকা (`tx_banks`, `tx_bank_branches`)।
- **শেয়ার্ড:** `Tabs`, `Field` (23/02), `EditGrid`, `Filters`, `Register`, `Box`, `Modal`, `printDocument`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_08_000100_create_acc_cheque_issues.php`:

- **`acc_cheque_issues`:** issue ref (`CHQ-2026-000001`, সেভে), ব্যাংক হিসাব, চেকের পাতা (একবারই), ইস্যু ও জমার তারিখ, ধরন (Money Requisition, Bill Payment, Supplier Payment, Advance, Salary & Wages, Utility, Others), ইস্যুর খাত (Cr), প্রাপক, কার জন্য (কর্মী কোড), চার্জ, delivered, পরিমাণ, acc payee, অর্থবছর, উদ্দেশ্য, narration, issued by, printed (+ কখন), cancelled।
- **`acc_cheque_issue_lines`:** কন্ট্রোল হেড (Dr), কার জন্য, issue ref (রিকুইজিশন বা বিলের রেফ), মুদ্রা (BDT), চাহিদা, পরিমাণ, মন্তব্য।
- **`acc_cheque_cancellations`:** পাতা (একবারই), চেক ইস্যু (থাকলে), issue ref, মন্তব্য, কে।

## ৫. Workflow / Status ও Business Rules

- **Cheque Issue Entry (Cheque Issue List / Cheque Issue ট্যাব, রিলোড ছাড়া):**
  - হিসাব বাছলে ব্যাংক আর শাখা আসে, আর Cheque No-তে সেই হিসাবের বইয়ের অব্যবহৃত সক্রিয় পাতা।
  - সেভে Ref নম্বর; পাতাটা সাথে সাথে ISSUED হয়ে তালিকা থেকে সরে — একটা চেক দুবার ইস্যু হয় না। অন্য হিসাবের বা ব্যবহৃত পাতা আটকায়।
  - অন্তত একটা লাইন; লাইনের মোট চেকের অঙ্ক ছাড়ায় না।
  - লাইনে Issue Ref আর Req Amt দিলে Used Amt (অন্য বাতিল-না-হওয়া চেকে যা মেটানো হয়েছে) নিজে আসে, Balance দেখায়; লাইনের পরিমাণ বাকির বেশি হয় না।
  - বদলানোর সময় পাতা বদলালে আগের পাতা আবার অব্যবহৃত; চেক মুছলে পাতা ফেরত।
  - **Cheque Print** (নিশ্চিত করে) একবারই; তারপর চেক বদলানো বা মোছা যায় না, শুধু বাতিল। তালিকায় "Finalized" মানে প্রিন্ট হয়েছে।
  - Print: চেক ইস্যুর ছাপা কপি (হেডার, লাইন, মোট, স্বাক্ষর)।
  - তালিকা: Brickland-এর ১০টা ফিল্টার (ব্যাংক, শাখা, রেফ, চেক নং, হিসাব নং, চূড়ান্ত, তারিখ, লাইনের রেফ) আর ১৭টা কলাম; সারিতে Print, Cheque Issue Details, Delete (প্রিন্ট না হলে)। ঠিকানায় `?no=CHQ-…`।
- **Cheque Cancellation Entry:**
  - "New cheque cancellation" ডায়ালগ: পাতা (অব্যবহৃত বা ইস্যু হওয়া), Issue Ref (ইস্যু হলে নিজে বসে), মন্তব্য (বাধ্যতামূলক)।
  - বাতিল পাতা CANCELLED ও নিষ্ক্রিয় হয়, কিন্তু মোছে না (বইয়ের নম্বর মেলাতে); আর কখনো ইস্যু হয় না। ইস্যু হওয়া চেক বাতিল হলে সেটা "বাতিল" দেখায়।
  - "Remove cancellation" পাতাকে আগের অবস্থায় (অব্যবহৃত বা ইস্যু) ফেরায়।
  - তালিকা: কোম্পানি, ব্যাংক, শাখা, হিসাবের ধরন, বই নং, বই গ্রহণের তারিখ দিয়ে ছাঁকা; PDF আর Excel।

## ৬. API ও পারমিশন

- **পারমিশন (নতুন):** `accounting.cheque.view` / `manage`। FACTORY_ADMIN আর FACTORY_MANAGER দুটোই; AUDITOR শুধু view; STOREKEEPER কিছু না।
- **API (`/accounting/cheques/…`):** তালিকা, POST, `{id}` GET / PUT / DELETE, `{id}/print`, `options`, `leaves?account=&keep=`, `used?ref=&except=`, `cancellations` (তালিকা, POST, `{id}` DELETE), `cancellations/leaves`।
- **মেনু:** Finance & Ledger-এর নিচে নতুন ফোল্ডার CHEQUE MANAGEMENT, ২টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/cheques.jsx` — `ChequeIssueDesk`, `ChequeCancelDesk`।
- **ঠিকানা:** `/finance-ledger/cheque-issue`, `/finance-ledger/cheque-cancellation`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (২টার ২টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Database/Migrations/2027_03_08_000100_create_acc_cheque_issues.php` (নতুন)
- `Http/Controllers/Api/ChequeApiController.php` (নতুন)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো)

**Frontend**
- `frontend/src/components/finance/cheques.jsx` (নতুন)
- `app/(app)/finance-ledger/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/ChequeManagementTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`ChequeManagementTest.php`, ২টা টেস্ট, ৬২টা assertion):** test2 DB-তে পাস। পুরো Accounting (১২টা টেস্ট, ৩৬১টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **ইস্যু:** হিসাবের ৩টা পাতা, অন্য হিসাবের ০। লাইন ছাড়া, অন্য হিসাবের পাতা, লাইনের মোট (৯০০) চেকের (৮০০) বেশি আটকায়। `CHQ-2026-000001`, CHQ1001, পাতা ISSUED, তালিকায় ২টা বাকি। একই পাতা আবার আটকায়; MCB-1-এ Used ৬০০, দ্বিতীয় চেকে ৯০১ আটকায় (বাকি ৯০০), ৯০০ চলে (`CHQ-2026-000002`)। পাতা বদলালে আগেরটা ফেরত; মুছলে পাতা ফেরত। Cheque Print একবার; আবার আটকায়; প্রিন্টের পর বদলানো বা মোছা আটকায়।
- **বাতিল:** অব্যবহৃত আর ইস্যু হওয়া পাতা মিলিয়ে ৩টা candidate (ইস্যু হওয়ারটায় রেফ); পাতা ছাড়া বা মন্তব্য ছাড়া আটকায়; বাতিলের পর পাতা CANCELLED ও নিষ্ক্রিয়, ইস্যুর তালিকা থেকে সরে, প্রিন্ট হওয়া চেক cancelled; আবার বাতিল আটকায়। বাতিল তুলে নিলে পাতা ISSUED / UNUSED-এ ফেরে। AUDITOR দেখেন, বাতিল করতে পারেন না; STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/fl05`; error 0; dev ডাটা রাখা আছে):**
- **ইস্যু:** ABN AMRO 0111-7654321, পাতা CHQ1000001, Bill Payment, KOYO TRIMS SOLUTION JP, ১৩,৮২৮, Acc Payee; লাইন Accounts Payable, রেফ MCB-2026-000001 (চাহিদা ১৩,৮২৮, Used ০, বাকি ১৩,৮২৮) → `CHQ-2026-000001`; Cheque Print → লক; Print-এর PDF দেখে নেওয়া।
- **বাতিল:** CHQ1000010, "Leaf damaged — torn while writing" → তালিকায় নিষ্ক্রিয়।

## ১০. খোলা প্রশ্ন

১. **"Finalized" কলাম:** Brickland-এর তালিকায় "Is Cheque Print" আর "Finalized" দুটো আলাদা কলাম, কিন্তু ফর্মে Finalize বাটন নেই। আমরা প্রিন্ট হওয়াকেই Finalized ধরেছি। আলাদা Finalize চাইলে জানাবেন।

২. **চেক থেকে ভাউচার:** চেক ইস্যু হলে নিজে থেকে Bank Payment Voucher (Dr কন্ট্রোল হেড, Cr ইস্যুর খাত) তৈরি হবে কিনা? এখন হয় না।

৩. **"Open book":** Brickland বলে "Available leaves on an open book"। dev-এর বইয়ে finalized = false, তাই আমরা বইয়ের অবস্থা না দেখে সব বইয়ের অব্যবহৃত সক্রিয় পাতা দেখাচ্ছি। শুধু চূড়ান্ত (গৃহীত) বই চাইলে জানাবেন।

৪. **Cheque Issue For / Issue For:** কর্মী কোড আর লাইনের "Issue For" এখন লেখার ঘর। HR-এর কর্মী তালিকা বা রিকুইজিশন থেকে বাছার ঘর চাইলে জানাবেন।

৫. **চেকের প্রিন্ট ফরম্যাট:** "Cheque Print" এখন শুধু প্রিন্ট হয়েছে বলে লেখে; ব্যাংকের চেকের পাতায় ছাপার ফরম্যাট (তারিখ, প্রাপক, কথায় অঙ্ক) দরকার হলে ব্যাংক অনুযায়ী মাপ জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** দুটো স্ক্রিনের স্পেক RSC payload থেকে — হেডার ঘর, লাইনের কলাম (Used Amt, Balance), record action ("Cheque Print" — "A cheque can only be printed once"), আর বাতিলের নোট ("The leaf itself is kept — a cheque book with a hole in its numbering would not reconcile")। demo-তে কোনো রেকর্ড নেই।
- **পাতার অবস্থা:** 23/01-এর পাতার UNUSED / ISSUED / CANCELLED এখানেই প্রথম ব্যবহার; 23/01-এর পাতার গ্রিড শুধু Active বদলায়।
- **UI চেকে ধরা পড়া:** কিছু নয়।
