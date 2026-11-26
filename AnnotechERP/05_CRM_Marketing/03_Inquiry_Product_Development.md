# Inquiry & Product Development

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [CRM & Marketing](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `05/03`

**⬅ ফিরে যান:** [CRM & Marketing](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Buyer Inquiry Register | `/m/crm-marketing/customer-buyer-inquiry` | 1 | 3 | 0 |
| 2 | Development Request Desk | `/m/crm-marketing/development-request` | 1 | 3 | 1 |
| 3 | Assisted Tech Pack | `/m/crm-marketing/ai-tech-pack` | 1 | 3 | 1 |
| 4 | Style & Product Master | `/m/crm-marketing/product-style-master` | 1 | 3 | 1 |
| 5 | Material Requirement Sheet | `/m/crm-marketing/bom-material-requirement` | 1 | 3 | 1 |
| 6 | Counter Sample Testing | `/m/crm-marketing/counter-sample-testing` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Buyer Inquiry Register

`/m/crm-marketing/customer-buyer-inquiry`

**ভিউ**
> 17 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer inquiry register | search |  | Search inquiries… |
| Filter by state | select |  | All states, Open, In development, Costed, Quoted, Converted, Closed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Quantity, Target, Respond by, Owner, State
- সেকশন: Buyer inquiries
- বাটন/অ্যাকশন: New inquiry, Reference, Buyer, Quantity, Target, Respond by, Owner, State

#### Development Request Desk

`/m/crm-marketing/development-request`

**ভিউ**
> 18 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Development request desk | search |  | Search requests… |
| Filter by stage | select |  | All stages, Raised, Accepted, In development, Delivered, Withdrawn |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Product, Due, Developer, Stage
- সেকশন: Development requests
- বাটন/অ্যাকশন: New request, Reference, Buyer, Product, Due, Developer, Stage

**ডায়ালগ: New request** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Reference* | text | ✔ |  |
| Against inquiry | text |  |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Product | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |
| Stage | select |  | Select…, Raised, Accepted, In development, Delivered, Withdrawn |
| Raised | date |  |  |
| Due | date |  |  |
| Developer | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Brief | textarea |  |  |

#### Assisted Tech Pack

`/m/crm-marketing/ai-tech-pack`

**ভিউ**
> 19 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Assisted tech pack drafts | search |  | Search drafts… |
| Filter by stage | select |  | All stages, Drafted, Under correction, Issued, Discarded |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Buyer, Sections, Corrections, Confidence, Stage
- সেকশন: Assisted tech packs
- বাটন/অ্যাকশন: New draft, Style, Buyer, Sections, Corrections, Confidence, Stage

**ডায়ালগ: New draft** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Drafted from | select |  | Select…, Buyer reference garment, Previous season pack, Inquiry sheet only, Buyer sketch |
| Sections drafted | number |  |  |
| Corrections made | number |  |  |
| Draft confidence | number |  |  |
| Drafted | date |  |  |
| Reviewer | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Stage | select |  | Select…, Drafted, Under correction, Issued, Discarded |

#### Style & Product Master

`/m/crm-marketing/product-style-master`

**ভিউ**
> 20 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Style and product master | search |  | Search styles… |
| Filter by state | select |  | All states, Development, Approved, In production, Retired |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Description, Fabric, GSM, Season, State
- সেকশন: Style master
- বাটন/অ্যাকশন: New style, Style, Description, Fabric, GSM, Season, State

**ডায়ালগ: New style** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style code* | text | ✔ |  |
| Description* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Product | select |  | Select…, T-shirt, Polo, Fleece, Sweater, Trouser, Legging, Jacket |
| Fabric | select |  | Select…, Single jersey, Pique, Brushed fleece, Rib, Interlock, Twill, French terry |
| GSM | number |  |  |
| Season | select |  | Select…, Winter 26, Summer 26, Winter 27, Summer 27 |
| Size range | select |  | Select…, XS–XXL, S–XL, XS–XL, One size, Kids 2–14 |
| State | select |  | Select…, Development, Approved, In production, Retired |
- খালি Save-এ মেসেজ: A style code is required. · Describe the style.
- invalid চিহ্নিত ফিল্ড: 2টা

#### Material Requirement Sheet

`/m/crm-marketing/bom-material-requirement`

**ভিউ**
> 21 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Material requirement sheet | search |  | Search materials… |
| Filter by style | select |  | All styles, ST-4471, ST-4482, ST-4509, ST-4520, ST-4533, ST-4544 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Material, Style, Per dozen, Wastage, Total, Source
- সেকশন: Material requirement
- বাটন/অ্যাকশন: New line, Material, Style, Per dozen, Wastage, Total, Source

**ডায়ালগ: New line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Material* | text | ✔ |  |
| Style | select |  | Select…, ST-4471, ST-4482, ST-4509, ST-4520, ST-4533, ST-4544 |
| Kind | select |  | Select…, Fabric, Yarn, Trim, Accessory, Packing |
| Consumption per dozen | number |  |  |
| Unit | select |  | Select…, kg, m, pcs, yds, sets |
| Wastage | number |  |  |
| Order quantity | number |  |  |
| Total required | number |  |  |
| Source | select |  | Select…, In house, Local supplier, Imported, Buyer nominated |
- খালি Save-এ মেসেজ: Name the material.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Counter Sample Testing

`/m/crm-marketing/counter-sample-testing`

**ভিউ**
> 22 of 48 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Counter sample testing | search |  | Search tests… |
| Filter by verdict | select |  | All verdicts, Pass, Marginal, Fail |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style, Test, Buyer, Ours, Reference, Tested, Verdict
- সেকশন: Counter sample tests
- বাটন/অ্যাকশন: New test, Style, Test, Buyer, Ours, Reference, Tested, Verdict

**ডায়ালগ: New test** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Style* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Test | select |  | Select…, Shrinkage, Colour fastness, Pilling, Bursting strength, GSM, Spirality |
| Our result | text |  |  |
| Reference result | text |  |  |
| Standard | select |  | Select…, AATCC 135, ISO 105, ASTM D3512, ISO 13938, ISO 3801, Buyer standard |
| Tested | date |  |  |
| Verdict | select |  | Select…, Pass, Marginal, Fail |
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
