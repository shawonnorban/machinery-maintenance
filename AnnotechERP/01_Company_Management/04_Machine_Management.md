# Machine Management

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Company Management](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `01/04`

**⬅ ফিরে যান:** [Company Management](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Machine Master | `/m/company-management/company-machine` | 6 | 0 | 1 |
| 2 | Machine Allocation | `/m/company-management/company-machine-allocation` | 6 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Machine Master

`/m/company-management/company-machine`

**ট্যাব: Machine Master**
- গ্রিড কলাম: SL, Machine Code, Machine Name, Type, Brand, Model, Serial Number, Purchase Date, Installation Date, Capacity, Status, Status, Action
- সেকশন: Machines
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Machine, Revert, Save

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

**ডায়ালগ: Add Machine** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine Code | text |  |  |
| Machine Name* | text | ✔ |  |
| Type | select |  | Select…, Circular Knitting, Sewing, Utility & Power, Dyeing |
| Or a new type | text |  |  |
| Brand | select |  | Select…, Atlas Copco (Utility & Power), Brother (Sewing), Fong's (Dyeing), Fukuhara (Circular Knitting), Juki (Sewing),  |
| Or a new brand | text |  |  |
| Model | text |  |  |
| Serial Number | text |  |  |
| Purchase Date | date |  |  |
| Installation Date | date |  |  |
| Capacity | number |  |  |
| Status | select |  | Select…, Active, Idle, Under Maintenance, Damaged, Retired |
- খালি Save-এ মেসেজ: Pick a machine type, or type a new one.

#### Machine Allocation

`/m/company-management/company-machine-allocation`

**ট্যাব: Machine Allocation**
- গ্রিড কলাম: SL, Machine Code, Machine, Building, Floor, Department, Section, Line, Workstation, Line machines, Allocated, Status, Action
- সেকশন: Machines
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Allocate Machine, Release, Revert, Save

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

- **Machine Master = `assets`** (asset_code, name, asset_type_id, manufacturer_id = Brand, asset_model_id, serial_number, purchase_date, installation_date, status)
- **Machine Allocation = `asset_locations`** (factory/building/floor/department/section/production_line/workstation)
- `asset_types`, `manufacturers`, `asset_models` মাস্টার আছে

## ৪. ডাটা মডেল (Entity ও Field)

- `assets` + `capacity` (decimal, nullable) আর `capacity_uom` (যেমন kg/day, pcs/hr)
- Brickland-এর মেশিন Status আর Asset status-এর ম্যাপিং (ব্যবহারকারী অনুমোদিত): Active → `RUNNING`, Idle → `IDLE`, Under Maintenance → `UNDER_MAINTENANCE`, Damaged → `BREAKDOWN`, Retired → `RETIRED`। Asset-এর বাকি স্টেট (DRAFT, COMMISSIONED …) Machinery-তেই থাকবে। Company Management-এর গ্রিডে শুধু এই ৫টা দেখানো হবে।
- "Or a new type / Or a new brand" মানে মডাল থেকে নতুন `asset_types`/`manufacturers` রো তৈরি করা যায়

## ৫. Workflow / Status ও Business Rules

- মেশিনের Type বাধ্যতামূলক ("Pick a machine type, or type a new one.")। Name বাধ্যতামূলক।
- Allocate: একটা মেশিন একসাথে একটাই location-এ থাকে। Release করলে location খালি হয়, আর Asset-এর বিদ্যমান transfer ইতিহাসে রেকর্ড যায়।
- স্ট্যাটাস বদল বিদ্যমান `ChangeAssetStatus` অ্যাকশন দিয়েই হবে, যাতে Machinery-র নিয়ম (elevation, ইতিহাস) বজায় থাকে।

## ৬. API ও পারমিশন

বিদ্যমান Asset API পুনর্ব্যবহার হবে, আর `/company/machines` (গ্রিড-ফরম্যাট) ও `/company/machine-allocations` (allocate/release) পাতলা wrapper হিসেবে থাকবে।

## ৭. Frontend পেজ (লাইট থিম)

Machine Master ট্যাব (গ্রিড + Add Machine মডাল, type/brand-এ "নতুন" অপশনসহ), Machine Allocation ট্যাব (Allocate Machine / Release)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (এই গ্রুপের নিজের ফাইল, তারপর Company Management-এর সাধারণ ফাইল):

**Backend**
- `app/Modules/Asset/Database/Migrations/2026_10_20_000200_add_capacity_to_assets.php`
- `app/Modules/Asset/Models/Asset.php`
- `app/Modules/Company/Http/Controllers/Api/MachineRegisterApiController.php`

**Frontend**
- `frontend/src/components/company/machine-register.jsx`
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

- **নকশা (২০২৬-১০-০৯):** দুটো আলাদা মেশিন-রেকর্ড নেই। Machine Master আর Allocation দুটোই Machinery-র `assets` আর `asset_locations` দেখায় (`GET /api/v1/company/machines`)। ব্যবহারকারীর নির্দেশ "Machinery অপরিবর্তিত থাকবে", তাই:
  - Capacity Company Management-এর নিজের (`PATCH /api/v1/company/machines/{asset}`, নতুন কলাম `assets.capacity`/`capacity_uom`)।
  - Status গ্রিডে Brickland-এর ৫টা নামে বদলায়, কিন্তু সেভ হয় Machinery-র নিজের `POST /assets/{id}/status` দিয়ে (version, এলিভেশনের নিয়ম আর ইতিহাস বজায় থাকে)। ম্যাপিং ব্যবহারকারী-অনুমোদিত: Active=RUNNING, Idle=IDLE, Under Maintenance=UNDER_MAINTENANCE, Damaged=BREAKDOWN, Retired=RETIRED। অন্য lifecycle-স্টেট (DRAFT ইত্যাদি) শুধু দেখায়।
  - **Add Machine** আর পূর্ণ এডিট খোলে Machinery-র ফর্ম (`/assets/create`, `/assets/{id}`)। কারণ Asset তৈরিতে category, criticality, location বাধ্যতামূলক, আর Brickland-এর ছোট ফর্মে এগুলো নেই।
  - **Allocation:** বর্তমান Building/Floor/Department/Section/Line/Workstation দেখায়। সরানো হয় Machinery-র transfer দিয়ে, যাতে লোকেশনের ইতিহাস থাকে।
- **ব্যবহারকারীর সাথে আলোচনার বিষয়:** Brickland-এর মতো একই গ্রিড থেকে সরাসরি নতুন মেশিন যোগ বা বরাদ্দ চাইলে Machinery-র নিয়মে ডিফল্ট মান (category, criticality) ঠিক করতে হবে।
