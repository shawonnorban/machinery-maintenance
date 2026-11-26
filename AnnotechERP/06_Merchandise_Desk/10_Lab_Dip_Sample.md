# Lab Dip & Sample

> **স্ট্যাটাস:** ✅ সম্পন্ন (আটটা স্ক্রিনই চালু: E1 Lab Dip Programme, E2 Lab Dip Submission, E3 Sample Programme, E4 Print Design Sheet, E5 Sample Production Entry, E6 Sample Delivery Challan, E7 Buyer Sample Feedback, E8 Collar & Cuff Measurement Sheet)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/10`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Lab Dip Programme | `/m/merchandising/labdip-program` | 3 | 15 | 1 |
| 2 | Lab Dip Submission | `/m/merchandising/labdip-submission` | 2 | 9 | 0 |
| 3 | Sample Programme | `/m/merchandising/sample-program` | 3 | 15 | 1 |
| 4 | Print Design Sheet | `/m/merchandising/print-design` | 1 | 19 | 1 |
| 5 | Sample Production Entry | `/m/merchandising/sample-production` | 1 | 4 | 0 |
| 6 | Sample Delivery Challan | `/m/merchandising/sample-delivery-challan` | 3 | 16 | 1 |
| 7 | Buyer Sample Feedback | `/m/merchandising/buyer-feedback-for-sample` | 3 | 16 | 1 |
| 8 | Collar & Cuff Measurement Sheet | `/m/merchandising/collar-cuff-measurement` | 3 | 11 | 2 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Lab Dip Programme

`/m/merchandising/labdip-program`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Acc | select |  | All |
| Buyer | select |  | All |
| Request To | select |  | All |
| Date of Request from | date |  |  |
| Date of Request to | date |  |  |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Lab dip requests with their buyer, request date, lab, requester and shade | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No., Buyer, Buyer Acc, Date of Request, Request To, Request By, Attn. User, Shades, ACTION
- বাটন/অ্যাকশন: Labdip Specification, New lab dip program, Clear, Export, Program No., Buyer, Buyer Acc, Date of Request, Request To, Request By, Attn. User, Shades

**ট্যাব: Labdip Specification**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search programs | search |  | Search… |
- সেকশন: Programs, Labdip Specification
- বাটন/অ্যাকশন: Labdip Specification, New lab dip program

**ট্যাব: New lab dip program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Acc* | select | ✔ | Select… |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Date of Request* | date | ✔ |  |
| Request To* | select | ✔ | Select… |
| Remarks | textarea |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- সেকশন: New lab dip program
- বাটন/অ্যাকশন: Labdip Specification, New lab dip program, Select…, Reset, Save lab dip program

**ডায়ালগ: New lab dip program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer Acc* | select | ✔ | Select… |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Date of Request* | date | ✔ |  |
| Request To* | select | ✔ | Select… |
| Remarks | textarea |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- খালি Save-এ মেসেজ: Date of Request is required.

#### Lab Dip Submission

`/m/merchandising/labdip-submission`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Account | select |  | All |
| Style No. | search |  |  |
| Order No. | search |  |  |
| Target Date from | date |  |  |
| Target Date to | date |  |  |
| Status | select |  | All, Pending, Submitted, Approved, Rejected, Resubmit |
| Search Lab dips submitted to the buyer with their style, order, program, dates a | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style No., Order No., Program No., Colour, Reg. Date, Target Date, Submitted, Feedback
- বাটন/অ্যাকশন: Labdip Specification, Clear, Export, Style No., Order No., Program No., Colour, Reg. Date, Target Date, Submitted, Feedback

**ট্যাব: Labdip Specification**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search programs | search |  | Search… |
- সেকশন: Programs, Labdip Specification
- বাটন/অ্যাকশন: Labdip Specification

#### Sample Programme

`/m/merchandising/sample-program`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Job/Work Style# | search |  | 0376 |
| Prog. Date from | date |  |  |
| Prog. Date to | date |  |  |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Sample programmes with their date, programme number, operation reference, | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Prog. Date, Smp Prog#, Tran Ref#, Job/Work Style#, Order#, Buyer A/C, Lines, R.Qty, Remarks, ACTION
- বাটন/অ্যাকশন: Sample Lines, New program, Clear, Export, Prog. Date, Smp Prog#, Tran Ref#, Job/Work Style#, Order#, Buyer A/C, Lines, R.Qty, Remarks

**ট্যাব: Sample Lines**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search programs | search |  | Search… |
- সেকশন: Programs, Sample Specification
- বাটন/অ্যাকশন: Sample Lines, New program

**ট্যাব: New program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prog. Date* | date | ✔ |  |
| Tran Ref# | text |  | BK-BUL-00012 |
| Buyer A/C | select |  | Select… |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Work Style# | text |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New sample program
- বাটন/অ্যাকশন: Sample Lines, New program, Reset

**ডায়ালগ: New program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Prog. Date* | date | ✔ |  |
| Tran Ref# | text |  |  |
| Buyer A/C | select |  | Select… |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Work Style# | text |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |

#### Print Design Sheet

`/m/merchandising/print-design`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Acc* | select | ✔ | Select… |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Program No. | text |  | Assigned on save |
| Date of Requisition* | date | ✔ |  |
| Request To* | text | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Material Description | text |  |  |
| Order No. | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Nature of Work | text |  |  |
| Shipment Date | date |  |  |
| Item | text |  |  |
| Old Style # | text |  |  |
| Print Type | text |  |  |
| Image | text |  | Artwork link |
| Print Color | text |  |  |
| Req. Qty | number |  |  |
| Unit | text |  |  |
| Priority | select |  | Select…, Low, Normal, High, Urgent |
| Sp. Instruction | textarea |  |  |
- সেকশন: Program Parameter
- বাটন/অ্যাকশন: Select…, Add, Cancel, Clear, Save program

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer Acc* | select | ✔ | Select… |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Program No. | text |  |  |
| Date of Requisition* | date | ✔ |  |
| Request To* | text | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Material Description | text |  |  |
| Order No. | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Nature of Work | text |  |  |
| Shipment Date | date |  |  |
| Item | text |  |  |
| Old Style # | text |  |  |
| Print Type | text |  |  |
| Image | text |  |  |
| Print Color | text |  |  |
| Req. Qty | number |  |  |
| Unit | text |  |  |
| Priority | select |  | Select…, Low, Normal, High, Urgent |
| Sp. Instruction | textarea |  |  |
- খালি Save-এ মেসেজ: A program with no styles asks for nothing — add at least one line.

#### Sample Production Entry

`/m/merchandising/sample-production`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
| Batch# | select |  |  |
| Lindex | radio |  |  |
| Other | radio |  |  |
- সেকশন: Program Parameter, Production Order List, Sample Producton Entry, Sample Producton & Delivery, Card Format
- বাটন/অ্যাকশন: Hide Production Order List, Add To Batch, Print, New Batch, Save, Submit

#### Sample Delivery Challan

`/m/merchandising/sample-delivery-challan`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Creation Date from | date |  |  |
| Creation Date to | date |  |  |
| Challan# | search |  |  |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Buyer Office | select |  | All |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Sample delivery challans with their dates, buyer office, vehicle, carrier | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Creation Date, Buyer Office, Challan#, Chln Date, Vehicle#, Carrier, Items, ACTION
- বাটন/অ্যাকশন: Challan Items, New challan, Clear, Export, Creation Date, Buyer Office, Challan#, Chln Date, Vehicle#, Carrier, Items

**ট্যাব: Challan Items**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search challans | search |  | Search… |
- সেকশন: Challans, Items
- বাটন/অ্যাকশন: Challan Items, New challan

**ট্যাব: New challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan Date* | date | ✔ |  |
| Buyer Office | select |  | Select…, LPP S.A., TEX EBO |
| Vehicle# | text |  |  |
| Carrier | text |  | DHL |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New challan
- বাটন/অ্যাকশন: Challan Items, New challan, Reset, Save challan

**ডায়ালগ: New challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Challan Date* | date | ✔ |  |
| Buyer Office | select |  | Select…, LPP S.A., TEX EBO |
| Vehicle# | text |  |  |
| Carrier | text |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Challan Date is required.

#### Buyer Sample Feedback

`/m/merchandising/buyer-feedback-for-sample`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Job/Order# | search |  |  |
| Sample Type | select |  | All |
| Batch Submit DT from | date |  |  |
| Batch Submit DT to | date |  |  |
| Status | select |  | All, Pending, Submitted, Approved, Rejected, Resubmit |
| Bulk Shade | select |  | All, Approved, Not approved |
| Search Submitted sample batches with the buyer's verdict and whether bulk shade  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Batch#, Batch Submit DT, Buyer A/C, Job#, Order#, Sample Type, Bulk Shade Approved?, Comments, ACTION
- বাটন/অ্যাকশন: Bulk Shade Approval, New feedback, Clear, Export, Batch#, Batch Submit DT, Buyer A/C, Job#, Order#, Sample Type, Bulk Shade Approved?, Comments

**ট্যাব: Bulk Shade Approval**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyer accounts | search |  | Search… |
- সেকশন: Buyer A/C, Submitted Samples
- বাটন/অ্যাকশন: Bulk Shade Approval, New feedback

**ট্যাব: New feedback**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Job/Order# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Batch Submit DT* | date | ✔ |  |
| Status | select |  | Select…, Pending, Submitted, Approved, Rejected, Resubmit |
| Bulk Shade Approved? A separate decision from the sample being accepted. | checkbox |  |  |
| Comments | textarea |  |  |
- সেকশন: New feedback
- বাটন/অ্যাকশন: Bulk Shade Approval, New feedback, Select…, Reset, Save feedback

**ডায়ালগ: New feedback**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Job/Order# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Batch Submit DT* | date | ✔ |  |
| Status | select |  | Select…, Pending, Submitted, Approved, Rejected, Resubmit |
| Bulk Shade Approved? A separate decision from the sample being accepted. | checkbox |  |  |
| Comments | textarea |  |  |
- খালি Save-এ মেসেজ: Batch Submit DT is required.

#### Collar & Cuff Measurement Sheet

`/m/merchandising/collar-cuff-measurement`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Account | select |  | All |
| Style # | search |  |  |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Collar and cuff measurement charts per style, sub-style, item and model | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer A/C, Style Name, Style #, Sub Style#, Item Name, Model #, Measurements, ACTION
- বাটন/অ্যাকশন: Add Measurement, New measurement chart, Clear, Export, Buyer A/C, Style Name, Style #, Sub Style#, Item Name, Model #, Measurements

**ট্যাব: Add Measurement**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search charts | search |  | Search… |
- সেকশন: Charts, Measurements
- বাটন/অ্যাকশন: Add Measurement, New measurement chart

**ট্যাব: New measurement chart**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Account | select |  | Select… |
| Sub Style# | text |  |  |
| Item Name* | text | ✔ | Collar |
| Model # | text |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- সেকশন: New measurement chart
- বাটন/অ্যাকশন: Add Measurement, New measurement chart, Select…, Reset, Save measurement chart

**ডায়ালগ: Add Measurement**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search charts | search |  |  |

**ডায়ালগ: New measurement chart**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer Account | select |  | Select… |
| Sub Style# | text |  |  |
| Item Name* | text | ✔ |  |
| Model # | text |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
- খালি Save-এ মেসেজ: Style is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- স্ক্রিন কিট (`ResourceDefinition`, `masterScreen`, `PickPanel`) আর CAD Consumption-এর লাইন-এডিটরের ধাঁচ (Add ডায়ালগ, জায়গায় বদল, Revert/Save)।
- মাস্টার: বায়ার আর বায়ার অফিস (Buyer Acc), সেকশন (`sections`: নিজস্ব ল্যাব), Material Procurement-এর সাপ্লায়ার (`mp_suppliers`: মনোনীত হাউস), কোম্পানির ইউজার (Request By, Attn. User)।
- স্টাইল আর অর্ডার (06/05, 06/07), যাদের জন্য শেড চাওয়া হয়।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_measure_charts` (E8):** style_id, buyer_office_id, sub_style, item_name (Collar, Cuff …), model_no, status, created_by। **`merch_measure_points`:** chart_id, size, point (Point of Measure), value, tolerance, uom, sort_order।
- **`merch_sample_feedback` (E7):** order_id (Job/Order#), buyer_office_id, sample_type_id, batch_id (নিজে থেকে), submit_date, status (PENDING, SUBMITTED, APPROVED, REJECTED, RESUBMIT), bulk_shade_approved, comments, created_by।
- **`merch_sample_challans` (E6):** challan_no (SDC-00001, Brickland-এর মতো), challan_date, buyer_office_id, vehicle_no, carrier, status (DRAFT … CANCELLED), remarks, created_by। **`merch_sample_challan_items`:** challan_id, description, sample_type_id, qty, uom, sort_order।
- **`merch_sample_batches` (E5):** batch_no (SMB-00001, Brickland-এর মতো), batch_date, card_format (LINDEX, OTHER), status (DRAFT, SUBMITTED), submitted_at, submitted_by, created_by। **`merch_sample_lines`-এ যোগ:** batch_id, sewn_qty, delivered_qty, rejected_qty, batch_remarks।
- **`merch_print_programs` (E4):** program_no (PD-YYYY00001), buyer_id, buyer_office_id, requisition_date, program_by (ইউজার), request_to (লেখা), created_by। **`merch_print_lines`:** program_id, style_id, order_id, sample_type_id, material_description, nature_of_work, shipment_date, item, old_style_no, print_type, print_color, req_qty, unit, priority (LOW, NORMAL, HIGH, URGENT), instruction, artwork_file_id আর artwork_name (আপলোড করা আর্টওয়ার্ক, শেয়ার্ড Files মডিউলে `merch_print_artwork` ধরনে), sort_order।
- **`merch_sample_programs` (E3):** program_no (SMP-YYYY00001, Brickland-এর ধাঁচে), program_date, tran_ref, buyer_office_id, order_id, work_style_no, status (DRAFT … CANCELLED), remarks, created_by।
- **`merch_sample_lines` (E3):** program_id, sample_type_id (Sample Type মাস্টার), item, colour, size, req_qty, unit, remarks, status (PLANNED, IN_BATCH, PRODUCED, DELIVERED), batch_no (Sample Production ভরবে), sort_order।
- **`merch_labdip_programs` (E1):** program_no (LD-YYYY-000001, কোম্পানিতে ইউনিক), buyer_id, buyer_office_id, request_date, request_section_id অথবা request_supplier_id (ল্যাব), request_by, attn_user_id, remarks, status (DRAFT, SUBMITTED, IN_PROGRESS, SENT, APPROVED, REJECTED, CANCELLED), created_by।
- **`merch_labdip_shades` (E1):** program_id, style_id, order_id, colour, pantone, fabric, light_source (D65, TL84, CWF, U30, U35, A, UV), target_date, sort_order; E2-এ status (PENDING, SUBMITTED, APPROVED, REJECTED, RESUBMIT), submitted_on, decided_on, feedback।

## ৫. Workflow / Status ও Business Rules

- **Lab Dip Programme (E1):**
  - Buyer, Buyer Acc, Date of Request, Request To আর Request By বাধ্যতামূলক, Brickland-এর বার্তাসহ ("Date of Request is required." ইত্যাদি)। Status না দিলে Draft।
  - তৈরির সময় পরের নম্বর পায়, advisory lock-এর ভেতরে।
  - Request To একটাই ঘর: নিজস্ব সেকশন অথবা মনোনীত হাউস (`SECTION:<id>` / `SUPPLIER:<id>`)। এডিটে একটা থেকে আরেকটায় বদলানো যায়।
  - শেড: রং বাধ্যতামূলক; একই প্রোগ্রামে একই স্টাইলের জন্য একই রং দুবার নয় (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)। স্টাইল আর অর্ডার প্রোগ্রামের বায়ারেরই হতে হবে।
  - শেড একসাথে সেভ হয়, কিন্তু প্রতিটা শেড তার id রাখে। বাদ দেওয়া শেড মুছে যায়, যদি না সেটা বায়ারের কাছে চলে গিয়ে থাকে; অন্য কেউ মুছে ফেলা শেড আবার সেভ করলে বার্তা আসে। প্রোগ্রাম মুছলে শেডও মোছে।
- **Collar & Cuff Measurement Sheet (E8):**
  - Style আর Item Name বাধ্যতামূলক ("Style is required.", "Item Name is required.")। Buyer Account ফাঁকা রাখলে স্টাইলের বায়ার অ্যাকাউন্ট বসে। চার্টের নাম "STYLE · Item" (যেমন "637PK · Collar")।
  - Add measurement সাথে সাথে point সেভ করে; Size, Point of Measure আর Value বাধ্যতামূলক। একই সাইজে একই Point of Measure দুবার নয় (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না): "That point already exists. Change it, or edit the existing record."।
  - Value, Tolerance, UOM জায়গায় বদলে Save; বাদ দেওয়া point মোছে। চার্ট মুছলে point-ও মোছে।
- **Buyer Sample Feedback (E7):**
  - Batch Submit DT বাধ্যতামূলক ("Batch Submit DT is required.")। Status না দিলে Submitted।
  - Buyer A/C ফাঁকা রাখলে অর্ডারের বায়ার অ্যাকাউন্ট বসে। Batch# নিজে থেকে: সেই অর্ডারের সেই Sample Type বানানো সর্বশেষ জমা হওয়া production batch (খসড়া ব্যাচ ধরা হয় না); অর্ডার বা টাইপ বদলালে আবার খোঁজা হয়।
  - Bulk Shade Approved? স্যাম্পল গৃহীত হওয়া থেকে আলাদা সিদ্ধান্ত: Bulk Shade Approval থেকে Release / Hold সাথে সাথে বসে, ফিডব্যাকের বাকি অংশ অক্ষত থাকে।
- **Sample Delivery Challan (E6):**
  - Challan Date বাধ্যতামূলক ("Challan Date is required.")। তৈরির সময় SDC-00001 নম্বর পায়; Status না দিলে Draft।
  - Add item সাথে সাথে item সেভ করে ("Added …"); Description আর Qty (শূন্যের বেশি) বাধ্যতামূলক ("Description is required.", "Qty is required.")। Description, Qty, UOM জায়গায় বদলে Save; বাদ দেওয়া item মোছে। চালান মুছলে item-ও মোছে।
- **Sample Production Entry (E5):**
  - New Batch তারিখ দিয়ে ব্যাচ খোলে ("Opened batch SMB-00001")। শুধু কোনো ব্যাচে না থাকা Planned লাইন টিক দিয়ে Add To Batch করা যায় ("Added n line(s) to SMB-…"); লাইন তখন In batch, Sample Programme-এ আর বদলায় না।
  - ব্যাচ থেকে সরালে লাইন আবার Planned, পরিমাণ শূন্য।
  - Save: Card Format আর প্রতিটা লাইনের S.Qty, D.Qty, Rj.Qty, Remarks। D.Qty + Rj.Qty কখনো S.Qty-র বেশি নয় ("Delivered plus rejected cannot exceed what was sewn.")।
  - Submit (নিশ্চিতকরণ ডায়ালগসহ): ব্যাচ বন্ধ, লাইন Produced; খালি ব্যাচ জমা হয় না। জমা হওয়া ব্যাচে আর যোগ, সরানো বা সেভ হয় না।
  - Print: বাছাই করা Card Format (Lindex / Other)-এ প্রতিটা লাইনের একটা স্যাম্পল কার্ড, আলাদা প্রিন্ট উইন্ডোতে।
- **Print Design Sheet (E4):**
  - Program Parameter-এ Buyer Acc, Buyer, Date of Requisition, Program By, Request To আর Company বাধ্যতামূলক, Brickland-এর ধাঁচের বার্তাসহ। Company শুধু চলতি কোম্পানি। তৈরির সময় PD-YYYY00001 নম্বর পায়।
  - Add line সাথে সাথে লাইন সেভ করে; Material Description, Sample Type আর Shipment Date লাগে ("… is needed before the row can be added.")। স্টাইল আর অর্ডার প্রোগ্রামের বায়ারেরই হতে হবে; অর্ডার বাছাই করলে শিপমেন্টের তারিখ ফাঁকা থাকলে অর্ডারেরটা বসে।
  - Image/Artwork আপলোড হয় (লিংক নয়): JPG, PNG বা WebP, ১০ MB পর্যন্ত, ব্যক্তিগত ফাইল হিসেবে; লাইনের থাম্বনেইল থেকে বদলানো যায়। ফাইল দেখতে `merchandise.sample.view`, বদলাতে `merchandise.sample.manage` লাগে।
  - লাইনের তালিকা জায়গায় বদলে Save; বাদ দেওয়া লাইন মোছে। প্রোগ্রাম মুছলে লাইনও মোছে।
- **Sample Programme (E3):**
  - Prog. Date বাধ্যতামূলক ("Prog. Date is required.")। তৈরির সময় SMP-YYYY00001 নম্বর পায়, advisory lock-এর ভেতরে। Status না দিলে Draft।
  - Job/Work Style# = লেখা Work Style#, না থাকলে অর্ডারের work style বা job। লাইনের Job# (Ord#) = "529 (LPP-2026-000008)"।
  - Add line সাথে সাথে লাইন সেভ করে ("Planned 3 × Proto Sample")। Smp. Type আর R.Qty (শূন্যের বেশি) বাধ্যতামূলক, Brickland-এর বার্তাসহ।
  - Item, Color, Size, R.Qty, Unit, Remarks জায়গায় বদলে Save; বাদ দেওয়া লাইন মোছে।
  - প্রোডাকশন ব্যাচে ওঠা লাইন (batch_no বা Planned ছাড়া অবস্থা) দেখা যায় কিন্তু বদলায় না, সরানো যায় না; এমন লাইনসহ প্রোগ্রামও মোছা যায় না।
  - Sample Type-এর ডিফল্ট (Proto, Fit, Size Set, PP, TOP, Shipment, Development, Styling, Counter, IC, Custom) প্রথমবার লাইন খোলার সময় তৈরি হয়।
- **Lab Dip Submission (E2):**
  - প্রতিটা শেডের অবস্থা: Pending → Submitted → Approved / Rejected / Resubmit। Pending ছাড়া যেকোনো অবস্থায় জমার তারিখ লাগে; সেটা প্রোগ্রামের অনুরোধের তারিখের আগে বা আজকের পরে হতে পারে না। জমার তারিখ দিলে Pending শেড নিজে থেকেই Submitted হয়।
  - বায়ারের রায় (Approved, Rejected, Resubmit) যেদিন লেখা হয় সেদিনের তারিখ পায়, আর রায় না বদলালে সেই তারিখ থাকে। বায়ারের মন্তব্য লেখা যায়।
  - Lab Dip Programme-এর প্যানেল একই শেড সেভ করে কিন্তু জমার তথ্য পাঠায় না, তাই সেটা অক্ষত থাকে। বায়ারের কাছে যাওয়া শেড কোনো প্যানেল থেকেই সরানো যায় না।
  - Style Progress Board-এর "Lab Dips" কলামে এখন স্টাইলের শেডের সংখ্যা আসে, আর "Budgets" কলামে বাজেট শিটের সংখ্যা।

## ৬. API ও পারমিশন

- `GET/POST /erp/merch-labdip-programs`, `GET/PATCH/DELETE /erp/merch-labdip-programs/{id}`: স্ক্রিন কিট।
- `GET/PUT /merchandise/labdip-programs/{program}/shades`: শেড, সাথে বায়ারের স্টাইল, অর্ডার আর লাইট সোর্সের তালিকা।
- `/merchandise/lookups`-এ এখন `sections`-ও আসে।
- `GET/POST /erp/merch-print-programs`, `GET/PATCH/DELETE /erp/merch-print-programs/{id}`; `GET/POST/PUT /merchandise/print-programs/{program}/lines` (POST = Add line, multipart, আর্টওয়ার্কসহ), `POST /merchandise/print-programs/{program}/lines/{line}/artwork` (E4)। ফ্রন্টএন্ডে আপলোড যায় `/api/merchandise/print-lines/...` route handler দিয়ে (server action-এর ১ MB সীমা এড়াতে)।
- `GET/POST /erp/merch-measure-charts`, `GET/PATCH/DELETE /erp/merch-measure-charts/{id}`; `GET/POST/PUT /merchandise/measure-charts/{chart}/points` (E8)।
- `GET/POST /erp/merch-sample-feedback`, `GET/PATCH/DELETE /erp/merch-sample-feedback/{id}` (E7; Bulk Shade-এর Release/Hold হলো PATCH-এ শুধু `bulk_shade_approved`)।
- `GET/POST /erp/merch-sample-challans`, `GET/PATCH/DELETE /erp/merch-sample-challans/{id}`; `GET/POST/PUT /merchandise/sample-challans/{challan}/items` (E6)।
- `GET /merchandise/sample-production`; `POST /merchandise/sample-batches`; `GET/PUT /merchandise/sample-batches/{batch}`; `POST /merchandise/sample-batches/{batch}/lines`, `DELETE …/lines/{line}`; `POST /merchandise/sample-batches/{batch}/submit` (E5)।
- `GET/POST /erp/merch-sample-programs`, `GET/PATCH/DELETE /erp/merch-sample-programs/{id}`; `GET/POST/PUT /merchandise/sample-programs/{program}/lines` (E3: POST = Add line, PUT = জায়গায় বদল আর Remove)।
- `GET /merchandise/labdip-submissions` (E2 Records); শেডের PUT-এ এখন `status`, `submitted_on`, `feedback`-ও নেওয়া হয়।
- পারমিশন: `merchandise.sample.view`, `merchandise.sample.manage`। FACTORY_ADMIN দুটোই পায়, FACTORY_MANAGER আর STORE_MANAGER শুধু view।
- মেনু: ফোল্ডার `MERCHANDISE.LAB_DIP_SAMPLE` ("LAB DIP & SAMPLE"), তার ভেতরে `MERCHANDISE.LABDIP_PROGRAM` → `/merchandise-desk/labdip-program`, `MERCHANDISE.LABDIP_SUBMISSION` → `/merchandise-desk/labdip-submission`, `MERCHANDISE.SAMPLE_PROGRAM` → `/merchandise-desk/sample-program`, `MERCHANDISE.PRINT_DESIGN` → `/merchandise-desk/print-design`, `MERCHANDISE.SAMPLE_PRODUCTION` → `/merchandise-desk/sample-production`, `MERCHANDISE.SAMPLE_CHALLAN` → `/merchandise-desk/sample-delivery-challan`, `MERCHANDISE.SAMPLE_FEEDBACK` → `/merchandise-desk/buyer-feedback-for-sample` আর `MERCHANDISE.MEASURE_CHART` → `/merchandise-desk/collar-cuff-measurement`।

## ৭. Frontend পেজ (লাইট থিম)

- **Collar & Cuff Measurement Sheet** (`/merchandise-desk/collar-cuff-measurement`):
  - **Records:** ফিল্টার Buyer Account, Style # (লিখে Enter), Status। কলাম: Buyer A/C, Style Name, Style #, Sub Style#, Item Name, Model #, Measurements, Status; সারিতে Edit (ট্যাব) আর Remove।
  - **Add Measurement** (`?tab=measure&chart=<STYLE · Item>`): বাঁয়ে Charts, ডানে "Measurements — …": Size, Point of Measure, Value / Tolerance / UOM (জায়গায় বদল), Status ("Edited"), Remove; উপরে Export আর Add measurement (ডায়ালগ: Size*, Point of Measure*, Value*, Tolerance, UOM; Reset আর Add measurement), নিচে Revert আর Save।
  - **New measurement chart:** Style*, Buyer Account (হিন্টসহ), Sub Style#, Item Name* (Collar), Model #, Status; উপরে Brickland-এর লেখা "Points of measure, sizes and tolerances are entered on the Add Measurement panel."।
- **Buyer Sample Feedback** (`/merchandise-desk/buyer-feedback-for-sample`):
  - **Records:** ফিল্টার Buyer A/C, Job/Order# (লিখে Enter), Sample Type, Batch Submit DT from/to, Status, Bulk Shade (Approved / Not approved)। কলাম: Batch#, Batch Submit DT, Buyer A/C, Job#, Order#, Sample Type, Bulk Shade Approved? (Yes/No), Comments, Status; সারিতে Edit (ট্যাব) আর Remove।
  - **Bulk Shade Approval** (`?tab=bulk&account=<Buyer A/C>`): বাঁয়ে Buyer A/C (ফিডব্যাকের সংখ্যাসহ), ডানে "Submitted Samples — …": Batch#, Batch Submit DT, Job#, Order#, Sample Type, Status, Comments, Bulk Shade Approved?, আর প্রতিটায় Release বা Hold।
  - **New feedback:** Job/Order#, Buyer A/C (হিন্টসহ), Sample Type, Batch Submit DT*, Status, Bulk Shade Approved? ("A separate decision from the sample being accepted."), Comments; বাটন "Save feedback"।
- **Sample Delivery Challan** (`/merchandise-desk/sample-delivery-challan`):
  - **Records:** ফিল্টার Creation Date from/to, Challan# (লিখে Enter), Challan Date from/to, Buyer Office, Status। কলাম: Creation Date, Buyer Office, Challan#, Chln Date, Vehicle#, Carrier, Items, Status; সারিতে Edit (ট্যাব) আর Remove।
  - **Challan Items** (`?tab=items&challan=<Challan#>`): বাঁয়ে Challans, ডানে "Items — SDC-…": Description, Qty, UOM (জায়গায় বদল), Sample Type, Status (বদলানো কিন্তু সেভ না হলে "Edited"), Remove; উপরে Export আর Add item (ডায়ালগ "Add item — SDC-…": Description*, Sample Type, Qty*, UOM; Reset আর Add item), নিচে Revert আর Save।
  - **New challan:** Challan Date*, Buyer Office, Vehicle#, Carrier (DHL), Status, Remarks; বাটন "Save challan"।
- **Sample Production Entry** (`/merchandise-desk/sample-production`, `?batch=<Batch#>`): Brickland-এর মতো একটাই কাজের পাতা, ট্যাব নেই।
  - বাঁয়ে Program Parameter (Date — "New Batch opens a batch on this date.", Batch# — "-- New batch --" আর "SMB-00001 — তারিখ · অবস্থা") আর Production Order List (Job# (Order#), Smp. Type, লাইনের সংখ্যা; ক্লিকে নিচের তালিকা ছাঁকে)। "Hide/Show Production Order List" দিয়ে বাঁ পাশ লুকানো যায়।
  - ডানে Sample Production Entry: Planned লাইন টিক-বক্সসহ (Buyer A/C, Job# (Ord#), Smp. Type, Item, Color, Size, R.Qty, Unit, Remarks), "n selected" আর Add To Batch; তার নিচে Sample Production & Delivery: ব্যাচের লাইন, S.Qty / D.Qty / Rj.Qty / Remarks জায়গায় বদল, লাইন সরানোর বাটন, Total Record আর মোট S/D/Rj.Qty।
  - নিচে Card Format (Lindex / Other), Print, New Batch, Save, Submit আর নিয়মের লেখা।
- **Print Design Sheet** (`/merchandise-desk/print-design`), অন্য স্ক্রিনের মতো ট্যাবে (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১০-১৩):
  - **Records:** ফিল্টার Buyer Acc, Buyer, Date of Requisition from/to। কলাম: Program No., Buyer, Buyer Acc, Date of Requisition, Program By, Request To, Lines, Qty.; সারিতে Edit (ট্যাব) আর Remove।
  - **Style & Order Lines** (`?tab=lines&program=<Program No.>`): বাঁয়ে Programs, ডানে "List of Style, Order and Instruction — PD-…": Style #, Order #, Sample Type (বাছাই), Item, Qty., Unit, Image (থাম্বনেইল, ক্লিকে বড়, পাশে আপলোড/বদলানোর বাটন), Sp. Instruction, Remove; উপরে Export আর Add line (ডায়ালগে Brickland-এর সব ঘর আর Image / Artwork আপলোড), নিচে Revert আর Save।
  - **New program:** Program Parameter — Buyer Acc*, Buyer*, Date of Requisition*, Program By*, Request To*, Company*; বাটন "Save program"।
- **Sample Programme** (`/merchandise-desk/sample-program`):
  - **Records:** ফিল্টার Buyer A/C, Job/Work Style# (লিখে Enter), Prog. Date from/to, Status। কলাম: Prog. Date, Smp Prog#, Tran Ref#, Job/Work Style#, Order#, Buyer A/C, Lines, R.Qty, Remarks, Status; সারিতে Edit (ট্যাব) আর Remove।
  - **Sample Lines** (`?tab=lines&program=<Smp Prog#>`): বাঁয়ে Programs, ডানে "Sample Specification — SMP-…": Smp. Type, Job# (Ord#), Item, Color, Size, R.Qty, Unit (জায়গায় বদল), Batch#, Remarks, Status, Remove; উপরে Export আর Add line (ডায়ালগ "Add line — SMP-…": Smp. Type*, Item, Color, Size, R.Qty*, Unit, Remarks; Reset আর Add line), নিচে Revert আর Save।
  - **New program** (ট্যাব "New program", ফর্ম "New sample program", সেভ বাটন "New program"): Prog. Date*, Tran Ref# (হিন্টসহ), Buyer A/C, Job/Work Style#, Work Style#, Status, Remarks।
- **Lab Dip Submission** (`/merchandise-desk/labdip-submission`):
  - **Records:** শুধু দেখা যায় (Brickland-এর মতো ACTION কলাম নেই)। ফিল্টার Buyer Account, Style No. আর Order No. (লিখে Enter), Target Date from/to, Status। কলাম: Style No., Order No., Program No., Colour, Reg. Date, Target Date, Submitted, Feedback।
  - **Labdip Specification** (`?tab=specification&program=<Program No.>`): Lab Dip Programme-এর একই প্যানেল, সাথে প্রতিটা শেডে Submitted (তারিখ), Feedback (অবস্থা, রায়ের তারিখসহ) আর Buyer Comments।
- `UrlFilters`-এ এখন `type: "text"` ফিল্টার আছে (Enter চাপলে বা ঘর ছাড়লে কাজ করে)।
- **Lab Dip Programme** (`/merchandise-desk/labdip-program`):
  - **Records:** ফিল্টার Buyer Acc, Buyer, Request To, Date of Request from/to, Status। কলাম: Program No., Buyer, Buyer Acc, Date of Request, Request To, Request By, Attn. User, Shades; সারিতে Edit (ট্যাব) আর Remove।
  - **Labdip Specification** (`?tab=specification&program=<Program No.>`): বাঁয়ে Programs (খোঁজা যায়, শেডের সংখ্যাসহ)। ডানে শেড: Colour, Pantone, Style, Order, Fabric, Light Source আর Target Date (এখানেই বদলানো যায়), Status (সেভ না হওয়া শেডে New), Remove। উপরে Export আর Add shade, নিচে Revert আর Save।
  - **New lab dip program / Edit** (৩ কলাম): Buyer Acc*, Buyer*, Date of Request*, Request To* (হিন্টসহ), Request By*, Attn. User, Remarks, Status; উপরে Brickland-এর লেখা "The shades themselves — colour, Pantone, fabric, light source and target date — are added on the Labdip Specification panel."

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| E1 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_18_000100_create_merchandise_labdip_programs.php`, `Models/LabdipProgram.php`, `Models/LabdipShade.php`, `Resources/LabdipProgramResource.php` (`ResourceRegistry.php`-এ যোগ), `Http/Controllers/Api/LabdipShadeApiController.php`, `Routes/api.php`, `MerchandiseLookupsApiController.php` (`sections`) |
| E2 | `Database/Migrations/2026_11_18_000200_add_submission_to_merchandise_labdip_shades.php`, `LabdipShade.php` (STATUSES, VERDICTS), `LabdipShadeApiController.php` (submissions, জমার নিয়ম), `StyleOrderBoardApiController.php` (lab_dips, budgets); `sample.js` (`labdipSubmission`, `specPanel`), `labdip-shades.jsx` (`submission`), `components/erp/url-filters.jsx` (`text`) |
| E3 | `Database/Migrations/2026_11_18_000300_create_merchandise_sample_programs.php`, `Models/SampleProgram.php`, `Models/SampleLine.php`, `Resources/SampleProgramResource.php`, `Http/Controllers/Api/SampleLineApiController.php`; `sample.js` (`sampleProgram`), `components/merchandise/sample-lines.jsx`, `actions.js` (`addSampleLine`, `saveSampleLines`) |
| E4 | `Database/Migrations/2026_11_18_000400_create_merchandise_print_programs.php`, `…000500_upload_artwork_on_merchandise_print_lines.php`, `Models/PrintProgram.php`, `Models/PrintLine.php`, `Resources/PrintProgramResource.php`, `Http/Controllers/Api/PrintLineApiController.php`, `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (`merch_print_artwork`); `sample.js` (`printDesign`), `components/merchandise/print-lines.jsx`, `actions.js` (`savePrintLines`), `frontend/src/app/api/merchandise/print-lines/[program]/route.js`, `…/[line]/artwork/route.js`; `AnnotechERP/tools/app_check/ui.mjs` (`upload`, `chooser`, `html`) |
| E5 | `Database/Migrations/2026_11_18_000600_create_merchandise_sample_batches.php`, `Models/SampleBatch.php`, `SampleLine.php` (ব্যাচের ঘর), `Http/Controllers/Api/SampleProductionApiController.php`; `sample.js` (`sampleProduction`), `components/merchandise/sample-production.jsx`, `actions.js` (`loadSampleProduction`, `showSampleBatch`, `openSampleBatch`, `addToSampleBatch`, `dropFromSampleBatch`, `saveSampleBatch`, `submitSampleBatch`) |
| E6 | `Database/Migrations/2026_11_18_000700_create_merchandise_sample_challans.php`, `Models/SampleChallan.php`, `Models/SampleChallanItem.php`, `Resources/SampleChallanResource.php`, `Http/Controllers/Api/SampleChallanItemApiController.php`; `sample.js` (`sampleChallan`), `components/merchandise/challan-items.jsx`, `actions.js` (`addChallanItem`, `saveChallanItems`) |
| E7 | `Database/Migrations/2026_11_18_000800_create_merchandise_sample_feedback.php`, `Models/SampleFeedback.php`, `Resources/SampleFeedbackResource.php`; `sample.js` (`sampleFeedback`), `components/merchandise/bulk-shade.jsx`, `actions.js` (`setBulkShade`) |
| E8 | `Database/Migrations/2026_11_18_000900_create_merchandise_measure_charts.php`, `Models/MeasureChart.php`, `Models/MeasurePoint.php`, `Resources/MeasureChartResource.php`, `Http/Controllers/Api/MeasurePointApiController.php`; `sample.js` (`measureChart`), `components/merchandise/measure-points.jsx`, `actions.js` (`addMeasurePoint`, `saveMeasurePoints`) |
| পারমিশন / মেনু | `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` |
| Frontend | `frontend/src/app/(app)/merchandise-desk/[screen]/sample.js`, `page.js`, `actions.js` (`saveLabdipShades`); `components/merchandise/labdip-shades.jsx` |
| Lang | `lang/en/merchandise.php`, `lang/bn/merchandise.php` (`master.ldp`, `master.lds`, `master.smp`, `master.pds`, `master.spe`, `master.sdc`, `master.bsf`, `master.ccm`, `labdip`, `sample`, `print`, `challan`, `feedback`, `measure`) |
| Test | `tests/Feature/Merchandise/MerchandiseSampleTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_নেই — Lab Dip & Sample সম্পন্ন।_

## ৯. টেস্ট ও যাচাই

- `MerchandiseSampleTest`:
  - প্রোগ্রাম: Brickland-এর বার্তা, অজানা ল্যাবে 422, LD নম্বর, সেকশন আর হাউস দুই রকম ল্যাব, এডিটে ল্যাব বদল আর নম্বর অক্ষত, মোছা।
  - শেড: বায়ারের স্টাইল/অর্ডারের তালিকা, রং বাধ্যতামূলক, অন্য বায়ারের স্টাইল বা অজানা লাইট সোর্সে 422, একই স্টাইলে একই রং দুবার আটকানো, আবার সেভে id অক্ষত, বাদ দেওয়া শেড মোছে, মুছে যাওয়া শেড আবার পাঠালে 422, প্রোগ্রাম মুছলে শেডও মোছে।
  - জমা: Pending ছাড়া অবস্থায় জমার তারিখ বাধ্যতামূলক (বার্তাসহ), অনুরোধের আগে বা ভবিষ্যতের তারিখে 422, অজানা অবস্থায় 422, রায়ের তারিখ আজ আর রায় না বদলালে অক্ষত, মন্তব্য trim, প্রোগ্রামের প্যানেলের সেভে জমার তথ্য অক্ষত, বায়ারের কাছে যাওয়া শেড সরানো আটকানো, Records-এর সারি, Style Progress Board-এর Lab Dips সংখ্যা।
  - স্যাম্পল প্রোগ্রাম: Prog. Date-এর বার্তা, SMP নম্বর, Job/Work Style# লেবেল, Add line-এর বার্তা (Smp. Type, R.Qty) আর শূন্য পরিমাণে 422, Add line সাথে সাথে সেভ, Records-এর Lines/R.Qty, জায়গায় বদল আর বাদ দেওয়া লাইন মোছা, অজানা লাইনে 422, ব্যাচে ওঠা লাইন না বদলানো/না সরানো, এমন প্রোগ্রাম না মোছা।
  - প্রিন্ট ডিজাইন: Program Parameter-এর বার্তা, অন্য কোম্পানিতে 422, PD নম্বর আর এডিটে অক্ষত, লাইনের প্যানেলে বায়ারের স্টাইল/অর্ডার আর শিপমেন্ট তারিখ, Add line-এর বার্তা, অন্য বায়ারের স্টাইল আর PDF আর্টওয়ার্কে 422, PNG আর্টওয়ার্কসহ লাইন, ফাইল ডাউনলোড (signed redirect), এক লাইনের আর্টওয়ার্ক বদল, জায়গায় বদলে আর্টওয়ার্ক অক্ষত, মুছে যাওয়া লাইনে 422, টেকনিশিয়ানের কাছে আর্টওয়ার্ক বন্ধ, প্রোগ্রাম মুছলে লাইন মোছে।
  - স্যাম্পল প্রোডাকশন: Planned তালিকা (Job# (Ord#), Buyer A/C), তারিখ ছাড়া ব্যাচে 422, SMB-00001/00002 নম্বর, ফাঁকা টিকে 422, লাইন ব্যাচে উঠে Planned থেকে সরে আর Sample Programme-এ locked, একই লাইন দুবার যোগে 422, ব্যাচ থেকে সরালে আবার Planned, D+Rj > S-এ বার্তা, ঠিক পরিমাণ আর Card Format সেভ, খালি ব্যাচ জমায় 422, Submit-এ Produced আর বন্ধ ব্যাচে সেভ/যোগ/সরানো আটকানো, ম্যানেজার শুধু দেখতে পারে।
  - ডেলিভারি চালান: Challan Date-এর বার্তা, SDC-00001/00002 নম্বর, Buyer Office নাম আর তৈরির তারিখ, Add item-এর বার্তা (Description, Qty), item সাথে সাথে সেভ আর Records-এ Items সংখ্যা, জায়গায় বদল আর বাদ দেওয়া item মোছা, মুছে যাওয়া item-এ 422, চালান মুছলে item মোছে।
  - বায়ার ফিডব্যাক: Batch Submit DT-এর বার্তা, খসড়া ব্যাচে Batch# নেই আর জমা হওয়া ব্যাচে SMB-00001, অর্ডার থেকে Buyer A/C, Job#/Order#/Sample Type, "1" থেকে Bulk Shade true, শুধু Bulk Shade-এর PATCH-এ বাকি অক্ষত, টাইপ বদলালে Batch# আবার খোঁজা, অজানা Status-এ 422।
  - মাপের চার্ট: Style/Item Name-এর বার্তা, স্টাইলের নাম আর চার্টের নাম, স্টাইল থেকে Buyer A/C, Add measurement-এর বার্তা (Size, Point, Value), সাথে সাথে সেভ, বড়-ছোট হাতের অক্ষর আলাদা করেও একই point দুবার আটকানো, Records-এ Measurements সংখ্যা, জায়গায় বদল আর বাদ দেওয়া point মোছা, মুছে যাওয়া point-এ 422, চার্ট মুছলে point মোছে।
  - টেকনিশিয়ান খুলতে পারে না, ম্যানেজার শুধু দেখতে পারে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**E1 — Lab Dip Programme (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: Records-এর ফিল্টার আর কলাম, Labdip Specification প্যানেল (বাঁয়ে Programs), New ফর্ম, তার হিন্ট আর খালি Save-এর বার্তা। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এ Buyer Acc আর Request To-এর তালিকা ফাঁকা অথচ দুটোই বাধ্যতামূলক, তাই সেখানে কোনো প্রোগ্রাম সেভ করা যায় না। ফলে শেডের গ্রিড, সারির বাটন আর Edit দেখা যায়নি। আমাদের শেডের ঘরগুলো এসেছে Brickland-এর হিন্ট থেকে ("colour, Pantone, fabric, light source and target date"), সাথে স্টাইল আর অর্ডার, যা Lab Dip Submission-এর কলামে লাগে। Edit একটা ট্যাব, অন্য সব স্ক্রিনের মতো।
  - Buyer Acc আসে বায়ার অফিস মাস্টার থেকে। Request To আসে সেকশন ("Section · …") আর সাপ্লায়ার ("Nominated house · …") থেকে।
  - Request By আর Attn. User Brickland-এ খোঁজার পিকার; আমাদেরটায় কোম্পানির ইউজারের ড্রপডাউন।

**E2 — Lab Dip Submission (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: Records-এর ফিল্টার আর কলাম (ACTION কলাম নেই, New ট্যাব নেই), আর Labdip Specification প্যানেল, যেটা Brickland-এ Lab Dip Programme-এর প্যানেলের হুবহু লেখা দেখায়। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এ কোনো প্রোগ্রাম সেভ করা যায় না (E1 দেখুন), তাই জমা কোথায় আর কীভাবে লেখা হয় দেখা যায়নি। আমাদের জমার তারিখ, রায় আর মন্তব্য লেখা হয় Labdip Specification প্যানেলে, শেডের পাশে। Records-এর Submitted আর Feedback কলাম সেখান থেকেই আসে।
  - Brickland-এর Records-এ সারি থেকে কোথাও যাওয়ার বাটন নেই; আমাদেরও নেই।
- **UI যাচাই (dev অ্যাপে, `AnnotechERP/tools/app_check/ui.mjs`; ডাটা রাখা আছে):**
  - New ফর্ম থেকে `LD-2026-000002` (Buyer Acc LPP S.A., Request To মনোনীত হাউস KOYO TRIMS, Request By/Attn. User বাছাই) সেভ।
  - Labdip Specification-এ Add shade দিয়ে Bottle Green (19-5420 TCX, FLOW STYLE-01 / Order, D65, 2026-10-20) আর Off White (TL84, 2026-10-22) যোগ করে Save।
  - Lab Dip Submission-এ দুটোয় জমার তারিখ, Bottle Green → Approved ("Approved under D65"), Off White → Resubmit; "Shades saved." আর রায়ের তারিখ দেখা গেছে।
  - Records-এ চারটা শেড, Status=Approved ফিল্টারে শুধু Bottle Green; প্রোগ্রামের প্যানেলে জমা হওয়া শেডের Remove নিষ্ক্রিয়। ব্রাউজারে কোনো JS error নেই। DB-তে সব মিলেছে।

**E3 — Sample Programme (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে, এবার Brickland-এ সত্যিকারের প্রোগ্রাম (SMP-202600001) আর লাইন সেভ করে: Records-এর কলাম আর সারির Edit/Remove, Sample Lines প্যানেল আর তার গ্রিড, Add line ডায়ালগ ও তার বার্তা, "Planned 3 × Proto Sample", সেভ বাটনের নাম "New program"।
- **Brickland থেকে আলাদা:**
  - Brickland-এ Smp. Type খোঁজার পিকার; আমাদেরটায় ড্রপডাউন (তালিকা বড় হলে নিজেই খোঁজা যায়)।
  - Brickland-এর Buyer A/C তালিকা ফাঁকা; আমাদেরটায় বায়ার অফিস মাস্টার থেকে আসে।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** খালি সেভে "Prog. Date is required."; SMP-202600001 (BK-BUL-00012, LPP S.A., অর্ডার "Order", Work Style FLOW-01-W) সেভ; Add line-এ "Smp. Type is required." তারপর Proto Sample (T-Shirt, Navy, M, 3 Pcs) → "Planned 3 × Proto Sample", Fit Sample (L, 2 Pcs); গ্রিডে Proto-র সাইজ S আর Fit-এর মন্তব্য বদলে Save → "Sample lines saved."; Records-এ Lines 2, R.Qty 5; Edit ট্যাব খোলে। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।

**E4 — Print Design Sheet (২০২৬-১০-১৩):**
- Brickland লাইভ দেখে বানানো হয়েছে: Program Parameter-এর ঘর আর হিন্ট, লাইনের ঘর আর Add-এর বার্তা (Material Description, Sample Type, Shipment Date), লাইনের গ্রিডের কলাম, Save program আর লাইন ছাড়া সেভের বার্তা।
- **ব্যবহারকারীর সিদ্ধান্তে বদল (২০২৬-১০-১৩):** প্রথমে Brickland-এর মতো এক পাতায় বানানো হয়েছিল; ব্যবহারকারী বললেন এক পাতায় হয়ে গেছে আর Image লিংক নয়, আপলোড হবে। এখন অন্য স্ক্রিনের মতো Records / Style & Order Lines / New program ট্যাব, আর আর্টওয়ার্ক আপলোড।
- **Brickland থেকে আলাদা:**
  - Brickland-এ এক পাতা, Records নেই আর Buyer Acc ফাঁকা বলে সেভ হয় না; আমাদের ট্যাবে সেভ করা প্রোগ্রাম তালিকায় থাকে।
  - Image Brickland-এ লিংকের ঘর ("Artwork link"); আমাদেরটায় ছবি আপলোড।
  - Program By, Style No. আর Sample Type Brickland-এ খোঁজার পিকার; আমাদেরটায় ড্রপডাউন। Company-তে চলতি কোম্পানি।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** খালি সেভে "Buyer Acc is required."; PD-202600002 (LPP S.A., LPP, Tanvir Rahman, "Creative Touch print section") সেভ; Add line-এ বার্তা, তারপর FLOW STYLE-01 / Order-এর PP Sample লাইন chest-print.png আর্টওয়ার্কসহ → "Line added."; Proto Sample লাইন আর্টওয়ার্ক ছাড়া, তারপর গ্রিডের বাটন দিয়ে back-neck.png আপলোড → "Artwork saved."; গ্রিডে পরিমাণ 5 → 8 Save → "Lines saved."; Records-এ Lines 2; Edit ট্যাব খোলে; থাম্বনেইল আর `/api/files/…/download` ছবি দেখায়। আগের PD-202600001-ও আছে (এক-পাতার সংস্করণে বানানো; তার লিংকের ঘর মাইগ্রেশনে বাদ গেছে)। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।

**E5 — Sample Production Entry (২০২৬-১০-১৪):**
- Brickland লাইভ দেখে বানানো হয়েছে, Brickland-এ সত্যিকারের ব্যাচ খুলে (SMB-00001): New Batch, লাইন টিক দিয়ে Add To Batch, ভুল পরিমাণে বার্তা, Save ("SMB-00001 lines: 1 updated"), Submit-এর নিশ্চিতকরণ ডায়ালগ আর জমার পরের অবস্থা, Hide Production Order List। প্রতিটা বাটনের কাজ মিলিয়ে দেখা হয়েছে; বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এর Print ব্রাউজারের প্রিন্ট খোলে, তাই তার কার্ডের নকশা দেখা যায়নি। আমাদের Print বাছাই করা Card Format-এ প্রতিটা লাইনের একটা স্যাম্পল কার্ড আলাদা প্রিন্ট উইন্ডোতে দেখায়।
  - Production Order List-এর সারিতে ক্লিক করলে Brickland কী করে দেখা যায়নি; আমাদেরটায় নিচের Planned তালিকা সেই Job/Type অনুযায়ী ছাঁকে।
  - Brickland-এর শিরোনামে "Producton" বানান; আমাদেরটায় "Production"।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** Production Order List-এ FLOW-01-W (Order)-এর Proto আর Fit; New Batch → SMB-00001; সব টিক ("2 selected") → Add To Batch → "Added 2 line(s) to SMB-00001", Planned খালি; Fit লাইন ব্যাচ থেকে সরানো → আবার Planned; Proto-তে S 2 / D 2 / Rj 1 → বার্তা; S 3 আর "one with a stain", Card Format Other → "SMB-00001 lines: 1 updated"; Submit ডায়ালগ → জমা; ?batch=SMB-00001 খুললে Submitted আর ঘর বন্ধ। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।

**E6 — Sample Delivery Challan (২০২৬-১০-১৫):**
- Brickland লাইভ দেখে বানানো হয়েছে, Brickland-এ সত্যিকারের চালান (SDC-00001) আর item সেভ করে: Records-এর কলাম (Status-সহ) আর সারির Edit/Remove, Challan Items প্যানেল, Add item ডায়ালগ ও তার বার্তা, "Added …", item-এর গ্রিড। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা:**
  - Brickland-এর item গ্রিডের Status কলাম সবসময় ফাঁকা; আমাদেরটায় বদলানো কিন্তু সেভ না হওয়া item-এ "Edited" দেখায়।
  - Sample Type Brickland-এ খোঁজার পিকার; আমাদেরটায় ড্রপডাউন।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** খালি সেভে "Challan Date is required."; SDC-00001 (LPP S.A., DHA-METRO-11-2345, DHL Express) সেভ; Add item-এ "Description is required.", তারপর "Proto sample T-Shirt Navy S (SMB-00001)" (Proto Sample, 2 Pcs) আর "Lab dip card LD-2026-000002 (Bottle Green)" (1); গ্রিডে দ্বিতীয়টার পরিমাণ 2 → "Edited" → Save → "Challan items saved."; Records-এ Items 2; Edit ট্যাব খোলে। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।

**E7 — Buyer Sample Feedback (২০২৬-১০-১৫):**
- Brickland লাইভ দেখে বানানো হয়েছে, Brickland-এ সত্যিকারের ফিডব্যাক সেভ করে: Records-এর কলাম (Status-সহ) আর সারির Edit/Remove, New feedback-এর ঘর আর বার্তা, Bulk Shade-এর হিন্ট, Bulk Shade Approval প্যানেলের লেখা। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা বা যাচাই করা যায়নি:**
  - Brickland-এর ফর্মে Batch-এর ঘর নেই আর Records-এ Batch# "—" দেখায়। আমাদের Batch# নিজে থেকে আসে — অর্ডার আর Sample Type মিলিয়ে সর্বশেষ জমা হওয়া production batch।
  - Brickland-এর Buyer A/C তালিকা ফাঁকা, তাই Bulk Shade Approval প্যানেলে কোনো অ্যাকাউন্ট আসেনি; ভেতরের গ্রিড দেখা যায়নি। আমাদের ডানে ফিডব্যাকের কলাম আর প্রতিটায় Release / Hold — Brickland-এর লেখা "release or hold the bulk shade for each" অনুযায়ী।
  - Job/Order# আর Sample Type Brickland-এ খোঁজার পিকার; আমাদেরটায় ড্রপডাউন।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** খালি সেভে "Batch Submit DT is required."; অর্ডার "Order"-এর Proto Sample (Approved, Bulk Shade টিক) → Batch# SMB-00001, Buyer A/C LPP S.A. নিজে থেকে; Fit Sample (Resubmit) → Batch# "—"; Bulk Shade Approval-এ LPP S.A. → Fit-এ Release, Proto-তে Hold; Bulk Shade = Approved ফিল্টারে শুধু Fit; Edit ট্যাব খোলে। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।

**E8 — Collar & Cuff Measurement Sheet (২০২৬-১০-১৫):**
- Brickland লাইভ দেখে বানানো হয়েছে, Brickland-এ সত্যিকারের চার্ট আর point সেভ করে: Records-এর কলাম (Status-সহ) আর সারির Edit/Remove, New measurement chart-এর ঘর (Style পিকারসহ) আর হিন্ট, Add Measurement প্যানেল, Add measurement ডায়ালগ, point-এর গ্রিড, একই point দুবারের বার্তা। বাইরে যাওয়ার লিংক শুধু breadcrumb-এ।
- **Brickland থেকে আলাদা:**
  - Brickland-এর point গ্রিডের Status কলাম ফাঁকা; আমাদেরটায় বদলানো কিন্তু সেভ না হওয়া point-এ "Edited"।
  - Style Brickland-এ খোঁজার পিকার; আমাদেরটায় ড্রপডাউন (তালিকা বড় হলে নিজেই খোঁজা যায়)।
  - Brickland-এর Buyer Account তালিকা ফাঁকা; আমাদেরটায় বায়ার অফিস থেকে আসে, ফাঁকা রাখলে স্টাইলেরটা বসে।
- **UI যাচাই (dev অ্যাপে, ডাটা রাখা আছে):** খালি সেভে "Style is required." আর "Item Name is required."; FLOW STYLE-01 · Collar (Polo, FL-COL-01) সেভ, Buyer A/C LPP S.A. নিজে থেকে; Add measurement-এ M · Collar length (42.5 ± 0.5 cm); "m · collar length" আবার দিলে "That point already exists…"; L · collar length (44) আর M · Collar height (7 cm); গ্রিডে L-এর মাপ 44.5 → "Edited" → Save → "Measurements saved."; Records-এ Measurements 3। ব্রাউজারে কোনো JS error নেই, DB-তে সব মিলেছে।
