# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `22/01`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Document Reference Types | `/m/commercial/reference-type` | 1 | 0 | 0 |
| 2 | Buyer-Wise Notify Party | `/m/commercial/buyer-wise-notify-party` | 1 | 15 | 0 |
| 3 | L/C Applicant Master | `/m/commercial/lc-applicant` | 1 | 6 | 0 |
| 4 | Currency Exchange Rates | `/m/commercial/loc-exchng-rates` | 1 | 5 | 0 |
| 5 | Bank Directory | `/m/commercial/bank-list` | 1 | 2 | 2 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Document Reference Types

`/m/commercial/reference-type`

**ভিউ**
- সেকশন: Select Reference

#### Buyer-Wise Notify Party

`/m/commercial/buyer-wise-notify-party`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | text |  | search... |
| Buyer Name | text |  | Pick a buyer on the left |
| * Party Name(EN) | text |  |  |
| * Address | text |  |  |
| Post Code | text |  |  |
| Work Phone # | text |  |  |
| Fax # | text |  |  |
| Web Url | text |  |  |
| Code | text |  | Generated on save |
| Party Name(BN) | text |  |  |
| Country | select |  | Bangladesh, Belgium, Brazil, Bulgaria, Canada, China, Denmark, France, Germany, Hong Kong, India, Italy, Japan, Japan, K |
| PO Box No | text |  |  |
| Mobile # | text |  |  |
| E-mail | email |  |  |
| Is Active? | checkbox |  |  |
- গ্রিড কলাম: Buyer ▼, Country ▼, Code ▼, Name ▼, Phone ▼, Mobile ▼, Fax ▼, E-mail ▼, Web-Url ▼, Status ▼, Action
- সেকশন: Select Buyer, Master Data, Notify Party List
- বাটন/অ্যাকশন: ⌕, Save, Cancel

#### L/C Applicant Master

`/m/commercial/lc-applicant`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| * Buyer Name | select |  | LPP |
| * Applicant Name(EN) | text |  |  |
| Applicant Name(BN) | text |  |  |
| * L/C Address | textarea |  |  |
| Is Active? | checkbox |  |  |
| Items per page | select |  | 10, 20, 50, 100 |
- গ্রিড কলাম: Buyer ▼, Applicant Name (EN) ▼, Applicant Name (BN) ▼, Address ▼, Status ▼, Action
- সেকশন: Master Data, Applicant List
- বাটন/অ্যাকশন: Save, Cancel, ⟳

#### Currency Exchange Rates

`/m/commercial/loc-exchng-rates`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company Name | select |  | Brickland Composite Ltd. |
| Rate For | select |  | Export Realization, Import Payment, Local Sales, Cash Incentive |
| From Date | date |  |  |
| To Date | date |  |  |
| Items per page | select |  | 10, 20, 50, 100 |
- গ্রিড কলাম: Company ▼, Rate By ▼, Exchng Rate ▼, Effective Date ▼, Created On ▼, Remarks ▼, Action
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search, ⟳

#### Bank Directory

`/m/commercial/bank-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bank Code | text |  |  |
| Bank Name | text |  |  |
- গ্রিড কলাম: Branches, Action, BANK CODE ▼, Bank Name (EN) ▼, Swift Code ▼, Active ▼
- সেকশন: Searching Data, Bank List
- বাটন/অ্যাকশন: + Add New, 🔍 Search, ▸, ✎ Edit Bank, + New Branch

**ডায়ালগ: + Add New** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| * Bank Code | text |  |  |
| * Bank Name (EN) | text |  |  |
| Bank Name (BN) | text |  |  |
| * Bank Prefix | text |  |  |
| Is Local | checkbox |  |  |
| * Country | select |  | Bangladesh, Belgium, Brazil, Bulgaria, Canada, China, Denmark, France, Germany, Hong Kong, India, Italy, Japan, Japan, K |
| * Swift Code | text |  |  |
| IBN No | text |  |  |
| Call Center No | text |  |  |
| Web Url | text |  |  |
| Is Active | checkbox |  |  |

**ডায়ালগ: + New Branch** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| * Branch Code | text |  |  |
| * Branch Name | text |  |  |
| Address | text |  |  |
| Swift Code | text |  |  |
| Routing No | text |  |  |
| Contact Person | text |  |  |
| Phone | text |  |  |
| E-mail | text |  |  |
| Is Active | checkbox |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/TradeExport`, সব টেবিল `tx_` নামে।
- পুরনো প্রজেক্টের Compliance মডিউল (মেনুতে "TRADE", bond/duty) এখানে ব্যবহার করা হয়নি। এর জন্য নতুন মেনু মডিউল `TRADE_EXPORT`।
- **অন্য ERP মডিউল থেকে নেওয়া:** Merchandise Desk-এর buyer আর origin country। Brickland-এর দেশের তালিকা হুবহু এক।
- **প্ল্যাটফর্মের অংশ, যা সবাই ব্যবহার করে:** Settings-এর মুদ্রা। বিনিময় হারের এন্ট্রিতে মুদ্রার `exchange_rate`-ই নেওয়া হয়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_07_000100_create_trade_export_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `tx_reference_entries` | list (INCO_TERM/PAYMENT_TERM/DELIVERY_PLACE/TRANSIT_PORT/LC_TYPE/SHIPMENT_MODE/BUSINESS_ASSOCIATION/FORWARDER), code (সেভের সময় তৈরি), name, name_bn, short_name, display_order, active |
| `tx_notify_parties` | buyer_id, code (NP-0001 …), name, name_bn, address, country_id, post_code, po_box, phone, mobile, fax, email, web_url, active |
| `tx_lc_applicants` | buyer_id, name, name_bn, address, active |
| `tx_exchange_rates` | rate_for (EXPORT_REALIZATION/IMPORT_PAYMENT/LOCAL_SALES/CASH_INCENTIVE), currency, rate, effective_date, remarks |
| `tx_banks` | code, name, name_bn, prefix, is_local, country_id, swift_code, iban, call_center, web_url, active |
| `tx_bank_branches` | bank_id, code, name, address, swift_code, routing_no, contact_person, phone, email, active |

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা (Brickland-এর মতো):**
  - সাতটা রেফারেন্স তালিকার এন্ট্রি: ৫টা ইনকোটার্ম, ৬টা পেমেন্ট টার্ম, ৩টা ডেলিভারির স্থান, ৩টা ট্রানজিট বন্দর, ৪টা L/C-র ধরন, ৩টা শিপমেন্টের মাধ্যম, ৩টা সংগঠন। ফরওয়ার্ডারের তালিকা খালি।
  - রেজিস্টারের ২৬টা ব্যাংক। প্রথমে ২০টা ধরা হয়েছিল, কারণ Brickland-এর Commercial তালিকায় শুধু প্রথম পাতার ২০টা দেখা যায়।
  - প্রথমবার খোলার সময় তৈরি হয়। একসাথে কয়েকটা রিকোয়েস্ট এলেও দুবার হয় না।
- **কোনো এন্ট্রিই মোছা যায় না।** ⊘ দিয়ে নিষ্ক্রিয় করা যায়, আবার সক্রিয়ও করা যায় (Brickland-এর মতো)।
- **Document Reference Types:**
  - কোড সেভের সময় সংক্ষিপ্ত নাম থেকে তৈরি হয়: বড় হাতের, আর আগে থেকে থাকলে শেষে -2, -3 যোগ হয়।
  - একটা তালিকায় একই সংক্ষিপ্ত নাম দুবার চলে না।
  - এন্ট্রি অন্য তালিকায় সরানো যায় না।
- **Buyer-Wise Notify Party:**
  - বায়ার বাছাই না করলে ফর্ম আর তালিকা আসে না।
  - কোড NP-0001 ধারায় নিজে থেকে দেওয়া হয়।
  - ই-মেইল যাচাই করা হয়।
- **L/C Applicant Master:** একজন বায়ারের একই নামের আবেদনকারী দুবার চলে না।
- **Currency Exchange Rates:**
  - হার আসে Settings-এ মুদ্রার নিজের হার থেকে। ফর্মে হার শুধু দেখা যায়, বদলানো যায় না; সেভের সময় হারটা কপি হয়ে থাকে।
  - মুদ্রার হার দেওয়া না থাকলে সেভ হয় না।
  - একটা মুদ্রা, কাজ আর দিনে একটাই হার।
  - তালিকার ফিল্টার শুরুতে গত মাসের প্রথম দিন থেকে এই মাসের শেষ দিন পর্যন্ত (Brickland-এর মতো)।
- **Bank Directory:**
  - এই ব্যাংক রেজিস্টার Finance & Ledger-এর Bank Account Master-ও ব্যবহার করে (23/01)। Brickland-এ রেজিস্টার একটাই।
  - ব্যাংক কোড আর SWIFT বড় হাতের হয়।
  - কোড, প্রিফিক্স, দেশ আর SWIFT বাধ্যতামূলক। ব্যাংক কোড ইউনিক।
  - একটা ব্যাংকে একই কোডের শাখা দুবার চলে না।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{tx-reference-entries|tx-notify-parties|tx-lc-applicants|tx-exchange-rates|tx-banks|tx-bank-branches}`। ব্যাংকের প্রতিটা সারির সাথে তার শাখাগুলোও আসে।
- **`/api/v1/tradeexport/lookups`:** কোম্পানি, বায়ার, দেশ, আর মুদ্রা (হারসহ)।
- **পারমিশন:** নতুন `tradeexport.master.view` আর `tradeexport.master.manage`।
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়।
  - AUDITOR পায় শুধু view।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/trade-export/<screen>`। সাইডবারে COMMERCIAL & EXPORT → Trade & Export → MASTER DATA।
- **Brickland-এর লেআউট মেনে কোনো ট্যাব নেই।** এন্ট্রি ফর্ম আর তালিকা একই পেজে, Brickland-এর বক্সের নামে: "Select Reference", "Select Buyer", "Master Data", "Data List", "Notify Party List", "Applicant List", "Bank List"।
- **Document Reference Types:** বামে আটটা তালিকা। ডানে Master Data ফর্ম (কোডের ঘরে লেখা "Generated on save") আর Data List।
- **Buyer-Wise Notify Party:** বামে বায়ার খোঁজা আর বাছাই। ডানে ফর্ম আর সেই বায়ারের তালিকা।
- **Currency Exchange Rates:**
  - তালিকায় "+ Add New CM Local Exchange Rate" বাটন, তারপর Searching Data ফিল্টার।
  - নতুন এন্ট্রির ফর্মে Back To List।
  - মুদ্রা বাছাই করলে Local Exchange Rate নিজে থেকে দেখায়।
- **Bank Directory:**
  - ওপরে "+ Add New", তার নিচে Searching Data।
  - Bank List-এ প্রতিটা ব্যাংকের ▸ চাপলে শাখাগুলো খোলে।
  - Edit Bank আর New Branch, দুটোই ডায়ালগে। শাখা নিজের সারি থেকে সম্পাদনা করা যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/TradeExport/Database/Migrations/2026_11_07_000100_create_trade_export_masters.php`
- `app/Modules/TradeExport/Models/TxReferenceEntry.php`
- `app/Modules/TradeExport/Models/TxNotifyParty.php`
- `app/Modules/TradeExport/Models/TxLcApplicant.php`
- `app/Modules/TradeExport/Models/TxExchangeRate.php`
- `app/Modules/TradeExport/Models/TxBank.php`
- `app/Modules/TradeExport/Models/TxBankBranch.php`
- `app/Modules/TradeExport/Support/TradeExportDefaults.php`
- `app/Modules/TradeExport/Resources/TradeExportResource.php`
- `app/Modules/TradeExport/Resources/ReferenceEntryResource.php`
- `app/Modules/TradeExport/Resources/NotifyPartyResource.php`
- `app/Modules/TradeExport/Resources/LcApplicantResource.php`
- `app/Modules/TradeExport/Resources/ExchangeRateResource.php`
- `app/Modules/TradeExport/Resources/BankResource.php`
- `app/Modules/TradeExport/Resources/BankBranchResource.php`
- `app/Modules/TradeExport/Http/Controllers/Api/TradeExportApiController.php`
- `app/Modules/TradeExport/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/trade-export/[screen]/page.js`
- `frontend/src/app/(app)/trade-export/[screen]/screens.js`
- `frontend/src/app/(app)/trade-export/[screen]/actions.js`
- `frontend/src/components/tradeexport/buyer-picker.jsx`
- `frontend/src/components/tradeexport/bank-directory.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/tradeexport.php`
- `lang/bn/tradeexport.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/TradeExport/TradeExportMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `TradeExportMasterTest` ৫/৫ (৭৪টা assertion):
  - **রেফারেন্স:** শুরুর এন্ট্রি আছে। নাম ছাড়া মেসেজ আসে। কোড তৈরি হয় (EXPO-FREIGHT)। একই সংক্ষিপ্ত নাম চলে না, অন্য তালিকায় চলে। নিষ্ক্রিয় করা যায়। তালিকা বদলানো যায় না। মোছা যায় না।
  - **নোটিফাই পার্টি ও আবেদনকারী:** ঠিকানা আর বায়ার ছাড়া মেসেজ আসে। ভুল ই-মেইল চলে না। কোড NP-0001, NP-0002 হয়। একই নামের আবেদনকারী চলে না।
  - **বিনিময় হার:** মুদ্রার হার lookups-এ আসে। পাঠানো হার উপেক্ষা করে মুদ্রার ১১৯.৫ নেওয়া হয়। হার ছাড়া মুদ্রা চলে না। একই দিন-কাজে দুবার চলে না; অন্য কাজে চলে।
  - **ব্যাংক:** শুরুতে ২৬টা ব্যাংক। কোড ছাড়া বা SWIFT ছাড়া চলে না। কোড আর SWIFT বড় হাতের হয়। একই কোড চলে না। শাখার কোড ছাড়া মেসেজ আসে; একই ব্যাংকে একই কোড চলে না, অন্য ব্যাংকে চলে। ব্যাংকের সাথে শাখা আসে।
  - **পারমিশন:** ফ্যাক্টরি ম্যানেজার বদলাতে পারে। অডিটর দেখতে পারে, বদলাতে পারে না। স্টোর ম্যানেজার কিছুই পায় না।
- **ব্রাউজার (dev):** পাঁচটা স্ক্রিনেই সেভ হয়েছে। কনসোলে কোনো error নেই।
  - ফরওয়ার্ডার যোগ হয়েছে, কোড EXPO-FREIGHT।
  - বায়ার বাছাই করে নোটিফাই পার্টি যোগ হয়েছে, কোড NP-0001।
  - L/C আবেদনকারী যোগ হয়েছে।
  - USD হার নিজে থেকে ১১৯.৫ এসেছে।
  - ব্যাংকে শাখা যোগ হয়ে ▸ চাপলে দেখা গেছে।

## ১০. খোলা প্রশ্ন

- **Brickland-এর পুরনো ধাঁচের পেজ:** এই পাঁচটা স্ক্রিন Brickland-এর পুরনো লেআউটে: শিরোনাম-বার, fieldset বক্স, সবুজ Save বাটন। এখানে বক্সের নাম, ক্রম আর একই পেজে ফর্ম ও তালিকা রাখার ব্যাপারটা মেলানো হয়েছে, কিন্তু রং আর বাটন অ্যাপের বাকি অংশের মতোই রাখা হয়েছে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- লাইভ probe দিয়ে আগে থেকে দেখে নেওয়া হয়েছে:
  - আটটা রেফারেন্স তালিকার প্রতিটা এন্ট্রি।
  - ব্যাংকের তালিকা (Commercial-এ প্রথম পাতার ২০টা দেখা যায়; পুরো রেজিস্টারে ২৬টা)।
  - Add New, Edit Bank আর New Branch ডায়ালগ।
  - বিনিময় হারের ফর্ম। সেখানে Local Exchange Rate মুদ্রা থেকে আসে আর শুধু দেখা যায়।
  - বায়ার বাছাইয়ের পরের অবস্থা।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - ট্যাব নেই এমন পেজে `?tab=new` হারিয়ে Records-এ ফিরে যাচ্ছিল। এখন url-এর ভিউ থাকে।
  - শুধু-দেখার ঘরে placeholder দেখাচ্ছিল না।

