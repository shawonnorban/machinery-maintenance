# Designation & Grade

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Company Management](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `01/05`

**⬅ ফিরে যান:** [Company Management](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Designation | `/m/company-management/company-designation` | 6 | 0 | 0 |
| 2 | Grade | `/m/company-management/company-grade` | 6 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Designation

`/m/company-management/company-designation`

**ট্যাব: Designation**
- গ্রিড কলাম: SL, Code, Designation, Department, Employee Category, Grade, Employees, Status, Status, Action
- সেকশন: Designations
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Designation, Revert, Save

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

#### Grade

`/m/company-management/company-grade`

**ট্যাব: Grade**
- গ্রিড কলাম: SL, Code, Grade Name, Order, Employees, Status, Status, Action
- সেকশন: Grades
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Grade, Revert, Save

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
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নেই। HR & Payroll (মডিউল ২৪)-এর Pay Grade/Employee Category এর সাথে সম্পর্ক থাকবে।

## ৪. ডাটা মডেল (Entity ও Field)

- **`designations` (নতুন):** id, company_id, code, name, department_id (nullable), employee_category (HR master আসার আগে enum: `WORKER`/`STAFF`/`OFFICER`/`MANAGEMENT`), grade_id (nullable), status, timestamps। "Employees" কলাম HR আসার পরে গোনা হবে।
- **`grades` (নতুন):** id, company_id, code, name, sort_order ("Order"), status

## ৫. Workflow / Status ও Business Rules

- Designation আর Grade-এর Name বাধ্যতামূলক। কর্মী থাকলে মোছা যাবে না (HR আসার পরে প্রযোজ্য)।

## ৬. API ও পারমিশন

`/company/designations`, `/company/grades`

## ৭. Frontend পেজ (লাইট থিম)

Designation আর Grade ট্যাব (ইনলাইন গ্রিড + Add মডাল)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (এই গ্রুপের নিজের ফাইল, তারপর পুরো Company Management-এর সাধারণ ফাইল):

**Backend**
- `app/Modules/Tenancy/Models/Grade.php`
- `app/Modules/Tenancy/Models/Designation.php`
- `app/Modules/Settings/MasterData/Types/GradeData.php`
- `app/Modules/Settings/MasterData/Types/DesignationData.php`
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

_সমাধান হয়েছে:_ Employee Category = Worker/Staff/Officer/Management (ব্যবহারকারী নিশ্চিত)। HR & Payroll-এর মাস্টার আসলে FK-তে বদলানো হবে।

## ১১. As-built নোট

- **Grade** (`grades`: `GRD-`, Order = `sort_order`, Status) আর **Designation** (`designations`: `DSG-`, ঐচ্ছিক Department আর Grade, Employee Category = Worker/Staff/Officer/Management, Status) তৈরি (২০২৬-১০-০৯)।
- যে Grade কোনো Designation-এ ব্যবহৃত, সেটা মোছা যায় না (409)। "Employees" কলাম HR & Payroll আসার পরে যোগ হবে।
