# Mappings

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Access Control](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `02/02`

**⬅ ফিরে যান:** [Access Control](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Buyer-Wise Fabric Transaction Rights | `/m/security/buyer-wise-fabric-trans-permission` | 3 | 8 | 1 |
| 2 | User Rule Mapping | `/m/security/user-and-rules-mapping` | 3 | 2 | 1 |
| 3 | User Requisition Mapping | `/m/security/user-and-requisition-mapping` | 3 | 3 | 1 |
| 4 | User Store Mapping | `/m/security/user-and-store-mapping` | 3 | 3 | 1 |
| 5 | User Company Mapping | `/m/security/user-wise-company-mapping` | 3 | 4 | 1 |
| 6 | User Production Category Mapping | `/m/security/user-and-production-category-mapping` | 3 | 2 | 1 |
| 7 | Fabric Defect Mapping | `/m/security/fabric-defect-type-mapping` | 3 | 6 | 1 |
| 8 | Buyer Store Assignment | `/m/security/buyer-store-mapping` | 3 | 2 | 1 |
| 9 | Menu Report Mapping | `/m/security/menu-wise-report-mapping` | 3 | 2 | 1 |
| 10 | Buyer Item Category Mapping | `/m/security/buyer-wise-item-category-mapping` | 3 | 5 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Buyer-Wise Fabric Transaction Rights

`/m/security/buyer-wise-fabric-trans-permission`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Permission for Grey Fabric Transfer, New fabric operation permission

**ট্যাব: Permission for Grey Fabric Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | search |  | Search… |
- সেকশন: Buyer A/C Group, Transfer Rules
- বাটন/অ্যাকশন: Permission for Grey Fabric Transfer, New fabric operation permission, LPP LPP 0

**ট্যাব: New fabric operation permission**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Issue | checkbox |  |  |
| Receive | checkbox |  |  |
| Transfer | checkbox |  |  |
| Return | checkbox |  |  |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
- সেকশন: New fabric operation permission
- বাটন/অ্যাকশন: Permission for Grey Fabric Transfer, New fabric operation permission, Select…, Reset, Save fabric operation permission

**ডায়ালগ: New fabric operation permission**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Issue | checkbox |  |  |
| Receive | checkbox |  |  |
| Transfer | checkbox |  |  |
| Return | checkbox |  |  |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
- খালি Save-এ মেসেজ: User is required.

#### User Rule Mapping

`/m/security/user-and-rules-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: User & Rules Mapping, New rule assignment

**ট্যাব: User & Rules Mapping**
- সেকশন: User & Rules Mapping
- বাটন/অ্যাকশন: User & Rules Mapping, New rule assignment, -- Select user --

**ট্যাব: New rule assignment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Business Rule* | select | ✔ | Select…, BR-001 — Purchase order above requisition value, BR-002 — Issue beyond available stock, BR-003 — Order confirme |
| May override a violation Overrides are always logged, whoever holds them. | checkbox |  |  |
- সেকশন: New rule assignment
- বাটন/অ্যাকশন: User & Rules Mapping, New rule assignment, Select…, Reset, Save rule assignment

**ডায়ালগ: New rule assignment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Business Rule* | select | ✔ | Select…, BR-001 — Purchase order above requisition value, BR-002 — Issue beyond available stock, BR-003 — Order confirme |
| May override a violation Overrides are always logged, whoever holds them. | checkbox |  |  |
- খালি Save-এ মেসেজ: User is required.

#### User Requisition Mapping

`/m/security/user-and-requisition-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: User & Requisition Mapping, New requisition mapping

**ট্যাব: User & Requisition Mapping**
- সেকশন: User & Requisition Mapping
- বাটন/অ্যাকশন: User & Requisition Mapping, New requisition mapping, -- Select user --

**ট্যাব: New requisition mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| May raise | checkbox |  |  |
| May approve | checkbox |  |  |
| Approval Limit | number |  |  |
- সেকশন: New requisition mapping
- বাটন/অ্যাকশন: User & Requisition Mapping, New requisition mapping, Select…, Reset, Save requisition mapping

**ডায়ালগ: New requisition mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| May raise | checkbox |  |  |
| May approve | checkbox |  |  |
| Approval Limit | number |  |  |
- খালি Save-এ মেসেজ: User is required.

#### User Store Mapping

`/m/security/user-and-store-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: User & Store Mapping, New store mapping

**ট্যাব: User & Store Mapping**
- সেকশন: User & Store Mapping
- বাটন/অ্যাকশন: User & Store Mapping, New store mapping, -- Select user --

**ট্যাব: New store mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Issue | checkbox |  |  |
| Receive | checkbox |  |  |
| Adjust Stock adjustment rewrites the book quantity — grant it sparingly. | checkbox |  |  |
- সেকশন: New store mapping
- বাটন/অ্যাকশন: User & Store Mapping, New store mapping, Select…, Reset, Save store mapping

**ডায়ালগ: New store mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Issue | checkbox |  |  |
| Receive | checkbox |  |  |
| Adjust Stock adjustment rewrites the book quantity — grant it sparingly. | checkbox |  |  |
- খালি Save-এ মেসেজ: User is required.

#### User Company Mapping

`/m/security/user-wise-company-mapping`

**ট্যাব: Records 12**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Group companies each user may work in, and which one they land in at sign | search |  | Search 12 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: User, Company, Default, ACTION
- বাটন/অ্যাকশন: User wise Company Mapping, New company mapping, Export, User, Company, Default, Edit

**ট্যাব: User wise Company Mapping**
- সেকশন: User wise Company Mapping
- বাটন/অ্যাকশন: User wise Company Mapping, New company mapping, -- Select user --

**ট্যাব: New company mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Sign in to this company Setting this clears the flag on the user's other compani | checkbox |  |  |
- সেকশন: New company mapping
- বাটন/অ্যাকশন: User wise Company Mapping, New company mapping, Select…, Reset, Save company mapping

**ডায়ালগ: New company mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Sign in to this company Setting this clears the flag on the user's other compani | checkbox |  |  |
- খালি Save-এ মেসেজ: User is required.

#### User Production Category Mapping

`/m/security/user-and-production-category-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: User & Production Category Mapping, New production category mapping

**ট্যাব: User & Production Category Mapping**
- সেকশন: User & Production Category Mapping
- বাটন/অ্যাকশন: User & Production Category Mapping, New production category mapping, -- Select user --

**ট্যাব: New production category mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| May plan | checkbox |  |  |
| May report production | checkbox |  |  |
- সেকশন: New production category mapping
- বাটন/অ্যাকশন: User & Production Category Mapping, New production category mapping, Select…, Reset, Save production category mapping

**ডায়ালগ: New production category mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| May plan | checkbox |  |  |
| May report production | checkbox |  |  |
- খালি Save-এ মেসেজ: User is required.

#### Fabric Defect Mapping

`/m/security/fabric-defect-type-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Fabric Defect Type Mapping, New defect mapping

**ট্যাব: Fabric Defect Type Mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select buyer* | select | ✔ | LPP (LPP) |
- সেকশন: Fabric Defect Type Mapping
- বাটন/অ্যাকশন: Fabric Defect Type Mapping, New defect mapping

**ট্যাব: New defect mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Penalty Points* | number | ✔ |  |
| Critical defect A critical defect rejects the roll regardless of the point total | checkbox |  |  |
| Display Order | number |  |  |
| Active | checkbox |  |  |
- সেকশন: New defect mapping
- বাটন/অ্যাকশন: Fabric Defect Type Mapping, New defect mapping, Select…, Reset, Save defect mapping

**ডায়ালগ: New defect mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Penalty Points* | number | ✔ |  |
| Critical defect A critical defect rejects the roll regardless of the point total | checkbox |  |  |
| Display Order | number |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Buyer is required.

#### Buyer Store Assignment

`/m/security/buyer-store-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Buyer & Store Mapping, New buyer store

**ট্যাব: Buyer & Store Mapping**
- সেকশন: Buyer & Store Mapping
- বাটন/অ্যাকশন: Buyer & Store Mapping, New buyer store, -- Select store --

**ট্যাব: New buyer store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Default store for this buyer Setting this clears the flag on the buyer's other s | checkbox |  |  |
- সেকশন: New buyer store
- বাটন/অ্যাকশন: Buyer & Store Mapping, New buyer store, Select…, Reset, Save buyer store

**ডায়ালগ: New buyer store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Default store for this buyer Setting this clears the flag on the buyer's other s | checkbox |  |  |
- খালি Save-এ মেসেজ: Buyer is required.

#### Menu Report Mapping

`/m/security/menu-wise-report-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Menu Wise Report Mapping, New report mapping

**ট্যাব: Menu Wise Report Mapping**
- সেকশন: Menu Wise Report Mapping
- বাটন/অ্যাকশন: Menu Wise Report Mapping, New report mapping, -- Select menu --

**ট্যাব: New report mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Display Order | number |  | 10 |
| Default report for this menu Setting this clears the flag on the menu's other re | checkbox |  |  |
- সেকশন: New report mapping
- বাটন/অ্যাকশন: Menu Wise Report Mapping, New report mapping, Select…, Reset, Save report mapping

**ডায়ালগ: New report mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Display Order | number |  |  |
| Default report for this menu Setting this clears the flag on the menu's other re | checkbox |  |  |
- খালি Save-এ মেসেজ: Menu is required.

#### Buyer Item Category Mapping

`/m/security/buyer-wise-item-category-mapping`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Buyer Wise Item Category Mapping, New buyer item category

**ট্যাব: Buyer Wise Item Category Mapping**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select buyer* | select | ✔ | LPP (LPP) |
- সেকশন: Buyer Wise Item Category Mapping
- বাটন/অ্যাকশন: Buyer Wise Item Category Mapping, New buyer item category

**ট্যাব: New buyer item category**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Nomination Code | text |  | HM-TRIM-2026 |
| Sub-style wise Accessories Booking? Labels and size-dependent trims differ betwe | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: New buyer item category
- বাটন/অ্যাকশন: Buyer Wise Item Category Mapping, New buyer item category, Select…, Reset, Save buyer item category

**ডায়ালগ: New buyer item category**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Nomination Code | text |  |  |
| Sub-style wise Accessories Booking? Labels and size-dependent trims differ betwe | checkbox |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Buyer is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `stores` (Inventory), `item_categories`, `item_departments` (Production Category-র সম্ভাব্য উৎস), `user_roles`/কোম্পানি সদস্যপদ (User Company Mapping), `ReportRegistry` (Menu Report Mapping)

## ৪. ডাটা মডেল (Entity ও Field)

- `user_store_mappings`: user_id, store_id, can_issue, can_receive, can_adjust। স্টোরের এন্ট্রি-স্ক্রিন শুধু ম্যাপ করা স্টোর দেখাবে।
- `user_requisition_mappings`: user_id, requisition_type, can_raise, can_approve, approval_limit
- `user_production_categories`: user_id, production_category, may_plan, may_report
- `user_business_rules`: user_id, business_rule_id, may_override (`business_rules` মাস্টার 02/04-এ)
- User Company Mapping = বিদ্যমান কোম্পানি-সদস্যপদ + `is_default`
- `menu_report_mappings`: menu_id, report_key, sort_order, is_default
- **Buyer-নির্ভর (পরে):** `buyer_fabric_permissions` (buyer, user, issue/receive/transfer/return, effective from/to), `buyer_store_mappings`, `buyer_item_category_mappings` (nomination code, sub-style booking), `buyer_defect_mappings` (buyer × fabric defect, penalty points, critical, order)

## ৫. Workflow / Status ও Business Rules

- প্রতিটা ম্যাপিংয়ে User/Role/Menu/Buyer বাধ্যতামূলক (Brickland-এর "User is required." ইত্যাদি)। "Default" ফ্ল্যাগ একটায় বসালে সেই User/Menu-র অন্যগুলো থেকে স্বয়ংক্রিয়ভাবে সরে যায়।

## ৬. API ও পারমিশন

- `GET /access/mappings/{kind}`: Records (subject আর target নামসহ, `flag_<name>`, amount, order, default)।
- `GET /access/mappings/{kind}/options`: ফর্মের subject আর target তালিকা, ফ্ল্যাগ আর তাদের ডিফল্ট।
- `GET|PUT /access/mappings/{kind}/subjects/{subject}`: "User & X Mapping" ম্যাট্রিক্স; PUT পুরো তালিকা একবারে সেভ করে।
- `POST /access/mappings/{kind}` (একটা সারি; আগে থাকলে হালনাগাদ), `PATCH|DELETE /access/mappings/{kind}/{mapping}`।
- User Company Mapping: `GET /access/user-companies`, `GET …/options`, `POST`, `PATCH|DELETE /access/user-companies/{membership}`।
- পারমিশন: ইউজার-ম্যাপিং `admin.user.manage`, Menu Report Mapping `admin.role.manage`।

## ৭. Frontend পেজ (লাইট থিম)

Brickland-এর মতো প্রতিটা স্ক্রিন আলাদা রুট `/access-control/<screen>`, তিনটা ট্যাবে:
- **Records:** সার্চ, Export, Edit-ডায়ালগ, মোছা।
- **"User & X Mapping":** User বা Menu বাছাই করলে সব target আসে, আজ যা দেওয়া আছে তাতে টিক। অধিকার, সীমা, ক্রম, ডিফল্ট বদলে একবারে Save।
- **"New X mapping":** ফর্ম।

সাইডবারে Access Control-এর ভেতরে MAPPINGS ফোল্ডার।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১০টা স্ক্রিনই):

**Backend**
- `app/Modules/Identity/Database/Migrations/2026_10_22_000100_create_access_mappings_table.php`
- `app/Modules/Identity/Models/AccessMapping.php`
- `app/Modules/Identity/Support/MappingRegistry.php`
- `app/Modules/Identity/Http/Controllers/Api/AccessMappingApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/UserCompanyMappingApiController.php`
- `app/Modules/Identity/Database/Seeders/LookupSeeder.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Routes/api.php`
- `app/Modules/Billing/Services/EntitlementGuard.php`
- `app/Modules/Identity/Database/Migrations/2026_12_26_000100_extend_access_mappings_and_tna_events.php`

**Frontend**
- `frontend/src/app/(app)/access-control/[tab]/mapping-screens.js`
- `frontend/src/app/(app)/access-control/[tab]/screens.js`
- `frontend/src/app/(app)/access-control/[tab]/form-actions.js`
- `frontend/src/components/access/subject-matrix.jsx`
- `frontend/src/components/access/grey-transfer-rules.jsx`
- `lang/en/access.php`
- `lang/bn/access.php`

**Tests**
- `tests/Feature/Identity/AccessMappingTest.php`
- `tests/Feature/Identity/AccessControlConfigurationTest.php`
- `tests/Feature/Identity/AccessBuyerMappingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- `tests/Feature/Identity/AccessMappingTest.php` (৫টা টেস্ট):
  - স্টোর-ম্যাট্রিক্স সেভ; সারি না থাকলে সীমাবদ্ধ নয়, থাকলে শুধু ম্যাপ করা স্টোর।
  - নতুন সারির ভ্যালিডেশন ("User is required.", অজানা target, অন্য কোম্পানির ইউজার), এডিটে না পাঠানো ফ্ল্যাগ আগের মতো থাকে, ভুল kind-এ 404।
  - মেনুর একটাই ডিফল্ট রিপোর্ট।
  - User Company Mapping: শুধু নিজের প্রশাসিত কোম্পানি, এই কোম্পানি ছাড়া যায় না (409)।
  - গেট।
- `tests/Feature/Identity/AccessBuyerMappingTest.php` (বায়ারের ৪টা স্ক্রিন):
  - Fabric rights: "User is required." / "Effective From is required.", শেষ তারিখ শুরুর আগে নয়; শুধু কার্যকর মেয়াদের অধিকার গোনা হয় (তিন দিন পরের ROLY বাদ)।
  - Grey transfer: FP01>FP02 জোড়া, নিষ্ক্রিয় নিয়মও থাকে, একই category-র জোড়া আটকায়।
  - Defect: ৩৯টা ধরন; পয়েন্ট বাধ্যতামূলক আর ১–৪; matrix-এ টিক দেওয়া সারিতে পয়েন্ট না থাকলে সারি ধরে বার্তা।
  - Buyer store: বায়ারের ডিফল্ট স্টোর একটাই, অন্য স্টোর থেকে সরে যায়; URL key = স্টোর কোড।
  - Item category: শুধু শীর্ষ স্তর; নমিনেশন কোড trim হয়।
  - গেট (storekeeper 403)।
- **ব্রাউজার (dev):** Jashim Uddin × LPP (Issue, Receive, 2026-10-01 থেকে); H&M-এর grey নিয়ম Sample → Bulk; H&M-এর defect Needle Drop ৪, Slub ১ (পয়েন্ট ছাড়া প্রথম সেভ আটকেছে); Fabric store (DHK-FAB)-এ H&M (ডিফল্ট) আর LPP; H&M × Carton, HM-TRIM-2026, sub-style।

## ১০. খোলা প্রশ্ন

- Brickland-এ Fabric Defect আর Item Category-র matrix-এ বায়ার বাছলে পাতা ভেঙে পড়ে ("This page couldn't load")। তাই এ দুটো Buyer Store-এর matrix-এর ধাঁচে বানানো হয়েছে (টিক, সাথে প্রতি সারির পয়েন্ট / কোড / ফ্ল্যাগ)।
- Brickland-এ defect-এর "নিজের ডিফল্ট পয়েন্ট" আছে, কিন্তু ধরনের কোনো master স্ক্রিন দেখা যায়নি। এখানে ধরন system lookup `FABRIC_DEFECT`, ডিফল্ট পয়েন্ট নেই। Knit Production-এর roll inspection বানানোর সময় ঠিক হবে।

## ১১. As-built নোট

**বায়ার ছাড়া ৬টা সম্পন্ন (২০২৬-১০-১০):** User Rule Mapping, User Requisition Mapping, User Store Mapping, User Company Mapping, User Production Category Mapping, Menu Report Mapping।
- **একটা ইঞ্জিন, আলাদা টেবিল নয়:** §৪-এ পরিকল্পিত আলাদা টেবিলের বদলে একটা `access_mappings` টেবিল। কলাম: company_id, kind, subject_id, target_id, flags (JSON), amount, sort_order, is_default; ইউনিক (company, kind, subject, target)।
  - প্রতিটা kind-এর subject, target, অধিকার আর বাড়তি কলাম ঠিক করে `MappingRegistry::KINDS`।
  - কারণ: সব ম্যাপিংয়ের আকার একই, তাই এক API আর এক UI-তে সব চলে, আর বায়ার-ম্যাপিংও নতুন kind হিসেবে যোগ হবে।
- **Target-এর উৎস:**
  - স্টোর: Inventory-র `stores`।
  - রিকুইজিশনের ধরন, প্রোডাকশন ক্যাটাগরি আর ব্যবসায়িক নিয়ম: সিস্টেম লুকআপ টেবিল `REQUISITION_TYPE`, `PRODUCTION_CATEGORY`, `BUSINESS_RULE`। ব্যবসায়িক নিয়মে Brickland-এর BR-001…BR-008, বাংলা নামসহ। লুকআপ হওয়ায় কোম্পানি Lookup Table Master থেকে নিজের মান যোগ করতে পারে।
  - রিপোর্ট: `ReportRegistry`।
  - মেনু: সক্রিয় স্ক্রিন, কোম্পানির override-সহ।
- **নিয়ম:**
  - ম্যাট্রিক্সে কোনো টিক না থাকা target মানে কোনো সারি নেই। Menu Report-এর ফ্ল্যাগ নেই, সেখানে "Mapped" টিক।
  - একটা subject-এর একটাই ডিফল্ট; নতুন ডিফল্ট দিলে বাকিগুলো থেকে সরে যায়।
  - একটা ভুলের জায়গা এড়ানো হয়েছে: এডিটে যে ফ্ল্যাগ পাঠানো হয়নি সেটা আগের মতো থাকে।
- **প্রয়োগের প্রশ্ন:** `MappingRegistry::targetsFor(kind, subject, flag)` আর `allows(...)`। কোনো সারি না থাকলে null (সীমাবদ্ধ নয়); থাকলে শুধু ম্যাপ করা target।
  - **বাকি:** স্টোর, রিকুইজিশন, প্রোডাকশন আর লঙ্ঘনের স্ক্রিন বানানোর সময় এগুলো ডাকতে হবে। তার আগে ম্যাপিং শুধু সেভ হয়, কিছু আটকায় না।
- **User Company Mapping:** সদস্যপদ (`company_user`) নিয়েই চলে, আলাদা টেবিল নেই।
  - শুধু সেই কোম্পানিগুলো দেখায় যেখানে অ্যাডমিন নিজে সদস্য; যোগ বা বাদ শুধু যেখানে তাঁর `admin.user.manage` আছে (`PermissionResolver::has`)।
  - অন্য কোম্পানিতে যোগ করলে সেই কোম্পানির প্ল্যান-সীমা মানা হয় (`EntitlementGuard::assertCanAdd` এখন কোম্পানি নিতে পারে)।
  - এই কোম্পানি থেকে বাদ দেওয়া হয় User Account Registration থেকে, এখানে নয় (409)।
- **যাচাই:** `AccessMappingTest` ৫/৫। ব্রাউজারে ম্যাট্রিক্সে টিক → Save → Records → মোছা পরীক্ষা করা হয়েছে, কনসোলে কোনো error নেই।

**বায়ারের ৪টা সম্পন্ন (২০২৬-১০-২৪):** Buyer-Wise Fabric Transaction Rights, Fabric Defect Mapping, Buyer Store Assignment, Buyer Item Category Mapping — একই ইঞ্জিনে নতুন kind।
- **নতুন kind:**
  - `user-buyer-fabric`: user × buyer, Issue / Receive / Transfer / Return, কার্যকর মেয়াদ (from বাধ্যতামূলক)। `targetsFor` এখন শুধু আজ কার্যকর সারি গোনে।
  - `buyer-grey-transfer`: বায়ার × "FP01>FP02" জোড়া, Active। Brickland-এর "Permission for Grey Fabric Transfer" ট্যাব: বাঁয়ে বায়ার আর নিয়মের সংখ্যা, ডানে নিয়ম, Add rule, Revert / Save। নিয়ম না থাকা মানে অনুমতি নেই।
  - `buyer-defect`: বায়ার × ত্রুটির ধরন, পেনাল্টি পয়েন্ট (১–৪, বাধ্যতামূলক), Critical, Display Order, Active।
  - `store-buyer`: স্টোর × বায়ার, ডিফল্ট — একটা বায়ারের একটাই ডিফল্ট স্টোর (`default_scope: target`)।
  - `buyer-item-category`: বায়ার × শীর্ষ স্তরের item category, নমিনেশন কোড, Sub-style booking, Active।
- **ইঞ্জিনে যোগ:** `access_mappings`-এ `text`, `date_from`, `date_to`; kind-এর `select` (টিক দিলেই সারি, ফ্ল্যাগ সারির গুণ), `required`, `amount_range`, `default_scope`।
- **নতুন lookup:** `FABRIC_DEFECT` (Brickland-এর ৩৯টা নাম, FD-01…FD-39) আর `FABRIC_BOOKING_CATEGORY` (FP01…FP12), বাংলা নামসহ।
- **উৎস:** বায়ার = Merchandise-এর `buyers`; স্টোর = `stores` (User Store Mapping-এর মতো); item category = `item_categories`-এর শীর্ষ স্তর (Merchandise এগুলোই ব্যবহার করে)।
- **পাঠযোগ্য URL:** বায়ার বা স্টোর-subject-এর matrix URL-এ কোড থাকে (`?tab=matrix&subject=HM`), ULID নয়। ইউজার আর মেনুর ক্ষেত্রে আগের মতোই।
- **Matrix-এর রূপ:** Mapped কলাম এখন প্রথমে (Brickland-এর মতো); টিক না দেওয়া সারির ঘর বন্ধ থাকে; নিচে "n / মোট"।
- **পারমিশন:** Fabric rights `admin.user.manage`; Defect, Buyer Store, Item Category `admin.role.manage`।
- **প্রয়োগ বাকি:** ফ্যাব্রিক ইস্যু / ট্রান্সফার, GRN-এর ডিফল্ট স্টোর, PO-র category সীমা আর ইন্সপেকশনের পয়েন্ট — সংশ্লিষ্ট স্ক্রিন বানানোর সময় `targetsFor` / `allows` ডাকতে হবে।
