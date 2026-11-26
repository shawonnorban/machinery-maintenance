# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন (Brickland-এর এখনকার ২৯টা স্ক্রিনই চালু; ধাপ D-তে যোগ হয়েছে Item Type Master, Nature of Work Master আর Style & Order Upload)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `06/01`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dyeing Process Loss Norms | `/m/merchandising/dyeing-process-loss-chart` | 1 | 0 | 0 |
| 2 | Country & Origin Master | `/m/merchandising/country-origin-master` | 2 | 7 | 1 |
| 3 | Buyer Office Directory | `/m/merchandising/buyer-office-master` | 3 | 10 | 1 |
| 4 | Buyer Account Master | `/m/merchandising/buyer-master` | 3 | 15 | 1 |
| 5 | Buyer Account Permission | `/m/merchandising/buyer-ac-permission` | 2 | 0 | 0 |
| 6 | Buyer Sample Mapping | `/m/merchandising/buyer-sample-mapping` | 1 | 2 | 0 |
| 7 | Buyer Test Lab Mapping | `/m/merchandising/buyer-test-institute-mapping` | 1 | 2 | 1 |
| 8 | Country Configuration | `/m/merchandising/country-setup` | 2 | 9 | 1 |
| 9 | Buyer-Wise Trims & Accessories | `/m/merchandising/buyer-wise-trim-accessories` | 3 | 12 | 1 |
| 10 | Buyer-Wise Supplier Configuration | `/m/merchandising/buyer-supplier-config` | 2 | 3 | 1 |
| 11 | Marketing Buyer Permission | `/m/merchandising/buyer-ac-permission-mkt` | 2 | 0 | 0 |
| 12 | Process Loss Quantity Setup | `/m/merchandising/process-loss-qty-config` | 1 | 0 | 0 |
| 13 | Cutting Process Loss Setup | `/m/merchandising/cutting-process-loss-config` | 1 | 0 | 0 |
| 14 | Colour Directory | `/m/merchandising/colour-master` | 2 | 9 | 1 |
| 15 | Size Directory | `/m/merchandising/size-master` | 2 | 10 | 1 |
| 16 | Garment Part Master | `/m/merchandising/garment-part` | 2 | 9 | 1 |
| 17 | Service Type Setup | `/m/merchandising/service-type-setup` | 2 | 9 | 1 |
| 18 | Fabric Type Master | `/m/merchandising/fabric-type-entry` | 3 | 14 | 1 |
| 19 | Fiber Master | `/m/merchandising/fiber-master` | 2 | 7 | 1 |
| 20 | Fiber Composition | `/m/merchandising/fiber-composition` | 2 | 5 | 1 |
| 21 | Inquiry Rate Chart | `/m/merchandising/rate-chart-for-inquiry` | 4 | 9 | 1 |
| 22 | Trims & Accessories Spec Setup | `/m/merchandising/trim-accessories-spec-cfg` | 3 | 7 | 1 |
| 23 | TNA Task Rights | `/m/merchandising/tna-task-permission` | 2 | 2 | 0 |
| 24 | TNA Template Setup | `/m/merchandising/template-for-tna` | 3 | 19 | 1 |
| 25 | Task & Action Register | `/m/merchandising/task-action-list` | 3 | 14 | 2 |
| 26 | Style & Order Upload | `/m/merchandising/upload-style-and-order` | 2 | 2 | 0 |
| 27 | GSM-Wise Yarn Count Setup | `/m/merchandising/gsm-wise-count-conf` | 2 | 10 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dyeing Process Loss Norms

`/m/merchandising/dyeing-process-loss-chart`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Save band

#### Country & Origin Master

`/m/merchandising/country-origin-master`

**ট্যাব: Records 28**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Countries of origin with their code, name, region and active state | search |  | Search 28 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Active?, Region, Status, ACTION
- বাটন/অ্যাকশন: New country, Export, Code, Name, Active?, Region, Status, Edit

**ট্যাব: New country**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Code* | text | ✔ | 1000 |
| Name* | text | ✔ | Bangladesh |
| Name (Bangla) | text |  |  |
| Region | select |  | Select…, Asian, EU, Americas, Africa, Oceania, Middle East |
| Active? | checkbox |  |  |
- সেকশন: New country
- বাটন/অ্যাকশন: New country, Reset, Save country

**ডায়ালগ: New country**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Name (Bangla) | text |  |  |
| Region | select |  | Select…, Asian, EU, Americas, Africa, Oceania, Middle East |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Code is required.

#### Buyer Office Directory

`/m/merchandising/buyer-office-master`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer offices with their type, address, country and active state | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Office Name, Office Type, Address, Country, Buyer Acc, Active?, Status, ACTION
- বাটন/অ্যাকশন: Buyer Acc, New office, Export, Office Name, Office Type, Address, Country, Active?, Status, Edit

**ট্যাব: Buyer Acc**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select office* | select | ✔ | LPP S.A., TEX EBO |
- সেকশন: Buyer Acc
- বাটন/অ্যাকশন: Buyer Acc, New office

**ট্যাব: New office**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Office Name* | text | ✔ | LNDBPL |
| Office Type* | select | ✔ | Select…, Liason Office, Head Office, Buying House, Agent |
| Address | textarea |  |  |
| Contact Person | text |  |  |
| Email | text |  |  |
| Phone | text |  |  |
| Active? | checkbox |  |  |
- সেকশন: New office
- বাটন/অ্যাকশন: Buyer Acc, New office, Select…, Reset, Save office

**ডায়ালগ: New office**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Office Name* | text | ✔ |  |
| Office Type* | select | ✔ | Select…, Liason Office, Head Office, Buying House, Agent |
| Address | textarea |  |  |
| Contact Person | text |  |  |
| Email | text |  |  |
| Phone | text |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Office Name is required.

#### Buyer Account Master

`/m/merchandising/buyer-master`

**ট্যাব: Records 1**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyers with their code, short name, address, display order and negotiatin | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Code, Buyer Name, Short Name, Address, Display Order, Bank Name, Offices, Status, ACTION
- বাটন/অ্যাকশন: Link to Office, New buyer, Export, Buyer Code, Buyer Name, Short Name, Address, Display Order, Bank Name, Offices, Status, Edit

**ট্যাব: Link to Office**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select buyer* | select | ✔ | LPP (LPP) |
- সেকশন: Link to Office
- বাটন/অ্যাকশন: Link to Office, New buyer

**ট্যাব: New buyer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer Code* | text | ✔ | GEB |
| Buyer Name (En)* | text | ✔ | GHFMBH + CO. KG |
| Buyer Name (Bn) | text |  |  |
| Buyer Short Name* | text | ✔ | G HUBER |
| Buyer Profile | textarea |  |  |
| Address For PI* | textarea | ✔ |  |
| Contact Person | text |  |  |
| Registration Period (From)* | date | ✔ |  |
| Registration Period (To) | date |  |  |
| Display Order | number |  | 20 |
| Is Active ? | checkbox |  |  |
| Link To Office* | select | ✔ | Select…, LPP S.A., TEX EBO |
- সেকশন: New buyer
- বাটন/অ্যাকশন: Link to Office, New buyer, Select…, Reset, Save buyer

**ডায়ালগ: New buyer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer Code* | text | ✔ |  |
| Buyer Name (En)* | text | ✔ |  |
| Buyer Name (Bn) | text |  |  |
| Buyer Short Name* | text | ✔ |  |
| Buyer Profile | textarea |  |  |
| Address For PI* | textarea | ✔ |  |
| Contact Person | text |  |  |
| Registration Period (From)* | date | ✔ |  |
| Registration Period (To) | date |  |  |
| Display Order | number |  |  |
| Is Active ? | checkbox |  |  |
| Link To Office* | select | ✔ | Select…, LPP S.A., TEX EBO |
- খালি Save-এ মেসেজ: Link To Office is required.

#### Buyer Account Permission

`/m/merchandising/buyer-ac-permission`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Buyer Account Permission

**ট্যাব: Buyer Account Permission**
- সেকশন: Buyer Account Permission
- বাটন/অ্যাকশন: Buyer Account Permission, -- Select user --

#### Buyer Sample Mapping

`/m/merchandising/buyer-sample-mapping`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| buyer | select |  | LPP (LPP) |
| Search buyers | search |  | Search 1 buyers… |
- সেকশন: Buyer Wise Sample Mapping, Search By, MAPPED BUYER LIST, SAMPLE TYPE LIST
- বাটন/অ্যাকশন: Load, LPP (LPP)

#### Buyer Test Lab Mapping

`/m/merchandising/buyer-test-institute-mapping`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| buyer | select |  | LPP (LPP) |
| Search buyers | search |  | Search 1 buyers… |
- সেকশন: Buyer Wise Institute Mapping, Search By, MAPPED BUYER LIST, TEST INSTITUTE LIST
- বাটন/অ্যাকশন: Load, New Institute, LPP (LPP)

**ডায়ালগ: New Institute** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Name[E]* | text | ✔ |  |
| Name[B] | text |  |  |
| Address | textarea |  |  |
- খালি Save-এ মেসেজ: Name[E] is required.

#### Country Configuration

`/m/merchandising/country-setup`

**ট্যাব: Records 28**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Country settlement setup: region, currency, dialling code and shipping el | search |  | Search 28 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Region, Currency, Dial Code, Ship To?, Active?, Status, ACTION
- বাটন/অ্যাকশন: Add setup, Export, Code, Name, Region, Currency, Dial Code, Ship To?, Active?, Status, Edit

**ট্যাব: Add setup**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Code* | text | ✔ | 2001 |
| Name* | text | ✔ |  |
| Region | select |  | Select…, Asian, EU, Americas, Africa, Oceania, Middle East |
| Currency | text |  | EUR |
| Dial Code | text |  | +880 |
| Shipping Destination? | checkbox |  |  |
| Active? | checkbox |  |  |
- সেকশন: New country setup
- বাটন/অ্যাকশন: Add setup, Reset

**ডায়ালগ: Add setup**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Region | select |  | Select…, Asian, EU, Americas, Africa, Oceania, Middle East |
| Currency | text |  |  |
| Dial Code | text |  |  |
| Shipping Destination? | checkbox |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Code is required.

#### Buyer-Wise Trims & Accessories

`/m/merchandising/buyer-wise-trim-accessories`

**ট্যাব: Records 10**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Trims and accessories with their category, specification, material, colou | search |  | Search 10 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Item Code, Item Name, Category, Specification, Material, Color, Size, UOM, Approved By, Status, ACTION
- বাটন/অ্যাকশন: Trim & Accessories, New trim item, Export, Item Code, Item Name, Category, Specification, Material, Color, Size, UOM, Approved By, Status, Edit

**ট্যাব: Trim & Accessories**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyer accounts | search |  | Search… |
- সেকশন: Buyer A/C, Trim & Accessories
- বাটন/অ্যাকশন: Trim & Accessories, New trim item

**ট্যাব: New trim item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Item Name* | text | ✔ | Main Label |
| Item Code* | text | ✔ | LBL-MAIN |
| Specification | text |  | Woven, damask, 50 x 20 mm |
| Material | text |  | Polyester |
| Color | text |  | Black |
| Size | text |  | 50 x 20 mm |
| UOM | text |  |  |
| Active? | checkbox |  |  |
| Description | textarea |  |  |
- সেকশন: New trim item
- বাটন/অ্যাকশন: Trim & Accessories, New trim item, Select…, Reset, Save trim item

**ডায়ালগ: New trim item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Item Name* | text | ✔ |  |
| Item Code* | text | ✔ |  |
| Specification | text |  |  |
| Material | text |  |  |
| Color | text |  |  |
| Size | text |  |  |
| UOM | text |  |  |
| Active? | checkbox |  |  |
| Description | textarea |  |  |
- খালি Save-এ মেসেজ: Item Code is required.

#### Buyer-Wise Supplier Configuration

`/m/merchandising/buyer-supplier-config`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New supplier configuration

**ট্যাব: New supplier configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Active? | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New supplier configuration
- বাটন/অ্যাকশন: New supplier configuration, Select…, Reset, Save supplier configuration

**ডায়ালগ: New supplier configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Active? | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Buyer is required.

#### Marketing Buyer Permission

`/m/merchandising/buyer-ac-permission-mkt`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Buyer Account Permission (Marketing)

**ট্যাব: Buyer Account Permission (Marketing)**
- সেকশন: Buyer Account Permission (Marketing)
- বাটন/অ্যাকশন: Buyer Account Permission (Marketing), -- Select user --

#### Process Loss Quantity Setup

`/m/merchandising/process-loss-qty-config`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Save Configuration

#### Cutting Process Loss Setup

`/m/merchandising/cutting-process-loss-config`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Save Configuration

#### Colour Directory

`/m/merchandising/colour-master`

**ট্যাব: Records 124**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shade names with their type, the Pantone reference they are matched again | search |  | Search 124 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Colour Type, Colour Code, Colour Name, Panton #, Version #, Status, ACTION
- বাটন/অ্যাকশন: New colour, Export, Colour Type, Colour Code, Colour Name, Panton #, Version #, Status, Edit

**ট্যাব: New colour**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Colour Code | text |  |  |
| Colour Type* | select | ✔ | Select…, Solid, Melange, Yarn Dyed, Aop, Stripe |
| Colour Name(En)* | text | ✔ |  |
| Panton Number | text |  | 18-3905 TCX |
| Version# | text |  |  |
| Swatch Provided ? | checkbox |  |  |
| Is Active ? | checkbox |  |  |
- সেকশন: New colour
- বাটন/অ্যাকশন: New colour, Select…, Reset, Save colour

**ডায়ালগ: New colour**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Colour Code | text |  |  |
| Colour Type* | select | ✔ | Select…, Solid, Melange, Yarn Dyed, Aop, Stripe |
| Colour Name(En)* | text | ✔ |  |
| Panton Number | text |  |  |
| Version# | text |  |  |
| Swatch Provided ? | checkbox |  |  |
| Is Active ? | checkbox |  |  |
- খালি Save-এ মেসেজ: Colour Name(En) is required.

#### Size Directory

`/m/merchandising/size-master`

**ট্যাব: Records 14**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sizes with their size group, comparison group, code, display order and ac | search |  | Search 14 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Size ID, Size Group, Comp Size Group, Code, Size Name(En), Is Active?, Display Order, Display Order (Comp), Status, ACTION
- বাটন/অ্যাকশন: New size, Export, Size ID, Size Group, Comp Size Group, Code, Size Name(En), Is Active?, Display Order, Display Order (Comp), Status, Edit

**ট্যাব: New size**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Size Group | select |  | Select…, XS, S, M, L & Like wise, Numeric, Age Group |
| Size Code | text |  | XL |
| Size Name (En)* | text | ✔ | XL |
| Size Name (Bn) | text |  |  |
| Comp Size Group | select |  | Select…, XS, S, M, L & Like wise, Numeric, Age Group |
| Display Order | number |  | 10 |
| Display Order (Comp) | number |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New size
- বাটন/অ্যাকশন: New size, Reset, Save size

**ডায়ালগ: New size**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Size Group | select |  | Select…, XS, S, M, L & Like wise, Numeric, Age Group |
| Size Code | text |  |  |
| Size Name (En)* | text | ✔ |  |
| Size Name (Bn) | text |  |  |
| Comp Size Group | select |  | Select…, XS, S, M, L & Like wise, Numeric, Age Group |
| Display Order | number |  |  |
| Display Order (Comp) | number |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Size Name (En) is required.

#### Garment Part Master

`/m/merchandising/garment-part`

**ট্যাব: Records 15**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Garment parts with their fabric group and whether they are circular knit, | search |  | Search 15 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Fabric Group, Circular, Body?, Process Group?, Status, ACTION
- বাটন/অ্যাকশন: New part, Export, Code, Name, Fabric Group, Circular, Body?, Process Group?, Status, Edit

**ট্যাব: New part**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Code* | text | ✔ | Armhole |
| Name* | text | ✔ | Arm Hole Band |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| Circular Knit? | checkbox |  |  |
| Process Group? A grouping row rather than a cuttable part. | checkbox |  |  |
| GMT Body? | checkbox |  |  |
| Active? | checkbox |  |  |
- সেকশন: New part
- বাটন/অ্যাকশন: New part, Reset, Save part

**ডায়ালগ: New part**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| Circular Knit? | checkbox |  |  |
| Process Group? A grouping row rather than a cuttable part. | checkbox |  |  |
| GMT Body? | checkbox |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Code is required.

#### Service Type Setup

`/m/merchandising/service-type-setup`

**ট্যাব: Records 29**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Service For | select |  | All, AOP Type, Y/D Type, Print Type, Embroidery Type, GMT Wash Type |
| Search Service types with the Services-desk column they are offered in, their co | search |  | Search 29 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Service For, Code, Name, Remarks, Order, Status, ACTION
- বাটন/অ্যাকশন: New service type, Clear, Export, Service For, Code, Name, Remarks, Order, Status, Edit

**ট্যাব: New service type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Service For* | select | ✔ | Select…, AOP Type, Y/D Type, Print Type, Embroidery Type, GMT Wash Type |
| Code* | text | ✔ | PRT-RUB |
| Name* | text | ✔ | Rubber |
| Remarks | text |  |  |
| Order | number |  |  |
| Active? | checkbox |  |  |
- সেকশন: New service type
- বাটন/অ্যাকশন: New service type, Reset, Save service type

**ডায়ালগ: New service type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Service For* | select | ✔ | Select…, AOP Type, Y/D Type, Print Type, Embroidery Type, GMT Wash Type |
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Remarks | text |  |  |
| Order | number |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Code is required.

#### Fabric Type Master

`/m/merchandising/fabric-type-entry`

**ট্যাব: Records 9**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Fabric constructions with their group, category, circular/flat, rib and b | search |  | Search 9 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type Code, Type Name, Short Name, Flat/Cir?, Rib?, Base?, GARM PART, Category, Group, Order, Status, ACTION
- বাটন/অ্যাকশন: GARM Part List, New fabric type, Export, Type Code, Type Name, Short Name, Flat/Cir?, Rib?, Base?, GARM PART, Category, Group, Order, Status, Edit

**ট্যাব: GARM Part List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select fabric type* | select | ✔ | Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Interlock (INT |
- সেকশন: GARM Part List
- বাটন/অ্যাকশন: GARM Part List, New fabric type

**ট্যাব: New fabric type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fabric Type Code* | text | ✔ | SJ |
| Fabric Type Name* | text | ✔ | Single Jersey |
| Short Name* | text | ✔ | S/J |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| Fabric Category | select |  | Select…, Knit, Sweater, Woven |
| Circular / Flat* | select | ✔ | Select…, Circular, Flat |
| Is Base Type? A base construction other types are derived from. | checkbox |  |  |
| Parent Type | select |  | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Is RIB? | checkbox |  |  |
| Is Active? | checkbox |  |  |
| Display Order* | number | ✔ | 10 |
- সেকশন: New fabric type
- বাটন/অ্যাকশন: GARM Part List, New fabric type, Reset, Save fabric type

**ডায়ালগ: New fabric type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fabric Type Code* | text | ✔ |  |
| Fabric Type Name* | text | ✔ |  |
| Short Name* | text | ✔ |  |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| Fabric Category | select |  | Select…, Knit, Sweater, Woven |
| Circular / Flat* | select | ✔ | Select…, Circular, Flat |
| Is Base Type? A base construction other types are derived from. | checkbox |  |  |
| Parent Type | select |  | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Is RIB? | checkbox |  |  |
| Is Active? | checkbox |  |  |
| Display Order* | number | ✔ |  |
- খালি Save-এ মেসেজ: Fabric Type Code is required.

#### Fiber Master

`/m/merchandising/fiber-master`

**ট্যাব: Records 12**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Fiber types with their code, care-label short name and active state | search |  | Search 12 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Fiber Code, Fiber Name, Short Name, Used By, Order, Status, ACTION
- বাটন/অ্যাকশন: New fiber, Export, Fiber Code, Fiber Name, Short Name, Used By, Order, Status, Edit

**ট্যাব: New fiber**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fiber Code* | text | ✔ | CO |
| Fiber Name* | text | ✔ | Cotton |
| Short Name | text |  | CO |
| Display Order* | number | ✔ | 10 |
| Is Active? | checkbox |  |  |
- সেকশন: New fiber
- বাটন/অ্যাকশন: New fiber, Reset, Save fiber

**ডায়ালগ: New fiber**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fiber Code* | text | ✔ |  |
| Fiber Name* | text | ✔ |  |
| Short Name | text |  |  |
| Display Order* | number | ✔ |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Fiber Code is required.

#### Fiber Composition

`/m/merchandising/fiber-composition`

**ট্যাব: Records 19**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Fabric fiber compositions with their generated description and per-fiber  | search |  | Search 19 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Comp. Code, Composition Desc., Fiber Types, Total %, Status, ACTION
- বাটন/অ্যাকশন: New Fiber Composition, Export, Comp. Code, Composition Desc., Fiber Types, Total %, Status, Edit

**ট্যাব: New Fiber Composition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Comp. Code | text |  | CVC6040 |
| Is Active? | checkbox |  |  |
| Fiber Type | text |  | Search... |
- সেকশন: New Fiber Composition, Composition, Composition Desc.
- বাটন/অ্যাকশন: New Fiber Composition, Records, Save, Cancel

**ডায়ালগ: New Fiber Composition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Comp. Code | text |  |  |
| Is Active? | checkbox |  |  |
| Fiber Type | text |  |  |
- খালি Save-এ মেসেজ: Select at least one fiber type from the Fiber Master.

#### Inquiry Rate Chart

`/m/merchandising/rate-chart-for-inquiry`

**ট্যাব: Records 28**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Master rate lines by group, with how many buyer offices have a rate in fo | search |  | Search 28 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: RateGrp, RateDesc, UOM, Offices Quoted, Avg. Current Rate, Order, Status, ACTION
- বাটন/অ্যাকশন: Rate Chart, Rate History, New rate line, Export, RateGrp, RateDesc, UOM, Offices Quoted, Avg. Current Rate, Order, Status, Edit

**ট্যাব: Rate Chart**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search offices | search |  | Search… |
- সেকশন: Buyer Office, Rate Chart
- বাটন/অ্যাকশন: Rate Chart, Rate History, New rate line, LPP S.A. 0, TEX EBO 0

**ট্যাব: Rate History**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search offices | search |  | Search… |
- সেকশন: Buyer Office, Rate History
- বাটন/অ্যাকশন: Rate Chart, Rate History, New rate line, LPP S.A. 0, TEX EBO 0

**ট্যাব: New rate line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Rate Group* | select | ✔ | Select…, Yarn Rate Chart, Knitting Rate Chart, Y/D Rate Chart, AOP Type, Dyeing Rate Chart, Dyeing Finishing Rate Chart |
| Rate Description* | text | ✔ | 100% Cotton Any Count (Combed) |
| UOM | text |  | Kg |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- সেকশন: New rate line
- বাটন/অ্যাকশন: Rate Chart, Rate History, New rate line, Reset, Save rate line

**ডায়ালগ: New rate line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Rate Group* | select | ✔ | Select…, Yarn Rate Chart, Knitting Rate Chart, Y/D Rate Chart, AOP Type, Dyeing Rate Chart, Dyeing Finishing Rate Chart |
| Rate Description* | text | ✔ |  |
| UOM | text |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Rate Group is required.

#### Trims & Accessories Spec Setup

`/m/merchandising/trim-accessories-spec-cfg`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Spec. Configuration, New spec template

**ট্যাব: Spec. Configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing spec template | select |  |  |
| Template Code* | text | ✔ | SPEC-LBL |
| Template Name* | text | ✔ |  |
| Active? | checkbox |  |  |
- সেকশন: Spec. Configuration, Item wise Controls Specification, Control Specification
- বাটন/অ্যাকশন: Spec. Configuration, New spec template, Add item rule, Add control, Clear, Save spec template

**ট্যাব: New spec template**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Template Code* | text | ✔ | SPEC-LBL |
| Template Name* | text | ✔ | STOCKMANN - Care Label |
| Active? | checkbox |  |  |
- সেকশন: New spec template
- বাটন/অ্যাকশন: Spec. Configuration, New spec template, Reset, Save spec template

**ডায়ালগ: New spec template**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Template Code* | text | ✔ |  |
| Template Name* | text | ✔ |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Template Code is required.

#### TNA Task Rights

`/m/merchandising/tna-task-permission`

**ট্যাব: Records 39**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Users and the create, read, update and delete rights they hold over each  | search |  | Search 39 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: User, Task Name, Task Group, C R U D, ACTION
- বাটন/অ্যাকশন: T&A Task Permission, Export, User, Task Name, Task Group, C R U D

**ট্যাব: T&A Task Permission**
- সেকশন: T&A Task Permission
- বাটন/অ্যাকশন: T&A Task Permission, -- Select user --

#### TNA Template Setup

`/m/merchandising/template-for-tna`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Time & action templates with their buyer, order type, garment type and le | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Template Code, Buyer, Order Type, Gmt. Type, Lead Time From, Lead Time To, Remarks, ACTION
- বাটন/অ্যাকশন: Template Tasks, Add New, Export, Template Code, Buyer, Order Type, Gmt. Type, Lead Time From, Lead Time To, Remarks, Edit, Copy

**ট্যাব: Template Tasks**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search templates | search |  | Search… |
- সেকশন: Templates, Calendar
- বাটন/অ্যাকশন: Template Tasks, Add New, TNGEN18 Development · 18–18 days 0, TNGEN12 Development · 12–12 days 0, TNGEN10 Development · 10–10 days 0, TNGEN120 New · 120–140 days 0, TNGEN90 New · 90–119 days 13, TNGEN75 New · 75–89 days 11, TNGEN60 New · 60–74 days 0, TNGEN30 New · 30–44 days 0

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Template Code* | text | ✔ | TNGEN00 |
| General | radio |  |  |
| Buyer Specific | radio |  |  |
| New | radio |  |  |
| Repeat | radio |  |  |
| Development | radio |  |  |
| Projection | radio |  |  |
| Basic | radio |  |  |
| Semi-Critical | radio |  |  |
| Critical | radio |  |  |
| Lead Time* | number | ✔ |  |
| To* | number | ✔ |  |
| Std Dys* | number | ✔ |  |
| Before Swing* | number | ✔ |  |
| After Swing* | number | ✔ |  |
| Description | textarea |  |  |
- সেকশন: New T&A template
- বাটন/অ্যাকশন: Template Tasks, Add New, Select…, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Template Code* | text | ✔ |  |
| General | radio |  |  |
| Buyer Specific | radio |  |  |
| New | radio |  |  |
| Repeat | radio |  |  |
| Development | radio |  |  |
| Projection | radio |  |  |
| Basic | radio |  |  |
| Semi-Critical | radio |  |  |
| Critical | radio |  |  |
| Lead Time* | number | ✔ |  |
| To* | number | ✔ |  |
| Std Dys* | number | ✔ |  |
| Before Swing* | number | ✔ |  |
| After Swing* | number | ✔ |  |
| Description | textarea |  |  |
- খালি Save-এ মেসেজ: Lead Time is required.

#### Task & Action Register

`/m/merchandising/task-action-list`

**ট্যাব: Records 44**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Supply-chain tasks with their group, order, Bangla caption and start/end/ | search |  | Search 44 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: TaskGroup, TaskOrder, Task Name(En), Task Name(Bn), Source Module, Active?, Sample?, Start?, End?, Production?, Status, ACTION
- বাটন/অ্যাকশন: Task Groups, New task, New task group, Export, TaskGroup, TaskOrder, Task Name(En), Task Name(Bn), Source Module, Active?, Sample?, Start?, End?, Production?, Status, Edit

**ট্যাব: Task Groups**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search groups | search |  | Search… |
- সেকশন: Task Groups, Tasks
- বাটন/অ্যাকশন: Task Groups, New task, New task group, General Process GENPROC 5, Sample SAMPLE 0, Bulk Production BULKPROD 8, Shipment SHIPMENT 0

**ট্যাব: New task**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Task Group | select |  | Select…, General Process, Sample, Bulk Production, Shipment |
| Task Code* | text | ✔ | TSK-078 |
| Task Order #* | number | ✔ |  |
| Task Name (En)* | text | ✔ |  |
| Task Name (Bn) | text |  |  |
| Offset Days | number |  |  |
| Sample Related? | checkbox |  |  |
| Is Start? | checkbox |  |  |
| Is End? | checkbox |  |  |
| Is Production? | checkbox |  |  |
| Is Active? | checkbox |  |  |
- সেকশন: New task
- বাটন/অ্যাকশন: Task Groups, New task, New task group, Select…, Reset, Save task

**ডায়ালগ: New task**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Task Group | select |  | Select…, General Process, Sample, Bulk Production, Shipment |
| Task Code* | text | ✔ |  |
| Task Order #* | number | ✔ |  |
| Task Name (En)* | text | ✔ |  |
| Task Name (Bn) | text |  |  |
| Offset Days | number |  |  |
| Sample Related? | checkbox |  |  |
| Is Start? | checkbox |  |  |
| Is End? | checkbox |  |  |
| Is Production? | checkbox |  |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Task Code is required.

**ডায়ালগ: New task group** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Task Group Name (En)* | text | ✔ |  |
| Task Group Name (Bn) | text |  |  |
| Is Active ? | checkbox |  |  |
- খালি Save-এ মেসেজ: Task Group Name (En) is required.

#### Style & Order Upload

`/m/merchandising/upload-style-and-order`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Upload

**ট্যাব: Upload**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Style and Order Detail | radio |  |  |
| Attach File | file |  |  |
- সেকশন: Upload, File Type
- বাটন/অ্যাকশন: Upload, Verify File Data

#### GSM-Wise Yarn Count Setup

`/m/merchandising/gsm-wise-count-conf`

**ট্যাব: Records 3**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Recommended yarn counts by construction, fibre template, gauge and GSM ba | search |  | Search 3 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Construction, Fiber Combination Template, Gauge, GSM Min, GSM Max, Top, Binding, Back, ACTION
- বাটন/অ্যাকশন: New count configuration, Export, Construction, Fiber Combination Template, Gauge, GSM Min, GSM Max, Top, Binding, Back, Edit

**ট্যাব: New count configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fab Type* | select | ✔ | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Template | select |  | Select…, 100% Cotton, Any Fiber Blend with Lycra, CVC Blend |
| Gauge* | text | ✔ | 24G |
| GSM Min* | number | ✔ |  |
| GSM Max* | number | ✔ |  |
| Count — Top | text |  | 30/1 Combed |
| Count — Binding | text |  |  |
| Count — Back | text |  |  |
- সেকশন: New count configuration
- বাটন/অ্যাকশন: New count configuration, Reset, Save count configuration

**ডায়ালগ: New count configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Fab Type* | select | ✔ | Select…, Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Inter |
| Template | select |  | Select…, 100% Cotton, Any Fiber Blend with Lycra, CVC Blend |
| Gauge* | text | ✔ |  |
| GSM Min* | number | ✔ |  |
| GSM Max* | number | ✔ |  |
| Count — Top | text |  |  |
| Count — Binding | text |  |  |
| Count — Back | text |  |  |
- খালি Save-এ মেসেজ: GSM Min is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- এর কোনো মাস্টারই আগে ছিল না। `production_styles`-এ buyer আর colour শুধু লেখা হিসেবে আছে। এই মাস্টারগুলো তৈরি হলে Order/Style নতুন করে তৈরির সময় সেখানে select বসবে।
- স্ক্রিন-কিট: `ResourceDefinition` (`/erp/{resource}`), `RecordsGrid`, `EntryForm`। Brickland-এর প্রায় সব মাস্টার "Records | New" ধাঁচের।
- Access Control-এর Lookup Table-এ একটা শেয়ার্ড `FABRIC_TYPE` lookup আছে। এখানকার Fabric Type Master আলাদা, কোম্পানির নিজের, পূর্ণ ফিল্ডসহ।

**উপ-ধাপ (২৭টা স্ক্রিন):**
- **A — রেফারেন্স (১০টা):** Country & Origin Master, Country Configuration, Colour Directory, Size Directory, Garment Part Master, Service Type Setup, Fabric Type Master (GARM Part List-সহ), Fiber Master, Fiber Composition, GSM-Wise Yarn Count Setup।
- **B — বায়ার (৮টা):** Buyer Office Directory, Buyer Account Master, Buyer Account Permission, Buyer Sample Mapping, Buyer Test Lab Mapping, Buyer-Wise Trims & Accessories, Buyer-Wise Supplier Configuration, Marketing Buyer Permission।
- **C — নর্ম আর TNA (৯টা):** Dyeing Process Loss Norms, Process Loss Quantity Setup, Cutting Process Loss Setup, Inquiry Rate Chart, Trims & Accessories Spec Setup, TNA Task Rights, TNA Template Setup, Task & Action Register, Style & Order Upload।

## ৪. ডাটা মডেল (Entity ও Field)

**ধাপ A:** নতুন মডিউল `app/Modules/Merchandise`, সব টেবিল কোম্পানির নিজের (ULID, `company_id`)।

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `origin_countries` | code, name, name_bn, region (ASIAN/EU/AMERICAS/AFRICA/OCEANIA/MIDDLE_EAST), active | code |
| `country_configs` | code, name, region, currency (৩ অক্ষর), dial_code, is_ship_to, active | code |
| `colours` | code, colour_type (SOLID/MELANGE/YARN_DYED/AOP/STRIPE), name, pantone_no, version_no, swatch_provided, active | name + type |
| `sizes` | size_group (LETTER/NUMERIC/AGE), code, name, name_bn, comp_size_group, display_order, comp_display_order, active | name + group |
| `garment_parts` | code, name, fabric_group (BODY/COLLAR/LINING/RIB), is_circular, is_process_group, is_body, active | code |
| `service_types` | service_for (AOP/YARN_DYEING/PRINT/EMBROIDERY/GMT_WASH), code, name, remarks, sort_order, active | code + service |
| `fabric_types` | code, name, short_name, fabric_group, category (KNIT/SWEATER/WOVEN), construction (CIRCULAR/FLAT), is_base, parent_id (একটা base type), is_rib, sort_order, active | code |
| `fabric_type_garment_parts` | fabric_type_id, garment_part_id | জোড়া |
| `fibers` | code, name, short_name, sort_order, active | code |
| `fiber_compositions` + `fiber_composition_items` | code, description (নিজে থেকে তৈরি), active · fiber_id, percent | code; একটা fiber একবারই |
| `yarn_count_configs` | fabric_type_id, template (COTTON_100/LYCRA_BLEND/CVC_BLEND), gauge, gsm_min, gsm_max, count_top/binding/back | — |

**ধাপ C** (মাইগ্রেশন `2026_10_28_000100_create_merchandise_norms_and_tna`):

| টেবিল | ফিল্ড | unique / নিয়ম |
|---|---|---|
| `dyeing_loss_bands` | fabric_type_id (ফাঁকা = যেকোনো), fiber_composition_id, color_group (WHITE/LIGHT/MEDIUM/DARK/EXTRA_DARK/MELANGE), weight_low, weight_high, loss_percent, active | চালু ব্যান্ড একে অপরের ওপর পড়ে না |
| `process_loss_allowances` | stage (BOOKING/CUTTING), qty_low, qty_high, loss_qty, add_to_booking, fabric_group, colour_type, effective_from, effective_to | একই stage-এ পরিমাণ আর তারিখ একসাথে ওভারল্যাপ করে না |
| `rate_lines` | rate_group (YARN/KNITTING/YARN_DYEING/AOP/DYEING/DYEING_FINISHING), description, uom, sort_order, active | description + group |
| `rate_line_rates` | rate_line_id, buyer_office_id, rate, currency, effective_from, entered_by | line + office + effective_from |
| `trim_spec_templates` + `trim_spec_item_rules` + `trim_spec_controls` | code, name, active · item_category, item_name, buyer_id · name, control_type (TEXT/NUMBER/SELECT/YESNO/DATE), options, required, sort_order | code |
| `tna_task_groups` | code, name, name_bn, active | code |
| `tna_tasks` | tna_task_group_id, code, sort_order, name, name_bn, source_module, offset_days, is_sample, is_start, is_end, is_production, active | code |
| `tna_templates` | code, scope (GENERAL/BUYER), buyer_id, order_type (NEW/REPEAT/DEVELOPMENT/PROJECTION), gmt_type (BASIC/SEMI_CRITICAL/CRITICAL), lead_from, lead_to, std_days, before_swing, after_swing, description | code |
| `tna_template_tasks` | tna_template_id, tna_task_id, offset_days (শিপমেন্ট থেকে, আগে হলে ঋণাত্মক), duration_days, sort_order, active | template + task |
| `tna_task_rights` | user_id, tna_task_id, can_create, can_update, can_delete (সারি থাকা মানেই দেখার অধিকার) | user + task |

## ৫. Workflow / Status ও Business Rules

- **Code:** বড় হাতে রাখা হয়, আর কোম্পানির মধ্যে unique, ছোট-বড় হাতের অক্ষর নির্বিশেষে ("bd" মানে BD)। অন্য কোম্পানি একই কোড ব্যবহার করতে পারে।
- **Colour:** নাম তার ধরনের মধ্যে unique। Navy solid আর Navy melange দুটো আলাদা রঙ।
- **Size:** নাম তার গ্রুপের মধ্যে unique।
- **Fabric type:** parent হতে পারে শুধু একই কোম্পানির একটা base type, নিজে নয়। yarn count chart-এ ব্যবহৃত type মোছা যায় না।
- **Fiber composition:**
  - অন্তত একটা fiber লাগে, একই fiber দুবার দেওয়া যায় না।
  - শতাংশের মোট ঠিক ১০০% না হলে সেভ হয় না।
  - বিবরণ নিজে থেকে তৈরি হয়, বড় অংশ আগে: "60% Cotton 40% Polyester"।
  - কোনো composition-এ ব্যবহৃত fiber মোছা যায় না। Fiber Master-এর "Used By" কলাম সেই সংখ্যা দেখায়।
- **GSM-wise count:** gsm_max ≥ gsm_min।
- **শুরুর ডাটা:** Brickland-এর ৯টা fabric type (SJ … MESH) আর ৮টা সাধারণ fiber। কোম্পানি প্রথমবার তালিকা খুললে তৈরি হয়, শুধু খালি তালিকায়।
- **ব্যান্ড (ধাপ C):**
  - নিচের সীমা ধরা হয়, ওপরের সীমা ধরা হয় না। তাই ১–২০০ আর ২০০–৫০০ একে অপরের গায়ে লাগে, কিন্তু ওভারল্যাপ করে না।
  - Dyeing loss: একই fabric type, composition আর colour group-এর দুটো **চালু** ব্যান্ড একই ওজনে পড়তে পারে না।
  - Process/Cutting loss: একই stage, fabric group আর colour type-এ পরিমাণ আর কার্যকর তারিখ একসাথে মিলে গেলে সেভ হয় না।
  - stage আসে স্ক্রিন থেকে, request থেকে না।
- **Rate chart:**
  - রেট কখনো সম্পাদনা হয় না। নতুন কার্যকর তারিখ মানে নতুন সংশোধন, আগেরটা ইতিহাসে থাকে।
  - একই দিনে একই লাইন আবার সেভ করলে সেই দিনের সংশোধনটাই ঠিক হয়। কিছু না বদলালে কিছুই লেখা হয় না।
  - শুরুর ডাটা: Brickland-এর ২৮টা রেট লাইন, ছয় গ্রুপে।
- **Trims spec:** New ট্যাবে শুধু code আর নাম। লাইনগুলো যোগ হয় Spec. Configuration-এ। কোনো তালিকা পাঠালে সেটা পুরোটা বদলে যায়, না পাঠালে আগের মতো থাকে। Select কন্ট্রোলে অপশন লাগে, আর কন্ট্রোলের নাম একটাই থাকতে পারে।
- **T&A:**
  - শুরুর ডাটা: ৪টা গ্রুপ আর ২৫টা টাস্ক (TSK-001 … TSK-025)।
  - কোড ছাড়া গ্রুপ তৈরি হলে নাম থেকে কোড নেওয়া হয় (Washing → WASHING, পরেরটা WASHING2)। Brickland-এর ডায়ালগে কোডের ঘর নেই।
  - কোনো ক্যালেন্ডারে ব্যবহৃত টাস্ক, বা টাস্কসহ গ্রুপ, মোছা যায় না।
  - Buyer-নির্দিষ্ট টেমপ্লেটে বায়ার লাগে, General-এ বায়ার দেওয়া যায় না। lead_to ≥ lead_from।
  - ক্যালেন্ডারের কোনো মাইলস্টোন টেমপ্লেটের সবচেয়ে বড় লিড টাইমের আগে পড়তে পারে না।
  - কপি করলে ক্যালেন্ডারসহ কপি হয়, কোড `<CODE>-C1`, `-C2` …।
  - Task rights: সারি থাকা মানে টাস্ক দেখা যায় (R)। C, U আর D তার ওপরে দেওয়া হয়। শুধু এই কোম্পানির সক্রিয় সদস্যকে দেওয়া যায়। এখনো কোথাও জোর করে মানানো হয় না; T&A এন্ট্রি তৈরির সময় প্রযোজ্য হবে।

## ৬. API ও পারমিশন

- সবগুলো স্ক্রিন-কিটের resource: `/api/v1/erp/{origin-countries|country-configs|colours|sizes|garment-parts|service-types|fabric-types|fibers|fiber-compositions|yarn-count-configs}`।
- Fiber composition-এর POST/PATCH-এ `items[]` (fiber_id, percent) সাথে যায়।
- GARM Part List: `GET/PUT /api/v1/merchandise/fabric-types/{id}/garment-parts` (`garment_part_ids[]`, পুরো তালিকা একসাথে)।
- ধাপ C-র resource: `/api/v1/erp/{dyeing-loss-bands|booking-loss-allowances|cutting-loss-allowances|rate-lines|trim-spec-templates|tna-task-groups|tna-tasks|tna-templates}`। Trims spec-এর POST/PATCH-এ `item_rules[]` আর `controls[]` যায়।
- Rate chart: `GET /merchandise/rate-chart` (অফিস আর কয়টা লাইনে রেট আছে), `GET/PUT /merchandise/rate-chart/{office}` (`rates[]`: rate_line_id, rate, currency, effective_from), `GET /merchandise/rate-chart/{office}/history`।
- TNA টেমপ্লেট: `GET/PUT /merchandise/tna-templates/{id}/tasks` (`tasks[]`, পুরো ক্যালেন্ডার একসাথে), `POST /merchandise/tna-templates/{id}/copy`।
- TNA rights: `GET /merchandise/tna-rights` (সব অনুমতি), `GET /merchandise/tna-rights/users`, `GET/PUT /merchandise/tna-rights/{user}` (`rights[]`)।
- **পারমিশন:**
  - নতুন `merchandise.master.view` আর `merchandise.master.manage`।
  - manage পায় FACTORY_ADMIN। Owner আর Admin সব পায়।
  - view পায় FACTORY_MANAGER, STORE_MANAGER আর read-only রোল।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/merchandise-desk/<screen>`। সাইডবারে Merchandise Desk → MASTER DATA।
- প্রতিটা স্ক্রিন Records | New। Fabric Type-এ তৃতীয় ট্যাব GARM Part List।
- New ট্যাব আর grid-এর Edit/Delete দেখা যায় শুধু manage পারমিশন থাকলে।
- Fiber Composition-এর নিজস্ব ফর্ম: fiber বেছে শতাংশ দেওয়া যায়, বিবরণ টাইপ করার সাথে সাথে তৈরি হয়, আর মোট ১০০% না হওয়া পর্যন্ত Save বন্ধ থাকে।
- **ধাপ C-র ট্যাব, Brickland-এর মতো:**
  - Inquiry Rate Chart: Records | Rate Chart | Rate History | New rate line। Rate Chart আর History-তে বাঁয়ে বায়ার অফিসের তালিকা, ডানে গ্রুপ অনুযায়ী রেট (বর্তমান রেট, কবে থেকে, নতুন রেট, মুদ্রা, তারিখ)। দুই ট্যাবের মধ্যে বাছাই করা অফিস থেকে যায়।
  - Trims Spec: Records | Spec. Configuration | New spec template। Configuration-এ আগের টেমপ্লেট বাছাই করা যায়, Item rule আর Control-এর সারি যোগ বা বাদ দেওয়া যায়।
  - TNA Task Rights: Records (User, Task, Group, C R U D) | T&A Task Permission (ইউজার বাছাই করে প্রতিটা টাস্কের C/R/U/D টিক)।
  - TNA Template: Records (প্রতিটা সারিতে Copy) | Template Tasks (বাঁয়ে টেমপ্লেট, ডানে ক্যালেন্ডার: ক্রম, শিপমেন্ট থেকে দিন, মেয়াদ, D-90 → D-86 ধাঁচের সময়সীমা) | Add New।
  - Task & Action Register: Records | Task Groups (বাঁয়ে গ্রুপ, ডানে সেই গ্রুপের টাস্ক, New task group ডায়ালগ) | New task।
  - তিনটা loss স্ক্রিনে Records | New।
  - Brickland-এর General/Buyer, New/Repeat ইত্যাদি radio এখানে select, কারণ EntryForm-এ radio নেই।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (ধাপ A):

**Backend**
- `app/Modules/Merchandise/Database/Migrations/2026_10_26_000100_create_merchandise_reference_masters.php`
- `app/Modules/Merchandise/Models/OriginCountry.php`
- `app/Modules/Merchandise/Models/CountryConfig.php`
- `app/Modules/Merchandise/Models/Colour.php`
- `app/Modules/Merchandise/Models/Size.php`
- `app/Modules/Merchandise/Models/GarmentPart.php`
- `app/Modules/Merchandise/Models/ServiceType.php`
- `app/Modules/Merchandise/Models/FabricType.php`
- `app/Modules/Merchandise/Models/FabricTypeGarmentPart.php`
- `app/Modules/Merchandise/Models/Fiber.php`
- `app/Modules/Merchandise/Models/FiberComposition.php`
- `app/Modules/Merchandise/Models/FiberCompositionItem.php`
- `app/Modules/Merchandise/Models/YarnCountConfig.php`
- `app/Modules/Merchandise/Resources/MasterResource.php`
- `app/Modules/Merchandise/Resources/OriginCountryResource.php`
- `app/Modules/Merchandise/Resources/CountryConfigResource.php`
- `app/Modules/Merchandise/Resources/ColourResource.php`
- `app/Modules/Merchandise/Resources/SizeResource.php`
- `app/Modules/Merchandise/Resources/GarmentPartResource.php`
- `app/Modules/Merchandise/Resources/ServiceTypeResource.php`
- `app/Modules/Merchandise/Resources/FabricTypeResource.php`
- `app/Modules/Merchandise/Resources/FiberResource.php`
- `app/Modules/Merchandise/Resources/FiberCompositionResource.php`
- `app/Modules/Merchandise/Resources/YarnCountConfigResource.php`
- `app/Modules/Merchandise/Support/MerchandiseDefaults.php`
- `app/Modules/Merchandise/Http/Controllers/Api/FabricTypePartsApiController.php`
- `app/Modules/Merchandise/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/merchandise-desk/[screen]/page.js`
- `frontend/src/app/(app)/merchandise-desk/[screen]/screens.js`
- `frontend/src/app/(app)/merchandise-desk/[screen]/actions.js`
- `frontend/src/components/merchandise/composition-form.jsx`
- `frontend/src/components/merchandise/garment-part-picker.jsx`
- `lang/en/merchandise.php`
- `lang/bn/merchandise.php`

**Tests**
- `tests/Feature/Merchandise/MerchandiseMasterTest.php`

As-built (ধাপ B):

**Backend**
- `app/Modules/Merchandise/Database/Migrations/2026_10_27_000100_create_merchandise_buyer_masters.php`
- `app/Modules/Merchandise/Models/BuyerOffice.php`
- `app/Modules/Merchandise/Models/Buyer.php`
- `app/Modules/Merchandise/Models/BuyerOfficeLink.php`
- `app/Modules/Merchandise/Models/BuyerUserPermission.php`
- `app/Modules/Merchandise/Models/SampleType.php`
- `app/Modules/Merchandise/Models/BuyerSampleType.php`
- `app/Modules/Merchandise/Models/TestInstitute.php`
- `app/Modules/Merchandise/Models/BuyerTestInstitute.php`
- `app/Modules/Merchandise/Models/BuyerTrimItem.php`
- `app/Modules/Merchandise/Models/BuyerSupplierConfig.php`
- `app/Modules/Merchandise/Models/BuyerSupplierConfigVendor.php`
- `app/Modules/Merchandise/Resources/BuyerOfficeResource.php`
- `app/Modules/Merchandise/Resources/BuyerResource.php`
- `app/Modules/Merchandise/Resources/SampleTypeResource.php`
- `app/Modules/Merchandise/Resources/TestInstituteResource.php`
- `app/Modules/Merchandise/Resources/BuyerTrimItemResource.php`
- `app/Modules/Merchandise/Resources/BuyerSupplierConfigResource.php`
- `app/Modules/Merchandise/Support/SampleTypeDefaults.php`
- `app/Modules/Merchandise/Http/Controllers/Api/BuyerMappingApiController.php`

**Frontend**
- `frontend/src/components/merchandise/pick-ticker.jsx`
- `frontend/src/components/merchandise/sample-mapping.jsx`
- `frontend/src/components/erp/dialog-form-button.jsx`
- `frontend/src/app/(app)/prototype-desk/[screen]/page.js`

**Tests**
- `tests/Feature/Merchandise/MerchandiseBuyerTest.php`

As-built (ধাপ C):

**Backend**
- `app/Modules/Merchandise/Database/Migrations/2026_10_28_000100_create_merchandise_norms_and_tna.php`
- `app/Modules/Merchandise/Models/DyeingLossBand.php`
- `app/Modules/Merchandise/Models/ProcessLossAllowance.php`
- `app/Modules/Merchandise/Models/RateLine.php`
- `app/Modules/Merchandise/Models/RateLineRate.php`
- `app/Modules/Merchandise/Models/TrimSpecTemplate.php`
- `app/Modules/Merchandise/Models/TrimSpecItemRule.php`
- `app/Modules/Merchandise/Models/TrimSpecControl.php`
- `app/Modules/Merchandise/Models/TnaTaskGroup.php`
- `app/Modules/Merchandise/Models/TnaTask.php`
- `app/Modules/Merchandise/Models/TnaTemplate.php`
- `app/Modules/Merchandise/Models/TnaTemplateTask.php`
- `app/Modules/Merchandise/Models/TnaTaskRight.php`
- `app/Modules/Merchandise/Resources/DyeingLossBandResource.php`
- `app/Modules/Merchandise/Resources/ProcessLossAllowanceResource.php`
- `app/Modules/Merchandise/Resources/BookingLossAllowanceResource.php`
- `app/Modules/Merchandise/Resources/CuttingLossAllowanceResource.php`
- `app/Modules/Merchandise/Resources/RateLineResource.php`
- `app/Modules/Merchandise/Resources/TrimSpecTemplateResource.php`
- `app/Modules/Merchandise/Resources/TnaTaskGroupResource.php`
- `app/Modules/Merchandise/Resources/TnaTaskResource.php`
- `app/Modules/Merchandise/Resources/TnaTemplateResource.php`
- `app/Modules/Merchandise/Http/Controllers/Api/RateChartApiController.php`
- `app/Modules/Merchandise/Http/Controllers/Api/TnaApiController.php`

**Frontend**
- `frontend/src/components/merchandise/rate-chart-editor.jsx`
- `frontend/src/components/merchandise/trim-spec-editor.jsx`
- `frontend/src/components/merchandise/tna-calendar-editor.jsx`
- `frontend/src/components/merchandise/tna-rights-matrix.jsx`

**Tests**
- `tests/Feature/Merchandise/MerchandiseNormsTnaTest.php`

**ধাপ D — Item Type, Nature of Work, Style & Order Upload**
- `app/Modules/Merchandise/Database/Migrations/2026_12_05_000100_create_merchandise_item_types_and_work_natures.php` (সাথে `merch_style_items.nature_of_work`-এর পুরোনো কোড নামে বদল)
- `app/Modules/Merchandise/Database/Migrations/2026_12_06_000100_create_merchandise_order_uploads.php`
- `app/Modules/Merchandise/Models/ItemType.php`, `WorkNature.php`, `OrderUpload.php`, `OrderUploadLine.php`
- `app/Modules/Merchandise/Resources/ItemTypeResource.php`, `WorkNatureResource.php` (`app/Modules/Erp/Support/ResourceRegistry.php`-এ নিবন্ধিত)
- `app/Modules/Merchandise/Support/MerchandiseDefaults.php` (`ensureItemTypes`, `ensureWorkNatures`, `WORK_NATURES`)
- `app/Modules/Merchandise/Http/Controllers/Api/StyleItemApiController.php` (Garment Items: master থেকে টাইপ আর কাজের ধরন, একাধিক কাজের ধরন)
- `app/Modules/Merchandise/Http/Controllers/Api/OrderUploadApiController.php`, `Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (`MERCHANDISE.ITEM_TYPE`, `MERCHANDISE.WORK_NATURE`, `MERCHANDISE.ORDER_UPLOAD`)
- `frontend/src/app/(app)/merchandise-desk/[screen]/screens.js` (`itemType`, `workNature`), `upload.js` (`UPLOAD_SCREENS`), `page.js`
- `frontend/src/components/merchandise/order-upload.jsx`, `style-item-desk.jsx` (Nature of Work → `MultiPick`)
- `frontend/src/app/api/merchandise/order-upload/route.js`, `frontend/src/app/api/merchandise/order-upload/verify/route.js`
- `lang/{en,bn}/merchandise.php` (`master.ity`, `master.nwk`, `master.oup`, `oup`, স্ক্রিন, noun, new)
- `tests/Feature/Merchandise/MerchandiseItemTypeWorkNatureTest.php`, `tests/Feature/Merchandise/MerchandiseOrderUploadTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব ধাপ তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `MerchandiseMasterTest` ৫/৫:
  - **Code:** ছোট-বড় হাতের অক্ষর নির্বিশেষে unique, অন্য কোম্পানি একই কোড নিতে পারে।
  - **রঙ আর সাইজ:** ধরন বা গ্রুপের মধ্যে unique।
  - **শুরুর ডাটা:** ৯টা fabric type আর ৮টা fiber।
  - **Parent:** parent শুধু একটা base type হতে পারে।
  - **Composition:** ১০০% না হলে সেভ হয় না, বিবরণ নিজে তৈরি হয় আর সম্পাদনায় বদলায়।
  - **মোছা নিষেধ:** ব্যবহৃত fiber বা fabric type মোছা যায় না।
  - **GARM Part List:** ঠিকমতো সেভ হয়।
  - **পারমিশন:** factory manager দেখতে পারে কিন্তু বদলাতে পারে না, technician কিছুই পায় না।
- ব্রাউজার (dev):
  - রঙ, তিনটা garment part, Single Jersey-তে Body আর Sleeve টিক।
  - composition 60/40-এর বিবরণ লাইভ তৈরি হয়ে সেভ হয়েছে।
  - GSM count সেভ হয়েছে।
  - Fabric Type-এর তালিকায় GARM PART কলামে "Body, Sleeve" এসেছে।
  - কনসোলে error নেই।

- `MerchandiseBuyerTest` ৪/৪:
  - **বায়ার আর অফিস:** দুই দিক থেকেই যুক্ত করা যায়। নতুন বায়ারে অন্তত একটা অফিস লাগে, আর নিবন্ধনের শেষ তারিখ শুরুর আগে হতে পারে না।
  - **বায়ার-অনুমতি:** merchandising আর marketing আলাদা। অন্য কোম্পানির ইউজার বা বায়ার বসানো যায় না।
  - **Sample type:** ১১টা ডিফল্ট দিয়ে শুরু হয়। সম্পাদনা করলে "Configured" হয়। বায়ার-ম্যাপিংয়ে নিজস্ব উৎস, সাইজ আর পরিমাণ রাখা যায়। কোনো বায়ারের প্রয়োজনীয় type মোছা যায় না।
  - **বাকি তিনটা:** test lab, ট্রিমের কোড (বায়ারের মধ্যে unique) আর বায়ারপ্রতি একটাই supplier কনফিগারেশন।
- ব্রাউজার (dev, ধাপ B):
  - অফিস → সেই অফিসের সাথে যুক্ত বায়ার → ম্যানেজারকে বায়ার-অনুমতি।
  - বায়ারের জন্য PP আর Fit স্যাম্পল টিক।
  - New Institute ডায়ালগ থেকে SGS যোগ করে বায়ারের সাথে ম্যাপ।
  - Prototype Desk-এ ১১টা sample type দেখা গেছে।

- `MerchandiseNormsTnaTest` ৬/৬:
  - **Dyeing loss:** গায়ে লাগা ব্যান্ড চলে, ওভারল্যাপ করা চলে না। অন্য colour group বা বন্ধ ব্যান্ড ওভারল্যাপ করতে পারে।
  - **Process/Cutting loss:** stage request থেকে বদলানো যায় না। দুই stage আলাদা। আগে শেষ হওয়া বা অন্য fabric group-এর ব্যান্ড চলে।
  - **Rate chart:** ২৮টা লাইন দিয়ে শুরু। একই রেট আবার পাঠালে সংশোধন হয় না, নতুন তারিখে হয়। ইতিহাস, অফিস-সংখ্যা আর গড় রেট ঠিক আসে।
  - **Trims spec:** আগে নাম দিয়ে তৈরি, পরে লাইন যোগ। নাম বদলালে লাইন থাকে। অপশন ছাড়া Select আর একই নামের দুটো কন্ট্রোল চলে না।
  - **TNA template:** ২৫টা টাস্ক আর ৪টা গ্রুপ দিয়ে শুরু। নাম থেকে গ্রুপ-কোড তৈরি হয়। বায়ার-নির্দিষ্ট টেমপ্লেটে বায়ার লাগে। লিড টাইমের বাইরের মাইলস্টোন চলে না। ক্যালেন্ডারসহ কপি হয়। ব্যবহৃত টাস্ক মোছা যায় না।
  - **TNA rights:** পুরো তালিকা একসাথে সেভ হয় (C R U D স্ট্রিং ঠিক আসে)। অন্য কোম্পানির ইউজারে 404। শুধু দেখার অনুমতিওয়ালা ম্যানেজার দেখতে পারে, বদলাতে পারে না।
- ব্রাউজার (dev, ধাপ C):
  - Dyeing band, process-loss band, LPP S.A.-র দুটো রেট (চার্ট আর ইতিহাসে দেখা গেছে)।
  - Care Label spec-এ দুটো কন্ট্রোল (একটা Select), TNA rights (CR·· আর ·R··)।
  - TNGEN90 টেমপ্লেটে দুটো টাস্ক, Copy থেকে TNGEN90-C1, আর Washing গ্রুপ।
  - কনসোলে error নেই।

- **ধাপ D:**
  - `MerchandiseItemTypeWorkNatureTest` (৫০টা assertion):
    - শুরুতে চার গ্রুপের সব টাইপ আর চারটা কাজের ধরন।
    - বাধ্যতামূলক ঘর। code একবারই থাকে। একই গ্রুপে একই টাইপ দুবার নয়, তবে অন্য গ্রুপে চলে।
    - কাজের ধরনের নামে কমা থাকলে আটকায়।
    - Garment Items ধাপে শুধু সক্রিয় master আসে। গ্রুপের বাইরের টাইপ আর বন্ধ করা কাজের ধরন আটকায়।
    - কয়েকটা কাজের ধরন "Cutting, Sewing" হিসেবে রাখা হয়। পুরোনো কোড (CUT_MAKE) নামে পড়া হয়।
    - ব্যবহৃত টাইপ বা কাজের ধরন মোছা যায় না। বন্ধ করলে যে আইটেমে আগে থেকে আছে সেখানে বহাল থাকে।
    - STORE_MANAGER বদলাতে পারে না।
  - `MerchandiseOrderUploadTest` (২৮টা assertion):
    - ফাইল ছাড়া, আর header-এ কলাম না থাকলে বার্তা আসে।
    - পাঁচ সারির ফাইলে অচেনা রং, Total ≠ Qty × Rate, একই লাইন দুবার আর অচেনা বায়ার কারণসহ reject হয়।
    - একটা সারি reject হলে পুরো ব্যাচ আটকায়।
    - ঠিক করা ফাইল (tab-separated) দিলে UPL-00001 হয়: নতুন স্টাইল ১টা, অর্ডার ২টা, CONFIRMED, রং-সাইজের লাইন, রেট।
    - Records ঠিক আসে। একই ফাইল আবার দিলে "already on the register" বার্তা আসে। STORE_MANAGER পারে না।
  - রিগ্রেশন: Master, Inquiry, Order, Style, MenuAccess, RoleMatrix আর ApiGateCoverage মিলিয়ে ৪৩টা টেস্ট, ৬২৫টা assertion সবুজ।
- **ব্রাউজার (dev, ধাপ D):**
  - সাইডবারে MASTER DATA এখন ২৯।
  - Item Type master-এ ৭৭টা ডিফল্ট রেকর্ড আর Item Group ফিল্টার। নতুন KT-RAGLAN "Raglan Tee" সেভ হয়েছে।
  - Nature of Work master-এ চারটা ডিফল্ট, নতুন NW-CUT "Cutting" সেভ হয়েছে। "Printing, Washing" কমার জন্য সেভ হয়নি।
  - Style Development Sheet → Item/Combo/Model(Fresh): Nature of Work এখন chip ("Cut & Make ×", আগের CUT_MAKE থেকে বদলানো), আর অপশন master থেকে আসে, Cutting সবার আগে।
  - Upload:
    - Black রঙের সারি reject হলো, Upload বাটন বন্ধ থাকল।
    - ঠিক ফাইল দিলে "3 rows · 3 accepted" দেখাল → Upload → UPL-00001।
    - নতুন স্টাইল SS27-TEE01 আর অর্ডার LPP-2026-000001 (১৯০০ পিস, রেট ১.৮৫, CONFIRMED) Order Register-এ এসেছে।
  - সব রেকর্ড dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **Item Type আর Nature of Work-এর তালিকা Brickland-এর ক্রলে ছিল না:** দুটো স্ক্রিন পরে Brickland-এ যোগ হয়েছে। তাই উপরের AUTO তালিকা (২৭টা) আর "লাইভ বিবরণ"-এ এগুলো নেই। ফিল্ড ধরা হয়েছে লাইভ প্রোব থেকে (`md2.json`, `md3.json`, `md4.json`)।
- **ডিফল্ট তালিকা:** Brickland-এ দুটো master-এ কোনো রেকর্ড নেই, তবু Garment Items-এ অপশন আসে। এখানে আগের স্থির তালিকাটাই প্রথমবারে master-এ বসানো হয় (৭৭টা টাইপ, চারটা কাজের ধরন)। এরপর তালিকা কোম্পানি নিজে বদলায়।
- **Upload-এর ধরন:**
  - একটা Order#-এর জন্য একটা কনফার্মড অর্ডার হয় (ধরন Bulk New, মুদ্রা USD)।
  - রেট হয় লাইনগুলোর ওজনসহ গড়।
  - Production Date শুধু আপলোড-লাইনে রাখা হয়, কারণ অর্ডারে এর জন্য কোনো ঘর নেই।
  - নতুন স্টাইলের অবস্থা Running আর বিবরণ Item। সাইজ রেঞ্জ ফাঁকা থাকে।

## ১১. As-built নোট

**ধাপ A সম্পন্ন (২০২৬-১০-১০):**
- নতুন মডিউল `app/Modules/Merchandise`। দশটা মাস্টারই স্ক্রিন-কিটের resource, শেয়ার্ড base `MasterResource` থেকে।
- `Country & Origin` আর `Country Configuration` আলাদা টেবিল, Brickland-এর মতোই। প্রথমটা উৎস দেশ, দ্বিতীয়টা ব্যবসার দেশ (মুদ্রা, ডায়াল কোড, চালানের গন্তব্য)।
- **Fiber composition:** item-গুলো একই resource request-এ যায় (`items[]`)। `beforeSave`-এ যাচাই আর বিবরণ তৈরি হয়, `afterSave`-এ item সেভ হয়।
- **পথে ধরা বাগ:**
  - Base UI select নির্বাচনের পরে রিসেট হয়ে একটা অচেনা মান পাঠায়, তাতে ফাঁকা fiber সারি তৈরি হচ্ছিল। এখন শুধু চেনা fiber-এর id নেওয়া হয়।
  - composition-এর তালিকা `name` কলাম দিয়ে সাজাতে চাইছিল, কিন্তু টেবিলে সে কলাম নেই।
- **একই সাথে:**
  - Approval-এর "সব দেখা" পারমিশনের নাম `approval.request.see_all` করা হয়েছে, কারণ `.view_any`-তে শেষ হওয়া পারমিশন read-only রোল নিজে থেকে পায়।
  - `AssetLifecycleTest`-এর একটা পুরনো টাইমিং-নির্ভর বাগ ঠিক হয়েছে (id দিয়ে tie ভাঙা)।
**ধাপ B সম্পন্ন (২০২৬-১০-১০):**
- **"একটা বেছে নিয়ে বাকিগুলো টিক দাও" স্ক্রিনগুলো একটাই সাধারণ endpoint-এ চলে:** অফিস → বায়ার, বায়ার → অফিস, ইউজার → বায়ার (merchandising আর marketing আলাদা), বায়ার → test lab।
  - endpoint `/merchandise/mappings/{kind}[/{subject}]`, একটা কম্পোনেন্ট `PickTicker`।
  - subject-এর তালিকাও এখান থেকে আসে, তাই admin-এর ইউজার-তালিকা দেখার অনুমতি লাগে না।
- **Sample Type Configuration (Prototype Desk 07/01)** এখানে তৈরি হয়েছে, কারণ Buyer Sample Mapping এর ওপর নির্ভর করে।
  - নতুন মডিউল-মেনু Prototype Desk, রুট `/prototype-desk/sample-type-configuration`।
  - ১১টা ডিফল্ট দিয়ে শুরু। Brickland-এর মতো নতুন যোগ করা যায় না, শুধু সম্পাদনা। ট্যাব: সব / Configured / On Defaults।
- **বায়ারের অনুমতি এখনো কোথাও জোর করে মানানো হয় না:** কে কোন বায়ারে কাজ করতে পারবে, তা শুধু রাখা হচ্ছে। অর্ডার আর স্টাইল তৈরির সময় এটা প্রযোজ্য হবে।
- **কিট:**
  - `DialogFormButton`: বাটন চাপলে ডায়ালগে ফর্ম।
  - `RecordsGrid`-এর কলামের key এখন অবস্থানসহ, কারণ "Active?" আর "Status" দুটো কলাম একই ঘর থেকে আসে।
  - EntryForm-এর multiselect মান (`*_ids`) API-তে তালিকা হিসেবে যায়।
**ধাপ D সম্পন্ন (২০২৬-১০-২০):**
- **Item Type Master** আর **Nature of Work Master:** screen-kit resource (`item-types`, `work-natures`)। Records-এ Item Group ফিল্টার আছে। New ফর্মে Brickland-এর hint আছে।
- **Garment Items ধাপ** (Item/Combo/Model Registration):
  - Item Type আর Nature of Work এখন master থেকে আসে।
  - Nature of Work একসাথে কয়েকটা বাছা যায়, Brickland-এর মতো chip হিসেবে। আইটেমে কমা দিয়ে জোড়া লেখা হিসেবে থাকে।
  - পুরোনো কোড migration-এ নামে বদলানো হয়েছে।
- **Style & Order Upload:**
  - Records আর Upload ট্যাব।
  - Upload-এ File Type, Attach File, তারপর Verify File Data আর Upload।
  - ফাইল Next route handler দিয়ে Laravel-এ stream হয়, server action-এর ১ MB সীমা এড়াতে।
- Brickland-এর এখনকার ২৯টা স্ক্রিনই এখন চালু।

**ধাপ C সম্পন্ন (২০২৬-১০-১০):**
- ২৭টার মধ্যে ২৬টা স্ক্রিন তৈরি। **Style & Order Upload** বাকি রাখা হয়েছে, কারণ এটা স্টাইল আর অর্ডারের টেবিলে লেখে, আর সেগুলো Order মডিউলে নতুন করে তৈরি হবে। আপলোড তখন সেই টেবিলের সাথেই তৈরি হবে।
- Process Loss Quantity আর Cutting Process Loss একই টেবিলে, stage দিয়ে আলাদা। দুটো resource (`booking-loss-allowances`, `cutting-loss-allowances`), একটা abstract base থেকে।
- **Brickland থেকে পার্থক্য:**
  - তিনটা loss স্ক্রিন Brickland-এ শুধু খালি "Save" কাঠামো। এখানে পুরোপুরি তৈরি, Records | New সহ।
  - TNA template-এর radio এখানে select।
- **কিট:**
  - `masterScreen` এখন `rowAction` নেয় (TNA template-এর Copy)। extraTab-এর `render` async হতে পারে। extraTab নিজের `href` দিতে পারে, তাই ট্যাব বদলালেও বাছাই করা অফিস থাকে।
- **পথে ধরা বাগ:** process-loss band-এ শেষ তারিখ না পাঠালে `effective_to` key না থাকায় 500 হচ্ছিল। এখন ফাঁকা ধরা হয়।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- Fiber Composition-এর New/Edit এখন Brickland-এর কার্ড: শিরোনামের ডানে Records বাটন, "Is Active?", বাছাই করা ফাইবার একটা টেবিলে (Fiber Type / Percentage / Remove), আর Save / Cancel। Save সবসময় চাপা যায়; 100% না হলে সার্ভার জানায়।
- লেবেল Brickland-এর মতো করা হয়েছে: Fiber, Size, Fabric Type আর Task-এর New ফর্মে "Is Active?"; Task-এ "Task Name (En)/(Bn)"; Fabric Type গ্রিডে "Category"; Size গ্রিডে "Code" আর "Size Name(En)"।
- Process Loss Quantity Setup আর Cutting Process Loss Setup-এ Lower Qty / Higher Qty / P/Loss Qty / "Add P/Loss Qty?" (সাহায্য-লেখাসহ, Fabric Group-এর আগে) / Color Type / Effect Date / Expire Date, আর ফর্মের ব্যাখ্যা-লাইন।
- Dyeing Process Loss Norms-এ Fabric Type / Color Group / Finish Fab Range (LW)/(HW) / Process Loss%, আর ব্যান্ডের ব্যাখ্যা।
- TNA Template-এর New ট্যাবের সাবমিট বাটন "Add New"।
- খালি তালিকা এখন Brickland-এর কার্ড দেখায় ("No X records yet" / "Add the first one on the New X tab"), খালি টেবিল না।
- বাকি পার্থক্য: Size-এর "Size ID" কলাম নেই (আমাদের id ULID); Task Action List-এর Records-এ Brickland-এর "New task group" বাটন নেই (Task Groups ট্যাবে আছে)।
