# Item Specification

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/04`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Garment Spec Entry | `/m/ie/gmt-item-specification-entry` | 3 | 35 | 1 |
| 2 | Garment Item Specification | `/m/ie/gmt-item-specification` | 4 | 26 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Garment Spec Entry

`/m/ie/gmt-item-specification-entry`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Garments Item Specification, New item specification

**ট্যাব: Garments Item Specification**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing specification | select |  |  |
| Style#* | text | ✔ |  |
| Variation* | number | ✔ |  |
| Description* | text | ✔ |  |
| Fabric | select |  | Fleece, Pique, Single Jersey, Twill |
| Nature | select |  | Basic (0-12), Semi Critical (12.01-20), Critical (20.01-35), Highly Critical (35.01-99) |
| Manpower OP* | number | ✔ |  |
| Manpower ASO* | number | ✔ |  |
| SMV Sewing* | number | ✔ |  |
| SMV Iron (MP) | number |  |  |
| SMV Poly (MP) | number |  |  |
| Efficiency Avg | number |  |  |
| Efficiency Pick | number |  |  |
| Efficiency Blncing | number |  |  |
| OB File Upload | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| SMV/Artwork? | checkbox |  |  |
| Fusing? | checkbox |  |  |
- সেকশন: Garments Item Specification, Style Info, Core Information, Technical Specification, M/C Requirement
- বাটন/অ্যাকশন: Garments Item Specification, New item specification, --Select--, Add process group, Add machine, Clear, Save specification

**ট্যাব: New item specification**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Style#* | text | ✔ |  |
| Variation* | number | ✔ |  |
| Description* | text | ✔ |  |
| Fabric | select |  | Select…, Fleece, Pique, Single Jersey, Twill |
| Nature | select |  | Select…, Basic (0-12), Semi Critical (12.01-20), Critical (20.01-35), Highly Critical (35.01-99) |
| Manpower OP* | number | ✔ |  |
| Manpower ASO* | number | ✔ |  |
| SMV Sewing* | number | ✔ |  |
| SMV Iron | number |  |  |
| SMV Poly | number |  |  |
| Efficiency Avg | number |  |  |
| Efficiency Pick | number |  |  |
| Efficiency Balancing | number |  |  |
| OB File Upload | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| SMV/Artwork? | checkbox |  |  |
| Fusing? | checkbox |  |  |
- সেকশন: New item specification
- বাটন/অ্যাকশন: Garments Item Specification, New item specification, --Select--, Reset, Save item specification

**ডায়ালগ: New item specification**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style#* | text | ✔ |  |
| Variation* | number | ✔ |  |
| Description* | text | ✔ |  |
| Fabric | select |  | Select…, Fleece, Pique, Single Jersey, Twill |
| Nature | select |  | Select…, Basic (0-12), Semi Critical (12.01-20), Critical (20.01-35), Highly Critical (35.01-99) |
| Manpower OP* | number | ✔ |  |
| Manpower ASO* | number | ✔ |  |
| SMV Sewing* | number | ✔ |  |
| SMV Iron | number |  |  |
| SMV Poly | number |  |  |
| Efficiency Avg | number |  |  |
| Efficiency Pick | number |  |  |
| Efficiency Balancing | number |  |  |
| OB File Upload | text |  |  |
| Attach | file |  |  |
| SMV/Artwork? | checkbox |  |  |
| Fusing? | checkbox |  |  |
- খালি Save-এ মেসেজ: Sewing SMV must be greater than zero.

#### Garment Item Specification

`/m/ie/gmt-item-specification`

**ট্যাব: GMT Item List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Booking Month -- Select Month -- | select |  |  |
| Company -- All Company -- Brickland Composite Ltd. (BCL) | select |  | Brickland Composite Ltd. (BCL) |
| Unit --All Unit -- Brickland Composite Ltd. Unit-1 (FAC-001) | select |  | Brickland Composite Ltd. Unit-1 (FAC-001) |
| Item Group -- All Item Group -- Basic Fancy Polo | select |  | Basic, Fancy, Polo |
| Item Category -- All Item Category -- | select |  |  |
| Gmt Type -- All GmtType -- | select |  |  |
| All | radio |  |  |
| N | radio |  |  |
| R | radio |  |  |
| My Buyer? | search |  | Search Style/Order... |
| My Buyer? | checkbox |  |  |
| !InH? | checkbox |  |  |
- সেকশন: Order nature
- বাটন/অ্যাকশন: GMT Item List, Gmt Tech Variations: Entry, Itm Group & Category, New tech variation, Search, All

**ট্যাব: Gmt Tech Variations: Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search categorys | search |  | Search… |
- সেকশন: Itm Category, Gmt Tech Variations
- বাটন/অ্যাকশন: GMT Item List, Gmt Tech Variations: Entry, Itm Group & Category, New tech variation, Basic T-Shirt Knit Tops 0, Polo Shirt Knit Tops 0, Pullover Hoodie Knit Tops 0, Twill Trouser Bottoms 0

**ট্যাব: Itm Group & Category**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search item groups | search |  | Search… |
- সেকশন: Itm Group, Itm Category
- বাটন/অ্যাকশন: GMT Item List, Gmt Tech Variations: Entry, Itm Group & Category, New tech variation, New Itm Group, Knit Tops GRP-KNIT 3, Bottoms GRP-BOTM 1, Outerwear GRP-OUTR 0

**ট্যাব: New tech variation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Itm Group* | select | ✔ | Select…, Knit Tops, Bottoms, Outerwear |
| Itm Category* | select | ✔ | Select…, Knit Tops — Basic T-Shirt, Knit Tops — Polo Shirt, Knit Tops — Pullover Hoodie, Bottoms — Twill Trouser |
| Technical Spec.* | text | ✔ |  |
| OP | number |  |  |
| ASO | number |  |  |
| SMV | number |  |  |
| 1 - 2000 | number |  |  |
| 2001 - 5000 | number |  |  |
| 5001 - 7000 | number |  |  |
| 7001 - 10000 | number |  |  |
| 10001 - 15000 | number |  |  |
| 15001 + | number |  |  |
- সেকশন: New tech variation
- বাটন/অ্যাকশন: GMT Item List, Gmt Tech Variations: Entry, Itm Group & Category, New tech variation, Reset, Save tech variation

**ডায়ালগ: New tech variation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Itm Group* | select | ✔ | Select…, Knit Tops, Bottoms, Outerwear |
| Itm Category* | select | ✔ | Select…, Knit Tops — Basic T-Shirt, Knit Tops — Polo Shirt, Knit Tops — Pullover Hoodie, Bottoms — Twill Trouser |
| Technical Spec.* | text | ✔ |  |
| OP | number |  |  |
| ASO | number |  |  |
| SMV | number |  |  |
| 1 - 2000 | number |  |  |
| 2001 - 5000 | number |  |  |
| 5001 - 7000 | number |  |  |
| 7001 - 10000 | number |  |  |
| 10001 - 15000 | number |  |  |
| 15001 + | number |  |  |
- খালি Save-এ মেসেজ: Itm Category is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy` (14/01-এর পরে), নতুন টেবিল `ws_` নামে।
- **14/01 থেকে নেওয়া:**
  - গার্মেন্ট আইটেম (`ws_garment_items`)। এগুলোই এখন Itm Category, নিজস্ব কোড আর SL পেয়েছে।
  - ফ্যাব্রিক ক্লাস আর জটিলতার মাত্রা (Learning Curve-এর তালিকা)।
  - মেশিনের ধরন (Resource Capability Profile-এর তালিকা: SNLS, Overlock …)।
  - Responsible Owner Mapping (GMT Item List-এর "My Buyer?"-এর জন্য)।
- **Merchandise Desk থেকে নেওয়া:**
  - Garment Part Master-এ যেগুলো "Process Group" চিহ্নিত, সেগুলো (Technical Specification-এর Process Group)।
  - Sample Type Master (SMV/SampleType)।
  - অর্ডার আর style item (GMT Item List)।
- **ফাইল:** OB ফাইল যুক্ত হয় প্ল্যাটফর্মের `StoreFileAttachment` দিয়ে, Finishing-এর key image-এর মতো। নতুন ধরন `ws_spec_ob_file` FileApiController-এর permission map-এ যোগ করা হয়েছে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_30_000100_create_work_study_specifications`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_item_groups` | name, code (GRP-KNIT …), sort_order | company + name, company + code |
| `ws_garment_items` (14/01-এর, নতুন ঘরসহ) | item_group (গ্রুপের নাম), name, **code** (ITM-TSHIRT …), **sl**, active | — |
| `ws_tech_variations` | garment_item_id, tech_spec, op, aso, smv, band1…band6 (১–২০০০ … ১৫০০১+), sort_order | — |
| `ws_specs` | style_no, variation_no, description, fabric_class, criticality, op_manpower, aso_manpower, sewing_smv, iron_smv, poly_smv, avg/pick/balancing_efficiency, ob_file_path, is_smv_artwork, is_fusing, sample_type_id, created_by | company + style + variation |
| `ws_spec_processes` | spec_id, garment_part_id, specification, sort_order | — |
| `ws_spec_machines` | spec_id, machine_type, requirement, sort_order | — |

## ৫. Workflow / Status ও Business Rules

- **Garment Spec Entry:**
  - একটা স্টাইলে একটা ভেরিয়েশন নম্বর একবারই থাকে ("SS27-TEE01 already has variation 1.")। স্টাইল নং বড় হাতে রাখা হয়।
  - সেলাই SMV শূন্যের বেশি হতে হয় (Brickland-এর মেসেজ: "Sewing SMV must be greater than zero.")।
  - দক্ষতা শতাংশে, ১০০-এর বেশি নয়।
  - **Nature:** না বাছলে সেলাই SMV থেকে বসে: ০–১২ Basic, ১২.০১–২০ Semi Critical, ২০.০১–৩৫ Critical, তার বেশি Highly Critical।
  - **Process group আর মেশিন:**
    - একটা প্রসেস গ্রুপ আর একটা মেশিনের ধরন একবারই থাকে।
    - মেশিনের প্রয়োজন অন্তত ১।
    - এগুলো ছাড়াও স্পেসিফিকেশন সেভ হয়, Brickland-এর নিয়মে।
  - **Edit:** Edit-এ যে তালিকা পাঠানো হয় না সেটা আগের মতো থাকে, পাঠানো তালিকা পুরো বদলে যায়।
  - **New item specification ট্যাব:** তালিকা ছাড়া শুধু হেডার সেভ হয়।
  - **OB ফাইল:** পাথ বা লিংক লেখা যায়, অথবা Attach দিয়ে ফাইল যুক্ত হয়ে তার ডাউনলোড-লিংক বসে।
- **Itm Group & Category:**
  - **শুরুর ডাটা:** প্রথমবার খুললে Brickland-এর তিনটা গ্রুপ (Knit Tops GRP-KNIT, Bottoms GRP-BOTM, Outerwear GRP-OUTR) আর ক্যাটাগরির কোড (ITM-TSHIRT, ITM-POLO, ITM-HOODIE, ITM-TROUSER) তৈরি হয়।
  - আগে থেকে থাকা যেকোনো গ্রুপের নামও গ্রুপ হয়। কোড না থাকলে নাম থেকে তৈরি হয় (GRP-KIDS, ITM-CREWNECKSW)।
  - একটা গ্রুপে একই নামের ক্যাটাগরি দুবার থাকে না।
  - টেক ভেরিয়েশন বা কম্পোনেন্ট ম্যাপিংয়ে (14/01) ব্যবহার হওয়া ক্যাটাগরি সরানো যায় না। তার বদলে Active টিক তুলে দিতে হয়।
  - ক্যাটাগরি আছে এমন গ্রুপ মোছা যায় না। গ্রুপের নাম বদলালে তার ক্যাটাগরিও সাথে যায়।
- **Gmt Tech Variations:**
  - প্রতিটা লাইনে টেকনিক্যাল স্পেক লাগে।
  - ব্যান্ডগুলো ছাপা অর্ডার-পরিমাণের পরিসর, প্রতিটায় সেই পরিমাণের SMV।
  - **New tech variation:** ক্যাটাগরি না দিলে Brickland-এর মেসেজ আসে: "Itm Category is required."। অন্য গ্রুপের ক্যাটাগরি বাছলে আটকায়।
- **GMT Item List:**
  - প্রতিটা অর্ডার (বাতিল বা প্রজেকশন বাদে) আর তার style item দেখায়, সাথে সেই স্টাইলের সর্বশেষ ভেরিয়েশনের SMV, OP আর ASO।
  - **ফিল্টার:**
    - Booking Month আসে নিশ্চিতের তারিখ থেকে, না থাকলে অর্ডারের তারিখ থেকে।
    - Unit, Item Group, Item Category, Gmt Type।
    - N/R: নতুন বনাম Repeat অর্ডার।
    - স্টাইল বা অর্ডার খোঁজা।
    - **My Buyer?:** Responsible Owner Mapping-এ নিজের বায়ার।
    - **!InH?:** ইউনিট ছাড়া অর্ডার।
    - Search চাপলে ফিল্টার খাটে।
  - CM/Dz আর BgtExp% আপাতত ০, কারণ এগুলো আসবে Spec-Wise CM Rate (14/05) থেকে। Brickland-এর নোটও বলে, রেট না থাকলে শূন্য দেখায়।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-tech-variations|ws-item-groups}`।
- **নিজস্ব endpoint** (`WsSpecApiController`):
  - `GET /workstudy/spec-options`
  - `GET|POST /workstudy/specs`
  - `GET|PUT /workstudy/specs/one?style=&v=`
  - `POST /workstudy/ob-files`
  - `GET /workstudy/item-list`
  - `GET|PUT /workstudy/item-groups/{code}/categories`
  - `GET|PUT /workstudy/categories/{code}/variations`
  - স্পেসিফিকেশন খোলে স্টাইল আর ভেরিয়েশন নম্বর দিয়ে, গ্রুপ আর ক্যাটাগরি তাদের কোড দিয়ে।
- **পারমিশন:** নতুন `workstudy.spec.view` আর `workstudy.spec.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Item Specification-এ দুটো স্ক্রিন।
- **Garment Spec Entry** (Records | Garments Item Specification | New item specification):
  - Records-এ Edit চাপলে `?tab=planner&style=&v=` খোলে।
  - **Planner:**
    - আগের ভেরিয়েশন বাছা (URL বদলায়, রিলোড ছাড়া)।
    - Style Info আর Core Information-এর ফিল্ডসেট।
    - OB ফাইল-এর পাথ বা Attach।
    - Technical Specification আর M/C Requirement-এর তালিকা (মোটসহ)।
    - Clear আর Save।
  - New: EntryForm, OB ফাইল `filepath` ধরনে।
- **Garment Item Specification** (Records | GMT Item List | Gmt Tech Variations: Entry | Itm Group & Category | New tech variation):
  - Brickland-এর মতো GMT Item List দিয়ে খোলে।
  - Tech Variation আর Category-র ট্যাব 14/01-এর `SideGrid` দিয়ে বানানো: বাঁদিকে তালিকা, ডানদিকে জায়গায় বদলানো যায় এমন তালিকা, Revert আর Save। New Itm Group একটা dialog।
  - Item List-এ Print আছে (ব্রাউজারের প্রিন্ট, ফিল্টার লুকিয়ে)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_30_000100_create_work_study_specifications.php`
- `app/Modules/WorkStudy/Models/WsItemGroup.php`
- `app/Modules/WorkStudy/Models/WsTechVariation.php`
- `app/Modules/WorkStudy/Models/WsSpec.php`
- `app/Modules/WorkStudy/Models/WsSpecProcess.php`
- `app/Modules/WorkStudy/Models/WsSpecMachine.php`
- `app/Modules/WorkStudy/Models/WsGarmentItem.php`
- `app/Modules/WorkStudy/Support/WsSpecs.php`
- `app/Modules/WorkStudy/Resources/TechVariationResource.php`
- `app/Modules/WorkStudy/Resources/ItemGroupResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsSpecApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/spec-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/app/api/work-study/ob-file/route.js`
- `frontend/src/components/workstudy/spec-entry.jsx`
- `lang/en/workstudy.php`
- `lang/bn/workstudy.php`

**Tests**
- `tests/Feature/WorkStudy/WorkStudySpecificationTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudySpecificationTest` ২/২ (৭৭ assertion):**
  - **স্পেসিফিকেশন:**
    - Process Group-এর তালিকায় শুধু চিহ্নিত পার্ট আসে।
    - শূন্য SMV-তে Brickland-এর মেসেজ আসে। ১০১% দক্ষতা চলে না। একই মেশিনের ধরন দুবার চলে না।
    - নতুন স্পেসিফিকেশনে Nature আসে Basic (৮.৫), মোট মেশিন ২৮। ভেরিয়েশন দুবার চলে না।
    - New ট্যাবে ১৪.২ দিলে Semi Critical।
    - Edit-এ তালিকা রাখা আর বদলানো দুটোই কাজ করে। OB ফাইল যুক্ত হয়ে তার লিংক থাকে।
  - **Item List:** স্টাইলের সর্বশেষ ভেরিয়েশন আসে। N/R, !InH, খোঁজা, মাস আর My Buyer ফিল্টার কাজ করে।
  - **গ্রুপ আর ক্যাটাগরি:**
    - Brickland-এর ৩টা গ্রুপ আর ৪টা কোড আসে।
    - নতুন গ্রুপ GRP-KIDSWEAR হয়, দুবার চলে না। ক্যাটাগরি দুবার চলে না। নতুন ক্যাটাগরির কোড ITM-CREWNECKSW।
  - **টেক ভেরিয়েশন:** ক্যাটাগরি ছাড়া মেসেজ আসে, অন্য গ্রুপের ক্যাটাগরিতে আটকায়, জায়গায় সেভ হয়, টেক স্পেক ছাড়া আটকায়।
  - **মোছা আর নাম বদল:** ব্যবহৃত ক্যাটাগরি আর ক্যাটাগরিসহ গ্রুপ সরানো যায় না। গ্রুপের নাম বদলালে ক্যাটাগরি সাথে যায়।
  - **পারমিশন:** স্টোর ম্যানেজার পায় না, ফ্যাক্টরি ম্যানেজার পায়।
- **পুরনো টেস্ট আবার:** WorkStudyMasterTest, ApiGateCoverage, MenuAccess, RoleMatrix সব পাস।
- **ব্রাউজার (dev, wu01–wu04, ডাটা রাখা আছে):**
  - **স্পেসিফিকেশন:** SS27-TEE01/1 (Ladies crew neck tee, Single Jersey, OP ৩০, ASO ৬, SMV ৯.২, ৬২%), Collar প্রসেস গ্রুপ, SNLS ১৬ আর Overlock ১২, OB PDF যুক্ত।
  - **Item List:** LPP-2026-000001 এখন Specified (৯.২ / ৩০ / ৬)।
  - **ক্যাটাগরি আর ভেরিয়েশন:** Knit Tops-এ "Ladies Tank Top Racer" যোগ। Basic T-Shirt-এ একটা টেক ভেরিয়েশন (SMV ৯.২, ১–২০০০-এ ৯.৮)। খালি New tech variation-এ Brickland-এর মেসেজ আসে।
  - কনসোলে error নেই।
- dev-এ Garment Part Master-এর Collar, Cuff, Pocket আর Waistband-কে Process Group চিহ্নিত করা হয়েছে (Brickland-এর তালিকার মতো)।

## ১০. খোলা প্রশ্ন

- **OB ফাইলের ধরন:** মীমাংসিত (২০২৬-১০-২৫)। প্ল্যাটফর্মের অনুমোদিত তালিকায় Excel (XLSX, XLS) যোগ হয়েছে, তাই OB ফাইল এখন Excel-এও যুক্ত করা যায়।
- **GMT Item List-এর কলাম:** Brickland-এর demo-তে Booking Month-এর তালিকা খালি, তাই আসল কলাম দেখা যায়নি। এখানে কলাম ঠিক করা হয়েছে অর্ডার, আইটেম, স্পেসিফিকেশন আর CM-এর নোট থেকে। CM/Dz আর BgtExp% এখন 14/05-এর Spec-Wise CM Rate থেকে আসে।
- **ক্রমিক:** Brickland-এ এই দুটো স্ক্রিন "10 of 22" আর "21 of 22", যা ফোল্ডারের ক্রম মানে না। Work Study-র বাকি গ্রুপ তৈরি হলে মেলানো হবে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৫):**
- দুটো স্ক্রিনই তৈরি। 14/01-এর অস্থায়ী গার্মেন্ট আইটেম টেবিলই এখন Brickland-এর Itm Category। 14/01-এর Item & Process Grouping আগের মতোই সেটা পড়ে।
- **Brickland প্রোব (ws01–ws04):** লাইভ demo-তে একটা স্পেসিফিকেশন সেভ করা হয়েছে (529/1 "Annotech probe crew neck tee")।
