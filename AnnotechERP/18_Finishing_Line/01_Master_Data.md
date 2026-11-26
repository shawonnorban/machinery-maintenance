# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `18/01`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Finishing Process Types | `/m/dyeing-finishing/dyeing-finishing-process-type` | 3 | 26 | 1 |
| 2 | Finishing Machine Profile | `/m/dyeing-finishing/dyeing-finishing-machine-profile` | 2 | 37 | 1 |
| 3 | Machine-Process Mapping | `/m/dyeing-finishing/machine-and-process-mapping` | 2 | 19 | 1 |
| 4 | Machine-Wise Target Setup | `/m/dyeing-finishing/df-mc-wise-production-target-setup` | 2 | 21 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Finishing Process Types

`/m/dyeing-finishing/dyeing-finishing-process-type`

**ট্যাব: Process Type List 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Group | select |  | All, Dyeing Finishing |
| WIP Stage | select |  | All, Brushing, Calendering, Compacting, Dewatering, Drying, Heat-Set, Stentering |
| Active? | select |  | All, Yes |
| Search Finishing processes with the group and sub-group they are filed under, th | search |  | Search 8 records… |
| Filter by Process Code | search |  |  |
| Filter by Process Name | search |  |  |
| Filter by Short Name | search |  |  |
| Filter by Sub Group | search |  |  |
| Filter by Link Process | search |  |  |
| Filter by Display Order | search |  |  |
| Filter by Rate | search |  |  |
| Filter by APL % | search |  |  |
| Filter by WIP Stage | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Process Code, Process Name, Short Name, Sub Group, Link Process, Display Order, Rate, APL %, WIP Stage, Status, ACTION
- বাটন/অ্যাকশন: Process Type List 8, Fabric Process Group List, New process type, Clear, Export, Process Code, Process Name, Short Name, Sub Group, Link Process, Display Order, Rate, APL %, WIP Stage, Status, Edit

**ট্যাব: Fabric Process Group List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search groups | search |  | Search… |
- সেকশন: Fabric Process Group List, Sub-Groups
- বাটন/অ্যাকশন: Process Type List 8, Fabric Process Group List, New process type, New Group, Knitting 0 process(es) 0, Yarn Dyeing 0 process(es) 0, Dyeing 0 process(es) 0, Dyeing Finishing 8 process(es) 3, AOP 0 process(es) 0, Yarn Twist 0 process(es) 0

**ট্যাব: New process type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Process Code* | text | ✔ | DF-HS |
| Process Name* | text | ✔ | Heat-Set |
| Short Name* | text | ✔ | HS |
| Fabric Process Group* | select | ✔ | Knitting, Yarn Dyeing, Dyeing, Dyeing Finishing, AOP, Yarn Twist |
| Process Sub-Group | select |  |  |
| Link Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Rate* | number | ✔ |  |
| Allowed Process Loss %* | number | ✔ |  |
| Display Order* | number | ✔ |  |
| Is Active? | checkbox |  |  |
- সেকশন: New process type
- বাটন/অ্যাকশন: Process Type List 8, Fabric Process Group List, New process type, Stentering, Reset, Save process type

**ডায়ালগ: New process type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Process Code* | text | ✔ |  |
| Process Name* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Fabric Process Group* | select | ✔ | Knitting, Yarn Dyeing, Dyeing, Dyeing Finishing, AOP, Yarn Twist |
| Process Sub-Group | select |  |  |
| Link Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Rate* | number | ✔ |  |
| Allowed Process Loss %* | number | ✔ |  |
| Display Order* | number | ✔ |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Fabric Process Group is required.

#### Finishing Machine Profile

`/m/dyeing-finishing/dyeing-finishing-machine-profile`

**ট্যাব: Machine List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All |
| Status | select |  | All |
| Active? | select |  | All |
| Search Finishing machines with their model, capacity, efficiency, serial, year,  | search |  | Search 0 records… |
| Filter by Machine No | search |  |  |
| Filter by Machine Name | search |  |  |
| Filter by Model | search |  |  |
| Filter by Cap. | search |  |  |
| Filter by Efficency | search |  |  |
| Filter by Serial | search |  |  |
| Filter by Year | search |  |  |
| Filter by RPM | search |  |  |
| Filter by Type | search |  |  |
| Filter by Brand | search |  |  |
| Filter by Country | search |  |  |
| Filter by Unit | search |  |  |
| Filter by Location | search |  |  |
| Filter by Sample? | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine No, Machine Name, Model, Cap., Efficency, Serial, Year, RPM, Type, Brand, Country, Unit, Location, Sample?, ACTION
- বাটন/অ্যাকশন: Machine List 0, New machine, Clear, Export, Machine No, Machine Name, Model, Cap., Efficency, Serial, Year, RPM, Type, Brand, Country, Unit, Location, Sample?

**ট্যাব: New machine**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine No* | text | ✔ | MFL-01 |
| Machine Name* | text | ✔ | Stenter-1 (Bruckner, MFL) |
| Model # | text |  |  |
| Brand* | select | ✔ | Fongs, PMM Moon star, Thies, Sclavos, Dilmenler |
| Capacity | number |  |  |
| Location | select |  | Dyeing Shed-1, Dyeing Shed-2, Sample House, Finishing Bay |
| Efficency | number |  |  |
| SL No | text |  |  |
| Machine Type* | select | ✔ | Stenter, Compactor, Dryer, Slitting, Hydro Extractor, Squeezer, Brush, Calender, Tube Heat Set |
| RPM | number |  |  |
| Year | number |  |  |
| Status* | select | ✔ | Running, Idle, Under Maintenance, Breakdown |
| Temp | number |  |  |
| Prod.Floor* | select | ✔ | Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Sample? | checkbox |  |  |
| Key Image | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New machine
- বাটন/অ্যাকশন: Machine List 0, New machine, -- Select Country --, -- Select MoU --, Clear, Save machine

**ডায়ালগ: New machine**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine No* | text | ✔ |  |
| Machine Name* | text | ✔ |  |
| Model # | text |  |  |
| Brand* | select | ✔ | Fongs, PMM Moon star, Thies, Sclavos, Dilmenler |
| Capacity | number |  |  |
| Location | select |  | Dyeing Shed-1, Dyeing Shed-2, Sample House, Finishing Bay |
| Efficency | number |  |  |
| SL No | text |  |  |
| Machine Type* | select | ✔ | Stenter, Compactor, Dryer, Slitting, Hydro Extractor, Squeezer, Brush, Calender, Tube Heat Set |
| RPM | number |  |  |
| Year | number |  |  |
| Status* | select | ✔ | Running, Idle, Under Maintenance, Breakdown |
| Temp | number |  |  |
| Prod.Floor* | select | ✔ | Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Sample? | checkbox |  |  |
| Key Image | text |  |  |
| Attach | file |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Machine Type is required.

#### Machine-Process Mapping

`/m/dyeing-finishing/machine-and-process-mapping`

**ট্যাব: Mapping List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dia Type | select |  | All |
| Process | select |  | All |
| Active? | select |  | All |
| Search Machine and process pairs with the dia they run, the stage a batch enters | search |  | Search 0 records… |
| Filter by Process | search |  |  |
| Filter by Machine No | search |  |  |
| Filter by Machine Name | search |  |  |
| Filter by Dia Type | search |  |  |
| Filter by Batch Status | search |  |  |
| Filter by Next Status | search |  |  |
| Filter by Process Sqeuence | search |  |  |
| Filter by Default? | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Process, Machine No, Machine Name, Dia Type, Batch Status, Next Status, Process Sqeuence, Default?, ACTION
- বাটন/অ্যাকশন: Mapping List 0, New mapping, Clear, Export, Process, Machine No, Machine Name, Dia Type, Batch Status, Next Status, Process Sqeuence, Default?

**ট্যাব: New mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dia Type* | select | ✔ | Open, Tube, Both |
| Machine* | select | ✔ |  |
| Process* | select | ✔ | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Process Sequence* | number | ✔ |  |
| Is Default? | checkbox |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New mapping
- বাটন/অ্যাকশন: Mapping List 0, New mapping, Awaiting Load, Stentering, Clear, Save mapping

**ডায়ালগ: New mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Dia Type* | select | ✔ | Open, Tube, Both |
| Machine* | select | ✔ |  |
| Process* | select | ✔ | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Process Sequence* | number | ✔ |  |
| Is Default? | checkbox |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Machine is required.

#### Machine-Wise Target Setup

`/m/dyeing-finishing/df-mc-wise-production-target-setup`

**ট্যাব: Target List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine | select |  | All |
| Dia Type | select |  | All |
| Active? | select |  | All |
| Search Shift output targeted per finishing machine, by process and dia, with the | search |  | Search 0 records… |
| Filter by Machine No | search |  |  |
| Filter by Machine Name | search |  |  |
| Filter by Process | search |  |  |
| Filter by Dia Type | search |  |  |
| Filter by Effective From | search |  |  |
| Filter by Target / Shift | search |  |  |
| Filter by Shift Hours | search |  |  |
| Filter by Per Hour | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine No, Machine Name, Process, Dia Type, Effective From, Target / Shift, Shift Hours, Per Hour, ACTION
- বাটন/অ্যাকশন: Target List 0, New target, Clear, Export, Machine No, Machine Name, Process, Dia Type, Effective From, Target / Shift, Shift Hours, Per Hour

**ট্যাব: New target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine* | select | ✔ |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Dia Type* | select | ✔ | Select…, Open, Tube, Both |
| Effective From* | date | ✔ |  |
| Target Qty / Shift* | number | ✔ |  |
| Shift Hours* | number | ✔ |  |
| Remarks | textarea |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New target
- বাটন/অ্যাকশন: Target List 0, New target, Reset, Save target

**ডায়ালগ: New target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine* | select | ✔ |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Dia Type* | select | ✔ | Select…, Open, Tube, Both |
| Effective From* | date | ✔ |  |
| Target Qty / Shift* | number | ✔ |  |
| Shift Hours* | number | ✔ |  |
| Remarks | textarea |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Machine is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/Finishing`, সব টেবিল `fin_` নামে।
- **অন্য ERP মডিউল থেকে নেওয়া:**
  - Dye House-এর মেশিন তালিকা (`dye_machine_lists`) থেকে Brand আর Location। লাইভ probe-এ দেখা গেছে, Brickland-এ ফিনিশিং মেশিনের Brand (Fongs, PMM Moon star …) আর Location (Dyeing Shed-1 …) ডাই মেশিনের তালিকার সাথে হুবহু এক। তাই দুই মডিউল একই তালিকা ব্যবহার করে, আর সম্পাদনা হয় Dye House-এর Machine Reference Lists-এ।
  - Merchandise Desk-এর origin country।
- **প্ল্যাটফর্মের অংশ, যা সবাই ব্যবহার করে:**
  - Company Management-এর ফ্লোর।
  - Key Image রাখার জন্য ফাইল সিস্টেম (`StoreFileAttachment`, নতুন attachable `fin_machine_key_image`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_06_000100_create_finishing_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `fin_process_groups` | code, name, sort_order, active |
| `fin_process_subgroups` | group_id, code, name, sort_order, active |
| `fin_process_types` | code, name, short_name, group_id, subgroup_id, link_process_id (নিজের টেবিলে), wip_stage, rate, loss_pct (APL %), display_order, active |
| `fin_machines` | machine_no, name, model, origin_country_id, brand_id, capacity, location_id, mou (BAG…YDS), efficiency, sl_no, machine_type (STENTER…TUBE_HEAT_SET), rpm, year, status (RUNNING/IDLE/UNDER_MAINTENANCE/BREAKDOWN), temperature, floor_id, is_sample, key_image (পাথ), active |
| `fin_machine_processes` | dia_type (OPEN/TUBE/BOTH), machine_id, process_id, sequence, batch_status, next_status, is_default, active |
| `fin_machine_targets` | machine_id, process_id (ফাঁকা = যেকোনো প্রসেস), dia_type, effective_from, target_qty, shift_hours, remarks, active |

WIP stage আর batch/next status-এর তালিকা Brickland-এর মতো ১৪টা: Awaiting Load থেকে At Sub-Contract।

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা (Brickland-এর মতো):**
  - ছয়টা প্রসেস গ্রুপ।
  - Dyeing Finishing-এ তিনটা সাব-গ্রুপ (DF-WET, DF-DRY, DF-SUR)।
  - আটটা প্রসেস, রেট, APL %, WIP ধাপ আর লিংক প্রসেসসহ।
  - প্রথমবার খোলার সময় তৈরি হয়। একসাথে কয়েকটা রিকোয়েস্ট এলেও দুবার হয় না।
- **Finishing Process Types:**
  - কোডে শুধু অক্ষর, সংখ্যা, ডট, ড্যাশ আর আন্ডারস্কোর চলে; সেভের সময় বড় হাতের হয়। কোড ইউনিক।
  - সাব-গ্রুপ অবশ্যই বাছাই করা গ্রুপের হতে হবে। ফর্মে গ্রুপ বাছাই করলে শুধু সেই গ্রুপের সাব-গ্রুপ আসে।
  - একটা প্রসেস নিজের সাথে লিংক হতে পারে না।
  - গ্রুপ না দিলে Brickland-এর মেসেজ আসে।
  - মোছা যায় না, নিষ্ক্রিয় করা হয়।
- **Fabric Process Group List:**
  - সাব-গ্রুপ পুরো গ্রিড একবারে সেভ হয়।
  - যে সাব-গ্রুপের নিচে প্রসেস আছে সেটা মোছা যায় না।
  - কোড অন্য কোনো সাব-গ্রুপের সাথে মিলতে পারে না।
  - New Group দিয়ে নতুন গ্রুপ যোগ করা যায়।
- **Finishing Machine Profile:**
  - মেশিন নং ইউনিক।
  - Capacity-র সীমা ০ থেকে ১,০০,০০০, এফিসিয়েন্সির ০ থেকে ১০০।
  - মেশিনের ধরন না দিলে Brickland-এর মেসেজ আসে।
  - Key Image: পাথ বা লিংক লেখা যায়, অথবা Attach দিয়ে ছবি আপলোড করা যায়। ছবি ফাইল সিস্টেমে প্রাইভেট রাখা হয়, সারিতে থাকে শুধু পাথ।
  - মোছা যায় না।
- **Machine-Process Mapping:**
  - একটা মেশিন প্রতিটা ডায়ায় একটা প্রসেসে একবারই ম্যাপ হয়।
  - Batch Status আর Next Status আলাদা হতে হবে।
  - একটা প্রসেস আর ডায়ায় একটাই ডিফল্ট মেশিন থাকে; নতুন ডিফল্ট দিলে আগেরটা উঠে যায়।
- **Machine-Wise Target Setup:**
  - প্রতিটা মেশিন, প্রসেস (বা যেকোনো), ডায়া আর কার্যকর তারিখে একটাই টার্গেট।
  - টার্গেট বদলানো মানে নতুন তারিখে নতুন সারি।
  - ঘণ্টাপ্রতি = শিফট টার্গেট ÷ শিফটের ঘণ্টা।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{fin-process-types|fin-machines|fin-machine-processes|fin-machine-targets}`।
- **`/api/v1/finishing/...`:**
  - `lookups`
  - `groups` (GET/POST)
  - `groups/{group}` (সাব-গ্রুপ GET/PUT)
  - `key-images` (POST multipart)। Frontend-এ `/api/finishing/key-image` route handler ফাইলটা stream করে পাঠায়, কারণ server action এক মেগাবাইটে আটকে যায়।
- **পারমিশন:** নতুন `finishing.master.view` আর `finishing.master.manage`।
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়।
  - LINE_CHIEF পায় শুধু view।
  - Key Image ফাইল দেখতে লাগে view পারমিশন, আপলোড করতে manage।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/finishing-line/<screen>`। সাইডবারে PRODUCTION → Finishing Line → MASTER DATA।
- চারটা স্ক্রিনই Brickland-এর মতো Records-এ খোলে। প্রতিটার ওপরে Brickland-এর ফিল্টার আছে, আর Records-এ শুধু Edit।
- **Fabric Process Group List:** বাম-ডান প্যানেল।
  - বামে গ্রুপের তালিকা: প্রসেসের সংখ্যা আর সাব-গ্রুপের সংখ্যা, সাথে New Group বাটন।
  - ডানে সাব-গ্রুপের editable গ্রিড।
- **লাইভ probe-এ দেখে নেওয়া:** WIP Stage, Batch/Next Status, Country আর MOU ড্রপডাউনের অপশন, আর ফর্মের ডিফল্ট মান: WIP Stage-এ Stentering, Status-এ Idle, Dia-তে Open, Batch Status-এ Awaiting Load, Next Status-এ Stentering, Sequence-এ 1।
- **Shared kit-এ নতুন (EntryForm):**
  - `optionsFrom`: একটা ঘরের অপশন অন্য ঘরের বাছাইয়ের ওপর নির্ভর করে।
  - `filepath` ধরন: পাথের ঘর আর তার পাশে Attach বাটন।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Finishing/Database/Migrations/2026_11_06_000100_create_finishing_masters.php`
- `app/Modules/Finishing/Models/FinProcessGroup.php`
- `app/Modules/Finishing/Models/FinProcessSubgroup.php`
- `app/Modules/Finishing/Models/FinProcessType.php`
- `app/Modules/Finishing/Models/FinMachine.php`
- `app/Modules/Finishing/Models/FinMachineProcess.php`
- `app/Modules/Finishing/Models/FinMachineTarget.php`
- `app/Modules/Finishing/Support/FinishingDefaults.php`
- `app/Modules/Finishing/Resources/FinishingResource.php`
- `app/Modules/Finishing/Resources/ProcessTypeResource.php`
- `app/Modules/Finishing/Resources/MachineResource.php`
- `app/Modules/Finishing/Resources/MachineProcessResource.php`
- `app/Modules/Finishing/Resources/MachineTargetResource.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinishingApiController.php`
- `app/Modules/Finishing/Routes/api.php`
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/page.js`
- `frontend/src/app/(app)/finishing-line/[screen]/screens.js`
- `frontend/src/app/(app)/finishing-line/[screen]/actions.js`
- `frontend/src/app/api/finishing/key-image/route.js`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/finishing.php`
- `lang/bn/finishing.php`
- `lang/en/erp.php`
- `lang/bn/erp.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Finishing/FinishingMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `FinishingMasterTest` ৩/৩ (৭২টা assertion):
  - **প্রসেস:** শুরুতে আটটা প্রসেস, লিংক আর সাব-গ্রুপসহ। ছয়টা গ্রুপ। গ্রুপ ছাড়া Brickland-এর মেসেজ আসে। অন্য গ্রুপের সাব-গ্রুপ চলে না। ফাঁকা থাকা কোড চলে না; কোড বড় হাতের হয়। নিজের সাথে লিংক চলে না। মোছা যায় না। সাব-গ্রুপ একবারে সেভ হয়, ব্যবহৃতটা থেকে যায়। নতুন গ্রুপ যোগ হয়।
  - **মেশিন, ম্যাপিং, টার্গেট:**
    - Dye House-এর Brand আর Location আসে। মেশিনের ধরন ছাড়া মেসেজ আসে। ১০০%-এর বেশি এফিসিয়েন্সি চলে না। একই নং (ছোট হাতে) চলে না।
    - Key Image আপলোড হয়ে পাথ ফেরত আসে।
    - ম্যাপিংয়ে মেশিন ছাড়া মেসেজ আসে। একই স্ট্যাটাস চলে না। একই মেশিন-প্রসেস-ডায়া দুবার চলে না; অন্য ডায়ায় চলে।
    - টার্গেটে ঘণ্টাপ্রতি হিসাব হয়। একই তারিখে দুবার চলে না; প্রসেস বা তারিখ আলাদা হলে চলে।
  - **পারমিশন:** ফ্যাক্টরি ম্যানেজার বদলাতে পারে। লাইন চিফ দেখতে পারে, ছবি আপলোড করতে পারে না। স্টোর ম্যানেজার কিছুই পায় না।
- **ব্রাউজার (dev):** চারটা স্ক্রিনেই সেভ হয়েছে। কনসোলে কোনো error নেই।
  - নতুন প্রসেস DF-SL; গ্রুপ বাছাইয়ের পরে শুধু সেই গ্রুপের সাব-গ্রুপ এসেছে।
  - Dyeing Finishing-এর সাব-গ্রুপ গ্রিড খোলা হয়েছে।
  - MFL-01 মেশিন যোগ হয়েছে; Attach দিয়ে ছবি আপলোড হয়ে Key Image-এ পাথ বসেছে।
  - Stentering ম্যাপিং আর একটা টার্গেট যোগ হয়েছে।

## ১০. খোলা প্রশ্ন

- **ফিনিশিং মেশিনের ধরনের তালিকা:** Brickland-এ ফিনিশিং মেশিনের ধরন (Stenter, Compactor …) কোন স্ক্রিনে সম্পাদনা হয় তা দেখা যায়নি, তাই এখানে এটা একটা স্থির তালিকা।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- লাইভ probe দিয়ে আগে থেকে দেখে নেওয়া হয়েছে:
  - প্রতিটা ট্যাব।
  - Dyeing Finishing-এর সাব-গ্রুপ।
  - আটটা প্রসেসের সব মান।
  - কাস্টম ড্রপডাউনের অপশন: WIP Stage, Batch Status, Country, MOU।
- **MOU:** Brickland-এর ১২টা এককের স্থির তালিকা।
- **যাচাইয়ে পাওয়া আর সারানো:** নিজের টেবিলের দিকে foreign key `Schema::create`-এর ভেতরে দিলে Postgres-এ ব্যর্থ হয়। তাই সেটা আলাদা `Schema::table`-এ যোগ করা হয়েছে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- তালিকার ফিল্টার তাৎক্ষণিক, শুধু Clear (Brickland-এর মতো)। Machine Profile গ্রিডে "Model"।
