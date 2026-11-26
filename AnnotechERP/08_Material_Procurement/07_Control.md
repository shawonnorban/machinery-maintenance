# Control

> **স্ট্যাটাস:** ✅ সম্পন্ন (Notifications, Audit Trail, Supplier Portal Access চালু)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/07`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Notifications | `/m/material-procurement/procurement-notifications` | 1 | 0 | 0 |
| 2 | Audit Trail | `/m/material-procurement/procurement-audit-trail` | 1 | 1 | 0 |
| 3 | Supplier Portal Access | `/m/material-procurement/supplier-portal-access` | 1 | 0 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Notifications

`/m/material-procurement/procurement-notifications`

**ভিউ**
> 13 of 15
- সেকশন: Notifications
- বাটন/অ্যাকশন: Mark all read

#### Audit Trail

`/m/material-procurement/procurement-audit-trail`

**ভিউ**
> 14 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search audit trail | text |  | Search user, document, summary… |
- গ্রিড কলাম: WHEN, USER, ACTION, SCREEN, REFERENCE, SUMMARY
- সেকশন: Audit trail

#### Supplier Portal Access

`/m/material-procurement/supplier-portal-access`

**ভিউ**
> 15 of 15
- গ্রিড কলাম: SUPPLIER, CONTACT, SIGN-IN EMAIL, CREATED, LAST SIGN-IN, ACTIVE
- সেকশন: Supplier portal logins
- বাটন/অ্যাকশন: New supplier login

**ডায়ালগ: New supplier login** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Contact name* | text | ✔ |  |
| Sign-in email* | text | ✔ |  |
| Initial password* | password | ✔ |  |
- খালি Save-এ মেসেজ: Select the supplier this login quotes for.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Access Control:**
  - `ManageCompanyUser::invite()`: অ্যাকাউন্ট তৈরি, কোম্পানির membership আর role একসাথে হয়; ACTIVE_USERS-এর সীমাও যাচাই হয়।
  - `setMembershipStatus()`: ACTIVE বা SUSPENDED।
  - `users.user_type`-এ EXTERNAL মান আগে থেকেই আছে।
- **08/01 Supplier Master (`mp_suppliers`):** লগইন যে সরবরাহকারীর হয়ে কোট দেবে। Supplier Master-এর portal_logins কলাম এখন সক্রিয় লগইন গোনে।
- **Notification:** `NotificationDispatcher::sendToMany()`; `notifications` টেবিল (read_at)। Notifications স্ক্রিন এখান থেকেই শুধু `MP_` ধরনের notification পড়ে।
- **ERP master-এর `afterSave` hook** (`ResourceDefinition`): Supplier, Fabric আর Accessories Master-এর Added / Updated লেখা এখান থেকে audit-এ যায়।

## ৪. ডাটা মডেল (Entity ও Field)

- **`mp_supplier_logins`:** supplier_id, user_id (কোম্পানিতে unique), contact_name, active, created_by। মোছা হয় না, বন্ধ করা হয়; তাতে কোটেশনের লেখক থেকে যায়।
- **লগইন যা তৈরি করে:**
  - অ্যাকাউন্ট (`users`): নাম = contact name, user_type EXTERNAL, locale en।
  - কোম্পানির membership: ACTIVE।
  - একটাই role: **SUPPLIER** (নতুন system role, COMPANY scope), অনুমতি শুধু `material.portal.view`।
  - প্রথম লগইনে পাসওয়ার্ড বদলাতে হয় (`must_change_password`): ঢুকলেই `/account`-এ নিয়ে যায়, বার্তা "Your password has to be changed before you can use anything else."।
  - **সাবস্ক্রিপশনের seat নেয় না:** যে সদস্যের সব role শুধু portal-এর (`Role::PORTAL_ONLY` = SUPPLIER), তিনি ACTIVE_USERS-এ গোনা হন না (`CompanyUser::seatCount`, EntitlementGuard আর UsageMeter দুই জায়গাতেই)। ব্যবহারকারীর সীমা পূর্ণ থাকলেও সরবরাহকারীর লগইন তৈরি হয়। কোনো কর্মীকে SUPPLIER-এর সাথে নিজের role-ও দিলে তিনি seat গোনেন।

- **`mp_audit_trail` (মাইগ্রেশন `2026_12_19_000100_create_mp_audit_trail`):**
  - **ঘর:** company_id, user_id, action (CREATE/UPDATE/DELETE), screen, reference (PR/QT/MRR নং বা সরবরাহকারীর কোড), summary, created_at।
  - created_at microsecond নির্ভুলতায় রাখা হয়, যাতে একই request-এ লেখা entry-গুলোর ক্রম ঠিক থাকে।
  - **Model `AuditEntry`:** entry শুধু যোগ করা যায়। বদলাতে বা মুছতে গেলে exception হয়।

## ৫. Workflow / Status ও Business Rules

### Notifications

- **কী দেখায়:** এই মডিউলের যে notification-গুলো কলারের কাছে এসেছে (`MP_…`), নতুনটা আগে। অন্য মডিউলের notification এখানে আসে না, আর Mark all read সেগুলো ছোঁয় না।
- **না-পড়া:** বিন্দু দিয়ে চিহ্নিত থাকে; উপরে লেখা থাকে "n unread of m"।
- **"Open":** লিংকের স্ক্রিনে নিয়ে যায়, আর notification-টা পড়া হয়ে যায়।
- **Mark all read:** মডিউলের সব notification একসাথে পড়া হয়।
- **কারা দেখেন:** মডিউলের কর্মীরা, অর্থাৎ যাঁদের requisition.view, price.approve, followup.view বা portal_access.manage অনুমতি আছে। সরবরাহকারীর লগইন এই স্ক্রিন পায় না।
- **নতুন notification** (Brickland-এর মতো; পান যাঁদের `material.price.approve` আছে):
  - **MP_QUOTATION_RECEIVED:** "New Supplier Quotation Received — POLY PLAN LTD quoted Denim on PR-… (QT-…)."
  - **MP_QUOTATION_REVISED:** "Supplier Submitted a Revised Quotation — … sent revision 3 for …"
  - দুটোরই লিংক Price Review-এ।

### Audit Trail

- **ধরন:** স্থায়ী আর শুধু দেখার; entry বদলানো বা মোছা যায় না।
- **ভাষা:** সারাংশ সবসময় ইংরেজিতে জমা থাকে (`Procurement::en`), ব্যবহারকারী বাংলায় কাজ করলেও। Brickland-ও এভাবেই রাখে।
- **কোথা থেকে entry আসে:**

| স্ক্রিন | কাজ | সারাংশ (উদাহরণ) |
|---|---|---|
| supplier / fabric / accessories master | CREATE / UPDATE | Added SUP-14 MM Accessories · Updated … |
| supplier portal access | CREATE / UPDATE | Created supplier portal login … for … · Enabled / Disabled … |
| create requisition | CREATE / UPDATE | Saved draft PR-… (Direct) — 1 line(s): Denim 300 Kg · সরাসরি submit হলে Created … · Updated draft … |
| my requisitions | UPDATE / DELETE | Submitted PR-… to all suppliers · Deleted draft PR-… |
| available requirements | CREATE | POLY PLAN LTD submitted QT-… revision 1 for Denim on PR-…: USD 6.40 × 20, 7 days |
| price review | UPDATE | Requested negotiation … · Rejected … · Approved QT-… (…) at USD 6.4 (revision 1); handed over to … · Rejected line … |
| assigned materials | CREATE | Follow-up on Denim (PR-…): PARTIALLY_RECEIVED, received 500 — total 500/2324 roll · Raised draft GRN MRR-IMF-… |

- **কখন entry লেখা হয় না:** console বা seeder থেকে, যেখানে কোনো ব্যবহারকারী নেই (যেমন erp:seed-masters)।
- **খোঁজা:** user, document আর summary দিয়ে; পাতা রিলোড হয় না।

### Supplier Portal Access

- **New supplier login:**
  - চারটা ঘর বাধ্যতামূলক: Supplier (শুধু সক্রিয় সরবরাহকারী), Contact name, Sign-in email, Initial password।
  - ইমেইল অন্য কোনো অ্যাকাউন্টে থাকলে: "This email already has an account."
  - পাসওয়ার্ড: অন্তত ১২ অক্ষর, বড় আর ছোট হাতের অক্ষর, সংখ্যা আর চিহ্ন (Brickland-এর নিয়ম)।
- **Active switch:**
  - চাপলেই সেভ হয়।
  - বন্ধ করলে membership SUSPENDED হয়, তাই সরবরাহকারী আর ঢুকতে পারেন না; পরের requisition-এর notification-ও পান না।
  - চালু করলে আবার ACTIVE।
- **সরবরাহকারী কী পান:**
  - সাধারণ লগইন পাতা দিয়েই ঢোকেন। কোম্পানির নিজস্ব কোনো স্ক্রিন বা কাজ পান না: requisition দেখা বা তোলা, অনুমোদনের অনুরোধ, কিছুই না।
  - প্রত্যেক সক্রিয় লগইন requisition submit হলে notification পায় **MP_REQUIREMENT_AVAILABLE**: "New Material Requirement Available — PR-…: n material line(s) are open for quotation."। এটা 08/03-এর Submit থেকে পাঠানো হয়।
- **এখনো নেই:** Brickland-এর সারিতে Edit বা পাসওয়ার্ড রিসেটের বাটন নেই, শুধু Active switch; এখানেও তাই।

## ৬. API ও পারমিশন

পারমিশন:
- `material.audit.view` (নতুন): FACTORY_ADMIN, FACTORY_MANAGER, COMPANY_OWNER (সাথে VIEWER আর AUDITOR, যাঁরা সব view অনুমতি পান)।
- Notifications: মডিউলের যেকোনো কর্মীর অনুমতি থাকলেই (`ProcurementControlApiController::STAFF`)।
- `material.portal_access.manage`: FACTORY_ADMIN (আর COMPANY_OWNER, যিনি সব পান)।
- `material.portal.view`: শুধু SUPPLIER role।

| Method | Path |
|---|---|
| GET | `/material-procurement/supplier-logins` (logins, সক্রিয় সংখ্যা, সক্রিয় সরবরাহকারী) |
| POST | `/material-procurement/supplier-logins` (supplier_id, contact_name, email, password) |
| PUT | `/material-procurement/supplier-logins/{id}/active` (active) |
| GET | `/material-procurement/procurement-notifications` |
| POST | `/material-procurement/procurement-notifications/{id}/read` |
| POST | `/material-procurement/procurement-notifications/read-all` |
| GET | `/material-procurement/procurement-audit-trail` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → **CONTROL** → Notifications, Audit Trail, Supplier Portal Access (Brickland-এর ক্রমে)।

- **Notifications:** কার্ড "Notifications", নিচে "n unread of m"; ডানে Mark all read। তালিকায় শিরোনাম (মোটা), লেখা, আর "সময় · Open"।
- **Audit Trail:** কার্ড "Audit trail" আর স্থায়িত্বের লাইন; খোঁজার ঘর "Search user, document, summary…"। টেবিলের কলাম WHEN, USER, ACTION (সবুজ CREATE, নীল UPDATE, লাল DELETE), SCREEN, REFERENCE, SUMMARY।

**Supplier Portal Access:**

- **কার্ড:**
  - **উপরে:** শিরোনাম "Supplier portal logins" আর লাইন "n active logins. Every supplier with an active login sees each submitted requirement and can quote on it."। ডানে New supplier login বোতাম।
  - **টেবিল:** Supplier (নাম আর কোড), Contact, Sign-in email, Created, Last sign-in, Active (switch আর Active/Inactive ব্যাজ)। switch চাপলে পাতা রিলোড হয় না।
- **Dialog:** শিরোনাম "New supplier login", সাথে লাইন "The supplier signs in on the normal ERP login page and sees only the Supplier Portal."। ঘর Supplier*, Contact name*, Sign-in email*, Initial password* (নিচে পাসওয়ার্ডের নিয়ম)। বাটন Cancel আর Create login।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_16_000200_create_mp_supplier_logins.php`, `Models/SupplierLogin.php`, `Http/Controllers/Api/SupplierLoginApiController.php`, `Routes/api.php`, `Resources/MaterialSupplierResource.php` (portal_logins), `Http/Controllers/Api/PurchaseRequisitionApiController.php` (Submit-এ notification) |
| পারমিশন / role / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`material.audit.view`), `RoleSeeder.php` (SUPPLIER), `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.CONTROL`, `MATERIAL.PROCUREMENT_NOTIFICATIONS`, `MATERIAL.PROCUREMENT_AUDIT_TRAIL`, Supplier Portal Access) |
| Seat-এর হিসাব | `app/Modules/Identity/Models/Role.php` (`PORTAL_ONLY`, `onlyPortal()`), `Models/CompanyUser.php` (`seatCount()`), `Actions/ManageCompanyUser.php` (portal-only invite-এ seat যাচাই নেই), `app/Modules/Billing/Services/EntitlementGuard.php`, `UsageMeter.php` |
| Notifications / Audit Trail | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_19_000100_create_mp_audit_trail.php`, `Models/AuditEntry.php`, `Http/Controllers/Api/ProcurementControlApiController.php`, `Support/Procurement.php` (`audit`, `en`, `qty`, `usersWith`), audit-এর লেখা: `PurchaseRequisitionApiController`, `SupplierLoginApiController`, `SupplierPortalApiController`, `PriceReviewApiController`, `AssignedMaterialApiController`, `Resources/MaterialSupplierResource.php`, `Resources/MaterialItemResource.php` |
| Notification | `app/Modules/Notification/Models/Notification.php` (`MP_REQUIREMENT_AVAILABLE`, `MP_QUOTATION_RECEIVED`, `MP_QUOTATION_REVISED`), `lang/{en,bn}/notification.php` |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/requisition.js`, `control.js`, `page.js`, `actions.js` (`supplierLoginAction`, `controlAction`), `frontend/src/components/materialprocurement/supplier-logins.jsx`, `control.jsx` |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`portal`, `ctl`, `audit`) |
| টেস্ট | `tests/Feature/MaterialProcurement/SupplierPortalAccessTest.php`, `ProcurementControlTest.php`, `tests/Feature/Identity/RoleMatrixTest.php` (১৪টা role) |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **হয়ে গেছে [Supplier Portal](04_Supplier_Portal.md)-এর সাথে:** সরবরাহকারী লগইন করলে সরাসরি Supplier Dashboard-এ যান। অন্য স্ক্রিন খুললে Brickland-এর বার্তা দেখায়: "This screen is not available — Supplier accounts can only use the Supplier Portal."।
- **Control-এর তিনটা স্ক্রিনই হয়ে গেছে।** পরের ধাপ: 08/02 Procurement Dashboard।

## ৯. টেস্ট ও যাচাই

- **`ProcurementControlTest`:** ২টা টেস্ট, ৪৬টা assertion।
  - **পুরো প্রবাহের ১৮টা entry, নতুনটা আগে:**
    - supplier master Added আর Updated।
    - লগইন Created, Disabled আর Enabled।
    - fabric master Added।
    - requisition Saved draft, Updated draft আর Submitted।
    - আরেকটা খসড়া Saved, তারপর Deleted।
    - quote revision ১, negotiation, revision ২, Approved & handed over।
    - Follow-up আর Raised draft GRN।
    - শেষে bn locale-এর ব্যবহারকারীর Updated, সেটাও ইংরেজিতে।
  - **ব্যবহারকারীর নাম:** প্রতিটা entry-তে ঠিক মানুষের নাম আছে।
  - **স্থায়িত্ব:** entry বদলানো বা মোছা আটকায়।
  - **অনুমতি:** Store Manager 403 পান, Factory Manager পড়তে পারেন।
  - **Notification:**
    - সরাসরি submit হলে একটাই "Created …" entry হয়।
    - প্রথম quote-এ owner পান "… quoted …", revision-এ "… sent revision 2 …"। storekeeper পান না।
    - তালিকায় শুধু MP-এর notification থাকে, নতুনটা আগে; MAINTENANCE_DUE বাদ পড়ে।
    - একটা পড়লে [false, true] হয়। Mark all read ১টা চিহ্নিত করে, অন্য মডিউলেরটা না-পড়া থাকে।
    - অন্যের notification পড়তে গেলে 404।
    - সরবরাহকারী 403 পান, storekeeper ফাঁকা তালিকা পান।
- **MaterialProcurement (সব), NotificationRouting আর ErpMasterDataSeeder** একসাথে: ২০টা টেস্ট, ৪১৯টা assertion সবুজ। seeder-এ ব্যবহারকারী থাকে না, তাই সেখানে audit লেখা বাদ পড়ে।
- **`SupplierPortalAccessTest`:** ৩টা টেস্ট, ৫১টা assertion।
  - **তালিকা আর ভ্যালিডেশন:** সক্রিয় সরবরাহকারীর তালিকা; ফাঁকা ফর্মে চারটা বার্তা; দুর্বল পাসওয়ার্ড দুবার আটকায়; বিদ্যমান ইমেইল আটকায়।
  - **লগইন তৈরি:** অ্যাকাউন্ট EXTERNAL আর membership ACTIVE; সক্রিয় ১; Supplier Master-এর portal_logins ১।
  - **সরবরাহকারীর অনুমতি:** শুধু `material.portal.view`; requisition আর লগইনের তালিকায় 403।
  - **Notification:** requisition submit হলে MP_REQUIREMENT_AVAILABLE আসে (pr, lines)।
  - **switch:** বন্ধ করলে SUSPENDED, সক্রিয় ০, পরের submit-এ notification আসে না; চালু করলে আবার ACTIVE।
  - **অনুমতির সীমা:** Store Manager লগইন দিতে পারেন না। নিষ্ক্রিয় সরবরাহকারীকে লগইন দেওয়া যায় না।
  - **পাসওয়ার্ড:** নতুন লগইনে পাসওয়ার্ড বদলের বাধ্যতা থাকে।
  - **Seat:** সীমা পূর্ণ থাকলেও সরবরাহকারীর লগইন তৈরি হয়; seatCount আর মাসিক UsageMeter-এ সংখ্যা বাড়ে না; একই অ্যাকাউন্টে STOREKEEPER role দিলে ১ বাড়ে।
- **রিগ্রেশন (Supplier Portal Access-এর সময়):** ১৭৩টা টেস্ট সবুজ, ৮৬০টা assertion (২০২৬-১০-২৩)।
- **রিগ্রেশন (Notifications আর Audit Trail):** MaterialProcurement (সব), ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite, Auth, আর Merchandise-এর PurchasedFabricMrr, AccReceipt ও FabricReturn মিলিয়ে ১৮০টা টেস্ট সবুজ, ১১৬৫টা assertion (২০২৬-১০-২৩, একা চালানো, ৪৩ মিনিট)।
- **ব্রাউজার যাচাই, Notifications আর Audit Trail** (২০২৬-১০-২৩):
  - **YKK সরবরাহকারী (shafiq@ykk-bd.test):** Quotation History-তে QT-2026-00002 revise করলেন, USD 2.98, revision ৩।
  - **owner:**
    - সাইডবারে CONTROL → Notifications, Audit Trail, Supplier Portal Access ✔
    - Pique লাইনে In Transit-এ ৩০ KG-এর follow-up দিলেন।
  - **Notifications:** "2 unread of 2"; উপরে "Supplier Submitted a Revised Quotation — YKK … sent revision 3 …" ✔ Mark all read চাপার পরে না-পড়া ০ (DB-তে যাচাই) ✔
  - **Audit Trail:** দুটো entry। Shafiqul Islam-এর CREATE (available requirements, QT-2026-00002, USD 2.98 × 80, 10 days), আর Rahim Uddin-এর CREATE (assigned materials, "… IN_TRANSIT, received 30 — total 150/300 KG") ✔
  - **খোঁজা:** "QT-2026-00002" দিলে একটা entry থাকে ✔
  - **ধরা পড়া সমস্যা:** Reference কলামে নম্বর দুই লাইনে ভাঙছিল। `whitespace-nowrap` দিয়ে ঠিক করা হয়েছে।
  - কোনো পাতায় console error নেই; ডাটা dev DB-তে রাখা আছে।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner লগইন):
  - সাইডবারে CONTROL → Supplier Portal Access ✔
  - **ফাঁকা Create login:** চারটা বার্তা ✔
  - **লগইন তৈরি:** YKK Bangladesh Pte Ltd, Shafiqul Islam, shafiq@ykk-bd.test → তালিকায় এসেছে ✔
  - **switch:** বন্ধ করলে "0 active logins" আর Inactive দেখায়; আবার চালু করলে Active ✔ (DB-তে membership ACTIVE)
  - **সরবরাহকারী হিসেবে লগইন:** ঢুকতে পারেন। সাইডবার ফাঁকা, ড্যাশবোর্ডে "Nothing to show" (Portal-এর স্ক্রিন 08/04-এ আসবে)। My Requisitions খুলতে গেলে আটকায়।
  - **পাসওয়ার্ড বদলের বাধ্যতা:** flag বসানো অবস্থায় লগইন করলে `/account`-এ নিয়ে যায়, সাথে বাধ্যতার বার্তা ✔
  - রেকর্ডটা dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_ ব্যবহারকারী ২০২৬-১০-২৩-এ দুটো প্রশ্নের উত্তর দিয়েছেন:
- প্রথম লগইনে পাসওয়ার্ড বদল বাধ্যতামূলক।
- সরবরাহকারীর লগইন সাবস্ক্রিপশনের ব্যবহারকারী-হিসাবের বাইরে।

## ১১. As-built নোট

- **Supplier Portal Access ✅, Notifications ✅, Audit Trail ✅** (২০২৬-১০-২৩)।
- **Notifications আর Audit Trail-এর লাইভ প্রোব:** `tools/erp_crawler/plans/mp36.json`, শুধু পড়া।
  - Brickland-এর Audit Trail-এ সারি চাপলে কিছু খোলে না, এখানেও তাই।
  - এই প্রোবেই ধরা পড়েছে, Brickland-এ আছে কিন্তু আমাদের এখানে ছিল না এমন তিনটা notification: "Material Received" (08/06-এ যোগ হয়েছে), আর "New Supplier Quotation Received" ও "Supplier Submitted a Revised Quotation" (এখানে যোগ হলো)।
- **লাইভ প্রোব:** `tools/erp_crawler/plans/mp11.json` থেকে `mp13.json`।
  - Brickland-এ POLY PLAN LTD-এর জন্য "Annotech Probe" লগইন তৈরি করা হয়েছে (লেখার অনুমতি অনুযায়ী)। সেটার লগইনের তথ্য শুধু gitignored `.annotech-erp-audit.env`-এ আছে (SUP_USER, SUP_PASS)।
  - mp13-এ সেই লগইন দিয়ে Supplier Dashboard, Available Requirements আর Quotation History দেখা হয়েছে, যা 08/04 বানাতে কাজে লাগবে।
- **প্রোব টুল:**
  - panel.mjs-এ `LOGIN_AS=SUP` দিলে সরবরাহকারীর লগইন দিয়ে ঢোকে।
  - plan-এ `$ENV:NAME` লিখলে মান env ফাইল থেকে আসে, তাই পাসওয়ার্ড plan ফাইলে বা output-এ থাকে না।
- **টেস্টের শিক্ষা:** background রিগ্রেশন চলার সময় আরেকটা phpunit চালানোয় test DB-তে deadlock হয়েছিল। সেই রিগ্রেশন বাতিল করে আবার একা চালানো হয়েছে।
- **seed-এর শিক্ষা:** shell loop-এ seeder class-এর নাম escape না হওয়ায় একবার seed চলেনি, কোনো error-ও দেখায়নি। তাই seeder সবসময় আলাদা কমান্ডে, single quote দিয়ে চালাতে হবে।
