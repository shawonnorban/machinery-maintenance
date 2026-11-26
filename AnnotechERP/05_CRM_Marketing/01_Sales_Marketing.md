# Sales & Marketing

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/01`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Campaign Management | `/m/crm-marketing/marketing-campaign` | 1 | 3 | 1 |
| 2 | Lead Source Master | `/m/crm-marketing/marketing-source` | 1 | 3 | 1 |
| 3 | Lead Pipeline | `/m/crm-marketing/lead-management` | 1 | 3 | 1 |
| 4 | Opportunity Pipeline | `/m/crm-marketing/opportunity-management` | 1 | 3 | 1 |
| 5 | Buyer Acquisition Tracking | `/m/crm-marketing/customer-acquisition` | 1 | 3 | 0 |
| 6 | Sales Activity Log | `/m/crm-marketing/sales-activities` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Campaign Management

`/m/crm-marketing/marketing-campaign`

**ভিউ**
> 01 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Marketing campaigns | search |  | Search campaigns… |
| Filter by channel | select |  | All channels, Trade fair, Email outreach, Agent network, Buyer visit, Digital, Referral drive |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Campaign, Channel, Market, Budget, Leads, Ends, State
- সেকশন: Campaigns
- বাটন/অ্যাকশন: New campaign, Campaign, Channel, Market, Budget, Leads, Ends, State

**ডায়ালগ: New campaign** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Campaign* | text | ✔ |  |
| Channel | select |  | Select…, Trade fair, Email outreach, Agent network, Buyer visit, Digital, Referral drive |
| Market | select |  | Select…, EU, UK, North America, Japan, Australia, Middle East |
| Owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Starts | date |  |  |
| Ends | date |  |  |
| Budget | number |  |  |
| Leads generated | number |  |  |
| State | select |  | Select…, Planned, Running, Complete, Cancelled |

#### Lead Source Master

`/m/crm-marketing/marketing-source`

**ভিউ**
> 02 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Lead source master | search |  | Search sources… |
| Filter by kind | select |  | All kinds, Trade fair, Referral, Agent, Campaign, Cold outreach, Buyer portal |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Source, Kind, Leads, Conversion, Cost / lead, ACTIVE
- সেকশন: Lead sources
- বাটন/অ্যাকশন: New source, Source, Kind, Leads, Conversion, Cost / lead

**ডায়ালগ: New source** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Source* | text | ✔ |  |
| Kind | select |  | Select…, Trade fair, Referral, Agent, Campaign, Cold outreach, Buyer portal |
| Region | select |  | Select…, EU, UK, North America, Japan, Australia, Middle East, Global |
| Leads all time | number |  |  |
| Conversion | number |  |  |
| Cost per lead | number |  |  |
- খালি Save-এ মেসেজ: Name the source.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Lead Pipeline

`/m/crm-marketing/lead-management`

**ভিউ**
> 03 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Lead pipeline | search |  | Search leads… |
| Filter by stage | select |  | All stages, Captured, Contacted, Qualified, Converted, Dropped |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Company, Market, Source, Score, Owner, Stage
- সেকশন: Lead pipeline
- বাটন/অ্যাকশন: New lead, Company, Market, Source, Score, Owner, Stage

**ডায়ালগ: New lead** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | text | ✔ |  |
| Contact | text |  |  |
| Market | select |  | Select…, EU, UK, North America, Japan, Australia, Middle East |
| Source | select |  | Select…, Bangladesh Apparel Expo, Existing buyer referral, European sourcing agents, Winter outreach mails, LinkedIn pro |
| Stage | select |  | Select…, Captured, Contacted, Qualified, Converted, Dropped |
| Qualification score | number |  |  |
| Owner | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Captured | date |  |  |

#### Opportunity Pipeline

`/m/crm-marketing/opportunity-management`

**ভিউ**
> 04 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Opportunity pipeline | search |  | Search opportunities… |
| Filter by stage | select |  | All stages, Discovery, Costing, Quoted, Negotiating, Won, Lost |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Opportunity, Value, Probability, Closes, Owner, Stage
- সেকশন: Opportunity pipeline
- বাটন/অ্যাকশন: New opportunity, Opportunity, Value, Probability, Closes, Owner, Stage

**ডায়ালগ: New opportunity** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Opportunity* | text | ✔ |  |
| Company | text |  |  |
| Stage | select |  | Select…, Discovery, Costing, Quoted, Negotiating, Won, Lost |
| Value | number |  |  |
| Probability | number |  |  |
| Expected close | date |  |  |
| Owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Product | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |

#### Buyer Acquisition Tracking

`/m/crm-marketing/customer-acquisition`

**ভিউ**
> 05 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer acquisition tracking | search |  | Search acquisitions… |
| Filter by source | select |  | All sources, Bangladesh Apparel Expo, Existing buyer referral, European sourcing agents, Winter outreach mails, LinkedIn |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Market, Won, Cost, First order, Payback
- সেকশন: Acquisitions
- বাটন/অ্যাকশন: Record acquisition, Buyer, Market, Won, Cost, First order, Payback

#### Sales Activity Log

`/m/crm-marketing/sales-activities`

**ভিউ**
> 06 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sales activity log | search |  | Search activities… |
| Filter by kind | select |  | All kinds, Call, Email, Meeting, Visit, Sample sent, Quotation sent |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Activity, Kind, Owner, Outcome
- সেকশন: Activity log
- বাটন/অ্যাকশন: Log activity, Date, Activity, Kind, Owner, Outcome
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
