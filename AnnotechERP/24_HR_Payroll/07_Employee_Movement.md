# Employee Movement

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/07`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Transfer | `/m/hr-payroll/employee-transfer` | 2 | 10 | 1 |
| 2 | Promotion | `/m/hr-payroll/employee-promotion` | 1 | 5 | 0 |
| 3 | Increment | `/m/hr-payroll/employee-increment` | 1 | 5 | 1 |
| 4 | Re-Joining | `/m/hr-payroll/employee-re-join` | 2 | 8 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Transfer

`/m/hr-payroll/employee-transfer`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New transfer

**ট্যাব: New transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Name & Designation | text |  |  |
| Old Department | text |  |  |
| Old Section | text |  |  |
| Old Line | text |  |  |
| Old Floor / Building | text |  |  |
| New Section | select |  | Select… |
| New Floor / Building | text |  |  |
| Effective Date* | date | ✔ |  |
| Reason | textarea |  |  |
| Status* | select | ✔ | Select…, Draft, Finalized |
- সেকশন: New transfer
- বাটন/অ্যাকশন: New transfer, Select…, Reset, Save transfer

**ডায়ালগ: New transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Name & Designation | text |  |  |
| Old Department | text |  |  |
| Old Section | text |  |  |
| Old Line | text |  |  |
| Old Floor / Building | text |  |  |
| New Section | select |  | Select… |
| New Floor / Building | text |  |  |
| Effective Date* | date | ✔ |  |
| Reason | textarea |  |  |
| Status* | select | ✔ | Select…, Draft, Finalized |
- খালি Save-এ মেসেজ: Employee is required.

#### Promotion

`/m/hr-payroll/employee-promotion`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee* | text | ✔ | Code, name, card or NID |
| Search | text |  | Code, name, ref no |
| Effective from | date |  |  |
| to | date |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Ref No, Employee, Designation, Grade, Gross A, Effective, Status, Action
- সেকশন: New Promotion, Promotion Requests
- বাটন/অ্যাকশন: All, Pending, Approved, Rejected, Excel (CSV), Ref No, Employee, Designation, Grade, Gross A, Effective, Status, Action

#### Increment

`/m/hr-payroll/employee-increment`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee* | text | ✔ | Code, name, card or NID |
| Search | text |  | Code, name, ref no |
| Effective from | date |  |  |
| to | date |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Ref No, Employee, Old Salary, New Salary, Increment, Effective, Reason, Approval, Action
- সেকশন: New Increment, Increment List
- বাটন/অ্যাকশন: All, Pending, Approved, Rejected, Excel (CSV), Ref No, Employee, Old Salary, New Salary, Increment, Effective, Reason, Approval, Action

**ডায়ালগ: New Salary**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Employee* | text | ✔ |  |
| Search | text |  |  |
| Effective from | date |  |  |
| to | date |  |  |
| Items per page | select |  | 10, 25, 50, 100 |

#### Re-Joining

`/m/hr-payroll/employee-re-join`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New re-join

**ট্যাব: New re-join**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Period | select |  | Select…, APP-BCLFY2026-27090001 — Brickland Composite Ltd., APP-BCLFY2026-27090002 — Brickland Composite Ltd. |
| Employee Code* | select | ✔ | Select… |
| Name & Position | text |  |  |
| Date of Rejoin* | date | ✔ |  |
| Employee Doc | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks [E] | textarea |  |  |
| Remarks [B] | textarea |  |  |
- সেকশন: New re-join
- বাটন/অ্যাকশন: New re-join, Reset, Save re-join

**ডায়ালগ: New re-join**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Period | select |  | Select…, APP-BCLFY2026-27090001 — Brickland Composite Ltd., APP-BCLFY2026-27090002 — Brickland Composite Ltd. |
| Employee Code* | select | ✔ | Select… |
| Name & Position | text |  |  |
| Date of Rejoin* | date | ✔ |  |
| Employee Doc | text |  |  |
| Attach | file |  |  |
| Remarks [E] | textarea |  |  |
| Remarks [B] | textarea |  |  |
- খালি Save-এ মেসেজ: Employee Code is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **কর্মী (24/03):** `hr_employees` (বর্তমান পোস্টিং, Gross A / B, অবস্থা, বিচ্ছেদের তারিখ), `hr_employee_assignments` (পোস্টিংয়ের ইতিহাস — Employee Assignment যেভাবে রাখে), `hr_employee_salaries` (বেতনের ইতিহাস), `hr_employee_pay_lines` (মূল বেতনের খাত)।
- **বেতন কাঠামো (24/01):** `SalaryStructures` — gross থেকে Basic, House Rent, Medical, Conveyance, Food ভাগ।
- **কোম্পানি:** বিভাগ, সেকশন, লাইন (লাইনের ফ্লোর), ফ্লোর আর বিল্ডিং, পদবি, গ্রেড।
- **পে পিরিয়ড (24/01):** পুনঃযোগদানের পিরিয়ড।
- **ফাইল:** `StoreFileAttachment` আর `/api/files` — পুনঃযোগদানের চিঠি।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_16_000100_create_hr_employee_movement`:
- **`hr_transfers`:** রেফ নং (TRF-000001…), কর্মী, পুরোনো বিভাগ / সেকশন / লাইন / ফ্লোর, নতুন বিভাগ / সেকশন / লাইন / ফ্লোর (লাইন থেকে), কার্যকর তারিখ, কারণ, অবস্থা (DRAFT / FINALIZED), কে কখন চূড়ান্ত করেছেন, কে লিখেছেন।
- **`hr_promotions`:** রেফ নং (PRM-000001…), কর্মী, পুরোনো ও নতুন পদবি, পুরোনো ও নতুন গ্রেড, পুরোনো ও নতুন Gross A (নতুনটা ঐচ্ছিক), কার্যকর তারিখ, কারণ, অবস্থা (PENDING / APPROVED / REJECTED), সিদ্ধান্ত আর নোট, কখন কার্যকর হয়েছে, কে চেয়েছেন।
- **`hr_increments`:** রেফ নং (INC-000001…), কর্মী, ধরন (NEW / AMOUNT / PERCENT) আর মান, পুরোনো ও নতুন Gross A, কার্যকর তারিখ, কারণ, অবস্থা, সিদ্ধান্ত আর নোট, কখন প্রয়োগ হয়েছে, কে চেয়েছেন।
- **`hr_rejoins`:** রেফ নং (RJN-000001…, শুধু ভেতরের), কর্মী, পে পিরিয়ড, পুনঃযোগদানের তারিখ, ডকুমেন্টের পাথ / লিংক আর সংযুক্ত ফাইল, মন্তব্য (ইংরেজি ও বাংলা), ছেড়ে যাওয়ার সময়ের অবস্থা আর তারিখ (মুছলে ফেরানোর জন্য)।

## ৫. Workflow / Status ও Business Rules

**Transfer:**
- কর্মী বাছলে নাম ও পদবি, পুরোনো বিভাগ, সেকশন, লাইন আর ফ্লোর / বিল্ডিং নিজে থেকেই ভরে।
- নতুন বিভাগ (বাধ্যতামূলক), তার সেকশন, তার লাইন। লাইনের ফ্লোর / বিল্ডিং নিজে থেকেই আসে।
- যা আটকায়: সেকশন অন্য বিভাগের, লাইন অন্য সেকশন / বিভাগের, কর্মী আগে থেকেই সেখানে আছেন, একই কর্মীর আরেকটা খসড়া আছে।
- **Draft:** কিছু সরে না; সারি থেকে Finalize (নিশ্চিতকরণসহ), এডিট বা মুছা যায়।
- **Finalized (সেভে বা পরে):**
  - কর্মীর বিভাগ, সেকশন, লাইন, ফ্লোর আর বিল্ডিং বদলায়।
  - পোস্টিংয়ের ইতিহাসে খোলা পোস্টিং কার্যকর তারিখে বন্ধ হয়, নতুনটা সেদিন থেকে খোলে ("Transfer TRF-… — কারণ")।
  - চূড়ান্ত বদলি আর বদলানো বা মুছা যায় না।

**Promotion:**
- কোড, নাম, কার্ড বা NID দিয়ে কর্মী খোঁজা। কার্ডে দেখায়: পদবি, গ্রেড, বিভাগ, সেকশন, লাইন, যোগদান, Gross A, কাঠামো।
- নতুন পদবি (বাধ্যতামূলক), নতুন গ্রেড, নতুন Gross A (ঐচ্ছিক; কাঠামোর নির্দিষ্ট খাত না ঢাকলে আটকায়), কার্যকর তারিখ, কারণ।
- কিছু না বদলালে আটকায়; একজনের একসাথে একটাই অপেক্ষমাণ পদোন্নতি।
- Approve বা Reject (নোট ঐচ্ছিক)। অপেক্ষমাণ থাকলে Withdraw (মুছে যায়)।
- অনুমোদিত আর আজ বা আগের তারিখ হলে এখনই কার্যকর; পরের তারিখ হলে সেই দিনে।
- কার্যকর হলে:
  - পদবি আর গ্রেড পোস্টিংয়ের ইতিহাসে ("Promotion PRM-…")।
  - নতুন gross বেতনের ইতিহাসে।
  - মূল বেতনের খাতগুলো কাঠামো অনুযায়ী আবার ভাগ হয়।
- টাইল: অপেক্ষায়, অনুমোদিত-তারিখের অপেক্ষায়, কার্যকর, বাতিল।
- তালিকায় অবস্থা (All / Pending / Approved / Rejected), খোঁজা, কার্যকর তারিখের সীমা, Excel (CSV)।

**Increment:**
- নতুন বেতন, টাকা বা শতাংশ হিসেবে দেওয়া যায় (শতাংশ পুরো টাকায় রাউন্ড)। পুরোনো → নতুন আর পার্থক্য (%) দেখায়।
- নতুন বেতন পুরোনোটার চেয়ে বেশি হতে হবে। একজনের একসাথে একটাই অপেক্ষমাণ ইনক্রিমেন্ট।
- শুধু Gross A বদলায়; Gross B কখনো বদলায় না।
- অনুমোদন, কার্যকর হওয়া আর টাইল পদোন্নতির মতো। "Applied" টাইলে মাসে মোট বৃদ্ধি দেখায়।

**কার্যকর হওয়ার দিন:** পরের তারিখের অনুমোদিত পদোন্নতি আর ইনক্রিমেন্ট `hr:apply-movements` কমান্ডে প্রয়োগ হয়। কমান্ড প্রতিদিন ঢাকা সময় রাত ১২:০৫-এ চলে; Promotion বা Increment স্ক্রিন খুললেও প্রয়োগ হয়।

**Re-Joining:**
- শুধু Separated বা Inactive কর্মী তালিকায় আসেন ("নাম, পদবি, বিভাগ, ছেড়েছেন তারিখ")।
- পে পিরিয়ড (ঐচ্ছিক, খোলাগুলো), পুনঃযোগদানের তারিখ (ছেড়ে যাওয়ার তারিখের পরে হতে হবে), ডকুমেন্ট (পাথ / লিংক, বা PDF / ছবি সংযুক্ত, ৫ MB পর্যন্ত), মন্তব্য দুই ভাষায়।
- সেভ করলে কর্মী আবার Active হন, বিচ্ছেদের তারিখ মুছে যায়, শেষ পোস্টিং থাকে।
- এডিটে কর্মী বদলায় না।
- মুছলে কর্মী আগের অবস্থা আর তারিখে ফেরেন।

## ৬. API ও পারমিশন

পারমিশন: `hrpayroll.movement.view` (দেখা), `hrpayroll.movement.manage` (বদলি, পদোন্নতি ও ইনক্রিমেন্টের অনুরোধ, প্রত্যাহার, পুনঃযোগদান), `hrpayroll.movement.approve` (পদোন্নতি / ইনক্রিমেন্টের সিদ্ধান্ত)। FACTORY_ADMIN সব; FACTORY_MANAGER view আর approve; AUDITOR view। ফাইল টাইপ `hr_rejoin_doc` → দেখা movement.view, যোগ movement.manage।

API (`/api/v1/hr-payroll/…`):
- **সাধারণ:** `GET movement/options`, `GET movement/employees?q=`।
- **Transfer:** `GET transfers`, `POST transfers`, `PUT transfers/{ref}`, `DELETE transfers/{ref}`, `POST transfers/{ref}/finalize`।
- **Promotion / Increment:** `GET promotions`, `POST promotions`, `GET increments`, `POST increments`, `POST {promotions|increments}/{ref}/decide`, `DELETE {promotions|increments}/{ref}` (প্রত্যাহার)।
- **Re-Joining:** `GET rejoins`, `POST rejoins`, `PUT rejoins/{ref}`, `DELETE rejoins/{ref}`, `POST rejoins/{ref}/doc`।
- সব রেকর্ড রেফ নং দিয়ে।

## ৭. Frontend পেজ (লাইট থিম)

`frontend/src/components/hrpayroll/movement.jsx`; সাইডবারে "EMPLOYEE MOVEMENT" ফোল্ডার:
- **Transfer** (`/hr-payroll/employee-transfer`): Records (গ্রিড, খোঁজা, Export; খসড়ায় Finalize / এডিট / মুছুন) আর New transfer (Brickland-এর ক্রমে ঘর, Reset / Save transfer)।
- **Promotion** (`/hr-payroll/employee-promotion`) আর **Increment** (`/hr-payroll/employee-increment`): ৪টা টাইল; বাঁয়ে অনুরোধের ফর্ম (কর্মী খোঁজা, কার্ড, Change); ডানে অনুরোধের তালিকা আর ফিল্টার; সিদ্ধান্তের ডায়ালগ (নোট)।
- **Re-Joining** (`/hr-payroll/employee-re-join`): Records (ডকুমেন্টের লিংক, এডিট / মুছুন) আর New re-join (Attach বাটন — ফাইল `/api/hr-payroll/rejoin-doc` route handler দিয়ে Laravel-এ স্ট্রিম হয়)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Database/Migrations/2027_03_16_000100_create_hr_employee_movement.php`
- `Support/MovementBook.php` (পোস্টিং আর তার ইতিহাস, নতুন gross আর মূল খাত, তারিখ এলে প্রয়োগ)
- `Http/Controllers/Api/HrMovementApiController.php`
- `Console/ApplyMovements.php` (`hr:apply-movements`), `routes/console.php` (রোজ ০০:০৫ ঢাকা)
- `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (`hr_rejoin_doc`), `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`mv`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/movement.jsx` (নতুন), `frontend/src/app/api/hr-payroll/rejoin-doc/route.js` (নতুন)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/HrPayroll/HrEmployeeMovementTest.php` (নতুন)

**টুল:** `tools/app_check/ui.mjs`-এ `pick`-এর `exactOption` (পুরো নাম মেলানো — "Finishing Department" বনাম "Fabric Finishing Department")।
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrEmployeeMovementTest.php`, ৩টা টেস্ট, ১২৫টা assertion):** test2 DB-তে পাস। পুরো HrPayroll ফোল্ডার ২৩/৯০৩, MenuAccess ৬/৩৪ পাস।
- **বদলি:**
  - ফর্মের তালিকায় কর্মীর পুরোনো পোস্টিং; লাইনের "Floor 2 · Building B"।
  - বাধ্যতামূলক ঘর; একই পোস্টিং আর অন্য বিভাগের সেকশন আটকায়।
  - খসড়া TRF-000001-এ কর্মী সরেন না; দ্বিতীয় খসড়া আটকায়; তারিখ এডিট হয়; কর্মী বদলানো আটকায়।
  - Finalize: বিভাগ, সেকশন, লাইন, ফ্লোর, বিল্ডিং বদলায়। ইতিহাসে ১ জানুয়ারি–১৫ নভেম্বর বন্ধ আর ১৫ নভেম্বর থেকে নতুন ("Transfer TRF-000001")।
  - চূড়ান্তের পরে আবার Finalize / মুছা / এডিট আটকায়।
  - সেভেই Finalized (TRF-000002) সঙ্গে সঙ্গে সরায়; খসড়া মুছা যায়।
  - Auditor দেখেন, লিখতে পারেন না।
- **পদোন্নতি আর ইনক্রিমেন্ট:**
  - কর্মী খোঁজা (নাম আর কার্ড দিয়ে)।
  - পদোন্নতি: কিছু না বদলালে আটকায়, বাধ্যতামূলক ঘর; PRM-000001 পাঠানো, দ্বিতীয়টা আটকায়, নোটসহ বাতিল, বাতিলের পরে Approve আটকায়।
  - পরেরটা প্রত্যাহার; প্রত্যাহারের নম্বর আবার ব্যবহার হয়।
  - Factory Manager অনুরোধ পারেন না, অনুমোদন পারেন। অনুমোদনে আজই কার্যকর: পদবি, গ্রেড ৪, Gross A ২৪০০০, বেতনের ইতিহাস (১১ নভেম্বর), পোস্টিংয়ের ইতিহাস, Basic খাত।
  - ইনক্রিমেন্ট:
    - পুরোনোর চেয়ে কম আটকায়। ৫% → ২১০০০ থেকে ২২০৫০ (INC-000001)।
    - টাকা +১৫০০ → ২২৫০০।
    - ১ ডিসেম্বরেরটা অনুমোদনে অপেক্ষায় থাকে। ১ নভেম্বরেরটা সঙ্গে সঙ্গে প্রয়োগ, Gross B একই।
    - ১ ডিসেম্বরে `hr:apply-movements` চালালে প্রয়োগ হয়।
  - Auditor সিদ্ধান্ত বা অনুরোধ পারেন না।
- **পুনঃযোগদান:**
  - তালিকায় শুধু ছেড়ে যাওয়া কর্মী ("… left 31 Oct 2026") আর খোলা পে পিরিয়ড।
  - যা আটকায়: বাধ্যতামূলক ঘর না দিলে, সক্রিয় কর্মী হলে, ছেড়ে যাওয়ার আগের তারিখ হলে।
  - RJN-000001 সেভে কর্মী Active, বিচ্ছেদের তারিখ মুছে যায়।
  - এডিট হয়। লেখা ফাইল আটকায়, PDF সংযুক্ত হয় (`hr_rejoin_doc`)।
  - মুছলে আবার Separated, ৩১ অক্টোবর। Auditor শুধু দেখেন।

**UI চেক (`app_check/plans/hr07`, `hr07t`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Transfer:
  - Poritosh Chandra Das-কে Cutting Department থেকে Finishing Department → Finishing → Finishing Line 1, ১২ নভেম্বর, Draft → TRF-000001 ✔
  - সারি থেকে Finalize (নিশ্চিতকরণ) → Finalized, Rahim Uddin ✔
- Promotion:
  - Sayem খুঁজে কার্ড ✔
  - Sr. Operator, Grade 3, Gross A ১৫৫০০ → PRM-000001 ✔
  - নোটসহ Approve → "In force", ৳১৪,০০০ → ৳১৫,৫০০ ✔
- Increment: Rokeya Begum, Amount ১০০০, ১ ডিসেম্বর → INC-000001 → Approve → "From 01 Dec 2026", অপেক্ষার টাইলে ১ ✔
- Re-Joining: Records খালি; New re-join ফর্ম Brickland-এর মতো (Period, Employee Code, Name & Position, Date, Employee Doc + Attach, Remarks) ✔ dev-এ ছেড়ে যাওয়া কর্মী নেই, তাই এখানে সেভ করা হয়নি — টেস্টে পুরো পথ যাচাই করা।

## ১০. খোলা প্রশ্ন

১. **পদোন্নতি / ইনক্রিমেন্টের অনুমোদন কোথায়:** Brickland-এ অনুমোদন এই স্ক্রিনেই (Approval Center-এ নয়)। এখানেও তাই; FACTORY_ADMIN আর FACTORY_MANAGER পারেন। Approval Center-এর workflow দিয়ে চাইলে জানাবেন।

২. **প্রত্যাহার করা অনুরোধ:** Withdraw করলে অনুরোধ মুছে যায়, তাই তার নম্বর পরের অনুরোধ আবার পায়। নম্বর ধরে রাখতে চাইলে জানাবেন (তাহলে "Withdrawn" অবস্থা লাগবে)।

৩. **পুনঃযোগদানের আগের ফাঁক:** কর্মী আগের রেকর্ডেই ফেরেন, যোগদানের তারিখ একই থাকে। ছেড়ে যাওয়া আর ফেরার মাঝের দিনগুলো হাজিরায় Absent দেখাতে পারে। পুনঃযোগদানের তারিখকে নতুন চাকরির শুরু (বোনাস, ছুটি) ধরতে চাইলে জানাবেন।

৪. **পুনঃযোগদানের পে পিরিয়ড:** এখন শুধু রেকর্ডে থাকে। মাসিক বেতনে কর্মী এমনিতেই আসেন, কারণ তিনি আবার Active আর বিচ্ছেদের তারিখ নেই।

## ১১. As-built নোট

- **লাইভ প্রোব:** Transfer আর Re-Joining Brickland-এর সাধারণ view (RSC spec, `tools/erp_crawler/out/em07`); Promotion আর Increment নিজস্ব স্ক্রিন (JS chunk `out/em07js`)। স্ক্রিনশট `out/em07shots`।
- **Employee Assignment (24/03)-এর সাথে:** বদলি আর পদোন্নতি একই পোস্টিংয়ের ইতিহাসে লেখে। Assignment-এর History-তে "Transfer TRF-…" / "Promotion PRM-…" কারণসহ দেখা যায়।
- **বেতন (24/06)-এর সাথে:** নতুন Gross A পরের মাসিক হিসাবে আসে। OT হার Basic থেকে, তাই মূল খাত নতুন করে ভাগ হলে OT হারও বদলায়।
- **UI চেকে ধরা পড়া:**
  - রেফ নং, কর্মী কোড আর পাতার "1 / 1" হাইফেনে বা জায়গার অভাবে ভেঙে যাচ্ছিল; এক লাইনে রাখা হয়েছে।
  - টুল: "Finishing Department" বাছতে গিয়ে "Fabric Finishing Department" মিলে যাচ্ছিল। ui.mjs-এ পুরো নাম মেলানোর অপশন যোগ করা হয়েছে।
