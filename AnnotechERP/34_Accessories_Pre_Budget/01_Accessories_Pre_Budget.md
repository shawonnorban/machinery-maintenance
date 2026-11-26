# Accessories Pre-Budget

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Accessories Pre-Budget](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `34/01`

**⬅ ফিরে যান:** [Accessories Pre-Budget](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Norban ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Accessories Budget Pending | `/accessories-inventory/accessories-budget-pending` | 1 | 1 | 0 |
| 2 | BOM List | `/accessories-inventory/bom` | 1 | 2 | 0 |
| 3 | Accessories Report | `/accessories-inventory/report` | 1 | 7 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Accessories Budget Pending

`/accessories-inventory/accessories-budget-pending`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Pending, Partial, Accessories Budget Created, All |
- গ্রিড কলাম: SL, PRE-BUDGET NO, REFERENCE, BUYER, CONTRACT NO, SENT BY, SENT AT, STATUS, ACTION

#### BOM List

`/accessories-inventory/bom`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No, Style | text |  |  |
| Status | select |  | All Status, Pending, Approved |
- গ্রিড কলাম: Sl, Pre-Budget No, Brand, Job No, Style Number, Artwork, Order Qty, Accessories Value, Cost Per Piece, Acc Cost (%), Status, Created By, Created At, Action
- সেকশন: Artwork Preview
- বাটন/অ্যাকশন: Create New Budget, Filter, Download

#### Accessories Report

`/accessories-inventory/report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BUYER All | select |  | All |
| BUYER All | select |  | All |
| BUYER All | select |  | All |
| BUYER All | select |  | All |
| BUYER All | select |  | All |
| BUYER All | date |  |  |
| BUYER All | date |  |  |
- বাটন/অ্যাকশন: Apply, By Style 0, By Item 0, By Supplier 0, By Buyer 0, Style × Item 0
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
