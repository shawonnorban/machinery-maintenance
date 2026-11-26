# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Fabric Requirement](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `13/01`

**⬅ ফিরে যান:** [Fabric Requirement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Batch Lead Days Before PCD | `/m/fabric-planning/batch-plan-date-before-shipment-pcd` | 2 | 15 | 1 |
| 2 | Standard Batch Hour Norms | `/m/fabric-planning/standard-batch-hour-setup` | 2 | 6 | 1 |
| 3 | Dye Machine Route Master | `/m/fabric-planning/dyeing-machine-routing-setup` | 2 | 11 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Batch Lead Days Before PCD

`/m/fabric-planning/batch-plan-date-before-shipment-pcd`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New routing rule

**ট্যাব: New routing rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Color Type | select |  | Select…, Solid, Melange, Yarn Dyed, All Over Print |
| All? | checkbox |  |  |
| Finish Type | select |  | Select…, Normal Finish, Peach Finish, Brush Finish, Silicone Finish |
| Any Finish? | checkbox |  |  |
| Wash Type | select |  | Select…, No Wash, Enzyme Wash, Stone Wash, Garment Wash |
| Any Wash? | checkbox |  |  |
| Order Lead Time From* | number | ✔ |  |
| Order Lead Time To* | number | ✔ |  |
| Order Volume From* | number | ✔ |  |
| Order Volume To* | number | ✔ |  |
| Production Lead Time* | number | ✔ |  |
| Shipment Or PCD?* | select | ✔ | Select…, Shipment, Pcd |
| Std Days Before Shipment | number |  |  |
| Std Days Before PCD | number |  |  |
| Is Embelishment? | checkbox |  |  |
- সেকশন: New routing rule
- বাটন/অ্যাকশন: New routing rule, Reset, Save routing rule

**ডায়ালগ: New routing rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Color Type | select |  | Select…, Solid, Melange, Yarn Dyed, All Over Print |
| All? | checkbox |  |  |
| Finish Type | select |  | Select…, Normal Finish, Peach Finish, Brush Finish, Silicone Finish |
| Any Finish? | checkbox |  |  |
| Wash Type | select |  | Select…, No Wash, Enzyme Wash, Stone Wash, Garment Wash |
| Any Wash? | checkbox |  |  |
| Order Lead Time From* | number | ✔ |  |
| Order Lead Time To* | number | ✔ |  |
| Order Volume From* | number | ✔ |  |
| Order Volume To* | number | ✔ |  |
| Production Lead Time* | number | ✔ |  |
| Shipment Or PCD?* | select | ✔ | Select…, Shipment, Pcd |
| Std Days Before Shipment | number |  |  |
| Std Days Before PCD | number |  |  |
| Is Embelishment? | checkbox |  |  |
- খালি Save-এ মেসেজ: A shipment-based rule needs Std Days Before Shipment above zero.

#### Standard Batch Hour Norms

`/m/fabric-planning/standard-batch-hour-setup`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New standard batch hour

**ট্যাব: New standard batch hour**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fiber Combination Template | select |  | Select…, 100% Cotton (FIB-COT), Any Fiber Blend with Lycra (FIB-LYC), CVC Blend (FIB-CVC) |
| Any Template? | checkbox |  |  |
| Buyer Category | select |  | Select…, Category A - Strategic, Category B - Regular, Category C - Occasional |
| Any Category? | checkbox |  |  |
| Dyeing Method* | select | ✔ | Select…, Exhaust Dyeing, Cold Pad Batch, Continuous Dyeing, Pigment Dyeing |
| Standard Batch Hour* | number | ✔ |  |
- সেকশন: New standard batch hour
- বাটন/অ্যাকশন: New standard batch hour, Select…, Reset, Save standard batch hour

**ডায়ালগ: New standard batch hour**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fiber Combination Template | select |  | Select…, 100% Cotton (FIB-COT), Any Fiber Blend with Lycra (FIB-LYC), CVC Blend (FIB-CVC) |
| Any Template? | checkbox |  |  |
| Buyer Category | select |  | Select…, Category A - Strategic, Category B - Regular, Category C - Occasional |
| Any Category? | checkbox |  |  |
| Dyeing Method* | select | ✔ | Select…, Exhaust Dyeing, Cold Pad Batch, Continuous Dyeing, Pigment Dyeing |
| Standard Batch Hour* | number | ✔ |  |
- খালি Save-এ মেসেজ: Standard Batch Hour must be greater than zero.

#### Dye Machine Route Master

`/m/fabric-planning/dyeing-machine-routing-setup`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New machine routing

**ট্যাব: New machine routing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fabric Group | select |  | Select…, Body (BODY), Collar (COLLAR), Lining (LINING), Rib (RIB) |
| Any Fabric Group? | checkbox |  |  |
| Fiber Combination Template | select |  | Select…, 100% Cotton (FIB-COT), Any Fiber Blend with Lycra (FIB-LYC), CVC Blend (FIB-CVC) |
| Any Template? | checkbox |  |  |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Any Buyer? | checkbox |  |  |
| Routed Dye M/C* | select | ✔ | Select… |
| Active? | checkbox |  |  |
| GSM From | number |  |  |
| GSM To | number |  |  |
| Any GSM? | checkbox |  |  |
- সেকশন: New machine routing
- বাটন/অ্যাকশন: New machine routing, Select…, Reset, Save machine routing

**ডায়ালগ: New machine routing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fabric Group | select |  | Select…, Body (BODY), Collar (COLLAR), Lining (LINING), Rib (RIB) |
| Any Fabric Group? | checkbox |  |  |
| Fiber Combination Template | select |  | Select…, 100% Cotton (FIB-COT), Any Fiber Blend with Lycra (FIB-LYC), CVC Blend (FIB-CVC) |
| Any Template? | checkbox |  |  |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Any Buyer? | checkbox |  |  |
| Routed Dye M/C* | select | ✔ | Select… |
| Active? | checkbox |  |  |
| GSM From | number |  |  |
| GSM To | number |  |  |
| Any GSM? | checkbox |  |  |
- খালি Save-এ মেসেজ: Give both ends of the GSM band, or tick Any.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/FabricPlanning`, সব টেবিল `fp_` নামে।
- **অন্য ERP মডিউল থেকে নেওয়া:**
  - Dye House-এর ডাই মেশিন (Dye Machine Route Master-এর জন্য)। এ কারণে Dye House-এর Master Data আগে তৈরি হয়েছে।
  - Merchandise Desk-এর buyer।
- **Merchandise Desk-এর তালিকা অনুযায়ী মান:** fiber combination template (COTTON_100/LYCRA_BLEND/CVC_BLEND, GSM-Wise Yarn Count Setup-এর মতো) আর fabric group (BODY/COLLAR/LINING/RIB, Garment Part Master-এর মতো)।
- এখানকার ডাইং পদ্ধতি (Exhaust, Cold Pad Batch, Continuous, Pigment) Dye House-এর Dye Method Master থেকে আলাদা একটা স্থির তালিকা, Brickland-এর মতোই।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_03_000100_create_fabric_planning_masters`। কোনো শর্ত ফাঁকা মানে "যেকোনো":

| টেবিল | ফিল্ড |
|---|---|
| `fp_batch_lead_rules` | colour_type (SOLID/MELANGE/YARN_DYED/AOP), finish_type (NORMAL/PEACH/BRUSH/SILICONE), wash_type (NO_WASH/ENZYME/STONE/GARMENT), lead_from, lead_to, volume_from, volume_to, production_lead_time, basis (SHIPMENT/PCD), days_before_shipment, days_before_pcd, is_embellishment |
| `fp_batch_hours` | fiber_template, buyer_category (A/B/C), dyeing_method (EXHAUST/COLD_PAD_BATCH/CONTINUOUS/PIGMENT), batch_hours |
| `fp_machine_routes` | fabric_group, fiber_template, buyer_id, dye_machine_id, gsm_from, gsm_to, active |

## ৫. Workflow / Status ও Business Rules

- **"Any?" বাক্স:** প্রতিটা শর্তে হয় একটা মান বাছাই করতে হয়, নয়তো Any-তে টিক দিতে হয়। Any টিক দিলে মান ফাঁকা রাখা হয়, আর তালিকায় "যেকোনো" দেখায়। মান বা Any কোনোটা না দিলে সেভ হয় না।
- **Batch Lead Days:**
  - লিড টাইম আর পরিমাণে "পর্যন্ত" কখনো "থেকে"-র কম হতে পারে না।
  - শিপমেন্ট-ভিত্তিক নিয়মে শিপমেন্টের আগের দিন শূন্যের বেশি হতে হয়, PCD-ভিত্তিক নিয়মে PCD-র আগের দিন। খালি থাকলে Brickland-এর মেসেজ আসে।
- **Standard Batch Hour:**
  - ঘণ্টা শূন্যের বেশি হতে হয় (Brickland-এর মেসেজ)।
  - একটা টেমপ্লেট, ক্যাটাগরি আর পদ্ধতির জন্য একটাই নর্ম থাকে।
- **Dye Machine Route:**
  - ডাই মেশিন বাধ্যতামূলক।
  - GSM-এর দুই প্রান্তই দিতে হয়, নয়তো Any (Brickland-এর মেসেজ)। "পর্যন্ত" "থেকে"-র কম হতে পারে না।
- নিয়মগুলো কাজে লাগবে ডাই ব্যাচ প্ল্যান (Fabric Requirement / Dye House-এর পরের ধাপ) তৈরির সময়। একাধিক নিয়ম মিললে সবচেয়ে নির্দিষ্টটা প্রাধান্য পাবে, এটা সেই ধাপে ঠিক হবে।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{fp-batch-lead-rules|fp-batch-hours|fp-machine-routes}`। প্রতিটা সারির সাথে Any বাক্সগুলোর অবস্থাও আসে।
- **পারমিশন:** নতুন `fabricplanning.master.view` আর `fabricplanning.master.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/fabric-requirement/<screen>`। সাইডবারে PLANNING → Fabric Requirement → MASTER DATA।
- তিনটা স্ক্রিনই Brickland-এর মতো Records | New। প্রতিটা শর্তের পাশে Any বাক্স।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/FabricPlanning/Database/Migrations/2026_11_03_000100_create_fabric_planning_masters.php`
- `app/Modules/FabricPlanning/Models/FpBatchLeadRule.php`
- `app/Modules/FabricPlanning/Models/FpBatchHour.php`
- `app/Modules/FabricPlanning/Models/FpMachineRoute.php`
- `app/Modules/FabricPlanning/Resources/FabricPlanningResource.php`
- `app/Modules/FabricPlanning/Resources/BatchLeadRuleResource.php`
- `app/Modules/FabricPlanning/Resources/BatchHourResource.php`
- `app/Modules/FabricPlanning/Resources/MachineRouteResource.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/fabric-requirement/[screen]/page.js`
- `frontend/src/app/(app)/fabric-requirement/[screen]/screens.js`
- `frontend/src/app/(app)/fabric-requirement/[screen]/actions.js`
- `lang/en/fabricplanning.php`
- `lang/bn/fabricplanning.php`

**Tests**
- `tests/Feature/FabricPlanning/FabricPlanningMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `FabricPlanningMasterTest` ৪/৪:
  - **Batch Lead Days:** শিপমেন্টের দিন ছাড়া Brickland-এর মেসেজ আসে। Any বা মান না দিলে সেভ হয় না। লিড টাইম উল্টো দিলে চলে না। Any দিলে মান ফাঁকা থাকে। PCD-তে বদলালে PCD-র দিন লাগে। "সব রং" দিলে রং ফাঁকা হয়।
  - **Standard Batch Hour:** শূন্য ঘণ্টায় Brickland-এর মেসেজ আসে। একই সমন্বয়ে দ্বিতীয় নর্ম চলে না। অন্য ক্যাটাগরিতে চলে।
  - **Dye Machine Route:** GSM-এর এক প্রান্ত বা উল্টো GSM চলে না। মেশিন ছাড়া Brickland-এর মেসেজ আসে। Dye House-এর মেশিন নং দেখায়। Any GSM দিলে ফাঁকা থাকে।
  - **পারমিশন:** ফ্যাক্টরি ম্যানেজার বদলাতে পারে। স্টোর ম্যানেজার পায় না।
- ব্রাউজার (dev): তিনটা স্ক্রিনেই একটা করে নিয়ম যোগ করা হয়েছে (Solid/Any/Any শিপমেন্ট ২১ দিন, 100% Cotton/Exhaust ৮ ঘণ্টা, Body 140–200 → MFL-01)। কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **একাধিক নিয়ম মিললে কোনটা প্রাধান্য পাবে:** যেমন একটা Solid নিয়ম আর একটা "সব রং" নিয়ম। এটা ঠিক হবে ডাই ব্যাচ প্ল্যান তৈরির সময়। সম্ভবত সবচেয়ে নির্দিষ্টটা জিতবে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- তিনটা স্ক্রিনই Brickland-এ খালি (Records 0), তাই কোনো শুরুর ডাটা নেই।
- Brickland-এর "Any?" বাক্স এখানে আলাদা কলাম নয়: Any মানে শর্তের মান ফাঁকা। সারি দেখানোর সময় বাক্সের অবস্থা সেখান থেকেই বের করা হয়।

