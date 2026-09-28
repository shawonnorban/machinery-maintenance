# Shared Systems & Compliance

**পরিচিতি:** এই ফাইলে সেই ফিচারগুলো আছে যা একাধিক মডিউল জুড়ে ব্যবহার হয় — কোনো নির্দিষ্ট মডিউলে "বসবাস" করে না, বরং সবাই এটাকে সার্ভিস হিসেবে ব্যবহার করে।

**⬅ ফিরে যান:** [Main Index](00_Main_Index.md)

---

## 1. Role/Permission, Approval Engine, Notification, Audit Log — **বিদ্যমান প্ল্যাটফর্ম ক্যাপাবিলিটি (এই ডকুমেন্টেশনের স্কোপের বাইরে)**

> **প্রজেক্ট নোট:** এই প্রজেক্টে User/Role/Permission, Approval Workflow, Notification, এবং Audit Log ব্যবস্থা ইতিমধ্যে বর্তমান প্ল্যাটফর্মে ইমপ্লিমেন্ট করা আছে। তাই এই ডকুমেন্টেশন সেট নতুন করে এগুলোর ডিটেইল ডিজাইন (Role লিস্ট, Approval Matrix থ্রেশহোল্ড, Notification ইভেন্ট টেবিল ইত্যাদি) প্রস্তাব করে না — নিচে শুধু **কোন কোন জায়গায় এই বিদ্যমান ক্যাপাবিলিটিগুলো ব্যবহার হবে** তার একটা রেফারেন্স তালিকা দেওয়া হলো, যাতে ইমপ্লিমেন্টেশনের সময় কনফিগারেশন করা সহজ হয়।

### 1.1 Approval প্রযোজ্য এমন জায়গা (বিদ্যমান Approval Engine ব্যবহার করে)
- [Procurement](03_Procurement_Module.md): PR, PO, RFQ, Return/Claim, Fabric Booking Over-limit
- [Inventory](02_Inventory_Module.md): Roll Unreserve, BOM Variance Exceed, Split/Merge
- [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md): (ঐচ্ছিক) High-Value/নির্দিষ্ট Category Item-এর জন্য Approval — ডিফল্টভাবে Floor Requisition-এ কোনো Approval লাগে না

> **As built (Phase 1):** Approval Engine এখন generic — `App\Modules\Approval\Contracts\Approvable` ইন্টারফেস (`approvalContext()`, `approvalSummary()`, `onApprovalDecided()`) ও `ApprovalEntities` রেজিস্ট্রি; `ApprovalWorkflow::ENTITY_TYPES`-এ `PURCHASE_REQUISITION`, `PURCHASE_ORDER` (Settings → Approval workflows থেকে কনফিগার)। `RequestApproval::forEntity()` workflow/matching rule না পেলে ডকুমেন্টকে `NOT_REQUIRED` জানায় → ডকুমেন্ট নিজে `APPROVED`-এ যায়। `/approval-requests` লিস্টে generic `entity {type,id,number,title,url}` ব্লক। Decision-এর পর requester-কে `APPROVAL_DECIDED` notification।

### 1.2 Notification প্রযোজ্য এমন জায়গা (বিদ্যমান Notification ব্যবস্থা ব্যবহার করে)
- Reorder Level Alert — Inventory
- PR/PO Pending Approval — Procurement
- LC Expiry Alert — Procurement
- Fabric Booking Required Date Nearing — Procurement
- Subcontract Overdue Return — Inventory
- BOM Variance Exceeded — Inventory
- Floor Requisition Submitted (Urgent priority হলে তাৎক্ষণিক Push) — Floor/Store Requisition
- Floor Requisition Pending Item Arrived (Stock এসেছে, বাকি Qty ইস্যু করুন) — Floor/Store Requisition

### 1.3 Audit Log প্রযোজ্য এমন জায়গা (বিদ্যমান Audit Log ব্যবস্থা ব্যবহার করে)
সব Critical Transaction — Item Master Create/Edit, PR/PO Create/Approve, GRN Complete, Inspection Result, Roll Split/Merge/Return, Landed Cost Post, Floor Requisition Submit/Issue/Auto-PR Trigger — এই বিদ্যমান Audit Log ব্যবস্থায় স্বয়ংক্রিয়ভাবে রেকর্ড হবে (আলাদা কোনো নতুন Audit স্কিমা ডিজাইন করার দরকার নেই)।

### 1.4 Role Scoping নোট
Store এখন বিদ্যমান `Company → Factory → Warehouse → Store → Bin` হায়ারার্কির অংশ, real FK-সহ (দেখুন [Master Data Module §5](01_Master_Data_Module.md#5-store-existing-warehouse-hierarchy-reuse)), কিন্তু এখনো কোনো জটিল রিলেশনাল হায়ারার্কি বা নিজস্ব পারমিশন-স্কিমা নেই। তাই Role-এর জন্য store-scoped permission (যেমন "এই Store Keeper শুধু Store A দেখতে পারবে") প্রয়োজন হলে সেটা বিদ্যমান Role/Permission ব্যবস্থার মধ্যেই একটা সাধারণ attribute/filter (store_id-ভিত্তিক) হিসেবে কনফিগার করা যাবে — এই ডকুমেন্টেশনের স্কোপে নতুন ডিজাইন লাগবে না।

## 2. Accounting / GL Integration (High-Level)

| Event | GL Impact | মডিউল |
|---|---|---|
| GRN Complete (Provisional) | Stock A/C Dr, GRN Clearing A/C Cr | Procurement/Inventory |
| Landed Cost Post | Stock A/C Dr (adjustment), Expense/Payable A/C Cr | Procurement |
| Issue to Production | WIP/Production A/C Dr, Stock A/C Cr | Inventory |
| Purchase Return | Vendor Payable Dr, Stock A/C Cr | Procurement |
| Advance Payment | Advance to Supplier Dr, Bank/Cash Cr | Procurement |
| Invoice Settlement | Vendor Payable Dr, Advance Adjust + Bank Cr | Procurement |

> **সচেতন সম্প্রসারণ (Phase 6):** উপরের ছয়টির সাথে সপ্তম একটি ইভেন্ট যোগ হয়েছে — **Stock Adjustment** (Stock A/C Cr, Stock Variance A/C Dr; বেশি পাওয়া গেলে উল্টো)। ফিজিক্যাল কাউন্টই একমাত্র নিয়মিত পথ যেখানে কিছু না আসা-না যাওয়া সত্ত্বেও স্টকের মূল্য নড়ে ([Inventory §12.2](02_Inventory_Module.md#122-physical-inventory--যেভাবে-তৈরি-হয়েছে-phase-6)), আর সেটা বইয়ের বাইরে রাখলে লেজার ও তাক স্থায়ীভাবে বেমিল থাকত।
>
> বিস্তারিত Chart of Accounts mapping Finance টিমের সাথে বসে চূড়ান্ত করতে হবে — এটি placeholder structure। উপরের প্রতিটা "A/C" এখন থেকে [GL Account Master](01_Master_Data_Module.md#12-gl-account-master--chart-of-accounts-skeleton)-এর একটা real `account_id` (FK) — হার্ডকোড অ্যাকাউন্ট কোড না। কীভাবে sign-off ছাড়াই এখনই কোড লেখা শুরু করা যায়, দেখুন Section 5।

## 3. Bangladesh RMG Compliance — Bonded Warehouse / UD-UP

### 3.1 Bond License Master
- bond_license_no, validity, issuing customs office
- Item-wise Bond entitlement (Annual/Per Order)

### 3.2 UD (Utilization Declaration) / UP (Utilization Permission)
- linked to Order/Style + Import PO ([Procurement](03_Procurement_Module.md#7-purchase-order-po))
- UD/UP number, issue date, validity
- Consumption reconciliation vs actual fabric usage ([Inventory](02_Inventory_Module.md#9-issue-to-production--bom-consumption-variance) BOM Variance রিপোর্টের সাথে লিংক)

### 3.3 Import PO-তে যোগ হবে
- bond_applicable flag
- hs_code ([Master Data](01_Master_Data_Module.md#8-hs-code--tax-master)), duty_exemption_reference

### 3.4 Bonded Warehouse Stock
- Item Library-এর `store` লুকআপ ভ্যালুতে একটা "Bonded" টাইপ যোগ হবে (আলাদা রিলেশনাল entity না, শুধু একটা attribute)
- Bonded স্টক থেকে Local sale/transfer করলে Duty ক্যালকুলেশন ট্রিগার হবে

### 3.5 নতুন রিপোর্ট
- Bond Utilization Report
- UD/UP vs Actual Consumption
- Duty Exemption Summary

### 3.6 যেভাবে তৈরি হয়েছে (Phase 6)

পুরো অংশটা নিজস্ব মডিউল `app/Modules/Compliance`-এ গেছে, Procurement-এ নয় — লাইসেন্স, ঘোষণা আর শুল্ক কাস্টমসের কাগজ, কেনাকাটার দলিল নয়। পাঁচটি টেবিল: `bond_licences`, `bond_entitlements`, `utilisation_declarations`, `utilisation_declaration_lines`, `duty_assessments`।

- **লাইসেন্স ও এনটাইটলমেন্ট (§3.1)** — `ManageBondCompliance`। এনটাইটলমেন্ট বাধ্যতামূলক নয়: পরিমাণের অ্যানেক্সার আসার আগেই লাইসেন্স চলে আসে, আর ততক্ষণ লাইসেন্সটা লেখা যাবে না বললে যে আমদানিগুলো এর ওপর দাঁড়াবে তাদের কিছুই থাকত না। Status তিনটি (ACTIVE/SUSPENDED/CANCELLED), কিন্তু **মেয়াদ কোনো status নয়** — `isLive()` তারিখ থেকে পড়ে, তাই কোনো ক্রন জব মেয়াদোত্তীর্ণ লাইসেন্সকে "বন্ধ" করতে ভুললেও সেটা বলবৎ দেখায় না।
- **আমদানি কতটা হলো — গণনা, জমা রাখা নয়** — `BondUtilisation::forLicence()` এনটাইটলমেন্টের বিপরীতে আসল আগমন গোনে **bond-ফ্লাগ করা অর্ডারের GRN লাইন থেকে**। পাশে একটা running total রাখলে সেটা একই প্রশ্নের দ্বিতীয় উত্তর হতো, আর প্রথমবার কোনো রিসিট বাতিল হলেই বেমিল শুরু হতো। সীমা ছাড়ালে ওয়ার্নিং ফ্লাগ — মাইনাস চিহ্ন কেউ খেয়াল করে না, আর বন্ড অডিট ঠিক এটাই খোঁজে।
- **UD/UP (§3.2)** — ঘোষণা লাইসেন্সের নিচে বসে, অপশনালি Import PO-র সঙ্গে বাঁধা। **অপচয় লাইনপ্রতি শতকরা**, ডকুমেন্টপ্রতি একটা সংখ্যা নয় — কাস্টমস প্রতিটি মালের জন্য আলাদা হার দেয়, ফ্যাবরিকের সাথে সুতোর মিল নেই। মেয়াদোত্তীর্ণ বা স্থগিত লাইসেন্সের বিপরীতে `issue` 422 — ওরকম একটা ঘোষণা বন্দরে কাগজ হিসেবে মূল্যহীন।
- **রিকনসিলিয়েশন সংকেতযুক্ত** — `BondUtilisation::reconcile()` ঘোষিত (+অপচয়) বনাম স্টক লেজারে আসল খরচ। পার্থক্য **পরম নয়, সংকেতযুক্ত**: ঘোষণার চেয়ে অনেক কম খরচ মানে রপ্তানির সমর্থন ছাড়া বন্ডেড মাল পড়ে আছে, আর বেশি খরচ মানে ঘাটতি — দুটো উল্টো সমস্যা, আর পরম সংখ্যা দুটোকে একই রকম দেখাত।
- **Import PO (§3.3)** — `purchase_orders`-এ `bond_applicable`, `bond_licence_id`, `duty_exemption_reference`; `purchase_order_lines`-এ `hs_code_id` ([Master Data §8](01_Master_Data_Module.md#8-hs-code--tax-master))। মাইগ্রেশনে কলাম যোগ করাই যথেষ্ট হয়নি — মডেলের `$fillable`, কন্ট্রোলার ভ্যালিডেশন, `ManagePurchaseOrder`-এর হেডার রাইটার আর ডিটেইল পেলোয়াড — প্রতিটি স্তরে বসাতে হয়েছে, নয়তো ফ্লাগটা নিঃশব্দে হারিয়ে যেত।
- **ডিউটি (§3.4)** — `AssessBondedDuty`। **স্টক মুভমেন্টের সাইড-ইফেক্ট নয়, সচেতন কাজ** — বন্ডেড ফ্যাবরিক কাটিং ফ্লোরে ইস্যু করা বন্ডের **উদ্দেশ্যই**, লঙ্ঘন নয়; প্রতিটি আউটবাউন্ড মুভমেন্টে শুল্ক বসালে সিস্টেমটা প্রথম দিনেই অব্যবহার্য হতো। মাল রপ্তানির জন্য বেরোল না অন্য কিছুর জন্য — এটা কেবল মানুষ জানে, তাই `reason` (LOCAL_SALE / TRANSFER_OUT / SHORTAGE / WRITE_OFF / OTHER) হাতে দেওয়া। বন্ডেড নয় এমন স্টোর দিলে 422 — যে মাল কখনো বন্ডে ছিল না তার শুল্ক কেউ দেয় না। VAT বসে **শুল্ক-পরিশোধিত মূল্যের ওপর** (assessable + duty), কাস্টমস যেভাবে কষে। হারদুটি সারিতে **কপি হয়**, রেফারেন্স নয় — আজকের অ্যাসেসমেন্টকে কালকে হার বদলালেও আজকের কথাই বলতে হবে। **HS কোড না থাকলে সারি বাদ যায় না, শূন্য হারে লেখা হয়** (`is_rated: false`) — যে চালানের হার কেউ বলতে পারল না সেটা কাস্টমসের কাছে প্রশ্ন, মুছে দেওয়ার সারি নয়। নম্বর `DA-` সিকুয়েন্স থেকে।
- **রিপোর্ট (§3.5)** — তিনটিই হয়েছে (`compliance_bond_utilisation`, `compliance_ud_reconciliation`, `compliance_duty_summary`), বিদ্যমান `ReportRegistry`-এ নতুন `compliance` গ্রুপে — এগুলো স্টকও নয়, কেনাকাটাও নয়, কাস্টমসের কাগজ।
- **স্ক্রিন** — `/compliance/bond-licences` (তালিকা, নতুন, ডিটেইলে এনটাইটলমেন্ট-বনাম-আমদানি টেবিল ও ঘোষণার তালিকা), `/compliance/declarations/{id}` (রিকনসিলিয়েশনসহ), `/compliance/duty-assessments` (তালিকা + assess ডায়লগ)।
- **পারমিশন** — `compliance.bond.view_any` / `compliance.bond.manage` / `compliance.duty.assess`। স্টোরকিপার কেবল দেখে (ইস্যু করার আগে জানা দরকার কোন স্টক বন্ডেড), কাগজ ও শুল্ক স্টোর ম্যানেজারের।
- টেস্ট: `tests/Feature/Compliance/BondComplianceTest`।

> **অগ্রাধিকার নোট:** যদি ফ্যাক্টরি মূলত Export/Bonded ব্যবসা করে, এই সেকশন Phase 2-এ এগিয়ে আনা উচিত (দেখুন [Main Index](00_Main_Index.md#phase-wise-implementation-instruction))।

## 4. Document Numbering

> **বিদ্যমান প্ল্যাটফর্ম ক্যাপাবিলিটি — নতুন ডিজাইন লাগবে না।**
>
> **সিদ্ধান্ত:** প্ল্যাটফর্মে ইতিমধ্যে একটা জেনেরিক ডকুমেন্ট-নাম্বারিং সিস্টেম আছে (`NumberSequenceFormat` — company নিজের `document_type`-ভিত্তিক ফরম্যাট ও প্যাডিং কাস্টমাইজ করতে পারে; কোনো রো না থাকলে প্ল্যাটফর্ম ডিফল্ট প্রযোজ্য হয়)। এই ডকুমেন্টেশন সেটে যেখানেই নতুন ডকুমেন্ট নাম্বার সিরিজের দরকার হয়েছে (FR-/PR- ইত্যাদি), সেগুলো আলাদা নতুন লজিক না বানিয়ে এই বিদ্যমান সিস্টেমেই নতুন `document_type` এন্ট্রি হিসেবে যোগ হবে — Approval/Notification/Audit Log-এর মতোই (Section 1 দ্রষ্টব্য)।

নতুন `document_type` লাগবে:
- `floor_requisition` — [Floor/Store Requisition §8](04_Floor_Store_Requisition_Module.md#8-requisition-number-series)
- `purchase_requisition` — Procurement PR
- `purchase_order`, `grn`, `fabric_booking` — প্রয়োজন অনুযায়ী Procurement/Inventory মডিউলে ব্যবহারের সময়

## 5. Implementing With These Gaps Open

তিনটা জিনিস এখনো বাকি — Finance-এর GL/Chart of Accounts sign-off, Customs/Compliance-এর Bond/UD-UP ভ্যালিডেশন, আর Approval Matrix থ্রেশহোল্ড/BOM Variance Tolerance %-এর বাস্তব সংখ্যা। **তিনটাই গ্যাপ রেখে ইমপ্লিমেন্টেশন শুরু করা সম্ভব**, কারণ প্রতিটাই মূল ট্রানজেকশনাল ফ্লো (Item, GRN, PR/PO, Roll, Requisition) থেকে বিচ্ছিন্ন। প্যাটার্ন একটাই: **এখনই কনফিগারযোগ্য কাঠামো বসানো, real সংখ্যা/ম্যাপিং পরে ডাটা হিসেবে বসানো** — যাতে sign-off আসার পর কোনো কোড/মাইগ্রেশন বদলাতে না হয়, শুধু ডাটা আপডেট লাগে।

### 5.1 GL / Chart of Accounts

GL পোস্টিং একটা সাইড-ইফেক্ট, মূল ফ্লো না — GRN Complete/Landed Cost Post/Issue-to-Production সবই GL ছাড়াই সম্পূর্ণ কাজ করবে (স্টক আপডেট, কস্ট ক্যালকুলেশন কোনোটাই GL পোস্টিং-এর ওপর নির্ভর করে না)।

- এখনই বানিয়ে রাখা: [GL Account Master](01_Master_Data_Module.md#12-gl-account-master--chart-of-accounts-skeleton), [Bank Master](01_Master_Data_Module.md#10-bank-master), [Payment Type Master](01_Master_Data_Module.md#11-payment-type-master) — এই তিনটা লাইব্রেরি এখনই বানানো যায়, বাস্তব account code ছাড়াই।
- GL পোস্টিং সার্ভিস Section 2-এর Event → Debit/Credit ম্যাপিং **একটা টেবিল থেকে পড়বে** (event_type → debit_account_id/credit_account_id, GL Account Master-এ FK), হার্ডকোড না।
- শুরুতে placeholder/Suspense account দিয়ে সিড করে রাখা যায়। Finance sign-off দিলে শুধু এই ম্যাপিং টেবিলের ডাটা বদলাবে — কোড/মাইগ্রেশন অপরিবর্তিত থাকবে।

> **As built (Phase 5):** `gl_event_mappings` (event_type → `debit_account_id`/`credit_account_id`/`secondary_credit_account_id`, সব `gl_accounts`-এ FK — **কোনো হার্ডকোড অ্যাকাউন্ট কোড নেই**) আর পোস্টিংগুলো `gl_journal_entries` + `gl_journal_lines`-এ (header + lines, দুটো কলাম নয়: settlement ইভেন্টই দুটি অ্যাকাউন্টে ক্রেডিট করে, আর দুই-কলামের আকার ফাইন্যান্স তৃতীয়টা চাইলেই ভেঙে ফেলতে হতো)। সার্ভিস `App\Modules\Finance\Services\GlPoster`; §২-র ছয়টি ইভেন্টই ওয়্যার করা — GRN complete, landed cost post, issue to production, purchase return dispatch, advance payment, invoice settlement।
>
> স্পেকের দুটো নিয়মই রাখা হয়েছে: **GL পোস্টিং সাইড-ইফেক্ট** — ম্যাপিং না থাকলে, বন্ধ থাকলে বা অসম্পূর্ণ হলে কিছুই থ্রো করে না, শুধু লগ হয়; অ্যাকাউন্টস টিম চার্ট শেষ করেনি বলে গেটে ডেলিভারি আটকানো চলে না। আর **ম্যাপিং পড়া হয়, লেখা নয়** — সার্ভিস শুধু ইভেন্টের নাম জানে। `(company_id, event_type, source_type, source_id)` unique, তাই একই GRN দুবার বইয়ে ওঠে না (রিট্রাই করা রিকোয়েস্টেও না)।
>
> প্রতিটি কোম্পানি প্লেসহোল্ডার (suspense) চার্ট ও ছয়টি ম্যাপিং নিয়ে শুরু করে — `SeedGlDefaults`, অনবোর্ডিংয়ের ভিতরে আর পুরনো কোম্পানির জন্য `finance:seed-gl-defaults`; idempotent, ফাইন্যান্সের বদলানো সারিতে হাত দেয় না। Sign-off এলে `/settings/gl-mapping` স্ক্রিন থেকে শুধু ডাটা বদলায় — কোড বা মাইগ্রেশন নয়। Permission `finance.gl.view_any|manage`। টেস্ট `tests/Feature/Finance/GlPostingTest`।

### 5.2 Bond / UD-UP Compliance

Section 3-এর পুরোটাই Import PO + Bonded Store-এ সীমাবদ্ধ — Phase 0-5-এর কোনো ফিচার এটার ওপর নির্ভর করে না।

- এখন শুধু নাল্যাবল কলাম যোগ করে রাখা যায় (`bond_applicable`, `hs_code` on PO) — Duty ক্যালকুলেশন/UD-UP রিপোর্ট বন্ধ রাখা যায় যতক্ষণ Customs টিমের ভ্যালিডেশন না আসে।
- ফ্যাক্টরি Export/Bonded ব্যবসা না করলে পুরো সেকশনটাই Phase 6 পর্যন্ত সম্পূর্ণ স্কিপ করা যায়, কোনো রিওয়ার্ক ছাড়াই (কলামগুলো additive)।

### 5.3 Approval Matrix থ্রেশহোল্ড ও BOM Variance Tolerance %

সবচেয়ে সহজ — এগুলো এমনিতেই Settings-এ configurable value হিসেবে রাখার কথা (দেখুন [Procurement §5.7](03_Procurement_Module.md#57-approval-matrix-উদাহরণ), [Inventory §9](02_Inventory_Module.md#9-issue-to-production--bom-consumption-variance))। যেকোনো যুক্তিসঙ্গত ডিফল্ট দিয়ে শুরু করলেই হবে — বাস্তব সংখ্যা এলে Settings screen থেকে বদলে দেওয়া যাবে, কোনো কোড পরিবর্তন লাগবে না।

---

**পরবর্তী:** [Master Data Module](01_Master_Data_Module.md) | [Inventory Module](02_Inventory_Module.md) | [Procurement Module](03_Procurement_Module.md) | [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md)
