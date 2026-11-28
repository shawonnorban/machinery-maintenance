# Order Summary

> **স্ট্যাটাস:** ✅ সম্পন্ন (১০টা স্ক্রিন: Dashboard, View All, Create New, LDD/RFI Pending, Buyer Month Wise, Audit Log, Column Setup, Merchandising Team, Mail Schedule)
> **মডিউল:** [Order Summary](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `32/01`

**⬅ ফিরে যান:** [Order Summary](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Norban ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | View All | `/order-summary-v1` | 1 | 396 | 0 |
| 2 | Create New | `/order-summary/create-v1` | 1 | 161 | 0 |
| 3 | Report Dashboard | `/order-summary/reports` | 1 | 0 | 0 |
| 4 | LDD Pending Summary | `/order-summary/reports/ldd-summary` | 1 | 8 | 0 |
| 5 | RFI Pending Summary | `/order-summary/reports/rfi-summary` | 1 | 8 | 0 |
| 6 | Buyer Month Wise | `/order-summary/reports/buyer-month-wise` | 1 | 6 | 0 |
| 7 | Audit Log | `/order-summary/audit-log` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### View All

`/order-summary-v1`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select-multiple |  | Carrefour, Next |
| Buyer | textarea |  |  |
| Brand | select-multiple |  |  |
| Brand | textarea |  |  |
| Style | select-multiple |  | 193284, AA6283, AA6289, AB4672, AB4673, AJ1664, AL7114 HW CONTR STCH STONE, AM7911 VEST STONE COPEN, AM7934 HW CONTR STC |
| Style | textarea |  |  |
| Item | select-multiple |  | Long pant, Polo, Sweat Top, T-Shirt, Woven Pant |
| Item | textarea |  |  |
| Job No | select-multiple |  | NCL-24-00798, NCL-24-00799, NCL-24-00800, NCL-24-00801, NCL-24-00802, NCL-24-01080, NCL-24-01327, NCL-24-01562, NCL-24-0 |
| Job No | textarea |  |  |
| Contract No | select-multiple |  | CARREFOUR/027/23, NEXT/013/24, NEXT/044/24 |
| Contract No | textarea |  |  |
| Entry From | date |  |  |
| Entry To | date |  |  |
| PO-wise RFI/SD From | date |  |  |
| PO-wise RFI/SD To | date |  |  |
| Serial No | checkbox |  |  |
| Factory | checkbox |  |  |
| Department | checkbox |  |  |
| Class | checkbox |  |  |
| Order Type | checkbox |  |  |
| TP Rcvd Date | checkbox |  |  |
| Artwork | checkbox |  |  |
| SMV | checkbox |  |  |
| Season / Quarter | checkbox |  |  |
| Style Number / Name | checkbox |  |  |
| JOB No | checkbox |  |  |
| Last Update Date | checkbox |  |  |
| Commercial Contract Number | checkbox |  |  |
| PO Number | checkbox |  |  |
| Shipment ID Number | checkbox |  |  |
| Colors | checkbox |  |  |
| Quantity (Ticket) | checkbox |  |  |
| PAC | checkbox |  |  |
| Quantity (PCS) | checkbox |  |  |
| Delay Planning Inspection | checkbox |  |  |
| Delay RFI Proposed | checkbox |  |  |
| Delay LDD Proposed | checkbox |  |  |
| Buyer Feedback On Delay | checkbox |  |  |
| Reprint Date | checkbox |  |  |
| Reprint LDD/ETD | checkbox |  |  |
| Reprint RFI/SD | checkbox |  |  |
| Reprint Days Left | checkbox |  |  |
| Reprint Shipment Mode | checkbox |  |  |
| Discount/Penalty % | checkbox |  |  |
| Discount Value | checkbox |  |  |
| PO Wise LDD/ETD | checkbox |  |  |
| PO Wise RFI/SD | checkbox |  |  |
| Days Left In Hand | checkbox |  |  |
| Shipment Mode | checkbox |  |  |
| Shipout Date | checkbox |  |  |
| Shipout Condition | checkbox |  |  |
| Shipment Status | checkbox |  |  |
| Style Description | checkbox |  |  |
| Embellishments | checkbox |  |  |
| Zipper | checkbox |  |  |
| Button | checkbox |  |  |
| Woven Trim | checkbox |  |  |
| Import Item | checkbox |  |  |
| Fabric Category | checkbox |  |  |
| Fabric Type | checkbox |  |  |
| GSM | checkbox |  |  |
| Composition | checkbox |  |  |
| Fabric Details | checkbox |  |  |
| Fabric Description | checkbox |  |  |
| Dye Type Solid Or YD | checkbox |  |  |
| Fabric Finish Type | checkbox |  |  |
| AOP | checkbox |  |  |
| Wash Info At Garmenting | checkbox |  |  |
| Sustainability | checkbox |  |  |
| Nego. Order Confm. Date | checkbox |  |  |
| Yarn Lead Time (Days) | checkbox |  |  |
| Yarn Inhouse Req. | checkbox |  |  |
| Textile Lead Time (Days) | checkbox |  |  |
| Fab. Inhouse Req. | checkbox |  |  |
| Print/Embr. Time (Days) | checkbox |  |  |
| Garment Lead Time (Days) | checkbox |  |  |
| Add LDD-RFI Time (Days) | checkbox |  |  |
| Total Lead Time (Days) | checkbox |  |  |
| Proposed LDD/ETD | checkbox |  |  |
| Actual Order Confirm Date | checkbox |  |  |
| Actual Leadtime Vs Confirm | checkbox |  |  |
| PO Need Date Vs Act RFI | checkbox |  |  |
| Actual PO Issue Date | checkbox |  |  |
| Act. Leadtime Vs PO | checkbox |  |  |
| FOB | checkbox |  |  |
| FOB Value | checkbox |  |  |
| Price Status | checkbox |  |  |
| Retail Price | checkbox |  |  |
| Avg Yarn Price/Kg | checkbox |  |  |
| Yarn Value As Per Requirement | checkbox |  |  |
| Yarn Value % | checkbox |  |  |
| Yarn Purchase Balance Value | checkbox |  |  |
| Trims Cost/Pc | checkbox |  |  |
| Trims Value | checkbox |  |  |
| Trims Value % | checkbox |  |  |
| Short Order Status | checkbox |  |  |
| Yarn Count & Yarn Composition | checkbox |  |  |
| Gross Consump. (KG/Doz) | checkbox |  |  |
| Knitting Dyeing Process Loss % | checkbox |  |  |
| Cutting % | checkbox |  |  |
| Cutting Req. Quantity (PCS) | checkbox |  |  |
| Total Yarn Requirement (KG) | checkbox |  |  |
| Yarn Purchased Quantity (KG) | checkbox |  |  |
| Yarn Purchase Balance (KG) | checkbox |  |  |
| Yarn Mill Name | checkbox |  |  |
| Yarn Booking Date | checkbox |  |  |
| Yarn Status | checkbox |  |  |
| Woven Fab. Booking Date | checkbox |  |  |
| Woven Fabric Mill Name | checkbox |  |  |
| Woven Inhouse Plan Date | checkbox |  |  |
| Woven Inhouse Actual Date | checkbox |  |  |
| Fabric Booking Date | checkbox |  |  |
| Fabric Status | checkbox |  |  |
| Fabric Closing Plan Date | checkbox |  |  |
| Fabric Delay From PCD (Days) | checkbox |  |  |
| Trims Booking Date | checkbox |  |  |
| Trims Status | checkbox |  |  |
| Base Hanger Submit Date | checkbox |  |  |
| NIS Submit Date | checkbox |  |  |
| Base Hanger Test Date | checkbox |  |  |
| Base Hanger Approve Date | checkbox |  |  |
| Base Hanger Status | checkbox |  |  |
| 1st Sample Submit Date | checkbox |  |  |
| 1st Sample Comments Date | checkbox |  |  |
| 1st/Proto Status | checkbox |  |  |
| Print/Embr. S/Off Submit Date | checkbox |  |  |
| Print/Embr. S/Off Comments Date | checkbox |  |  |
| Print/Embr. S/Off Status | checkbox |  |  |
| Black SS/Fit Submit Date | checkbox |  |  |
| Black SS/Fit Approve Date | checkbox |  |  |
| Black SS/Fit Status | checkbox |  |  |
| Test Sample Status | checkbox |  |  |
| GSS/PP Submit Date | checkbox |  |  |
| GSS/PP Comments Date | checkbox |  |  |
| GSS/PP Status | checkbox |  |  |
| Photo/Press Send Date | checkbox |  |  |
| Shipping Sample Send Date | checkbox |  |  |
| FFP Status | checkbox |  |  |
| FFP Date | checkbox |  |  |
| Prod. File Target | checkbox |  |  |
| Prod. File H/O Date | checkbox |  |  |
| Prod. File Status | checkbox |  |  |
| Bulk Hanger Submit Date | checkbox |  |  |
| Bulk Hanger Approve Date | checkbox |  |  |
| Bulk Hanger Status | checkbox |  |  |
| PCD | checkbox |  |  |
| PSD | checkbox |  |  |
| PED | checkbox |  |  |
| Garment Production Status | checkbox |  |  |
| RFI Plan Date | checkbox |  |  |
| RFI Diff. In Plan | checkbox |  |  |
| RFI Plan Remarks | checkbox |  |  |
| Shipout Quantity (Ticket) | checkbox |  |  |
| Shipout Quantity (PCS) | checkbox |  |  |
| Short/Excess (Ticket) | checkbox |  |  |
| Short/Excess (PCS) | checkbox |  |  |
| Shipout Value (USD) | checkbox |  |  |
| Discounted FOB Price | checkbox |  |  |
| Discounted Shipout Value | checkbox |  |  |
| Total Discounted Value | checkbox |  |  |
| Short/Excess Ship Value | checkbox |  |  |
| Reason Of Discount/Penalty | checkbox |  |  |
| Shipout Commercial Contract No | checkbox |  |  |
| Shipment Invoice Number | checkbox |  |  |
| ETA Date | checkbox |  |  |
| Payment Rcvd Date | checkbox |  |  |
| Payment Rcvd Status | checkbox |  |  |
| Marketing Merchandiser | checkbox |  |  |
| Production Merchandiser | checkbox |  |  |
| Buyer Merchandiser | checkbox |  |  |
| Remarks | checkbox |  |  |
| Per Page | select |  | 10, 25, 50, 100, 200 |
| — | checkbox |  |  |
| Selected[0] | checkbox |  |  |
| Serial No[0] | text |  |  |
| Factory[0] | select |  | NCL |
| Buyer[0] | select |  | Next |
| Department[0] | select |  | Mens |
| Brand[0] | select |  |  |
| Class[0] | select |  | 346 |
| Order Type[0] | select |  | Confirmed PO |
| Tp Rcvd Date[0] | text |  |  |
| Artwork[0] | file |  |  |
| Item[0] | select |  | T-Shirt |
| Smv[0] | text |  |  |
| Season Quarter[0] | select |  | Spring 2025 |
| Style Number Name[0][] | select-multiple |  | AY4912 3PK HW STRIPE PLAIN |
| Job No[0][] | select-multiple |  | NCL-24-01760 |
| Commercial Contract Number[0][] | select-multiple |  | NEXT/044/24 |
| Po Number[0][] | select-multiple |  | MJLWT/03120 |
| Shipment Id Number[0][] | select-multiple |  | KR8612270 |
| Colors[0][] | select-multiple |  | GRAY PINSTRIPE, NAVY, WHITE |
| Quantity Ticket[0] | text |  |  |
| Pac[0] | text |  |  |
| Delay Planning Inspection[0] | text |  |  |
| Delay Rfi Proposed[0] | text |  |  |
| Delay Ldd Proposed[0] | text |  |  |
| Buyers Feedback On Delay[0] | text |  |  |
| Reprint Date[0] | text |  |  |
| Reprint Ldd Etd[0] | text |  |  |
| Reprint Shipment Mode[0] | select |  |  |
| Discount Penalty Percent[0] | text |  |  |
| Po Wise Ldd Etd[0] | text |  |  |
| Shipment Mode[0] | select |  | FOB SEA |
| Shipout Date[0] | text |  |  |
| Shipout Condition[0] | select |  |  |
| Shipment Status[0] | select |  | Ship Out |
| Style Description[0] | text |  |  |
| Emballishments[0] | select |  | N / A |
| Zipper[0] | select |  | N / A |
| Button[0] | select |  | N / A |
| Woven Trim[0] | select |  | WOVEN TRIM - Pending Approval |
| Import Item[0] | select |  |  |
| Fabric Category Id[0] | select |  |  |
| Fabric Type[0] | select |  | Single Jersey |
| Gsm[0] | select |  | 300 |
| Composition[0] | select |  |  |
| Fabric Description[0] | text |  |  |
| Dye Type Solid Or Yd[0] | select |  | Multiple Type |
| Fabric Finish Type[0] | select |  | Regular |
| Aop[0] | select |  |  |
| Wash Info At Garmenting[0] | select |  |  |
| Sustainability[0] | select |  | BCI |
| Nego Order Confm Date[0] | text |  |  |
| Yarn Lead Time Days[0] | number |  |  |
| Textile Lead Time Days[0] | number |  |  |
| Print Embr Time Days[0] | number |  |  |
| Garment Lead Time Days[0] | number |  |  |
| Add Ldd Rfi Time Days[0] | number |  |  |
| Actual Order Confirm Date[0] | text |  |  |
| Actual Po Issue Date[0] | text |  |  |
| Fob[0] | text |  |  |
| Price Status[0] | select |  | CONFIRMED |
| Retail Price[0] | text |  |  |
| Avg Yarn Price Per Kg[0] | text |  |  |
| Trims Cost Per Pc[0] | text |  |  |
| Short Order Status[0] | select |  | Processing |
| Yarn Count Yarn Composition[0] | text |  |  |
| Gross Consump Kg Doz[0] | text |  |  |
| Knitting Dyeing Process Loss Percent[0] | text |  |  |
| Cutting Percent[0] | text |  |  |
| Yarn Purchased Quantity Kg[0] | text |  |  |
| Yarn Mill Name[0] | select |  |  |
| Yarn Booking Date[0] | text |  |  |
| Yarn Status[0] | select |  | Ship Out |
| Woven Fab Booking Date[0] | text |  |  |
| Woven Fabric Mill Name[0] | select |  |  |
| Woven Inhouse Plan Date[0] | text |  |  |
| Woven Inhouse Actual Date[0] | text |  |  |
| Fabric Booking Date[0] | text |  |  |
| Fabric Status[0] | select |  | Fabric Delivery Done |
| Fabric Closing Plan Date[0] | text |  |  |
| Trims Booking Date[0] | text |  |  |
| Trims Status[0] | select |  | ALL Trims Inhouse |
| Base Hanger Submit Date[0] | text |  |  |
| Nis Submit Date[0] | text |  |  |
| Base Hanger Test Date[0] | text |  |  |
| Base Hanger Approv Date[0] | text |  |  |
| Base Hanger Status[0] | select |  | APPROVED |
| First Sample Submit Date[0] | text |  |  |
| First Sample Comnts Date[0] | text |  |  |
| Proto Status[0] | select |  | APPROVED |
| Print Embr Soff Submit Date[0] | text |  |  |
| Print Embr Soff Comets Date[0] | text |  |  |
| Print Embr Soff Status[0] | select |  | NOT Submitted |
| Black Ss Fit Submit Date[0] | text |  |  |
| Black Ss Fit Approv Date[0] | text |  |  |
| Black Ss Fit Status[0] | select |  | APPROVED |
| Test Sample Status[0] | select |  | NOT Submitted |
| Gss Pp Submit Date[0] | text |  |  |
| Gss Pp Comments Date[0] | text |  |  |
| Gss Pp Status[0] | select |  | APPROVED |
| Photo Press Send Date[0] | text |  |  |
| Shipping Sample Send Date[0] | text |  |  |
| Ffp Status[0] | select |  | APPROVED |
| Ffp Date[0] | text |  |  |
| Prod File Handover Date[0] | text |  |  |
| Prod File Status[0] | select |  | Prod. File Given |
| Bulk Hangr Submit Date[0] | text |  |  |
| Bulk Hangr Approv Date[0] | text |  |  |
| Bulk Hangr Status[0] | select |  | APPROVED |
| Pcd[0] | text |  |  |
| Psd[0] | text |  |  |
| Ped[0] | text |  |  |
| Garment Production Status[0] | select |  | Not Started |
| Rfi Plan Date[0] | text |  |  |
| Rfi Plan Remarks[0] | text |  |  |
| Shipout Quantity Ticket[0] | text |  |  |
| Shipout Quantity Pcs[0] | text |  |  |
| Final Discount Penalty Percent[0] | text |  |  |
| Reason Of Discount Penalty[0] | text |  |  |
| Shipout Commercial Contract Number[0] | text |  |  |
| Shipment Invoice Number[0] | text |  |  |
| Eta Date[0] | text |  |  |
| Payment Rcvd Date[0] | text |  |  |
| Payment Rcvd Status[0] | select |  |  |
| Marketing Merchandiser[0] | select |  | Nur-Alam |
| Production Merchandiser[0] | select |  |  |
| Buyer Merchandiser[0] | select |  |  |
| Remarks[0] | text |  |  |
| Selected[1] | checkbox |  |  |
| Serial No[1] | text |  |  |
| Factory[1] | select |  | NCL |
| Buyer[1] | select |  | Next |
| Department[1] | select |  | Mens |
| Brand[1] | select |  |  |
| Class[1] | select |  | 346 |
| Order Type[1] | select |  | Confirmed PO |
| Tp Rcvd Date[1] | text |  |  |
| Artwork[1] | file |  |  |
| Item[1] | select |  | T-Shirt |
| Smv[1] | text |  |  |
| Season Quarter[1] | select |  | Spring 2025 |
| Style Number Name[1][] | select-multiple |  | AY4912 3PK HW STRIPE PLAIN |
| Job No[1][] | select-multiple |  | NCL-24-01760 |
| Commercial Contract Number[1][] | select-multiple |  | NEXT/044/24 |
| Po Number[1][] | select-multiple |  | MJLWT/03120 |
| Shipment Id Number[1][] | select-multiple |  | KR8612270 |
| Colors[1][] | select-multiple |  | GRAY PINSTRIPE, NAVY, WHITE |
| Quantity Ticket[1] | text |  |  |
| Pac[1] | text |  |  |
| Delay Planning Inspection[1] | text |  |  |
| Delay Rfi Proposed[1] | text |  |  |
| Delay Ldd Proposed[1] | text |  |  |
| Buyers Feedback On Delay[1] | text |  |  |
| Reprint Date[1] | text |  |  |
| Reprint Ldd Etd[1] | text |  |  |
| Reprint Shipment Mode[1] | select |  |  |
| Discount Penalty Percent[1] | text |  |  |
| Po Wise Ldd Etd[1] | text |  |  |
| Shipment Mode[1] | select |  | FOB SEA |
| Shipout Date[1] | text |  |  |
| Shipout Condition[1] | select |  |  |
| Shipment Status[1] | select |  | Ship Out |
| Style Description[1] | text |  |  |
| Emballishments[1] | select |  | N / A |
| Zipper[1] | select |  | N / A |
| Button[1] | select |  | N / A |
| Woven Trim[1] | select |  |  |
| Import Item[1] | select |  |  |
| Fabric Category Id[1] | select |  |  |
| Fabric Type[1] | select |  | Single Jersey |
| Gsm[1] | select |  | 300 |
| Composition[1] | select |  |  |
| Fabric Description[1] | text |  |  |
| Dye Type Solid Or Yd[1] | select |  | Multiple Type |
| Fabric Finish Type[1] | select |  | Regular |
| Aop[1] | select |  |  |
| Wash Info At Garmenting[1] | select |  |  |
| Sustainability[1] | select |  | BCI |
| Nego Order Confm Date[1] | text |  |  |
| Yarn Lead Time Days[1] | number |  |  |
| Textile Lead Time Days[1] | number |  |  |
| Print Embr Time Days[1] | number |  |  |
| Garment Lead Time Days[1] | number |  |  |
| Add Ldd Rfi Time Days[1] | number |  |  |
| Actual Order Confirm Date[1] | text |  |  |
| Actual Po Issue Date[1] | text |  |  |
| Fob[1] | text |  |  |
| Price Status[1] | select |  | CONFIRMED |
| Retail Price[1] | text |  |  |
| Avg Yarn Price Per Kg[1] | text |  |  |
| Trims Cost Per Pc[1] | text |  |  |
| Short Order Status[1] | select |  | Processing |
| Yarn Count Yarn Composition[1] | text |  |  |
| Gross Consump Kg Doz[1] | text |  |  |
| Knitting Dyeing Process Loss Percent[1] | text |  |  |
| Cutting Percent[1] | text |  |  |
| Yarn Purchased Quantity Kg[1] | text |  |  |
| Yarn Mill Name[1] | select |  |  |
| Yarn Booking Date[1] | text |  |  |
| Yarn Status[1] | select |  | Shipment Pending |
| Woven Fab Booking Date[1] | text |  |  |
| Woven Fabric Mill Name[1] | select |  |  |
| Woven Inhouse Plan Date[1] | text |  |  |
| Woven Inhouse Actual Date[1] | text |  |  |
| Fabric Booking Date[1] | text |  |  |
| Fabric Status[1] | select |  | Fabric Delivery Done |
| Fabric Closing Plan Date[1] | text |  |  |
| Trims Booking Date[1] | text |  |  |
| Trims Status[1] | select |  | ALL Trims Inhouse |
| Base Hanger Submit Date[1] | text |  |  |
| Nis Submit Date[1] | text |  |  |
| Base Hanger Test Date[1] | text |  |  |
| Base Hanger Approv Date[1] | text |  |  |
| Base Hanger Status[1] | select |  | APPROVED |
| First Sample Submit Date[1] | text |  |  |
| First Sample Comnts Date[1] | text |  |  |
| Proto Status[1] | select |  | APPROVED |
| Print Embr Soff Submit Date[1] | text |  |  |
| Print Embr Soff Comets Date[1] | text |  |  |
| Print Embr Soff Status[1] | select |  | NOT Submitted |
| Black Ss Fit Submit Date[1] | text |  |  |
| Black Ss Fit Approv Date[1] | text |  |  |
| Black Ss Fit Status[1] | select |  | APPROVED |
- গ্রিড কলাম: #ID, Serial No, Factory, Buyer, Department, Brand, Class, Order Type, TP Rcvd Date, Artwork, Item, SMV, Season / Quarter, Style Number / Name, JOB No, Last Update Date, Commercial Contract Number, PO Number, Shipment ID Number, Colors, Quantity (Ticket), PAC, Quantity (PCS), Delay Planning Inspection, Delay RFI Proposed, Delay LDD Proposed, Buyer Feedback On Delay, Reprint Date, Reprint LDD/ETD, Reprint RFI/SD, Reprint Days Left, Reprint Shipment Mode, Discount/Penalty %, Discount Value, PO Wise LDD/ETD, PO Wise RFI/SD, Days Left In Hand, Shipment Mode, Shipout Date, Shipout Condition, Shipment Status, Style Description, Embellishments, Zipper, Button, Woven Trim, Import Item, Fabric Category, Fabric Type, GSM, Composition, Fabric Details, Fabric Description, Dye Type Solid Or YD, Fabric Finish Type, AOP, Wash Info At Garmenting, Sustainability, Nego. Order Confm. Date, Yarn Lead Time (Days), Yarn Inhouse Req., Textile Lead Time (Days), Fab. Inhouse Req., Print/Embr. Time (Days), Garment Lead Time (Days), Add LDD-RFI Time (Days), Total Lead Time (Days), Proposed LDD/ETD, Actual Order Confirm Date, Actual Leadtime Vs Confirm, PO Need Date Vs Act RFI, Actual PO Issue Date, Act. Leadtime Vs PO, FOB, FOB Value, Price Status, Retail Price, Avg Yarn Price/Kg, Yarn Value As Per Requirement, Yarn Value %, Yarn Purchase Balance Value, Trims Cost/Pc, Trims Value, Trims Value %, Short Order Status, Yarn Count & Yarn Composition, Gross Consump. (KG/Doz), Knitting Dyeing Process Loss %, Cutting %, Cutting Req. Quantity (PCS), Total Yarn Requirement (KG), Yarn Purchased Quantity (KG), Yarn Purchase Balance (KG), Yarn Mill Name, Yarn Booking Date, Yarn Status, Woven Fab. Booking Date, Woven Fabric Mill Name, Woven Inhouse Plan Date, Woven Inhouse Actual Date, Fabric Booking Date, Fabric Status, Fabric Closing Plan Date, Fabric Delay From PCD (Days), Trims Booking Date, Trims Status, Base Hanger Submit Date, NIS Submit Date, Base Hanger Test Date, Base Hanger Approve Date, Base Hanger Status, 1st Sample Submit Date, 1st Sample Comments Date, 1st/Proto Status, Print/Embr. S/Off Submit Date, Print/Embr. S/Off Comments Date, Print/Embr. S/Off Status, Black SS/Fit Submit Date, Black SS/Fit Approve Date, Black SS/Fit Status, Test Sample Status, GSS/PP Submit Date, GSS/PP Comments Date, GSS/PP Status, Photo/Press Send Date, Shipping Sample Send Date, FFP Status, FFP Date, Prod. File Target, Prod. File H/O Date, Prod. File Status, Bulk Hanger Submit Date, Bulk Hanger Approve Date, Bulk Hanger Status, PCD, PSD, PED, Garment Production Status, RFI Plan Date, RFI Diff. In Plan, RFI Plan Remarks, Shipout Quantity (Ticket), Shipout Quantity (PCS), Short/Excess (Ticket), Short/Excess (PCS), Shipout Value (USD), Discount/Penalty %, Discounted FOB Price, Discounted Shipout Value, Total Discounted Value, Short/Excess Ship Value, Reason Of Discount/Penalty, Shipout Commercial Contract No, Shipment Invoice Number, ETA Date, Payment Rcvd Date, Payment Rcvd Status, Marketing Merchandiser, Production Merchandiser, Buyer Merchandiser, Remarks
- সেকশন: Summary Cards (KPI), 23, 50,256 Tkt, 60,886 Pcs, $418,425.52, 0.00%, $0.00, 0 Kg, 0 Kg, $0.00, 0.00%, 36,429 Tkt, 51,985 Pcs, 13,827 Tkt, $221,875.00
- বাটন/অ্যাকশন: Filter, Clear, Copy Row, Bulk Delete, Column View, Select All, Clear All, Reset Default, Apply

#### Create New

`/order-summary/create-v1`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Serial No[0] | text |  |  |
| Factory[0] | select |  |  |
| Buyer[0] | select |  |  |
| Department[0] | select |  |  |
| Brand[0] | select |  |  |
| Class[0] | select |  |  |
| Order Type[0] | select |  |  |
| Tp Rcvd Date[0] | text |  |  |
| Artwork[0] | file |  |  |
| Item[0] | select |  |  |
| Smv[0] | text |  |  |
| Season Quarter[0] | select |  |  |
| Style Number Name[0][] | select-multiple |  |  |
| -- Select -- | textarea |  |  |
| Job No[0][] | select-multiple |  |  |
| Updated At[0] | text |  |  |
| Commercial Contract Number[0][] | select-multiple |  |  |
| Po Number[0][] | select-multiple |  |  |
| Shipment Id Number[0][] | select-multiple |  |  |
| Colors[0][] | select-multiple |  |  |
| Quantity Ticket[0] | number |  |  |
| Pac[0] | text |  |  |
| Quantity Pcs[0] | number |  |  |
| Delay Planning Inspection[0] | text |  |  |
| Delay Rfi Proposed[0] | text |  |  |
| Delay Ldd Proposed[0] | text |  |  |
| Buyers Feedback On Delay[0] | text |  |  |
| Reprint Date[0] | text |  |  |
| Reprint Ldd Etd[0] | text |  |  |
| Reprint Rfi Sd[0] | text |  |  |
| Reprint Days Left[0] | number |  |  |
| Reprint Shipment Mode[0] | select |  |  |
| Discount Penalty Percent[0] | text |  |  |
| Discount Value[0] | text |  |  |
| Po Wise Ldd Etd[0] | text |  |  |
| Po Wise Rfi Sd[0] | text |  |  |
| Days Left In Hand[0] | number |  |  |
| Shipment Mode[0] | select |  |  |
| Shipout Date[0] | text |  |  |
| Shipout Condition[0] | select |  |  |
| Shipment Status[0] | select |  |  |
| Style Description[0] | text |  |  |
| Emballishments[0] | select |  |  |
| Zipper[0] | select |  |  |
| Button[0] | select |  |  |
| Woven Trim[0] | select |  |  |
| Import Item[0] | select |  |  |
| Fabric Category Id[0] | select |  |  |
| Fabric Type[0] | select |  |  |
| Gsm[0] | select |  |  |
| Composition[0] | select |  |  |
| Fabric Details[0] | text |  |  |
| Fabric Description[0] | text |  |  |
| Dye Type Solid Or Yd[0] | select |  |  |
| Fabric Finish Type[0] | select |  |  |
| Aop[0] | select |  |  |
| Wash Info At Garmenting[0] | select |  |  |
| Sustainability[0] | select |  |  |
| Nego Order Confm Date[0] | text |  |  |
| Yarn Lead Time Days[0] | number |  |  |
| Yarn Inhouse Req[0] | text |  |  |
| Textile Lead Time Days[0] | number |  |  |
| Fab Inhouse Req[0] | text |  |  |
| Print Embr Time Days[0] | number |  |  |
| Garment Lead Time Days[0] | number |  |  |
| Add Ldd Rfi Time Days[0] | number |  |  |
| Total Lead Time Days[0] | number |  |  |
| Proposed Ldd Etd[0] | text |  |  |
| Actual Order Confirm Date[0] | text |  |  |
| Actual Leadtime Vs Confirm[0] | number |  |  |
| Po Need Date Vs Act Rfi[0] | text |  |  |
| Actual Po Issue Date[0] | text |  |  |
| Act Leadtime Vs Po[0] | number |  |  |
| Fob[0] | number |  |  |
| Fob Value[0] | number |  |  |
| Price Status[0] | select |  |  |
| Retail Price[0] | text |  |  |
| Avg Yarn Price Per Kg[0] | text |  |  |
| Yarn Value As Per Requirement[0] | text |  |  |
| Yarn Value Percent[0] | text |  |  |
| Yarn Purchase Balance Value[0] | text |  |  |
| Trims Cost Per Pc[0] | text |  |  |
| Trims Value[0] | text |  |  |
| Trims Value Percent[0] | text |  |  |
| Short Order Status[0] | select |  |  |
| Yarn Count Yarn Composition[0] | text |  |  |
| Gross Consump Kg Doz[0] | text |  |  |
| Knitting Dyeing Process Loss Percent[0] | text |  |  |
| Cutting Percent[0] | text |  |  |
| Cutting Req Quantity Pcs[0] | number |  |  |
| Total Yarn Requirement Kg[0] | text |  |  |
| Yarn Purchased Quantity Kg[0] | number |  |  |
| Yarn Purchase Balance Kg[0] | text |  |  |
| Yarn Mill Name[0] | select |  |  |
| Yarn Booking Date[0] | text |  |  |
| Yarn Status[0] | select |  |  |
| Woven Fab Booking Date[0] | text |  |  |
| Woven Fabric Mill Name[0] | select |  |  |
| Woven Inhouse Plan Date[0] | text |  |  |
| Woven Inhouse Actual Date[0] | text |  |  |
| Fabric Booking Date[0] | text |  |  |
| Fabric Status[0] | select |  |  |
| Fabric Closing Plan Date[0] | text |  |  |
| Fabric Delay From Pcd Days[0] | number |  |  |
| Trims Booking Date[0] | text |  |  |
| Trims Status[0] | select |  |  |
| Base Hanger Submit Date[0] | text |  |  |
| Nis Submit Date[0] | text |  |  |
| Base Hanger Test Date[0] | text |  |  |
| Base Hanger Approv Date[0] | text |  |  |
| Base Hanger Status[0] | select |  |  |
| First Sample Submit Date[0] | text |  |  |
| First Sample Comnts Date[0] | text |  |  |
| Proto Status[0] | select |  |  |
| Print Embr Soff Submit Date[0] | text |  |  |
| Print Embr Soff Comets Date[0] | text |  |  |
| Print Embr Soff Status[0] | select |  |  |
| Black Ss Fit Submit Date[0] | text |  |  |
| Black Ss Fit Approv Date[0] | text |  |  |
| Black Ss Fit Status[0] | select |  |  |
| Test Sample Status[0] | select |  |  |
| Gss Pp Submit Date[0] | text |  |  |
| Gss Pp Comments Date[0] | text |  |  |
| Gss Pp Status[0] | select |  |  |
| Photo Press Send Date[0] | text |  |  |
| Shipping Sample Send Date[0] | text |  |  |
| Ffp Status[0] | select |  |  |
| Ffp Date[0] | text |  |  |
| Prod File Target[0] | text |  |  |
| Prod File Handover Date[0] | text |  |  |
| Prod File Status[0] | select |  |  |
| Bulk Hangr Submit Date[0] | text |  |  |
| Bulk Hangr Approv Date[0] | text |  |  |
| Bulk Hangr Status[0] | select |  |  |
| Pcd[0] | text |  |  |
| Psd[0] | text |  |  |
| Ped[0] | text |  |  |
| Garment Production Status[0] | select |  |  |
| Rfi Plan Date[0] | text |  |  |
| Rfi Diff In Plan[0] | text |  |  |
| Rfi Plan Remarks[0] | text |  |  |
| Shipout Quantity Ticket[0] | number |  |  |
| Shipout Quantity Pcs[0] | number |  |  |
| Short Excess Ticket[0] | text |  |  |
| Short Excess Pcs[0] | text |  |  |
| Shipout Value Usd[0] | text |  |  |
| Final Discount Penalty Percent[0] | text |  |  |
| Discounted Fob Price[0] | number |  |  |
| Discounted Shipout Value[0] | text |  |  |
| Total Discounted Value[0] | text |  |  |
| Short Excess Ship Value[0] | text |  |  |
| Reason Of Discount Penalty[0] | text |  |  |
| Shipout Commercial Contract Number[0] | text |  |  |
| Shipment Invoice Number[0] | text |  |  |
| Eta Date[0] | text |  |  |
| Payment Rcvd Date[0] | text |  |  |
| Payment Rcvd Status[0] | select |  |  |
| Marketing Merchandiser[0] | select |  |  |
| Production Merchandiser[0] | select |  |  |
| Buyer Merchandiser[0] | select |  |  |
| Remarks[0] | text |  |  |
- গ্রিড কলাম: #, Serial No, Factory, Buyer, Department, Brand, Class, Order Type, TP Rcvd Date, Artwork, Item, SMV, Season / Quarter, Style Number / Name, JOB No, Last Update Date, Commercial Contract Number, PO Number, Shipment ID Number, Colors, Quantity (Ticket), PAC, Quantity (PCS), Delay Planning Inspection, Delay RFI Proposed, Delay LDD Proposed, Buyer Feedback On Delay, Reprint Date, Reprint LDD/ETD, Reprint RFI/SD, Reprint Days Left, Reprint Shipment Mode, Discount/Penalty %, Discount Value, PO wise LDD/ETD, PO wise RFI/SD, Days Left In Hand, Shipment Mode, Shipout Date, Shipout Condition, Shipment Status, Style Description, Embellishments, Zipper, Button, Woven Trim, Import Item, Fabric Category, Fabric Type, GSM, Composition, Fabric Details, Fabric Description, Dye Type Solid Or YD, Fabric Finish Type, AOP, Wash Info At Garmenting, Sustainability, Nego. Order Confm. Date, Yarn Lead Time (Days), Yarn Inhouse Req., Textile Lead Time (Days), Fab. Inhouse Req., Print/Embr. Time (Days), Garment Lead Time (Days), Add LDD-RFI Time (Days), Total Lead Time (Days), Proposed LDD/ETD, Actual Order Confirm Date, Actual Leadtime vs Confirm, PO Need Date vs Act RFI, Actual PO Issue Date, Act. Leadtime vs PO, FOB, FOB Value, Price Status, Retail Price, Avg Yarn Price/Kg, Yarn Value As Per Requirement, Yarn Value %, Yarn Purchase Balance Value, Trims Cost/Pc, Trims Value, Trims Value %, Short Order Status, Yarn Count & Yarn Composition, Gross Consump. (KG/Doz), Knitting Dyeing Process Loss %, Cutting %, Cutting Req. Quantity (PCS), Total Yarn Requirement (KG), Yarn Purchased Quantity (KG), Yarn Purchase Balance (KG), Yarn Mill Name, Yarn Booking Date, Yarn Status, Woven Fab. Booking Date, Woven Fabric Mill Name, Woven Inhouse Plan Date, Woven Inhouse Actual Date, Fabric Booking Date, Fabric Status, Fabric Closing Plan Date, Fabric Delay From PCD (Days), Trims Booking Date, Trims Status, Base Hanger Submit Date, NIS Submit Date, Base Hanger Test Date, Base Hanger Approve Date, Base Hanger Status, 1st Sample Submit Date, 1st Sample Comments Date, 1st/Proto Status, Print/Embr. S/Off Submit Date, Print/Embr. S/Off Comments Date, Print/Embr. S/Off Status, Black SS/Fit Submit Date, Black SS/Fit Approve Date, Black SS/Fit Status, Test Sample Status, GSS/PP Submit Date, GSS/PP Comments Date, GSS/PP Status, Photo/Press Send Date, Shipping Sample Send Date, FFP Status, FFP Date, Prod. File Target, Prod. File H/O Date, Prod. File Status, Bulk Hanger Submit Date, Bulk Hanger Approve Date, Bulk Hanger Status, PCD, PSD, PED, Garment Production Status, RFI Plan Date, RFI Diff. In Plan, RFI Plan Remarks, Shipout Quantity (Ticket), Shipout Quantity (PCS), Short/Excess (Ticket), Short/Excess (PCS), Shipout Value (USD), Discount/Penalty %, Discounted FOB Price, Discounted Shipout Value, Total Discounted Value, Short/Excess Ship Value, Reason of Discount/Penalty, Shipout Commercial Contract No, Shipment Invoice Number, ETA Date, Payment Rcvd Date, Payment Rcvd Status, Marketing Merchandiser, Production Merchandiser, Buyer Merchandiser, Remarks
- বাটন/অ্যাকশন: Add Row, Back

#### Report Dashboard

`/order-summary/reports`

**ভিউ**
- সেকশন: 📅 LDD wise Pending Summary, 🚚 RFI wise Pending Summary, 📊 Buyer Month Wise LDD Analysis, 📊 Buyer Month Wise RFI Analysis, 📊 Quick Statistics, 12, 8, 25, 156, 🔍 How to Use Reports

#### LDD Pending Summary

`/order-summary/reports/ldd-summary`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report Type | select |  | 🧵 Fabric Booking, ✂️ Trims Booking, 🏷️ Base Hanger, ⚫ Black Seal, ✅ FFP/GSS |
| LDD From | date |  |  |
| LDD To | date |  |  |
| Buyer | select-multiple |  | 🔘 Select All Buyers, Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Lim |
| Buyer | textarea |  |  |
| Item | select-multiple |  | 🔘 Select All Items, Long pant, Polo, Sweat Top, T-Shirt, Woven Pant |
| Item | textarea |  |  |
| — | checkbox |  |  |
- গ্রিড কলাম: Urgency, Buyer, Item, Style, Job No, PO Number, LDD Date, Days to LDD, Pending Type, Quantity (PCS), FOB Value, Status
- সেকশন: 0, 0, 0, 0, Filters & Report Type, 🧵 Fabric Booking - Report Data, No Pending Items Found
- বাটন/অ্যাকশন: Back to Dashboard, Filter, Export Excel, Export PDF, Refresh

#### RFI Pending Summary

`/order-summary/reports/rfi-summary`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report Type | select |  | 🧵 Fabric Booking, ✂️ Trims Booking, 🏷️ Base Hanger, ⚫ Black Seal, ✅ FFP/GSS |
| RFI From | date |  |  |
| RFI To | date |  |  |
| Buyer | select-multiple |  | 🔘 Select All Buyers, Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Lim |
| Buyer | textarea |  |  |
| Item | select-multiple |  | 🔘 Select All Items, Long pant, Polo, Sweat Top, T-Shirt, Woven Pant |
| Item | textarea |  |  |
| — | checkbox |  |  |
- গ্রিড কলাম: Urgency, Buyer, Item, Style, Job No, PO Number, RFI Date, Days to RFI, Pending Type, Quantity (PCS), FOB Value, Status
- সেকশন: 8, 0, 0, 0, Filters & Report Type, 🧵 Fabric Booking - Report Data, No Pending Items Found
- বাটন/অ্যাকশন: Back to Dashboard, Filter, Export Excel, Export PDF, Refresh

#### Buyer Month Wise

`/order-summary/reports/buyer-month-wise`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report Type | select | ✔ | LDD Wise, RFI Wise |
| Start Date | date |  |  |
| End Date | date |  |  |
| Buyer | select |  | All Buyers, Adler, AKH, American Tall, Antim Knit Composite Ltd, Apex Fashion Wear Limited, Aswad, Auko-Tex Limited., BC |
| Department | select |  | All Departments, Baby, Boys, Girls, Kids, Kids-Boys, Kids-Girls, Ladies, Mens, Toddler Boys, Toddler Girls, Unisex |
| — | checkbox |  |  |
- সেকশন: -, Total Quantity, -, Total Styles, -, Total Value, $-, Buyer Month Wise Report
- বাটন/অ্যাকশন: Back to Reports, Filter

#### Audit Log

`/order-summary/audit-log`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Action | select |  | Action: All, Created, Updated, Copied, Duplicated, Deleted, Artwork Uploaded |
| User Id | select |  | User: All, Admin User |
| Style / Job No | text |  |  |
| Date From | date |  |  |
| Date To | date |  |  |
- গ্রিড কলাম: SL, WHEN, ACTION, STYLE / JOB NO, SUMMARY, USER, 
- সেকশন: Updated — I979067 / NCL-24-00801, Updated — AJ1664,SS NEUT MRL HW PKT POLO / NCL-24-01600
- বাটন/অ্যাকশন: Filter, Details
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **উৎস (Norban):** `config/order_summary.php` (১৮টা গ্রুপ, ১৬১টা কলাম), ফর্মুলা তিন জায়গায় — `app/Services/OrderSummary/FormulaEngine.php`, `resources/js/pages/order-summary-create-v1.js` (স্ক্রিন), `app/Models/OrderMaster.php::syncCalculatedAttributes()` (Fabric Details ইত্যাদি)। রিপোর্ট `OrderSummaryReportsController`, অডিট `OrderSummaryAuditLogController`। লাইভ স্ক্রিনশট `tools/erp_crawler/out/norban/os_*.png`; ব্যবহারকারীর আসল Excel শিটের ছবি (২০২৬-১১-১৮) চেহারার রেফারেন্স।
- **AnnotechERP:** বায়ার `buyers`, ফ্যাক্টরি `factories`, আইটেম `item_types`, রঙ `colours`; Brand/Season/Garment Department lookup (31/01-এ তৈরি); বাকি ড্রপডাউন নতুন lookup table; বায়ার-মার্চেন্ডাইজার = Merchandise Desk-এর Buyer Account Permission (`buyer_user_permissions`, kind MERCHANDISING); ডিপার্টমেন্ট = Company Management-এর `departments`; মেইল = Mail Service Configuration (`mail_services`); কলাম বাছাই = `components/erp/column-view.jsx`।

## ৪. ডাটা মডেল (Entity ও Field)

মডিউল `app/Modules/OrderSummary`, টেবিল `os_*`:

| টেবিল | কী রাখে |
|---|---|
| `os_groups` | কোম্পানির কলাম-গ্রুপ: code, name, sort_order, color (হেডারের রঙ), active |
| `os_columns` | কলাম: key (স্থায়ী), label (কোম্পানিতে অনন্য — ফর্মুলা এই নামে ডাকে), group_id, sort_order, type, source, multiple, prefix, suffix, decimals, width, vertical, required, formula (ভেতরে `{key}` দিয়ে), volatile (TODAY() নির্ভর), color_rules (json), role_access (json: role → none/view/edit), is_system, hidden |
| `os_orders` | একটা সারি: serial_no, buyer_id (স্কোপের জন্য), values (jsonb — প্রতিটা কলামের মান, ফর্মুলার ফলসহ), artwork_file_id, version (একসাথে এডিট ঠেকাতে), created_by, updated_by, soft delete |
| `os_changes` | প্রতিটা ঘরের পরিবর্তন: order_id, column_key, old, new, by, at, batch, kind (CREATE/EDIT/DELETE/RECALC), mailed_at |
| `os_column_events` | কলাম/ফর্মুলার ইতিহাস: column, action, before, after, by, at, recalculated rows |
| `os_department_heads` | department_id → ডিপার্টমেন্ট হেড (user) |
| `os_teams`, `os_team_members` | টিম: name, department_id, team_head_id; সদস্য = মার্চেন্ডাইজার (user) |
| `os_mail_settings` | enabled, times (json, যেমন ["10:00","18:00"]), last_sent_at |

**কলামের ধরন:** text, longtext, number, money, percent, date, days, dropdown (source), multi (একাধিক মান/ট্যাগ), image (artwork), system (Serial No, Last Update)।
**উৎস (source):** `buyers`, `factories`, `items`, `colours`, `merchandisers`, `lookup:<CODE>` (যেকোনো lookup table), বা খালি (মুক্ত ট্যাগ)।

## ৫. Workflow / Status ও Business Rules

- **ফর্মুলা ভাষা (Excel-এর মতো):** `[Column Label]`, সংখ্যা, "লেখা", `+ - * /`, `( )`, তুলনা `= <> < > <= >=`; ফাংশন `IF(c,a,b)`, `AND`, `OR`, `NOT`, `ROUND(x,n)`, `MIN`, `MAX`, `ABS`, `SUM(...)`, `N(x)` (ফাঁকা হলে ০), `ISBLANK(x)`, `TODAY()`, `DAYS(a,b)` (a − b দিন), `ADD_DAYS(d,n)`, `JOIN(sep, ...)` (ফাঁকা বাদ দিয়ে জোড়া)।
- **ফাঁকা নিয়ম:** কোনো ইনপুট ফাঁকা হলে হিসাবও ফাঁকা (Norban-এর মতো "সব থাকলে তবেই হিসাব"); শূন্য দিয়ে ভাগ → ফাঁকা; `SUM` সব ফাঁকা হলে ফাঁকা, নইলে ফাঁকাগুলো বাদ দিয়ে যোগ; `N()` ফাঁকাকে ০ ধরে।
- **ফল:** কলামের ধরন অনুযায়ী — date হলে তারিখ, সংখ্যা হলে কলামের দশমিক ঘরে round (half away from zero)।
- **ক্রম:** নির্ভরতা অনুযায়ী (topological); চক্র থাকলে সেভ হয় না। একই parser JS (`lib/formula.js`) আর PHP (`App\Shared\Formula`)-এ; `tests/fixtures/formula_cases.json` দুই দিকেই চলে।
- **ফর্মুলা বদলালে** সব সারি আবার হিসাব, `os_column_events`-এ আগের/নতুন আর কয়টা সারি বদলাল, প্রতিটা ঘরের পরিবর্তন `os_changes`-এ kind RECALC (মেইলে যায় না)।
- **TODAY() নির্ভর কলাম** (Days Left In Hand, Reprint Days Left, আর যারা এদের ওপর নির্ভর) সেভ হয় না, পড়ার সময় হিসাব।
- **শুরুর ফর্মুলা** (Norban-এর স্ক্রিনেরটা): Quantity (PCS) = Ticket × PAC; PO wise RFI/SD = LDD − ১০; Days Left = DAYS(RFI/SD, TODAY()); Reprint RFI/SD, Reprint Days Left একইভাবে; Yarn/Fab Inhouse Req; Total Lead Time = SUM(৫টা); Proposed LDD; Actual Leadtime vs Confirm; PO Need Date vs Act RFI; Act. Leadtime vs PO; FOB Value; Discount Value; Prod. File Target = RFI/SD − ৩৫; Trims Value, Trims %; Cutting Req. Qty = Qty + Qty × Cutting%; Total Yarn Req = ROUND(CutReq × Gross ÷ 12 × (1 + (Loss% + Cutting%) ÷ 100), 0); Yarn Value, Yarn %, Yarn Purchase Balance (KG/Value); Fabric Delay From PCD; RFI Diff. In Plan; Short/Excess (Ticket/PCS); Shipout Value; Discounted FOB Price; Discounted Shipout Value (round 0); Total Discounted Value = Shipout Value − Discounted Shipout Value; Short/Excess Ship Value; Fabric Details = JOIN(", ", Fabric Type, GSM, Composition, Finish Type)।
- **একই নামের দুই কলাম:** Norban-এ "Discount/Penalty %" দুবার (Delay Summary আর Shipment Status)। ফর্মুলা নাম দিয়ে ডাকে বলে দ্বিতীয়টার নাম "Final Discount/Penalty %"।
- **কে কোন অর্ডার দেখে/এডিট করে:** `ordersummary.view_all` থাকলে সব; নইলে নিজের বায়ার (Buyer Account Permission) ∪ যে টিমের টিম হেড সেই টিমের সদস্যদের বায়ার ∪ যে ডিপার্টমেন্টের হেড তার সব টিমের সদস্যদের বায়ার।
- **কলাম পারমিশন:** role_access-এ রোল → none/view/edit (ঠিক না করা = edit); কারও একাধিক রোল হলে সবচেয়ে বেশি অধিকারটা।
- **এডিট:** শিটে সরাসরি; বদলানো সারি একসাথে Save (প্রতিটা সারি তার version নিয়ে যায়; অন্য কেউ আগে বদলালে সেই সারি ফেরত, বাকিগুলো সেভ); Bulk Update (টিক দেওয়া সারিতে কলাম = মান); Excel থেকে পেস্ট; Fill Down; Copy Row; Bulk Delete (soft)।
- **মেইল:** শুধু EDIT পরিবর্তন; অ্যাডমিনের ঠিক করা সময়ে (Asia/Dhaka) আগের মেইলের পরের সব না-পাঠানো পরিবর্তন; প্রাপক = ওই সারির বায়ারের মার্চেন্ডাইজার + তাদের টিম হেড + ডিপার্টমেন্ট হেড; প্রত্যেকে শুধু নিজের অংশ; কিছু না থাকলে মেইল নয়; কোম্পানির ডিফল্ট Mail Service দিয়ে।

## ৬. API ও পারমিশন

রুট `/api/v1/order-summary`:

| রুট | কাজ | পারমিশন |
|---|---|---|
| GET `/meta` | গ্রুপ, কলাম (এই ইউজারের view/edit সহ), ড্রপডাউনের অপশন | ordersummary.view |
| GET `/orders` | ফিল্টার (কলাম অনুযায়ী, সিজন ট্যাব, সার্চ), পাতা, KPI | ordersummary.view |
| POST `/orders` | এক বা একাধিক নতুন সারি | ordersummary.create |
| PATCH `/orders` | বদলানো সারিগুলো (version সহ) | ordersummary.update |
| POST `/orders/bulk-update` | টিক দেওয়া সারিতে কলাম = মান | ordersummary.update |
| POST `/orders/copy`, DELETE `/orders` | Copy Row, Bulk Delete | create / delete |
| POST `/orders/artwork` | Artwork ছবি | create বা update |
| GET/POST/PATCH/DELETE `/columns`, `/groups`, POST `/columns/preview`, `/columns/validate` | Column Setup | ordersummary.columns.manage (GET: view) |
| GET/PUT `/teams`, `/department-heads` | Merchandising Team | ordersummary.team.manage |
| GET/PUT `/mail-settings`, POST `/mail-settings/test` | Mail Schedule | ordersummary.mail.manage |
| GET `/dashboard`, `/reports/{ldd,rfi,buyer-month}`, `/audit-log` | ড্যাশবোর্ড, রিপোর্ট, অডিট | ordersummary.view / .report |

রোল: FACTORY_ADMIN সব; FACTORY_MANAGER view, view_all, report। Console `ordersummary:mail-digest` (প্রতি মিনিটে scheduler)।

## ৭. Frontend পেজ (লাইট থিম)

মডিউল `ORDER_SUMMARY` (SALES & MERCHANDISING), রুট `/order-summary/{screen}`:
- **Dashboard** (মূল ড্যাশবোর্ড): KPI (অর্ডার, Qty, FOB Value, Days Left লাল/হলুদ, শিপড), বায়ার/মাস অনুযায়ী, রিপোর্টের লিংক।
- **View All:** Excel-এর মতো শিট — ছোট ফন্ট, বড় হাতের বোল্ড ধূসর হেডার, গ্রুপের রঙিন উপরের সারি, প্রতিটা কলামের হেডারে ফিল্টার, "N of M records found", খাড়া লেখা, রঙের নিয়ম, artwork থাম্বনেইল, নিচে ট্যাব (Total Order, প্রতিটা সিজন); Column View; শিটে এডিট + Save, Bulk Update, পেস্ট, Fill Down, Copy Row, Bulk Delete।
- **Create New:** একই শিট খালি সারি নিয়ে (Add Row), লাইভ হিসাব।
- **LDD Pending Summary, RFI Pending Summary, Buyer Month Wise (LDD/RFI), Audit Log** (Norban-এর মতো)।
- **Column Setup:** গ্রুপ আর কলামের তালিকা (টেনে ক্রম), Add Column / Edit (ধরন, উৎস, ফর্মুলা বিল্ডার — কলাম/ফাংশন ক্লিকে বসে, নমুনা সারিতে ফল, ভুল/চক্র সাথে সাথে), রঙের নিয়ম, খাড়া লেখা, রোল অনুযায়ী View/Edit, ইতিহাস।
- **Merchandising Team:** ডিপার্টমেন্ট হেড; টিম (হেড, সদস্য, প্রতিটা সদস্যের বায়ার)।
- **Mail Schedule:** চালু/বন্ধ, দিনের সময়গুলো, শেষ পাঠানো, Test mail।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Shared/Formula/Formula.php`, `FormulaSet.php`, `FormulaError.php` — ফর্মুলা ভাষা (parser, evaluator, নির্ভরতার ক্রম, TODAY-নির্ভরতা)
- `app/Modules/OrderSummary/Database/Migrations/2027_04_02_000100_create_order_summary.php`
- `app/Modules/OrderSummary/Models/{OsGroup,OsColumn,OsOrder,OsChange,OsColumnEvent,OsDepartmentHead,OsTeam,OsTeamMember,OsMailSetting}.php`
- `app/Modules/OrderSummary/Support/ColumnCatalog.php` (Norban-এর config থেকে তৈরি: ১৮ গ্রুপ, ১৬০ কলাম, ৩৩ ফর্মুলা, রঙের নিয়ম, KPI), `Sheet.php`, `OrderScope.php`, `MailDigest.php`, `OrderEditDigestMail.php`
- `app/Modules/OrderSummary/Http/Controllers/Api/{OrderApiController,ColumnApiController,TeamApiController,MailSettingApiController,ReportApiController}.php`
- `app/Modules/OrderSummary/Console/SendEditDigest.php`, `routes/console.php` (প্রতি মিনিটে)
- `app/Modules/OrderSummary/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `LookupSeeder.php` (৩৩টা OS_* তালিকা)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (`os_artwork`)
- `lang/{en,bn}/ordersummary.php`, `lang/{en,bn}/nav.php`

**Frontend**
- `frontend/src/lib/formula.js`, `formula.cases.json`, `formula.test.js`
- `frontend/src/app/(app)/order-summary/[screen]/{page,screens,actions}.js`, `frontend/src/app/api/order-summary/artwork/route.js`
- `frontend/src/components/ordersummary/{common.js,sheet.jsx,dashboard.jsx,reports.jsx,column-setup.jsx,teams.jsx,mail.jsx}`
- `frontend/src/components/erp/column-view.jsx` (31/01 থেকে)

**Tests**
- `tests/Feature/OrderSummary/FormulaTest.php` (fixture-এর ৪৯টা কেস), `tests/Feature/OrderSummary/OrderSummaryTest.php` (৮টা), vitest `src/lib/formula.test.js` (৫০টা)

**Tooling**
- `AnnotechERP/tools/app_check/ui.mjs`: নতুন ধাপ `type`, `paste`, `eval`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

Backend `app/Modules/OrderSummary/…` (Migrations, Models, Support/{OrderSheet, ColumnCatalog, Scope, MailDigest}, Http/Controllers/Api/*, Console/MailDigestCommand, Database/Seeders/OrderSummaryColumnSeed), `app/Shared/Formula/{Lexer,Parser,Evaluator,Formula}.php`, MenuRegistry, Permission/Role/Lookup seeders, FileApiController, lang `ordersummary.php`।
Frontend `app/(app)/order-summary/[screen]/…`, `components/ordersummary/*`, `lib/formula.js`। Tests `tests/Feature/OrderSummary/*`, `tests/Unit/FormulaTest.php`, `frontend` formula fixture check।

## ৯. টেস্ট ও যাচাই

- ফর্মুলা: একই fixture JS আর PHP-তে একই ফল; ফাঁকা নিয়ম; তারিখ; round; চক্র ধরা; অজানা কলাম।
- কলাম যোগ (গ্রুপ/অবস্থান), ফর্মুলা বদল → সব সারি আবার হিসাব + ইতিহাস; সিস্টেম কলাম মোছা যায় না।
- স্কোপ: মার্চেন্ডাইজার/টিম হেড/ডিপার্টমেন্ট হেড/view_all; কলাম পারমিশন (view-only কলাম লেখা ফেরত)।
- version সংঘাত; Bulk Update; Copy; Delete।
- মেইল ডাইজেস্ট: সময় হলে প্রাপক অনুযায়ী ভাগ, mailed_at, কিছু না থাকলে মেইল নয় (Mail::fake)।
- ব্রাউজার: Column Setup থেকে কলাম যোগ + ফর্মুলা, শিটে লাইভ হিসাব, Bulk Update, রিপোর্ট — dev DB-তে রেখে দেওয়া।

## ১০. খোলা প্রশ্ন

- **Discount % ব্যাখ্যা:** Norban-এর স্ক্রিন Final Discount/Penalty % ১-এর বেশি হলে শতাংশ, নইলে ভগ্নাংশ ধরত (৫ আর ০.০৫ দুটোই ৫%)। এখানে সবসময় শতাংশ (৫ = ৫%)। ঠিক আছে কিনা।
- **Fabric Details:** Norban-এ চারটা কলাম জুড়ে তৈরি; এখানে JOIN ফর্মুলা, Column Setup থেকে বদলানো যাবে।

- **Norban-এর আসল তালিকা:** ড্রপডাউনের OS_* তালিকাগুলোয় Norban-এর দেখা মান দিয়ে শুরু; Norban-এর MySQL চালু থাকলে পুরো তালিকা আনা যায়। লাগবে কিনা।
- **মেইল সার্ভিস:** মেইল যেতে Access Control → Mail Service Configuration-এ SMTP সার্ভিস লাগবে (পাসওয়ার্ড environment variable-এ)। dev-এ নেই, তাই এখনো আসল মেইল পাঠানো যাচাই হয়নি (টেস্টে Mail::fake দিয়ে যাচাই)।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১১-১৮):** ব্যবহারকারীর দুই দফা আলোচনার সিদ্ধান্ত (মডিউলের `00_Index.md`) অনুযায়ী।
- **ফর্মুলা ডেটা, কোড নয়:** Norban-এ ফর্মুলা ছিল তিন জায়গায় (FormulaEngine, JS, OrderMaster মডেল), আর তিনটায় তিনটা আলাদা হিসাব। এখন একটাই জায়গা — কোম্পানির `os_columns.formula` — ব্রাউজার আর সার্ভার একই parser দিয়ে পড়ে; একই fixture দুই দিকে পাস করে।
- **শিট:** Excel-এর মতো ছোট ফন্ট, দুই সারির হেডার (রঙিন গ্রুপ + ধূসর বড় হাতের কলাম), প্রতিটা কলামে ফিল্টার (মান আর সংখ্যা সহ), সর্ট, খাড়া লেখা, রঙের নিয়ম, artwork, KPI কার্ড (কলামে Sum/Avg), নিচে Total Order + প্রতিটা সিজনের ট্যাব, "N of M records found"। এডিট: ক্লিক/Enter/F2 বা সরাসরি টাইপ, Tab/Enter/তীর, Delete, Excel থেকে পেস্ট (তারিখ 26-Nov-26 / 13/12/2026 / ISO, "$1.88" পড়ে), Ctrl+D, Save-এ শুধু বদলানো সারি version সহ, Bulk Update, Copy Row, Delete।
- **ইচ্ছাকৃত পার্থক্য:** দ্বিতীয় "Discount/Penalty %"-এর নাম "Final Discount/Penalty %" (ফর্মুলা নামে ডাকে); Final Discount সবসময় শতাংশ; ফর্মুলা/সিস্টেম কলামে পাঠানো মান (পেস্ট করা পুরো Excel সারি) চুপচাপ বাদ, শুধু রোলের কারণে শুধু-দেখার কলাম ফেরত; ড্রপডাউনের মান মাস্টারের নামেই রাখা (Norban-এর মতো), বায়ারের সাথে `buyer_id` আলাদা রাখা স্কোপের জন্য।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - Postgres-এ `FOR UPDATE` + `max()` চলে না → কোম্পানির সারি লক করে ক্রমিক নম্বর।
  - মেইলের টেস্ট আসল ঘড়ির ওপর নির্ভর করছিল → টেস্টে ঘড়ি ০৯:০০-এ বাঁধা।
  - KPI-তে "Total Total …" → নামে Total/Avg থাকলে আর প্রিফিক্স নয়।
  - Merchandising Team-এ ৩৫টা ডিপার্টমেন্টের লম্বা তালিকা → শুধু হেড/টিম থাকা ডিপার্টমেন্ট, বাকিগুলো বাছাই করে যোগ।
  - ui.mjs-এ `press`/`choose` আগেই ছিল, দ্বিতীয়বার যোগ করায় প্রতিটা কী দুবার যাচ্ছিল (অ্যাপের দোষ ভেবে খোঁজা হয়েছিল) → ডুপ্লিকেট সরানো; এডিটর খোলার কী-টা এডিটরে না পৌঁছানোর পাহারা (keyup) রাখা হয়েছে।
  - Turbopack পুরোনো কোড দেখাচ্ছিল → ক্যাশ ভলিউম মুছে নতুন করে চালু।
- **ব্যবহারকারীর পরিবর্তন (২০২৬-১১-১৮, দ্বিতীয় দফা):** View All থেকে KPI কার্ড আর উপরের ফিল্টার বার বাদ (হেডারের ফিল্টার থাকল; KPI-র হিসাবও API থেকে বাদ, Column Setup-এ সামারি কার্ড লুকানো)। এডিট এখন **Bulk Edit মোড**: সারি টিক দিয়ে Bulk Edit চাপলে শুধু সেই সারিগুলো খোলে (হলুদ, ঘর বক্স করা), ঘরে এক ক্লিকে এডিট, Save-এ বন্ধ হয়; আগের Bulk Update ডায়ালগ বাদ। কলাম-হেডার উঁচু (h-16), খাড়া কলাম সরু (৩৪px)। স্মুথনেস: প্রতিটা সারি memo করা কম্পোনেন্ট, table-fixed + colgroup।
- **dev DB-তে রেখে দেওয়া (Delta Apparels):** ৪টা অর্ডার (H&M ২, C&A ২; পেস্ট করে তৈরি, একটা Copy), Remarks এডিট, ২টা সারিতে Bulk Update (Ship Out), Column Setup-এ নতুন কলাম "Agent Commission" = ROUND([FOB Value] × 0.05, 2) (Price and Purchase গ্রুপে FOB Value-এর পরে, ৪টা সারি আবার হিসাব), টিম "Team Alpha" (Merchandising Department, হেড Nasrin Akter, সদস্য Rahim Uddin → H&M)। ব্রাউজার যাচাইয়ের সময় একটা ক্লিক ভুল পেজে পড়ে Serial No লুকিয়ে গিয়েছিল — আবার দেখানো হয়েছে।
- **হোম ড্যাশবোর্ড (user, 2026-11-28: "লগইন করলে ডিফল্ট ড্যাশবোর্ড মেসিনারিজ এর টা দেখায় … Order Summary, Pre-Budget নিয়ে একটি ড্যাশবোর্ড বানাও"):** `/` এখন নতুন হোম ড্যাশবোর্ড (`frontend/src/app/(app)/page.js` + `components/home/dashboard.jsx`)। ডেটা আসে `/order-summary/dashboard` আর `/pre-budget/dashboard` থেকে; যে অংশে 403/404 আসে, সেটা দেখানো হয় না। উপর থেকে ক্রমে আছে: শুভেচ্ছা ব্যান্ড ও দ্রুত লিংক; ৬টা KPI; Order → Requisition → Budget → Approved পাইপলাইন; OS-এর Days-left বাকেট ও Pending; PB-এর Budget status ও Open work; বায়ার অনুযায়ী FOB (অর্ডার আর রিকুইজিশন পাশাপাশি); মাস অনুযায়ী FOB (দুই সিরিজ, চলতি মাস মাঝখানে); আর দুই মডিউলের সর্বশেষ পরিবর্তন একসাথে। সাইডবারের উপরে "Dashboard" লিংক যোগ হয়েছে। Machinery ড্যাশবোর্ড সরে গেছে `/machinery-dashboard`-এ (MenuRegistry URL বদলেছে, তাই `access:sync-menus` চালাতে হবে)। লেবেল `dashboard.home.*`-এ। পরে এখানে অন্য মডিউলও যোগ করা যাবে।
