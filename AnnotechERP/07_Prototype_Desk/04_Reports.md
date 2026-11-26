# Reports

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Prototype Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `07/04`

**⬅ ফিরে যান:** [Prototype Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sample Status Report | `/m/sample/sample-status-report` | 1 | 11 | 0 |
| 2 | Sample Pending Report | `/m/sample/sample-pending-report` | 1 | 8 | 0 |
| 3 | Sample Approval Report | `/m/sample/sample-approval-report` | 1 | 8 | 0 |
| 4 | Sample Revision Report | `/m/sample/sample-revision-report` | 1 | 8 | 0 |
| 5 | Sample Material Report | `/m/sample/sample-material-report` | 1 | 9 | 0 |
| 6 | Daily Target vs Achievement | `/m/sample/target-vs-achievement-report` | 1 | 7 | 0 |
| 7 | Sample Performance Report | `/m/sample/sample-performance-report` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sample Status Report

`/m/sample/sample-status-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Request Date from | date |  |  |
| Request Date to | date |  |  |
| Required Date from | date |  |  |
| Required Date to | date |  |  |
| Buyer | select |  | All |
| Sample Type | select |  | All |
| Sample Stage | select |  | All, Pre-Order, Order / PO, Pre-Production, Bulk Production, Post-Production |
| Current Stage | select |  | All, Requested, Development, Material, Production, Quality, Approval, Revision, Handover, Delivery, Completed |
| Status | select |  | All, Draft, Pending, Approved, In Progress, On Hold, Completed, Rejected, Cancelled, Delayed |
| Search Every sample with its buyer, style, order, type, dates, current stage, st | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Sample No, Buyer, Style, Order No, Sample Type, Sample Stage, Request Date, Required Date, Current Stage, Responsible Person
- বাটন/অ্যাকশন: Clear, Export, Sample No, Buyer, Style, Order No, Sample Type, Sample Stage, Request Date, Required Date, Current Stage, Responsible Person

#### Sample Pending Report

`/m/sample/sample-pending-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Required Date from | date |  |  |
| Required Date to | date |  |  |
| Buyer | select |  | All, LPP |
| Sample Type | select |  | All, PP Sample |
| Waiting On | select |  | All, Samples, Requisition approval |
| Stage | select |  | All, Requested, Development, Material, Production, Quality, Approval, Revision, Handover, Delivery, Completed |
| Search Samples not yet completed and requisitions awaiting approval, with the st | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Sample No, Style, Buyer, Pending Stage, Pending Days, Required Date, Responsible Person, Status
- বাটন/অ্যাকশন: Clear, Export, Sample No, Style, Buyer, Pending Stage, Pending Days, Required Date, Responsible Person, Status

#### Sample Approval Report

`/m/sample/sample-approval-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Submitted Date from | date |  |  |
| Submitted Date to | date |  |  |
| Buyer | select |  | All |
| Sample Type | select |  | All |
| Step | select |  | All, Merchandising Review, Buyer Approval |
| Approval Status | select |  | All, Pending, Approved, Approved With Comment, Rejected, Revision Required |
| Search Merchandising and buyer approvals with their version, dates, decision, ap | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Sample No, Buyer, Style, Sample Type, Step, Version, Submitted Date, Approval Date, Approved By, Comments
- বাটন/অ্যাকশন: Clear, Export, Sample No, Buyer, Style, Sample Type, Step, Version, Submitted Date, Approval Date, Approved By, Comments

#### Sample Revision Report

`/m/sample/sample-revision-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Revision Date from | date |  |  |
| Revision Date to | date |  |  |
| Buyer | select |  | All |
| Sample Type | select |  | All |
| Revision Status | select |  | All, Open, In Progress, Completed, Cancelled |
| Sample Stage | select |  | All, Requested, Development, Material, Production, Quality, Approval, Revision, Handover, Delivery, Completed |
| Search Sample revisions with their version, date, reason, buyer comment, count p | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Sample No, Revision No, Version, Revision Date, Reason, Buyer Comment, Revision Count, Current Status
- বাটন/অ্যাকশন: Clear, Export, Sample No, Revision No, Version, Revision Date, Reason, Buyer Comment, Revision Count, Current Status

#### Sample Material Report

`/m/sample/sample-material-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition Date from | date |  |  |
| Requisition Date to | date |  |  |
| Buyer | select |  | All |
| Sample Type | select |  | All |
| Material Group | select |  | All, Fabric, Thread, Button, Zipper, Label, Hangtag, Elastic, Interlining, Lining, Poly, Carton, Packaging, Other Access |
| State | select |  | All, Draft, Submitted, Approved, Fully Issued, Rejected, Dept. Approved, Store Verified, Partially Issued, Cancelled |
| Shortage | select |  | All, Short, Covered |
| Search Material requisition lines raised for samples with required, issued, bala | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Sample No, Style, Requisition No, Material, Required Qty, Issued Qty, Balance Qty, Shortage Qty, Unit, Issue Date
- বাটন/অ্যাকশন: Clear, Export, Sample No, Style, Requisition No, Material, Required Qty, Issued Qty, Balance Qty, Shortage Qty, Unit, Issue Date

#### Daily Target vs Achievement

`/m/sample/target-vs-achievement-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| View | select |  | All, Date, Week, Month, Style, Line, Plan |
| Date from | date |  |  |
| Date to | date |  |  |
| Style | select |  | All |
| Line | select |  | All |
| Search Sample room targets against achievement by date, week, month, style, line | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: View, Date / Period, Style, Line, Target Qty, Actual Qty, Balance, Achievement %, Manpower, Target Hours, Actual Hours, Efficiency %
- বাটন/অ্যাকশন: Clear, Export, View, Date / Period, Style, Line, Target Qty, Actual Qty, Balance, Achievement %, Manpower, Target Hours, Actual Hours, Efficiency %

#### Sample Performance Report

`/m/sample/sample-performance-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | All, All Buyers |
| Rows | select |  | All, Buyers, All Buyers total |
| Search Sample performance per buyer: volumes, approval and revision rates, avera | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Total Samples, Completed, Approved, Rejected, Revision Samples, Avg Development Time, Avg Approval Time, Avg Delivery Time, On-Time %, Delay %, Approval %, Revision %, Status
- বাটন/অ্যাকশন: Clear, Export, Buyer, Total Samples, Completed, Approved, Rejected, Revision Samples, Avg Development Time, Avg Approval Time, Avg Delivery Time, On-Time %, Delay %, Approval %, Revision %, Status
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
_এখনো কোনো কোড নেই।_
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

_বাস্তবায়নের আগে লেখা হবে।_

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

_বাস্তবায়নের পরে লেখা হবে।_
