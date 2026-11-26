# Alerts & Exceptions

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Command Center](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `27/03`

**⬅ ফিরে যান:** [Command Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | KPI Threshold Rules | `/m/executive-dashboard/kpi-alert-rules` | 1 | 3 | 1 |
| 2 | Alert History Log | `/m/executive-dashboard/alert-log` | 1 | 3 | 0 |
| 3 | Exception Review Center | `/m/executive-dashboard/exception-center` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### KPI Threshold Rules

`/m/executive-dashboard/kpi-alert-rules`

**ভিউ**
> 08 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search KPI threshold rules | search |  | Search rules… |
| Filter by severity | select |  | All severities, Critical, Serious, Warning, Information |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Rule, Severity, Told, Channel, ARMED
- সেকশন: Threshold rules
- বাটন/অ্যাকশন: New rule, Rule, Severity, Told, Channel

**ডায়ালগ: New rule** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Indicator | select |  | Select…, Line efficiency, On-time shipment, Fabric wastage, Right first time, Operator attrition, Machine downtime, Gros |
| Comparison | select |  | Select…, Falls below, Rises above, Changes by more than |
| Threshold | number |  |  |
| Unit | select |  | Select…, %, Minutes, Days, Pcs, BDT |
| Checked | select |  | Select…, Per shift, Daily, Weekly, Monthly |
| Severity | select |  | Select…, Critical, Serious, Warning, Information |
| Told | select |  | Select…, Chief executive, Division heads, Production manager, Merchandising manager, HR manager, Maintenance manager |
| Channel | select |  | Select…, Email, SMS, In-app, Email + SMS |
- খালি Save-এ মেসেজ: Rule on Line efficiency created.

#### Alert History Log

`/m/executive-dashboard/alert-log`

**ভিউ**
> 09 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Alert history log | search |  | Search alerts… |
| Unacknowledged only | checkbox |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Fired, Indicator, Severity, Acknowledged
- সেকশন: Alert history
- বাটন/অ্যাকশন: Log an alert, Fired, Indicator, Severity, Acknowledged, Acknowledge

#### Exception Review Center

`/m/executive-dashboard/exception-center`

**ভিউ**
> 18 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Open exceptions across every module | search |  | Search exceptions… |
| Filter by module | select |  | All modules, Merchandising, Knit Production, Dye House, Cutting Floor, Assembly Floor, Central Stores, Trade & Export, H |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Exception, Module, Severity, Owner, Age, State
- সেকশন: Exception review
- বাটন/অ্যাকশন: Raise exception, Exception, Module, Severity, Owner, Age, State
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
