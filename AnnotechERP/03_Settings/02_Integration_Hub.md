# Integration Hub

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টা স্ক্রিন; SMS, Bank, API Keys & Webhooks Brickland-এ Scaffold ছিল, এখানে পুরোপুরি বানানো)
> **মডিউল:** [Settings](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `03/02`

**⬅ ফিরে যান:** [Settings](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Integration Endpoints | `/m/settings/integration-endpoints` | 1 | 2 | 0 |
| 2 | Integration Activity Log | `/m/settings/integration-activity` | 1 | 14 | 0 |
| 3 | SMS Gateway | `/m/settings/sms-gateway` | 1 | 0 | 0 |
| 4 | Bank & Payment Integration | `/m/settings/bank-payment-integration` | 1 | 0 | 0 |
| 5 | API Keys & Webhooks | `/m/settings/api-keys-webhooks` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Integration Endpoints

`/m/settings/integration-endpoints`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Integration endpoints with direction, address, port and the device settin | search |  | Search 6 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Endpoint, Direction, Address, Port, Where It Is Set, Notes, Status
- বাটন/অ্যাকশন: Export, Endpoint, Direction, Address, Port, Where It Is Set, Notes, Status

#### Integration Activity Log

`/m/settings/integration-activity`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Device | select |  | All, ATTANDENCE-01 — Main Gate Fingerprint, MMS1 — Main gate, ZK-TEST123 — ZKTeco TEST123 |
| Event | select |  | All, Push Upload, Pull Sync, Connection Test, Command, Device Discovered, Rejected, Hr Post |
| Result | select |  | All, Succeeded, Failed / refused |
| From Date | date |  |  |
| To Date | date |  |  |
| Search Integration events with device, kind, result, record count and source add | search |  | Search 23 records… |
| Filter by Time | search |  |  |
| Filter by Device | search |  |  |
| Filter by Event | search |  |  |
| Filter by Records | search |  |  |
| Filter by From / To | search |  |  |
| Filter by Details | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Time, Device, Event, Records, From / To, Details, Status
- সেকশন: Activity Search
- বাটন/অ্যাকশন: Show, Reset, Export, Time, Device, Event, Records, From / To, Details, Status

#### SMS Gateway

`/m/settings/sms-gateway`

**ভিউ**
> 07 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Bank & Payment Integration

`/m/settings/bank-payment-integration`

**ভিউ**
> 08 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### API Keys & Webhooks

`/m/settings/api-keys-webhooks`

**ভিউ**
> 09 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `api_clients` + `IssueApiToken`: API Keys ট্যাব; পুরনো `/settings/api-clients` ফর্ম আর অ্যাকশনগুলোই আবার ব্যবহার করা হয়েছে।
- `webhook_endpoints`, `webhook_deliveries`, `WebhookDeliverer`: Webhooks ট্যাব, আর Activity Log-এ ডেলিভারির সারি।
- `WebhookUrlGuard::isCallable`: SSRF রক্ষা (শুধু https আর পাবলিক ঠিকানা)। এখন SMS আর ব্যাংকের ঠিকানাতেও এটা ব্যবহার হয়।
- `mail_services` (Access Control → Configuration): Endpoints তালিকায় মেইলের সারি।
- স্ক্রিন-কিট: `ResourceDefinition` / `/erp/{resource}`, `RecordsGrid`, `EntryForm`, `ScreenTabs`।

## ৪. ডাটা মডেল (Entity ও Field)

সব টেবিল ULID, `company_id` (BelongsToTenant), টাইমস্ট্যাম্প। মাইগ্রেশন: `2026_10_23_000200_create_integration_hub_tables`।

| টেবিল | ফিল্ড |
|---|---|
| `sms_gateways` | name, provider (BULKSMSBD / SSL_WIRELESS / TWILIO / GENERIC_HTTP), base_url, username, sender_id, **api_key_env** (env-variable-এর নাম, কী নিজে না), is_default, active, last_used_at |
| `bank_integrations` | bank_name, account_name, account_no, routing_no, swift_code, channel (API / SFTP / FILE), file_format (NONE / CSV / MT940 / ISO20022), endpoint, **credential_env**, active, last_tested_at, last_test_status |
| `integration_logs` | occurred_at, channel (SMS / BANK / MAIL / WEBHOOK / API / DEVICE), event, status (SUCCEEDED / FAILED), source_name, target, records, details |

## ৫. Workflow / Status ও Business Rules

- **গোপন তথ্য কখনো DB-তে থাকে না।** শুধু env-variable-এর নাম রাখা হয়, ফরম্যাট `^[A-Z][A-Z0-9_]*$`। কী-এর মান সার্ভারের environment থেকে পড়া হয়, লগেও লেখা হয় না (টেস্টে যাচাই করা)।
- **ডিফল্ট SMS গেটওয়ে একটাই।** নতুন একটা ডিফল্ট করলে আগেরটা আর ডিফল্ট থাকে না।
- **Send test SMS:**
  - নম্বর `^\+?[0-9]{8,15}$`, মেসেজ সর্বোচ্চ ৩০০ অক্ষর।
  - env-variable সেট না থাকলে কিছুই পাঠানো হয় না, লগে কারণ লেখা হয় (FAILED)।
  - ঠিকানা guard পার না হলে পাঠানো হয় না। redirect অনুসরণ করা হয় না।
  - সফল হলে `last_used_at` বসে।
- **Test connection (ব্যাংক):**
  - API: guard পার হলে GET।
  - SFTP: host:port guard পার হলে TCP সংযোগ।
  - FILE: ফাইল ফরম্যাট NONE হলে ব্যর্থ ("ফাইল ফরম্যাট বেছে নিন")।
  - প্রতিবার `last_tested_at` আর `last_test_status` বসে, আর লগ হয়।
- **ব্যাংকের ফিল্ড:**
  - SWIFT ৮ বা ১১ অক্ষর। ছোট হাতে লিখলেও চলে, সেভ হয় বড় হাতে।
  - channel API বা SFTP হলে endpoint বাধ্যতামূলক।
- **Activity Log:** নিজস্ব `integration_logs` আর webhook ডেলিভারি মিলিয়ে নতুনগুলো আগে, সর্বোচ্চ ৫০০ সারি। ফিল্টার: channel, status, from/to তারিখ।
- **Endpoints:** একটা ইনবাউন্ড সারি (REST API, `APP_URL/api/v1`) আর প্রতিটা webhook, মেইল, SMS ও ব্যাংকের আউটবাউন্ড সারি, সাথে "কোথায় সেট করা হয়" স্ক্রিনের নাম।

## ৬. API ও পারমিশন

সব endpoint-এ পারমিশন `settings.company.manage`। API Keys ও Webhooks-এ তাদের নিজস্ব পারমিশন আগের মতোই।

| Method | Path | কাজ |
|---|---|---|
| GET/POST/PATCH/DELETE | `/api/v1/erp/sms-gateways[/{id}]` | SMS গেটওয়ে (স্ক্রিন-কিট resource) |
| GET/POST/PATCH/DELETE | `/api/v1/erp/bank-integrations[/{id}]` | ব্যাংক ইন্টিগ্রেশন |
| POST | `/api/v1/settings/sms-gateways/{id}/test` | টেস্ট SMS → `{ok, message}` |
| POST | `/api/v1/settings/bank-integrations/{id}/test` | সংযোগ টেস্ট → `{ok, message}` |
| GET | `/api/v1/settings/integration/endpoints` | Endpoints তালিকা |
| GET | `/api/v1/settings/integration/activity?channel=&status=&from=&to=` | Activity Log |

## ৭. Frontend পেজ (লাইট থিম)

রুট `/system-settings/<screen>`। সাইডবারে Settings → INTEGRATION HUB।

| স্ক্রিন | ট্যাব |
|---|---|
| Integration Endpoints (`integration-endpoints`) | Records |
| Integration Activity Log (`integration-activity`) | Records, উপরে FilterBar (Channel, Status, From, To, Show, Reset; ফিল্টার url-এ থাকে) |
| SMS Gateway (`sms-gateway`) | Records (Edit/Delete) · Send test SMS · New SMS gateway |
| Bank & Payment Integration (`bank-integration`) | Records (Test connection / Edit / Delete) · New bank integration (৩ কলাম) |
| API Keys & Webhooks (`api-keys-webhooks`) | API Keys (revoke) · Webhooks (delete) · New API key · New webhook |

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Settings/Database/Migrations/2026_10_23_000200_create_integration_hub_tables.php`
- `app/Modules/Settings/Models/SmsGateway.php`
- `app/Modules/Settings/Models/BankIntegration.php`
- `app/Modules/Settings/Models/IntegrationLog.php`
- `app/Modules/Settings/Resources/SmsGatewayResource.php`
- `app/Modules/Settings/Resources/BankIntegrationResource.php`
- `app/Modules/Settings/Services/IntegrationLogger.php`
- `app/Modules/Settings/Services/SmsSender.php`
- `app/Modules/Settings/Services/BankConnectionTester.php`
- `app/Modules/Settings/Http/Controllers/Api/IntegrationHubApiController.php`
- `app/Modules/Settings/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/app/(app)/system-settings/[screen]/hub-screens.js`
- `frontend/src/app/(app)/system-settings/[screen]/screens.js`
- `frontend/src/app/(app)/system-settings/[screen]/actions.js`
- `frontend/src/components/erp/filter-bar.jsx`
- `lang/en/settings.php`
- `lang/bn/settings.php`

**Tests**
- `tests/Feature/Settings/IntegrationHubTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব তৈরি হয়ে গেছে, ওপরের As-built দেখুন।_

## ৯. টেস্ট ও যাচাই

- `IntegrationHubTest` ৪/৪:
  - টেস্ট SMS env থেকে কী পড়ে, আর কী কোথাও লেখা হয় না।
  - প্রাইভেট ঠিকানা (169.254.169.254, 127.0.0.1) কখনো কল হয় না।
  - ব্যাংক টেস্টের স্ট্যাম্প বসে আর লগ হয়, SWIFT আর endpoint-এর ভ্যালিডেশন কাজ করে।
  - Endpoints তালিকায় প্রতিটা বাইরের ঠিকানা আছে।
- ব্রাউজার:
  - পাঁচটা স্ক্রিনের স্ক্রিনশট দেখা হয়েছে।
  - ব্যাংক (FILE) সেভ → Test connection → Activity Log-এ `CONNECTION_TEST` Failed সারি এসেছে।
  - কনসোলে error বা warning নেই।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- Brickland-এ Endpoints আর Activity ছিল ডিভাইস-কেন্দ্রিক (Device ফিল্টার, Push Upload ইত্যাদি)। এখানে channel-ভিত্তিক করা হয়েছে, যাতে SMS, ব্যাংক, মেইল, webhook, API সব এক জায়গায় থাকে। `DEVICE` channel রাখা আছে; Device Integration (HR & Payroll-এর সাথে) সেখানে লগ লিখবে।
- পুরনো `/settings/api-clients` আর `/settings/webhooks` পাতা সাইডবার থেকে লুকানো; সেগুলোর ফর্ম আর অ্যাকশন এই স্ক্রিন থেকে আবার ব্যবহার হয়।
- **বাকি:**
  - SMS দিয়ে আসল নোটিফিকেশন পাঠানো (Notification Preferences-এ SMS channel, ধাপ C)।
  - ব্যাংকের আসল পেমেন্ট ফাইল তৈরি বা স্টেটমেন্ট ইমপোর্ট (Finance)।
- **পথে ঠিক করা:**
  - SWIFT regex ছোট হাতের অক্ষর বাতিল করছিল, কারণ normalisation-এর আগেই যাচাই হতো; এখন `/i`।
  - Activity status কলাম badge করা হয়েছে।

**AI Services (২০২৬-১১-১২):** Integration Hub-এ নতুন স্ক্রিন `/system-settings/ai-services`। এখানে ERP-র সব AI ফিচারের key আর সার্ভিস (Claude, OpenAI, Gemini) ঠিক হয়। কোড `app/Modules/AiCore`; বিস্তারিত [26/01 Intake → আসল AI দিয়ে পড়া](../26_AI_File_PO_Import/01_Intake.md)।
