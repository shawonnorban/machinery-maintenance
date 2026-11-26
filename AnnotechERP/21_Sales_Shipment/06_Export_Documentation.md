# Export Documentation

> **স্ট্যাটাস:** ✅ সম্পন্ন (৭টার ৭টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/06`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | EXP / Export Documents | `/m/sales-delivery/export-documents` | 2 | 27 | 1 |
| 2 | Certificate Management | `/m/sales-delivery/certificate-management` | 2 | 28 | 1 |
| 3 | COO / Certificate of Origin | `/m/sales-delivery/certificate-of-origin` | 2 | 26 | 1 |
| 4 | GSP / REX | `/m/sales-delivery/gsp-rex` | 2 | 27 | 1 |
| 5 | Bill of Lading / AWB | `/m/sales-delivery/bl-awb` | 2 | 27 | 1 |
| 6 | Document Handover | `/m/sales-delivery/document-handover` | 1 | 15 | 0 |
| 7 | Shipment Attachments | `/m/sales-delivery/shipment-attachments` | 2 | 18 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### EXP / Export Documents

`/m/sales-delivery/export-documents`

**ট্যাব: Export Documents List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document Type | select |  | All, EXP, Inspection Certificate, Insurance Document, Other Document |
| Status | select |  | All, Pending, Received, Submitted, Approved, Cancelled |
| Document Date from | date |  |  |
| Document Date to | date |  |  |
| Search Export Documents with their type, number, shipment, buyer, dates, expiry, | search |  | Search 0 records… |
| Filter by Type | search |  |  |
| Filter by Document No | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Document Date | search |  |  |
| Filter by Issue Date | search |  |  |
| Filter by Expiry Date | search |  |  |
| Filter by Expiry | search |  |  |
| Filter by Issuing Authority | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment, ACTION
- বাটন/অ্যাকশন: Export Documents List 0, New export document, Clear, Export, Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment

**ট্যাব: New export document**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, EXP, Inspection Certificate, Insurance Document, Other Document |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New export document
- বাটন/অ্যাকশন: Export Documents List 0, New export document, Reset, Save export document

**ডায়ালগ: New export document**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, EXP, Inspection Certificate, Insurance Document, Other Document |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  |  |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Document Type is not one of the allowed values.

#### Certificate Management

`/m/sales-delivery/certificate-management`

**ট্যাব: Certificate List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Active, Cancelled |
| Validity | select |  | All, Valid, Expiring, Expired |
| Expiry Date from | date |  |  |
| Expiry Date to | date |  |  |
| Search Shipment certificates with their number, name, type, shipment, buyer and  | search |  | Search 0 records… |
| Filter by Certificate No | search |  |  |
| Filter by Certificate | search |  |  |
| Filter by Type | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by SO No | search |  |  |
| Filter by Issue Date | search |  |  |
| Filter by Expiry Date | search |  |  |
| Filter by Validity | search |  |  |
| Filter by Issuing Authority | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Certificate No, Certificate, Type, Shipment No, Buyer, SO No, Issue Date, Expiry Date, Validity, Issuing Authority, Attachment, ACTION
- বাটন/অ্যাকশন: Certificate List 0, New certificate, Clear, Export, Certificate No, Certificate, Type, Shipment No, Buyer, SO No, Issue Date, Expiry Date, Validity, Issuing Authority, Attachment

**ট্যাব: New certificate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Certificate Type | select |  | Select…, ISO 9001:2015 (DOC-00001), Membership Certificate (DOC-00002), Fire License (DOC-00003), CPP License (DOC-00004 |
| Certificate Name | text |  |  |
| Certificate No* | text | ✔ |  |
| Shipment* | select | ✔ | Select… |
| Issue Date* | date | ✔ |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Active, Cancelled |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New certificate
- বাটন/অ্যাকশন: Certificate List 0, New certificate, Reset, Save certificate

**ডায়ালগ: New certificate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Certificate Type | select |  | Select…, ISO 9001:2015 (DOC-00001), Membership Certificate (DOC-00002), Fire License (DOC-00003), CPP License (DOC-00004 |
| Certificate Name | text |  |  |
| Certificate No* | text | ✔ |  |
| Shipment* | select | ✔ | Select… |
| Issue Date* | date | ✔ |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Active, Cancelled |
| Attachment | text |  |  |
| Attach | file |  |  |
| Remarks | textarea |  |  |

#### COO / Certificate of Origin

`/m/sales-delivery/certificate-of-origin`

**ট্যাব: Certificate of Origin List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Pending, Received, Submitted, Approved, Cancelled |
| Document Date from | date |  |  |
| Document Date to | date |  |  |
| Search Certificate of Origin with their type, number, shipment, buyer, dates, ex | search |  | Search 0 records… |
| Filter by Type | search |  |  |
| Filter by Document No | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Document Date | search |  |  |
| Filter by Issue Date | search |  |  |
| Filter by Expiry Date | search |  |  |
| Filter by Expiry | search |  |  |
| Filter by Issuing Authority | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment, ACTION
- বাটন/অ্যাকশন: Certificate of Origin List 0, New certificate of origin, Clear, Export, Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment

**ট্যাব: New certificate of origin**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, Certificate of Origin |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New certificate of origin
- বাটন/অ্যাকশন: Certificate of Origin List 0, New certificate of origin, Reset, Save certificate of origin

**ডায়ালগ: New certificate of origin**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, Certificate of Origin |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  |  |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Shipment is required.

#### GSP / REX

`/m/sales-delivery/gsp-rex`

**ট্যাব: GSP / REX List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document Type | select |  | All, GSP, REX |
| Status | select |  | All, Pending, Received, Submitted, Approved, Cancelled |
| Document Date from | date |  |  |
| Document Date to | date |  |  |
| Search GSP / REX with their type, number, shipment, buyer, dates, expiry, issuin | search |  | Search 0 records… |
| Filter by Type | search |  |  |
| Filter by Document No | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Document Date | search |  |  |
| Filter by Issue Date | search |  |  |
| Filter by Expiry Date | search |  |  |
| Filter by Expiry | search |  |  |
| Filter by Issuing Authority | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment, ACTION
- বাটন/অ্যাকশন: GSP / REX List 0, New GSP / REX document, Clear, Export, Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment

**ট্যাব: New GSP / REX document**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, GSP, REX |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New GSP / REX document
- বাটন/অ্যাকশন: GSP / REX List 0, New GSP / REX document, Reset, Save GSP / REX document

**ডায়ালগ: New GSP / REX document**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, GSP, REX |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  |  |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Document Type is not one of the allowed values.

#### Bill of Lading / AWB

`/m/sales-delivery/bl-awb`

**ট্যাব: B/L / AWB List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document Type | select |  | All, Bill of Lading, Air Waybill |
| Status | select |  | All, Pending, Received, Submitted, Approved, Cancelled |
| Document Date from | date |  |  |
| Document Date to | date |  |  |
| Search B/L / AWB with their type, number, shipment, buyer, dates, expiry, issuin | search |  | Search 0 records… |
| Filter by Type | search |  |  |
| Filter by Document No | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Document Date | search |  |  |
| Filter by Issue Date | search |  |  |
| Filter by Expiry Date | search |  |  |
| Filter by Expiry | search |  |  |
| Filter by Issuing Authority | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment, ACTION
- বাটন/অ্যাকশন: B/L / AWB List 0, New bill of lading / air waybill, Clear, Export, Type, Document No, Shipment No, Buyer, Document Date, Issue Date, Expiry Date, Expiry, Issuing Authority, Attachment

**ট্যাব: New bill of lading / air waybill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, Bill of Lading, Air Waybill |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New bill of lading / air waybill
- বাটন/অ্যাকশন: B/L / AWB List 0, New bill of lading / air waybill, Reset, Save bill of lading / air waybill

**ডায়ালগ: New bill of lading / air waybill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| Document Type* | select | ✔ | Select…, Bill of Lading, Air Waybill |
| Document No* | text | ✔ |  |
| Document Date* | date | ✔ |  |
| Issue Date | date |  |  |
| Expiry Date | date |  |  |
| Issuing Authority | text |  |  |
| Status | select |  | Select…, Pending, Received, Submitted, Approved, Cancelled |
| Attachment | text |  |  |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Document Type is not one of the allowed values.

#### Document Handover

`/m/sales-delivery/document-handover`

**ট্যাব: Document Handover List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Pending, Handed Over, Received, Returned |
| Handover Date from | date |  |  |
| Handover Date to | date |  |  |
| Search Document Handover records with their number, date, shipment, buyer, the d | search |  | Search 0 records… |
| Filter by Handover No | search |  |  |
| Filter by Date | search |  |  |
| Filter by Shipment No | search |  |  |
| Filter by Buyer | search |  |  |
| Filter by Invoice No | search |  |  |
| Filter by Packing No | search |  |  |
| Filter by Documents | search |  |  |
| Filter by Sender | search |  |  |
| Filter by Receiver | search |  |  |
| Filter by Attachment | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Handover No, Date, Shipment No, Buyer, Invoice No, Packing No, Documents, Sender, Receiver, Attachment, ACTION
- বাটন/অ্যাকশন: Document Handover List 0, Save document handover, Clear, Export, Handover No, Date, Shipment No, Buyer, Invoice No, Packing No, Documents, Sender, Receiver, Attachment

#### Shipment Attachments

`/m/sales-delivery/shipment-attachments`

**ট্যাব: Attachment List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document Type | select |  | All, Commercial Invoice, Packing List, EXP, B/L, AWB, Certificate, Inspection Report, Insurance, Photo, Other |
| Upload Date from | date |  |  |
| Upload Date to | date |  |  |
| Search Files attached to shipments, with the shipment, order, document type, fil | search |  | Search 0 records… |
| Filter by Shipment No | search |  |  |
| Filter by SO No | search |  |  |
| Filter by Document Type | search |  |  |
| Filter by File Name | search |  |  |
| Filter by File | search |  |  |
| Filter by Uploaded By | search |  |  |
| Filter by Upload Date | search |  |  |
| Filter by Remarks | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Shipment No, SO No, Document Type, File Name, File, Uploaded By, Upload Date, Remarks, ACTION
- বাটন/অ্যাকশন: Attachment List 0, New attachment, Clear, Export, Shipment No, SO No, Document Type, File Name, File, Uploaded By, Upload Date, Remarks

**ট্যাব: New attachment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Shipment* | select | ✔ | Select… |
| File Name* | text | ✔ | Signed commercial invoice |
| Attachment* | text | ✔ | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
- সেকশন: New attachment
- বাটন/অ্যাকশন: Attachment List 0, New attachment, Select…, Reset, Save attachment
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/04–21/05-এর `ss_shipments`:** সব কাগজ শিপমেন্টের সাথে যুক্ত। প্রতিটা বদলের পর শিপমেন্টের stage আবার হিসাব হয় (`ShipmentBook::restage`)।
- **21/05-এর `ss_doc_handovers` আর `FormDesk`:**
  - Document Handover স্ক্রিন একই টেবিল ব্যবহার করে, নতুন `kind` কলাম দিয়ে আলাদা: COMMERCIAL (21/05) বা FINAL (21/06)।
  - চারটা ডকুমেন্ট স্ক্রিন, Certificate আর Attachment একই generic তালিকা + ফর্ম ডেস্কে বানানো।
- **Certificate Master:** 03-এর `company_documents` (Certificate Type ড্রপডাউন)। নতুন মাস্টার বানানো হয়নি।
- **21/01-এর settings:**
  - `cert_alert_days`: মেয়াদ শেষের কতদিন আগে "Expiring" দেখাবে।
  - `require_export_docs`: শিপমেন্ট কনফার্মের Documents চেক।
- **ফাইল আপলোড:** `StoreFileAttachment`, তিনটা নতুন টাইপ (`ss_export_doc`, `ss_certificate`, `ss_attachment`), FileApiController-এর দুই map-এ যোগ করা। একটা generic upload route handler (`/api/sales-shipment/upload?path=…`, শুধু তালিকাভুক্ত পাথ) দিয়ে যায়, কারণ server action-এর সীমা ১ MB।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_25_000100_create_ss_export_documentation.php`:

- **`ss_export_docs`:** চারটা ডকুমেন্ট স্ক্রিনের এক টেবিল।
  - family (EXPORT / COO / GSP / BLAWB) আর doc_type:
    - EXPORT: EXP, Inspection Certificate, Insurance Document, Other Document।
    - COO: Certificate of Origin।
    - GSP: GSP, REX।
    - BLAWB: Bill of Lading, Air Waybill।
  - shipment, document_no (কোম্পানি + ধরন অনুযায়ী অনন্য), document_date, issue_date, expiry_date, issuing_authority।
  - status (PENDING / RECEIVED / SUBMITTED / APPROVED / CANCELLED), remarks, file_id / file_name।
- **`ss_certificates`:** certificate_type_id (→ `company_documents`), certificate_name, certificate_no (এক শিপমেন্টে একবার), shipment, issue / expiry, issuing_authority, status (ACTIVE / CANCELLED), file।
- **`ss_shipment_attachments`:** shipment, document_type (১০ ধরন: Commercial Invoice, Packing List, EXP, B/L, AWB, Certificate, Inspection Report, Insurance, Photo, Other), title, file, uploaded_by / at।
- **`ss_doc_handovers`-এ যোগ:** `kind` (COMMERCIAL / FINAL)।

## ৫. Workflow / Status ও Business Rules

- **সব স্ক্রিনে:** বন্ধ বা ক্যানসেল হওয়া শিপমেন্টে কিছু যোগ, বদল বা মোছা যায় না (`v.ed_closed`)। শিপমেন্ট ড্রপডাউনে শুধু খোলা শিপমেন্ট।
- **চারটা ডকুমেন্ট স্ক্রিন (EXP / COO / GSP-REX / B/L-AWB):**
  - একই নিয়ম, শুধু ধরনের তালিকা আলাদা। এক ধরনের স্ক্রিনে (COO) ধরন নিজে থেকে বসে।
  - মেয়াদ ইস্যুর আগে নয়। একই ধরন আর নম্বর দুবার নয়।
  - সারির কাজ: Mark Received (Pending থেকে) → Mark Submitted → Mark Approved। Approved বা Cancelled হলে লক।
  - মেয়াদের কলাম: Valid / Expiring (n days) / Expired, `cert_alert_days` ধরে।
  - সংযুক্তি: PDF বা ছবি; সারির "File" কাজে ডাউনলোড।
- **Certificate Management:**
  - Certificate Type বাছলে, নাম আর issuing authority ফাঁকা থাকলে মাস্টার থেকে বসে।
  - এক শিপমেন্টে একই নম্বর একবার। তালিকায় Validity ফিল্টার (valid / expiring / expired)।
- **Document Handover (চূড়ান্ত, ব্যাংক বা বায়ারকে):**
  - 21/05-এর হস্তান্তরের মতোই নম্বর (DH-…), অবস্থা আর সংযুক্তি। তবে Document List বাধ্যতামূলক, আর ঘরের নাম Sender / Receiver।
  - তালিকায় শুধু FINAL; Commercial Document Handover-এ শুধু COMMERCIAL।
- **Shipment Attachments:** যেকোনো খোলা শিপমেন্টে ধরন আর নাম দিয়ে ফাইল রাখা যায় (PDF / PNG / JPG / WEBP, ৫ MB পর্যন্ত)। বদলালে নতুন ফাইল আগেরটার জায়গা নেয়।
- **শিপমেন্ট কনফার্মের Documents চেক (21/05-এর খোলা প্রশ্ন ২-এর সমাধান):** "Require Export Docs" চালু থাকলে এখন লাগে একটা EXP আর একটা Bill of Lading বা Air Waybill (ক্যানসেল বাদে)। আগে Commercial Document Handover লাগত; সেটা আর Documents চেকে গোনা হয় না।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - নতুন `salesshipment.exportdoc.view` / `manage`।
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়। STORE_MANAGER, AUDITOR আর VIEWER পায় view। STOREKEEPER কিছু পায় না।
  - চূড়ান্ত হস্তান্তর exportdoc পারমিশনে চলে; কমার্শিয়ালটা আগের মতো shipment পারমিশনে। সারির কাজ, সংযুক্তি আর মোছা হস্তান্তরের kind দেখে পারমিশন মেলায়।
- **API (`/sales-shipment/…`):**
  - `export-documentation/options`
  - `export-docs/{export|coo|gsp|blawb}` (GET / POST, PUT `/{id}`)
  - `export-doc-items/{id}` (DELETE, POST `/{received|submitted|approved}`)
  - `certificates` (CRUD)
  - `attachments-of/{export-docs|certificates}/{id}`
  - `shipment-attachments` (GET, POST, POST `/{id}`, DELETE)
  - `final-handovers` (GET, POST, PUT `/{no}`); সারির কাজ আগের `handovers/{no}/…` দিয়েই।
- **মেনু:** OPERATION-এর ভেতরে নতুন উপ-ফোল্ডার "Export Documentation", ৭টা স্ক্রিন। Order-to-Shipment স্ট্রিপের ধাপ ৯ আর ১০ এখন লিংক; ১২টা ধাপই এখন লিংক।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/sales-shipment/export-docs.jsx`।
  - `DocDesk(family)`: চারটা ডকুমেন্ট স্ক্রিন।
  - `CertificateDesk`, `AttachmentDesk`।
  - সবগুলো 21/05-এর `FormDesk`-এর ওপর।
- **Document Handover:** `HandoverDesk kind="FINAL"` (21/05-এর কম্পোনেন্ট, নিজস্ব লেবেল আর প্রিন্টের শিরোনাম)।
- **আপলোড:** `app/api/sales-shipment/upload/route.js`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৭টার ৭টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_25_000100_create_ss_export_documentation.php`
- `Models/` — SsExportDoc, SsCertificate, SsShipmentAttachment (নতুন), SsDocHandover (বদলানো: `kind`)
- `Http/Controllers/Api/ExportDocApiController.php` (নতুন)
- `Http/Controllers/Api/ShipmentPaperApiController.php` (বদলানো: চূড়ান্ত হস্তান্তর), `Support/ShipmentBook.php` (বদলানো: Documents চেক), `Routes/api.php` (বদলানো)
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো: তিনটা নতুন টাইপ)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/export-docs.jsx` (নতুন)
- `frontend/src/app/api/sales-shipment/upload/route.js` (নতুন)
- `frontend/src/components/sales-shipment/shipment.jsx`, `shared.jsx` (বদলানো)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentExportDocsTest.php` (নতুন), `SalesShipmentShipmentTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentExportDocsTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট আর ৯৪টা assertion। Sales & Shipment-এর ছয় ফাইল মিলিয়ে ২৬টা টেস্ট, ৬৪৮টা assertion। MenuAccess + RoleMatrix-ও পাস (১৩টা টেস্ট)।
- **ডকুমেন্ট (family অনুযায়ী):**
  - Export স্ক্রিনে COO ধরন আটকায়। ইস্যুর আগের মেয়াদ আটকায়। একই ধরন আর নম্বর দুবার আটকায়, কিন্তু অন্য ধরনে একই নম্বর চলে।
  - মেয়াদ: EXP Expiring (১২ দিন), Inspection Valid (১১৩ দিন), Insurance Expired। COO তালিকায় এগুলো আসে না।
  - Pending থেকে সরাসরি Approved আটকায়; Received → Submitted → Approved হয়।
  - সংযুক্তি আপলোড হয়; মালিক ডাউনলোড পান, TECHNICIAN পান না।
- **Documents চেক:** শুধু EXP থাকলে ✗; AWB যোগ করলে ✓; AWB ক্যানসেল করলে আবার ✗। REX আর COO সেভ হয়।
- **বন্ধ শিপমেন্ট:** নতুন ডকুমেন্ট আর মোছা আটকায়; সারিগুলো লক দেখায়।
- **সার্টিফিকেট:** Company Documents থেকে ধরন আসে ("ISO 9001:2015 (DOC-00001)")। ধরন বা নাম ছাড়া আটকায়। ধরন বাছলে নাম আর authority বসে। এক শিপমেন্টে একই নম্বর দুবার আটকায়। মেয়াদ Expiring (২৩ দিন)।
- **হস্তান্তরের kind:**
  - Document List ছাড়া চূড়ান্ত হস্তান্তর আটকায়। চূড়ান্তটা পায় DH-2026-000001, কমার্শিয়ালটা DH-2026-000002 (একই নম্বরের ধারা)। প্রতিটা তালিকায় শুধু নিজের kind।
  - STORE_MANAGER চূড়ান্ত হস্তান্তর দেখেন কিন্তু বানাতে পারেন না; FACTORY_MANAGER পারেন।
- **শিপমেন্ট সংযুক্তি:** ফাইল ছাড়া, docx আর ৬ MB আটকায়। PNG সেভ হয়। ফাইল ছাড়া এডিট করলে আগের ফাইল থাকে। AUDITOR দেখেন, মুছতে পারেন না। STOREKEEPER সার্টিফিকেট দেখেন না।
- **21/05-এর টেস্ট বদলানো:** হস্তান্তরের পর stage এখন DOCUMENTATION। EXP আর তারপর B/L দিলে READY হয়।

**UI চেক (`app_check/plans/ed01`, `ed02`, `ed03`; শেষ রানে error 0, dev ডাটা রাখা আছে):**
- **নতুন শিপমেন্ট:** SHP-2026-000001 বন্ধ, তাই SO-20261107-0001-এর বাকি ২০০ পিস দিয়ে SHP-2026-000002 প্ল্যান করা হয়েছে (ETD ২৫ নভেম্বর, ex-factory ২৩ নভেম্বর)।
- **EXP:** EXP-2026-11-0602 (Sonali Bank, Agrabad), PDF সংযুক্ত। Mark Received → Received।
- **COO:** COO-EPB-778812 (EPB Chattogram), মেয়াদ ২০ নভেম্বর → "Expiring · 12 days left"।
- **GSP / REX:** REX-BD-2026-0091। **B/L / AWB:** Bill of Lading MAEU245W00871 (Maersk Line)।
- **সার্টিফিকেট:** OEKO-TEX Standard 100, OT-22.HBD.11873 (Hohenstein), মেয়াদ ২৫ নভেম্বর → "Expiring · 17 days left"। PDF সংযুক্ত। টাইলে Certificates ১ / Expiring ১।
- **Document Handover:** DH-2026-000002: EXP নম্বর, ডকুমেন্টের তালিকা, Sender "Rahim Uddin" (ফাঁকা রাখায়), Receiver Sonali Bank → Handed Over।
- **শিপমেন্ট সংযুক্তি:** Inspection Report "Final inspection report (signed)", exp-scan.pdf, আপলোডকারী Rahim Uddin। এডিটে নাম বদলায় আর নতুন ফাইল বসে।
- **Shipment Confirmation-এর Check:** Documents চেকের বর্ণনা এখন "the EXP and the B/L or AWB filed (Export Documentation)"।

## ১০. খোলা প্রশ্ন

১. **Documents চেকে আর কী লাগবে:** এখন "Require Export Docs" চালু থাকলে লাগে একটা EXP আর একটা B/L বা AWB। COO, GSP / REX বা কোনো সার্টিফিকেটও কি বাধ্যতামূলক হবে (যেমন নির্দিষ্ট বায়ার বা দেশের জন্য)? আর ডকুমেন্টকে কি অন্তত "Received" হতে হবে, নাকি Pending অবস্থাতেই গোনা হবে?

২. **Certificate Master:** Brickland-এ Certificate Management একটা "Certificate Master" থেকে ধরন নেয়। আমরা 03-এর Company Documents মাস্টার ব্যবহার করছি, আলাদা মাস্টার বানাইনি। এতে চলবে কিনা জানাবেন।

৩. **চূড়ান্ত হস্তান্তর আর Commercial-এর Document Submission:** Document Handover (ব্যাংক বা বায়ারকে) এখন শুধু শিপমেন্ট পর্যায়ের রেকর্ড। Commercial মডিউলের Export Document Submission বানানোর পর এটা কি সেখান থেকে টানা হবে, নাকি দুটো আলাদা থাকবে?

৪. **বন্ধ শিপমেন্টের ডকুমেন্ট:** শিপমেন্ট বন্ধ হলে কোনো ডকুমেন্ট বা সংযুক্তি যোগ বা বদল করা যায় না। কিন্তু B/L-এর মূল কপি বা ব্যাংকের অনুমোদন অনেক সময় শিপমেন্টের পরে আসে। বন্ধের পরও কি ডকুমেন্টের অবস্থা (Received / Approved) বদলানো আর ফাইল যোগ করা যাবে?

## ১১. As-built নোট

- **Brickland প্রোব:** RSC টুল দিয়ে নেওয়া হয়েছে সাতটা স্ক্রিনের কলাম, ফিল্টার, স্ট্যাট টাইল, সারির কাজ (অবস্থা অনুযায়ী), ফর্মের ঘর আর নোট। চারটা ডকুমেন্ট স্ক্রিন একই গঠনের, শুধু ধরনের তালিকা আলাদা। তাই এক টেবিল আর এক কম্পোনেন্ট `family` দিয়ে।
- **Document Handover:** Brickland-এ এর আলাদা স্ক্রিন, কিন্তু ঘরগুলো Commercial Document Handover-এর মতোই (শুধু Sender / Receiver আর বাধ্যতামূলক Document List)। তাই একই টেবিল আর `kind` কলাম; নম্বরের ধারা (DH-) দুটোর একটাই।
- **Documents চেক বদলানো:** 21/05-এর খোলা প্রশ্ন ২ অনুযায়ী এখন EXP আর B/L বা AWB লাগে। Commercial Document Handover আর এই চেকে লাগে না।
- **UI চেকে ধরা পড়া:**
  - Upload route-এর whitelist শুধু বড় হাতের ULID মানত, কিন্তু Laravel ULID ছোট হাতে রাখে। ফলে ডকুমেন্ট আর সার্টিফিকেটের সংযুক্তি, আর শিপমেন্ট সংযুক্তির এডিট 404 দিচ্ছিল। এখন case-insensitive।
  - হস্তান্তরের ফর্মে Status ঘরে "PENDING" কাঁচা লেখা দেখাচ্ছিল (21/05-এর Commercial স্ক্রিনেও)। এখন অনুবাদ করা লেবেল ("Pending")।
  - Route ফাইল বদলের পর frontend পুরোনো কোড চালাচ্ছিল। `docker compose restart frontend` করে ঠিক হয়েছে।
