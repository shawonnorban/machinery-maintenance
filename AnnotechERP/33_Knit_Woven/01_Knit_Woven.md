# Knit Woven

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [Knit Woven](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `33/01`

**⬅ ফিরে যান:** [Knit Woven](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Norban ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | View All Requisition | `/apps/requisitions/knit-woven` | 1 | 112 | 0 |
| 2 | Add New Requisition | `/apps/requisitions/knit-woven/create` | 1 | 45 | 0 |
| 3 | Draft Requisition | `/apps/requisitions/knit-woven/drafts` | 1 | 0 | 0 |
| 4 | Create Budget | `/apps/requisitions/knit-woven/pivot` | 1 | 11 | 0 |
| 5 | Budget Status | `/apps/requisitions/knit-woven/budget-status` | 1 | 7 | 0 |
| 6 | Stock Yarn Use | `/apps/requisitions/knit-woven/store-stock` | 1 | 2 | 0 |
| 7 | Knit/Dying Costing | `/apps/requisitions/knit-woven/costing` | 1 | 2 | 0 |
| 8 | Budget Finalized | `/apps/requisitions/knit-woven/pivot/finalized` | 1 | 8 | 0 |
| 9 | Stock Yarn Declaration | `/apps/requisitions/knit-woven/stock-declaration` | 1 | 1 | 0 |
| 10 | Budget Report | `/apps/requisitions/knit-woven/report` | 1 | 12 | 0 |
| 11 | Yarn Report | `/apps/requisitions/knit-woven/yarn-report` | 1 | 10 | 0 |
| 12 | Short Purchase | `/apps/requisitions/knit-woven/short-purchase` | 1 | 10 | 0 |
| 13 | Excess Purchase | `/apps/requisitions/knit-woven/excess-purchase` | 1 | 9 | 0 |
| 14 | Audit Log | `/apps/requisitions/knit-woven/audit-log` | 1 | 6 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### View All Requisition

`/apps/requisitions/knit-woven`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select-multiple |  | Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Limited., BC Tex Knitwea |
| Buyer | textarea |  |  |
| Brand | select-multiple |  | 356-JGTOD Dress, 436-JBTOD F.uk Jogger, ABC, ADMIRAL, ADMIRAL SPORTS, American Tall, Anpanman, Apunto, ARENA, AUTHENTIC, |
| Brand | textarea |  |  |
| Style | select-multiple |  | 2174190, 2191043, 2191481, 2194681, 2195774, 21900463, 2274357 Holden, 2274534 Lionel, 2274681 Lucifer, 2274696 Lisboa,  |
| Style | textarea |  |  |
| Item | select-multiple |  | Dress, Dress, Hoody Jacket, Hoody Jacket, Jog Pant/ Pajama, Jog Pant/ Pajama, Legging, Legging, Long pant, Long pant, Ot |
| Item | textarea |  |  |
| Job No | select-multiple |  | 26HAL00161, 26NCL00415, 26NCL00416, 26NCL00417, 26NCL0043 |
| Job No | textarea |  |  |
| Contract No. | select-multiple |  | HAL/ZXY/11/AUG-26, NCL/S.OLIVER/033/OCT/26 |
| Contract No. | textarea |  |  |
| Entry From | date |  |  |
| Entry To | date |  |  |
| Delivery From | date |  |  |
| Delivery To | date |  |  |
| Order Booking From | date |  |  |
| Order Booking To | date |  |  |
| PO-wise RFI/SD From | date |  |  |
| PO-wise RFI/SD To | date |  |  |
| Yarn Booking From | date |  |  |
| Yarn Booking To | date |  |  |
| Per Page | select |  | All, Knit, Woven |
| Per Page | select |  | 10, 25, 50, 100, 200, 500 |
| — | checkbox |  |  |
| S/N | text |  |  |
| Entry Type | text |  |  |
| Filter Factory | text |  |  |
| Filter Buyer | text |  |  |
| Filter Brand | text |  |  |
| Filter Season | text |  |  |
| Delivery Date | text |  |  |
| Order Booking Date | text |  |  |
| RFI Date | text |  |  |
| Yarn Booking Date | text |  |  |
| No of Style | text |  |  |
| Order Type | text |  |  |
| Contract No | text |  |  |
| Dept | text |  |  |
| Artwork | text |  |  |
| Wash Type | text |  |  |
| Order Color | text |  |  |
| Fabric Type | text |  |  |
| Fabric Composition | text |  |  |
| Fabrication | text |  |  |
| GSM | text |  |  |
| Sustainability | text |  |  |
| Parts | text |  |  |
| Yarn Type | text |  |  |
| Yarn Count | text |  |  |
| Composition | text |  |  |
| Additional Process | text |  |  |
| Ticket | text |  |  |
| PAC | text |  |  |
| Order Qty | text |  |  |
| FOB Price | text |  |  |
| FOB Value | text |  |  |
| Quantity Pcs | text |  |  |
| Cutting % | text |  |  |
| Qty w % | text |  |  |
| CAD/CON | text |  |  |
| Loss % | text |  |  |
| CAD w Loss | text |  |  |
| Feeding % | text |  |  |
| Sample Yarn | text |  |  |
| Bulk Yarn | text |  |  |
| Total Yarn | text |  |  |
| Stock | text |  |  |
| Entry Date | text |  |  |
| Factory Id | select |  | Select Factory, HAL, NCL, NFL |
| Buyer Id | select |  | Select Buyer, Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Limited.,  |
| Brand Id | select |  | Select Brand, 356-JGTOD Dress, 436-JBTOD F.uk Jogger, ABC, ADMIRAL, ADMIRAL SPORTS, American Tall, Anpanman, Apunto, ARE |
| Season Id | select |  | Select Season, 508, 508, 509, 509, 510, 510, 511, 511, 512, 512, 513 |
| Delivery Date | date |  |  |
| Order Booking Date | date |  |  |
| Po Wise Rfi Sd Date | date |  |  |
| Yarn Booking Date | date |  |  |
| No Of Style | number |  |  |
| Style | text |  |  |
| Order Type | select |  | Select Order Type, BLOCK, BLOCK, BLOCK NOS, BLOCK NOS, Buying File, Buying File, Cancelled, Cancelled, Cancelled + Liabi |
| Job No | text |  |  |
| Contract Number | text |  |  |
| Dept Id | select |  | Select Department, Baby, Boys, Girls, Kids, Kids-Boys, Kids-Girls, Ladies, Mens, Toddler Boys, Toddler Girls, Unisex |
| Cad Image | file |  |  |
| Item Id | select |  | Select Item, Dress, Dress, Hoody Jacket, Hoody Jacket, Jog Pant/ Pajama, Jog Pant/ Pajama, Legging, Legging, Long pant,  |
| Wash Type Id | select |  | Select Wash Type, Acid Wash, Cold Pigment Dye, Deep Dye Wash, Enzyme Wash, Garments Dye, Garments Dye+Acid Wash, Garment |
| Order Color | text |  |  |
| Fabric Type | select |  | Select Fabric Type, 12x4 Rib Elastane, 1X1 Fineliner Rib Elasthan, 1X1 Flat knit, 1x1 Flat Knit Rib, 1X1 Flat Knit Rib E |
| Fabric Composition | select |  | Select Fabric Composition, 100% Cotton, 100% Cotton Melange, 100% Cotton Slub, 100% Cotton Slub Melange, 52% Cotton 44%  |
| Gsm Id | select |  | Select GSM, 100 GSM, 110 GSM, 120 GSM, 130 GSM, 140 GSM, 150 GSM, 160 GSM, 170 GSM, 180 GSM, 190 GSM, 200 GSM |
| Sustainability Id | select |  | Select Sustainability, BCI, BCI + Lycra, BCI + Recycle, BCI GRS, BCI RCS, Bio + Recycle, BIO GOTS, BIO OCS, C2C, C2C BCI |
| Parts | select |  | Select Part, [Body + Sleeve Placket + Collar ], 3D Applique, AOP, Applique fabrics, Arm Hole Piping, Arm Hole Rib, Armho |
| Yarn Type | select |  | Select Yarn Type, 100% Card Combed Cotton, 100% Card Compact Cotton, 100% Card Compact Cotton White, 100% Card Compact V |
| Yarn Count | select |  | Select Yarn Count, 10/1 Ne, 100D, 100D/100F, 100D/144F, 100D/34F, 100D/36 SD, 100D/36F, 100D/38F, 100D/48F, 100D/72F, 10 |
| Composition | select |  | Select Composition, [100% Acrylic], [100% Askin Polyester], [100% BCI Cotton Melange], [100% BCI Cotton Slub], [100% BCI |
| Additional Process Id | select |  | Select Additional Process, Acid Wash, Afson Print, Air Turning, All Over Embroidery, All Tests, Anti Bactarial+Cool IT f |
| Quantity Ticket | number |  |  |
| Pac | number |  |  |
| Order Qty | number |  |  |
| Fob Price | number |  |  |
| Fob Value | number |  |  |
| Quantity Pcs | number |  |  |
| Cutting Percentage | number |  |  |
| Qty With Percentage | number |  |  |
| Cad Consumption | number |  |  |
| Process Loss Percentage | number |  |  |
| Cad With Process Loss | number |  |  |
| Feeding Percentage | number |  |  |
| Sample Yarn | number |  |  |
| Bulk Yarn | number |  |  |
| Total Req Yarn | number |  |  |
| Stock Yarn | number |  |  |
- গ্রিড কলাম: , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , , All No Budget Draft Process Pending Approved Rejected
- সেকশন: Total No of Style, 13, Total Order Qty, 165,026 Pcs, Total FOB Value, $786,309.70, Total Sample Yarn, 154.00 Kg, Total Bulk Knit Yarn, 68,254.84 Kg, Total Req Knit Yarn, 68,408.84 Kg, Total Bulk Woven Fabric, 0.00 Yds, Total Req Woven Fabric
- বাটন/অ্যাকশন: Filter, Clear, Edit, Cancel, Create Budget, Delete, Copy, Print, Column View, Select All, Clear All

#### Add New Requisition

`/apps/requisitions/knit-woven/create`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Entry Type | select | ✔ | Knit (Kg), Woven (Yds) |
| Factory Id | select | ✔ | Select Factory, HAL, NCL, NFL |
| Buyer Id | select | ✔ | Select Buyer, Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Limited.,  |
| Brand Id | select | ✔ | Select Brand, 356-JGTOD Dress, 436-JBTOD F.uk Jogger, ABC, ADMIRAL, ADMIRAL SPORTS, American Tall, Anpanman, Apunto, ARE |
| Season Id | select | ✔ | Select Season, 508, 508, 509, 509, 510, 510, 511, 511, 512, 512, 513 |
| Delivery Date | date |  |  |
| Order Booking Date | date |  |  |
| Po Wise Rfi Sd Date | date |  |  |
| Yarn Booking Date | date |  |  |
| No Of Style | number | ✔ |  |
| Style | text | ✔ |  |
| Order Type | select | ✔ | Select Order Type, BLOCK, BLOCK, BLOCK NOS, BLOCK NOS, Buying File, Buying File, Cancelled, Cancelled, Cancelled + Liabi |
| Job No | text |  |  |
| e.g. 2024-ABC-001 | text |  |  |
| Dept Id | select | ✔ | Select Dept, Baby, Boys, Girls, Kids, Kids-Boys, Kids-Girls, Ladies, Mens, Toddler Boys, Toddler Girls, Unisex |
| Cad Image | file | ✔ |  |
| Item Id | select | ✔ | Select Item, Dress, Dress, Hoody Jacket, Hoody Jacket, Jog Pant/ Pajama, Jog Pant/ Pajama, Legging, Legging, Long pant,  |
| Wash Type Id | select | ✔ | Select Wash Type, Acid Wash, Cold Pigment Dye, Deep Dye Wash, Enzyme Wash, Garments Dye, Garments Dye+Acid Wash, Garment |
| Order Color | text | ✔ |  |
| Fabric Type | select | ✔ | Select Fabric Type, 12x4 Rib Elastane, 1X1 Fineliner Rib Elasthan, 1X1 Flat knit, 1x1 Flat Knit Rib, 1X1 Flat Knit Rib E |
| Fabric Composition | select |  | Select Fabric Composition, 100% Cotton, 100% Cotton Melange, 100% Cotton Slub, 100% Cotton Slub Melange, 52% Cotton 44%  |
| Fabrication | text |  |  |
| Gsm Id | select | ✔ | Select GSM, 100 GSM, 110 GSM, 120 GSM, 130 GSM, 140 GSM, 150 GSM, 160 GSM, 170 GSM, 180 GSM, 190 GSM, 200 GSM |
| Sustainability Id | select |  | Select Sustainability, BCI, BCI + Lycra, BCI + Recycle, BCI GRS, BCI RCS, Bio + Recycle, BIO GOTS, BIO OCS, C2C, C2C BCI |
| Parts | select | ✔ | Select Part, [Body + Sleeve Placket + Collar ], 3D Applique, AOP, Applique fabrics, Arm Hole Piping, Arm Hole Rib, Armho |
| Yarn Type | select | ✔ | Select Yarn Type, 100% Card Combed Cotton, 100% Card Compact Cotton, 100% Card Compact Cotton White, 100% Card Compact V |
| Yarn Count | select | ✔ | Select Yarn Count, 10/1 Ne, 100D, 100D/100F, 100D/144F, 100D/34F, 100D/36 SD, 100D/36F, 100D/38F, 100D/48F, 100D/72F, 10 |
| Composition | select | ✔ | Select Composition, [100% Acrylic], [100% Askin Polyester], [100% BCI Cotton Melange], [100% BCI Cotton Slub], [100% BCI |
| Additional Process Id | select |  | Select Additional Process, Acid Wash, Afson Print, Air Turning, All Over Embroidery, All Tests, Anti Bactarial+Cool IT f |
| Quantity Ticket | number | ✔ |  |
| Pac | number | ✔ |  |
| Order Qty | number | ✔ |  |
| Fob Price | number | ✔ |  |
| Fob Value | number |  |  |
| Quantity Pcs | number | ✔ |  |
| Cutting Percentage | number | ✔ |  |
| Qty With Percentage | number |  |  |
| Cad Consumption | number | ✔ |  |
| Process Loss Percentage | number | ✔ |  |
| Cad With Process Loss | number |  |  |
| Feeding Percentage | number | ✔ |  |
| Sample Yarn | number |  |  |
| Bulk Yarn | number |  |  |
| Total Req Yarn | number |  |  |
| Stock Yarn | number |  |  |
- গ্রিড কলাম: S/L, Entry Type, Factory, Buyer, Brand, Season, Delivery Date, Order Booking Date, PO-wise RFI/SD Date, Yarn Booking Date, No of Style, Style, Order Type, Job No, Contract No, Dept, Artwork, Item, Wash Type, Order Color, Fabric Type, Fabric Composition, Fabrication, GSM, Sustainabilities, Parts, Yarn Type, Yarn Count, Composition, Additional Process, Quantity (Ticket), PAC, Order Qty, FOB Price, FOB Value, Quantity Pcs, Cutting %, Qty with %, CAD/CON (kg), Process Loss %, CAD with Process Loss (kg), Feeding %, Sample Yarn (kg), Bulk Yarn (kg), Total Req Yarn (kg), Stock Yarn, Action
- সেকশন: Create Knit/Woven Requisition
- বাটন/অ্যাকশন: Add Row, Save Draft, Complete, Cancel, Back to list, Remove

#### Draft Requisition

`/apps/requisitions/knit-woven/drafts`

**ভিউ**
- গ্রিড কলাম: Sl, Draft, Buyer, Style, Rows, Created By, Last Saved, Action
- বাটন/অ্যাকশন: Add New Requisition, Open, Delete

#### Create Budget

`/apps/requisitions/knit-woven/pivot`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select-multiple |  | Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Limited., BC Tex Knitwea |
| Buyer | textarea |  |  |
| Brand | select-multiple |  | 356-JGTOD Dress, 436-JBTOD F.uk Jogger, ABC, ADMIRAL, ADMIRAL SPORTS, American Tall, Anpanman, Apunto, ARENA, AUTHENTIC, |
| Brand | textarea |  |  |
| Style | select-multiple |  | 2174190, 2191043, 2191481, 2194681, 2195774, 21900463, 2274357 Holden, 2274534 Lionel, 2274681 Lucifer, 2274696 Lisboa,  |
| Style | textarea |  |  |
| Item | select-multiple |  | Dress, Dress, Hoody Jacket, Hoody Jacket, Jog Pant/ Pajama, Jog Pant/ Pajama, Legging, Legging, Long pant, Long pant, Ot |
| Item | textarea |  |  |
| Job No | select-multiple |  | 26HAL00161, 26NCL00415, 26NCL00416, 26NCL00417, 26NCL0043 |
| Job No | textarea |  |  |
| — | select |  | PB-CNA-1026-0018 — CnA — Finalized 10-Nov-2026, Draft #14 — CnA — Draft, Draft #16 — CnA — Draft, PB-SOL-1126-0020 — S.O |
- সেকশন: Pre-Budget Approval, Use from Stock
- বাটন/অ্যাকশন: Filter, Clear, Draft, Process, Load, Budget Edit, Cancel Edit, Cancel, OK — Fill All Rows

#### Budget Status

`/apps/requisitions/knit-woven/budget-status`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No, Buyer, Contract No | text |  |  |
| Buyer Name | select |  | Buyer: All, CnA, S.Oliver, ZXY |
| Job No | select |  | Job No: All, 26HAL00161, 26NCL00415, 26NCL00416, 26NCL00417, 26NCL0043 |
| Budget No | select |  | Pre-Budget No: All, PB-CNA-1026-0018, PB-SOL-1126-0020, PB-ZXY-1126-0021 |
| Store Status | select |  | Store: All, Store: Processed, Store: Stock Submitted |
| Accessories Status | select |  | Accessories: All, Accessories: Pending, Accessories: Partial, Accessories: Created |
| Costing Status | select |  | Costing: All, Costing: Sent to Costing, Costing: Costing Submitted |
- গ্রিড কলাম: SL, PRE-BUDGET NO, REFERENCE, BUYER, CONTRACT NO, BUDGET STATUS, STORE STATUS, USE FROM STOCK QTY, USE FROM STOCK VALUE, ACCESSORIES STATUS, ACCESSORIES QTY, ACCESSORIES VALUE, COSTING STATUS, COSTING VALUE, ACTION
- সেকশন: Total Budget, 3, Budget Pending, 3, Budget Approved, 0, Total Order Qty, 165,026, Total Order Value, $784,509.70, Total Yarn Cost, $240,428.58, Total Woven Cost, $0.00, Total Knitting Cost
- বাটন/অ্যাকশন: Filter, View, Load Yarn Budget, Delete

#### Stock Yarn Use

`/apps/requisitions/knit-woven/store-stock`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No, Buyer, Contract No | text |  |  |
| Status | select |  | Processed, Stock Submitted, All |
- গ্রিড কলাম: SL, PRE-BUDGET NO, REFERENCE, BUYER, CONTRACT NO, TOTAL YARN REQ (KG), TOTAL WOVEN REQ (YDS), SENT BY, SENT AT, SUBMITTED BY, SUBMITTED AT, STATUS, ACTION
- বাটন/অ্যাকশন: Filter, View, Edit, Delete

#### Knit/Dying Costing

`/apps/requisitions/knit-woven/costing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No, Buyer, Contract No | text |  |  |
| Status | select |  | Sent to Costing, Costing Submitted, All |
- গ্রিড কলাম: SL, PRE-BUDGET NO, REFERENCE, BUYER, CONTRACT NO, SENT BY, SENT AT, SUBMITTED BY, SUBMITTED AT, STATUS, ACTION
- বাটন/অ্যাকশন: Filter, View, Edit, Delete, Fill Cost

#### Budget Finalized

`/apps/requisitions/knit-woven/pivot/finalized`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No, Buyer or Contract No | text |  |  |
| Approval | select |  | All Approval, Pending Approval, Approved, Rejected |
| Store — Use from Stock | checkbox |  |  |
| Accessories Pre-Budget | checkbox |  |  |
| Costing | checkbox |  |  |
| Yarn Budget / Planning | checkbox |  |  |
| Other | checkbox |  |  |
| What's wrong with this budget? | textarea | ✔ |  |
- গ্রিড কলাম: SL, PRE-BUDGET NO, BUYER, STYLE NO, FINALIZED BY, FINALIZED AT, APPROVAL, APPROVED BY, APPROVED AT, APPROVE / REJECT, ACTION
- সেকশন: Reject Budget
- বাটন/অ্যাকশন: Search, Show Deleted, Trash, Approve, Reject, View, Load, Budget Print, Cost Print, Stock Print, Accessories Print, Delete, Cancel, Reject Budget

#### Stock Yarn Declaration

`/apps/requisitions/knit-woven/stock-declaration`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Pre-Budget No (e.g. PB-ZXY-0926-0001) | text |  |  |
- গ্রিড কলাম: SL, PRE-BUDGET NO, BUYER, CONTRACT NO, STOCK ALLOCATIONS, TOTAL VALUE (USD), APPROVAL, ACTION
- বাটন/অ্যাকশন: Search, View, Delete

#### Budget Report

`/apps/requisitions/knit-woven/report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BUYER All CnA S.Oliver ZXY | select |  | All, CnA, S.Oliver, ZXY |
| BUYER All CnA S.Oliver ZXY | select |  | All, HAL, NCL |
| BUYER All CnA S.Oliver ZXY | select |  | All, Dress, Shorts Pant, Shorts Pant, T-Shirt, T-Shirt, Tank Top |
| BUYER All CnA S.Oliver ZXY | select |  | All, 2174190, 2191043, 2191481, 2194681, 2195774, 2274357 Holden, 2274534 Lionel, 2274681 Lucifer, 2274696 Lisboa, 22746 |
| BUYER All CnA S.Oliver ZXY | select |  | All, 26HAL00161, 26NCL00415, 26NCL00416, 26NCL00417, 26NCL0043 |
| BUYER All CnA S.Oliver ZXY | select |  | All, PB-CNA-1026-0018, PB-SOL-1126-0020, PB-ZXY-1126-0021 |
| BUYER All CnA S.Oliver ZXY | select |  | All, No Budget, Draft, Process, Pending, Approved, Rejected |
| BUYER All CnA S.Oliver ZXY | date |  |  |
| BUYER All CnA S.Oliver ZXY | date |  |  |
| Cost breakdown | checkbox |  |  |
| Search this table… | search |  |  |
| Rows per page | select |  | 25, 50, 100, All |
- গ্রিড কলাম: Factory, Buyers, Budgets / Styles, Qty (pcs), Value ($), Required (kg), From Stock (kg), Purchase (kg), Excess (kg), Yarn Value ($), Excess Value ($), Knitting, Dyeing, Yarn Dyeing, AOP, Accessories, Embellishment, CM, Total Cost, Profit, Margin
- গ্রিড কলাম: Pre-Budget No, Buyer, Style No, Budget Date, Shipment, Status, Qty (pcs), Value ($), Required (kg), From Stock (kg), Purchase (kg), Excess (kg), Yarn Value ($), Excess Value ($), Knitting, Dyeing, Yarn Dyeing, AOP, Accessories, Embellishment, CM, Total Cost, Profit, Margin
- গ্রিড কলাম: Style No, Buyer, Pre-Budget No, Job No, Qty (pcs), Value ($), Required (kg), From Stock (kg), Purchase (kg), Excess (kg), Yarn Value ($), Excess Value ($), Knitting, Dyeing, Yarn Dyeing, AOP, Accessories, Embellishment, CM, Total Cost, Profit, Margin
- গ্রিড কলাম: Job No, Buyer, Style No, Qty (pcs), Value ($), Required (kg), From Stock (kg), Purchase (kg), Excess (kg), Yarn Value ($), Excess Value ($), Knitting, Dyeing, Yarn Dyeing, AOP, Accessories, Embellishment, CM, Total Cost, Profit, Margin
- গ্রিড কলাম: Item, Buyers, Budgets / Styles, Qty (pcs), Value ($), Required (kg), From Stock (kg), Purchase (kg), Excess (kg), Yarn Value ($), Excess Value ($), Knitting, Dyeing, Yarn Dyeing, AOP, Accessories, Embellishment, CM, Total Cost, Profit, Margin
- বাটন/অ্যাকশন: Apply, By Factory 2, By Budget 3, By Style 13, By Job No 6, By Item 6, Excel, Print

#### Yarn Report

`/apps/requisitions/knit-woven/yarn-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, 10/1 Ne, 20D, 22S, 24/1 Ne, 26S, 28/1 Ne, 30/1 Ne, 30S, 32/1 Ne, 40/1 Ne, 40D |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, 40D Spandex, 100% Carded Cotton White, 100% COMBED COTTON, 100% Cotton, 100% Cotton Viscose, Black Elastane, Cotton |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, 100% Physical BCI Card Compact Cotton, 100% Physical BCI Cotton Slub, 100% Recycle Polyester, CVC 60/40 [ 60% BCI C |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, 140 GSM, 160 GSM, 180 GSM, 200 GSM, 210 GSM, 220 GSM, 230 GSM, 240 GSM, 250 GSM, 260 GSM, 300 GSM |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, 1X1 Lycra Rib, 1X1 Lycra RIB White, 1x1 Rib, 1X1 RIB. YD, Design Jersey, Flat Back Rib, Flat Rib, Fleece, Single Je |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | select |  | All, CnA, S.Oliver, ZXY |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | date |  |  |
| YARN COUNT All 10/1 Ne 20D 22S 24/1 Ne 26S 28/1 Ne 30/1 Ne 30S 32/1 Ne 40/1 Ne 40D 100D/36F 150D/48F | date |  |  |
| Search this table… | search |  |  |
| Rows per page | select |  | 25, 50, 100, All |
- গ্রিড কলাম: Yarn Count, Budgets, Styles, Required (kg), From Stock (kg), Need to Purchase (kg), Purchase (kg), Excess / Shortage (kg), Excess (%), Avg Price ($ / kg), Stock Use, Main (Need), PI Value, Excess, Short Purchase, PI Total
- গ্রিড কলাম: Composition, Budgets, Styles, Required (kg), From Stock (kg), Need to Purchase (kg), Purchase (kg), Excess / Shortage (kg), Excess (%), Avg Price ($ / kg), Stock Use, Main (Need), PI Value, Excess, Short Purchase, PI Total
- গ্রিড কলাম: Yarn Type, Budgets, Styles, Required (kg), From Stock (kg), Need to Purchase (kg), Purchase (kg), Excess / Shortage (kg), Excess (%), Avg Price ($ / kg), Stock Use, Main (Need), PI Value, Excess, Short Purchase, PI Total
- গ্রিড কলাম: GSM, Budgets, Styles, Required (kg), From Stock (kg), Need to Purchase (kg), Purchase (kg), Excess / Shortage (kg), Excess (%), Avg Price ($ / kg), Stock Use, Main (Need), PI Value, Excess, Short Purchase, PI Total
- গ্রিড কলাম: Fabric Type, Budgets, Styles, Required (kg), From Stock (kg), Need to Purchase (kg), Purchase (kg), Excess / Shortage (kg), Excess (%), Avg Price ($ / kg), Stock Use, Main (Need), PI Value, Excess, Short Purchase, PI Total
- বাটন/অ্যাকশন: Apply, By Yarn Count 13, By Composition 15, By Yarn Type 13, By GSM 13, By Fabric Type 15, Excel, Print

#### Short Purchase

`/apps/requisitions/knit-woven/short-purchase`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BUYER All CnA S.Oliver | select |  | All, CnA, S.Oliver |
| BUYER All CnA S.Oliver | select |  | All, PB-CNA-1026-0018, PB-SOL-1126-0020 |
| BUYER All CnA S.Oliver | select |  | All, Knit Yarn, Woven Fabric |
| BUYER All CnA S.Oliver | select |  | All, 30S, 40/1 Ne |
| BUYER All CnA S.Oliver | select |  | All, Draft, Process, Pending, Approved, Rejected |
| BUYER All CnA S.Oliver | select |  | All, Purchase, Not Purchase |
| BUYER All CnA S.Oliver | date |  |  |
| BUYER All CnA S.Oliver | date |  |  |
| Search this table… | search |  |  |
| Rows per page | select |  | 25, 50, 100, All |
- গ্রিড কলাম: Pre-Budget No, Buyer, Style No, Status, Type, Yarn Count / Fabrication, Composition, Fabric Type / Style, Unit, Need to Purchase (kg / yds), Now Purchase (kg / yds), Shortage (kg / yds), Action, Price ($ / unit), Short Value, Recorded / Updated, By
- বাটন/অ্যাকশন: Apply, Excel, Print

#### Excess Purchase

`/apps/requisitions/knit-woven/excess-purchase`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BUYER All CnA S.Oliver | select |  | All, CnA, S.Oliver |
| BUYER All CnA S.Oliver | select |  | All, PB-CNA-1026-0018, PB-SOL-1126-0020 |
| BUYER All CnA S.Oliver | select |  | All, Knit Yarn, Woven Fabric |
| BUYER All CnA S.Oliver | select |  | All, 10/1 Ne, 22S, 24/1 Ne, 26S, 28/1 Ne, 30/1 Ne, 40/1 Ne |
| BUYER All CnA S.Oliver | select |  | All, Draft, Process, Pending, Approved, Rejected |
| BUYER All CnA S.Oliver | date |  |  |
| BUYER All CnA S.Oliver | date |  |  |
| Search this table… | search |  |  |
| Rows per page | select |  | 25, 50, 100, All |
- গ্রিড কলাম: Pre-Budget No, Buyer, Style No, Status, Type, Yarn Count / Fabrication, Composition, Fabric Type / Style, Unit, Need to Purchase (kg / yds), Now Purchase (kg / yds), Excess (kg / yds), Price ($ / unit), Excess Value, Recorded / Updated, By
- বাটন/অ্যাকশন: Apply, Excel, Print

#### Audit Log

`/apps/requisitions/knit-woven/audit-log`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Module | select |  | Module: All, Requisition, Yarn Budget, Store — Use from Stock, Knit/Dying Costing, Accessories BOM, Budget Finalized |
| Action | select |  | Action: All, Created, Updated, Deleted, Bulk Copied, Repeat Order, Bulk Deleted, Bulk Updated, Draft Saved, Processed, F |
| User Id | select |  | User: All, Abeer, Admin User, Foyzur Rahman, Sami, Shahadat Hossain, Shudarshan Kumar Biman, Tanjim Mahmood |
| Budget No | select |  | Pre-Budget No: All, PB-ZXY-1126-0021, PB-ZXY-1026-0011, PB-ZXY-1026-0010, PB-ZXY-1026-0009, PB-ZXY-1026-0008, PB-ZXY-092 |
| Date From | date |  |  |
| Date To | date |  |  |
- গ্রিড কলাম: SL, WHEN, MODULE, ACTION, PRE-BUDGET NO, SUMMARY, USER, 
- সেকশন: Costing Submitted — PB-ZXY-1126-0021, Stock Submitted — PB-SOL-1126-0020, styles_added — PB-SOL-1126-0020, styles_removed — PB-SOL-1126-0020, styles_added — PB-SOL-1126-0020, Stock Submitted — PB-ZXY-1126-0021, BOM Created — PB-ZXY-1126-0021, Costing Submitted — PB-ZXY-1126-0021, Created, Created, Bulk Updated, Bulk Updated, Bulk Updated, Bulk Updated, Bulk Updated
- বাটন/অ্যাকশন: Filter, Details
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
