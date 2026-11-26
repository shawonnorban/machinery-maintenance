# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Capacity Planning](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `12/01`

**⬅ ফিরে যান:** [Capacity Planning](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Factory Calendar | `/m/planning/factory-calendar` | 1 | 3 | 1 |
| 2 | Production Line | `/m/planning/planning-line-setup` | 2 | 13 | 1 |
| 3 | Machine Master | `/m/planning/machine-master` | 1 | 4 | 0 |
| 4 | Manpower Master | `/m/planning/manpower-master` | 1 | 3 | 0 |
| 5 | Shift Master | `/m/planning/shift-master` | 1 | 3 | 0 |
| 6 | Efficiency Master | `/m/planning/efficiency-master` | 2 | 9 | 1 |
| 7 | SMV / SAM Master | `/m/planning/smv-sam-master` | 2 | 7 | 1 |
| 8 | Other Setup | `/m/planning/planning-setup` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Factory Calendar

`/m/planning/factory-calendar`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Unit Brickland Composite Ltd. Unit-1 (FAC-001) | select |  | Brickland Composite Ltd. Unit-1 (FAC-001) |
| Year 2025 2026 2027 2028 | select |  | 2025, 2026, 2027, 2028 |
| Month January February March April May June July August September October Novemb | select |  | January, February, March, April, May, June, July, August, September, October, November, December |
- সেকশন: Factory Calendar — October 2026
- বাটন/অ্যাকশন: Weekly Holiday, Add Holiday, Calendar, List, 1 Working Day, 2 Factory Holiday friday, 3 Working Day, 4 Working Day, 5 Working Day, 6 Working Day, 7 Working Day, 8 Working Day, 9 Factory Holiday Friday, 10 Working Day, 11 Working Day, 12 Working Day, 13 Working Day, 14 Working Day, 15 Working Day, 16 Factory Holiday friday

**ডায়ালগ: Add Holiday** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Status Working Day Weekly Holiday Public Holiday Factory Holiday Emergency Holid | select |  | Working Day, Weekly Holiday, Public Holiday, Factory Holiday, Emergency Holiday, Special Working Day |
| Reason* | text | ✔ |  |

#### Production Line

`/m/planning/planning-line-setup`

**ট্যাব: Records 4**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sewing lines with the standard manpower, machines, hours and efficiency e | search |  | Search 4 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Unit, Floor, Line, Name, Manpower, Machine, Hours, Efficiency, Floor Line (output), Status, ACTION
- বাটন/অ্যাকশন: New line, Export, Unit, Floor, Line, Name, Manpower, Machine, Hours, Efficiency, Floor Line (output), Status, Edit

**ট্যাব: New line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor* | select | ✔ | Select…, FAC-001>>FLR-001, FAC-001>>FLR-002, FAC-001>>FINISHING, FAC-001>>PACKING, FAC-001>>FLR-004 |
| Line Code* | text | ✔ |  |
| Line Name* | text | ✔ |  |
| Standard Manpower* | number | ✔ |  |
| Standard Machines* | number | ✔ |  |
| Standard Working Hours* | number | ✔ |  |
| Standard Efficiency %* | number | ✔ |  |
| Best Fit (garment families) | text |  |  |
| Display Order | number |  |  |
| Under Maintenance | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: New line
- বাটন/অ্যাকশন: New line, Select…, Reset, Save line

**ডায়ালগ: New line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Floor* | select | ✔ | Select…, FAC-001>>FLR-001, FAC-001>>FLR-002, FAC-001>>FINISHING, FAC-001>>PACKING, FAC-001>>FLR-004 |
| Line Code* | text | ✔ |  |
| Line Name* | text | ✔ |  |
| Standard Manpower* | number | ✔ |  |
| Standard Machines* | number | ✔ |  |
| Standard Working Hours* | number | ✔ |  |
| Standard Efficiency %* | number | ✔ |  |
| Best Fit (garment families) | text |  |  |
| Display Order | number |  |  |
| Under Maintenance | checkbox |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Floor is required.

#### Machine Master

`/m/planning/machine-master`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Line | select |  | All |
| Status | select |  | All |
| Search Machines installed on each sewing line, as the Line Master keeps them | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Floor, Line, Planning Line, Machine Type, Name / Code, Category, Installed, Working, Standby, Cap. / Hour, Status
- বাটন/অ্যাকশন: Clear, Export, Floor, Line, Planning Line, Machine Type, Name / Code, Category, Installed, Working, Standby, Cap. / Hour, Status

#### Manpower Master

`/m/planning/manpower-master`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Line | select |  | All |
| Search Operators, helpers and staff each sewing line needs, as the Line Master k | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Floor, Line, Planning Line, Role, Required, Remarks
- বাটন/অ্যাকশন: Clear, Export, Floor, Line, Planning Line, Role, Required, Remarks

#### Shift Master

`/m/planning/shift-master`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Line | select |  | All |
| Search The shifts each sewing line runs, as the Line Master keeps them | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Floor, Line, Planning Line, Shift, Time, Break (min), Working Hours, Manpower, Target, Status
- বাটন/অ্যাকশন: Clear, Export, Floor, Line, Planning Line, Shift, Time, Break (min), Working Hours, Manpower, Target, Status

#### Efficiency Master

`/m/planning/efficiency-master`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New efficiency standard

**ট্যাব: New efficiency standard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Applies To* | select | ✔ | Select…, Style, Buyer, Line, Unit (factory) |
| Unit | select |  | Select…, Brickland Composite Ltd. Unit-1 (FAC-001) |
| Line | select |  | Select…, FLR-001>>13, FLR-001>>14, FLR-001>>LINE01 |
| Buyer | select |  | Select…, LPP (LPP) |
| Style No | text |  |  |
| Efficiency %* | number | ✔ |  |
| Effective From* | date | ✔ |  |
| Remarks | text |  |  |
| Active | checkbox |  |  |
- সেকশন: New efficiency standard
- বাটন/অ্যাকশন: New efficiency standard, Reset, Save efficiency standard

**ডায়ালগ: New efficiency standard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Applies To* | select | ✔ | Select…, Style, Buyer, Line, Unit (factory) |
| Unit | select |  | Select…, Brickland Composite Ltd. Unit-1 (FAC-001) |
| Line | select |  | Select…, FLR-001>>13, FLR-001>>14, FLR-001>>LINE01 |
| Buyer | select |  | Select…, LPP (LPP) |
| Style No | text |  |  |
| Efficiency %* | number | ✔ |  |
| Effective From* | date | ✔ |  |
| Remarks | text |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Efficiency must be between 0 and 150%.

#### SMV / SAM Master

`/m/planning/smv-sam-master`

**ট্যাব: Records 6**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Standard minutes per style for planning — sewing SMV and whole-garment SA | search |  | Search 6 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style No, SMV, SAM, IE Sewing SMV, Planning Uses (SMV), Remarks, Status, ACTION
- বাটন/অ্যাকশন: New style SMV, Export, Style No, SMV, SAM, IE Sewing SMV, Planning Uses (SMV), Remarks, Status, Edit

**ট্যাব: New style SMV**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Style No* | text | ✔ |  |
| SMV (sewing) | number |  |  |
| SAM (whole garment) | number |  |  |
| Remarks | text |  |  |
| Active | checkbox |  |  |
- সেকশন: New style SMV
- বাটন/অ্যাকশন: New style SMV, Reset, Save style SMV

**ডায়ালগ: New style SMV**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style No* | text | ✔ |  |
| SMV (sewing) | number |  |  |
| SAM (whole garment) | number |  |  |
| Remarks | text |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Enter the SMV, the SAM or both.

#### Other Setup

`/m/planning/planning-setup`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Default lead time of each activity a new master plan starts from | search |  | Search 7 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Activity, Lead Time (Days), Counted From, Remarks, Last Changed, ACTION
- বাটন/অ্যাকশন: Export, Activity, Lead Time (Days), Counted From, Remarks, Last Changed, Edit
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/Planning`, সব টেবিল `planning_` নামে। Machinery বাদে সবকিছু নতুন, এই নিয়ম মেনে আগের project-এর কোনো ব্যবসায়িক টেবিল (যেমন পুরনো Calendar মডিউল) ব্যবহার হয়নি।
- **ভিত্তি থেকে নেওয়া (Company Management):** factory (Unit), floor আর floor line (`production_lines`)। Planning line একটা floor-এ থাকে। আসল আউটপুট পড়ার জন্য একটা floor line যুক্ত করা যায়।
- **Merchandise Desk থেকে নেওয়া:** buyer-এর জন্য Merchandise Desk-এর নিজের `buyers` টেবিল (Efficiency Master-এ)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_01_000100_create_planning_masters`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `planning_weekly_holidays` | factory_id, weekday (0 রবি … 6 শনি) | factory + weekday |
| `planning_calendar_days` | factory_id, day, status (WORKING/WEEKLY_HOLIDAY/PUBLIC_HOLIDAY/FACTORY_HOLIDAY/EMERGENCY_HOLIDAY/SPECIAL_WORKING), reason, working_hours | factory + day |
| `planning_lines` | floor_id, floor_line_id, code, name, std_manpower, std_machines, std_hours, std_efficiency, best_fit, display_order, under_maintenance, active | company + floor + code |
| `planning_line_machines` | planning_line_id, machine_type, name_code, category (SEWING/SPECIAL/FINISHING/OTHER), installed, working, standby, capacity_per_hour, active | — |
| `planning_line_manpower` | planning_line_id, role (OPERATOR/HELPER/IRONMAN/QC/SUPERVISOR/OTHER), required, remarks | line + role |
| `planning_line_shifts` | planning_line_id, shift_name, start_time, end_time, break_minutes, manpower, target, active | line + shift_name |
| `planning_efficiency_standards` | applies_to (STYLE/BUYER/LINE/UNIT), factory_id, planning_line_id, buyer_id, style_no, efficiency, effective_from, remarks, active | — |
| `planning_style_smvs` | style_no, smv, sam, remarks, active | company + style_no |
| `planning_lead_times` | activity, lead_days, counted_from (BEFORE_CUTTING/BEFORE_FIRST_SEWING/AFTER_LAST_SEWING), remarks, sort_order, changed | company + activity |

## ৫. Workflow / Status ও Business Rules

- **Factory Calendar:**
  - কোনো দিন সাধারণভাবে কাজের দিন। তার বার যদি সাপ্তাহিক ছুটির তালিকায় থাকে, তাহলে সাপ্তাহিক ছুটি।
  - কোনো দিনের নিজস্ব অবস্থা থাকলে সেটাই প্রাধান্য পায়: সরকারি, ফ্যাক্টরি বা জরুরি ছুটি, বিশেষ কাজের দিন, বা নিজস্ব ঘণ্টার কাজের দিন। যেমন শুক্রবারকে বিশেষ কাজের দিন করা যায়।
  - কাজের দিন ছাড়া অন্য সব অবস্থায় কারণ লিখতে হয়।
  - সাধারণ বারে ঘণ্টা ছাড়া "কাজের দিন" বাছাই করলে সেই দিনের নিজস্ব অবস্থা মুছে যায়।
  - মাস্টার প্ল্যান আর দৈনিক প্ল্যান সব ছুটি বাদ দেবে, আর নিজস্ব ঘণ্টার দিনে সেই ঘণ্টায় প্ল্যান করবে (12/02 …)।
- **Production Line:**
  - কোড বড় হাতে রাখা হয়, আর একই floor-এ unique।
  - floor line শুধু সেই একই floor-এর হতে পারে।
  - এফিসিয়েন্সি ০ থেকে ১৫০%, ঘণ্টা ০.৫ থেকে ২৪। নতুন লাইনে ডিফল্ট ১০ ঘণ্টা আর ৬৫%।
  - লাইন মুছলে তার মেশিন, জনবল আর শিফটও মুছে যায়।
- **মেশিন:** চালু আর স্ট্যান্ডবাই মিলে বসানো মেশিনের বেশি হতে পারে না।
- **জনবল:** একটা লাইনে প্রতিটা ভূমিকার একটাই সারি।
- **শিফট:**
  - শেষের সময় শুরুর আগে হলে শিফট মধ্যরাত পেরিয়ে চলে (যেমন ২২:০০–০৬:০০)।
  - কাজের ঘণ্টা = সময়কাল − বিরতি। বিরতির পরে সময় না থাকলে শিফট সেভ হয় না।
- **Efficiency Master:**
  - প্রতিটা স্ট্যান্ডার্ড শুধু যার জন্য সেটাই রাখে (STYLE হলে স্টাইল নং, UNIT হলে ইউনিট …)। বাকি ঘরে কিছু পাঠালেও মুছে যায়।
  - সবচেয়ে নির্দিষ্ট স্ট্যান্ডার্ড প্রাধান্য পায়: স্টাইল, তারপর বায়ার, তারপর লাইন, তারপর ইউনিট। এই খোঁজাটা হবে মাস্টার প্ল্যান তৈরির সময়।
  - এফিসিয়েন্সি ০–১৫০%-এর বাইরে হলে Brickland-এর মেসেজ আসে।
- **SMV / SAM:**
  - SMV, SAM বা দুটোই লাগে।
  - স্টাইল নং বড় হাতে রাখা হয় আর unique।
  - প্ল্যানিং কোন মান ব্যবহার করবে: Work Study (14) থেকে IE-র sewing SMV থাকলে সেটা, না হলে এই সারির SMV, SMV না থাকলে SAM।
- **Other Setup:**
  - ৭টা কাজ (Fabric Booking … Packing) স্থির: নতুন যোগ বা মোছা যায় না, নামও বদলায় না।
  - শুধু দিন, কোথা থেকে গোনা আর মন্তব্য বদলানো যায়। বদলালে "Last Changed" কলামে "বদলানো" দেখায়।
  - শুরুর মান Brickland-এর মতো।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{planning-lines|planning-line-machines|planning-line-manpower|planning-line-shifts|planning-efficiencies|planning-style-smvs|planning-lead-times}`। লাইনের নিচের তিনটা তালিকা `?filter[planning_line_id]=` দিয়ে ছাঁকা যায়।
- `GET /planning/lookups`: ফর্মের জন্য ইউনিট, "FAC>>FLR" নামের floor, floor line, planning line আর buyer, শুধু planning পারমিশন দিয়েই।
- Factory Calendar:
  - `GET /planning/calendar?factory_id&year&month`: মাসের দিন, অবস্থা আর প্রতিটা অবস্থার সংখ্যা।
  - `PUT /planning/calendar/{factory}/day`: একটা দিন ঠিক করা।
  - `GET/PUT /planning/calendar/{factory}/weekly`: সাপ্তাহিক ছুটির বার।
- **পারমিশন:** নতুন `planning.master.view` আর `planning.master.manage`।
  - manage পায় FACTORY_ADMIN আর FACTORY_MANAGER।
  - view পায় LINE_CHIEF আর read-only রোল।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/capacity-planning/<screen>`। সাইডবারে PLANNING → Capacity Planning → MASTER DATA।
- **Production Plan ধাপের বার:** Factory Calendar আর Production Line-এ Brickland-এর "Production Plan · Step N of 6" বার আছে, Previous আর Next সহ। যে ধাপের স্ক্রিন এখনো তৈরি হয়নি (Projections, Master Plan, Daily Plan, Plan vs Actual), সেটা দেখায় কিন্তু লিংক হয় না।
- **Factory Calendar:**
  - ইউনিট, বছর আর মাস বাছাই করা যায় (url-এ থাকে)।
  - সাপ্তাহিক ছুটি আর ছুটি যোগের ডায়ালগ।
  - প্রতিটা অবস্থার সংখ্যার চিপ।
  - শনি থেকে শুক্রবারের ক্যালেন্ডার, বা তালিকা হিসেবে দেখা যায়। যেকোনো দিনে চাপলে "Set …" ডায়ালগ খোলে।
- **Production Line, Efficiency Master, SMV / SAM Master:** Records | New, Brickland-এর বিবরণ-লাইনসহ।
- **Machine, Manpower আর Shift Master:** লাইনের ফিল্টার, New ডায়ালগ, আর Floor, Line, Planning Line কলামসহ তালিকা।
- **Other Setup:** শুধু Edit।
- **কিট:** `EntryForm`-এ `time` ঘর।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Planning/Database/Migrations/2026_11_01_000100_create_planning_masters.php`
- `app/Modules/Planning/Models/PlanningWeeklyHoliday.php`
- `app/Modules/Planning/Models/PlanningCalendarDay.php`
- `app/Modules/Planning/Models/PlanningLine.php`
- `app/Modules/Planning/Models/PlanningLineMachine.php`
- `app/Modules/Planning/Models/PlanningLineManpower.php`
- `app/Modules/Planning/Models/PlanningLineShift.php`
- `app/Modules/Planning/Models/PlanningEfficiencyStandard.php`
- `app/Modules/Planning/Models/PlanningStyleSmv.php`
- `app/Modules/Planning/Models/PlanningLeadTime.php`
- `app/Modules/Planning/Support/PlanningDefaults.php`
- `app/Modules/Planning/Support/PlanningLabels.php`
- `app/Modules/Planning/Resources/PlanningMasterResource.php`
- `app/Modules/Planning/Resources/PlanningLineResource.php`
- `app/Modules/Planning/Resources/LineMachineResource.php`
- `app/Modules/Planning/Resources/LineManpowerResource.php`
- `app/Modules/Planning/Resources/LineShiftResource.php`
- `app/Modules/Planning/Resources/EfficiencyStandardResource.php`
- `app/Modules/Planning/Resources/StyleSmvResource.php`
- `app/Modules/Planning/Resources/LeadTimeResource.php`
- `app/Modules/Planning/Http/Controllers/Api/PlanningCalendarApiController.php`
- `app/Modules/Planning/Http/Controllers/Api/PlanningLookupApiController.php`
- `app/Modules/Planning/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/capacity-planning/[screen]/page.js`
- `frontend/src/app/(app)/capacity-planning/[screen]/screens.js`
- `frontend/src/app/(app)/capacity-planning/[screen]/actions.js`
- `frontend/src/components/planning/plan-steps.jsx`
- `frontend/src/components/planning/factory-calendar.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/planning.php`
- `lang/bn/planning.php`

**Tests**
- `tests/Feature/Planning/PlanningMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `PlanningMasterTest` ৪/৪:
  - **ক্যালেন্ডার:** সাপ্তাহিক ছুটি শুক্রবার করলে অক্টোবর ২০২৬-এ ৫টা সাপ্তাহিক ছুটি আসে। কারণ ছাড়া ছুটি দেওয়া যায় না। বিশেষ কাজের দিন একটা শুক্রবারকে বদলে দেয়। "কাজের দিন" দিলে নিজস্ব অবস্থা মুছে যায়। নিজস্ব ঘণ্টা সেভ হয়। অন্য কোম্পানির ফ্যাক্টরিতে 404।
  - **লাইন:** floor ছাড়া Brickland-এর মেসেজ আসে। ইউনিট, floor আর floor line-এর লেবেল ঠিক আসে। একই floor-এ একই কোড চলে না। ১৫০%-এর বেশি এফিসিয়েন্সি চলে না। lookup-এ "FAC01>>FLR-001" আসে।
  - **মেশিন, জনবল, শিফট:** বসানোর চেয়ে বেশি মেশিন চলে না। একই ভূমিকা দুবার দেওয়া যায় না। রাতের শিফটে ৭.৫ ঘণ্টা আর দিনের শিফটে ৮ ঘণ্টা আসে। বিরতি পুরো শিফটের সমান হলে চলে না। লাইন মুছলে শিফটও মুছে যায়।
  - **Efficiency, SMV, লিড টাইম:** ইউনিটের স্ট্যান্ডার্ড শুধু ইউনিট রাখে। ১৫১% দিলে Brickland-এর মেসেজ আসে। SMV বা SAM লাগে। স্টাইল নং unique। ৭টা লিড টাইম আসে। বদলালে "বদলানো" হয়। নতুন যোগ, নাম বদল আর মোছা চলে না।
  - **পারমিশন:** লাইন চিফ দেখতে পারে, বদলাতে পারে না। ফ্যাক্টরি ম্যানেজার বদলাতে পারে। টেকনিশিয়ান কিছুই পায় না।
- ব্রাউজার (dev):
  - শুক্রবার সাপ্তাহিক ছুটি করা হয়েছে, ২১ অক্টোবর Durga Puja (সরকারি ছুটি)। ক্যালেন্ডার আর তালিকা দুটোই দেখা হয়েছে।
  - LINE01 তৈরি করা হয়েছে, তাতে একটা SNLS মেশিন আর একটা দিনের শিফট।
  - লাইনের এফিসিয়েন্সি স্ট্যান্ডার্ড ৬৮% আর একটা স্টাইল SMV যোগ করা হয়েছে।
  - Other Setup-এ ৭টা কাজ এসেছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **Machine, Manpower আর Shift Master:** Brickland-এ এগুলো "Line Master যেভাবে রাখে" তা দেখায়। মানে ডাটা আসে অন্য মডিউলের একটা Line Master থেকে, যেটা এখনো তৈরি হয়নি, আর Brickland-এর demo-তে তালিকাগুলো ফাঁকা। এখানে এগুলোর নিজস্ব তালিকা, New আর Edit সহ, planning line-এর সাথে যুক্ত। Assembly Floor (20) বা Work Study (14) তৈরির সময় দেখা হবে, ডাটা সেখান থেকে আসবে কিনা।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- আটটা স্ক্রিনই তৈরি। Factory Calendar আর Production Line, Brickland-এর Production Plan ধাপের প্রথম দুটো।
- **শুরুর ডাটা:** শুধু Other Setup-এর ৭টা লিড টাইম। সাপ্তাহিক ছুটি নিজে থেকে বসানো হয় না, কারণ Brickland-এর demo-তেও শুক্রবারগুলো আলাদা করে দেওয়া। কোম্পানি Weekly Holiday থেকে নিজে ঠিক করবে।
- লাইনের লেবেল (ইউনিট, floor, floor line) প্রতি অনুরোধে একবারেই পড়া হয় (`PlanningLabels`), প্রতিটা সারির জন্য আলাদা কোয়েরি হয় না।

