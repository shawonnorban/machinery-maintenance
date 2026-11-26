# Separation

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন — ক্রলের ২টা আর Brickland-এ পরে যোগ হওয়া Lefty Management)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/08`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Resignation / Release | `/m/hr-payroll/employee-separation` | 1 | 5 | 0 |
| 2 | Final Settlement | `/m/hr-payroll/final-settlement` | 2 | 12 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Resignation / Release

`/m/hr-payroll/employee-separation`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee* | text | ✔ | Code, name, card or NID |
| Search | text |  | Code, name, ref no |
| Last day from | date |  |  |
| to | date |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Ref No, Employee, Type, Application, Last Working Day, Notice, Reason, Status, Action
- সেকশন: New Separation, Separation List
- বাটন/অ্যাকশন: All, Pending, Approved, Rejected, Excel (CSV), Ref No, Employee, Type, Application, Last Working Day, Notice, Reason, Status, Action

#### Final Settlement

`/m/hr-payroll/final-settlement`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New settlement

**ট্যাব: New settlement**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Separation | text |  |  |
| Settlement Type* | select | ✔ | Select…, Resign From Job, Disable For Long Absenteeism, Termination, Retirement, Death |
| Last Working / Action Date* | date | ✔ |  |
| Salary Due | number |  |  |
| OT Due | number |  |  |
| Bonus Due | number |  |  |
| Leave Encashment | number |  |  |
| Deduction | number |  |  |
| Loan Recovery | number |  |  |
| Other Adjustment | number |  |  |
| Final Payable | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New settlement
- বাটন/অ্যাকশন: New settlement, Select…, Reset, Save settlement
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **কর্মী (24/03):** `hr_employees` (অবস্থা, বিচ্ছেদের তারিখ, Gross A), পোস্টিংয়ের ইতিহাস। বিচ্ছেদে খোলা পোস্টিং বন্ধ হয়।
- **পুনঃযোগদান (24/07):** Separated / Inactive কর্মী আবার Active।
- **হাজিরা (24/04):** শেষ হাজিরা, অনুপস্থিত দিন।
- **ছুটি (24/05):** `LeaveBook` — নগদায়নযোগ্য ছুটির বাকি।
- **বেতন (24/06):** `SalaryBook::otDays` (OT), চূড়ান্ত স্লিপ (শেষ পরিশোধিত পিরিয়ড), উৎসব বোনাস, পেরোল সমন্বয়।
- **Movement-এর ফ্রন্টএন্ড অংশ:** কর্মী খোঁজা, ফিল্টার, তালিকা, সিদ্ধান্তের ডায়ালগ (`movement.jsx` থেকে)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_17_000100_create_hr_separation`:
- **`hr_separations`:** রেফ নং (SEP-2026-00001…), কর্মী, ধরন (RESIGNATION / RELEASE / TERMINATION), আবেদন ও শেষ কর্মদিবস, নোটিশের দিন, কারণ, অবস্থা (PENDING / APPROVED / REJECTED), সিদ্ধান্ত আর নোট, কখন কার্যকর, কে চেয়েছেন।
- **`hr_lefty_cases`:** রেফ নং (LF-2026-0001…), কর্মী, শেষ হাজিরা, শেষ কর্মদিবস, রিপোর্টের তারিখ, কারণ, সহায়ক ডকুমেন্ট, শেষ মন্তব্য, অবস্থা (PENDING_REVIEW / UNDER_INVESTIGATION / CONFIRMED / REJECTED / REINSTATED / CLOSED), কে রিপোর্ট / যাচাই / নিশ্চিত করেছেন, ইতিহাস (json: কখন, কে, থেকে → পর্যন্ত, নোট)।
- **`hr_settlements`:** রেফ নং (FS-000001…, ভেতরের), কর্মী, বিচ্ছেদ বা লেফটি, সেটেলমেন্টের ধরন (RESIGN_FROM_JOB / DISABLE_FOR_LONG_ABSENTEEISM / TERMINATION / RETIREMENT / DEATH), কার্যকর তারিখ, বকেয়া বেতন / OT / বোনাস, ছুটির নগদায়ন, কর্তন, লোন আদায়, অন্য সমন্বয়, চূড়ান্ত প্রদেয়, মন্তব্য, হিসাবের ব্যাখ্যা, অবস্থা (DRAFT / APPROVED / PAID), অনুমোদন ও পরিশোধ।

## ৫. Workflow / Status ও Business Rules

**Resignation / Release:**
- কোড, নাম, কার্ড বা NID দিয়ে সক্রিয় কর্মী খোঁজা। ধরন (Resignation / Release / Termination), আবেদনের তারিখ, শেষ কর্মদিবস (আবেদনের আগে নয়), নোটিশের দিন (খালি রাখলে দুই তারিখের ফারাক), কারণ।
- একজনের একটাই খোলা বিচ্ছেদ (অপেক্ষায়, বা অনুমোদিত কিন্তু এখনো কার্যকর নয়)।
- Approve বা Reject (নোট ঐচ্ছিক); অপেক্ষায় থাকলে Withdraw (মুছে যায়); বাতিল হলে Reopen (আবার অপেক্ষায়)।
- **অনুমোদনের পরে:**
  - শেষ কর্মদিবস পার হলে কর্মী তালিকা থেকে বাদ যান ("Off the roster"): অবস্থা Separated, বিচ্ছেদের তারিখ = শেষ কর্মদিবস, খোলা পোস্টিং বন্ধ। নইলে "Leaves after …"।
  - দেরিতে পড়লে `hr:apply-movements` (রোজ ০০:০৫) বা স্ক্রিন খুললে কার্যকর হয়।
- অনুমোদিত বিচ্ছেদে "Create Final Settlement" — রেকর্ড থেকে হিসাব করা খসড়া; তারপর "Settlement · অবস্থা" লিংক।
- টাইল: অপেক্ষায়, অনুমোদিত-এখনো কাজে, বিচ্ছিন্ন (কতজনের সেটেলমেন্ট হয়নি), বাতিল।
- তালিকায় অবস্থা, খোঁজা, শেষ দিনের সীমা আর Excel (CSV)।

**Lefty Management:**
- কোড বা কার্ড দিয়ে সক্রিয় কর্মী খোঁজা। দেখায়: কার্ড, পদবি, গ্রেড, কোম্পানি, বিভাগ, সেকশন, শেষ হাজিরা আর কত দিন আগে (১০ দিন বা বেশি হলে লাল), আগে থেকে খোলা রেকর্ড।
- শেষ কর্মদিবস (শুরুতে শেষ হাজিরার দিন), রিপোর্টের তারিখ (শেষ কর্মদিবসের আগে নয়), কারণ, সহায়ক ডকুমেন্ট → Pending Review।
- ধাপ:
  - Pending Review → Investigate / Confirm / Reject।
  - Under Investigation → Confirm / Reject।
  - Confirmed → Reinstate / Close।
  - Rejected বা Reinstated → Close।
- Reject আর Reinstate-এ কারণ লাগে।
- **Confirm:** শেষ কর্মদিবস থেকে কর্মী Separated, নিয়মিত পেরোলের বাইরে। পাওনা Final Settlement-এ; চূড়ান্ত পেরোল বদলায় না।
- **Reinstate:** কর্মী আবার Active।
- প্রতিটা ধাপ ইতিহাসে থাকে। ID চাপলে বিস্তারিত আর ইতিহাস দেখায়।
- Lefty Reports: অবস্থা অনুযায়ী বিভাগ বা সেকশনভিত্তিক সংখ্যা।

**Final Settlement:**
- কর্মী বাছলে (যাদের সেটেলমেন্ট বাকি তারা আগে, "· to settle") বিচ্ছেদ বা লেফটির লাইন, ধরন, তারিখ আর পাওনা রেকর্ড থেকে ভরে যায়। তারিখ বদলালে আবার হিসাব হয়।
- **হিসাব:**
  - **বেতন:** শেষ চূড়ান্ত বেতনের পরের দিন থেকে (না থাকলে মাসের শুরু, বা যোগদানের দিন) শেষ কর্মদিবস পর্যন্ত। প্রতি দিন Gross A ÷ মাসের দিন; Absent আর বিনা বেতনের ছুটি বাদ।
  - **OT:** একই সময়ের হাজিরার OT, সংশোধনসহ।
  - **বোনাস:** অনুমোদিত কিন্তু চূড়ান্ত বেতনে না-দেওয়া।
  - **ছুটির নগদায়ন:** নগদায়নযোগ্য ছুটির অর্জিত দিন (বছরে প্রতি ১৮ দিন উপস্থিতিতে ১ দিন + আনা − নেওয়া, বাকির বেশি নয়) × Gross A ÷ ৩০।
  - **অন্য সমন্বয়:** অনুমোদিত কিন্তু না-দেওয়া পেরোল সমন্বয়।
  - **লোন:** ০।
  - ব্যাখ্যাটা Notes-এ লেখা থাকে।
- চূড়ান্ত প্রদেয় = পাওনা − কর্তন − লোন ± সমন্বয়; সেভে আবার হিসাব হয়। একজনের একটাই খসড়া।
- খসড়ায়: Calculate (রেকর্ড থেকে আবার, নিশ্চিতকরণসহ), Approve, এডিট, মুছা।
- Approved-এ লক; Mark Paid। পরিশোধে না-দেওয়া পেরোল সমন্বয়গুলো Applied হয়।
- Print: Final Settlement Statement — কর্মী, বিচ্ছেদ, খাত (+ / − / ±), চূড়ান্ত প্রদেয়, Notes, চার স্বাক্ষর।

## ৬. API ও পারমিশন

পারমিশন: `hrpayroll.separation.view` (দেখা), `hrpayroll.separation.manage` (বিচ্ছেদ চাওয়া / প্রত্যাহার, লেফটি রিপোর্ট, সেটেলমেন্ট তৈরি / এডিট / হিসাব / মুছা), `hrpayroll.separation.approve` (বিচ্ছেদের সিদ্ধান্ত ও Reopen, লেফটির ধাপ, সেটেলমেন্ট অনুমোদন ও পরিশোধ)। FACTORY_ADMIN সব; FACTORY_MANAGER সব তিনটা; AUDITOR view।

API (`/api/v1/hr-payroll/…`):
- **বিচ্ছেদ:** `GET separation/employees?q=`, `GET separations`, `POST separations`, `POST separations/{ref}/decide`, `POST separations/{ref}/reopen`, `POST separations/{ref}/settlement`, `DELETE separations/{ref}`।
- **লেফটি:** `GET lefty`, `GET lefty/candidates?q=`, `POST lefty`, `POST lefty/{ref}/move`।
- **সেটেলমেন্ট:** `GET settlements`, `GET settlements/preview?employee=&date=`, `POST settlements`, `PUT settlements/{ref}`, `DELETE settlements/{ref}`, `POST settlements/{ref}/move` (calculate / approve / paid)।

## ৭. Frontend পেজ (লাইট থিম)

`frontend/src/components/hrpayroll/separation.jsx`; Movement-এর কর্মী খোঁজা, ফিল্টার, তালিকা আর সিদ্ধান্তের ডায়ালগ ব্যবহার করে। সাইডবারে "SEPARATION" ফোল্ডার:
- **Resignation / Release** (`/hr-payroll/employee-separation`): ৪টা টাইল; বাঁয়ে New Separation; ডানে Separation List (ফিল্টার, Excel)।
- **Final Settlement** (`/hr-payroll/final-settlement`): Records (Calculate / Approve / Mark Paid / Print / এডিট / মুছুন) আর New settlement।
- **Lefty Management** (`/hr-payroll/lefty-management`): ৪টা টাইল; বাঁয়ে Report Lefty; ডানে Lefty Records (অবস্থা ফিল্টার, খোঁজা, Export, ধাপের বাটন); নিচে Lefty Reports; ধাপ আর বিস্তারিতের ডায়ালগ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Database/Migrations/2027_03_17_000100_create_hr_separation.php`
- `Support/SeparationBook.php` (তালিকা থেকে বাদ, চাকরির মেয়াদ, সেটেলমেন্টের হিসাব)
- `Http/Controllers/Api/HrSeparationApiController.php`
- `Console/ApplyMovements.php` (বিচ্ছেদও কার্যকর করে — বদলানো)
- `Routes/api.php`, `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`st`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/separation.jsx` (নতুন); `movement.jsx` (শেয়ার করা অংশ export, কর্মী খোঁজার op আর তারিখের লেবেল — বদলানো)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/HrPayroll/HrSeparationTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrSeparationTest.php`, ৩টা টেস্ট, ১২৬টা assertion):** test2 DB-তে পাস। পুরো HrPayroll ফোল্ডার ২৬/১০২৯, MenuAccess ৬/৩৪ পাস।
- **পদত্যাগ / অব্যাহতি:**
  - কর্মী খোঁজা। বাধ্যতামূলক ঘর না দিলে আর আবেদনের আগের শেষ দিন আটকায়।
  - SEP-2026-00001 (নোটিশ নিজে থেকে ১৯ দিন)। দ্বিতীয় খোলা বিচ্ছেদ আটকায়।
  - নোটসহ বাতিল; বাতিলের পরে Approve আটকায়; Reopen; Manager Approve → "Leaves after 20-11-2026", কর্মী এখনো Active।
  - অন্যটা Withdraw (নম্বর আবার ব্যবহার হয়)।
  - Termination (শেষ দিন ৫ নভেম্বর) অনুমোদনে সঙ্গে সঙ্গে Separated, খোলা পোস্টিং বন্ধ, খোঁজায় আর আসেন না।
  - সারি থেকে সেটেলমেন্ট FS-000001: বেতন ৩৫০০ (৫ দিন × ৭০০); দ্বিতীয়বার আটকায়।
  - ২১ নভেম্বর পদত্যাগ কার্যকর। Auditor দেখেন, চাইতে পারেন না।
- **লেফটি:**
  - খোঁজায় শেষ হাজিরা ৩ নভেম্বর, ৮ দিন আগে।
  - LF-2026-0001 (ধাপ: Investigate / Confirm / Reject); দ্বিতীয়টা আটকায়, খোলা রেকর্ড দেখায়।
  - Investigate → কারণ ছাড়া Reject আটকায় → Reinstate ভুল ধাপে আটকায় → Confirm (Separated, ৩ নভেম্বর; ধাপ Reinstate / Close) → কারণ ছাড়া Reinstate আটকায় → Reinstate (Active) → Close।
  - ইতিহাসে ৫টা ধাপ।
  - দ্বিতীয় কর্মীর নিশ্চিত লেফটির সেটেলমেন্ট প্রিভিউ "Disable For Long Absenteeism"।
  - Auditor শুধু দেখেন।
- **ফাইনাল সেটেলমেন্ট:**
  - সেটেলমেন্ট বাকি কর্মী তালিকায় আগে ("· to settle")।
  - প্রিভিউ: ১–৬ নভেম্বর, ১ দিন অনুপস্থিত → ৩৫০০; চূড়ান্ত বেতন নেই তাই মাসের শুরু থেকে; বিচ্ছেদের লাইন।
  - বাধ্যতামূলক ঘর না দিলে আটকায়। কর্তন ৫০০ দিয়ে সেভ (মেয়াদ ০y ১০m ৫d); দ্বিতীয় খসড়া আটকায়; সমন্বয় −১০০।
  - Calculate কর্তন ০-তে ফেরায়। আগেভাগে Paid আটকায়। Manager Approve → এডিট / মুছা আটকায় → Paid।
  - অন্যের খসড়া মুছা যায়। Auditor লিখতে পারেন না।

**UI চেক (`app_check/plans/hr08`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Resignation / Release: Hasan Sarkar পদত্যাগ, ১–৮ নভেম্বর (৭ দিন নোটিশ) → SEP-2026-00001 → নোটসহ Approve → "Off the roster" → Create Final Settlement → "Settlement · draft" ✔
- Final Settlement: FS-000001 → Approve → প্রিন্ট (Statement, খাত, Notes, চার স্বাক্ষর) ✔ New settlement ফর্ম ✔
- Lefty Management: Shawonoor Rahman খুঁজে শেষ হাজিরা দেখে রিপোর্ট → LF-2026-0001 → Confirm Lefty → কারণসহ Reinstate ("On regular payroll", ধাপ Close) ✔ বিস্তারিত আর ইতিহাস ✔ Department-wise রিপোর্ট ✔

## ১০. খোলা প্রশ্ন

১. **ছুটির নগদায়নের হার:** অর্জিত Earn Leave-এর দিন × Gross A ÷ ৩০ ধরা হয়েছে। Brickland-এর ডেমোতে নগদায়ন সবসময় ০ ("no encashable leave balance") দেখা গেছে। Basic দিয়ে হিসাব (বা অন্য নিয়ম) চাইলে জানাবেন।

২. **নোটিশ ঘাটতির কর্তন:** শ্রম আইনে নোটিশ কম হলে বেতন কাটার নিয়ম আছে। এখানে কর্তন হাতে লিখতে হয়, নিজে থেকে হিসাব হয় না। নিজে থেকে চাইলে জানাবেন।

৩. **Release-এর সেটেলমেন্টের ধরন:** Release হলে ধরন "Resign From Job" ধরা হয়েছে (Brickland-এর ধরনে Release নেই)। Retirement চাইলে জানাবেন।

৪. **লোন আদায়:** লোনের রেজিস্টার নেই, তাই ০ ("nothing outstanding")। লোন মডিউল এলে এখানে বকেয়া কিস্তি আসবে।

৫. **Final Settlement-এর অনুমোদন:** Brickland-এর মতো স্ক্রিনের সারি থেকেই। Approval Center দিয়ে চাইলে জানাবেন।

## ১১. As-built নোট

- **Lefty Management:** ক্রলে (২টা স্ক্রিন) ছিল না, Brickland-এ পরে যোগ হয়েছে। লাইভ প্রোব থেকে বানানো: RSC props আর JS chunk (`tools/erp_crawler/out/sp08`, `sp08js`), স্ক্রিনশট `sp08shots`। Final Settlement-এর হিসাবের নিয়ম Brickland-এর প্রিন্টের Notes থেকে নেওয়া।
- **সাইডবারের ক্রম:** Resignation / Release, Final Settlement, Lefty Management।
- **বেতনের সাথে:** বিচ্ছিন্ন কর্মী আর মাসিক বেতনে আসেন না। শেষ দিন পর্যন্ত পাওনা Final Settlement-এ মেটে।
- **UI চেকে ধরা পড়া:** ছুটির নগদায়নে বছরের পুরো Earn Leave বরাদ্দ (১৮ দিন) ধরা হচ্ছিল। Hasan Sarkar (১০ অক্টোবর যোগদান) ৳২১,০০০ পাচ্ছিলেন। এখন শুধু অর্জিত দিন ধরা হয় (প্রতি ১৮ দিন উপস্থিতিতে ১ দিন)। dev-এর FS-000001 আবার হিসাব করে ঠিক করা হয়েছে।
