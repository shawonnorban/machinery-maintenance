# Target & Feeding

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/03`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Excess Yarn Allowance | `/m/ie/excess-yarn-target` | 3 | 4 | 1 |
| 2 | Style Feeding & Change-over | `/m/ie/style-feeding-sco` | 3 | 19 | 1 |
| 3 | Garment Target Setting | `/m/ie/gmt-target-setting` | 6 | 29 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Excess Yarn Allowance

`/m/ie/excess-yarn-target`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Excess Yarn Target, New excess yarn target

**ট্যাব: Excess Yarn Target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
- সেকশন: Excess Yarn Target
- বাটন/অ্যাকশন: Excess Yarn Target, New excess yarn target, -- Select Year\|Month --, -- Select --, Show, Save Target

**ট্যাব: New excess yarn target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Target (Qty/Kg)* | number | ✔ |  |
- সেকশন: New excess yarn target
- বাটন/অ্যাকশন: Excess Yarn Target, New excess yarn target, Select…, Reset, Save excess yarn target

**ডায়ালগ: New excess yarn target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Target (Qty/Kg)* | number | ✔ |  |
- খালি Save-এ মেসেজ: Target is required.

#### Style Feeding & Change-over

`/m/ie/style-feeding-sco`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Style Changeover Dashboard, New style change-over

**ট্যাব: Style Changeover Dashboard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Line | select |  |  |
| From | date |  |  |
| To Date | date |  |  |
| Buyer | select |  | LPP (LPP) |
| W.Style/Job# | select |  |  |
- সেকশন: Style Changeover Dashboard
- বাটন/অ্যাকশন: Style Changeover Dashboard, New style change-over, Search, Finalize, Reopen, Remove

**ট্যাব: New style change-over**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date* | date | ✔ |  |
| New Buyer | select |  | Select…, LPP (LPP) |
| New W.Style/Job#* | select | ✔ |  |
| New Gmt. Item | text |  |  |
| Previous Buyer | select |  | Select…, LPP (LPP) |
| Previous W.Style/Job# | select |  |  |
| Previous Gmt. Item | text |  |  |
| Previous Output On | date |  |  |
| SCO (minutes)* | number | ✔ |  |
| FT (hours) | number |  |  |
| Remarks | textarea |  |  |
| Finalize | checkbox |  |  |
- সেকশন: New style change-over
- বাটন/অ্যাকশন: Style Changeover Dashboard, New style change-over, Select…, Reset, Save style change-over

**ডায়ালগ: New style change-over**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date* | date | ✔ |  |
| New Buyer | select |  | Select…, LPP (LPP) |
| New W.Style/Job#* | select | ✔ |  |
| New Gmt. Item | text |  |  |
| Previous Buyer | select |  | Select…, LPP (LPP) |
| Previous W.Style/Job# | select |  |  |
| Previous Gmt. Item | text |  |  |
| Previous Output On | date |  |  |
| SCO (minutes)* | number | ✔ |  |
| FT (hours) | number |  |  |
| Remarks | textarea |  |  |
| Finalize | checkbox |  |  |
- খালি Save-এ মেসেজ: Line is required.

#### Garment Target Setting

`/m/ie/gmt-target-setting`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target

**ট্যাব: Sewing Target Setting**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
| Copy From | date |  |  |
- সেকশন: Sewing Target Setting
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target, Refresh, Save, Start Copy

**ট্যাব: Production Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Line | select |  |  |
- সেকশন: Production Entry
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target, Refresh, Save

**ট্যাব: Production Plans**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Line | select |  |  |
- সেকশন: Production Plans
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target, Refresh, Save

**ট্যাব: NPT Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
| Line | select |  |  |
- সেকশন: NPT Entry
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target, Refresh, Save

**ট্যাব: New sewing target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Order/Style | text |  |  |
| Used OP* | number | ✔ |  |
| Used ASO* | number | ✔ |  |
| Layout OP | number |  |  |
| Layout ASO | number |  |  |
| SMV* | number | ✔ |  |
| T.Effi* | number | ✔ |  |
| WH* | number | ✔ |  |
| WH Range From | number |  |  |
| WH Range To | number |  |  |
| WIP[RFI] | number |  |  |
| Prod Gen Hr | number |  |  |
| Prod OT Hr | number |  |  |
| Is Merged? | checkbox |  |  |
| Is Single? | checkbox |  |  |
| Is Off? | checkbox |  |  |
- সেকশন: New sewing target
- বাটন/অ্যাকশন: Sewing Target Setting, Production Entry, Production Plans, NPT Entry, New sewing target, Select…, Reset, Save sewing target

**ডায়ালগ: New sewing target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Order/Style | text |  |  |
| Used OP* | number | ✔ |  |
| Used ASO* | number | ✔ |  |
| Layout OP | number |  |  |
| Layout ASO | number |  |  |
| SMV* | number | ✔ |  |
| T.Effi* | number | ✔ |  |
| WH* | number | ✔ |  |
| WH Range From | number |  |  |
| WH Range To | number |  |  |
| WIP[RFI] | number |  |  |
| Prod Gen Hr | number |  |  |
| Prod OT Hr | number |  |  |
| Is Merged? | checkbox |  |  |
| Is Single? | checkbox |  |  |
| Is Off? | checkbox |  |  |
- খালি Save-এ মেসেজ: SMV must be greater than zero.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy`, নতুন টেবিল `ws_` নামে।
- **Capacity Planning (12/02) থেকে নেওয়া:** ফ্লোর আর লাইন (`planning_lines`)। লাইন URL-এ যায় তার কোড দিয়ে।
- **Merchandise থেকে নেওয়া:** বায়ার আর অর্ডার (W.Style/Job#: "অর্ডার নং — স্টাইল")।
- **14/05 থেকে নেওয়া:**
  - শিটের কার্ডের অংশগুলো (`costing.jsx`)।
  - **Daily Line Cost এখন এই টার্গেট শিট পড়ে:** `WsCosting::loaded()` সেদিনের টার্গেট শিট থাকলে সেখান থেকে অর্ডার, SMV, FOB, OP, ASO, WH আর Target নেয়। না থাকলে 12/02-এর লাইন প্ল্যান থেকে।
- **বিভাগ:** `departments`। Excess Yarn আর NPT-এর বাইরের বিভাগ-তালিকা।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000300_create_work_study_target_feeding`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_yarn_targets` | period, department_id, target_qty | company + period + department |
| `ws_changeovers` | floor_id, planning_line_id, changeover_date, new_buyer_id, new_order, new_gmt_item, prev_buyer_id, prev_order, prev_gmt_item, prev_output_on, sco_minutes, ft_hours, remarks, finalized, created_by | — |
| `ws_sewing_targets` | target_date, floor_id, planning_line_id, buyer_id, order_style, gmt_item, ship_date, fob, smv, is_car, day_run, lo_effi, t_effi, wh, wh_from, wh_to, used_op, used_aso, layout_op, layout_aso, wip_rfi, plan, **target_gen** (হিসাব করা), target_ot, prod_gen, prod_ot, input_min, prod_min, ttl_value, is_merged, is_single, is_off, sort_order | — |
| `ws_line_allocations` | floor_id, planning_line_id, buyer_id, order_style, style, item, smv, input_date, plan_start, plan_end, allocation, production, in_target, status (OPEN/RUNNING/CLOSED) | — |
| `ws_npt_entries` | npt_date, floor_id, planning_line_id, order_style, reason_type, reason_details, responsible_dept, hour_slots (JSON), manpower, minutes, **npt** (হিসাব করা) | — |

## ৫. Workflow / Status ও Business Rules

- **Excess Yarn Allowance:**
  - **New excess yarn target:** কোম্পানি, বছর, মাস, বিভাগ আর টার্গেট (Qty/Kg)। টার্গেট না দিলে Brickland-এর মেসেজ আসে: "Target is required."। একটা বিভাগের একটা মাসে একটাই লাইন।
  - **Excess Yarn Target শিট:**
    - কোম্পানি আর মাস (2026|October) বেছে Show চাপলে সব বিভাগ আসে।
    - ঘর খালি করলে সেই বিভাগের টার্গেট বাদ যায়। খালি মানে "কোনো অনুমতি ঠিক হয়নি", শূন্য নয়।
    - Copy From অন্য মাস থেকে গ্রিড বানায়, কিছু লেখে না। Save Target চাপলে বাছা মাসে লেখে।
- **Style Feeding & Change-over:**
  - **New style change-over:**
    - ফ্লোর, লাইন (বাছা ফ্লোরের), তারিখ, নতুন আর আগের বায়ার / W.Style/Job# / Gmt. Item, আগের আউটপুটের তারিখ, SCO (মিনিট), FT (ঘণ্টা), মন্তব্য আর Finalize।
    - লাইন না দিলে Brickland-এর মেসেজ আসে: "Line is required."।
    - নতুন আর আগের অর্ডার এক হতে পারে না। আগের আউটপুটের তারিখ change-over-এর পরে হতে পারে না।
  - **Records:** Feeding Start = লাইনের টার্গেট শিটে নতুন অর্ডার প্রথম যেদিন আসে। Feeding End = প্রথম যেদিন উৎপাদন হয়। Status Open বা Finalized।
  - **Dashboard:**
    - ফ্লোর, লাইন, তারিখের সীমা, বায়ার আর স্টাইল দিয়ে খোঁজা যায়।
    - টিক দেওয়া সারি Finalize, Reopen বা Remove করা যায়।
    - চূড়ান্ত সারি মোছা যায় না ("A finalised change-over can be reopened, but not removed.")।
    - নিচে সারাংশ থাকে: change-over সংখ্যা, নষ্ট মিনিট, চূড়ান্ত হয়নি এমন সংখ্যা।
- **Garment Target Setting:**
  - **Target[Gen Hr]** = (Used OP + ASO) × WH × ৬০ × T.Effi ÷ SMV, পূর্ণ পিসে। Brickland-এর probe: ৩৬ জন, ১০ ঘণ্টা, ৬৫%, ৮.৫ SMV → ১,৬৫১। সেভের সময় হিসাব হয়, কখনো লেখা হয় না। TTL Target = Gen + OT, TTL Prod = Gen + OT।
  - **New sewing target:** SMV শূন্য হলে Brickland-এর মেসেজ আসে: "SMV must be greater than zero."। Records-এ অবস্থা দেখায়: Behind (উৎপাদন < টার্গেট), Achieved, বা Off।
  - **Sewing Target Setting শিট:**
    - ফ্লোরের প্রতিটা লাইন আর তার অর্ডারগুলো, প্রতি লাইনে দ্বিতীয় অর্ডারের জন্য একটা খালি সারি।
    - খালি সারি কিছু লেখে না। সেভ করা সারি খালি করলে মুছে যায়।
    - কোনো লাইনে অর্ডার আছে কিন্তু SMV নেই, এমন হলে আটকায়।
    - **Start Copy:** অন্য দিনের সেটআপ আনে (অর্ডার, SMV, manpower, ঘণ্টা, efficiency), উৎপাদন শূন্য রেখে, আর D'R এক বাড়িয়ে। আজ যে লাইনে অর্ডার আছে সেগুলো ছোঁয় না।
    - নিচে Brickland-এর সারাংশ থাকে: uOP 30[32] · uASO 6[6] · Target · Achiv · InputMin · ProdMin · Value।
  - **Production Entry:**
    - একটা লাইনের বরাদ্দ, সাথে নতুন বরাদ্দের জন্য তিনটা খালি সারি।
    - Planned = শুরু → শেষ। Achieved = "৯৭০ · ১৯.৪% [৪০৩০]" (উৎপাদন · বরাদ্দের শতাংশ · বাকি)।
    - বরাদ্দে অর্ডার লাগে। শেষ তারিখ শুরুর আগে হতে পারে না।
  - **Production Plans:**
    - একই বরাদ্দ। In Trgt? টিক আর Set Status (Open/Running/Closed) বদলানো যায়।
    - Closed হলে টার্গেট থেকে নামে।
    - নিচে সারাংশ থাকে: Allocation, Load (বাকি), Un-Load (তৈরি, শতাংশসহ)।
  - **NPT Entry:**
    - একটা লাইনের একদিনের থামা: অর্ডার, কারণ (Brickland-এর ১২টা), বিবরণ, দায়ী বিভাগ (IE/Production/Quality/Maintenance/Planning), Hours Slot (১–১১, একাধিক বাছা যায়), manpower আর মিনিট।
    - NPT = manpower × মিনিট। Brickland-এর probe: ৪ × ৩০ = ১২০।
    - পাঁচটা খালি সারি থাকে। পূরণ করা সারিতে কারণ, লোক, মিনিট আর ঘণ্টা লাগে।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-yarn-targets|ws-changeovers|ws-sewing-targets}`।
- **নিজস্ব endpoint** (`WsTargetApiController`):
  - `GET /workstudy/targets/options`
  - `GET|PUT /workstudy/targets/yarn?period=[&copy=]`
  - `GET /workstudy/targets/changeovers?floor=&line=&from=&to=&buyer=&order=`, `POST …/changeovers/action`
  - `GET|PUT /workstudy/targets/sheet?floor=&date=`, `POST …/sheet/copy`
  - `GET|PUT /workstudy/targets/allocations?floor=&line=[&view=plans]`, `PUT …/plans`
  - `GET|PUT /workstudy/targets/npt?floor=&line=&date=`
  - `GET /workstudy/targets/export/{targets|entry|plans|npt}/{pdf|xlsx}`
- **পারমিশন:** নতুন `workstudy.target.view` আর `workstudy.target.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF পায় শুধু দেখার অনুমতি, কারণ ফ্লোর দিনের টার্গেটে চলে।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Target & Feeding-এ তিনটা স্ক্রিন (Item Specification-এর আগে, Brickland-এর ক্রমে)।
- **শিটের কম্পোনেন্ট** (`targets.jsx`): YarnSheet, ChangeoverBoard, TargetSheet, AllocationSheet (Production Entry / Plans), NptSheet।
- **URL:** মাস, ফ্লোর, তারিখ আর লাইন URL-এ থাকে, রিলোড ছাড়া।
- **PDF আর Excel:** সার্ভারের `TablePdf`, টার্গেট শিট, Production Entry, Production Plans আর NPT-এর জন্য (Brickland-এ যেখানে আছে)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000300_create_work_study_target_feeding.php`
- `app/Modules/WorkStudy/Models/WsYarnTarget.php`
- `app/Modules/WorkStudy/Models/WsChangeover.php`
- `app/Modules/WorkStudy/Models/WsSewingTarget.php`
- `app/Modules/WorkStudy/Models/WsLineAllocation.php`
- `app/Modules/WorkStudy/Models/WsNptEntry.php`
- `app/Modules/WorkStudy/Support/WsTargets.php`
- `app/Modules/WorkStudy/Support/WsCosting.php` (`loaded()`: Daily Line Cost টার্গেট শিট পড়ে)
- `app/Modules/WorkStudy/Resources/YarnTargetResource.php`
- `app/Modules/WorkStudy/Resources/ChangeoverResource.php`
- `app/Modules/WorkStudy/Resources/SewingTargetResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsTargetApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/tf-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/targets.jsx`
- `frontend/src/app/api/work-study/targets/[sheet]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`tf.*`)

**Test**
- `tests/Feature/WorkStudy/WorkStudyTargetFeedingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudyTargetFeedingTest` ২/২ (৭৪ assertion):**
  - **Yarn:** টার্গেট ছাড়া মেসেজ আসে। একই বিভাগ-মাস দুবার চলে না। শিটে সব বিভাগ আসে। খালি ঘর বাদ যায়। Copy From লেখে না।
  - **Change-over:** লাইন ছাড়া মেসেজ আসে। Finalize করা যায়। চূড়ান্ত সারি মোছা আটকায় (বোর্ড থেকে আর record থেকে দুই পথেই)। Reopen করলে তারপর মোছা যায়।
  - **টার্গেট:**
    - SMV ছাড়া মেসেজ আসে। ১,৬৫১ টার্গেট, Behind অবস্থা।
    - শিটে লাইন আর খালি সারি আসে। খালি সারিতে দ্বিতীয় অর্ডার যায় (SMV ছাড়া আটকায়, ১২ SMV-তে ৫৭৬)।
    - Start Copy ২টা সারি আনে, উৎপাদন শূন্য রেখে। দ্বিতীয়বার কিছু আনে না।
  - **লাইন কস্টিং:** Order Config আর Line Wise Prod এখন টার্গেট শিট থেকে অর্ডার, স্টাইল, FOB, OP ৩০, ASO ৬, WH ১০ আর Target ১,৬৫১ নেয়।
  - **বরাদ্দ আর প্ল্যান:**
    - Production Entry-তে তিনটা খালি সারি থাকে। অর্ডার ছাড়া বরাদ্দ আটকায়। ১৯.৪% আর ৪,০৩০ বাকি মেলে।
    - Plans-এ In Trgt? সেভ হয়। Closed হলে টার্গেট থেকে নামে।
  - **NPT:** ৫টা খালি সারি। অসম্পূর্ণ সারি আটকায়। ১২০ আর ৫৪০ মেলে।
  - **ফাইল আর পারমিশন:** চারটা ফাইল তৈরি হয়। Line Chief শিট দেখতে পায়, সেভ করতে পারে না।
- **পুরনো টেস্ট আবার:** Work Study-র সব টেস্ট (১৩/১৩, ৩৮২ assertion) পাস।
- **ব্রাউজার (dev, tf-ui1–tf-ui4, ডাটা রাখা আছে):**
  - Yarn: ডিসেম্বর ২০২৬-এ Knitting ৩৫০ আর Dyeing ১২০।
  - Change-over: A-1F-এর LINE05, ১৯ ডিসেম্বর, Order → LPP-2026-000001, SCO ৪০, FT ৩, Finalized।
  - টার্গেট: LINE01-এ LPP-2026-000001, ৩০+৬, ৯.২ SMV, ৬৫%, ৮ ঘণ্টা → ১,২২০, উৎপাদন ৯৫০।
  - বরাদ্দ ১,০৭৩, উৎপাদন ৯৫০ (৮৮.৫%), Running, In Trgt।
  - NPT: Machine Breakdown, ঘণ্টা ৩, ৪ × ৩০ = ১২০।
  - তিনটা PDF (টার্গেট শিট, Plans, NPT) প্রিভিউ করে দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

- **"car" আর "D'R" কলাম:** Brickland-এর শিটে "car" একটা টিক-ঘর আর "D'R" একটা সংখ্যা, কোনো ব্যাখ্যা নেই। Brickland-এর নামেই রাখা হয়েছে (`is_car`, `day_run`)। D'R ধরা হয়েছে লাইনে স্টাইল চলার দিন, Start Copy-তে এক বাড়ে। এটা অনুমান।
- **Feeding Start/End:** Brickland-এর demo-তে দুটোই "—" ছিল। এখানে টার্গেট শিট থেকে বের করা হয় (প্রথম যেদিন শিটে এল, প্রথম যেদিন উৎপাদন হলো)। এটা অনুমান।
- **Production Entry আর টার্গেট শিট:** Brickland বলে "In Trgt? is what puts a plan on the day's target sheet"। এখানে In Trgt? চিহ্ন রাখা হয়, কিন্তু টার্গেট শিটে নিজে থেকে সারি বসায় না। demo-তে এর প্রভাব দেখা যায়নি।
- **Excess Yarn-এর ব্যবহার:** এই টার্গেট কোন রিপোর্ট বা খরচ ফেরত ধরায় ব্যবহার হয়, তা পরের মডিউলে (Knitting/Yarn) দেখা যাবে।
- **Lock-এর সীমা:** টেবিল বাড়তে বাড়তে টেস্টের `migrate:fresh` Postgres-এর `max_locks_per_transaction` (৬৪) ছাড়িয়েছে। dev Postgres-এ এটা ৫১২ করা হয়েছে (`ALTER SYSTEM`)।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৭):**
- তিনটা স্ক্রিনই তৈরি। 14/05-এর Daily Line Cost এখন টার্গেট শিট পড়ে।
- **Brickland প্রোব (tf01–tf05):** লাইভ demo-তে লেখা হয়েছে:
  - একটা excess yarn target (Knitting, অক্টোবর ২০২৬)।
  - একটা change-over (LN-001)।
  - একটা sewing target (৩০+৬, ৮.৫ SMV, ৬৫%, ১০ ঘণ্টা)।
  - একটা বরাদ্দ (৫,০০০, ৯৭০)।
  - একটা NPT (Machine Breakdown, ৪ × ৩০)।
  - সেগুলো থেকে টার্গেট, Achieved আর NPT-এর সূত্র মেলানো হয়েছে।
