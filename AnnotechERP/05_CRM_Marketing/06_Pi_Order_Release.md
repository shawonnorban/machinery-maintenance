# Pi & Order Release

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/06`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Proforma Invoice Entry | `/m/crm-marketing/proforma-invoice` | 1 | 3 | 1 |
| 2 | Order Confirmation Sheet | `/m/crm-marketing/order-confirmation` | 1 | 3 | 1 |
| 3 | Order Handover Note | `/m/crm-marketing/order-handover` | 1 | 3 | 1 |
| 4 | Production Release Order | `/m/crm-marketing/production-release` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Proforma Invoice Entry

`/m/crm-marketing/proforma-invoice`

**ভিউ**
> 36 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Proforma invoices | search |  | Search proformas… |
| Filter by state | select |  | All states, Raised, Sent, Accepted, L/C opened, Cancelled |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Value, Quantity, Terms, Amends, State
- সেকশন: Proforma invoices
- বাটন/অ্যাকশন: New proforma, Reference, Buyer, Value, Quantity, Terms, Amends, State

**ডায়ালগ: New proforma** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Reference* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Against quotation | text |  |  |
| Value | number |  |  |
| Quantity | number |  |  |
| Payment terms | select |  | Select…, L/C at sight, L/C 60 days, TT 30 days, TT 60 days |
| Raised | date |  |  |
| Amendments | number |  |  |
| State | select |  | Select…, Raised, Sent, Accepted, L/C opened, Cancelled |

#### Order Confirmation Sheet

`/m/crm-marketing/order-confirmation`

**ভিউ**
> 37 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order confirmation sheets | search |  | Search confirmations… |
| Filter by state | select |  | All states, Confirmed, In production, Shipped, Cancelled |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order, Buyer, Quantity, Price, Value, Ship date, State
- সেকশন: Order confirmations
- বাটন/অ্যাকশন: New confirmation, Order, Buyer, Quantity, Price, Value, Ship date, State

**ডায়ালগ: New confirmation** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order number* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | select |  | Select…, ST-4471, ST-4482, ST-4509, ST-4520, ST-4533, ST-4544 |
| Quantity | number |  |  |
| Price | number |  |  |
| Value | number |  |  |
| Ship date | date |  |  |
| Confirmed | date |  |  |
| State | select |  | Select…, Confirmed, In production, Shipped, Cancelled |
- খালি Save-এ মেসেজ: An order number is required.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Order Handover Note

`/m/crm-marketing/order-handover`

**ভিউ**
> 38 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order handover notes | search |  | Search handovers… |
| Filter by state | select |  | All states, Sent, Accepted, Returned, Recalled |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order, Buyer, Attached, Handed over, Accepted by, State
- সেকশন: Handovers
- বাটন/অ্যাকশন: New handover, Order, Buyer, Attached, Handed over, Accepted by, State

**ডায়ালগ: New handover** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Handed to | select |  | Select…, Merchandising, Planning, Merchandising and Planning |
| Attachments | number |  |  |
| Handed over | date |  |  |
| Accepted by | select |  | Select…, Not yet accepted, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Sent, Accepted, Returned, Recalled |

#### Production Release Order

`/m/crm-marketing/production-release`

**ভিউ**
> 39 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Production release orders | search |  | Search releases… |
| Filter by state | select |  | All states, Blocked, Ready, Released, Revoked |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order, Buyer, Quantity, SAMPLE, FABRIC, TRIMS, State
- সেকশন: Production releases
- বাটন/অ্যাকশন: New release, Order, Buyer, Quantity, State

**ডায়ালগ: New release** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Quantity | number |  |  |
| Released | date |  |  |
| Released by | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Blocked, Ready, Released, Revoked |
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
