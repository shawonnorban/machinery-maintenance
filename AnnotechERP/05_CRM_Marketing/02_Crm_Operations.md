# Crm Operations

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/02`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Relationship Dashboard | `/m/crm-marketing/crm-dashboard` | 1 | 3 | 1 |
| 2 | Buyer Account Register | `/m/crm-marketing/buyer-accounts` | 1 | 3 | 1 |
| 3 | Contact Directory | `/m/crm-marketing/contacts` | 1 | 3 | 1 |
| 4 | Buyer Master | `/m/crm-marketing/buyer` | 1 | 3 | 1 |
| 5 | Buyer Segment Grouping | `/m/crm-marketing/buyer-segmentation` | 1 | 3 | 1 |
| 6 | Follow-up Scheduler | `/m/crm-marketing/follow-up-management` | 1 | 3 | 1 |
| 7 | Visit Plan & Log | `/m/crm-marketing/visit-planning` | 1 | 3 | 0 |
| 8 | Complaint & Claim Desk | `/m/crm-marketing/complaint-claims` | 1 | 3 | 0 |
| 9 | Competitor Watch | `/m/crm-marketing/competitor-tracking` | 1 | 3 | 1 |
| 10 | Win & Loss Review | `/m/crm-marketing/win-loss-analysis` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Relationship Dashboard

`/m/crm-marketing/crm-dashboard`

**ভিউ**
> 07 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Relationship health board | search |  | Search accounts… |
| Filter by health | select |  | All health bands, Strong, Steady, Watch, At risk |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Account, Owner, Open, Pipeline, Due, Last contact, Health
- সেকশন: Relationship board
- বাটন/অ্যাকশন: Add account line, Account, Owner, Open, Pipeline, Due, Last contact, Health

**ডায়ালগ: Add account line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Account | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Open opportunities | number |  |  |
| Pipeline value | number |  |  |
| Follow-ups due | number |  |  |
| Last contact | date |  |  |
| Health | select |  | Select…, Strong, Steady, Watch, At risk |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: H&M added.

#### Buyer Account Register

`/m/crm-marketing/buyer-accounts`

**ভিউ**
> 08 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer account register | search |  | Search accounts… |
| Filter by tier | select |  | All tiers, Strategic, Core, Developing, Watch |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Account, Country, Tier, Terms, Annual, Owner, ACTIVE
- সেকশন: Buyer accounts
- বাটন/অ্যাকশন: New account, Account, Country, Tier, Terms, Annual, Owner

**ডায়ালগ: New account** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Account* | text | ✔ |  |
| Parent group | text |  |  |
| Country | select |  | Select…, Sweden, United Kingdom, Spain, United States, Japan, Australia, Germany, United Arab Emirates |
| Tier | select |  | Select…, Strategic, Core, Developing, Watch |
| Payment terms | select |  | Select…, L/C at sight, L/C 60 days, TT 30 days, TT 60 days, Open account 90 days |
| Account owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Annual value | number |  |  |
| Trading since | date |  |  |
- খালি Save-এ মেসেজ: Name the account.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Contact Directory

`/m/crm-marketing/contacts`

**ভিউ**
> 09 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Contact directory | search |  | Search contacts… |
| Filter by account | select |  | All accounts, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Contact, Account, Role, Authority, Last contact, PRIMARY
- সেকশন: Contacts
- বাটন/অ্যাকশন: New contact, Contact, Account, Role, Authority, Last contact

**ডায়ালগ: New contact** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Name* | text | ✔ |  |
| Account | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Role | select |  | Select…, Merchandiser, Sourcing head, Quality manager, Designer, Logistics, Finance |
| Authority | select |  | Select…, Decision maker, Influencer, Gatekeeper, User |
| Email | text |  |  |
| Preferred channel | select |  | Select…, Email, Phone, Portal, WhatsApp, In person |
| Last contact | date |  |  |
- খালি Save-এ মেসেজ: Name the contact.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Buyer Master

`/m/crm-marketing/buyer`

**ভিউ**
> 10 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer master | search |  | Search buyers… |
| Filter by state | select |  | All states, Active, On hold, Closed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Buyer, Country, Currency, Incoterm, Open orders, State
- সেকশন: Buyer master
- বাটন/অ্যাকশন: New buyer, Code, Buyer, Country, Currency, Incoterm, Open orders, State

**ডায়ালগ: New buyer** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Buyer name* | text | ✔ |  |
| Account | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Country | select |  | Select…, Sweden, United Kingdom, Spain, United States, Japan, Australia, Germany, United Arab Emirates |
| Currency | select |  | Select…, USD, EUR, GBP, JPY |
| Default incoterm | select |  | Select…, FOB, CIF, DDP, EXW |
| Open orders | number |  |  |
| State | select |  | Select…, Active, On hold, Closed |
- খালি Save-এ মেসেজ: A code is required. · Name the buyer.
- invalid চিহ্নিত ফিল্ড: 2টা

#### Buyer Segment Grouping

`/m/crm-marketing/buyer-segmentation`

**ভিউ**
> 11 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer segment grouping | search |  | Search segments… |
| Filter by basis | select |  | All bases, Value, Volume, Margin, Behaviour, Product mix |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Segment, Basis, Buyers, Annual value, Review, ACTIVE
- সেকশন: Segments
- বাটন/অ্যাকশন: New segment, Segment, Basis, Buyers, Annual value, Review

**ডায়ালগ: New segment** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Segment* | text | ✔ |  |
| Basis | select |  | Select…, Value, Volume, Margin, Behaviour, Product mix |
| Buyers in it | number |  |  |
| Annual value | number |  |  |
| Service model | select |  | Select…, Named account manager, Shared desk, Portal self-service, Agent managed |
| Review cycle | select |  | Select…, Monthly, Quarterly, Half yearly, Yearly |
- খালি Save-এ মেসেজ: Name the segment.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Follow-up Scheduler

`/m/crm-marketing/follow-up-management`

**ভিউ**
> 12 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Follow-up scheduler | search |  | Search follow-ups… |
| Filter by state | select |  | All states, Due, Overdue, Done, Cancelled |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Owed, Owner, Due, Reminder, Priority, State
- সেকশন: Follow-ups
- বাটন/অ্যাকশন: New follow-up, Owed, Owner, Due, Reminder, Priority, State

**ডায়ালগ: New follow-up** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Owed* | text | ✔ |  |
| Account | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Due | date |  |  |
| Reminder | select |  | Select…, Same day, One day before, Three days before, One week before, None |
| Priority | select |  | Select…, High, Normal, Low |
| State | select |  | Select…, Due, Overdue, Done, Cancelled |

#### Visit Plan & Log

`/m/crm-marketing/visit-planning`

**ভিউ**
> 13 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Visit plan and log | search |  | Search visits… |
| Filter by kind | select |  | All kinds, Buyer visits us, We visit the buyer, Audit, Trade fair meeting |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Visit, Account, Kind, Where, State
- সেকশন: Visits
- বাটন/অ্যাকশন: Plan a visit, Date, Visit, Account, Kind, Where, State

#### Complaint & Claim Desk

`/m/crm-marketing/complaint-claims`

**ভিউ**
> 14 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Complaint and claim desk | search |  | Search complaints… |
| Filter by state | select |  | All states, Open, Investigating, Offer made, Settled, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Account, Category, Claimed, Settled, Raised, State
- সেকশন: Complaints and claims
- বাটন/অ্যাকশন: Log complaint, Reference, Account, Category, Claimed, Settled, Raised, State

#### Competitor Watch

`/m/crm-marketing/competitor-tracking`

**ভিউ**
> 15 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Competitor watch | search |  | Search competitors… |
| Filter by account | select |  | All accounts, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Competitor, Product, Their price, Our price, Lead time, Source
- সেকশন: Competitor watch
- বাটন/অ্যাকশন: New note, Competitor, Product, Their price, Our price, Lead time, Source

**ডায়ালগ: New note** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Competitor* | text | ✔ |  |
| Account | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Product | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |
| Their price | number |  |  |
| Our price | number |  |  |
| Their lead time | number |  |  |
| Source | select |  | Select…, Buyer told us, Agent, Trade press, Inferred, Rumour |
| Noted on | date |  |  |
- খালি Save-এ মেসেজ: Name the competitor.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Win & Loss Review

`/m/crm-marketing/win-loss-analysis`

**ভিউ**
> 16 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Win and loss review | search |  | Search reviews… |
| Filter by outcome | select |  | All outcomes, Won, Lost, No decision |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Opportunity, Product, Value, Reason, Lost to, Outcome
- সেকশন: Win and loss
- বাটন/অ্যাকশন: New review, Opportunity, Product, Value, Reason, Lost to, Outcome

**ডায়ালগ: New review** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Opportunity* | text | ✔ |  |
| Account | text |  |  |
| Product | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |
| Value | number |  |  |
| Outcome | select |  | Select…, Won, Lost, No decision |
| Reason | select |  | Select…, Price, Lead time, Capacity, Quality record, Relationship, Compliance, Product capability |
| Lost to | text |  |  |
| Decided | date |  |  |
| Lesson | textarea |  |  |
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
