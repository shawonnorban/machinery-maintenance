# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `24/01`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Employment Type | `/m/hr-payroll/employee-type` | 3 | 10 | 1 |
| 2 | Employee Category | `/m/hr-payroll/designation-group` | 2 | 10 | 1 |
| 3 | Pay Grade | `/m/hr-payroll/grade` | 2 | 6 | 1 |
| 4 | Pay Element | `/m/hr-payroll/pay-element` | 2 | 5 | 1 |
| 5 | Allowance | `/m/hr-payroll/allowance-entry` | 3 | 13 | 0 |
| 6 | Deduction | `/m/hr-payroll/head-wise-deduction` | 3 | 10 | 1 |
| 7 | Shift | `/m/hr-payroll/shift-master` | 2 | 27 | 1 |
| 8 | Leave Type | `/m/hr-payroll/leave-type` | 2 | 25 | 1 |
| 9 | OT Rule | `/m/hr-payroll/ot-rule` | 2 | 13 | 1 |
| 10 | Bonus Rule | `/m/hr-payroll/bonus-rules-setup` | 2 | 12 | 1 |
| 11 | Wage/Gazette Rule | `/m/hr-payroll/salary-policy-and-rules` | 3 | 15 | 1 |
| 12 | Gazette Grade Wage | `/m/hr-payroll/gazette-grade-wage` | 2 | 12 | 1 |
| 13 | Festival Calendar | `/m/hr-payroll/festival-calendar` | 1 | 11 | 1 |
| 14 | Payroll Settings | `/m/hr-payroll/company-pay-period` | 5 | 38 | 2 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Employment Type

`/m/hr-payroll/employee-type`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Employee type tree with parent and level | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type Name [E], Type Name [B], Short Name, Parent Type, Type Level, Leaf?, Status, ACTION
- বাটন/অ্যাকশন: Employee Type Information, New employee type, Export, Type Name [E], Type Name [B], Short Name, Parent Type, Type Level, Leaf?, Status, Edit

**ট্যাব: Employee Type Information**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search types | search |  | Search… |
- সেকশন: Please select a node for operation, Types under this node
- বাটন/অ্যাকশন: Employee Type Information, New employee type, Staff Level 1 · leaf, Worker Level 1 · leaf

**ট্যাব: New employee type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Parent Type | select |  | Select…, Staff, Worker |
| Type Level | text |  |  |
| Type Name [E]* | text | ✔ |  |
| Type Name [B]* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Leaf? | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: New employee type
- বাটন/অ্যাকশন: Employee Type Information, New employee type, Reset, Save employee type

**ডায়ালগ: New employee type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Parent Type | select |  | Select…, Staff, Worker |
| Type Level | text |  |  |
| Type Name [E]* | text | ✔ |  |
| Type Name [B]* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Leaf? | checkbox |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Type Name [E] is required.

#### Employee Category

`/m/hr-payroll/designation-group`

**ট্যাব: Records 45**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Designation group tree with parent, level, display order and manpower cat | search |  | Search 45 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Group Name [E], Short Name, Manpower Category, Parent Group, Group Level, Group Order, Leaf?, Status, ACTION
- বাটন/অ্যাকশন: New designation group, Export, Group Name [E], Short Name, Manpower Category, Parent Group, Group Level, Group Order, Leaf?, Status, Edit

**ট্যাব: New designation group**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Group Level | text |  |  |
| Group Name [E]* | text | ✔ |  |
| Group Name [B]* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Group Order* | number | ✔ |  |
| Manpower Category | select |  | Select…, Operator, Quality, Helper, Supervisor, Staff |
| Leaf? | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: New designation group
- বাটন/অ্যাকশন: New designation group, Select…, Reset, Save designation group

**ডায়ালগ: New designation group**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Group Level | text |  |  |
| Group Name [E]* | text | ✔ |  |
| Group Name [B]* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Group Order* | number | ✔ |  |
| Manpower Category | select |  | Select…, Operator, Quality, Helper, Supervisor, Staff |
| Leaf? | checkbox |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Group Name [E] is required.

#### Pay Grade

`/m/hr-payroll/grade`

**ট্যাব: Records 7**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Pay grades with name, English and Bengali numerals | search |  | Search 7 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Grade No [E], Grade Name, Grade No [B], Designations, Status, ACTION
- বাটন/অ্যাকশন: New grade, Export, Grade No [E], Grade Name, Grade No [B], Designations, Status, Edit

**ট্যাব: New grade**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Grade# [E]* | text | ✔ | 1 |
| Grade# [B]* | text | ✔ | গ্রেড-১ |
| Grade Name | text |  | Grade-01 |
| Active | checkbox |  |  |
- সেকশন: New grade
- বাটন/অ্যাকশন: New grade, Reset, Save grade

**ডায়ালগ: New grade**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Grade# [E]* | text | ✔ |  |
| Grade# [B]* | text | ✔ |  |
| Grade Name | text |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Grade# [E] is required.

#### Pay Element

`/m/hr-payroll/pay-element`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Pay elements — the heads a salary is built from | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Pay Element, In Structures, GL Mapped, Status, ACTION
- বাটন/অ্যাকশন: New pay element, Export, Code, Pay Element, In Structures, GL Mapped, Status, Edit

**ট্যাব: New pay element**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Element Code* | text | ✔ | BASIC |
| Element Name* | text | ✔ | Basic Pay |
| Active | checkbox |  |  |
- সেকশন: New pay element
- বাটন/অ্যাকশন: New pay element, Reset, Save pay element

**ডায়ালগ: New pay element**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Element Code* | text | ✔ |  |
| Element Name* | text | ✔ |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Element Code is required.

#### Allowance

`/m/hr-payroll/allowance-entry`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Excel Upload, Manual Entry

**ট্যাব: Excel Upload**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| CSV (.csv) | radio |  |  |
| Excel (.xlsx, .xls) | radio |  |  |
| JSON (.json) | radio |  |  |
| Pay Element | select |  | Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance (HRENT),  |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Pay Period | select |  | APP-BCLFY2026-27090001 — Brickland Composite Ltd., APP-BCLFY2026-27090002 — Brickland Composite Ltd. |
| Attach File | file |  |  |
- সেকশন: Excel Upload, File Type
- বাটন/অ্যাকশন: Excel Upload, Manual Entry, Show File Data, Template, Upload

**ট্যাব: Manual Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pay Element* | select | ✔ | Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance (HRENT),  |
| Company* | select | ✔ | Brickland Composite Ltd. (BCL) |
| Pay Period* | select | ✔ | APP-BCLFY2026-27090001, APP-BCLFY2026-27090002 |
| Excel Upload | radio |  |  |
| Manual Entry | radio |  |  |
| Amount* | number | ✔ |  |
- সেকশন: New allowance
- বাটন/অ্যাকশন: Excel Upload, Manual Entry, Search..., Reset, Save allowance

#### Deduction

`/m/hr-payroll/head-wise-deduction`

**ট্যাব: Entry 0**
- বাটন/অ্যাকশন: Entry 0, Upload, New Deduction

**ট্যাব: Upload**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| CSV (.csv) | radio |  |  |
| Excel (.xlsx, .xls) | radio |  |  |
| JSON (.json) | radio |  |  |
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Pay Period | select |  | 1 — APP-BCLFY2026-27090001 (Brickland Composite Ltd.), 2 — APP-BCLFY2026-27090002 (Brickland Composite Ltd.) |
| Attach File | file |  |  |
- সেকশন: Upload, File Type
- বাটন/অ্যাকশন: Entry 0, Upload, New Deduction, Show File Data, Template

**ট্যাব: New Deduction**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Salary Pay Period* | select | ✔ | APP-BCLFY2026-27090001 — Brickland Composite Ltd., APP-BCLFY2026-27090002 — Brickland Composite Ltd. |
| Select Company | text |  |  |
| Salary Head* | select | ✔ | Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance (HRENT),  |
| Amount* | number | ✔ |  |
- সেকশন: New deduction
- বাটন/অ্যাকশন: Entry 0, Upload, New Deduction, Search..., Cancel, Save

**ডায়ালগ: New Deduction**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Salary Pay Period* | select | ✔ | APP-BCLFY2026-27090001 — Brickland Composite Ltd., APP-BCLFY2026-27090002 — Brickland Composite Ltd. |
| Select Company | text |  |  |
| Salary Head* | select | ✔ | Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance (HRENT),  |
| Amount* | number | ✔ |  |
- খালি Save-এ মেসেজ: Amount is required.

#### Shift

`/m/hr-payroll/shift-master`

**ট্যাব: Records 4**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shifts with start and end time, break, working hours, grace time, cross-d | search |  | Search 4 records… |
| Filter by Shift Code | search |  |  |
| Filter by Shift Name | search |  |  |
| Filter by Start Time | search |  |  |
| Filter by Regular End Time | search |  |  |
| Filter by Break (min) | search |  |  |
| Filter by Working Hours | search |  |  |
| Filter by Grace (min) | search |  |  |
| Filter by Cross Day | search |  |  |
| Filter by OT Allowed | search |  |  |
| Filter by OT Approval Required | search |  |  |
| Filter by Effective Date | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Shift Code, Shift Name, Start Time, Regular End Time, Break (min), Working Hours, Grace (min), Cross Day, OT Allowed, OT Approval Required, Effective Date, Status, ACTION
- বাটন/অ্যাকশন: New shift, Export, Shift Code, Shift Name, Start Time, Regular End Time, Break (min), Working Hours, Grace (min), Cross Day, OT Allowed, OT Approval Required, Effective Date, Status, Edit

**ট্যাব: New shift**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shift Code* | text | ✔ | ST-GEN |
| Shift Name* | text | ✔ | General |
| Start Time* | text | ✔ | 08:00 |
| Regular End Time* | text | ✔ | 17:00 |
| Break Time | number |  |  |
| Working Hours | number |  |  |
| Grace Time | number |  |  |
| Cross Day Shift Set automatically when the end is before the start (22:00 – 06:0 | checkbox |  |  |
| OT Allowed | checkbox |  |  |
| OT Approval Required | checkbox |  |  |
| Effective Date | date |  |  |
| Active | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New shift
- বাটন/অ্যাকশন: New shift, Reset, Save shift

**ডায়ালগ: New shift**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shift Code* | text | ✔ |  |
| Shift Name* | text | ✔ |  |
| Start Time* | text | ✔ |  |
| Regular End Time* | text | ✔ |  |
| Break Time | number |  |  |
| Working Hours | number |  |  |
| Grace Time | number |  |  |
| Cross Day Shift Set automatically when the end is before the start (22:00 – 06:0 | checkbox |  |  |
| OT Allowed | checkbox |  |  |
| OT Approval Required | checkbox |  |  |
| Effective Date | date |  |  |
| Active | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Shift Code is required.

#### Leave Type

`/m/hr-payroll/leave-type`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Leave types with paid flag, accrual, group, limits and applicability | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Leave Type Name, Paid?, Group, Max Days, Applicable For, Encashable?, Status, ACTION
- বাটন/অ্যাকশন: New leave type, Export, Code, Leave Type Name, Paid?, Group, Max Days, Applicable For, Encashable?, Status, Edit

**ট্যাব: New leave type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Leave Type Code* | text | ✔ | CL |
| Color Code | text |  | #3987e5 |
| Leave Type Name [E]* | text | ✔ |  |
| Leave Type Name[B] | text |  |  |
| Paid Leave? | checkbox |  |  |
| Pay Element | select |  | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Day Forward? | checkbox |  |  |
| No. of Days Forward | number |  |  |
| Is Accrual? | checkbox |  |  |
| Is Accrual Auto ? | checkbox |  |  |
| Is Offday Included ? | checkbox |  |  |
| Is Holiday Included ? | checkbox |  |  |
| Is Balance Check? | checkbox |  |  |
| Leave Group* | select | ✔ | Select…, Leave, Present, Absent |
| Min. Period of Employment | number |  |  |
| Min. Period Unit | select |  | Select…, Day, Month, Year |
| Days to Notify | number |  |  |
| Max. Days Leave | number |  |  |
| Min. Days Work | number |  |  |
| Applicable For* | select | ✔ | Select…, Male, Female, Common |
| Encashable? | checkbox |  |  |
| Active? | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New leave type
- বাটন/অ্যাকশন: New leave type, Reset, Save leave type

**ডায়ালগ: New leave type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Leave Type Code* | text | ✔ |  |
| Color Code | text |  |  |
| Leave Type Name [E]* | text | ✔ |  |
| Leave Type Name[B] | text |  |  |
| Paid Leave? | checkbox |  |  |
| Pay Element | select |  | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Day Forward? | checkbox |  |  |
| No. of Days Forward | number |  |  |
| Is Accrual? | checkbox |  |  |
| Is Accrual Auto ? | checkbox |  |  |
| Is Offday Included ? | checkbox |  |  |
| Is Holiday Included ? | checkbox |  |  |
| Is Balance Check? | checkbox |  |  |
| Leave Group* | select | ✔ | Select…, Leave, Present, Absent |
| Min. Period of Employment | number |  |  |
| Min. Period Unit | select |  | Select…, Day, Month, Year |
| Days to Notify | number |  |  |
| Max. Days Leave | number |  |  |
| Min. Days Work | number |  |  |
| Applicable For* | select | ✔ | Select…, Male, Female, Common |
| Encashable? | checkbox |  |  |
| Active? | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Leave Type Code is required.

#### OT Rule

`/m/hr-payroll/ot-rule`

**ট্যাব: Records 1**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search How an OT hour is priced — by effective date, employee category and grade | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Hourly Rate Formula, Category, Grade, Effective, Legal / Policy Reference, Status, ACTION
- বাটন/অ্যাকশন: New OT rule, Export, Code, Name, Hourly Rate Formula, Category, Grade, Effective, Legal / Policy Reference, Status, Edit

**ট্যাব: New OT rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Rule Code* | text | ✔ | OT-STD |
| Rule Name* | text | ✔ | Standard OT |
| OT Wage Basis* | select | ✔ | Select…, Basic, Gross |
| Hourly Divisor* | number | ✔ |  |
| Multiplier* | number | ✔ |  |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
| Applicable Employee Category | select |  | Select…, Staff, Worker |
| Legal / Policy Reference | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- সেকশন: New OT rule
- বাটন/অ্যাকশন: New OT rule, -- Select --, Reset, Save OT rule

**ডায়ালগ: New OT rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Rule Code* | text | ✔ |  |
| Rule Name* | text | ✔ |  |
| OT Wage Basis* | select | ✔ | Select…, Basic, Gross |
| Hourly Divisor* | number | ✔ |  |
| Multiplier* | number | ✔ |  |
| Effective From* | date | ✔ |  |
| Effective To | date |  |  |
| Applicable Employee Category | select |  | Select…, Staff, Worker |
| Legal / Policy Reference | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Effective From is required.

#### Bonus Rule

`/m/hr-payroll/bonus-rules-setup`

**ট্যাব: Records 6**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Bonus rules by rule set, service length, salary band, basis, percentage,  | search |  | Search 6 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Rule Set, Service (months), Salary Band, Basis, %, Fixed, Employee Type, Scope, Status, ACTION
- বাটন/অ্যাকশন: New bonus rule, Export, Rule Set, Service (months), Salary Band, Basis, %, Fixed, Employee Type, Scope, Status, Edit

**ট্যাব: New bonus rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bonus Rule Set* | select | ✔ | Select…, Bonus Rule |
| Service Length Low* | number | ✔ |  |
| Service Length High* | number | ✔ |  |
| Salary Limit Low* | number | ✔ |  |
| Salary Limit High* | number | ✔ |  |
| Bonus Based On* | select | ✔ | Select…, Basic, Gross |
| Bonus Percentage (%) | number |  |  |
| Fixed Bonus (৳) | number |  |  |
| Shift Team Name | select |  | Select…, Team A, Team B |
| Active | checkbox |  |  |
- সেকশন: New bonus rule
- বাটন/অ্যাকশন: New bonus rule, -- Select --, Reset, Save bonus rule

**ডায়ালগ: New bonus rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Bonus Rule Set* | select | ✔ | Select…, Bonus Rule |
| Service Length Low* | number | ✔ |  |
| Service Length High* | number | ✔ |  |
| Salary Limit Low* | number | ✔ |  |
| Salary Limit High* | number | ✔ |  |
| Bonus Based On* | select | ✔ | Select…, Basic, Gross |
| Bonus Percentage (%) | number |  |  |
| Fixed Bonus (৳) | number |  |  |
| Shift Team Name | select |  | Select…, Team A, Team B |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Service Length Low is required.

#### Wage/Gazette Rule

`/m/hr-payroll/salary-policy-and-rules`

**ট্যাব: Records 15**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Salary structure by gazette, category and pay element, with what each amo | search |  | Search 15 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Gazette, Number / Year, Category, Pay Element, Amount, Means, Effective Date, Status, ACTION
- বাটন/অ্যাকশন: Wage Gazette, New salary structure line, Export, Gazette, Number / Year, Category, Pay Element, Amount, Means, Effective Date, Status, Edit

**ট্যাব: Wage Gazette**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing gazette | select |  | RMG Minimum Wage 2023 (Non-EPZ) — Effective 2023-12-01 · 5 structure line(s) · 1 policy(s), Gejet for 2023 — Effective 2 |
| Gazette Name* | text | ✔ | RMG Minimum Wage 2023 |
| Gazette Number | text |  | S.R.O. No. 389-Law/2023 |
| Year | number |  | 2023 |
| Industry | text |  | Readymade Garments |
| Effective Date* | date | ✔ |  |
| Active? Status. A superseded gazette stays active for the employees still on it; | checkbox |  |  |
| Reference Document | text |  | https://… link to the published gazette, or a file path |
- সেকশন: Wage Gazette, Gazette, Validity, Policy Under Gazette, Salary Structure
- বাটন/অ্যাকশন: Wage Gazette, New salary structure line, Add policy, Clear, Save gazette

**ট্যাব: New salary structure line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Gazette* | select | ✔ | Select…, Gejet for 2023, RMG Minimum Wage 2023 (Non-EPZ), Gejet for 2018, Gejet for 2013 |
| Employee Category* | select | ✔ | Select…, Staff, Worker |
| Pay Element* | select | ✔ | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Amount* | number | ✔ |  |
| Active | checkbox |  |  |
- সেকশন: New salary structure line
- বাটন/অ্যাকশন: Wage Gazette, New salary structure line, Reset, Save salary structure line

**ডায়ালগ: New salary structure line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Gazette* | select | ✔ | Select…, Gejet for 2023, RMG Minimum Wage 2023 (Non-EPZ), Gejet for 2018, Gejet for 2013 |
| Employee Category* | select | ✔ | Select…, Staff, Worker |
| Pay Element* | select | ✔ | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Amount* | number | ✔ |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Amount is required.

#### Gazette Grade Wage

`/m/hr-payroll/gazette-grade-wage`

**ট্যাব: Records 1**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Gazette wage by grade — Basic, House Rent, allowances and the Gross-A the | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Gazette, Year, Category, Grade, Basic, House Rent, Medical, Conveyance, Food, Gross-A, Status, ACTION
- বাটন/অ্যাকশন: New gazette grade wage, Export, Gazette, Year, Category, Grade, Basic, House Rent, Medical, Conveyance, Food, Gross-A, Status, Edit

**ট্যাব: New gazette grade wage**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Gazette* | select | ✔ | Select…, Gejet for 2023, RMG Minimum Wage 2023 (Non-EPZ), Gejet for 2018, Gejet for 2013 |
| Employee Category* | select | ✔ | Select…, Staff, Worker |
| Grade* | select | ✔ | Select…, Grade 1, Grade 2, Grade 3, Grade 4, Grade 5, Grade 6, Grade 7 |
| Basic Salary* | number | ✔ |  |
| House Rent (% of Basic)* | number | ✔ |  |
| Medical Allowance* | number | ✔ |  |
| Conveyance Allowance* | number | ✔ |  |
| Food Allowance* | number | ✔ |  |
| Other Allowance | number |  |  |
| Active | checkbox |  |  |
- সেকশন: New gazette grade wage
- বাটন/অ্যাকশন: New gazette grade wage, Reset, Save gazette grade wage

**ডায়ালগ: New gazette grade wage**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Gazette* | select | ✔ | Select…, Gejet for 2023, RMG Minimum Wage 2023 (Non-EPZ), Gejet for 2018, Gejet for 2013 |
| Employee Category* | select | ✔ | Select…, Staff, Worker |
| Grade* | select | ✔ | Select…, Grade 1, Grade 2, Grade 3, Grade 4, Grade 5, Grade 6, Grade 7 |
| Basic Salary* | number | ✔ |  |
| House Rent (% of Basic)* | number | ✔ |  |
| Medical Allowance* | number | ✔ |  |
| Conveyance Allowance* | number | ✔ |  |
| Food Allowance* | number | ✔ |  |
| Other Allowance | number |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Gazette is required.

#### Festival Calendar

`/m/hr-payroll/festival-calendar`

**ট্যাব: Festivals 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Festival Name* | text | ✔ | Eid-ul-Fitr |
| Festival Date* | date | ✔ |  |
| Year* | number | ✔ |  |
| Payment Month* | select | ✔ | Pick the date first |
| Bonus Rule | select |  | Select…, Bonus Rule |
| Bonus Type | select |  | Select…, Attendance Bonus, Eid-ul-Adha Bonus, Eid-ul-Fitr Bonus, Production Bonus |
| Minimum Service | number |  |  |
| Status | select |  | Select…, Active, Inactive |
| Eligibility Rule | textarea |  | e.g. Confirmed employees with the minimum service on the festival date |
| Reference | textarea |  | Moon-sighting notice, government circular or board decision |
| Also add its holiday Files the days in the holiday register and puts them on the | checkbox |  |  |
- গ্রিড কলাম: Festival, Year, Festival Date, Payment Month, Bonus Rule, Bonus Type, Min. Service, Eligibility, Status
- সেকশন: New Festival, Festivals
- বাটন/অ্যাকশন: Festivals 0, Holidays 3, Add Festival

**ডায়ালগ: Add Festival**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Festival Name* | text | ✔ |  |
| Festival Date* | date | ✔ |  |
| Year* | number | ✔ |  |
| Payment Month* | select | ✔ | Pick the date first |
| Bonus Rule | select |  | Select…, Bonus Rule |
| Bonus Type | select |  | Select…, Attendance Bonus, Eid-ul-Adha Bonus, Eid-ul-Fitr Bonus, Production Bonus |
| Minimum Service | number |  |  |
| Status | select |  | Select…, Active, Inactive |
| Eligibility Rule | textarea |  |  |
| Reference | textarea |  |  |
| Also add its holiday Files the days in the holiday register and puts them on the | checkbox |  |  |
- খালি Save-এ মেসেজ: That could not be saved. Try again. · Festival Name is required.

#### Payroll Settings

`/m/hr-payroll/company-pay-period`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Fiscal Year | select |  | FY2027-28, FY2026-27 |
| Pay Element | select |  | Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance (HRENT),  |
| Pay Period Number | text |  | APP-C-001202606 |
| Dept/Section Type | select |  | Production, Non Production |
| Closed? | select |  | Yes, No |
| Pay From Date | date |  |  |
| Pay To Date | date |  |  |
| Search Company pay periods with fiscal year, month, dates, status and payable to | search |  | Search 2 records… |
| Filter by Company | search |  |  |
| Filter by Fiscal Year | search |  |  |
| Filter by Month | search |  |  |
| Filter by Type | search |  |  |
| Filter by Pay Perod No | search |  |  |
| Filter by Pay Element | search |  |  |
| Filter by Pay Date | search |  |  |
| Filter by Show for Report | search |  |  |
| Filter by Close? | search |  |  |
| Filter by Total No Of Pay | search |  |  |
| Filter by Total Payable | search |  |  |
| Filter by Remarks | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Company, Fiscal Year, Month, Type, Pay Perod No, Pay Element, Pay Date, Show for Report, Close?, Total No Of Pay, Total Payable, Remarks, Status, ACTION
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Payroll Settings, Pay Period Actions, New Fiscal Year, New pay period, -- Select --, Search, Reset, Export, Company, Fiscal Year, Month, Type, Pay Perod No, Pay Element, Pay Date, Show for Report, Close?, Total No Of Pay, Total Payable, Remarks

**ট্যাব: Payroll Settings**
- গ্রিড কলাম: Setting, Value, Allowed, What it does, Last changed
- সেকশন: Payroll Settings
- বাটন/অ্যাকশন: Payroll Settings, Pay Period Actions, New Fiscal Year, New pay period, Revert, Save settings

**ট্যাব: Pay Period Actions**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search companys | search |  | Search… |
- সেকশন: Company, Pay Periods
- বাটন/অ্যাকশন: Payroll Settings, Pay Period Actions, New Fiscal Year, New pay period

**ট্যাব: New Fiscal Year**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search registers | search |  | Search… |
- সেকশন: Fiscal Years, Fiscal Year
- বাটন/অ্যাকশন: Payroll Settings, Pay Period Actions, New Fiscal Year, New pay period, Fiscal Year 2 year(s) on the register 2

**ট্যাব: New pay period**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pay Period No | text |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Period Type* | select | ✔ | Select…, Monthly, Days |
| Fiscal Year* | select | ✔ | Select…, FY2027-28, FY2026-27 |
| Start Date* | date | ✔ |  |
| End Date* | date | ✔ |  |
| Pay Date* | date | ✔ |  |
| Action Status* | select | ✔ | Select…, Initiated, Submitted, Activated, Closed |
| Pay Element | select |  | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Dept/Section Type | select |  | Select…, Production, Non Production |
| Remarks | textarea |  |  |
| Show for Report? | checkbox |  |  |
| Is Close? | checkbox |  |  |
- সেকশন: New pay period
- বাটন/অ্যাকশন: Payroll Settings, Pay Period Actions, New Fiscal Year, New pay period, Select…, Reset, Save pay period

**ডায়ালগ: New Fiscal Year**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search registers | search |  |  |

**ডায়ালগ: New pay period**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Pay Period No | text |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Period Type* | select | ✔ | Select…, Monthly, Days |
| Fiscal Year* | select | ✔ | Select…, FY2027-28, FY2026-27 |
| Start Date* | date | ✔ |  |
| End Date* | date | ✔ |  |
| Pay Date* | date | ✔ |  |
| Action Status* | select | ✔ | Select…, Initiated, Submitted, Activated, Closed |
| Pay Element | select |  | Select…, Basic Pay (BASIC), Conveyance Allowance (CONV), Food Allowance (FOOD), Gross Pay (GROSS), House Rent Allowance  |
| Dept/Section Type | select |  | Select…, Production, Non Production |
| Remarks | textarea |  |  |
| Show for Report? | checkbox |  |  |
| Is Close? | checkbox |  |  |
- খালি Save-এ মেসেজ: Company is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/HrPayroll`, সব টেবিল `hr_` নামে।
- **প্ল্যাটফর্ম থেকে নেওয়া (সবার ভাগের ভিত্তি):**
  - **Pay Grade:** Company Management → Designation & Grade-এর `grades` টেবিল। একটা গ্রেড একবারই রাখা হয়, দুই স্ক্রিন একই তালিকা দেখে। এখানে শুধু `code_bn` (Grade# [B]) কলাম যোগ হয়েছে।
  - Designations-এর সংখ্যা আসে `designations.grade_id` থেকে।
  - **কোম্পানির ইউজার:** Allowance আর Deduction-এ কর্মী হিসেবে (`users.employee_code`), যতদিন Employee মাস্টার (24/03) তৈরি না হয়।
  - **ফ্যাক্টরির ক্যালেন্ডার** (`factory_holidays`): তারিখসহ ছুটি এখানে বসে।
  - Company Management-এর বিভাগ, Bonus Rule-এর Department ঘরের জন্য।
- **অন্য ERP মডিউল থেকে নেওয়া:** Finance & Ledger-এর অর্থবছর রেজিস্টার (`acc_fiscal_years`)। Brickland-এ রেজিস্টার একটাই ("Registered once and shared with the HR calendar")। Payroll Settings → New Fiscal Year একই রেজিস্টারে লেখে।
- পুরনো প্রজেক্টের কোনো HR বা শিফট টেবিল ব্যবহার করা হয়নি। Knit Production প্ল্যাটফর্মের `shifts` ব্যবহার করে, কিন্তু HR-এর শিফট কোড (ST-MOR …) আলাদা, তাই HR-এর নিজের `hr_shifts`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_09_000100_create_hr_payroll_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `hr_employment_types` | name, name_bn, short_name, parent_id (নিজের টেবিলে), level, is_leaf, active |
| `hr_designation_groups` | name, name_bn, short_name, parent_id (নিজের টেবিলে), level, sort_order, manpower_category (OPERATOR/QUALITY/HELPER/SUPERVISOR/STAFF, ফাঁকা = পদবির নাম থেকে), is_leaf, active |
| `grades` (যোগ) | code_bn |
| `hr_pay_elements` | code, name, active |
| `hr_pay_periods` | period_no, period_type (MONTHLY/DAYS), fiscal_year_id, month, start_date, end_date, pay_date, status (INITIATED/SUBMITTED/ACTIVATED/CLOSED), pay_element_id, dept_type (PRODUCTION/NON_PRODUCTION), remarks, show_for_report, closed |
| `hr_allowances`, `hr_deductions` | pay_period_id, pay_element_id, user_id, amount — বেতনকাল + খাত + কর্মী ইউনিক |
| `hr_shifts` | code, name, start_time, end_time, break_min, working_hours, grace_min, cross_day, ot_allowed, ot_approval, effective_date, active, remarks |
| `hr_leave_types` | code, color, name, name_bn, paid, pay_element_id, day_forward, forward_days, accrual, accrual_auto, offday_included, holiday_included, balance_check, leave_group (LEAVE/PRESENT/ABSENT), min_employment, min_unit (DAY/MONTH/YEAR), notify_days, max_days, min_work_days, applicable_for (MALE/FEMALE/COMMON), encashable, active, remarks |
| `hr_ot_rules` | code, name, wage_basis (BASIC/GROSS), divisor, multiplier, effective_from, effective_to, employment_type_id, grade_id, legal_ref, remarks, active |
| `hr_bonus_rule_sets`, `hr_shift_teams` | name, active |
| `hr_bonus_rules` | rule_set_id, service_low, service_high, salary_low, salary_high, basis, pct, fixed, employment_type_id, department_id, designation_group_id, shift_team_id, active |
| `hr_gazettes` | name, number, year, industry, effective_date, active, reference |
| `hr_gazette_policies` | gazette_id, code (গেজেটের মধ্যে ইউনিক), name, active |
| `hr_gazette_lines` | gazette_id, employment_type_id, pay_element_id, amount, active |
| `hr_gazette_grade_wages` | gazette_id, employment_type_id, grade_id, basic, house_rent_pct, medical, conveyance, food, other, active |
| `hr_holidays` | fiscal_year_id, code, name, type (COMPANY/GOVT/WEEKLY_OFF), start_date, days, recurring, calendar_days, remarks, active |
| `hr_festivals` | name, festival_date, year, payment_month (YYYY-MM), bonus_rule_set_id, bonus_type (ATTENDANCE/EID_ADHA/EID_FITR/PRODUCTION), min_service, active, eligibility, reference, holiday_id |
| `hr_payroll_settings` | key, value |

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা (Brickland-এর মতো):** প্রথমবার খোলার সময়, শুধু খালি তালিকায়, তৈরি হয়। কেউ আগে সরাসরি কিছু যোগ করলেও আগে ডিফল্টগুলো বসে। একসাথে কয়েকটা রিকোয়েস্ট এলেও দুবার হয় না।
  - চাকরির ধরন: Staff (STF), Worker (WKR)।
  - ৪৫টা কর্মী শ্রেণি, ক্রম আর জনবল শ্রেণিসহ। Operetions Head আর Merchandiser লিফ নয়।
  - ৭টা গ্রেড (Grade# [B] = Grade-1 … Grade-7), যদি কোম্পানির কোনো গ্রেড না থাকে।
  - ৮টা বেতনের খাত: BASIC, CONV, FOOD, GROSS, HRENT, MED, OTREG, SHIFT।
  - ৪টা শিফট: ST-MOR, ST-GEN, ST-EVE, ST-NGT (রাত পেরোনো)। সবগুলোতে বিরতি ৬০, ৮ ঘণ্টা, গ্রেস ১৫, ওভারটাইম চলে আর অনুমোদন লাগে।
  - ৮টা ছুটির ধরন: CL, CPL, EL, LWP, ML, OL, SL, SPL।
  - OT-STD: Basic ÷ ২০৮ × ২, শ্রম আইন ২০০৬, ধারা ১০৮।
  - "Bonus Rule" সেটে ৬টা বোনাস নিয়ম। শিফট টিম Team A আর Team B।
  - ৪টা গেজেট। RMG 2023-এর Worker-এর ৫টা লাইন, Gejet 2013-এর Worker আর Staff-এর ১০টা লাইন, নীতি EPP-2013। RMG 2023 Worker Grade 4-এর গ্রেড মজুরি (Gross-A ১২,৫০০)।
  - ৩টা ছুটি (FY2026-27-এ): HOL-JANMASHTAMI, HOL-VICTORY (প্রতি বছর), HOL-WEEKLY-FRI। এগুলো ক্যালেন্ডারে বসানো হয় না, Brickland-এর মতোই।
  - ৬টা পে-রোল সেটিং।
- **মোছা যায় না**, শুধু নিষ্ক্রিয় করা যায়। ব্যতিক্রম: Allowance আর Deduction-এর এন্ট্রি, বেতন কাঠামো লাইন (Brickland-এর মতো), আর যে ছুটি ক্যালেন্ডারে দিন বসায়নি।
- **কোড বড় হাতের হয়, আর ইউনিকনেস বড়-ছোট হাত মানে না:** শিফট, ছুটি, ওভারটাইম নিয়ম, বেতনের খাত, ছুটির কোড। নামের বেলাতেও একই নিয়ম: চাকরির ধরন, শ্রেণি, গেজেট।
- **Employment Type, Employee Category:**
  - লেভেল প্যারেন্ট থেকে ঠিক হয় (প্যারেন্টের লেভেল + ১)।
  - কোনো নোড নিজের বা নিজের নিচের কারো নিচে বসতে পারে না।
  - নিচে কিছু এলে প্যারেন্ট আর লিফ থাকে না, আর শেষটা সরে গেলে আবার লিফ হয় (Brickland-এর মতো)।
  - **Employee Type Information:** নোড বাছাই করলে তার নিচের ধরনগুলোর editable গ্রিড আসে (নাম [ই], নাম [বা], সংক্ষিপ্ত নাম, Active)। পুরো গ্রিড একবারে সেভ হয়, আর নাম ইউনিক থাকে। "New" ডায়ালগ ("New — <নোড>") ওই নোডের নিচে নতুন ধরন তৈরি করে।
- **Pay Grade:** Grade# [E] ইউনিক। Grade Name ফাঁকা থাকলে "Grade N"। Active ঘর প্ল্যাটফর্মের `status` (ACTIVE/INACTIVE) হিসেবে লেখা হয়।
- **Pay Element:** In Structures = কতগুলো গেজেট লাইন খাতটা ব্যবহার করে। GL Mapped এখন ০; Salary Processing হিসাবের খাতায় পোস্ট করলে এটা আসবে।
- **Allowance / Deduction:**
  - প্রতি বেতনকালে একজন কর্মীর একটা খাতে একটাই এন্ট্রি।
  - Manual Entry-তে Allowance আবার দিলে অঙ্ক বদলে যায়। ফাইল থেকে এলে আগে দেওয়া এন্ট্রি বদলায় না, সারিটা বাদ পড়ে। Deduction-এ একই খাত দুবার কাটা যায় না।
  - **Employee Input Mode** (Excel Upload / Manual Entry): Excel Upload বাছাই করলে কর্মী আর অঙ্কের ঘর লুকায়, আর হিন্ট পাশের Excel Upload ট্যাব দেখায়।
  - বন্ধ বেতনকালে আর কিছু লেখা বা বদলানো যায় না।
  - **ফাইল আপলোড:** CSV, Excel (.xlsx) বা JSON।
    - হেডার Brickland-এর মতো: Allowance-এ "Emp Code, Amount"। Deduction-এ "Emp Code, Ded Type Id, Pay Period Id, Amount" — Ded Type Id হলো খাতের কোড, আর Pay Period Id ফাঁকা থাকলে বাছাই করা বেতনকাল ধরা হয়।
    - Show File Data প্রতিটা সারি যাচাই করে: কোড নেই, অজানা কর্মী, অজানা খাত, অজানা বা বন্ধ বেতনকাল, একই সারি দুবার, আগে দেওয়া বা কাটা, অঙ্ক ভুল। কিছু সেভ হয় না।
    - Upload শুধু ঠিক সারিগুলো সেভ করে।
- **Shift:** শেষের সময় শুরুর আগে হলে রাত পেরোনো শিফট নিজে থেকে চালু হয়। Working Hours ফাঁকা রাখলে = মোট সময় − বিরতি (রাত পেরোলে ২৪ ঘণ্টা যোগ করে)।
- **Leave Type:** Day Forward বন্ধ করলে জের টানা দিনের সংখ্যা মুছে যায়।
- **OT Rule:** কার্যকর শেষ, শুরুর আগে হতে পারে না। সূত্র দেখায় "Basic ÷ ২০৮ × ২"।
- **Bonus Rule:** ঊর্ধ্ব সীমা ০ = কোনো সীমা নেই। তা না হলে ঊর্ধ্ব সীমা নিম্ন সীমার কম হতে পারে না। শতাংশ বা নির্দিষ্ট অঙ্ক, একটা লাগবেই।
- **Wage/Gazette Rule:**
  - একটা গেজেটে একটা শ্রেণি আর খাতের লাইন একবারই থাকে।
  - Means: BASIC = "Gross ÷ x", HRENT = "x% of Basic", বাকিগুলো = "৳x"।
  - গেজেটের ফর্ম গেজেট আর তার নীতিগুলো একসাথে সেভ করে। নীতির কোড গেজেটের মধ্যে ইউনিক।
  - বেতন কাঠামো শুধু দেখার জন্য। Gross Minimum আসে GROSS লাইন থেকে। Basic at Minimum = (গ্রস − চিকিৎসা − খাবার − যাতায়াত) ÷ BASIC।
  - যে গেজেটের লাইন বা গ্রেড মজুরি আছে, সেটা মোছা যায় না।
- **Gazette Grade Wage:** একটা গেজেটে একটা শ্রেণি আর গ্রেড একবারই। Gross-A = মূল বেতন + বাড়িভাড়া (মূল বেতনের %) + চিকিৎসা + যাতায়াত + খাবার + অন্যান্য।
- **Festival Calendar:**
  - বছর অবশ্যই উৎসবের তারিখের বছর হবে। একই বছরে একই উৎসব একবারই।
  - "Also add its holiday" দিলে ছুটির রেজিস্টারে HOL-<নাম>-<বছর> নামে একটা সরকারি ছুটি হয়, যে অর্থবছরে তারিখটা পড়ে সেখানে, আর ক্যালেন্ডারে বসে। কোনো অর্থবছরে তারিখটা না পড়লে সেভ হয় না।
  - **উৎসবের সারি:** Status সুইচ (Active/Inactive) আর মোছা।
  - **ছুটির সারি:** "Put on calendar" (তারিখসহ সক্রিয় ছুটি ক্যালেন্ডারে বসায়), Status সুইচ আর মোছা।
  - **Payment Month:** উৎসবের তারিখের মাস আর তার আগে-পরের মাস থেকে বাছাই করা হয় ("Pick the date first")।
  - **ছুটি:** শুরুর তারিখ অবশ্যই অর্থবছরের ভেতরে হবে। তারিখসহ সক্রিয় ছুটি কোম্পানির প্রতিটা ফ্যাক্টরির ক্যালেন্ডারে প্রতিটা দিনে বসে। আগে থেকে থাকা দিন কখনো বদলানো হয় না। On Calendar দেখায় কয় দিন বসেছে। যে ছুটি দিন বসিয়েছে সেটা মোছা যায় না।
- **Payroll Settings:**
  - **বেতনকালের নম্বর:** সেভের সময় তৈরি হয়, `APP-<কোম্পানির কোড><অর্থবছর><মাস><ক্রম>`, যেমন APP-DALFY2026-27090001। হাতে লেখা হয় না।
  - শেষের তারিখ শুরুর আগে হতে পারে না। CLOSED অবস্থা দিলে "বন্ধ?" নিজে থেকে চালু হয়।
  - Total No Of Pay = Allowance পাওয়া কর্মীর সংখ্যা। Total Payable = Allowance − Deduction।
  - **Pay Period Actions:** প্রতিটা সারিতে পাঁচটা ধাপ:
    - Submit for Activation (Initiated → Submitted), Activate (Submitted → Activated), Send back (Submitted → Initiated, Activated → Submitted), Close (Activated → Closed), Re-open (Closed → Activated)।
    - বাছাই করা ধাপ Save পর্যন্ত রাখা থাকে। বর্তমান অবস্থা যেটা অনুমতি দেয় না, সেটা কারণসহ ফেরত আসে।
    - একই গ্রিডে Show for Report আর Remarks বদলানো যায়। মোট সংখ্যা শুধু পড়া যায়।
  - **New Fiscal Year:** গ্রিডে FY Name, FY/CY শুরু আর শেষ, Pay Periods সংখ্যা, Active আর Status। নতুন বছর যোগ হয়। গ্রিড থেকে সরানো বছর মুছে যায়, যদি তার অধীনে বেতনকাল বা ছুটি না থাকে। Active মানে Finance-এর "বন্ধ নয়"।
  - **সেটিং:** প্রতিটার নিজের নিয়ম (১–৩১, ০–২৪০, NONE/LATE_DAYS/PER_MINUTE …)। লেখা বড় হাতের হয়।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{hr-employment-types|hr-designation-groups|hr-grades|hr-pay-elements|hr-allowances|hr-deductions|hr-shifts|hr-leave-types|hr-ot-rules|hr-bonus-rules|hr-gazette-lines|hr-gazette-grade-wages|hr-festivals|hr-holidays|hr-pay-periods|hr-fiscal-years}`।
  - `hr-grades` প্ল্যাটফর্মের `grades`-এর ওপর।
  - `hr-fiscal-years` Finance-এর `acc_fiscal_years`-এর ওপর, HR-এর পারমিশন দিয়ে।
- **`/api/v1/hr-payroll/...`:**
  - `lookups`
  - `type-tree`, `type-tree/{type}`
  - `entries/{allowances|deductions}` (POST, এক বা অনেক সারি)
  - `uploads` (POST multipart: format, file, element_chosen) — ফাইল পড়ে যাচাই করে, কিছু সেভ করে না।
  - `gazettes/{gazette}` (GET/PUT/DELETE), `gazettes` (POST)
  - `settings` (GET/PUT)
  - `period-companies`, `period-companies/{company}` (GET/PUT)
  - `fiscal-registers`, `fiscal-registers/FY` (GET/PUT)
- **পারমিশন:** নতুন `hrpayroll.master.view` আর `hrpayroll.master.manage`।
  - Owner আর Company Admin সব পায়।
  - FACTORY_ADMIN পায় দুটোই। FACTORY_MANAGER আর AUDITOR পায় শুধু view।
  - VIEWER সব view পারমিশনের মতো এটাও পায়।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/hr-payroll/<screen>`। সাইডবারে HUMAN RESOURCE → HR & Payroll → MASTER DATA, Company Management-এর পরে।
- **Edit (Brickland-এর মতো):** Records-এর Edit মডাল খোলে না। "New X" ট্যাবের জায়গায় "Edit X" ট্যাব আসে (`?tab=edit&id=…`), ফর্ম ভরা অবস্থায়। সাথে "Rewriting <নাম>. Save to keep the changes, or cancel to leave it as it was." লেখা, আর Cancel / Update X বোতাম। ১১টা স্ক্রিনে এভাবে।
- প্রতিটা স্ক্রিন Brickland-এর ট্যাবে:
  - **Employment Type:** Records · Employee Type Information · New employee type। Information ট্যাবে বামে নোডের গাছ, ডানে বাছাই করা নোডের নিচের ধরন।
  - **Employee Category, Pay Grade, Pay Element, Shift, Leave Type, OT Rule, Bonus Rule, Gazette Grade Wage:** Records আর New।
  - **Allowance:** Records · Excel Upload · Manual Entry। **Deduction:** Entry · Upload · New Deduction।
    - Upload ট্যাবে ফাইলের ধরন (CSV / Excel / JSON), খাত (শুধু Allowance-এ), কোম্পানি, বেতনকাল, ফাইল, আর Show File Data · Template · Upload।
    - যাচাই হওয়া সারিগুলো টেবিলে আসে, প্রতিটার ফলাফলসহ।
    - ফাইল একটা route handler (`/api/hr-payroll/uploads`) দিয়ে সরাসরি Laravel-এ যায়, কারণ server action ১ MB-এর বেশি নেয় না।
  - **Wage/Gazette Rule:** Records · Wage Gazette · New salary structure line।
    - Wage Gazette ট্যাবে Existing gazette বাছাই করলে ফর্মে আসে: Gazette, Validity, Policy Under Gazette (editable গ্রিড, Add policy) আর Salary Structure (শুধু দেখার জন্য)।
    - বোতাম: Delete · Clear · Save gazette বা Save changes।
  - **Festival Calendar:** Festivals · Holidays।
    - Festivals ট্যাবে New Festival ফর্ম আর তার নিচে তালিকা। Holidays ট্যাবে Add Holiday ফর্ম আর Holiday Register।
  - **Payroll Settings:** Records · Payroll Settings · Pay Period Actions · New Fiscal Year · New pay period।
    - Records-এ "Searching Data" প্যানেলে ৯টা ফিল্টার।
    - Payroll Settings ট্যাবে Setting, Value, Allowed, What it does আর Last changed কলাম, সাথে Revert আর Save settings।
    - Pay Period Actions-এ বামে কোম্পানি, ডানে বেতনকাল; অবস্থা বদলে Save করতে হয়।
    - New Fiscal Year-এ বামে রেজিস্টার, ডানে বছরগুলোর editable গ্রিড, তারিখের ঘরসহ।
- **স্ক্রিন-কিটে যোগ:**
  - RecordsGrid-এ `edit.href`: Edit বাটন "Edit X" ট্যাবে যায়।
  - EntryForm-এ `cancelHref` (Cancel), `inline` (কার্ডের ভেতরে Add বোতাম), ৪ কলাম, `radio` ঘর, `showWhen` (শর্তে দেখানো ঘর) আর `monthsFrom` (তারিখ থেকে মাস বাছাই)।
  - EditGridTable-এ `actions` কলাম (সারিতে ধাপের বোতাম)।
  - SideGrid-এ `newForm`: ডান প্যানেলে New ডায়ালগ।
  - EntryForm-এ `month` ধরনের ঘর।
  - EditGridTable-এ `date` ধরনের কলাম।
  - FilterBar-এ ঐচ্ছিক শিরোনাম আর হিন্ট।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/HrPayroll/Database/Migrations/2026_11_09_000100_create_hr_payroll_masters.php`
- `app/Modules/HrPayroll/Models/HrEmploymentType.php`
- `app/Modules/HrPayroll/Models/HrDesignationGroup.php`
- `app/Modules/HrPayroll/Models/HrPayElement.php`
- `app/Modules/HrPayroll/Models/HrPayPeriod.php`
- `app/Modules/HrPayroll/Models/HrAllowance.php`
- `app/Modules/HrPayroll/Models/HrDeduction.php`
- `app/Modules/HrPayroll/Models/HrShift.php`
- `app/Modules/HrPayroll/Models/HrLeaveType.php`
- `app/Modules/HrPayroll/Models/HrOtRule.php`
- `app/Modules/HrPayroll/Models/HrBonusRuleSet.php`
- `app/Modules/HrPayroll/Models/HrShiftTeam.php`
- `app/Modules/HrPayroll/Models/HrBonusRule.php`
- `app/Modules/HrPayroll/Models/HrGazette.php`
- `app/Modules/HrPayroll/Models/HrGazettePolicy.php`
- `app/Modules/HrPayroll/Models/HrGazetteLine.php`
- `app/Modules/HrPayroll/Models/HrGazetteGradeWage.php`
- `app/Modules/HrPayroll/Models/HrHoliday.php`
- `app/Modules/HrPayroll/Models/HrFestival.php`
- `app/Modules/HrPayroll/Models/HrPayrollSetting.php`
- `app/Modules/HrPayroll/Support/HrDefaults.php`
- `app/Modules/HrPayroll/Support/HolidayCalendar.php`
- `app/Modules/HrPayroll/Resources/HrResource.php`
- `app/Modules/HrPayroll/Resources/EmploymentTypeResource.php`
- `app/Modules/HrPayroll/Resources/DesignationGroupResource.php`
- `app/Modules/HrPayroll/Resources/PayGradeResource.php`
- `app/Modules/HrPayroll/Resources/PayElementResource.php`
- `app/Modules/HrPayroll/Resources/AllowanceResource.php`
- `app/Modules/HrPayroll/Resources/DeductionResource.php`
- `app/Modules/HrPayroll/Resources/ShiftResource.php`
- `app/Modules/HrPayroll/Resources/LeaveTypeResource.php`
- `app/Modules/HrPayroll/Resources/OtRuleResource.php`
- `app/Modules/HrPayroll/Resources/BonusRuleResource.php`
- `app/Modules/HrPayroll/Resources/GazetteLineResource.php`
- `app/Modules/HrPayroll/Resources/GazetteGradeWageResource.php`
- `app/Modules/HrPayroll/Resources/HolidayResource.php`
- `app/Modules/HrPayroll/Resources/FestivalResource.php`
- `app/Modules/HrPayroll/Resources/PayPeriodResource.php`
- `app/Modules/HrPayroll/Resources/HrFiscalYearResource.php`
- `app/Modules/HrPayroll/Http/Controllers/Api/HrPayrollApiController.php`
- `app/Modules/HrPayroll/Routes/api.php`
- `app/Modules/Tenancy/Models/Grade.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/hr-payroll/[screen]/page.js`
- `frontend/src/app/(app)/hr-payroll/[screen]/screens.js`
- `frontend/src/app/(app)/hr-payroll/[screen]/actions.js`
- `frontend/src/app/api/hr-payroll/uploads/route.js`
- `frontend/src/components/hrpayroll/entry-upload.jsx`
- `frontend/src/components/hrpayroll/gazette-form.jsx`
- `frontend/src/components/hrpayroll/payroll-settings.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `frontend/src/components/erp/filter-bar.jsx`
- `AnnotechERP/tools/erp_crawler/plans/hr6.json`
- `AnnotechERP/tools/erp_crawler/plans/hr5.json`
- `AnnotechERP/tools/erp_crawler/probe.mjs`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/workstudy/edit-grid.jsx`
- `lang/en/hrpayroll.php`
- `lang/bn/hrpayroll.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/HrPayroll/HrPayrollMasterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `HrPayrollMasterTest` ৭/৭। Identity guard suite ৮৮/৮৮ (RoleMatrixTest সহ)।
  - **দ্বিতীয় দফায় যোগ হওয়া যাচাই:** গাছের গ্রিড সেভ, একই নাম চলে না, লিফ আবার ফেরে। Manual-এ replace ছাড়া আগের এন্ট্রি চলে না। Brickland-এর হেডারে আপলোড, আগে দেওয়া বা অজানা বেতনকাল ধরা পড়ে। পাঁচটা ধাপ ক্রমে, ভুল ধাপ কারণসহ ফেরত আসে, Remarks আর Show for Report সেভ হয়। ব্যবহৃত অর্থবছর মোছা যায় না, অব্যবহৃত মোছা যায়। Put on calendar কাজ করে, তারিখ ছাড়া চলে না। উৎসব নিষ্ক্রিয় আর মোছা যায়।
  - **শুরুর ডাটা:**
    - ২টা ধরন। ৪৫টা শ্রেণি, যার ২টা লিফ নয়; Worker-এর জনবল শ্রেণি ফাঁকা, QI = Quality। ৭টা গ্রেড।
    - ৮টা খাত; BASIC আছে ৩টা কাঠামোতে।
    - ST-NGT রাত পেরোনো। ML = FEMALE, ১১২ দিন।
    - OT সূত্র "Basic ÷ 208 × 2"। বোনাস নিয়ম Staff-এর ৪টা, Worker-এর ২টা।
    - ১৫টা গেজেট লাইন, Means সহ। গ্রেড মজুরির Gross-A ১২,৫০০। ৩টা ছুটি। ৬টা সেটিং। ২টা অর্থবছর।
  - **গাছ, গ্রেড আর খাত:**
    - নাম ছাড়া মেসেজ আসে। Staff-এর নিচে Probation লেভেল ২-এ বসে, আর Staff লিফ থাকে না। নিজের নিচে বসানো চলে না। একই নাম চলে না। গাছে "— Probation" দেখায়। মোছা যায় না (405)।
    - গ্রেড: নম্বর ছাড়া মেসেজ আসে। নতুন গ্রেডের নাম "Grade 8" হয়। একই নম্বর চলে না। নিষ্ক্রিয় করলে `grades.status` = INACTIVE হয়।
    - খাত: কোড বড় হাতের হয়। একই কোড চলে না।
  - **শিফট, ছুটি, ওভারটাইম, বোনাস:**
    - শিফট: সময় ভুল হলে চলে না। ২০:০০–০৪:৩০ রাত পেরোনো, বিরতি ৩০ মিনিট হলে ৮ ঘণ্টা।
    - ছুটি: অজানা গ্রুপ চলে না। একই কোড চলে না, ডিফল্টগুলো আগে না খুললেও।
    - ওভারটাইম: কার্যকর শেষ শুরুর আগে চলে না। সূত্র "Gross ÷ 208 × 1.5"।
    - বোনাস: ঊর্ধ্ব < নিম্ন চলে না। শতাংশ বা অঙ্ক ছাড়া চলে না।
  - **গেজেট:**
    - ২০১৩-র নীতি আর কাঠামো আসে। নাম ছাড়া বা একই নীতির কোড দুবার দিলে চলে না। নীতির কোড বড় হাতের হয়, আর সেভ করলে পুরো তালিকা বদলে যায়।
    - লাইন: দুবার চলে না। Gross Minimum ৳১২,৫০০ আর Basic at Minimum ৳৬,৭০০.০০।
    - যে গেজেটের লাইন আছে সেটা মোছা যায় না; খালি গেজেট মোছা যায়।
    - গ্রেড মজুরি: Gross-A ১৪,৫৫০। দুবার চলে না।
  - **উৎসব ও ছুটি:**
    - বছর না মিললে চলে না। কোনো অর্থবছরে না পড়লে ছুটি যোগ করা যায় না।
    - HOL-EID-UL-ADHA-2027 তৈরি হয় আর ফ্যাক্টরির ক্যালেন্ডারে বসে। একই উৎসব একই বছরে দুবার চলে না।
    - অর্থবছরের বাইরের তারিখ চলে না। ৩ দিনের ছুটি ক্যালেন্ডারে ৩ দিন বসায়। ক্যালেন্ডারে থাকা ছুটি মোছা যায় না, না থাকলে যায়।
  - **বেতনকাল, এন্ট্রি, আপলোড, সেটিং আর অর্থবছর:**
    - নম্বর হয় APP-DALFY2026-27090001, তারপর …0002; হাতে দেওয়া নম্বর মানা হয় না।
    - Allowance আবার দিলে অঙ্ক বদলায়। অজানা কোড চলে না। Deduction দুবার চলে না। Total No Of Pay ১, Total Payable ৫৫০।
    - CSV-তে চারটা সারির ফলাফল আলাদা আলাদা আসে। JSON-এ প্রতিটা সারির খাত যাচাই হয়। ধরন না মিললে ফাইল চলে না।
    - এক লাফে ACTIVATED চলে না; তিন ধাপে CLOSED হয়, তারপর এন্ট্রি চলে না।
    - সেটিং সীমার বাইরে চলে না; মান বড় হাতের হয়।
    - অর্থবছর: মিলে যাওয়া বছর চলে না। নতুন বছর Finance-এর তালিকাতেও আসে।
  - **পারমিশন:**
    - অডিটর দেখতে পারে, বদলাতে পারে না।
    - ফ্যাক্টরি ম্যানেজার দেখতে পারে, গেজেট বদলাতে পারে না।
    - ফ্যাক্টরি অ্যাডমিন বদলাতে পারে।
    - ভিউয়ার দেখতে পারে, লিখতে পারে না।
- **ব্রাউজার, দ্বিতীয় দফা:**
  - Shift-এর Edit ট্যাবে grace বদলে Update করা হয়েছে।
  - গাছে Staff → New → "Probation" যোগ করা হয়েছে।
  - Manual Entry-র দুই মোড দেখা হয়েছে।
  - Deduction Upload-এর লেআউট দেখা হয়েছে।
  - Festival-এ তারিখ দিলে মাসের অপশন আসে।
  - Holiday Register, Pay Period Actions-এর ধাপের বোতাম আর New Fiscal Year দেখা হয়েছে।
- **ব্রাউজার (dev):** ১৪টা স্ক্রিন আর তাদের প্রতিটা ট্যাব খোলা হয়েছে। কনসোলে কোনো error নেই। যা সেভ বা যাচাই হয়েছে:
  - অক্টোবর ২০২৬-এর বেতনকাল।
  - Manual Entry দিয়ে একটা Food Allowance।
  - CSV ফাইল যাচাই: ২টা সারি, দুটোতেই সমস্যা দেখায়।
  - গাছে Staff বাছাই করা।
  - Gejet for 2013 লোড করলে নীতি আর কাঠামো আসে।
  - Eid-ul-Fitr ২০২৭ "Also add its holiday" দিয়ে সেভ; Holidays ট্যাবে HOL-EID-UL-FITR-2027, On Calendar ১।
  - Pay Period Actions আর New Fiscal Year-এর গ্রিড।

## ১০. খোলা প্রশ্ন

- **কর্মী কে:** Employee মাস্টার (24/03) না আসা পর্যন্ত Allowance আর Deduction কোম্পানির ইউজার (employee_code) দিয়ে চলে। ওই মাস্টার এলে `user_id` কর্মীর foreign key-তে বদলাবে।
- **Designation-এর শ্রেণি:** Company Management-এর `designations.employee_category` এখনো লেখা ঘর। কর্মী শ্রেণি (`hr_designation_groups`) এসে গেছে, তাই Employee মাস্টারের সময় এটা foreign key করা যাবে।
- **GL Mapped:** Salary Processing (24/06) যখন হিসাবের খাতায় পোস্ট করবে, তখন খাতগুলো লেজারের সাথে যুক্ত হবে।
- **পুরনো Excel (.xls):** Brickland-এর লেবেলে .xls আছে, কিন্তু এখানে শুধু .xlsx পড়া যায়। .xls দিলে "ফাইলের ধরন মিলছে না" মেসেজ আসে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- লাইভ probe দিয়ে আগে থেকে দেখে নেওয়া হয়েছে: ১৪টা স্ক্রিনের প্রতিটা ট্যাব, ফর্ম আর ডায়ালগ; ৪৫টা শ্রেণি, ৮টা খাত, ৪টা শিফট, ৮টা ছুটি, OT আর বোনাস নিয়ম, ৪টা গেজেটের লাইন আর নীতি, গ্রেড মজুরি, ছুটির রেজিস্টার আর ৬টা সেটিং; প্রতিটা ফর্মের হিন্ট।
- **দ্বিতীয় দফায় মিলিয়ে দেখা (ব্যবহারকারীর ধরিয়ে দেওয়ার পর):** প্রথম probe শুধু ট্যাব খুলেছিল। এবার probe টুল ডায়ালগের বিষয়বস্তু পড়ে, আর Edit, গাছের নোড, সারির বোতাম আর Upload-এর অবস্থা আলাদা করে খুলেছে (plans `hr5.json`, `hr6.json`)। যা বাদ পড়েছিল আর এখন যোগ হয়েছে:
  - Employee Type Information-এর New ডায়ালগ আর editable গ্রিড।
  - সব স্ক্রিনে Edit ট্যাব।
  - Employee Input Mode।
  - Brickland-এর Upload হেডার, নিয়ম আর লেআউট।
  - Pay Period Actions-এর পাঁচটা ধাপ।
  - Fiscal Year গ্রিডের কলাম আর মোছা।
  - Festival আর Holiday-এর লেআউট, সারির বোতাম আর Payment Month।
  - কয়েকটা হিন্ট আর ফুটারের লেখা।
- **Brickland-এর সাথে তফাত:**
  - **Pay Grade-এর Grade Name:** Brickland-এ ফাঁকা (—), এখানে "Grade N"। প্ল্যাটফর্মের গ্রেড টেবিলে নাম বাধ্যতামূলক।
  - **Shift-এর কলামভিত্তিক ফিল্টার:** Brickland-এ প্রতিটা কলামের নিচে ফিল্টারের ঘর আছে। এখানে স্ক্রিন-কিটের সাধারণ খোঁজা (সব কলামে) ব্যবহার হয়।
  - **New Fiscal Year-এর ওপরের বোতাম:** Brickland-এ লেবেল "+ Save" (নতুন সারি যোগ করে), এখানে "+ New"।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - তালিকা না খুলে সরাসরি কিছু যোগ করলে ডিফল্টগুলো আর কখনো বসত না। এখন যাচাইয়ের আগেই বসে।
  - ফাইল বাছাই করলে ফাইলের বাক্স আবার তৈরি হয়ে নাম মুছে যাচ্ছিল। এখন শুধু ধরন বদলালে বা আপলোডের পরে মোছে।
  - মেনুর নাম lang ফাইলের আগে sync হয়েছিল, তাই কাঁচা key দেখাচ্ছিল। আবার sync করা হয়েছে।

