# Learning

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/03`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Buyer Layout Learning | `/m/ai-file-import/buyer-template-learning` | 1 | 3 | 0 |
| 2 | Layout Template Library | `/m/ai-file-import/template-library` | 1 | 3 | 1 |
| 3 | Template Match Selection | `/m/ai-file-import/template-selection` | 1 | 3 | 0 |
| 4 | Adaptive Field Mapping | `/m/ai-file-import/dynamic-ai-mapping` | 1 | 3 | 1 |
| 5 | Colour & Size Dictionary | `/m/ai-file-import/colour-size-vocabulary` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Buyer Layout Learning

`/m/ai-file-import/buyer-template-learning`

**ভিউ**
> 18 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Learned corrections | search |  | Search lessons… |
| Filter by buyer | select |  | All buyers, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Lesson, Buyer, Taught by, Taught, Applied, Gain, ACTIVE
- সেকশন: Buyer layout learning
- বাটন/অ্যাকশন: Record a lesson, Lesson, Buyer, Taught by, Taught, Applied, Gain

#### Layout Template Library

`/m/ai-file-import/template-library`

**ভিউ**
> 19 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Learned layout templates | search |  | Search templates… |
| Filter by family | select |  | All families, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Productio |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Template, Buyer, Ver, Fields, Hit rate, Last used, State
- সেকশন: Layout template library
- বাটন/অ্যাকশন: New template, Template, Buyer, Ver, Fields, Hit rate, Last used, State

**ডায়ালগ: New template** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Template* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Generic |
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Production rep |
| Version | number |  |  |
| Fields mapped | number |  |  |
| Hit rate | number |  |  |
| Last used | date |  |  |
| State | select |  | Select…, In use, Learning, Superseded, Retired |
- খালি Save-এ মেসেজ: Name the template.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Template Match Selection

`/m/ai-file-import/template-selection`

**ভিউ**
> 20 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Template match decisions | search |  | Search matches… |
| Filter by outcome | select |  | All outcomes, Accepted, Overridden, No template found |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Person chose, Match score, Decided by, Decided, Outcome
- সেকশন: Template matching
- বাটন/অ্যাকশন: Record a match, File, Person chose, Match score, Decided by, Decided, Outcome

#### Adaptive Field Mapping

`/m/ai-file-import/dynamic-ai-mapping`

**ভিউ**
> 21 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Adaptive field mappings | search |  | Search mappings… |
| Filter by buyer | select |  | All buyers, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Generic |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Column in the file, Buyer, Transform, Match, Used, CONFIRMED
- সেকশন: Field mappings
- বাটন/অ্যাকশন: New mapping, Column in the file, Buyer, Transform, Match, Used

**ডায়ালগ: New mapping** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Column in the file* | text | ✔ |  |
| ERP field* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Generic |
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Production rep |
| Transform | select |  | Select…, None, Dozens to pieces, Trim and upper-case, Parse date, Strip currency, Lookup master |
| Match score | number |  |  |
| Times used | number |  |  |
- খালি Save-এ মেসেজ: Name the source column. · Name the target field.
- invalid চিহ্নিত ফিল্ড: 2টা

#### Colour & Size Dictionary

`/m/ai-file-import/colour-size-vocabulary`

**ভিউ**
> 22 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Colour and size dictionary | search |  | Search vocabulary… |
| Filter by kind | select |  | All kinds, Colour, Size, Fit, Fabric |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer's word, Kind, Buyer, Used, Added by, VERIFIED
- সেকশন: Colour and size dictionary
- বাটন/অ্যাকশন: New entry, Buyer's word, Kind, Buyer, Used, Added by

**ডায়ালগ: New entry** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer's word* | text | ✔ |  |
| ERP code* | text | ✔ |  |
| Kind | select |  | Select…, Colour, Size, Fit, Fabric |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Any |
| Times used | number |  |  |
| Added by | select |  | Select…, Engine, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
- খালি Save-এ মেসেজ: Enter the buyer's word. · Enter the ERP code.
- invalid চিহ্নিত ফিল্ড: 2টা
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
