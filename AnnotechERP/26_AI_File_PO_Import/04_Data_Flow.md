# Data & Flow

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/04`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Unified Data Hub | `/m/ai-file-import/central-data-hub` | 1 | 3 | 0 |
| 2 | Digital Style Twin | `/m/ai-file-import/digital-product-twin` | 1 | 3 | 1 |
| 3 | Automated ERP Posting | `/m/ai-file-import/erp-auto-data-flow` | 1 | 3 | 1 |
| 4 | Import Job Register | `/m/ai-file-import/all-imports` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Unified Data Hub

`/m/ai-file-import/central-data-hub`

**ভিউ**
> 23 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Unified data hub | search |  | Search staged records… |
| Filter by state | select |  | All states, Staged, Validated, Released, Held, Discarded |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Record, Type, Buyer, Confidence, Staged, State
- সেকশন: Staged records
- বাটন/অ্যাকশন: Stage a record, Record, Type, Buyer, Confidence, Staged, State

#### Digital Style Twin

`/m/ai-file-import/digital-product-twin`

**ভিউ**
> 24 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Digital style twins | search |  | Search styles… |
| Filter by state | select |  | All states, Complete, Assembling, Gaps, Superseded |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Description, Families, Completeness, Last file, State
- সেকশন: Digital style twins
- বাটন/অ্যাকশন: New twin, Style, Description, Families, Completeness, Last file, State

**ডায়ালগ: New twin** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Description | text |  |  |
| Document families held | number |  |  |
| Completeness | number |  |  |
| Last file added | date |  |  |
| State | select |  | Select…, Complete, Assembling, Gaps, Superseded |
- খালি Save-এ মেসেজ: Name the style.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Automated ERP Posting

`/m/ai-file-import/erp-auto-data-flow`

**ভিউ**
> 25 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Automated ERP posting routes | search |  | Search routes… |
| Filter by module | select |  | All modules, Merchandising, Planning, Commercial, Cutting Floor, Assembly Floor, Central Stores, Accounting |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Route, Target, Posted, Failed, Last posted, ENABLED
- সেকশন: Posting routes
- বাটন/অ্যাকশন: New route, Route, Target, Posted, Failed, Last posted

**ডায়ালগ: New route** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Route* | text | ✔ |  |
| Record type | select |  | Select…, Order header, Order line, Style, BOM line, Cost line, L/C header, Production row |
| Target module | select |  | Select…, Merchandising, Planning, Commercial, Cutting Floor, Assembly Floor, Central Stores, Accounting |
| Posted | number |  |  |
| Failed | number |  |  |
| Last posted | date |  |  |
- খালি Save-এ মেসেজ: Name the route.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Import Job Register

`/m/ai-file-import/all-imports`

**ভিউ**
> 26 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Import job register | search |  | Search jobs… |
| Filter by status | select |  | All statuses, Complete, Partial, Failed, Running |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Arrived by, Rows, Duration, Started, Status
- সেকশন: Import jobs
- বাটন/অ্যাকশন: Add a job, File, Buyer, Arrived by, Rows, Duration, Started, Status

**ডায়ালগ: Add a job** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Production rep |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Not identified |
| Arrived by | select |  | Select…, Email attachment, Portal upload, Watched folder, SFTP drop, Manual upload |
| Started | date |  |  |
| Rows produced | number |  |  |
| Duration | number |  |  |
| Status | select |  | Select…, Complete, Partial, Failed, Running |
- খালি Save-এ মেসেজ: Name the file.
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
