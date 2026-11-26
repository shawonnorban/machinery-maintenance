# Purchase Requisition

> **স্ট্যাটাস:** ✅ সম্পন্ন (২টা স্ক্রিনই চালু: Create Requisition, My Requisitions)
> **মডিউল:** [Material Procurement](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `08/03`

**⬅ ফিরে যান:** [Material Procurement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Create Requisition | `/m/material-procurement/create-requisition` | 1 | 0 | 1 |
| 2 | My Requisitions | `/m/material-procurement/my-requisitions` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Create Requisition

`/m/material-procurement/create-requisition`

**ভিউ**
> 05 of 15
- সেকশন: 1. Requisition by Order, 2. Direct Requisition
- বাটন/অ্যাকশন: Select Order & Create, Create Direct Requisition

**ডায়ালগ: Create Direct Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Required Qty* | number | ✔ |  |
| UOM* | text | ✔ |  |
| Requirement Date* | date | ✔ |  |
| Specification | text |  |  |
| Remarks | text |  |  |
| Requisition remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Add at least one material line.

#### My Requisitions

`/m/material-procurement/my-requisitions`

**ট্যাব: All 5**
> 06 of 15
- সেকশন: All requisitions
- বাটন/অ্যাকশন: All 5, Draft 0, Submitted 2, Rejected 0, Approved 3
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08/01 Master Data:** `mp_items` (FABRIC/TRIMS, category, colour, gsm, width, size, uom, specification) থেকে লাইনের Item; `mp_suppliers` থেকে পরে Approved Supplier।
- **Merchandise Desk:** confirmed অবস্থার অর্ডার (`merch_orders`): buyer (short name, contact person), style (style_ref), total_qty আর unit, ship_date, আর created_by (Original Merchandiser)।
- **ব্যবহারকারী:** নাম, `employee_code`, `phone`, `department_id`। Designation আসবে HR-এর কর্মী master থেকে, যা এখনো তৈরি হয়নি; ততদিন "—" দেখাবে।
- **UOM:** `units_of_measure` master থেকে ড্রপডাউন (`UomCodes`, `UomSelect`)। Brickland-এ ঘরটা লেখার ঘর, এখানে memory-র নিয়ম মেনে master থেকে বাছাই।

## ৪. ডাটা মডেল (Entity ও Field)

- **`mp_requisitions`:** pr_no (`PR-2026-00001`, কোম্পানি আর বছর ধরে ক্রমিক), req_type (BY_ORDER/DIRECT), req_date, order_id (By Order-এ), status (DRAFT/SUBMITTED/REJECTED/APPROVED), remarks, created_by, department_id (তোলার সময়ের বিভাগ), submitted_at।
- **`mp_requisition_lines`:** requisition_id, kind (FABRIC/TRIMS), item_id, req_qty, uom (master-এর code), req_date, specification, remarks, status (OPEN_FOR_QUOTATION; পরের ধাপে আরও), approved_supplier_id (Price Review-এ বসবে), sort_order।

## ৫. Workflow / Status ও Business Rules

- **দুই পথ:**
  - **Requisition by Order:** একটা confirmed অর্ডার বাছতে হয়। তার Buyer, Style No., Order Qty, Delivery Date, Original Merchandiser, Buyer Contact আর Order Status নিজে বসে, বদলানো যায় না।
  - **Direct Requisition:** অর্ডার লাগে না। কোনো অর্ডার পাঠানো হলেও রাখা হয় না।
- **লাইন:** Fabric আর Accessories দুই ট্যাব। Item বাছলে Category, Colour, GSM/Width (fabric) বা Size/UOM (accessories) দেখায়। UOM আর Specification item থেকে নিজে বসে, বদলানো যায়।
- **ভ্যালিডেশন (Brickland-এর লেখায়):**
  - অর্ডার না থাকলে বা confirmed না হলে: "Select the confirmed order this requisition is for."
  - লাইন না থাকলে: "Add at least one material line."
  - লাইন অনুযায়ী (নম্বর গোনা হয় প্রতিটা ট্যাবের ভেতরে):
    - "Line 1: Select an item." (অন্য ট্যাবের item দিলেও এই বার্তা আসে)
    - "Line 1: Required qty must be a number greater than zero."
    - "Line 1: UOM is required." / "Line 1: X is not a unit of measure."
    - "Line 1: Requirement date is required."
- **অবস্থা:**
  - **Save draft:** Draft হিসেবে থাকে।
  - **Submit requisition / Submit to all suppliers:** Submitted হয়, submitted_at বসে। Brickland-এর মতো কোনো নিশ্চিতকরণ চাওয়া হয় না।
  - **Draft-এর তিন বাটন:** Edit draft (`?draft=PR-…`), Submit to all suppliers, Delete draft। Delete-এ dialog আসে: "Delete PR-…? The draft and its lines are removed. Submitted requisitions can never be deleted."
  - **Submitted হলে:** আর বদলানো বা মোছা যায় না।
  - Approved আর Rejected বসবে Price Review (08/05)-এ। কোটেশনের সংখ্যা আসবে Supplier Portal (08/04) থেকে।
  - **Submit হলে notification:** প্রত্যেক সক্রিয় সরবরাহকারী লগইন পায় "New Material Requirement Available — PR-…: n material line(s) are open for quotation." (যোগ হয়েছে [Supplier Portal Access](07_Control.md)-এর সাথে)।
- **নম্বর:** শেষ নম্বরের draft মুছলে পরের requisition সেই নম্বরটাই পায়, কারণ ওই নম্বর কোথাও যায়নি।

## ৬. API ও পারমিশন

পারমিশন:
- `material.requisition.view`: FACTORY_ADMIN, STORE_MANAGER, FACTORY_MANAGER, STOREKEEPER।
- `material.requisition.manage`: FACTORY_ADMIN, STORE_MANAGER।

| Method | Path |
|---|---|
| GET | `/material-procurement/requisitions/options` (পরের নম্বর, confirmed অর্ডার, দুই ধরনের item, UOM, কে তুলছেন) |
| GET | `/material-procurement/requisitions`, `/material-procurement/requisitions/{PR-…}` |
| POST / PUT | `/material-procurement/requisitions[/{PR-…}]` (req_type, order_id, remarks, lines[], submit) |
| POST | `/material-procurement/requisitions/{PR-…}/submit` |
| DELETE | `/material-procurement/requisitions/{PR-…}` (শুধু Draft) |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Material Procurement → MASTER DATA → **PURCHASE REQUISITION** (২)।

- **Create Requisition:**
  - **প্রথম ধাপ:** দুটো কার্ড, "1. Requisition by Order" (Select Order & Create) আর "2. Direct Requisition" (Create Direct Requisition)।
  - **ফর্মের উপরের কার্ড:** শিরোনাম আর Change option। তথ্য: Requisition No. ("PR-2026-00002 (on save)"), Requisition Date, Created By, Employee ID, Department, Designation, Contact Number। By Order-এ Order No. বাছলে অর্ডারের সাতটা তথ্য দেখায়।
  - **Material Requirements:** Fabric / Accessories ট্যাব, সংখ্যাসহ। প্রতিটা লাইনের বাক্সে Line n আর মোছার বাটন; Item*, item-এর তথ্য, Required Qty*, UOM* (master), Requirement Date*, Specification, Remarks। নিচে Add fabric line / Add accessories line।
  - **শেষে:** Requisition remarks; নোট, Save draft আর Submit requisition। সেভ হলে My Requisitions খোলে, নতুন requisition খোলা অবস্থায় (`?open=PR-…`)।
- **My Requisitions:**
  - **উপরে:** "All requisitions" আর New requisition বোতাম; pill All / Draft / Submitted / Rejected / Approved, সংখ্যাসহ।
  - **প্রতিটা সারি:** PR নং, By Order/Direct, অর্ডার · বায়ার বা "No order", লাইন · তারিখ · কে তুলেছেন, আর অবস্থা।
  - **সারি খুললে:** পাতা রিলোড ছাড়া খোলে। ভেতরে Requisition Type, Created By, Department, Designation, Contact, Submitted, অর্ডারের তথ্য বা "Not applicable (direct requisition)"। লাইনের টেবিলে #, Item, Req. Qty, Req. Date, Quotations, Approved Supplier, Status। Draft হলে তিনটা বাটন।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/MaterialProcurement/Database/Migrations/2026_12_16_000100_create_mp_purchase_requisitions.php`, `Models/PurchaseRequisition.php`, `Models/PurchaseRequisitionLine.php`, `Support/Procurement.php` (নম্বর আর নাম), `Http/Controllers/Api/PurchaseRequisitionApiController.php`, `Routes/api.php` |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`material.requisition.view`, `material.requisition.manage`), `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`MATERIAL.PURCHASE_REQUISITION`, দুটো স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/material-procurement/[screen]/requisition.js`, `page.js`, `actions.js` (`requisitionAction`), `frontend/src/components/materialprocurement/requisition.jsx` |
| ভাষা | `lang/{en,bn}/materialprocurement.php` (`pr`) |
| টেস্ট | `tests/Feature/MaterialProcurement/PurchaseRequisitionTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ:** Supplier Portal Access (08/07-এর অংশ), তারপর Supplier Portal (08/04)। সরবরাহকারীর লগইন এলে Submitted লাইনগুলো তাঁদের Available Requirements-এ দেখাবে, আর কোটেশনের সংখ্যা এখানে বসবে।

## ৯. টেস্ট ও যাচাই

- **`PurchaseRequisitionTest`:** ২টা টেস্ট, ৬২টা assertion।
  - **Options:** পরের নম্বর; শুধু confirmed অর্ডার (Draft অর্ডার বাদ); অর্ডারের সাতটা তথ্য; item-এর লেবেল, UOM আর specification; কে তুলছেন।
  - **ভ্যালিডেশন:**
    - অর্ডার আর লাইন ছাড়া জমা দিলে দুটো বার্তা।
    - লাইনের বার্তা ট্যাব ধরে গোনা: qty ০, ভুল ট্যাবের item, অচেনা UOM, তারিখ ছাড়া।
  - **Draft থেকে Submit:**
    - Draft হলো (KG-তে স্বাভাবিক করা UOM, Open for Quotation, ০ কোটেশন)।
    - Edit করে accessories লাইন যোগ আর একই সেভে Submit।
    - এরপর বদলানো, আবার Submit আর মোছা আটকায়।
    - Cancelled অর্ডার আর বাছা যায় না।
  - **Direct:** অর্ডার উপেক্ষা হয়; Submit; Draft মোছা; মোছার পর 404; শেষ নম্বর আবার পাওয়া যায়; ধরন ছাড়া জমায় বার্তা।
  - **অনুমতি:** Storekeeper দেখেন কিন্তু তুলতে পারেন না; Technician দেখেন না।
- **রিগ্রেশন:** MaterialProcurement, ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration আর ErpMasterDataSeeder মিলিয়ে ৩৩টা টেস্ট সবুজ, ৩০২টা assertion।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩, owner লগইন):
  - সাইডবারে PURCHASE REQUISITION (২) ✔
  - **Direct:** FB-SJ-160, ১২০, KG নিজে বসেছে, ২০ Nov, মন্তব্য "Sample room top-up", Save draft → PR-2026-00001 (Draft); My Requisitions-এ খোলা অবস্থায় এসেছে ✔
  - **By Order:**
    - LPP-2026-000001 বাছতেই LPP, SS27-TEE01, 1,900 Pcs, 20 Dec 2026, Rahim Uddin, confirmed বসেছে ✔
    - Fabric FB-PIQ-220 ৩০০ KG; Accessories TR-LBL-MAIN ৫০০০ PCS; Submit requisition → PR-2026-00002 (Submitted), ২টা লাইন Open for Quotation ✔
  - **Draft-এর বাটন:**
    - Delete draft-এর dialog এসেছে (Cancel চাপা হয়েছে, ডাটা রাখা হয়েছে) ✔
    - Edit draft আগের item, UOM, তারিখ আর মন্তব্যসহ ফর্ম খুলেছে ✔
  - Console-এ কোনো error নেই। রেকর্ডগুলো dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- My Requisitions-এ এখন কোম্পানির সব requisition দেখায় (Brickland-এ admin হিসেবে তা-ই দেখা গেছে)। সাধারণ ব্যবহারকারী শুধু নিজেরগুলো দেখবেন কিনা, তা Brickland-এ অন্য ভূমিকার লগইন দিয়ে দেখা হয়নি।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `tools/erp_crawler/plans/mp1.json` থেকে `mp10.json`। mp6 থেকে mp10 লেখার অনুমতি নিয়ে চালানো হয়েছে।
- **Brickland-এ বানানো পরীক্ষার ডাটা:**
  - PR-2026-00006 (Direct, Cotton Jersey ১২০ Kg) তৈরি হয়ে submit হয়ে গেছে, কারণ "Submit to all suppliers" কোনো নিশ্চিতকরণ ছাড়াই সরাসরি জমা দেয়।
  - PR-2026-00007 (By Order) বানিয়ে আবার মুছে ফেলা হয়েছে।
- **প্রোব টুল:** browser-এর নিজস্ব confirm dialog ধরতে panel.mjs-এ বার্তা রেকর্ড করার ব্যবস্থা যোগ হয়েছে (`ACCEPT_DIALOGS=1` দিলে গ্রহণ করে)।
- **UOM:** Brickland-এ লেখার ঘর, এখানে master থেকে ড্রপডাউন। লেখা কোড ("kg") master-এর কোডে ("KG") বদলে যায়।
