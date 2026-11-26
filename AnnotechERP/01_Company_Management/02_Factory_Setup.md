# Factory Setup

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Company Management](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `01/02`

**⬅ ফিরে যান:** [Company Management](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Factory | `/m/company-management/company-factory` | 6 | 0 | 0 |
| 2 | Building | `/m/company-management/company-building` | 6 | 0 | 1 |
| 3 | Floor | `/m/company-management/company-floor` | 6 | 0 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Factory

`/m/company-management/company-factory`

**ট্যাব: Factory**
- গ্রিড কলাম: Factory Code, Factory Name, Address, Contact, Established Date, Factory Area (sqft), Status, Remarks
- সেকশন: Factory
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Company Profile**
- গ্রিড কলাম: Company Code, Company Name, Address, Contact Number, Email, Website, Logo (link), Trade License, TIN, BIN, Registration No, Registration Date, Status
- সেকশন: Company
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

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

#### Building

`/m/company-management/company-building`

**ট্যাব: Building**
- গ্রিড কলাম: SL, Building Code, Building Name, Factory, Number of Floors, Floors Registered, Status, Remarks, Status, Action
- সেকশন: Buildings
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Building, Revert, Save

**ট্যাব: Company Profile**
- গ্রিড কলাম: Company Code, Company Name, Address, Contact Number, Email, Website, Logo (link), Trade License, TIN, BIN, Registration No, Registration Date, Status
- সেকশন: Company
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Factory**
- গ্রিড কলাম: Factory Code, Factory Name, Address, Contact, Established Date, Factory Area (sqft), Status, Remarks
- সেকশন: Factory
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Revert, Save

**ট্যাব: Floor**
- গ্রিড কলাম: SL, Floor Code, Floor Name / Number, Building, Factory, Floor Capacity, Status, Status, Action
- সেকশন: Floors
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Floor, Revert, Save

**ট্যাব: Department**
- গ্রিড কলাম: SL, Code, Name, Department Type, Sections, Status, Status, Action
- সেকশন: Departments
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Department, Revert, Save

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

**ডায়ালগ: Add Building** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Building Code | text |  |  |
| Building Name* | text | ✔ |  |
| Number of Floors | number |  |  |
| Status | select |  | Select…, Active, Inactive |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Building Name is required.

#### Floor

`/m/company-management/company-floor`

**ট্যাব: Floor**
- গ্রিড কলাম: SL, Floor Code, Floor Name / Number, Building, Factory, Floor Capacity, Status, Status, Action
- সেকশন: Floors
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Floor, Revert, Save

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

**ট্যাব: Department**
- গ্রিড কলাম: SL, Code, Name, Department Type, Sections, Status, Status, Action
- সেকশন: Departments
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Department, Revert, Save

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

**ডায়ালগ: Add Floor** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Floor Code | text |  |  |
| Floor Name / Number* | text | ✔ |  |
| Building* | select | ✔ | Select…, BCL (BLD-001) |
| Floor Capacity | number |  |  |
| Status | select |  | Select…, Active, Inactive |
- খালি Save-এ মেসেজ: Building is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `factories`, `buildings`, `floors` (Tenancy)। Asset location আর Breakdown এগুলো ব্যবহার করে।

## ৪. ডাটা মডেল (Entity ও Field)

- **`factories`** + `contact`, `established_date`, `area_sqft`, `remarks` (status আছে)
- **`buildings`** + `number_of_floors` (int, ঘোষিত), `status` (`ACTIVE`/`INACTIVE`), `remarks`। "Floors Registered" স্টোর করা হয় না, `floors` গুনে বের করা হয়।
- **`floors`** + `capacity` (int), `status`। "Factory" কলাম building-এর মাধ্যমে আসে।

## ৫. Workflow / Status ও Business Rules

- Floor তৈরিতে Building বাধ্যতামূলক ("Building is required.")। Building Name আর Floor Name/Number বাধ্যতামূলক।
- Building-এর অধীনে ফ্লোর, লাইন, কর্মী বা মেশিন থাকলে মোছা যাবে না (Brickland-এর মেসেজের মতো)।
- Floors Registered > Number of Floors হলে সতর্কতা দেখায় (ব্লক না)।

## ৬. API ও পারমিশন

`/company/factories`, `/company/buildings`, `/company/floors`: index, store, bulk update (গ্রিড Save), destroy।

## ৭. Frontend পেজ (লাইট থিম)

একই ট্যাব-শেলে Factory / Building / Floor ট্যাব, ইনলাইন গ্রিড, Add Building আর Add Floor মডাল।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (এই গ্রুপের নিজের ফাইল, তারপর Company Management-এর সাধারণ ফাইল):

**Backend**
- `app/Modules/Tenancy/Models/Factory.php`
- `app/Modules/Tenancy/Actions/SaveFactory.php`
- `app/Modules/Tenancy/Http/Controllers/Api/FactoryApiController.php`
- `app/Modules/Tenancy/Models/Building.php`
- `app/Modules/Tenancy/Models/Floor.php`
- `app/Modules/Settings/MasterData/Types/BuildingData.php`
- `app/Modules/Settings/MasterData/Types/FloorData.php`
- `app/Modules/Tenancy/Database/Migrations/2026_10_20_000100_add_company_management_fields.php`
- `app/Modules/Settings/MasterData/Field.php`
- `app/Modules/Settings/MasterData/MasterDataType.php`
- `app/Modules/Settings/MasterData/MasterDataRegistry.php`
- `app/Modules/Settings/Actions/SaveMasterDataRow.php`
- `app/Modules/Settings/Http/Controllers/Api/MasterDataApiController.php`

**Frontend**
- `frontend/src/components/settings/dynamic-field.jsx`
- `frontend/src/components/settings/row-form-modal.jsx`
- `frontend/src/components/settings/master-data-table.jsx`
- `frontend/src/app/(app)/layout.js`
- `lang/en/masterdata.php`
- `lang/bn/masterdata.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`
- `frontend/src/app/(app)/company-management/page.js`
- `frontend/src/app/(app)/company-management/[tab]/page.js`
- `frontend/src/app/(app)/company-management/[tab]/actions.js`
- `frontend/src/components/company/company-tabs.js`
- `frontend/src/components/company/company-tab-nav.jsx`
- `frontend/src/components/company/company-grid.jsx`
- `lang/en/company.php`
- `lang/bn/company.php`

**Tests**
- `tests/Feature/Company/CompanyStructureTest.php`
- `tests/Feature/Company/CompanyProfileDocumentsMachinesTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_উপরের As-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- Feature টেস্ট: তৈরি, বাধ্যতামূলক ফিল্ডের ভ্যালিডেশন (Brickland-এর মেসেজের সমতুল্য), Code অটো-ইস্যু, ব্যবহৃত রেকর্ড মুছলে 409 আর বাধার তালিকা, গ্রিডের বাল্ক সেভ, পারমিশন গেট।
- রিগ্রেশন: Asset, Breakdown, WorkOrder, Maintenance টেস্ট (এই টেবিলগুলো Machinery ব্যবহার করে)।
- লাইভ স্মোক: লাইট থিমে পেজ রেন্ডার, Brickland-এর স্ক্রিনশটের সাথে ফিল্ড আর কলাম মিলিয়ে দেখা।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **Building আর Floor তৈরি (২০২৬-১০-০৯):** বিদ্যমান MasterData ফ্রেমওয়ার্কের টাইপ হিসেবে, নতুন কন্ট্রোলার ছাড়া। API: `GET/POST/PATCH/DELETE /api/v1/master-data/buildings|floors`। Code খালি থাকলে BLD-001 / FLR-001 ইস্যু হয়, সংখ্যা ধরা হয় সর্বোচ্চ বিদ্যমান suffix থেকে। "Floors Registered" কোথাও স্টোর হয় না, `floors` গুনে বের করা হয় (`computedColumns`/`computedValues`)।
- **Factory ট্যাব এখনো হয়নি।** এর নিজের API (`/factories`, `SaveFactory`: ৫-অক্ষরের কোড, timezone) আছে, পরের ধাপে Company Profile-এর সাথে বানানো হবে। কলামগুলো (contact, established_date, area_sqft, remarks) মাইগ্রেশনে আর মডেলে যোগ হয়ে গেছে।
- **পারমিশন:** স্পেকে প্রস্তাব ছিল `company.structure.*`। বাস্তবে বিদ্যমান `masterdata.manage` ব্যবহার করা হয়েছে, কারণ একই API, আর RBAC বদলানো এড়ানো গেছে। আলাদা পারমিশন লাগলে পরে যোগ করা যাবে।
- **মোছার মেসেজ:** এখন "N records use this" (গোনা সংখ্যা)। Brickland-এর মতো কী কী বাধা দিচ্ছে তার নাম এখনো দেখায় না (উন্নতির সুযোগ)।
- **Factory ট্যাব (২০২৬-১০-০৯):** বিদ্যমান `/api/v1/factories` API-র ওপর একই গ্রিড (`SaveFactory`-র entitlement চেক আর ৫-অক্ষরের কোড অক্ষত)। নতুন ফিল্ড: contact, established_date, area_sqft, remarks। কোনো পুরনো ক্লায়েন্ট শুধু name/timezone পাঠালে নতুন ফিল্ড মুছে যায় না (টেস্টে যাচাই করা)।
