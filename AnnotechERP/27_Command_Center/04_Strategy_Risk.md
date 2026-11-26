# Strategy & Risk

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Command Center](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `27/04`

**⬅ ফিরে যান:** [Command Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Leadership Calendar | `/m/executive-dashboard/executive-calendar` | 1 | 3 | 1 |
| 2 | Strategic Initiative Tracker | `/m/executive-dashboard/strategic-initiatives` | 1 | 3 | 1 |
| 3 | Enterprise Risk Log | `/m/executive-dashboard/enterprise-risk-register` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Leadership Calendar

`/m/executive-dashboard/executive-calendar`

**ভিউ**
> 13 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Leadership calendar | search |  | Search entries… |
| Filter by kind | select |  | All kinds, Board meeting, Management review, Buyer visit, Audit, Statutory filing, Town hall |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Entry, Kind, Owner, Where, State
- সেকশন: Leadership calendar
- বাটন/অ্যাকশন: New entry, Date, Entry, Kind, Owner, Where, State

**ডায়ালগ: New entry** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Entry* | text | ✔ |  |
| Kind | select |  | Select…, Board meeting, Management review, Buyer visit, Audit, Statutory filing, Town hall |
| Owner | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain |
| Date | date |  |  |
| Location | select |  | Select…, Head office, Unit 1, Unit 2, Buyer office, Online |
| Attendees | number |  |  |
| State | select |  | Select…, Scheduled, Confirmed, Postponed, Done |
| Agenda | textarea |  |  |
- খালি Save-এ মেসেজ: Name the entry.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Strategic Initiative Tracker

`/m/executive-dashboard/strategic-initiatives`

**ভিউ**
> 14 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Strategic initiatives | search |  | Search initiatives… |
| Filter by stage | select |  | All stages, Proposed, Approved, In flight, Benefits tracking, Closed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Initiative, Pillar, Sponsor, Investment, Benefit p.a., Progress, Stage
- সেকশন: Initiative tracker
- বাটন/অ্যাকশন: New initiative, Initiative, Pillar, Sponsor, Investment, Benefit p.a., Progress, Stage

**ডায়ালগ: New initiative** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Initiative* | text | ✔ |  |
| Sponsor | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain |
| Stage | select |  | Select…, Proposed, Approved, In flight, Benefits tracking, Closed |
| Strategic pillar | select |  | Select…, Cost leadership, Buyer intimacy, Sustainability, Digitisation, People |
| Investment | number |  |  |
| Annual benefit | number |  |  |
| Progress | number |  |  |
| Target date | date |  |  |
| Summary | textarea |  |  |
- খালি Save-এ মেসেজ: Name the initiative.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Enterprise Risk Log

`/m/executive-dashboard/enterprise-risk-register`

**ভিউ**
> 15 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Enterprise risk register | search |  | Search risks… |
| Filter by category | select |  | All categories, Supply chain, Financial, Compliance, Operational, People, Reputation, Environmental |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Risk, Category, Owner, L, I, Band, Reviewed
- সেকশন: Risk register
- বাটন/অ্যাকশন: Log a risk, Risk, Category, Owner, L, I, Band, Reviewed
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
