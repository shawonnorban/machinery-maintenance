# Approved Material & Follow-Up

> **স্ট্যাটাস:** ✅ সম্পন্ন (Assigned Materials, Delivery Tracking চালু)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/06`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Assigned Materials | `/m/material-procurement/assigned-materials` | 2 | 0 | 0 |
| 2 | Delivery Tracking | `/m/material-procurement/delivery-tracking` | 1 | 1 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Assigned Materials

`/m/material-procurement/assigned-materials`

**ট্যাব: Assigned to me**
> 11 of 15
- গ্রিড কলাম: MATERIAL, SUPPLIER, PRICE, REQUIRED, RECEIVED, BALANCE, EXPECTED, STATUS
- সেকশন: Materials assigned to me
- বাটন/অ্যাকশন: Assigned to me, All (monitor)

**ট্যাব: All (monitor)**
> 11 of 15
- গ্রিড কলাম: MATERIAL, SUPPLIER, PRICE, REQUIRED, RECEIVED, BALANCE, EXPECTED, STATUS
- সেকশন: All approved materials
- বাটন/অ্যাকশন: Assigned to me, All (monitor)

#### Delivery Tracking

`/m/material-procurement/delivery-tracking`

**ট্যাব: All 3**
> 12 of 15

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | PR, order, item, supplier… |
- গ্রিড কলাম: MATERIAL, SUPPLIER, PRICE, REQUIRED, RECEIVED, BALANCE, EXPECTED, STATUS, MERCHANDISERS, NEXT FOLLOW-UP
- সেকশন: Delivery tracking
- বাটন/অ্যাকশন: All 3, Before dispatch 0, Dispatched / in transit 0, Partially received 0, Received / in-house 3
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08/05 Price Review:** অনুমোদিত লাইন (`approved_quotation_id`, `approved_supplier_id`, `followup_user_id`), অনুমোদনকারী আর সময়।
- **08/04 Supplier Portal:** কোটেশনের সর্বশেষ revision থেকে দাম, কারেন্সি, lead time আর expected delivery।
- **Merchandise Desk:** Purchased Fabric MRR (MRR-IMF) আর Accessories Receipt (MRR-ACC)। Raise GRN এগুলোতেই খসড়া বানায়, নতুন GRN টেবিল বানানো হয়নি (ব্যবহারকারীর সিদ্ধান্ত: "Merchandise-এর MRR-এ")।

## ৪. ডাটা মডেল (Entity ও Field)

- **মাইগ্রেশন `2026_12_18_000100_create_mp_followups`:** `mp_followups` টেবিল।
  - **ঘর:** requisition_line_id, followup_date, contact_person, status (delivery-র ধাপ), received_qty (এই delivery-তে কত এল), expected_delivery, actual_delivery, supplier_response, remarks, next_followup_date, created_by।
  - **GRN-এর ঘর:** grn_type (FABRIC/TRIMS), grn_id, grn_no।
- **মাইগ্রেশন `2026_12_18_000200_allow_procurement_receipts_in_mrr`** (Merchandise):
  - `merch_pfab_mrr_lines.po_line_id` এখন nullable, আর নতুন ঘর `mp_requisition_line_id`।
  - `merch_acc_mrr_lines`-এ নতুন ঘর `mp_requisition_line_id`।
- **লাইনের ধাপ (৯টা):** Approved → Supplier Confirmed → Production / Preparation → Ready for Dispatch → Dispatched → In Transit → Partially Received → Fully Received → In-House।
- **হিসাব:** Received = সব follow-up-এর received_qty-র যোগফল; Balance = Required − Received। এগুলো জমা রাখা হয় না, প্রতিবার হিসাব হয়।

## ৫. Workflow / Status ও Business Rules

- **Assigned Materials:**
  - "Assigned to me"-তে যে লাইনগুলো Price Review-এ এই ব্যবহারকারীকে হস্তান্তর হয়েছে। "All (monitor)" ট্যাব শুধু monitor অনুমতি থাকলে দেখায়; সেখানে অন্যের লাইন শুধু দেখা যায় ("Monitoring (read-only)" ব্যাজ)।
  - সারি খুললে পাতা রিলোড ছাড়াই নিচে বিবরণ আসে:
    - Required, Received আর Balance-এর ঘর।
    - ৯ ধাপের strip।
    - পাঁচটা অংশ: Order, Requisition, Approval, Supplier আর Material information।
    - Follow-up-এর টেবিল।
- **Add follow-up** (শুধু যাঁকে হস্তান্তর হয়েছে):
  - **বাধ্যতামূলক:** তারিখ আর অবস্থা।
  - **পাওয়া পরিমাণ:** বাকির বেশি হলে আটকায় ("… exceeds the balance of 180 KG")।
  - **লাইনের অবস্থা:** follow-up-এর অবস্থাই লাইনের অবস্থা হয়ে যায়।
  - **Material Received notification:** পরিমাণ পাওয়া গেলে requisition যিনি তুলেছেন আর দাম যিনি অনুমোদন করেছেন, দুজনেই জানতে পারেন, যেমন "Cotton Jersey on PR-…: 40 of 100 KG received."। merchandiser নিজে এটা পান না। (Brickland-এ দেখা: "Denim on PR-2026-00005: 500 of 2324 roll received.")
  - **In-House হলে:** লাইন বন্ধ ("In-house — closed"), আর follow-up নেওয়া হয় না।
  - **অন্যের লাইন:** follow-up আটকায় ("This material is not assigned to you.")।
- **Raise GRN** (যে follow-up-এ পরিমাণ পাওয়া গেছে, আর GRN এখনো হয়নি):
  - **যা বাছতে হয়:** Company আর Receiving Store বাধ্যতামূলক; Supplier Challan No. ঐচ্ছিক।
  - **FABRIC:** খসড়া Purchased Fabric MRR (MRR-IMF)। লাইনে PO থাকে না; description, GSM আর UOM আসে material থেকে, rate অনুমোদিত দাম থেকে।
  - **TRIMS:** খসড়া Accessories Receipt (MRR-ACC, ধরন PO); procurement-এর item দিয়ে।
  - **হেডার:** Payment LC, Source Local, কারেন্সি কোটেশনের, Remarks "From Material Procurement PR-… / QT-…"।
  - **কারেন্সি:** Merchandise-এর তালিকায় না থাকলে আটকায়।
  - **ফলাফল:** follow-up-এ GRN নং বসে। একই follow-up-এ দ্বিতীয়বার আটকায়, আর পরিমাণ ছাড়া follow-up-এও আটকায়।
  - **এরপরের কাজ:** খসড়াটা Merchandise Desk-এর Fabric MRR বা Accessories Receipt স্ক্রিনে যাচাই, সেভ আর Finalize করা হয়; stock আর ledger সেখানেই হয়। সারিতে লেখা থাকে "From Material Procurement"।
- **Delivery Tracking** (monitor):
  - **তালিকা:** সব অনুমোদিত লাইন।
  - **Pill:** All, Before dispatch, Dispatched / in transit, Partially received, Received / in-house, প্রতিটার সাথে সংখ্যা।
  - **খোঁজা:** PR, order, item, supplier আর merchandiser দিয়ে।
  - **কলাম:** Merchandisers (Req আর Follow-up)। Next follow-up-এর তারিখ পেরিয়ে গেলে লাল দেখায়।

## ৬. API ও পারমিশন

- **`material.followup.view`:** Assigned Materials, follow-up আর GRN। পায় FACTORY_ADMIN, COMPANY_OWNER, STORE_MANAGER আর FACTORY_MANAGER।
- **`material.followup.monitor`:** All (monitor) আর Delivery Tracking। পায় FACTORY_ADMIN, COMPANY_OWNER আর FACTORY_MANAGER।
- Price Review-এর merchandiser তালিকা এখন সেই সক্রিয় কর্মীরা, যাঁদের role-এ `material.followup.view` আছে। সরবরাহকারীর লগইন কখনো এতে আসে না।

| Method | Path |
|---|---|
| GET | `/material-procurement/assigned-materials` (`?scope=all` হলে monitor-এর তালিকা; সাথে can_monitor) |
| GET | `/material-procurement/assigned-materials/grn-options` (company, stores) |
| GET | `/material-procurement/assigned-materials/{line}` (পুরো বিবরণ আর follow-up) |
| POST | `/material-procurement/assigned-materials/{line}/followups` |
| POST | `/material-procurement/followups/{followup}/grn` (unit_company_id, store_id, challan_no) |
| GET | `/material-procurement/delivery-tracking` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → **APPROVED MATERIAL & FOLLOW-UP** → Assigned Materials আর Delivery Tracking।

- **Assigned Materials:**
  - **উপরের কার্ড:** "Materials assigned to me"; ডানে "Assigned to me" আর "All (monitor)" ট্যাব।
  - **টেবিল:** Material (PR আর order নং সহ), Supplier, Price, Required, Received (সবুজ বার সহ), Balance, Expected, Status।
  - **সারি খুললে:** উপরে তিনটা পরিমাণের ঘর আর ৯ ধাপের strip, তার নিচে চারটা তথ্যের কার্ড আর Material information।
  - **Follow-up entries:** "Add follow-up" বাটন; টেবিলের কলাম Date, Contact, Supplier Response, Status, Expected, Actual, Received, Next Follow-up, GRN, By। GRN কলামে "Raise GRN" বাটন অথবা GRN নং দেখায়।
  - **Dialog:** "Add follow-up" আর "Raise GRN"।
- **Delivery Tracking:** pill-এর সারি আর খোঁজার ঘর; তালিকায় দুটো বাড়তি কলাম, Merchandisers আর Next follow-up।
- **Merchandise-এর Fabric MRR entry:** PO ছাড়া লাইনে "From Material Procurement" লেবেল থাকে। সেভ করলে requisition লাইন, description, UOM আর GSM অক্ষত থাকে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_18_000100_create_mp_followups.php`, `Models/Followup.php`, `Http/Controllers/Api/AssignedMaterialApiController.php`, `Routes/api.php`, `Http/Controllers/Api/PriceReviewApiController.php` (merchandiser তালিকা অনুমতি দিয়ে) |
| Merchandise | `app/Modules/Merchandise/Database/Migrations/2026_12_18_000200_allow_procurement_receipts_in_mrr.php`, `Http/Controllers/Api/PurchasedFabricMrrApiController.php` (PO ছাড়া লাইন, `nextNumber` public, material থেকে GSM), `Http/Controllers/Api/AccReceiptApiController.php`, `Models/PurchasedFabricMrrLine.php`, `Models/AccReceiptLine.php` |
| Notification | `app/Modules/Notification/Models/Notification.php` (`MP_MATERIAL_RECEIVED`), `lang/{en,bn}/notification.php` |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`material.followup.view`, `material.followup.monitor`), `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.APPROVED_MATERIAL`, `MATERIAL.ASSIGNED_MATERIALS`, `MATERIAL.DELIVERY_TRACKING`) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/followup.js`, `page.js`, `actions.js` (`followupAction`), `frontend/src/components/materialprocurement/followup.jsx`, `frontend/src/components/merchandise/pfab-mrr-entry.jsx` |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`fu`) |
| টেস্ট | `tests/Feature/MaterialProcurement/ApprovedMaterialFollowUpTest.php`, `tests/Feature/Merchandise/MerchandiseAccReceiptTest.php` (UOM-এর প্রত্যাশা `PCS`) |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ:**
  - ~~08/07 Control-এর Notifications আর Audit Trail~~: হয়ে গেছে ([Control](07_Control.md))। follow-up আর Raise GRN এখন audit trail-এ লেখা হয়। "New Supplier Quotation Received" আর "Supplier Submitted a Revised Quotation" notification-ও সেখানেই যোগ হয়েছে।
  - 08/02 Procurement Dashboard।

## ৯. টেস্ট ও যাচাই

- **`ApprovedMaterialFollowUpTest`:** ২টা টেস্ট।
  - **তালিকা:** merchandiser নিজের লাইন দেখেন। monitor ছাড়া কেউ `?scope=all` চাইলে 403 পান। Delivery Tracking-এর অনুমতি আছে।
  - **বিবরণ:** order, requisition, approval, supplier আর material (যেমন `160 / 60"`)।
  - **Follow-up:**
    - তারিখ বা অবস্থা ছাড়া আটকায়; বাকির বেশি পরিমাণ আটকায়; অন্যের লাইনে আটকায়।
    - লাইনের অবস্থা বদলায়; Received আর Balance হিসাব হয়।
  - **Raise GRN, FABRIC:**
    - খসড়া MRR-IMF তৈরি হয় (DRAFT, LC, LOCAL, USD, remarks)।
    - দ্বিতীয়বার Raise আটকায়; পরিমাণ ছাড়া follow-up-এও আটকায়।
    - Merchandise খসড়াটা খুলে সেভ করে। PO ছাড়া লাইন, accept_qty আর GSM `160` টিকে থাকে।
  - **Raise GRN, TRIMS:** খসড়া MRR-ACC তৈরি হয়; লাইনে requisition লাইন, ৫০০, ০.০৮ আর PCS।
  - **In-House:** লাইন বন্ধ হয়, আর follow-up আটকায়।
  - **Material Received:** owner (requisition-এর লেখক আর অনুমোদনকারী) একবারই পান "… 40 of 100 KG received."; merchandiser পান না।
- **Merchandise-এর পুরনো টেস্ট:** MerchandisePurchasedFabricMrrTest আর MerchandiseAccReceiptTest সবুজ। দুটো ফাইল এক রানে মিলিয়ে ৪টা টেস্ট, ২০৫টা assertion।
- **রিগ্রেশন:** MaterialProcurement (সব), ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite, Auth, আর Merchandise-এর PurchasedFabricMrr, AccReceipt ও FabricReturn মিলিয়ে ১৭৮টা টেস্ট সবুজ, ১১১৯টা assertion (২০২৬-১০-২৩, একা চালানো, ৪৭ মিনিট)।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner Rahim Uddin):
  - **Assigned Materials:** PR-2026-00002-এর Pique 220 GSM খুললে সব অংশ ঠিক দেখায়: 300 KG, USD 4.10, QT-2026-00001, YKK, LPP-2026-000001 ✔
  - **Add follow-up:** Mr. Karim, Partially Received, ১২০ KG। Received ১২০, Balance ১৮০, strip-এ ৭ ধাপ সবুজ ✔
  - **Raise GRN:** Fabric store আর CH-7781 দিয়ে খসড়া MRR-IMF-2026100002 তৈরি হলো। follow-up-এর GRN কলামে নং দেখায় ✔
  - **Delivery Tracking:** Partially received ১; Req আর Follow-up দেখায় ✔
  - **Merchandise Fabric MRR:** খসড়াটা তালিকায় আছে। entry খুললে লাইনে "From Material Procurement", ১২০ KG, 4.1 আর 492.00 USD।
    - **ধরা পড়া bug:** সেভ করলে GSM হারিয়ে যাচ্ছিল। ঠিক করার পরে batch B-PQ-01 দিয়ে আবার সেভ করলে GSM 220 থাকে ✔
  - কোনো পাতায় console error নেই। খসড়া MRR আর follow-up dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **Follow-up merchandiser-এর তালিকা:** এখন অনুমতি-ভিত্তিক (`material.followup.view`), Brickland-এর "Merchandising Desk" role-এর মতো। ব্যবহারকারী পরে চাইলে আলাদা merchandiser role বা বিভাগ দিয়ে সীমিত করা যাবে।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/mp23.json` থেকে `mp35.json`।
  - Brickland-এ পরীক্ষার merchandiser annotech.merch বানানো হয়েছে, আর QT-2026-00005 (PR-00005 Denim) তাঁকে হস্তান্তর করা হয়েছে।
  - দুটো follow-up দেওয়া হয়েছে, আর Raise GRN থেকে খসড়া MRR-IMF-2026100001 তৈরি হয়েছে।
- **GRN-এর ঠিকানা:** আলাদা GRN টেবিল বানানো হয়নি। Brickland-এর মতোই Merchandise Desk-এর MRR-এ খসড়া যায়, আর stock সেখানে Finalize করলে তবেই হয়।
