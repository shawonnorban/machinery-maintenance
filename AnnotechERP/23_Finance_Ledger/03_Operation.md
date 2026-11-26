# Operation

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন)
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `23/03`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Voucher Entry | `/m/accounting/voucher` | 2 | 23 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Voucher Entry

`/m/accounting/voucher`

**ট্যাব: Records 0**

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
- বাটন/অ্যাকশন: Voucher Entry, Search, Refresh

**ট্যাব: Voucher Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing voucher | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Voucher Date* | date | ✔ |  |
| Voucher Ref. Number | text |  | Issued on save |
| Voucher Type* | select | ✔ | Select…, Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Vo |
| Source Ref# | select |  | Select…, Head Office - Finance & Accounts, Head Office - Commercial, Factory - Store, Factory - HR & Payroll, System Gen |
| Last Voucher# | text |  | None yet |
| Bill No | text |  |  |
| Voucher For Type | select |  | Select…, General, Supplier, Buyer, Employee, Party |
| Voucher For | text |  |  |
| Narration | textarea |  |  |
| Action Status* | select | ✔ | Select…, Create Voucher, Submit For Approval, Approved, Rejected, On Hold |
- সেকশন: Voucher Entry, Voucher Details Data
- বাটন/অ্যাকশন: Voucher Entry, Add line, Clear, Save voucher
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **23/02-এর ভাউচার:** একই `acc_vouchers` / `acc_voucher_lines` টেবিল, একই কন্ট্রোলার (`VoucherApiController`) আর একই ডেস্ক (`VoucherDesk`, `mode="voucher"`)। মিল, অর্থবছর আর Maker-Checker-এর নিয়ম হুবহু এক।
- **Voucher Posting Control (23/02):** এখানে তোলা ভাউচার সেখানেই পোস্ট / আনপোস্ট হয়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_06_000100_create_acc_voucher_logs.php`:

- **`acc_voucher_logs` (নতুন):** প্রতিটা ভাউচারের প্রতিটা কাজ — CREATE, UPDATE, APPROVE, POST, UNPOST, DELETE — সাথে তখনকার পুরো ভাউচার (হেডার আর লাইন, JSON), কে করেছেন আর কখন। মোছা ভাউচারের লগ থেকে যায় (foreign key নেই)। 23/06 Voucher Audit (Deleted Voucher History, Voucher Amendment Log, Voucher Activity Trail) এখান থেকে পড়বে।
- ভাউচারের ঘর 23/02-এর মতোই; এই স্ক্রিন বাড়তি দেয় Bill No, Voucher For Type আর Voucher For।

## ৫. Workflow / Status ও Business Rules

- **Records আর Voucher Entry ট্যাব** (রিলোড ছাড়া)। তালিকায় commit হওয়া সব ভাউচার: Voucher & Ref, Date, Company, Type & Source, Bill No, Amount, Post Status, Prepared By, Last Update; Brickland-এর ১১টা ফিল্টার।
- **সেভেই commit:** নম্বর সেভে আসে (`CPV-2026-000001`, সাথে Ref `VR-…`), ড্রাফট তালিকায় যায় না, Unposted।
- **বদলানো:** পোস্টের আগ পর্যন্ত বদলানো আর মোছা যায়; নম্বর একই থাকে, কিন্তু ধরন বদলানো যায় না (নম্বর ধরন অনুযায়ী)। "Existing voucher"-এ শুধু আনপোস্টেড ভাউচার। পোস্ট হওয়া ভাউচার খুললে শুধু দেখা যায়, সাথে "আনপোস্ট করে বদলান" বার্তা। ঠিকানায় `?no=CPV-…`।
- **অনুমোদন আর পোস্ট:** Action Status Approved হলে অনুমোদিত (Maker-Checker 23/02-এর মতো); অন্য কোনো Action Status দিয়ে সেভ করলে অনুমোদন মুছে যায়। **শুধু অনুমোদিত ভাউচার পোস্ট হয়** — ড্রাফট থেকে আসা ভাউচারও অনুমোদিত হয়েই আসে।
- **লগ:** তৈরি, বদল, অনুমোদন, পোস্ট, আনপোস্ট আর মোছা — প্রতিটায় তখনকার ভাউচারের ছবি রাখা হয়।

## ৬. API ও পারমিশন

- **পারমিশন:** 23/02-এর `accounting.voucher.view` / `manage` / `post`।
- **API:** 23/02-এর `/accounting/vouchers` — POST-এ `commit: true` পাঠালে সেভেই নম্বর; PUT একটা আনপোস্টেড ভাউচারকে commit অবস্থাতেই রাখে; DELETE ড্রাফট বা আনপোস্টেড; `{id}/post` অনুমোদন ছাড়া আটকায়।
- **মেনু:** VOUCHER ENTRY ফোল্ডারে Draft Voucher Entry-র পরে "Voucher Entry" (Brickland-এর ক্রমে)।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/finance/vouchers.jsx` — `VoucherEntryDesk` (= `VoucherDesk mode="voucher"`); Draft Voucher Entry একই ডেস্কের `mode="draft"`।
- **ঠিকানা:** `/finance-ledger/voucher`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন):

**Backend (`app/Modules/Accounting`)**
- `Database/Migrations/2027_03_06_000100_create_acc_voucher_logs.php` (নতুন)
- `Http/Controllers/Api/VoucherApiController.php` (বদলানো: commit, অনুমোদন ছাড়া পোস্ট নয়, লগ)
- `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/accounting.php`, `lang/bn/accounting.php` (বদলানো)

**Frontend**
- `frontend/src/components/finance/vouchers.jsx` (বদলানো: `VoucherDesk`, `VoucherEntryDesk`)
- `app/(app)/finance-ledger/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/Accounting/VoucherEntryTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`test_voucher_entry_commits_on_save_and_posts_once_approved`):** test2 DB-তে পাস। পুরো Accounting (৮টা টেস্ট, ২৩২টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- সেভেই `CPV-2026-000001`, Unposted, Bill No / Voucher For সহ; ড্রাফট তালিকা খালি।
- অনুমোদনের আগে Post আটকায়; ধরন বদলানো আটকায়; Approved আর বিবরণ বদলে সেভ — নম্বর একই। পোস্টের পর বদলানো বা মোছা আটকায়।
- দ্বিতীয় ভাউচার `CPV-2026-000002` মোছা যায়। প্রথমটার লগ CREATE → UPDATE → APPROVE → POST; মোছারটার লগে নম্বর, Voucher For আর দুটো লাইন রয়ে যায়।
- 23/02-এর ড্রাফট টেস্টও নতুন নিয়মে মেলানো: অনুমোদিত (commit) ভাউচার পোস্টের আগে নম্বর আর অনুমোদন রেখে বদলানো যায়।

**UI চেক (`app_check/plans/fl03`; error 0; dev ডাটা রাখা আছে):** CPV, Bill No UTL-2611, Supplier · Dhaka Electric Supply; Utility Bill Payable DR ৪৮,৫০০ / Cash In Hand CR ৪৮,৫০০ → সেভেই `CPV-2026-000001` (Ref `VR-2026-000002`); Approved; Posting Control-এ Post → Posted; `?no=CPV-2026-000001` খুললে লক আর বার্তা।

## ১০. খোলা প্রশ্ন

১. **পোস্টের আগে অনুমোদন:** Brickland-এর ফর্মে Action Status আছে কিন্তু পোস্টের শর্ত দেখা যায়নি। আমরা শুধু অনুমোদিত (Approved) ভাউচার পোস্ট করতে দিচ্ছি। অনুমোদন ছাড়াও পোস্ট চাইলে জানাবেন।

২. **নম্বর পাওয়া ভাউচার মোছা:** পোস্টের আগে মোছা যায়, তাতে নম্বরের ক্রমে ফাঁক থাকে (লগে মোছা ভাউচার রয়ে যায়)। মোছার বদলে "Cancelled" করতে চাইলে জানাবেন।

৩. **Voucher For:** Brickland-এর ফিল্টারে "-- Select --" দেখায়, অর্থাৎ ধরন অনুযায়ী তালিকা (সাপ্লায়ার, ক্রেতা, কর্মী) হতে পারে। এখন লিখে দেওয়ার ঘর।

## ১১. As-built নোট

- **Brickland প্রোব:** RSC payload থেকে স্পেক (23/02-এর সাথেই পড়া): তালিকা "Vouchers committed to the ledger", নোট "The register here is what Post/Unpost acts on"।
- **ড্রাফটের সাথে একই কোড:** একই টেবিল, কন্ট্রোলার আর ডেস্ক; ড্রাফট অনুমোদনে commit হয়, এখানে সেভেই।
- **আগাম লগ:** 23/06-এর ইতিহাস স্ক্রিন যাতে শুরু থেকে সব পায়, তাই লগ এখনই চালু।
- **UI চেকে ধরা পড়া:** সার্ভিস রিস্টার্টের পর লগইন আটকেছিল (nginx পুরোনো ব্যাকএন্ড ধরে ছিল) — nginx রিস্টার্টে ঠিক।
