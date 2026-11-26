# Access & Onboarding

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Buyer Gateway](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `25/01`

**⬅ ফিরে যান:** [Buyer Gateway](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Portal User Accounts | `/m/buyer-portal/portal-users` | 1 | 3 | 1 |
| 2 | Portal Access Permissions | `/m/buyer-portal/access-permissions` | 1 | 3 | 1 |
| 3 | Buyer Onboarding Workflow | `/m/buyer-portal/buyer-onboarding` | 1 | 3 | 0 |
| 4 | Notification Settings | `/m/buyer-portal/notification-preferences` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Portal User Accounts

`/m/buyer-portal/portal-users`

**ভিউ**
> 01 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal user accounts | search |  | Search users… |
| Filter by buyer | select |  | All buyers, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: User, Buyer, Role, Last seen, State, LOGIN
- সেকশন: Portal users
- বাটন/অ্যাকশন: New portal user, User, Buyer, Role, Last seen, State

**ডায়ালগ: New portal user** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Full name* | text | ✔ |  |
| Email* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Role | select |  | Select…, Merchandiser, Quality manager, Sourcing head, Logistics, Finance, Read only |
| Country | select |  | Select…, Sweden, United Kingdom, Spain, United States, Japan, Australia, Germany, United Arab Emirates |
| Last seen | date |  |  |
| State | select |  | Select…, Active, Invited, Suspended, Locked |
- খালি Save-এ মেসেজ: Name the user. · An email is required.
- invalid চিহ্নিত ফিল্ড: 2টা

#### Portal Access Permissions

`/m/buyer-portal/access-permissions`

**ভিউ**
> 17 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal access permissions | search |  | Search permission sets… |
| Filter by buyer | select |  | All buyers, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Role, Orders, Samples, Documents, Commercial, APPROVE
- সেকশন: Permission sets
- বাটন/অ্যাকশন: New permission set, Role, Orders, Samples, Documents, Commercial

**ডায়ালগ: New permission set** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Role | select |  | Select…, Merchandiser, Quality manager, Sourcing head, Logistics, Finance, Read only |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Orders | select |  | Select…, Full, Read only, Hidden |
| Samples | select |  | Select…, Full, Read only, Hidden |
| Documents | select |  | Select…, Download, View only, Hidden |
| Commercial | select |  | Select…, Full, Read only, Hidden |
| Set on | date |  |  |
- খালি Save-এ মেসেজ: Merchandiser added.

#### Buyer Onboarding Workflow

`/m/buyer-portal/buyer-onboarding`

**ভিউ**
> 19 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer onboarding workflow | search |  | Search onboardings… |
| Filter by step | select |  | All steps, Contract signed, Account created, Users invited, Permissions set, Branding applied, Visibility agreed, Traini |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Current step, Progress, Users, Target live, State
- সেকশন: Onboarding
- বাটন/অ্যাকশন: Start onboarding, Buyer, Current step, Progress, Users, Target live, State

#### Notification Settings

`/m/buyer-portal/notification-preferences`

**ভিউ**
> 20 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Notification settings | search |  | Search events… |
| Filter by channel | select |  | All channels, Email, In-portal, Email + In-portal, Muted |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Event, Channel, Digest, Recipients, ENABLED
- সেকশন: Notification settings
- বাটন/অ্যাকশন: New preference, Event, Channel, Digest, Recipients

**ডায়ালগ: New preference** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Event | select |  | Select…, Sample approved, Sample commented, Shipment booked, Shipment delivered, Document shared, Invoice published, Ann |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Channel | select |  | Select…, Email, In-portal, Email + In-portal, Muted |
| Digest | select |  | Select…, Immediate, Daily digest, Weekly digest |
| Recipients | select |  | Select…, All portal users, Merchandisers only, Named contacts, Account owner |
| Set on | date |  |  |
- খালি Save-এ মেসেজ: Sample approved added.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **ERP screen kit:** `ResourceDefinition` + `ResourceApiController` (`/api/v1/erp/{key}`) — তালিকা, New, Edit, Delete; frontend-এ `RecordsGrid` আর `DialogFormButton`।
- **Masters:**
  - Buyer: `buyers` (Merchandise-এর buyer master; short name দেখানো হয়)।
  - দেশ: `country_configs`।
  - Owner: company-র user।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_20_000100_create_buyer_gateway_access`:
- **`bg_portal_users`:**
  - নাম, ইমেইল (company-তে অনন্য, ছোট হাতে রাখা), buyer, ভূমিকা, দেশ, শেষ দেখা।
  - অবস্থা: ACTIVE / INVITED / SUSPENDED / LOCKED।
  - লগইন চালু কিনা, কে তৈরি করেছেন।
  - ভূমিকা ছয়টা: Merchandiser, Quality manager, Sourcing head, Logistics, Finance, Read only।
- **`bg_permission_sets`:**
  - buyer আর ভূমিকা — প্রতি buyer-এ প্রতি ভূমিকার একটাই সেট।
  - Orders / Samples / Commercial: FULL / READ_ONLY / HIDDEN। Documents: DOWNLOAD / VIEW_ONLY / HIDDEN।
  - অনুমোদন করতে পারে কিনা, সেটের তারিখ।
- **`bg_onboardings`:**
  - buyer-এর নাম, লেখা হিসেবে (master-এ না থাকলেও চলে; company-তে অনন্য)।
  - ধাপ — আটটা: Contract signed … Live।
  - Owner, শুরু, চালুর লক্ষ্য, অগ্রগতি %, আমন্ত্রিত ইউজার।
  - অবস্থা: ON_TRACK / AT_RISK / BLOCKED / COMPLETE।
- **`bg_notification_prefs`:**
  - buyer আর ঘটনা — প্রতি buyer-এ প্রতি ঘটনার একটাই। ঘটনা আটটা: Sample approved … Request answered।
  - চ্যানেল: EMAIL / IN_PORTAL / BOTH / MUTED। Digest: IMMEDIATE / DAILY / WEEKLY। প্রাপক: ALL / MERCHANDISERS / NAMED / OWNER।
  - চালু কিনা, সেটের তারিখ।

## ৫. Workflow / Status ও Business Rules

- **Portal users:**
  - নাম, ইমেইল আর buyer বাধ্যতামূলক। একই ইমেইলে দ্বিতীয় লগইন আটকায়।
  - Suspended বা Locked করলে লগইন নিজে থেকে বন্ধ হয়। তখন LOGIN switch চালু করতে গেলে আটকায় ("… make them Active or Invited before switching the login on")।
  - নতুন ইউজার শুরুতে Invited, লগইন চালু।
- **Permission sets:** ভূমিকা আর buyer বাধ্যতামূলক। একই buyer-এ একই ভূমিকার দ্বিতীয় সেট আটকায়। নতুন সেটের ডিফল্ট: Orders / Samples Read only, Documents View only, Commercial Hidden; সেটের তারিখ আজ।
- **Onboarding:**
  - অগ্রগতি ধাপ থেকে নিজে আসে: আটটার কতটা, half-to-even rounding (Brickland-এর মতো ১২, ৩৮, ৫০, ৬২, ৮৮, ১০০ %)।
  - Live হলে অবস্থা Complete; Live থেকে ফিরলে On track।
  - চালুর লক্ষ্য শুরুর আগে হতে পারে না। শুরু না দিলে আজ।
- **Notification settings:**
  - ঘটনা আর buyer বাধ্যতামূলক। একই buyer-এ একই ঘটনার দ্বিতীয়টা আটকায়।
  - Muted হলে বন্ধ; চ্যানেল না দিয়ে চালু করা আটকায়।
- **সব তালিকায়:** খোঁজা, buyer / ধাপ / চ্যানেল দিয়ে ফিল্টার, কলামে sort, ১০ / ২৫ / ৫০ / ১০০ করে পাতা, Edit আর Delete (নিশ্চিতকরণসহ)।
- **টাইল:**
  - Users: পোর্টাল ইউজার, সক্রিয়, স্থগিত, বায়ার অ্যাকাউন্ট।
  - Permissions: সেট, অনুমোদন করতে পারে, বায়ার আওতায়, চালু ভূমিকা।
  - Onboarding: অনবোর্ডিং, ঠিক পথে, আটকে আছে, গড় অগ্রগতি।
  - Notifications: পছন্দ, চালু, নিঃশব্দ, বায়ার সেট করা।

## ৬. API ও পারমিশন

পারমিশন: `buyergateway.access.view`, `buyergateway.access.manage`। Factory Admin / Factory Manager — দুটোই; Auditor — দেখা।

API: `/api/v1/erp/bg-portal-users`, `bg-permission-sets`, `bg-onboardings`, `bg-notification-prefs` (GET / POST / PATCH / DELETE), আর `GET /api/v1/buyer-gateway/options` (buyer, দেশ, staff)।

## ৭. Frontend পেজ (লাইট থিম)

`frontend/src/app/(app)/buyer-gateway/[screen]/` (`page.js`, `screens.js`, `actions.js`), `components/buyergateway/tiles.jsx`:
- চার স্ক্রিনেই ৪টা টাইল, তারপর একটা কার্ড: শিরোনাম, New, খোঁজা (বাঁয়ে), ফিল্টার (ডানে), তালিকা, switch কলাম (LOGIN / APPROVE / ENABLED), Edit / Delete, পেজার।
- Edit আর New একই ডায়ালগ; ইউজারের Edit-এ "Login enabled"ও।
- **Kit-এ যোগ হলো (`components/erp/records-grid.jsx`, সব ঐচ্ছিক):**
  - `header.searchLeft` + `header.pick` (client-side ফিল্টার)।
  - `edit.description`।
  - কলামের `sub` (নিচে দ্বিতীয় লাইন), badge-এর `tones`।
  - `progress` টাইপ, pill ছাড়া `bare` toggle।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/BuyerGateway`, নতুন মডিউল)**
- `Database/Migrations/2027_03_20_000100_create_buyer_gateway_access.php`
- `Models/PortalUser.php`, `PortalPermissionSet.php`, `PortalOnboarding.php`, `PortalNotificationPref.php`
- `Resources/BuyerGatewayResource.php`, `PortalUserResource.php`, `PortalPermissionSetResource.php`, `PortalOnboardingResource.php`, `PortalNotificationPrefResource.php`
- `Support/PortalBook.php`, `Http/Controllers/Api/BuyerGatewayApiController.php`, `Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/buyergateway.php`, `lang/bn/buyergateway.php` (নতুন), `lang/*/nav.php` (বদলানো)

**Frontend**
- `frontend/src/app/(app)/buyer-gateway/[screen]/page.js`, `screens.js`, `actions.js`, `frontend/src/components/buyergateway/tiles.jsx` (নতুন)
- `frontend/src/components/erp/records-grid.jsx` (বদলানো)

**Test**
- `tests/Feature/BuyerGateway/BuyerGatewayAccessTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`BuyerGatewayAccessTest.php`, ৩টা টেস্ট, ৮২টা assertion):** test2 DB-তে পাস। MenuAccess, ERP master seeder আর Buyer Gateway মিলিয়ে ১০ টেস্ট / ১৩১ assertion — সব পাস।
- **Portal users:**
  - picker-এ buyer-এর short name।
  - বাধ্যতামূলক ঘরের বার্তা; ইমেইল ছোট হাতে; দ্বিতীয় লগইন, ভুল ইমেইল, অন্য buyer আটকায়।
  - Suspended → লগইন বন্ধ, আবার চালু আটকায় → Active করে চালু → বন্ধ → মুছা।
- **Permission sets / Onboarding / Notifications:**
  - ডিফল্ট; দ্বিতীয় সেট আটকায়; অজানা ভূমিকা আটকায়; APPROVE বন্ধ।
  - অনবোর্ডিং: Permissions set ৫০ %, owner, শুরু আজ; একই buyer আর শুরুর আগের লক্ষ্য আটকায়; ধাপ অনুযায়ী ১২ / ৩৮ / ৮৮; Live → ১০০ % Complete; ফিরলে On track; Blocked।
  - Notification: দ্বিতীয়টা আটকায়; Muted → বন্ধ, চালু আটকায়; চ্যানেল দিয়ে চালু; নতুন Muted বন্ধ থাকে।
- **কে পারেন:** Auditor দেখেন, যোগ করতে পারেন না। Manager পারেন। Storekeeper দেখেন না। অন্য কোম্পানি কিছু দেখে না, এই buyer ব্যবহার করতে পারে না।

**UI চেক (`app_check/plans/bg01`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Portal users:
  - Anna Lindqvist (H&M, Merchandiser, Sweden, Active) আর Marta Ruiz (INDITEX, Quality manager, Suspended → লগইন বন্ধ) ✔
  - একই ইমেইলে দ্বিতীয় ইউজার আটকায় ✔
  - Marta-র LOGIN চালু করতে গেলে বার্তা ✔
- Permission set: Merchandiser · H&M (Full / Full / Download / Read only), APPROVE switch ✔
- Onboarding: Next Retail (Permissions set, ৫০ %, Rahim Uddin, ৪ জন) আর Matalan (১২ %, At risk) ✔
- Notifications: Sample approved · H&M (Email + In-portal) আর Announcement posted · LPP (Muted, বন্ধ) ✔ Edit ডায়ালগ ✔

## ১০. খোলা প্রশ্ন

১. **বায়ারের আসল পোর্টাল লগইন:** Brickland-এর এই স্ক্রিনগুলো "Demo data" — ভেতরের অ্যাডমিন তালিকা। এখানে সেগুলো পুরো কাজ করে (আসল টেবিল, আমাদের buyer master), কিন্তু বায়াররা নিজেরা ঢুকবে এমন আলাদা পোর্টাল (লগইন পেজ, বায়ারের নিজের অর্ডার / স্যাম্পল / শিপমেন্ট দেখা) এখনো নেই। তাই "Last seen" হাতে লেখা হয়, আর LOGIN switch শুধু অনুমতিটা রাখে। আলাদা বায়ার পোর্টাল বানাব কিনা জানাবেন।

২. **নোটিফিকেশন পাঠানো:** Notification Settings পছন্দগুলো রাখে, কিন্তু পোর্টাল না থাকায় এখনো কোনো মেইল বা পোর্টাল-বার্তা যায় না। পোর্টাল হলে এই পছন্দ মেনে পাঠানো হবে।

৩. **Onboarding-এর বায়ার:** Brickland-এর মতো লেখা নাম (Next Retail …), কারণ নতুন বায়ার তখনো master-এ নাও থাকতে পারে। Live হলে buyer master-এ নিজে থেকে বায়ার তৈরি হবে কিনা জানাবেন।

## ১১. As-built নোট

- **Demo data স্ক্রিন:** Brickland-এ এখন `PortalUsersScreen` ইত্যাদি আলাদা component, ডাটা server action দিয়ে আসে, ব্যাজ "Demo data"। লাইভ প্রোব: `tools/erp_crawler/out/bg01`, `bg01p`, `bg01d` (তালিকা, New / Edit / Delete ডায়ালগ, অপশন)।
- **নতুন মডিউল:** Buyer Gateway সাইডবারের OVERVIEW অংশে, Access & Onboarding ফোল্ডারে চারটা স্ক্রিন।
- **ক্রমিক নম্বর:** Brickland-এ এই চারটার নম্বর 01, 17, 19, 20 (২০টার মধ্যে; ফোল্ডারের ক্রম মানে না)। আমাদের নম্বর সাইডবারের ক্রম ধরে (এখন 01–04 of 4)। বাকি ফোল্ডার এলে ক্রম ঠিক হবে।
- **অগ্রগতির rounding:** Brickland-এর ১২ / ৩৮ / ৬২ / ৮৮ % মেলাতে half-to-even; প্রথমে সাধারণ rounding-এ ১৩ আসছিল (test ধরেছে)।
