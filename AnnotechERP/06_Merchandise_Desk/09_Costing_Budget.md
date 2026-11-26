# Costing & Budget

> **স্ট্যাটাস:** ✅ সম্পন্ন (D1 Fabric Process Rate, D2 Print & Embroidery Rate, D3 CAD Consumption, D4 Order Budget Sheet, D5 Budget vs Actual Costing আর D6 Budget Sheet Workflow চালু)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/09`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Fabric Process Rate | `/m/merchandising/fab-process-rate` | 3 | 21 | 1 |
| 2 | Order Budget Sheet | `/m/merchandising/budget-sheet` | 3 | 21 | 2 |
| 3 | CAD Consumption Register | `/m/merchandising/cad-consumption-list` | 3 | 15 | 1 |
| 4 | Print & Embroidery Rate Setup | `/m/merchandising/print-embr-rate-operation` | 3 | 16 | 1 |
| 5 | Budget vs Actual Costing | `/m/merchandising/budget-vs-post-prod-costing` | 5 | 7 | 0 |
| 6 | Budget Sheet Workflow | `/m/merchandising/budget-sheet-workflow` | 1 | 12 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Fabric Process Rate

`/m/merchandising/fab-process-rate`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Rate Chart Category | select |  | All, Knitting, Yarn Dyeing, Aop, Dyeing, Dyeing Finishing |
| Fab Type | select |  | All |
| Yarn Count-Range | select |  | All |
| Fib. Combi. Template | select |  | All |
| Rate Desc. | search |  |  |
| Active? | select |  | All, Active, Inactive |
| Search Knitting, dyeing, AOP and finishing rates by construction, count range, f | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Id, Category, Fab Type, CntRange, Fib. Combi. Template, Rate Desc., Rate, Prod.Rate, APO %, S., A., ACTION
- বাটন/অ্যাকশন: Pending Fiber Combination, New process rate, Clear, Export, Id, Category, Fab Type, CntRange, Fib. Combi. Template, Rate Desc., Rate, Prod.Rate, APO %, S., A.

**ট্যাব: Pending Fiber Combination**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search categorys | search |  | Search… |
- সেকশন: Rate Chart Category, Pending Rates
- বাটন/অ্যাকশন: Pending Fiber Combination, New process rate, Knitting 0, Yarn Dyeing 0, Aop 0, Dyeing 0, Dyeing Finishing 0

**ট্যাব: New process rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Rate Chart Category* | select | ✔ | Select…, Knitting, Yarn Dyeing, Aop, Dyeing, Dyeing Finishing |
| Fiber Combi. Template | select |  | Select…, 100% Cotton, Any Fiber Blend with Lycra, CVC Blend |
| Yarn Count-Range | text |  | 7/s to 80/s |
| Fabric Type | select |  | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Slub? | checkbox |  |  |
| Rate Description* | textarea | ✔ | Slub 2x2 RIB YD Feeder Stripe Lycra Full Feeder |
| Rate* | number | ✔ |  |
| Currency | text |  |  |
| Per | text |  |  |
| Production Rate* | number | ✔ |  |
| Allowed Process Loss (%)* | number | ✔ |  |
| Active? | checkbox |  |  |
- সেকশন: New process rate
- বাটন/অ্যাকশন: Pending Fiber Combination, New process rate, Select…, Reset, Save process rate

**ডায়ালগ: New process rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Rate Chart Category* | select | ✔ | Select…, Knitting, Yarn Dyeing, Aop, Dyeing, Dyeing Finishing |
| Fiber Combi. Template | select |  | Select…, 100% Cotton, Any Fiber Blend with Lycra, CVC Blend |
| Yarn Count-Range | text |  |  |
| Fabric Type | select |  | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Slub? | checkbox |  |  |
| Rate Description* | textarea | ✔ |  |
| Rate* | number | ✔ |  |
| Currency | text |  |  |
| Per | text |  |  |
| Production Rate* | number | ✔ |  |
| Allowed Process Loss (%)* | number | ✔ |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Rate Description is required.

#### Order Budget Sheet

`/m/merchandising/budget-sheet`

**ট্যাব: Bill of Material**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Currency | text |  |  |
| Conversion rate | text |  |  |
| Order quantity | text |  |  |
| Unit price | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Figure, Yarn, Fab, Trims/Accs, Print, Embr, Wash, CM, Commercial, Service, FOB, Budget
- সেকশন: Budget Sheet, Key Information, Budget Vs Inquiry Comparisons
- বাটন/অ্যাকশন: Print Inquiry, Bill of Material, Fabric Process Cost, Overall Cost, 1 .Yarn, Add, 2 .Purchased Fabric, 3 .Sewing Accessoreis, 4 .Finishing Accessoreis, 5 .General Accessories, 6 .Special Accessories, Save, Cancel, Reset, Create Booking

**ট্যাব: Fabric Process Cost**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Currency | text |  |  |
| Conversion rate | text |  |  |
| Order quantity | text |  |  |
| Unit price | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Figure, Yarn, Fab, Trims/Accs, Print, Embr, Wash, CM, Commercial, Service, FOB, Budget
- গ্রিড কলাম: Cost Head, Inquiry, Budget, +/-, Knitting
- সেকশন: Budget Sheet, Key Information, Budget Vs Inquiry Comparisons
- বাটন/অ্যাকশন: Print Inquiry, Bill of Material, Fabric Process Cost, Overall Cost, Save, Cancel, Reset, Create Booking

**ট্যাব: Overall Cost**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Currency | text |  |  |
| Conversion rate | text |  |  |
| Order quantity | text |  |  |
| Unit price | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Figure, Yarn, Fab, Trims/Accs, Print, Embr, Wash, CM, Commercial, Service, FOB, Budget
- গ্রিড কলাম: Cost Head, Inquiry, Budget, +/-, Yarn
- সেকশন: Budget Sheet, Key Information, Budget Vs Inquiry Comparisons
- বাটন/অ্যাকশন: Print Inquiry, Bill of Material, Fabric Process Cost, Overall Cost, Save, Cancel, Reset, Create Booking

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Currency | text |  |  |
| Conversion rate | text |  |  |
| Order quantity | text |  |  |
| Unit price | text |  |  |
| Remarks | textarea |  |  |

**ডায়ালগ: Create Booking**
- খোলা যায়নি: locator.click: Timeout 3000ms exceeded.

#### CAD Consumption Register

`/m/merchandising/cad-consumption-list`

**ট্যাব: Records 1**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Job/W.Style | search |  |  |
| Buyer A/C | select |  | All |
| Category | select |  | All, Bulk |
| Date from | date |  |  |
| Date to | date |  |  |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Marker consumption imported from CAD, by buyer account, category and work | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer A/C, Category, Job/W.Style, Date, Sizes, Avg Consumption, Status, ACTION
- বাটন/অ্যাকশন: Consumption, New CAD record, Clear, Export, Buyer A/C, Category, Job/W.Style, Date, Sizes, Avg Consumption, Status, Edit

**ট্যাব: Consumption**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search records | search |  | Search… |
- সেকশন: CAD Records, Consumption
- বাটন/অ্যাকশন: Consumption, New CAD record, 8797098 Bulk 0

**ট্যাব: New CAD record**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | Select… |
| Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Category | select |  | Select…, Bulk, Sample, Development |
| Job/W.Style* | text | ✔ |  |
| Date* | date | ✔ |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- সেকশন: New CAD record
- বাটন/অ্যাকশন: Consumption, New CAD record, Select…, Reset, Save CAD record

**ডায়ালগ: New CAD record**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer A/C | select |  | Select… |
| Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Category | select |  | Select…, Bulk, Sample, Development |
| Job/W.Style* | text | ✔ |  |
| Date* | date | ✔ |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- খালি Save-এ মেসেজ: Job/W.Style is required.

#### Print & Embroidery Rate Setup

`/m/merchandising/print-embr-rate-operation`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Print, Embroidery |
| Buyer | select |  | All |
| Search Style/Order | search |  |  |
| Supplier | select |  | All |
| State | select |  | All, Confirmed, Pending |
| Search Print and embroidery rates against a style or order, and whether they hav | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Buyer, Order/Style#, Rate Related Information, Rate, Supplier, Confirmed?, ACTION
- বাটন/অ্যাকশন: Pendings, New embellishment rate, Clear, Export, Type, Buyer, Order/Style#, Rate Related Information, Rate, Supplier, Confirmed?

**ট্যাব: Pendings**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | search |  | Search… |
- সেকশন: Buyers, Unconfirmed Rates
- বাটন/অ্যাকশন: Pendings, New embellishment rate

**ট্যাব: New embellishment rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type* | select | ✔ | Select…, Print, Embroidery |
| Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer | select |  | Select…, LPP (LPP) |
| Rate Related Information* | textarea | ✔ |  |
| Rate* | number | ✔ |  |
| Currency | text |  |  |
| Per | text |  |  |
| Confirmed? Unconfirmed rates are what the Pendings tab lists. | checkbox |  |  |
- সেকশন: New embellishment rate
- বাটন/অ্যাকশন: Pendings, New embellishment rate, Select…, Reset, Save embellishment rate

**ডায়ালগ: New embellishment rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Type* | select | ✔ | Select…, Print, Embroidery |
| Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer | select |  | Select…, LPP (LPP) |
| Rate Related Information* | textarea | ✔ |  |
| Rate* | number | ✔ |  |
| Currency | text |  |  |
| Per | text |  |  |
| Confirmed? Unconfirmed rates are what the Pendings tab lists. | checkbox |  |  |
- খালি Save-এ মেসেজ: Rate Related Information is required.

#### Budget vs Actual Costing

`/m/merchandising/budget-vs-post-prod-costing`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Budget Ref# | search |  |  |
| Search Budget sheets with their style, order, quantity, value and cost-head tota | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Budget Ref#, Buyer A/C, Style #, Order No, O'Qty(Pcs), U.Price ($), O'Value ($)
- বাটন/অ্যাকশন: Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense, Clear, Export, Budget Ref#, Buyer A/C, Style #, Order No, O'Qty(Pcs), U.Price ($), O'Value ($)

**ট্যাব: Yarn+Trims/Accs-Expense**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search budgets | search |  | Search… |
- সেকশন: Budgets, Cost Heads
- বাটন/অ্যাকশন: Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense

**ট্যাব: Fabric Process-Expense**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search budgets | search |  | Search… |
- সেকশন: Budgets, Cost Heads
- বাটন/অ্যাকশন: Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense

**ট্যাব: CMT+Print+Embr.+Wash-Expense**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search budgets | search |  | Search… |
- সেকশন: Budgets, Cost Heads
- বাটন/অ্যাকশন: Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense

**ট্যাব: Export List-Expense**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search budgets | search |  | Search… |
- সেকশন: Budgets, Cost Heads
- বাটন/অ্যাকশন: Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense

#### Budget Sheet Workflow

`/m/merchandising/budget-sheet-workflow`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Month -- All -- | select |  |  |
| From: | date |  |  |
| To Date | date |  |  |
| All Week | radio |  |  |
| Week 1 | radio |  |  |
| Week 2 | radio |  |  |
| Week 3 | radio |  |  |
| Week 4 | radio |  |  |
| Week 5 | radio |  |  |
| ApporvedOnly? | search |  | Search Job#/Style/PO |
| ApporvedOnly? | checkbox |  |  |
| Skip Date? | checkbox |  |  |
- সেকশন: Week
- বাটন/অ্যাকশন: All
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- স্ক্রিন কিট (`ResourceDefinition`, `masterScreen`, `PickPanel`)।
- মাস্টার: Fabric Type (`fabric_types`), Dye Colour Group (`dye_colour_groups`)। Fiber Template একটা নির্দিষ্ট তালিকা (COTTON_100, LYCRA_BLEND, CVC_BLEND), Yarn Count Config আর Fabric Planning যেটা আগে থেকেই ব্যবহার করে।
- Style Development Sheet-এর BOM আর Order Register-এর অর্ডার, যা থেকে Order Budget Sheet (D4) বানানো হবে।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_budgets` (D4):** order_id (ইউনিক, একটা অর্ডারে একটাই বাজেট), budget_no (BGT-YYYY-000001), style_id, buyer_office_id, currency, exchange_rate, process_costs (JSON: knitting, fabric, yarn_dyeing, aop, dyeing, dyeing_finishing, import_fabric, c2f), overall_costs (JSON: print, embroidery, wash, cm, commercial, service, expense, fob), remarks, created_by; D5-এ actual_costs (JSON, খাত → প্রতি পিসে আসল খরচ)। D6-এ approved_at, approved_by। **`merch_budget_events` (D6):** budget_id, action (APPROVED, UNLOCKED), note, user_id। **`merch_budget_lines`:** budget_id, section (YARN, PURCHASED_FABRIC, SEWING_ACC, FINISHING_ACC, GENERAL_ACC, SPECIAL_ACC), description, uom, qty, rate, sort_order।
- **`merch_cad_records` (D3):** buyer_office_id (Buyer A/C), style_id, order_id, category (BULK, SAMPLE, DEVELOPMENT), job_no, record_date, status (DRAFT, SUBMITTED, IN_PROGRESS, SENT, APPROVED, REJECTED, CANCELLED), created_by। **`merch_cad_sizes`:** cad_id, size, garment_part_id, consumption, uom, sort_order।
- **`merch_embellishment_rates` (D2):** type (PRINT, EMBROIDERY), style_id, order_id, buyer_id, supplier_id, description, rate, currency (BDT), per (Pcs), confirmed, confirmed_at, confirmed_by, created_by।
- **`merch_fab_process_rates`:**
  - rate_no (কোম্পানিতে ক্রমিক, ইউনিক)।
  - category (KNITTING, YARN_DYEING, AOP, DYEING, DYEING_FINISHING)।
  - fiber_template, count_range, fabric_type_id, colour_group_id (রঙের ধরন), is_slub।
  - description, rate, currency (ডিফল্ট BDT), per (ডিফল্ট Kg), prod_rate, process_loss (%), active।

## ৫. Workflow / Status ও Business Rules

- **Order Budget Sheet (D4):**
  - সব অঙ্ক প্রতি পিসে, বাজেটের কারেন্সিতে। SET বা PACK অর্ডারে পরিমাণ পিসে বদলানো হয়, দামও পিসে ভাগ হয়।
  - সেভ না হওয়া শিট খোলে অর্ডারের তথ্য নিয়ে: পরিমাণ, পিস প্রতি দাম (FOB হিসেবে), কারেন্সি, রেট আর স্টাইলের বায়ার অফিস।
  - ইনকোয়ারির কোটেড FOB তুলনার জন্য আসে; হিসাবের একক DZ হলে ১২ দিয়ে ভাগ।
  - Overall Cost-এর Yarn আর Trims আসে BOM থেকে। FOB আর খাতগুলোর যোগফল না মিললে সতর্কবার্তা দেখায়।
  - প্রথম সেভে নম্বর পায় (advisory lock-এর ভেতরে); পরের সেভে একই বাজেট আর নম্বর থাকে, BOM লাইন নতুন করে লেখা হয়। BOM লাইনে Description বাধ্যতামূলক।
- **Budget vs Actual Costing (D5):**
  - চারটা খরচের দল: Yarn+Trims/Accs (yarn আর চার রকম এক্সেসরিজ), Fabric Process (purchased fabric আর প্রসেসের খাত), CMT+Print+Embr.+Wash, Export (commercial, service, expense)।
  - প্রতিটা খাতে বাজেট (BOM সেকশনের যোগ বা প্রসেস/সার্বিক খাত), আসল খরচ (লেখা হয়) আর +/-। খরচ বাজেটের বেশি হলে লাল, কম হলে সবুজ।
  - এক দলের সেভে অন্য দলের আসল খরচ বদলায় না। আসল খরচ ঋণাত্মক হতে পারে না।
- **Budget Sheet Workflow (D6):**
  - সেভ হওয়া প্রতিটা বাজেট শিট অনুমোদনের অপেক্ষায় থাকে (Waiting)। অনুমোদনকারী Approve করলে শিটটা কস্টিংয়ের জন্য লক হয়: বাজেট শিট সেভ করলে 422, শিটে লক থাকার বার্তা দেখায় আর সব ঘর বন্ধ থাকে। আসল খরচ (D5) তখনো লেখা যায়।
  - Unlock করলে কারণ লিখতে হয়। শিট আবার বদলানো যায় আর অপেক্ষায় ফেরে। প্রতিটা ধাপ (কে, কখন, নোট) `merch_budget_events`-এ থাকে।
  - অপেক্ষায় থাকা শিট Unlock করা বা অনুমোদিত শিট আবার Approve করা যায় না (422)।
  - Cost/Pc = BOM-এর যোগ + প্রসেসের খাত + সার্বিক খাত (FOB বাদে)। FOB-এর চেয়ে বেশি হলে লালে দেখায়।
  - ফিল্টার: Month আর Week চলে অর্ডারের শিপমেন্টের তারিখে (Week n = মাসের ৭(n−1)+১ থেকে ৭n তারিখ; Week 5 মাসের শেষ পর্যন্ত)। From/To চলে বাজেটের তারিখে, আর "Skip Date?" (শুরুতে টিক দেওয়া) থাকলে এটা বাদ যায়। সার্চে Job#, Style, PO আর অর্ডার নম্বর খোঁজা যায়। Approved Only? দিলে শুধু অনুমোদিত শিট দেখায়।
- **CAD Consumption (D3):**
  - Job/W.Style আর Date বাধ্যতামূলক, Brickland-এর বার্তাসহ।
  - সাইজের লাইন একসাথে সেভ হয়। একই গার্মেন্ট পার্টে একই সাইজ দুবার দেওয়া যায় না; সাইজের নামে বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না।
  - Sizes হলো লাইনের সংখ্যা, Avg Consumption লাইনগুলোর গড় (৪ দশমিক)। রেকর্ড মুছলে তার সাইজও মুছে যায়।
- **Print & Embroidery Rate (D2):**
  - Type, Rate Related Information আর Rate বাধ্যতামূলক। খালি তথ্যে Brickland-এর বার্তা আসে: "Rate Related Information is required."
  - Currency না দিলে BDT, Per না দিলে Pcs।
  - কনফার্ম না হওয়া রেট Pendings ট্যাবে বায়ার অনুযায়ী থাকে। শুধু যে রেটে বায়ার আছে সেটাই সেখানে দেখায়, Brickland-এর মতো।
  - Confirm করলে রেট কস্টিংয়ে লাগানো যায়, কিন্তু আর বদলানো যায় না। কে আর কখন কনফার্ম করেছে সেটা রাখা হয়।
  - কনফার্ম হওয়া রেট মোছা যায়, Brickland-এর Records-এর Remove-এর মতো।
- রেট তৈরির সময় পরের নম্বর পায়, advisory lock-এর ভেতরে। কোনো রেট মুছলেও নম্বর আবার ব্যবহার হয় না।
- Category, Description, Rate, Production Rate আর Process Loss (০–১০০%) বাধ্যতামূলক। খালি Description-এ Brickland-এর বার্তা আসে: "Rate Description is required."
- যে রেটের Fiber Template নেই, সেটা Pending Fiber Combination ট্যাবে থাকে, যতক্ষণ না template বসানো হয়।

## ৬. API ও পারমিশন

- `GET/POST /erp/merch-fab-process-rates`, `GET/PATCH/DELETE /erp/merch-fab-process-rates/{id}`: স্ক্রিন কিট। Template বসাতে PATCH-এ শুধু `fiber_template` পাঠানো হয়।
- `/merchandise/lookups`-এ এখন `fabric_types`-ও আসে।
- `GET/POST /erp/merch-embellishment-rates`, `GET/PATCH/DELETE /erp/merch-embellishment-rates/{id}`। Confirm হলো PATCH-এ `confirmed: true`; কনফার্ম হওয়া রেটে PATCH করলে 422।
- `GET/POST /erp/merch-cad-records`, `GET/PATCH/DELETE /erp/merch-cad-records/{id}`; `GET/PUT /merchandise/cad-records/{record}/sizes`।
- `GET /merchandise/budgets/options` (বায়ার অফিস, স্টাইল, অর্ডার); `GET/PUT /merchandise/orders/{order}/budget` (D4)।
- `GET /merchandise/budget-workflow`; `POST /merchandise/budgets/{budget}/approval` (`move`: approve বা unlock, `note`; unlock-এ note বাধ্যতামূলক) (D6)।
- `GET /merchandise/budgets` (Records); `GET/PUT /merchandise/budgets/{budget}/actuals/{group}`, group = yarn_trims, fabric_process, cmt, export; অন্য group-এ 404 (D5)।
- পারমিশন: `merchandise.costing.view`, `merchandise.costing.manage`, `merchandise.costing.approve` (D6: বাজেট শিট অনুমোদন বা আনলক)। FACTORY_ADMIN তিনটাই পায়, FACTORY_MANAGER শুধু view।
- মেনু: ফোল্ডার `MERCHANDISE.COSTING` ("COSTING & BUDGET"), তার ভেতরে Brickland-এর ক্রমে: `MERCHANDISE.FAB_PROCESS_RATE` → `/merchandise-desk/fab-process-rate`, `MERCHANDISE.CAD_CONSUMPTION` → `/merchandise-desk/cad-consumption-list`, `MERCHANDISE.EMBELLISHMENT_RATE` → `/merchandise-desk/print-embr-rate-operation`, `MERCHANDISE.BUDGET_SHEET` → `/merchandise-desk/budget-sheet`, `MERCHANDISE.BUDGET_VS_ACTUAL` → `/merchandise-desk/budget-vs-post-prod-costing` আর `MERCHANDISE.BUDGET_WORKFLOW` → `/merchandise-desk/budget-sheet-workflow`।

## ৭. Frontend পেজ (লাইট থিম)

- **Order Budget Sheet** (`/merchandise-desk/budget-sheet`, `?order=<অর্ডার নম্বর>`): ট্যাব বার নেই, একটাই শিট।
  - উপরে Budget Sheet গ্রিড (Figure, Yarn, Fab, Trims/Accs, Print, Embr, Wash, CM, Commercial, Service, FOB, Budget), Key Information (Buyer A/C, Style, Order, Currency, Conversion rate, Order quantity, Unit price, Remarks) আর Budget Vs Inquiry Comparisons।
  - ভেতরের ট্যাব Bill of Material (৬টা সেকশন, প্রতিটায় Add), Fabric Process Cost আর Overall Cost (Cost Head, Inquiry, Budget, +/-)। পাতা রিলোড হয় না।
  - বাটন: Print Inquiry, Save, Cancel, Reset, Create Booking (06/11 Fabric Booking বানানো না হওয়া পর্যন্ত নিষ্ক্রিয়, কারণসহ title) আর Back to List → Budget vs Actual Costing।
- **Budget Sheet Workflow** (`/merchandise-desk/budget-sheet-workflow`): ট্যাব বার নেই, একটাই ভিউ।
  - উপরের কার্ডে Brickland-এর ফিল্টার: Month (-- All --, যে মাসে শিপমেন্ট আছে), From:, To Date, All Week / Week 1–5, Search Job#/Style/PO আর সার্চ বাটন, Approved Only?, Skip Date?। নিচে বায়ারের পিল: All আর প্রতিটা বায়ার, সংখ্যাসহ। সব ফিল্টার পাতায় বসেই কাজ করে, URL বা রিলোড ছাড়া।
  - তালিকা: Budget Ref#, Buyer (Buyer A/C), Job#, Style, PO / Order, Ship Date, Qty (Pcs), Cost/Pc, FOB/Pc, Status (Waiting / Approved, কে অনুমোদন করেছেন, শেষ আনলকের নোট)। সারিতে Open → `/merchandise-desk/budget-sheet?order=<অর্ডার নম্বর>`, আর অনুমোদনকারী দেখেন Approve অথবা Unlock (নোটের ডায়ালগসহ)।
  - খালি অবস্থায় Brickland-এর লেখা: "Nothing waiting here — No document matches the filter. Widen the month, the week or the buyer."; পেজার আর "0 - 0 of 0 items"।
- **Budget vs Actual Costing** (`/merchandise-desk/budget-vs-post-prod-costing`):
  - **Records:** কলাম Budget Ref#, Buyer A/C, Style #, Order No, O'Qty(Pcs), U.Price ($), O'Value ($); সারিতে Open → `/merchandise-desk/budget-sheet?order=<অর্ডার নম্বর>`। Export আর গ্রিডের সার্চ।
  - **Yarn+Trims/Accs-Expense, Fabric Process-Expense, CMT+Print+Embr.+Wash-Expense, Export List-Expense** (`?tab=<group>&budget=<Budget Ref#>`): বাঁয়ে Budgets (খোঁজা যায়), ডানে Cost Heads: Cost Head, Budget, Actual (লেখার ঘর), +/-, Total; নিচে Revert আর Save।
- **CAD Consumption Register** (`/merchandise-desk/cad-consumption-list`):
  - **Records:** ফিল্টার Buyer A/C, Category, Date from/to, Status; গ্রিডের সার্চে Job/W.Style খোঁজা যায়। কলাম: Buyer A/C, Category, Job/W.Style, Date, Sizes, Avg Consumption, Status; সারিতে Edit আর Remove।
  - **Consumption** (`?tab=consumption&record=<job>`): বাঁয়ে CAD রেকর্ড, সাইজের সংখ্যাসহ। ডানে Size, Garment Part, Consumption আর UOM (এখানেই বদলানো যায়), Status (সেভ না হওয়া লাইনে New) আর Remove। উপরে Export আর Add size (ডায়ালগ: Size*, Garment Part, Consumption*, UOM), নিচে Revert আর Save।
  - **New / Edit CAD record:** Buyer A/C, Style, Order, Category, Job/W.Style*, Date*, Status।
  - masterScreen-এর ফিল্টারে এখন `match` দেওয়া যায় (যেমন তারিখের সীমা)।
- **Print & Embroidery Rate Setup** (`/merchandise-desk/print-embr-rate-operation`):
  - **Records:** ফিল্টার Type, Buyer, Supplier, State (Confirmed/Pending); গ্রিডের সার্চে স্টাইল বা অর্ডার খোঁজা যায়। কলাম: Type, Buyer, Order/Style#, Rate Related Information, Rate ("0.35 BDT /Pcs"), Supplier, Confirmed? (Yes/No), Status (Pending/Confirmed); সারিতে Edit আর Remove।
  - **Pendings** (`?tab=pendings&buyer=LPP`): বাঁয়ে কনফার্ম না হওয়া রেটের বায়ার, সংখ্যাসহ। ডানে "Unconfirmed Rates — <বায়ার>", উপরে Export, প্রতি সারিতে Confirm আর Drop। দুটোই আগে নিশ্চিতকরণ চায়, Brickland-এর লেখাসহ।
  - **New / Edit embellishment rate:** Type, Style, Order, Buyer, Supplier, Rate Related Information, Rate, Currency, Per, Confirmed?।
- **Fabric Process Rate** (`/merchandise-desk/fab-process-rate`):
  - **Records:** ফিল্টার Rate Chart Category, Fab Type, Yarn Count-Range, Fib. Combi. Template, Active?। কলাম: Id, Category, Fab Type, CntRange, Fib. Combi. Template, Rate Desc., Rate ("0.45 BDT /Kg"), Prod.Rate, APO %, S., A., Status।
  - **Pending Fiber Combination** (`?tab=pending&category=KNITTING`): বাঁয়ে ৫টা ক্যাটাগরি, template ছাড়া রেটের সংখ্যাসহ। ডানে সেই রেটগুলো, প্রতিটায় template বাছাই করে Assign।
  - **New process rate / Edit** (`?tab=edit&ref=<Id>`): ৩ কলামের ফর্ম।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| D1 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_16_000100_create_merchandise_fab_process_rates.php`, `Models/FabProcessRate.php`, `Resources/FabProcessRateResource.php` (`ResourceRegistry.php`-এ যোগ), `MerchandiseLookupsApiController.php` (`fabric_types`) |
| D3 | `Database/Migrations/2026_11_17_000200_create_merchandise_cad_records.php`, `Models/CadRecord.php`, `Models/CadSize.php`, `Resources/CadRecordResource.php`, `Http/Controllers/Api/CadSizeApiController.php`; `frontend/src/components/merchandise/cad-sizes.jsx`, `costing.js`, `screens.js` (`match`) |
| D2 | `Database/Migrations/2026_11_17_000100_create_merchandise_embellishment_rates.php`, `Models/EmbellishmentRate.php`, `Resources/EmbellishmentRateResource.php`; `frontend/src/components/merchandise/pending-embellishment-rates.jsx`, `costing.js`, `actions.js` (`confirmEmbellishmentRate`, `dropEmbellishmentRate`) |
| D4 | `Database/Migrations/2026_11_17_000300_create_merchandise_budgets.php`, `Models/Budget.php`, `Models/BudgetLine.php`, `Http/Controllers/Api/BudgetApiController.php` (options, show, save), `Routes/api.php`; `frontend/src/components/merchandise/budget-sheet.jsx`, `costing.js` (`budgetSheet`), `actions.js` (`loadBudget`, `saveBudget`) |
| D5 | `Database/Migrations/2026_11_17_000400_add_actual_costs_to_merchandise_budgets.php`, `Budget::ACTUAL_GROUPS`, `BudgetApiController.php` (index, actuals, saveActuals); `frontend/src/components/merchandise/budget-actuals.jsx`, `costing.js` (`budgetVsActual`), `actions.js` (`saveBudgetActuals`) |
| D6 | `Database/Migrations/2026_11_17_000500_add_approval_to_merchandise_budgets.php`, `Models/BudgetEvent.php`, `Budget.php` (approved_at/by), `BudgetApiController.php` (workflow, decide, সেভে লক); `frontend/src/components/merchandise/budget-workflow.jsx`, `budget-sheet.jsx` (লক), `costing.js` (`budgetWorkflow`), `actions.js` (`decideBudget`) |
| পারমিশন / মেনু | `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` |
| Frontend | `frontend/src/app/(app)/merchandise-desk/[screen]/costing.js`, `page.js`, `actions.js` (`assignFiberTemplate`); `components/merchandise/pending-fiber-templates.jsx` |
| Lang | `lang/en/merchandise.php`, `lang/bn/merchandise.php` (`master.fpr`, `master.emb`, `master.cad`, `master.bgt`, `master.bva`, `master.bsw`, `fab_process_rate`, `embellishment_rate`, `cad`, `budget`) |
| Test | `tests/Feature/Merchandise/MerchandiseCostingTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_নেই — Costing & Budget সম্পন্ন।_

## ৯. টেস্ট ও যাচাই

- `MerchandiseCostingTest`:
  - রেটের নম্বর, BDT/Kg ডিফল্ট, ভ্যালিডেশন (Brickland-এর বার্তাসহ), template বসানো, মোছার পরেও নম্বর আবার ব্যবহার না হওয়া।
  - এমবেলিশমেন্ট রেট: বার্তা, BDT/Pcs ডিফল্ট, কনফার্মের পরে বদলানো বন্ধ, কনফার্ম হওয়া রেট মোছা যায়।
  - CAD: বার্তা, সাইজের লাইন, একই পার্টে একই সাইজ দুবার আটকানো, গড় কনজাম্পশন, মুছলে সাইজও মোছে।
  - বাজেট: সেভ না হওয়া শিট অর্ডারের পরিমাণ/দাম/কারেন্সি/বায়ার অফিস নিয়ে খোলে, BOM লাইনের Description আর Currency-র ভ্যালিডেশন, BGT নম্বর, আবার সেভে একই নম্বর আর নতুন লাইন, Records তালিকা।
  - আসল খরচ: দলের খাত আর বাজেট (BOM সেকশনের যোগ, প্রসেস/সার্বিক খাত), অজানা দলে 404, ঋণাত্মক খরচ আটকানো, এক দলের সেভে অন্য দল অক্ষত।
  - ওয়ার্কফ্লো: অপেক্ষায় থাকা শিটের তথ্য (বায়ার, জব, PO, শিপমেন্ট, Cost/Pc, FOB), ম্যানেজারের Approve-এ 403, ভুল ধাপে 422, অনুমোদনের পর বাজেট সেভে 422 অথচ আসল খরচ সেভ হয়, কারণ ছাড়া Unlock-এ বার্তা, Unlock-এর পর আবার সেভ, দুটো ধাপের ইতিহাস।
  - টেকনিশিয়ান খুলতে পারে না, ম্যানেজার শুধু দেখতে পারে (বাজেট আর আসল খরচ সেভ করলে 403)।

## ১০. খোলা প্রশ্ন

১. **নিট বুকিংয়ের সুতার রেট:** বুকিং থেকে আনা নিট কাপড় (আর নিট ট্রিমস) বাজেটের "Yarn"-এ kg হিসেবে আসে, কিন্তু রেট ০ থাকে, কারণ বুকিংয়ের রেট কাপড়ের, সুতার নয়। সুতার দাম কোথা থেকে নেওয়া হবে (Yarn Spec, সুতা কেনার রেট, নাকি হাতে লেখা) জানাবেন।

২. **Additional / Buffer বুকিং:** এখন শুধু Bulk বুকিং থেকে আনা হয়। Additional বা Buffer বুকিংও বাজেটে যোগ হবে কিনা জানাবেন।

৩. **সাপ্লায়ারের দামের ক্রম:** এক্সেসরিজের রেট সাপ্লায়ারের দাম থেকে আসে। ক্রম: অনুমোদিত কোটেশন → শেষ এক্সেসরিজ PO → খোলা কোটেশন। আলাদা "সাপ্লায়ার প্রাইস লিস্ট" মাস্টার লাগবে কিনা, বা ক্রম বদলাতে হবে কিনা জানাবেন।

৪. **অন্য মুদ্রার দাম:** BDT দাম শিটের Conv. Loc. দিয়ে ভাগ হয়। BDT শিটে বিদেশি PO-র দাম সেই PO-র রেটে গুণ হয়। এর বাইরে (যেমন USD শিটে EUR কোটেশন) রেট বসে না, শুধু সাপ্লায়ারের পাশে দাম দেখায়। মুদ্রার রেট মাস্টার থেকে বদলাতে হবে কিনা জানাবেন।

## ১১. As-built নোট

**D1 — Fabric Process Rate (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে, একটা টেস্ট রেট তৈরি করে Records আর Pending ট্যাব যাচাই করা হয়েছে।
- **Brickland থেকে আলাদা:**
  - Brickland-এর ফর্মে "Colour Type" ঘরের অপশন ফাঁকা থাকে। আমাদেরটায় এটা Dye Colour Group মাস্টার থেকে আসে, কারণ ডাইংয়ের রেট রঙের শেড অনুযায়ী বদলায়।
  - Rate Desc. ফিল্টারটা আলাদা লেখার ঘর নয়; গ্রিডের নিজের সার্চেই সেই কাজ হয়।

**D2 — Print & Embroidery Rate Setup (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে, দুটো টেস্ট রেট তৈরি করে:
  - Records, New, Edit, Remove আর তার ডায়ালগ।
  - Pendings-এ বায়ার বাছাই, Confirm ডায়ালগ ("Confirm this rate? — The rate is confirmed and can be costed against. It can no longer be edited.") আর Drop।
  - কনফার্ম হওয়া রেট আবার সেভ করার চেষ্টা।
  - প্রতিটা বাটনের গন্তব্যও দেখা হয়েছে: এই স্ক্রিনে বাইরে যাওয়ার কোনো লিংক নেই, Edit একটা ট্যাব।
- **Brickland থেকে আলাদা:**
  - কনফার্ম হওয়া রেট সেভ করলে Brickland ভুল বার্তা দেয়: "One of the records you selected no longer exists." আমাদেরটায় পরিষ্কার বার্তা আসে যে কনফার্ম হওয়া রেট আর বদলানো যায় না।
  - Brickland-এ Style আর Supplier খোঁজার পিকার; আমাদেরটায় ড্রপডাউন।

**D3 — CAD Consumption Register (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে, আগে থেকে থাকা রেকর্ড দিয়ে:
  - Records, Consumption-এ রেকর্ড বাছাই, Add size ডায়ালগ আর Edit ট্যাব।
  - বাটন যাচাই: বাইরে যাওয়ার কোনো লিংক নেই। সারিতে Edit আর Remove।
- **Brickland থেকে আলাদা:**
  - Brickland-এ সাইজের Status কলাম ফাঁকা থাকে। আমাদেরটায় সেভ না হওয়া লাইনে "New" দেখায়।
  - Brickland-এর Buyer A/C তালিকা ফাঁকা ("No records to choose from yet")। আমাদেরটায় বায়ার অফিস মাস্টার থেকে আসে।
  - Style আর Garment Part Brickland-এ খোঁজার পিকার; আমাদেরটায় ড্রপডাউন।

**D4 — Order Budget Sheet (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: তিনটা ভেতরের ট্যাব, ছয়টা BOM সেকশনের Add, Key Information, Budget Vs Inquiry Comparisons আর প্রতিটা বাটন।
- **Brickland থেকে আলাদা:**
  - Brickland-এ Save চাপলেও বাজেট আসলে সেভ হয় না। আমাদেরটায় সেভ হয় এবং BGT নম্বর পায়।
  - Create Booking যায় 06/11 Fabric Booking-এ, যা এখনো বানানো হয়নি; তাই বাটনটা নিষ্ক্রিয়, কারণ title-এ লেখা।
  - Back to List Brickland-এ মডিউলের হোম পাতায় যায়, যেটা আমাদের নেই। আমাদেরটায় যায় Budget vs Actual Costing-এর Records-এ, যেখানে সব বাজেট শিটের তালিকা আছে।

**D5 — Budget vs Actual Costing (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: Records-এর কলাম আর চারটা খরচের ট্যাব (বাঁয়ে Budgets, ডানে Cost Heads)।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এ কোনো বাজেট নেই (Records 0) আর বাজেট শিট সেভ হয় না, তাই সারির লিংক আর Cost Heads-এর ভেতরের কলাম দেখা যায়নি। আমাদের সারিতে Open যায় সেই অর্ডারের বাজেট শিটে। Cost Heads-এ Budget, Actual আর +/- রাখা হয়েছে, Budget Sheet-এর Inquiry/Budget/+/- ধাঁচে।
  - Brickland-এর Records-এ আলাদা "Budget Ref#" সার্চ ঘর আছে; আমাদেরটায় গ্রিডের নিজের সার্চেই Budget Ref# খোঁজা যায়।

**D6 — Budget Sheet Workflow (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: ফিল্টারের কার্ড, Week রেডিও, Skip Date? (শুরুতে টিক দেওয়া), বায়ারের পিল, খালি অবস্থার লেখা আর পেজার। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এর বাজেট শিট সেভ হয় না, তাই এই ওয়ার্কফ্লোতে কখনো কোনো ডকুমেন্ট আসে না; সারির কলাম আর বাটন দেখা যায়নি। আমাদের সারির কলাম, Open, Approve আর Unlock বানানো হয়েছে স্ক্রিনের বিবরণ ("Approval route a budget sheet travels before it is locked for costing") আর Style Approval-এর ধাঁচে।
  - Brickland-এর বানান "ApporvedOnly?"; আমাদেরটায় "Approved Only?"।
  - Brickland-এ ফিল্টারগুলো কোন তারিখে চলে তা দেখা যায়নি। আমাদের Month/Week চলে শিপমেন্টের তারিখে, From/To বাজেটের তারিখে।

**Order Budget Sheet — Bill of Material অগ্রগতি (২০২৬-১১-১৭, ব্যবহারকারীর সিদ্ধান্ত "অগ্রগতি যোগ করুন"):** দেখতে Brickland-এর মতোই (৬টা গ্রুপ, Description/UOM/Qty/Rate/Amount), তবে সাথে যোগ হয়েছে:
- **Cons/Dzn আর Wastage %:** দিলে Qty (প্রতি পিস) নিজে হিসাব হয়: Cons/Dzn ÷ ১২ × (১ + Wastage %)। সার্ভারও একই হিসাব করে, পাঠানো Qty মানে না। Cons/Dzn না দিলে আগের মতো Qty হাতে লেখা যায়।
- **Order Req.:** Qty × অর্ডারের পিস, শুধু দেখার জন্য। শিটের সব হিসাব প্রতি পিসে, তাই ব্যবহারকারী যে "OQ দিয়ে গুণ" বলেছিলেন, সেটা এই কলামে দেখানো হয়।
- **নতুন লাইনের একক গ্রুপ ধরে:** Yarn = KG, Purchased Fabric = কোম্পানির দৈর্ঘ্যের একক (YDS), বাকিগুলো = PCS।
- **"Load from BOM / Booking"** (`GET /merchandise/orders/{order}/budget/sources`):
  - **ফ্যাব্রিক বুকিং** (Bulk, বাতিল বা প্রত্যাখ্যাত নয়; এই অর্ডারের, বা স্টাইলের "all orders" লাইন): কাপড় আর পার্ট ধরে এক লাইন। এই অর্ডারের যত পিসে লাইনটা খাটে (রং/সাইজ ধরে, `FabricBookingApiController::piecesFor(..., $onlyOrder)`), সেই অনুযায়ী ভারিত গড় নেওয়া হয়।
    - নিট আর ট্রিমস → **Yarn**, kg-তে (দৈর্ঘ্যে বুক হলে GSM/চওড়া দিয়ে kg; না পারলে নিজের এককে থাকে আর একটা নোট দেখায়)। wastage = কাটিং wastage × process loss। রেট ০, প্রশ্ন ১ দেখুন।
    - ওভেন → **Purchased Fabric**, নিজের এককে, বুকিংয়ের রেটে।
  - **স্টাইলের BOM** (চালু অনুমোদিত ভার্সন, না থাকলে সর্বশেষ): ক্যাটাগরি → গ্রুপ। Sewing Acc./Trims/Labels → Sewing; Packaging → Finishing; Accessories/Others → General; Main Fabric → Purchased Fabric, তবে বুকিং থেকে কাপড় এলে বাদ। Cons/Dzn = consumption ÷ per GMT × ১২। Wastage = Allow % (+ টিক থাকলে BOM-এর addl %)। রেট BOM থেকে।
  - আনা লাইনে "BOM"/"Booking" চিহ্ন থাকে (`merch_budget_lines.source`)। আবার আনলে শুধু আগের আনা লাইনগুলো বদলায় (জিজ্ঞাসা করে), হাতে লেখা লাইন থাকে। Save না করা পর্যন্ত কিছু রাখা হয় না।
- **Fabric Booking / Create Booking বাটন:** আগে নিষ্ক্রিয় ছিল ("module আসবে")। এখন Bulk Fabric Booking-এ যায়: প্রথমটা এই অর্ডার ফিল্টার করে, দ্বিতীয়টা নতুন বুকিং খোলে।
- **টেবিল:** `merch_budget_lines` + `cons_dzn`, `wastage_pct`, `source` (মাইগ্রেশন `2027_03_31_000200`)।
- **টেস্ট:** `MerchandiseCostingTest::test_a_budget_bill_of_material_loads_from_the_booking_and_the_bom_and_works_qty_from_consumption`। বডির সুতা: ২৪০ + ৮০ (Navy) = ৩২০ kg ÷ ১২০০ পিস = ৩.২ kg/ডজন, wastage ১১.৬২৫%। ওভেন টুইল: ৩ গজ/ডজন, ৪%, রেট ২। GSM ছাড়া রিব: নোট দেখায়। BOM-এর মেইন ফ্যাব্রিক বাদ, আর বুকিং না থাকা অর্ডারে আসে। Save-এ Qty সার্ভার হিসাব করে। Costing ৮টা + Fabric Booking ৫টা পাস।
- **UI চেক (dev-এ রাখা):**
  - SS27-TEE01-এর BOM-এ "Sewing Thread 40/2" যোগ করা হয়েছে (১৫০ মি./পিস, ১০%, ০.০০২)।
  - LPP-2026-000001-এর বাজেট BGT-2026-000002-এ Load দিলে এল: Cons/Dzn ১৮০০, Qty ১৬৫, Amount ০.৩৩, Order Req. ৩১৩,৫০০ MTR। Save করা হয়েছে।
  - "Order" অর্ডারের বাজেট অনুমোদিত, তাই সেখানে Load বাটন দেখায় না।
  - **লক্ষ্য করার মতো:** ওই বাজেটের সংখ্যাগুলো প্রতি পিসের নয়, পুরো অর্ডারের মনে হয় (Yarn qty ৫২০ → Order Req. ৫,২০,০০০ kg; FOB ৪৫০০)।

**এক্সেসরিজ লাইনে আইটেম আর সাপ্লায়ার (২০২৬-১১-১৭, ব্যবহারকারীর চাওয়া):** Sewing / Finishing / General / Special Accessories-এর লাইনে এখন কলাম: Item | Item Code | Supplier | UOM | Cons/Dzn | Wastage % | Qty | Rate | Amount | Order Req.
- **Item:** ট্রিমস মাস্টার (`mp_items`, kind TRIMS) থেকে বাছা হয়, যেটা Accessories Requisition-ও ব্যবহার করে। বাছলেই Item Code, UOM আর বিবরণ (আইটেমের নাম) আসে। মাস্টারে না থাকা জিনিসের জন্য Item ফাঁকা রেখে বিবরণ লেখা যায়।
- **Cons/Dzn আর Wastage %:** স্টাইলের BOM-এর লাইন থেকে আসে। মিলানো হয় কোড দিয়ে, কোড না থাকলে নাম দিয়ে। BOM-এ না থাকলে হাতে লিখতে হয়। অন্য আইটেম বসালে আগের সংখ্যা মুছে যায়।
- **Supplier → Rate:** `App\Modules\Merchandise\Support\BudgetItemPrices` প্রতি সাপ্লায়ারের একটা দাম দেয়:
  - ক্রম: অনুমোদিত কোটেশন (সর্বশেষ revision) → শেষ এক্সেসরিজ PO → খোলা কোটেশন।
  - দাম শিটের মুদ্রায় আনা হয়।
  - আইটেম বাছলে সাপ্লায়ার নিজে বসে: BOM-এ নাম থাকা সাপ্লায়ার (নাম, short name বা কোডে মেলে), তার দাম না থাকলে সেরা দাম।
  - সাপ্লায়ারের তালিকায় যাদের দাম আছে তারা আগে, দাম আর উৎস (Quotation/PO নং) সহ।
  - সাপ্লায়ার বদলালে রেট বদলায়। রেট হাতে বদলানোও যায়।
- **Load from BOM / Booking:** এক্সেসরিজ লাইন এখন মাস্টারের আইটেম, BOM-এর সাপ্লায়ার আর সাপ্লায়ারের দাম নিয়ে আসে (দাম না থাকলে BOM-এর রেট)।
- **API:** `GET /merchandise/orders/{order}/budget/item?item_id=&currency=&rate=` (আইটেম, BOM, offers, pick)। শিটের `catalog` (items, suppliers) আর `accessory_sections`।
- **টেবিল:** `merch_budget_lines` + `item_id`, `item_code`, `supplier_id` (মাইগ্রেশন `2027_03_31_000300`)।
- **টেস্ট:** `MerchandiseCostingTest::test_an_accessory_line_takes_its_item_from_the_master_and_its_rate_from_the_supplier`। যাচাই হয়:
  - Koyo অনুমোদিত কোটেশনের সর্বশেষ revision ০.০৪৫।
  - Apex-এর PO ৪.২ টাকা ÷ ১২০ = ০.০৩৫, যা তার খোলা কোটেশনকে হারায়।
  - BOM-এ কোডে নাম থাকা Apex বাছা হয়।
  - BOM-এ না থাকা আইটেম সেরা দাম পায়।
  - EUR দাম রেটে বসে না।
  - নামে মেলা Hanger।
  - Save-এ বিবরণ, কোড আর UOM মাস্টার থেকে আসে।
  - Costing ৯টা পাস।
- **UI চেক (dev-এ রাখা):** BGT-2026-000002-এ Conv. Loc. ১২০ দিয়ে Sewing Accessories-এ "Button 18L 4-Hole" বাছা হয়েছে। এল TR-BTN-18L, KOYO TRIMS SOLUTION JP, রেট ০.০০৭১ (PO-ACC-2026100001-এর ০.৮৫ টাকা ÷ ১২০)। Save করা হয়েছে, error ০।
