# Cash Incentive

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন)
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `22/05`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Cash Incentive Claims | `/m/commercial/cash-incentives` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Cash Incentive Claims

`/m/commercial/cash-incentives`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| LC/SC No. | text |  |  |
| Claim SL No. | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Items per page | select |  | 10, 20, 50, 100 |
- গ্রিড কলাম: Action, Finalized ▼, LC/SC No. ▼, CIA Type ▼, Claim SL No ▼, Claim Sub. Date ▼, Doc Sub. Date ▼, Audt. Cert. Date ▼, Close Date ▼, Remarks ▼, Audt. Amount ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search, ⟳
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **22/02-এর Export L/C (`tx_export_lcs`):** দাবি হয় L/C-র বিপরীতে; L/C নম্বর, ইস্যুর তারিখ, মূল্য, Port of Discharge (গন্তব্য), Advising/Lien Bank এখান থেকে আসে।
- **22/02-এর Commercial Invoice আর Bank Bill Realisation:** ইনভয়েসের মোট মূল্য, আদায়ের মূল্য, সর্বনিম্ন হার আর সর্বশেষ আদায়ের তারিখ এগুলো থেকে হিসাব হয়।
- **22/02-এর `Box`, `Row`, `ReadGrid`, `Filters`, `Register`:** তালিকা আর "Manage Cash Incentive" একই পেজে।
- **ফাইল আপলোড:** `app/api/trade-export/bill-attachment/route.js` আর `StoreFileAttachment` (PRC ডকুমেন্ট)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_04_000100_create_tx_cash_incentives.php`:

- **`tx_cash_incentives` (নতুন):** L/C, CIA Type (REGULAR / SPECIAL), Claim Part No (১–৪), Application Serial No (কোম্পানিতে অনন্য), আবেদনের তারিখ, কাগজ জমার তারিখ, দাবির পরিমাণ ও মুদ্রা, Certificate No/Bank Ref, অডিট সার্টিফিকেটের মুদ্রা, তারিখ ও পরিমাণ, আদায়ের তারিখ, মন্তব্য, final। একই L/C-তে একই ধরনের একই Part একবারই।
- **`tx_export_lcs`-এ নতুন ঘর (PRC):** `prc_no`, `prc_date`, `prc_entry_date` (প্রথমবার PRC দেওয়ার দিন), `prc_file_id`, `prc_file_name`।

## ৫. Workflow / Status ও Business Rules

- **তালিকা:** Finalized, LC/SC No, CIA Type, Claim SL No, Claim Sub. Date, Doc Sub. Date, Audt. Cert. Date, Close Date (আদায়ের তারিখ), Remarks, Audt. Amount; LC/SC, Claim SL আর তারিখ দিয়ে ফিল্টার। সারির Edit দাবিটা তার L/C সহ খোলে।
- **Manage Cash Incentive (একই পেজে):**
  - LC/SC No লিখে Search (বড়-ছোট হাতের অক্ষর যাই হোক; পুরো না মিললে অংশ মিলিয়ে)। না পেলে "No credit matches"।
  - **LC/SC Info:** L/C নং, Beneficiary, গন্তব্য, ইস্যুর তারিখ, Applicant (buyer), মূল্য, Bank (Advising/Lien)।
  - **Invoice & Bank Realization:** ইনভয়েসের মোট (L/C-র সব ইনভয়েস), আদায় (প্রতিটা বিলের চূড়ান্ত realisation একবার — FINAL থাকলে সেটা, না হলে EARLY; 22/06-এ ঠিক করা), সর্বনিম্ন ODS হার, % আদায়। CIA Notification Date Brickland-এর মতো ফাঁকা।
  - **PRC Info:** PRC ID, ইস্যুর তারিখ, ডকুমেন্ট (না থাকলে লাল "Document Not Found"), Entry Date, সর্বশেষ আদায়ের তারিখ, আর Count/Down: সর্বশেষ আদায় থেকে ১৮০ দিনের কত দিন বাকি (পার হলে "Overdue")। "Manage PRC" L/C-টা Export L/C স্ক্রিনে খোলে।
  - **দাবি:** CIA Type (Regular ৪ / Special ১), Part No, Serial No আর আবেদনের তারিখ বাধ্যতামূলক। কাগজ জমা, অডিট আর আদায়ের তারিখ আবেদনের আগে নয়। Collection Amount = Audit Cert. Amount।
  - Finalized টিক দিলে আদায়ের তারিখ লাগে; চূড়ান্ত দাবি লক।
  - নিচের গ্রিডে L/C-র সব দাবি, প্রতিটায় Edit (✎), আর মোট দাবি ও অডিট পরিমাণ।
  - ঠিকানায় `?lc=LC-0098812`; সেই ঠিকানা খুললে সরাসরি সেই L/C।
- **PRC (Export L/C ফর্মে):** L/C খুললে নিচে PRC Info বক্স: PRC ID, ইস্যুর তারিখ, ডকুমেন্ট, Entry Date, আলাদা "Save PRC"। L/C Complete (লক) হলেও PRC দেওয়া যায়। PRC ID-র সাথে তারিখ লাগে; PRC ID ছাড়া ডকুমেন্ট নেয় না।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন কিছু নেই; 22/02-এর `tradeexport.export.view` / `manage`। PRC ডকুমেন্টের ডাউনলোড `tx_export_lc_prc` হিসেবে একই পারমিশনে।
- **API (`/tradeexport/ops/…`):**
  - `cash-incentives` (তালিকা, POST, `{id}` PUT), `cash-incentives/credit?lc=`
  - `lcs/{lc}/prc` (PUT), `lcs/{lc}/prc/attachment` (POST)
- **মেনু:** Trade & Export-এর নিচে নতুন ফোল্ডার CASH_INCENTIVE, ১টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/tradeexport/cash-incentive.jsx`: `CashIncentiveDesk` (তালিকা) আর `Manage`।
- **PRC বক্স:** `operations.jsx`-এর `PrcBox`, Export L/C ফর্মের নিচে।
- **আপলোড:** `app/api/trade-export/bill-attachment/route.js` (`?kind=prc`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন):

**Backend (`app/Modules/TradeExport`)**
- `Database/Migrations/2027_03_04_000100_create_tx_cash_incentives.php` (নতুন)
- `Models/TxCashIncentive.php` (নতুন), `Models/TxExportLc.php` (বদলানো: PRC)
- `Http/Controllers/Api/CashIncentiveApiController.php` (নতুন)
- `Http/Controllers/Api/ExportCreditApiController.php`, `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো: `tx_export_lc_prc`)
- `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/tradeop.php`, `lang/bn/tradeop.php` (বদলানো)

**Frontend**
- `frontend/src/components/tradeexport/cash-incentive.jsx` (নতুন)
- `frontend/src/components/tradeexport/operations.jsx`, `app/api/trade-export/bill-attachment/route.js`, `app/(app)/trade-export/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/TradeExport/TradeExportOperationsTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`test_cash_incentive_claims_and_the_lcs_prc`):** test2 DB-তে পাস, ৩৬টা assertion। পুরো Trade & Export (১৫টা টেস্ট, ৩৭২টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **L/C খোঁজা:** না মিললে ফাঁকা; `lc-1` লিখলে LC-1। ইনভয়েস ১০০০, আদায় ৮০০ (৮০%), সর্বনিম্ন হার ১১৮, সর্বশেষ আদায় ৮ নভেম্বর, ১০ নভেম্বরে "178 days left"।
- **দাবি:** Part ছাড়া, আবেদনের আগে কাগজ জমা, আদায় ছাড়া Finalized আটকায়। একই Serial বা একই ধরনের একই Part আটকায়; Special-এ Part 1 চলে। অডিট ৪৫০০ আর আদায়সহ Final; তারপর বদলানো আটকায়। তালিকায় LC-1, আদায়ের তারিখ, অডিট পরিমাণ।
- **PRC:** PRC ছাড়া ডকুমেন্ট আটকায়; তারিখ ছাড়া PRC আটকায়; Entry Date = আজ; ডকুমেন্ট সংযুক্ত, TECHNICIAN ডাউনলোড পান না; L/C-র তথ্যেও PRC দেখায়।
- **কে পারেন:** AUDITOR দাবি বানাতে পারেন না।

**UI চেক (`app_check/plans/tx09`, `tx09b`; error 0; dev ডাটা রাখা আছে):**
- **PRC:** LC-0098812-এ PRC-2026-0441, ৯ নভেম্বর ২০২৬, PDF সংযুক্ত; Entry Date ১০ নভেম্বর।
- **দাবি:** `lc-0098812` খুঁজে CIA-2026-0101: Regular (4), Part 1, আবেদন ও কাগজ জমা ১০ নভেম্বর, দাবি ১৪০.৬০। Invoice ১,৮৫০, আদায় ১,৮০০ (৯৭.৩%), হার ১১৯.৫, "179 days left"।
- তালিকার Edit দাবিটা `?lc=LC-0098812` ঠিকানায় "Update Claim" সহ খোলে।

## ১০. খোলা প্রশ্ন

১. **PRC কোথায় রাখা হবে:** Brickland-এর "Manage PRC" Export L/C স্ক্রিনে নিয়ে যায়, কিন্তু সেখানে PRC-র কোনো ঘর নেই। আমরা Export L/C ফর্মের নিচে PRC Info বক্স রেখেছি (PRC ID, ইস্যুর তারিখ, ডকুমেন্ট, আলাদা Save PRC)। অন্য কোথাও রাখতে চাইলে জানাবেন।

২. **Count/Down-এর নিয়ম:** সর্বশেষ আদায়ের তারিখ থেকে ১৮০ দিন ধরে বাকি দিন দেখানো হয়েছে। আসল সময়সীমা কত, আর কোন তারিখ থেকে গোনা হবে (আদায়, না PRC)?

৩. **CIA Notification Date:** Brickland-এ এটা সবসময় ফাঁকা। কোথা থেকে আসবে?

৪. **Finalize-এর শর্ত:** আমরা আদায়ের তারিখ ছাড়া দাবি Final হতে দিইনি। ঠিক আছে কিনা জানাবেন।

৫. **দাবির পরিমাণ:** Regular (4) আর Special (1) মানে কি ৪% আর ১%? হলে আদায়ের মূল্য থেকে দাবির পরিমাণ নিজে থেকে বসবে কিনা? এখন হাতে লেখা হয়।

৬. **দাবি মোছা:** Brickland-এ দাবি মোছার বাটন নেই, তাই আমরাও রাখিনি। ভুল ড্রাফট মোছা লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** তালিকা `/cash-incentives`, ফর্ম `/cash-incentives/manage` (`panel.mjs`)। demo-তে কোনো দাবি নেই, তাই ফর্মের ঘর, গ্রিড, বার্তা আর লিংক `jsgrep.mjs` দিয়ে JS chunk থেকে পড়া।
- **Brickland-এ সেভ না হওয়া ঘর:** Claim Part No, Certificate No/Bank Ref আর Audit Cert. Currency Brickland-এর ফর্মে আছে কিন্তু সেভ হয় না। scaffold পুরো বানানোর নিয়মে আমরা তিনটাই সেভ করি।
- **Collection Amount:** Brickland-এর মতো Audit Cert. Amount-ই দেখায়।
