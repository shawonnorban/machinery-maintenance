# Reports

> **স্ট্যাটাস:** 🟡 প্রায় সম্পন্ন (৩০টার ২৯টা রিপোর্ট; Sew S/C Production সাব-কন্ট্রাক্টের উৎস না আসা পর্যন্ত বাকি)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/09`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Apparel Assembly Reports | `/m/garments/garments-reports` | 1 | 34 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Apparel Assembly Reports

`/m/garments/garments-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Sewing M/C Specification | radio |  |  |
| Order, Color and Size wise Hourly Production | radio |  |  |
| Hourly Target and Achivement | radio |  |  |
| Daily End Line Quality Inspection | radio |  |  |
| Daily Line Wise Defect Analysis Report | radio |  |  |
| Order Closing Report | radio |  |  |
| Daily End Table Inspection Report | radio |  |  |
| Monthly Floor Wise Defect Analysis Report | radio |  |  |
| Daily End Table Inspection Report-1 | radio |  |  |
| Hourly Defect Summary Report | radio |  |  |
| Order Closing Statement | radio |  |  |
| Daily Sewing Output by Floor | radio |  |  |
| Trims & Accessories Stock Ledger | radio |  |  |
| Daily Finishing Final Inspection Report | radio |  |  |
| Order Wise Garment Finishing And Export Statement | radio |  |  |
| Buyer A/C wise Actual Sewing Done Undone as per Booking | radio |  |  |
| Daily Fin Manpower Utilization Report | radio |  |  |
| Buyer A/C wise Sewing Done Undone as per Booking | radio |  |  |
| Buyer A/C wise GMT Booking as Per Capacity | radio |  |  |
| Nature Wise GMT Production Statement | radio |  |  |
| Buyer A/C wise Actual GMT Booking as Per Capacity | radio |  |  |
| Buyer A/C wise Knitting, Dyeing - Done, Undone and Summary | radio |  |  |
| Buyer A/C wise Sample Booking Status | radio |  |  |
| Buyer wise Yarn To Fabric Status | radio |  |  |
| Sew S/C Production | radio |  |  |
| Company wise Sewing Production | radio |  |  |
| Daily Store Receive & Input Report | radio |  |  |
| Daily Color Wise Store Receive | radio |  |  |
| Daily Line Wise Input | radio |  |  |
| Daily Color Wise Input | radio |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store (ST-CHM), Cutting Sub-Store (ST-CUT), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), GMT Sample St |
| From Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Assembly Floor-এর নিজের খাতা (20/01–20/08):** সুইং ইন / আউট, ফিনিশিং ইন / আউট, কোয়ালিটি ইন / ইন্সপেকশন / আউট, প্যাকিং ব্যাচ, Finished Goods লেজার, শিপমেন্ট, এক্সেসরিজ স্টোরের লেজার।
- **19 Cutting Floor-এর `cut_bundles`:** কাটিং আর কাটিং স্টোরে রিসিভ।
- **Planning আর Work Study:** `planning_lines` (জনবল, ঘণ্টা, এফিসিয়েন্সি, ফ্লোর, ইউনিট), `planning_line_machines`, `planning_style_smvs`, `planning_calendar_days`, `ws_sewing_targets`।
- **Merchandising:** অর্ডার, রং-সাইজের পরিমাণ, শিপ তারিখ, স্যাম্পল প্রোগ্রাম। **Knit Production / Dye House:** নিট প্রোডাকশন অর্ডার, কার্ড, রোল, সুতার রিকুইজিশন, ডাই ব্যাচ।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন কোনো টেবিল নেই; প্রতিটা রিপোর্ট উৎসের টেবিল থেকে প্রতিবার হিসাব করে।

| # | রিপোর্ট | প্যারামিটার | উৎস ও হিসাব |
|---|---|---|---|
| 1 | Sewing M/C Specification | Prod Line | লাইনের মেশিন (`planning_line_machines`): ধরন, নাম/কোড, ক্যাটাগরি, বসানো, চালু, স্ট্যান্ডবাই, ঘণ্টায় ক্যাপাসিটি |
| 2 | Order, Color and Size wise Hourly Production | Style/Order#, From, To | সুইং আউট, অর্ডার × রং × সাইজ, ঘণ্টা অনুযায়ী কলাম (ঢাকার সময়), মোট |
| 3 | Hourly Target and Achivement | Production Date | লাইনের টার্গেট (Work Study-র টার্গেট শিট), ঘণ্টায় টার্গেট = টার্গেট ÷ কর্মঘণ্টা, ঘণ্টায় আউটপুট, মোট, অর্জন % |
| 4 | Daily End Line Quality Inspection | Production Date | কোয়ালিটি ইন্সপেকশন, লাইন অনুযায়ী: বান্ডেল, ইন্সপেকশন, পাস, রিজেক্ট, রিওয়ার্ক, পাস %, DHU % |
| 5 | Daily Line Wise Defect Analysis Report | Prod Line (কোয়ালিটি), Production Date | লাইন × ত্রুটির ধরন: রিজেক্ট, রিওয়ার্ক, মোট, লাইনের মধ্যে অংশ % |
| 6 | Order Closing Report | Buyer A/C, Style/Order# | অর্ডার: অর্ডার পরিমাণ, কাটিং, সুইং ইন / আউট, ফিনিশিং, QC পাস, প্যাক, শিপড, বাকি, কম/বেশি % |
| 7 | Daily End Table Inspection Report | Production Date | ফিনিশিং আউট, লাইন অনুযায়ী: বান্ডেল, রিসিভ, ভালো, রিজেক্ট, রিওয়ার্ক, ভালো % |
| 8 | Monthly Floor Wise Defect Analysis Report | From, To | মাস × ফ্লোর × ত্রুটি |
| 9 | Daily End Table Inspection Report-1 | Production Date | ফিনিশিং আউট, বান্ডেল ধরে (সময়, লাইন, অর্ডার, রং, সাইজ, রিওয়ার্কের অবস্থা) |
| 10 | Hourly Defect Summary Report | Production Date | ত্রুটি × ঘণ্টা |
| 11 | Order Closing Statement | Buyer A/C, Style/Order# | অর্ডার × রং × সাইজ: অর্ডার, কাটিং, সুইং আউট, ফিনিশিং, QC পাস, প্যাক (FG ইন), শিপড, বাকি, কম/বেশি % |
| 12 | Daily Sewing Output by Floor | Production Date | ফ্লোর × লাইন: ইনপুট, আউটপুট, টার্গেট, অর্জন % |
| 13 | Trims & Accessories Stock Ledger | Store, Item, From, To | এক্সেসরিজ স্টোরের চলাচল: আইটেম ধরে আগের জের, তারপর প্রতিটা রিসিট / ইস্যু / রিটার্ন আর চলমান ব্যালেন্স |
| 14 | Daily Finishing Final Inspection Report | Production Date | কোয়ালিটি ইন্সপেকশন, বান্ডেল ও রাউন্ড ধরে |
| 15 | Order Wise Garment Finishing And Export Statement | Buyer A/C, Style/Order#, From, To | সময়ের মধ্যে ফিনিশিং ভালো, QC পাস, প্যাক, শিপড, কার্টন |
| 16 | Buyer A/C wise Actual Sewing Done Undone as per Booking | Buyer A/C, From, To | ওই সময়ে শিপ হওয়া অর্ডার: বুকড (অর্ডার) পরিমাণ, এ পর্যন্ত সেলাই, বাকি, সম্পন্ন % (Brickland-এর কলাম) |
| 17 | Daily Fin Manpower Utilization Report | Production Date | ফিনিশিং লাইন: জনবল, টার্গেট, আউটপুট, জনপ্রতি, অর্জন % |
| 18 | Buyer A/C wise Sewing Done Undone as per Booking | Buyer A/C, From, To | বায়ার ধরে: অর্ডার, বুকড, ওই সময়ের মধ্যে সেলাই, বাকি, সম্পন্ন % |
| 19 | Buyer A/C wise GMT Booking as Per Capacity | Buyer A/C, Company, From, To | বায়ারের বুকড পরিমাণ × SMV = মিনিট; সুইং লাইনের ক্যাপাসিটি (জনবল × ঘণ্টা × ৬০ × এফিসিয়েন্সি × কর্মদিবস); অংশ % |
| 20 | Nature Wise GMT Production Statement | From, To | আইটেম · গার্মেন্টের ধরন অনুযায়ী অর্ডার, ইনপুট, আউটপুট |
| 21 | Buyer A/C wise Actual GMT Booking as Per Capacity | Buyer A/C, Company, From, To | ১৯-এর মতো, তবে বুকিংয়ের বদলে সময়ের মধ্যে আসলে সেলাই হওয়া পরিমাণ |
| 22 | Buyer A/C wise Knitting, Dyeing - Done, Undone and Summary | Buyer A/C, From, To | ওই সময়ে শিপ হওয়া অর্ডার: কাপড় দরকার (নিট প্রোডাকশন অর্ডার), নিটিং হয়েছে (রোলের ওজন), ডায়িং হয়েছে (ডাই ব্যাচ), বাকি, % |
| 23 | Buyer A/C wise Sample Booking Status | Buyer A/C, From, To | স্যাম্পল প্রোগ্রামের লাইন: ধরন, আইটেম, রং, সাইজ, চাহিদা, সেলাই, ডেলিভারি, রিজেক্ট, বাকি, অবস্থা |
| 24 | Buyer wise Yarn To Fabric Status | Buyer A/C, From, To | অর্ডার: সুতা ইস্যু (নিটিং রিকুইজিশন), গ্রে কাপড় (রোল), ব্যালেন্স, প্রসেস লস % |
| 25 | Company wise Sewing Production | Company, From, To | ইউনিট × দিন: ইনপুট, আউটপুট |
| 26 | Daily Store Receive & Input Report | Production Date | অর্ডার × রং: কাটিং স্টোরে রিসিভ (সেদিন কাটা বান্ডেল), সুইং ইনপুট |
| 27 | Daily Color Wise Store Receive | Production Date | অর্ডার × রং: সাইজ ভাগ, বান্ডেল, পরিমাণ |
| 28 | Daily Line Wise Input | Prod Line, Production Date | সুইং ইন, বান্ডেল ধরে |
| 29 | Daily Color Wise Input | Production Date | অর্ডার × রং: লাইন, বান্ডেল, পরিমাণ |
| — | Sew S/C Production | From, To | **বাকি (deferred):** সেলাইয়ের সাব-কন্ট্রাক্ট প্রোডাকশনের কোনো উৎস এখনো নেই |

## ৫. Workflow / Status ও Business Rules

- **স্ক্রিন:** "Selection of Parameters" বক্সে বাঁয়ে রিপোর্টের তালিকা (রেডিও), ডানে শুধু বাছাই করা রিপোর্টের প্যারামিটার (Brickland-এর মতো), "Export to Excel?" আর Preview।
- **Preview:** নিচে রিপোর্টের নাম, বাছাই করা প্যারামিটার আর সারির সংখ্যা, তারপর টেবিল আর Total সারি। "Export to Excel?" দিলে একই রিপোর্ট Excel-এ খোলার মতো CSV ফাইলে নামে।
- **বাধ্যতামূলক:** Production Date (যেখানে লাগে), From / To (যেখানে লাগে), Trims Ledger-এ Store। তারিখ উল্টো বা ৩৬৬ দিনের বেশি হলে আটকায়।
- **Buyer বাছলে** Style/Order#-এর তালিকা সেই বায়ারের অর্ডারে ছোট হয়।
- **ঘণ্টার কলাম** ঢাকার সময়ে স্ক্যানের ঘণ্টা ধরে ("09-10", "10-11"), যে ঘণ্টায় কিছু আছে শুধু সেগুলো।
- **কর্মদিবস (ক্যাপাসিটি):** Planning-এর ক্যালেন্ডারে ছুটি দেওয়া দিন বাদ; ক্যালেন্ডারে না থাকলে শুক্রবার বাদ।

## ৬. API ও পারমিশন

- **পারমিশন:** `assembly.report.view`। FACTORY_ADMIN, FACTORY_MANAGER আর LINE_CHIEF পায়।
- **API (`AssemblyReportApiController`):**
  - `GET /assembly/reports/options` — রিপোর্টের তালিকা (প্রতিটার প্যারামিটারসহ), বায়ার, অর্ডার, সুইং লাইন, কোয়ালিটি লাইন, ইউনিট, স্টোর, ট্রিমস আইটেম
  - `GET /assembly/reports/run?kind=&date=&from=&to=&buyer=&order=&line=&qline=&unit=&store=&item=` — কলাম (ঘণ্টার কলামে নিজের লেবেল), সারি, Total
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার REPORTS, স্ক্রিন `/assembly-floor/garments-reports`। ACCESSORIES STORE ফোল্ডারের আইকন Brickland-এর মতো Warehouse করা হয়েছে।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/reports.jsx` (`AssemblyReports`)। একই `assembly-floor/[screen]` রুট।
- **লেআউট:** Brickland-এর মতো দুই কলাম: রিপোর্টের রেডিও তালিকা আর ডানে প্যারামিটার (লেবেল বাঁয়ে, ঘর ডানে), Export to Excel?, Preview; নিচে রিপোর্ট। Brickland-এ এই স্ক্রিনে আলাদা Print বাটন নেই, তাই রাখা হয়নি।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩০টার ২৯টা রিপোর্ট):

**Backend**
- `app/Modules/Assembly/Http/Controllers/Api/AssemblyReportApiController.php`
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/reports.jsx` (নতুন)
- `frontend/src/app/(app)/assembly-floor/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyReportTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyReportTest.php`):** test2 DB-তে পাস, ২টা টেস্ট, ১৬২টা assertion। পুরো Assembly-র ২৪টা টেস্ট (১,৩৪২টা assertion) চালানো হয়েছিল; একটা (Colour Store Receive-এ সাইজের ক্রম কখনো M আগে আসছিল) ধরা পড়ে, সাইজ ক্রম ঠিক করে আবার চালানো হয়েছে — পাস। MenuAccessTest + RoleMatrixTest (১৩টা, ৮৪টা assertion) পাস।
- `test_every_report_runs_and_reads_the_floor_books`: দুটো বান্ডেল (S ১০ পাস ১০, M ৮ পাস ৭ রিজেক্ট ১) প্যাক করে ১২ শিপ।
  - ২৯টা রিপোর্টই শুধু নিজের প্যারামিটারে চলে।
  - Order Closing: অর্ডার ৬০০, কাটিং ১৮, সুইং ইন / আউট ১৮, ফিনিশিং ১৮, QC পাস ১৭, প্যাক ১৭, শিপড ১২, বাকি ৫৮৮। Statement: S / M / L সারি (অর্ডারের সাইজ যেখানে কিছু কাটা হয়নি সেটাও)।
  - End Line QC: ১৮ / পাস ১৭ / রিজেক্ট ১, পাস ৯৪.৪ %, DHU ৫.৬ %। Line Defect: Stitching ১ (১০০ %)। Hourly Defect-এর কলাম "10-11"। Final Inspection-এ ২টা সারি।
  - Hourly Production: S ১০ আর M ৮, দুটোই ১০-১১ ঘণ্টায়, মোট ১৮। Actual Sewing Done/Undone: ডিসেম্বরে শিপ হওয়া অর্ডার, বুকড ৬০০, সেলাই ১৮, বাকি ৫৮২, ৩ %; নভেম্বরে কোনো অর্ডার নেই। Sewing Done/Undone: সময়ের মধ্যে সেলাই।
  - Store Receive & Input (কাটার দিন রিসিভ ১৮, সেলাইয়ের দিন ইনপুট ১৮), Colour Store Receive ("S:10 M:8"), Line Input, Sewing by Floor।
  - End Table, Fin Manpower (১২ জন, ১৮, জনপ্রতি ১.৫, ৪.৫ %), Finishing & Export (১৮ / ১৭ / ১৭ / ১২, ১ কার্টন)।
  - তারিখ ছাড়া, স্টোর ছাড়া Trims Ledger, উল্টো তারিখ আর অচেনা রিপোর্ট (sew_sc) আটকায়।
- `test_trims_ledger_capacity_and_who_may`: Trims Ledger-এ আগের জের ৫০০, ইস্যু ১২০ → ৩৮০, রিটার্ন ৩০ → ৩৫০। Booking Capacity: ৬০০ × ১২.৫ SMV = ৭,৫০০ মিনিট, ক্যাপাসিটি ১৫,৬০০ × ২৭ কর্মদিবস (ডিসেম্বরের শুক্রবার বাদ), অংশ %; Actual-এ ডিসেম্বরে সেলাই নেই। LINE_CHIEF পায়, STORE_MANAGER পায় না।

**UI চেক (`AnnotechERP/tools/app_check/plans/ar01`), dev অ্যাপে:**
- Order Closing Report (SS27-TEE01 — LPP-2026-000001): অর্ডার ১,৯০০, কাটিং ৭২, সুইং ইন ৭১ / আউট ৬৫, ফিনিশিং ৬৩, QC পাস ৬২, প্যাক ৬২, শিপড ৪০, বাকি ১,৮৬০, Total সারিসহ।
- Order Closing Statement: Navy Blue S (৬০০ / কাটিং ৭২ / … / শিপড ৪০), M আর L (এখনো কাটা হয়নি)।
- Daily End Line Quality Inspection (০৭-১১): Quality Line1 — ৬৫ ইন্সপেকশন, পাস ৬২, রিজেক্ট ১, রিওয়ার্ক ২, ৯৫.৪ % / DHU ৪.৬ %। Hourly Defect: "02-03" ঘণ্টায় Stitching ৩।
- Hourly Production (০১–০৭ নভেম্বর): S ৬৫ পিস "21-22" ঘণ্টায়।
- Trims & Accessories Stock Ledger (Trims & accessories store, নভেম্বর): সুতা — জের ০, রিসিট ১২০, ইস্যু ৪০ → ৮০; বাটন — রিসিট ২,০০০, রিসিট রিটার্ন ৫০ → ১,৯৫০। "Export to Excel?" দিয়ে CSV ফাইল নামল।
- Actual Sewing Done Undone: দুটো অর্ডার (১,৯০০-এর ৬৫ সেলাই, ৩.৪ %; ১,০০০-এর ০)।
- **error:** ০।

## ১০. খোলা প্রশ্ন

১. **রিপোর্টের কলাম:** Brickland-এর ডেমোতে ৩০টার মধ্যে শুধু "Buyer A/C wise Actual Sewing Done Undone as per Booking" ডাটা দেখায় (তার কলাম হুবহু নেওয়া হয়েছে)। বাকি ২৮টার কলাম রিপোর্টের নাম আর আমাদের খাতা থেকে ঠিক করা হয়েছে (উপরের টেবিল দেখুন)। কোনো রিপোর্টের কাঙ্ক্ষিত ফরম্যাট / কলাম থাকলে নমুনা দেবেন।

২. **"Actual" বনাম "as per Booking":** এখন "Actual Sewing Done Undone" = এ পর্যন্ত মোট সেলাই বনাম অর্ডার (অর্ডার ধরে), আর "Sewing Done Undone" = বাছাই করা সময়ের মধ্যে সেলাই বনাম অর্ডার (বায়ার ধরে)। "Actual GMT Booking as per Capacity" = আসলে সেলাই হওয়া মিনিট, আর "GMT Booking as per Capacity" = অর্ডারের (বুকড) মিনিট। এই পার্থক্য ঠিক আছে কিনা জানাবেন।

৩. **End Line বনাম Final Inspection:** আমাদের সুইং-এ আলাদা এন্ড লাইন QC নেই; তাই "Daily End Line Quality Inspection" আর "Daily Finishing Final Inspection" দুটোই 20/03-এর কোয়ালিটি ইন্সপেকশন থেকে (প্রথমটা লাইনের সারসংক্ষেপ, দ্বিতীয়টা বান্ডেল ধরে)। "End Table Inspection" ফিনিশিংয়ের আউট থেকে। সুইং লাইনে আলাদা এন্ড লাইন QC লাগবে কিনা জানাবেন।

৪. **Sew S/C Production:** সেলাইয়ের সাব-কন্ট্রাক্ট (বাইরে সেলাই করানো) প্রোডাকশন রাখার কোনো স্ক্রিন / টেবিল এখনো নেই, তাই এই রিপোর্টটা বাকি রাখা হয়েছে। কোন মডিউলে সাব-কন্ট্রাক্ট সেলাই আসবে জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/rprobe.mjs` দিয়ে ৩০টা রিপোর্টই চালানো, `plans/gr02`):** প্রতিটা রিপোর্ট কোন প্যারামিটার চালু করে সেটা নেওয়া হয়েছে (Production Date, From / To Date, Buyer A/C, Style/Order#, Prod Line, Store ইত্যাদি)। ডেমোতে প্রায় সব রিপোর্ট ফাঁকা; শুধু Actual Sewing Done Undone-এর কলাম পাওয়া গেছে।
- **Sew S/C Production বাকি:** নিয়ম অনুযায়ী ("রিপোর্ট তার উৎস তৈরি হওয়ার পরে") উৎস না থাকায় তালিকায় রাখা হয়নি; উৎস এলে যোগ হবে।
- **Assembly Floor (20) মডিউলের অবস্থা:** ২০/০১–২০/০৮ সম্পন্ন, ২০/০৯-এ একটা রিপোর্ট বাকি।
