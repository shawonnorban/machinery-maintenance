# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `15/01`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | User Company & Office Mapping | `/m/knitting/user-company-office-mapping` | 1 | 1 | 0 |
| 2 | Machine Capacity Hour Plan | `/m/knitting/machine-capacity-hour-plan-list` | 2 | 18 | 1 |
| 3 | Knitting Machine Profile | `/m/knitting/machine-profile` | 3 | 30 | 0 |
| 4 | Machine Operator Assignment | `/m/knitting/assign-machine-operator` | 3 | 15 | 1 |
| 5 | Yarn Count Master | `/m/knitting/yarn-count-setup` | 2 | 19 | 1 |
| 6 | Label Printer Configuration | `/m/knitting/label-printer-setup` | 2 | 40 | 0 |
| 7 | Sub-Contract Party Rate | `/m/knitting/sc-party-rate` | 1 | 11 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### User Company & Office Mapping

`/m/knitting/user-company-office-mapping`

**ট্যাব: User & Office Mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search users | search |  | Search 31 users… |
- সেকশন: User & Office Mapping, Search By, USER LIST, COMPANY & OFFICE LIST
- বাটন/অ্যাকশন: Mappings 0, User & Office Mapping, -- Select user --, Load, ACCESSORIES VIEW (accview@bcl.com), Accounts Desk (accountant), Arafah Knit (arafah@bcl.com), BCL (bcl@gmail.com), BCL (inhouse@bcl.com), Commercial Desk (commercial), Creative Touch (creative@bcl.com), Dyeing Desk (dyeing), HR & Payroll Desk (hr), Industrial Engineering Desk (ie), Kamruzzaman (kamruzzaman), Koyo Trims (koyo@bcl.com), Leaket (leaket), MD.Ibrahim Hossain (info@sitrims.com), Maksudul (maksudul)

#### Machine Capacity Hour Plan

`/m/knitting/machine-capacity-hour-plan-list`

**ট্যাব: Capacity Plans 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine# | search |  | 01 |
| Prod.Floor | select |  | All |
| Schedule | select |  | All |
| Effective from | date |  |  |
| Effective to | date |  |  |
| Search Every machine's planned running hours — the schedule they are quoted agai | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine#, Prod.Floor, Schedule, EFFECTIVE, Plan Hours/Day, Days/Month, Efficiency(%), Capacity/Hour (Kg), Plan Qty/Month (Kg), ACTION, From, To
- বাটন/অ্যাকশন: Capacity Plans 0, New capacity band, Clear, Export, Machine#, Prod.Floor, Schedule, Plan Hours/Day, Days/Month, Efficiency(%), Capacity/Hour (Kg), Plan Qty/Month (Kg), From, To

**ট্যাব: New capacity band**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine#* | select | ✔ | Search… |
| Prod.Floor | text |  |  |
| Schedule | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
| Plan Hours/Day* | number | ✔ |  |
| Running Days/Month* | number | ✔ |  |
| Efficiency(%) | number |  |  |
| Capacity/Hour (Kg)* | number | ✔ | 8.500 |
| Remarks | textarea |  |  |
| Active? | checkbox |  |  |
- সেকশন: New capacity band
- বাটন/অ্যাকশন: Capacity Plans 0, New capacity band, Cancel, Save capacity band

**ডায়ালগ: New capacity band**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine#* | select | ✔ | Search… |
| Prod.Floor | text |  |  |
| Schedule | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
| Plan Hours/Day* | number | ✔ |  |
| Running Days/Month* | number | ✔ |  |
| Efficiency(%) | number |  |  |
| Capacity/Hour (Kg)* | number | ✔ |  |
| Remarks | textarea |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Effective From is required.

#### Knitting Machine Profile

`/m/knitting/machine-profile`

**ট্যাব: Machine List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine# | search |  | 01 |
| M/C Type | select |  | All |
| Brand | select |  | All |
| Prod.Floor | select |  | All |
| GG | select |  | All |
| Dia | select |  | All |
| Active? | select |  | All, Active, Withdrawn |
| Search Every knitting machine on the floor — its type, make and origin, the diam | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine#, M/C Type, Brand, Model, M/C DIA & GG, Supported GG, Prod. Capacity (Kg), # of Feeder, RPM, Effencncy(%), Prod.Floor, Country of Orgin, Serial#, Mfg. Year, ACTION, Dia, GG
- বাটন/অ্যাকশন: Machine List 0, Machine Reference Lists, Key Information, Clear, Export, Machine#, M/C Type, Brand, Model, Supported GG, Prod. Capacity (Kg), # of Feeder, RPM, Effencncy(%), Prod.Floor, Country of Orgin, Serial#, Mfg. Year, Dia, GG

**ট্যাব: Machine Reference Lists**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search lists | search |  | Search… |
- সেকশন: List, Entries
- বাটন/অ্যাকশন: Machine List 0, Machine Reference Lists, Key Information, M/C Type machine type list 6, Brand brand list 6, M/C Dia diameter list 6, M/C GG gauge list 6

**ট্যাব: Key Information**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine#* | text | ✔ | 01 |
| M/C Type* | select | ✔ | Single Jersey (SJ), Rib (RIB), Interlock (INTL), Fleece / Terry (FLEECE), Jacquard (JCQ), Flat Bed (Collar/Cuff) (FLAT) |
| Brand* | select | ✔ | Mayer & Cie, Terrot, Fukuhara, Pailung, Orizio, Wellknit |
| Model | text |  | MV4-3.2 II |
| Serial# | text |  | 01 |
| M/C Dia | select |  | 26", 30", 32", 34", 36", 38" |
| M/C GG | select |  | 14GG, 18GG, 20GG, 24GG, 28GG, 32GG |
| 14GG | checkbox |  |  |
| 18GG | checkbox |  |  |
| 20GG | checkbox |  |  |
| 24GG | checkbox |  |  |
| 28GG | checkbox |  |  |
| 32GG | checkbox |  |  |
| Prod. Capacity(Kg) | number |  | 180 |
| # of Feeder | number |  | 96 |
| RPM | number |  | 24 |
| Effencncy(%) | number |  | 85 |
| Prod.Floor* | select | ✔ | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Mfg. Year | number |  | YYYY |
| Active? | checkbox |  |  |
- সেকশন: New machine
- বাটন/অ্যাকশন: Machine List 0, Machine Reference Lists, Key Information, -- Select --, Cancel, Save machine

#### Machine Operator Assignment

`/m/knitting/assign-machine-operator`

**ট্যাব: Assigned Operators 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine# | search |  | 01 |
| Schedule | select |  | All |
| Prod.Floor | select |  | All |
| Standing? | select |  | All, Standing, Ended |
| Search Every machine-to-operator posting — the machine, the operator's code and  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Machine#, Emp. Code, Name, Schedule, Effective, Until, Prod.Floor, ACTION
- বাটন/অ্যাকশন: Assigned Operators 0, Machine Wise Operator Assign, New Assign, Clear, Export, Machine#, Emp. Code, Name, Schedule, Effective, Until, Prod.Floor

**ট্যাব: Machine Wise Operator Assign**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search machines | search |  | Search… |
- সেকশন: Machine List, Assigned Operators
- বাটন/অ্যাকশন: Assigned Operators 0, Machine Wise Operator Assign, New Assign

**ট্যাব: New Assign**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine#* | select | ✔ | Search… |
| Prod.Floor | text |  |  |
| Emp. Code | text |  |  |
| Name | text |  |  |
| Schedule* | select | ✔ | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Effective* | datetime-local | ✔ |  |
| Until | datetime-local |  |  |
| Active? | checkbox |  |  |
- সেকশন: New posting
- বাটন/অ্যাকশন: Assigned Operators 0, Machine Wise Operator Assign, New Assign, Search…, Cancel, Save posting

**ডায়ালগ: New Assign**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine#* | select | ✔ | Search… |
| Prod.Floor | text |  |  |
| Emp. Code | text |  |  |
| Name | text |  |  |
| Schedule* | select | ✔ | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Effective* | datetime-local | ✔ |  |
| Until | datetime-local |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Effective is required.

#### Yarn Count Master

`/m/knitting/yarn-count-setup`

**ট্যাব: Yarn Count Entry 25**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Yarn Count Sys | select |  | Ne, Nm, D, Tex, Dtex |
| Single? | select |  | All, Single, Plied |
| Active? | select |  | All, Active, Withdrawn |
| Search Every yarn count on file — the count number, whether it is single, whethe | search |  | Search 25 records… |
| Filter by Yarn Count No | search |  |  |
| Filter by Single? | search |  |  |
| Filter by Active? | search |  |  |
| Filter by Yarn Count Sys | search |  |  |
| Filter by Yarn Count Desc | search |  |  |
| Filter by Yarn Count Value | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Yarn Count No, Single?, Active?, Yarn Count Sys, Yarn Count Desc, Yarn Count Value, Status, ACTION
- বাটন/অ্যাকশন: Yarn Count Entry 25, Add New, Clear, Export, Yarn Count No, Single?, Active?, Yarn Count Sys, Yarn Count Desc, Yarn Count Value, Status, Withdraw, Edit

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Yarn Count No* | text | ✔ | 30/1 |
| Yarn Count Sys* | select | ✔ | Select…, Ne, Nm, D, Tex, Dtex |
| Yarn Count Desc* | text | ✔ | 30/1 Ne |
| Yarn Count Value | number |  | 30 |
| Single? Clear it for a plied yarn. A 30/2 is two 30s twisted together and knits  | checkbox |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- সেকশন: New yarn count
- বাটন/অ্যাকশন: Yarn Count Entry 25, Add New, Cancel, Save yarn count

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Yarn Count No* | text | ✔ |  |
| Yarn Count Sys* | select | ✔ | Select…, Ne, Nm, D, Tex, Dtex |
| Yarn Count Desc* | text | ✔ |  |
| Yarn Count Value | number |  |  |
| Single? Clear it for a plied yarn. A 30/2 is two 30s twisted together and knits  | checkbox |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Yarn Count No is required.

#### Label Printer Configuration

`/m/knitting/label-printer-setup`

**ট্যাব: Label Printer Setup 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Printer Name | search |  | EMS |
| Is Auto Scale | select |  | All, Auto scaled, Fixed size |
| Search Every label printer the knitting floor prints tags on — its name, the que | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Printer Name, Printer Address, User List, Is Auto Scale, ACTION
- বাটন/অ্যাকশন: Label Printer Setup 0, Add Printer, Clear, Export, Printer Name, Printer Address, User List, Is Auto Scale

**ট্যাব: Add Printer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Printer Name* | text | ✔ | MFL-SOUTH |
| Printer Address* | text | ✔ | Printer1 |
| ACCESSORIES VIEW (accview@bcl.com) | checkbox |  |  |
| Accounts Desk (accountant) | checkbox |  |  |
| Arafah Knit (arafah@bcl.com) | checkbox |  |  |
| BCL (bcl@gmail.com) | checkbox |  |  |
| BCL (inhouse@bcl.com) | checkbox |  |  |
| Commercial Desk (commercial) | checkbox |  |  |
| Creative Touch (creative@bcl.com) | checkbox |  |  |
| Dyeing Desk (dyeing) | checkbox |  |  |
| HR & Payroll Desk (hr) | checkbox |  |  |
| Industrial Engineering Desk (ie) | checkbox |  |  |
| Kamruzzaman (kamruzzaman) | checkbox |  |  |
| Koyo Trims (koyo@bcl.com) | checkbox |  |  |
| Leaket (leaket) | checkbox |  |  |
| MD.Ibrahim Hossain (info@sitrims.com) | checkbox |  |  |
| Maksudul (maksudul) | checkbox |  |  |
| Marketing Desk (marketer) | checkbox |  |  |
| Md. Moniruzzaman Miah (verveinternationalltd@gmail.com) | checkbox |  |  |
| Merchandising Desk (merchandiser) | checkbox |  |  |
| Plabon (plabon) | checkbox |  |  |
| Planning Desk (planning) | checkbox |  |  |
| Poly Plan (poly@bcl.com) | checkbox |  |  |
| Production Desk (production) | checkbox |  |  |
| Purchase Desk (purchase) | checkbox |  |  |
| S.S Trading International (s.s@bcl.com) | checkbox |  |  |
| SS INTERNATIONAL (ss@bcl.com) | checkbox |  |  |
| Shamim (shamim) | checkbox |  |  |
| Ss International (ssint@bcl.com) | checkbox |  |  |
| Store (storebcl) | checkbox |  |  |
| Store Desk (store) | checkbox |  |  |
| System Administrator (admin) | checkbox |  |  |
| Tofayel Ahmed (plabon.bcl) | checkbox |  |  |
| Is Auto Scale Tick where the driver scales the label to the stock rather than pr | checkbox |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- সেকশন: New printer
- বাটন/অ্যাকশন: Label Printer Setup 0, Add Printer, Cancel, Save printer

#### Sub-Contract Party Rate

`/m/knitting/sc-party-rate`

**ট্যাব: S/C Party Wise Rate Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Rate Group List | search |  | search… |
| Existing quotation | select |  |  |
| Rate Group | text |  | Pick one on the left |
| Quatation# | text |  | Left blank, the server issues one |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  | Signed rate sheet, 14-Aug-2026 |
- গ্রিড কলাম: Rate Group
- সেকশন: Rate Group List, S/C Party Wise Rate Entry, Quatation Rate Detail, Selected Docs
- বাটন/অ্যাকশন: Quatation List 0, S/C Party Wise Rate Entry, Knitting, Yarn Dyeing, Dyeing, Dyeing Finishing, AOP, Yarn Twist, -- Select --, Add, Cancel, Attach, Clear, Save quotation

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Rate Group List | search |  |  |
| Existing quotation | select |  |  |
| Rate Group | text |  |  |
| Quatation# | text |  |  |
| Auto? | checkbox |  |  |
| Quatation Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Status* | select | ✔ | Draft, Submitted, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Rate | number |  |  |
| Document | text |  |  |
- খালি Save-এ মেসেজ: Pick a rate group on the left before saving — a quotation is always for one group.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/Knitting`, সব টেবিল `knit_` নামে।
- **প্ল্যাটফর্মের অংশ, যা সবাই ব্যবহার করে:**
  - Company Management-এর ফ্লোর আর ফ্যাক্টরি (অফিস)।
  - Settings → Calendar & Shifts-এর `shifts`। Brickland-এর "Schedule" এখান থেকে আসে।
  - কোম্পানির ইউজার।
- **অন্য ERP মডিউল থেকে নেওয়া:**
  - Merchandise Desk-এর origin country।
  - Material Procurement-এর সাপ্লায়ার (S/C পার্টি)।
- **Sub-Contract Party Rate:** এটা Dye House-এর কোটেশনই (`dye_sc_quotations`, 17/01)।
  - Brickland দুই মডিউলে একই ডকুমেন্ট দেখায়: একই রেট গ্রুপ, একই ফর্ম।
  - তাই নতুন টেবিল হয়নি। Dye House বা Knit Production যেকোনোটার master পারমিশন থাকলেই খোলা যায়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_05_000100_create_knitting_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_ref_entries` | list (MC_TYPE/BRAND/DIA/GG), code, name, sort_order, active |
| `knit_machines` | machine_no, machine_type_id, brand_id, model, origin_country_id, serial_no, dia_id, gauge_id, supported_gauge_ids (json), capacity_kg, feeders, rpm, efficiency, floor_id, mfg_year, active |
| `knit_capacity_plans` | machine_id, shift_id (ফাঁকা = যেকোনো প্যাটার্ন), effective_from, effective_to (ফাঁকা = শেষ নেই), plan_hours, running_days, efficiency, capacity_per_hour, remarks, active |
| `knit_operator_postings` | machine_id, user_id (অপারেটর), shift_id, effective_at, until_at (তারিখ ও সময়), active |
| `knit_yarn_counts` | count_no, count_system (Ne/Nm/D/Tex/Dtex), description, count_value, is_single, display_order, active |
| `knit_label_printers` | name, address, user_ids (json; ফাঁকা = সবাই), auto_scale, display_order, active |
| `knit_user_offices` | user_id, factory_id, is_primary, can_write |

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা (Brickland-এর মতো):**
  - চারটা রেফারেন্স তালিকার প্রতিটায় ছয়টা এন্ট্রি।
  - ২৫টা ইয়ার্ন কাউন্ট, Brickland-এর ক্রমে।
  - এগুলো প্রথমবার তালিকা খোলার সময় তৈরি হয়। একসাথে কয়েকটা রিকোয়েস্ট এলেও দুবার তৈরি হয় না (`insertOrIgnore`)।
- **Machine Reference Lists:**
  - পুরো তালিকা একবারে সেভ হয়।
  - নতুন এন্ট্রির কোড দেওয়া যায়; সেভ হওয়া কোড আর বদলায় না।
  - দুটো এন্ট্রির কোড এক হতে পারে না।
  - কোনো মেশিন যে এন্ট্রি ব্যবহার করে সেটা মোছা যায় না; বদলে Active? তুলে দিতে হয়। Machines কলামে সমর্থিত গেজও গোনা হয়।
- **Knitting Machine Profile:**
  - মেশিন নং ইউনিক (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)।
  - বর্তমান গেজ অবশ্যই সমর্থিত গেজগুলোর একটা হতে হবে।
  - মেশিন মোছা যায় না; Active? তুলে দিয়ে প্রত্যাহার করা হয়।
- **Machine Capacity Hour Plan:**
  - একটা মেশিনের সক্রিয় ব্যান্ড একটার ওপর আরেকটা পড়তে পারে না।
  - শেষ তারিখ শুরুর আগে হতে পারে না।
  - মাসে প্ল্যান পরিমাণ = ঘণ্টা × দিন × ঘণ্টাপ্রতি কেজি × এফিসিয়েন্সি% (এফিসিয়েন্সি ফাঁকা থাকলে ১০০%)।
- **Machine Operator Assignment:**
  - একটা মেশিন আর শিডিউলে একই সময়ে একজনই চলতি অপারেটর থাকেন। মিলে গেলে নেওয়া হয় না।
  - Until অবশ্যই Effective-এর পরে।
  - Machine Wise ট্যাবে Until-এ তারিখ দিয়ে পোস্টিং বন্ধ করা যায়, আর লাইন সরিয়ে ভুল পোস্টিং প্রত্যাহার করা যায় (রেকর্ড থাকে, inactive হয়)।
- **Yarn Count Master:**
  - কাউন্ট নং ইউনিক।
  - মোছা যায় না; Withdraw দিলে Active? উঠে যায়।
- **Label Printer Configuration:** কোনো ইউজার টিক না থাকলে প্রিন্টার সবার জন্য।
- **User Company & Office Mapping:**
  - কিছু টিক না থাকলে ইউজারের কোনো সীমা নেই।
  - টিক দিলে শুধু সেই অফিসগুলোতে সীমিত।
  - একটাই অফিস প্রধান হতে পারে।
  - Save গ্রিড যেমন আছে তেমন লেখে।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{knit-machines|knit-capacity-plans|knit-operator-postings|knit-yarn-counts|knit-label-printers}`।
- **`/api/v1/knitting/...`:**
  - `lookups`
  - `ref-lists`, `ref-lists/{list}` (GET/PUT)
  - `user-offices`, `office-users`, `office-users/{user}` (GET/PUT)
  - `posting-machines`, `posting-machines/{machine}` (GET/PUT)
- **Sub-Contract Party Rate:** `/api/v1/dyehouse/sc-quotations…`। তালিকায় এখন Docs সংখ্যাও আসে।
- **পারমিশন:** নতুন `knitting.master.view` আর `knitting.master.manage`।
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়।
  - LINE_CHIEF পায় শুধু view।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/knit-production/<screen>`। সাইডবারে PRODUCTION → Knit Production → MASTER DATA।
- প্রতিটা স্ক্রিনের ট্যাব, ফিল্টার আর লেখা Brickland-এর লাইভ probe ([`plans/knitting.json`](../tools/erp_crawler/plans/knitting.json)) থেকে মেলানো।
- **কোন ট্যাবে খোলে:**
  - User Company & Office Mapping খোলে Mapping ট্যাবে।
  - Sub-Contract Party Rate খোলে Entry ট্যাবে।
  - বাকিগুলো খোলে Records-এ।
- **ফিল্টার:** প্রতিটা Records ট্যাবের ওপরে Brickland-এর ফিল্টার আছে। Machine# লিখে খোঁজা, আর ফ্লোর, শিডিউল, ধরন, ব্র্যান্ড, গেজ, ডায়া, Active?, তারিখের মতো ড্রপডাউন।
- **Records-এর বাটন:**
  - Records-এ শুধু Edit; কোনো Records-এ Delete নেই।
  - Yarn Count-এ বাড়তি Withdraw।
  - Single?/Active? দেখায় Y/N হিসেবে।
- **ফর্ম:**
  - Prod.Floor (মেশিন থেকে), আর Emp. Code ও Name (অপারেটর থেকে) নিজে থেকে ভরে, শুধু দেখার জন্য।
  - ঘণ্টা, দিন, %, কেজির একক ঘরের ভেতরে দেখায়।
  - Effective আর Until-এ তারিখ আর সময় দুটোই দেওয়া যায়।
- **বাম-ডান প্যানেল (SideGrid):** Machine Reference Lists, Machine Wise Operator Assign, আর User & Office Mapping (ওপরে "Search By" কার্ডসহ)।
- **Shared kit-এ নতুন:**
  - EntryForm-এ `derived` আর `datetime` ধরন।
  - গ্রিডে `input`, `number`, `datetime`, `status` কলাম।
  - SideGrid-এ `ns`, `addLabel`, `picker`, `selectedKey`, আর তালিকার আইটেমে hint।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Knitting/Database/Migrations/2026_11_05_000100_create_knitting_masters.php`
- `app/Modules/Knitting/Models/KnitRefEntry.php`
- `app/Modules/Knitting/Models/KnitMachine.php`
- `app/Modules/Knitting/Models/KnitCapacityPlan.php`
- `app/Modules/Knitting/Models/KnitOperatorPosting.php`
- `app/Modules/Knitting/Models/KnitYarnCount.php`
- `app/Modules/Knitting/Models/KnitLabelPrinter.php`
- `app/Modules/Knitting/Models/KnitUserOffice.php`
- `app/Modules/Knitting/Support/KnittingDefaults.php`
- `app/Modules/Knitting/Support/KnittingLabels.php`
- `app/Modules/Knitting/Resources/KnittingResource.php`
- `app/Modules/Knitting/Resources/MachineResource.php`
- `app/Modules/Knitting/Resources/CapacityPlanResource.php`
- `app/Modules/Knitting/Resources/OperatorPostingResource.php`
- `app/Modules/Knitting/Resources/YarnCountResource.php`
- `app/Modules/Knitting/Resources/LabelPrinterResource.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnittingApiController.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeScQuotationApiController.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js`
- `frontend/src/components/workstudy/side-grid.jsx`
- `frontend/src/components/workstudy/edit-grid.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `lang/en/knitting.php`
- `lang/bn/knitting.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Knitting/KnittingMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `KnittingMasterTest` ৫/৫ (৯৯টা assertion):
  - **রেফারেন্স তালিকা:** শুরুতে ৬টা করে এন্ট্রি থাকে। একই কোড দুবার চলে না। নাম বদলানো, নতুন যোগ আর অব্যবহৃতটা মোছা একবারে হয়। ব্যবহৃত এন্ট্রি মোছা যায় না। সমর্থিত গেজও Machines-এ গোনা হয়।
  - **মেশিন:** মেশিন নং ছাড়া Brickland-এর মেসেজ আসে। অসমর্থিত গেজ চলে না। লেবেল ঠিক দেখায়। একই নং (ছোট হাতে) চলে না। মোছা যায় না।
  - **ক্যাপাসিটি ও পোস্টিং:** শুরুর তারিখ ছাড়া মেসেজ আসে। মাসে প্ল্যান পরিমাণ হিসাব হয়। মিলে যাওয়া ব্যান্ড চলে না, পরের দিন থেকে চলে। মিলে যাওয়া পোস্টিং চলে না। Machine Wise-এ আগের তারিখে Until চলে না; তারিখ দিয়ে বন্ধ করলে পরেরটা চলে; লাইন সরালে প্রত্যাহার হয়।
  - **কাউন্ট, প্রিন্টার, অফিস:** ২৫টা কাউন্ট ক্রমে থাকে। কাউন্ট নং ছাড়া মেসেজ আসে, একই নং চলে না। Withdraw কাজ করে, মোছা যায় না। প্রিন্টারে অজানা ইউজার চলে না। অফিস ম্যাপিং আর Mappings তালিকা ঠিক থাকে।
  - **পারমিশন:** ফ্যাক্টরি ম্যানেজার বদলাতে পারে। লাইন চিফ সব দেখতে পারে (S/C কোটেশনসহ), কিছু বদলাতে পারে না। স্টোর ম্যানেজার নিটিং মেশিন দেখতে পারে না।
- **ব্রাউজার (dev):** সাতটা স্ক্রিনেই সেভ হয়েছে।
  - M/C Type তালিকা খোলা হয়েছে।
  - MC-01 মেশিন যোগ হয়েছে (18GG আর 24GG সমর্থিত)।
  - ক্যাপাসিটি ব্যান্ড যোগ হয়েছে, Prod.Floor নিজে থেকে ভরেছে।
  - অপারেটর পোস্টিং যোগ হয়েছে, Machine Wise ট্যাবে দেখা গেছে।
  - প্রিন্টার যোগ হয়েছে।
  - ইউজারের অফিস ম্যাপিং সেভ হয়েছে।
  - S/C entry আর তালিকা খোলা হয়েছে।
  - কনসোলে কোনো error নেই।
- **যাচাইয়ে পাওয়া আর সারানো:** পেজ খোলার সময় একসাথে কয়েকটা রিকোয়েস্ট শুরুর তালিকা তৈরি করতে গিয়ে duplicate error দিচ্ছিল। এখন `insertOrIgnore` দিয়ে সারানো।

## ১০. খোলা প্রশ্ন

- **অপারেটর:** HR & Payroll বানানোর পরে অপারেটর কর্মীর তালিকায় সরবে (এখন কোম্পানির ইউজার, Emp. Code আসে ইউজারের employee code থেকে)।
- **অফিস:** Brickland-এর "Company & Office" তালিকা ডেমোতে খালি ছিল, তাই কলামগুলো দেখা যায়নি। এখানে অফিস মানে Company Management-এর ফ্যাক্টরি।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- Brickland-এর প্রতিটা ট্যাব, তালিকা আর ফর্ম লাইভ probe দিয়ে আগে দেখে নেওয়া হয়েছে:
  - চারটা রেফারেন্স তালিকার এন্ট্রি আর কোড।
  - ২৫টা ইয়ার্ন কাউন্ট।
  - প্রতিটা হিন্ট আর ফুটারের লেখা।
  - কোন ট্যাবে খোলে।
- **Brickland-এর সাথে তফাত:**
  - **Schedule:** HR-এর work schedule-এর বদলে Settings-এর shift।
  - **Operator:** কর্মীর বদলে কোম্পানির ইউজার।
  - **Office:** ফ্যাক্টরি।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- তালিকার ফিল্টার এখন Brickland-এর মতো তাৎক্ষণিক: select বদলালেই কাজ করে, টাইপ থামলে টেক্সট বক্স; শুধু Clear বাটন থাকে, Show নেই।
- Machine Capacity Hour Plan-এর বাড়তি Effective From/To ফিল্টার সরানো হয়েছে (Brickland-এ শুধু Machine#, Prod.Floor, Schedule)। Label Printer গ্রিডে Status কলাম যোগ হয়েছে।
- Sub-Contract Party Rate (Dye House-এর সাথে একই এডিটর): Brickland-এর মতো "Quatation Rate Detail"-এ Rate Details + Rate বসিয়ে Add/Cancel, নিচে লাইনের তালিকা; "Selected Docs"-এ নাম লিখে Attach/Cancel, একাধিক ডকুমেন্ট (নতুন মাইগ্রেশন `2026_11_10_000300`: `document` → `documents` json); Status শুরুতে "-- Select Status --"; নিচের কার্ডে নোট আর Clear / Save quotation; লেবেল "Quatation#", "Quatation Date"।
- বাকি পার্থক্য: Capacity Plan গ্রিডে Brickland-এর দুই সারির "EFFECTIVE → From/To" হেডার নেই, এক সারিতে From/To।
