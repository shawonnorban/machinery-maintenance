# Salary Processing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন — ক্রলের ৪টা আর Brickland-এ পরে যোগ হওয়া Payroll Adjustment)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/06`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Daily Salary | `/m/hr-payroll/daily-salary` | 1 | 4 | 0 |
| 2 | OT Salary | `/m/hr-payroll/ot-salary` | 1 | 3 | 0 |
| 3 | Festival Bonus | `/m/hr-payroll/festival-bonus` | 1 | 1 | 0 |
| 4 | Monthly Salary | `/m/hr-payroll/monthly-salary` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Daily Salary

`/m/hr-payroll/daily-salary`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Search Daily salary per employee | search |  | Search code, name, line… |
| Select all rows on this page | checkbox |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Employee, Dept / Section / Line, Attendance, Daily Salary, Allowance, OT, Late Ded., Other Ded., Net, Status
- সেকশন: Load Attendance, Daily Salary — 2026-10-09
- বাটন/অ্যাকশন: Bulk, Individual, Department-wise, Section-wise, Line-wise, All departments, All sections, All lines, All employees, Load, Calculate, Review, Approve, CSV, Employee, Dept / Section / Line, Attendance, Daily Salary, Allowance, OT

#### OT Salary

`/m/hr-payroll/ot-salary`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Employee | select |  | All |
- গ্রিড কলাম: Date, Employee, Department, Section, Line, Shift, Regular Out, OT Start, OT End, OUT Punch, Approved h, Actual OT h, OT Rate, OT Amount
- সেকশন: OT Details
- বাটন/অ্যাকশন: All, Show, Recalculate, Excel

#### Festival Bonus

`/m/hr-payroll/festival-bonus`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Payroll Month | month |  |  |
- সেকশন: Festival Check

#### Monthly Salary

`/m/hr-payroll/monthly-salary`

**ট্যাব: Salary Review 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pay Period* | select | ✔ | Select…, APP-BCLFY2026-27090002 · September 2026, APP-BCLFY2026-27090001 · September 2026 |
| Search Monthly salary per employee | search |  | Search code, name, line… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Employee, Days, Gross, Allowance, OT, Bonus, Late, Other Ded., Loan/Adv., Net, Payslip
- সেকশন: Load Data, APP-BCLFY2026-27090002 · September 2026
- বাটন/অ্যাকশন: Bulk, Individual, Department-wise, Section-wise, Line-wise, All departments, All sections, All lines, All employees, Load, Calculate, Salary Sheet CSV, Salary Review 0, Bank Payment 0, Cash Payment 0, Employee, Days, Gross, Allowance, OT
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Master Data (24/01):** `hr_pay_periods` (মাসিক পে পিরিয়ড, বন্ধ কিনা), `hr_allowances` / `hr_deductions` (পিরিয়ডের এককালীন ভাতা / কর্তন — লগইনের employee code দিয়ে কর্মীর সাথে মেলে), `hr_festivals` (পরিশোধের মাস, বোনাস নিয়ম, ন্যূনতম চাকরি), `hr_bonus_rules` (চাকরির মাস আর বেতনের সীমা, Basic / Gross, %, নির্দিষ্ট টাকা), `hr_gazette_grade_wages` (গ্রেডের ন্যূনতম মজুরি), `hr_payroll_settings` (দেরির কর্তনের নিয়ম, নিট রাউন্ডিং)।
- **কর্মী (24/03):** Gross A / Gross B, যোগদান ও বিচ্ছেদের তারিখ, পোস্টিং, `hr_employee_pay_lines` (মূল বেতনের বাইরের নিয়মিত ভাতা / কর্তন)।
- **হাজিরা (24/04):** `hr_attendance_days` — অবস্থা, দেরি, ছুটির প্রকার; `AttendanceBook::process` (OT Salary-র Recalculate)।
- **ছুটি ও OT (24/05):** `OtBook` (কে OT পান, হার, Basic), `hr_ot_corrections` (সংশোধিত OT), `hr_leave_types.paid`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_15_000100_create_hr_salary_processing`:
- **`hr_daily_salaries`:** কর্মী + তারিখে একটা; হাজিরার অবস্থা (বা NO_RECORD), ইন / আউট, দেরি, Gross A, দৈনিক বেতন, ভাতা, OT ঘণ্টা / হার / টাকা, দেরি ও অন্য কর্তন, নিট, অবস্থা (CALCULATED / REVIEWED / APPROVED), সতর্কতা, কে হিসাব / যাচাই / অনুমোদন করেছেন।
- **`hr_salary_runs`:** পে পিরিয়ডে একটা রান — অবস্থা (CALCULATED / REVIEWED / APPROVED / FINALIZED / PAID), প্রতিটা ধাপের কে আর কখন।
- **`hr_salary_slips`:** পিরিয়ড + কর্মীতে একটা স্লিপ — পোস্টিং (বিভাগ, সেকশন, লাইন, পদবি, গ্রেড), Gross A / B, Basic, পিরিয়ডের দিন, উপস্থিত, অনুপস্থিত, বিনা বেতনের দিন, প্রদেয় দিন, ছুটির কর্তন, অর্জিত Gross, ভাতা, OT, বোনাস, দেরির দিন ও কর্তন, স্ট্যাম্প, অন্য কর্তন, লোন / অগ্রিম, সমন্বয়, নিট, ভাতা / কর্তনের খাত (পে-স্লিপের জন্য), অবস্থা (DRAFT / PROCESSED / PAID), পরিশোধের ধরন (BANK / CASH), আটকে রাখা, মজুরি কাঠামো, ন্যূনতম মজুরি আর তার নিচে কিনা।
- **`hr_bonus_runs` / `hr_bonus_lines`:** উৎসবে একটা বোনাস শিট — যে পে পিরিয়ডে দেওয়া হবে, অবস্থা (DRAFT / REVIEWED / APPROVED / FINALIZED / PAID), মোট; প্রতিটা কর্মীর সারি — যোগ্য কিনা, চাকরির দিন, ভিত্তি (Basic / Gross A) আর তার টাকা, %, নির্দিষ্ট, বোনাস, নিয়ম, অযোগ্যতার কারণ।
- **`hr_payroll_adjustments`:** রেফ নং (PADJ-000001…), চূড়ান্ত স্লিপ, পিরিয়ড, কর্মী, ধরন (OT / OTHER), সংশোধিত ঘণ্টা ও হার, মূল, সংশোধিত, সমন্বয় (সংশোধিত − মূল), কারণ, অবস্থা (PENDING / APPROVED / REJECTED / APPLIED), সিদ্ধান্ত আর নোট, যে বেতনে দেওয়া হলো।

## ৫. Workflow / Status ও Business Rules

**পরিসর (Daily আর Monthly):** Bulk (বিভাগ, সেকশন, লাইন, কর্মী — যেকোনোটা দিয়ে ছাঁকা), Individual (একজন), Department-wise, Section-wise, Line-wise। ফিল্টার বদলালে Load না চাপা পর্যন্ত হিসাব / অনুমোদন বন্ধ। কেবল সক্রিয় (আর Suspended) কর্মী, যারা ওই দিনে বা পিরিয়ডে চাকরিতে ছিলেন।

**OT:** হাজিরার OT ঘণ্টা × OT নিয়মের হার (24/05-এর OT Eligibility); OT Correction থাকলে তার ঘণ্টা আর হার।

**Daily Salary:**
- এক দিন = মাসিক Gross A ÷ ওই মাসের দিন। উপস্থিত, পাঞ্চ নেই, ছুটির দিন, সাপ্তাহিক ছুটি আর বেতনসহ ছুটিতে বেতন হয়। অনুপস্থিত, বিনা বেতনের ছুটি আর হাজিরা নেই এমন দিনে হয় না, সতর্কতাসহ।
- ভাতা আর অন্য কর্তন = কর্মীর নিয়মিত ভাতা / কর্তন ÷ মাসের দিন। দেরির কর্তন মাস শেষে, তাই এখানে ০।
- নিট = দৈনিক + ভাতা + OT − কর্তন।
- Calculate (সব বা টিক দেওয়া) → Review → Approve; অনুমোদিত দিন আবার হিসাবে বদলায় না, Un-approve করলে যাচাই অবস্থায় ফেরে। ভবিষ্যতের দিন হিসাব হয় না।

**OT Salary:** সর্বোচ্চ ৯২ দিনের সীমা, বিভাগ / সেকশন / লাইন / কর্মী দিয়ে ছাঁকা; প্রতিটা দিনের মূল আর প্রদেয় OT ঘণ্টা, হার, টাকা, Auto বা Corrected। পাঞ্চ নেই এমন দিন "হাজিরার অপেক্ষায়"। Recalculate পরিসরের হাজিরা আবার হিসাব করে। প্রথম ৫০০০ সারি দেখায়।

**Festival Bonus:**
- বেতনের মাস বাছলে Festival Calendar-এ যে উৎসবের পরিশোধ ওই মাসে সেগুলো আসে। একটা থাকলে নিজে থেকেই খোলে।
- প্রত্যেক সক্রিয় কর্মী যোগ্য, নইলে কারণসহ অযোগ্য: যোগদানের তারিখ নেই; উৎসবের পরে যোগদান; ন্যূনতম চাকরির (দিন) কম; Gross A নেই; কোনো নিয়মে পড়েন না।
- নিয়ম: উৎসবের বোনাস নিয়ম সেট থেকে যে নিয়মের চাকরির মাস আর বেতনের সীমায় কর্মী পড়েন, কর্মীর ধরন (বা তার ওপরের) আর বিভাগ (নিয়মে থাকলে) মিলে।
- বোনাস = ভিত্তি (Basic বা Gross A) × % + নির্দিষ্ট টাকা। % ১-এর কম বা সমান হলে ভগ্নাংশ (০.৫ = ৫০%), বেশি হলে শতাংশ।
- হিসাবের আগে প্রিভিউ। Calculate → Mark Reviewed → Approve → Finalize (লক) → Mark Paid; যাচাই বা অনুমোদিত শিট খসড়ায় ফেরানো যায়।
- যা আটকায়: বোনাস নিয়ম নেই; ওই মাসের পে পিরিয়ড নেই; ওই মাসের বেতন আগেই চূড়ান্ত।
- চূড়ান্ত বা পরিশোধিত বোনাস পরিশোধের মাসের স্লিপে যায়।

**Monthly Salary:**
- পে পিরিয়ড (খোলা, বা যার রান আছে) আর পরিসর বেছে Load। Calculate পরিসরের সব কর্মীর স্লিপ বানায় বা আবার হিসাব করে। রান যাচাই বা অনুমোদিত থাকলে আবার হিসাবে "Calculated"-এ ফেরে। বন্ধ পিরিয়ড আর চূড়ান্ত রান হিসাব হয় না।
- স্লিপ:
  - বিনা বেতনের দিন = যোগদানের আগের আর বিচ্ছেদের পরের দিন + অনুপস্থিত + বিনা বেতনের ছুটি; ছুটির কর্তন = Gross A ÷ পিরিয়ডের দিন × সেই দিন।
  - ভাতা = নিয়মিত ভাতা + পিরিয়ডের ভাতা।
  - OT = পিরিয়ডের সব OT।
  - বোনাস = চূড়ান্ত উৎসব বোনাস।
  - দেরি = Payroll Settings অনুযায়ী (প্রতি ৩ দিন দেরিতে এক দিনের বেসিক, বা প্রতি মিনিট, বা নেই)।
  - অন্য কর্তন = ৳১০ স্ট্যাম্প (কিছু পেলে) + নিয়মিত আর পিরিয়ডের কর্তন।
  - লোন / অগ্রিম ০।
  - সমন্বয় = আগের পিরিয়ডের অনুমোদিত পেরোল সমন্বয়।
  - নিট = Gross + ভাতা + OT + বোনাস + সমন্বয় − দেরি − অন্য কর্তন − লোন, Payroll Settings-এর রাউন্ডিংয়ে।
- মজুরি কাঠামো: গ্রেডের ন্যূনতম Gross (গেজেটে থাকলে), নইলে নির্দিষ্ট ভাতাগুলোর যোগফল; তার নিচে হলে সতর্কতা।
- Suspended কর্মী Held, ব্যাংক / নগদ তালিকায় নেই। ব্যাংক হিসাব দেওয়ার জায়গা এখনো নেই, তাই সবাই নগদ।
- Gross B শুধু দেখানো, Complex-এ প্রক্রিয়া হয়নি।
- ধাপ: Calculate → Review → Approve → Finalize (লক: স্লিপ Processed, এই পিরিয়ডে দেওয়া সমন্বয় Applied) → Mark Paid (স্লিপ Paid)। চূড়ান্ত রান Re-open করলে Approved-এ ফেরে, স্লিপ খসড়া, সমন্বয় আবার Approved।
- চূড়ান্ত রানের স্লিপে আজকের OT মিলিয়ে "Adjustment Required" দেখায়।
- ট্যাব: Salary Review, Gross A & Gross B, Bank Payment, Cash Payment। পে-স্লিপ (একটা বা সব), বেতন শিট / ব্যাংক / নগদ CSV।

**Payroll Adjustment:**
- চূড়ান্ত পিরিয়ড (সর্বশেষ নিজে থেকেই), বিভাগ, কর্মী (কোড / নাম / কার্ড), Show (Adjustment Required / সব চূড়ান্ত স্লিপ)।
- স্লিপে সতর্কতা আসে যখন:
  - আজকের OT (হাজিরা + সংশোধন) স্লিপের OT আর আগে তোলা OT সমন্বয়ের যোগফল থেকে আলাদা হয়; বা
  - স্লিপে কোনো সমন্বয় অপেক্ষায় থাকে, বা অনুমোদিত কিন্তু এখনো দেওয়া হয়নি।
- Raise দুই রকম:
  - **OT:** সংশোধিত ঘণ্টা × হার; মূল = স্লিপের OT + আগের OT সমন্বয়।
  - **Other:** দেওয়ার (+) বা কাটার (−) টাকা; মূল = স্লিপের নিট।
  - কারণ লাগে; পার্থক্য ০ হলে আটকায়।
- অপেক্ষমাণ সব (যে পিরিয়ডেরই হোক) আলাদা তালিকায়। Approve, বা নোটসহ Reject।
- অনুমোদিত সমন্বয় পরের হিসাব করা মাসিক বেতনে একবার যায় ("Paid In"); সেই রান চূড়ান্ত হলে Applied।

## ৬. API ও পারমিশন

পারমিশন: `hrpayroll.salary.view` (দেখা), `.calculate` (হিসাব, যাচাই, OT Recalculate, সমন্বয় তোলা), `.approve` (অনুমোদন / ফেরত, বোনাস খসড়ায় ফেরানো), `.finalize` (চূড়ান্ত, Re-open, পরিশোধিত); `hrpayroll.adjustment.manage` (সমন্বয় তোলা), `hrpayroll.adjustment.approve` (সমন্বয়ের সিদ্ধান্ত)। FACTORY_ADMIN সব; FACTORY_MANAGER view, approve, adjustment.approve; AUDITOR view।

API (`/api/v1/hr-payroll/salary/…`): `GET options`; `GET daily`, `POST daily/calculate`, `POST daily/move` (review / approve / unapprove, টিক দেওয়া কর্মী ঐচ্ছিক); `GET ot`, `POST ot/recalculate`; `GET monthly`, `POST monthly/calculate`, `POST monthly/move` (review / approve / finalize / pay / reopen); `GET bonus` (month, festival), `POST bonus/calculate`, `POST bonus/move` (review / approve / finalize / pay / reopen); `GET adjustments`, `POST adjustments`, `POST adjustments/{ref}/decide`। পে পিরিয়ড আর সমন্বয় তাদের নম্বর দিয়ে।

## ৭. Frontend পেজ (লাইট থিম)

`frontend/src/components/hrpayroll/salary.jsx` — পাঁচটা স্ক্রিন, Brickland-এর বিন্যাসে; সাইডবারে "SALARY PROCESSING" ফোল্ডার:
- **Daily Salary** (`/hr-payroll/daily-salary`): Load Attendance কার্ড (Mode, তারিখ, বিভাগ / সেকশন / লাইন / কর্মী, Load), ৫টা টাইল, Calculate / Review / Approve / Un-approve / Print / CSV, টিক দেওয়ার গ্রিড, নিশ্চিতকরণ।
- **OT Salary** (`/hr-payroll/ot-salary`): From / To / বিভাগ / সেকশন / লাইন / কর্মী, Show / Recalculate / Print / PDF / Excel, ৩টা টাইল, OT Details।
- **Festival Bonus** (`/hr-payroll/festival-bonus`): Festival Check (মাস, উৎসবের কার্ড), ৪টা টাইল, Workflow (ধাপ, বাধা, লক, বাটন), বোনাস শিট — Eligible Employee / Not Eligible ট্যাব।
- **Monthly Salary** (`/hr-payroll/monthly-salary`): Load Data (Mode, পে পিরিয়ড, পরিসর), পিরিয়ডের কার্ড (ধাপের সারি, বাটন, নোট), ৬টা টাইল, চারটা ট্যাব; সারির পে-স্লিপ।
- **Payroll Adjustment** (`/hr-payroll/payroll-adjustment`): ফিল্টার, ৪টা টাইল, Finalized Slips (Raise), পিরিয়ডের Adjustments, Pending Approval; Raise আর সিদ্ধান্তের ডায়ালগ।
- প্রিন্ট `lib/print-document` দিয়ে: দৈনিক বেতন, OT বেতন, বোনাস শিট, পে-স্লিপ (প্রতিটা আলাদা পাতায়: আয় / কর্তন, নিট, স্বাক্ষর)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Database/Migrations/2027_03_15_000100_create_hr_salary_processing.php`
- `Support/SalaryBook.php` (পরিসর, OT, দৈনিক ও মাসিক হিসাব, ন্যূনতম মজুরি, বোনাস, চূড়ান্ত স্লিপের অবস্থা)
- `Http/Controllers/Api/HrSalaryApiController.php`, `HrFestivalBonusApiController.php`, `HrPayrollAdjustmentApiController.php`
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`sp`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/salary.jsx` (নতুন)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/HrPayroll/HrSalaryProcessingTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrSalaryProcessingTest.php`, ৩টা টেস্ট, ১৪৪টা assertion):** test2 DB-তে পাস। পুরো HrPayroll ফোল্ডার ২০/৭৭৮, MenuAccess ৬/৩৪ পাস।
- **দৈনিক আর OT বেতন:**
  - হিসাবের আগে ৩টা সারি "Not calculated"; ভবিষ্যতের দিন আটকায়।
  - হাজিরা নেই এমন দিন সতর্কতাসহ ০।
  - ১০ নভেম্বর: দৈনিক ২১০০০ ÷ ৩০ = ৭০০, Worker One-এর ৩ ঘণ্টা × Basic ÷ ২০৮ × ২।
  - কেউ যাচাই না হলে Approve আটকায়। শুধু টিক দেওয়া একজন যাচাই আর অনুমোদিত, আবার হিসাবে অনুমোদিত সারি থাকে, Un-approve।
  - লাইন আর একজনের পরিসর কাজ করে।
  - OT বেতন: ২ জন, ৫ ঘণ্টা; সংশোধনের পরে ৪ × ১০০ = ৪০০ (Corrected); ৯২ দিনের বেশি আটকায়; Recalculate চলে।
  - Auditor দেখেন কিন্তু হিসাব পারেন না; Factory Manager অনুমোদন পারেন, হিসাব না।
- **উৎসব বোনাস আর মাসিক রান:**
  - অক্টোবরে উৎসব নেই। নভেম্বরে দুটো, বোনাস নিয়ম না থাকা উৎসব আটকায়।
  - প্রিভিউ: দুই শ্রমিক অর্ধেক Basic (৩২৩ দিন, ১০ মাস); Staff One ৫০ দিনে ১৮০ দিনের ন্যূনতমের কম।
  - Calculate (DRAFT, নভেম্বরের পিরিয়ড)। আগেভাগে Approve আটকায়। Review → Approve → খসড়ায় ফেরত → আবার → Finalize। লকের পরে আবার হিসাব আটকায়।
  - মাসিক: হিসাবের আগে ৩ জন বাকি। স্লিপে ৩০ দিন, ২১০০০, ৩ ঘণ্টা OT, বোনাস, ৳১০ স্ট্যাম্প, নগদ, নিট রাউন্ড করা।
  - Worker Two একদিন অনুপস্থিত → ২৯ দিন, ৭০০ কর্তন।
  - ১২০০০ টাকায় গ্রেডের ন্যূনতম ১২৫০০-এর নিচে সতর্কতা।
  - আগেভাগে Approve আটকায়। Review → Manager Approve (Finalize পারেন না) → Finalize (Processed) → আবার হিসাব আটকায় → Re-open → Finalize → Paid।
  - চূড়ান্ত বেতনের মাসে নতুন বোনাস আটকায়।
- **পেরোল সমন্বয়:**
  - চূড়ান্ত পিরিয়ড না থাকলে খালি। চূড়ান্তের পরে কিছু আলাদা নয়; OT সংশোধনে Worker One সতর্কতায় (৫ ঘণ্টা, ৫০০)। নভেম্বরের মাসিকেও Adjustment Required।
  - কারণ ছাড়া আর একই অঙ্কে আটকায়। PADJ-000001 OT (মূল = স্লিপের OT, সংশোধিত ৫০০) তোলা; অপেক্ষায়, "এখনো দেওয়া হয়নি"।
  - Other ধরনে −২৫০ (PADJ-000002)। Auditor তুলতে পারেন না।
  - নোট ছাড়া Reject আটকায়; নোটসহ Reject। Manager Approve; দ্বিতীয় সিদ্ধান্ত আটকায়।
  - ডিসেম্বরের মাসিকে Worker One-এর সমন্বয় যায়, Staff One-এর (বাতিল) না; Paid In ডিসেম্বর।
  - ডিসেম্বর চূড়ান্ত হলে Applied, নভেম্বরে আর সতর্কতা নেই।

**UI চেক (`app_check/plans/hr06`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Daily Salary ১০ নভেম্বর:
  - Load → Calculate: Mohammad Sayem ৳৪৬৬.৬৭ + OT ১ ঘণ্টা × ৳৮৮.৬০ = ৳৫৫৫.২৭। Rokeya Begum-এর ভাতা ৳২০ আর কর্তন ৳৫। Hasan Sarkar অনুপস্থিত, সতর্কতাসহ ০।
  - Review → Approve (নিশ্চিতকরণ ডায়ালগ) ✔ প্রিন্ট ✔
- OT Salary ১–১১ নভেম্বর: ১টা সারি (Sayem, Corrected), "২টা হাজিরার অপেক্ষায়" ✔ প্রিন্ট ✔
- Festival Bonus: মার্চ ২০২৭-এ Eid-ul-Fitr 2027। প্রিভিউতে ৫ জনই অযোগ্য (চাকরি ৬ মাসের কম)। "No pay period covers March 2027" বাধায় Calculate বন্ধ ✔
- Monthly Salary:
  - অক্টোবর: Calculate → Review → Approve → Finalize। Shawonoor (২৫ অক্টোবর যোগদান) ৭ / ৩১, Hasan (১০ অক্টোবর) ২২ / ৩১। নিট ৳৬৩,৯৬০ ✔
  - Gross A & Gross B আর Cash Payment ট্যাব ✔ একজনের আর সবার পে-স্লিপ ✔
  - নভেম্বর Calculate, শুধু হিসাব করা, চলতি মাস তাই চূড়ান্ত নয় ✔
- Payroll Adjustment: অক্টোবরের ৪টা চূড়ান্ত স্লিপ। Poritosh Chandra Das-এর জন্য Other +৳৭৫০ তোলা (PADJ-000001) → Decide → Approve ✔ নভেম্বরের Recalculate-এ Poritosh-এর সমন্বয় ৳৭৫০, নিট ৳১৮,৩৪০ ✔
- ফিক্সের পরে আবার: Shawonoor-এর পে-স্লিপে আনুপাতিক বেসিক ৳১,৭৩৮.৭১ ✔ Show = সব চূড়ান্ত স্লিপ রেখে Shawonoor-এর জন্য Other −৳২০০ (PADJ-000002) তোলা ও অনুমোদন — তালিকায় ৪টা স্লিপই থাকে ✔ (নভেম্বর আবার হিসাব করলে এটা কাটা যাবে)

## ১০. খোলা প্রশ্ন

১. **কর্মীর বেতনের ব্যাংক হিসাব:** Brickland-এর Bank Payment ট্যাব "assigned salary bank account" চায়, কিন্তু Brickland-এর কোথায় এটা দেওয়া হয় পাওয়া যায়নি; Employee Profile বা Bank Account Master কোনোটাতেই নেই। এখন সবাই নগদে। কোথায় দেওয়া হবে জানালে Bank Payment ট্যাব ভরবে।

২. **লোন / অগ্রিম:** লোনের কোনো স্ক্রিন এখনো নেই, তাই ০। লোন মডিউল এলে কিস্তি এখানে কাটা যাবে।

৩. **Gross B (Complex Management):** Brickland-এর HR-এ পরে একটা Complex-* গ্রুপ এসেছে। সেটা না বানানো পর্যন্ত Gross B শুধু কর্মীর Gross B দেখায়, "Not processed in Complex"।

৪. **স্ট্যাম্প চার্জ:** Brickland প্রতিটা স্লিপে ৳১০ কাটে ("Stamp Charge")। এখানেও স্থির ৳১০; Payroll Settings-এ বদলানো যায় না। বদলানো যাক চাইলে জানাবেন।

৫. **নিট রাউন্ডিং:** Payroll Settings-এর ROUND_NET_TO (১) মেনে নিট পুরো টাকায় রাউন্ড হয়; Brickland-এর ডেমোতে পয়সাসহ দেখায়। পয়সাসহ রাখতে চাইলে জানাবেন।

৬. **বোনাসের %:** dev / Brickland-এর নিয়মে ০.৫ আর ১.০ — এগুলো ভগ্নাংশ ধরা হয়েছে (০.৫ = Basic-এর অর্ধেক)। ১-এর বেশি লিখলে শতাংশ (৫০ = ৫০%)। Designation Group আর Shift Team দেওয়া নিয়ম এখন মেলে না (কর্মীর সাথে এগুলোর যোগ নেই)।

৭. **উৎসবের ন্যূনতম চাকরি:** Festival Calendar-এর "Minimum Service" দিন ধরা হয়েছে (Brickland কার্ডে "N days' service" লেখে), আর বোনাস নিয়মের চাকরির সীমা মাসে।

৮. **হাজিরা না থাকা দিন:** মাসিক বেতনে কাটা হয় শুধু Absent আর বিনা বেতনের ছুটির দিন; যেদিনের হাজিরা নেই সেদিন কাটা হয় না। দৈনিক বেতনে হাজিরা না থাকা দিনে বেতন হয় না।

## ১১. As-built নোট

- **Payroll Adjustment:** ক্রলে (৪টা স্ক্রিন) ছিল না, Brickland-এ পরে যোগ হয়েছে। লাইভ প্রোব থেকে বানানো: RSC props, JS chunk-এর লেখা (`tools/erp_crawler/out/sp06`, `sp06js`), স্ক্রিনশট `sp06shots`, প্রিন্ট `sp06print`। সাইডবারের ক্রম Brickland-এর: Daily Salary, OT Salary, Festival Bonus, Monthly Salary, Payroll Adjustment। উপরের AUTO তালিকায় শুধু ক্রলের ৪টা।
- **24/05-এর প্রশ্ন ৬ (চূড়ান্ত পেরোলে OT Correction-এর তালা):** Brickland তালা দেয় না; চূড়ান্ত স্লিপ "Adjustment Required" হয়, পার্থক্য Payroll Adjustment-এ তোলা হয়। এখানেও তাই।
- **UI চেকে ধরা পড়া:**
  - পে-স্লিপের "of which Basic" পুরো মাসের বেসিক দেখাচ্ছিল (মাঝমাসে যোগ দেওয়া কর্মীর Gross-এর চেয়েও বেশি)। Brickland-এর মতো আনুপাতিক করা হয়েছে।
  - Raise বা সিদ্ধান্তের পরে Payroll Adjustment-এর তালিকা Show ফিল্টার ভুলে যেত। এখন ফিল্টার থাকে।
