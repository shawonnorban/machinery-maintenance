# Attendance & Shift

> **স্ট্যাটাস:** ✅ সম্পন্ন (৬টার ৬টা স্ক্রিন — ক্রলের ৫টা আর Brickland-এ পরে যোগ হওয়া Monthly Attendance Summary)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/04`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Daily Attendance | `/m/hr-payroll/daily-attendance` | 1 | 4 | 0 |
| 2 | Biometric Sync | `/m/hr-payroll/biometric-sync` | 1 | 4 | 0 |
| 3 | Missing Punch | `/m/hr-payroll/missing-punch` | 1 | 5 | 0 |
| 4 | Attendance Correction | `/m/hr-payroll/attendance-correction` | 2 | 4 | 0 |
| 5 | Shift Roster | `/m/hr-payroll/shift-roster` | 1 | 14 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Daily Attendance

`/m/hr-payroll/daily-attendance`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
| Status | select |  | All, Present, Absent, Late, Early Out, Leave, Missing Punch, Holiday, Weekly Off |
| Search Daily attendance with shift, IN, OUT, working hours, late minutes, early  | search |  | Search… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Employee, Date, Shift, IN, OUT, Working Hours, Late Minutes, Early Out, OT Hours, Status
- সেকশন: Day and filters, Attendance — 2026-10-09
- বাটন/অ্যাকশন: Process, All, PRESENT 0, ABSENT 0, LATE 0, EARLY OUT 0, LEAVE 0, MISSING PUNCH 0, HOLIDAY 0, WEEKLY OFF 0, Export, Employee, Date, Shift, IN, OUT, Working Hours, Late Minutes, Early Out, OT Hours

#### Biometric Sync

`/m/hr-payroll/biometric-sync`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Items per page | select |  | 10, 25, 50, 100 |
| Date | date |  |  |
| Only unmapped punches | checkbox |  |  |
| Search Raw device punches for the day with card, employee and device | search |  | Search… |
- গ্রিড কলাম: Device, Location, Device Status, Last Seen, Last Sync, Punches Today, Users, Fingerprints, Action
- গ্রিড কলাম: Time, Card / User ID, Employee, Device
- সেকশন: Attendance terminals, Raw punch log — 2026-10-09
- বাটন/অ্যাকশন: Import file, Sync now, Device, Location, Device Status, Last Seen, Last Sync, Punches Today, Users, Fingerprints, Action, Time, Card / User ID, Employee

#### Missing Punch

`/m/hr-payroll/missing-punch`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Search Attendance days with a missing IN or OUT punch | search |  | Search… |
| Items per page | select |  | 10, 25, 50, 100 |
| Search Missing punch corrections with correct IN, correct OUT, reason, requested | search |  | Search… |
- গ্রিড কলাম: Employee, Date, Line, Shift, IN, OUT, Status, Correction
- গ্রিড কলাম: Employee, Date, Old IN – OUT, Correct IN, Correct OUT, Reason, Requested By, Approved By, Correction Date, Status, Action
- সেকশন: Date range and filters, Missing punches, Corrections
- বাটন/অ্যাকশন: Show, All, Employee, Date, Line, Shift, IN, OUT, Status, Correction, Old IN – OUT, Correct IN, Correct OUT, Reason, Requested By, Approved By, Correction Date, Action

#### Attendance Correction

`/m/hr-payroll/attendance-correction`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Request correction

**ট্যাব: Request correction**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Attendance Date* | date | ✔ |  |
| Correct IN | text |  | 08:00 |
| Correct OUT | text |  | 17:00 |
| Reason* | textarea | ✔ |  |
- সেকশন: New correction
- বাটন/অ্যাকশন: Request correction, Select…, Reset

#### Shift Roster

`/m/hr-payroll/shift-roster`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shift* | select | ✔ | Select…, Morning 06:00–14:00, General 08:00–17:00, Evening 14:00–22:00, Night 22:00–06:00 |
| From* | date | ✔ |  |
| To | date |  |  |
| Sat | checkbox |  |  |
| Sun | checkbox |  |  |
| Mon | checkbox |  |  |
| Tue | checkbox |  |  |
| Wed | checkbox |  |  |
| Thu | checkbox |  |  |
| Fri | checkbox |  |  |
| Remarks | text |  |  |
| Shift | select |  | All, Morning 06:00–14:00, General 08:00–17:00, Evening 14:00–22:00, Night 22:00–06:00 |
| Search Shift roster with employee, shift, dates, weekly off, mode and batch | search |  | Search… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Employee, Line, Shift, From, To, Weekly Off, Mode, Batch, Action
- সেকশন: Assign shift, Current roster
- বাটন/অ্যাকশন: Individual, Multiple Employee, Line-wise, Weekly Rotation, Select…, Assign, All, Employee, Line, Shift, From, To, Weekly Off, Mode, Batch, Action
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **কর্মী (24/03):** `hr_employees` — বিভাগ, সেকশন, লাইন, কর্মীর ধরন (Staff / Worker), কার্ড নং।
- **শিফট (24/01 Master Data):** `hr_shifts` — শুরু, শেষ, grace, বিরতি, OT চলে কিনা। ডিফল্ট শিফট Payroll Settings-এর `DEFAULT_SHIFT`, না থাকলে `ST-GEN`।
- **ছুটির দিন:** `factory_holidays` (Company Management-এর ক্যালেন্ডার)।
- **পাঞ্চ (03/01 Device Integration):** `dev_punches` — যন্ত্র থেকে আসা, কর্মীর সাথে মেলানো পাঞ্চ। ADMS-এ পাঞ্চ এলেই (যন্ত্রে "Update daily attendance" চালু থাকলে) ঐ কর্মীর ঐ দিন আর আগের দিন আবার হিসাব হয়।
- **রপ্তানি:** শেয়ার্ড `TablePdf` (mPDF) আর OpenSpout XLSX; প্রিন্ট `lib/print-document`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_13_000100_create_hr_attendance`:
- **`hr_attendance_days`:** কর্মী + তারিখ (অনন্য), শিফট, IN, OUT, কাজের মিনিট, দেরির মিনিট, আগে যাওয়ার মিনিট, OT মিনিট, অবস্থা (PRESENT / ABSENT / LEAVE / MISSING_PUNCH / HOLIDAY / WEEKLY_OFF), দেরি / আগে যাওয়া চিহ্ন, সংশোধিত কিনা, মন্তব্য, কে সংশোধন করেছেন, কখন হিসাব হয়েছে।
- **`hr_attendance_corrections`:** কর্মী, তারিখ, পুরনো IN / OUT, ঠিক IN / OUT, কারণ, অবস্থা (PENDING / APPROVED / REJECTED), কে চেয়েছেন, কে সিদ্ধান্ত দিয়েছেন আর কখন।
- **`hr_shift_rosters`:** কর্মী, শিফট (`hr_shifts`), শুরু, শেষ (খালি = খোলা), সাপ্তাহিক ছুটি (json — খালি হলে কর্মীর আগের ছুটির দিন), ধরন (INDIVIDUAL / MULTIPLE / LINE / ROTATION), ব্যাচ (RS-000001…), মন্তব্য।

## ৫. Workflow / Status ও Business Rules

**দিনের হিসাব (`Support/AttendanceBook::process`)** — পাঞ্চ থেকে প্রতিটা কর্মীর প্রতিটা দিন:
- **শিফট:** দিনটা ঢাকে এমন নতুনতম রোস্টার; না থাকলে ডিফল্ট শিফট। সাপ্তাহিক ছুটি রোস্টার থেকে, না থাকলে শুক্রবার।
- **পাঞ্চের সময়সীমা:** শিফট শুরুর ৪ ঘণ্টা আগে থেকে শেষের ৬ ঘণ্টা পর পর্যন্ত (পরের দিনের সীমার আগে থেমে) — রাতের শিফট পরের দিনে গড়ায়। প্রথম পাঞ্চ IN, শেষটা OUT — IN-এর অন্তত ৬০ মিনিট পরে হলেই।
- **অবস্থা:** ছুটির দিন → HOLIDAY; সাপ্তাহিক ছুটি → WEEKLY_OFF (পাঞ্চ থাকলে PRESENT, পুরো সময় OT)। পাঞ্চ নেই → ABSENT, তবে শুধু শুরু + grace পেরোলে। শুধু IN → সময়সীমা খোলা থাকতে PRESENT, পরে MISSING_PUNCH।
- **কাজ** = OUT − IN − বিরতি। **দেরি** = grace পেরোলে শুরুর পর কত মিনিট। **আগে যাওয়া** = শেষের আগে কত মিনিট।
- **OT:** শিফট শেষের পর পুরো ঘণ্টা, শুধু Worker-দের (কর্মীর ধরন Staff নয়) আর যে শিফটে OT চলে।
- **সংশোধিত দিন** আর হিসাব হয় না — Attendance Correction-এ মুছলে আবার পাঞ্চ থেকে।
- **কখন হিসাব হয়:** পাঞ্চ এলে (ADMS), Sync now, ফাইল import, রোস্টার বসালে / মুছলে, আর scheduler প্রতি দশ মিনিটে আজ-গতকাল (`hr:process-attendance --days=2`)। স্ক্রিন খুললেও দেখানো দিনগুলো হিসাব হয়।

**স্ক্রিন:**
- **Daily Attendance:** দিন (Today / Yesterday / This week / Last 7 days / This month — সর্বোচ্চ ৬২ দিন) আর Filter (বিভাগ, সেকশন, লাইন, অবস্থা); উপরে ৮টা গণনা (Present, Absent, Late, Early Out, Leave, Missing Punch, Holiday, Weekly Off — চাপলে সেটাই ছাঁকে); গ্রিড; Export Excel / Export PDF।
- **Biometric Sync:** উপরে Devices, Online, Punches today, Fingerprint registered / pending, Not registered; যন্ত্রের তালিকা (শেষ দেখা, শেষ sync, আজকের পাঞ্চ, ইউজার, আঙুল); **Sync now** (আজকের হাজিরা আবার হিসাব); **Import file** (USB-র `attlog.dat` — PIN, সময়, ধরন; যন্ত্র বেছে; উৎস FILE; একই পাঞ্চ দুবার নয়); তারিখ ধরে raw পাঞ্চ, "Only unmapped punches"। হেডারে Device Setup (Settings-এর পারমিশন থাকলে)।
- **Missing Punch:** From / To, বিভাগ, সেকশন, লাইন, কর্মী → Show। যে দিনে IN বা OUT নেই সেগুলো, সারিতে **Correct** — ঠিক IN / OUT আর কারণ দিয়ে অনুরোধ (PENDING)। নিচে Corrections — Approve / Reject (`hrpayroll.attendance.approve`); Approve হলে দিনটা ঐ সময়ে সংশোধিত হয়ে আবার হিসাব (দেরি, OT সহ)।
- **Attendance Correction:** কর্মী, মাস, বছর → পুরো মাসের চার্ট: প্রতিদিন অবস্থা, IN, OUT, দেরি, OT, মন্তব্য হাতে বদলে Save; Delete দিলে আবার পাঞ্চ থেকে। আজকের পরের দিন বদলানো যায় না। উপরে গণনা (Present, Absent, Late, Missing punch, Leave, Off days, OT hours)।
- **Monthly Attendance Summary:** মাস, বছর, বিভাগ, সেকশন, লাইন → প্রতি কর্মীর ১–৩১ দিনের কোড (P, L, A, MP, LV, H, W; ছোট সংখ্যায় OT) আর মোট Present, Absent, Leave, Holiday / Off, Total OT; Print (ল্যান্ডস্কেপ, স্বাক্ষর), Export Excel / PDF।
- **Shift Roster:** চার ট্যাব (রিলোড ছাড়া) — Individual (একজন), Multiple Employee (বিভাগ / সেকশন / লাইন / খোঁজা দিয়ে টিক), Line-wise (পুরো লাইন), Weekly Rotation (From, সপ্তাহের সংখ্যা ≤ ২৬, প্রতি সপ্তাহের শিফট — অন্তত দুটো; প্রতি সপ্তাহে একটা সারি)। শিফট, From, To, সাপ্তাহিক ছুটি (Sat…Fri), মন্তব্য → Assign; প্রতিবার একটা ব্যাচ নং। নিচে Current roster — ছাঁকনি আর মোছা।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `hrpayroll.attendance.view`, `.manage`, `.approve`। FACTORY_ADMIN তিনটাই; FACTORY_MANAGER view + approve; AUDITOR view। Missing Punch-এর মেনু approve থাকলেও দেখায় (শুধু Approve করার লোকও সেখানে পৌঁছান)।

| Method | Path |
|---|---|
| GET | `/hr-payroll/attendance/options`, `/daily`, `/daily/{pdf\|xlsx}`, `/sync`, `/missing`, `/month`, `/summary`, `/summary/{pdf\|xlsx}` |
| POST | `/hr-payroll/attendance/sync`, `/import`, `/corrections`, `/corrections/{id}/decide`, `/day`, `/day/delete` |
| GET / POST | `/hr-payroll/roster/options`, `/hr-payroll/roster` |
| DELETE | `/hr-payroll/roster/{id}` |

- **মেনু:** HR & Payroll-এ নতুন ফোল্ডার ATTENDANCE & SHIFT (Dye House-এর মতো স্টাইলে), Brickland-এর ক্রমে ৬টা স্ক্রিন।
- **কমান্ড:** `hr:process-attendance --days=2`, scheduler-এ প্রতি দশ মিনিটে।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/hrpayroll/attendance.jsx` — `DailyAttendance`, `BiometricSync`, `MissingPunch`, `AttendanceCorrection`, `MonthlySummary`, `ShiftRoster`; গ্রিড আর গণনার টাইল Device Integration-এর (`components/settings/devices.jsx`) সাথে ভাগ।
- **ঠিকানা:** `/hr-payroll/daily-attendance`, `/biometric-sync`, `/missing-punch`, `/attendance-correction`, `/monthly-attendance-summary`, `/shift-roster`।
- **ডাউনলোড আর আপলোড:** `/api/hr-payroll/attendance-export/[report]/[format]` (Excel / PDF), `/api/hr-payroll/attendance-import` (punch ফাইল — server action-এর ১ MB সীমা পেরোয়)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৬টার ৬টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Database/Migrations/2027_03_13_000100_create_hr_attendance.php`
- `Support/AttendanceBook.php` (দিনের হিসাব)
- `Http/Controllers/Api/HrAttendanceApiController.php`, `Http/Controllers/Api/HrRosterApiController.php`
- `Console/ProcessAttendance.php`; `routes/console.php` (scheduler)
- `Routes/api.php`, `Providers` (বদলানো)
- `app/Modules/AttendanceDevice/Http/Controllers/AdmsController.php` (পাঞ্চ এলে দিন হিসাব — বদলানো)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`att`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/attendance.jsx` (নতুন)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)
- `app/api/hr-payroll/attendance-export/[report]/[format]/route.js`, `app/api/hr-payroll/attendance-import/route.js` (নতুন)

**Test**
- `tests/Feature/HrPayroll/HrAttendanceTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrAttendanceTest.php`, ২টা টেস্ট, ৮০টা assertion):** test2 DB-তে পাস। HrPayroll-এর পুরো স্যুট ১৪টা টেস্ট সবুজ, AttendanceDevice ২/৮৪, Access-এর ২৭/১৮৪ (২০২৬-১১-১১)।
- **পাঞ্চ থেকে দিন** (নকল ADMS যন্ত্রে পাঠানো পাঞ্চ): Worker One আগে এসে দেরিতে গেছেন → Present, OT; Worker Two দেরিতে (৪০ মিনিট), OUT নেই → Missing Punch; Staff One আগে গেছেন (৬০ মিনিট) → OT ০। সপ্তাহে শুক্রবার Weekly Off, রবিবার Holiday, পাঞ্চ নেই → Absent; উল্টো তারিখ আটকায়।
- **Missing Punch:** কারণ / সময় ছাড়া অনুরোধ আটকায়; অনুরোধ → Pending (একই দিনে দ্বিতীয় অনুরোধ আটকায়) → Approve → Present ১৭:৩০, ৭.৮৩ ঘণ্টা, সংশোধিত — Sync now-এর পরেও তাই থাকে।
- **Attendance Correction:** মাসে ৩০ দিন, গণনা (Present ১, Absent ৮, Off ২, OT ৩); আজকের পরের দিন আটকায়; সোমবার Leave সেভ → Leave, Delete → আবার Absent।
- **Monthly Summary:** P (OT ৩), W, H, A, L; বিভাগ ছাড়া আটকায়; Summary PDF, Daily PDF আর XLSX।
- **Biometric Sync:** দিনের ৬টা raw পাঞ্চ, ১টা অচেনা; Import file (`attlog.dat`) — ২টা পড়া, ১টা নতুন (অন্যটা আগেই ছিল), ১টা দিন; ভুল ফাইল আটকায়।
- **রোস্টার:** Individual (খালি হলে তিনটা বার্তা; Staff One Morning-এ → দেরি ১২৫ মিনিট, শনিবার ছুটি), Weekly Rotation (একটা শিফটে আটকায়; দুটো শিফট, দুই সপ্তাহ → প্রতি সপ্তাহে একটা সারি, ছুটি শুক্রবার), Line-wise (লাইনের সবাই), মুছলে আবার General শিফট।
- **পারমিশন:** Factory Manager দেখেন আর Approve করেন, বদলাতে পারেন না; Storekeeper দেখতে পারেন না।

**UI চেক (`app_check/plans/hr04`, ২০২৬-১১-১০, owner; error 0; dev ডাটা রাখা আছে):**
- Daily: Mohammad Sayem Present ০৮:০১–১৭:০২, ৮.০০; বাকিরা Missing Punch / Absent ✔ Biometric Sync: GATE-01 আর ৬টা raw পাঞ্চ ✔
- Missing Punch: EMP-000002-এর Correct (০৮:০৩–১৭:৩০, "Forgot to punch out at the gate") → Approve → "Approved" ✔
- Attendance Correction: EMP-000004-এর নভেম্বর চার্ট, ২০২৬-১১-০৯ Leave ("Sick leave") সেভ ✔ — এর পরে চার্টের গণনায় Leave চিপ যোগ করা হয়েছে।
- Monthly Summary (Sewing): ৩ জন, Print PDF "Monthly Attendance Summary" (১–৩০ দিন, স্বাক্ষর) ✔ Shift Roster: EMP-000004 Morning ০৬:০০–১৪:০০, Open-ended, Fri, Individual, RS-000001 ✔ Weekly Rotation ট্যাব ✔

## ১০. খোলা প্রশ্ন

১. **OT কে পায়:** ✅ মিটেছে — 24/05-এর OT Eligibility থেকে আসে (গ্রেডের শ্রমিকের General OT, চালু করা স্টাফের Worker OT)।

২. **OT-র হিসাব পুরো ঘণ্টায়:** ✅ মিটেছে — 24/05-এর OT সীমায় রাউন্ডিং, গ্রেস, বিরতি, ন্যূনতম আর সর্বোচ্চ বদলানো যায়।

৩. **সাপ্তাহিক ছুটি:** রোস্টার না থাকলে শুক্রবার। কারখানায় আলাদা দিন থাকলে (বা Payroll Settings-এ রাখতে চাইলে) জানাবেন।

৪. **Leave:** ✅ মিটেছে — 24/05-এ অনুমোদিত ছুটির দিনগুলো হাজিরায় নিজে থেকেই Leave হয়।

৫. **কর্মীর শিফট:** খোলা রোস্টার (আজ বা আগে থেকে) কর্মীর চলতি শিফট — তবে `hr_employees.shift_id`-এ লেখা হয় না, কারণ সেটা Calendar-এর শিফট টেবিলের দিকে নির্দেশ করে, HR-এর `hr_shifts`-এর দিকে নয়।

৬. **Brickland-এর নতুন স্ক্রিন:** ক্রলের পরে Brickland-এ Monthly Attendance Summary (এখানে বানানো), OT Correction / Eligibility / Request / Approval, Final Settlement, Lefty Management আর একটা Complex-* গ্রুপ যোগ হয়েছে। বাকিগুলো নিজ নিজ সাব-মডিউলে (24/05, 24/08 ইত্যাদি) বানানো হবে।

## ১১. As-built নোট

- **Monthly Attendance Summary:** ক্রলে (৫টা স্ক্রিন) ছিল না, Brickland-এ পরে যোগ হয়েছে; লাইভ প্রোব থেকে বানানো, সাইডবারে Attendance Correction-এর পরে, Shift Roster-এর আগে (Brickland-এর ক্রম)। উপরের AUTO তালিকায় শুধু ক্রলের ৫টা।
- **Brickland প্রোব:** স্ক্রিনগুলোর স্পেক RSC payload থেকে (কলাম, ছাঁকনি, ট্যাব, গণনার টাইল), Monthly Summary-র লেআউট লাইভ স্ক্রিনশট থেকে।
- **03/01-এর সাথে:** যন্ত্রের "Update daily attendance in/out time" সুইচ এখন কাজ করে — পাঞ্চ এলেই ঐ কর্মীর দিন হিসাব হয়।
- **UI চেকে ধরা পড়া:** Attendance Correction-এর গণনায় Leave ছিল না — Leave চিপ যোগ করা হয়েছে।
- **রিগ্রেশনে ধরা পড়া:** `hrpayroll.attendance.approve` কোনো মেনুতে ছিল না (Access Control-এর ম্যাট্রিক্স থেকে দেওয়া যেত না) — Missing Punch-এর মেনুতে যোগ করা হয়েছে।
