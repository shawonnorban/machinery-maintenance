# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `08/01`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Fabric Master | `/m/material-procurement/fabric-master` | 1 | 1 | 1 |
| 2 | Trims & Accessories Master | `/m/material-procurement/accessories-master` | 1 | 1 | 1 |
| 3 | Supplier Master | `/m/material-procurement/supplier-master` | 1 | 1 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Fabric Master

`/m/material-procurement/fabric-master`

**ট্যাব: All 6**
> 02 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search items | text |  | Search code, name, category… |
- গ্রিড কলাম: ITEM CODE, FABRIC NAME, CATEGORY, GSM, WIDTH, COMPOSITION, COLOUR, UOM, STATUS
- সেকশন: Fabric items
- বাটন/অ্যাকশন: Add new, All 6, Active 6, Inactive 0, Edit

**ডায়ালগ: Add new** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Item Code* | text | ✔ |  |
| Fabric Name* | text | ✔ |  |
| Category* | text | ✔ |  |
| Sub Category | text |  |  |
| GSM | text |  |  |
| Width | text |  |  |
| Composition | text |  |  |
| Colour | text |  |  |
| UOM* | text | ✔ |  |
| Specification | text |  |  |
| Description | textarea |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Item code is required.

#### Trims & Accessories Master

`/m/material-procurement/accessories-master`

**ট্যাব: All 4**
> 03 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search items | text |  | Search code, name, category… |
- গ্রিড কলাম: ITEM CODE, ITEM NAME, CATEGORY, SIZE, SPECIFICATION, COLOUR, UOM, STATUS
- সেকশন: Trims & accessories items
- বাটন/অ্যাকশন: Add new, All 4, Active 4, Inactive 0, Edit

**ডায়ালগ: Add new** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Item Code* | text | ✔ |  |
| Item Name* | text | ✔ |  |
| Category* | text | ✔ |  |
| Sub Category | text |  |  |
| Size | text |  |  |
| Colour | text |  |  |
| UOM* | text | ✔ |  |
| Specification | text |  |  |
| Description | textarea |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Item code is required.

#### Supplier Master

`/m/material-procurement/supplier-master`

**ট্যাব: All 12**
> 04 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search suppliers | text |  | Search code, name, owner… |
- গ্রিড কলাম: CODE, SUPPLIER, TYPE, GOODS / SERVICE, OWNER, PORTAL LOGINS, STATUS
- সেকশন: Suppliers
- বাটন/অ্যাকশন: Add supplier, All 12, Active 12, Inactive 0, Edit

**ডায়ালগ: Add supplier** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Supplier Code* | text | ✔ |  |
| Supplier Name* | text | ✔ |  |
| Short Name | text |  |  |
| Supplier Type* | select | ✔ | Select…, Local (Non-EPZ), Local (EPZ), Foreign |
| Owner Name | text |  |  |
| Goods / Service | text |  |  |
| VAT Reg. No | text |  |  |
| TIN No | text |  |  |
| Address | textarea |  |  |
- খালি Save-এ মেসেজ: Supplier code is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Machinery Maintenance বাদে সবকিছু নতুন করে তৈরি হচ্ছে (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১০-১০)। তাই আগের `vendors` (Machinery-র vendor তালিকা) আর `items` (স্টোরের আইটেম লাইব্রেরি) কোনোটাই এখানে ব্যবহার হয় না। দুটো Machinery-র নিজের থাকে।
- Material Procurement-এর নিজের দুটো টেবিল: `procurement_items` (fabric আর trims ক্যাটালগ) আর `procurement_suppliers`।
- আগের `PROCUREMENT` মেনু-মডিউল (PR, PO, GRN …) এখনো আছে। এই মডিউলের পরের ধাপগুলো নতুন করে তৈরি হলে সেগুলোর জায়গা নেবে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_10_29_000100_create_procurement_master_tables`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `procurement_items` | kind (FABRIC/TRIMS), item_code, name, category, sub_category, gsm, width, composition, size, colour, uom, specification, description, remarks, active | company + kind + item_code |
| `procurement_suppliers` | code, name, short_name, supplier_type (LOCAL_NON_EPZ/LOCAL_EPZ/FOREIGN), owner_name, goods_service, vat_reg_no, tin_no, address, active, created_by | company + code |

GSM আর width লেখা হিসেবে রাখা হয়, যেমন সাপ্লায়ার লেখে ("160", "60\"")।

## ৫. Workflow / Status ও Business Rules

- **কোড:** বড় হাতে রাখা হয়, আর ছোট-বড় হাতের অক্ষর নির্বিশেষে unique ("fab-001" মানে FAB-001)। আইটেম কোড unique তার নিজের ধরনের (kind) মধ্যে, তাই fabric আর trims একই কোড নিতে পারে।
- **kind:** আসে স্ক্রিন থেকে, request থেকে না।
- **মোছা:** কোনো আইটেম বা সাপ্লায়ার মোছা যায় না (405)। Status switch দিয়ে বন্ধ করা হয়। নতুন রিকুইজিশনে শুধু সক্রিয়গুলো আসবে (08/03)।
- **বাধ্যতামূলক:**
  - আইটেম: কোড, নাম, ক্যাটাগরি আর UOM। খালি কোডে Brickland-এর মেসেজ "Item code is required."
  - সাপ্লায়ার: কোড, নাম আর ধরন। খালি কোডে মেসেজ "Supplier code is required."
- **Portal Logins:** কলামটা এখন ০ দেখায়। Supplier Portal Access (08/07) তৈরি হলে আসল সংখ্যা আসবে।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{material-fabrics|material-trims|material-suppliers}`। Status switch চাপলে `PATCH {active: bool}` যায়।
- **পারমিশন:** নতুন `material.master.view` আর `material.master.manage`।
  - manage পায় FACTORY_ADMIN আর STORE_MANAGER। কেনাকাটা স্টোর ম্যানেজারই করেন।
  - view পায় FACTORY_MANAGER, STOREKEEPER আর read-only রোল।
  - Owner আর Admin সব পায়।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/material-procurement/<screen>`। সাইডবারে SALES & MERCHANDISING → Material Procurement → MASTER DATA।
- Brickland-এর মতো এখানে কোনো ট্যাব নেই। পুরো স্ক্রিন একটা কার্ড:
  - শিরোনাম আর "N টার মধ্যে M টা সক্রিয়" লাইন।
  - ডানে Add new / Add supplier বাটন, যা ডায়ালগ খোলে।
  - All / Active / Inactive পিল (`?status=`)।
  - তার নিচে তালিকা: Status কলামে switch, যা চাপলে সাথে সাথে সেভ হয়, আর প্রতিটা সারিতে Edit।
- **কিট:**
  - নতুন `CatalogCard`।
  - `RecordsGrid`-এ নতুন কলাম-ধরন `toggle`।
  - `DialogFormButton`-এ এখন `variant` আর `description` দেওয়া যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/MaterialProcurement/Database/Migrations/2026_10_29_000100_create_procurement_master_tables.php`
- `app/Modules/MaterialProcurement/Models/MaterialItem.php`
- `app/Modules/MaterialProcurement/Models/MaterialSupplier.php`
- `app/Modules/MaterialProcurement/Resources/MaterialItemResource.php`
- `app/Modules/MaterialProcurement/Resources/FabricItemResource.php`
- `app/Modules/MaterialProcurement/Resources/TrimItemResource.php`
- `app/Modules/MaterialProcurement/Resources/MaterialSupplierResource.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/material-procurement/[screen]/page.js`
- `frontend/src/app/(app)/material-procurement/[screen]/screens.js`
- `frontend/src/app/(app)/material-procurement/[screen]/actions.js`
- `frontend/src/components/erp/catalog-card.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/erp/dialog-form-button.jsx`
- `lang/en/materialprocurement.php`
- `lang/bn/materialprocurement.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/MaterialProcurement/MaterialProcurementMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `MaterialProcurementMasterTest` ৩/৩:
  - **আইটেম:** fabric আর trims আলাদা তালিকা, আর একই কোড দুই ধরনেই চলে। কোড বড় হাতে রাখা হয় আর ছোট-বড় হাতের অক্ষর নির্বিশেষে unique। kind request থেকে বদলানো যায় না। খালি কোডে Brickland-এর মেসেজ আসে। switch দিয়ে বন্ধ করা যায়, মোছা যায় না।
  - **সাপ্লায়ার:** নিজের টেবিলে সেভ হয় (short name, ধরন, মালিক, VAT, কে তৈরি করেছে)। Machinery-র vendor তালিকায় কিছু যোগ হয় না। ভুল ধরন বা একই কোড চলে না। মোছা যায় না।
  - **পারমিশন:** factory manager দেখতে পারে কিন্তু বদলাতে পারে না। store manager বদলাতে পারে। technician কিছুই পায় না।
- ব্রাউজার (dev):
  - ডায়ালগ থেকে দুটো fabric যোগ করা হয়েছে (fab-001 থেকে FAB-001 হয়েছে)।
  - switch দিয়ে একটা বন্ধ করার পর Inactive পিলে শুধু সেটা আসে।
  - একটা trims আইটেম যোগ করা হয়েছে।
  - সাপ্লায়ার যোগ ও Edit ডায়ালগ খোলা হয়েছে। তালিকায় শুধু procurement-এর নিজের সাপ্লায়ার আছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- প্রথম সংস্করণে Supplier Master বসানো হয়েছিল Machinery-র `vendors` টেবিলের ওপর। ব্যবহারকারীর সিদ্ধান্ত হলো Machinery বাদে সবকিছু নতুন করে, তাই সেটা আলাদা টেবিল `procurement_suppliers`-এ সরানো হয়েছে। `vendors`-এ যোগ করা কলামগুলোও সরানো হয়েছে।
- **পথে ধরা বাগ:** Laravel-এর `unique` নিয়ম request-এর কাঁচা মান ("fab-001") মেলায় সেভ হওয়া "FAB-001"-এর সাথে। Postgres-এ এই তুলনা ছোট-বড় হাতের অক্ষর আলাদা ধরে, তাই একই কোড দুবার ঢুকে যাচ্ছিল। এখন তুলনা হয় বড় হাতে রূপান্তরের পরে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- তিনটা তালিকাই এখন Brickland-এর একটা কার্ড: শিরোনাম, "N active of M", Add বাটন; তারপর All/Active/Inactive পিল আর ডানে সার্চ ("Search code, name, category…" / "Search code, name, owner…"); নিচে সাধারণ টেবিল (বড় হাতের হেডার, sort/pager নেই)। Status কলামে টগল আগের মতো আছে।
- Supplier-এর সাবটাইটেল Brickland-এর মতো: "Only active suppliers can be given a portal login."

**আলাদা মডিউল (২০২৬-১০-১২):** মাস্টার ডাটা আগে ভুল করে পুরোনো প্রজেক্টের `app/Modules/Procurement`-এর ভেতরে বানানো হয়েছিল। এখন সেটা নিজের মডিউল `app/Modules/MaterialProcurement`-এ।
- ক্লাসগুলো `MaterialItem`, `MaterialSupplier`, `MaterialItemResource`, `MaterialSupplierResource`।
- টেবিল `mp_items` আর `mp_suppliers`। আগের নামে তৈরি ডাটাবেস `2026_11_10_000400_rename_material_procurement_tables` চালালে নতুন নাম পায়।
- রিসোর্স `/erp/material-fabrics`, `/erp/material-trims`, `/erp/material-suppliers`। পারমিশন `material.master.view` আর `material.master.manage`।
- lang ফাইল `lang/*/materialprocurement.php`, টেস্ট `tests/Feature/MaterialProcurement/`।
- পুরোনো প্রজেক্টের Procurement কোড, lang আর টেস্ট কমিট করা অবস্থায় ফেরত গেছে।
- পুরোনো প্রজেক্টের Procurement, Store (Item Library, Low Stock, Fabric Inspection, Stock Count, Floor Requisition) আর Compliance মডিউল, আর Merchandise Desk-এর নিচের পুরোনো Production Orders ও Cutting Programs সাইডবার থেকে সরানো হয়েছে। কোড মোছা হয়নি। Brickland-এর মডিউল তৈরি হলে এগুলোর জায়গা নেবে।

