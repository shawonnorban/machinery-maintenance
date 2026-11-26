# Voucher Entry

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/02`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Draft Voucher Entry | `/m/accounting/draft-voucher` | 2 | 17 | 0 |
| 2 | Voucher Posting Control | `/m/accounting/voucher-post-unpost` | 2 | 11 | 1 |
| 3 | Combined MRR Bill | `/m/accounting/mrr-combine-bill` | 3 | 16 | 1 |
| 4 | Statutory Voucher Register | `/m/accounting/compliance-vouchers` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Draft Voucher Entry

`/m/accounting/draft-voucher`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Voucher No# | text |  | voucher no... |
| Voucher Reference# | text |  | voucher ref... |
| Voucher Type | select |  | Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Voucher, Jo |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Trx Source | select |  | Head Office - Finance & Accounts, Head Office - Commercial, Factory - Store, Factory - HR & Payroll, System Generated |
| Posting Status | select |  | Draft, Unposted, Posted, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Draft Voucher Entry, Search, Refresh

**ট্যাব: Draft Voucher Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing draft voucher | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Voucher Date* | date | ✔ |  |
| Voucher Ref. Number | text |  | Issued on save |
| Voucher Type* | select | ✔ | Select…, Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Vo |
| Source Ref# | select |  | Select…, Head Office - Finance & Accounts, Head Office - Commercial, Factory - Store, Factory - HR & Payroll, System Gen |
| Last Voucher# | text |  | None yet |
| Narration | textarea |  |  |
| Action Status* | select | ✔ | Select…, Create Voucher, Submit For Approval, Approved, Rejected, On Hold |
- সেকশন: Draft Voucher Entry, Voucher Details Data
- বাটন/অ্যাকশন: Draft Voucher Entry, Add line, Clear, Save draft voucher

#### Voucher Posting Control

`/m/accounting/voucher-post-unpost`

**ট্যাব: Voucher Post/Unpost 0**

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
- বাটন/অ্যাকশন: Voucher Post/Unpost 0, Post / Unpost, New voucher, Search, Excel, Refresh

**ট্যাব: New voucher**
- সেকশন: New voucher
- বাটন/অ্যাকশন: Voucher Post/Unpost 0, Post / Unpost, New voucher, Reset, Save voucher

**ডায়ালগ: New voucher**
- খালি Save-এ মেসেজ: This screen posts vouchers that already exist. Raise one on the Voucher screen, with its lines.

#### Combined MRR Bill

`/m/accounting/mrr-combine-bill`

**ট্যাব: Mrr Combine Bill List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| MRR No | text |  | MRR No... |
| Bill No | text |  | Bill No... |
| From Date | date |  |  |
| To Issue Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Mrr Combine Bill List 0, Select Bill, Mrr Combine Bill Entry, -- Select Supplier --, Search, Add New, Refresh

**ট্যাব: Select Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  |  |
| Company | select |  |  |
| Bill NO | search |  |  |
| REF No | search |  |  |
- গ্রিড কলাম: Bill Ref#, REF No, Bill NO, DATE, NOTE, BILL AMT, PAY AMT, FINAL AMT, CUR., Action
- সেকশন: Search, Bill List
- বাটন/অ্যাকশন: Mrr Combine Bill List 0, Select Bill, Mrr Combine Bill Entry, Show Same Bill, Bill List

**ট্যাব: Mrr Combine Bill Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing MRR combine bill | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Bill No | text |  | Generated on save |
| Bill Date* | date | ✔ |  |
| MRR Type* | select | ✔ | General MRR, Yarn MRR, Dyes Chemical MRR, Trims & Accessories MRR, M/C Oil MRR, Needle MRR |
| Remarks | textarea |  |  |
| MRR No | text |  | -- Select MRR -- |
- সেকশন: Mrr Combine Bill Entry, Master Info, Detail Info
- বাটন/অ্যাকশন: Mrr Combine Bill List 0, Select Bill, Mrr Combine Bill Entry, -- Select Supplier --, -- Select --, Select…, Add MRR line, Cancel, Back To List, Clear, Save MRR combine bill

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing MRR combine bill | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Bill No | text |  |  |
| Bill Date* | date | ✔ |  |
| MRR Type* | select | ✔ | General MRR, Yarn MRR, Dyes Chemical MRR, Trims & Accessories MRR, M/C Oil MRR, Needle MRR |
| Remarks | textarea |  |  |
| MRR No | text |  |  |
- খালি Save-এ মেসেজ: A bill needs at least one MRR line.

#### Statutory Voucher Register

`/m/accounting/compliance-vouchers`

**ভিউ**
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/01-এর মাস্টার:** Chart of Accounts (শুধু postable আর active খাত লাইনে), Cost Centre, Fiscal Year (খোলা অর্থবছর), Maker-Checker grant (`acc_voucher_grants`), Statutory Ledger Rules (`acc_compliance_rules`)। `AccountingDefaults` দিয়ে খাত আর অর্থবছর আগে থেকে বসে।
- **মুদ্রা:** `currencies` মাস্টার আর `tx_exchange_rates` (22/01) থেকে হার; BDT = ১।
- **MRR-এর উৎস:** Store & Material GRN (`sm_grns`), Knitting yarn / oil / needle রসিদ, Dye House chemical রসিদ, Merchandise accessories MRR।
- **শেয়ার্ড কম্পোনেন্ট:** `Filters`, `Register`, `EditGrid` (21/02), `Box` (22/02), `printDocument`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_05_000100_create_acc_vouchers_and_mrr_bills.php`:

- **`acc_vouchers`:** voucher_no (অনুমোদনে, ধরন অনুযায়ী: `JV-2026-000001`), voucher_ref_no (সেভে: `VR-2026-000001`), তারিখ, ধরন (BPV, BRV, CPV, CRV, CV, BV, JV), উৎস (FINANCE / COMMERCIAL / STORE / HR / SYSTEM), bill no, voucher for (ধরন ও নাম), narration, action status, posting status (DRAFT / UNPOSTED / POSTED / CANCELLED), মোট, approved / posted by ও at, created / updated by। 23/03-এর Voucher Entry-ও এই টেবিলে লিখবে।
- **`acc_voucher_lines`:** খাত, কস্ট সেন্টার, bill no, narration, ref, মুদ্রা, হার, মূল্য (বিদেশি মুদ্রায়), DR, CR (BDT)।
- **`acc_mrr_bills`:** bill no (`MCB-2026-000001`), তারিখ, সাপ্লায়ার (`mp_suppliers`), MRR-এর ধরন (GENERAL / YARN / DYES / TRIMS / OIL / NEEDLE), Bill Prepared By (`hr_employees`), Created By (user), Bill Credit To (খাত), মন্তব্য, fixed asset, final।
- **`acc_mrr_bill_lines`:** Bill Debit To, MRR (ধরন + id, প্রতিটা MRR একটাই বিলে), নম্বর, তারিখ, PO / WO, দর, একক, মোট পরিমাণ, মোট টাকা, অন্য খরচ, VDS, TDS, নিট।

## ৫. Workflow / Status ও Business Rules

- **Draft Voucher Entry (Records / Draft Voucher Entry ট্যাব, রিলোড ছাড়া):**
  - হেডার আর লাইন একসাথে সেভ। প্রতিটা লাইনে DR অথবা CR, দুটো নয়; মোট DR = মোট CR; অন্তত একটা লাইন।
  - তারিখ খোলা অর্থবছরের ভেতরে (না থাকলে বা বন্ধ হলে আটকায়)।
  - সেভে শুধু Ref নম্বর (`VR-…`), ভাউচার নম্বর নেই, বদলানো আর মোছা যায়। "Existing draft voucher" দিয়ে আগের ড্রাফট ফর্মে ফেরে; ঠিকানায় `?no=VR-…`।
  - কারেন্সি বাছলে হার নিজে বসে; Last Voucher# দেখায় ওই ধরনের শেষ নম্বর।
  - Action Status = Approved হলে ভাউচার "commit" হয়: ধরন অনুযায়ী নম্বর (`JV-2026-000001`), ড্রাফট তালিকা থেকে সরে Unposted হয়ে Posting Control-এ যায়; তারপর পোস্টের আগ পর্যন্ত Voucher Entry (23/03)-তে বদলানো যায়।
  - **Maker-Checker:** যে ধরনের grant আছে সেখানে শুধু Maker ভাউচার তোলেন/বদলান, শুধু Checker অনুমোদন করেন, আর যিনি তুলেছেন তিনি নিজে অনুমোদন করতে পারেন না। grant না থাকলে voucher manage পারমিশনধারী যে কেউ।
- **Voucher Posting Control:**
  - **Voucher Post/Unpost ট্যাব:** commit হওয়া সব ভাউচার, Brickland-এর ১১টা ফিল্টার, Excel আর PDF (প্রিন্ট)।
  - **Post / Unpost ট্যাব:** বাঁয়ে কোম্পানি, ডানে তার ভাউচার; Post (মিল আবার যাচাই, খোলা অর্থবছর, অনুমোদিত হতে হবে — 23/03-এ যোগ) আর Unpost (নিশ্চিত করে)। পোস্ট হওয়া ভাউচার আবার পোস্ট হয় না।
  - **New voucher ট্যাব:** Brickland-এর মতো শুধু জানায়, নতুন ভাউচার Voucher স্ক্রিনে তুলতে হয়।
- **Combined MRR Bill (তালিকা / Select Bill / Entry):**
  - সাপ্লায়ার আর MRR-এর ধরন আগে; তারপর সেই সাপ্লায়ারের ওই ধরনের, অন্য বিলে না ওঠা রসিদ থেকে লাইন। লাইন উঠলে সাপ্লায়ার আর ধরন আর বদলায় না।
  - লাইনের তারিখ, PO / WO, দর, একক, পরিমাণ আর টাকা রসিদ থেকেই আসে (ক্লায়েন্টের পাঠানো সংখ্যা নয়)। নিট = মোট + অন্য খরচ − VDS − TDS; শূন্যের নিচে নয়।
  - কোন রসিদ ধরা হয়: GRN উল্টানো নয়; Yarn, Dyes (PURCHASE), Oil, Needle ড্রাফট বা বাতিল নয়; Trims শুধু Finalized (BDT-তে রূপান্তরিত)।
  - Print (A4 আড়াআড়ি, মোট আর স্বাক্ষরসহ), Mark as Fixed Asset, Finalize (নিশ্চিত করে)। চূড়ান্ত বিল বদলানো বা মোছা যায় না।
  - Select Bill: সাপ্লায়ার, বিল নং, রেফ (MRR নং) দিয়ে খুঁজে "Show Same Bill"; Select চাপলে এন্ট্রিতে খোলে। Bill NO কলামে রসিদের চালান নম্বর।
- **Statutory Voucher Register:** পোস্ট হওয়া ভাউচারের যে লাইনের খাতে Statutory Ledger Rule আছে — পরিমাণ, শতাংশ আর যোগ্য অংশ (পরিমাণ × % ÷ ১০০)।

## ৬. API ও পারমিশন

- **পারমিশন (নতুন):** `accounting.voucher.view` / `manage` / `post`, `accounting.bill.view` / `manage`। FACTORY_ADMIN আর FACTORY_MANAGER সব পায়; AUDITOR শুধু view; STOREKEEPER কিছু না।
- **API:**
  - `/accounting/vouchers` (তালিকা `?scope=draft`, POST, `{id}` GET / PUT / DELETE, `{id}/post`, `{id}/unpost`), `options`, `last-no?type=`, `posting`, `statutory`
  - `/accounting/mrr-bills` (তালিকা, POST, `{id}` GET / PUT / DELETE, `{id}/final`, `{id}/fixed-asset`), `options`, `mrrs?type=&supplier=&except=`
- **মেনু:** Finance & Ledger-এর নিচে নতুন ফোল্ডার VOUCHER ENTRY, ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/vouchers.jsx` (`DraftVoucherDesk`, `PostingDesk`, `StatutoryRegister`), `components/finance/mrr-bills.jsx` (`MrrBillDesk`)। প্রতিটার ট্যাব কম্পোনেন্টের ভেতরে, রিলোড ছাড়া বদলায়।
- **পেজ:** `app/(app)/finance-ledger/[screen]` — নতুন স্ক্রিনের শিরোনাম `accounting.vo.*` থেকে, পারমিশন পেজ থেকে পাঠানো।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Database/Migrations/2027_03_05_000100_create_acc_vouchers_and_mrr_bills.php` (নতুন)
- `Models/AccVoucher.php`, `Models/AccMrrBill.php` (নতুন)
- `Support/MrrSources.php` (নতুন)
- `Http/Controllers/Api/VoucherApiController.php`, `MrrBillApiController.php` (নতুন)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো: নতুন `vo` গ্রুপ)

**Frontend**
- `frontend/src/components/finance/vouchers.jsx`, `mrr-bills.jsx` (নতুন)
- `app/(app)/finance-ledger/[screen]/screens.js`, `page.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/VoucherEntryTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`VoucherEntryTest.php`, ৩টা টেস্ট):** test2 DB-তে পাস। পুরো Accounting (৭টা টেস্ট, ২১৩টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **ড্রাফট থেকে পোস্ট:** লাইন ছাড়া, এক লাইনে DR আর CR দুটোই, না মেলা (৫০০ / ৪০০), অর্থবছরের বাইরের তারিখ আটকায়। ড্রাফট `VR-2026-000001`, নম্বর নেই। Approved হলে `JV-2026-000001`, Unposted, ড্রাফট তালিকা খালি; Last Voucher# মেলে; আর বদলানো বা মোছা যায় না। Post → Posted; আবার Post আটকায়; Unpost → আবার Post। ১০% rule-এ Statutory register-এ ৫০০-র ৫০। দ্বিতীয় ড্রাফট `VR-2026-000002`, মোছা যায়।
- **Maker-Checker:** মেকার নন এমন ব্যবহারকারী BPV তুলতে পারেন না; চেকার নন এমন কেউ অনুমোদন করতে পারেন না; মেকার-চেকার দুটোই এমন ব্যবহারকারী নিজের ভাউচার অনুমোদন করতে পারেন না; চেকার অন্যের ড্রাফট অনুমোদন করেন (`BPV-2026-000001`)। grant ছাড়া CPV সরাসরি অনুমোদিত। AUDITOR দেখেন, তুলতে বা পোস্ট করতে পারেন না; STOREKEEPER কিছুই না।
- **Combined MRR Bill:** Oil রসিদের তালিকায় শুধু এই সাপ্লায়ারের চূড়ান্ত রসিদ (ড্রাফট আর অন্য সাপ্লায়ারেরটা নয়), দর ৫০, একক LTR। লাইন ছাড়া, অন্য সাপ্লায়ারের MRR, ভুল ধরন, নিট শূন্যের নিচে আটকায়। `MCB-2026-000001`, নিট ৫০০০ + ২০০ − ১০০ − ৫০ = ৫০৫০, চালান CH-OMRR-1। একই MRR অন্য বিলে আটকায়, তালিকা থেকে সরে। দ্বিতীয় লাইনে মোট ৫৮০০, নিট ৫৮৫০; Fixed Asset; Finalize-এর পর বদলানো বা মোছা আটকায়।

**UI চেক (`app_check/plans/fl02`, `fl02b`, `fl02c`; error 0; dev ডাটা রাখা আছে):**
- **ড্রাফট:** JV, Head Office - Finance; Accessories Stock DR ১৩,৮৭৮ / Accounts Payable CR ১৩,৮৭৮ → `VR-2026-000001`; তারপর Approved → `JV-2026-000001`, Unposted।
- **পোস্টিং:** Post / Unpost ট্যাবে Delta Apparels → Post → Posted, পাশে Unpost।
- **MRR বিল:** KOYO TRIMS, Trims & Accessories MRR, MRR-ACC-2026100001 আর -100002, Other Cost ১৫০, VDS ২০০ → `MCB-2026-000001`, মোট ১৩,৮৭৮, নিট ১৩,৮২৮। Print-এর PDF দেখে নেওয়া হয়েছে।

## ১০. খোলা প্রশ্ন

১. **General MRR-এর উৎস:** Store & Material-এর GRN ধরা হয়েছে, কারণ General Store-এর সাপ্লায়ার আলাদা মাস্টারে (`gs_suppliers`) আর বিলের সাপ্লায়ার `mp_suppliers` থেকে। General Store-এর রসিদও আনতে হবে কিনা জানাবেন। এখন dev-এর GRN-এ দর না থাকায় মোট ০ আসে।

২. **কোন রসিদ বিলযোগ্য:** Trims শুধু Finalized; Yarn, Dyes, Oil, Needle ড্রাফট বা বাতিল ছাড়া সব; GRN উল্টানো ছাড়া। ঠিক আছে কিনা?

৩. **Yarn-এর একক:** সুতার রসিদে একক আলাদা করে নেই, তাই KG ধরা হয়েছে।

৪. **চূড়ান্ত MRR বিল থেকে ভাউচার:** Brickland-এ Finalize-এর পর চেক লেখা হয়, কিন্তু নিজে থেকে Bill Voucher তৈরি হয় কিনা দেখা যায়নি। চাইলে Finalize-এ Debit To / Credit To দিয়ে BV ড্রাফট বানানো যায়।

৫. **Select Bill-এর কলাম:** Brickland-এ Bill Ref#, REF No আর Bill NO তিনটা আলাদা কলাম, কিন্তু ফর্মে একটাই বিল নম্বর। আমরা দেখিয়েছি: বিল নম্বর, MRR নম্বর আর রসিদের চালান নম্বর। PAY AMT এখন ০ (চেক 23/05-এ আসবে)।

৬. **ব্যাংক লাইন ক্লিয়ার হলে Unpost আটকানো:** ✅ 23/04 Bank Statement Reconciliation-এর সাথে যোগ হয়েছে।

৭. **Maker-Checker ছাড়া অনুমোদন:** কোনো ধরনের grant না থাকলে voucher manage পারমিশনধারী যে কেউ, নিজের ভাউচারও, অনুমোদন করতে পারেন। grant না থাকলেও নিজে অনুমোদন আটকাতে চাইলে জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** চারটা স্ক্রিনের পুরো স্পেক RSC payload থেকে (`netjson` → `rsc_view` → `rsc_brief`): কলাম, ফিল্টার, প্যানেল, লাইনের কলাম, record action আর তাদের নিশ্চিতকরণের লেখা। demo-তে কোনো রেকর্ড নেই।
- **মূল Voucher Entry স্ক্রিন (23/03):** Brickland-এর মেনুতে Draft আর Posting-এর মাঝে আছে, কিন্তু আমাদের ডকে 23/03-এ। একই `acc_vouchers` টেবিলের জন্য তার স্পেকও আগে পড়ে নেওয়া হয়েছে (bill no, voucher for আছে)।
- **ট্যাব:** Brickland-এর মতো Records / Entry, কম্পোনেন্টের ভেতরে, রিলোড ছাড়া।
- **UI চেকে ধরা পড়া:** অনুমোদনের সময় চেকারের কাছেও মেকারের grant চাওয়া হচ্ছিল — এখন অনুমোদনে শুধু চেকার যাচাই হয়। Post / Unpost প্যানেল পাতার বাইরে যাচ্ছিল — গ্রিড কলাম ঠিক করা হয়েছে।
