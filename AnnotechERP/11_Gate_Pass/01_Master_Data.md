# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Gate Pass](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `11/01`

**⬅ ফিরে যান:** [Gate Pass](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Gate Pass Category | `/m/gate-pass/gp-categories` | 1 | 0 | 1 |
| 2 | Location Type | `/m/gate-pass/gp-location-types` | 1 | 0 | 1 |
| 3 | Factory / Location | `/m/gate-pass/gp-locations` | 1 | 0 | 1 |
| 4 | Item / Product | `/m/gate-pass/gp-items` | 1 | 0 | 1 |
| 5 | Purpose / Reason | `/m/gate-pass/gp-purposes` | 1 | 0 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Gate Pass Category

`/m/gate-pass/gp-categories`

**ভিউ**
> 08 of 13
- গ্রিড কলাম: CODE, NAME, KIND, DESCRIPTION, STATUS
- সেকশন: Gate Pass Types, Gate Pass Category (2)
- বাটন/অ্যাকশন: New Gate Pass Category, Edit

**ডায়ালগ: New Gate Pass Category** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Main Gate Pass (Employee) | radio |  |  |
| Item Gate Pass (Product/Material) | radio |  |  |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new gate passes. | checkbox |  |  |

#### Location Type

`/m/gate-pass/gp-location-types`

**ভিউ**
> 09 of 13
- গ্রিড কলাম: CODE, NAME, LOCATIONS, DESCRIPTION, STATUS
- সেকশন: Location Type (10)
- বাটন/অ্যাকশন: New Location Type, Edit

**ডায়ালগ: New Location Type** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new gate passes. | checkbox |  |  |

#### Factory / Location

`/m/gate-pass/gp-locations`

**ভিউ**
> 10 of 13
- গ্রিড কলাম: CODE, LOCATION, TYPE, ADDRESS, CONTACT PERSON, MOBILE, STATUS
- সেকশন: Factory / Location (0)
- বাটন/অ্যাকশন: New Factory / Location

**ডায়ালগ: New Factory / Location** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Location Name* | text | ✔ |  |
| Contact Person | text |  |  |
| Mobile | text |  |  |
| Email | text |  |  |
| Address | textarea |  |  |
| Remarks | textarea |  |  |
| Active Inactive entries are not offered on new gate passes. | checkbox |  |  |

#### Item / Product

`/m/gate-pass/gp-items`

**ভিউ**
> 11 of 13
- গ্রিড কলাম: CODE, NAME, ITEM CATEGORY, UNIT, DESCRIPTION, STATUS
- সেকশন: Item / Product (0)
- বাটন/অ্যাকশন: New Item / Product

**ডায়ালগ: New Item / Product** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Item Name* | text | ✔ |  |
| Item Category* | select | ✔ | Select…, Finished Garments, Fabric, Trims & Accessories, Sample Garments, Packing Material, Machinery, Equipment, Docume |
| Unit* | select | ✔ | Select…, Pcs, Dozen, Set, Kg, Yds, Mtr, Roll, Carton, Box, Bag, Ltr, Nos |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new gate passes. | checkbox |  |  |

#### Purpose / Reason

`/m/gate-pass/gp-purposes`

**ভিউ**
> 12 of 13
- গ্রিড কলাম: CODE, NAME, DESCRIPTION, STATUS
- সেকশন: Purpose / Reason (14)
- বাটন/অ্যাকশন: New Purpose / Reason, Edit

**ডায়ালগ: New Purpose / Reason** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Description | textarea |  |  |
| Active Inactive entries are not offered on new gate passes. | checkbox |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- আগে কোনো গেট পাস কোড ছিল না। নতুন মডিউল `app/Modules/GatePass`।
- **ইউনিট:** Brickland-এর গেট পাস আইটেমের নিজের স্থির ইউনিট-তালিকা আছে (Pcs, Dozen, Set, Kg, Yds, Mtr, Roll, Carton, Box, Bag, Ltr, Nos)। এখানেও তাই, General Store-এর ওপর কোনো নির্ভরতা নেই।
- **লেআউট:** General Store-এর কিট (`CatalogCard` আর ডায়ালগ) এখানেও ব্যবহার হয়েছে। এটা শুধু ফ্রন্টএন্ডের কম্পোনেন্ট, কোনো ডাটা শেয়ার হয় না।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_10_31_000100_create_gate_pass_masters`। সব টেবিল কোম্পানির নিজের, প্রতিটায় unique company + code:

| টেবিল | ফিল্ড |
|---|---|
| `gate_pass_categories` | code, name, kind (MAIN = কর্মী, ITEM = প্রোডাক্ট/ম্যাটেরিয়াল), description, active |
| `gate_pass_location_types` | code, name, description, active |
| `gate_pass_locations` | location_type_id, code, name, contact_person, mobile, email, address, remarks, active |
| `gate_pass_items` | code, name, item_category, unit (PCS/DZN/SET/KG/YDS/MTR/ROLL/CTN/BOX/BAG/LTR/NOS), description, active |
| `gate_pass_purposes` | code, name, description, active |

item_category-র মান Brickland-এর তালিকা অনুযায়ী: FINISHED_GARMENTS, FABRIC, TRIMS, SAMPLE_GARMENTS, PACKING_MATERIAL, MACHINERY, EQUIPMENT, DOCUMENTS, OTHER।

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা:** কোম্পানি প্রথমবার তালিকা খুললে তৈরি হয়, শুধু খালি তালিকায়। পরে যতবার খোলা হোক, আর যোগ হয় না।
  - ২টা ক্যাটাগরি (ITEM, MAIN)।
  - ১০টা লোকেশনের ধরন (LT-01 … LT-10)।
  - ১২টা উদ্দেশ্য (PUR-01 … PUR-12)।
- **কোড:** বড় হাতে রাখা হয়, আর ছোট-বড় হাতের অক্ষর নির্বিশেষে unique।
- **মোছা:** কিছুই মোছা যায় না (405)। নিষ্ক্রিয় এন্ট্রি নতুন গেট পাসে দেখানো হবে না।
- **লোকেশন:** একটা লোকেশনের ধরন বাধ্যতামূলক। প্রতিটা ধরনের পাশে তার লোকেশনের সংখ্যা দেখায়।
- **"Gate Pass Types" কার্ড:** Returnable (OUT ও IN) আর Non-Returnable (শুধু OUT)। এই দুটো মডিউলের নিয়মে স্থির, কোনো তালিকা নয়। গেট পাস তৈরির সময় (11/02) এগুলো কার্যকর হবে।

## ৬. API ও পারমিশন

- স্ক্রিন-কিটের resource: `/api/v1/erp/{gatepass-categories|gatepass-location-types|gatepass-locations|gatepass-items|gatepass-purposes}`।
- **পারমিশন:** নতুন `gatepass.master.view` আর `gatepass.master.manage`।
  - manage পায় FACTORY_ADMIN আর STORE_MANAGER।
  - view পায় FACTORY_MANAGER, STOREKEEPER আর read-only রোল।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/gate-pass/<screen>`। সাইডবারে INVENTORY & STORES → Gate Pass → MASTER DATA।
- প্রতিটা স্ক্রিন একটা কার্ড, শিরোনামে সংখ্যা (যেমন "Location Type (10)"), New ডায়ালগ আর প্রতিটা সারিতে Edit।
- Gate Pass Category-র ওপরে Brickland-এর "Gate Pass Types" কার্ড।
- Brickland-এর Main/Item radio এখানে select।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GatePass/Database/Migrations/2026_10_31_000100_create_gate_pass_masters.php`
- `app/Modules/GatePass/Models/GatePassCategory.php`
- `app/Modules/GatePass/Models/GatePassLocationType.php`
- `app/Modules/GatePass/Models/GatePassLocation.php`
- `app/Modules/GatePass/Models/GatePassItem.php`
- `app/Modules/GatePass/Models/GatePassPurpose.php`
- `app/Modules/GatePass/Support/GatePassDefaults.php`
- `app/Modules/GatePass/Resources/GatePassMasterResource.php`
- `app/Modules/GatePass/Resources/GatePassCategoryResource.php`
- `app/Modules/GatePass/Resources/GatePassLocationTypeResource.php`
- `app/Modules/GatePass/Resources/GatePassLocationResource.php`
- `app/Modules/GatePass/Resources/GatePassItemResource.php`
- `app/Modules/GatePass/Resources/GatePassPurposeResource.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/gate-pass/[screen]/page.js`
- `frontend/src/app/(app)/gate-pass/[screen]/screens.js`
- `frontend/src/app/(app)/gate-pass/[screen]/actions.js`
- `lang/en/gatepass.php`
- `lang/bn/gatepass.php`

**Tests**
- `tests/Feature/GatePass/GatePassMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `GatePassMasterTest` ৩/৩:
  - **শুরুর ডাটা:** ২টা ক্যাটাগরি, ১০টা লোকেশনের ধরন আর ১২টা উদ্দেশ্য আসে। একটার নাম বদলে আবার খুললেও দ্বিতীয়বার যোগ হয় না।
  - **কোড আর kind:** ছোট-বড় হাতের অক্ষর নির্বিশেষে কোড unique। অচেনা kind বা item category চলে না।
  - **লোকেশন আর আইটেম:** লোকেশনে ধরন লাগে, আর ধরনের পাশে লোকেশন সংখ্যা ঠিক আসে। আইটেমে ইউনিট লাগে। মোছা যায় না।
  - **ইউনিট:** গেট পাসের নিজের তালিকার বাইরের ইউনিট চলে না।
  - **পারমিশন:** ম্যানেজার পড়তে পারে, কিন্তু বদলাতে পারে না। স্টোর ম্যানেজার বদলাতে পারে। টেকনিশিয়ান কিছুই পায় না।
- ব্রাউজার (dev):
  - Gate Pass Types কার্ড আর দুটো ক্যাটাগরি দেখা গেছে।
  - ১০টা লোকেশনের ধরন এসেছে।
  - ডায়ালগ থেকে Star Embroidery (Embroidery Factory) আর Polo Shirt (Finished Garments, PCS) যোগ করা হয়েছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **উদ্দেশ্যের শুরুর তালিকা:** Brickland-এ ১৪টা, কিন্তু crawl-এর স্ক্রিনশটে পড়া যায় প্রথম ১১টা। এখানে সেই ১১টা আর "Other" মিলিয়ে ১২টা দিয়ে শুরু। বাকিগুলো কোম্পানি নিজে যোগ করবে।
- Factory / Location ডায়ালগে Brickland-এর মতোই লোকেশনের ধরন বাধ্যতামূলক। crawl-এর টেক্সট-তালিকায় ঘরটা আসেনি, কিন্তু ডায়ালগের স্ক্রিনশটে আছে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- পাঁচটা তালিকাই এখন Brickland-এর একটা কার্ড: শিরোনাম আর New একই কার্ডে, তারপর সাধারণ টেবিল (সার্চ/এক্সপোর্ট/pager নেই, খালি হলে "No factory / location yet.")। Edit আগের মতো ডায়ালগে।
