# Report

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন, ৩১টা রিপোর্ট)
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `22/06`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Trade & Export Reports | `/m/commercial/commercial-reports` | 1 | 47 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Trade & Export Reports

`/m/commercial/commercial-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Export Invoice | radio |  |  |
| Import & Export Register Book | radio |  |  |
| IFDBC Payment Schedule | radio |  |  |
| BBLC & Liability Status | radio |  |  |
| Cost Against Realization | radio |  |  |
| IFDBC Payment List | radio |  |  |
| Export LC List | radio |  |  |
| BBLC Summary | radio |  |  |
| LC Wise Realization | radio |  |  |
| Exp. LC Wise Cash Incentive | radio |  |  |
| Imp. LC Wise Bill of Entry | radio |  |  |
| Import LC Application Letter DataSet | radio |  |  |
| BBLC, IFDBC Liabilities & Payment Status | radio |  |  |
| Statement Of Import LC | radio |  |  |
| IFDBC Outstanding Report | radio |  |  |
| Export & Payment Follow-up | radio |  |  |
| Monthly Export Summary | radio |  |  |
| BOE Wise Invoices | radio |  |  |
| UD Summary | radio |  |  |
| Weekly Margin & BBLC Statement | radio |  |  |
| LC Opening & BBLC Ratio | radio |  |  |
| Customs & Bond Report | radio |  |  |
| Invoice & BoE wise Collection Summary | radio |  |  |
| Export Invoice Profile | radio |  |  |
| B2B Order Summary | radio |  |  |
| Doc Export Invoices Summery | radio |  |  |
| Bank Wise Margin Status | radio |  |  |
| Exp. BoE Profile | radio |  |  |
| Short Shipment Status | radio |  |  |
| Fund Forecast | radio |  |  |
| BTB Liability Acceptance | radio |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Buyer | select |  | LPP (LPP) |
| Export L/C | select |  |  |
| Invoice No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| X-factory From | date |  |  |
| X-factory To | date |  |  |
| All | radio |  |  |
| Yes | radio |  |  |
| No | radio |  |  |
| FDBP | radio |  |  |
| FDBC | radio |  |  |
| With On Board Date | radio |  |  |
| Without On Board Date | radio |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters, Report
- বাটন/অ্যাকশন: Preview
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/07 Sales & Shipment Report:** একই ধাঁচের স্ক্রিন (বাঁয়ে রিপোর্টের তালিকা, ডানে বক্স, Preview, Export to Excel, নিচে মোটসহ টেবিল)। সেই কাঠামো এখানে নেওয়া হয়েছে।
- **22/01–22/05-এর সব কাগজ:** Export L/C ও তার অর্ডার, Commercial Invoice, Lodgement, Bill of Exchange, Realisation, CM রসিদ, Cash Incentive; ইমপোর্টে B2B L/C, PI, Bill of Entry, Acceptance (IFDBC), UD ও Transfer। রিপোর্টের উৎস এগুলোই।
- **`ExportBook`:** buyer, ব্যাংক, reference নাম, UD নম্বর; নতুন `realised()`।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই; রিপোর্ট শুধু পড়ে।

- **`ExportBook::realised()` (নতুন):** প্রতিটা Bill of Exchange-এর চূড়ান্ত realisation একবার — FINAL থাকলে সেটা, না থাকলে EARLY। আগে EARLY আর পরে FINAL হওয়া বিল দুবার গোনা হয় না। Collected = FINAL-এর collection, EARLY-তে invoice amount।

## ৫. Workflow / Status ও Business Rules

- **স্ক্রিন:** Brickland-এর মতো Selection of Parameters: বাঁয়ে ৩১টা রিপোর্ট (radio), ডানে Company, Buyer, Export L/C (buyer অনুযায়ী ছাঁকা), Invoice No, From / To Date, X-factory From / To, Invoice Negotiated? (All / Yes / No), Bill Type? (All / FDBP / FDBC), On Board? (All / With / Without), Export to Excel?, Preview। Brickland-এর মতো খোলার সময় কোনো রিপোর্ট নিজে চলে না।
- **যে বক্স রিপোর্ট পড়ে না সেটা নিষ্ক্রিয়** (21/07-এর মতো); সার্ভারও সেই বক্স উপেক্ষা করে।
- **তারিখ:** ডিফল্ট বছরের শুরু থেকে আজ। To আগে হতে পারে না; সর্বোচ্চ ৩৬৬ দিন। প্রতিটা রিপোর্টের নিজের তারিখ: L/C-র তালিকায় ইস্যুর তারিখ, কাগজের তালিকায় কাগজের তারিখ, IFDBC সূচিতে ম্যাচিউরিটি (বাড়ানো থাকলে সেটা), পেমেন্ট তালিকায় পেমেন্টের তারিখ, Fund Forecast-এ বিলের due date আর IFDBC ম্যাচিউরিটি।
- **রিপোর্টগুলো (কী পড়ে):**
  - **এক্সপোর্ট L/C:** Export LC List; Import & Export Register Book (L/C, রপ্তানি, আদায়, B2B, আমদানি); BBLC Summary (BB সীমা, খোলা, বাকি); LC Opening & BBLC Ratio (B2B %, সীমার মধ্যে কিনা); LC Wise Realization; Short Shipment Status (L/C-র অর্ডারের পরিমাণ বনাম ইনভয়েস)।
  - **ইনভয়েস:** Export Invoice; Export Invoice Profile (অর্ডার, স্টাইল, HS, EXP, B/L, জাহাজ, কন্টেইনার, ওজন); Export & Payment Follow-up (বিল, জমা, due, আদায়, অবস্থা); Monthly Export Summary; Doc Export Invoices Summery (Lodgement অনুযায়ী)।
  - **বিল ও আদায়:** BOE Wise Invoices; Invoice & BoE wise Collection Summary (আদায়, collected, CM গৃহীত, বাকি); Exp. BoE Profile; Cost Against Realization (invoice − collected, FBC, জরিমানা, খরচ %); Exp. LC Wise Cash Incentive; Bank Wise Margin Status (Margin Closing-এর ধরন অনুযায়ী)।
  - **ইমপোর্ট:** Statement Of Import LC; BBLC & Liability Status (গৃহীত, পরিশোধিত, দায়, অগৃহীত); BBLC, IFDBC Liabilities & Payment Status (পরের ম্যাচিউরিটি, মেয়াদোত্তীর্ণ); Import LC Application Letter DataSet; B2B Order Summary (PI লাইন, PO); Imp. LC Wise Bill of Entry; Customs & Bond Report (B/E লাইন, MRR, UD)।
  - **IFDBC:** IFDBC Payment Schedule (বাকি দিন); IFDBC Payment List; IFDBC Outstanding Report (দেরির দিন); BTB Liability Acceptance।
  - **অন্যান্য:** UD Summary (B2B, ট্রান্সফার আউট / ইন); Weekly Margin & BBLC Statement (শনিবার থেকে সপ্তাহ); Fund Forecast (মাস অনুযায়ী অনাদায়ী বিল আর বাকি IFDBC, নিট)।
- **মোট সারি:** পরিমাণ আর টাকার কলামে; %, দর আর দিনে মোট নেই। Export to Excel-এ CSV, মোট সারিসহ।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `tradeexport.report.view`। FACTORY_ADMIN, FACTORY_MANAGER আর AUDITOR পায়; STOREKEEPER পায় না।
- **API (`/tradeexport/ops/…`):** `reports/options` (GET), `reports/run` (POST)।
- **মেনু:** Trade & Export-এর নিচে নতুন ফোল্ডার REPORT, ১টা স্ক্রিন। Trade & Export এখন Brickland-এর মতো ২২টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/tradeexport/reports.jsx`: `TradeReports`।
- **ঠিকানা:** `/trade-export/commercial-reports`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন, ৩১টা রিপোর্ট):

**Backend (`app/Modules/TradeExport`)**
- `Http/Controllers/Api/TradeReportApiController.php` (নতুন)
- `Support/ExportBook.php` (বদলানো: `realised()`), `Http/Controllers/Api/CashIncentiveApiController.php` (বদলানো: আদায় `realised()` দিয়ে)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/tradeop.php`, `lang/bn/tradeop.php` (বদলানো)

**Frontend**
- `frontend/src/components/tradeexport/reports.jsx` (নতুন)
- `app/(app)/trade-export/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/TradeExport/TradeExportOperationsTest.php`, `TradeExportImportTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** test2 DB-তে পাস। পুরো Trade & Export (১৬টা টেস্ট, ৪৫৫টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **`test_the_trade_and_export_reports` (৬৩টা assertion):** ৩১টা রিপোর্টই চলে। Export Invoice-এ X-factory, Bill Type, On Board, Negotiated আর Invoice No ছাঁকে; যে বক্স পড়ে না তা উপেক্ষিত। EARLY আর FINAL দুটোই চূড়ান্ত হওয়া বিল একবার গোনা: আদায় ১০০০, collected ৯৮০, বাকি ২০০; খরচ ২০ (২%)। Follow-up-এ "Realised" / "Not Billed"; Short Shipment ১০০০-এর ৬০০, কম ৪০০ (৪০%), মূল্য ৮০০। উল্টো আর এক বছরের বেশি তারিখ আটকায়; AUDITOR চালাতে পারেন, STOREKEEPER পারেন না।
- **`test_the_b2b_chain_from_lc_to_ud_and_transfer`-এ যোগ:** Statement (গৃহীত B/E ৩০০, IFDBC ৩০০, পরিশোধ ০), দায় ৩০০, IFDBC সূচি (৮ ডিসেম্বর, ৩০০, YKK), বকেয়া ৩০০, Customs & Bond (BE-1, UD-1, ৫০০, MRR-1), B2B Order (IP-1, PO-ACC-1), UD Summary (UD-1 আউট ১০০, UD-2 ইন ১০০), BBLC সীমা ১০০০, Fund Forecast ডিসেম্বরে ব্যয় ৩০০।

**UI চেক (`app_check/plans/tx10`; error 0):** dev ডাটায় Export Invoice (BCL-EX-0001, ১,৮০০), LC Wise Realization (LC-0098812: রপ্তানি, বিল, আদায়, collected ১,৮০০, ১০০%), Statement Of Import LC (BBLC-KY-0002 আর 270126020091), Exp. LC Wise Cash Incentive (CIA-2026-0101, ১৪০.৬০) দেখায়। IFDBC Payment Schedule আর Fund Forecast খালি, কারণ dev-এর IFDBC ৮ জানুয়ারি ২০২৭-এ ম্যাচিউর — ডিফল্ট তারিখের (আজ পর্যন্ত) বাইরে।

## ১০. খোলা প্রশ্ন

১. **রিপোর্টের কলাম:** Brickland-এ এই স্ক্রিন শুধু প্যারামিটারের ফর্ম; রিপোর্টের ভেতরটা এখনো বানানো হয়নি ("report bodies are not wired to data yet")। তাই ৩১টা রিপোর্টের কলাম আর হিসাব আমরা নিজেদের ডাটা থেকে ঠিক করেছি (৫ নম্বর অংশে তালিকা)। কোনো রিপোর্টে নির্দিষ্ট কলাম বা ফরম্যাট (যেমন ব্যাংকের ছাপা ফর্ম) লাগলে জানাবেন।

২. **নিষ্ক্রিয় বক্স:** Brickland-এ সব রিপোর্টে সব বক্স খোলা থাকে। আমরা 21/07-এর মতো যে বক্স রিপোর্ট পড়ে না সেটা নিষ্ক্রিয় রেখেছি। সব খোলা চাইলে জানাবেন।

৩. **Fund Forecast-এর তারিখ:** সামনের দিকের রিপোর্ট, কিন্তু ডিফল্ট তারিখ আজ পর্যন্ত। আলাদা ডিফল্ট (যেমন আজ থেকে তিন মাস) চাইলে জানাবেন।

৪. **Weekly Margin & BBLC-র সপ্তাহ:** শনিবার থেকে শুক্রবার ধরা হয়েছে। অন্য দিন চাইলে জানাবেন।

৫. **Cost Against Realization-এর খরচ:** খরচ = Invoice Amount − Collected (ব্যাংকের কাটা), সাথে FBC আর জরিমানা আলাদা কলামে। Lodgement-এর চার্জ বা অন্য খরচ যোগ করতে হবে কিনা?

## ১১. As-built নোট

- **Brickland প্রোব:** `rprobe.mjs` দিয়ে ৩১টা রিপোর্ট চালানো হয়েছে; কোনোটাতেই টেবিল আসেনি। `jsgrep.mjs` দিয়ে chunk পড়ে দেখা গেল Preview শুধু বাছাই করা প্যারামিটারের তালিকা দেখায়। তাই scaffold নিয়মে পুরো রিপোর্ট বানানো হয়েছে।
- **একবার গোনা আদায়:** রিপোর্ট বানাতে গিয়ে দেখা গেল EARLY আর FINAL দুটোই চূড়ান্ত হলে Cash Incentive (22/05)-এর "Realization Value" দুবার গুনত। `ExportBook::realised()` দিয়ে দুই জায়গাতেই ঠিক করা হয়েছে।
- **মডিউল সম্পূর্ণ:** এই স্ক্রিন দিয়ে Trade & Export-এর ২২টা স্ক্রিনই তৈরি (22/01–22/06)।
