# Order & Shipment Visibility

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Buyer Gateway](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `25/02`

**⬅ ফিরে যান:** [Buyer Gateway](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Order Visibility Rules | `/m/buyer-portal/order-visibility` | 1 | 3 | 1 |
| 2 | Sample Progress View | `/m/buyer-portal/sample-status` | 1 | 3 | 1 |
| 3 | Shipment Progress View | `/m/buyer-portal/shipment-status` | 1 | 3 | 1 |
| 4 | Shared Document Vault | `/m/buyer-portal/document-sharing` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Order Visibility Rules

`/m/buyer-portal/order-visibility`

**ভিউ**
> 02 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order visibility rules | search |  | Search rules… |
| Filter by depth | select |  | All depths, Status only, Status + milestones, Milestones + quantities, Full production trail |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Scope, Depth, Delays, ACTIVE
- সেকশন: Visibility rules
- বাটন/অ্যাকশন: New rule, Buyer, Scope, Depth, Delays

**ডায়ালগ: New rule** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Scope | select |  | Select…, All orders, Confirmed orders only, Current season, Named orders |
| Depth | select |  | Select…, Status only, Status + milestones, Milestones + quantities, Full production trail |
| Shows delays | select |  | Select…, Always, After internal review, Never |
| Effective from | date |  |  |
| Set by | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: H&M added.

#### Sample Progress View

`/m/buyer-portal/sample-status`

**ভিউ**
> 03 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample progress as the buyer sees it | search |  | Search samples… |
| Filter by stage | select |  | All stages, In development, Sent, Commented, Revised, Approved, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Ver, Sent, Decision due, Courier, Stage
- সেকশন: Sample progress
- বাটন/অ্যাকশন: Add sample, Style, Buyer, Ver, Sent, Decision due, Courier, Stage

**ডায়ালগ: Add sample** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Sample type | select |  | Select…, Proto, Fit, Size set, PP, Shipment, Photo |
| Stage | select |  | Select…, In development, Sent, Commented, Revised, Approved, Rejected |
| Sent on | date |  |  |
| Decision due | date |  |  |
| Version | number |  |  |
| Courier | select |  | Select…, DHL, FedEx, Aramex, Hand carried, Not yet sent |
- খালি Save-এ মেসেজ: Name the style.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Shipment Progress View

`/m/buyer-portal/shipment-status`

**ভিউ**
> 04 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shipment progress as the buyer sees it | search |  | Search shipments… |
| Filter by stage | select |  | All stages, Booked, Gate out, On the water, Arrived, Delivered |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order, Buyer, Quantity, Cartons, Agreed, Actual, Stage
- সেকশন: Shipment progress
- বাটন/অ্যাকশন: Add shipment, Order, Buyer, Quantity, Cartons, Agreed, Actual, Stage

**ডায়ালগ: Add shipment** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Mode | select |  | Select…, Sea, Air, Sea-air, Road |
| Stage | select |  | Select…, Booked, Gate out, On the water, Arrived, Delivered |
| Agreed date | date |  |  |
| Actual date | date |  |  |
| Quantity | number |  |  |
| Cartons | number |  |  |
- খালি Save-এ মেসেজ: Name the order.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Shared Document Vault

`/m/buyer-portal/document-sharing`

**ভিউ**
> 05 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shared document vault | search |  | Search documents… |
| Filter by kind | select |  | All kinds, Tech pack, Test report, Inspection report, Packing list, Invoice, Certificate |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Document, Kind, Shared, Size, Downloads, State
- সেকশন: Shared documents
- বাটন/অ্যাকশন: Share document, Document, Kind, Shared, Size, Downloads, State
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **25/01-এর কাঠামো:** `BuyerGatewayResource` (ERP screen kit), `PortalBook`, `screens.js`-এর `portalScreen()`, টাইল।
- **Masters:** buyer master, company-র user।
- **ফাইল:** `StoreFileAttachment` আর `FileApiController` (নতুন ধরন `bg_shared_document`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_21_000100_create_buyer_gateway_visibility`:
- **`bg_visibility_rules`:**
  - buyer — প্রতি buyer-এ একটা নিয়ম।
  - scope: ALL / CONFIRMED / SEASON / NAMED। depth: STATUS / MILESTONES / QUANTITIES / FULL। delays: ALWAYS / REVIEWED / NEVER।
  - কার্যকর তারিখ, কে ঠিক করেছেন, নোট, চালু কিনা।
- **`bg_sample_progress`:**
  - স্টাইল (বড় হাতে), buyer, ধরন (Proto / Fit / Size set / PP / Shipment / Photo), ভার্সন — এই চারটার মিল অনন্য।
  - ধাপ: In development / Sent / Commented / Revised / Approved / Rejected।
  - পাঠানোর তারিখ, সিদ্ধান্তের তারিখ, কুরিয়ার।
- **`bg_shipment_progress`:**
  - অর্ডার (বড় হাতে), buyer, মাধ্যম (Sea / Air / Sea-air / Road)।
  - ধাপ: Booked / Gate out / On the water / Arrived / Delivered।
  - সম্মত ও প্রকৃত তারিখ, পরিমাণ, কার্টন।
- **`bg_shared_documents`:**
  - নাম, buyer, ধরন (Tech pack / Test report / Inspection report / Packing list / Invoice / Certificate)।
  - শেয়ারের তারিখ, কে শেয়ার করেছেন, আকার (MB), ডাউনলোড।
  - অবস্থা: Shared / Withdrawn / Expired। ফাইলের path।

## ৫. Workflow / Status ও Business Rules

- **Visibility rules:** buyer বাধ্যতামূলক; একই buyer-এর দ্বিতীয় নিয়ম আটকায় ("… edit it instead")। ডিফল্ট: Confirmed orders only, Status only, After internal review; কার্যকর আজ থেকে, ঠিক করেছেন যিনি সেভ করলেন। ACTIVE switch।
- **Sample progress:**
  - স্টাইল আর buyer বাধ্যতামূলক। একই স্টাইল-ধরন-ভার্সনের দ্বিতীয়টা আটকায়।
  - In development-এর পরের ধাপে পাঠানোর তারিখ লাগবে। সিদ্ধান্তের তারিখ পাঠানোর আগে হতে পারে না।
- **Shipment progress:** অর্ডার আর buyer বাধ্যতামূলক। Arrived আর Delivered-এ প্রকৃত তারিখ লাগবে। সম্মত তারিখ মানে বায়ারের তারিখ।
- **Document vault:**
  - নাম আর buyer বাধ্যতামূলক।
  - "Attach file" দিয়ে PDF / Excel / ছবি (১০ MB পর্যন্ত) সংযুক্ত করলে আকার নিজে বসে।
  - শুধু vault-এর জন্য আপলোড করা ফাইলই নেওয়া হয় (অন্য path আটকায়)।
  - শেয়ারের তারিখ আর কে শেয়ার করেছেন নিজে বসে। Withdraw / Expire অবস্থা দিয়ে।
- **টাইল:**
  - Rules / Active / Full trail / Delays always shown।
  - Samples / Awaiting the buyer (Sent) / Approved / Rejected।
  - Shipments / Delivered / In transit (On the water) / Pieces shipped (মোট পরিমাণ)।
  - Documents / Currently shared / Downloads / Withdrawn।

## ৬. API ও পারমিশন

পারমিশন: `buyergateway.visibility.view`, `buyergateway.visibility.manage` (Factory Admin / Factory Manager; Auditor দেখা)।

API: `/api/v1/erp/bg-visibility-rules`, `bg-sample-progress`, `bg-shipment-progress`, `bg-shared-documents`; `POST /api/v1/buyer-gateway/document-files` (ফাইল; frontend-এ `/api/buyer-gateway/document-file` দিয়ে stream)। ফাইল ডাউনলোড `/api/files/{id}/download` (visibility view পারমিশন)।

## ৭. Frontend পেজ (লাইট থিম)

একই `buyer-gateway/[screen]` route; সাইডবারে Order & Shipment Visibility ফোল্ডার।
- **Order Visibility Rules** (`/buyer-gateway/order-visibility`): buyer-এর নিচে নোট; depth ফিল্টার; ACTIVE switch।
- **Sample Progress View** (`/buyer-gateway/sample-status`): স্টাইল, buyer (নিচে ধরন), ভার্সন, তারিখ, কুরিয়ার, রঙিন ধাপ; ধাপ ফিল্টার।
- **Shipment Progress View** (`/buyer-gateway/shipment-status`): অর্ডার, buyer (নিচে মাধ্যম), পরিমাণ (১৮৪,০০০), কার্টন, তারিখ, রঙিন ধাপ।
- **Shared Document Vault** (`/buyer-gateway/document-sharing`): নাম (নিচে buyer), ধরন, তারিখ, আকার "MB", ডাউনলোড, অবস্থা, ফাইল; ধরন ফিল্টার।
- **Grid-এ যোগ হলো:** number কলামে `grouping` আর `suffix`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/BuyerGateway`)**
- `Database/Migrations/2027_03_21_000100_create_buyer_gateway_visibility.php`
- `Models/VisibilityRule.php`, `SampleProgress.php`, `ShipmentProgress.php`, `SharedDocument.php`
- `Resources/VisibilityRuleResource.php`, `SampleProgressResource.php`, `ShipmentProgressResource.php`, `SharedDocumentResource.php`; `BuyerGatewayResource.php` (অংশ অনুযায়ী পারমিশন — বদলানো)
- `Support/PortalBook.php`, `Http/Controllers/Api/BuyerGatewayApiController.php` (ফাইল আপলোড), `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` (বদলানো)
- `lang/en/buyergateway.php`, `lang/bn/buyergateway.php` (বদলানো)

**Frontend**
- `app/(app)/buyer-gateway/[screen]/screens.js`, `page.js`, `components/buyergateway/tiles.jsx`, `components/erp/records-grid.jsx` (বদলানো)
- `app/api/buyer-gateway/document-file/route.js` (নতুন)

**Test**
- `tests/Feature/BuyerGateway/BuyerGatewayVisibilityTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`BuyerGatewayVisibilityTest.php`, ৩টা টেস্ট):** Buyer Gateway-এর ৬টা টেস্ট / ১৫৪টা assertion test2 DB-তে পাস। MenuAccess আর ERP master seeder-এর টেস্টও পাস। File API-র ৩টা পুরোনো টেস্ট কন্টেইনারে JPEG ছবি বানাতে না পারায় (GD-তে imagejpeg নেই) চলে না — এই কাজের সাথে সম্পর্ক নেই।
- **Visibility rules:** buyer ছাড়া আটকায়; H&M (All / Full / Always, কার্যকর আজ, ঠিক করেছেন owner); দ্বিতীয় H&M আটকায়; ভুল depth আটকায়; ডিফল্ট; ACTIVE বন্ধ; নোট বদল।
- **Samples:**
  - বাধ্যতামূলক ঘর। পাঠানোর তারিখ ছাড়া Sent আটকায়; পাঠানোর আগের সিদ্ধান্তের তারিখ আটকায়।
  - st-4471 → ST-4471 v2। একই ভার্সন আটকায়, v3 চলে। Approved।
- **Shipments:** অর্ডার ছাড়া আটকায়; so-4402 → SO-4402 (১৮৪,০০০ / ৩৬৮০); প্রকৃত তারিখ ছাড়া Delivered আটকায়; তারিখসহ Delivered; ঋণাত্মক পরিমাণ আটকায়।
- **Vault:**
  - নাম ছাড়া আটকায়। লেখা আকার (১.৪ MB), ডিফল্ট।
  - ছবি আপলোড → path; সংযুক্ত ফাইলের আকার লেখা আকারকে বদলায়।
  - অন্য কোম্পানির / ভুল / বাইরের path আটকায়; ডাউনলোড চলে।
  - Withdrawn আর ডাউনলোড ১৮; মুছা।
- **কে পারেন:** Auditor দেখেন, নিয়ম যোগ বা ফাইল আপলোড পারেন না। Manager শিপমেন্ট যোগ করেন। Storekeeper দেখেন না। অন্য কোম্পানি কিছু দেখে না।

**UI চেক (`app_check/plans/bg02`, `bg02b`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Visibility: H&M (All orders, Full production trail, Always) আর PRIMARK (Milestones + quantities) ✔ দ্বিতীয় H&M-এর বার্তা ✔
- Samples: পাঠানোর তারিখ ছাড়া Sent-এ বার্তা ✔ ST-4471 (H&M, PP, v2, DHL, Sent) আর ST-4482 (PRIMARK, Fit, FedEx, Approved) ✔
- Shipments: SO-4402 (H&M, Sea, Delivered, ১৮৪,০০০) আর SO-4437 (INDITEX, Air, On the water) — Pieces shipped ২০৮,৮০০ ✔
- Vault: ST-4471 tech pack rev 3 (H&M, PDF সংযুক্ত — ০.১ MB, Download লিংক, ১৮ ডাউনলোড) আর OEKO-TEX certificate 2026 (TAKKO, ১.৪ MB, Expired) ✔

## ১০. খোলা প্রশ্ন

১. **স্যাম্পল আর শিপমেন্ট নিজে থেকে আসবে কিনা:** Brickland-এর মতো এখানে স্যাম্পল আর শিপমেন্ট হাতে লেখা হয়। আমাদের Merchandise / Prototype-এ স্যাম্পল আর Sales & Shipment-এ শিপমেন্ট আছে। সেখান থেকে নিজে থেকে এই তালিকায় আনব (আর বায়ারের জন্য প্রকাশ করার আগে যাচাই রাখব) কিনা জানাবেন।

২. **Shared Document Vault-এর File কলাম:** Brickland-এ ফাইল নেই, আকার হাতে লেখা হয়। এখানে আসল ফাইল সংযুক্ত করা যায়, তাই তালিকায় একটা "File / Download" কলাম বেশি আছে। না চাইলে সরিয়ে দেব।

৩. **ডাউনলোডের সংখ্যা:** বায়ারের পোর্টাল না থাকায় ডাউনলোড এখনো হাতে লেখা হয়। পোর্টাল হলে বায়ার যতবার নামাবে, নিজে থেকে গোনা হবে।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/bg02` (তালিকা, New / Edit ডায়ালগ, অপশন, টাইল)। Brickland-এর ক্রম 02–05 of 20।
- **পারমিশন অংশে ভাগ:** 25/01 `buyergateway.access.*`, 25/02 `buyergateway.visibility.*`; `BuyerGatewayResource::area()` ঠিক করে।
- **ফাইল:** "Attach file" route handler দিয়ে stream হয় (server action-এর ১ MB সীমা এড়াতে)। ফাইলের ধরন `bg_shared_document` FileApiController-এর map-এ যোগ হয়েছে, নইলে ডাউনলোডে ভুল পারমিশন লাগত।
- **UI চেকে:** dev-এ প্রথম সেভে ১০ সেকেন্ডের বেশি লাগায় একটা ডায়ালগ খোলা থেকে দুটো এন্ট্রি মিশে গিয়েছিল (প্ল্যানের অপেক্ষার সমস্যা)। Edit দিয়ে ঠিক করা হয়েছে, পণ্যে কোনো bug নেই।
