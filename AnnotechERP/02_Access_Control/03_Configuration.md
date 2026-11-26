# Configuration

> **স্ট্যাটাস:** 🟡 আংশিক (Approval Workflow Designer, Report Template Setup, Mail Service Configuration সম্পন্ন; Ledger Integration Finance-এর সাথে)
> **মডিউল:** [Access Control](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `02/03`

**⬅ ফিরে যান:** [Access Control](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Approval Workflow Designer | `/m/security/request-and-approval-work-flow` | 4 | 11 | 1 |
| 2 | Report Template Setup | `/m/security/report-template` | 3 | 10 | 1 |
| 3 | Mail Service Configuration | `/m/security/mail-service-config` | 2 | 18 | 1 |
| 4 | Ledger Integration Setup | `/m/security/gl-configuration` | 3 | 19 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Approval Workflow Designer

`/m/security/request-and-approval-work-flow`

**ট্যাব: Records 4**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Approval action types with their short name, document, screen and how man | search |  | Search 4 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type Code, Action Type Name, Short Name, Workflow Type, Page URL, Actions, Status, ACTION
- বাটন/অ্যাকশন: Approval Actions, Action Assignment, New action type, Export, Type Code, Action Type Name, Short Name, Workflow Type, Page URL, Actions, Status, Edit

**ট্যাব: Approval Actions**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search action types | search |  | Search… |
- সেকশন: Master List, Actions
- বাটন/অ্যাকশন: Approval Actions, Action Assignment, New action type, AT-003 — Yarn Return Approval Workflow 3

**ট্যাব: Action Assignment**
- সেকশন: Action Assignment
- বাটন/অ্যাকশন: Approval Actions, Action Assignment, New action type, -- Select action --

**ট্যাব: New action type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type Code* | text | ✔ | AT-011 |
| Type Name* | text | ✔ | Yarn Return Approval Workflow |
| Short Name | text |  | YRet |
| Document Type* | text | ✔ | Yarn Return |
| Workflow Type | select |  | Select…, Approved By End User, Purchase Requisition |
| Page URL | text |  | /m/purchase/yarn-return |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Is Active | checkbox |  |  |
- সেকশন: New action type
- বাটন/অ্যাকশন: Approval Actions, Action Assignment, New action type, Reset, Save action type

**ডায়ালগ: New action type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Type Code* | text | ✔ |  |
| Type Name* | text | ✔ |  |
| Short Name | text |  |  |
| Document Type* | text | ✔ |  |
| Workflow Type | select |  | Select…, Approved By End User, Purchase Requisition |
| Page URL | text |  |  |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Is Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Type Code is required.

#### Report Template Setup

`/m/security/report-template`

**ট্যাব: Records 13**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Report templates with output format, paper size, orientation and the menu | search |  | Search 13 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Template, Report Path, Format, Paper, Orientation, Buyers, Menus, Updated, Status, ACTION
- বাটন/অ্যাকশন: Template & Buyer Mapping, New report template, Export, Code, Template, Report Path, Format, Paper, Orientation, Buyers, Menus, Updated, Status, Edit

**ট্যাব: Template & Buyer Mapping**
- সেকশন: Template & Buyer Mapping
- বাটন/অ্যাকশন: Template & Buyer Mapping, New report template, -- Select report template --

**ট্যাব: New report template**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Template Code* | text | ✔ | RPT-PO |
| Template Name* | text | ✔ | Purchase Order Print |
| Report Path | text |  | rptBundleCard |
| Output Format* | select | ✔ | Select…, Pdf, Excel, Csv, Html |
| Orientation | select |  | Select…, Portrait, Landscape |
| Paper Size | select |  | Select…, A4, A5, Letter, Legal |
| Description | textarea |  |  |
| Active | checkbox |  |  |
- সেকশন: New report template
- বাটন/অ্যাকশন: Template & Buyer Mapping, New report template, Reset, Save report template

**ডায়ালগ: New report template**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Template Code* | text | ✔ |  |
| Template Name* | text | ✔ |  |
| Report Path | text |  |  |
| Output Format* | select | ✔ | Select…, Pdf, Excel, Csv, Html |
| Orientation | select |  | Select…, Portrait, Landscape |
| Paper Size | select |  | Select…, A4, A5, Letter, Legal |
| Description | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Template Code is required.

#### Mail Service Configuration

`/m/security/mail-service-config`

**ট্যাব: Records 6**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Mail services with the module they send for, their recipients, relay and  | search |  | Search 6 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Service Name, Module Name, Title, To Mail, CC Mail, BCC Mail, Relay, Password Source, Remarks, Created, Status, ACTION
- বাটন/অ্যাকশন: New mail service, Export, Service Name, Module Name, Title, To Mail, CC Mail, BCC Mail, Relay, Password Source, Remarks, Created, Status, Edit

**ট্যাব: New mail service**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Service Name* | text | ✔ | AutoMailSend4OrderConfirmListService |
| Title | text |  | Order confirmation list |
| To Mail | text |  | ac.saidur@multi-fabs.com, dev.reja@multi-fabs.com |
| CC Mail | text |  |  |
| BCC Mail | text |  |  |
| SMTP Host* | text | ✔ | smtp.bricklandcompositeltd.com |
| Port* | number | ✔ |  |
| SMTP Username* | text | ✔ | erp-notifications |
| Password Environment Variable* | text | ✔ | SMTP_PASSWORD |
| Encryption* | select | ✔ | Select…, None, Ssl, Tls, Starttls |
| From Name* | text | ✔ | Brickland ERP |
| From Address* | text | ✔ | no-reply@bricklandcompositeltd.com |
| Reply-To Address | text |  | it-support@bricklandcompositeltd.com |
| Remarks | textarea |  |  |
| Default relay | checkbox |  |  |
| Is Active | checkbox |  |  |
- সেকশন: New mail service
- বাটন/অ্যাকশন: New mail service, Select…, Reset, Save mail service

**ডায়ালগ: New mail service**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Service Name* | text | ✔ |  |
| Title | text |  |  |
| To Mail | text |  |  |
| CC Mail | text |  |  |
| BCC Mail | text |  |  |
| SMTP Host* | text | ✔ |  |
| Port* | number | ✔ |  |
| SMTP Username* | text | ✔ |  |
| Password Environment Variable* | text | ✔ |  |
| Encryption* | select | ✔ | Select…, None, Ssl, Tls, Starttls |
| From Name* | text | ✔ |  |
| From Address* | text | ✔ |  |
| Reply-To Address | text |  |  |
| Remarks | textarea |  |  |
| Default relay | checkbox |  |  |
| Is Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Password Environment Variable is required.

#### Ledger Integration Setup

`/m/security/gl-configuration`

**ট্যাব: Records 4**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Posting sources per company, with their mapping type, originating screen  | search |  | Search 4 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Company, Source Name, Source Name (BN), Mapping Type, Menu, Lines, Editable From GL, Updated, Status, ACTION
- বাটন/অ্যাকশন: GL Configuration Entry, New GL source, Export, Company, Source Name, Source Name (BN), Mapping Type, Menu, Lines, Editable From GL, Updated, Status, Edit

**ট্যাব: GL Configuration Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing GL source | select |  | Bank Bill Collection — BCL · Bank Bill Collection, Dyes & Chemical Receipt — BCL · General, Export Sales Invoice — BCL · |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Source Name (English)* | text | ✔ | Bank Bill Collection |
| Source Name (Bangla) | text |  |  |
| GL Mapping Type* | select | ✔ | Select…, General, Bank Bill Collection |
| Link Ref | text |  |  |
| Final Bill Collection Bank bill sources only — this header covers the final sett | checkbox |  |  |
| Is Editable From GL | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: GL Configuration Entry, COA Detail Data
- বাটন/অ্যাকশন: GL Configuration Entry, New GL source, Select…, Add COA line, Clear, Save GL source

**ট্যাব: New GL source**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Source Name (English)* | text | ✔ | Bank Bill Collection |
| Source Name (Bangla) | text |  |  |
| GL Mapping Type* | select | ✔ | Select…, General, Bank Bill Collection |
| Link Ref | text |  |  |
| Final Bill Collection Bank bill sources only. | checkbox |  |  |
| Is Editable From GL | checkbox |  |  |
| Active | checkbox |  |  |
- সেকশন: New GL source
- বাটন/অ্যাকশন: GL Configuration Entry, New GL source, Select…, Reset, Save GL source

**ডায়ালগ: New GL source**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Source Name (English)* | text | ✔ |  |
| Source Name (Bangla) | text |  |  |
| GL Mapping Type* | select | ✔ | Select…, General, Bank Bill Collection |
| Link Ref | text |  |  |
| Final Bill Collection Bank bill sources only. | checkbox |  |  |
| Is Editable From GL | checkbox |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Company is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Approval মডিউল (`approval_workflows`, `ApprovalEntities::MODELS`) = Approval Workflow Designer
- Finance (`gl_accounts`, `gl_event_mappings`, `GlPoster`) = Ledger Integration Setup
- Laravel mail config = Mail Service

## ৪. ডাটা মডেল (Entity ও Field)

- **Approval Workflow Designer:** বিদ্যমান `approval_workflows` + Brickland-এর "Action Type" (type_code, name, short_name, document_type, workflow_type, page_url, company, active) আর Action Assignment (কোন ধাপে কোন রোল বা ইউজার)। বিদ্যমান সংজ্ঞায় এগুলো ম্যাপ হবে।
- **`report_templates` (নতুন):** code, name, report_path, output_format (`PDF`/`EXCEL`/`CSV`/`HTML`), orientation, paper_size, description, active; `report_template_buyers` (বায়ার-ভিত্তিক প্রিন্ট-লেআউট, Buyer master আসার পরে)
- **`mail_services` (নতুন):** service_name, module, title, to/cc/bcc, smtp_host, port, username, **password_env** (শুধু এনভায়রনমেন্ট-ভ্যারিয়েবলের নাম; পাসওয়ার্ড DB-তে থাকে না, Brickland-এর মতোই), encryption, from_name, from_address, reply_to, remarks, is_default, active
- **Ledger Integration:** Brickland-এর GL Source (company, source name en/bn, mapping type General/Bank Bill Collection, link ref, final bill collection, editable from GL, COA lines)। বিদ্যমান `gl_event_mappings`-এর সম্প্রসারণ, Finance & Ledger মডিউলের (২৩) সাথে চূড়ান্ত হবে।

## ৫. Workflow / Status ও Business Rules

- Mail: Password Environment Variable বাধ্যতামূলক (Brickland-এর নিয়ম, যাতে পাসওয়ার্ড কখনো DB-তে না থাকে)। "Default relay" একটাই থাকে।
- Report Template: Template Code বাধ্যতামূলক আর ইউনিক।

## ৬. API ও পারমিশন

`/erp/report-templates` আর `/erp/mail-services` (স্ক্রিন-কিটের resource API: list, show, create, update, delete); পারমিশন `settings.company.manage`।

## ৭. Frontend পেজ (লাইট থিম)

Report Template Setup: Records | New report template। Mail Service Configuration: Records | New mail service (৩ কলামের ফর্ম)। Template & Buyer Mapping ট্যাব Buyer মাস্টারের সাথে আসবে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (Report Template Setup, Mail Service Configuration):

**Backend**
- `app/Modules/Erp/Support/ResourceDefinition.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Erp/Http/Controllers/Api/ResourceApiController.php`
- `app/Modules/Erp/Routes/api.php`
- `app/Modules/Identity/Database/Migrations/2026_10_22_000200_create_access_configuration_tables.php`
- `app/Modules/Identity/Models/ReportTemplate.php`
- `app/Modules/Identity/Models/MailService.php`
- `app/Modules/Identity/Resources/ReportTemplateResource.php`
- `app/Modules/Identity/Resources/MailServiceResource.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/erp/grouped-records.jsx`
- `frontend/src/app/(app)/access-control/[tab]/config-screens.js`
- `frontend/src/app/(app)/access-control/[tab]/form-actions.js`
- `lang/en/access.php`
- `lang/bn/access.php`

**Tests**
- `tests/Feature/Identity/AccessConfigurationResourcesTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

`tests/Feature/Identity/AccessConfigurationResourcesTest.php`: টেমপ্লেট তৈরি/ইউনিক কোড/এডিট/মোছা, অন্য কোম্পানির id 404; মেইল-রিলের পাসওয়ার্ড-ভ্যারিয়েবলের নিয়ম আর একটাই ডিফল্ট।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**আংশিক সম্পন্ন (২০২৬-১০-১০):** Report Template Setup আর Mail Service Configuration।
- **নতুন স্ক্রিন-কিট (ERP Resource):** `app/Modules/Erp`-এ `ResourceDefinition`, `ResourceRegistry`, `ResourceApiController`, আর রুট `/api/v1/erp/{resource}`।
  - একটা ক্লাসে মডেল, পারমিশন (দেখা আর বদলানো), ভ্যালিডেশন, দেখানোর কলাম আর beforeSave/afterSave হুক লিখলেই list/show/create/update/delete চলে, টেন্যান্ট-সুরক্ষা সহ।
  - ফ্রন্টএন্ডে `resourceScreen(config)` Records | (গ্রুপ-ট্যাব) | New বানায়।
  - Brickland-এর বাকি "Records + New X" স্ক্রিনগুলো এভাবে দ্রুত বানানো যাবে।
- **মেইল আসলে পাঠানো** (রিলে দিয়ে) নোটিফিকেশনের ইমেইল-চ্যানেলের সাথে যুক্ত হবে। এখন কনফিগারেশন সেভ হয়, পাসওয়ার্ড কখনো DB-তে যায় না।
- **বাকি:**
  - Approval Workflow Designer: বর্তমান Approval মডিউলের সাথে, পরের মডিউল Approval Center (04)-এ।
  - Ledger Integration Setup: Finance মডিউলে।
  - Template & Buyer Mapping: Buyer মাস্টারের পরে।

### Approval Workflow Designer — সম্পন্ন (২০২৬-১০-১০, Approval Center-এর সাথে)

Brickland-এর স্ক্রিনে চারটা ট্যাব: Records · Approval Actions · Action Assignment · New action type। ধাপের তথ্য ডেমোতে AT-001 খুলে নেওয়া (পড়ার জন্য ক্লিক, কিছু সেভ করা হয়নি)।

- **Action type = `approval_workflows`**। নতুন কলাম:
  - type_code (AT-001, কোম্পানিতে unique, খালি থাকলে পরের নম্বর), short_name, document_type (লেখা, যেমন "Yarn Return")।
  - workflow_type (APPROVED_BY_END_USER / PURCHASE_REQUISITION), page_url।
  - বিদ্যমান entity_type (কোন document নিজে থেকে এই chain নেয়) রাখা হয়েছে, সাথে `MANUAL_REQUEST` ("General request", শুধু request type-এর chain হিসেবে)।
- **Action (ধাপ) = `approval_rules`**। নতুন কলাম:
  - task_message, role_flag, next_action।
  - level: ০ = কাজের ধাপ (Raise, Issue, Receive), যা অনুমোদন চায় না আর chain-এ গোনা হয় না। ≥১ = অনুমোদনের লেভেল।
  - requires_signature (Sign?), can_repeat (Repeat?), can_cancel (Cancel?), can_resend (Resend?), active।
  - min_amount, max_amount: band, যা condition_json-এর cost শর্তে লেখা হয়।
  - বিদ্যমান সারির ডিফল্ট level = ১, active = true, তাই আগের workflow আগের মতোই চলে।
- **Action Assignment = নতুন `approval_rule_users`** (rule_id, user_id)।
  - ধাপে কেউ বসানো থাকলে শুধু তারাই সেই ধাপে কাজ করতে পারে। না থাকলে role, user বা team দিয়ে আগের মতো।
  - Brickland-এর সতর্কবার্তা "role আছে কিন্তু কেউ নেই, ধাপ আটকে যাবে" grid-এ Assigned = ০ দেখিয়ে জানানো হয়।
- **Records grid:** Type Code, Action Type Name, Short Name, Workflow Type, Page URL, Actions (ধাপের সংখ্যা), Status।
- **Approval Actions:**
  - বাঁয়ে master list (সার্চসহ), ডানে বেছে নেওয়া type-এর ধাপের সম্পাদনযোগ্য grid।
  - কলাম: Order, Action Name, Task Message, Role, Role Flag, Next Action, Assigned, Level, Min/Max amount, Sign?, Repeat?, Cancel?, Resend?, Active?।
  - Add action, Delete, Revert, Save (পুরো তালিকা একসাথে)।
- **API:**
  - `/api/v1/approval-center/workflows` (GET/POST/PATCH)।
  - `/workflows/{id}/steps` (GET/PUT, পুরো তালিকা)।
  - `/steps/{id}/users` (GET/PUT)।
  - পারমিশন `approval.type.manage`।
- **As-built:**
  - Backend: `app/Modules/Approval/Http/Controllers/Api/WorkflowDesignerApiController.php`, `Models/ApprovalRuleUser.php`, migration `2026_10_25_000100` (আগের chain-গুলো নিজে থেকে AT-001… কোড পায়)।
  - Frontend: `frontend/src/app/(app)/access-control/[tab]/designer-screen.js`, `designer-actions.js`, `components/approval/workflow-steps.jsx`, `step-assignment.jsx`।
  - Test: `tests/Feature/Approval/ApprovalCenterTest.php` (chain-এর গঠন রক্ষা, অন্য কোম্পানির ইউজার নিষেধ, band)।
- **নিরাপত্তা:**
  - role কেবল এই কোম্পানির নিজের বা প্ল্যাটফর্মের।
  - ধাপে কেবল এই কোম্পানির সক্রিয় সদস্য বসানো যায়।
  - অপেক্ষমাণ request থাকলে chain-এর গঠন বদলানো যায় না, শুধু নাম আর বার্তা বদলানো যায়।
- **পুরনো** `/settings/approval-workflows` পাতা সাইডবার থেকে লুকানো হয়েছে, এই ডিজাইনার তার জায়গা নিয়েছে।
