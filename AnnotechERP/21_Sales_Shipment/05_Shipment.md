# Shipment

> **স্ট্যাটাস:** ✅ সম্পন্ন (৭টার ৭টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/05`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Shipment Booking | `/m/sales-delivery/shipment-booking` | 2 | 34 | 0 |
| 2 | Container / Vessel Details | `/m/sales-delivery/container-vessel` | 2 | 33 | 1 |
| 3 | Commercial Document Handover | `/m/sales-delivery/commercial-doc-handover` | 1 | 15 | 0 |
| 4 | Shipment Invoice | `/m/sales-delivery/shipment-invoice` | 2 | 22 | 0 |
| 5 | Packing List | `/m/sales-delivery/packing-list` | 2 | 13 | 0 |
| 6 | Shipment Confirmation | `/m/sales-delivery/shipment-confirmation` | 1 | 1 | 0 |
| 7 | Shipment Closing | `/m/sales-delivery/shipment-closing` | 1 | 17 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Shipment Booking

`/m/sales-delivery/shipment-booking`

**ট্যাব: Booking List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Requested, Confirmed, Cancelled, Completed |
| ETD from | date |  |  |
| ETD to | date |  |  |
| Search Shipment bookings with their number, date, shipment, forwarder, shipping  | search |  | Search 0 records… |
| Filter by Booking No | search |  |  |
| Filter by Booking Date | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by SO No | search |  |  |
| Filter by Forwarder | search |  |  |
| Filter by Shipping Line | search |  |  |
| Filter by Vessel / Voyage | search |  |  |
| Filter by ETD | search |  |  |
| Filter by ETA | search |  |  |
| Filter by POL | search |  |  |
| Filter by POD | search |  |  |
| Filter by Final Destination | search |  |  |
| Filter by Containers | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Booking No, Booking Date, Shipment No, SO No, Forwarder, Shipping Line, Vessel / Voyage, ETD, ETA, POL, POD, Final Destination, Containers, ACTION
- বাটন/অ্যাকশন: Booking List 0, New booking, Clear, Export, Booking No, Booking Date, Shipment No, SO No, Forwarder, Shipping Line, Vessel / Voyage, ETD, ETA, POL, POD, Final Destination, Containers

**ট্যাব: New booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Booking No | text |  | [Auto Gen] |
| Booking Date* | date | ✔ |  |
| Forwarder* | text | ✔ |  |
| Shipping Line | text |  |  |
| Vessel Name | text |  |  |
| Voyage No | text |  |  |
| ETD | date |  |  |
| ETA | date |  |  |
| Port of Loading | text |  |  |
| Port of Discharge | text |  |  |
| Final Destination | text |  |  |
| Container Type | select |  | Select…, 20' GP, 40' GP, 40' HC, 45' HC, 20' RF, 40' RF, LCL, Air Pallet |
| Container Qty | number |  |  |
| Status* | select | ✔ | Select…, Requested, Confirmed, Cancelled, Completed |
| Remarks | textarea |  |  |
- সেকশন: New booking
- বাটন/অ্যাকশন: Booking List 0, New booking, Reset, Save booking

#### Container / Vessel Details

`/m/sales-delivery/container-vessel`

**ট্যাব: Container List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All |
| ETD from | date |  |  |
| ETD to | date |  |  |
| Search Containers with their number, shipment, booking, type, seal, weights, vol | search |  | Search 0 records… |
| Filter by Container No | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Booking No | search |  |  |
| Filter by Type | search |  |  |
| Filter by Seal No | search |  |  |
| Filter by Gross Wt | search |  |  |
| Filter by Net Wt | search |  |  |
| Filter by CBM | search |  |  |
| Filter by Cartons | search |  |  |
| Filter by Vessel / Voyage | search |  |  |
| Filter by Loading Date | search |  |  |
| Filter by ETD | search |  |  |
| Filter by ETA | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Container No, Shipment No, Booking No, Type, Seal No, Gross Wt, Net Wt, CBM, Cartons, Vessel / Voyage, Loading Date, ETD, ETA, ACTION
- বাটন/অ্যাকশন: Container List 0, New container, Clear, Export, Container No, Shipment No, Booking No, Type, Seal No, Gross Wt, Net Wt, CBM, Cartons, Vessel / Voyage, Loading Date, ETD, ETA

**ট্যাব: New container**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Booking | select |  | Select… |
| Container No* | text | ✔ | MSKU1234567 |
| Container Type | select |  | Select…, 20' GP, 40' GP, 40' HC, 45' HC, 20' RF, 40' RF, LCL, Air Pallet |
| Seal No | text |  |  |
| Gross Weight | number |  |  |
| Net Weight | number |  |  |
| CBM | number |  |  |
| Cartons | number |  |  |
| Vessel Name | text |  |  |
| Voyage No | text |  |  |
| Loading Date | date |  |  |
| ETD | date |  |  |
| ETA | date |  |  |
| Remarks | textarea |  |  |
- সেকশন: New container
- বাটন/অ্যাকশন: Container List 0, New container, Reset, Save container

**ডায়ালগ: New container**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Booking | select |  | Select… |
| Container No* | text | ✔ |  |
| Container Type | select |  | Select…, 20' GP, 40' GP, 40' HC, 45' HC, 20' RF, 40' RF, LCL, Air Pallet |
| Seal No | text |  |  |
| Gross Weight | number |  |  |
| Net Weight | number |  |  |
| CBM | number |  |  |
| Cartons | number |  |  |
| Vessel Name | text |  |  |
| Voyage No | text |  |  |
| Loading Date | date |  |  |
| ETD | date |  |  |
| ETA | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Container No is required.

#### Commercial Document Handover

`/m/sales-delivery/commercial-doc-handover`

**ট্যাব: Commercial Document Handover List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Pending, Handed Over, Received, Returned |
| Handover Date from | date |  |  |
| Handover Date to | date |  |  |
| Search Commercial Document Handover records with their number, date, shipment, b | search |  | Search 0 records… |
| Filter by Handover No | search |  |  |
| Filter by Date | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Invoice No | search |  |  |
| Filter by Packing No | search |  |  |
| Filter by EXP / Certificate | search |  |  |
| Filter by Submitted By | search |  |  |
| Filter by Received By | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Handover No, Date, Shipment No, Buyer, Invoice No, Packing No, EXP / Certificate, Submitted By, Received By, Attachment, ACTION
- বাটন/অ্যাকশন: Commercial Document Handover List 0, Save commercial document handover, Clear, Export, Handover No, Date, Shipment No, Buyer, Invoice No, Packing No, EXP / Certificate, Submitted By, Received By, Attachment

#### Shipment Invoice

`/m/sales-delivery/shipment-invoice`

**ট্যাব: Invoice List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Invoice No | text |  |  |
| Shipment No | text |  |  |
| SO No | text |  |  |
| Currency | select |  | All, BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| State | select |  | All, Draft, Final, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Invoice List 0, Shipment Invoice Entry, All, Search, Reset

**ট্যাব: Shipment Invoice Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shipment List | search |  | Search shipment, SO or buyer… |
| Existing shipment invoice | select |  |  |
| Shipment* | select | ✔ | Select… |
| Invoice No | text |  | [Auto Gen] |
| Invoice Date* | date | ✔ |  |
| Currency* | select | ✔ | Select…, BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Commercial Invoice | select |  | Select… |
| Consignee | textarea |  |  |
| Notify Party | textarea |  |  |
| Port | text |  |  |
| Shipment Mode* | select | ✔ | Select…, Sea, Air, Road, Sea-Air, Courier |
| Incoterm | select |  | Select…, FOB, CFR, CIF, EXW, FCA, CPT, CIP, DAP, DDP |
| Payment Terms | text |  |  |
| LC / Contract No | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Shipment No, Buyer, To Invoice, Ship Date
- সেকশন: Shipment List, Shipment Invoice Entry, Invoice, Parties, Shipping & Terms, Invoice Lines
- বাটন/অ্যাকশন: Invoice List 0, Shipment Invoice Entry, Select…, Add line, Back To List, Clear, Save shipment invoice

#### Packing List

`/m/sales-delivery/packing-list`

**ট্যাব: Packing List Register 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Packing No | text |  |  |
| Shipment No | text |  |  |
| Invoice No | text |  |  |
| State | select |  | All, Draft, Final, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Packing List Register 0, Packing List Entry, All, Search, Reset

**ট্যাব: Packing List Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shipment List | search |  | Search shipment, SO or buyer… |
| Existing packing list | select |  |  |
| Shipment* | select | ✔ | Select… |
| Packing No | text |  | [Auto Gen] |
| Packing Date* | date | ✔ |  |
| Invoice | select |  | Select… |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Shipment No, Buyer, To Pack, Ship Date
- সেকশন: Shipment List, Packing List Entry, Packing List, Carton-wise Packing
- বাটন/অ্যাকশন: Packing List Register 0, Packing List Entry, Add carton run, Back To List, Clear, Save packing list

#### Shipment Confirmation

`/m/sales-delivery/shipment-confirmation`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment | select |  | All |
- সেকশন: Checklist
- বাটন/অ্যাকশন: Search, Check, Reset

#### Shipment Closing

`/m/sales-delivery/shipment-closing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Stage | select |  | All, Shipped, Closed |
| Shipment Date from | date |  |  |
| Shipment Date to | date |  |  |
| Search Confirmed and closed shipments with their order, PO, shipped quantity, fi | search |  | Search 0 records… |
| Filter by Shipment No | search |  |  |
| Filter by SO No | search |  |  |
| Filter by PO | search |  |  |
| Filter by Shipment Qty | search |  |  |
| Filter by Invoice Value | search |  |  |
| Filter by Shipment Date | search |  |  |
| Filter by Vessel | search |  |  |
| Filter by Containers | search |  |  |
| Filter by Documents | search |  |  |
| Filter by Confirmation | search |  |  |
| Filter by Closing Date | search |  |  |
| Filter by Closed By | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Shipment No, SO No, PO, Shipment Qty, Invoice Value, Shipment Date, Vessel, Containers, Documents, Confirmation, Closing Date, Closed By, ACTION
- বাটন/অ্যাকশন: Clear, Export, Shipment No, SO No, PO, Shipment Qty, Invoice Value, Shipment Date, Vessel, Containers, Documents, Confirmation, Closing Date, Closed By
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/04-এর `ss_shipments`:** এই সাব-মডিউলের সব কাগজ এর সাথে যুক্ত। শিপমেন্টের stage এখন কাগজপত্র অনুযায়ী নিজে থেকে এগোয় (`ShipmentBook::restage`)।
- **21/01-এর settings:**
  - প্রিফিক্স আর অঙ্কসংখ্যা: BKG (বুকিং), INV (ইনভয়েস), PL (প্যাকিং লিস্ট), DH (হস্তান্তর)।
  - "Require Export Docs" চালু থাকলে কনফার্মের আগে ডকুমেন্টের চেক লাগে।
- **ফাইল আপলোড:** প্রজেক্টের নিজস্ব পদ্ধতি দিয়ে (`StoreFileAttachment`, `ss_handover` টাইপ, FileApiController-এর দুই permission map-এ যোগ করা), আর একটা upload route handler (`/api/sales-shipment/handover-attachment`), কারণ server action-এর সীমা ১ MB।
- **21/02-এর shared UI আর Order-to-Shipment স্ট্রিপ:** ধাপ ৫–৮, ১১ আর ১২ এখন লিংক। ৯ আর ১০ বাকি, 21/06-এ আসবে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_24_000100_create_ss_shipment_papers.php`:

- **`ss_bookings`** (BKG-2026-000001):
  - forwarder, shipping_line, vessel_name, voyage_no, etd, eta, port_of_loading / discharge, final_destination।
  - container_type (8 ধরনের), container_qty।
  - status (REQUESTED / CONFIRMED / COMPLETED / CANCELLED)।
- **`ss_containers`:** shipment, booking, container_no (৪ অক্ষর + ৭ অঙ্ক, এক শিপমেন্টে একবার), type, seal, gross / net, cbm, cartons, vessel, voyage, loading / ETD / ETA।
- **`ss_invoices`** (INV-…) + লাইন:
  - হেডার: currency, commercial_invoice_ref, consignee, notify_party, country, port, mode, incoterm, payment_terms, lc_contract_no, state (DRAFT / FINAL / CANCELLED)।
  - লাইন: shipment line, description, qty, unit_price, cartons, net / gross।
- **`ss_packing_lists`** (PL-…) + কার্টন রান: shipment line, color, size, carton_from / to, pcs_per_carton, net / gross, cbm।
- **`ss_doc_handovers`** (DH-…):
  - invoice, packing list, exp_no, certificate, document_type, document_list, submitted_by / received_by।
  - status (PENDING / HANDED_OVER / RECEIVED / RETURNED), file_id / file_name।
- **`ss_shipments`-এ যোগ:** confirmed_at / by, closed_at / by।

## ৫. Workflow / Status ও Business Rules

- **শিপমেন্টের stage নিজে থেকে (খোলা থাকলে):**
  - সব চেক পাস → READY।
  - তা না হলে, চূড়ান্ত ইনভয়েস বা প্যাকিং থাকলে → DOCUMENTATION।
  - তা না হলে, জাহাজ ও ETD-সহ কনফার্মড বা সম্পন্ন বুকিং থাকলে → BOOKED।
  - না হলে PLANNING।
  - প্রতিটা বুকিং, কনটেইনার, ইনভয়েস, প্যাকিং আর হস্তান্তরের বদলে আবার হিসাব হয়।
- **Shipment Booking:**
  - শুধু প্রস্তুতির মধ্যে থাকা শিপমেন্টে। ETA, ETD-র আগে নয়; forwarder বাধ্যতামূলক।
  - Confirm → Complete; Cancel। REQUESTED / CONFIRMED অবস্থায় বদলানো যায়; REQUESTED / CANCELLED মোছা যায়।
- **Container / Vessel:**
  - কনটেইনার নম্বরের ফরম্যাট (ছোট হাতের লিখলেও বড় হাতের হয়) আর এক শিপমেন্টে একবার।
  - বুকিং ওই শিপমেন্টেরই হতে হবে; ফাঁকা ধরন, জাহাজ, ভয়েজ, ETD আর ETA বুকিং থেকে নেওয়া হয়।
- **Commercial Document Handover:**
  - নম্বর সেভে হয়; Submitted By ফাঁকা থাকলে লগইন করা ব্যবহারকারী।
  - Mark Handed Over → Mark Received (গ্রহণকারীর নাম লাগে); Mark Returned।
  - গ্রহণ হলে লক; শুধু অপেক্ষমাণটা মোছা যায়।
  - সংযুক্তি: PDF বা ছবি, ১০ MB পর্যন্ত; সারির "Attachment" কাজে ডাউনলোড হয়।
- **Shipment Invoice:**
  - বাঁদিকে খোলা শিপমেন্ট (যাদের ইনভয়েস বাকি)। বাছলে লাইন, রেট, কার্টন আর হেডারের ডিফল্ট আসে: মুদ্রা, ডেলিভারি পার্টির নাম ও ঠিকানা (কনসাইনি), দেশ, বন্দর, মাধ্যম, incoterm, পেমেন্টের শর্ত।
  - লাইন ≤ শিপমেন্ট লাইন − অন্য চালু ইনভয়েস। মূল্য = পরিমাণ × একক দাম।
  - Finalize (লক, প্রস্তুতিতে গোনা হয়) / Cancel; শুধু ড্রাফট বদলানো বা মোছা যায়।
  - তালিকায় মুদ্রা অনুযায়ী মোট (ক্যানসেল বাদে)।
- **Packing List:**
  - কার্টন রান: কার্টন = পর্যন্ত − থেকে + ১, পিস = কার্টন × পিস/কার্টন।
  - রেঞ্জ ১ থেকে, তালিকার মধ্যে ওভারল্যাপ নয়; মোট ওজন নিট ওজনের কম নয়।
  - শিপমেন্ট লাইনের বেশি প্যাক নয় (চালু সব প্যাকিং লিস্ট মিলিয়ে)।
  - "Add carton run" আগের রানের লাইন, রং, সাইজ আর প্যাক সাইজ নিয়ে পরের কার্টন থেকে শুরু করে।
- **শিপমেন্ট প্ল্যানের লক (21/04):** এখন Brickland-এর নোট অনুযায়ী: চালু ইনভয়েস বা প্যাকিং লিস্ট হলে প্ল্যানের লাইন লক।
- **Shipment Confirmation:**
  - চেকলিস্ট: শিপমেন্ট বেছে Check দিলে ৬টা চেক বিস্তারিতসহ দেখায়।
  - তালিকায় প্রতিটা চেকে ✓ / ✗:
    - Qty: চূড়ান্ত ইনভয়েসে কিছু আছে, শিপমেন্টের বেশি নয়, আর চূড়ান্ত প্যাকিং ঠিক ততটাই।
    - Shipping: জাহাজ ও ETD-সহ বুকিং।
    - Container: Sea / Sea-Air হলে অন্তত একটা।
    - Invoice আর Packing: চূড়ান্ত।
    - Documents: settings-এ বাধ্যতামূলক হলে হস্তান্তর বা গ্রহণ হওয়া একটা ডকুমেন্ট হস্তান্তর।
  - Confirm শুধু সব পাস হলে; বার্তায় কোন চেক বাকি তা বলে। কনফার্ম হলে চূড়ান্ত ইনভয়েসের পরিমাণ শিপমেন্ট লাইনে shipped হিসেবে লেখা হয়, আর সেলস অর্ডার তালিকায় Shipped Qty / Status বদলায়।
  - Unconfirm করলে shipped শূন্য হয়, আর শিপমেন্ট কাগজপত্র অনুযায়ী stage-এ ফেরে।
  - কনফার্মের পর বুকিং, কনটেইনার, ইনভয়েস আর প্যাকিং বদলায় না।
- **Shipment Closing:**
  - কনফার্মড আর বন্ধ শিপমেন্ট, সাথে শিপ পরিমাণ, চূড়ান্ত ইনভয়েসের মূল্য, জাহাজ, কনটেইনার, ডকুমেন্টের সংখ্যা আর কনফার্মেশনের তথ্য।
  - Close; Reopen শুধু অ্যাডমিন।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `salesshipment.shipment.view` / `manage` (21/04 থেকে)।
  - নতুন `salesshipment.shipment.confirm` (Confirm / Unconfirm / Close) আর `salesshipment.shipment.reopen` (অ্যাডমিন)।
  - FACTORY_ADMIN চারটাই পায়। FACTORY_MANAGER পায় view / manage / confirm। STORE_MANAGER, AUDITOR আর VIEWER পায় view।
- **API (`/sales-shipment/…`):**
  - `shipment-papers/options`
  - `bookings` (+ `{no}/{confirm|complete|cancel}`)
  - `containers` (+ `{shipment}/{container}`)
  - `handovers` (+ `{no}/{handed-over|received|returned}`, `{no}/attachment`)
  - `invoice-shipments`
  - `invoices` / `packing-lists` (+ `source/{shipment}`, `{no}/{finalize|cancel}`)
  - `confirmations-shipment` (+ `{no}/check|confirm|unconfirm`)
  - `closings` (+ `{no}/close|reopen`)
- **মেনু:** OPERATION-এর ভেতরে নতুন উপ-ফোল্ডার "Shipment", ৭টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/sales-shipment/shipment.jsx`।
  - সাধারণ ফর্মের তিনটা স্ক্রিন (Booking, Container, Handover) একটা generic `FormDesk` দিয়ে বানানো: তালিকা আর এন্ট্রি, ঘরগুলো spec থেকে।
  - Invoice আর Packing composite: বাঁদিকের তালিকা আর গ্রিড।
  - Confirmation-এ চেকলিস্ট আর ✓ / ✗ কলাম; Closing একটা রেজিস্টার।
- **আপলোড:** `app/api/sales-shipment/handover-attachment/route.js`।
- **প্রিন্ট:** Commercial Invoice, Packing List, Document Handover আর Shipment Confirmation।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৭টার ৭টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_24_000100_create_ss_shipment_papers.php`
- `Models/` — SsBooking, SsContainer, SsDocHandover, SsInvoice, SsInvoiceLine, SsPackingList, SsPackingLine (নতুন), SsShipment (বদলানো)
- `Support/ShipmentBook.php` (ইনভয়েস ও প্যাকিং হওয়া পরিমাণ, প্রস্তুতির চেক, stage)
- `Http/Controllers/Api/ShipmentPaperApiController.php` (বুকিং, কনটেইনার, হস্তান্তর), `ShipmentInvoiceApiController.php` (ইনভয়েস, প্যাকিং), `ShipmentConfirmApiController.php` (কনফার্মেশন, ক্লোজিং)
- `Http/Controllers/Api/ShipmentPlanApiController.php`, `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো: `ss_handover`)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/shipment.jsx` (নতুন)
- `frontend/src/app/api/sales-shipment/handover-attachment/route.js` (নতুন)
- `frontend/src/components/sales-shipment/shipment-plan.jsx`, `shared.jsx` (বদলানো)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentShipmentTest.php` (নতুন), `SalesShipmentPlanningTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentShipmentTest.php`):** test2 DB-তে পাস। Sales & Shipment-এর পাঁচ ফাইল মিলিয়ে ২৩টা টেস্ট, ৫৫০টা assertion। MenuAccess + RoleMatrix-ও পাস।
- **বুকিং ও কনটেইনার:** আটকায় ETD-র আগের ETA আর forwarder ছাড়া। BKG-2026-000001 সেভে stage PLANNING; Confirm-এর আগে Complete আটকায়; Confirm করলে BOOKED। তালিকায় "MAERSK KOWLOON / 245W", "1 × 40' HC"। কনটেইনারে ভুল নম্বর আটকায়; ছোট হাতের নম্বর বড় হাতের হয়; একই নম্বর দুবার আটকায়; ধরন, জাহাজ আর ETD বুকিং থেকে আসে। বুকিং ক্যানসেল করলে stage আবার PLANNING।
- **ইনভয়েস ও প্যাকিং:** source-এ পেমেন্টের শর্ত, বন্দর, বাকি ৪০০, রেট ২। আটকায় ৪০১ আর কনসাইনি ছাড়া। INV-2026-000001 (৭০০ পিস, ১৫৫০, USD); এরপর আরও ১ আটকায়। ইনভয়েস থাকলে প্ল্যান বদলানো আটকায় (sp_papered)। Finalize → DOCUMENTATION; তারপর বদল আটকায়। প্যাকিংয়ে আটকায় ০ থেকে শুরু, ওভারল্যাপ (সারি ২ বনাম ১), নিটের কম মোট ওজন আর বেশি প্যাক। PL-2026-000001: ৩৫ কার্টন / ৭০০ পিস। ইনভয়েস ক্যানসেল করলে বাকি আবার ৪০০।
- **কনফার্ম ও ক্লোজ:** Check-এ শুধু documents ✗; Confirm আটকায় ("Documents" বাকি)। DH-2026-000001 (Submitted By = মালিক)। সংযুক্তি আপলোড হয়; মালিক ডাউনলোড পান, TECHNICIAN পান না। Handed Over → READY; গ্রহণকারীর নাম ছাড়া Received আটকায়। FACTORY_MANAGER কনফার্ম করেন: shipped ৪০০ / ৩০০, সেলস অর্ডারে ৭০০ / Partially Shipped। কনফার্মের পর বুকিং আর ইনভয়েস ক্যানসেল আটকায়। Unconfirm → READY, shipped ০। আবার কনফার্ম → Close: ৭০০ / ১৫৫০ / MSKU1234567 / ১ ডকুমেন্ট / জাহাজ। বন্ধের পর Unconfirm আটকায়। FACTORY_MANAGER Reopen পারেন না; মালিক পারেন। AUDITOR দেখে, Close পারে না। STOREKEEPER বুকিং দেখে না।
- **টেস্টে ধরা পড়া:** রোলের প্রত্যাশা ঠিক করা হয়েছে (কনফার্ম করেন ফ্যাক্টরি ম্যানেজার, স্টোর ম্যানেজার নন)। টেস্টের একটা একক দাম ৪ দশমিকের বেশি ছিল, ফলে মূল্য ১৪৯৯.৯৯ আসছিল; সেটা ঠিক করা হয়েছে।

**UI চেক (`app_check/plans/shp01`; error 0, dev ডাটা রাখা আছে):** SHP-2026-000001-এর পুরো চক্র।
- **বুকিং:** BKG-2026-000001 (Expo Freight Ltd, Maersk, MAERSK KOWLOON / 245W, ETD ২৪ নভেম্বর, ETA ২০ ডিসেম্বর, 1 × 40' HC) → Confirmed।
- **কনটেইনার:** MSKU1234567 (সিল SL-9001, ৪২০০ / ৩৯০০ কেজি, ৬১.৫ m³, ৪৫ কার্টন); জাহাজ আর তারিখ বুকিং থেকে।
- **ইনভয়েস:** INV-2026-000001: ৯০০ পিস, ৪০৫০, ৪৫ কার্টন। কনসাইনি আগে থেকে ভরা (ডেলিভারি পার্টি); চূড়ান্ত করা হয়েছে। Commercial Invoice-এর প্রিন্ট দেখা হয়েছে।
- **প্যাকিং:** PL-2026-000001: দুই কার্টন রান (১–২০ আর ২১–৪৫, ২০ পিস করে), ৪৫ কার্টন / ৯০০ পিস / নিট ৪০৫ / মোট ৪৫০; চূড়ান্ত।
- **হস্তান্তর:** DH-2026-000001: ইনভয়েস, প্যাকিং লিস্ট, EXP-2026-11-0451, GSP Form A, Submitted By "Rahim Uddin"; Handed Over।
- **কনফার্মেশন:** Check-এ ৬টা চেকই ✓। Confirm → stage "Shipped"।
- **ক্লোজিং:** ৯০০ / ৪০৫০ / জাহাজ / MSKU1234567 / ১ ডকুমেন্ট → Closed।
- **সেলস অর্ডার তালিকা:** Shipped Qty ৯০০, "Partially Shipped" (অর্ডার ১১০০)।
- **চেকলিস্টের লেআউট:** ✓ আইকনটা কার্ডের মাঝখানে ঠেলে যাচ্ছিল; এখন বাঁদিকে।

## ১০. খোলা প্রশ্ন

১. **Assembly Floor-এর Ready স্টক:** আগের খোলা প্রশ্নের মতোই। শিপমেন্ট কনফার্ম হলে 20/07-এর ফ্লোর Ready স্টক (`asm_fg_ledger`) থেকে এখন কিছু কমে না; Sales & Shipment আর ফ্লোরের হিসাব আলাদা। কনফার্মেশন ফ্লোরের স্টক থেকে কমাবে কিনা জানাবেন।

২. **Documents চেক (21/06-এ সমাধান):** এখন EXP আর B/L বা AWB লাগে; দেখুন 21/06 Export Documentation। আগের প্রশ্ন: settings-এ "Require Export Docs" চালু থাকলে এখন হস্তান্তর বা গ্রহণ হওয়া একটা Commercial Document Handover লাগে। 21/06 Export Documentation বানানোর পর কি EXP আর B/L বা AWB লাগবে (settings-এর ইঙ্গিত যেমন বলে)? আমরা তখন চেকটা সেভাবেই বাড়াব।

৩. **Commercial Invoice-এর সংযোগ:** Brickland-এ ইনভয়েসে Commercial মডিউলের ইনভয়েস বাছাই করা যায়। আমাদের Commercial মডিউলে এখনো ইনভয়েস নেই, তাই এখন লিখে রাখার ঘর। Commercial মডিউল বানানোর পর বাছাইয়ের তালিকা হবে।

৪. **কনফার্মে কোন পরিমাণ shipped হবে:** Brickland অনুযায়ী চূড়ান্ত ইনভয়েসের পরিমাণ shipped হিসেবে লেখা হয় (প্যাকিং বা প্ল্যান নয়)। Qty চেকে প্যাকিং আর ইনভয়েস সমান লাগে; প্ল্যানের চেয়ে কম শিপ হলে বাকিটা অর্ডারে থাকে। এই নিয়ম ঠিক আছে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** RSC টুল দিয়ে নেওয়া হয়েছে কলাম, ফিল্টার, অবস্থা অনুযায়ী সারির কাজ আর confirm-এর লেখা, ফর্মের ঘর (Booking / Container / Handover-এর `fields` আর `formNote`), composite প্যানেল, Confirmation-এর Readiness গ্রুপ আর Check বাটন, আর পেজের সাবটাইটেল।
- **Commercial Document Handover-এর অবস্থা:** Brickland-এর ফর্মে Status একটা select, কিন্তু নোট বলে সারির কাজ দিয়ে এগোতে হয়। তাই ফর্মে শুধু পড়ার জন্য দেখায়।
- **UI চেকে ধরা পড়া:**
  - Packing-এ শিপমেন্ট বাছলে সব লাইনে "Carton From" = ১ বসত, ফলে দ্বিতীয় লাইন থেকে ওভারল্যাপ হত। এখন শুধু প্রথম লাইনে বসে।
  - রিস্টার্টের পর frontend সব রুটে 404 দিচ্ছিল (Turbopack-এর পুরোনো ক্যাশ)। পুরো stop / up করে ঠিক হয়েছে, memory-তে যেমন লেখা আছে।
