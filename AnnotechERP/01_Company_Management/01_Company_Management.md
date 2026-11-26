# Company Management

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Company Management](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `01/01`

**⬅ ফিরে যান:** [Company Management](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Company Management | `/m/company-management/company-management` | 1 | 0 | 0 |
| 2 | Company Profile | `/m/company-management/company-profile` | 6 | 0 | 0 |
| 3 | Company Documents | `/m/company-management/company-documents` | 6 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Company Management

`/m/company-management/company-management` — মেনুতে: Dashboard

**ভিউ**
- গ্রিড কলাম: #, Building Code, Building Name, No. of Floors, Status, Action
- গ্রিড কলাম: #, Floor Code, Floor Name, Building, Capacity, Status, Action
- গ্রিড কলাম: #, Code, Name, Status, Action
- গ্রিড কলাম: #, Code, Name, Department, Status, Action
- গ্রিড কলাম: #, Line Code, Line Name, Department, Section, Floor, Capacity, Status, Action
- গ্রিড কলাম: #, Machine Code, Machine Name, Type, Status, Action
- গ্রিড কলাম: #, Code, Designation, Status, Action
- গ্রিড কলাম: #, Code, Grade Name, Status, Action
- সেকশন: Company Profile, Factory Setup, Building List, Floor List, Department List, Section List, Line List, Workstation List, Machine Master, Designation List, Grade List, Company Documents

#### Company Profile

`/m/company-management/company-profile`

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

**ট্যাব: Section**
- গ্রিড কলাম: SL, Section Code, Section Name, Department, Floor, Employees, Status, Status, Action
- সেকশন: Sections
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Export, Add Section, Revert, Save

#### Company Documents

`/m/company-management/company-documents`

**ট্যাব: Company Documents**
- সেকশন: Documents
- বাটন/অ্যাকশন: Company Profile, Factory, Building, Floor, Department, Section, Line, Workstation, Machine Master, Machine Allocation, Designation, Grade, Company Documents, Add Document, Revert, Save

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

- `app/Modules/Tenancy/Models/Company.php` (`companies`: name, code, legal_name, email, phone, country, address, logo_path, status …)
- লোগো আপলোড: বিদ্যমান `FileAttachment`/স্টোরেজ প্যাটার্ন

## ৪. ডাটা মডেল (Entity ও Field)

**`companies` (বিদ্যমান) + যোগ হবে:** `website`, `trade_license_no`, `tin`, `bin`, `registration_no`, `registration_date`। ম্যাপিং: Company Code = `code`, Contact Number = `phone`, Logo (link) = `logo_path`।

**`company_documents` (নতুন):** id (ULID), company_id, document_type (`TRADE_LICENSE`/`TIN`/`BIN`/`BOND_LICENSE`/`FIRE_LICENSE`/`ENVIRONMENT`/`OTHER`), title, document_no, issue_date, expiry_date, issued_by, remarks, status, ফাইল (`FileAttachment`, attachable_type `company_document`), timestamps। গ্রিডের কলাম (ব্যবহারকারী নিশ্চিত করেছেন, ২০২৬-১০-০৯): Document type, Title, Document no, Issue date, Expiry date, Issued by, ফাইল।

## ৫. Workflow / Status ও Business Rules

- Company Profile একটাই রেকর্ড (বর্তমান কোম্পানি)। এখানে Add নেই, শুধু এডিট আর Save।
- মেয়াদোত্তীর্ণ হতে চলা ডকুমেন্টে নোটিফিকেশন যায় (`COMPANY_DOCUMENT_EXPIRING`, সেটিং `company.document_expiry_notice_days`, ডিফল্ট ৩০)।

## ৬. API ও পারমিশন

`GET/PUT /company/profile`, `GET/POST/PUT/DELETE /company/documents` (+ ফাইল আপলোড)। পারমিশন `company.structure.view_any/manage`।

## ৭. Frontend পেজ (লাইট থিম)

`/company-management/company-profile` (এক-সারির গ্রিড-ফর্ম), `/company-management/company-documents` (গ্রিড + Add Document মডাল + ফাইল)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (এই গ্রুপের নিজের ফাইল, তারপর Company Management-এর সাধারণ ফাইল):

**Backend**
- `app/Modules/Tenancy/Database/Migrations/2026_10_20_000100_add_company_management_fields.php`
- `app/Modules/Tenancy/Models/Company.php`
- `app/Modules/Tenancy/Models/CompanyDocument.php`
- `app/Modules/Company/Routes/api.php`
- `app/Modules/Company/Http/Controllers/Api/CompanyProfileApiController.php`
- `app/Modules/Company/Http/Controllers/Api/CompanyDocumentApiController.php`
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php`
- `app/Shared/Files/Http/Controllers/FileAttachmentController.php`

**Frontend**
- `frontend/src/components/company/company-profile-form.jsx`
- `frontend/src/components/company/company-documents.jsx`
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

_সমাধান হয়েছে:_ ডকুমেন্টের কলাম ব্যবহারকারী নিশ্চিত করেছেন (উপরে §৪)।

## ১১. As-built নোট

- **Company Profile (২০২৬-১০-০৯):** `GET/PUT /api/v1/company/profile` (`settings.company.manage`)। একটাই রেকর্ড, Add নেই। `code` বদলানো যায় না (অনুরোধে পাঠালেও উপেক্ষিত হয়)। নতুন কলাম: website, trade_license_no, tin, bin, registration_no, registration_date।
- **Company Documents:** `GET/POST/PATCH/DELETE /api/v1/company/documents`, ফাইল যোগ হয় `GET/POST …/{document}/files`-এ (`FileAttachment`, attachable_type `company_document`, ডাউনলোড বিদ্যমান `/api/files/{id}/download` দিয়ে)। কলাম ব্যবহারকারী-নিশ্চিত: Document type (Trade Licence/TIN/BIN/Bond Licence/Fire Licence/Environment/Other), Title, Document no, Issue date, Expiry date (issue date-এর আগে হতে পারবে না), Issued by, Status।
- **এখনো বাকি:** মেয়াদ শেষ হওয়ার আগে নোটিফিকেশন (`COMPANY_DOCUMENT_EXPIRING`)। স্পেকে আছে, কিন্তু scheduler-এর কাজ তাই পরে আসবে। লোগো এখনো Settings → Company থেকে আপলোড হয়।

**UI-তে হুবহু Brickland (২০২৬-১০-১০):**
- **Dashboard** (`/company-management`): তারিখ-কার্ড, ৭টা স্ট্যাট-কার্ড (মোট কর্মী, মেশিন, বিল্ডিং, ফ্লোর, ডিপার্টমেন্ট, সেকশন, লাইন; সাথে সক্রিয়-সংখ্যা), তারপর Company Profile, Factory Setup আর Building List কার্ড। API: `GET /company/dashboard` (`masterdata.manage`)। HR & Payroll না আসা পর্যন্ত "কর্মী" মানে কোম্পানির সাইন-ইন অ্যাকাউন্ট।
- **সাইডবার:** Dashboard, Company Profile, ফোল্ডার Factory Setup (Factory, Building, Floor), Organization (Department, Section, Line, Workstation), Machine Management (Machine Master, Machine Allocation), Designation & Grade (Designation, Grade), শেষে Company Documents। মোট ১৪টা, Brickland-এর মতো।
- **প্রতিটা স্ক্রিন:** Brickland-এর পাতার মাথা ("NN of 14"), ট্যাব-সারিতে "Records N" আর পাশের স্ক্রিনগুলো। Records ট্যাব (`?tab=records`) সারিগুলো শুধু-দেখা গ্রিডে দেখায় (সার্চ, সাজানো, পেজার, Export); স্ক্রিনের নিজের ট্যাবে আগের মতো ইনলাইন-এডিট গ্রিড।
- ফাইল: `frontend/src/app/(app)/company-management/page.js`, `frontend/src/app/(app)/company-management/[tab]/page.js`, `frontend/src/components/company/company-grid.jsx`, `app/Modules/Company/Http/Controllers/Api/CompanyDashboardApiController.php`, `app/Modules/Company/Routes/api.php`, `lang/{en,bn}/company.php`।
