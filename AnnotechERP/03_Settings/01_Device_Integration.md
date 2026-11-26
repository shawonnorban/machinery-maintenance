# Device Integration

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন; ADMS Push পুরো, TCP Pull-এ শুধু রেজিস্টার আর Test Connection)
> **মডিউল:** [Settings](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `03/01`

**⬅ ফিরে যান:** [Settings](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Attendance Devices | `/m/settings/attendance-devices` | 2 | 16 | 0 |
| 2 | Device Punch Log | `/m/settings/device-punch-log` | 1 | 5 | 0 |
| 3 | Device Enrolled Users | `/m/settings/device-users` | 1 | 12 | 0 |
| 4 | Device Command Queue | `/m/settings/device-commands` | 2 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Attendance Devices

`/m/settings/attendance-devices`

**ট্যাব: Device Register 3**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Attendance terminals with protocol, address, connection state and last pu | search |  | Search 3 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Device, Protocol, IP : Port / Serial, Location, Last Contact, Last Punch, Users / FP / Logs, Post to HR, Last Result, Status, ACTION
- বাটন/অ্যাকশন: Device Register 3, Register Device, Export, Code, Device, Protocol, IP : Port / Serial, Location, Last Contact, Last Punch, Users / FP / Logs, Post to HR, Last Result, Status, Test Connection, Re-upload All Logs, Refresh Device Info, Post Pending to HR, Disable, Reboot

**ট্যাব: Register Device**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Device Code* | text | ✔ | GATE-01 |
| Device Name* | text | ✔ | Main Gate Fingerprint |
| Vendor | select |  | Select…, Zkteco, Other |
| Model | text |  | F22 / K40 / MB20 / SpeedFace |
| ADMS Push (device → server) | radio |  |  |
| TCP Pull (server → device, port 4370) | radio |  |  |
| Serial Number | text |  | e.g. CQUJ203960123 |
| IP Address | text |  | 192.168.1.201 |
| Port | number |  |  |
| Comm Key | number |  |  |
| Allowed Source IPs | text |  | 103.12.4.20, 192.168.1.201 |
| Time Zone (UTC +) | number |  |  |
| Status | select |  | Select…, Active, Disabled, Pending approval |
| Location | text |  | Factory gate, Floor 2 … |
- সেকশন: New device
- বাটন/অ্যাকশন: Device Register 3, Register Device, Device, Attendance, Continue, Save Device, Add New, Back To List

#### Device Punch Log

`/m/settings/device-punch-log`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Device | select |  | All, ATTANDENCE-01 — Main Gate Fingerprint, MMS1 — Main gate, ZK-TEST123 — ZKTeco TEST123 |
| User ID / Emp Code | text |  | Exact ID or code |
| Employee Match | select |  | All, Matched to an employee, No employee |
- সেকশন: Punch Search
- বাটন/অ্যাকশন: Show, Post Pending to HR, Reset

#### Device Enrolled Users

`/m/settings/device-users`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Device | select |  | All, ATTANDENCE-01 — Main Gate Fingerprint, MMS1 — Main gate, ZK-TEST123 — ZKTeco TEST123 |
| Employee Match | select |  | All, Matched to an employee, No employee |
| Search Users enrolled on each terminal with card number and matched employee | search |  | Search 1 records… |
| Filter by Device | search |  |  |
| Filter by User ID | search |  |  |
| Filter by Name on Device | search |  |  |
| Filter by Card No | search |  |  |
| Filter by Role | search |  |  |
| Filter by Matched Employee | search |  |  |
| Filter by Reported | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Device, User ID, Name on Device, Card No, Role, Matched Employee, Reported, Status
- সেকশন: User Search
- বাটন/অ্যাকশন: Show, Reset, Export, Device, User ID, Name on Device, Card No, Role, Matched Employee, Reported, Status

#### Device Command Queue

`/m/settings/device-commands`

**ট্যাব: Command Queue 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Commands queued for push terminals with delivery state and result | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: #, Device, Command, Requested By, Queued, Delivered, Answered, Return, Status, ACTION
- বাটন/অ্যাকশন: Command Queue 2, Send Command, Export, #, Device, Command, Requested By, Queued, Delivered, Answered, Return, Status

**ট্যাব: Send Command**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Device* | select | ✔ | Select…, ATTANDENCE-01 — Main Gate Fingerprint, MMS1 — Main gate, ZK-TEST123 — ZKTeco TEST123 |
| Command* | select | ✔ | Select…, INFO — report firmware, users and log counts, CHECK — re-read server options and upload, REBOOT — restart the t |
- সেকশন: New command
- বাটন/অ্যাকশন: Command Queue 2, Send Command, Reset, Queue Command
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **HR-এর কর্মী (24/03):** `hr_employees` — কার্ড / প্রক্সি নং আর কর্মী কোড দিয়েই যন্ত্রের User ID (PIN) মেলানো হয়।
- **Settings-এর পাতা:** `/system-settings/[screen]`; স্ক্রিনগুলো নিজের ট্যাব নিজে আঁকে, পাতা রিলোড হয় না।
- **নতুন মডিউল `app/Modules/AttendanceDevice`:** যন্ত্রের নিজের কথা (`/iclock/*`) আর Settings ও Biometric-এর API এক জায়গায়। HR-এর Biometric স্ক্রিনও এই মডিউলের।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_12_000100_create_attendance_devices`:
- **`dev_devices`:** কোড (কোম্পানি ধরে অনন্য), নাম, নির্মাতা, মডেল, সংযোগ (ADMS_PUSH / TCP_PULL), সিরিয়াল নং (সব কোম্পানি মিলিয়ে অনন্য), IP, পোর্ট, Comm Key, কত মিনিটে টানা, অনুমোদিত উৎস IP, টাইম জোন, অবস্থা (ACTIVE / DISABLED / PENDING), অবস্থান, User ID মেলানোর নিয়ম (AUTO / PROXY_NO / EMPLOYEE_CODE), HR-এ পাঠানো আর দৈনিক হাজিরা হালনাগাদের সুইচ, মন্তব্য; আর যন্ত্র শেষবার কী জানাল — যোগাযোগের সময় ও IP, শেষ পাঞ্চ, ফার্মওয়্যার, ইউজার / আঙুল / লগের সংখ্যা, শেষ ফল।
- **`dev_punches`:** যন্ত্র যেমন পাঠিয়েছে তেমন পাঞ্চ — PIN, সময় (UTC-তে, যন্ত্রের টাইম জোন থেকে), ধরন (in/out/break/OT), যাচাই (আঙুল/কার্ড/পাসওয়ার্ড/মুখ), work code, উৎস, মেলানো কর্মী, HR-এ গেছে কিনা, কখন পাওয়া। (যন্ত্র, PIN, সময়) একবারই — একই পাঞ্চ আবার এলে বাদ।
- **`dev_users`:** প্রতিটা যন্ত্রের ইউজার — PIN, নাম, কার্ড, ভূমিকা, মেলানো কর্মী।
- **`dev_fingerprints`:** যন্ত্রের পাঠানো আঙুলের template — যন্ত্র, PIN, আঙুল নং, আকার, valid, template, কর্মী।
- **`dev_employee_maps`:** কোন যন্ত্রে কোন কর্মী কোন PIN-এ — PENDING / REGISTERED (24/03 Biometric)।
- **`dev_commands`:** পুশ যন্ত্রের কমান্ড কিউ — নম্বর (যন্ত্র নম্বর ধরে উত্তর দেয়), ধরন, কমান্ডের লেখা, অবস্থা (QUEUED / SENT / DONE / FAILED / CANCELLED), কখন পৌঁছাল আর উত্তর এল, ফেরত কোড, ফল।

## ৫. Workflow / Status ও Business Rules

- **যন্ত্রের কথা (ZKTeco ADMS Push), `/iclock/*`-এ** — যন্ত্র শুধু সার্ভারের ঠিকানা আর পোর্ট নেয়, পথটা তার ফার্মওয়্যারে বাঁধা; তাই এই রাউট সার্ভারের গোড়ায় (`/api/v1`-এর নিচে নয়), session বা CSRF ছাড়া; প্রতি IP-তে মিনিটে ৬০০টা পর্যন্ত। কিছু ফার্মওয়্যার `.aspx` যোগ করে — সেটাও চলে।
  - `GET cdata?SN=…&options=all` — handshake: সব পাঠাতে বলা হয় (Stamp=None), প্রতি দশ সেকেন্ডে কমান্ড চাইতে, যন্ত্রের নিজের টাইম জোনে।
  - `POST cdata?table=ATTLOG` — পাঞ্চ (PIN, সময়, ধরন, যাচাই, work code); উত্তর `OK: <কতটা নতুন>`।
  - `POST cdata?table=OPERLOG` (আর USERINFO, FINGERTMP, BIODATA, `querydata`) — ইউজার আর আঙুলের ছাপ; OPLOG লাইন বাদ।
  - `GET getrequest` — কিউর কমান্ড (`C:<নম্বর>:<কমান্ড>`, একবারে ২০টা পর্যন্ত, SENT হয়); `INFO=` থাকলে ফার্মওয়্যার আর সংখ্যা হালনাগাদ।
  - `POST devicecmd` — উত্তর (`ID=…&Return=…`): ০ বা বেশি DONE, ঋণাত্মক FAILED; INFO-র উত্তরের সংখ্যাও বসে।
- **কে শোনা হয়:** শুধু রেজিস্টার করা সিরিয়াল নম্বর (অচেনা হলে 404 — আমাদের সিস্টেমে অনেক কোম্পানি, অচেনা যন্ত্র কোন কোম্পানির জানা যায় না)। Disabled বা Pending approval হলে ডাটা ফেরত (403) — যন্ত্র পাঞ্চ রেখে দেয়, সক্রিয় হলে পাঠায়; তবু তার যোগাযোগ লেখা থাকে। Allowed Source IPs থাকলে অন্য ঠিকানা থেকে ডাটা ফেরত, আর Last Result-এ লেখা থাকে।
- **কর্মী মেলানো:** আগে Biometric-এর ম্যাপ (যন্ত্র + PIN); না থাকলে যন্ত্রের নিয়ম — Auto (আগে প্রক্সি / কার্ড নং, তারপর কর্মী কোড), শুধু প্রক্সি, বা শুধু কর্মী কোড। না মিললেও পাঞ্চ রাখা হয়; **Post Pending to HR** পরে আবার মেলায় (পাঞ্চ আসার পরে নিবন্ধিত কার্ড)। মেলা পাঞ্চ HR-এ গেছে ধরা হয় যন্ত্রে "Post punches to HR" চালু থাকলে।
- **Attendance Devices:** উপরে Devices, Online / synced, Awaiting approval, Punches today, Not yet in HR। তালিকায় অবস্থা (Online — ৩ মিনিটের মধ্যে যোগাযোগ; Offline; Disabled; Pending approval)। সারির কাজ (Brickland-এর মতো, অবস্থা অনুযায়ী): Edit, Approve (Pending), Test Connection, Re-upload All Logs (পুশ; নিশ্চিত করে — সব পাঞ্চ, ইউজার আর আঙুল আবার চাওয়া হয়), Refresh Device Info (পুশ), Post Pending to HR, Disable (নিশ্চিত করে) / Enable, Reboot (পুশ; নিশ্চিত করে), Delete (পাঞ্চ থাকলে মোছা যায় না — নিষ্ক্রিয় করুন)।
  - **Test Connection:** IP থাকলে সেই ঠিকানার পোর্টে TCP সংযোগ (৩ সেকেন্ড); না থাকলে পুশ যন্ত্র শেষ কবে, কোথা থেকে যোগাযোগ করেছে।
  - **Register Device:** দুই ট্যাব — Device (কোড, নাম, নির্মাতা, মডেল, সংযোগ, সিরিয়াল, IP, পোর্ট, Comm Key, Pull Every [শুধু পুল], Allowed Source IPs [শুধু পুশ], টাইম জোন, অবস্থা, অবস্থান) আর Attendance (User ID মেলানোর নিয়ম, HR-এ পাঠানো, দৈনিক হাজিরা, মন্তব্য)। পুশে সিরিয়াল, পুলে IP বাধ্যতামূলক।
  - নিচে লেখা থাকে যন্ত্রের Cloud Server-এ কোন ঠিকানা দিতে হবে (সার্ভারের `APP_URL` + `/iclock/`)।
- **Device Punch Log:** তারিখ (না দিলে আজ), যন্ত্র, User ID বা কর্মী কোড (হুবহু), মিলেছে কিনা; নতুনতম ৫০০টা; উপরে Punches, Matched, No employee, Posted to HR; প্রতি কলামে ছাঁকনি; Post Pending to HR (সব যন্ত্র, নিশ্চিত করে)।
- **Device Enrolled Users:** যন্ত্র আর মিল দিয়ে খোঁজা; Enrolled, Unmatched।
- **Device Command Queue:** তালিকা আর Send Command (শুধু পুশ যন্ত্র; INFO, CHECK, REBOOT)। কিউতে থাকা কমান্ড বাতিল করা যায়।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `settings.device.view` আর `settings.device.manage`। FACTORY_ADMIN দুটোই; FACTORY_MANAGER আর AUDITOR শুধু view।
- **যন্ত্রের রাউট** (অনুমতি ছাড়া, সিরিয়াল নম্বর দিয়ে): `/iclock/cdata`, `querydata`, `getrequest`, `devicecmd`, `ping` (আর `.aspx`)।
- **API:** `GET/POST /settings/devices`, `GET/PUT/DELETE /settings/devices/{code}`, `POST /settings/devices/{code}/action`, `GET /settings/devices/options`, `GET /settings/device-punches`, `POST /settings/device-punches/post-pending`, `GET /settings/device-users`, `GET/POST /settings/device-commands`, `POST /settings/device-commands/{id}/cancel`।
- **মেনু:** Settings-এ নতুন ফোল্ডার DEVICE INTEGRATION (Dye House-এর মতো স্টাইলে), ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/settings/devices.jsx` — `DeviceRegister`, `PunchLog`, `EnrolledUsers`, `CommandQueue`, আর তাদের ভাগ করা গ্রিড (খোঁজা, Export, কলামের ছাঁকনি, ১০/২৫/৫০/১০০)। সারির কাজ একটা Actions মেনুতে।
- **ঠিকানা:** `/system-settings/attendance-devices`, `/device-punch-log`, `/device-users`, `/device-commands`।
- "৭ সেকেন্ড আগে" ধরনের সময় শুধু ব্রাউজারে আঁকা হয়, আর তারিখ সবসময় ঢাকার সময়ে — সার্ভার আর ব্রাউজারের লেখা মেলে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/AttendanceDevice`, নতুন মডিউল)**
- `Database/Migrations/2027_03_12_000100_create_attendance_devices.php`
- `Support/Terminals.php` (মেলানো, HR-এ পাঠানো, কমান্ডের লেখা, অনলাইন)
- `Http/Controllers/AdmsController.php` (`/iclock/*`), `Providers/AttendanceDeviceServiceProvider.php`, `Routes/device.php`
- `Http/Controllers/Api/DeviceApiController.php`, `Routes/api.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/settings.php`, `lang/bn/settings.php` (`dev`, `hub.screens`)

**Frontend**
- `frontend/src/components/settings/devices.jsx` (নতুন)
- `app/(app)/system-settings/[screen]/device-screens.js`, `device-actions.js` (নতুন); `screens.js`, `page.js` (বদলানো)

**Test**
- `tests/Feature/AttendanceDevice/DeviceIntegrationTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`DeviceIntegrationTest.php`, ২টা টেস্ট, ৮৪টা assertion):** test2 DB-তে পাস। পুরো HrPayroll স্যুট (১২টা টেস্ট, ৪১৬টা assertion) আর MenuAccess, RoleMatrix, ApiGateCoverage, AccessControlConfiguration (২৭টা টেস্ট, ১৮৪টা assertion) সবুজ।
- রেজিস্টার: পুশে সিরিয়াল আর পুলে IP বাধ্যতামূলক; একই সিরিয়াল দুবার আটকায়।
- `/iclock`: অচেনা সিরিয়াল 404; handshake-এ `GET OPTION FROM` আর TimeZone=6। তিনটা পাঞ্চ (কার্ড দিয়ে, কর্মী কোড দিয়ে, অচেনা) → `OK: 3`; আবার পাঠালে `OK: 0`; ০৮:০৫:১২ (UTC+6) জমা হয় ০২:০৫:১২ UTC-তে। ইউজার আর আঙুলের ছাপ → `OK: 3`, OPLOG বাদ।
- কমান্ড: INFO কিউ → `getrequest`-এ `C:<id>:INFO` → `devicecmd`-এর উত্তরে DONE, ইউজার / আঙুল / লগের সংখ্যা আর ফার্মওয়্যার বসে।
- নিষ্ক্রিয় যন্ত্রের পাঞ্চ 403; Allowed Source IPs-এর বাইরে 403; তালিকায় 127.0.0.1 দিলে নেয়।
- রেজিস্টারের সংখ্যা, Re-upload (৩টা কমান্ড), Punch Log (আজ, PIN বা কোড, মিল), কার্ড পরে বসালে Post Pending to HR মেলায়, Enrolled Users, পাঞ্চ থাকা যন্ত্র মোছা আটকায়; Factory Manager দেখেন কিন্তু কাজ করতে পারেন না, Storekeeper দেখতে পারেন না।

**আসল পথে যাচাই** (২০২৬-১১-১০; nginx `:8010`, `curl` দিয়ে নকল ZKTeco যন্ত্র, সিরিয়াল DEMO-SN-0001):
- অচেনা সিরিয়াল → 404 "Unknown device" ✔ handshake ✔ ৬টা পাঞ্চ → `OK: 6`, আবার → `OK: 0` ✔ ৪ জন ইউজার আর ২টা আঙুল → `OK: 6` ✔ কিউর দুটো কমান্ড `getrequest`-এ গেল, `devicecmd`-এ DONE ✔
- ৫টা পাঞ্চ dev কর্মীর সাথে মিলেছে (কার্ড 50001001, 50001002, 1053, 50001005), 7777 "No employee" ✔

**UI চেক (`app_check/plans/di01a`, `di01b`, `di02`; শেষে error 0; dev ডাটা রাখা আছে):** খালি Register-এ তিনটা বার্তা; GATE-01 রেজিস্টার; Actions মেনু; Punch Log-এ ৬টা পাঞ্চ (Check-in/out, Fingerprint/Card); Enrolled Users; Send Command (INFO) কিউতে।

## ১০. খোলা প্রশ্ন

১. **TCP Pull (পোর্ট 4370):** রেজিস্টার আর Test Connection আছে, কিন্তু যন্ত্র থেকে টেনে পাঞ্চ পড়া (Pull Logs Now, Full Re-read) এখনো হয়নি — ZKTeco-র বাইনারি প্রোটোকল আসল যন্ত্র ছাড়া পরীক্ষা করা যায় না, তাই ঐ দুটো বাটন দেখানো হয়নি। Brickland-এর ছয়টা যন্ত্রই ADMS Push। পুল যন্ত্র ব্যবহার হলে জানাবেন — একটা যন্ত্রে পরীক্ষা করে বানাব।

২. **অচেনা যন্ত্র:** Brickland অচেনা সিরিয়ালের যন্ত্রকে নিজে থেকে "Pending approval" হিসেবে তুলে নেয় বলে মনে হয়। এখানে অনেক কোম্পানি, অচেনা যন্ত্র কোন কোম্পানির বোঝা যায় না — তাই আগে রেজিস্টার করতে হয় (অবস্থা Pending approval রেখে পরে Approve করা যায়)।

৩. **দৈনিক হাজিরা:** ✅ মিটেছে — 24/04 Attendance & Shift তৈরি হয়েছে; "Update daily attendance in/out time" চালু থাকলে পাঞ্চ এলেই ঐ কর্মীর দিন হিসাব হয়।

৪. **Integration Hub-এর DEVICE চ্যানেল:** যন্ত্রের প্রতিটা যোগাযোগ Integration Activity-তে লেখা হয় না (খুব বেশি — প্রতি দশ সেকেন্ডে)। শুধু শেষ যোগাযোগ আর শেষ ফল যন্ত্রের সারিতে থাকে।

## ১১. As-built নোট

- **Brickland প্রোব:** চারটা স্ক্রিনের স্পেক RSC payload থেকে (`tools/erp_crawler/out/di01`): কলাম, ছাঁকনি, সারির কাজ আর কখন দেখায় (`showWhen`), নিশ্চিত করার লেখা, Register Device-এর দুই ট্যাব আর `visibleWhen`, Command Queue-র কমান্ড। Brickland-এর demo-তে ৬টা ADMS Push যন্ত্র চালু (প্রতিটা কয়েক সেকেন্ড পরপর যোগাযোগ করে)।
- **24/03-এর সাথে:** HR → Biometric / Fingerprint এই মডিউলের ডাটা ব্যবহার করে (যন্ত্রে কর্মী পাঠানো, আঙুলের ছাপ, ম্যাপ)।
- **UI চেকে ধরা পড়া:** "৭ সেকেন্ড আগে" আর সার্ভারের ঠিকানা সার্ভার আর ব্রাউজারে আলাদা আসছিল (hydration error) — এখন শুধু ব্রাউজারে আঁকা হয়, তারিখ ঢাকার সময়ে। নোটে যন্ত্রের জন্য ফ্রন্টএন্ডের ঠিকানা দেখাচ্ছিল — এখন সার্ভারের `APP_URL`।
