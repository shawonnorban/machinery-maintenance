# Kpi & Targets

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Command Center](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `27/02`

**⬅ ফিরে যান:** [Command Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | KPI Master Library | `/m/executive-dashboard/kpi-library` | 1 | 3 | 1 |
| 2 | Balanced Scorecard Board | `/m/executive-dashboard/balanced-scorecards` | 1 | 3 | 1 |
| 3 | Target Definition | `/m/executive-dashboard/target-setting` | 1 | 3 | 1 |
| 4 | Target Achievement Tracking | `/m/executive-dashboard/target-tracking` | 1 | 3 | 0 |
| 5 | KPI Approval Queue | `/m/executive-dashboard/kpi-approvals` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### KPI Master Library

`/m/executive-dashboard/kpi-library`

**ভিউ**
> 02 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search KPI master library | search |  | Search indicators… |
| Filter by perspective | select |  | All perspectives, Financial, Customer, Internal Process, Learning & Growth |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Indicator, Perspective, Unit, Frequency, Good when, Owner, Source, Status
- সেকশন: KPI master library
- বাটন/অ্যাকশন: New indicator, Code, Indicator, Perspective, Unit, Frequency, Good when, Owner, Source, Status

**ডায়ালগ: New indicator** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Indicator name* | text | ✔ |  |
| Formula* | textarea | ✔ |  |
| Perspective | select |  | Select…, Financial, Customer, Internal Process, Learning & Growth |
| Unit | select |  | Select…, %, USD, BDT, Pcs, Days, Ratio, Minutes |
| Frequency | select |  | Select…, Daily, Weekly, Monthly, Quarterly, Yearly |
| Good when | select |  | Select…, Higher is better, Lower is better, On target |
| Owner* | text | ✔ |  |
| Source module | select |  | Select…, Accounting, Merchandising, Knit Production, Assembly Floor, Cutting Floor, Central Stores, HR & Payroll, Work S |
| Status | select |  | Select…, Published, Draft, Under review, Retired |
- খালি Save-এ মেসেজ: An indicator code is required. · Give the indicator a name. · Someone has to answer for it.
- invalid চিহ্নিত ফিল্ড: 3টা

#### Balanced Scorecard Board

`/m/executive-dashboard/balanced-scorecards`

**ভিউ**
> 03 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Balanced scorecard measures | search |  | Search measures… |
| Scorecard period | select |  | Q1 2026, Q2 2026, Q3 2026, Q4 2026 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Perspective, Measure, Weight, Target, Actual, Attainment
- সেকশন: Perspective balance, Scorecard measures
- বাটন/অ্যাকশন: New measure, Perspective, Measure, Weight, Target, Actual, Attainment

**ডায়ালগ: New measure** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Measure* | text | ✔ |  |
| Perspective | select |  | Select…, Financial, Customer, Internal Process, Learning & Growth |
| Owner | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain |
| Weight within perspective | number |  |  |
| Unit | select |  | Select…, %, Ratio, Days, BDT, Pcs |
| Target | number |  |  |
| Actual | number |  |  |
| Period | select |  | Select…, Q1 2026, Q2 2026, Q3 2026, Q4 2026 |
- খালি Save-এ মেসেজ: Name the measure.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Target Definition

`/m/executive-dashboard/target-setting`

**ভিউ**
> 04 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Departmental targets | search |  | Search targets… |
| Target year | select |  | 2025, 2026, 2027 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Department, Indicator, Annual target, Monthly spread, Status
- সেকশন: Target definition
- বাটন/অ্যাকশন: New target, Department, Indicator, Annual target, Monthly spread, Status, Months

**ডায়ালগ: New target** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Department | select |  | Select…, Merchandising, Knitting, Dyeing, Cutting, Sewing, Finishing, Commercial, Human Resource, Maintenance |
| Indicator | select |  | Select…, Shipment value, Shipment quantity, Line efficiency, Fabric wastage, Right first time, Operator attrition, Machi |
| Annual target* | number | ✔ |  |
| Unit | select |  | Select…, USD, Pcs, %, Kg, Hours |
| Monthly spread | select |  | Select…, Even, Seasonal peak (Q3), Ramp up, Front loaded |
| Year | select |  | Select…, 2025, 2026, 2027 |
| Owner | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain, Shamima Akter |
| Status | select |  | Select…, Agreed, Proposed, Rejected |
- খালি Save-এ মেসেজ: An annual figure above zero is required.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Target Achievement Tracking

`/m/executive-dashboard/target-tracking`

**ভিউ**
> 05 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Target achievement register | search |  | Search readings… |
| Reporting period | select |  | Apr 2026, May 2026, Jun 2026, Jul 2026, Aug 2026 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Department, Indicator, Target, Actual, Variance
- সেকশন: Group shipment value against plan, Achievement register
- বাটন/অ্যাকশন: Record reading, Department, Indicator, Target, Actual, Variance

#### KPI Approval Queue

`/m/executive-dashboard/kpi-approvals`

**ভিউ**
> 19 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search KPI approval queue | search |  | Search requests… |
| Filter by state | select |  | All states, Waiting, In review, Approved, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Indicator, Change, Requested by, Approver, Raised, State
- সেকশন: Approval queue
- বাটন/অ্যাকশন: Raise request, Indicator, Change, Requested by, Approver, Raised, State
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
