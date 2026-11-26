# Lab-Dip

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/02`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dosing Template Master | `/m/dyeing/dosing-template` | 1 | 14 | 1 |
| 2 | Lab Dip Recipe Entry | `/m/dyeing/lab-dip-recipe` | 2 | 11 | 1 |
| 3 | Lab Dip Recipe Lookup | `/m/dyeing/lab-dip-recipe-view` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dosing Template Master

`/m/dyeing/dosing-template`

**ট্যাব: Detail Template**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Template List | search |  | search… |
| Template Code | text |  | Issued on save |
| Template Name* | text | ✔ | CONVENTIONAL FOR REACTIVE |
| Short Name | text |  | CONV/REACT |
| Dyeing Method* | select | ✔ | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Color Group* | select | ✔ | White, Light, Medium, Dark, Extra Dark, Mellange/YD, PC/CVC, Black/Royal Blue |
| Template Type* | select | ✔ | Pre Treatment, Dyeing, Post Treatment, Finishing, Wash |
| Is Finalize? Until this is ticked the curve is still being written, and no lab d | checkbox |  |  |
| Step No | number |  |  |
| Dye Method | select |  | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Dye Phase | select |  | Pre-Treatment, Dyeing, After-Treatment, Finishing |
| Is Lab-dip | checkbox |  |  |
| Is LN BRK | checkbox |  |  |
| Dose | number |  |  |
- গ্রিড কলাম: Template
- সেকশন: Template List, Detail Template, Agents
- বাটন/অ্যাকশন: Detail Template, CONVENTIONAL FOR REACTIVE, BIO-SCOURING FOR REACTIVE, DEMINERALIZATION FOR REACTIVE, WHITE COTTON (OBA), WHITE PC/CVC (TWO PART ), CONVENTIONAL FOR DISPERSED, BIO-SCOURING FOR DISPERSED, DEMINERALIZATION FOR DISPERSED, 2 PART DYEING CONVENSONAL, 2 PART FIRST SCOURING, Mellange and Y/D WASH, Machine Wash, Finishing Template, DEMINERALIZATION FOR PIGMENT, Lab-dip Template, Add, Cancel, -- Select Process --, -- Select Category --

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Template List | search |  |  |
| Template Code | text |  |  |
| Template Name* | text | ✔ |  |
| Short Name | text |  |  |
| Dyeing Method* | select | ✔ | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Color Group* | select | ✔ | White, Light, Medium, Dark, Extra Dark, Mellange/YD, PC/CVC, Black/Royal Blue |
| Template Type* | select | ✔ | Pre Treatment, Dyeing, Post Treatment, Finishing, Wash |
| Is Finalize? Until this is ticked the curve is still being written, and no lab d | checkbox |  |  |
| Step No | number |  |  |
| Dye Method | select |  | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Dye Phase | select |  | Pre-Treatment, Dyeing, After-Treatment, Finishing |
| Is Lab-dip | checkbox |  |  |
| Is LN BRK | checkbox |  |  |
| Dose | number |  |  |
- খালি Save-এ মেসেজ: Dyeing Method is required.

#### Lab Dip Recipe Entry

`/m/dyeing/lab-dip-recipe`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style # | select |  |  |
| Recipe # | search |  | LD-… |
| Search Every lab dip on file — the buyer and style it was worked out for, the sh | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style No, Color Type, Color, Fabric Type, Color Group, Dyeing Method, Fiber Comp, Recipe No, Option No, Created By, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Buyer Name, Style No, Color Type, Color, Fabric Type, Color Group, Dyeing Method, Fiber Comp, Recipe No, Option No, Created By

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Recipe #* | text | ✔ | LD-2026-0001 |
| Option No* | number | ✔ |  |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Fabric Type | select |  | 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Dyeing Method | select |  | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Fiber Comp | text |  | 95% Cotton 5% Elastane |
- সেকশন: New recipe
- বাটন/অ্যাকশন: Add New, -- Select Style --, -- Select Colour --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Recipe #* | text | ✔ |  |
| Option No* | number | ✔ |  |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Fabric Type | select |  | 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Dyeing Method | select |  | 2 Part Dye, Discharge, Dispersed, N/A, Pigment, Reactive, Wht. Dyeing |
| Fiber Comp | text |  |  |
- খালি Save-এ মেসেজ: Buyer A/C is required.

#### Lab Dip Recipe Lookup

`/m/dyeing/lab-dip-recipe-view`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style # | select |  |  |
| Recipe # | search |  | LD-… |
| Search Lab dips as the merchandising desk reads them — buyer, style, shade and t | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style No, Color, Fabric Type, Fiber Comp, Recipe No, Option No
- বাটন/অ্যাকশন: Clear, Export, Buyer Name, Style No, Color, Fabric Type, Fiber Comp, Recipe No, Option No
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **মাস্টার:** 17/01-এর Dye Method আর Colour Group, Merchandise-এর Buyer, Style (`merch_styles`), Colour আর Fabric Type। এজেন্ট আসে Item Master থেকে, Dyes Chemical Store (`DYES_CHEMICAL_STORE`) বিভাগের আইটেম। ইউনিট আসে units-of-measure মাস্টার থেকে (`UomSelect`, `UomCodes`)।
- **রেসিপি:** screen-kit resource। Brickland-এর in-place Edit ("Edit recipe — Rewriting LPP …", Update recipe, Cancel) `RecordsGrid`-এ আগে থেকেই ছিল। URL-এ রেসিপির পড়ার মতো রেফারেন্স যায় (`?edit=LD-2026-0001-1`), ULID না।
- **টেমপ্লেট এডিটর:** বাঁয়ে তালিকা, ডানে ফর্ম। সাজানো হয়েছে Dye Sub-Contract Party Rate-এর এডিটরের ধাঁচে, মানে এন্ট্রি সারিতে লিখে Add করলে নিচের গ্রিডে লাইন যোগ হয়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_25_000100_create_dye_lab_dip.php`:
- **`dye_dosing_templates`:**
  - `code`: TPL-0001, সেভ করলে বসে, কোম্পানিতে ইউনিক।
  - `name`: কোম্পানিতে ইউনিক।
  - `short_name`, `dye_method_id`, `colour_group_id`।
  - `template_type`: PRE_TREATMENT / DYEING / POST_TREATMENT / FINISHING / WASH।
  - `is_finalized`, `created_by`।
- **`dye_dosing_template_agents`:**
  - `template_id` (মুছলে cascade)।
  - `step_no`: টেমপ্লেটের মধ্যে ইউনিক।
  - `dye_method_id`।
  - `phase`: PRE_TREATMENT / DYEING / AFTER_TREATMENT / FINISHING।
  - `process`: ফেজের নিজের প্রসেস, যেমন DYEING → LEVELLING, SALT_DOSING, COLOUR_DOSING, ALKALI_DOSING, FIXATION।
  - `item_id` (ডাই বা কেমিক্যাল), `is_labdip`, `is_line_break`, `dose`, `uom` (মাস্টারের কোড)।
- **`dye_lab_dip_recipes`:**
  - `recipe_no`, `option_no`: জোড়াটা কোম্পানিতে ইউনিক।
  - `buyer_id`, `style_id`, `colour_id`, `fabric_type_id`, `dye_method_id`, `fiber_composition`, `created_by`।
  - Color Type আর Color Group সেভ হয় না, শেড (colour) থেকে পড়া হয়।

## ৫. Workflow / Status ও Business Rules

- **টেমপ্লেট কোড:** সেভ করলে advisory lock-এর ভেতরে ফাইলে থাকা সবচেয়ে বড় কোডের পরেরটা বসে।
- **Template Name:** একই নামে দুটো টেমপ্লেট রাখা যায় না।
- **ফাইনালাইজ:** Is Finalize? টিক না থাকা পর্যন্ত কার্ভ "লেখা চলছে"। Brickland-এর নিয়মে এমন টেমপ্লেট কোনো ল্যাব ডিপের তালিকায় আসবে না। এই নিয়মটা প্রযোজ্য হবে ল্যাব ডিপ প্রোগ্রামিংয়ে (Batch Sample, 17/04)। Records ট্যাবের Status-এ দেখায় Finalised বা "Being written"।
- **এজেন্ট:**
  - প্রতিটা লাইনে Step No, Dye Phase আর এজেন্ট লাগবে।
  - একই Step No দুবার দেওয়া যায় না, কারণ কার্ভ ধাপ নম্বর ধরে পড়া হয়।
  - Dye Process সবসময় লাইনের ফেজেরই হতে হবে। ফেজ না বেছে প্রসেস বাছলে ফেজটাও সঙ্গে বসে যায়।
  - Add করলে লাইনগুলো ধাপের ক্রমে সাজে।
  - সেভ করলে হেডার আর সব লাইন একসাথে লেখা হয়: যে লাইনগুলো পাঠানো হয়েছে, সেগুলোই থাকে।
- **রেসিপি নম্বর আর অপশন:**
  - একটা সাবমিশনে একটা Recipe #।
  - একই শেডের পরের চেষ্টা সেই নম্বরের পরের Option No। একই (Recipe #, Option No) দুবার রাখা যায় না।
  - Recipe # সেভের সময় ট্রিম হয়।
- **স্টাইল আর বায়ার:** Brickland-এর মতো Style # তালিকায় সব বায়ারের স্টাইল থাকে ("SS27-TEE01 · LPP")। অন্য বায়ারের স্টাইল বাছলে সেভে Brickland-এর বার্তা আসে: "Style X belongs to a different buyer. Pick a style of the buyer named above."
- **Color Type / Color Group:** শেডের নিজের মান থেকে দেখায়, হাতে লেখা যায় না।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.labdip.view`: FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER পায়।
  - `dyehouse.labdip.manage`: FACTORY_ADMIN আর FACTORY_MANAGER পায়।
- **Lookup:** Lab Dip Recipe Lookup-এ শুধু view লাগে।
- **API:**
  - `GET /dyehouse/lab-dip/options`: মেথড, কালার গ্রুপ, কেমিক্যাল, বায়ার, স্টাইল, কালার, ফেব্রিক টাইপ, টেমপ্লেটের ধরন আর ফেজ-প্রসেসের তালিকা।
  - `GET|POST /dyehouse/dosing-templates`।
  - `GET|PUT|DELETE /dyehouse/dosing-templates/{code}`: কোড দিয়ে, ULID দিয়ে নয়।
  - `/erp/dye-lab-dip-recipes`: screen-kit resource।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Dye House → LAB-DIP → Dosing Template Master / Lab Dip Recipe Entry / Lab Dip Recipe Lookup।
- **Dosing Template Master:**
  - Records ট্যাব: Template Code, Name, Short Name, Dyeing Method, Color Group, Template Type, Agents, Status।
  - Detail Template ট্যাব:
    - বাঁয়ে Template List, সার্চসহ, পাতায় ১৫টা করে।
    - ডানে টেমপ্লেটের ফর্ম, পাশে Editing/New ব্যাজ।
    - Agents কার্ড: এন্ট্রি সারি, Add আর Cancel, প্রতিটা লাইন এডিট করা যায় এমন গ্রিড, নিচে Steps-এর সংখ্যা।
    - নিচে Delete (নিশ্চিত করতে হয়), Clear আর Save changes।
  - তালিকা থেকে টেমপ্লেট খুললে পেজ reload হয় না।
- **Lab Dip Recipe Entry:**
  - Records ট্যাব: Buyer A/C, Style # আর Recipe #-এর ফিল্টার, সেভ সাথে সাথে।
  - গ্রিডের কলাম: Buyer Name, Style No, Color Type, Color, Fabric Type, Color Group, Dyeing Method, Fiber Comp, Recipe No, Option No, Created By। প্রতিটা সারিতে Edit আর Remove।
  - Add New ট্যাব: New recipe ফর্ম, Reset আর Add New।
  - Edit চাপলে গ্রিডের জায়গায় ফর্ম আসে (Update recipe / Cancel)।
- **Lab Dip Recipe Lookup:** শুধু পড়ার তালিকা, একই ফিল্টার, Clear আর Export।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_25_000100_create_dye_lab_dip.php`
- `app/Modules/DyeHouse/Models/DyeDosingTemplate.php`
- `app/Modules/DyeHouse/Models/DyeDosingTemplateAgent.php`
- `app/Modules/DyeHouse/Models/DyeLabDipRecipe.php`
- `app/Modules/DyeHouse/Resources/DyeLabDipRecipeResource.php`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeLabDipApiController.php`
- `app/Modules/DyeHouse/Routes/api.php` (বদলানো)
- `app/Modules/DyeHouse/Database/Seeders/DyeHouseMasterSeed.php` (বদলানো: Brickland-এর ১৫টা টেমপ্লেট, conventional reactive কার্ভ)
- `app/Modules/Erp/Support/ResourceRegistry.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো: `DYE_HOUSE.LAB_DIP` ফোল্ডার আর ৩টা লিফ)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `labdip` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/page.js`, `screens.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/dosing-template-editor.jsx`

**Test**
- `tests/Feature/DyeHouse/DyeHouseLabDipTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseLabDipTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ৯৩টা assertion।
- `test_dosing_template_curve`:
  - কেমিক্যালের তালিকায় শুধু Dyes Chemical Store-এর আইটেম আসে।
  - "Dyeing Method is required." বার্তা আসে।
  - একই ধাপ নম্বর আবার দিলে, ফেজের বাইরের প্রসেস দিলে, অজানা ইউনিট দিলে বা এজেন্ট ছাড়া লাইন দিলে সেভ আটকায়।
  - সেভে কোড TPL-0001 বসে, লাইন ধাপের ক্রমে সাজে, ইউনিট মাস্টারের কোডে যায়।
  - একই নামে দ্বিতীয় টেমপ্লেট আটকায়।
  - PUT করলে লাইনগুলো পুরোপুরি বদলে যায়।
  - তালিকায় agent-এর সংখ্যা আর Status ঠিক দেখায়; মোছা যায়।
- `test_lab_dip_recipes`:
  - Required ঘরের বার্তা, অন্য বায়ারের স্টাইলে Brickland-এর বার্তা।
  - Color Type আর Color Group শেড থেকে আসে, Recipe # ট্রিম হয়, Created By বসে।
  - একই অপশন আবার দিলে আটকায়, Option 2 সেভ হয়।
  - এডিটে বায়ার বদলালে স্টাইলের নিয়ম আবার যাচাই হয়; মোছা যায়।
- `test_who_may_write_templates_and_recipes`:
  - STORE_MANAGER শুধু পড়তে পারে।
  - FACTORY_MANAGER লিখতে পারে।
  - TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseMasterTest` (৬টা টেস্ট, ৯৪টা assertion) আর `MenuAccessTest` (৬টা টেস্ট, ৩৪টা assertion) আবার পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/ld01`–`ld06`), dev অ্যাপে:**
- **Dosing Template Master:**
  - Records-এ ১৬টা টেমপ্লেট দেখায়।
  - CONVENTIONAL FOR REACTIVE খুলে ধাপ ১ যোগ করে সেভ করা হয়েছে (মোট ৪টা agent)।
  - নতুন LAB-DIP REACTIVE MEDIUM (TPL-0016) চার ধাপসহ ফাইনালাইজ হয়ে সেভ হয়েছে।
  - গ্রিডের লাইনে প্রসেস আর মেথড এডিট করা যায়।
- **UI চেকে ধরা পড়া বাগ, ঠিক করা হয়েছে:** ফেজ না বেছে প্রসেস বাছলে প্রসেসটা মুছে যেত, কারণ ফেজের select আর প্রসেসের ছোট হয়ে আসা তালিকা খালি মান ফেরত পাঠাত। এখন শুধু সত্যিকারের বাছাই আর ফেজ বদলানোই প্রসেসে হাত দেয়।
- **Lab Dip Recipe Entry:**
  - অন্য বায়ারের স্টাইলে ঘরের নিচে বার্তা আসে।
  - LD-2026-0001 অপশন ১ আর ২ সেভ হয়েছে।
  - একই অপশন আবার দিলে বার্তা আসে।
  - Edit চাপলে "Edit recipe" ট্যাব খোলে (`?edit=LD-2026-0001-2`)। Update recipe-এ Fiber Comp বদলেছে।
- **Lab Dip Recipe Lookup:** দুটো রেসিপি দেখায়, Recipe # ফিল্টার কাজ করে।
- **error:** ০। তৈরি করা সব রেকর্ড dev DB-তে রেখে দেওয়া হয়েছে।

**প্রিন্ট:** Brickland-এর তিনটা স্ক্রিনের কোনোটাতেই Print নেই।

## ১০. খোলা প্রশ্ন

১. **Brickland-এর এজেন্ট লেবেল:** Dyes/Chemical Agent-এর ঘরে Brickland "-- Select Category --" লেখে, যদিও তালিকায় থাকে কেমিক্যাল। আমরা হুবহু সেটাই রেখেছি। "-- Select Agent --" চাইলে জানাবেন।

২. **টেমপ্লেট মোছা:** এখন যেকোনো টেমপ্লেট মোছা যায়। ল্যাব ডিপ বা বাল্ক ব্যাচ (17/03–17/04) টেমপ্লেট ব্যবহার শুরু করলে ব্যবহৃত টেমপ্লেট মোছা আটকানো হবে।

৩. **Lookup-এ "approved":** Brickland-এর বর্ণনা বলে "approved lab dip recipes", কিন্তু রেসিপিতে কোনো অনুমোদনের ঘর নেই, আর Brickland-এর Lookup-ও সব রেসিপি দেখায়। আমরাও সব দেখাই।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/dh01`–`dh05`):**
  - টেমপ্লেটের Records আর Detail ট্যাব, Agents গ্রিড, ফেজ ধরে প্রসেসের তালিকা, রেসিপির New/Edit/Records আর Lookup দেখা হয়েছে।
  - Brickland demo-তে একটা রেসিপি তৈরি হয়েছে (LD-2026-0901)।
- **Brickland-এর বার্তা হুবহু:** "Dyeing Method is required.", "Recipe # is required.", "Buyer A/C is required.", "Style X belongs to a different buyer…"।
- **রেসিপিতে লাইন নেই:** Brickland-এর রেসিপিতে ডাই বা কেমিক্যালের কোনো লাইন নেই, শুধু শেডের হেডার। ডোজ থাকে টেমপ্লেটের এজেন্টে।
- **Print:** তিনটা স্ক্রিনের কোনোটাতেই Brickland-এর Print বাটন নেই।
