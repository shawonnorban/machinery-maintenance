# Employee

> **স্ট্যাটাস:** ✅ সম্পন্ন (৬টার ৬টা স্ক্রিন)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/03`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Quick Registration | `/m/hr-payroll/employee-quick-registration` | 1 | 13 | 0 |
| 2 | Employee List | `/m/hr-payroll/employee-register` | 1 | 5 | 0 |
| 3 | Employee Profile | `/m/hr-payroll/employee-profile` | 2 | 39 | 1 |
| 4 | Employee Assignment | `/m/hr-payroll/employee-assignment` | 1 | 2 | 0 |
| 5 | Biometric / Fingerprint | `/m/hr-payroll/biometric-fingerprint` | 1 | 4 | 0 |
| 6 | ID Card | `/m/hr-payroll/id-card-print` | 2 | 9 | 2 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Quick Registration

`/m/hr-payroll/employee-quick-registration`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee Code | text |  |  |
| Card / Proxi No.* | text | ✔ | e.g. 10235 |
| Employee Name* | text | ✔ | Full name |
| NID No.* | text | ✔ | 10, 13 or 17 digits |
| Upload Photo JPG, PNG · max 2 MB | file |  |  |
| Camera Take a photo | file |  |  |
| Date of Birth | date |  |  |
| Joining Date | date |  |  |
| Mobile No. | text |  | 01712345678 |
| Employee Status | select |  | Select…, Active, Inactive, Suspended, Separated |
| Salary Structure* | select | ✔ | Select…, Worker — RMG Minimum Wage 2023 (Non-EPZ), Worker — Gejet for 2013, Staff — Gejet for 2013 |
| Gross Salary A (Actual)* | text | ✔ | 14000 |
| Gross Salary B (Reporting)* | text | ✔ | 18000 |
- সেকশন: Basic Information, Salary Information, Employee Summary, Save Options
- বাটন/অ্যাকশন: Clear Form, Hide, Save, Save & Assignment, Save & Add New

#### Employee List

`/m/hr-payroll/employee-register`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code, name, card, NID or mobile |
| Factory | select |  | All, Brickland Composite Ltd. Head Office |
| Shift | select |  | All, 1 Shift (06 am) · Morning 06:00–14:00, Car Allow 09:00 -18:00 · General 08:00–17:00, Executive 08:00 AM-5:30 PM · G |
| Employee Status | select |  | All, Active, Inactive, Suspended, Separated |
| Assignment Status | select |  | All, Assigned, Pending |
- গ্রিড কলাম: Employee, Card / Proxi, Designation, Department, Section, Line, Grade, Shift, NID, Mobile, Gross A, Gross B, Assignment, Status, Action
- সেকশন: Employee Search, Employees
- বাটন/অ্যাকশন: All, Reset, Search, Previous, Next

#### Employee Profile

`/m/hr-payroll/employee-profile`

**ট্যাব: Records 500**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Active, Separated, Suspended, Inactive |
| Employee Code | text |  | GMT1314421 |
| Search Employees with code, name, section, designation, floor, line and status | search |  | Search 500 records… |
| Filter by Code | search |  |  |
| Filter by Name | search |  |  |
| Filter by Section | search |  |  |
| Filter by Designation | search |  |  |
| Filter by Floor | search |  |  |
| Filter by Line# | search |  |  |
| Filter by Grade | search |  |  |
| Filter by Shift | search |  |  |
| Filter by Assignment | search |  |  |
| Filter by Business Type | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Section, Designation, Floor, Line#, Grade, Shift, Assignment, Business Type, Status, ACTION
- সেকশন: Search
- বাটন/অ্যাকশন: New employee, All, Search, Reset, Export, Code, Name, Section, Designation, Floor, Line#, Grade, Shift, Assignment, Business Type, Status, Edit

**ট্যাব: New employee**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee Code* | text | ✔ | GMT1315577 |
| Proxi# | text |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office* | select | ✔ | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| First Name[E] | text |  |  |
| Last Name[E] | text |  |  |
| Full Name [E]* | text | ✔ |  |
| Nick Name [E] | text |  |  |
| Father's Name [E] | text |  |  |
| Mother's Name [E] | text |  |  |
| Guardian Name [E] | text |  |  |
| National ID# | text |  |  |
| Birth Certificate# | text |  |  |
| Date of Birth | date |  |  |
| Religion* | select | ✔ | Select…, Islam, Hinduism, Christianity, Buddhism, Other |
| Gender* | select | ✔ | Select…, Male, Female, Other |
| Marital Status | select |  | Select…, Single, Married, Divorced, Widowed |
| #of Child | number |  |  |
| Blood Group | select |  | Select…, A Pos, A Neg, B Pos, B Neg, Ab Pos, Ab Neg, O Pos, O Neg |
| Personal Mobile# | text |  |  |
| Home Phone# | text |  |  |
| Ext. | text |  |  |
| Personal Email | text |  |  |
| TIN# | text |  |  |
- সেকশন: New employee
- বাটন/অ্যাকশন: New employee, Personal Information, Job Detail, Contact Detail, Document, Allowance/Deduction, Optional, Back Ground Check, Select…, Continue, Save employee, Add New, Back To List

**ডায়ালগ: New employee**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Employee Code* | text | ✔ |  |
| Proxi# | text |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office* | select | ✔ | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| First Name[E] | text |  |  |
| Last Name[E] | text |  |  |
| Full Name [E]* | text | ✔ |  |
| Nick Name [E] | text |  |  |
| Father's Name [E] | text |  |  |
| Mother's Name [E] | text |  |  |
| Guardian Name [E] | text |  |  |
| National ID# | text |  |  |
| Birth Certificate# | text |  |  |
| Date of Birth | date |  |  |
| Religion* | select | ✔ | Select…, Islam, Hinduism, Christianity, Buddhism, Other |
| Gender* | select | ✔ | Select…, Male, Female, Other |
| Marital Status | select |  | Select…, Single, Married, Divorced, Widowed |
| #of Child | number |  |  |
| Blood Group | select |  | Select…, A Pos, A Neg, B Pos, B Neg, Ab Pos, Ab Neg, O Pos, O Neg |
| Personal Mobile# | text |  |  |
| Home Phone# | text |  |  |
| Ext. | text |  |  |
| Personal Email | text |  |  |
| TIN# | text |  |  |

#### Employee Assignment

`/m/hr-payroll/employee-assignment`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code, name or card |
| Tick every employee on this page | checkbox |  |  |
- গ্রিড কলাম: Employee, Card, Designation, Department, Line, Status
- সেকশন: Employees, Employee Assignment
- বাটন/অ্যাকশন: Pending (0), Assigned (952), All (952), Previous, Next

#### Biometric / Fingerprint

`/m/hr-payroll/biometric-fingerprint`

**ট্যাব: Fingerprint Registration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code, name or card |
| Biometric Status | select |  | All, Registered (0), Pending (0), Not Registered (952) |
| Enrol on device | select |  | Select…, Main Gate Fingerprint (ATTANDENCE-01), Main gate (MMS1), ZKTeco TEST123 (ZK-TEST123) |
| Tick every employee on this page | checkbox |  |  |
- গ্রিড কলাম: Device, Model / Serial, Protocol, Location, Status, Last Seen, Last Sync, Users, Fingers, Mapped
- গ্রিড কলাম: Employee, Card, Department, Line, Device Mapping, Biometric Status, Registered, Action
- সেকশন: Device Setup
- বাটন/অ্যাকশন: Fingerprint Registration, Employee Mapping 1, All, Search, Queue enrolment, Map, Previous, Next

#### ID Card

`/m/hr-payroll/id-card-print`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee Status | select |  | All, Active, Separated, Suspended, Inactive |
| Joining Date | date |  |  |
| Employee Code | text |  | Search… |
| Bangla | radio |  |  |
| English | radio |  |  |
| Mtex English | radio |  |  |
| Single employee in a page? | checkbox |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Add to print queue, All, Search, Add, Clear Queue, Reset

**ট্যাব: Add to print queue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Print Option* | select | ✔ | Select…, Bangla, English, Mtex English |
| Single employee in a page? One card per sheet. Off prints the queue several to a | checkbox |  |  |
- সেকশন: New ID card print
- বাটন/অ্যাকশন: Add to print queue, Select…, Reset

**ডায়ালগ: Add to print queue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Print Option* | select | ✔ | Select…, Bangla, English, Mtex English |
| Single employee in a page? One card per sheet. Off prints the queue several to a | checkbox |  |  |
- খালি Save-এ মেসেজ: Employee Code is required.

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Print Option* | select | ✔ | Select…, Bangla, English, Mtex English |
| Single employee in a page? One card per sheet. Off prints the queue several to a | checkbox |  |  |
- খালি Save-এ মেসেজ: Employee Code is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **24/01 Master Data:**
  - wage gazette আর তার লাইন (`hr_gazettes`, `hr_gazette_lines`) আর employee category (`hr_employment_types`)। Salary Structure হলো একটা gazette আর একটা category, যেমন "Worker — RMG Minimum Wage 2023 (Non-EPZ)"।
  - `GazetteLineResource::means()`-এর নিয়ম: BASIC হলো gross-এর ভাজক, HRENT হলো Basic-এর শতাংশ, বাকিগুলো নির্দিষ্ট অঙ্ক।
  - `HrDefaults::ensure()` নতুন কোম্পানিতে Brickland-এর ডিফল্ট gazette বসায়।
- **Company Management-এর master:** `factories`, `buildings`, `floors`, `departments`, `sections`, `production_lines`, `designations` (department ধরে), `grades` আর `shifts`। Assignment-এর সব অপশন এখান থেকে আসে; এখানে নতুন কিছু তৈরি করা যায় না।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_20_000100_create_hr_employees`:
- **`hr_employees`:**
  - **পরিচয়:** employee_code (EMP-000001, কোম্পানি ধরে unique, সেভের সময় নম্বর পড়ে), card_no (Card / Proxi, কোম্পানি ধরে unique), name, nid, date_of_birth, joining_date, mobile, status (ACTIVE / INACTIVE / SUSPENDED / SEPARATED)।
  - **বেতন:** gazette_id আর employment_type_id (Salary Structure), gross_a, gross_b, actual_salary।
  - **ব্যক্তিগত তথ্য:** father/mother name, email, gender, religion, blood group, marital status, country, rocket, present/permanent address। এগুলো Employee Profile থেকে ভরবে; এখন তালিকায় "—" দেখায়।
  - **বর্তমান posting:** factory, building, floor, department, section, line, designation, grade, shift।
- **`hr_employee_assignments`:** প্রতিটা posting, সাথে effective_from, effective_to (বর্তমানটায় ফাঁকা) আর reason।
- **`hr_employee_salaries`:** বেতন যেমন বসানো হয়েছিল; নিবন্ধনের সময় joining date থেকে, পরে বদলালে নতুন রেকর্ড।
- **Model:** `HrEmployee` (`STATUSES`, `PLACEMENT`, `isAssigned()`), `HrEmployeeAssignment`, `HrEmployeeSalary`।
- **Support:** `SalaryStructures` (`all`, `find`, `split`)।

মাইগ্রেশন `2027_03_10_000100_create_hr_employee_profile` (Employee Profile):
- **`hr_employees`-এ নতুন কলাম:**
  - **Personal Information-এর বাকি অংশ:** নামের প্রথম ও শেষ অংশ, ডাকনাম, অভিভাবক আর সম্পর্ক, জন্ম সনদ, জাতীয়তা, সন্তান সংখ্যা, বাসার ফোন ও এক্সট., TIN।
  - **Job Detail:** অব্যাহতির তারিখ আর Active।
  - **Contact Detail:** দুই ঠিকানার পোস্ট কোড; জরুরি যোগাযোগের নাম, সম্পর্ক, মোবাইল আর ঠিকানা।
  - **Document:** পাসপোর্ট (নং, ইস্যুর স্থান, ইস্যু ও মেয়াদের তারিখ), ড্রাইভিং লাইসেন্স (নং, মেয়াদ), ছবি (`photo_file_id`)।
  - **Optional:** বাংলা নাম, উচ্চতর শিক্ষা, স্বামী/স্ত্রীর নাম, সম্পর্ক আর কর্মী কোড, পরিবারের সাথে থাকেন কিনা, উৎসব ছুটির OT।
  - NID এখন ঐচ্ছিক: Quick Registration এখনো চায়, Profile চায় না।
- **`hr_employee_pay_lines`:** কর্মীর ভাতা আর কর্তন — pay element, ধরন (ALLOWANCE / DEDUCTION), Core Salary?, পরিমাণ, Active?, Effect Salary?। একজন কর্মীর একই element একবারই।
- **`hr_employee_background_checks`:** যাচাইয়ের ধরন, কে যাচাই করেছেন, জমা দেওয়ার তারিখ (Verified On), ক্লিয়ার কিনা, মন্তব্য।
- **Model:** `HrEmployee::PROFILE` (Profile-এর কলাম)।

মাইগ্রেশন `2027_03_11_000100_create_hr_id_card_queue` (ID Card):
- **`hr_id_card_queue`:** প্রিন্টের অপেক্ষায় থাকা কার্ড — কর্মী (একজন একবারই), Print Option (BANGLA / ENGLISH / MTEX_ENGLISH), Single employee in a page?, কে আর কখন কিউতে তুলেছেন। কার্ডের তথ্য এখানে রাখা হয় না; প্রিন্টের সময় প্রোফাইল থেকে পড়া হয়।

Biometric / Fingerprint-এর ডাটা থাকে [Device Integration](../03_Settings/01_Device_Integration.md)-এর টেবিলে (`app/Modules/AttendanceDevice`): `dev_employee_maps` (কোন যন্ত্রে কোন কর্মী কোন PIN-এ, Pending / Registered), `dev_fingerprints` (যন্ত্রের পাঠানো আঙুলের template, কর্মীর সাথে), `dev_users`, `dev_commands`।

## ৫. Workflow / Status ও Business Rules

### Quick Registration

- **ধাপের strip:** তিনটা ধাপ, Basic Information → Salary Information → Finish। পূরণ হলে ধাপ সবুজ হয়।
- **Employee Code:** "Auto"; পরের খালি নম্বর দেখায়, সেভের সময় lock নিয়ে নম্বর পড়ে।
- **বাধ্যতামূলক** (Brickland-এর বার্তায়):
  - Card / Proxi No.: "Card / Proxi No. is required."
  - Employee Name, NID No.
  - Salary Structure, Gross Salary A আর B।
- **অন্য নিয়ম:**
  - NID ১০, ১৩ বা ১৭ সংখ্যার।
  - মোবাইল ০১ দিয়ে শুরু ১১ সংখ্যার।
  - Card আগে কারো থাকলে: "Card / Proxi No. 10235 is already used by EMP-000001."
  - gross যদি structure-এর নির্দিষ্ট ভাতাও পূরণ না করে, আটকায়।
- **স্বয়ংক্রিয় ভাঙ্গন** (A আর B দুটোরই), structure-এর লাইন থেকে:
  - Basic = (gross − Medical − Food − Conveyance) ÷ BASIC-এর ভাজক।
  - House Rent = Basic × HRENT%।
  - Other = rounding-এর পরে যা বাকি থাকে।
  - উদাহরণ: ১৪০০০ → ৭৭০০ + ৩৮৫০ + ৪৫০ + ১২৫০ + ৭৫০ (Brickland-এর সাথে মেলে); ১৮০০০ → ১০৩৬৭ + ৫১৮৩।
- **Salary Structure-এর তালিকা:** আগে Worker, পরে Staff; প্রতিটার মধ্যে gazette-এর নিজের ক্রম, নতুনটা আগে।
- **সেভের ধরন:**
  - **Save:** Employee List-এ যায়।
  - **Save & Add New:** ফর্ম আবার শুরু হয়, joining date আর structure থেকে যায়। Enter চাপলেও এটাই হয়।
  - **Save & Assignment:** নতুন কর্মীকে বাছা অবস্থায় Employee Assignment খোলে।
- **Edit** (`?edit=EMP-000001`): একই ফর্ম, বাটন Update। বেতন বদলালে নতুন salary রেকর্ড হয়; Starting Salary আগেরটাই থাকে।

### Employee List

- **পাতা:** একবারে ২৫ জন, নতুন কোড আগে।
- **Filter** (বাটন চাপলে খোলে): Search (code, name, card, NID বা mobile), Factory, Department, Section, Line, Designation, Shift, Employee Status আর Assignment Status।
- **কলাম:** ৩৪টা, Brickland-এর মতো। Service Period ("00 year, 0 month, 8 day") joining date থেকে হিসাব হয়।
- **সারির মেনু:**
  - Transfer আর Assignment: Employee Assignment-এ নিয়ে যায়।
  - Edit: Quick Registration-এ নিয়ে যায়।
  - Salary: dialog খোলে, A আর B-এর ভাঙ্গন আর salary-র ইতিহাসসহ।
  - History: posting-এর ইতিহাস দেখায়।
  - Transfer, Edit আর Assignment দেখায় শুধু যাঁর manage অনুমতি আছে।

### Employee Assignment

- **বাঁদিক:**
  - ট্যাব Pending, Assigned আর All, সংখ্যাসহ।
  - খোঁজার ঘর: code, name বা card।
  - প্রতি সারিতে টিক, আর "এই পাতার সবাই"।
- **ডানদিকের posting:**
  - বাধ্যতামূলক: Factory, Department আর Designation।
  - **কোন তালিকা কীসের নিচে:** Building আসে factory থেকে, Floor building থেকে, Section department থেকে, Designation department থেকে। Line আসে floor থেকে; floor বসানো না থাকলে section বা department থেকে।
  - উপরের ঘর বদলালে নিচের ঘরগুলো মুছে যায়।
  - একজন বাছলে ফর্ম তাঁর বর্তমান posting দিয়ে শুরু হয়।
- **বদলি:** যিনি আগেই বসানো, তাঁর জন্য Transfer Reason বাধ্যতামূলক। বর্তমান posting আজকের তারিখে বন্ধ হয়ে history-তে থাকে, আর নতুনটা আজ থেকে চালু হয়।
- **একসাথে কয়েকজন:** একই posting সবার জন্য। কেউ আগে বসানো থাকলে কারণ লাগে।
- **অন্য কোম্পানির জায়গা** বাছলে আটকায়।

### Employee Profile

- **Records:**
  - Search প্যানেল: Department, Section, Status আর Employee Code (কোডের অংশ দিলেই চলে)। ফাঁকা রাখলে পুরো তালিকা।
  - তালিকায় খোঁজার ঘর, Export, প্রতি কলামের নিচে ছাঁকনি, প্রতি পাতায় ১০ / ২৫ / ৫০ / ১০০।
  - কলাম: Code, Name, Section, Designation, Floor, Line#, Grade, Shift, Assignment, Business Type (কর্মীর ধরনের গাছের মাথা — Staff বা Worker), Status।
  - সারির বাটন: Print, SB Print (সার্ভিস বই), Edit, মুছুন।
- **New / Edit employee:**
  - **উপরের অংশ:** Employee Code (সেভে নম্বর পড়ে), Proxi#, Company, Office (= factory), আর ছবি।
  - **সাতটা ট্যাব:** Personal Information, Job Detail, Contact Detail, Document, Allowance/Deduction, Optional, Back Ground Check। যেকোনো ক্রমে পূরণ করা যায়; Back আর Continue দিয়ে ট্যাব বদলায়।
  - **একবারের সেভ:** পুরো প্রোফাইল, pay লাইন আর যাচাই একসাথে লেখা হয়।
  - ভুল থাকলে উপরে সব বার্তা আসে, আর প্রথম ভুলের ট্যাব খুলে যায়।
- **বাধ্যতামূলক:**
  - Proxi# (Brickland-এ ঐচ্ছিক; এখানে লাগে, কারণ হাজিরা, গেট আর স্টোর কর্মীকে এটা দিয়েই চেনে), Office, Full Name [E], Nationality, Religion, Gender, Employee Type, Job Status।
  - নতুন কর্মীর Gross Salary — বেতনের ভাগ এখান থেকে হয়।
- **অন্য নিয়ম:**
  - NID দিলে ১০, ১৩ বা ১৭ সংখ্যা। মোবাইল আর জরুরি মোবাইল ০১ দিয়ে শুরু ১১ সংখ্যা।
  - Card আগে কারো থাকলে Quick Registration-এর মতো আটকায়।
  - অব্যাহতির তারিখ যোগদানের আগে নয়; পাসপোর্টের মেয়াদ ইস্যুর পরে।
- **posting:**
  - Job Detail-এর কোনো ঘর দিলে Department আর Designation লাগবে, Employee Assignment-এর মতো।
  - নতুন কর্মী: posting যোগদানের তারিখ থেকে চালু হয়।
  - Edit-এ posting বদলালে বর্তমানটা আজ বন্ধ হয়, আর নতুনটা "Changed on the Employee Profile" কারণসহ আজ থেকে চালু হয় — Assignment-এর ইতিহাসেই।
- **বেতন:**
  - Gross Salary = Gross A। নতুন কর্মীর Gross B-ও একই অঙ্ক।
  - Salary Structure আসে Employee Type থেকে: একই gazette-এ সেই ধরন থাকলে সেটা, না থাকলে ধরনের সবচেয়ে নতুনটা। ধরনের কোনো কাঠামো না থাকলে আটকায়।
  - Gross বা ধরন বদলালে নতুন salary রেকর্ড হয়।
- **Allowance/Deduction:**
  - ট্যাবে এই কর্মী আর Gross Salary, নিচে Pay Element গ্রিড: Element, Type, Core Salary?, Amount, Active?, Effect Salary?।
  - সেভ করলে কর্মীর লাইনগুলো গ্রিডে যা আছে তা দিয়ে বদলে যায়। একই element দুবার রাখা যায় না।
  - কার্যকর লাইনের নিট (ভাতা − কর্তন, Active আর Effect Salary চিহ্নিত লাইন) দেখায়।
- **Back Ground Check:** Check Type (বাধ্যতামূলক), Cleared?, Verified By, Verified On, Remarks। Verified On হলো জমা দেওয়ার দিন — নতুন যাচাইয়ে সেভের দিন বসে, পরে বদলায় না। গ্রিড থেকে সরালে মুছে যায়।
- **ছবি:** Document ট্যাবে Attach — JPG বা PNG, সর্বোচ্চ ২ MB। নতুন কর্মীর ছবি সেভের পরপরই আপলোড হয়; Edit-এ সাথে সাথে। সরানো যায়।
- **মোছা:** কর্মী অন্য কোনো রেকর্ডে (গেট পাস, উৎপাদন, বিল …) ব্যবহৃত হলে আটকায় — "Inactive করুন"। না হলে তাঁর posting, বেতনের ইতিহাস, pay লাইন আর যাচাইসহ মুছে যায়।
- **প্রিন্ট** (Brickland-এর শিটের মতো, `lib/print-document`):
  - **Print — Employee Profile:** IDENTITY (ছবিসহ), JOB DETAIL, CONTACT, ALLOWANCE / DEDUCTION আর কার্যকর লাইনের নিট; Authorised Signature।
  - **SB Print — Service Book:** SERVICE PARTICULARS, POSTINGS & TRANSFERS (প্রতিটা posting কারণসহ), INCREMENTS (প্রতিটা বেতন রেকর্ড আর আগেরটার চেয়ে কত বেড়েছে); Employee আর HR Signature।

### ID Card

- **Records:**
  - Search ঘর: Employee Status (শুরুতে Active), Employee Section, Joining Date (ঠিক সেই দিন), Employee Code (কোডের অংশ)। নিচে Print Option (Bangla / English / Mtex English; শুরুতে Bangla) আর Single employee in a page?
  - বাটন: **Search** (কিউর তালিকা ছাঁকে), **Add** (ঘরগুলো যাদের বাছে সবাইকে এখানে বাছা অপশন দিয়ে কিউতে তোলে; কেউ না মিললে বার্তা), **Clear Queue** (নিশ্চিত করে পুরো কিউ খালি), **Print Queue**, আর ডানে Reset।
  - তালিকা: Photo (ছবি আছে কিনা), Emp. Code, Name, Section, Designation, Print Option, Single Per Page, Queued; খোঁজার ঘর আর Export। সারিতে Print, Edit (অপশন আর Single বদলানো, dialog-এ) আর মুছুন।
- **Add to print queue:** Employee Code আর Print Option বাধ্যতামূলক (শুরুতে Mtex English), Single employee in a page?। ফাঁকা হলে Brickland-এর বার্তা "Employee Code is required."।
- **একজন একবারই:** আগে থেকে কিউতে থাকলে আবার তুললে শুধু অপশন বদলায়, জায়গা একই থাকে।
- **কার্ড প্রোফাইল থেকে, প্রিন্টের সময়:** ছবি, পদবি, সেকশন, বিভাগ, যোগদানের তারিখ আর রক্তের গ্রুপ — তাই কিউর কার্ড কখনো পুরোনো হয় না। ছবি না থাকলে "No Photo"।
- **কার্ডের নকশা** (ক্রেডিট-কার্ড মাপ ৮৫.৬ × ৫৪ mm):
  - উপরে কোম্পানির নাম আর ঠিকানা; নাম, আইডি নং, পদবি, সেকশন, বিভাগ, যোগদান, রক্তের গ্রুপ; ডানে ছবি; নিচে কার্ড / প্রক্সি নং আর স্বাক্ষরের জায়গা।
  - **Bangla:** লেবেল বাংলায় (আইডি নং, পদবি, সেকশন, বিভাগ, যোগদান, রক্তের গ্রুপ, কর্তৃপক্ষের স্বাক্ষর), নাম বাংলায় থাকলে বাংলা নাম — স্ক্রিনের ভাষা যা-ই হোক।
  - **English:** একই নকশা, ইংরেজি লেবেল।
  - **Mtex English:** খাড়া কার্ড (৫৪ × ৮৫.৬ mm), ছবি উপরে মাঝখানে।
- **পাতা:** Single কার্ড প্রতিটা আলাদা পাতায়; বাকিগুলো A4-এ দুই কলামে কয়েকটা।

### Biometric / Fingerprint

- **উপরে:** Employees, Registered, Pending, Not Registered (অব্যাহতিপ্রাপ্ত বাদে), Devices Online (x / y), Last Sync (কখন আর তারিখ)। হেডারে "Device Setup" — Settings-এর Attendance Devices-এ যায়।
- **Device Setup কার্ড:** যন্ত্রগুলো — নাম ও কোড, মডেল ও সিরিয়াল, প্রোটোকল, অবস্থান, Online / Offline, শেষ দেখা, ইউজার, আঙুল, ম্যাপ করা · কতজন ম্যাপ হয়নি; "Open device admin"।
- **বায়োমেট্রিক অবস্থা:** Registered — ERP-তে কর্মীর আঙুলের ছাপ আছে (বা কোনো যন্ত্রে ম্যাপ Registered); Pending — কোনো যন্ত্রে ম্যাপ করা, আঙুল এখনো আসেনি; Not Registered — কিছুই নেই।
- **Fingerprint Registration:**
  - খোঁজা (কোড, নাম, কার্ড), Biometric Status (প্রতিটার সংখ্যাসহ), Department; ২৫ জন এক পাতায়।
  - **Queue enrolment:** টিক দেওয়া কর্মীদের বাছা যন্ত্রে ম্যাপ (PIN = কার্ড নং; কার্ড না থাকলে কোডের সংখ্যা) আর ইউজার হিসেবে পাঠানো (`DATA UPDATE USERINFO`) — Pending। যন্ত্র আঙুলের ছাপ জানালে Registered।
  - **Send to all machines:** টিক দেওয়া কর্মীদের ইউজার আর ERP-তে রাখা আঙুলের ছাপ (প্রতি আঙুলে নতুনতমটা) সব সক্রিয় পুশ যন্ত্রে।
  - **Send everyone to all machines:** আঙুলের ছাপ আছে এমন সব সক্রিয় কর্মী।
  - **Collect from machines:** সব পুশ যন্ত্রকে ইউজার আর আঙুল আবার পাঠাতে বলা।
  - **Delete from all machines** (নিশ্চিত করে): প্রতিটা পুশ যন্ত্র থেকে ইউজার মোছা (`DATA DELETE USERINFO`), ERP-র আঙুলের ছাপ আর ম্যাপ মোছা।
  - সারিতে Device Mapping-এর চিপ (যন্ত্র · PIN; সবুজ Registered, হলুদ Pending), × দিয়ে Unmap; **Map** — যন্ত্র আর PIN (শুরুতে কার্ড নং) দিয়ে হাতে ম্যাপ। ম্যাপ করলে ঐ PIN-এর ইউজার, আঙুল আর না-মেলা পাঞ্চ কর্মীর হয়ে যায়।
  - একই যন্ত্রে একটা PIN একজনেরই — অন্যজনের হলে আটকায়।
- **Employee Mapping:** যন্ত্রের যে ইউজারের সাথে কোনো কর্মী নেই — যন্ত্র, User ID, যন্ত্রে নাম, কার্ড আর মেলা কর্মী (কার্ড বা PIN কারো কার্ড নং হলে; যে কর্মী ঐ যন্ত্রে আগেই ম্যাপ করা তাঁকে আর দেখানো হয় না)। **Auto-map by card** সবগুলো ম্যাপ করে; পরামর্শ না থাকলে কর্মী কোড লিখে Map।
- **কমান্ড** শুধু সক্রিয় পুশ যন্ত্রে যায়; Command Queue-তে দেখা যায়।

## ৬. API ও পারমিশন

- **`hrpayroll.employee.view`:** তালিকা, বিবরণ আর history। পায় FACTORY_ADMIN, FACTORY_MANAGER আর AUDITOR (Owner সব পান)।
- **`hrpayroll.employee.manage`:** নিবন্ধন, Edit আর Assignment। পায় FACTORY_ADMIN।

| Method | Path |
|---|---|
| GET | `/hr-payroll/employees/options` (next_code, structures, recent, statuses আর ৯টা জায়গার তালিকা) |
| GET | `/hr-payroll/employees` (q, status, assignment, factory, department, section, line, designation, shift, page; উত্তরে counts) |
| POST | `/hr-payroll/employees` |
| GET / PUT | `/hr-payroll/employees/{code}` |
| GET | `/hr-payroll/employees/{code}/history` (assignments, salaries) |
| POST | `/hr-payroll/employees/assign` (employees[], ৯টা জায়গা, reason) |

**Employee Profile** (একই পারমিশন: দেখা `hrpayroll.employee.view`, লেখা `hrpayroll.employee.manage`; ছবির ফাইল `hr_employee_photo` হিসেবে FileApiController-এ):

| Method | Path |
|---|---|
| GET | `/hr-payroll/profiles/options` (next_code, company, employee types, pay elements, nationalities, বাছাইয়ের তালিকা) |
| GET | `/hr-payroll/profiles` (department, section, status, code) |
| POST | `/hr-payroll/profiles` |
| GET / PUT / DELETE | `/hr-payroll/profiles/{code}` |
| POST / DELETE | `/hr-payroll/profiles/{code}/photo` (ব্রাউজার থেকে `/api/hr-payroll/employee-photo` হয়ে — ছবি server action-এর ১ MB সীমা ছাড়ায়) |

**ID Card** (দেখা আর প্রিন্ট `hrpayroll.employee.view`, কিউ বদলানো `hrpayroll.employee.manage`):

| Method | Path |
|---|---|
| GET | `/hr-payroll/id-cards/options` (কর্মী, সেকশন) |
| GET | `/hr-payroll/id-cards` (status, section, joining_date, code) |
| POST | `/hr-payroll/id-cards` (একজন) · `/hr-payroll/id-cards/add` (Search যাদের বাছে) |
| PUT / DELETE | `/hr-payroll/id-cards/{id}` |
| DELETE | `/hr-payroll/id-cards` (Clear Queue) |
| GET | `/hr-payroll/id-cards/print?ids=` (ফাঁকা হলে পুরো কিউ) |

**Biometric / Fingerprint** (নতুন পারমিশন `hrpayroll.biometric.view` আর `.manage`; FACTORY_ADMIN দুটোই, FACTORY_MANAGER আর AUDITOR view):

| Method | Path |
|---|---|
| GET | `/hr-payroll/biometric/overview`, `/employees` (q, status, department, page), `/unmapped` |
| POST | `/hr-payroll/biometric/enrol`, `/send`, `/collect`, `/delete`, `/map`, `/unmap`, `/automap` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: HR & Payroll → **Employee** → Quick Registration, Employee List, Employee Assignment।

- **হেডারের বাটন** (Brickland-এর মতো): Quick Registration-এ "Employee List", Employee List-এ "Quick Registration" (primary), Assignment-এ "Quick Registration"।
- **Quick Registration:**
  - ৩ ধাপের strip।
  - পাশাপাশি চারটা কার্ড: Basic Information, Salary Information, Employee Summary, Save Options।
  - Salary কার্ডে Salary Calculation Preview, Hide/Show আর A ও B-এর দুটো ভাঙ্গন।
  - Summary কার্ডে Assignment Status (Pending)।
  - Save Options-এ চারটা বাটন আর Recently Registered।
- **Employee List:** কার্ড "Employees 1–25 of N", Filter, চওড়া টেবিল (৩৪ কলাম), সারির মেনু আর Previous/Next। পাতা রিলোড হয় না।
- **Employee Assignment:** দুই কলাম। নিচে নীল নোট "Every option comes from Company Management …"। বাটন Cancel আর Save Assignment, বদলির সময় Save Transfer।

- **Employee Profile** (সাইডবারে Employee List-এর পরে): দুটো ট্যাব, Records আর New employee / Edit employee, পাতা রিলোড ছাড়া বদলায় (`?edit=EMP-000001` দিলে সেই কর্মীতে খোলে)। ফর্মের নিচে সবসময় দেখা যায় এমন বার: Back, Continue, Cancel, Save / Update employee, Add New, Back To List।

- **ID Card** (সাইডবারে Employee Assignment-এর পরে): Records আর Add to print queue, পাতা রিলোড ছাড়া। কার্ড প্রিন্ট নিজের উইন্ডোতে (উপরে শিরোনাম আর Print), কারণ কার্ডের মাপ A4 শিটের নকশায় আঁটে না।

- **Biometric / Fingerprint** (সাইডবারে Employee Assignment-এর পরে, ID Card-এর আগে): `components/hrpayroll/employee/biometric.jsx`। দুই ট্যাব পাতা রিলোড ছাড়া; Map একটা dialog-এ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/HrPayroll/Database/Migrations/2026_12_20_000100_create_hr_employees.php`, `Models/HrEmployee.php`, `Models/HrEmployeeAssignment.php`, `Models/HrEmployeeSalary.php`, `Support/SalaryStructures.php`, `Http/Controllers/Api/HrEmployeeApiController.php`, `Routes/api.php` |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`hrpayroll.employee.view`, `.manage`), `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`HR_PAYROLL.EMPLOYEE` ফোল্ডার, তিনটা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/hr-payroll/[screen]/employee.js`, `page.js` (নিজস্ব lang group আর হেডারের বাটন), `actions.js` (`employeeAction`), `frontend/src/components/hrpayroll/employee/quick-registration.jsx`, `employee-list.jsx`, `employee-assignment.jsx` |
| ভাষা | `lang/{en,bn}/hrpayroll.php` (`emp`) |
| টেস্ট | `tests/Feature/HrPayroll/HrEmployeeTest.php` |
| Employee Profile | `app/Modules/HrPayroll/Database/Migrations/2027_03_10_000100_create_hr_employee_profile.php`, `Http/Controllers/Api/HrEmployeeProfileApiController.php`, `Models/HrEmployee.php` (বদলানো), `Routes/api.php` (বদলানো); `app/Shared/Files/Http/Controllers/Api/FileApiController.php`, `FileAttachmentController.php` (`hr_employee_photo`); `MenuRegistry.php` (`HR_PAYROLL.EMPLOYEE_PROFILE`); `frontend/src/components/hrpayroll/employee/employee-profile.jsx`, `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো), `frontend/src/app/api/hr-payroll/employee-photo/route.js`; `lang/{en,bn}/hrpayroll.php` (`emp.prof`); `tests/Feature/HrPayroll/HrEmployeeProfileTest.php` |
| ID Card | `app/Modules/HrPayroll/Database/Migrations/2027_03_11_000100_create_hr_id_card_queue.php`, `Http/Controllers/Api/HrIdCardApiController.php`, `Routes/api.php` (বদলানো); `MenuRegistry.php` (`HR_PAYROLL.ID_CARD`); `frontend/src/components/hrpayroll/employee/id-card.jsx`, `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো); `lang/{en,bn}/hrpayroll.php` (`emp.idc`); `tests/Feature/HrPayroll/HrIdCardTest.php` |
| Biometric / Fingerprint | `app/Modules/AttendanceDevice/Http/Controllers/Api/BiometricApiController.php`, `Routes/api.php`, `Support/Terminals.php` (Device Integration-এর সাথে ভাগ); `MenuRegistry.php` (`HR_PAYROLL.BIOMETRIC`); `frontend/src/components/hrpayroll/employee/biometric.jsx`, `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো); `lang/{en,bn}/hrpayroll.php` (`emp.bio`); `tests/Feature/AttendanceDevice/DeviceIntegrationTest.php` (Biometric-এর টেস্ট) |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **Employee Profile:** ✅ হয়েছে (উপরে)। Quick Registration-এর Upload Photo আর Camera এখনো আসেনি — ছবি Profile-এর Document ট্যাবে দেওয়া যায়।
- **ID Card:** ✅ হয়েছে (উপরে)।
- **Biometric / Fingerprint:** ✅ হয়েছে (উপরে), Settings-এর [Device Integration](../03_Settings/01_Device_Integration.md)-এর সাথে।
- **কর্মী খোঁজা:** হয়ে গেছে, [Store & Material](../09_Store_Material/01_Store_Material.md)-এর `GET /store-material/employee?id=`। code বা card / proxy নং দিয়ে খোঁজে; Received By, Receiver আর Returned By এটাই ব্যবহার করে।
- **24/01-এর Allowance আর Deduction** এখন কোম্পানির ব্যবহারকারীর নাম ধরে। Employee master চলে আসায় পরে এগুলো কর্মীর সাথে যুক্ত করা যাবে।

## ৯. টেস্ট ও যাচাই

- **`HrEmployeeTest`:** ২টা টেস্ট, ৫২টা assertion।
  - **Quick Registration:**
    - next_code EMP-000001; Worker — RMG 2023 structure আছে।
    - ফাঁকা ফর্মে চারটা বার্তা, Brickland-এর লেখায়।
    - NID-এর সংখ্যা ভুল হলে আর gross কম হলে আটকায়।
    - সেভ হলে EMP-000001, Active, Pending। ভাঙ্গন A = 7700/3850/450/1250/750/0/14000, B = 10367/5183/18000।
    - একই card দিলে আটকায়।
    - Edit-এ নতুন salary রেকর্ড হয়, Starting Salary 14000 থাকে।
  - **List:** খোঁজা যায় নাম আর card দিয়ে; counts pending ২। Factory Manager নিবন্ধন করতে পারেন না, Storekeeper তালিকা দেখতে পারেন না।
  - **Assignment:**
    - ফাঁকা হলে চারটা বার্তা।
    - দুজনকে একসাথে বসানো যায়, তারপর counts assigned ২; line ধরে filter কাজ করে।
    - বদলিতে কারণ বাধ্যতামূলক; আগের posting আজকের তারিখে বন্ধ হয়, খোলা posting থাকে ১টা।
    - অন্য কোম্পানির factory আটকায়।
- **রিগ্রেশন:** HrPayroll (সব, HrEmployee সহ), MaterialProcurement, ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite আর Auth মিলিয়ে ১৮৭টা টেস্ট সবুজ, ১৩৪৪টা assertion (২০২৬-১০-২৩/২৪, একা চালানো, ৫৫ মিনিট)।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner):
  - **Quick Registration:**
    - ফাঁকা "Save & Add New" চাপলে বার্তা আসে ✔
    - Mohammad Sayem (card 50001001, ১৪০০০/১৮০০০, actual ১৫০০০): summary আর ভাঙ্গন দেখায়, সেভ হয়ে EMP-000001 ✔ ফর্ম আবার শুরু হয়, joining date থেকে যায়, Recently Registered-এ নাম আসে ✔
    - Poritosh Chandra Das (EMP-000002) "Save & Assignment" দিয়ে সেভ ✔
  - **Assignment:**
    - Pending (২)-এ দুজনকে টিক দিয়ে Dhaka Unit 1 · Sewing / Production · Jr. Operator → "2 employee(s) assigned." ✔
    - EMP-000002-কে কারণ "Moved to cutting for the new order" দিয়ে Cutting Department · Cutting Man-এ বদলি ✔
  - **Employee List:** ২ জন, সঠিক department আর designation ✔ History dialog-এ দুটো posting: আগেরটা ২৩-১০-২০২৬-এ বন্ধ, নতুনটা Current, কারণসহ ✔
  - **ধরা পড়া সমস্যা, ঠিক করা হয়েছে:**
    - ডিফল্ট structure "Gejet for 2013" আসছিল। এখন gazette-এর ক্রমে আসে।
    - আগের error বার্তা ঘর পূরণের পরেও থেকে যাচ্ছিল। এখন ঘর বদলালেই মুছে যায়।
  - **dev master:** Company Management-এ designation ছিল না। ৫টা department-এ ৯টা designation বসানো হয়েছে।
  - সব রেকর্ড dev DB-তে রাখা আছে।

- **`HrEmployeeProfileTest`** (২০২৬-১১-১০; ২টা টেস্ট, ৫৬টা assertion):
  - options: next_code EMP-000001, জাতীয়তার প্রথমটা Bangladesh, Worker ধরন আর pay element আছে।
  - ফাঁকা ফর্মে Office, Full Name [E], Employee Type, Job Status, Proxi# আর Gross Salary-র বার্তা; posting-এ Department ছাড়া আটকায়; একই element দুবার আটকায়; ভুল NID আটকায়।
  - সেভ: EMP-000001, Assigned, Worker, Sewing · Jr. Operator · Line 1, gross ১৪০০০; জাতীয়তা, ধর্ম, বাংলা নাম, পরিবার, পাসপোর্ট ঠিকঠাক; ২টা pay লাইন, নিট ৩৮০; যাচাইয়ের তারিখ ১০-১১-২০২৬; posting ০১-১০-২০২৬ থেকে খোলা। একই card আটকায়। Records department, code আর status দিয়ে ছাঁকে। Factory Manager লিখতে পারেন না, Storekeeper দেখতে পারেন না।
  - এক সপ্তাহ পরে Edit: Store · Receive Man-এ বদলি, gross ১৫৫০০, pay লাইন সরানো, যাচাই ক্লিয়ার (তারিখ ১০-১১ থাকে) আর নতুন যাচাই ১৭-১১। ইতিহাসে দুটো posting (আগেরটা ১৭-১১-এ বন্ধ, নতুনটার কারণ "Changed on the Employee Profile") আর দুটো বেতন।
  - ছবি: PDF আটকায়, PNG সেভ হয়, সরানো যায়।
  - মোছা: অন্য রেকর্ডে ব্যবহৃত হলে আটকায়; না হলে ইতিহাসসহ মুছে যায়।
- **রিগ্রেশন:** HrPayroll (সব; ১১টা টেস্ট, ৩৮৩টা assertion), MenuAccess, RoleMatrix, FabricInspectionPhoto আর ApiGateCoverage (২২টা টেস্ট, ১৩৫টা assertion) সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১১-১০, owner; `app_check/plans/hr03p`, `hr03q`; error 0):
  - ফাঁকা "Save employee" চাপলে সাতটা বার্তা একসাথে ✔
  - Rokeya Begum (Proxi# 50001005, Dhaka Unit 1, Sewing / Production · Jr. Operator, Worker, ১৪৫০০; ছবি; Shift Allowance ৬০০ আর Food Allowance কর্তন ১৫০; পুলিশ যাচাই) → EMP-000005, Assigned ✔
  - Edit-এ ছবি আর যাচাই (জমার তারিখ ১০ Nov 2026) ফিরে আসে ✔
  - Print: ছবিসহ Employee Profile, নিট ৪৫০ ✔; SB Print: posting ০১-Nov-২০২৬ থেকে Current, বেতন ১৪৫০০ ✔
  - **ধরা পড়া সমস্যা, ঠিক করা হয়েছে:** প্রিন্টে ছবি IDENTITY-র টেবিলকে নিচে ঠেলে দিচ্ছিল — এখন ছবি টেবিলের পাশে।
  - রেকর্ড dev DB-তে রাখা আছে।

- **`HrIdCardTest`** (২০২৬-১১-১০; ১টা টেস্ট, ৩৩টা assertion): test2 DB-তে পাস; পুরো HrPayroll স্যুট ১২টা টেস্ট / ৪১৬টা assertion আর MenuAccess + RoleMatrix ১৩টা টেস্ট সবুজ।
  - ফাঁকা Add to print queue-এ দুটো বার্তা; একজনকে Mtex English, Single দিয়ে তোলা।
  - Add (Active, Sewing, Bangla): দুজন; আগের জনের অপশন বদলায়, দুবার ওঠে না। কেউ না মিললে বার্তা।
  - Joining Date দিয়ে Add (ঠিক সেই দিন): দুজন; Search সেকশন দিয়ে কিউ ছাঁকে।
  - প্রিন্ট: সেকশনের নাম বদলালে কার্ডে সাথে সাথে নতুন নাম; বাংলা নাম, কার্ড নং, রক্তের গ্রুপ, যোগদান ঠিক; একটা id দিলে শুধু সেই কার্ড।
  - Edit, মুছুন, Clear Queue। Factory Manager দেখেন কিন্তু কিউতে তুলতে পারেন না; Storekeeper দেখতে পারেন না।
- **ব্রাউজার যাচাই** (২০২৬-১১-১০, owner; `app_check/plans/hr03id`, `hr03id2`; error 0):
  - ফাঁকা Add to print queue → "Employee Code is required." ✔ Rokeya Begum কিউতে ✔
  - English বেছে কোড "EMP-00000" দিয়ে Add → ৫ জন কিউতে, Rokeya-র Photo "Yes" ✔
  - একটা সারির Print: ছবিসহ English কার্ড ✔
  - EMP-000001 Bangla-য়, Rokeya Edit-এ Mtex English + Single → Print Queue: Rokeya-র খাড়া কার্ড আলাদা পাতায়, বাকি চারটা এক পাতায়, Bangla কার্ডে বাংলা লেবেল ✔
  - কিউ dev DB-তে রাখা আছে।

- **Biometric** (`DeviceIntegrationTest::test_biometric_enrols_sends_maps_and_deletes`, ২০২৬-১১-১০): পুরো HrPayroll স্যুট (১২টা টেস্ট, ৪১৬টা assertion) আর MenuAccess, RoleMatrix, ApiGateCoverage, AccessControlConfiguration (২৭টা টেস্ট, ১৮৪টা assertion) সবুজ।
  - দুটো যন্ত্র, তিনজন কর্মী — সবাই Not Registered। দুজনকে M-01-এ Queue enrolment → কার্ড নং PIN, Pending, ইউজার কমান্ডের লেখা ঠিক।
  - যন্ত্র আঙুল জানালে Registered; Pending ছাঁকলে একজন।
  - Send to all machines → দুই যন্ত্রে ইউজার + আঙুল (৪টা কমান্ড), M-02-এর ম্যাপ Registered।
  - Employee Mapping: কার্ড মিলে এমন ইউজার Auto-map; অন্যের PIN আটকায়; হাতে Map আর Unmap।
  - Collect, তারপর Delete from all machines → দুই যন্ত্রে DELETE, আঙুল আর ম্যাপ মুছে যায়। Factory Manager কাজ করতে পারেন না।
- **ব্রাউজার যাচাই** (২০২৬-১১-১০, owner; `app_check/plans/di02`; error 0): নকল যন্ত্রের আঙুলের ছাপ পাঠানোর পর Mohammad Sayem আর Poritosh Registered (১টা আঙুল); Shawonoor Rahman আর Rokeya Begum টিক দিয়ে GATE-01-এ Queue enrolment → Pending, চিপ "GATE-01 · 1053", "GATE-01 · 50001005" ✔ Employee Mapping-এ Admin (1001) আর Visitor (7777) ✔
  - **ধরা পড়া, ঠিক করা হয়েছে:** Visitor (কার্ড 50001005) Rokeya-কে দেখাচ্ছিল, কিন্তু Rokeya ঐ যন্ত্রে আগেই ম্যাপ — Auto-map কিছু করছিল না। এখন যন্ত্রে আগেই ম্যাপ করা কর্মীকে আর পরামর্শে দেখানো হয় না।

## ১০. খোলা প্রশ্ন

- **"manpower updates as you save":** Brickland বলে সেভ করলেই জনবলের হিসাব বদলায়। এখানে line-এর `manpower` ঘর Planning মডিউল নিজে ব্যবহার করে, তাই সেটা এখন বদলানো হয় না। গোনা হয় কর্মীর posting থেকে। Planning-কে কর্মীর সংখ্যা থেকে নিতে হবে কিনা, তা Planning-এর পালায় ঠিক হবে।

- **Employee Profile-এর Allowance/Deduction আর Back Ground Check:** Brickland-এ এই দুটো ট্যাব আলাদা প্যানেল — ভেতরে আবার যেকোনো কর্মী বাছা যায় (সব কর্মীর তালিকা)। এখানে দুটোই যে কর্মীর প্রোফাইল খোলা তাঁরই, আর প্রোফাইলের সাথে একবারের সেভে যায়। আলাদা "সব কর্মীর" প্যানেল চাইলে জানাবেন।
- **Employee Code:** Brickland-এ কোড হাতে লেখা হয় (GMT1315577)। এখানে Quick Registration-এর মতোই স্বয়ংক্রিয় (EMP-000001), বদলানো যায় না। হাতে কোড দিতে চাইলে জানাবেন।
- **Proxi# বাধ্যতামূলক:** Brickland-এ ঐচ্ছিক, এখানে বাধ্যতামূলক (Quick Registration-এর মতো)।
- **Gross Salary নতুন কর্মীর জন্য বাধ্যতামূলক:** বেতনের ভাগ আর salary রেকর্ড এখান থেকেই হয়।

- **ID Card-এর Mtex English:** Brickland-এর ডেমোতে Mtex English কার্ডের নমুনা দেখা যায়নি (কিউর একমাত্র কার্ডটা Bangla)। এখানে এটাকে খাড়া (portrait) ইংরেজি কার্ড ধরা হয়েছে। আসল Mtex নকশা অন্যরকম হলে একটা নমুনা দিলে মিলিয়ে দেব।
- **কার্ডের পেছনের দিক:** Brickland একদিকই ছাপে; এখানেও তাই। পেছনে ঠিকানা, জরুরি নম্বর বা হারানো কার্ড ফেরতের নির্দেশ চাইলে জানাবেন।

- **Biometric-এর PIN:** কার্ড নং-ই PIN (Brickland-এর মতো)। কার্ড না থাকলে কর্মী কোডের সংখ্যা (EMP-000004 → 000004) — যন্ত্র শুধু সংখ্যা নেয়। অন্য নিয়ম চাইলে জানাবেন।
- **যন্ত্রে আঙুল নেওয়া:** Queue enrolment কর্মীকে ইউজার হিসেবে যন্ত্রে পাঠায়; আঙুলের ছাপ যন্ত্রেই দিতে হয় (অপারেটর যন্ত্রের মেনু থেকে), তারপর যন্ত্র নিজে জানায়। ERP থেকে যন্ত্রকে "এখন আঙুল নাও" (ENROLL_FP) বলা সব ফার্মওয়্যারে চলে না, তাই রাখা হয়নি।

## ১১. As-built নোট

- **🟡** (২০২৬-১০-২৩): ৬টার মধ্যে ৩টা স্ক্রিন হয়েছে: Quick Registration, Employee List আর Employee Assignment।
  - **কেন এখন:** ব্যবহারকারীর সিদ্ধান্ত ছিল Store & Material-এর আগে HR-এর কর্মী-তালিকা বানানো, যাতে Receiver আসল কর্মী-তালিকা থেকে খোঁজা যায়।
  - **বাকি:** Employee Profile, Biometric আর ID Card।
- **লাইভ প্রোব:** `tools/erp_crawler/plans/hr01.json` থেকে `hr03.json`, শুধু পড়া। Brickland-এর ভাঙ্গনের অঙ্ক ১৪০০০/১৮০০০ দিয়ে মেলানো হয়েছে।
- **ছবি:** Brickland-এ Upload Photo আর Camera আছে ("Can also be added or changed later")। container-এর GD-তে JPEG নেই, আর file attachment-এর ব্যবস্থা লাগবে; তাই ছবি Employee Profile-এর ধাপে আসবে।
- **Employee Profile ✅** (২০২৬-১১-১০):
  - **প্রোব:** স্পেক Brickland-এর RSC payload থেকে (`tools/erp_crawler/out/hr03b`): ৬৫টা ফিল্ড, সাতটা ট্যাব আর দুটো প্যানেল। Edit-এর ভেতরের ট্যাবগুলো আর Print / SB Print-এর শিট লাইভ দেখা (`out/hr03p`, `out/hr03print`)।
  - **বিদ্যমান কোডের ব্যবহার:** `HrEmployeeProfileApiController` Employee কন্ট্রোলারের সাহায্যকারী (rows, names, recordSalary, নম্বর) ব্যবহার করে। ছবি সাধারণ file attachment দিয়ে।
  - **বাকি:** Biometric আর ID Card।
- **ID Card ✅** (২০২৬-১১-১০):
  - **প্রোব:** স্পেক RSC payload থেকে (`tools/erp_crawler/out/hr03b/p3`): কলাম, Search-এর ঘর, Add / Clear Queue / Print Queue, ফর্মের ঘর আর নোট। স্ক্রিন, ফর্ম আর একটা কার্ড ও কিউর প্রিন্ট লাইভ দেখা (`out/hr03bio`)।
  - **UI চেকে ধরা পড়া:** কিছু নয় অ্যাপে; চেকের সিলেক্টর "Print Queue" ভুল করে "Add to print queue" ট্যাবে চাপছিল — সিলেক্টর ঠিক করা হয়েছে।
  - **বাকি:** Biometric / Fingerprint — Settings-এর Device Integration (03/01) সহ।
- **Biometric / Fingerprint ✅** (২০২৬-১১-১০) — Settings-এর Device Integration (03/01)-এর সাথে একসাথে বানানো, কারণ দুটো একই যন্ত্রের ডাটা ব্যবহার করে। **24/03 সম্পূর্ণ** (৬টার ৬টা)।
  - **প্রোব:** Brickland-এর স্ক্রিন কাস্টম, RSC-তে স্পেক নেই; লেখা আর বাটন JS chunk থেকে (`tools/erp_crawler/out/bio_js`), লেআউট লাইভ স্ক্রিনশট থেকে (`out/hr03bio`)।
