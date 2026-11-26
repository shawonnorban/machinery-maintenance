# Organization

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Company Management](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `01/03`

**⬅ ফিরে যান:** [Company Management](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Department | `/m/company-management/company-department` | 6 | 0 | 1 |
| 2 | Section | `/m/company-management/company-section` | 6 | 0 | 1 |
| 3 | Line Master | `/m/company-management/company-line` | 1 | 1 | 0 |
| 4 | Workstation | `/m/company-management/company-workstation` | 6 | 0 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Department

`/m/company-management/company-department`

**ট্যাব: Department**
- গ্রিড কলাম: SL, Code, Name, Department Type, Sections, Status, Status, Action
- সেকশন: Departments
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Department, Revert, Save

**ট্যাব: Company Profile**
- গ্রিড কলাম: Company Code, Company Name, Address, Contact Number, Email, Website, Logo (link), Trade License, TIN, BIN, Registration No, Registration Date, Status
- সেকশন: Company
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Factory**
- গ্রিড কলাম: Factory Code, Factory Name, Address, Contact, Established Date, Factory Area (sqft), Status, Remarks
- সেকশন: Factory
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Building**
- গ্রিড কলাম: SL, Building Code, Building Name, Factory, Number of Floors, Floors Registered, Status, Remarks, Status, Action
- সেকশন: Buildings
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Building, Revert, Save

**ট্যাব: Floor**
- গ্রিড কলাম: SL, Floor Code, Floor Name / Number, Building, Factory, Floor Capacity, Status, Status, Action
- সেকশন: Floors
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Floor, Revert, Save

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

**ডায়ালগ: Add Department** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code | text |  |  |
| Name* | text | ✔ |  |
| Department Type | select |  | Select…, Production, Non-Production |
| Status | select |  | Select…, Active, Inactive |
- খালি Save-এ মেসেজ: Name is required.

#### Section

`/m/company-management/company-section`

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

**ট্যাব: Company Profile**
- গ্রিড কলাম: Company Code, Company Name, Address, Contact Number, Email, Website, Logo (link), Trade License, TIN, BIN, Registration No, Registration Date, Status
- সেকশন: Company
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Factory**
- গ্রিড কলাম: Factory Code, Factory Name, Address, Contact, Established Date, Factory Area (sqft), Status, Remarks
- সেকশন: Factory
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Building**
- গ্রিড কলাম: SL, Building Code, Building Name, Factory, Number of Floors, Floors Registered, Status, Remarks, Status, Action
- সেকশন: Buildings
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Building, Revert, Save

**ট্যাব: Floor**
- গ্রিড কলাম: SL, Floor Code, Floor Name / Number, Building, Factory, Floor Capacity, Status, Status, Action
- সেকশন: Floors
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Floor, Revert, Save

**ট্যাব: Department**
- গ্রিড কলাম: SL, Code, Name, Department Type, Sections, Status, Status, Action
- সেকশন: Departments
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Department, Revert, Save

**ডায়ালগ: Add Section** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Section Code | text |  |  |
| Section Name* | text | ✔ |  |
| Floor | select |  | Select…, BCL › Finishing (FINISHING), BCL › SWEING (FLR-001), BCL › Cuting Floor (FLR-002), BCL › Finishing (FLR-004), B |
| Status | select |  | Select…, Active, Inactive |
- খালি Save-এ মেসেজ: Department is required.

#### Line Master

`/m/company-management/company-line` — মেনুতে: Line

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search lines | text |  | Search code, name, floor… |
- গ্রিড কলাম: CODE, LINE, TYPE, FACTORY, FLOOR, SECTION, MANPOWER, EMPLOYEES, MACHINES, ALLOCATED (AVAIL. / MAINT.), CAPACITY/DAY, TARGET/DAY, STATUS
- সেকশন: Production lines

#### Workstation

`/m/company-management/company-workstation`

**ট্যাব: Workstation**
- সেকশন: Workstations
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Add Workstation, Revert, Save

**ট্যাব: Company Profile**
- গ্রিড কলাম: Company Code, Company Name, Address, Contact Number, Email, Website, Logo (link), Trade License, TIN, BIN, Registration No, Registration Date, Status
- সেকশন: Company
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Factory**
- গ্রিড কলাম: Factory Code, Factory Name, Address, Contact, Established Date, Factory Area (sqft), Status, Remarks
- সেকশন: Factory
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Building**
- গ্রিড কলাম: SL, Building Code, Building Name, Factory, Number of Floors, Floors Registered, Status, Remarks, Status, Action
- সেকশন: Buildings
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Building, Revert, Save

**ট্যাব: Floor**
- গ্রিড কলাম: SL, Floor Code, Floor Name / Number, Building, Factory, Floor Capacity, Status, Status, Action
- সেকশন: Floors
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Floor, Revert, Save

**ট্যাব: Department**
- গ্রিড কলাম: SL, Code, Name, Department Type, Sections, Status, Status, Action
- সেকশন: Departments
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Department, Revert, Save

**ডায়ালগ: Add Workstation** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code | text |  |  |
| Name* | text | ✔ |  |
| Status | select |  | Select…, Active, Inactive |
- খালি Save-এ মেসেজ: Line is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `departments`, `sections`, `production_lines`, `workstations` (Tenancy)। Production line মেইনটেন্যান্স রিপোর্ট আর লাইন চিফ রোলে ব্যবহৃত হয়।

## ৪. ডাটা মডেল (Entity ও Field)

- **`departments`** + `department_type` (`PRODUCTION`/`NON_PRODUCTION`), `status`। "Sections" কলাম গুনে বের করা হয়।
- **`sections`** + `floor_id` (nullable FK), `status`। "Employees" কলাম HR আসার পরে গোনা হবে।
- **`production_lines`** + `line_type` (বাধ্যতামূলক: `SEWING`/`CUTTING`/`FINISHING`/`QUALITY`/`SAMPLE`/`SPECIAL`, Brickland-এর অপশন থেকে নিশ্চিত), `factory_id` (derive বা স্টোর), `floor_id`, `manpower` (int), `capacity_per_day`, `target_per_day`, `status`। Line Master গ্রিডের MACHINES আর ALLOCATED (AVAIL./MAINT.) কলাম `asset_locations` আর asset status থেকে হিসাব হয়, যেটা Machinery-র সাথে সরাসরি সংযোগ।
- **`workstations`** + `status`

## ৫. Workflow / Status ও Business Rules

- Section-এ Department বাধ্যতামূলক ("Department is required."), Workstation-এ Line বাধ্যতামূলক ("Line is required.")।
- Line Master শুধু দেখার জন্য (Brickland-এও এটা সার্চ-টেবিল)। এন্ট্রি হয় Line ট্যাবে।

## ৬. API ও পারমিশন

`/company/departments`, `/company/sections`, `/company/lines`, `/company/workstations`

## ৭. Frontend পেজ (লাইট থিম)

Department / Section / Line / Workstation ট্যাব, আর আলাদা `/company-management/company-line` পেজে Line Master (সার্চসহ সারাংশ টেবিল)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (এই গ্রুপের নিজের ফাইল, তারপর পুরো Company Management-এর সাধারণ ফাইল):

**Backend**
- `app/Modules/Tenancy/Models/Department.php`
- `app/Modules/Tenancy/Models/Section.php`
- `app/Modules/Tenancy/Models/ProductionLine.php`
- `app/Modules/Tenancy/Models/Workstation.php`
- `app/Modules/Settings/MasterData/Types/DepartmentData.php`
- `app/Modules/Settings/MasterData/Types/SectionData.php`
- `app/Modules/Settings/MasterData/Types/ProductionLineData.php`
- `app/Modules/Settings/MasterData/Types/WorkstationData.php`
- `app/Modules/Tenancy/Database/Migrations/2026_10_20_000100_add_company_management_fields.php`
- `app/Modules/Settings/MasterData/Field.php`
- `app/Modules/Settings/MasterData/MasterDataType.php`
- `app/Modules/Settings/MasterData/MasterDataRegistry.php`
- `app/Modules/Settings/Actions/SaveMasterDataRow.php`
- `app/Modules/Settings/Http/Controllers/Api/MasterDataApiController.php`

**Frontend**
- `frontend/src/app/(app)/company-management/page.js`
- `frontend/src/app/(app)/company-management/[tab]/page.js`
- `frontend/src/app/(app)/company-management/[tab]/actions.js`
- `frontend/src/components/company/company-tabs.js`
- `frontend/src/components/company/company-tab-nav.jsx`
- `frontend/src/components/company/company-grid.jsx`
- `frontend/src/components/settings/dynamic-field.jsx`
- `frontend/src/components/settings/row-form-modal.jsx`
- `frontend/src/components/settings/master-data-table.jsx`
- `frontend/src/app/(app)/layout.js`
- `lang/en/company.php`
- `lang/bn/company.php`
- `lang/en/masterdata.php`
- `lang/bn/masterdata.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Company/CompanyStructureTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_উপরের As-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- Feature টেস্ট: তৈরি, বাধ্যতামূলক ফিল্ডের ভ্যালিডেশন (Brickland-এর মেসেজের সমতুল্য), Code অটো-ইস্যু, ব্যবহৃত রেকর্ড মুছলে 409 আর বাধার তালিকা, গ্রিডের বাল্ক সেভ, পারমিশন গেট।
- রিগ্রেশন: Asset, Breakdown, WorkOrder, Maintenance টেস্ট (এই টেবিলগুলো Machinery ব্যবহার করে)।
- লাইভ স্মোক: লাইট থিমে পেজ রেন্ডার, Brickland-এর স্ক্রিনশটের সাথে ফিল্ড আর কলাম মিলিয়ে দেখা।

## ১০. খোলা প্রশ্ন

_সমাধান হয়েছে:_ Line Type = Sewing/Cutting/Finishing/Quality/Sample/Special, বাধ্যতামূলক (ব্যবহারকারীর দেওয়া Brickland স্ক্রিনশট)।

## ১১. As-built নোট

- **চারটা ট্যাব তৈরি (২০২৬-১০-০৯):** Department (`DPT-`, ধরন Production/Non-Production, "Sections" গোনা), Section (`SEC-`, ঐচ্ছিক Floor), Line (`LN-`, Line Type বাধ্যতামূলক: Sewing/Cutting/Finishing/Quality/Sample/Special, Manpower, Capacity/Day, Target/Day), Workstation (`WS-`)।
- **Line-এর Machines আর Under Maintenance কলাম** সরাসরি Machinery-র `assets` + `asset_locations` থেকে গোনা হয়। Under Maintenance মানে স্ট্যাটাস UNDER_MAINTENANCE, BREAKDOWN বা UNDER_REPAIR। তাই দুই সিস্টেম কখনো আলাদা কথা বলবে না।
- **বিদ্যমান লাইনের Line Type খালি** (মাইগ্রেশনের আগে তৈরি)। গ্রিডে সেই লাইনের কিছু এডিট করে Save করলে Line Type দিতে হবে।
- **Line Master** (Brickland-এর আলাদা সার্চ-টেবিল পেজ) এখনো হয়নি। Line ট্যাবেই সব কলাম দেখা যায়।
- **Line Master:** Brickland-এ এটা আলাদা সার্চ-টেবিল পেজ। এখানে তার সব কলাম (Code, Line, Type, Floor, Section, Manpower, Machines, Under Maintenance, Capacity/Day, Target/Day, Status) Line ট্যাবেই আছে, তাই আলাদা পেজ বানানো হয়নি। **Employees** কলাম HR & Payroll (মডিউল ২৪) আসার পরে যোগ হবে।
