# Costing & Quotation

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/05`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Assisted Cost Estimation | `/m/crm-marketing/ai-auto-costing` | 1 | 3 | 1 |
| 2 | Costing Worksheet | `/m/crm-marketing/costing-sheet` | 1 | 3 | 1 |
| 3 | Cost Revision Control | `/m/crm-marketing/cost-revision` | 1 | 3 | 0 |
| 4 | Cost Approval Desk | `/m/crm-marketing/cost-approval` | 1 | 3 | 0 |
| 5 | Quotation Entry | `/m/crm-marketing/quotation` | 1 | 3 | 1 |
| 6 | Quotation History Trail | `/m/crm-marketing/quotation-history` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Assisted Cost Estimation

`/m/crm-marketing/ai-auto-costing`

**ভিউ**
> 30 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Assisted cost estimates | search |  | Search estimates… |
| Filter by stage | select |  | All stages, Drafted, Checked, Superseded, Discarded |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Estimated, Final, Variance, Confidence, Stage
- সেকশন: Assisted estimates
- বাটন/অ্যাকশন: New estimate, Style, Buyer, Estimated, Final, Variance, Confidence, Stage

**ডায়ালগ: New estimate** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Estimated FOB | number |  |  |
| Final costed FOB | number |  |  |
| Variance | number |  |  |
| Based on | select |  | Select…, BOM and SMV, Past order of the same style, Similar style, Buyer target only |
| Confidence | number |  |  |
| Drafted | date |  |  |
| Stage | select |  | Select…, Drafted, Checked, Superseded, Discarded |

#### Costing Worksheet

`/m/crm-marketing/costing-sheet`

**ভিউ**
> 31 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Costing worksheets | search |  | Search costings… |
| Filter by state | select |  | All states, Draft, Awaiting approval, Approved, Superseded |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Material, CM, Overhead, Margin, FOB, State
- সেকশন: Costing worksheets
- বাটন/অ্যাকশন: New costing, Style, Buyer, Material, CM, Overhead, Margin, FOB, State

**ডায়ালগ: New costing** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Material | number |  |  |
| Cut, make, trim | number |  |  |
| Overhead | number |  |  |
| Margin | number |  |  |
| FOB | number |  |  |
| Quantity | number |  |  |
| Costed | date |  |  |
| State | select |  | Select…, Draft, Awaiting approval, Approved, Superseded |

#### Cost Revision Control

`/m/crm-marketing/cost-revision`

**ভিউ**
> 32 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Cost revision control | search |  | Search revisions… |
| Filter by reason | select |  | All reasons, Yarn price, Quantity change, Specification change, Currency, Freight, Error corrected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, From, To, Change, Raised, State
- সেকশন: Cost revisions
- বাটন/অ্যাকশন: New revision, Style, Buyer, From, To, Change, Raised, State

#### Cost Approval Desk

`/m/crm-marketing/cost-approval`

**ভিউ**
> 33 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Cost approval desk | search |  | Search approvals… |
| Filter by state | select |  | All states, Waiting, Approved, Rejected, Withdrawn |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, FOB, Margin, Order value, Sent, State
- সেকশন: Cost approvals
- বাটন/অ্যাকশন: Send for approval, Style, Buyer, FOB, Margin, Order value, Sent, State

#### Quotation Entry

`/m/crm-marketing/quotation`

**ভিউ**
> 34 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Quotations issued | search |  | Search quotations… |
| Filter by state | select |  | All states, Issued, Accepted, Rejected, Lapsed, Withdrawn |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Price, Quantity, Incoterm, Valid to, State
- সেকশন: Quotations
- বাটন/অ্যাকশন: New quotation, Reference, Buyer, Price, Quantity, Incoterm, Valid to, State

**ডায়ালগ: New quotation** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Reference* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | select |  | Select…, ST-4471, ST-4482, ST-4509, ST-4520, ST-4533, ST-4544 |
| Price | number |  |  |
| Quantity | number |  |  |
| Incoterm | select |  | Select…, FOB, CIF, DDP, EXW |
| Lead time | number |  |  |
| Issued | date |  |  |
| Valid to | date |  |  |
| State | select |  | Select…, Issued, Accepted, Rejected, Lapsed, Withdrawn |

#### Quotation History Trail

`/m/crm-marketing/quotation-history`

**ভিউ**
> 35 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Quotation history trail | search |  | Search the trail… |
| Filter by answer | select |  | All answers, Accepted as quoted, Countered and settled, Countered and lost, No answer |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Ours, Counter, Settled, Answered, Answer
- সেকশন: Quotation history
- বাটন/অ্যাকশন: Add entry, Reference, Buyer, Ours, Counter, Settled, Answered, Answer

**ডায়ালগ: Add entry** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Reference* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | select |  | Select…, ST-4471, ST-4482, ST-4509, ST-4520, ST-4533, ST-4544 |
| Our price | number |  |  |
| Counter price | number |  |  |
| Settled at | number |  |  |
| Answered | date |  |  |
| Answer | select |  | Select…, Accepted as quoted, Countered and settled, Countered and lost, No answer |
- খালি Save-এ মেসেজ: A reference is required.
- invalid চিহ্নিত ফিল্ড: 1টা
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
