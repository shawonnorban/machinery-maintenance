# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন (Merchandise Desk-এর বায়ার-মাস্টারের সাথে তৈরি)
> **মডিউল:** [Prototype Desk](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `07/01`

**⬅ ফিরে যান:** [Prototype Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sample Type Configuration | `/m/sample/sample-type-configuration` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sample Type Configuration

`/m/sample/sample-type-configuration`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Sample Stage | select |  | All, Pre-Order, Order / PO, Pre-Production, Bulk Production, Post-Production |
| Rules | select |  | All, Configured, Default |
| Active | select |  | All, Active, Inactive |
| Search Sample types with the stage, requirements and lead days the Prototype Des | search |  | Search 11 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Sample Type, Sample Stage, Order Required, PO Required, Buyer Approval Required, QC Required, Material Required, Revision Allowed, Delivery Required, Lead Days, Rules, Status, ACTION
- বাটন/অ্যাকশন: Clear, Export, Code, Sample Type, Sample Stage, Order Required, PO Required, Buyer Approval Required, QC Required, Material Required, Revision Allowed, Delivery Required, Lead Days, Rules, Status, Edit
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

_বাস্তবায়নের আগে লেখা হবে।_

## ৪. ডাটা মডেল (Entity ও Field)

_বাস্তবায়নের আগে লেখা হবে।_

## ৫. Workflow / Status ও Business Rules

_বাস্তবায়নের আগে লেখা হবে।_

## ৬. API ও পারমিশন

_বাস্তবায়নের আগে লেখা হবে।_

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Merchandise/Models/SampleType.php`
- `app/Modules/Merchandise/Resources/SampleTypeResource.php`
- `app/Modules/Merchandise/Support/SampleTypeDefaults.php`
- `app/Modules/Merchandise/Database/Migrations/2026_10_27_000100_create_merchandise_buyer_masters.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/app/(app)/prototype-desk/[screen]/page.js`
- `frontend/src/app/(app)/merchandise-desk/[screen]/screens.js`

**Tests**
- `tests/Feature/Merchandise/MerchandiseBuyerTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

_বাস্তবায়নের আগে লেখা হবে।_

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **কলাম:** `sample_types`: code, name, stage (PRE_ORDER/ORDER/PRE_PRODUCTION/BULK/POST_PRODUCTION), সাতটা হ্যাঁ/না শর্ত (order, PO, buyer approval, QC, material, revision, delivery), lead_days, customised, active।
- **ডিফল্ট:** Brickland-এর মতো ১১টা type। শর্তগুলো pre-order, order বা PO লাগে না, বাকি সব লাগে, ৭ দিন lead।
  - কোম্পানি প্রথমবার খুললে তৈরি হয়। সম্পাদনা করলে "Configured"।
  - কোনো বায়ারের প্রয়োজনীয় type মোছা যায় না।
- **পারমিশন:** Merchandise-এর `merchandise.master.view/manage`।
