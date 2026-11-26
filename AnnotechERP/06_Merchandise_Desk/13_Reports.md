# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন (H1 Order Merchandising Reports: ২৪টা রিপোর্ট চালু)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/13`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Order Merchandising Reports | `/m/merchandising/merchandising-reports` | 1 | 26 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Order Merchandising Reports

`/m/merchandising/merchandising-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Sample Production | radio |  |  |
| Bulk Fabric Booking | radio |  |  |
| Budget Sheet | radio |  |  |
| Task Wise T&A Fail Report | radio |  |  |
| Dept Wise T&A Fail Status | radio |  |  |
| Style-Dept Wise T&A Task Fail | radio |  |  |
| Buyer-Dept Wise T&A Task Fail | radio |  |  |
| Order Wise Accessories Status Report | radio |  |  |
| TnA Pass Fail Summary Report | radio |  |  |
| Buyer wise Monthly Costing and Net Margin | radio |  |  |
| Buyer wise Monthly CM Earning | radio |  |  |
| Order wise Budget and Costing | radio |  |  |
| Budget Vs Actual Cost Sheet | radio |  |  |
| Budget Vs Actual Cost Sheet-Chart | radio |  |  |
| Order Confirmation List | radio |  |  |
| Buyer wise Accessories Purchase Requisition | radio |  |  |
| Order Tracking Report | radio |  |  |
| Comparative Report Budgeted vs Actual (Textile Division) | radio |  |  |
| Import Fabric Receive Statement | radio |  |  |
| PO Wise Actual Shipment Report | radio |  |  |
| Purchase Fabric Stock Report | radio |  |  |
| Finish Fabric Stock Report | radio |  |  |
| Trims Stock Report | radio |  |  |
| Order Size Breakdown | radio |  |  |
| From Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Merchandise Desk-এর নিজের টেবিল: অর্ডার আর তার সাইজ (06/07), বাজেট আর বাজেট বনাম আসল (06/09), স্যাম্পল প্রোগ্রাম আর ব্যাচ (06/10), সব ধরনের ফ্যাব্রিক বুকিং (06/11), অর্ডারের T&A ক্যালেন্ডার (06/12)।
- পুরোনো `app/Modules/Reporting` মেশিনারি অংশের জন্য। ERP মডিউলের রিপোর্ট স্ক্রিন এটাই প্রথম, তাই একটা সাধারণ কম্পোনেন্ট (`ReportSet`) বানানো হয়েছে, যেটা পরের মডিউলগুলোও ব্যবহার করবে।

## ৪. ডাটা মডেল (Entity ও Field)

- নতুন কোনো টেবিল নেই। প্রতিটা রিপোর্ট Preview চাপার মুহূর্তে মডিউলের টেবিল থেকে পড়ে, কিছুই জমা রাখা হয় না (Brickland-এর লেখা অনুযায়ী)।

## ৫. Workflow / Status ও Business Rules

- **Brickland-এর স্ক্রিন:** বাঁয়ে ২৪টা রিপোর্টের রেডিও, ডানে From Date (শুরুতে আজ), Export to Excel? আর Preview। Preview একটা ডায়ালগ খোলে: রিপোর্টের নাম, "Every figure is read from the module's own tables…", টেবিল, "n rows." আর Export।
- **Brickland-এ ২৪টার মধ্যে ১৪টা রিপোর্ট Preview-তে আটকে থাকে** (কোনো ডায়ালগ বা বার্তা আসে না)। তাই সেগুলোর কলাম আমাদের ডাটা আর রিপোর্টের নাম দেখে ঠিক করা হয়েছে। যে ১০টা কাজ করে, সেগুলোর কলাম Brickland-এর মতো।
- **From Date** প্রতিটা রিপোর্টের মূল তারিখে খাটে:
  - অর্ডার: কনফার্মের তারিখ
  - বুকিং: বুকিংয়ের তারিখ
  - বাজেট: তৈরির তারিখ
  - স্যাম্পল: প্রোগ্রামের তারিখ
  - T&A: প্ল্যান তারিখ
- **রিপোর্টগুলো:**
  - **অর্ডার:**
    - Order Confirmation List: Buyer, Order #, Style, Confirmed, Ship, Qty, Value।
    - Order Size Breakdown: অর্ডার, রং, সাইজ, পরিমাণ।
    - Buyer wise Monthly Costing and Net Margin: বায়ার ধরে অর্ডার, পরিমাণ, মূল্য, গড় FOB, বাজেট খরচ, নিট মার্জিন।
    - Buyer wise Monthly CM Earning: বায়ার আর শিপমেন্টের মাস ধরে বাজেটের CM, আর CM প্রতি ডজন।
  - **বুকিং:**
    - Bulk Fabric Booking: সব ধরনের বুকিং (Brickland-ও সব ধরনই দেখায়), সাথে ধরন, অর্ডার, তারিখ, অবস্থা আর পরিমাণ।
    - Import Fabric Receive Statement, Purchase Fabric Stock Report (Additional/Buffer/Import), Finish Fabric Stock Report (বাল্ক): বুক করা ফ্যাব্রিকের লাইন। স্টোর মডিউল এখনো নেই, তাই গৃহীত পরিমাণ ০ আর বাকি মানে পুরো বুক করা পরিমাণ।
  - **বাজেট:** খরচ = Budget vs Actual Costing-এর সব খাত (সুতা আর অ্যাক্সেসরিজ লাইন, প্রসেস খরচ, ওভারঅল খরচ), FOB বাদে। মূল্য = অর্ডারের পরিমাণ × দাম।
    - Budget Sheet, Order wise Budget and Costing: মার্জিন আর মার্জিন %।
    - Budget Vs Actual Cost Sheet: প্রতিটা খাতের বাজেট, আসল আর পার্থক্য।
    - Budget Vs Actual Cost Sheet-Chart: অর্ডার ধরে মোট বাজেট আর আসল, বার চার্টসহ।
    - Comparative Report (Textile Division): শুধু ফ্যাব্রিক আর প্রসেসের খাত।
  - **T&A:** একটা মাইলস্টোন ব্যর্থ ধরা হয়, যদি প্ল্যানের পরে সম্পন্ন হয়, অথবা খোলা থাকা অবস্থায় প্ল্যান পার হয়ে যায়। দেরির দিন গোনা হয় আসল তারিখ পর্যন্ত, খোলা থাকলে আজ পর্যন্ত।
    - Task Wise T&A Fail Report: অর্ডার, কাজ, প্ল্যান, আসল, দেরির দিন।
    - Dept, Style-Dept আর Buyer-Dept Wise: কাজ, ব্যর্থ, ব্যর্থতা %।
    - TnA Pass Fail Summary: কাজ ধরে সংখ্যা, সম্পন্ন, খোলা, ব্যর্থ।
  - **স্যাম্পল:** Sample Production: প্রোগ্রাম, ব্যাচ, অর্ডার, ধরন, আইটেম, রং, সাইজ, চাহিদা, সেলাই, ডেলিভারি, বাতিল, অবস্থা।
  - **যে মডিউল এখনো তৈরি হয়নি** (Brickland-এর মতোই শূন্য দেখায়, আর ডায়ালগে কারণ লেখা থাকে):
    - Order Wise Accessories Status আর Buyer wise Accessories Purchase Requisition: অ্যাক্সেসরিজ মডিউল নেই।
    - Order Tracking: কাটিং, সেলাই, ফিনিশিং, শিপমেন্ট মডিউল নেই।
    - PO Wise Actual Shipment: শিপমেন্ট মডিউল নেই।
    - Trims Stock: স্টোর নেই।
- **Export to Excel?** টিক দিলে Preview ডায়ালগ খোলে না, সরাসরি CSV ফাইল নামে (UTF-8 BOM থাকে, তাই Excel-এ বাংলা ঠিক দেখায়)। ডায়ালগের Export-ও একই ফাইল দেয়।

## ৬. API ও পারমিশন

- `GET /merchandise/reports/{report}?from=YYYY-MM-DD`: `{report, from, columns: [{key, type}], rows, chart, pending_module}`।
  - কলামের ধরন: text, number, date, money, yesno, status, badge, head।
  - অজানা রিপোর্টে 422।
- পারমিশন (নতুন): `merchandise.report.view`। FACTORY_ADMIN আর FACTORY_MANAGER পায়।
- মেনু: ফোল্ডার `MERCHANDISE.REPORTS` ("REPORTS"), ভেতরে `MERCHANDISE.MERCH_REPORTS` → `/merchandise-desk/merchandising-reports` (Brickland-এর পাথ)।

## ৭. Frontend পেজ (লাইট থিম)

- **Order Merchandising Reports** (`/merchandise-desk/merchandising-reports`): একটাই পাতা, ট্যাব নেই।
  - "Selection of Parameters" ফিল্ডসেট। বাঁয়ে ২৪টা রিপোর্টের রেডিও তালিকা, ডানে From Date, Export to Excel? আর Preview (পুরো চওড়া)।
  - Preview ডায়ালগ:
    - নাম আর Brickland-এর লেখা।
    - দরকার হলে "মডিউল এখনো নেই" নোটিশ।
    - "রিপোর্ট · from 01 Jan 2026"।
    - চার্ট থাকলে বার চার্ট।
    - টেবিল: সংখ্যা ডানে, তারিখ "16 Oct 2026" ধাঁচে, অবস্থা পড়ার মতো শব্দে, বাজেটের খাত নামে।
    - "n rows." আর Export।
- **`components/erp/report-set.jsx` (`ReportSet`):** যে কোনো মডিউলের রিপোর্ট সেট এটা দিয়ে চলবে। লেবেল আসে মডিউলের ভাষা থেকে, `prefix`-এর নিচে: `report.*`, `col.*`, `head.*`, `pending.*`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Merchandise/Http/Controllers/Api/MerchandiseReportApiController.php` (২৪টা রিপোর্ট, `REPORTS`, `PENDING_MODULES`), `Routes/api.php` |
| পারমিশন ও মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` |
| ফ্রন্টএন্ড | `frontend/src/components/erp/report-set.jsx` (`ReportSet`), `frontend/src/app/(app)/merchandise-desk/[screen]/reports.js` (`REPORT_SCREENS`), `page.js`, `actions.js` (`previewMerchandiseReport`) |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.mrp`: রিপোর্ট, কলাম, খাত, pending; ফোল্ডার `reports`; স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseReportTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- অ্যাক্সেসরিজ, স্টোর, কাটিং, সেলাই, শিপমেন্ট মডিউল তৈরি হলে সংশ্লিষ্ট রিপোর্টের শূন্য ঘরগুলো আসল সংখ্যা পড়বে, আর `PENDING_MODULES` থেকে সেই রিপোর্ট সরে যাবে।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseReportTest`** (২টা টেস্ট, ১২১ assertion; আজ ১৬ অক্টোবর ২০২৬ ধরে):
  - ২৪টা রিপোর্টই চলে আর কলাম দেয়।
  - অর্ডার কনফার্মেশন (৪৮০ × ২ = ৯৬০), বুকিং (BFB-00001, ৩৯.৮), সাইজ।
  - বাজেট: ২০০ সুতা + ৫০ নিটিং + ১২০ CM = ৩৭০, FOB ধরা হয় না; মার্জিন ৫৯০ আর ৬১.৪৬%; খাত ধরে পার্থক্য; চার্ট (৩৭০ বনাম ২৫০); টেক্সটাইলে শুধু নিটিং।
  - বায়ার: নিট মার্জিন; CM ১২০, প্রতি ডজনে ৩।
  - T&A: খোলা Issue PI ৬ দিন দেরি; ডিপার্টমেন্ট ৫০%; বায়ার আর স্টাইল ধরে; পাস-ফেল সারাংশ।
  - তৈরি না হওয়া মডিউলের রিপোর্টে শূন্য আর `pending_module`।
  - পরের From Date দিলে খালি; অজানা রিপোর্টে 422।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার দেখতে পারেন।
- গেট কভারেজ + রিপোর্ট + T&A একসাথে: ৮টা টেস্ট, ১৮৯ assertion সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - Brickland-এর বিবরণ, ২৪টা রিপোর্টের নাম একই ক্রমে, From Date শুরুতে আজ ✔
  - Order Confirmation List (০১ জানুয়ারি থেকে): "LPP · Order · FLOW STYLE-01 · 08 Oct 2026 · 12 Jan 2027 · 1,000 · USD 4,500" ✔
  - Bulk Fabric Booking-এ চার ধরনের বুকিং (Import 400, Buffer 80, Additional 180, Bulk 124) ✔
  - Order Tracking-এ "মডিউল এখনো নেই" নোটিশ আর শূন্য ✔
  - TnA Pass Fail Summary-তে ২৫টা কাজ ✔
  - dev অর্ডারে BGT-2026-000001 বাজেট বানিয়ে: চার্ট (৩,০৯৪ বনাম ৩,১০০), খাত ধরে Budget vs Actual (সুতা ১,২৭৪ বনাম ১,৩১০…), বায়ারের মার্জিন ১,৪০৬ ✔
  - বাজেটটা dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- Brickland-এর ১৪টা রিপোর্ট (Sample Production, Budget Sheet, T&A Dept/Style/Buyer, CM Earning, Order wise Budget, Budget vs Actual + Chart, Accessories PR, Textile Division, Import Receive, PO Shipment, Purchase/Finish Fabric Stock, Trims Stock) Preview-তে আটকে থাকে। এগুলোর কলাম এখানে ধরে নেওয়া। SME দেখে কলাম বদলাতে চাইলে বদলানো যাবে।
- "Monthly" রিপোর্টে মাস ধরা হয়েছে শিপমেন্টের মাস দিয়ে। কনফার্মের মাস চাইলে বদলানো যাবে।

## ১১. As-built নোট

- রিপোর্টগুলো একটা কন্ট্রোলারে, প্রতিটা আলাদা মেথড। অর্ডার, বাজেট, ফ্যাব্রিক লাইন আর T&A মাইলস্টোন পড়ার কাজ সব রিপোর্টে একই হেল্পার দিয়ে হয়, যাতে একই সংখ্যা দুই রিপোর্টে আলাদা না আসে।
- বাজেটের খরচ Budget vs Actual Costing-এর হিসাবেই পড়া হয় (একই খাত), তাই রিপোর্ট আর বাজেট স্ক্রিনের সংখ্যা মেলে।
- ভাষার কী `master.mrp`, আগে থেকে থাকা কোনো কীর সাথে সংঘাত নেই (যাচাই করা হয়েছে)।
