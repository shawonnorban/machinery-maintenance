# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Fabric Requirement](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `13/03`

**⬅ ফিরে যান:** [Fabric Requirement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Fabric Requirement Reports | `/m/fabric-planning/fabric-planning-reports` | 3 | 6 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Fabric Requirement Reports

`/m/fabric-planning/fabric-planning-reports`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Preview, New report run

**ট্যাব: Preview**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search reports | search |  | Search… |
- সেকশন: Selection of Parameters, Preview
- বাটন/অ্যাকশন: Preview, New report run

**ট্যাব: New report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report* | select | ✔ | Select…, Batch Plan Register, Batches Without a Machine, Machine Load vs Capacity, Machines Over Capacity, Schedule Boar |
| Unit | select |  | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Week | select |  | Select… |
| Export to Excel? | checkbox |  |  |
- সেকশন: New report run
- বাটন/অ্যাকশন: Preview, New report run, Reset, Save report run

**ডায়ালগ: New report run**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | select | ✔ | Select…, Batch Plan Register, Batches Without a Machine, Machine Load vs Capacity, Machines Over Capacity, Schedule Boar |
| Unit | select |  | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Week | select |  | Select… |
| Export to Excel? | checkbox |  |  |
- খালি Save-এ মেসেজ: Report is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/FabricPlanning`। রিপোর্টগুলো পড়ে 13/01-এর master আর 13/02-এর অপারেশনের টেবিল থেকে: ব্যাচ প্ল্যান, মেশিন ক্যাপাসিটি, শিডিউল বোর্ড, ছুটি, রুট আর ব্যাচ-ঘণ্টা।
- সপ্তাহ আর সপ্তাহের লেবেল আসে 13/02-এর `FpOps` থেকে (শনি থেকে শুক্র)।
- মেশিনের ইউনিট বের হয় ডাই মেশিনের floor থেকে (floor → building → ইউনিট)।
- Excel লেখা হয় OpenSpout দিয়ে, Capacity Planning-এর মতো।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_29_000100_create_fabric_planning_reports`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `fp_reports` | code, name, category (PLAN/CAPACITY/SCHEDULE/CONFIGURATION), sort_order, active | company + code |
| `fp_report_runs` | report_id, factory_id, buyer_id, week_start (ফাঁকা মানে All), export_excel, rows, created_by | — |

## ৫. Workflow / Status ও Business Rules

- **ক্যাটালগ:** Brickland বলে ক্যাটালগ কোডে বাঁধা নয়, ডাটায় রাখা। তাই প্রথমবার খুললে Brickland-এর ৮টা রিপোর্ট টেবিলে তৈরি হয়। যে রিপোর্ট `active` নয়, সেটা তালিকা থেকে সরে যায়।
- **রিপোর্টগুলো** (প্রতিবার মডিউলের টেবিল থেকে সরাসরি পড়া, কিছুই জমিয়ে রাখা হয় না):
  - **Batch Plan Register:** BP#, সপ্তাহ, বায়ার, স্টাইল, কালার গ্রুপ, মেশিন, ব্যাচ, পরিমাণ, অবস্থা।
  - **Batches Without a Machine:** মেশিন ছাড়া আর বাতিল নয় এমন প্ল্যান।
  - **Machine Load vs Capacity:** ইউনিট, মেশিন, সপ্তাহ, সর্বোচ্চ লোড, প্ল্যান করা কেজি, ব্যাচ, ব্যবহার %, আর অবস্থা (ক্যাপাসিটির মধ্যে বা বেশি)।
  - **Machines Over Capacity:** শুধু যেসব মেশিনে প্ল্যান করা কেজি সর্বোচ্চ লোডের বেশি, আর কত বেশি।
  - **Schedule Board Listing:** বোর্ড, ক্রম, BP#, বায়ার, মেশিন, শুরু, শেষ, পরিমাণ।
  - **Dye Machine Routing Register, Factory Holiday Register, Standard Batch Hour Register:** master আর ছুটির তালিকা। Any হলে "যেকোনো" দেখায়, সেকশন না থাকলে "সব সেকশন"।
- **Report run:**
  - রিপোর্ট না দিলে Brickland-এর মেসেজ আসে: "Report is required."।
  - ইউনিট, বায়ার বা সপ্তাহ ফাঁকা মানে All। সপ্তাহ তার শনিবারে বসে।
  - সেভ বা Edit করার সময় সেই মুহূর্তের ডাটায় রিপোর্টের সারি গোনা হয়ে Rows-এ থাকে।
  - **কোন রিপোর্ট কোন শর্ত মানে:**
    - ইউনিট মানে মেশিনের ইউনিট (ছুটির ক্ষেত্রে অফিস)।
    - বায়ার মানে ব্যাচ প্ল্যান বা রুটের বায়ার।
    - সপ্তাহ মানে প্ল্যান বা ক্যাপাসিটির সপ্তাহ (ছুটির ক্ষেত্রে সেই সপ্তাহের দিনগুলো)।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/fp-report-runs`। মোছা যায় না, Brickland-এ শুধু Edit।
- **নিজস্ব endpoint** (`FpReportsApiController`):
  - `GET /fabric-planning/reports`: ক্যাটালগ।
  - `GET /fabric-planning/reports/{code}`: কলাম আর সারি।
  - `GET /fabric-planning/reports/{code}/excel`
  - রিপোর্ট সবসময় তার কোড দিয়ে খোলে (FP-MACHINE-LOAD)।
- **পারমিশন:** নতুন `fabricplanning.report.view`। দেখা আর run সেভ দুটোই এটা দিয়ে চলে। পায় FACTORY_ADMIN আর FACTORY_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: PLANNING → Fabric Requirement → REPORTS → Fabric Requirement Reports (09 of 9)।
- **Records:** run-এর তালিকা (Report, Category, Unit, Buyer, Week, Rows, Export to Excel?)। Edit একই জায়গায় খোলে।
- **Preview:**
  - বাঁদিকে "Selection of Parameters": ৮টা রিপোর্ট, ক্যাটাগরি আর কোডসহ, ▶ চিহ্ন। URL-এ `?group=FP-...`।
  - ডানদিকে লাইভ সারণী আর Export (xlsx, `/api/fabric-requirement/excel/{code}`)।
  - অবস্থার পিল রঙিন। নিচে "N row(s). Every figure here is read … Nothing is cached."।
- **New report run:** EntryForm (Report, Unit, Buyer A/C, Week, Export to Excel?), Brickland-এর ব্যাখ্যার লাইনসহ।
- Brickland-এর নিজের Preview-তে একটা বাগ আছে: ডাটার বদলে কলামের নামগুলো সারি হিসেবে দেখায়। এখানে আসল সারি দেখায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/FabricPlanning/Database/Migrations/2026_12_29_000100_create_fabric_planning_reports.php`
- `app/Modules/FabricPlanning/Models/FpReport.php`
- `app/Modules/FabricPlanning/Models/FpReportRun.php`
- `app/Modules/FabricPlanning/Support/FpReports.php`
- `app/Modules/FabricPlanning/Resources/ReportRunResource.php`
- `app/Modules/FabricPlanning/Http/Controllers/Api/FpReportsApiController.php`
- `app/Modules/FabricPlanning/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/fabric-requirement/[screen]/page.js`
- `frontend/src/app/(app)/fabric-requirement/[screen]/ops-screens.js`
- `frontend/src/app/api/fabric-requirement/excel/[code]/route.js`
- `lang/en/fabricplanning.php`
- `lang/bn/fabricplanning.php`

**Tests**
- `tests/Feature/FabricPlanning/FabricPlanningReportsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`FabricPlanningReportsTest` ১/১ (৪৫ assertion):**
  - **ক্যাটালগ আর রিপোর্ট:** ক্যাটালগে ৮টা রিপোর্ট আসে, Brickland-এর ক্রমে।
    - ৩টা ব্যাচ প্ল্যান (একটা মেশিন ছাড়া) আর দুটো মেশিনের W44 ক্যাপাসিটি দিয়ে:
    - **Register আর Unplaced:** Register-এ ৩টা সারি। Unplaced-এ শুধু BP-00003।
    - **লোড:** MFL-01-এ ৬৫% (মধ্যে), DM-05-এ ১২০% (বেশি)। Over Capacity-তে DM-05 ৫০ কেজি বেশি।
    - **বাকি তিনটা:** বোর্ডের ক্রম ঠিক থাকে, ছুটির তালিকা আসে, ব্যাচ-ঘণ্টা খালি আসে।
  - **ত্রুটি আর Excel:** ভুল কোডে 404। Excel ডাউনলোড হয়।
  - **Run:** রিপোর্ট ছাড়া Brickland-এর মেসেজ আসে।
    - সব সারিতে ৩টা আসে। LPP আর W44 দিলে ২টা, অন্য সপ্তাহে Edit করলে ০।
    - Over Capacity, ইউনিট ধরে: ১টা।
  - **পারমিশন:** স্টোর ম্যানেজার পায় না, ফ্যাক্টরি ম্যানেজার পায়।
- **পুরনো টেস্ট আবার:** FabricPlanningOperationsTest, ApiGateCoverage, MenuAccess, RoleMatrix।
- **ব্রাউজার (dev, fu04, ডাটা রাখা আছে):**
  - **Preview:** ৮টা রিপোর্ট দেখায়। Machine Load-এ DM-01 W44 আসে: ১,৩৫০ / ৮৭৫ / ২টা ব্যাচ / ৬৫%, ক্যাপাসিটির মধ্যে। Excel ডাউনলোড হয়। Register-এ BP-00001।
  - **Run:** খালি সেভে "Report is required."। Machine Load-এর একটা run সেভ করা হয়েছে (Dhaka Unit 1, W44, Excel হ্যাঁ, ১টা সারি)।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **Export to Excel? ঘর:** Brickland-এ run-এর এই ঘর সেভ হয়, কিন্তু কোথাও ফাইল তৈরি হতে দেখা যায়নি। এখানেও এটা run-এর তথ্য হিসেবে থাকে। Excel আসে Preview-এর Export থেকে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৫):**
- Fabric Requirement মডিউলের তিনটা গ্রুপই এখন ✅ (13/01, 13/02, 13/03)।
- **Brickland প্রোব (fr04, fr05):** লাইভ demo-তে একটা run সেভ করে দেখা হয়েছে (Machine Load vs Capacity, সব, ০টা সারি)। আটটা রিপোর্টের কলাম Preview থেকে নেওয়া।
