# Dashboard

> **স্ট্যাটাস:** ✅ সম্পন্ন (Procurement Dashboard চালু)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/02`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Procurement Dashboard | `/m/material-procurement/procurement-dashboard` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Procurement Dashboard

`/m/material-procurement/procurement-dashboard`

**ভিউ**
> 01 of 15
- গ্রিড কলাম: PR NO., TYPE, ORDER, LINES, DATE, STATUS
- গ্রিড কলাম: MATERIAL, SUPPLIER, PRICE, BALANCE, STATUS
- সেকশন: Complete workflow, Latest requisitions, Deliveries being followed up
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08/01 থেকে 08/07-এর সব টেবিল:** `mp_items`, `mp_suppliers`, `mp_supplier_logins`, `mp_requisitions`, `mp_requisition_lines`, `mp_quotations`, `mp_quotation_revisions` আর `mp_followups`। dashboard নিজে কোনো টেবিল রাখে না, এগুলো থেকেই হিসাব করে।
- **`ProcurementControlApiController::STAFF`:** মডিউলের কর্মী কারা। dashboard-এর অনুমতিও একই নিয়মে।
- **My Requisitions-এর `?open=PR-…`:** PR নং-এ ক্লিক করলে সেই requisition খোলা অবস্থায় আসে। URL-এ ULID নয়, PR নং থাকে।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই। সব সংখ্যা প্রতিবার হিসাব করা হয়।

## ৫. Workflow / Status ও Business Rules

- **Complete workflow:** ৮টা ধাপ; প্রতিটা ধাপ Brickland-এর মতো একটা স্ক্রিনে নিয়ে যায়।

| ধাপ | স্ক্রিন |
|---|---|
| Master Data | Fabric Master |
| Purchase Requisition | Create Requisition |
| All Suppliers | Supplier Portal Access |
| Quotation | Price Review |
| Super Admin Review | Price Review |
| Handover | Assigned Materials |
| Follow-up | Delivery Tracking |
| GRN | Assigned Materials |

- **৮টা সংখ্যা** (Brickland-এর হিসাব মিলিয়ে)। এগুলোয় ক্লিক করা যায় না, Brickland-এও না।

| ঘর | হিসাব |
|---|---|
| Draft requisitions | DRAFT অবস্থার requisition |
| Submitted requisitions | খসড়া নয় এমন সব requisition (Submitted, Approved, Rejected) |
| Lines open for quotation | জমা দেওয়া requisition-এর খোলা লাইন |
| Quotations received | সব কোটেশন |
| Awaiting price review | খোলা লাইনে Pending বা Revised কোটেশন |
| In negotiation | খোলা লাইনে Negotiation-এ থাকা কোটেশন |
| Approved lines | অনুমোদিত লাইন (Approved থেকে In-House পর্যন্ত) |
| In transit / receiving | Dispatched, In Transit আর Partially Received |

- **Latest requisitions:**
  - সর্বশেষ ৮টা requisition, PR নং ধরে নতুনটা আগে।
  - সাবটাইটেল: "n active items in master data · x of y active suppliers have portal logins"।
  - **New requisition বাটন:** দেখায় শুধু যাঁর `material.requisition.manage` আছে।
- **Deliveries being followed up:**
  - অনুমোদিত লাইন, সর্বোচ্চ ৮টা। যেগুলো এখনো আসছে সেগুলো আগে, তারপর পুরো পাওয়া বা In-House হওয়াগুলো; প্রতিটা দলে সর্বশেষ বদল আগে।
  - Balance = Required − follow-up-এ পাওয়া মোট।
  - সাবটাইটেল: "n line(s) fully received or in-house"।
  - **Delivery tracking বাটন:** দেখায় শুধু যাঁর `material.followup.monitor` আছে।

## ৬. API ও পারমিশন

- **কে খুলতে পারেন:** মডিউলের কর্মীরা, অর্থাৎ যাঁদের requisition.view, price.approve, followup.view বা portal_access.manage অনুমতি আছে।
- **সরবরাহকারী:** তাঁর লগইন 403 পায়; তাঁর নিজের Supplier Dashboard আছে।

| Method | Path |
|---|---|
| GET | `/material-procurement/procurement-dashboard` (counts, active_items, active_suppliers, suppliers_with_login, requisitions, deliveries, done) |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → **DASHBOARD** → Procurement Dashboard। এটা মডিউলের প্রথম ফোল্ডার, হেডারে "01 of 15"।

- **"Complete workflow" কার্ড:** তীর দেওয়া লাইন, তার নিচে নম্বর আর icon দেওয়া ৮টা লিংক, যেগুলো জায়গা না পেলে পরের সারিতে যায়।
- **সংখ্যার tile:** ৪টা করে দুই সারি; প্রতিটায় রঙিন icon, লেবেল আর সংখ্যা।
- **নিচে দুটো কার্ড পাশাপাশি:**
  - **Latest requisitions:**
    - কলাম PR No. (লিংক), Type (chip), Order, Lines, Date, Status (chip)।
    - ডানে "New requisition" বাটন।
  - **Deliveries being followed up:**
    - কলাম Material, Supplier, Price, Balance, Status।
    - ডানে "Delivery tracking" বাটন।
- **লিংক:** সব লিংক পাতার ভেতরেই চলে (Next Link), পুরো পাতা রিলোড হয় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Http/Controllers/Api/ProcurementDashboardApiController.php`, `Routes/api.php` |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.DASHBOARD` ফোল্ডার, `MATERIAL.PROCUREMENT_DASHBOARD`) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/dashboard.js`, `page.js`, `frontend/src/components/materialprocurement/dashboard.jsx` |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`dash`) |
| টেস্ট | `tests/Feature/MaterialProcurement/ProcurementDashboardTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **Material Procurement (08)-এর সব স্ক্রিন শেষ।**

## ৯. টেস্ট ও যাচাই

- **`ProcurementDashboardTest`:** ১টা টেস্ট, ২৫টা assertion।
  - **ডাটা:**
    - দুই সরবরাহকারী, যার একটার লগইন আছে।
    - তিনটা item, তার একটা নিষ্ক্রিয়।
    - তিনটা requisition: একটা খসড়া, একটা অনুমোদিত আর আংশিক পাওয়া, একটা খোলা যার দুই লাইনে একটা Pending আর একটা Negotiation কোটেশন।
  - **ফল:**
    - আটটা সংখ্যাই মেলে: 1, 2, 2, 3, 1, 1, 1, 1।
    - সক্রিয় item ২, সক্রিয় সরবরাহকারী ২, লগইন আছে ১জনের।
    - সর্বশেষ requisition-এর লাইন আর অবস্থা ঠিক।
    - delivery-তে Balance ৬০ KG, দাম USD 2.65।
  - **In-House হলে:** done ১, In transit ০, balance ০।
  - **অনুমতি:** storekeeper খুলতে পারেন; সরবরাহকারীর লগইন 403 পায়।
- **রিগ্রেশন:** MaterialProcurement (সব), ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite, Auth, আর Merchandise-এর PurchasedFabricMrr, AccReceipt ও FabricReturn মিলিয়ে ১৮১টা টেস্ট সবুজ, ১১৯০টা assertion (২০২৬-১০-২৩, একা চালানো, ৫৫ মিনিট)।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner):
  - **সাইডবার:** Material Procurement-এর প্রথমে DASHBOARD → Procurement Dashboard, হেডারে "01 of 15" ✔
  - **সংখ্যা:** dev DB-র ডাটা দিয়ে আটটাই ঠিক: Draft 1, Submitted 2, Open lines 1, Quotations 2, Awaiting review 1, Negotiation 0, Approved 1, In transit 1 ✔
  - **সাবটাইটেল:** "22 active items in master data · 3 of 9 active suppliers have portal logins" ✔
  - **Deliveries:** Pique 220 GSM, YKK, USD 4.10, 150 KG, In Transit ✔
  - **লিংক:** "Super Admin Review" চাপলে Price Review খোলে ✔ "PR-2026-00002" চাপলে My Requisitions-এ সেটা খোলা অবস্থায় আসে ✔
  - কোনো console error নেই।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/mp37.json`, শুধু পড়া; ধাপের প্রতিটা লিংকের গন্তব্য Brickland থেকে মেলানো।
- **Brickland-এর ভিত্তি:** Brickland-এর "Submitted requisitions 6" ছিল খসড়া ছাড়া সব requisition-এর যোগফল (Submitted ১ আর Approved ৫), আর "In transit / receiving 1" ছিল শুধু Partially Received লাইনটা। এখানে হিসাব সেভাবেই করা হয়েছে।
- **লিংকের ঠিকানা:** Brickland-এ PR-এর লিংক `?pr=6` দিয়ে খোলে। এখানে `?open=PR-2026-00006`, যাতে URL-এ id না থাকে।
