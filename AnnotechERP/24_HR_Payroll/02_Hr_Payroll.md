# HR & Payroll

> **স্ট্যাটাস:** ✅ সম্পন্ন (২টার ২টা স্ক্রিন — Dashboard আর ৪৭টা রিপোর্টের Reports)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/02`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dashboard | `/m/hr-payroll/hr-dashboard` | 1 | 0 | 0 |
| 2 | Reports | `/m/hr-payroll/hr-reports` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dashboard

`/m/hr-payroll/hr-dashboard`

**ট্যাব: OT Approval 0**
- গ্রিড কলাম: #, Line, Total, Present, Absent, OT Emp., OT Hours
- সেকশন: Designation-wise Manpower, Line-wise Manpower (Sewing), Attendance Summary (Today), Today's OT (Top 5 Lines), Pending Approvals, Recent Activities
- বাটন/অ্যাকশন: Today, This Month, Sewing, OT Approval 0, Leave Approval 0, Attendance 0

#### Reports

`/m/hr-payroll/hr-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | All, Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Employee | text |  | Code, name or card |
| Employee Status | select |  | All, Active, Inactive, Suspended, Separated |
- সেকশন: Report Categories, 1. Employee Master
- বাটন/অ্যাকশন: 1. Employee Master, 2. Employee List, 3. Employee Assignment, 4. Joining Report, 5. Employee Status, 6. Employee Movement, 7. Total Manpower, 8. Designation-wise Manpower, 9. Floor-wise Manpower, 10. Department-wise Manpower, 11. Section-wise Manpower, 12. Line-wise Manpower, 13. Daily Attendance, 14. Monthly Attendance, 15. Present Report, 16. Absent Report, 17. Late Report, 18. Early Out Report, 19. Missing Punch Report, 20. Shift-wise Attendance
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

রিপোর্টগুলো নিজের কোনো টেবিল রাখে না; অন্য HR স্ক্রিনগুলোর রেকর্ড পড়ে:
- **কর্মী (24/03):** `hr_employees`, `hr_employee_assignments`, `dev_fingerprints` (ফিঙ্গারপ্রিন্ট নিবন্ধিত কিনা)।
- **হাজিরা (24/04):** `hr_attendance_days`, `hr_attendance_corrections`, `hr_shifts`।
- **ছুটি ও OT (24/05):** `hr_leave_applications`, `LeaveBook::balances`, `hr_ot_corrections`, `hr_ot_correction_history`, `hr_ot_requests`, `OtBook::eligibility`।
- **বেতন (24/06):** `hr_daily_salaries`, `hr_salary_slips` + `hr_salary_runs`, `hr_allowances`, `hr_bonus_lines`, `hr_payroll_adjustments`, `SalaryBook::rates`।
- **মুভমেন্ট ও বিচ্ছেদ (24/07, 24/08):** `hr_transfers`, `hr_promotions`, `hr_increments`, `hr_rejoins`, `hr_separations`, `hr_lefty_cases`, `hr_settlements`।
- **জনবলের ধরন:** `hr_designation_groups.manpower_category` (Operator / Quality / Helper / Supervisor / Staff)।
- **Letterhead:** `LetterBook::letterhead()` (24/09)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল বা মাইগ্রেশন নেই।

রিপোর্টের উত্তরের গঠন (`HrReportBook::run`):
- tiles: লেবেল, মান, ধরন।
- columns: key, লেবেল, kind (text / int / num / money / hours / pct / date)।
- rows: কাঁচা মান; স্ক্রিন ফরম্যাট করে।
- foot: মোটের সারি।
- empty / note: খালি থাকলে কী বলবে, আর গ্রিডের নিচের লাইন।
- সর্বোচ্চ ৫০০০ সারি।

## ৫. Workflow / Status ও Business Rules

**Reports — ৬টা ধরনে ৪৭টা রিপোর্ট (Brickland-এর নম্বর ও ক্রম; ২২–২৪ নম্বর Brickland-এর তালিকায় নেই):**
- **কর্মী (১–৬):** Employee Master (৩২ কলাম), Employee List, Employee Assignment (খোলা পোস্টিং আছে কিনা), Joining, Employee Status, Employee Movement (বদলি, পদোন্নতি, ইনক্রিমেন্ট, পুনঃযোগদান, বিচ্ছেদ একসাথে)।
- **জনবল (৭–১২):**
  - Total Manpower: ১৪টা measure।
  - Designation / Floor / Department / Section / Line অনুযায়ী জনবল।
  - এক দিনের সক্রিয় কর্মী গোনা হয়: উপস্থিত = PRESENT বা MISSING_PUNCH; অনুপস্থিত = ABSENT বা কোনো রেকর্ড নেই; ছুটি; সাপ্তাহিক / সরকারি ছুটি।
  - Line অনুযায়ী জনবলে OT কর্মী আর ঘণ্টাও।
- **হাজিরা (১৩–২১):**
  - Daily Attendance (অবস্থা ফিল্টারে Late / Early Out ফ্ল্যাগ ধরে), Monthly Attendance, Present।
  - Absent: "Marked absent" আর "No punch" আলাদা করে।
  - Late, Early Out, Missing Punch (IN বা OUT কোনটা নেই)।
  - Shift-wise ("No shift set" সারিসহ)।
  - Attendance Correction: Miss Punch Confirmation / Daily Correction; কোন পাঞ্চ, আগে → পরে।
- **OT (২৫–২৯, ৪৪–৪৬):**
  - OT হাজিরা থেকে নিজে আসে; সংশোধন থাকলে তার ঘণ্টা ও রেটে টাকা।
  - ধরন (General / Worker) দিনের ot_type, নইলে কর্মীর OT যোগ্যতা থেকে।
  - Daily, Employee / Section / Line / Grade অনুযায়ী, OT Salary Summary।
  - Monthly OT: General আর Worker আলাদা, গ্রেডওয়ারি টাইল।
  - OT Correction History: আগে-পরে ঘণ্টা, রেট আর টাকা, নিট পরিবর্তন।
- **বেতন (৩০–৩৮, ৪৭–৫০):**
  - Daily Salary, Employee-wise Daily Salary।
  - পে-পিরিয়ডের স্লিপ থেকে Monthly Salary, Salary Register, Salary Deduction, Bank Salary Sheet আর Gross A।
  - Payslip: পে-পিরিয়ড আর কর্মী বাধ্যতামূলক।
  - Allowance (লেখা ভাতা), Bonus (উৎসব বা পে-পিরিয়ড)।
  - Payroll Adjustment। পেরোলের অবস্থা: run-এর অবস্থা, অপেক্ষমাণ সমন্বয় থাকলে "Adjustment Required"।
  - Gross B / Gross A+B: Complex Management এখনো নেই, তাই Brickland-এর মতো "Not processed in Complex"।
- **এইচআর (৩৯–৪৩):** Leave (তারিখের মধ্যে পড়া আবেদন), Leave Balance (অর্থবছর, `LeaveBook`), Promotion / Increment, Transfer (আগের → নতুন পোস্টিং), Final Settlement।
- **ফিল্টার:** বিভাগ বাছলে সেকশন আর লাইন সেই বিভাগের মধ্যে আসে। কর্মীর ঘরে কোড, কার্ড বা নামের অংশ।
- **ডিফল্ট:** আজ, মাসের ১ তারিখ থেকে আজ, এই মাস, চলতি পে-পিরিয়ড, চলতি অর্থবছর।
- **বোতাম:** View, Print, PDF (একই শিট, "Save as PDF" লেখা), Excel (সার্ভার থেকে .xlsx: শিরোনাম, হেডার, সারি, মোট)।

**Dashboard:**
- ১০টা টাইল: মোট কর্মী (মাসের শুরুর তুলনায় %), সক্রিয়, নিষ্ক্রিয় / সাময়িক বরখাস্ত, পদত্যাগী (অপেক্ষমাণ বিচ্ছেদ), নিশ্চিত লেফটি, আজ উপস্থিত (% · দেরি), আজ অনুপস্থিত (% · মিসিং পাঞ্চ), অপেক্ষমাণ OT অনুরোধ, এই মাসের OT ঘণ্টা, এই মাসের নিট প্রদেয় (বেতন দেখার অনুমতি থাকলে)।
- বিভাগ অনুযায়ী কর্মী (শীর্ষ ৫ + অন্যান্য), মাসের দিনওয়ারি হাজিরা (উপস্থিত / অনুপস্থিত / দেরি), মাসিক পেরোল সারাংশ।
- সাম্প্রতিক কর্মী, অপেক্ষমাণ বিচ্ছেদ, সাম্প্রতিক লেফটি, সর্বশেষ নোটিফিকেশন (ব্যবহারকারীর নিজের)।
- **Today's Operations:**
  - পদবি অনুযায়ী জনবল (চার্ট)।
  - লাইন অনুযায়ী জনবল (বিভাগ বাছা যায়; শুরুতে Sewing, নইলে সবচেয়ে বড় বিভাগ)।
  - হাজিরার সারাংশ: সময়মতো / দেরি / মিসিং পাঞ্চ / ছুটি / অনুপস্থিত / এখনো পাঞ্চ হয়নি।
  - OT শীর্ষ ৫ লাইন।
  - অপেক্ষমাণ অনুমোদন: OT (থাকলে), ছুটি, হাজিরা সংশোধন।
  - সাম্প্রতিক কার্যক্রম: ডিভাইস সিঙ্ক, নতুন কর্মী।
- **Today / This Month:** This Month হলে Today's Operations মাসের ১ তারিখ থেকে বাছা দিন পর্যন্ত কর্মী-দিন গোনে; উপরের টাইল দিনের হিসাবেই থাকে (Brickland-এর মতো)।

## ৬. API ও পারমিশন

পারমিশন: `hrpayroll.dashboard.view`, `hrpayroll.report.view`, `hrpayroll.report.export` (Print / PDF / Excel)। বেতনের রিপোর্টে এর সাথে `hrpayroll.salary.view` লাগে।
- Factory Admin / Factory Manager: তিনটাই।
- Auditor: Dashboard আর রিপোর্ট দেখা।

API (`/api/v1/hr-payroll/…`): `GET dashboard?date=&period=TODAY|MONTH&department=`, `GET reports` (ধরন, রিপোর্ট, ফিল্টারের অপশন, ডিফল্ট, letterhead), `POST reports/{key}` (চালানো), `GET reports/{key}/xlsx`।

## ৭. Frontend পেজ (লাইট থিম)

`frontend/src/components/hrpayroll/reports.jsx` আর `frontend/src/lib/hr-report-print.js`:
- **Dashboard** (`/hr-payroll/hr-dashboard`): সাইডবারে HR-এর প্রথম স্ক্রিন ("01 of 49")। তারিখ আর Today / This Month — পেজ রিলোড ছাড়া।
- **Reports** (`/hr-payroll/hr-reports`): বাঁয়ে Report Categories; ডানে ফিল্টার আর বোতাম, টাইল, খোঁজা যায় এমন গ্রিড আর মোটের সারি। রিপোর্ট বদলালে পেজ থাকে, url-এ `?report=…`।
- **Print / PDF:** Brickland-এর শিট — কোম্পানির letterhead, শিরোনাম ("Manpower Report · No. 8"), রিপোর্টের তারিখ / সময় / কে, Selection of Parameters, Summary, Report Data, মন্তব্য আর তিন স্বাক্ষর। চওড়া রিপোর্ট landscape-এ।
- Excel: `app/api/hr-payroll/report-export/[key]/route.js`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (২টার ২টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Support/HrReportBook.php` (৪৭টা রিপোর্টের সংজ্ঞা আর হিসাব, ফিল্টারের অপশন, জনবলের ধরন)
- `Support/HrDashboardBook.php`
- `Http/Controllers/Api/HrReportApiController.php`
- `Routes/api.php`, `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`rp`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/reports.jsx`, `frontend/src/lib/hr-report-print.js`, `frontend/src/app/api/hr-payroll/report-export/[key]/route.js` (নতুন)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/HrPayroll/HrReportTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrReportTest.php`, ৩টা টেস্ট, ১১৫টা assertion):** test2 DB-তে পাস। HrPayroll পুরো suite ৩২ টেস্ট / ১২৬৯ assertion আর MenuAccess ৬ / ৩৪ — সব পাস।
- **ক্যাটালগ ও কর্মী / জনবল:**
  - ৪৭টা রিপোর্ট, ৬টা ধরন; Payslip-এর বাধ্যতামূলক ঘর; ডিফল্ট; সেকশন; letterhead।
  - অজানা রিপোর্ট 404, ভুল তারিখ আটকায়।
  - Employee List (অবস্থা ও কার্ড দিয়ে খোঁজা), Employee Status-এর টাইল, Employee Master-এর ৩২ কলাম, Assignment, Joining-এর মোট (৪ জন, ৮৪,০০০), Movement ফাঁকা।
  - ১০ নভেম্বর: ৩ জন সক্রিয় — ২ জন উপস্থিত (একজন দেরি, একজনের OUT নেই), ১ জন অনুপস্থিত; বিচ্ছিন্ন কর্মী গোনায় নেই।
  - Operator / Helper / Supervisor ধরন (Line Chief নাম থেকে)।
  - Designation-এর মোট ৬৬.৭%, Section অনুযায়ী, লাইন না থাকলে "Not assigned"।
- **হাজিরা ও OT:**
  - Daily-র ৬টা টাইল; Late ফিল্টারে ০৯:১২, ১২ মিনিট।
  - Present ২ জন। Absent: ১০ নভেম্বর একজন চিহ্নিত, ১১ নভেম্বর ৩ জন "No punch"।
  - Late-এর মোট ১২, Missing Punch-এ OUT নেই, Monthly, Shift-wise-এ "No shift set"।
  - সংশোধিত OT (২ ঘণ্টা × ১০০ = ২০০) Daily / Employee / Section / Monthly OT-তে। Worker OT ফিল্টারে কিছু নেই।
  - পেরোল না থাকলে Monthly Salary-র বার্তা; কর্মী ছাড়া Payslip; Gross B-তে "Not processed in Complex"।
  - Excel: spreadsheet, নাম Late-Report.xlsx।
- **Dashboard ও কে পারেন:**
  - টাইল: মোট ৪, সক্রিয় ৩, পদত্যাগী ১, উপস্থিত ২, অনুপস্থিত ১, দেরি ১, মিসিং পাঞ্চ ১, OT ২ ঘণ্টা।
  - বেতন না থাকলে নিট প্রদেয় নেই। বিভাগ, মাসের ৩০ দিন, পদবি, সারাংশ, লাইন, OT শীর্ষ লাইন।
  - This Month: ৩০টা কর্মী-দিন, ২৭টা এখনো পাঞ্চ হয়নি। ভুল period আটকায়।
  - Auditor দেখেন, Excel পারেন না। Storekeeper কোনোটাই দেখেন না।

**UI চেক (`app_check/plans/hr02`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Dashboard: আজ, ১০ নভেম্বর, This Month — টাইল, চার্ট, পেরোল সারাংশ (৳৭৩,০৮৯), সাম্প্রতিক কর্মী / লেফটি / নোটিফিকেশন, লাইন অনুযায়ী জনবল, হাজিরার সারাংশ, কার্যক্রম ✔
- Reports: Designation-wise Manpower (১০ নভেম্বর) → View → Print (letterhead, Parameters, Summary, Report Data, তিন স্বাক্ষর) ✔
- Daily Attendance ✔ Monthly Salary → PDF (landscape) → Excel (Monthly-Salary-Report.xlsx) ✔ Payslip (EMP-000001, নিট ৳৯,৮৭৯) ✔ Leave Balance ✔

## ১০. খোলা প্রশ্ন

১. **Gross B রিপোর্ট (৪৮, ৪৯):** Brickland পরে "Complex Management" নামে ৮টা স্ক্রিনের নতুন ফোল্ডার যোগ করেছে, যেখানে Gross B পেরোল হয়। আমাদের ক্রলে সেটা নেই। তাই এখন Gross B রিপোর্ট Brickland-এর মতোই "Not processed in Complex" দেখায়। Complex Management বানাব কিনা জানাবেন।

২. **জনবলের ধরন:** Operator / Quality / Helper / Supervisor / Staff বের হয় পদবির সমান নামের Designation Group থেকে; না মিললে পদবির নাম দেখে (helper, quality, supervisor, line chief, operator …); তাতেও না মিললে Worker → Operator, অন্যরা → Staff। পদবিতে সরাসরি ধরন বসানোর ঘর লাগবে কিনা জানাবেন।

৩. **Bank Salary Sheet:** কর্মীর বেতনের ব্যাংক অ্যাকাউন্ট (ব্যাংক, শাখা, অ্যাকাউন্টের নাম ও নম্বর) রাখার ঘর এখনো নেই, তাই এসব কলাম "—" আর সবাই নগদ। কর্মীর প্রোফাইলে এই ঘর যোগ করব কিনা জানাবেন।

৪. **Latest Notifications:** Brickland HR-এর নিজের ঘটনা (কর্মী নিবন্ধন, প্রোফাইল বদল) দেখায়। আমাদের এখানে ব্যবহারকারীর নিজের নোটিফিকেশন। HR-এর ঘটনার আলাদা লগ চাইলে জানাবেন।

## ১১. As-built নোট

- **Brickland বদলেছে:** ক্রলে Dashboard-এর একটা ট্যাব আর Reports-এ ৩টা ফিল্ড ছিল। লাইভ প্রোবে (`tools/erp_crawler/out/hr02`, `hp02r`, `hp02s`, `hp02p`, `hp02d`) পাওয়া গেছে: পুরো ড্যাশবোর্ড আর ৪৭টা রিপোর্ট।
- **নতুন প্রোব টুল:** `tools/erp_crawler/listprobe.mjs` — নম্বর দেওয়া রিপোর্টের তালিকা থেকে প্রতিটা খুলে View চাপে, টাইল / কলাম / প্রথম সারি / স্ক্রিনশট রাখে। `DATES`, `MONTH`, `EMP`, `PRINT` দিয়ে তারিখ, মাস, কর্মী আর Print / PDF-এর popup।
- **ডেমোতে ডাটা নেই এমন রিপোর্ট:** Daily OT, OT Correction History, Daily Salary, Payslip, Allowance, Bonus, Payroll Adjustment, Leave, Leave Balance, Promotion / Increment, Transfer-এর কলাম বিবরণ আর টাইল দেখে বানানো (Brickland এগুলোর কলাম সার্ভার থেকে পাঠায়, JS-এ নেই)।
- **সাইডবারের ক্রম:** Dashboard শুরুতে, Reports শেষে, ফোল্ডার ছাড়া (Brickland-এর মতো)।
- **UI চেকে ধরা পড়া:** "x মিনিট আগে" সার্ভার আর ব্রাউজারে আলাদা হয়ে hydration error দিচ্ছিল — এখন mount-এর পরে দেখায়। Payslip-এ শূন্য কর্তন "-0.00" দেখাচ্ছিল। চওড়া টেবিল print শিটের বাইরে যাচ্ছিল। ভুল তারিখের ফিল্টার যাচাই হচ্ছিল না (test ধরেছে)।
