# Price Review & Approval

> **স্ট্যাটাস:** ✅ সম্পন্ন (Price Review & Approval চালু)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/05`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Price Review & Approval | `/m/material-procurement/price-review` | 1 | 1 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Price Review & Approval

`/m/material-procurement/price-review`

**ট্যাব: Pending 0**
> 10 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | PR, order, item, supplier… |
- সেকশন: Received quotations
- বাটন/অ্যাকশন: Pending 0, Negotiation 0, Awaiting quotes 2, Approved 3, Rejected 0, All lines 5
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08/04 Supplier Portal:** কোটেশন আর তার revision (`mp_quotations`, `mp_quotation_revisions`)। প্রতিটা কোটেশনের সর্বশেষ revision-ই এখানে তুলনা হয়।
- **08/03 Purchase Requisition:** লাইন আর requisition-এর অবস্থা; My Requisitions-এ অনুমোদিত সরবরাহকারী।
- **08/07 Supplier Portal Access:** কোন সরবরাহকারীর লগইন notification পাবে; SUPPLIER role (merchandiser তালিকা থেকে বাদ পড়ে)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_17_000200_add_price_review_decisions`:
- **`mp_quotations`-এ নতুন:** negotiation_message, negotiated_at, reject_reason, decided_by, decided_at।
- **`mp_requisition_lines`-এ নতুন:** approved_quotation_id, followup_user_id (follow-up merchandiser), decided_by, decided_at, reject_reason।
- **লাইনের অবস্থা:** OPEN_FOR_QUOTATION → APPROVED অথবা REJECTED। delivery-র ধাপগুলো 08/06-এ যোগ হবে।

## ৫. Workflow / Status ও Business Rules

- **তালিকা:** খসড়া ছাড়া সব requisition-এর লাইন।
  - **ট্যাব:**
    - Pending: খোলা লাইন, যাতে Pending বা Revised কোটেশন আছে।
    - Negotiation: খোলা লাইন, যাতে Negotiation-এ থাকা কোটেশন আছে।
    - Awaiting quotes: খোলা লাইন, কোনো কোটেশন নেই।
    - Approved, Rejected আর All lines।
  - **সংখ্যা:** Pending quotations, Negotiation, Approved (লাইন), Rejected (লাইন)।
- **Negotiate** (Pending বা Revised কোটেশনে):
  - বার্তা বাধ্যতামূলক। ডিফল্ট লেখা: "Please revise your quotation and submit your best possible price."
  - কোটেশন NEGOTIATION হয়। সরবরাহকারী দেখেন "Negotiation Requested", বার্তা আর "Send revised price" বাটন, সাথে notification "Price Revision Requested"।
  - নতুন দাম দিলে কোটেশন Revised হয়, আগের revision থেকে যায়।
- **Reject** (Pending, Revised বা Negotiation): কারণ বাধ্যতামূলক। সরবরাহকারী কারণসহ notification পান। প্রত্যাখ্যাত কোটেশনে আর revise চলে না।
- **Approve & Handover:**
  - follow-up merchandiser বাধ্যতামূলক। তালিকায় থাকেন কোম্পানির সক্রিয় কর্মী, সরবরাহকারীর লগইন বাদে।
  - কোটেশন APPROVED হয়; একই লাইনের অন্য খোলা কোটেশন CLOSED।
  - লাইনে বসে APPROVED, অনুমোদিত সরবরাহকারী, অনুমোদিত কোটেশন আর merchandiser।
  - সরবরাহকারী notification পান "Quotation Approved … at USD 2.65"; merchandiser পান "Approved Material Assigned to You"।
- **Reject line:** কারণ বাধ্যতামূলক। কোনো সরবরাহকারী বাছা হয় না; লাইনের সব খোলা কোটেশন প্রত্যাখ্যাত হয় আর সরবরাহকারীরা notification পান।
- **requisition-এর অবস্থা:** সব লাইনের সিদ্ধান্ত হয়ে গেলে requisition Approved হয় (অন্তত একটা লাইন অনুমোদিত হলে), নইলে Rejected।
- **সিদ্ধান্ত হওয়া লাইন:** আর বদলানো যায় না ("This line is already decided."), আর সরবরাহকারী তাতে কোটও দিতে পারেন না।

## ৬. API ও পারমিশন

পারমিশন: `material.price.approve` (FACTORY_ADMIN, COMPANY_OWNER)। সরবরাহকারী আর অন্য কর্মী 403 পান।

| Method | Path |
|---|---|
| GET | `/material-procurement/price-review` (counts, lines, প্রতিটার কোটেশন, merchandisers) |
| POST | `/material-procurement/price-review/quotations/{id}/negotiate` (message) |
| POST | `/material-procurement/price-review/quotations/{id}/approve` (handover_to) |
| POST | `/material-procurement/price-review/quotations/{id}/reject` (reason) |
| POST | `/material-procurement/price-review/lines/{id}/reject` (reason) |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → **PRICE REVIEW & APPROVAL**।

- **সংখ্যার ট্যাইল:** উপরে চারটা।
- **"Received quotations" কার্ড:** তালার icon আর গোপনীয়তার নোট; ছয়টা pill, সংখ্যাসহ; খোঁজার ঘর "PR, order, item, supplier…"।
- **প্রতিটা লাইন:**
  - **উপরে:** PR নং, By Order/Direct, item আর বিবরণ, লাইনের অবস্থা, আর Reject line (খোলা থাকলে)।
  - **তার নিচে:** "Required 300 KG by 25 Nov 2026 · Requested by … · Order … (buyer, style …) · Follow-up: …"।
  - **কোটেশনের টেবিল:** Supplier (নাম, QT নং আর কোড; negotiation-এর বার্তা বা প্রত্যাখ্যানের কারণ), Unit Price, Quoted Qty, Lead Time, Delivery, Revisions, Status, Actions (Approve & Handover, Negotiate, Reject)।
- **Dialog:** চারটা, Brickland-এর লেখায়। Approve-এর dialog-এ কোটেশনের নয়টা তথ্য দেখায় আর merchandiser বাছতে হয়। সিদ্ধান্তের পর পাতা রিলোড ছাড়াই তালিকা হালনাগাদ হয়।
- **My Requisitions:** লাইনে অনুমোদিত সরবরাহকারীর নিচে "USD 2.65 · Follow-up: …"; লাইনের অবস্থা Approved (সবুজ) বা Rejected (লাল)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_17_000200_add_price_review_decisions.php`, `Http/Controllers/Api/PriceReviewApiController.php`, `Models/Quotation.php`, `Models/PurchaseRequisitionLine.php`, `Routes/api.php`, `Http/Controllers/Api/SupplierPortalApiController.php` (negotiation-এর বার্তা, প্রত্যাখ্যানের কারণ), `Http/Controllers/Api/PurchaseRequisitionApiController.php` (অনুমোদিত দাম, follow-up) |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`material.price.approve`), `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.PRICE_REVIEW`) |
| Notification | `app/Modules/Notification/Models/Notification.php` (`MP_QUOTATION_NEGOTIATION`, `MP_QUOTATION_REJECTED`, `MP_QUOTATION_APPROVED`, `MP_MATERIAL_ASSIGNED`), `lang/{en,bn}/notification.php` |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/review.js`, `page.js`, `actions.js` (`reviewAction`), `frontend/src/components/materialprocurement/price-review.jsx`, `supplier-portal.jsx` (Send revised price), `requisition.jsx` (অনুমোদিত দাম) |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`review`, আর `pr`/`sp`-এ নতুন key) |
| টেস্ট | `tests/Feature/MaterialProcurement/PriceReviewTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ (08/06 Approved Material & Follow-Up):**
  - **Assigned Materials:** merchandiser-এর নিজের তালিকা আর "All (monitor)"। সারি খুললে order, requisition, approval, supplier আর item-এর বিবরণ; follow-up লেখা (তারিখ, যোগাযোগ, সরবরাহকারীর উত্তর, পাওয়া পরিমাণ, পরের follow-up); GRN।
  - **Delivery Tracking:** delivery-র ধাপ (Supplier Confirmed থেকে In-House), Required, Received আর Balance।

## ৯. টেস্ট ও যাচাই

- **`PriceReviewTest`:** ২টা টেস্ট, ৬৫টা assertion।
  - **তালিকা:** দুই সরবরাহকারীর কোটেশন; সংখ্যা; merchandiser-এর তালিকায় সরবরাহকারীর লগইন নেই।
  - **Negotiate:** বার্তা ছাড়া আটকায়; NEGOTIATION; দ্বিতীয়বার আটকায়; notification-এর লেখা; সরবরাহকারীর History-তে বার্তা; নতুন দামে Revised।
  - **Approve:**
    - merchandiser ছাড়া বা সরবরাহকারীর লগইন বাছলে আটকায়।
    - কোটেশন APPROVED আর অন্যটা CLOSED; লাইন বন্ধ।
    - requisition Approved, লাইনে "USD 2.65" আর follow-up।
    - সরবরাহকারী আর merchandiser-এর notification।
    - সিদ্ধান্ত হওয়া লাইনে আর কিছু চলে না; সরবরাহকারী আর কোট দিতে পারেন না।
    - Storekeeper আর সরবরাহকারী 403 পান।
  - **Reject আর Reject line:**
    - কারণ ছাড়া আটকায়; প্রত্যাখ্যানের notification; প্রত্যাখ্যাত কোটেশনে revise আটকায়।
    - একটা লাইন প্রত্যাখ্যাত হলে requisition তখনো Submitted থাকে; দুটো লাইনই প্রত্যাখ্যাত হলে Rejected হয়; কারণ দেখায়।
- **রিগ্রেশন:** MaterialProcurement (সব), ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite আর Auth মিলিয়ে ১৭৩টা টেস্ট সবুজ, ৮৬০টা assertion (২০২৬-১০-২৩, একা চালানো)।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner আর YKK সরবরাহকারী):
  - **Approve & Handover:**
    - PR-2026-00002-এর Pique লাইনে YKK-এর QT-2026-00001।
    - merchandiser ছাড়া চাপলে বার্তা আসে।
    - Rahim Uddin বাছার পর লাইন Approved, "Follow-up: Rahim Uddin" ✔
  - **Reject line:** Main Label লাইনে "Main label comes from the buyer's nominated source" → Rejected; PR-2026-00002 Approved ✔
  - **Negotiation:**
    - owner Direct requisition জমা দিলেন → PR-2026-00003 (1x1 Rib 220 GSM, ৮০ KG)।
    - YKK USD 3.20 কোট দিল।
    - owner Negotiate পাঠালেন → Negotiation ট্যাবে ১।
    - YKK-এর History-তে "Negotiation Requested", বার্তা আর "Send revised price"; USD 3.05 পাঠানো হলো।
    - owner-এর পর্দায় Revised, ২টা revision ✔
  - কোনো পাতায় console error নেই। PR-2026-00003 খোলা রাখা হয়েছে; সব রেকর্ড dev DB-তে আছে।

## ১০. খোলা প্রশ্ন

- **Follow-up merchandiser-এর তালিকা:** Brickland-এ শুধু merchandiser-দের নাম দেখায়। এখানে আলাদা merchandiser role নেই, তাই কোম্পানির সক্রিয় সব কর্মী থাকেন (সরবরাহকারীর লগইন বাদে)। ব্যবহারকারী চাইলে পরে বিভাগ বা role দিয়ে সীমিত করা যাবে।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/mp18.json` থেকে `mp22.json`।
  - Brickland-এ QT-2026-00004 দিয়ে পুরো প্রবাহ চালানো হয়েছে: Negotiate → সরবরাহকারীর revision ৩ (USD 2.65) → Approve & Handover (Merchandising Desk)।
  - শেষে Brickland-এর PR-2026-00006 Approved হয়েছে।
- **রিগ্রেশনের শিক্ষা:** রিগ্রেশন চলার সময় নতুন controller লেখায় NotificationRoutingTest ব্যর্থ হয়েছিল। টেস্টটা source কোড পড়ে, তাই নতুন notification-এর ধরন তালিকায় যোগ না হওয়া পর্যন্ত ব্যর্থ দেখায়। তালিকায় যোগ করার পরে আবার পরিষ্কারভাবে চালানো হয়েছে।
