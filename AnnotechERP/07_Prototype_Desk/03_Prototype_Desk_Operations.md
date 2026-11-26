# Prototype Desk operations

> **স্ট্যাটাস:** ✅ সম্পন্ন (১১টা স্ক্রিনই চালু: P1 Sample Requisition, P2 Sample Development, P3 Sample Revision, P4 Prototype Material Requisition, P5 Sample Bundle Card Issue, P6 Sample Room Daily Target, P7 Sample Bundle Tracking, P8 Sample Quality Check, P9 Sample Approval, P10 Buyer Feedback, P11 Sample Handover / Delivery)
> **মডিউল:** [Prototype Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `07/03`

**⬅ ফিরে যান:** [Prototype Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sample Requisition | `/m/sample/prototype-requisition` | 3 | 47 | 1 |
| 2 | Sample Development | `/m/sample/sample-development` | 2 | 26 | 1 |
| 3 | Sample Revision | `/m/sample/sample-revision` | 2 | 30 | 1 |
| 4 | Prototype Material Requisition | `/m/sample/sample-requisition` | 4 | 27 | 0 |
| 5 | Sample Bundle Card Issue | `/m/sample/bundle-card-create` | 3 | 25 | 0 |
| 6 | Sample Room Daily Target | `/m/sample/daily-target-setting` | 3 | 30 | 0 |
| 7 | Sample Bundle Tracking | `/m/sample/bundle-movement` | 5 | 15 | 0 |
| 8 | Sample Quality Check | `/m/sample/sample-quality-check` | 2 | 23 | 0 |
| 9 | Sample Approval | `/m/sample/sample-approval` | 2 | 25 | 0 |
| 10 | Buyer Feedback | `/m/sample/buyer-feedback` | 2 | 28 | 0 |
| 11 | Sample Handover / Delivery | `/m/sample/sample-handover` | 2 | 28 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sample Requisition

`/m/sample/prototype-requisition`

**ট্যাব: Requisition List 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition Date from | date |  |  |
| Requisition Date to | date |  |  |
| Buyer | select |  | LPP |
| Sample Type | select |  | Development Sample, PP Sample |
| Priority | select |  | Low, Normal, High, Urgent, Critical |
| Status | select |  | Draft, Submitted, Pending Approval, Approved, Rejected, Assigned, In Development, Completed, Cancelled |
| Search Sample requisitions with their number, date, buyer, style, sample type an | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Date, Buyer, Style, Sample Type, Stage, Qty, Required, Priority, Pending At, Sample No, Status, ACTION
- বাটন/অ্যাকশন: Requisition List 2, Sample Requisition, Requisition Approval, Clear, New Requisition, Export, Requisition No, Date, Buyer, Style, Sample Type, Stage, Qty, Required, Priority, Pending At, Sample No, Status, Open, Decide

**ট্যাব: Sample Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sample requisition | select |  | SR-2026-00001 — LPP · Summer Set · 10 Oct 2026 · Pending Approval, SR-2026-00002 — LPP · 353535345453453453466763535-454 |
| Requisition No | text |  | [Auto Gen] |
| Requisition Date* | date | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Status | text |  | Draft |
| Pending At | text |  |  |
| Sample No | text |  |  |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Buying House | select |  | Select… |
| Order No | select |  | Select…, 529 — LPP-2026-000008 [529], 1111 — LPP-2026-000007 [1111], 529 — LPP-2026-000006 [529], 234234 — LPP-2026-0000 |
| Style No* | select | ✔ | Select… |
| Style Name | text |  |  |
| Season | select |  | Select…, Autumn/Winter 2026, Spring/Summer 2026, Spring/Summer 2027 |
| Product Category | text |  |  |
| Sample Stage | select |  | Select…, Pre-Order, Order / PO, Pre-Production, Bulk Production, Post-Production |
| Sample Quantity* | number | ✔ |  |
| Required Date* | date | ✔ |  |
| Priority | select |  | Select…, Low, Normal, High, Urgent, Critical |
| Remarks | textarea |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Sample Requisition, Requisition, Buyer & Style, Sample, People, Notes, Approval History
- বাটন/অ্যাকশন: Requisition List 2, Sample Requisition, Requisition Approval, Select…, Clear, Save sample requisition

**ট্যাব: Requisition Approval**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing approval decision | select |  | SR-2026-00001 — LPP · awaiting Merchandising Manager |
| Requisition No | text |  |  |
| Requisition Date | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Sample Type | text |  |  |
| Sample Stage | text |  |  |
| Sample Quantity | text |  |  |
| Required Date | text |  |  |
| Priority | text |  |  |
| Requested By | text |  |  |
| Status | text |  |  |
| Pending At | text |  |  |
| Decision* | select | ✔ | Select…, Approve, Reject |
| Comments | textarea |  |  |
| Rejection Reason | textarea |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Requisition Approval, Requisition, Decision, Approval History
- বাটন/অ্যাকশন: Requisition List 2, Sample Requisition, Requisition Approval, Select…, Clear, Record Decision

**ডায়ালগ: New Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing sample requisition | select |  | SR-2026-00001 — LPP · Summer Set · 10 Oct 2026 · Pending Approval, SR-2026-00002 — LPP · 353535345453453453466763535-454 |
| Requisition No | text |  |  |
| Requisition Date* | date | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Status | text |  |  |
| Pending At | text |  |  |
| Sample No | text |  |  |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Buying House | select |  | Select… |
| Order No | select |  | Select…, 529 — LPP-2026-000008 [529], 1111 — LPP-2026-000007 [1111], 529 — LPP-2026-000006 [529], 234234 — LPP-2026-0000 |
| Style No* | select | ✔ | Select… |
| Style Name | text |  |  |
| Season | select |  | Select…, Autumn/Winter 2026, Spring/Summer 2026, Spring/Summer 2027 |
| Product Category | text |  |  |
| Sample Stage | select |  | Select…, Pre-Order, Order / PO, Pre-Production, Bulk Production, Post-Production |
| Sample Quantity* | number | ✔ |  |
| Required Date* | date | ✔ |  |
| Priority | select |  | Select…, Low, Normal, High, Urgent, Critical |
| Remarks | textarea |  |  |
| Attachment | text |  |  |
| Attach | file |  |  |
- খালি Save-এ মেসেজ: Requisition Date is required.

#### Sample Development

`/m/sample/sample-development`

**ট্যাব: Development List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Development Date from | date |  |  |
| Development Date to | date |  |  |
| Buyer | select |  |  |
| Sample Type | select |  |  |
| Status | select |  | Pending, In Progress, On Hold, Completed, Delayed, Cancelled, Overdue |
| Search Sample developments with their number, sample and version, buyer, style,  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Development No, Date, Sample No, Version, Buyer, Style, Sample Type, Expected, Completed, Tasks, Progress, ACTION
- বাটন/অ্যাকশন: Development List 0, Sample Development, Clear, New Development, Export, Development No, Date, Sample No, Version, Buyer, Style, Sample Type, Expected, Completed, Tasks, Progress

**ট্যাব: Sample Development**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sample development | select |  |  |
| Development No | text |  | [Auto Gen] |
| Sample* | select | ✔ | Select… |
| Version | text |  |  |
| Status | select |  | Select…, Pending, In Progress, On Hold, Completed, Delayed, Cancelled |
| Progress % | text |  |  |
| Requisition No | text |  |  |
| Sample No | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Order | text |  |  |
| Sample Type | text |  |  |
| Sample Stage | text |  |  |
| Revision | text |  |  |
| Development Date* | date | ✔ |  |
| Start Date | date |  |  |
| Expected Completion | date |  |  |
| Actual Completion | date |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Task, Task Status, Completed At, Remarks
- সেকশন: Sample Development, Development, Sample, Schedule, Team, Notes, Development Checklist
- বাটন/অ্যাকশন: Development List 0, Sample Development, Select…, Clear, Save sample development

**ডায়ালগ: New Development**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing sample development | select |  |  |
| Development No | text |  |  |
| Sample* | select | ✔ | Select… |
| Version | text |  |  |
| Status | select |  | Select…, Pending, In Progress, On Hold, Completed, Delayed, Cancelled |
| Progress % | text |  |  |
| Requisition No | text |  |  |
| Sample No | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Order | text |  |  |
| Sample Type | text |  |  |
| Sample Stage | text |  |  |
| Revision | text |  |  |
| Development Date* | date | ✔ |  |
| Start Date | date |  |  |
| Expected Completion | date |  |  |
| Actual Completion | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Development Date is required.

#### Sample Revision

`/m/sample/sample-revision`

**ট্যাব: Revision List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Revision Date from | date |  |  |
| Revision Date to | date |  |  |
| Buyer | select |  |  |
| Sample No | select |  |  |
| Status | select |  | Open, In Progress, Completed, Cancelled |
| Approved? | select |  | Approved, Awaiting approval |
| Search Sample revisions with their number, sample, the version change, date, rea | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Revision No, Date, Sample No, Buyer, Style, Version, Reason, Due, Requested By, Approved By, Revisions, ACTION
- বাটন/অ্যাকশন: Revision List 0, Sample Revision, Clear, New Revision, Export, Revision No, Date, Sample No, Buyer, Style, Version, Reason, Due, Requested By, Approved By, Revisions

**ট্যাব: Sample Revision**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sample revision | select |  |  |
| Revision No | text |  | [Auto Gen] |
| Sample No* | select | ✔ | Select… |
| Previous Version | text |  |  |
| New Version | text |  |  |
| Revision Date* | date | ✔ |  |
| Due Date | date |  |  |
| Status | text |  | Open |
| Revision Reason* | text | ✔ |  |
| Source Approval | select |  | Select… |
| Source Feedback | select |  | Select… |
| Buyer Comment | textarea |  |  |
| Internal Comment | textarea |  |  |
| Changed Measurement | textarea |  |  |
| Changed Fabric | textarea |  |  |
| Changed Color | textarea |  |  |
| Changed Accessories | textarea |  |  |
| Changed Construction | textarea |  |  |
| Approved By | text |  |  |
| Approved At | text |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Sample Revision, Revision, Why, What Changes, Sign-off, Revisions of This Sample
- বাটন/অ্যাকশন: Revision List 0, Sample Revision, Select…, Clear, Save sample revision

**ডায়ালগ: New Revision**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing sample revision | select |  |  |
| Revision No | text |  |  |
| Sample No* | select | ✔ | Select… |
| Previous Version | text |  |  |
| New Version | text |  |  |
| Revision Date* | date | ✔ |  |
| Due Date | date |  |  |
| Status | text |  |  |
| Revision Reason* | text | ✔ |  |
| Source Approval | select |  | Select… |
| Source Feedback | select |  | Select… |
| Buyer Comment | textarea |  |  |
| Internal Comment | textarea |  |  |
| Changed Measurement | textarea |  |  |
| Changed Fabric | textarea |  |  |
| Changed Color | textarea |  |  |
| Changed Accessories | textarea |  |  |
| Changed Construction | textarea |  |  |
| Approved By | text |  |  |
| Approved At | text |  |  |
| Attachment | text |  |  |
| Attach | file |  |  |
- খালি Save-এ মেসেজ: Revision Date is required.

#### Prototype Material Requisition

`/m/sample/sample-requisition`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| State | select |  | All, Draft, Submitted, Approved, Fully Issued, Rejected, Dept. Approved, Store Verified, Partially Issued, Cancelled |
| Sample No | select |  | All |
| Req. Date from | date |  |  |
| Req. Date to | date |  |  |
| Search Sample material requisitions with their number, date, sample, requester,  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Req. Date, Sample No, Required Date, Requisition By, Requisition Type, Requisition To, Delivery To, Programs, Items, Short Lines, Req. Qty, Issued %, State, Remarks, ACTION
- বাটন/অ্যাকশন: Sample Fabric Requisition, Sample Programs, Material Issue, Clear, Export, Requisition No, Req. Date, Sample No, Required Date, Requisition By, Requisition Type, Requisition To, Delivery To, Programs, Items, Short Lines, Req. Qty, Issued %, State, Remarks

**ট্যাব: Sample Fabric Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Material Requisition No | text |  | Issued on save |
| Sample No | select |  | Select… |
| Requisition No | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Requisition Date* | date | ✔ |  |
| Required Date | date |  |  |
| Remarks | textarea |  |  |
| State | text |  |  |
| Dept. Approved By | text |  |  |
| Store Verified By | text |  |  |
| Approved By | text |  |  |
| Rejection Reason | textarea |  |  |
- সেকশন: Sample Fabric Requisition, Sample, Requisition, Approval, Sample Requisition, Fabric Requisition
- বাটন/অ্যাকশন: Sample Fabric Requisition, Sample Programs, Material Issue, Select…, Add, Add Material, Clear, Save requisition

**ট্যাব: Sample Programs**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search programs | search |  | Search… |
- সেকশন: Sample Programs, Requisitions Raised
- বাটন/অ্যাকশন: Sample Fabric Requisition, Sample Programs, Material Issue

**ট্যাব: Material Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing material issue | select |  |  |
| Material Issue No | text |  | Issued on save |
| Requisition* | select | ✔ | Select… |
| Issue Date* | date | ✔ |  |
| Received By | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Material Issue, Issued Lines
- বাটন/অ্যাকশন: Sample Fabric Requisition, Sample Programs, Material Issue, Select…, Add Line, Clear, Save material issue

#### Sample Bundle Card Issue

`/m/sample/bundle-card-create`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bundle Status | select |  | All, Created, Issued, Received, In Production, Completed, Hold, Returned, Cancelled |
| Sample No | select |  | All |
| Overdue | select |  | All, Overdue, On time |
| Cut Date from | date |  |  |
| Cut Date to | date |  |  |
| Search Sample bundle cards with their number, sample, buyer, style, cutting, tab | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Card No, Cut Date, Sample No, Buyer, Style, Order, Cutting#, Table, Fabric, Bundles, Req Qty, Cutting Qty, Operator, Due Date, Bundle Status, Is Finalized, ACTION
- বাটন/অ্যাকশন: Bundle Card Creation, Cutting List, Clear, Export, Card No, Cut Date, Sample No, Buyer, Style, Order, Cutting#, Table, Fabric, Bundles, Req Qty, Cutting Qty, Operator, Due Date, Bundle Status, Is Finalized

**ট্যাব: Bundle Card Creation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing bundle card | select |  |  |
| Sample No | select |  | Select… |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Buyer A/C | select |  | Select… |
| Job/Order# | select |  | Select…, 529 — LPP-2026-000008 [529], 1111 — LPP-2026-000007 [1111], 529 — LPP-2026-000006 [529], 234234 — LPP-2026-0000 |
| Cutting#* | text | ✔ |  |
| Table* | select | ✔ | Select… |
| Fabric | select |  | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Cutting Date* | date | ✔ |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Is Finalized? Finalizing mints the bundle numbers and opens the card to the scan | checkbox |  |  |
| Bundle Status | text |  |  |
| Assigned Team | text |  |  |
| Issue Date | date |  |  |
| Due Date | date |  |  |
| Priority | select |  | Select…, Low, Normal, High, Urgent, Critical |
| Remarks | textarea |  |  |
- সেকশন: Bundle Card Creation, Styling + Cutting Information, Issue, Bundling Information
- বাটন/অ্যাকশন: Bundle Card Creation, Cutting List, Select…, Add Record, Clear, Save bundle card

**ট্যাব: Cutting List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search tables | search |  | Search… |
- সেকশন: Cutting Tables, Bundles
- বাটন/অ্যাকশন: Bundle Card Creation, Cutting List

#### Sample Room Daily Target

`/m/sample/daily-target-setting`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| State | select |  | All, Draft, Confirmed, Closed |
| Overdue | select |  | All, Overdue, Not overdue |
| Sample Room | select |  | All |
| Target Date from | date |  |  |
| Target Date to | date |  |  |
| Search Sample room day sheets with their date, room, line count, total target, a | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Target Date, Sample Room, Shift, Company, Lines, Total Target, Actual, Achievement %, Average WH, Planned Hrs, Capacity Hrs, Efficiency %, Prepared By, Remarks, ACTION
- বাটন/অ্যাকশন: Target Setting, Weekly / Monthly Plan, Clear, Export, Target Date, Sample Room, Shift, Company, Lines, Total Target, Actual, Achievement %, Average WH, Planned Hrs, Capacity Hrs, Efficiency %, Prepared By, Remarks

**ট্যাব: Target Setting**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing day sheet | select |  |  |
| Target Date* | date | ✔ |  |
| Sample Room | text |  | Sample Room 1 |
| Shift | select |  | Select…, Day, Evening, Night, General |
| Company | select |  | Select…, Brickland Composite Ltd. |
| State | select |  | Select…, Draft, Confirmed, Closed |
| Total Manpower | number |  | 0 |
| Available Manpower | number |  | 0 |
| Shift Hours | text |  | 8 |
| Remarks | textarea |  |  |
- সেকশন: Target Setting, Day, Capacity, Line Targets
- বাটন/অ্যাকশন: Target Setting, Weekly / Monthly Plan, Select…, Add Line, Clear, Save day sheet

**ট্যাব: Weekly / Monthly Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing target plan | select |  |  |
| Period* | select | ✔ | Select…, Daily, Weekly, Monthly |
| Period Start* | date | ✔ |  |
| Period End* | date | ✔ |  |
| Sample Room | text |  |  |
| Team | text |  |  |
| Target Qty | number |  | 0 |
| Available Manpower | number |  | 0 |
| Available Hours | text |  | 0 |
| Planned Hours | text |  | 0 |
| Actual (Day Sheets) | text |  |  |
| Achievement % | text |  |  |
| Remarks | textarea |  |  |
- সেকশন: Weekly / Monthly Plan, Period, Target & Capacity, Day Sheets in Period
- বাটন/অ্যাকশন: Target Setting, Weekly / Monthly Plan, Select…, Clear, Save target plan

#### Sample Bundle Tracking

`/m/sample/bundle-movement`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Process | select |  | All, Delv. For Print, Delv. For Embr, Delv. For Print+Embr, Recv. From Embellishment, Delv. for Sewing, Sewing Done, Dis |
| Status | select |  | All, Pending, Issued, Received, In Progress, Completed, Hold, Delayed, Rejected |
| Sample No | select |  | All |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Sample bundle movements with the bundle scanned, the process, the sample, | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Process, Bundle Card, Sample No, Card No, Job/Order#, Sample Type, Color, Size, Qty, Operator, Status, Delay Hrs, Delay Reason, Scanned At, ACTION
- বাটন/অ্যাকশন: Scan Desk, Tracking Update, Bundle Lookup, Sample Card, Clear, Export, Date, Process, Bundle Card, Sample No, Card No, Job/Order#, Sample Type, Color, Size, Qty, Operator, Status, Delay Hrs, Delay Reason

**ট্যাব: Scan Desk**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bundle Card | text |  | Bundle Card |
- সেকশন: Scan Desk
- বাটন/অ্যাকশন: Scan Desk, Tracking Update, Bundle Lookup, Sample Card, Delv. For Print 0, Delv. For Embr 0, Delv. For Print+Embr 0, Recv. From Embellishment 0, Delv. for Sewing 0, Sewing Done 0, Distribution 0, Scan, Clear

**ট্যাব: Tracking Update**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search bundles | search |  | Search… |
- সেকশন: Bundles, Movements
- বাটন/অ্যাকশন: Scan Desk, Tracking Update, Bundle Lookup, Sample Card

**ট্যাব: Bundle Lookup**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Cut Month -- All Months -- | select |  |  |
| Bundle Status -- All Status -- Created Issued Received In Production Completed H | select |  | Created, Issued, Received, In Production, Completed, Hold, Returned, Cancelled |
| All | radio |  |  |
| Delayed? | search |  | Bundle / Card / Sample / Style No... |
| Delayed? | checkbox |  |  |
- সেকশন: Order nature
- বাটন/অ্যাকশন: Scan Desk, Tracking Update, Bundle Lookup, Sample Card, Search, All

**ট্যাব: Sample Card**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Buyer A/C — Job/Order#, Sample Cards
- বাটন/অ্যাকশন: Scan Desk, Tracking Update, Bundle Lookup, Sample Card

#### Sample Quality Check

`/m/sample/sample-quality-check`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Check Date from | date |  |  |
| Check Date to | date |  |  |
| Buyer | select |  | All |
| Result | select |  | All, Pass, Pass With Comment, Fail, Rework Required |
| Sample No | search |  | SMP-2026-00001 |
| Search Sample quality checks with their number, sample, version, check date, res | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: QC No, Sample No, Style No, Buyer, Version, Check Date, Defects, Severity, Defect, Checked By, ACTION
- বাটন/অ্যাকশন: Sample Quality Check, Clear, Export, QC No, Sample No, Style No, Buyer, Version, Check Date, Defects, Severity, Defect, Checked By

**ট্যাব: Sample Quality Check**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing quality check | select |  |  |
| QC No | text |  | Issued on save |
| Sample No* | select | ✔ | Select… |
| Buyer | text |  |  |
| Style No | text |  |  |
| Sample Type | text |  |  |
| Version | text |  |  |
| Check Date* | date | ✔ |  |
| QC Result* | select | ✔ | Select…, Pass, Pass With Comment, Fail, Rework Required |
| Severity | select |  | Select…, Minor, Major, Critical |
| Defect Type | text |  | Measurement out of tolerance |
| Defect | textarea |  |  |
| Correction | textarea |  |  |
| Remarks | textarea |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- গ্রিড কলাম: Checkpoint, Result, Defect, Severity, Remarks
- সেকশন: Sample Quality Check, Sample, Check, Findings, QC Checklist
- বাটন/অ্যাকশন: Sample Quality Check, Select…, Clear, Save quality check

#### Sample Approval

`/m/sample/sample-approval`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Submitted Date from | date |  |  |
| Submitted Date to | date |  |  |
| Buyer | select |  | All |
| Step | select |  | All, Merchandising Review, Buyer Approval |
| Decision | select |  | All, Pending, Approved, Approved With Comment, Rejected, Revision Required |
| Sample No | search |  | SMP-2026-00001 |
| Search Sample approvals with their number, sample, version, step, dates, who app | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Approval No, Sample No, Style No, Buyer, Version, Step, Submitted, Approval Date, Waiting (days), Approval By, QC Ref., Comment, ACTION
- বাটন/অ্যাকশন: Sample Approval, Clear, Export, Approval No, Sample No, Style No, Buyer, Version, Step, Submitted, Approval Date, Waiting (days), Approval By, QC Ref., Comment

**ট্যাব: Sample Approval**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing approval | select |  |  |
| Approval No | text |  | Issued on save |
| Sample No* | select | ✔ | Select… |
| Buyer | text |  |  |
| Style No | text |  |  |
| Sample Type | text |  |  |
| Version | text |  |  |
| QC Ref. | text |  | The passed QC, linked on save |
| Step* | select | ✔ | Select…, Merchandising Review, Buyer Approval |
| Submitted Date* | date | ✔ |  |
| Decision | select |  | Select…, Pending, Approved, Approved With Comment, Rejected, Revision Required |
| Approval Date | date |  |  |
| Approval By | text |  | As signed on the buyer's comment sheet |
| Buyer Comment | textarea |  |  |
| Internal Comment | textarea |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Sample Approval, Sample, Approval, Comments
- বাটন/অ্যাকশন: Sample Approval, Clear, Save approval

#### Buyer Feedback

`/m/sample/buyer-feedback`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Feedback Date from | date |  |  |
| Feedback Date to | date |  |  |
| Buyer | select |  | All |
| Type | select |  | All, Approval, Comment, Correction, Rejection, General |
| Status | select |  | All, Open, In Progress, Resolved, Closed |
| Assigned To | select |  | All |
| Search Buyer feedback with its number, date, sample, buyer, type, comment, assig | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Feedback No, Date, Sample No, Version, Buyer, Type, Comment, Approval, Action Req., Assigned To, Due Date, Overdue (days), ACTION
- বাটন/অ্যাকশন: Buyer Feedback, Clear, Export, Feedback No, Date, Sample No, Version, Buyer, Type, Comment, Approval, Action Req., Assigned To, Due Date, Overdue (days)

**ট্যাব: Buyer Feedback**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing feedback | select |  |  |
| Feedback No | text |  | Issued on save |
| Sample No* | select | ✔ | Select… |
| Buyer | text |  |  |
| Style No | text |  |  |
| Sample Type | text |  |  |
| Version | text |  |  |
| Related Approval | select |  | Select… |
| Feedback Date* | date | ✔ |  |
| Feedback Type* | select | ✔ | Select…, Approval, Comment, Correction, Rejection, General |
| Status | select |  | Select…, Open, In Progress, Resolved, Closed |
| Comment | textarea |  |  |
| Measurement Change | textarea |  |  |
| Fabric Change | textarea |  |  |
| Color Change | textarea |  |  |
| Construction Change | textarea |  |  |
| Action Required Requires Assigned To and Due Date; the assignee is alerted immed | checkbox |  |  |
| Due Date | date |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Buyer Feedback, Sample, Feedback, Changes Asked For, Follow-up
- বাটন/অ্যাকশন: Buyer Feedback, Select…, Clear, Save feedback

#### Sample Handover / Delivery

`/m/sample/sample-handover`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Handover Date from | date |  |  |
| Handover Date to | date |  |  |
| Buyer | select |  | All |
| Status | select |  | All, Ready, Handover Pending, Handed Over, Couriered, In Transit, Delivered, Received, Cancelled |
| Courier | select |  | All |
| Sample No | search |  | SMP-2026-00001 |
| Search Sample handovers with their number, sample, quantity, recipient, courier  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Handover No, Handover Date, Sample No, Buyer, Qty, Handover To, Courier / Tracking, Expected Delivery, Actual Delivery, Delay (days), Received By, ACTION
- বাটন/অ্যাকশন: Sample Handover / Delivery, Clear, Export, Handover No, Handover Date, Sample No, Buyer, Qty, Handover To, Courier / Tracking, Expected Delivery, Actual Delivery, Delay (days), Received By

**ট্যাব: Sample Handover / Delivery**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing handover | select |  |  |
| Handover No | text |  | Issued on save |
| Sample No* | select | ✔ | Select… |
| Buyer | text |  |  |
| Style No | text |  |  |
| Sample Type | text |  |  |
| Sample Qty | text |  |  |
| Handover Qty* | number | ✔ |  |
| Handover Date* | date | ✔ |  |
| Handover To* | text | ✔ | Buyer / Merchandising / Buying house / Department |
| Status | select |  | Select…, Ready, Handover Pending, Handed Over, Couriered, In Transit, Delivered, Received, Cancelled |
| Courier | text |  | DHL |
| Tracking No | text |  |  |
| Expected Delivery | date |  |  |
| Actual Delivery | date |  |  |
| Received By | text |  |  |
| Received Date | date |  |  |
| Remarks | textarea |  |  |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
- সেকশন: Sample Handover / Delivery, Sample, Handover, Courier, Receipt
- বাটন/অ্যাকশন: Sample Handover / Delivery, Select…, Clear, Save handover
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Merchandise Desk থেকে নেওয়া:** বায়ার, buying house (BUYING_HOUSE ধরনের buyer office), স্টাইল (`merch_styles`, season), অর্ডার, Sample Type master (ডিফল্ট stage, order/PO-বাধ্যতা, revision_allowed)। ডিপার্টমেন্ট আসে Company Management থেকে, ব্যক্তি আসেন কোম্পানির ব্যবহারকারী তালিকা থেকে।
- **Notification:** স্যাম্পল দায়িত্বে গেলে `SAMPLE_ASSIGNED` বার্তা যায়। বায়ার ফিডব্যাকে Action Required দিলে দায়িত্বপ্রাপ্তকে `SAMPLE_FEEDBACK_ACTION` (WARNING) যায়; Resolved বা Closed হলে সেই বার্তা পড়া হিসেবে চিহ্নিত হয়।

## ৪. ডাটা মডেল (Entity ও Field)

- **`proto_requisitions`:** req_no (`SR-2026-00001`, কোম্পানি ও বছর ধরে ক্রমিক), req_date, buyer_id, buyer_office_id, order_id, style_id, season, product_category, sample_type_id, sample_stage, qty, required_date, priority (LOW/NORMAL/HIGH/URGENT/CRITICAL), requested_by, department_id, assigned_to, remarks, attachment, status, pending_level, sample_id।
- **`proto_requisition_decisions`:** requisition_id, level (MERCHANDISER / MERCHANDISING_MANAGER / SAMPLE_MANAGER), decision (APPROVED/REJECTED), decided_by, decided_at, comments, rejection_reason, attachment। কখনো বদলায় না।
- **`proto_samples`:** sample_no (`SMP-2026-00001`), requisition_id, version, phase (REQUISITION/DEVELOPMENT/REVISION/QUALITY/APPROVAL/HANDOVER), state (APPROVED/ASSIGNED/IN_PROGRESS/COMPLETED/PENDING/FAILED/REWORK/REJECTED/REVISION_REQUIRED/READY/DELIVERED/CANCELLED; কলাম ২৪ অক্ষর), assigned_to, assigned_at।
- **`proto_developments`:** dev_no (`DEV-2026-00001`), sample_id, version (sample + version unique), status, progress, dev_date, start_date, expected_completion, actual_completion, pattern_master, sample_maker, technician, supervisor, delay_reason, remarks।
- **`proto_development_tasks`:** চৌদ্দটা point: Pattern, Fabric, Color, Measurement, Accessories, Label, Print, Embroidery, Wash, Construction, Cutting, Sewing, Finishing, Quality। প্রতিটার status (খালি/PENDING/IN_PROGRESS/COMPLETED/NOT_REQUIRED), completed_at, remarks।
- **`proto_revisions`:** rev_no (`REV-2026-00001`), sample_id, previous_version, new_version, revision_date, due_date, status (OPEN/IN_PROGRESS/COMPLETED/CANCELLED), reason, source_approval_id, source_feedback_id, buyer_comment, internal_comment, পাঁচটা changed_* ঘর, requested_by, approved_by, approved_at, attachment।
- **`proto_material_requisitions`:** mr_no (`MR-2026-00001`), sample_id, req_date, required_date, department_id, requested_by, requisition_type (Brickland-এর ২৩টা নামের একটা, `AccReceipt::REQUISITION_TYPES` থেকে), requisition_to_id ও delivery_to_id (Inventory-এর `stores`), remarks, state (DRAFT/SUBMITTED/DEPT_APPROVED/STORE_VERIFIED/APPROVED/PARTIALLY_ISSUED/FULLY_ISSUED/REJECTED/CANCELLED), dept_approved_by/at, store_verified_by/at, approved_by/at, rejection_reason, created_by।
- **`proto_mr_programs`:** requisition_id, program_id (Merchandise-এর `merch_sample_programs`), sort_order।
- **`proto_mr_lines`:** item_id (Material Procurement-এর `mp_items`), material_group (Fabric … Other Accessories, ১৩টা), fabric_type_id (Merchandise-এর `fabric_types`), color, size, description, specification, req_qty, unit (আইটেমের uom থেকে), available_qty, warehouse_id (store), issued_qty, remarks।
- **`proto_material_issues`:** issue_no (`MI-2026-00001`), requisition_id, issue_date, store_id, received_by, remarks, issued_by। **`proto_material_issue_lines`:** issue_id, line_id, qty, remarks।
- **`proto_bundle_cards`:** card_no (`SBC-2026100001`: SBC + বছর + মাস + মাসের ক্রমিক, Brickland-এর SBC-2026080004-এর মতো), sample_id, buyer_id, buyer_office_id (Buyer A/C), style_id, order_id, cutting_no, table_id (Company Management-এর `production_lines`, line_type CUTTING), fabric_type_id, cutting_date, status (DRAFT/SUBMITTED/IN_PROGRESS/SENT/APPROVED/REJECTED/CANCELLED), is_finalized, bundle_status (CREATED/ISSUED/RECEIVED/IN_PRODUCTION/COMPLETED/HOLD/RETURNED/CANCELLED), department_id, assigned_team, operator_id, issue_date, due_date, priority, remarks। (company, table, buyer, cutting_no) unique।
- **`proto_bundles`:** card_id, bundle_no (`SBC-2026100001-01`, কার্ড নং + সারির ক্রম; এটাই স্ক্যান কোড), order_id, sample_type_id, color, size, req_qty, cutting_qty, now_at (P7 Bundle Tracking-এ শেষ স্ক্যানের জায়গা), remarks।
- **`proto_bundle_movements`:** bundle_id, card_id, process (DELV_PRINT/DELV_EMBR/DELV_PRINT_EMBR/RECV_EMBELLISHMENT/DELV_SEWING/SEWING_DONE/DISTRIBUTION), movement_date, scanned_at, scanned_by, qty (bundle-এর কাটিং), operator_id, received_at, start_at, end_at, expected_hours, status (PENDING/ISSUED/RECEIVED/IN_PROGRESS/COMPLETED/HOLD/DELAYED/REJECTED), delay_reason, remarks। (bundle, process) unique। `proto_bundle_cards`-এ নতুন delivered_qty আর rejected_qty (Sample Card)।
- **`proto_day_sheets`:** target_date (কোম্পানি ধরে unique; URL-এ এটাই ঠিকানা), sample_room (লেখা), shift (DAY/EVENING/NIGHT/GENERAL), unit_company_id, supervisor_id, prepared_by, state (DRAFT/CONFIRMED/CLOSED), total_manpower, available_manpower, shift_hours (ডিফল্ট ৮), remarks।
- **`proto_day_sheet_lines`:** day_sheet_id, planning_line_id (Capacity Planning-এর line, বাধ্যতামূলক), sample_id, sample_type_id, order_id, target, wh, manpower, target_hrs, actual, actual_hrs, start_time, end_time, team, priority, remarks, sort_order।
- **`proto_target_plans`:** plan_no (`TP-2026-00001`), period (DAILY/WEEKLY/MONTHLY), period_start, period_end, sample_room, team, supervisor_id, sample_type_id, target_qty, available_manpower, available_hours, planned_hours, remarks।
- **`proto_quality_checks`:** qc_no (`QC-2026-00001`), sample_id, version, checked_by, check_date, result (PASS/PASS_WITH_COMMENT/FAIL/REWORK_REQUIRED), severity (MINOR/MAJOR/CRITICAL), defect_type, defect, correction, remarks, attachment।
- **`proto_quality_checkpoints`:** তেরোটা checkpoint: Measurement, Fabric, Color, Construction, Stitch, Seam, Print, Embroidery, Label, Accessories, Finishing, Appearance, Packing। প্রতিটার result (OK/NOT_OK/NA), defect, severity, remarks।
- **`proto_approvals`:** approval_no (`APR-2026-00001`), sample_id, version, quality_check_id (QC Ref.), step (MERCHANDISING_REVIEW/BUYER_APPROVAL), submitted_date, decision (PENDING/APPROVED/APPROVED_WITH_COMMENT/REJECTED/REVISION_REQUIRED), approval_date, approval_by (লেখা), buyer_comment, internal_comment, attachment, decided_by।
- **`proto_feedback`:** feedback_no (`FB-2026-00001`), sample_id, version, approval_id, feedback_date, feedback_type (APPROVAL/COMMENT/CORRECTION/REJECTION/GENERAL), status (OPEN/IN_PROGRESS/RESOLVED/CLOSED), comment, চারটা *_change ঘর, action_required, assigned_to, due_date, attachment।
- **`proto_handovers`:** handover_no (`HO-2026-00001`), sample_id, version, qty, handover_date, handover_from, handover_to (লেখা), department_id, status (READY/HANDOVER_PENDING/HANDED_OVER/COURIERED/IN_TRANSIT/DELIVERED/RECEIVED/CANCELLED), courier, tracking_no, expected_delivery, actual_delivery, received_by, received_date, remarks, attachment।

## ৫. Workflow / Status ও Business Rules

- **Requisition:**
  - Draft → Submit → Pending Approval। প্রথমে Merchandiser, তারপর Merchandising Manager, তারপর Sample Manager সিদ্ধান্ত নেন।
  - Reject-এ কারণ বাধ্যতামূলক ("A rejection needs a reason."), আর রিকুইজিশন Rejected হয়ে মার্চেন্ডাইজারের কাছে ফেরে। Rejected-টা সেভ করলে Draft হয়।
  - Sample Manager-এর Approve-এ Sample নম্বর তৈরি হয়। Assigned To শুধু এই স্তরে দেওয়া যায়; দিলে Sample সঙ্গে সঙ্গে Assigned হয় আর দায়িত্বপ্রাপ্তকে বার্তা যায়।
  - Approved থাকলে তালিকার Assign বাটন দিয়ে পরেও দায়িত্ব দেওয়া যায়। Assigned To ছাড়া চলে না।
  - Draft বা Rejected অবস্থায় পুরো ফর্ম বদলানো যায়। অনুমোদনের পর শুধু Assigned To। ডেভেলপমেন্ট শুরু হলে বা সম্পন্ন হলে Cancel চলে না।
  - Sample Type অর্ডার- বা PO-বাধ্যতামূলক হলে Order No লাগে। স্টাইল আর অর্ডার বাছাই করা বায়ারেরই হতে হয়। Stage খালি থাকলে Sample Type-এর stage, Requested By খালি থাকলে লগইন করা ব্যবহারকারী, Season খালি থাকলে স্টাইলের season, Product Category খালি থাকলে স্টাইলের garment।
  - ফাঁকা Save-এ Brickland-এর বার্তা: "Requisition Date is required."
- **Development:**
  - শুধু খোলা (বাতিল নয়) Sample, আর তার বর্তমান version-এ একটাই development ("… version 1 already has a development.")।
  - Progress = প্রযোজ্য point-এর মধ্যে সম্পন্নের ভাগ; Not Required গোনা হয় না।
  - সব প্রযোজ্য point সম্পন্ন হলে, বা status Completed করলে, development বন্ধ ও লক হয়, Actual Completion-এ আজকের তারিখ বসে।
  - শুরু হলে Sample "Development · In Progress" আর রিকুইজিশন In Development হয়; সম্পন্ন হলে দুটোই Completed। Revision-এর version-এর development হলে সেই revision In Progress, তারপর Completed হয়।
- **Revision:**
  - Sample-এর বর্তমান version থেকে পরের version (V1 → V2)। Sample "Revision · In Progress" হয়, রিকুইজিশন আবার In Development।
  - Sample Type-এ revision_allowed বন্ধ থাকলে রিভিশন চলে না।
  - শুধু Open অবস্থায় বদলানো যায়, কখনো মোছা যায় না। Approve Revision দিলে Approved By আর Approved At বসে।
  - Source Approval-এ সেই স্যাম্পলের Rejected বা Revision Required অনুমোদন, Source Feedback-এ তার ফিডব্যাক; অন্য স্যাম্পলের হলে আটকায়। অনুমোদনের Create Revision `?tab=revision&sample=SMP-…&approval=APR-…` দিয়ে ফর্ম খোলে, স্যাম্পল, উৎস আর বায়ারের মন্তব্য বসানো থাকে।
- **Material Requisition:**
  - নতুন রিকুইজিশনে স্যাম্পল লাগে; রিকুইজিশন নং, বায়ার আর স্টাইল স্যাম্পল থেকে আসে। Requested By, Requisition To আর Delivery To বাধ্যতামূলক। Required Date রিকুইজিশনের তারিখের আগে হতে পারে না। অন্তত একটা লাইন লাগে, আর প্রতিটা লাইনে Item আর ০-এর বেশি Req. Qty।
  - সেভ করলে প্রোগ্রাম আর লাইন হুবহু গ্রিডের মতো হয়ে যায়। একক আসে আইটেম থেকে।
  - **Submit** (Draft থেকে, নিশ্চিতকরণসহ) → **Dept. Approve** (Submitted থেকে, dialog নেই) → **Store Verify** (Dept. Approved থেকে; প্রতিটা লাইনে Available Qty আর Warehouse লিখে সেভ না করলে আটকায়; এরপর লাইন লক) → **Approve** (Store Verified থেকে)। প্রতিটা ধাপ শুধু নিজের state থেকে চলে।
  - **Reject:** অনুমোদনের অপেক্ষায় থাকা (Submitted, Dept. Approved, Store Verified) রিকুইজিশনে চলে, তবে কারণ আগে সেভ করতে হয়। প্রত্যাখ্যাত রিকুইজিশন আবার সেভ করলে Draft হয়, অনুমোদনগুলো মুছে যায়।
  - **Cancel:** Partially বা Fully Issued হওয়ার আগে পর্যন্ত চলে। **Delete:** অনুমোদনের আগে, অথবা বাতিল হলে।
  - Approve হওয়ার পর হেডার আর বদলায় না। Short Qty = Req. Qty − Available Qty।
- **Material Issue:**
  - শুধু Approved বা Partially Issued রিকুইজিশনের বিপরীতে। Issue Date লাগে, আর অন্তত একটা লাইন।
  - প্রতিটা লাইনে ইস্যু করা যায় প্রয়োজন আর যাচাই করা প্রাপ্য পরিমাণের মধ্যে যেটা কম, তার বাকি অংশ পর্যন্ত। এক ইস্যুতে একই লাইন দুবার থাকলে যোগ করে দেখা হয় ("Row 2: no more than 3 KG is left to issue on this line.")।
  - Store খালি রাখলে রিকুইজিশন যে স্টোরে তোলা হয়েছিল সেটা বসে। সেভ হওয়া ইস্যু কখনো বদলায় না বা মোছে না।
  - সব লাইন যতটা পারে ততটা ইস্যু হয়ে গেলে রিকুইজিশন Fully Issued, নইলে Partially Issued। Issued % = issued ÷ req।
  - Short Lines = যেসব লাইনে প্রাপ্য পরিমাণ প্রয়োজনের কম আর এখনো কিছু ইস্যু হয়নি।
- **Bundle Card:**
  - Buyer, Cutting#, Table আর Cutting Date বাধ্যতামূলক। Table শুধু Cutting ধরনের line। Cutting# একই buyer-এর জন্য একই টেবিলে একবারই ("Cutting# 7 is already used on this table for this buyer.")।
  - স্যাম্পল দিলে buyer সেই স্যাম্পলেরই হতে হয়; ফর্মে স্যাম্পল বাছলে buyer, buyer A/C, স্টাইল আর অর্ডার বসে যায়। স্টাইল আর অর্ডার buyer-এর হতে হয়। Due Date কাটিংয়ের তারিখের আগে নয়।
  - অন্তত একটা bundle লাগে, আর প্রতিটায় Cutting ≥ ১। সেভ করলে গ্রিড হুবহু বসে আর scan code নতুন করে তৈরি হয়।
  - **Bundle Status-এর ধাপ:** Issue (Created থেকে; কার্ড finalized হয়, Issue Date খালি থাকলে আজ), Receive (Issued থেকে), Start / Resume (Received বা Hold থেকে → In Production), Hold (Issued, Received বা In Production থেকে), Return (Hold পর্যন্ত যেকোনো ফ্লোর অবস্থা থেকে), Cancel (Completed বা Returned ছাড়া; কার্ডের Status-ও Cancelled হয়)।
  - Received হলে বা কোনো bundle স্ক্যান হলে (now_at) bundle নড়াচড়া শুরু হয়েছে ধরা হয়। তখন আর re-bundle হয় না: হেডার সেভ হয়, bundle বদলায় না। Completed, Returned বা Cancelled হলে কার্ড লক।
  - Overdue = Due Date পার হয়েছে আর Bundle Status বন্ধ হয়নি।
- **Bundle Tracking (Scan Desk):**
  - শুধু finalized কার্ডের, আর যার bundle ফ্লোরে (Issued, Received, In Production), সেই bundle স্ক্যান হয়। কোড ছোট হাতের হলেও মেলে।
  - প্রতিটা process-এ একটা bundle একবারই। Print, Embr আর Print+Embr একটা আরেকটাকে বাদ দেয়। Recv. From Embellishment শুধু পাঠানো bundle-এর। বাইরে থাকা bundle (পাঠানো কিন্তু ফেরেনি) Delv. for Sewing-এ যায় না। Sewing Done-এর আগে Delv. for Sewing, Distribution-এর আগে Sewing Done লাগে।
  - প্রতিটা স্ক্যান নিজেই রেকর্ড: bundle-এর `now_at` বদলায়, কার্ড প্রথম স্ক্যানে In Production, আর সব bundle Distribution-এ গেলে Completed। Completed কার্ডে আর স্ক্যান হয় না।
  - Undo চলে যতক্ষণ bundle পরের ধাপে যায়নি (`now_at` এখনো ওই process)। Undo-র পর `now_at` আগের ধাপে ফেরে, আর শেষ Distribution ফেরালে কার্ড আবার In Production।
  - স্ক্যান হলে কার্ড আর re-bundle হয় না।
- **Tracking Update:** প্রতিটা movement-এ operator, received/start/end, expected hours, status, delay reason। End শুরুর আগে নয়। Delay Hrs = (end − start, বা চলমান হলে এখন পর্যন্ত) − expected, ০-এর কম নয়। Delayed status বা delay থাকলে কারণ বাধ্যতামূলক। সময় স্থানীয় থেকে offset-সহ পাঠানো হয়।
- **Sample Card:** order ধরে কার্ড: Req Qty, Cutting, Sewn (Sewing Done-এর পরিমাণ), Gone Out (Distribution), Delivered, Rejected। Delivered + Rejected সেলাই হওয়ার বেশি নয়; Hand Over দিলে Delivered = Sewn − Rejected।
- **Daily Target (day sheet):**
  - প্রতি তারিখে একটা শিট ("22 Oct 2026 already has a day sheet — open it from Existing day sheet.")। ফর্মে এমন তারিখ বাছলে সেই শিট লোড হয়।
  - উপস্থিত জনবল মোটের বেশি হতে পারে না। অন্তত একটা লাইন লাগে, আর প্রতিটা লাইনে Line বাধ্যতামূলক। শেষের সময় শুরুর পরে হতে হয়।
  - সেভ করলে লাইনগুলো হুবহু গ্রিডের মতো হয়ে যায়। লাইনে স্যাম্পল দিলে আর Sample Type বা Order খালি থাকলে, রিকুইজিশন থেকে সেগুলো বসে।
  - হিসাব: Total Target = লাইনের target-এর যোগ; Actual = actual-এর যোগ; Achievement = actual ÷ target; Average WH = WH-এর গড়; Planned Hrs = target hrs-এর যোগ; Capacity Hrs = উপস্থিত জনবল × শিফটের ঘণ্টা; Efficiency = অর্জিত ঘণ্টা (প্রতিটা লাইনের target hrs × actual ÷ target) ÷ actual hrs।
  - Planned Hrs ক্ষমতার বেশি হলে সেভ হয়, সাথে সতর্কবার্তা আসে। তালিকায় তখন "Over Capacity"।
  - Overdue মানে তারিখ পার হয়ে গেছে কিন্তু শিট Closed নয়। Closed শিট লক থাকে আর মোছা যায় না; বাকিগুলো তালিকা থেকে মোছা যায়।
- **Weekly / Monthly Plan:**
  - Daily-তে শুরু আর শেষ একই দিন, Weekly-তে ঠিক সাত দিন, Monthly-তে এক ক্যালেন্ডার মাসের প্রথম থেকে শেষ দিন।
  - Actual (Day Sheets) = সময়কালের মধ্যে থাকা দৈনিক শিটের প্রকৃত উৎপাদনের যোগ। পরিকল্পনায় রুম দেওয়া থাকলে শুধু সেই রুমের শিট গোনা হয় (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)।
  - Planned Hours প্রাপ্য ঘণ্টার বেশি হলে সতর্কবার্তাসহ সেভ হয়।
- **Development সম্পন্ন হলে** Sample যায় "Quality · Pending"-এ।
- **Quality Check:**
  - তালিকায় আসে Quality-তে থাকা স্যাম্পল, অথবা Approval-এ আছে কিন্তু ওই version-এ এখনো কোনো অনুমোদন তোলা হয়নি (পুনরায় চেক)।
  - Not OK checkpoint-এ ত্রুটি লাগে ("Seam is Not OK: describe the defect.")। Major বা Critical Not OK থাকলে PASS চলে না। Fail আর Rework Required-এ Defect লাগে।
  - Pass বা Pass With Comment → "Approval · Pending"। Fail → "Quality · Failed"। Rework Required → "Development · Rework", সেই version-এর development আবার In Progress, রিকুইজিশন In Development।
  - শুধু বর্তমান version-এর সর্বশেষ চেক সংশোধন করা যায়, আর তার ওপর অনুমোদন তোলা হলে আর নয় (`correctable`)।
- **Approval:**
  - সর্বশেষ চেক পাস না করলে অনুমোদন তোলা যায় না; সেই চেক QC Ref. হিসেবে যুক্ত হয়।
  - একই version-এ একই ধাপ Pending বা অনুমোদিত থাকলে আবার তোলা যায় না। Buyer Approval-এর আগে একই version-এ Merchandising Review অনুমোদিত হতে হয়।
  - Rejected আর Revision Required-এ বায়ার বা অভ্যন্তরীণ মন্তব্য লাগে। Approval Date খালি রাখলে সিদ্ধান্তের দিন (আজ) বসে, Submitted Date-এর আগে হতে পারে না।
  - সিদ্ধান্ত হলে লক। Rejected → "Approval · Rejected", Revision Required → "Approval · Revision Required"। Buyer Approval অনুমোদিত হলে → "Handover · Ready"।
- **Buyer Feedback:**
  - Action Required দিলে Assigned To আর Due Date লাগে, আর নতুন দায়িত্বপ্রাপ্তকে সঙ্গে সঙ্গে বার্তা যায় (একই ব্যক্তিকে আবার নয়)।
  - Resolved বা Closed হলে বার্তা মুছে যায়। Closed হলে লক। Due Date ফিডব্যাকের তারিখের আগে হতে পারে না। Related Approval একই স্যাম্পলের হতে হয়।
- **Handover:**
  - টাইপ যে অনুমোদন চায় তা ছাড়া হস্তান্তর চলে না: buyer_approval_required হলে Buyer Approval লাগে; না হলে পাস করা QC; qc_required-ও বন্ধ থাকলে সম্পন্ন development-ই যথেষ্ট।
  - বাতিল নয় এমন সব হ্যান্ডওভার মিলিয়ে স্যাম্পলের পরিমাণের বেশি নয় ("No more than the sample quantity (3) can be handed over: 1 left.")। Handover From খালি রাখলে লগইন করা ব্যবহারকারী।
  - Couriered-এ কুরিয়ার আর ট্র্যাকিং নম্বর, Delivered-এ প্রকৃত ডেলিভারি, Received-এ কে আর কবে লাগে। পরের তারিখগুলো হ্যান্ডওভারের তারিখের আগে হতে পারে না।
  - কোনো হ্যান্ডওভার Delivered বা Received হলে Sample "Handover · Delivered"। Received হলে লক। শুধু Ready হ্যান্ডওভার মোছা যায়।
  - Delay (days) = প্রত্যাশিত ডেলিভারির পরে প্রকৃত ডেলিভারি পর্যন্ত (না পৌঁছালে আজ পর্যন্ত)।

## ৬. API ও পারমিশন

পারমিশন: `prototype.requisition.view|manage|approve`, `prototype.development.view|manage`, `prototype.revision.view|manage|approve`, `prototype.material.view|manage|approve|issue`, `prototype.bundle.view|manage`, `prototype.tracking.view|manage`, `prototype.target.view|manage`, `prototype.quality.view|manage`, `prototype.approval.view|manage`, `prototype.feedback.view|manage`, `prototype.handover.view|manage`। Factory Admin সব পান। Factory Manager সব দেখতে, রিকুইজিশনে সিদ্ধান্ত নিতে, রিভিশন অনুমোদন করতে আর ম্যাটেরিয়াল রিকুইজিশন অনুমোদন করতে পারেন (তোলা বা ইস্যু করা নয়)।

| Method | Path |
|---|---|
| GET | `/prototype/requisitions/options`, `/prototype/requisitions`, `/prototype/requisitions/{SR-…}` |
| POST / PUT | `/prototype/requisitions`, `/prototype/requisitions/{SR-…}` |
| POST | `/prototype/requisitions/{SR-…}/submit`, `/decide`, `/assign`, `/cancel` |
| GET / POST / PUT | `/prototype/developments[/options]`, `/prototype/developments/{DEV-…}` |
| GET / POST / PUT | `/prototype/revisions[/options]`, `/prototype/revisions/{REV-…}` |
| POST | `/prototype/revisions/{REV-…}/approve` |
| GET / POST / PUT / DELETE | `/prototype/material-requisitions[/options]`, `/prototype/material-requisitions/{MR-…}` |
| POST | `/prototype/material-requisitions/{MR-…}/submit`, `/dept-approve`, `/verify`, `/approve`, `/reject`, `/cancel` |
| GET / POST | `/prototype/material-issues[/options]`, `/prototype/material-issues/{MI-…}` (শুধু তৈরি আর দেখা) |
| GET / POST / PUT | `/prototype/bundle-cards[/options]`, `/prototype/bundle-cards/{SBC-…}` |
| POST | `/prototype/bundle-cards/{SBC-…}/issue`, `/receive`, `/start`, `/hold`, `/return`, `/cancel` |
| GET | `/prototype/tracking[/options]`, `/prototype/tracking/desk/{process}`, `/prototype/tracking/bundles`, `/prototype/tracking/lookup`, `/prototype/tracking/sample-cards` |
| POST | `/prototype/tracking/scan` (process, code) |
| PUT / DELETE | `/prototype/tracking/movements/{id}` (Tracking Update / Undo) |
| PUT | `/prototype/tracking/sample-cards/{SBC-…}` (delivered, rejected, hand_over) |
| GET / POST / PUT / DELETE | `/prototype/day-sheets[/options]`, `/prototype/day-sheets/{2026-10-22}` (DELETE Closed নয় এমন শিটে) |
| GET / POST / PUT | `/prototype/target-plans[/options]`, `/prototype/target-plans/{TP-…}` |
| GET / POST / PUT | `/prototype/quality-checks[/options]`, `/prototype/quality-checks/{QC-…}` |
| GET / POST / PUT | `/prototype/approvals[/options]`, `/prototype/approvals/{APR-…}` |
| GET / POST / PUT | `/prototype/feedback[/options]`, `/prototype/feedback/{FB-…}` |
| GET / POST / PUT / DELETE | `/prototype/handovers[/options]`, `/prototype/handovers/{HO-…}` (DELETE শুধু Ready) |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Prototype Desk → MASTER DATA → OPERATION → **Sample Planning & Development** (৩), **Sample Material & Production** (৪: Prototype Material Requisition, Sample Bundle Card Issue, Sample Room Daily Target, Sample Bundle Tracking), **Sample Quality & Approval** (৩), **Sample Handover & Delivery** (১)।

- **Sample Requisition:**
  - **Requisition List:** pill Draft / Pending Approval / Approved / Rejected / Overdue, সংখ্যাসহ। ফিল্টার Requisition Date from–to, Buyer, Sample Type, Priority, Status। Clear আর New Requisition।
  - **কলাম:** Requisition No, Date, Buyer, Style, Sample Type, Stage, Qty, Required ("05 Nov 2026 (overdue)"), Priority, Pending At, Sample No, Status।
  - **সারির বাটন:** Open · Decide · Submit · Assign · Cancel। Submit, Assign আর Cancel-এ নিশ্চিতকরণ ডায়ালগ আসে, যেমন "Assign this sample requisition?"।
  - **Sample Requisition ফর্ম:** উপরে Existing sample requisition; সেকশন Requisition, Buyer & Style, Sample, People, Notes; প্রতিটা ঘরের নিচে Brickland-এর hint; Approval History; নোট, Clear আর Save sample requisition।
  - **Requisition Approval:** Existing approval decision ("SR-… — LPP · awaiting Merchandiser"); Requisition-এর তথ্য শুধু পড়ার জন্য; Decision*, Comments, Rejection Reason, Assigned To (শুধু Sample Manager স্তরে), Attachment; Approval History; Record Decision।
- **Sample Development:**
  - **Development List:** ফিল্টার Development Date, Buyer, Sample Type, Status (Overdue সহ)। কলাম Development No, Date, Sample No, Version, Buyer, Style, Sample Type, Expected, Completed, Tasks, Progress।
  - **ফর্ম:** সেকশন Development, Sample (শুধু পড়ার জন্য), Schedule, Team, Notes, আর Development Checklist (Task, Task Status, Completed At, Remarks)।
- **Sample Revision:**
  - **Revision List:** ফিল্টার Revision Date, Buyer, Sample No, Status, Approved?। কলাম Revision No … Revisions।
  - **ফর্ম:** সেকশন Revision, Why, What Changes, Sign-off; Revisions of This Sample; Approve Revision আর Save sample revision। Source Approval/Feedback শুধু বাছাই করা স্যাম্পলের।
- **Prototype Material Requisition:** চার ট্যাব, Records · Sample Fabric Requisition · Sample Programs · Material Issue।
  - **Records:** pill Requisitions / Awaiting Approval / To Issue / Fully Issued / With Shortage। ফিল্টার State, Sample No, Req. Date from–to। কলাম Requisition No, Req. Date, Sample No, Required Date, Requisition By, Requisition Type, Requisition To, Delivery To, Programs, Items, Short Lines, Req. Qty, Issued %, State, Remarks।
  - **Sample Fabric Requisition:** Existing requisition; সেকশন Requisition আর Approval (State, Dept. Approved By, Store Verified By, Approved By, Rejection Reason)। গ্রিড Sample Requisition (Sample Program No, Company Name, Sample Req. Date) আর Fabric Requisition (Item Code / Item, Material Group, Fabric Type, Color, Size, Description, Specification, Req. Qty, Unit, Available Qty, Short Qty, Issued, Warehouse, Remarks, তার নিচে Total)।
  - Save-এর পাশে state আর অনুমতি অনুযায়ী বাটন: Delete, Submit, Dept. Approve, Store Verify, Approve, Reject, Cancel Requisition। Dept. Approve ছাড়া প্রতিটায় Brickland-এর নিশ্চিতকরণ dialog আসে।
  - **Sample Programs:** বাঁয়ে খোঁজার ঘরসহ প্রোগ্রামের তালিকা; একটা বাছলে ডানে তাতে তোলা রিকুইজিশনগুলো (নম্বরে ক্লিক করলে ফর্ম খোলে)।
  - **Material Issue:** Existing material issue; Requisition, Issue Date, Store, Received By, Remarks; Issued Lines গ্রিড (Requisition Line, Issue Qty, Remarks), লাইনের লেবেলে বাকি পরিমাণ দেখায়, যেমন "MR-2026-00001 · Single Jersey 160 GSM (FB-SJ-160) · Navy · bal 8 KG"। সেভ হওয়া ইস্যু শুধু দেখা যায়।
  - URL: `?tab=requisition&mr=MR-2026-00001`, `?tab=issue&mi=MI-2026-00001`।
- **Sample Bundle Card Issue:** তিন ট্যাব, Records · Bundle Card Creation · Cutting List।
  - **Records:** pill Cards / On the Floor / On Hold / Overdue। ফিল্টার Bundle Status, Sample No, Overdue (Overdue / On time), Cut Date from–to। কলাম Card No, Cut Date, Sample No, Buyer, Style, Order, Cutting#, Table, Fabric, Bundles, Req Qty, Cutting Qty, Operator, Due Date, Bundle Status, Is Finalized।
  - **Bundle Card Creation:** Existing bundle card; সেকশন Styling + Cutting Information (Sample No, Buyer, Buyer A/C, Style, Job/Order#, Cutting#, Table, Fabric, Cutting Date, Status, Is Finalized?, Bundle Status) আর Issue (Department, Assigned Team, Assigned Operator, Issue Date, Due Date, Priority, Remarks)। গ্রিড Bundling Information (Order, Sample, Color, Size, Req Qty, Cutting, Scan Code, Now At, Remarks), Add Record, নিচে মোট। Save-এর পাশে Bundle Status অনুযায়ী Issue / Receive / Start / Resume / Hold / Return / Cancel, প্রতিটায় নিশ্চিতকরণ dialog।
  - **Cutting List:** বাঁয়ে খোঁজার ঘরসহ কাটিং টেবিলের তালিকা (প্রতিটায় কার্ডের সংখ্যা); একটা বাছলে ডানে তাতে কাটা কার্ড, bundle সংখ্যা, কাটিং পরিমাণ আর অবস্থা।
  - URL: `?tab=card&card=SBC-2026100001`।
- **Sample Bundle Tracking:** পাঁচ ট্যাব, Records · Scan Desk · Tracking Update · Bundle Lookup · Sample Card।
  - **Records:** pill Movements / Delayed আর Delay Hours (যোগফল, ফিল্টার করে না)। ফিল্টার Process, Status, Sample No, Date from–to। কলাম Date, Process, Bundle Card, Sample No, Card No, Job/Order#, Sample Type, Color, Size, Qty, Operator, Status, Delay Hrs, Delay Reason, Scanned At।
  - **Scan Desk:** সাতটা process-এর বাটন, প্রতিটায় আজকের সংখ্যা; Bundle Card ঘরে স্ক্যান বা নম্বর লিখে Enter; "N Scanned", "N pcs", Clear; নিচে process-এর Brickland hint; আজকের চলাচল (Undo সহ); পাতার নোট।
  - **Tracking Update:** বাঁয়ে চলাচল করা bundle (খোঁজার ঘরসহ, এখন কোথায়); ডানে তার প্রতিটা movement এক সারিতে, আলাদা Save।
  - **Bundle Lookup:** Cut Month, Bundle Status, All, খোঁজার ঘর (Bundle / Card / Sample / Style No) আর Search, Delayed?; মাসের chip; ফলাফল আর Print; নোট।
  - **Sample Card:** বাঁয়ে Buyer A/C — Job/Order#; ডানে কার্ডের সারি, Delivered আর Rejected ঘরেই বদলানো যায়, Save আর Hand Over।
- **ProtoList-এর সংখ্যা-pill:** সার্ভার পাতা function পাঠাতে পারে না, তাই `sum: "<field>"` দিলে ProtoList নিজেই যোগফল দেখায়; client পাতা চাইলে `stat` function দিতে পারে।
- **Sample Room Daily Target:** তিন ট্যাব, Records · Target Setting · Weekly / Monthly Plan।
  - **Records:** pill Day Sheets / Overdue / Over Capacity আর Avg. Achievement (এটা শুধু সংখ্যা দেখায়, ফিল্টার করে না)। ফিল্টার State, Overdue, Sample Room, Target Date from–to। কলাম Target Date, Sample Room, Shift, Company, Lines, Total Target, Actual, Achievement %, Average WH, Planned Hrs, Capacity Hrs, Efficiency %, Prepared By, Remarks, Status। সারির বাটন Open আর Remove (Closed হলে বন্ধ)।
  - **Target Setting:** Existing day sheet; সেকশন Day (Target Date, Sample Room, Shift, Company, Supervisor, Prepared By, State) আর Capacity (Total/Available Manpower, Shift Hours), Remarks। তারপর Line Targets গ্রিড: Line, Sample No, Sample Type, Job/Order#, Target, WH, Manpower, Target Hrs, Actual, Actual Hrs, Start Time, End Time, Team, Priority, Remarks, আর Add Line ও সারি সরানোর বাটন। গ্রিডের নিচে Total Target Qty, Average WH, Total Manpower, Total Target Hrs, Total Actual, Capacity Hrs।
  - **Weekly / Monthly Plan:** Existing target plan; সেকশন Period (Period, Start, End, Sample Room, Team, Supervisor, Sample Type) আর Target & Capacity (Target Qty, Available Manpower, Available Hours, Planned Hours, Actual (Day Sheets), Achievement %); Day Sheets in Period টেবিল।
  - URL: `?tab=target&date=2026-10-22`, `?tab=plan&plan=TP-2026-00001`।
- **চারটা নতুন স্ক্রিন** Brickland-এর মতো দুই ট্যাবে: **Records** আর ফর্ম। ফর্মে উপরে "Existing …" বাছাই, সেকশনের নিচে Brickland-এর নিয়ম-নোট, Clear আর Save।
- **Sample Quality Check:**
  - **তালিকা:** pill Total Checks / Passed / Failed / Rework। ফিল্টার Check Date, Buyer, Result, Sample No। কলাম QC No, Sample No, Style No, Buyer, Version, Check Date, Defects, Severity, Defect, Checked By।
  - **ফর্ম:** সেকশন Sample, Check, Findings, আর QC Checklist টেবিল (Checkpoint, Result, Defect, Severity, Remarks)।
- **Sample Approval:**
  - **তালিকা:** pill Pending / Approved / Rejected / Revision Required। ফিল্টার Submitted Date, Buyer, Step, Decision, Sample No। কলাম Approval No, Sample No, Style No, Buyer, Version, Step, Submitted, Approval Date, Waiting (days), Approval By, QC Ref., Comment।
  - **ফর্ম:** সেকশন Sample (QC Ref. সহ), Approval (Step ডিফল্ট Merchandising Review, Decision ডিফল্ট Pending), Comments। সিদ্ধান্তের পর লক; Rejected/Revision Required-এ **Create Revision**।
- **Buyer Feedback:**
  - **তালিকা:** pill Open / Action Required / Overdue / Resolved / Closed। ফিল্টার Feedback Date, Buyer, Type, Status, Assigned To। কলাম Feedback No, Date, Sample No, Version, Buyer, Type, Comment, Approval, Action Req., Assigned To, Due Date, Overdue (days)।
  - **ফর্ম:** সেকশন Sample (Related Approval সহ), Feedback, Comment, Changes Asked For, Follow-up (Action Required চেকবক্স)।
- **Sample Handover / Delivery:**
  - **তালিকা:** pill Waiting Handover / On the Way / Delivered / Received / Past Expected Delivery। ফিল্টার Handover Date, Buyer, Status, Courier, Sample No। কলাম Handover No, Handover Date, Sample No, Buyer, Qty, Handover To, Courier / Tracking, Expected Delivery, Actual Delivery, Delay (days), Received By। সারির বাটন Open আর Remove (শুধু Ready, নিশ্চিতকরণসহ)।
  - **ফর্ম:** সেকশন Sample (Sample Qty), Handover (Qty, Date, From, To, Department, Status), Courier, Receipt।

ফর্মে কোনো রেকর্ড লোড করলে পাতা রিলোড হয় না। URL-এ নম্বর থাকে (`?req=SR-2026-00001`, `?qc=QC-…`, `?apr=APR-…`, `?fb=FB-…`, `?ho=HO-…`), id নয়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Prototype/Database/Migrations/2026_12_09_000100_create_prototype_samples.php`, `2026_12_10_000100_create_prototype_quality_approval_handover.php`, `2026_12_10_000200_add_handover_from_and_department.php`, `2026_12_10_000300_widen_proto_sample_state.php`, `2026_12_11_000100_create_prototype_daily_targets.php`, `2026_12_12_000100_create_prototype_material_requisitions.php`, `2026_12_13_000100_create_prototype_bundle_cards.php`, `2026_12_14_000100_create_prototype_bundle_movements.php`, `Models/SampleRequisition.php`, `SampleRequisitionDecision.php`, `Sample.php`, `SampleDevelopment.php`, `SampleDevelopmentTask.php`, `SampleRevision.php`, `SampleQualityCheck.php`, `SampleQualityCheckpoint.php`, `SampleApproval.php`, `SampleFeedback.php`, `SampleHandover.php`, `SampleDaySheet.php`, `SampleDaySheetLine.php`, `SampleTargetPlan.php`, `MaterialRequisition.php`, `MaterialRequisitionProgram.php`, `MaterialRequisitionLine.php`, `MaterialIssue.php`, `MaterialIssueLine.php`, `BundleCard.php`, `Bundle.php`, `BundleMovement.php`, `Support/Prototype.php`, `Support/SampleCards.php`, `Http/Controllers/Api/SampleRequisitionApiController.php`, `SampleDevelopmentApiController.php`, `SampleRevisionApiController.php`, `SampleQualityCheckApiController.php`, `SampleApprovalApiController.php`, `SampleFeedbackApiController.php`, `SampleHandoverApiController.php`, `SampleDaySheetApiController.php`, `SampleTargetPlanApiController.php`, `MaterialRequisitionApiController.php`, `MaterialIssueApiController.php`, `BundleCardApiController.php`, `BundleTrackingApiController.php`, `Routes/api.php` |
| পারমিশন / বার্তা | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`prototype`), `RoleSeeder.php`, `app/Modules/Notification/Models/Notification.php` (`SAMPLE_ASSIGNED`, `SAMPLE_FEEDBACK_ACTION`), `lang/{en,bn}/notification.php` |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`PROTOTYPE.OPERATION`, `PROTOTYPE.SAMPLE_PLANNING`, `PROTOTYPE.SAMPLE_MATERIAL`, `PROTOTYPE.SAMPLE_QUALITY`, `PROTOTYPE.SAMPLE_HANDOVER`, এগারোটা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/prototype-desk/[screen]/sample-planning.js`, `sample-material.js`, `sample-quality.js`, `actions.js`, `page.js`, `frontend/src/components/prototype/proto-kit.jsx` (`ProtoList` সংখ্যা-pill সহ, `Section`, `Field`, `useDocForm` সেভ-সতর্কবার্তা আর `take` সহ, `DocHead`, `DocFoot`), `requisition.jsx`, `development.jsx`, `revision.jsx`, `quality.jsx`, `approval.jsx`, `feedback.jsx`, `handover.jsx`, `daily-target.jsx`, `material.jsx`, `bundle-card.jsx`, `tracking.jsx`, `frontend/src/components/erp/records-grid.jsx` (`rowActions`), `frontend/src/components/ui/select.jsx` (লম্বা লেবেল এক লাইনে) |
| ভাষা | `lang/{en,bn}/prototype.php` |
| টেস্ট | `tests/Feature/Prototype/SampleRequisitionDevelopmentTest.php`, `SampleQualityApprovalHandoverTest.php`, `SampleDailyTargetTest.php`, `SampleMaterialRequisitionTest.php`, `SampleBundleCardTest.php`, `SampleBundleTrackingTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **পরের ধাপ:** 07/03-এর সব স্ক্রিন চালু, আর প্রতিটা controller এখন [Dashboard](02_Dashboard.md)-এর ledger-এ (timeline আর alert) লেখে। বাকি Reports (07/04, 07/05); আর HR-এর কর্মী master এলে Assigned Operator কর্মীতে বদলানো।

## ৯. টেস্ট ও যাচাই

- **`SampleRequisitionDevelopmentTest`:** ২টা টেস্ট, ৯৯টা assertion।
  - ফাঁকা Save-এ Brickland-এর বার্তা। অর্ডার-বাধ্যতামূলক টাইপে Order No লাগে। Stage আর অনুরোধকারী নিজে থেকে বসে।
  - Submit, তিন স্তর, অন্য স্তরে Assigned To আটকায়, কারণ ছাড়া Reject আটকায়, Reject করলে আবার Draft হয়ে আবার ওঠে। SMP তৈরি হয়, Assigned হয়, notification যায়। অনুমোদনের পর শুধু Assigned To বদলায়। Assign আর Cancel। Factory Manager আর Technician-এর অনুমতি।
  - Development: ১৪টা point, ১/১১ = 9.1%, একই version-এ দ্বিতীয়টা আটকায়, সব সম্পন্ন হলে 100%, আজকের তারিখ আর lock; Sample আর রিকুইজিশনের অবস্থা বদলায়।
  - Revision: কারণ বাধ্যতামূলক, V1 → V2, ফাঁকা Requested By-তে লগইন করা ব্যবহারকারী বসে, Approve (দ্বিতীয়বার আটকায়), নতুন version-এর development revision-কে In Progress আর তারপর Completed করে, Open না থাকলে বদলায় না।
- **`SampleQualityApprovalHandoverTest`:** ৫টা টেস্ট, ২০১টা assertion।
  - QC: ফাঁকা Save-এর বার্তা; Fail-এ defect; Not OK checkpoint-এ defect; Major-এ PASS আটকায়; Fail → Quality · Failed; Rework → Development · Rework (development আবার খোলে); পুরনো চেক সংশোধন আটকায়; আবার সম্পন্ন হয়ে পাস → Approval · Pending; সর্বশেষটা সংশোধন হয়।
  - Approval: QC ছাড়া আটকায়; Review-এর আগে Buyer আটকায়; একই ধাপ দুবার আটকায়; আজকের Approval Date; সিদ্ধান্তের পর লক; Revision Required-এ মন্তব্য আর তারিখের ক্রম; Source Approval দিয়ে রিভিশন V2; V2-তে review আবার শুরু; Buyer অনুমোদনে Handover · Ready।
  - Feedback: Action Required-এর নিয়ম, একবারই বার্তা, Resolved-এ বার্তা পড়া, Closed-এ লক, অন্য স্যাম্পলের অনুমোদন আটকায়।
  - Handover: অনুমোদন ছাড়া আটকায়; পরিমাণের সীমা; Couriered/Delivered/Received-এর শর্ত; Delay ২ দিন; Delivered; Received-এ লক; শুধু Ready মোছা যায়। Buyer approval-বিহীন টাইপ QC-র পরে, QC-বিহীন টাইপ development-এর পরেই হস্তান্তর হয়।
- **`SampleDailyTargetTest`:** ২টা টেস্ট, ৬৫টা assertion।
  - Day sheet: ফাঁকা Save; উপস্থিত জনবল বেশি; লাইন ছাড়া; Line ছাড়া সারি; সময়ের ক্রম; ৯০ টার্গেট, ৪৪.৪% অর্জন, গড় WH ৮, ৫৪ পরিকল্পিত, ৬৪ ক্ষমতা, ৮০% দক্ষতা; স্যাম্পল থেকে টাইপ বসে; একই তারিখে দ্বিতীয়টা আটকায়; লাইন বদলায়; ক্ষমতার বেশি হলে সতর্কবার্তা; Closed-এ লক আর মোছা আটকায়; পুরনো Draft Overdue, আর মোছা যায়; অনুমতি।
  - Plan: সাত দিন না হলে আটকায়; সপ্তাহে দুই রুমের শিট মিলে ৬০, ৩০%; রুম দিলে শুধু সেই রুমের; প্রাপ্য ঘণ্টার বেশিতে সতর্কবার্তা; Monthly আর Daily-র সীমা।
- **`SampleMaterialRequisitionTest`:** ১টা টেস্ট, ৮২টা assertion।
  - Options (store, আইটেমের একক, ২৩টা requisition type); ফাঁকা Save, লাইন ছাড়া, তারিখের ক্রম; MR-2026-00001 তৈরি, প্রোগ্রামসহ, আর Sample Programs-এ সেটা দেখা যায়।
  - ভুল state থেকে ধাপ আটকায়; কারণ ছাড়া Reject আটকায়; Reject, তারপর সেভ করলে Draft; Submit, Dept. Approve (নাম আর তারিখ বসে); প্রাপ্য পরিমাণ ছাড়া Verify আটকায়; Short Qty ৪ আর ০, Short Lines ১; Verify-এর পর লাইন লক থাকে কিন্তু হেডার সেভ হয়; Approve-এর পর সেভ আর Delete আটকায়।
  - Issue: লাইনের লেবেলে বাকি পরিমাণ; লাইন ছাড়া আটকায়; ৮-এর বেশি আটকায়; ৫ ইস্যুতে Partially Issued, ৩৫.৭%; একই লাইন দুবার দিয়ে বাকির বেশি আটকায়; বাকিটা ইস্যু করলে Fully Issued; এরপর ইস্যু আর Cancel আটকায়।
  - দ্বিতীয় রিকুইজিশন Cancel করে Delete; অনুমতি (Factory Manager অনুমোদন দেন, তোলেন না বা ইস্যু করেন না; Technician কিছু দেখেন না)।
- **`SampleBundleCardTest`:** ১টা টেস্ট, ৬৩টা assertion।
  - Options-এ শুধু Cutting line টেবিল; স্যাম্পলের buyer আর স্টাইল। ফাঁকা Save; Sewing line টেবিল হিসেবে আটকায়; bundle ছাড়া আটকায়; অন্য buyer আটকায়; Cutting ০ আটকায়।
  - SBC-2026100001 তৈরি, ২টা bundle (৪ প্রয়োজন, ৫ কাটিং), scan code -01, -02; একই টেবিলে একই Cutting# আটকায়, অন্য টেবিলে চলে (SBC-2026100002)।
  - নড়াচড়ার আগে re-bundle (৩টা কোড); Created থেকে Receive আটকায়; Issue (finalized, আজকের তারিখ); Issued অবস্থায় re-bundle চলে; Receive-এর পর bundle বদলায় না কিন্তু হেডার সেভ হয়; Start, Hold, Resume, Return; Returned-এ লক আর Cancel আটকায়।
  - দ্বিতীয় কার্ড Cancel (Status-ও Cancelled); অনুমতি (Technician দেখেন না, Factory Manager দেখেন কিন্তু Issue করতে পারেন না)।
- **`SampleBundleTrackingTest`:** ২টা টেস্ট, ৮১টা assertion।
  - অচেনা কোড; না পাঠিয়ে গ্রহণ আটকায়; Embr-এ পাঠানো (ছোট হাতের কোডেও), কার্ড In Production; একই process দুবার আটকায়; Embr-এর পর Print আটকায়; বাইরে থাকা অবস্থায় Sewing আটকায়; Sewing ছাড়া Sewing Done আটকায়।
  - Undo: পরের ধাপে গেলে আটকায়, শেষটা ফেরে আর `now_at` আগের ধাপে; আজকের desk আর process অনুযায়ী সংখ্যা।
  - দুই bundle সেলাই, একটা বিতরণে কার্ড এখনো In Production, দুটোতে Completed; শেষ বিতরণ ফেরালে আবার In Production; Completed কার্ডে স্ক্যান আটকায়।
  - Tracking Update: End শুরুর আগে আটকায়; ১ ঘণ্টা delay-তে কারণ বাধ্যতামূলক; কারণসহ সেভ, Delay ১, Delayed। Records ৮, bundle ভেদে ৫ আর ৩ movement, Lookup-এ Distribution আর Delayed।
  - Sample Card: Sewn ৫, Gone Out ৫; ৫ + ১ আটকায়; Rejected ১ দিয়ে Hand Over → Delivered ৪। অনুমতি (Technician দেখেন না, Factory Manager দেখেন কিন্তু স্ক্যান করেন না)।
  - ইস্যু না করা কার্ড স্ক্যান হয় না; ইস্যুর পর হয়, আর তখন আর re-bundle নয়।
- **রিগ্রেশন:** ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, IconCatalogue, ErpMasterDataSeeder, Notification-সংক্রান্ত টেস্ট।
- **ব্রাউজার যাচাই** (২০২৬-১০-২১, owner লগইন):
  - সাইডবারে MASTER DATA, তারপর OPERATION → Sample Planning & Development (৩) ✔
  - নতুন রিকুইজিশন: LPP, SS27-TEE01, PP Sample, ২ পিস, Urgent, Merchandising Department → SR-2026-00001 (Draft) ✔ → তালিকা থেকে Submit → Pending Approval · Merchandiser ✔
  - Requisition Approval-এ তিনবার Approve, শেষবার Assigned To Karim Mia → SMP-2026-00001, তালিকায় In Development ✔
  - Development: SMP বেছে, তারিখ, Pattern Master, Pattern আর Fabric Completed, Embroidery আর Wash Not Required → DEV-2026-00001, progress 16.7% ✔
  - Revision: কারণ আর মাপের বদল লিখে → REV-2026-00001 (V1 → V2), তারপর Approve Revision ✔
  - সব রেকর্ড dev DB-তে রাখা আছে।
- **ব্রাউজার যাচাই P8–P11** (২০২৬-১০-২১, owner লগইন):
  - সাইডবারে Sample Quality & Approval (৩) আর Sample Handover & Delivery (১) ✔
  - SMP-2026-00001 V2-এর development Completed → Quality · Pending ✔
  - QC: Pass With Comment, Minor, Seam Not OK ("Thread ends at side seam"), Measurement/Fabric OK, Print N.A. → QC-2026-00001 ✔
  - Approval: Merchandising Review Approved → APR-2026-00001; Buyer Approval Approved With Comment → APR-2026-00002, ফর্ম লক, স্যাম্পল Handover · Ready ✔
  - Feedback: Correction, Related Approval APR-2026-00002, Action Required → Karim Mia, due 25 Oct → FB-2026-00001, বার্তা গেছে ✔
  - Handover: ১ পিস, LPP Buying House, Couriered DHL 1Z999AA10123456784 → HO-2026-00001, তালিকায় "On the Way 1" ✔
  - চারটা তালিকার pill, ফিল্টার আর কলাম Brickland-এর মতো ✔। সব রেকর্ড dev DB-তে রাখা আছে।

- **ব্রাউজার যাচাই P7** (২০২৬-১০-২৩, owner লগইন):
  - সাইডবারে Sample Material & Production → Sample Bundle Tracking ✔
  - Scan Desk: SBC-2026100001-01 Delv. For Embr-এ; তারপর Delv. For Print-এ স্ক্যান আটকেছে ("…was sent to Delv. For Embr, so it cannot go this way too.") ✔; Recv. From Embellishment; দুই bundle Delv. for Sewing আর Sewing Done; -01 Distribution। প্রতিটা বাটনে আজকের সংখ্যা, "2 Scanned · 5 pcs", Undo ✔
  - Tracking Update: -01-এর Delv. for Sewing-এ ০৮:০০–১৩:০০, Expected ৪, কারণ "Machine down for an hour" → Delay ১ ✔
  - Records: Movements ৭, Delayed ১, Delay Hours ১ ✔। Lookup আর কার্ডের অবস্থা (In Production) ✔। রেকর্ডগুলো dev DB-তে রাখা আছে।
- **ব্রাউজার যাচাই P5** (২০২৬-১০-২৩, owner লগইন):
  - Company Management → Line-এ Add Line দিয়ে C1 (Cutting 1) আর C2 (Cutting 2), ধরন Cutting ✔
  - সাইডবারে Sample Material & Production → Sample Bundle Card Issue ✔
  - নতুন কার্ড: SMP-2026-00001 বাছতেই LPP আর স্টাইল বসেছে; Cutting# 7, টেবিল C1, Single Jersey, Due 26 Oct, অপারেটর Karim Mia, টিম Sample Team A, High; দুটো bundle (Navy M ২/২, Navy L ২/৩) → SBC-2026100001, scan code SBC-2026100001-01 আর -02 ✔
  - Issue (dialog) → finalized আর Issue Date বসেছে; Receive; Start / Resume → In Production; এরপর বাটনে Hold, Return আর Cancel ✔
  - Records-এ On the Floor ১; Cutting List-এ C1 বাছলে SBC-2026100001 · ২ bundle · ৫ · In Production ✔। রেকর্ডগুলো dev DB-তে রাখা আছে।
- **ব্রাউজার যাচাই P4** (২০২৬-১০-২২, owner লগইন):
  - সাইডবারে Sample Material & Production → Prototype Material Requisition ✔
  - নতুন রিকুইজিশন: SMP-2026-00001, Design & Product Development, Rahim Uddin, Fin Fab Req by Sample Section, Fabric store থেকে Main Store, প্রোগ্রাম SMP-202600001; Single Jersey 160 GSM (Navy, M, ১২ KG) আর Pique 220 GSM (২ KG) → MR-2026-00001 ✔
  - Submit (dialog), Dept. Approve, Available ৮ আর ৫ ও Warehouse দিয়ে সেভ, Store Verify (dialog), Approve (dialog) → Approved; অনুমোদনকারীদের নাম আর তারিখ বসেছে, Short Qty ৪, লাইন লক ✔
  - Material Issue: MR-2026-00001-এর জার্সি লাইন থেকে ৫ KG, Received By Sample Room → MI-2026-00001; রিকুইজিশন Partially Issued ✔
  - Records-এ To Issue ১; Sample Programs-এ SMP-202600001 বাছলে MR-2026-00001 · Partially Issued ✔। রেকর্ডগুলো dev DB-তে রাখা আছে।
- **ব্রাউজার যাচাই P6** (২০২৬-১০-২২, owner লগইন):
  - সাইডবারে Sample Material & Production → Sample Room Daily Target ✔
  - Target Setting: ২২ Oct 2026, Sample Room 1, Day, Delta Apparels Ltd, Supervisor Karim Mia, Prepared By Rahim Uddin, জনবল ১০/৮, শিফট ৮ ঘণ্টা; LINE01-এ SMP-2026-00001 (৫০ টার্গেট, ৪০ প্রকৃত, ০৮:০০–১৪:০০, High; টাইপ নিজে বসেছে PP Sample), LINE02-এ ৪০ টার্গেট → সেভ, নিচে মোট ৯০ / ৮.০০ / ৮ / ৫৪.০০ / ৪০ / ৬৪.০০ ✔
  - Weekly plan TP-2026-00001 (১৯–২৫ Oct, Sample Room 1, ৩০০ টার্গেট) → Actual ৪০, Achievement ১৩.৩%, Day Sheets in Period-এ ২২ Oct ✔
  - Records: Day Sheets ১, Avg. Achievement ৪৪.৪% ✔। রেকর্ডগুলো dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **P1–P3 ✅** (২০২৬-১০-২১)। লাইভ প্রোব: `tools/erp_crawler/plans/proto1.json` থেকে `proto3.json`।
  - Brickland-এ SR-2026-00001-কে Merchandising Manager আর Sample Manager হিসেবে অনুমোদন দিয়ে SMP-2026-00001 তৈরি করা হয়েছে (ব্যবহারকারীর লেখার অনুমতি অনুযায়ী), যাতে Development ফর্মে Sample দেখা যায়।
- **P8–P11 ✅** (২০২৬-১০-২১)। লাইভ প্রোব: `tools/erp_crawler/plans/proto7.json`, `proto8.json` (Handover ফর্ম, চার তালিকার ফিল্টার/কলাম), `proto9.json` (pill)।
  - Handover ফর্মে Brickland-এর Handover From আর Department ঘর ছিল; পরের migration-এ যোগ হয়েছে।
  - Sample-এর state কলাম ১৬ থেকে ২৪ অক্ষর করা হয়েছে (REVISION_REQUIRED-এর জন্য)।
  - একই সেকেন্ডে দুটো চেক হলে "সর্বশেষ" ঠিক করতে qc_no দিয়েও সাজানো হয়।
- **P7 ✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `proto19.json` (সব ট্যাবের লেখা), `proto20.json` (প্রতিটা process-এর hint, Lookup আর Sample Card)। Brickland-এ কোনো bundle card নেই, তাই আসল স্ক্যান লাইভে দেখা যায়নি; নিয়ম এসেছে hint আর নোট থেকে।
  - "Sample Card"-এর Brickland বর্ণনা: অর্ডারের কার্ডে কী চাওয়া হয়েছিল, কতটা সেলাই, কতটা বেরিয়েছে, Delivered আর Rejected ঘরেই বদলানো, পুরো লাইন হস্তান্তর। সেই মতো কার্ডে delivered/rejected রাখা হয়েছে।
  - UI যাচাইয়ে ধরা পড়েছে: Records-এর Delay Hours pill সার্ভার থেকে function পাঠাচ্ছিল (পাতা ভেঙে যাচ্ছিল), এখন `sum`; Tracking Update-এর সময় UTC ধরে নেওয়া হচ্ছিল (৬ ঘণ্টা সরে যাচ্ছিল), এখন offset-সহ; ডান পাশের প্যানেল পাতার বাইরে যাচ্ছিল, এখন কার্ডের ভেতরে স্ক্রল হয়।
- **P5 ✅** (২০২৬-১০-২৩)। লাইভ প্রোব: `proto5.json`, `proto11.json`, `proto18.json` (তালিকার pill, Style, Order আর Operator-এর অপশন, Cutting List)।
  - Brickland-এ Table-এর তালিকা ফাঁকা ("No records to choose from yet"), তাই সেখানে কার্ড সেভ করে ধাপগুলো লাইভে দেখা যায়নি। নিয়মগুলো এসেছে ফর্মের hint আর নোট থেকে। টেবিল হিসেবে নেওয়া হয়েছে Company Management-এর Cutting ধরনের line, কারণ Brickland-এর line master-এ C1–C4 "Cutting" ধরনের line আছে।
  - Assigned Operator Brickland-এ কর্মীর তালিকা (নাম ও কর্মী নং); HR-এর কর্মী master এখনো নেই, তাই আপাতত কোম্পানির ব্যবহারকারী। HR বানানো হলে এটা কর্মীতে বদলাবে।
  - Brickland-এর Buyer A/C = buyer-এর সাথে যুক্ত buying house (Sample Requisition-এর মতো)।
  - dev DB-র পুরনো "Cutting Table1–4" line-গুলোর code-এ ছোট হাতের অক্ষর আর space আছে, যা code-এর নিয়মে চলে না। তাই Line স্ক্রিনে সেগুলোর ধরন বদলে সেভ করা যায় না (আগের import-এর ডাটা)। UI যাচাইয়ে তাই নতুন C1/C2 বানানো হয়েছে।
- **P4 ✅** (২০২৬-১০-২২)। লাইভ প্রোব: `proto4.json`, `proto6.json`, `proto11.json` (লাইনের অপশন), আর `proto12.json` থেকে `proto17.json`। শেষেরগুলোতে Brickland-এ MR-2026-00001 লিখে Submit, Dept. Approve, Store Verify আর Approve করা হয়েছে, তারপর Material Issue-তে ৫ ইস্যু করে Partially Issued, ৪১.৭% দেখা গেছে।
  - Store Material বা Cutting Floor-এ আলাদা master লাগেনি। Store আছে Inventory-তে, Requisition Type আছে Merchandise-এ, Item আছে Material Procurement-এ, Fabric Type আর Sample Program আছে Merchandise-এ।
  - Brickland-এর "Short Lines" issue শুরু হওয়ার পর ০ দেখায়, তাই ঘাটতির লাইন গোনা হয় যতক্ষণ কিছু ইস্যু হয়নি।
- **P6 ✅** (২০২৬-১০-২২)। লাইভ প্রোব: `tools/erp_crawler/plans/proto5.json` (ফর্ম আর গ্রিড) আর `proto10.json` (Brickland-এ ২২ Oct-এর শিট আর একটা weekly plan লিখে হিসাব আর তালিকা দেখা)।
  - Brickland-এর Records-এ শুধু day sheet দেখায়, plan নয়। Plan খোলা যায় Weekly / Monthly Plan ট্যাবের Existing target plan থেকে।
  - Efficiency-র সূত্র Brickland থেকে সরাসরি দেখা যায়নি (প্রকৃত ঘণ্টা ছাড়া "—" দেখায়), তাই অর্জিত ঘণ্টা ÷ প্রকৃত ঘণ্টা ধরা হয়েছে।
  - Brickland-এর "Company" = লগইন করা কোম্পানি, `unit_company_id` (আগের স্ক্রিনগুলোর মতো)।
  - Line grid-এর ড্রপডাউনে লম্বা লেবেল ভেঙে যাচ্ছিল, তাই Select-এর লেবেল এখন সব জায়গায় এক লাইনে থাকে, শেষে "…"।
- **Attachment:** এখন পাথ বা লিংক লেখা যায়। ফাইল আপলোড (Attach) পরের ধাপে, বাকি স্ক্রিনগুলোর সাথে একসাথে।
