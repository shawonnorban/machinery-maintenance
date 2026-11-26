# System & Maintenance

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টা স্ক্রিন; Brickland-এ Scaffold ছিল, এখানে পুরোপুরি বানানো)
> **মডিউল:** [Settings](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `03/04`

**⬅ ফিরে যান:** [Settings](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Notification Preferences | `/m/settings/notification-preferences` | 1 | 0 | 0 |
| 2 | Scheduled Jobs | `/m/settings/scheduled-jobs` | 1 | 0 | 0 |
| 3 | Backup & Restore | `/m/settings/backup-restore` | 1 | 0 | 0 |
| 4 | System Health Monitor | `/m/settings/system-health` | 1 | 0 | 0 |
| 5 | Data Retention & Purge | `/m/settings/data-retention` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Notification Preferences

`/m/settings/notification-preferences`

**ভিউ**
> 15 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Scheduled Jobs

`/m/settings/scheduled-jobs`

**ভিউ**
> 16 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Backup & Restore

`/m/settings/backup-restore`

**ভিউ**
> 17 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### System Health Monitor

`/m/settings/system-health`

**ভিউ**
> 18 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Data Retention & Purge

`/m/settings/data-retention`

**ভিউ**
> 19 of 19 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **নোটিফিকেশন:** `NotificationPreference` (প্রতি ইউজার, প্রতি ইভেন্ট: in_app/email/sms/whatsapp) আর `NotificationDispatcher::preferenceFor()`। এখন ক্রম হলো ইউজারের সারি, না থাকলে কোডে লেখা ডিফল্ট (`EMAIL_BY_DEFAULT`)। কোম্পানি-স্তরের ডিফল্ট নেই।
- **শিডিউলার:** `routes/console.php`-এ ১৬টা নির্ধারিত কমান্ড আছে, কিন্তু কোনো রান-লগ নেই। Laravel-এর `ScheduledTaskStarting/Finished/Failed` ইভেন্ট দিয়ে লগ লেখা যায়।
- **হেলথ:** `HealthController` (`/health`, `/health/ready`) অনুমোদন ছাড়া খোলা, ইচ্ছা করেই কিছু বলে না। স্ক্রিনের জন্য একটা আলাদা, অনুমোদিত endpoint লাগবে।
- **মুছে ফেলার কমান্ড:** `PruneReportFiles`, `PruneWebhookPayloads` আছে, দুটোই গ্লোবাল আর নির্দিষ্ট মেয়াদের। কোম্পানি নিজে মেয়াদ ঠিক করতে পারে না।
- **কিউ:** Redis (dev-এ), `failed_jobs` টেবিল গ্লোবাল। এর payload-এ অন্য কোম্পানির ডাটা থাকতে পারে, তাই শুধু সংখ্যা দেখানো যাবে।
- **ইন্টিগ্রেশন:** `IntegrationLogger`, ডিফল্ট `mail_services`, ডিফল্ট `sms_gateways` (হেলথ কার্ডে কনফিগারেশন আছে কিনা দেখাতে)।

## ৪. ডাটা মডেল (Entity ও Field)

| টেবিল | স্কোপ | ফিল্ড |
|---|---|---|
| `notification_event_defaults` | কোম্পানি | event_type (unique প্রতি কোম্পানিতে), email, sms, whatsapp, locked |
| `scheduled_job_runs` | গ্লোবাল (company_id নেই) | command, expression, started_at, finished_at, duration_ms, status (RUNNING/SUCCEEDED/FAILED), exit_code |
| `company_backups` | কোম্পানি | kind (MANUAL/PRE_RESTORE), status (QUEUED/RUNNING/READY/FAILED/EXPIRED), file_path, size_bytes, checksum (sha256), tables_json (টেবিল→সারি), requested_by, started_at, finished_at, error |
| `company_restores` | কোম্পানি | file_path, original_name, status (INVALID/PREVIEWED/QUEUED/RUNNING/APPLIED/FAILED), manifest_json, preview_json (টেবিল→{in_file, new, existing}), pre_restore_backup_id, requested_by, applied_by, applied_at, error |
| `retention_policies` | কোম্পানি | category (unique প্রতি কোম্পানিতে), keep_days, active, last_purged_at, last_purged_count |

## ৫. Workflow / Status ও Business Rules

### ৫.১ Notification Preferences (কোম্পানির ডিফল্ট)
- ইভেন্ট × চ্যানেলের ম্যাট্রিক্স। শুধু কোম্পানির ইভেন্ট দেখানো হয়, `PLATFORM_*` বাদ।
- In-app সব সময় চালু, বদলানো যায় না (অডিট ট্রেইলের অংশ)।
- **চ্যানেল ঠিক করার ক্রম:**
  1. ইভেন্টটা **locked** হলে কোম্পানির সারিই চলবে, ইউজারের নিজের পছন্দ উপেক্ষা হবে। জরুরি ইভেন্টে, যেমন BREAKDOWN_CRITICAL, কেউ ইমেইল বন্ধ করতে পারবে না।
  2. না হলে ইউজারের নিজের সারি।
  3. তাও না থাকলে কোম্পানির সারি।
  4. শেষে কোডে লেখা ডিফল্ট।
- কোনো সারি না বদলালে কোডের ডিফল্টই দেখায়। "Reset" করলে কোম্পানির সারি মুছে যায়।
- পারমিশন `settings.company.manage`।

### ৫.২ Scheduled Jobs
- তালিকা সরাসরি Laravel scheduler থেকে আসে:
  - কমান্ড আর বিবরণ (lang থেকে)।
  - cron expression আর মানুষের ভাষায় ("প্রতি ৫ মিনিটে", "প্রতিদিন ০২:১৫")।
  - পরের রান (কোম্পানির টাইমজোনে দেখানো)।
  - শেষ রান, ফল, সময়কাল।
- রান-লগ লেখা হয় scheduler-এর ইভেন্ট থেকে। কমান্ডের output লগে **রাখা হয় না**, কারণ গ্লোবাল কমান্ডের output-এ অন্য কোম্পানির তথ্য থাকতে পারে। শুধু অবস্থা, exit code, সময়।
- কোম্পানির জন্য শুধু দেখা যায়। চালানো বা বন্ধ করা প্ল্যাটফর্মের কাজ, কারণ একটা কমান্ড সব কোম্পানির জন্য চলে।
- লগ ৯০ দিন রাখা হয় (`settings:prune-job-runs`, প্রতিদিন)।

### ৫.৩ Backup & Restore
**ব্যাকআপ:**
- "Backup now" চাপলে কিউ-জব শুরু হয়। কোম্পানির সব টেবিল ধরা হয়: যেসব টেবিলে `company_id` কলাম আছে, সেগুলো ডাটাবেস-স্কিমা থেকে খুঁজে নেওয়া হয়।
- প্রতিটা টেবিলের `company_id = এই কোম্পানি` সারিগুলো JSON Lines আকারে একটা zip-এ যায়। সাথে `manifest.json`: format version, company id ও code, তৈরির সময়, অ্যাপ ভার্সন, শেষ মাইগ্রেশন, আর প্রতি টেবিলের সারি-সংখ্যা ও sha256।
- **কখনো ব্যাকআপে যায় না:** `users`, `sessions`, `password_reset_tokens`, API token/client secret, `personal_access_tokens`, `jobs`/`failed_jobs`, `company_backups`/`company_restores` নিজেরা। গোপন তথ্য ফাইলে যাবে না।
- ফাইল private disk-এ থাকে (`backups/<company>/<id>.zip`)। ডাউনলোড শুধু অনুমোদিত API দিয়ে, স্ট্রিম করে। পাবলিক URL নেই।
- একসাথে একটাই ব্যাকআপ চলে। QUEUED বা RUNNING থাকলে নতুনটা নেওয়া হয় না।
- মেয়াদ ঠিক হয় Data Retention-এর BACKUPS শ্রেণি দিয়ে (ডিফল্ট ৩০ দিন)। মেয়াদ শেষে ফাইল মুছে যায়, আর সারিটা EXPIRED হয়ে থেকে যায় (কে কখন নিয়েছিল, তার হিসাব থাকে)।

**রিস্টোর** (দুই ধাপ, আলাদা পারমিশন `settings.backup.restore`):
1. **আপলোড ও যাচাই:**
   - zip খোলা যায় কিনা।
   - manifest-এর format version চেনা কিনা।
   - **company id এই কোম্পানিরই কিনা।** অন্য কোম্পানির ফাইল সব সময় বাতিল।
   - প্রতি টেবিলের sha256 মিলছে কিনা।
   - টেবিল আর কলাম বর্তমান স্কিমায় আছে কিনা।
   - প্রতিটা সারির `company_id` এই কোম্পানির কিনা।
   - কিছু না মিললে INVALID, কারণসহ।
2. **প্রিভিউ:** প্রতি টেবিলে ফাইলে কত সারি, কতগুলো নতুন (id এখন নেই), কতগুলো বিদ্যমান (id আছে, ফিরিয়ে আনা হবে)।
3. **প্রয়োগ:**
   - কোম্পানির code টাইপ করে নিশ্চিত করতে হয়।
   - কিউ-জব প্রথমে একটা **PRE_RESTORE ব্যাকআপ** নেয়, যাতে রিস্টোরও ফেরানো যায়।
   - তারপর একটা transaction-এ টেবিলগুলো foreign-key-র নির্ভরতা অনুযায়ী সাজিয়ে (আগে parent) **upsert by id** করে।
- **রিস্টোর কী করে আর কী করে না:**
  - ব্যাকআপের পরে মুছে ফেলা সারি ফেরত আসে।
  - বদলানো সারি ব্যাকআপের অবস্থায় ফেরে।
  - **ব্যাকআপের পরে তৈরি সারি থেকে যায়, মোছে না।** ("merge restore": ডাটা হারানোর ঝুঁকি নেই।)
- `audit_logs` ব্যাকআপে থাকে, কিন্তু রিস্টোর করা হয় না। অডিট ট্রেইল কেবল যোগ হয়, কখনো পেছনে ফেরে না।
- প্রতিটা ব্যাকআপ, ডাউনলোড, রিস্টোর আর প্রয়োগ অডিট লগে যায়।

### ৫.৪ System Health Monitor
- অনুমোদিত endpoint (`settings.company.manage`)। কার্ড আকারে, প্রতিটায় OK / WARNING / DOWN:
  - **Database:** সাড়া দেয় কিনা, latency।
  - **Cache:** লেখা আর পড়া।
  - **Queue:** driver, অপেক্ষমাণ জব, গত ২৪ ঘণ্টায় ব্যর্থ জবের সংখ্যা (শুধু সংখ্যা)।
  - **Storage:** ফাঁকা জায়গার %। ১০%-এর নিচে WARNING।
  - **Scheduler:** শেষ রান কখন। ১০ মিনিটের বেশি আগে হলে DOWN। এটাই সবচেয়ে জরুরি সংকেত (ADR-061)।
  - **Mail:** কোম্পানির ডিফল্ট মেইল সার্ভিস আছে কিনা।
  - **SMS:** ডিফল্ট গেটওয়ে আছে কিনা, আর তার env-variable সেট আছে কিনা (মান নয়)।
  - **Backup:** শেষ সফল ব্যাকআপ কবে। ৭ দিনের বেশি আগে হলে WARNING।
  - **App:** অ্যাপ, PHP, Laravel ভার্সন আর environment।
- host বা password-এর মতো কিছু দেখানো হয় না।

### ৫.৫ Data Retention & Purge
| শ্রেণি | কী মোছে | ডিফল্ট | সর্বনিম্ন |
|---|---|---|---|
| NOTIFICATIONS | পড়া হয়ে গেছে এমন নোটিফিকেশন (আর তার delivery) | ১৮০ দিন | ৩০ |
| INTEGRATION_LOGS | `integration_logs` | ৯০ | ৭ |
| WEBHOOK_DELIVERIES | শেষ হয়ে যাওয়া (DELIVERED/FAILED) ডেলিভারি | ৯০ | ৭ |
| EXPORT_FILES | এক্সপোর্ট জবের ফাইল (সারি EXPIRED হয়ে থাকে; রিপোর্ট ফাইল আগের মতো গ্লোবাল `reports:prune` মোছে) | ৩০ | ১ |
| BACKUPS | ব্যাকআপ ফাইল (সারি EXPIRED হয়ে থাকে) | ৩০ | ৭ |
| AUDIT_LOGS | অডিট লগ | ৩৬৫০ (১০ বছর) | ৩৬৫ |

- **Preview:** প্রতি শ্রেণিতে কাটঅফ তারিখ আর কতগুলো মুছবে।
- **Purge now:** নিশ্চিত করার ডায়ালগ, তারপর সাথে সাথে মোছা (একটা কোম্পানির পুরনো সারি, তাই কিউ লাগে না)।
- **নির্ধারিত কমান্ড** `settings:purge-retention`, প্রতিদিন ০৩:০০। শুধু active নীতি চলে, প্রতিটা কোম্পানি আলাদাভাবে। ফল `last_purged_at` আর `last_purged_count`-এ বসে, আর অডিট লগে যায়।
- `login_attempts` গ্লোবাল (company নেই), তাই এখানে নেই। প্ল্যাটফর্ম কনসোলের কাজ।
- কাউকে সর্বনিম্নের চেয়ে কম মেয়াদ দিতে দেওয়া হয় না (ভ্যালিডেশন)।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET/PUT | `/api/v1/settings/notification-defaults` · DELETE `/settings/notification-defaults/{event}` (ডিফল্টে ফেরা) | `settings.company.manage` |
| GET | `/api/v1/settings/scheduled-jobs` · `/settings/scheduled-jobs/runs?command=&status=` | `settings.company.manage` |
| GET/POST | `/api/v1/settings/backups` | `settings.company.manage` |
| GET | `/api/v1/settings/backups/{id}/download` | `settings.company.manage` |
| POST | `/api/v1/settings/restores` (zip আপলোড, সর্বোচ্চ ১০০ MB) · GET `/settings/restores` | `settings.backup.restore` |
| POST | `/api/v1/settings/restores/{id}/apply` (`confirm_code`) | `settings.backup.restore` |
| GET | `/api/v1/settings/system-health` | `settings.company.manage` |
| GET/PUT | `/api/v1/settings/retention` (নীতি + প্রিভিউ একসাথে) · POST `/settings/retention/purge` | `settings.company.manage` |

এই route-গুলো `/settings/{key}`-এর **আগে** রেজিস্টার করা। না হলে `PUT /settings/retention` একটা setting key হিসেবে ধরা পড়ত।

নতুন পারমিশন `settings.backup.restore` COMPANY_OWNER রোলে থাকবে, অন্য কোনো রোলে ডিফল্টভাবে না।

## ৭. Frontend পেজ (লাইট থিম)

রুট `/system-settings/<screen>`, সাইডবারে Settings → SYSTEM & MAINTENANCE।

| স্ক্রিন | ট্যাব |
|---|---|
| Notification Preferences (`notification-preferences`) | Preferences (ম্যাট্রিক্স: ইভেন্ট, In-app🔒, Email, SMS, WhatsApp, Locked) · Records |
| Scheduled Jobs (`scheduled-jobs`) | Records (জবের তালিকা) · Run History |
| Backup & Restore (`backup-restore`) | Records (ব্যাকআপ; Download) · Restore (আপলোড, প্রিভিউ, প্রয়োগ) · New Backup |
| System Health Monitor (`system-health`) | Overview (কার্ড) |
| Data Retention & Purge (`data-retention`) | Policies (ফর্ম) · Preview (গ্রিড, Purge now) |

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Settings/Database/Migrations/2026_10_24_000100_create_system_maintenance_tables.php`
- `app/Modules/Notification/Models/NotificationEventDefault.php`
- `app/Modules/Notification/Services/NotificationDispatcher.php`
- `app/Modules/Notification/Http/Controllers/Api/NotificationApiController.php`
- `app/Modules/Settings/Models/ScheduledJobRun.php`
- `app/Modules/Settings/Models/CompanyBackup.php`
- `app/Modules/Settings/Models/CompanyRestore.php`
- `app/Modules/Settings/Models/RetentionPolicy.php`
- `app/Modules/Settings/Services/ScheduleCatalog.php`
- `app/Modules/Settings/Services/TenantTables.php`
- `app/Modules/Settings/Services/CompanyBackupWriter.php`
- `app/Modules/Settings/Services/CompanyRestorer.php`
- `app/Modules/Settings/Services/SystemHealth.php`
- `app/Modules/Settings/Services/RetentionPurger.php`
- `app/Modules/Settings/Jobs/RunCompanyBackup.php`
- `app/Modules/Settings/Jobs/ApplyCompanyRestore.php`
- `app/Modules/Settings/Listeners/RecordScheduledJobRuns.php`
- `app/Modules/Settings/Console/PurgeRetention.php`
- `app/Modules/Settings/Console/PruneJobRuns.php`
- `app/Modules/Settings/Providers/SettingsServiceProvider.php`
- `app/Modules/Settings/Http/Controllers/Api/SystemMaintenanceApiController.php`
- `app/Modules/Settings/Routes/api.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `routes/console.php`

**Frontend**
- `frontend/src/app/(app)/system-settings/[screen]/system-screens.js`
- `frontend/src/app/(app)/system-settings/[screen]/screens.js`
- `frontend/src/app/(app)/system-settings/[screen]/actions.js`
- `frontend/src/app/api/settings-backups/[backupId]/download/route.js`
- `frontend/src/app/api/settings-restores/route.js`
- `frontend/src/components/settings/notification-matrix.jsx`
- `frontend/src/components/settings/restore-panel.jsx`
- `frontend/src/components/settings/health-cards.jsx`
- `frontend/src/components/erp/action-card.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/notifications/preferences-form.jsx`
- `lang/en/settings.php`
- `lang/bn/settings.php`
- `lang/en/notification.php`
- `lang/bn/notification.php`
- `lang/en/erp.php`
- `lang/bn/erp.php`

**Tests**
- `tests/Feature/Settings/SystemMaintenanceTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব তৈরি হয়ে গেছে, ওপরের As-built দেখুন।_

## ৯. টেস্ট ও যাচাই

- `SystemMaintenanceTest` ৮/৮:
  - Locked ইভেন্ট ইউজারের পছন্দের ওপর জেতে, unlocked হলে ইউজারের পছন্দ জেতে। ইউজার locked ইভেন্ট বদলাতে পারে না। প্ল্যাটফর্ম ইভেন্ট তালিকায় নেই।
  - রান-লগে output কলামই নেই। ব্যর্থ রান (exit code ≠ 0) FAILED হিসেবে লেখা হয়।
  - ব্যাকআপে শুধু নিজের কোম্পানির সারি থাকে, গোপন বা বিলিং টেবিল নেই। একসাথে দুটো ব্যাকআপ চলে না।
  - রিস্টোর বদলানো সারি ফেরায়, পরে তৈরি সারি রেখে দেয়, আর আগে PRE_RESTORE ব্যাকআপ নেয়। ভুল code দিলে প্রয়োগ হয় না।
  - হাতে বদলানো ব্যাকআপ আর অন্য কোম্পানির আসল ব্যাকআপ, দুটোই INVALID।
  - COMPANY_ADMIN রিস্টোর দেখতে পারে না (403), কিন্তু ব্যাকআপ পারে।
  - হেলথ উত্তরে DB host, password বা app key নেই।
  - Purge শুধু নিজের কোম্পানির পুরনো সারি মোছে। সর্বনিম্নের নিচে মেয়াদ দিলে 422।
- ব্রাউজার (dev):
  - পাঁচটা স্ক্রিনের স্ক্রিনশট দেখা হয়েছে।
  - Backup now → কিউ-জব → READY (২.২ MB, ১৭৭ টেবিল, ৩৭,৯২২ সারি)।
  - Next proxy দিয়ে ডাউনলোড, সেই ফাইল আপলোড → PREVIEWED।
  - ম্যাট্রিক্স সেভ → Records।
  - শিডিউলারের রান-লগ লেখা হচ্ছে।
  - কনসোলে error নেই।
  - dev-এ রিস্টোর **প্রয়োগ করা হয়নি** (dev ডাটা বদলে যেত), এটা টেস্টে যাচাই করা।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **ব্যাকআপে সই (HMAC):** manifest-এ প্রতিটা টেবিলের checksum থাকে, আর পুরো manifest `APP_KEY` দিয়ে সই করা।
  - শুধু এই সিস্টেমের তৈরি ফাইলই রিস্টোর হয়। হাতে বানানো ফাইল দিয়ে অন্য কোম্পানির id-তে সারি ঢোকানো যায় না।
  - সাথে আরেক স্তর: প্রতিটা id আগে অন্য কোম্পানির সারি কিনা যাচাই হয়, প্রিভিউতে আর প্রয়োগের transaction-এর ভেতরে দুবার।
  - **ফল:** `APP_KEY` বদলালে পুরনো ব্যাকআপ আর রিস্টোর হবে না। ডাউনলোড করে রাখা যাবে, কিন্তু ফেরানো যাবে না।
- **ব্যাকআপে কোন টেবিল যায়, তা স্কিমা থেকে ঠিক হয়:** যেসব টেবিলে `company_id` আছে। নতুন মডিউলের টেবিল নিজে থেকেই ঢুকে যায়। বাদ দেওয়ার তালিকা `TenantTables::NEVER`-এ।
  - রিস্টোরের ক্রম foreign key দেখে ঠিক হয়, parent আগে।
  - এক transaction-এ সব হয়, তাই কোনো সারিতে সংঘাত হলে (যেমন একই code-এর নতুন সারি) কিছুই বদলায় না, আর কারণ লেখা থাকে।
- **শিডিউলারের তালিকা লাইভ পড়া হয়:** web request থেকে console kernel bootstrap করে `schedule:run` যা দেখে, ঠিক সেটা।
  - "কখন চলে" কোম্পানির টাইমজোনে দেখানো হয়, পরের রানের সময় থেকে নিয়ে। schedule নিজে সার্ভারের সময়ে (UTC) লেখা।
- **রিস্টোর আপলোড Next route handler দিয়ে হয়** (`/api/settings-restores`, স্ট্রিম করে)। কারণ server action-এর সীমা ১ MB, আর আসল ব্যাকআপ তার চেয়ে বড়। সীমা ১০০ MB (PHP আর nginx-এর সাথে মিলিয়ে)।
- **নোটিফিকেশন:** ইউজারের নিজের পছন্দের পাতায় locked ইভেন্ট "কোম্পানি ঠিক করে দিয়েছে" দেখায়, বদলানো যায় না।
  - সেই পাতায় ইভেন্টের নাম আগে কখনো দেখা যেত না (lang key-এর বড়-ছোট হাতের অমিল)। এখন দেখা যায়, আর বাকি ১৩টা ইভেন্টের নামও যোগ করা হয়েছে।
- **কিট:** `ActionCard` (এক-বাটনের কাজ, ইচ্ছামতো নিশ্চিত-করার ডায়ালগসহ), আর `RecordsGrid`-এ নতুন `download` কলাম।
- **dev পরিবেশ:** queue আর scheduler কন্টেইনার পুরনো কোড নিয়ে চলছিল (৩৭ ঘণ্টা)। নতুন job আর listener-এর জন্য `docker compose restart queue scheduler` লেগেছে।
- **বাকি:**
  - SMS আর ইমেইলে আসল ডেলিভারি। `notification_deliveries`-এ PENDING সারি তৈরি হয়, কিন্তু পাঠানোর worker এখনো নেই। এটা পরে messaging-এর কাজে আসবে।
  - Health কার্ডে অ্যাপ ভার্সন খালি (`config('app.version')` সেট নেই)।
