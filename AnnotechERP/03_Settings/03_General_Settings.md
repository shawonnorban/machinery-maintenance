# General Settings

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টা স্ক্রিন; Brickland-এ এগুলো Scaffold ছিল, এখানে পুরোপুরি বানানো)
> **মডিউল:** [Settings](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `03/03`

**⬅ ফিরে যান:** [Settings](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | General Preferences | `/m/settings/general-preferences` | 1 | 0 | 0 |
| 2 | Date, Currency & Localization | `/m/settings/localization` | 1 | 0 | 0 |
| 3 | Document Numbering Series | `/m/settings/document-numbering` | 1 | 0 | 0 |
| 4 | Print & Letterhead Settings | `/m/settings/print-letterhead` | 1 | 0 | 0 |
| 5 | Barcode & Label Settings | `/m/settings/barcode-label` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### General Preferences

`/m/settings/general-preferences`

**ভিউ**
> 10 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Date, Currency & Localization

`/m/settings/localization`

**ভিউ**
> 11 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Document Numbering Series

`/m/settings/document-numbering`

**ভিউ**
> 12 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Print & Letterhead Settings

`/m/settings/print-letterhead`

**ভিউ**
> 13 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Barcode & Label Settings

`/m/settings/barcode-label`

**ভিউ**
> 14 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

_বাস্তবায়নের আগে লেখা হবে।_

## ৪. ডাটা মডেল (Entity ও Field)

_বাস্তবায়নের আগে লেখা হবে।_

## ৫. Workflow / Status ও Business Rules

_বাস্তবায়নের আগে লেখা হবে।_

## ৬. API ও পারমিশন

_বাস্তবায়নের আগে লেখা হবে।_

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Settings/Database/Seeders/SettingDefinitionSeeder.php`
- `app/Modules/Settings/Actions/SetSetting.php`
- `app/Modules/Settings/Http/Controllers/Api/SettingsApiController.php`
- `app/Modules/Settings/Database/Migrations/2026_10_23_000100_create_currencies_and_label_templates.php`
- `app/Modules/Settings/Models/Currency.php`
- `app/Modules/Settings/Models/LabelTemplate.php`
- `app/Modules/Settings/Resources/CurrencyResource.php`
- `app/Modules/Settings/Resources/LabelTemplateResource.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Actions/SyncMenus.php`

**Frontend**
- `frontend/src/app/(app)/system-settings/[screen]/page.js`
- `frontend/src/app/(app)/system-settings/[screen]/screens.js`
- `frontend/src/app/(app)/system-settings/[screen]/actions.js`
- `frontend/src/components/settings/letterhead-preview.jsx`
- `frontend/src/components/settings/label-previews.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/settings.php`
- `lang/bn/settings.php`

**Tests**
- `tests/Feature/Settings/GeneralSettingsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

_বাস্তবায়নের আগে লেখা হবে।_

## ১০. খোলা প্রশ্ন

_নেই এখনো।_ (২০২৬-১১-১৫-এর পাঁচটা প্রশ্নের উত্তর নিচে, As-built-এ।)

## ১১. As-built নোট

**এক তালিকা, এক স্ক্রিন (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১১-১৫):**
- **কারখানার ক্যালেন্ডার ও শিফট → HR & Payroll।** শিফট = HR → Shift Master (`hr_shifts`), ছুটি = HR → Festival Calendar → Holidays (`hr_holidays`, এখন HR-এর হাজিরাও সরাসরি এটাই পড়ে), সাপ্তাহিক ছুটি = Settings → General Preferences → Working days (কারখানা-স্তরেও), চালু থাকার ধরন = `factory.operating_mode`। `WorkingTimeService` এখন `HrPayroll/Services`-এ; কারখানার কর্মীরা যে শিফটে, সেগুলোই কারখানার শিফট (না থাকলে কোম্পানির সব সক্রিয় শিফট), ওভারল্যাপ একবার গোনা হয়, HR-এর `break_min` শিফটের মাঝখানে ধরা হয়। পুরনো Settings → Calendar & Shifts আর Capacity Planning-এর Factory Calendar সরানো; Capacity-র Shift Master লাইনের শিফট HR থেকে বাছে (`hr_shift_id`)। Knitting/HR কর্মীর `shift_id` এখন `hr_shifts`-এ। Migration `2027_03_28_000100`।
  - সীমা: HR শিফটে `effective_to` নেই, তাই পুরনো শিফট বাদ দিতে নিষ্ক্রিয় করতে হয় (বন্ধ মাসের KPI snapshot-এ থাকে)। "শুক্রবার কাজ" জাতীয় একদিনের ওভাররাইড HR-এ নেই — দরকার হলে কারখানার Working days বদলাতে হয়।
- **GL → Finance & Ledger-এর Chart of Account।** (সংশোধন: Machinery আগে কোনো GL-এ পোস্ট করত না।) Access Control → Ledger Integration Setup (Brickland-এর GL Configuration) বানানো: GL source (নাম en/bn, Mapping Type, Link Ref, Final Bill Collection, Editable From GL, Active) আর COA লাইন (Debit/Credit, Chart of Account, Cost Center, Note)। Machinery-র চারটা source (Parts, Labour, Vendor & External Service, Other) প্রথম দেখাতেই আসে; Debit আর Credit লাইন দিলে প্রতিটা খরচ Finance-এ Draft JV (source SYSTEM) হয়, পরিবর্তন/মুছলে শুধু পার্থক্য (`acc_ledger_postings`)। পুরনো GL account mapping আর Master Data → GL accounts সরানো।
- **ব্যাংক → এক তালিকা `tx_banks`** (Finance → Manage Bank ও Trade Export → Bank List আগে থেকেই এটা)। পুরনো খালি `banks` টেবিল আর Master Data → Banks বাদ; Vendor-এর ব্যাংক এখন `tx_banks` থেকে।
- **Unit, Store, Category → General Store-এর স্ক্রিন, এক টেবিল।** Units = `units_of_measure` (platform-এর একক সব কোম্পানির, বদলানো যায় না), Stores = `stores` (Warehouse → Store → Bin; নতুন: location, in-charge, default; স্ক্রিনে Warehouse আর ধরন), Categories = `item_categories` (তিন স্তর পর্যন্ত, Parent সহ)। General Store-এর `gs_units/gs_stores/gs_categories` আর Machinery-র `spare_part_categories` মিশিয়ে বাদ; General Store আর Knitting-এর ২২টা store লিংক শেয়ার করা টেবিলে। Master Data থেকে Units, Stores, Item/Spare part categories সরানো (Warehouses, Bins থাকল)। Migration `2027_03_28_000200`।
- **Grade → HR & Payroll → Pay Grade** (একই `grades` টেবিল); Company Management থেকে Grade ট্যাব সরানো।

**সম্পন্ন (২০২৬-১০-১০):** Brickland-এ পাঁচটাই "Scaffold" ছিল। ব্যবহারকারীর সিদ্ধান্তে পুরোপুরি কার্যকর বানানো হয়েছে, Brickland-এর লেআউটে।
- **রুট:** `/system-settings/<screen>`। সাইডবারে Settings → GENERAL SETTINGS। পুরনো Settings-পাতাগুলো আপাতত OTHER SETTINGS-এ।
- **General Preferences:** নতুন setting key `general.working_days` (LIST, শনি–বৃহস্পতি), `general.fiscal_year_start_month` (জুলাই), `general.landing_page`, `general.page_size`। ট্যাব: Preferences (ফর্ম) | Records (কী, মান, উৎস, হালনাগাদ)।
- **Date, Currency & Localization:**
  - key: `locale.default/date_format/time_format/timezone`, `currency.base` (BDT), `number.decimal_places`, `number.grouping` (লাখ-কোটি বা হাজার)।
  - Currencies ট্যাব: `currencies` টেবিল, স্ক্রিন-কিটের resource `/erp/currencies`; ISO কোড, প্রতীক, দশমিক, বিনিময় হার, হারের তারিখ। মূল মুদ্রার হার সব সময় ১ রাখা হয়।
- **Document Numbering Series:** বিদ্যমান numbering ইঞ্জিন (`/numbering`)। Records-এ ধরন, অঙ্ক, আবার শুরু, পরের নম্বর, কতগুলো দেওয়া হয়েছে। Edit-ডায়ালগে ধরন আর অঙ্ক বদলানো যায়; মোছা মানে নিজস্ব ধরনে ফেরা, আগের নম্বর বদলায় না।
- **Print & Letterhead:** key `print.header_title/header_address/header_contact/footer_text/show_logo/show_print_time/signatures/paper_size/margin_mm`; পাশে লাইভ প্রিভিউ (কাগজের মাপের অনুপাতে, লোগো, শিরোনাম, স্বাক্ষরের ঘর, ফুটার)।
  - **বাকি:** প্রতিটা ডকুমেন্টের প্রিন্ট-লেআউট এই key পড়বে; প্রিন্টযোগ্য ডকুমেন্টের মডিউল তৈরির সময় যুক্ত হবে।
- **Barcode & Label:** `label_templates` (`/erp/label-templates`): কীসের জন্য, বারকোডের ধরন, মাপ, পাশাপাশি কটা, যা প্রিন্ট হবে, প্রিন্টার PDF/ZPL। Preview ট্যাবে নমুনা ডাটাসহ প্রতিটা টেমপ্লেট। লেবেল প্রিন্টে ব্যবহার হবে সংশ্লিষ্ট মডিউল তৈরির সময়।
- **পথে ঠিক করা:**
  - লেখার সেটিং খালি করলে সেভ ব্যর্থ হচ্ছিল (Laravel খালি স্ট্রিং null বানায়); এখন খালি মান রাখা যায়।
  - LIST-এর কমা-আলাদা মানে ফাঁকা জায়গা ছেঁটে নেওয়া হয় ("Prepared By, Checked By")।
  - ফর্ম শুধু বদলানো key সেভ করে, তাই না-ছোঁয়া key ডিফল্ট মানতেই থাকে।
  - সাইডবারের ফোল্ডারের ক্রম এখন SyncMenus নিজে হিসাব করে (প্রথম স্ক্রিনের ঠিক আগে)।
- **কিট:** `EntryForm`-এ নতুন `multiselect` ফিল্ড (টিকের সেট, "A,B"-রূপে)।
- **যাচাই:** `GeneralSettingsTest` ৩/৩; ব্রাউজারে ফর্ম সেভ → Records-এ উৎস "Company", কনসোলে error নেই।
