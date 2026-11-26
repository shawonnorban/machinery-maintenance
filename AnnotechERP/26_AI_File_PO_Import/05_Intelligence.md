# Intelligence

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/05`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | ERP Assistant Copilot | `/m/ai-file-import/ai-erp-copilot` | 1 | 3 | 0 |
| 2 | Document Query Assistant | `/m/ai-file-import/document-assistant` | 1 | 4 | 0 |
| 3 | Engine Control Board | `/m/ai-file-import/ai-control-dashboard` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### ERP Assistant Copilot

`/m/ai-file-import/ai-erp-copilot`

**ভিউ**
> 27 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Ask the copilot | text |  | Ask about orders, floors, stores or the ledger… |
| Search Copilot question register | search |  | Search questions… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Question, Asked, Sources, Outcome, Rated
- সেকশন: Copilot, What people are asking
- বাটন/অ্যাকশন: Which orders are late today?, How is sewing efficiency this week?, What yarn stock is sitting over 90 days?, Show me margin by buyer, Which imports failed this week?, Ask, Question, Asked, Sources, Outcome, Rated, Ask again

#### Document Query Assistant

`/m/ai-file-import/document-assistant`

**ভিউ**
> 28 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document | select |  | hm-po-winter26-b2.xlsx, zara-cable-knit-pack.pdf, uniqlo-lc-amendment.pdf, target-costsheet-q4.xlsx |
| Ask about this document | text |  | What quantity does it order? What did it change? |
| Search Fields extracted from the selected document | search |  | Search fields… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Field, Value, Confidence
- সেকশন: Document, Ask about this document, Fields read off this document
- বাটন/অ্যাকশন: Ask, Field, Value, Confidence

#### Engine Control Board

`/m/ai-file-import/ai-control-dashboard`

**ভিউ**
> 29 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Engine performance by family | search |  | Search families… |
| Filter by period | select |  | All periods, Aug 2026, Jul 2026, Jun 2026 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Family, Files, Rows, Accuracy, Corrected, Unattended
- সেকশন: Engine control board
- বাটন/অ্যাকশন: Add reading, Family, Files, Rows, Accuracy, Corrected, Unattended

**ডায়ালগ: Add reading** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Production rep |
| Period | select |  | Select…, Aug 2026, Jul 2026, Jun 2026 |
| Files read | number |  |  |
| Rows produced | number |  |  |
| Accuracy | number |  |  |
| Corrected by hand | number |  |  |
| Posted unattended | number |  |  |
| Average time | number |  |  |
- খালি Save-এ মেসেজ: Purchase order added.
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
