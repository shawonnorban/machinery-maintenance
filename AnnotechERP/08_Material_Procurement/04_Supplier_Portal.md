# Supplier Portal

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টা স্ক্রিনই চালু: Supplier Dashboard, Available Requirements, Quotation History)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/04`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Supplier Dashboard | `/m/material-procurement/supplier-dashboard` | 1 | 0 | 0 |
| 2 | Available Requirements | `/m/material-procurement/available-requirements` | 1 | 0 | 0 |
| 3 | Quotation History | `/m/material-procurement/quotation-history` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Supplier Dashboard

`/m/material-procurement/supplier-dashboard`

**ভিউ**
> 07 of 15
- সেকশন: This screen is not available

#### Available Requirements

`/m/material-procurement/available-requirements`

**ভিউ**
> 08 of 15
- সেকশন: This screen is not available

#### Quotation History

`/m/material-procurement/quotation-history`

**ভিউ**
> 09 of 15
- সেকশন: This screen is not available
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08/03 Purchase Requisition:** জমা হওয়া requisition-এর "Open for Quotation" লাইনগুলোই এখানে চাহিদা।
- **08/07 Supplier Portal Access:** সরবরাহকারীর লগইন (`mp_supplier_logins`), SUPPLIER role আর `material.portal.view`।
- **Notification:** requisition জমার সময় পাঠানো MP_REQUIREMENT_AVAILABLE; ড্যাশবোর্ডের Notifications অংশে দেখায়।

## ৪. ডাটা মডেল (Entity ও Field)

- **`mp_quotations`:** qt_no (`QT-2026-00001`), requisition_line_id, supplier_id, status, revisions (কয়টা revision)।
  - একই লাইনে একজন সরবরাহকারীর একটাই কোটেশন থাকে; নতুন দাম তার revision হয়।
  - অবস্থা: PENDING, REVISED, NEGOTIATION, APPROVED, REJECTED, CLOSED।
- **`mp_quotation_revisions`:** quotation_id, revision (১, ২…), quoted_qty, unit_price, currency (USD, BDT, EUR, GBP, CNY, INR), lead_time_days, expected_delivery, remarks, submitted_by, submitted_at।
  - জমার পর কখনো বদলায় না বা মোছে না।

## ৫. Workflow / Status ও Business Rules

- **কে দেখেন:** শুধু সক্রিয় লগইনের সরবরাহকারী।
  - লগইন ছাড়া কেউ (owner-সহ, যাঁর সব অনুমতি আছে) এই স্ক্রিন খুললে দেখেন: "This screen is not available — This screen is for supplier portal accounts. Ask the Super Admin for a supplier login."
  - Portal-এর অনুমতি নেই এমন কর্মী API-তে 403 পান।
- **চাহিদা:** জমা হওয়া requisition-এর প্রতিটা Open for Quotation লাইন, প্রত্যেক সক্রিয় সরবরাহকারীর জন্য একই। নতুন requisition আগে, লাইন তার ক্রমে। খসড়া requisition-এর লাইন দেখায় না।
- **Submit quotation:**
  - Quoted Qty আর Unit Price শূন্যের বেশি; Currency; Lead Time পূর্ণ দিনে; Expected Delivery।
  - প্রথম দাম দিলে QT নম্বর তৈরি হয়, অবস্থা Pending।
  - আবার দিলে (Revise) সেটা revision ২, ৩… হয়, অবস্থা Revised। আগের দাম অপরিবর্তিত থাকে।
- **কখন আটকায়:**
  - লাইন বা requisition আর খোলা না থাকলে: "This requirement is no longer open for quotation."
  - কোটেশন Approved, Rejected বা Closed হলে: "This quotation is … and can no longer be revised."
- **গোপনীয়তা:** একজন সরবরাহকারী শুধু নিজের কোটেশন দেখেন।
- **কোটেশনের সংখ্যা:** My Requisitions-এর লাইনে কয়জন সরবরাহকারী কোট দিয়েছেন, তা দেখায়।
- **ড্যাশবোর্ডের সংখ্যা:** Pending requirements (সব খোলা লাইন), Submitted quotations, Approved, Rejected / closed।
- **সরবরাহকারীর লগইনের সীমা (AnnotechERP 08/07):**
  - `/auth/me`-তে `portal_only` জানায় অ্যাকাউন্টটা শুধু portal-এর কিনা।
  - এমন অ্যাকাউন্ট লগইনের পরে সরাসরি Supplier Dashboard-এ যায়।
  - Portal ছাড়া অন্য কোনো স্ক্রিন খুললে পাতার বদলে দেখায় "This screen is not available — Supplier accounts can only use the Supplier Portal."।
  - `/account` আর `/notifications` খোলা থাকে।

## ৬. API ও পারমিশন

পারমিশন: `material.portal.view` (SUPPLIER role; COMPANY_OWNER-ও পান, কিন্তু লগইন না থাকায় বার্তা দেখেন)।

| Method | Path |
|---|---|
| GET | `/material-procurement/portal/dashboard` (supplier, counts, recent, notifications) |
| GET | `/material-procurement/portal/requirements` (currencies, lines, প্রতিটায় নিজের কোটেশন) |
| POST | `/material-procurement/portal/requirements/{line}/quote` (quoted_qty, unit_price, currency, lead_time_days, expected_delivery, remarks) |
| GET | `/material-procurement/portal/quotations` (নিজের কোটেশন, প্রতিটার সব revision) |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → **SUPPLIER PORTAL** (৩)। সরবরাহকারীর লগইনে শুধু এই folder দেখায়।

- **Supplier Dashboard:**
  - উপরে "Welcome, Supplier" আর সরবরাহকারীর নাম; চারটা সংখ্যা।
  - Recent requirements (নতুন খোলা চাহিদা আগে, Quote/Revise বাটনসহ); Notifications।
- **Available Requirements:**
  - "Available requirements" কার্ড; pill All / Fabric / Accessories, সংখ্যাসহ; Search requirements।
  - কলাম: Requisition (PR নং আর ধরন), Order No., Item (নাম আর বিবরণ), Req. Qty, Required, Your quotation ("Not quoted" অথবা অবস্থা · দাম · rev n)। শেষে Quote বা Revise।
- **Quote dialog:**
  - শিরোনাম "Submit quotation" বা "Revise quotation — revision n"।
  - উপরে লাইনের তথ্য: Requisition No., Type, Item, Specification, Colour / Size, Required Qty, Requirement Date; revise-এ "Your last price"।
  - ঘর: Quoted Qty* (পাশে একক), Unit Price*, Currency*, Lead Time* (দিন), Expected Delivery*, Remarks। নিচে গোপনীয়তার নোট।
  - জমা দিলে পাতা রিলোড ছাড়াই তালিকা হালনাগাদ হয়।
- **Quotation History:**
  - pill All / Revision requested / Approved / Rejected-closed।
  - প্রতিটা সারিতে QT নং, PR · item · পরিমাণ, অবস্থা, "Latest USD 4.10 · 2 revision(s)" আর Revise price।
  - সারি খুললে প্রতিটা revision আলাদা: "Revision 1 (initial)", দাম, পরিমাণ · lead time · delivery · সময় · মন্তব্য।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_17_000100_create_mp_quotations.php`, `Models/Quotation.php`, `Models/QuotationRevision.php`, `Http/Controllers/Api/SupplierPortalApiController.php`, `Routes/api.php`, `Http/Controllers/Api/PurchaseRequisitionApiController.php` (লাইনে কোটেশনের সংখ্যা) |
| লগইন / অ্যাকাউন্ট | `app/Modules/Api/Http/Controllers/Api/AuthController.php` (`portal_only`) |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.SUPPLIER_PORTAL`, তিনটা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/portal.js`, `page.js`, `actions.js` (`portalAction`), `frontend/src/components/materialprocurement/supplier-portal.jsx`, `frontend/src/components/layout/portal-guard.jsx`, `frontend/src/app/(app)/layout.js` |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`sp`) |
| টেস্ট | `tests/Feature/MaterialProcurement/SupplierPortalTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ (08/05 Price Review & Approval):** admin প্রতিটা লাইনে সব সরবরাহকারীর সর্বশেষ দাম তুলনা করবেন। প্রতিটা কোটেশনে Approve & Handover, Negotiate বা Reject, আর পুরো লাইনে Reject line।
  - Negotiate দিলে কোটেশন হবে NEGOTIATION ("Revision requested")।
  - Approve দিলে লাইনের Approved Supplier বসবে, আর requisition হবে Approved।

## ৯. টেস্ট ও যাচাই

- **`SupplierPortalTest`:** ২টা টেস্ট, ৫৩টা assertion।
  - **`portal_only`:** সরবরাহকারীর ক্ষেত্রে true, owner-এর ক্ষেত্রে false।
  - **ড্যাশবোর্ড আর চাহিদা:** ড্যাশবোর্ডের সংখ্যা আর notification-এর লেখা; খসড়া requisition-এর লাইন বাদ যায়; লাইনের তথ্য ঠিক আসে।
  - **কোটেশন:**
    - ফাঁকা কোটেশনে পাঁচটা বার্তা।
    - প্রথম দামে QT-…-00001 Pending; দ্বিতীয় দামে revision ২ Revised, আগের দাম অক্ষত।
    - History আর চাহিদার সারিতে কোটেশনের অবস্থা দেখায়।
  - **দুই সরবরাহকারী:** দ্বিতীয় সরবরাহকারী প্রথমজনের কোটেশন দেখেন না, আর তাঁর কোটেশন QT-…-00002। requisition-এর লাইনে কোটেশনের সংখ্যা ২।
  - **Approved:** কোটেশনে আর revise চলে না; ড্যাশবোর্ডে Approved ১।
  - **বাধা আর অনুমতি:**
    - খসড়া requisition-এর লাইনে কোট আটকায়।
    - owner পান supplier null, খালি তালিকা আর লগইন না থাকার বার্তা।
    - Storekeeper 403 পান।
- **রিগ্রেশন:** MaterialProcurement (সব), ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite আর Auth মিলিয়ে ১৭৩টা টেস্ট সবুজ, ৮৬০টা assertion (২০২৬-১০-২৩, একা চালানো)।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩):
  - **সরবরাহকারী হিসেবে লগইন (YKK, Shafiqul Islam):**
    - সরাসরি Supplier Dashboard; সাইডবারে শুধু SUPPLIER PORTAL ✔
    - PR-2026-00002-এর দুটো লাইন Not quoted ✔
  - **কোটেশন:**
    - ফাঁকা Submit quotation-এ তিনটা বার্তা ✔
    - Pique 220 GSM-এ USD 4.35, ১২ দিন, ২০ Nov, "Ex-factory Gazipur" → QT-2026-00001 Pending ✔
    - Revise dialog-এ "Your last price USD 4.35 (revision 1)"; USD 4.10 দিয়ে জমা ✔
    - History-তে Revised, "Latest USD 4.10 · 2 revision(s)", দুটো revision আলাদা ✔
  - **অন্য স্ক্রিন:** My Requisitions খুললে "Supplier accounts can only use the Supplier Portal." ✔
  - **owner হিসেবে:** Supplier Dashboard-এ "This screen is for supplier portal accounts…"; My Requisitions-এ সেই লাইনে কোটেশন ১ ✔
  - কোনো পাতায় console error নেই। রেকর্ডগুলো dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/mp13.json` থেকে `mp17.json`। এগুলো চালানো হয়েছে Brickland-এর পরীক্ষার সরবরাহকারী লগইন (POLY PLAN LTD) দিয়ে।
  - Brickland-এ PR-2026-00006-এ QT-2026-00004 দেওয়া হয়েছে: USD 2.85, তারপর revision ২-এ USD 2.70 (লেখার অনুমতি অনুযায়ী)।
- **dev ডাটা:** YKK লগইনের পাসওয়ার্ড বদলের বাধ্যতা আগেই যাচাই হয়েছিল। Portal দেখার জন্য শুধু এই dev অ্যাকাউন্টে বাধ্যতাটা তুলে দেওয়া হয়েছে।
- **টেস্টে ধরা পড়েছে:** চাহিদার query-তে join-এর পরে `status` কলাম কোন টেবিলের তা অস্পষ্ট ছিল (Postgres ambiguous column)। এখন কলামের আগে টেবিলের নাম দেওয়া আছে।
