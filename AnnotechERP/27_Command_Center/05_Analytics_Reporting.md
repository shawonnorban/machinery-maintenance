# Analytics & Reporting

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Command Center](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `27/05`

**⬅ ফিরে যান:** [Command Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Board Review Pack | `/m/executive-dashboard/board-reporting-pack` | 1 | 3 | 1 |
| 2 | Peer Benchmark Study | `/m/executive-dashboard/industry-benchmarking` | 1 | 3 | 1 |
| 3 | Revenue Performance Insight | `/m/executive-dashboard/revenue-analytics` | 1 | 3 | 1 |
| 4 | Cost Performance Insight | `/m/executive-dashboard/cost-analytics` | 1 | 3 | 1 |
| 5 | Board Report Subscriptions | `/m/executive-dashboard/report-subscriptions` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Board Review Pack

`/m/executive-dashboard/board-reporting-pack`

**ভিউ**
> 07 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Board pack sections | search |  | Search sections… |
| Pack | select |  | Board Pack — Aug 2026, Board Pack — Jul 2026, Audit Committee — Q3 2026 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Section, Drawn from, Author, Due, Pages, Status
- সেকশন: Board review pack
- বাটন/অ্যাকশন: Circulate, Add section, Section, Drawn from, Author, Due, Pages, Status

**ডায়ালগ: Add section** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Section* | text | ✔ |  |
| Pack | select |  | Select…, Board Pack — Aug 2026, Board Pack — Jul 2026, Audit Committee — Q3 2026 |
| Drawn from | select |  | Select…, KPI library, Financial statements, Order book, Production board, Risk register, Strategic initiatives, Manual n |
| Author | select |  | Select…, Nusrat Jahan, Tanvir Ahmed, Rashed Karim, Farhana Islam, Imran Hossain |
| Due | date |  |  |
| Pages | number |  |  |
| Status | select |  | Select…, Not started, Drafting, In review, Signed off |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: Name the section.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Peer Benchmark Study

`/m/executive-dashboard/industry-benchmarking`

**ভিউ**
> 10 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Peer benchmark register | search |  | Search indicators… |
| Study period | select |  | H1 2026, H2 2025, H1 2025 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Indicator, Us, Peer median, Best in class, Against peers
- সেকশন: Us, the peer median and the best in class, Benchmark register
- বাটন/অ্যাকশন: Add indicator, Indicator, Us, Peer median, Best in class, Against peers

**ডায়ালগ: Add indicator** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Indicator* | text | ✔ |  |
| Our figure | number |  |  |
| Peer median | number |  |  |
| Best in class | number |  |  |
| Unit | select |  | Select…, %, USD, Days, Ratio, Minutes |
| Direction | select |  | Select…, Higher is better, Lower is better |
| Source | select |  | Select…, BGMEA panel study, Buyer scorecard, Consultant benchmark, Public filings |
| Period | select |  | Select…, H1 2026, H2 2025, H1 2025 |
- খালি Save-এ মেসেজ: Name the indicator being benchmarked.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Revenue Performance Insight

`/m/executive-dashboard/revenue-analytics`

**ভিউ**
> 16 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Revenue performance by buyer and product | search |  | Search buyers and products… |
| Filter by season | select |  | All seasons, Winter 26, Summer 26, Winter 25, Summer 25 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Market, Season, Revenue, Quantity, Margin, Growth
- সেকশন: Revenue lines
- বাটন/অ্যাকশন: Add revenue line, Buyer, Market, Season, Revenue, Quantity, Margin, Growth

**ডায়ালগ: Add revenue line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | text | ✔ |  |
| Product family | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |
| Season | select |  | Select…, Winter 26, Summer 26, Winter 25, Summer 25 |
| Market | select |  | Select…, EU, UK, North America, Japan, Australia |
| Revenue | number |  |  |
| Quantity | number |  |  |
| Gross margin | number |  |  |
| Growth on last season | number |  |  |
- খালি Save-এ মেসেজ: Name the buyer.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Cost Performance Insight

`/m/executive-dashboard/cost-analytics`

**ভিউ**
> 17 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Cost performance by element | search |  | Search cost elements… |
| Filter by group | select |  | All groups, Material, Direct labour, Factory overhead, Administration, Selling & distribution, Finance |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Element, Period, Actual, Budget, % of sales, Movement, Owner
- সেকশন: Cost lines
- বাটন/অ্যাকশন: Add cost line, Element, Period, Actual, Budget, % of sales, Movement, Owner

**ডায়ালগ: Add cost line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Cost element* | text | ✔ |  |
| Group | select |  | Select…, Material, Direct labour, Factory overhead, Administration, Selling & distribution, Finance |
| Period | select |  | Select…, Aug 2026, Jul 2026, Jun 2026 |
| Actual | number |  |  |
| Budget | number |  |  |
| Share of sales | number |  |  |
| Movement on last period | number |  |  |
| Owner | select |  | Select…, Nusrat Jahan, Rashed Karim, Imran Hossain, Farhana Islam |
- খালি Save-এ মেসেজ: Name the cost element.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Board Report Subscriptions

`/m/executive-dashboard/report-subscriptions`

**ভিউ**
> 20 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Board report subscriptions | search |  | Search subscriptions… |
| Filter by schedule | select |  | All schedules, Daily 07:00, Weekly Monday, Monthly 1st, Quarterly, On close |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Report, Schedule, Format, Channel, Last sent, ACTIVE
- সেকশন: Subscriptions
- বাটন/অ্যাকশন: New subscription, Report, Schedule, Format, Channel, Last sent

**ডায়ালগ: New subscription** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Report* | text | ✔ |  |
| Recipient | select |  | Select…, Board members, Chief executive, Division heads, All managers, Audit committee, Buyer account managers |
| Schedule | select |  | Select…, Daily 07:00, Weekly Monday, Monthly 1st, Quarterly, On close |
| Format | select |  | Select…, PDF, Excel, In-app, PDF + Excel |
| Channel | select |  | Select…, Email, In-app, Email + In-app |
| Last sent | date |  |  |
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
