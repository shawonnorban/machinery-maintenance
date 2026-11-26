# Requisition

> **স্ট্যাটাস:** ✅ সম্পন্ন (৮টার ৮টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/05`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Batch Dye Chemical Indent | `/m/dyeing/requisition-for-dc-for-batch` | 2 | 20 | 2 |
| 2 | Machine Wash Chemical Indent | `/m/dyeing/requisition-for-dc-machine-wash` | 2 | 21 | 2 |
| 3 | Finishing Process Chemical Indent | `/m/dyeing/requisition-for-dc-finishing-process` | 2 | 22 | 2 |
| 4 | Re-Process Approval Desk | `/m/dyeing/reprocess-approval` | 1 | 8 | 0 |
| 5 | Re-Process Update Entry | `/m/dyeing/update-reprocess` | 2 | 23 | 2 |
| 6 | Dye Batch Re-Process | `/m/dyeing/dyeing-reprocess` | 2 | 22 | 1 |
| 7 | Dye Machine Maintenance | `/m/dyeing/dyeing-mc-maintenance` | 2 | 15 | 1 |
| 8 | Print Chemical Indent | `/m/dyeing/print-chemical-requisition` | 2 | 25 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Batch Dye Chemical Indent

`/m/dyeing/requisition-for-dc-for-batch`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IR-DC-2026064108 |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Batch No | search |  | B-26080001 |
| M/C No | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Dyes and chemicals requisitioned against a planned batch, with what the s | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Color, Process Type, Batch No, M/C No, Required Qty, Issued Qty, Items, ACTION
- বাটন/অ্যাকশন: New Batch Program Requisition, Clear, Add New, Export, REQ No, Req Date, Color, Process Type, Batch No, M/C No, Required Qty, Issued Qty, Items

**ট্যাব: New Batch Program Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing Batch Program requisition | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ | Store Requisition for Batch Program |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Batch Program Requisition, Requisition, What it is for, Dyes & Chemicals
- বাটন/অ্যাকশন: New Batch Program Requisition, -- Color --, -- Select Style --, Add Item, Cancel, -- Select Item --, -- Unit --, Back To List, Clear, Save Batch Program requisition

**ডায়ালগ: New Batch Program Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Batch Program requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Batch Program requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

#### Machine Wash Chemical Indent

`/m/dyeing/requisition-for-dc-machine-wash`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IR-DC-2026064094 |
| Date from | date |  |  |
| Date to | date |  |  |
| Company | select |  | All |
| Requisition Type | select |  | All |
| Machine No | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals drawn to wash a machine down between two shades, and the balanc | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Req. Type, From Store, M/C No, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: New M/C Wash Requisition, Clear, Add New, Export, REQ No, Req Date, Company, Req. Type, From Store, M/C No, Required Qty, Issued Qty, Balance Qty, Remarks

**ট্যাব: New M/C Wash Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing M/C Wash requisition | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ | Store Requisition for M/C Wash |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New M/C Wash Requisition, Requisition, What it is for, Dyes & Chemicals
- বাটন/অ্যাকশন: New M/C Wash Requisition, -- Color --, -- Select Style --, Add Item, Cancel, -- Select Item --, -- Unit --, Back To List, Clear, Save M/C Wash requisition

**ডায়ালগ: New M/C Wash Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing M/C Wash requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing M/C Wash requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

#### Finishing Process Chemical Indent

`/m/dyeing/requisition-for-dc-finishing-process`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IR-DC-2026064035 |
| Date from | date |  |  |
| Date to | date |  |  |
| Buyer | select |  | All |
| Style/Order # | select |  | All |
| Color | select |  | All |
| Batch | search |  | B-26080001 |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals drawn for softening, stentering and other finishing, against th | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Unit, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Order Info, Batch Info, ACTION
- বাটন/অ্যাকশন: New Finishing Process Requisition, Clear, Add New, Export, REQ No, Req Date, Unit, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Order Info, Batch Info

**ট্যাব: New Finishing Process Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing Finishing Process requisition | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ | Store Requisition for Finishing Process |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Finishing Process Requisition, Requisition, What it is for, Dyes & Chemicals
- বাটন/অ্যাকশন: New Finishing Process Requisition, -- Color --, -- Select Style --, Add Item, Cancel, -- Select Item --, -- Unit --, Back To List, Clear, Save Finishing Process requisition

**ডায়ালগ: New Finishing Process Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Finishing Process requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Finishing Process requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

#### Re-Process Approval Desk

`/m/dyeing/reprocess-approval`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req. No | search |  | IR-DC-2026063369 |
| Company | select |  | All |
| RP Type | select |  | All, Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Buyer | select |  | All |
| Batch | search |  | B-26060327 |
| Status | select |  | All, Not Required, Pending, Approved, Rejected |
| Search Re-processes waiting on sign-off, with what each would cost the floor in  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Company, Process Type, Order Info, Batch No, Color, Required Qty, M/C No, ACTION
- বাটন/অ্যাকশন: Clear, Export, REQ No, Company, Process Type, Order Info, Batch No, Color, Required Qty, M/C No

#### Re-Process Update Entry

`/m/dyeing/update-reprocess`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IR-DC-2026063369 |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Color Name | select |  | All |
| Process Type | select |  | All, Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Req. Store | select |  | All |
| Batch No | search |  | B-26060327 |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Re-process requisitions the floor is running, with the shade problem each | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Color, Process Type, From Store, Batch No, Remarks, ACTION
- বাটন/অ্যাকশন: New Re-Process Requisition, Clear, Add New, Export, REQ No, Req Date, Color, Process Type, From Store, Batch No, Remarks

**ট্যাব: New Re-Process Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing Re-Process requisition | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ | Store Requisition for Re-Process |
| Process Type* | select | ✔ | Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Re-Process Requisition, Requisition, What it is for, Dyes & Chemicals
- বাটন/অ্যাকশন: New Re-Process Requisition, -- Color --, -- Select Style --, Add Item, Cancel, -- Select Item --, -- Unit --, Back To List, Clear, Save Re-Process requisition

**ডায়ালগ: New Re-Process Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Re-Process requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Process Type* | select | ✔ | Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing Re-Process requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Process Type* | select | ✔ | Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.

#### Dye Batch Re-Process

`/m/dyeing/dyeing-reprocess`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | CRR-2026080001 |
| Req. Date from | date |  |  |
| Req. Date to | date |  |  |
| Not Ok Date from | date |  |  |
| Not Ok Date to | date |  |  |
| Color | select |  | All |
| Process Type | select |  | All, Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Batch No | search |  | B-26060327 |
| M/C No | select |  | All |
| Search Check rolls QC has failed and returned for re-processing, with the defect | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Not Ok Date, Company, Re-process Type, Req. Store, Batch No, Color, Defect, Qty(Kg), Waited, Remarks, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, REQ No, Req Date, Not Ok Date, Company, Re-process Type, Req. Store, Batch No, Color, Defect, Qty(Kg), Waited, Remarks

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Return No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Not Ok Date | date |  |  |
| Re-process Type* | select | ✔ | Select…, Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| Req. Store | select |  | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Qty* | number | ✔ |  |
| Defect | text |  | Rib shade not OK against body |
| Remarks | textarea |  |  |
- সেকশন: New check-roll return
- বাটন/অ্যাকশন: Add New, -- Color --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Return No | text |  |  |
| Req Date* | date | ✔ |  |
| Not Ok Date | date |  |  |
| Re-process Type* | select | ✔ | Select…, Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| Req. Store | select |  | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Qty* | number | ✔ |  |
| Defect | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Qty must be more than nothing.

#### Dye Machine Maintenance

`/m/dyeing/dyeing-mc-maintenance`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dyeing M/C | select |  | All |
| Date from | date |  |  |
| Date to | date |  |  |
| Defect Type | select |  | All |
| Status | select |  | All, Reported, In Progress, Resolved, Cancelled |
| Search Dyeing machine downtime — what broke, when it was called in, when it stop | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Dyeing M/C, Date, Report Time, Problem Time, Resolved Time, Downtime, Defect Type, Report By, Service By, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Dyeing M/C, Date, Report Time, Problem Time, Resolved Time, Downtime, Defect Type, Report By, Service By

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dyeing M/C* | select | ✔ |  |
| Date* | date | ✔ |  |
| Report Time* | datetime-local | ✔ |  |
| Problem Time | datetime-local |  |  |
| Resolved Time | datetime-local |  |  |
| Defect Type* | text | ✔ | Pump seal |
| Status* | select | ✔ | Select…, Reported, In Progress, Resolved, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New maintenance log
- বাটন/অ্যাকশন: Add New, -- Select Employee --, -- Select Engineer --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Dyeing M/C* | select | ✔ |  |
| Date* | date | ✔ |  |
| Report Time* | datetime-local | ✔ |  |
| Problem Time | datetime-local |  |  |
| Resolved Time | datetime-local |  |  |
| Defect Type* | text | ✔ |  |
| Status* | select | ✔ | Select…, Reported, In Progress, Resolved, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Report Time is required.

#### Print Chemical Indent

`/m/dyeing/print-chemical-requisition`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IR-DC-2026064108 |
| Date from | date |  |  |
| Date to | date |  |  |
| Req Type | select |  | All |
| Buyer | select |  | All |
| Style/Order # | select |  | All |
| Color | select |  | All |
| Batch | search |  | B-26080001 |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Every chemical requisition the store has to act on, whatever it was raise | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Req. Type, From Store, M/C No, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: Raise a Requisition, Clear, Add New, Export, REQ No, Req Date, Company, Req. Type, From Store, M/C No, Required Qty, Issued Qty, Balance Qty, Remarks

**ট্যাব: Raise a Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Raised For* | select | ✔ | Select…, Batch Program, M/C Wash, Finishing Process, Re-Process |
| Req. Type* | text | ✔ | Store Requisition for M/C Wash |
| Process Type | select |  | Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Raise a Requisition, Requisition, What it is for, Dyes & Chemicals
- বাটন/অ্যাকশন: Raise a Requisition, -- Color --, -- Select Style --, Add Item, Cancel, -- Select Item --, -- Unit --, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Raised For* | select | ✔ | Select…, Batch Program, M/C Wash, Finishing Process, Re-Process |
| Req. Type* | text | ✔ |  |
| Process Type | select |  | Re-Matching, Re-Wash, Re-Dyeing, Stripping, Softener |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE) |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Buyer | select |  | LPP (LPP) |
| Remarks | textarea |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A requisition with no items asks the store for nothing. Add at least one line.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/03-এর batch card:** requisition আর check-roll return-এর "What it is for"। batch বাছলে মেশিন, কালার, বায়ার আর অর্ডার নিজে বসে (options-এর batches-এ এখন এই id-গুলো থাকে)।
- **ডাই আর কেমিক্যাল:** Dyes Chemical Store বিভাগের আইটেম। লজিকটা এখন `DyeBatches::chemicals()`-এ, Lab-Dip-এর সাথে ভাগ করা।
- **ইউনিট:** মাস্টার থেকে (`UomSelect`, `UomCodes`)।
- **HR:** `hr_employees` থেকে maintenance-এর Report By আর Service By।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_28_000100_create_dye_requisitions.php`:
- **`dye_chem_requisitions`:**
  - req_no (IR-DC-YYYYMM####), req_date।
  - req_for: BATCH_PROGRAM / MC_WASH / FINISHING / REPROCESS।
  - req_type (স্টোরের নিজের ভাষা), process_type (শুধু রি-প্রসেসে: RE_MATCHING / RE_WASH / RE_DYEING / STRIPPING / SOFTENER)।
  - company, store, batch, machine, colour, buyer, order, remarks।
  - approval: NOT_REQUIRED / PENDING / APPROVED / REJECTED, সঙ্গে decided_by আর decided_at।
  - status: WAITING / PARTIAL / ISSUED / CANCELLED, সঙ্গে issued_by আর issued_at।
- **`dye_chem_requisition_lines`:** item, uom, required_qty, issued_qty, remarks।
- **`dye_check_roll_returns`:** CRR-YYYYMM####, req_date, not_ok_date, reprocess_type, company, store, batch, colour, machine, qty_kg, defect, remarks, sent_back_at।
- **`dye_machine_maintenance`:** machine, log_date, report_time, problem_time, resolved_time, defect_type, report_by আর service_by (hr_employees), status (REPORTED / IN_PROGRESS / RESOLVED / CANCELLED), remarks।

## ৫. Workflow / Status ও Business Rules

- **একটাই কাগজ, পাঁচ জায়গা থেকে:** Batch Dye Chemical, Machine Wash আর Finishing Process Chemical Indent, আর Re-Process Update Entry প্রত্যেকে নিজের ধরনটা তোলে। Print Chemical Indent ডেস্ক "Raised For" দিয়ে যেকোনো ধরন তোলে আর সব ধরন দেখায়।
- **নম্বর আর লাইন:**
  - নম্বর সার্ভার দেয় (IR-DC-YYYYMM####)।
  - অন্তত একটা লাইন লাগবে, প্রতিটা লাইনে আইটেম আর শূন্যের বেশি পরিমাণ।
  - মোট পরিমাণ লাইনের যোগফল।
  - "Existing … requisition" থেকে আগেরটা খুলে বদলানো যায়, যতক্ষণ ইস্যু হয়নি আর রি-প্রসেস বাতিল হয়নি।
  - ধরন (Raised For) পরে বদলানো যায় না।
- **রি-প্রসেস:**
  - Process Type বাধ্যতামূলক। তোলার সময় অনুমোদন থাকে Pending।
  - Re-Process Approval Desk-এ Approve করলে স্টোর ইস্যু করতে পারে। Reject করলে রিকুইজিশন বাতিল (CANCELLED)।
  - দুটো সিদ্ধান্তই নিশ্চিত করতে হয়, Brickland-এর বার্তা দিয়ে।
- **10-Issue (Print desk):**
  - স্টোর প্রতিটা লাইনে পুরো চাওয়া পরিমাণ ছেড়েছে বলে লেখা হয়, আর রিকুইজিশন ISSUED হয়।
  - একবারই হয়। রি-প্রসেস হলে শুধু অনুমোদনের পরে।
  - অপেক্ষায় থাকা আর কিছু ইস্যু না হওয়া রিকুইজিশন মোছা যায়।
- **Dye Batch Re-Process (check-roll return):**
  - নম্বর CRR-YYYYMM####।
  - Not Ok Date রিকুইজিশনের তারিখের পরে হতে পারে না। Waited = দুই তারিখের মাঝের দিন।
  - রেজিস্টারের Back রোলকে ফেরত পাঠায় (নিশ্চিত করতে হয়)। এরপর আর বদলানো বা মোছা যায় না।
- **Dye Machine Maintenance:**
  - Downtime হিসাব হয় Problem আর Resolved Time থেকে।
  - Resolved Time Problem Time-এর আগে হতে পারে না।
  - Resolved অবস্থায় Resolved Time লাগবে।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.requisition.view`: FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER।
  - `dyehouse.requisition.manage`: FACTORY_ADMIN আর FACTORY_MANAGER।
  - `dyehouse.requisition.approve`: FACTORY_ADMIN আর FACTORY_MANAGER।
  - `dyehouse.requisition.issue` (10-Issue): FACTORY_ADMIN আর STORE_MANAGER।
- **API:**
  - `GET /dyehouse/chem-requisitions?for=…`, `POST`।
  - `GET|PUT|DELETE /dyehouse/chem-requisitions/{no}`।
  - `POST …/{no}/issue`, `POST …/{no}/approve|reject`।
  - `POST /dyehouse/check-roll-returns/{no}/back`।
- **Screen-kit resource:** `dye-check-roll-returns`, `dye-machine-maintenance`।
- **options:** `/dyehouse/batch/options` এখন requisition-এর পারমিশন দিয়েও পড়া যায়, তাতে `chemicals` যোগ হয়েছে।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Dye House → REQUISITION, ৮টা লিফ।
- **রিকুইজিশনের চার স্ক্রিন আর Print desk:**
  - Records: Brickland-এর ফিল্টার আর কলাম, অবস্থার চিপসহ।
  - "New … Requisition" বা "Raise a Requisition" ট্যাবে editor: Existing select, Requisition, What it is for, Dyes & Chemicals (Add Item, Cancel, প্রতিটা লাইন এডিট করা যায়, নিচে Required-এর যোগফল)।
  - নিচে Back To List, Clear আর Save।
  - URL-এ `?req=IR-DC-…` দিলে সেই রিকুইজিশনটা খোলে।
- **Print desk:** প্রতিটা সারিতে 10-Issue আর Delete, নিশ্চিতকরণসহ।
- **Re-Process Approval Desk:** ফিল্টার আর গ্রিড, প্রতিটা সারিতে Approve আর Reject (শুধু Pending হলে চালু)।
- **Dye Batch Re-Process আর Dye Machine Maintenance:** Records আর Add New, Edit একই পাতায়। Re-Process-এ প্রতিটা সারিতে Back।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৮টার ৮টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_28_000100_create_dye_requisitions.php`
- `app/Modules/DyeHouse/Models/` — `DyeChemRequisition`, `DyeChemRequisitionLine`, `DyeCheckRollReturn`, `DyeMachineMaintenance`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeRequisitionApiController.php` (নতুন); `DyeBatchApiController.php`, `DyeLabDipApiController.php` (বদলানো: options, কেমিক্যাল)
- `app/Modules/DyeHouse/Resources/DyeCheckRollReturnResource.php`, `DyeMachineMaintenanceResource.php`
- `app/Modules/DyeHouse/Support/DyeBatches.php` (বদলানো: `chemicals()`)
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `req` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/req-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/chem-requisition.jsx` (`ChemRequisitionEditor`, `ActionRegister`)

**Test**
- `tests/Feature/DyeHouse/DyeHouseRequisitionTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseRequisitionTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ৯৮টা assertion।
- `test_batch_programme_requisition_and_its_issue`:
  - তিনটা বার্তা: লাইন নেই, স্টোর নেই, পরিমাণ শূন্য।
  - নম্বর IR-DC-YYYYMM0001। batch থেকে কালার, বায়ার আর অর্ডার আসে। যোগফল ১৬.৫। ইউনিট kg থেকে KG হয়।
  - ধরন বদলানো আটকায়।
  - 10-Issue একবারই হয়। ইস্যুর পরে বদলানো বা মোছা আটকায়।
  - Print desk-এর M/C Wash তোলা আর মোছা যায়।
- `test_re_process_waits_on_the_approval_desk`:
  - Process Type না দিলে আটকায়। তোলার পরে Pending।
  - অনুমোদনের আগে ইস্যু আটকায়।
  - Approve হলে ইস্যু চলে। Reject হলে CANCELLED, এরপর ইস্যু বা বদলানো আটকায়।
- `test_check_roll_returns_and_machine_downtime`:
  - Not Ok Date আর Qty-এর বার্তা। Waited ৩ দিন।
  - Back-এর পরে বদলানো বা মোছা আটকায়।
  - Downtime ৩ঘ ৩০মি। Resolved-এর দুই নিয়ম কাজ করে।
- `test_who_may_raise_approve_and_issue`: STORE_MANAGER দেখতে আর ইস্যু করতে পারে (তোলা বা অনুমোদন নয়)। FACTORY_MANAGER অনুমোদন দিতে পারে। TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseLabDipTest` (৩টা টেস্ট, ৯৩টা assertion), `DyeHouseBatchTest` (৫টা, ১৭৩টা), `DyeHouseSampleTest` (৩টা, ৯৯টা) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/dr01`), dev অ্যাপে:**
- **Batch Program:** IR-DC-2026110001, B-26110001-এ, DM-01, দুটো লাইন (মোট ২০.৫)।
- **Re-Process:** IR-DC-2026110002, Re-Dyeing, B-26110001-এর কালার Black নিজে বসেছে। Approval Desk-এ নিশ্চিত করে Approve করা হয়েছে।
- **Print desk:** M/C Wash হিসেবে IR-DC-2026110003 তোলা হয়েছে। IR-DC-2026110001-এ 10-Issue নিশ্চিত করার পরে ISSUED।
- **Check-roll return:** CRR-2026110001 (Waited ২ দিন), তারপর Back।
- **Maintenance:** DM-03, Pump seal, ডাউনটাইম ৩ঘ ৩০মি।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।

**প্রিন্ট:** Brickland-এর Print Chemical Indent-এর সারিতে শুধু 10-Issue আর Delete আছে, কোনো Print বাটন নেই। বাকি সাতটা স্ক্রিনেও Print নেই।

## ১০. খোলা প্রশ্ন

১. **10-Issue আর স্টক:** _সমাধান হয়েছে (17/06)।_ 10-Issue এখন কেমিক্যাল স্টোরের লেজার থেকে স্টক কমায়, যেটার মেয়াদ আগে শেষ সেই লট থেকে। স্টোরে কম থাকলে আটকায়। আংশিক ইস্যু হয় Chemical Store-এর issue স্ক্রিন থেকে।

২. **Approve/Reject বাটন:** Pending না হলেও সারিতে বাটন দেখায়, তবে বন্ধ (disabled) অবস্থায়। Brickland-এ সিদ্ধান্তের পরে বাটন থাকে কিনা demo-তে দেখা যায়নি।

৩. **Re-Process Update Entry-র "Amend an approved re-process":** Brickland-এর বর্ণনা বলে অনুমোদিত রি-প্রসেস বদলানো যায়, কিন্তু সেখানকার ফর্ম আসলে নতুন রি-প্রসেস তোলে। আমরা অনুমোদনের পরেও, ইস্যু না হওয়া পর্যন্ত, বদলানোর সুযোগ রেখেছি। বদলালে আবার অনুমোদন লাগবে কিনা জানাবেন।

৪. **Dye Batch Re-Process-এর "extra dye and chemical it cost":** check-roll return-এ কোনো কেমিক্যালের লাইন নেই (Brickland-এর ফর্মেও নেই)। খরচ আসে রি-প্রসেস রিকুইজিশন থেকে।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/dr01`–`dr03`):**
  - আটটা স্ক্রিনের ফর্ম, হিন্ট আর বার্তা দেখা হয়েছে।
  - Brickland demo-তে একটা রি-প্রসেস রিকুইজিশন তৈরি করা হয়েছে (IR-DC-2026110001)। তা থেকে Approval Desk-এর Approve/Reject, Print desk-এর "10-Issue" আর দুটো নিশ্চিতকরণের লেখা ধরা হয়েছে।
- **নম্বরের ফরম্যাট:** Brickland-এর মতোই, IR-DC-YYYYMM#### আর CRR-YYYYMM####।
- **পাঁচটা স্ক্রিন, একটা editor:** কোনো স্ক্রিনই আলাদা কোড রাখে না। শুধু ধরন, শিরোনাম আর Records-এর কলাম বদলায়।
