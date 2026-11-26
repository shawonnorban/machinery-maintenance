# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `10/01`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Item Category | `/m/general-store/gs-categories` | 1 | 1 | 1 |
| 2 | Item Master | `/m/general-store/gs-items` | 1 | 1 | 1 |
| 3 | Unit Master | `/m/general-store/gs-units` | 1 | 1 | 1 |
| 4 | Department | `/m/general-store/gs-departments` | 1 | 1 | 1 |
| 5 | Supplier | `/m/general-store/gs-suppliers` | 1 | 1 | 1 |
| 6 | Store / Location | `/m/general-store/gs-stores` | 1 | 1 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Item Category

`/m/general-store/gs-categories`

**ভিউ**
> 02 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: CODE, NAME, ITEMS, DESCRIPTION, STATUS
- সেকশন: Item Category (10)
- বাটন/অ্যাকশন: Excel, New Item Category, Edit

**ডায়ালগ: New Item Category** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |

#### Item Master

`/m/general-store/gs-items`

**ভিউ**
> 03 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: ITEM CODE, ITEM NAME, CATEGORY, UNIT, BRAND, MIN. STOCK, PRICE (৳), STOCK, RACK, STOCK STATUS, STATUS
- সেকশন: Item (0)
- বাটন/অ্যাকশন: Excel, New Item, All categories

**ডায়ালগ: New Item** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Item Code* | text | ✔ |  |
| Item Name* | text | ✔ |  |
| Sub Category | text |  |  |
| Brand | text |  |  |
| Minimum Stock | number |  |  |
| Reorder Level | number |  |  |
| Default Price (৳) | number |  |  |
| Rack / Location | text |  |  |
| Specification | textarea |  |  |
| Opening Stock | number |  |  |
| Opening Stock Store | select |  | Select…, Electrical Store, Main Store, Maintenance Store, Packaging Store, Safety Store |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |

#### Unit Master

`/m/general-store/gs-units`

**ভিউ**
> 04 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: CODE, NAME, ITEMS, STATUS
- সেকশন: Unit (14)
- বাটন/অ্যাকশন: Excel, New Unit, Edit

**ডায়ালগ: New Unit** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |

#### Department

`/m/general-store/gs-departments`

**ভিউ**
> 05 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: CODE, NAME, DESCRIPTION, STATUS
- সেকশন: Department (12)
- বাটন/অ্যাকশন: Excel, New Department, Edit

**ডায়ালগ: New Department** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |

#### Supplier

`/m/general-store/gs-suppliers`

**ভিউ**
> 06 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: CODE, NAME, CONTACT PERSON, MOBILE, EMAIL, ADDRESS, STATUS
- সেকশন: Supplier (0)
- বাটন/অ্যাকশন: Excel, New Supplier

**ডায়ালগ: New Supplier** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Contact Person | text |  |  |
| Mobile | text |  |  |
| Email | text |  |  |
| Address | textarea |  |  |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |

#### Store / Location

`/m/general-store/gs-stores`

**ভিউ**
> 07 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Code or name |
- গ্রিড কলাম: CODE, NAME, LOCATION, IN-CHARGE, DEFAULT, STATUS
- সেকশন: Store / Location (5)
- বাটন/অ্যাকশন: Excel, New Store / Location, Edit

**ডায়ালগ: New Store / Location** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Store Name* | text | ✔ |  |
| Location | text |  |  |
| In-charge | text |  |  |
| Default store Entry forms open on this store. | checkbox |  |  |
| Active Inactive entries are not offered on new entries. | checkbox |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Machinery Maintenance বাদে সবকিছু নতুন করে তৈরি হচ্ছে। তাই আগের আইটেম লাইব্রেরি (`items`, `item_categories`), `units_of_measure`, `stores`/`bins`, `ItemLedger` আর `vendors` এখানে ব্যবহার হয় না। এগুলো Machinery-র spare-part স্টোরের নিজের থাকে।
- General Store-এর সব তালিকা আর স্টক-লেজার নতুন মডিউল `app/Modules/GeneralStore`-এ, `gs_` নামের টেবিলে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_10_30_000100_create_general_store_masters`। সব টেবিল কোম্পানির নিজের, প্রতিটায় unique company + code:

| টেবিল | ফিল্ড |
|---|---|
| `gs_categories` | code, name, description, active |
| `gs_units` | code, name, active |
| `gs_departments` | code, name, description, active |
| `gs_suppliers` | code, name, contact_person, mobile, email, address, active |
| `gs_stores` | code, name, location, in_charge, is_default, active |
| `gs_items` | item_code, name, gs_category_id, sub_category, gs_unit_id, brand, minimum_stock, reorder_level, default_price, rack_location, specification, active |
| `gs_stock_movements` | gs_item_id, gs_store_id, movement_type (OPENING/RECEIVE/ISSUE/RETURN/ADJUST/TRANSFER), quantity (ঢুকলে +, বের হলে −), unit_price, moved_on, reference |

কোনো আইটেমের একটা স্টোরে স্টক হলো সেই আইটেম আর স্টোরের সব movement-এর যোগফল। পরের ধাপের Receive, Issue, Return, Adjust আর Transfer (10/03 …) এই টেবিলেই লিখবে।

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা:** কোম্পানি প্রথমবার তালিকা খুললে Brickland-এর তালিকা তৈরি হয়, শুধু খালি তালিকায়।
  - ১০টা ক্যাটাগরি (CLEAN … STAT)।
  - ১৪টা ইউনিট (BOX … SET)।
  - ১২টা ডিপার্টমেন্ট (ACC … STORE)।
  - ৫টা স্টোর (ELEC, MAIN, MAINT, PACK, SAFE)। এর মধ্যে MAIN ডিফল্ট।
- **কোড:** বড় হাতে রাখা হয়, আর ছোট-বড় হাতের অক্ষর নির্বিশেষে কোম্পানির মধ্যে unique।
- **মোছা:** কিছুই মোছা যায় না, শুধু Active বন্ধ করা যায়। নিষ্ক্রিয় এন্ট্রি নতুন এন্ট্রিতে আসে না, যেমন Item ডায়ালগের ক্যাটাগরি আর ইউনিটের তালিকায়।
- **আইটেম:**
  - Opening Stock দিলে স্টোর বাছাই করতে হয়। ডিফল্ট স্টোর আগে থেকে বাছাই করা থাকে।
  - Opening Stock আইটেমের প্রথম movement হিসেবে লেখা হয় (OPENING, আজকের তারিখ, দাম ধরা হয় ডিফল্ট দাম)।
  - সম্পাদনার সময় Opening Stock দেওয়া যায় না।
  - Stock Status: স্টক ০ হলে "স্টক নেই", ন্যূনতম স্টকের সমান বা কম হলে "কম", না হলে "স্টক আছে"।
- **স্টোর:** একটাই ডিফল্ট থাকে। নতুনটাকে ডিফল্ট করলে আগেরটা আর ডিফল্ট থাকে না।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{store-categories|store-items|store-units|store-departments|store-suppliers|store-locations}`।
  - Item-এর তালিকা `?filter[gs_category_id]=` দিয়ে সার্ভারেই ছাঁকা হয়। ক্যাটাগরি, ইউনিট আর স্টক একই কোয়েরির subselect-এ আসে।
  - Item-এর POST-এ `opening_stock` আর `opening_store_id` দেওয়া যায়।
- **পারমিশন:** নতুন `store.master.view` আর `store.master.manage`।
  - manage পায় FACTORY_ADMIN, STORE_MANAGER আর STOREKEEPER।
  - view পায় FACTORY_MANAGER, MAINTENANCE_MANAGER আর read-only রোল।
- **কিট:** `ResourceDefinition::rowQuery()` নতুন। একটা সারি দেখা, বদলানো বা মোছার জন্য কোথায় খোঁজা হবে, তা এখন resource নিজে ঠিক করতে পারে। ডিফল্টে মডেলের নিজের কোয়েরি।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/general-store/<screen>`। সাইডবারে INVENTORY & STORES → General Store → MASTER DATA।
- প্রতিটা স্ক্রিন Brickland-এর মতো একটা কার্ড:
  - শিরোনামে সংখ্যা, যেমন "Item Category (10)"।
  - ডানে New বাটন, যা ডায়ালগ খোলে।
  - তার নিচে তালিকা: Search, Export, Status pill আর প্রতিটা সারিতে Edit।
- Item Master-এ "All categories" select আছে (`?category=`)। কিটের ২০০০ সারির সীমা ছাড়ালে শিরোনামে "2000+" দেখায়।
- **কিট:** নতুন `UrlSelect`। `CatalogCard`-এ এখন পিল ছাড়া `toolbar` দেওয়া যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Database/Migrations/2026_10_30_000100_create_general_store_masters.php`
- `app/Modules/GeneralStore/Models/GsCategory.php`
- `app/Modules/GeneralStore/Models/GsUnit.php`
- `app/Modules/GeneralStore/Models/GsDepartment.php`
- `app/Modules/GeneralStore/Models/GsSupplier.php`
- `app/Modules/GeneralStore/Models/GsStore.php`
- `app/Modules/GeneralStore/Models/GsItem.php`
- `app/Modules/GeneralStore/Models/GsStockMovement.php`
- `app/Modules/GeneralStore/Support/GeneralStoreDefaults.php`
- `app/Modules/GeneralStore/Resources/StoreMasterResource.php`
- `app/Modules/GeneralStore/Resources/StoreCategoryResource.php`
- `app/Modules/GeneralStore/Resources/StoreItemResource.php`
- `app/Modules/GeneralStore/Resources/StoreUnitResource.php`
- `app/Modules/GeneralStore/Resources/StoreDepartmentResource.php`
- `app/Modules/GeneralStore/Resources/StoreSupplierResource.php`
- `app/Modules/GeneralStore/Resources/StoreLocationResource.php`
- `app/Modules/Erp/Support/ResourceDefinition.php`
- `app/Modules/Erp/Http/Controllers/Api/ResourceApiController.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/general-store/[screen]/page.js`
- `frontend/src/app/(app)/general-store/[screen]/screens.js`
- `frontend/src/app/(app)/general-store/[screen]/actions.js`
- `frontend/src/components/erp/url-select.jsx`
- `frontend/src/components/erp/catalog-card.jsx`
- `lang/en/store.php`
- `lang/bn/store.php`

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `GeneralStoreMasterTest` ৪/৪:
  - **শুরুর ডাটা:** ১০টা ক্যাটাগরি, ১৪টা ইউনিট, ১২টা ডিপার্টমেন্ট আর ৫টা স্টোর আসে, আর MAIN ডিফল্ট। আবার খুললে দ্বিতীয়বার যোগ হয় না। কোড ছোট-বড় হাত নির্বিশেষে unique। Machinery-র ইউনিটে কিছু যোগ হয় না।
  - **আইটেম:** Opening Stock দিলে স্টোর লাগে। movement সেভ হয় OPENING ধরন, সঠিক স্টোর, পরিমাণ আর দামসহ। তালিকায় আর ক্যাটাগরি ফিল্টারে একই স্টক আসে। Stock Status OUT, LOW আর OK তিনটাই ঠিক আসে। সম্পাদনায় Opening Stock দেওয়া যায় না। মোছা যায় না।
  - **স্টোর আর সাপ্লায়ার:** ডিফল্ট স্টোর একটাই থাকে। সাপ্লায়ার স্টোরের নিজের তালিকায় সেভ হয়, Machinery-র vendor-এ নয়। ইমেইল যাচাই হয়।
  - **পারমিশন:** ম্যানেজার শুধু দেখতে পারে, স্টোরকিপার বদলাতে পারে, টেকনিশিয়ান কিছুই পায় না।
- ব্রাউজার (dev):
  - শুরুর চারটা তালিকা এসেছে।
  - A4 Paper (Stationery, REAM, Opening 10) যোগ করার পর স্টক ১০, অবস্থা "কম"।
  - স্টোরের সাপ্লায়ার যোগ করা হয়েছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **Department:** স্টোরের ডিপার্টমেন্ট আলাদা তালিকা (`gs_departments`), Brickland-এর মতোই। Company Management-এর ডিপার্টমেন্টের সাথে এক করা হবে কিনা, তা ঠিক হবে HR & Payroll তৈরির সময়।
- **শুরুর ১২টা ডিপার্টমেন্ট:** স্ক্রিনশটে পড়া যায় প্রথম ১০টা (ACC … QA)। শেষ দুটো ধরে নেওয়া হয়েছে SEW (Sewing) আর STORE (Store)।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- প্রথম সংস্করণ বসানো হয়েছিল আগের আইটেম লাইব্রেরি, ইউনিট, স্টোর আর vendor-এর ওপর। ব্যবহারকারীর সিদ্ধান্ত হলো Machinery বাদে সবকিছু নতুন, তাই এখন সব `gs_` টেবিলে, স্টকের নিজের movement-লেজারসহ। আগের মডেলগুলোয় করা পরিবর্তন ফিরিয়ে নেওয়া হয়েছে।
- **পারফরম্যান্স:** আইটেম তালিকায় ক্যাটাগরি, ইউনিট আর স্টক এখন subselect দিয়ে একই কোয়েরিতে আসে, প্রতিটা সারির জন্য আলাদা কোয়েরি হয় না।
- কিটের তালিকা এখনো সর্বোচ্চ ২০০০ সারি দেখায়, এর বেশি হলে শিরোনামে "2000+" আসে। সার্ভার-সাইড পেজিনেশন Stock Ledger বা রিপোর্টের সাথে যোগ হবে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- ছয়টা তালিকাই এখন Brickland-এর একটা কার্ড: শিরোনাম "Item (1)", ডানে Excel আর New; নিচে লেবেলসহ "Search" (Code or name) আর Item Master-এ "Category"; তারপর সাধারণ টেবিল (বড় হাতের হেডার, sort/pager নেই, খালি হলে "No supplier yet." ধরনের লেখা)।
- পেজের বর্ণনা Brickland-এর লেখায় বদলানো হয়েছে, আর Item Master-এর কার্ডে সাবটাইটেল যোগ হয়েছে।
