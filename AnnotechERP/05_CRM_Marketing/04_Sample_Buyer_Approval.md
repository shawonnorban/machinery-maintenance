# Sample & Buyer Approval

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/04`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sample Development Tracker | `/m/crm-marketing/sample-development` | 1 | 3 | 1 |
| 2 | Sample Submission Log | `/m/crm-marketing/sample-submission` | 1 | 3 | 1 |
| 3 | Buyer Comment Sheet | `/m/crm-marketing/buyer-comments` | 1 | 3 | 0 |
| 4 | Sample Revision Control | `/m/crm-marketing/sample-revision` | 1 | 3 | 1 |
| 5 | Buyer Approval Desk | `/m/crm-marketing/buyer-approval` | 1 | 3 | 0 |
| 6 | Approval History Trail | `/m/crm-marketing/approval-history` | 1 | 3 | 0 |
| 7 | Sample Progress Tracking | `/m/crm-marketing/sample-tracking` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sample Development Tracker

`/m/crm-marketing/sample-development`

**ভিউ**
> 23 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample development tracker | search |  | Search samples… |
| Filter by stage | select |  | All stages, Pattern, Cutting, Sewing, Finishing, Ready, Sent |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Pieces, Due, Developer, Stage
- সেকশন: Sample development
- বাটন/অ্যাকশন: New sample, Style, Buyer, Pieces, Due, Developer, Stage

**ডায়ালগ: New sample** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Sample type | select |  | Select…, Proto, Fit, Size set, PP, Shipment, Photo |
| Stage | select |  | Select…, Pattern, Cutting, Sewing, Finishing, Ready, Sent |
| Pieces | number |  |  |
| Started | date |  |  |
| Due | date |  |  |
| Developer | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |

#### Sample Submission Log

`/m/crm-marketing/sample-submission`

**ভিউ**
> 24 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample submission log | search |  | Search submissions… |
| Filter by state | select |  | All states, Sent, Delivered, Received by buyer, Lost |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Type, Ver, Courier, Sent, State
- সেকশন: Submissions
- বাটন/অ্যাকশন: New submission, Style, Buyer, Type, Ver, Courier, Sent, State

**ডায়ালগ: New submission** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Sample type | select |  | Select…, Proto, Fit, Size set, PP, Shipment, Photo |
| Version | number |  |  |
| Pieces sent | number |  |  |
| Courier | select |  | Select…, DHL, FedEx, Aramex, Hand carried |
| Tracking number | text |  |  |
| Sent | date |  |  |
| State | select |  | Select…, Sent, Delivered, Received by buyer, Lost |

#### Buyer Comment Sheet

`/m/crm-marketing/buyer-comments`

**ভিউ**
> 25 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer comment sheet | search |  | Search comments… |
| Filter by area | select |  | All areas, Measurement, Construction, Fabric, Colour, Trim, Packing |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Comment, Area, Severity, Received, Actioned by, State
- সেকশন: Buyer comments
- বাটন/অ্যাকশন: Record comment, Comment, Area, Severity, Received, Actioned by, State

#### Sample Revision Control

`/m/crm-marketing/sample-revision`

**ভিউ**
> 26 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample revision control | search |  | Search revisions… |
| Filter by stage | select |  | All stages, Raised, In the room, Ready, Sent, Cancelled |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Version, Comments, Due, Stage
- সেকশন: Revisions
- বাটন/অ্যাকশন: New revision, Style, Buyer, Version, Comments, Due, Stage

**ডায়ালগ: New revision** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| From version | number |  |  |
| To version | number |  |  |
| Comments answered | number |  |  |
| Raised | date |  |  |
| Due | date |  |  |
| Stage | select |  | Select…, Raised, In the room, Ready, Sent, Cancelled |
| Changes | textarea |  |  |

#### Buyer Approval Desk

`/m/crm-marketing/buyer-approval`

**ভিউ**
> 27 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer approval desk | search |  | Search approvals… |
| Filter by outcome | select |  | All outcomes, Approved, Approved with conditions, Rejected, Pending |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Type, Ver, Approved by, Date, Outcome
- সেকশন: Approvals
- বাটন/অ্যাকশন: Record approval, Style, Buyer, Type, Ver, Approved by, Date, Outcome

#### Approval History Trail

`/m/crm-marketing/approval-history`

**ভিউ**
> 28 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Approval history trail | search |  | Search the trail… |
| Filter by event | select |  | All events, Submitted, Commented, Revised, Approved, Rejected, Withdrawn |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Style, Event, Ver, By, Buyer
- সেকশন: Approval history
- বাটন/অ্যাকশন: Add entry, Date, Style, Event, Ver, By, Buyer

#### Sample Progress Tracking

`/m/crm-marketing/sample-tracking`

**ভিউ**
> 29 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample progress tracking | search |  | Search samples… |
| Filter by status | select |  | All statuses, On time, At risk, Late, Closed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Phase, Promised, Days late, Owner, Status
- সেকশন: Sample tracking
- বাটন/অ্যাকশন: Track a sample, Style, Buyer, Phase, Promised, Days late, Owner, Status
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
