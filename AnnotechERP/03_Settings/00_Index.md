# Settings

> **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **ফোল্ডার:** `03_Settings`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `03/01` | [Device Integration](01_Device_Integration.md) | ৪ | ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন; ADMS Push পুরো, TCP Pull-এ শুধু রেজিস্টার আর Test Connection) |
| `03/02` | [Integration Hub](02_Integration_Hub.md) | ৫ | ✅ সম্পন্ন (৫টা স্ক্রিন; SMS, Bank, API Keys & Webhooks Brickland-এ Scaffold ছিল, এখানে পুরোপুরি বানানো) |
| `03/03` | [General Settings](03_General_Settings.md) | ৫ | ✅ সম্পন্ন (৫টা স্ক্রিন; Brickland-এ এগুলো Scaffold ছিল, এখানে পুরোপুরি বানানো) |
| `03/04` | [System & Maintenance](04_System_Maintenance.md) | ৫ | ✅ সম্পন্ন (৫টা স্ক্রিন; Brickland-এ Scaffold ছিল, এখানে পুরোপুরি বানানো) |
<!-- AUTO:status:end -->

## নোট

**সিদ্ধান্ত (ব্যবহারকারী, ২০২৬-১১-১৫): ডুপ্লিকেট হলে Brickland-এর স্ক্রিন থাকবে, পুরনোটা বাদ।**
Machinery-যুগে বানানো Settings-স্ক্রিন যেগুলোর কাজ পরে বানানো Brickland ERP-র স্ক্রিন করে, সেগুলো সরানো হয়েছে; পুরনো URL Brickland-স্ক্রিনে চলে যায় (`frontend/next.config.mjs`-এর `redirects()`, 307)।

| পুরনো (সরানো) | এখন যেখানে |
|---|---|
| Settings → Factories | Company Management → Factory |
| Settings → Company (সব সেটিং, লোগো) | Access Control → System Parameter (নতুন: "স্তর" — পুরো কোম্পানি বা একটা কারখানা) · লোগো: Company Management → Company Profile |
| Settings → Numbering | Settings → Document Numbering Series |
| Settings → Approval Workflows | Access Control → Approval Workflow Designer (একই টেবিল) |
| Settings → Users | Access Control → User Account Registration (নতুন: Production Line, Phone, Remove) |
| Settings → Roles | Access Control → Role Definition / Role Menu Assignment (৪২২টা permission-এর সবই মেনু-অনুমতি দিয়ে দেওয়া যায়) |
| Settings → Webhooks, API Clients | Settings → API Keys & Webhooks (নতুন: প্রতিটা key/webhook-এর ট্যাব — Edit, Rotate secret, Pause/Enable, Recent deliveries, Redeliver) |
| Master Data-র Organisation অংশ (Building … Designation) | Company Management-এর একই নামের ট্যাব (পুরনো `/settings/master-data/<type>` সেখানে যায়) |

OTHER SETTINGS-এ এখন থাকে: Master Data (Machinery-র মাস্টার, Stores & Items, Cost, Finance), Custom Fields, Locations, Calendar & Shifts, GL account mapping, Escalation rules। এগুলোর মধ্যে যেগুলোর Brickland-জোড়া আলাদা টেবিলে আছে, সেগুলো [খোলা প্রশ্ন](03_General_Settings.md)-এ।

**সিদ্ধান্ত (ব্যবহারকারী, ২০২৬-১০-১০):**
- Brickland-এর Settings-এর ১৯টা স্ক্রিনের মধ্যে ১৩টা ওদের নিজেদেরই "Scaffold" (তৈরি হয়নি)। তবু সবগুলো পুরোপুরি কার্যকর বানানো হবে, Brickland-এর লেআউটে।
- **ব্যতিক্রম:** Device Integration-এর ৪টা (ZKTeco ডিভাইস, পাঞ্চ লগ, এনরোল্ড ইউজার, কমান্ড কিউ) হবে HR & Payroll মডিউলের সাথে, কারণ পাঞ্চ যায় HR-এর কর্মী আর হাজিরায়।

**রুট আর মেনু:**
- রুট `/system-settings/<screen>`।
- সাইডবারে Brickland-এর চারটা ফোল্ডার: DEVICE INTEGRATION (HR-এর সাথে আসবে), INTEGRATION HUB, GENERAL SETTINGS, SYSTEM & MAINTENANCE।
- পুরনো Settings-পাতাগুলো (Master Data, Factories, Locations, Calendar, GL Mapping, Approval Workflows, Escalations, Custom Fields, Users, Roles …) নিজ নিজ Brickland-মডিউল তৈরি না হওয়া পর্যন্ত একটা আলাদা "OTHER SETTINGS" ফোল্ডারে থাকবে।

**পরিকল্পনা: তিন ধাপে ১৫টা স্ক্রিন।**

*ধাপ A: General Settings*
1. **General Preferences:** কোম্পানির সাধারণ পছন্দ, নতুন setting key হিসেবে। কর্ম-সপ্তাহ (কোন দিন খোলা), অর্থবছরের শুরুর মাস, লগইনের পরের প্রথম পাতা, ডিফল্ট ফ্যাক্টরি, তালিকার পাতা-আকার। Brickland-ধাঁচের ফর্ম আর Records গ্রিড।
2. **Date, Currency & Localization:**
   - setting: ডিফল্ট ভাষা, তারিখ আর সময়ের ফরম্যাট, টাইমজোন, মূল মুদ্রা, দশমিক-ঘর, সংখ্যার বিভাজক।
   - মুদ্রার মাস্টার আর বিনিময় হার (`currencies`: code, name, symbol, decimal places, rate to base, rate date, active)।
3. **Document Numbering Series:** বিদ্যমান numbering ইঞ্জিন (`/numbering`), Brickland-এর Records গ্রিড আর Edit-ডায়ালগে (format, reset policy, padding, next number)।
4. **Print & Letterhead Settings:** লেটারহেড (কোম্পানির নাম, ঠিকানা, যোগাযোগ, ফুটার), লোগো দেখানো, স্বাক্ষরের ঘর (Prepared/Checked/Approved), কাগজের মাপ আর মার্জিন; লাইভ প্রিভিউ। setting key হিসেবে, লোগো বিদ্যমান আপলোড দিয়ে।
5. **Barcode & Label Settings:** লেবেল টেমপ্লেট (`label_templates`: code, name, use (ITEM/ROLL/ASSET/BUNDLE/CARTON), symbology CODE128/QR/EAN13, width/height mm, columns, fields printed, printer language PDF/ZPL, active); প্রিভিউ।

*ধাপ B: Integration Hub*
6. **Integration Endpoints:** সিস্টেমের প্রতিটা সংযোগবিন্দুর তালিকা: API বেস URL, webhook, মেইল রিলে, SMS গেটওয়ে, ব্যাংক; Direction, Address, Port, কোথায় সেট হয়, Status। শুধু দেখা।
7. **Integration Activity Log:** `integration_logs` (channel WEBHOOK/SMS/MAIL/BANK/API, event, records, from/to, details, status, time); ফিল্টার আর গ্রিড। SMS, ব্যাংক, মেইল-টেস্ট আর webhook পাঠানো এখানে লেখে।
8. **SMS Gateway:** `sms_gateways` (provider, base URL, sender ID, API-key এনভায়রনমেন্ট ভ্যারিয়েবলের নাম; সিক্রেট DB-তে নয়, মেইলের মতো; default, active) আর "টেস্ট SMS পাঠান"।
9. **Bank & Payment Integration:** `bank_integrations` (bank, account, routing/SWIFT, channel API/SFTP/FILE, file format CSV/MT940/ISO20022, endpoint, credential env var, active) আর "সংযোগ পরীক্ষা"।
10. **API Keys & Webhooks:** বিদ্যমান API client আর webhook ইঞ্জিন, Brickland-এর ট্যাবে (API Keys | Webhooks | New)।

*ধাপ C: System & Maintenance*
11. **Notification Preferences:** কোন ইভেন্ট কোন চ্যানেলে (In-app/Email/SMS) যাবে, ইভেন্ট × চ্যানেলের ম্যাট্রিক্স; বিদ্যমান নোটিফিকেশন-পছন্দের ওপর।
12. **Scheduled Jobs:** নির্ধারিত কাজের তালিকা (cron, বিবরণ, পরের রান, শেষ রান আর ফল; `scheduled_job_runs` লগ)।
13. **Backup & Restore:** কোম্পানির ডাটার ব্যাকআপ (টেবিলভিত্তিক JSON-এর zip, কিউ-জব, ডাউনলোড) আর রিস্টোর (ব্যাকআপ যাচাই করে প্রিভিউ, তারপর অনুমোদিত হলে প্রয়োগ)। বিস্তারিত নিয়ম ধাপ C-র আগে এখানে লেখা হবে।
14. **System Health Monitor:** ডাটাবেস, ক্যাশ, কিউ (অপেক্ষমাণ আর ব্যর্থ জব), স্টোরেজ, শিডিউলারের শেষ সাড়া, মেইল/SMS কনফিগারেশন, অ্যাপ আর PHP ভার্সন; কার্ড আকারে।
15. **Data Retention & Purge:** ডাটার শ্রেণি অনুযায়ী রাখার মেয়াদ (অডিট লগ, নোটিফিকেশন, লগইন-চেষ্টা, ইন্টিগ্রেশন লগ, এক্সপোর্ট ফাইল …), কতগুলো মুছবে তার প্রিভিউ, আর একটা নির্ধারিত purge কমান্ড।
