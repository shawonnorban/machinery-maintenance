# Voucher Audit

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/06`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | 503 Service Temporarily Unavailable | `/m/accounting/voucher-activity` | 1 | 0 | 0 |
| 2 | Deleted Voucher History | `/m/accounting/voucher-delete-history` | 1 | 11 | 0 |
| 3 | Voucher Amendment Log | `/m/accounting/voucher-change-logs` | 1 | 11 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### 503 Service Temporarily Unavailable

`/m/accounting/voucher-activity` — মেনুতে: Voucher Activity Trail

**ভিউ**

#### Deleted Voucher History

`/m/accounting/voucher-delete-history`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Voucher No# | text |  | voucher no... |
| Voucher Reference# | text |  | voucher ref... |
| Bill No# | text |  | Bill No... |
| Voucher Type | select |  | Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Voucher, Jo |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Trx Source | select |  | Head Office - Finance & Accounts, Head Office - Commercial, Factory - Store, Factory - HR & Payroll, System Generated |
| Voucher For Type | select |  | General, Supplier, Buyer, Employee, Party |
| Voucher For | text |  | -- Select -- |
| Posting Status | select |  | Draft, Unposted, Posted, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Search, Refresh

#### Voucher Amendment Log

`/m/accounting/voucher-change-logs`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Voucher No# | text |  | voucher no... |
| Voucher Reference# | text |  | voucher ref... |
| Bill No# | text |  | Bill No... |
| Voucher Type | select |  | Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Voucher, Jo |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Trx Source | select |  | Head Office - Finance & Accounts, Head Office - Commercial, Factory - Store, Factory - HR & Payroll, System Generated |
| Voucher For Type | select |  | General, Supplier, Buyer, Employee, Party |
| Voucher For | text |  | -- Select -- |
| Posting Status | select |  | Draft, Unposted, Posted, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Search, Refresh
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/03-এর `acc_voucher_logs`:** ভাউচারের প্রতিটা কাজ (তৈরি, বদল, অনুমোদন, পোস্ট, আনপোস্ট, মোছা) তখনকার পুরো ভাউচারসহ — এই তিনটা স্ক্রিন শুধু এটাই পড়ে।
- **শেয়ার্ড:** `Filters`, `Register`, `printDocument`, `useW` / `opts` (23/02)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই; শুধু পড়া। লগের প্রতিটা সারি থেকে: কাজ, পোস্টিংয়ের অবস্থা, ভাউচার আর Ref নম্বর, তারিখ, ধরন, উৎস, Voucher For, তৈরির তারিখ ও তৈরিকারী, Action Status, কাজের সময় আর কে করেছেন, Bill No, পরিমাণ।

## ৫. Workflow / Status ও Business Rules

- **Voucher Activity Trail:** সব কাজ, নতুনটা আগে। Note-এ ভাউচারের narration। PDF আর Excel।
- **Deleted Voucher History:** শুধু মোছা ভাউচার; নম্বর লেখা হিসেবে থাকে (যে সারির দিকে দেখাত সেটা আর নেই)। Note: "কতটা লাইন আর কত টাকাসহ মোছা"। নিচে মোট মোছা ভাউচার আর টাকা।
- **Voucher Amendment Log:** শুধু বদল। Note-এ আগের অবস্থার তুলনায় কী বদলেছে — তারিখ, ধরন, উৎস, Bill No, Voucher For, Narration, Action Status, আর লাইন (মোট বদলালে "আগে → পরে")। কিছু না বদলালে "কোনো বদল ছাড়াই আবার সেভ"।
- তিনটাতেই Brickland-এর ১১টা ফিল্টার (ভাউচার নং, Ref, Bill No, ধরন, কোম্পানি, উৎস, Voucher For ধরন ও নাম, পোস্টিং অবস্থা, তারিখ) আর ১২টা কলাম। কিছুই বদলানো বা মোছা যায় না।
- **সীমা:** লগ চালু হয়েছে 23/03 থেকে; তার আগে তোলা ভাউচারের (যেমন JV-2026-000001) কাজ এখানে নেই।

## ৬. API ও পারমিশন

- **পারমিশন:** 23/02-এর `accounting.voucher.view` (বা `manage`)।
- **API:** `GET /accounting/voucher-audit?kind=activity|deleted|amended`।
- **মেনু:** Finance & Ledger-এর নিচে নতুন ফোল্ডার VOUCHER AUDIT, ৩টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/voucher-audit.jsx` — `VoucherAuditRegister` (তিনটা স্ক্রিনেই, `kind` দিয়ে)।
- **ঠিকানা:** `/finance-ledger/voucher-activity`, `/voucher-delete-history`, `/voucher-change-logs`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Http/Controllers/Api/VoucherAuditApiController.php` (নতুন)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো)

**Frontend**
- `frontend/src/components/finance/voucher-audit.jsx` (নতুন)
- `app/(app)/finance-ledger/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/VoucherAuditTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`VoucherAuditTest.php`, ১৭টা assertion):** test2 DB-তে পাস। পুরো Accounting (১৩টা টেস্ট, ৩৭৮টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- একটা ভাউচার তোলা, দুবার বদলানো (Bill No + Narration; তারপর Approved + লাইন ১০০ → ১৫০), পোস্ট; আরেকটা তুলে মোছা।
- Activity: DELETE, CREATE, POST, APPROVE, UPDATE, UPDATE, CREATE (নতুনটা আগে)। Deleted: একটা, JV-2026-000002, B-9, "২টা লাইন আর ১০০.০০ সহ মোছা"। Amended: দুটো — "Bill No, Narration" আর "Action Status, লাইন (১০০.০০ → ১৫০.০০)", কে করেছেন সহ।
- ভুল kind আটকায়; AUDITOR দেখেন; STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/fl06`; error 0; dev ডাটা রাখা আছে):** Voucher Entry-তে JV-2026-000003 (TEST-AUDIT-1, ৭৫০) তুলে narration আর অঙ্ক ৮০০ করে বদলানো, তারপর মোছা। Activity Trail-এ Raised → Amended ("Narration, lines (750.00 → 800.00)") → Removed ("2 line(s), 800.00"), সাথে আগের ভাউচারগুলোর কাজ; Deleted History-তে JV-2026-000003; Amendment Log-এ JV-2026-000003 আর CPV-2026-000001 ("Action Status")।

## ১০. খোলা প্রশ্ন

১. **সংশোধনের বিস্তারিত:** Brickland-এর নোট বলে "What a voucher looked like before an edit is not reconstructable from here"। আমরা প্রতিটা কাজের সাথে পুরো ভাউচার রাখি, তাই কোন ঘর বদলেছে তা Note-এ দেখাচ্ছি। আগের আর পরের পুরো ভাউচার পাশাপাশি দেখার পাতা চাইলে জানাবেন।

২. **পুরোনো ভাউচার:** লগ 23/03 থেকে চালু; তার আগের ভাউচারের কাজ নেই। প্রোডাকশনে এটা কোনো সমস্যা নয়, কারণ সেখানে শুরু থেকেই লগ থাকবে।

৩. **Excel / PDF:** Brickland-এ শুধু Activity Trail-এ PDF আর Excel; অন্য দুটোয় নেই, আমরাও রাখিনি (সব তালিকার নিজস্ব Export আছে)।

## ১১. As-built নোট

- **Brickland প্রোব:** আগের crawl-এ Activity Trail 503 দিয়েছিল; এবার তিনটারই স্পেক RSC payload থেকে — একই কলাম আর ফিল্টার, আর তিনটা নোট ("an audit trail that could be edited would not be one", "the row it pointed at is gone", "The amendments only")।
- **লগ আগেই চালু:** 23/03-এ লগ চালু রাখায় এখানে শুধু পড়ার স্ক্রিন লেগেছে।
- **UI চেকে ধরা পড়া:** কিছু নয়।
