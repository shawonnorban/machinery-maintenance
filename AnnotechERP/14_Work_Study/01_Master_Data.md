# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `14/01`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Item & Process Grouping | `/m/ie/item-and-process-group` | 2 | 5 | 1 |
| 2 | Floor-Wise Target Setup | `/m/ie/ie-floor-wise-target-setting-options` | 1 | 0 | 1 |
| 3 | Finishing Floor Access Setup | `/m/ie/finishing-floor-access-setting-options` | 1 | 0 | 1 |
| 4 | Learning Curve Model | `/m/ie/learning-curve` | 2 | 4 | 1 |
| 5 | Responsible Owner Mapping | `/m/ie/responsible-dept-person-mapping` | 1 | 1 | 1 |
| 6 | Resource Capability Profile | `/m/ie/resource-profiling` | 2 | 5 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Item & Process Grouping

`/m/ie/item-and-process-group`

**ট্যাব: Records 17**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Which components and parts make up each garment item, in printed order | search |  | Search 17 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Garments Item Group, Garments Item, Components/Parts Name, Component SL, Process Group?, GMT Body?, Status, ACTION
- বাটন/অ্যাকশন: New item-part mapping, Export, Garments Item Group, Garments Item, Components/Parts Name, Component SL, Process Group?, GMT Body?, Status, Edit

**ট্যাব: New item-part mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Garments Item* | select | ✔ | Select…, Knit Tops — Basic T-Shirt, Knit Tops — Polo Shirt, Knit Tops — Pullover Hoodie, Bottoms — Twill Trouser |
| Component SL* | number | ✔ |  |
| Include? | checkbox |  |  |
- সেকশন: New item-part mapping
- বাটন/অ্যাকশন: New item-part mapping, Select…, Reset, Save item-part mapping

**ডায়ালগ: New item-part mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Garments Item* | select | ✔ | Select…, Knit Tops — Basic T-Shirt, Knit Tops — Polo Shirt, Knit Tops — Pullover Hoodie, Bottoms — Twill Trouser |
| Component SL* | number | ✔ |  |
| Include? | checkbox |  |  |
- খালি Save-এ মেসেজ: Garments Item is required.

#### Floor-Wise Target Setup

`/m/ie/ie-floor-wise-target-setting-options`

**ট্যাব: IE Target Setting Options**
- সেকশন: IE Target Setting Options
- বাটন/অ্যাকশন: IE Target Setting Options, Add, Revert, Save

**ডায়ালগ: Add**
- খালি Save-এ মেসেজ: Every line needs a user.

#### Finishing Floor Access Setup

`/m/ie/finishing-floor-access-setting-options`

**ট্যাব: Finishing Floor Access Setting Options**
- সেকশন: Finishing Floor Access Setting Options
- বাটন/অ্যাকশন: Finishing Floor Access Setting Options, Add, Revert, Save

**ডায়ালগ: Add**
- খালি Save-এ মেসেজ: Every line needs a user.

#### Learning Curve Model

`/m/ie/learning-curve`

**ট্যাব: Planning Learning Curve**
- সেকশন: Planning Learning Curve
- বাটন/অ্যাকশন: Planning Learning Curve, New learning curve point, Fleece 0, Pique 0, Single Jersey 34, Twill 0

**ট্যাব: New learning curve point**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fabric Class* | select | ✔ | Select…, Fleece, Pique, Single Jersey, Twill |
| Prod. Type* | select | ✔ | Select…, Basic (0-12), Semi Critical (12.01-20), Critical (20.01-35), Highly Critical (35.01-99) |
| Day* | number | ✔ |  |
| Efficiency %* | number | ✔ |  |
- সেকশন: New learning curve point
- বাটন/অ্যাকশন: Planning Learning Curve, New learning curve point, Reset, Save learning curve point

**ডায়ালগ: New learning curve point**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fabric Class* | select | ✔ | Select…, Fleece, Pique, Single Jersey, Twill |
| Prod. Type* | select | ✔ | Select…, Basic (0-12), Semi Critical (12.01-20), Critical (20.01-35), Highly Critical (35.01-99) |
| Day* | number | ✔ |  |
| Efficiency %* | number | ✔ |  |
- খালি Save-এ মেসেজ: Efficiency % is required.

#### Responsible Owner Mapping

`/m/ie/responsible-dept-person-mapping`

**ট্যাব: Responsible Dept-Person Mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search departments | search |  | Search… |
- সেকশন: Responsible Dept., Responsible Person
- বাটন/অ্যাকশন: Responsible Dept-Person Mapping, New department, Industrial Engineering 0, Production 0, Quality 0, Maintenance 0, Planning 0

**ডায়ালগ: New department** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Department* | text | ✔ |  |
| Code | text |  |  |
| SL | number |  |  |

#### Resource Capability Profile

`/m/ie/resource-profiling`

**ট্যাব: Product Criticality-Line & Preferred Gmt Category**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search criticalitys | search |  | Search… |
- সেকশন: Product Criticality, Line List
- বাটন/অ্যাকশন: New line profile, Basic (0-12) 0, Semi Critical (12.01-20) 0, Critical (20.01-35) 0, Highly Critical (35.01-99) 0

**ট্যাব: New line profile**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine Type | select |  | Select…, Plain Machine (SNLS), Overlock, Flatlock, Button Attach, Button Hole, Bartack |
| Operators* | number | ✔ |  |
| ASO* | number | ✔ |  |
| Machines* | number | ✔ |  |
- সেকশন: New line profile
- বাটন/অ্যাকশন: New line profile, Select…, Reset, Save line profile

**ডায়ালগ: New line profile**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine Type | select |  | Select…, Plain Machine (SNLS), Overlock, Flatlock, Button Attach, Button Hole, Bartack |
| Operators* | number | ✔ |  |
| ASO* | number | ✔ |  |
| Machines* | number | ✔ |  |
- খালি Save-এ মেসেজ: Line is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/WorkStudy`, সব টেবিল `ws_` নামে।
- **অন্য ERP মডিউল থেকে নেওয়া:**
  - Merchandise Desk: Garment Part Master (Process Group? আর GMT Body? ফ্ল্যাগ এখান থেকেই আসে), buyer।
  - Capacity Planning: প্ল্যানিং লাইন (Resource Capability Profile-এর জন্য)।
- **প্ল্যাটফর্মের অংশ, যা সবাই ব্যবহার করে:** Company Management-এর ফ্লোর, আর কোম্পানির ইউজার।
- গার্মেন্টস আইটেম (Knit Tops → Basic T-Shirt …) Brickland রাখে তার Garments Item Specification স্ক্রিনে। সেটা এখনো বানানো হয়নি (14/04), তাই আপাতত নিজস্ব `ws_garment_items` টেবিল আছে। Item & Process Grouping-এর "Garments Items" ট্যাব থেকে এগুলো যোগ বা বদলানো যায়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_04_000100_create_work_study_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `ws_garment_items` | item_group, name, active |
| `ws_item_parts` | garment_item_id, garment_part_id → garment_parts, component_sl, included |
| `ws_target_access` | user_id, floor_ids (json), target_right (R/W), product_right (R/W), is_admin |
| `ws_finishing_access` | user_id, node_type (IRON/POLY), floor_ids (json) |
| `ws_learning_points` | fabric_class (FLEECE/PIQUE/SINGLE_JERSEY/TWILL), prod_type (BASIC/SEMI_CRITICAL/CRITICAL/HIGHLY_CRITICAL), day, efficiency |
| `ws_responsible_depts` | name, code, sl |
| `ws_responsible_persons` | dept_id, user_id, floor_ids (json), all_floors, buyer_ids (json), all_buyers |
| `ws_line_profiles` | planning_line_id → planning_lines, machine_type (ফাঁকা = পুরো লাইন), operators, aso, machines |
| `ws_criticality_lines` | criticality, planning_line_id, runs, preferred_group (Knit Tops / Bottoms / Outerwear, আর নতুন কোনো আইটেম গ্রুপ) |

## ৫. Workflow / Status ও Business Rules

- **Item & Process Grouping:**
  - একটা আইটেমে একটা পার্ট একবারই থাকে, তবে একটা পার্ট অনেক আইটেমে থাকতে পারে।
  - আইটেম না দিলে Brickland-এর মেসেজ আসে: "Garments Item is required."
  - যে আইটেমে পার্ট ম্যাপ করা আছে, সেটা মোছা যায় না।
  - একই গ্রুপে একই নামের আইটেম দুবার রাখা যায় না।
- **Floor-Wise Target Setup:**
  - পুরো গ্রিড একবারে সেভ হয়।
  - একজন ইউজার একবারই থাকেন ("A user appears once.")।
  - প্রতিটা লাইনে ইউজার লাগে ("Every line needs a user.")।
- **Finishing Floor Access Setup:**
  - আয়রন আর পলি আলাদাভাবে দেওয়া হয়, তাই একই ইউজারের প্রতিটা নোডের জন্য একটা লাইন থাকে।
  - প্রতিটা লাইনে অন্তত একটা ফ্লোর লাগে।
- **Learning Curve Model:**
  - প্রতিদিনের জন্য একটা সারি; ফ্যাব্রিক ক্লাস আর প্রোডাক্টের ধরন মিলিয়ে একটা দিন একবারই থাকে।
  - এফিসিয়েন্সি না দিলে Brickland-এর মেসেজ আসে: "Efficiency % is required."
  - এফিসিয়েন্সি শতাংশে, ১০০-র বেশি চলে না।
  - **Planning Learning Curve ট্যাব:** প্রতিটা ফ্যাব্রিক ক্লাস একটা অ্যাকর্ডিয়ন। খুললে একটা editable গ্রিড আসে: সারিতে চারটা জটিলতার মাত্রা, কলামে Day-1 থেকে Day-10 (কার্ভ লম্বা হলে আরও)। Save পুরো ফ্যাব্রিক ক্লাসের কার্ভ একবারে লেখে, আর কোনো ঘর ফাঁকা রাখলে সেই দিনটা কার্ভ থেকে বাদ যায়।
- **Responsible Owner Mapping:**
  - শুরুতে Brickland-এর পাঁচটা বিভাগ থাকে।
  - একটা বিভাগ বাছাই করলে তার পুরো গ্রিড আসে, আর Save সেটা একবারে লেখে।
  - একজন ব্যক্তি একটা বিভাগে একবারই থাকেন ("Every line needs an employee" না মানলে সেভ হয় না)।
  - "সব ফ্লোর" আর "সব বায়ার" আলাদা ফিল্ড হিসেবে রাখা হয়। তাই ফাঁকা তালিকা মানে "এখনো পূরণ হয়নি", "সবাই" নয় (Brickland-এর মতো)।
- **Resource Capability Profile:**
  - একটা লাইনে প্রতিটা মেশিনের ধরনের জন্য একটাই প্রোফাইল থাকে। মেশিনের ধরন ফাঁকা রাখলে সেটা পুরো লাইনের লোকবল।
  - লাইন না দিলে Brickland-এর মেসেজ আসে: "Line is required."
  - **Product Criticality-Line ট্যাব:** একটা মাত্রা বাছাই করলে সব সক্রিয় প্ল্যানিং লাইনের গ্রিড আসে: Unit || Floor, Line, "Runs this band?" টিক আর একটা Preferred Gmt Category। Save পুরো গ্রিড একবারে লেখে।
  - টিক না দেওয়া লাইনকে কাজ থেকে বাদ দেওয়া হয় না, শুধু এই মাত্রার জন্য পছন্দের নয়।
  - বামের তালিকার সংখ্যা মানে কতগুলো লাইনে টিক আছে।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-garment-items|ws-item-parts|ws-learning-points|ws-line-profiles}`।
- **`/api/v1/workstudy/...`:**
  - `lookups`
  - `target-access` আর `finishing-access` (GET, আর পুরো গ্রিডের জন্য PUT)
  - `departments`; `departments/{dept}/persons` (GET, আর পুরো গ্রিডের জন্য PUT); `persons`
  - `criticality`; `criticality/{band}` (GET, আর পুরো গ্রিডের জন্য PUT)
  - `learning-curve/{fabricClass}` (PUT): একটা ফ্যাব্রিক ক্লাসের পুরো কার্ভ
- **পারমিশন:** নতুন `workstudy.master.view` আর `workstudy.master.manage`।
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়।
  - LINE_CHIEF পায় শুধু view।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/work-study/<screen>`। সাইডবারে INDUSTRIAL ENGINEERING → Work Study → MASTER DATA।
- **Item & Process Grouping:** Records, New item part mapping, Garments Items।
- **Floor-Wise Target Setup আর Finishing Floor Access Setup:** Records, আর Brickland-এর মতো একটা editable গ্রিড (Add / Export / Revert / Save, Unsaved চিহ্ন, ফ্লোর ট্যাগ)।
- **Learning Curve Model:** Records (শুধু Edit), Planning Learning Curve, New learning curve point (Efficiency-র ঘরে %)।
- **Responsible Owner Mapping:** Records (শুধু দেখা), আর বাম-ডান প্যানেল।
  - বামে বিভাগের তালিকা, সংখ্যা আর New department বাটনসহ।
  - ডানে "Responsible Person — <বিভাগ>" গ্রিড, Export আর Add সহ। কলাম: SL, Floor(s), AllFlr?, Buyer Account(s), AllByr?, Selected Employee, Search Employee, মুছে ফেলা।
- **Resource Capability Profile:** Records (শুধু Edit), Product Criticality-Line & Preferred Gmt Category (বাম-ডান প্যানেল, "Line List — <মাত্রা>" গ্রিড আর Export), New line profile।
- **কোন ট্যাবে খোলে (Brickland-এর মতো):**
  - টার্গেট আর ফিনিশিং খোলে নিজেদের গ্রিড ট্যাবে।
  - Learning Curve খোলে Planning Learning Curve ট্যাবে।
  - Responsible খোলে Mapping ট্যাবে।
  - Resource Capability খোলে Criticality ট্যাবে।
  - Item & Process Grouping খোলে Records-এ।
- **গ্রিডের আচরণ:**
  - কিছু না বদলালে Save আর Revert নিষ্ক্রিয় থাকে।
  - খালি গ্রিডে "No grants yet / Add the first one with Add above, then Save." দেখায়।
  - ফিনিশিং গ্রিডে Export নেই, টার্গেট গ্রিডে আছে (Brickland-এর মতো)।
- **নতুন shared component:**
  - `MultiPick`: একাধিক মান বাছার জন্য, মানগুলো ট্যাগ হিসেবে দেখায়।
  - `edit-grid` (`EditGridTable`)
  - `AccessGrid`
  - `SideGrid`
  - `CurveEditor`
- **EntryForm-এ নতুন:** ঘরের ভেতরে একক দেখানোর জন্য `suffix`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_11_04_000100_create_work_study_masters.php`
- `app/Modules/WorkStudy/Models/WsGarmentItem.php`
- `app/Modules/WorkStudy/Models/WsItemPart.php`
- `app/Modules/WorkStudy/Models/WsTargetAccess.php`
- `app/Modules/WorkStudy/Models/WsFinishingAccess.php`
- `app/Modules/WorkStudy/Models/WsLearningPoint.php`
- `app/Modules/WorkStudy/Models/WsResponsibleDept.php`
- `app/Modules/WorkStudy/Models/WsResponsiblePerson.php`
- `app/Modules/WorkStudy/Models/WsLineProfile.php`
- `app/Modules/WorkStudy/Models/WsCriticalityLine.php`
- `app/Modules/WorkStudy/Support/WorkStudyDefaults.php`
- `app/Modules/WorkStudy/Resources/WorkStudyResource.php`
- `app/Modules/WorkStudy/Resources/GarmentItemResource.php`
- `app/Modules/WorkStudy/Resources/ItemPartResource.php`
- `app/Modules/WorkStudy/Resources/LearningPointResource.php`
- `app/Modules/WorkStudy/Resources/LineProfileResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WorkStudyApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/erp/multi-pick.jsx`
- `frontend/src/components/workstudy/access-grid.jsx`
- `frontend/src/components/workstudy/edit-grid.jsx`
- `frontend/src/components/workstudy/side-grid.jsx`
- `frontend/src/components/workstudy/curve-editor.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `lang/en/workstudy.php`
- `lang/bn/workstudy.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/WorkStudy/WorkStudyMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `WorkStudyMasterTest` ৫/৫ (৯৩টা assertion):
  - **আইটেম ও পার্ট:** শুরুতে চারটা আইটেম থাকে। আইটেম ছাড়া Brickland-এর মেসেজ আসে। একই পার্ট দুবার দেওয়া যায় না। ম্যাপ করা আইটেম মোছা যায় না। একই গ্রুপে একই নাম চলে না, অন্য গ্রুপে চলে।
  - **অ্যাক্সেস গ্রিড:** একই ইউজার দুবার দিলে আর ইউজার ছাড়া লাইন দিলে Brickland-এর মেসেজ আসে। গ্রিড সেভ হয়, আবার খালিও করা যায়। ফিনিশিংয়ে একই নোড দুবার বা ফ্লোর ছাড়া লাইন চলে না; আয়রন আর পলি দুটোই চলে।
  - **লার্নিং কার্ভ:** এফিসিয়েন্সি ছাড়া বা ১০০-র বেশি দিলে মেসেজ আসে। একই দিন দুবার চলে না। পুরো কার্ভ একবারে সেভ হয় আর আগেরটা বদলে দেয়; গ্রিডে একই দিন দুবার বা অজানা ফ্যাব্রিক ক্লাস চলে না।
  - **দায়িত্বপ্রাপ্ত ব্যক্তি ও লাইনের সক্ষমতা:** পাঁচটা বিভাগ থাকে। গ্রিডে একই ব্যক্তি দুবার বা কর্মী ছাড়া লাইন চলে না। AllByr? দিলে বায়ার তালিকা ফাঁকা থাকে। সংখ্যা ঠিকমতো দেখায়। লাইন ছাড়া মেসেজ আসে। একই লাইনে একই মেশিন-ধরনের প্রোফাইল দুবার চলে না। মাত্রার গ্রিডে সব লাইন আসে। অজানা ক্যাটাগরি চলে না। টিক তুলে দিলে সংখ্যা কমে। অজানা মাত্রায় 404।
  - **পারমিশন:** ফ্যাক্টরি ম্যানেজার বদলাতে পারে। লাইন চিফ দেখতে পারে, বদলাতে পারে না। স্টোর ম্যানেজার কিছুই পায় না।
- **Brickland-এর সাথে মেলানো:** একটা read-only লাইভ probe ([`probe.mjs`](../tools/erp_crawler/probe.mjs), plan `plans/work_study.json`) দিয়ে প্রতিটা ট্যাব খোলা হয়েছে, বামের তালিকা থেকে আইটেম বাছাই করা হয়েছে, আর Edit খোলা হয়েছে। লেখা, বাটন, কলাম, select-এর অপশন, কোন ট্যাবে খোলে আর ফুটারের লেখা সেখান থেকে নেওয়া।
- **ব্রাউজার (dev):** ছয়টা স্ক্রিনেই সেভ হয়েছে। কার্ভ গ্রিড, মাত্রার গ্রিড আর দায়িত্বপ্রাপ্ত ব্যক্তির গ্রিডও সেভ করে দেখা হয়েছে।
  - Basic T-Shirt → Body।
  - টার্গেট গ্রিডে একজন ইউজার আর একটা ফ্লোর।
  - Single Jersey/Basic: দিন ১ = ৪৫%, দিন ২ = ৬০%।
  - IE বিভাগে একজন দায়িত্বপ্রাপ্ত ব্যক্তি।
  - একটা লাইন প্রোফাইল।
  - কনসোলে কোনো error নেই।

## ১০. খোলা প্রশ্ন

- **গার্মেন্টস আইটেম কোথায় থাকবে:** Garments Item Specification (14/04) বানানোর সময় `ws_garment_items` সেখানে সরবে, নাকি সেটাই এই টেবিল ব্যবহার করবে, তখন ঠিক হবে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- Brickland-এ ছয়টা স্ক্রিনই খালি (Records 0)। শুধু দুটো তালিকা শুরু থেকে থাকে: চারটা গার্মেন্টস আইটেম আর পাঁচটা দায়িত্বপ্রাপ্ত বিভাগ।
- **Brickland-এর সাথে তফাত:**
  - গার্মেন্টস আইটেম রাখার জন্য একটা "Garments Items" ট্যাব যোগ হয়েছে, কারণ Garments Item Specification এখনো বানানো হয়নি।
  - কর্মী (Selected/Search Employee): HR & Payroll এখনো বানানো হয়নি, তাই আপাতত কোম্পানির ইউজার। HR বানানোর পরে কর্মীর তালিকায় সরবে।
- **প্রথম সংস্করণে কী ভুল ছিল (ব্যবহারকারী ধরিয়ে দিয়েছেন, ২০২৬-১০-১১):**
  - Learning Curve আর Resource Capability-র বিশেষ ট্যাব, আর Responsible Owner-এর ডান প্যানেল, এক-একটা করে যোগ করার তালিকা হিসেবে বানানো হয়েছিল। Brickland-এ এগুলো পুরো গ্রিড একবারে সেভ করে।
  - এখন লাইভ probe দিয়ে প্রতিটা অবস্থা দেখে মেলানো হয়েছে।

