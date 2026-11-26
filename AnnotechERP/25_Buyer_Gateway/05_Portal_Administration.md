# Portal Administration

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Buyer Gateway](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `25/05`

**⬅ ফিরে যান:** [Buyer Gateway](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Portal Usage Insight | `/m/buyer-portal/portal-analytics` | 1 | 3 | 1 |
| 2 | Portal Branding Setup | `/m/buyer-portal/portal-branding` | 1 | 3 | 1 |
| 3 | Portal Activity Trail | `/m/buyer-portal/portal-activity-log` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Portal Usage Insight

`/m/buyer-portal/portal-analytics`

**ভিউ**
> 08 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal usage insight | search |  | Search buyers… |
| Filter by period | select |  | All periods, Aug 2026, Jul 2026, Jun 2026 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Logins, Users, Downloads, Avg session, Adoption
- সেকশন: Portal usage
- বাটন/অ্যাকশন: Add usage line, Buyer, Logins, Users, Downloads, Avg session, Adoption

**ডায়ালগ: Add usage line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Period | select |  | Select…, Aug 2026, Jul 2026, Jun 2026 |
| Logins | number |  |  |
| Active users | number |  |  |
| Most opened | select |  | Select…, Order visibility, Shipment progress, Sample progress, Document vault, Invoice lookup |
| Downloads | number |  |  |
| Average session | number |  |  |
| Adoption | number |  |  |
- খালি Save-এ মেসেজ: H&M added.

#### Portal Branding Setup

`/m/buyer-portal/portal-branding`

**ভিউ**
> 09 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal branding profiles | search |  | Search profiles… |
| Filter by theme | select |  | All themes, Light, Dark, Follows the viewer, High contrast |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Theme, Accent, Logo, Updated, LIVE
- সেকশন: Branding profiles
- বাটন/অ্যাকশন: New profile, Buyer, Theme, Accent, Logo, Updated

**ডায়ালগ: New profile** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Theme | select |  | Select…, Light, Dark, Follows the viewer, High contrast |
| Accent slot | select |  | Select…, Slot 1, Slot 2, Slot 3, Slot 4, Slot 5, Slot 6, Slot 7, Slot 8 |
| Logo file | text |  |  |
| Welcome line | text |  |  |
| Support address | text |  |  |
| Last updated | date |  |  |
- খালি Save-এ মেসেজ: H&M added.

#### Portal Activity Trail

`/m/buyer-portal/portal-activity-log`

**ভিউ**
> 18 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal activity trail | search |  | Search activity… |
| Filter by action | select |  | All actions, Signed in, Downloaded, Approved, Commented, Raised request, Signed out |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Time, Actor, Buyer, Action, Device
- সেকশন: Activity trail
- বাটন/অ্যাকশন: Add entry, Date, Time, Actor, Buyer, Action, Device

**ডায়ালগ: Add entry** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Actor* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Action | select |  | Select…, Signed in, Downloaded, Approved, Commented, Raised request, Signed out |
| Target | text |  |  |
| Date | date |  |  |
| Time | text |  |  |
| Device | select |  | Select…, Desktop, Mobile, Tablet |
- খালি Save-এ মেসেজ: Name the actor.
- invalid চিহ্নিত ফিল্ড: 1টা
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **25/01–04-এর কাঠামো:** `BuyerGatewayResource` (`unique()`, `buyerRule()`), `PortalBook`, `portalScreen()`, টাইল।
- **লোগো আপলোড:** 25/02-এর ডকুমেন্ট ভল্টের মতো — `StoreFileAttachment`, route handler দিয়ে stream, `FileApiController`-এর পারমিশন ম্যাপ।
- **Masters:** buyer master।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_24_000100_create_buyer_gateway_admin`:
- **`bg_usage_lines`:**
  - buyer, মাস (`YYYY-MM`), লগইন, সক্রিয় ইউজার।
  - সবচেয়ে বেশি খোলা পাতা (Order visibility / Shipment progress / Sample progress / Document vault / Invoice lookup)।
  - ডাউনলোড, গড় সেশন (মিনিট), ব্যবহারের হার (%)।
  - buyer + মাস অনন্য।
- **`bg_branding_profiles`:**
  - buyer (অনন্য), থিম (Light / Dark / Follows the viewer / High contrast), অ্যাকসেন্ট স্লট (১–৮)।
  - লোগো (ফাইলের নাম, বা এখানে আপলোড করা ছবির ডাউনলোড পাথ), স্বাগত লেখা, সাপোর্ট ইমেইল।
  - শেষ বদলের দিন, `live`।
- **`bg_activity_entries`:**
  - কে (নাম), buyer, কাজ (Signed in / Downloaded / Approved / Commented / Raised request / Signed out)।
  - কীসের উপর, তারিখ, সময় (`HH:MM`), ডিভাইস (Desktop / Mobile / Tablet)।

## ৫. Workflow / Status ও Business Rules

- **ব্যবহার:**
  - এক buyer-এর এক মাসে একটাই লাইন। অন্য লাইনকে সেই মাসে সরানোও আটকায়।
  - মাস বাছতে হয়: চলতি মাস আর তার আগের ১১ মাস। সামনের মাস বা এক বছরের বেশি পুরোনো মাস আটকায়।
  - লগইনের চেয়ে বেশি সক্রিয় ইউজার হতে পারে না। পরে লগইন কমিয়ে ইউজারের নিচে নামানোও আটকায়।
  - ব্যবহারের হার ০–১০০%, গড় সেশন ০–৬০০ মিনিট।
- **ব্র্যান্ডিং:**
  - প্রতি buyer-এ একটা প্রোফাইল। নতুন হলে থিম Light, স্লট ১, চালু।
  - লোগো শুধু PNG / JPEG / WebP, ২ MB পর্যন্ত। SVG নেওয়া হয় না, কারণ তাতে script থাকতে পারে।
  - লোগোর পাথ হতে হবে এই কোম্পানির, এই স্ক্রিনে আপলোড করা ছবির। অন্য কোম্পানির বা ভল্টের ফাইল আটকায়।
  - গ্রিডে লোগো দেখায় ফাইলের আসল নামে।
  - শেষ বদলের দিন খালি রাখলে, যেকোনো সেভে (Live টগলসহ) সেদিনের তারিখ বসে।
  - গ্রিডের Live টগল দিয়ে চালু / বন্ধ।
- **কাজের খতিয়ান:**
  - কে করেছে, তার নাম লাগে।
  - তারিখ আর সময় খালি রাখলে আজ আর এখন। সময় ২৪ ঘণ্টার `HH:MM` (৯:৪১ → 09:41)।
  - ভবিষ্যতের দিন বা আজকের পরের সময় আটকায়।
  - এডিটে তারিখ বা সময় খালি করলে আগেরটাই থাকে।
  - তালিকা নতুন থেকে পুরোনো (তারিখ, তারপর সময়)।

## ৬. API ও পারমিশন

পারমিশন: `buyergateway.admin.view`, `buyergateway.admin.manage` (Factory Admin / Factory Manager; Auditor শুধু দেখেন)।

API:
- `/api/v1/erp/bg-usage-lines`, `bg-branding-profiles`, `bg-activity-entries`।
- `POST /api/v1/buyer-gateway/logo-files` — লোগো আপলোড (attachable `bg_portal_logo`; ডাউনলোডে admin.view লাগে)।
- `GET /api/v1/buyer-gateway/options` — এখন মাসের তালিকাও (`periods`) দেয়।

## ৭. Frontend পেজ (লাইট থিম)

একই `buyer-gateway/[screen]` route; সাইডবারে Portal Administration ফোল্ডার।
- **Portal Usage Insight** (`/buyer-gateway/portal-analytics`):
  - কলাম: buyer (নিচে সবচেয়ে বেশি খোলা পাতা), মাস, লগইন, ইউজার, ডাউনলোড, গড় সেশন (min), ব্যবহারের হার (বার)।
  - মাস দিয়ে ফিল্টার।
  - টাইল: লগইন, সক্রিয় ইউজার আর ডাউনলোডের যোগফল; গড় ব্যবহারের হার।
- **Portal Branding Setup** (`/buyer-gateway/portal-branding`):
  - কলাম: buyer (নিচে স্বাগত লেখা), থিম, অ্যাকসেন্ট, লোগো, বদলানো, Live টগল।
  - থিম দিয়ে ফিল্টার।
  - লোগোর ঘরে নাম লেখা যায়, বা Upload logo দিয়ে ছবি দেওয়া যায়।
  - টাইল: প্রোফাইল, চালু, ডিফল্টে (বন্ধ প্রোফাইল), ব্যবহৃত থিম।
- **Portal Activity Trail** (`/buyer-gateway/portal-activity-log`):
  - কলাম: তারিখ, সময়, কে (নিচে কীসের উপর), buyer, কাজ, ডিভাইস।
  - কাজ দিয়ে ফিল্টার।
  - টাইল: এন্ট্রি, ডাউনলোড, অনুমোদন, আলাদা ব্যক্তি।
- **ফর্ম kit (`entry-form.jsx`):** ফাইল আপলোড চলার সময় Save বন্ধ থাকে। এটা সব filepath ঘরেই প্রযোজ্য, 25/02-এর ডকুমেন্ট ভল্টসহ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/BuyerGateway`)**
- `Database/Migrations/2027_03_24_000100_create_buyer_gateway_admin.php`
- `Models/UsageLine.php`, `BrandingProfile.php`, `ActivityEntry.php`
- `Resources/UsageLineResource.php`, `BrandingProfileResource.php`, `ActivityEntryResource.php`
- `Support/PortalBook.php` (`periods()`, নতুন তালিকা), `Http/Controllers/Api/BuyerGatewayApiController.php` (`logoFile()`, periods), `Routes/api.php` (বদলানো)
- `Resources/AnnouncementResource.php` (`v.notice_title`; নিচের নোট দেখুন)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php`, `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো)
- `lang/en/buyergateway.php`, `lang/bn/buyergateway.php` (বদলানো)

**Frontend**
- `app/(app)/buyer-gateway/[screen]/screens.js`, `components/buyergateway/tiles.jsx`, `components/erp/entry-form.jsx` (বদলানো)
- `app/api/buyer-gateway/logo-file/route.js` (নতুন)

**Test**
- `tests/Feature/BuyerGateway/BuyerGatewayAdminTest.php` (নতুন), `BuyerGatewayEngagementTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`BuyerGatewayAdminTest.php`, ৪টা টেস্ট):** test2 DB-তে পাস। Buyer Gateway-এর মোট ১৭টা টেস্ট / ৪৬০টা assertion পাস; MenuAccess ৬ / ৩৪ পাস।
- **ব্যবহার:**
  - মাসের তালিকায় ১২টা মাস, Nov 2026 থেকে Dec 2025।
  - buyer ছাড়া আটকায়; মাস ছাড়া, এক বছরের পুরোনো মাস আর সামনের মাস আটকায়।
  - H&M, Aug 2026 (৪১২ / ৯ / ১৪৮ / ১১.৪ / ৯২.৪%) সেভ হয়।
  - একই মাস আবার আটকায়; জুলাইয়ের লাইন আগস্টে সরানো আটকায়; অন্য buyer-এর একই মাস চলে।
  - লগইনের বেশি ইউজার আটকায়, লগইন কমানোও আটকায়।
  - ১০৪% আর অজানা পাতা আটকায়।
- **ব্র্যান্ডিং:**
  - buyer ছাড়া আটকায়; নাম ছেঁটে লোগো; দেওয়া তারিখ থাকে।
  - একই buyer-এর দ্বিতীয় প্রোফাইল আটকায়; অজানা থিম, স্লট ৯ আর ভুল ইমেইল আটকায়।
  - খালি রাখলে Light / ১ / আজ / চালু।
  - দু'দিন পরে থিম বদলালে সেদিনের তারিখ বসে; Live বন্ধ হয়।
  - লোগো: SVG আর ২ MB-এর বেশি আটকায়; PNG আপলোড হয়, নিজের নামে দেখায়, ডাউনলোড হয়।
  - অজানা পাথ আর ভল্টের ফাইল লোগো হিসেবে আটকায়।
- **খতিয়ান:**
  - নাম ছাড়া আটকায়; খালি রাখলে আজ ১০:০০; 9:41 → 09:41।
  - 25:00 আর "2pm" আটকায়; কালকের দিন আর আজ ১১:৩০ (ভবিষ্যৎ) আটকায়; অজানা কাজ আটকায়।
  - তালিকা নতুন থেকে পুরোনো; এডিটে খালি তারিখ / সময় আগেরটাই রাখে।
- **কে পারেন:**
  - Auditor দেখেন, লোগো ডাউনলোড করতে পারেন; যোগ বা আপলোড করতে পারেন না।
  - Manager পারেন।
  - Storekeeper দেখেন না, লোগোও নামাতে পারেন না।
  - অন্য কোম্পানি কিছু দেখে না, এই buyer নিতে পারে না, এই কোম্পানির লোগো নিজের প্রোফাইলে বসাতে পারে না।

**UI চেক (`app_check/plans/bg05`, `bg05b`, `bg05c`, ২০২৬-১১-১২, owner; error 0; dev ডাটা রাখা আছে):**
- ব্যবহার: H&M Aug 2026 আর KIABI Aug 2026 (৩ লগইনে ৫ ইউজারে বার্তা → ৯৬ / ৩)। H&M-এর আগস্ট আবার দিলে বার্তা আসে। টাইল ৫০৮ / ১২ / ১৭৬ / ৭৫.৩% ✔
- ব্র্যান্ডিং: H&M (Follows the viewer, Slot 3, PNG লোগো → hm-portal-mark.png) আর KIABI (High contrast, Slot 6; ভুল ইমেইলে বার্তা)। গ্রিডে KIABI-র Live বন্ধ → টাইলে চালু ১, ডিফল্টে ১ ✔
- আপলোড চলার সময় Save বন্ধ থাকে; শেষ হলে লোগোসহ সেভ হয় ✔
- খতিয়ান: নাম ছাড়া বার্তা; Anna Lindqvist (Approved ১৪:২২, Downloaded; "2pm"-এ বার্তা → ১৪:১৮) আর Hiro Tanaka (এখন, Tablet)। নতুনটা উপরে ✔
- Visibility rules কার্ডের শিরোনাম আবার ঠিক দেখায় ✔

## ১০. খোলা প্রশ্ন

১. **ব্যবহার কোথা থেকে আসবে:** Brickland-এর মতো এখন হাতে লেখা হয়। আসল বায়ার পোর্টাল চালু হলে লগইন, ডাউনলোড আর সেশন নিজে থেকে গোনা হবে কিনা জানাবেন (25/01-এর প্রশ্ন দেখুন)। ব্যবহারের হারও তখন সক্রিয় ইউজার ÷ পোর্টাল ইউজার থেকে হিসাব করা যাবে।

২. **ব্র্যান্ডিং কোথায় দেখাবে:** প্রোফাইল এখন শুধু রাখা থাকে। বায়ার পোর্টাল আর বায়ারকে পাঠানো ইমেইলে লোগো আর রং লাগানো হবে কিনা জানাবেন। "গ্রুপের ডিফল্ট" কোনটা, সেটাও জানাবেন (কোম্পানির নিজের লোগো?)।

৩. **খতিয়ানে কে:** এখন নাম লেখা হয়। 25/01-এর পোর্টাল ইউজার থেকে বাছাই করার ব্যবস্থা লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/bg05`। Brickland-এর ক্রম 08, 09, 18 of 20; সাইডবারের ক্রম আমাদের ফোল্ডার অনুযায়ী (18–20 of 20)।
- **Brickland থেকে পার্থক্য:**
  - ব্যবহারের গ্রিডে "Period" কলাম যোগ হয়েছে, কারণ একই buyer-এর কয়েক মাসের লাইন পাশাপাশি থাকে।
  - "On the default" = বন্ধ প্রোফাইল। Brickland-এর ডেমোতে এটা ১, আর সেটা বন্ধ থাকা Target-এর সাথে মেলে।
  - লোগো Brickland-এ শুধু লেখা; এখানে আপলোডও করা যায়।
  - Live Brickland-এর ডায়ালগে switch; এখানে kit-এর checkbox।
- **Test আর UI চেকে ধরা পড়া, সব ঠিক করা হয়েছে:**
  - Branding-এর `live`-এর কোনো validation rule ছিল না, তাই গ্রিডের টগল সেভ হতো না।
  - ব্যবহারে মাস খালি থাকলে একই মাসের চেক বাদ পড়ত, আর DB-র unique index-এ 500 error আসত। এখন নতুন লাইনে মাস বাধ্যতামূলক।
  - আপলোড চলার সময় সেভ করলে ফাইল হারিয়ে যেত (shared ফর্ম kit-এর সমস্যা)। এখন সেভ বন্ধ থাকে।
  - **25/04-এর বাগ:** ঘোষণার validation বার্তা `v.title`, Visibility rules স্ক্রিনের (25/02) কার্ড শিরোনামের key-টা overwrite করেছিল। বার্তা এখন `v.notice_title`-এ, শিরোনাম ফেরত এসেছে।
