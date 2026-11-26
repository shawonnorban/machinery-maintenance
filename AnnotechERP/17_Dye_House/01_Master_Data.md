# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `17/01`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dye Method Master | `/m/dyeing/dyeing-method-setup` | 2 | 7 | 1 |
| 2 | Dye Machine Profile | `/m/dyeing/dyeing-machine-profile` | 3 | 25 | 1 |
| 3 | Finishing & Quality Owner Setup | `/m/dyeing/dyeing-fin-and-g-quality-resp-emploee` | 2 | 5 | 1 |
| 4 | Dye Chemical Audit Recipe | `/m/dyeing/dyes-chemical-audit-recipe-setup` | 1 | 1 | 0 |
| 5 | Colour Group Rate Update | `/m/dyeing/color-group-wise-rate-update` | 1 | 0 | 1 |
| 6 | Dye Sub-Contract Party Rate | `/m/dyeing/dyeing-sc-party-rate` | 1 | 11 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dye Method Master

`/m/dyeing/dyeing-method-setup`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Every dyeing method a batch may be run under — whether it is single or do | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Dye Method, S/D?, Default?, Active?, ACTION
- বাটন/অ্যাকশন: Add New, Export, Dye Method, S/D?, Default?, Active?, Edit

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dyeing Method Name* | text | ✔ | Normal Wash |
| Dyeing Method SName* | text | ✔ | N/Wash |
| Is S/D?* | select | ✔ | Select…, Single, Double |
| Is Default? Proposed on a new batch. Ticking it here clears it from whichever me | checkbox |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New dyeing method
- বাটন/অ্যাকশন: Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Dyeing Method Name* | text | ✔ |  |
| Dyeing Method SName* | text | ✔ |  |
| Is S/D?* | select | ✔ | Select…, Single, Double |
| Is Default? Proposed on a new batch. Ticking it here clears it from whichever me | checkbox |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Dyeing Method Name is required.

#### Dye Machine Profile

`/m/dyeing/dyeing-machine-profile`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine No | search |  | MFL-01 |
| Machine Type | select |  | All |
| Brand | select |  | All |
| M/C State | select |  | All, FREE, BUSY, UNDER MAINTENANCE, BREAKDOWN, RESERVED |
| Status | select |  | All, Active, In-Active |
| Search Every dyeing machine on the floor — its capacity, nozzles, make and origi | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine No, Model, Capacity, Efficency, Serial, Year, RPM, Nozle, Type, Brand, Country, Unit, Location, M/C State, ACTION
- বাটন/অ্যাকশন: Machine Reference Lists, New dye machine, Clear, Export, Machine No, Model, Capacity, Efficency, Serial, Year, RPM, Nozle, Type, Brand, Country, Unit, Location, M/C State

**ট্যাব: Machine Reference Lists**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search lists | search |  | Search… |
- সেকশন: List, Entries
- বাটন/অ্যাকশন: Machine Reference Lists, New dye machine, Machine Type machine type list 6, Brand brand list 5, Location location list 4

**ট্যাব: New dye machine**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine No* | text | ✔ | MFL-01 |
| Model # | text |  | FC28 |
| Machine Type* | select | ✔ | High Pressure, Atmospheric, Soft Flow, Sample Dyeing, Winch |
| Brand* | select | ✔ | Fongs, PMM Moon star, Thies, Sclavos, Dilmenler |
| Location | select |  | Dyeing Shed-1, Dyeing Shed-2, Sample House, Finishing Bay |
| Capacity | number |  | 1250 |
| Efficency | number |  | 85 |
| Status* | select | ✔ | Active, In-Active |
| SL No* | text | ✔ | 01 |
| Year | number |  | 2010 |
| RPM | number |  | 0 |
| Temp | number |  | 135 |
| Nozle | number |  | 4 |
| Sample? Tick where sample batches may be run on it. | checkbox |  |  |
| Prod.Floor1* | select | ✔ |  |
| M/C Version* | text | ✔ | 0 |
| Prod. Status* | select | ✔ | FREE, BUSY, UNDER MAINTENANCE, BREAKDOWN, RESERVED |
- সেকশন: New dye machine
- বাটন/অ্যাকশন: Machine Reference Lists, New dye machine, -- Select Country --, -- Select MoU --, Reset, Save dye machine

**ডায়ালগ: New dye machine**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine No* | text | ✔ |  |
| Model # | text |  |  |
| Machine Type* | select | ✔ | High Pressure, Atmospheric, Soft Flow, Sample Dyeing, Winch |
| Brand* | select | ✔ | Fongs, PMM Moon star, Thies, Sclavos, Dilmenler |
| Location | select |  | Dyeing Shed-1, Dyeing Shed-2, Sample House, Finishing Bay |
| Capacity | number |  |  |
| Efficency | number |  |  |
| Status* | select | ✔ | Active, In-Active |
| SL No* | text | ✔ |  |
| Year | number |  |  |
| RPM | number |  |  |
| Temp | number |  |  |
| Nozle | number |  |  |
| Sample? Tick where sample batches may be run on it. | checkbox |  |  |
| Prod.Floor1* | select | ✔ |  |
| M/C Version* | text | ✔ |  |
| Prod. Status* | select | ✔ | FREE, BUSY, UNDER MAINTENANCE, BREAKDOWN, RESERVED |
- খালি Save-এ মেসেজ: Machine Type is required.

#### Finishing & Quality Owner Setup

`/m/dyeing/dyeing-fin-and-g-quality-resp-emploee`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New responsible employee

**ট্যাব: New responsible employee**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Employee Type* | select | ✔ | DF Process Operator, DF Quality Inspection, GFab Process Operator, GFab Quality Inspection |
| Employee Name | text |  |  |
| Department | text |  |  |
| Designation | text |  |  |
| Pass Code* | text | ✔ |  |
- সেকশন: New responsible employee
- বাটন/অ্যাকশন: New responsible employee, -- Select Employee --, Reset, Save responsible employee

**ডায়ালগ: New responsible employee**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Employee Type* | select | ✔ | DF Process Operator, DF Quality Inspection, GFab Process Operator, GFab Quality Inspection |
| Employee Name | text |  |  |
| Department | text |  |  |
| Designation | text |  |  |
| Pass Code* | text | ✔ |  |
- খালি Save-এ মেসেজ: Employee Type is required.

#### Dye Chemical Audit Recipe

`/m/dyeing/dyes-chemical-audit-recipe-setup`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | LPP (LPP) · 0 checks |
- সেকশন: Buyer Audit Item
- বাটন/অ্যাকশন: Load

#### Colour Group Rate Update

`/m/dyeing/color-group-wise-rate-update`

**ভিউ**
- গ্রিড কলাম: Color Code, Color Name, Is LabDip Req?, Water Cost, Wash Cost, Display Order, Is Active?
- সেকশন: Dyeing Color Group
- বাটন/অ্যাকশন: Add colour group, Revert, Save

**ডায়ালগ: Add colour group** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Color Code* | text | ✔ |  |
| Color Name* | text | ✔ |  |
| Is LabDip Req? | checkbox |  |  |
| Water Cost | number |  |  |
| Wash Cost | number |  |  |
| Display Order | number |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Color Code is required.

#### Dye Sub-Contract Party Rate

`/m/dyeing/dyeing-sc-party-rate`

**ট্যাব: S/C Party Wise Rate Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Rate Group List | search |  | search… |
| Existing quotation | select |  |  |
| Rate Group | text |  | Pick one on the left |
| Quatation# | text |  | Left blank, the server issues one |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  | Signed rate sheet, 14-Aug-2026 |
- গ্রিড কলাম: Rate Group
- সেকশন: Rate Group List, S/C Party Wise Rate Entry, Quatation Rate Detail, Selected Docs
- বাটন/অ্যাকশন: S/C Party Wise Rate Entry, Knitting, Yarn Dyeing, Dyeing, Dyeing Finishing, AOP, Yarn Twist, -- Select --, Add, Cancel, Attach, Clear, Save quotation

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Rate Group List | search |  |  |
| Existing quotation | select |  |  |
| Rate Group | text |  |  |
| Quatation# | text |  |  |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  |  |
- খালি Save-এ মেসেজ: Pick a rate group on the left before saving — a quotation is always for one group.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/DyeHouse`, সব টেবিল `dye_` নামে। Machinery-র asset বা পুরনো কোনো টেবিল ব্যবহার হয়নি। ডাই মেশিন Dye House-এর নিজের, Machinery-র asset নয়।
- **অন্য ERP মডিউল থেকে নেওয়া:**
  - Company Management-এর floor (প্রোডাকশন ফ্লোর)।
  - Merchandise Desk-এর উৎস দেশ, buyer আর Inquiry Rate Chart-এর রেট লাইন (সাব-কন্ট্রাক্ট রেট হেড)।
  - Material Procurement-এর supplier (S/C পার্টি)।
  - Settings-এর currency।
- Fabric Requirement (13/01)-এর Dye Machine Route Master এখানকার ডাই মেশিনের ওপর নির্ভর করে। তাই Dye House-এর Master Data আগে তৈরি হলো।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_02_000100_create_dye_house_masters`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `dye_methods` | name, short_name, sd (SINGLE/DOUBLE), is_default, active | company + name |
| `dye_machine_lists` | list (TYPE/BRAND/LOCATION), name, sort_order, active | company + list + name |
| `dye_machines` | machine_no, model, machine_type_id, brand_id, location_id, origin_country_id, capacity, capacity_unit (KG/LBS), efficiency, sl_no, year, rpm, temperature, nozzles, is_sample, floor_id, mc_version, prod_status (FREE/BUSY/UNDER_MAINTENANCE/BREAKDOWN/RESERVED), active | company + machine_no |
| `dye_responsible_employees` | employee_type (DF/GFab × Process Operator/Quality Inspection), user_id, employee_name, department, designation, pass_code_hash | company + pass_code_hash; company + type + user |
| `dye_audit_checks` | buyer_id, item_name, alter_item_name, dropped, sort_order | buyer + item_name |
| `dye_colour_groups` | code, name, labdip_required, water_cost, wash_cost, display_order, active | company + code |
| `dye_sc_quotations` | quotation_no, rate_group (KNITTING/YARN_DYEING/DYEING/DYEING_FINISHING/AOP/YARN_TWIST), supplier_id, quotation_date, currency, status (DRAFT … CANCELLED), remarks, document | company + quotation_no |
| `dye_sc_quotation_lines` | quotation_id, rate_line_id, head, uom, rate | — |

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা:** প্রথমবার খুললে Brickland-এর তালিকা তৈরি হয়, শুধু খালি তালিকায়।
  - ৮টা ডাইং মেথড (Normal Wash নিষ্ক্রিয়)।
  - ৫টা মেশিনের ধরন, ৫টা ব্র্যান্ড আর ৪টা লোকেশন।
  - ১০টা কালার গ্রুপ, Brickland-এর কোড, ক্রম আর ল্যাব ডিপ চিহ্নসহ।
- **ডাইং মেথড:** নাম unique। একটাই ডিফল্ট থাকে, নতুনটাকে টিক দিলে আগেরটা সরে যায়। মোছা যায় না।
- **ডাই মেশিন:**
  - মেশিন নং বড় হাতে রাখা হয় আর unique।
  - ধরন, ব্র্যান্ড আর লোকেশন শুধু সঠিক তালিকা থেকে নেওয়া যায় (ব্র্যান্ডকে ধরন হিসেবে দেওয়া যায় না)।
  - উৎস দেশ আর প্রোডাকশন ফ্লোর বাধ্যতামূলক।
  - Status মানে খাতায় আছে কিনা, Prod. Status মানে এখন কী করছে।
  - মেশিন মোছা যায় না।
  - কোনো মেশিন যে তালিকা-এন্ট্রি ব্যবহার করে, সেটা মোছা যায় না।
- **দায়িত্বপ্রাপ্ত কর্মী:**
  - পাস কোড ৪ থেকে ৬ অঙ্কের।
  - কোড রাখা হয় শুধু কোম্পানি-ভিত্তিক keyed hash (HMAC-SHA256) হিসেবে, কখনো দেখানো বা ফেরত পাঠানো হয় না।
  - দুজনের একই কোড হতে পারে না।
  - সম্পাদনার সময় কোড ফাঁকা রাখলে আগের কোডই থাকে।
  - ব্যক্তি হলেন কোম্পানির একজন সক্রিয় ইউজার। HR & Payroll তৈরি হলে কর্মী তালিকা আসবে।
- **অডিট যাচাই:** বায়ার ধরে পুরো তালিকা একসাথে সেভ হয়: নাম বদল, বাদ চিহ্ন, যোগ আর বাদ দেওয়া। একই আইটেম দুবার থাকতে পারে না। বায়ারের পাশে শুধু বাদ-না-দেওয়া যাচাইয়ের সংখ্যা দেখায়।
- **কালার গ্রুপ:** গ্রিড একসাথে সেভ হয়। একই সেভে দুই সারির কোড অদলবদল করা যায়, কিন্তু একই কোড দুবার থাকতে পারে না। নতুন গ্রুপ যোগ হয় ডায়ালগ থেকে।
- **সাব-কন্ট্রাক্ট কোটেশন:**
  - একটা কোটেশন একটা রেট গ্রুপের।
  - হেড আসে Inquiry Rate Chart-এ সেই গ্রুপের লাইন থেকে। Yarn Twist-এর কোনো লাইন নেই, তাই সেখানে হেড লিখে দিতে হয়।
  - অন্য গ্রুপের হেড বা একই হেড দুবার চলে না। অন্তত একটা লাইন লাগে।
  - Auto থাকলে নম্বর দেওয়া হয় `DSQ-<বছর>-<0001>`।
  - হেডার আর লাইন একসাথে সেভ হয়।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{dye-methods|dye-machine-lists|dye-machines|dye-responsible-employees|dye-colour-groups}`।
- `GET /dyehouse/lookups`: উৎস দেশ, ফ্লোর, ইউজার, বায়ার, সাপ্লায়ার আর মুদ্রা।
  - কোম্পানি Settings-এ কোনো মুদ্রা না দিলে সিস্টেমের সব মুদ্রা কোড দেখায়।
- `PUT /dyehouse/colour-groups`: পুরো গ্রিড একসাথে।
- `GET /dyehouse/audit-buyers`, `GET/PUT /dyehouse/buyers/{buyer}/audit-checks`।
- `GET /dyehouse/sc-quotations`, `GET /dyehouse/sc-quotations/heads?group=`, `POST /dyehouse/sc-quotations`, `GET/PUT /dyehouse/sc-quotations/{id}`।
- **পারমিশন:** নতুন `dyehouse.master.view` আর `dyehouse.master.manage`।
  - manage পায় FACTORY_ADMIN আর FACTORY_MANAGER।
  - view পায় STORE_MANAGER আর read-only রোল।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/dye-house/<screen>`। সাইডবারে PRODUCTION → Dye House → MASTER DATA।
- **Dye Method Master আর Finishing & Quality Owner Setup:** Records | New।
- **Dye Machine Profile:** Records (ধরন, ব্র্যান্ড, মেশিনের অবস্থা আর Status-এর ফিল্টারসহ) | Machine Reference Lists (বাঁয়ে তিনটা তালিকা, ডানে এন্ট্রি, New entry ডায়ালগ) | New dye machine।
- **Dye Chemical Audit Recipe:** বায়ার বাছাই করে Load, তারপর যাচাইয়ের তালিকা, Add check আর Update।
- **Colour Group Rate Update:** সম্পাদনাযোগ্য গ্রিড, Add colour group ডায়ালগ, Revert আর Save।
- **Dye Sub-Contract Party Rate:**
  - Records: কোটেশনের তালিকা, প্রতিটায় Open লিংক।
  - S/C Party Wise Rate Entry: বাঁয়ে রেট গ্রুপ, ডানে হেডার আর রেটের লাইন।
- **কিট:**
  - `RecordsGrid`-এ `link` কলাম।
  - `EntryForm`-এ `hidden` ঘর।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2026_11_02_000100_create_dye_house_masters.php`
- `app/Modules/DyeHouse/Models/DyeMethod.php`
- `app/Modules/DyeHouse/Models/DyeMachineList.php`
- `app/Modules/DyeHouse/Models/DyeMachine.php`
- `app/Modules/DyeHouse/Models/DyeResponsibleEmployee.php`
- `app/Modules/DyeHouse/Models/DyeAuditCheck.php`
- `app/Modules/DyeHouse/Models/DyeColourGroup.php`
- `app/Modules/DyeHouse/Models/DyeScQuotation.php`
- `app/Modules/DyeHouse/Models/DyeScQuotationLine.php`
- `app/Modules/DyeHouse/Support/DyeHouseDefaults.php`
- `app/Modules/DyeHouse/Resources/DyeMasterResource.php`
- `app/Modules/DyeHouse/Resources/DyeMethodResource.php`
- `app/Modules/DyeHouse/Resources/DyeMachineListResource.php`
- `app/Modules/DyeHouse/Resources/DyeMachineResource.php`
- `app/Modules/DyeHouse/Resources/ResponsibleEmployeeResource.php`
- `app/Modules/DyeHouse/Resources/DyeColourGroupResource.php`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeHouseApiController.php`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeScQuotationApiController.php`
- `app/Modules/DyeHouse/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/page.js`
- `frontend/src/app/(app)/dye-house/[screen]/screens.js`
- `frontend/src/app/(app)/dye-house/[screen]/actions.js`
- `frontend/src/components/dyehouse/colour-group-grid.jsx`
- `frontend/src/components/dyehouse/audit-checks.jsx`
- `frontend/src/components/dyehouse/sc-quotation-editor.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/dyehouse.php`
- `lang/bn/dyehouse.php`
- `lang/en/erp.php`
- `lang/bn/erp.php`

**Tests**
- `tests/Feature/DyeHouse/DyeHouseMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `DyeHouseMasterTest` ৬/৬:
  - **ডাইং মেথড:** ৮টা দিয়ে শুরু হয়। ডিফল্ট একটাই থাকে। খালি নামে Brickland-এর মেসেজ আসে। একই নাম দুবার চলে না।
  - **ডাই মেশিন:** ১৪টা তালিকা-এন্ট্রি দিয়ে শুরু হয়। ব্র্যান্ডকে ধরন হিসেবে দেওয়া যায় না। ধরন ছাড়া Brickland-এর মেসেজ আসে। ধরন, ব্র্যান্ড, দেশ আর ফ্লোরের লেবেল ঠিক আসে। একই মেশিন নং চলে না। ব্যবহৃত তালিকা-এন্ট্রি মোছা যায় না, অব্যবহৃতটা যায়।
  - **পাস কোড:** API-তে কখনো আসে না, hash হিসেবে রাখা হয়। একই কোড দুজনের হতে পারে না। সম্পাদনায় ফাঁকা রাখলে কোড থাকে। অন্য কোম্পানির ইউজার দেওয়া যায় না।
  - **অডিট আর কালার গ্রুপ:** একই আইটেম দুবার চলে না। পুরো তালিকা একসাথে বদলায়। কোড অদলবদল চলে, একই কোড দুবার চলে না।
  - **সাব-কন্ট্রাক্ট কোটেশন:** লাইন ছাড়া বা গ্রুপ ছাড়া সেভ হয় না। নম্বর DSQ-বছর-0001 আর 0002। অন্য গ্রুপের হেড বা একই হেড দুবার চলে না। সম্পাদনায় অবস্থা আর লাইন বদলায়, নম্বর একই থাকে। Yarn Twist-এ লেখা হেড চলে।
  - **পারমিশন:** স্টোর ম্যানেজার দেখতে পারে, বদলাতে পারে না। ফ্যাক্টরি ম্যানেজার বদলাতে পারে। টেকনিশিয়ান কিছুই পায় না।
- ব্রাউজার (dev):
  - ৮টা মেথড দেখা গেছে।
  - Country & Origin Master-এ China যোগ করার পর MFL-01 (Soft Flow, Fongs, China) তৈরি হয়েছে। Brand তালিকা খোলা হয়েছে।
  - DF Process Operator যোগ করা হয়েছে।
  - LPP-র অডিটে "Azo dyes" যোগ করা হয়েছে।
  - White-এর পানির খরচ ১২.৫ করা হয়েছে।
  - Dyeing গ্রুপে KOYO-র কোটেশন DSQ-2026-0001 সেভ হয়েছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **ডাই মেশিনের উৎস দেশ:** এটা Merchandise Desk-এর Country & Origin Master থেকে আসে। সেই তালিকা ফাঁকা থাকলে মেশিন যোগ করা যায় না। Brickland-এর demo-তে সেখানে ২৮টা দেশ ছিল।
- **দায়িত্বপ্রাপ্ত কর্মী:** HR & Payroll তৈরি হলে "Responsible Employee" কর্মী তালিকা থেকে আসবে, আর বিভাগ ও পদবি নিজে থেকে বসবে।
- **Finishing & Quality Owner আর In-House Sub-Contract Party Rate:** In-House Sub-Contract Party Rate (S/C IN HOUSE ফোল্ডার) এই মাস্টারের পাশের স্ক্রিন। এটা তৈরি হবে S/C IN HOUSE গ্রুপের সাথে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- Fabric Requirement-এর Master Data-র Dye Machine Route Master ডাই মেশিনের ওপর নির্ভর করে। তাই index-এর ক্রম বদলে Dye House-এর Master Data আগে তৈরি হলো।
- **পথে ধরা বাগ:**
  - কালার গ্রুপের কোড অদলবদলের সময় যে সাময়িক মান বসানো হয়, সেটা ১৬ অক্ষরের কলামে আঁটছিল না। এখন `'~' || right(id, 15)`।
  - লেখা হেডের লাইনে `rate_line_id` key না থাকায় 500 হচ্ছিল। এখন সেটা ঐচ্ছিক।
- কোটেশন সেভের ঠিক পরে "Existing quotation" select-এ কাঁচা id দেখাচ্ছিল, কারণ পাতার তালিকা তখনো নতুন হয়নি। এখন ফর্মের কোটেশনটাও অপশনে থাকে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- Dye Machine Profile: Records ফিল্টারে "Machine No" টেক্সট বক্স যোগ হয়েছে; লেবেল Brickland-এর মতো (গ্রিডে "Model", "Efficency"; ফর্মে "Country of Origine", "Temp", "Prod.Floor1")। ফিল্টার তাৎক্ষণিক, শুধু Clear।
- Sub-Contract Party Rate এডিটর Knit Production-এর সাথে একসাথে বদলেছে (Rate Detail-এর এন্ট্রি সারি, Selected Docs-এ একাধিক ডকুমেন্ট)।
- বাকি পার্থক্য: Status ফর্মে এখনো চেকবক্স (Brickland-এ select)।
