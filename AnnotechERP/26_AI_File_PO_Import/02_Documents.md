# Documents

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/02`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Inquiry Document Intake | `/m/ai-file-import/inquiry-files` | 1 | 3 | 1 |
| 2 | Purchase Order Reading | `/m/ai-file-import/po-order-processing` | 1 | 3 | 1 |
| 3 | Tech Pack Reading | `/m/ai-file-import/tech-pack` | 1 | 3 | 1 |
| 4 | Artwork File Reading | `/m/ai-file-import/artwork` | 1 | 3 | 1 |
| 5 | Bill of Materials Reading | `/m/ai-file-import/bom` | 1 | 3 | 1 |
| 6 | Process Routing Reading | `/m/ai-file-import/routing` | 1 | 3 | 1 |
| 7 | Work Order Reading | `/m/ai-file-import/work-order` | 1 | 3 | 1 |
| 8 | Cost Sheet Reading | `/m/ai-file-import/cost-sheet` | 1 | 3 | 1 |
| 9 | Commercial Document Reading | `/m/ai-file-import/commercial-documents` | 1 | 3 | 1 |
| 10 | Production Report Reading | `/m/ai-file-import/production-report` | 1 | 3 | 1 |
| 11 | Quality Document Reading | `/m/ai-file-import/quality-documents` | 1 | 3 | 1 |
| 12 | Image & CAD Reading | `/m/ai-file-import/image-cad` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Inquiry Document Intake

`/m/ai-file-import/inquiry-files`

**ভিউ**
> 06 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Inquiry document intake | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Inquiry document intake
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Inquiry reference | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Style lines found | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Buyer Inquiry Register, Development Request Desk, Style & Product Master |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Purchase Order Reading

`/m/ai-file-import/po-order-processing`

**ভিউ**
> 07 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Purchase order reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Purchase order reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Order number | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Order lines found | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Order Confirmation Sheet, Merchandising order book, Production Release Order |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Tech Pack Reading

`/m/ai-file-import/tech-pack`

**ভিউ**
> 08 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Tech pack reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Tech pack reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Measurement points | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Style & Product Master, Material Requirement Sheet, Counter Sample Testing |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Artwork File Reading

`/m/ai-file-import/artwork`

**ভিউ**
> 09 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Artwork file reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Artwork file reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Placements found | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Style & Product Master, Sample Development Tracker, Print & embroidery |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Bill of Materials Reading

`/m/ai-file-import/bom`

**ভিউ**
> 10 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Bill of materials reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Bill of materials reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Material lines | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Material Requirement Sheet, Fabric Requirement, Costing Worksheet |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Process Routing Reading

`/m/ai-file-import/routing`

**ভিউ**
> 11 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Process routing reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Process routing reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Operations found | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Work Study routes, Line Program, Item Specification |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Work Order Reading

`/m/ai-file-import/work-order`

**ভিউ**
> 12 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Work order reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Work order reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Work order | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Quantity lines | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Line Program, Cutting work order, Knitting production |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Cost Sheet Reading

`/m/ai-file-import/cost-sheet`

**ভিউ**
> 13 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Cost sheet reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Cost sheet reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Style | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Cost lines | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Costing Worksheet, Cost Revision Control, Quotation Entry |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Commercial Document Reading

`/m/ai-file-import/commercial-documents`

**ভিউ**
> 14 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Commercial document reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Commercial document reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Document reference | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Clauses read | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Export L/C register, Commercial Invoice, Bill of Exchange |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Production Report Reading

`/m/ai-file-import/production-report`

**ভিউ**
> 15 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Production report reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Production report reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Section and date | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Hour rows read | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Hourly production, Line efficiency board, Downtime register |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Quality Document Reading

`/m/ai-file-import/quality-documents`

**ভিউ**
> 16 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Quality document reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Quality document reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State

**ডায়ালগ: Add a file** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| File* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Inspection or report | text |  |  |
| Received | date |  |  |
| Pages or sheets | number |  |  |
| Result lines | number |  |  |
| Confidence | number |  |  |
| Posts to | select |  | Select…, Inspection register, Lab test results, Complaint & Claim Desk |
| Reviewer | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| State | select |  | Select…, Posted, Awaiting review, Corrected, Rejected |
- খালি Save-এ মেসেজ: Name the file.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Image & CAD Reading

`/m/ai-file-import/image-cad`

**ভিউ**
> 17 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Image and CAD reading | search |  | Search files… |
| Filter by state | select |  | All states, Posted, Awaiting review, Corrected, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Buyer, Fields, Confidence, Posts to, Received, State
- সেকশন: Image and CAD reading
- বাটন/অ্যাকশন: Add a file, File, Buyer, Fields, Confidence, Posts to, Received, State
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
