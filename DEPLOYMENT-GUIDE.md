# প্রোডাকশন ডিপ্লয়মেন্ট গাইড — Shared Hosting (cPanel), Git-ভিত্তিক

**এই ফাইলে যা আছে**: বর্তমান আর্কিটেকচার — একটা Laravel 13 API (`app/`) আর
আলাদা একটা Next.js 16 ফ্রন্টএন্ড (`frontend/`), দুটোই একই Git রিপোজিটরিতে —
এটাকে cPanel shared hosting অ্যাকাউন্টে ডিপ্লয় করার সম্পূর্ণ ধাপ, cPanel-এর
Git Version Control ফিচার বা প্লেইন `git`/SSH ব্যবহার করে, MySQL ডাটাবেস সহ।

**যা ধরে নেওয়া হয়েছে** (এই ডিপ্লয়মেন্টের জন্য নিশ্চিত করা):

- হোস্টিং-এ cPanel-এ **"Setup Node.js App"** সুবিধা আছে (Cloudlinux/Phusion
  Passenger Node.js Selector)। এটা ছাড়া Next.js অংশ চালানোই সম্ভব না —
  এই অ্যাপের কোনো static-export বিকল্প নেই, প্রতিটা পেজ সার্ভার-সাইডে,
  কুকি-অথেনটিকেটেড রিকোয়েস্ট দিয়ে লাইভ ডেটা পড়ে।
- হোস্টিং-এ **MySQL/MariaDB** আছে, PostgreSQL না। `DB_CONNECTION=pgsql` এই
  প্রজেক্টের বর্তমান ডিফল্ট, কিন্তু MySQL সম্পূর্ণ সাপোর্টেড ও CI-টেস্টেড
  বিকল্প (`.github/workflows/tests.yml` প্রতিটা push-এ দুটো ইঞ্জিনেই পুরো
  টেস্ট স্যুট চালায়) — এটা কোনো downgrade না।
- PHP **8.4.1+** পাওয়া যাচ্ছে (কেন 8.4.1-ই floor, `composer.json`-এর
  `^8.3`-এর কথা না, তার বিস্তারিত `docs/11-Deployment.md` §2-তে আছে)।

এই ফাইল হলো এই ডিপ্লয়মেন্টের বাস্তব, ধাপে-ধাপে রানবুক — এর প্রতিটা স্টেপ
**সত্যিই একবার একটা আসল cPanel অ্যাকাউন্টে চালিয়ে দেখা হয়েছে**, আর যেসব
আসল সমস্যা হয়েছিল সেগুলোর সমাধানও এখানে যোগ করা হয়েছে — শুধু তাত্ত্বিক
ধাপ না। `docs/11-Deployment.md` হলো গভীর রেফারেন্স (backup, scaling, health
check, alerting, PostgreSQL migration রানবুক) — তার §13 Next.js split-এর
আগের সময়ের, পুরনো single-app Blade ডিপ্লয়মেন্ট বর্ণনা করে; ফ্রন্টএন্ড
জড়িত যেকোনো কিছুর জন্য এই ফাইলটাই প্রযোজ্য।

**Shared hosting-এর একটা বারবার-ফিরে-আসা সমস্যা, §1-এর আগে জেনে রাখা ভালো**:
cPanel অ্যাকাউন্ট একটা resource-সীমিত container-এ চলে (CloudLinux LVE),
যেখানে একসাথে কতগুলো process/thread চলতে পারবে তার একটা কম সীমা থাকে।
`git`, `composer`, আর `npm` — এই তিনটাই তাদের সবচেয়ে ভারী কাজের সময়
(packfile indexing, archive extraction, native module build) সাব-প্রসেস
চালায়, আর এই ধরনের হোস্টিং-এ সেই কলগুলো মাঝে মাঝে *"unable to create
thread"* বা *"Unable to launch a new process"* এই ধরনের এরর দিয়ে ব্যর্থ
হয় — কোনো কিছু ভুল হওয়ার জন্য না, বরং সাময়িকভাবে অ্যাকাউন্টের process
সীমায় পৌঁছে যাওয়ার জন্য। **প্রতিবারই সমাধান একই: সেই নির্দিষ্ট কমান্ডের
parallelism কমিয়ে দিন, অথবা সহজভাবে আবার চেষ্টা করুন।** এটা নিচে তিন
জায়গায় দেখা যাবে (§1.4, §1.6, §1.7) — এগুলো তিনটা আলাদা সমস্যা না।

---

## ০. এই হোস্টিং-এ আর্কিটেকচার

দুইটা সাবডোমেইন, একটা রিপোজিটরি, একটা MySQL ডাটাবেস:

```
https://api.yourdomain.com   →  Laravel API           (public/ ই document root, PHP)
https://app.yourdomain.com   →  Next.js frontend       (cPanel "Node.js App", frontend/)
```

ব্রাউজার **কখনোই সরাসরি Laravel API কল করে না** — প্রতিটা `apiFetch()` কল
হয় সার্ভার-সাইডে, Next.js-এর নিজস্ব Node প্রসেসের ভেতরে, একটা bearer
token ব্যবহার করে যেটা httpOnly কুকিতে থাকে যা ব্রাউজার পড়তে পারে না
(`frontend/src/lib/session.js`)। এটা হোস্টিং-এর জন্য গুরুত্বপূর্ণ কারণ:

- `api.yourdomain.com`-কে শুধু Next.js সার্ভার প্রসেস আর ছবি/ফাইল লোড
  করা ব্রাউজারদের (asset document, কোম্পানি লোগো, এক্সপোর্ট করা রিপোর্ট)
  কাছে পৌঁছানো গেলেই চলবে — ব্রাউজার থেকে cross-origin `fetch`/XHR-এর
  জন্য CORS কনফিগার করার **দরকার নেই**।
- ফ্রন্টএন্ডে কিছু ভুল হলেও API আলাদাভাবে সম্পূর্ণ টেস্টযোগ্য থাকে
  (`curl https://api.yourdomain.com/api/v1/health`)।

একটা রিপোজিটরি, তার ভেতরে দুইটা ডিপ্লয়মেন্ট টার্গেট — cPanel-এর Node.js
Selector একটা "Application Root" কে ক্লোন করা রিপোর সাবফোল্ডারে
(`frontend/`) পয়েন্ট করতে দেয়, তাই একটা `git pull`-ই দুটো অংশ একসাথে
আপডেট করে দেয়।

---

## ১. একবারের জন্য cPanel সেটআপ

### ১.১ PHP ভার্সন

```bash
php -v                        # শেলের ডিফল্ট — অনেক সময় ওয়েবের চেয়ে পুরনো হয়
ls -d /opt/cpanel/ea-php* /opt/alt/php*  2>/dev/null   # আসলে কী ইনস্টল আছে (path হোস্ট ভেদে ভিন্ন)
```

শেলের ডিফল্ট `php` যদি আগে থেকেই 8.4+ হয়, তাহলে নিচের সব কমান্ডে শুধু
`php` লিখলেই চলবে — আলাদা করে versioned path খুঁজতে হবে না। পুরনো হলে,
8.4 বাইনারিটা খুঁজুন (classic cPanel/EasyApache-এ
`/opt/cpanel/ea-php84/root/usr/bin/php`, CloudLinux-এর নিজস্ব ALT-PHP-এ
`/opt/alt/php84/usr/bin/php`) এবং এই গাইডের প্রতিটা কমান্ডে (এমনকি §7-এর
দুইটা cron entry-তেও) সেই পুরো path ব্যবহার করুন।

**ওয়েব** PHP ভার্সন আলাদাভাবে সেট করুন cPanel → **MultiPHP Manager**-এ,
`api.yourdomain.com` সাবডোমেইনের জন্য — এটা শেলকে অনুসরণ করে না।

### ১.২ MySQL ডাটাবেস

cPanel → **MySQL® Databases**:

1. একটা ডাটাবেস বানান (যেমন `cpaneluser_machinery`) — cPanel নিজে থেকে
   আপনার অ্যাকাউন্ট ইউজারনেম সামনে জুড়ে দেবে।
2. **MySQL Users → Add New User** — একটা ইউজারনেম আর শক্তিশালী পাসওয়ার্ড।
3. **Add User to Database** — দুটোই সিলেক্ট করে **ALL PRIVILEGES** টিক
   দিয়ে **Make Changes**।

তিনটা পুরো নাম (cPanel-prefixed) লিখে রাখুন — §৩-এ Laravel-এর `.env`-এ
লাগবে।

### ১.৩ সাবডোমেইন

cPanel → **Domains** → **Create A New Domain**, দুইবার:

| সাবডোমেইন | Document root |
|---|---|
| `api.yourdomain.com` | `<repo-ফোল্ডার>/public` |
| `app.yourdomain.com` | cPanel-এর ডিফল্টই থাকুক — §১.৬-এ Node.js App সেটআপই আসল সার্ভিং পাথ ঠিক করবে |

**Laravel-এর document root অবশ্যই `public/` হতে হবে, কখনোই রিপো রুট না**
— অন্য কিছু হলে `.env` ফাইল ইন্টারনেটে খোলা থাকবে। DNS প্রোপাগেট হওয়ার
সাথে সাথে যাচাই করুন:

```bash
curl -I https://api.yourdomain.com/.env      # 403 বা 404 হতে হবে, কখনো 200 না
```

সাবডোমেইন বানালে সাধারণত সাথে সাথেই তার হোম ফোল্ডার তৈরি হয়ে যায়, যার
ভেতরে থাকে `cgi-bin/`, একটা খালি `public/`, আর (SSL অটো-ভেরিফিকেশনের
জন্য) একটা `.well-known/` — **`.well-known/` মুছবেন না**, AutoSSL-এর
এটা দরকার। পরের স্টেপে এটাই গুরুত্বপূর্ণ: ফোল্ডারটা খালি না, তাই সরাসরি
`git clone <url> .` করলে ব্যর্থ হবে।

### ১.৪ রিপোজিটরি ক্লোন করুন

**যদি ক্লোন করার সময় `fatal: unable to create thread: Resource
temporarily unavailable` এরর আসে** ("Receiving objects" সম্পূর্ণ হওয়ার
পর, `fetch-pack: invalid index-pack output` সহ) — এটাই ভূমিকায় বলা LVE
process-limit সমস্যা। git-কে single-thread-এ packfile index করতে বাধ্য
করুন:

```bash
git -c pack.threads=1 clone <url> ...
```

এটা একটু ধীরে হবে; ব্যর্থ হবে না।

**ক্লোন করার দুইটা উপায়** — cPanel যেটা দেয় সেটাই ব্যবহার করুন।

**ক. cPanel → Git™ Version Control**

1. **Create**, রিপোজিটরির clone URL পেস্ট করুন, repository path সেট করুন
   `~/<সাবডোমেইনের ফোল্ডার>`।
2. রিপো প্রাইভেট হলে, cPanel যে deploy key দেখাবে সেটা Git হোস্টে
   (GitHub → repo → Settings → Deploy keys) **read-only deploy key**
   হিসেবে যোগ করুন — এখানে কখনো ব্যক্তিগত SSH key পুনর্ব্যবহার করবেন না।
3. এর নিজস্ব **"Pull or Deploy"** ট্যাবই প্রথমটার **পরের** প্রতিটা
   ডিপ্লয়মেন্টে (§৬) ব্যবহার হবে — এক ক্লিকে `git pull` চলে।

**খ. SSH টার্মিনাল, cPanel আগে থেকেই যে ফোল্ডার বানিয়ে রেখেছে তার ভেতরে (§১.৩)**

সরাসরি সাবডোমেইনের নিজের ফোল্ডারে ক্লোন করলে ব্যর্থ হয় ("destination
path '.' already exists and is not an empty directory") কারণ cPanel আগে
থেকেই সেখানে `cgi-bin`/`public`/`.well-known` রেখেছে। পাশে একটা
সাময়িক ফোল্ডারে ক্লোন করে তারপর merge করুন:

```bash
cd ~/<সাবডোমেইনের ফোল্ডার>
git -c pack.threads=1 clone <url> tmp-clone

ls -la public/                   # merge করার আগে নিশ্চিত হন cPanel-এর public/ খালি

shopt -s dotglob
mv tmp-clone/public/* public/    # Laravel-এর public/-এর কনটেন্ট cPanel-এর খালি public/-এ
rmdir tmp-clone/public
mv tmp-clone/* .                 # বাকি সব, dotfile সহ (.git, .env.example, …)
shopt -u dotglob

rmdir tmp-clone
```

এখন `app/`, `composer.json`, `frontend/`, `.git`, আর অক্ষত
`cgi-bin/`/`.well-known/` — সব একসাথে টপ লেভেলে থাকা উচিত।

### ১.৫ Node.js App (Next.js অংশ)

Next.js-এর নিজস্ব `next start` — cPanel-এর Node Selector সরাসরি এটাকে
পয়েন্ট করতে পারে না (এটা একটা single JS ফাইল `require` করতে চায়)। এই
রিপোতে ঠিক এই কারণেই `frontend/server.js` ফাইলটা আগে থেকেই আছে — নতুন
করে কিছু বানানোর দরকার নেই।

cPanel → **Setup Node.js App** → **Create Application**, সাবধানে পূরণ
করুন (এই ফর্মের ডিফল্ট মান এই অ্যাপের জন্য বেশ কয়েক জায়গায় ভুল):

| ফিল্ড | ফর্ম ডিফল্টে যা থাকে | যা বসাতে হবে |
|---|---|---|
| Node.js version | প্রায়ই অনেক পুরনো কিছু, যেমন 10.x | ড্রপডাউনে **সবচেয়ে নতুন** যেটা আছে (20 LTS/22 LTS/24 — Next.js 16-এর জন্য একটা আধুনিক Node লাগবে) |
| Application mode | Development | **Production** |
| Application root | খালি, বা একটা আন্দাজ করা নাম | `<সাবডোমেইনের ফোল্ডার>/frontend` — আপনার হোম ডিরেক্টরি থেকে *সম্পর্কিত* একটা path, যেমন `annotechrmg.example.com/frontend` |
| Application URL | **মূল** ডোমেইন | ড্রপডাউন থেকে **ঠিক সেই সাবডোমেইনটা**, যেমন `app.example.com` — এক ধাপ ওপরের বেয়ার রুট ডোমেইন না |
| Application startup file | খালি | `server.js` |

**Environment variables** এখানে খালি রাখুন — `frontend/.env.production`
(§৩) সেটা সামলাবে। **Create**-এর পর cPanel একটা **"Run NPM Install"**
বাটন দেখাবে; শুধু প্রথমবার না, `frontend/package.json` বদলায় এমন প্রতিটা
ডিপ্লয়মেন্টের পরে (§৬) এটা ব্যবহার করুন।

---

## ২. Laravel `.env`

সার্ভারে সরাসরি `~/<সাবডোমেইনের ফোল্ডার>/.env` বানান (এটা git-ignored —
কখনো commit হয় না, pull-এও আসে না)। দ্রুততম পথ: `cp .env.example .env`,
তারপর এই নির্দিষ্ট লাইনগুলো ঠিক করুন (ডাটাবেসের তিনটা লাইন বাদে বাকি সবের
জন্য `sed -i` নির্ভরযোগ্য, ডাটাবেসেরটা শুধু আপনিই জানেন):

```bash
sed -i '/^FRONTEND_URL=$/d' .env                                   # টেমপ্লেটে একটা বাড়তি খালি ডুপ্লিকেট আছে — সেটা বাদ
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i '/^DB_PORT=/d' .env                                         # MySQL-এ এটা আলাদা করে বসানোর দরকার নেই
sed -i 's/^DB_CHARSET=.*/DB_CHARSET=utf8mb4/' .env
sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=log/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env
sed -i 's/^CACHE_STORE=.*/CACHE_STORE=database/' .env
sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=sendmail/' .env
sed -i 's|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS="noreply@yourdomain.com"|' .env
```

> **`APP_ENV` সত্যিই বদলেছে কিনা যাচাই করুন।** যে রানের ভিত্তিতে এই গাইড
> লেখা, সেখানে ঠিক এই `sed` কমান্ডটাই `APP_ENV` লাইনে চুপচাপ কাজ করেনি,
> যদিও পাশের পাঁচটা গঠনগতভাবে হুবহু একই রকম `sed` কমান্ড সবগুলোই ঠিকমতো
> কাজ করেছে — সম্ভবত মাল্টি-লাইন ব্লক কপি-পেস্ট করার সময় একটা বাড়তি
> ক্যারেক্টার ঢুকে গিয়েছিল। সবসময় চেক করুন:
> ```bash
> grep "^APP_ENV" .env
> ```
> এখনো `local` দেখালে `nano .env`-এ হাতে ঠিক করুন।

তারপর টেমপ্লেটে যে লাইনের প্লেসহোল্ডার নেই সেটা যোগ করুন, আর §১.২-এর
তিনটা ডাটাবেস মান হাতে বসান (`nano .env`):

```dotenv
FRONTEND_URL=https://app.yourdomain.com

DB_DATABASE=<পুরো cPanel-prefixed ডাটাবেস নাম>
DB_USERNAME=<পুরো cPanel-prefixed ইউজারনেম>
DB_PASSWORD=<যে পাসওয়ার্ড সেট করেছেন>
```

---

## ৩. Next.js `.env.production`

`~/<সাবডোমেইনের ফোল্ডার>/frontend/.env.production` বানান (এটাও
git-ignored — `NEXT_PUBLIC_*` মানগুলো *build* সময়ে ব্রাউজার বান্ডেলে
বেক হয়ে যায়, তাই এই ফাইলটা `npm run build`-এর **আগে** সঠিকভাবে থাকতে
হবে, শুধু অ্যাপ চালানোর আগে না):

```dotenv
LARAVEL_API_URL=https://api.yourdomain.com/api/v1

# Shared hosting-এ Reverb বন্ধ (স্থায়ীভাবে চালানোর মতো সুপারভাইজড প্রসেস
# নেই — উপরের BROADCAST_CONNECTION=log-এর মতোই কারণ)। এগুলো খালি রাখলে
# sync indicator শুধু "Reconnecting" দেখাবে, বাকি সব কাজ করবে। পরে যদি
# API হোস্ট এমন কোথাও নেন যেখানে reverb:start স্থায়ীভাবে চালানো যায়,
# তখনই এগুলো ভরুন।
# NEXT_PUBLIC_REVERB_APP_KEY=
# NEXT_PUBLIC_REVERB_HOST=
# NEXT_PUBLIC_REVERB_PORT=443
# NEXT_PUBLIC_REVERB_SCHEME=https
```

---

## ৪. প্রথম ডিপ্লয়মেন্ট — Laravel

```bash
cd ~/<সাবডোমেইনের ফোল্ডার>

php artisan key:generate
php artisan storage:link
```

**Composer install, এবং মাঝপথে ব্যর্থ হলে যা করবেন:**

```bash
composer install --no-dev --optimize-autoloader
```

Resource-সীমিত হোস্টে এটা সব ডাউনলোড করার পর ব্যর্থ হতে পারে, বেশিরভাগ
প্যাকেজের জন্য `Install of <package> failed` দেখিয়ে, শেষে `In
Process.php line 356: Unable to launch a new process.` — এটা §১.৪-এর
git-এর মতোই একই LVE process-limit সমস্যা, এবার Composer-এর archive
extraction থেকে। **ঠিক একই কমান্ডটাই আবার চালান।** Composer-এর install
resumable — যা আগে সফল হয়েছে সেটা অক্ষত থাকে, আর যে রানের ভিত্তিতে এই
গাইড লেখা, সেখানে দ্বিতীয়বার প্লেইন রিট্রাইতে কোনো ব্যর্থতা ছাড়াই শেষ
হয়েছিল। আগে চেক করে নিন PHP-এর নিজস্ব zip সাপোর্ট আছে কিনা (এটা থাকলে
এই ব্যর্থতা কম হয়):

```bash
php -m | grep -i zip
```

**Migrate ও seed করুন:**

```bash
php artisan migrate --force
```

এটা যদি `2026_09_03_083842_migrate_legacy_rbac_to_spatie`-তে
`SQLSTATE[42000]: ... Key column 'role_id' doesn't exist in table` দিয়ে
ব্যর্থ হয় — এটা একটা আসল MySQL/MariaDB-ভার্সন-নির্দিষ্ট বাগ ছিল এই
মাইগ্রেশনে (একটা composite unique index থাকা অবস্থায় কলাম ড্রপ করার
চেষ্টা), যেটা commit `d324516`-এ ঠিক করা হয়েছে। `git pull` করে ফিক্সটা
নিন, তারপর — যেহেতু MySQL DDL কোনো ট্রানজ্যাকশনের ভেতরে চালায় না, তাই
ব্যর্থ চেষ্টাটা কিছু আংশিক ডেটা রেখে গেছে (`permissions`/`roles`-এ কিছু
সারি, `user_roles`-এ একটা বাড়তি কলাম) — একদম নতুন ডাটাবেসে যেখানে এখনো
আসল কিছু নেই, হাতে-হাতে ঠিক করার চেয়ে নতুন করে শুরু করাই সহজ:

```bash
php artisan migrate:fresh --force
```

এটা যদি `2026_01_01_000500_create_asset_tables`-এ (বা এর পরের যেকোনো
মাইগ্রেশনে) `SQLSTATE[42000]: ... 1067 Invalid default value for
'<column>'` দিয়ে ব্যর্থ হয় — এটাও একটা আসল MySQL/MariaDB-নির্দিষ্ট বাগ,
উপরেরটার থেকে আলাদা। যেসব হোস্টে সিস্টেম ভ্যারিয়েবল
`explicit_defaults_for_timestamp` বন্ধ থাকে (অনেক shared hosting-এ এটাই
ডিফল্ট), সেখানে একটা টেবিলে পরপর দুইটা "বেয়ার" `NOT NULL timestamp`
কলাম (কোনো `->nullable()`, `->useCurrent()`, বা `->default()` ছাড়া)
থাকলে দ্বিতীয়টা চুপচাপ একটা অকার্যকর `'0000-00-00 00:00:00'` ডিফল্ট
পায়, যেটা আধুনিক MySQL-এর `NO_ZERO_DATE` strict mode `CREATE TABLE`-এর
সময়েই প্রত্যাখ্যান করে। commit `3d51967`-এ পুরো মাইগ্রেশন ট্রি খুঁজে
এই প্যাটার্নের প্রতিটা কলামে `->useCurrent()` যোগ করে ঠিক করা হয়েছে
(অ্যাপ্লিকেশন কোড এমনিতেই ইনসার্টের সময় আসল ভ্যালু দেয়, তাই এই ডিফল্ট
কখনো আসলে ব্যবহার হয় না — শুধু স্কিমাটা প্রতিটা MySQL বিল্ডে বৈধ রাখার
জন্য)। `git pull` করে নিশ্চিত করুন কমিটটা আছে, তারপর আবার
`migrate:fresh --force` চালান।

তারপর seed করুন:

```bash
php artisan db:seed --force
```

এটা শুধু **প্ল্যাটফর্ম** সিড — roles, permissions, asset/failure
taxonomy, আর একটা fixed platform administrator অ্যাকাউন্ট
(`admin@noirban.com`, `PlatformAdminSeeder` দিয়ে idempotent-ভাবে সিড
করা — এই অ্যাকাউন্ট এই পাসওয়ার্ড দিয়ে এই ডিপ্লয়মেন্টে না থাকা উচিত হলে
সেই ক্লাসটা দেখুন)। এটা ইচ্ছাকৃতভাবে কোনো ডেমো কোম্পানি ডেটা বানায় না।

**ঐচ্ছিক: ডেমো ডাটা**, আসল কাস্টমার আসার আগে প্রোডাক্ট দেখানোর জন্য —
একটা কোম্পানি, একটা ফ্যাক্টরি, মেশিন/ওয়ার্ক অর্ডার/ব্রেকডাউন/স্টক
আগে থেকেই ভরা:

```bash
php artisan db:seed --class="Database\Seeders\DemoCustomerSeeder" --force
```

পাবলিক URL-এ এটা চালানোর আগে পড়ে নিন: এটা **প্রতিটা রোলের জন্য একটা করে
(মোট ১২টা) ইউজার অ্যাকাউন্ট** বানায় যাদের **সবার একই well-known
পাসওয়ার্ড** — সিডারের নিজের
ডকব্লকেই এটা সরাসরি লেখা আছে। ওয়াকথ্রুর জন্য ঠিক আছে, আসল কাস্টমার
অ্যাকাউন্টের পাশে স্থায়ীভাবে রাখার মতো কিছু না। কাজ শেষ হলে এটা মুছে
ফেলুন (Platform কনসোল থেকে, বা আসল কাউকে অনবোর্ড করার আগে আবার
`migrate:fresh` করে)।

(`DemoCustomerSeeder`-এর বদলে সরাসরি এর প্যারেন্ট ক্লাস
`--class=DemoTenantSeeder` চালালে দুইটা কোম্পানি/ফ্যাক্টরি সহ পুরো ডেমো
ডেটা বসে — বৈধ, শুধু বড়।)

এই সিডার (দুটো ভ্যারিয়েন্টই) যদি "Demo: N machines..." লাইনের পরে
`SubscriptionContract::limitFor(): Return value must be of type ?int,
string returned` দিয়ে ব্যর্থ হয় — এটা একটা আসল pre-existing বাগ ছিল
`SubscriptionContract`-এ, seeder-এর নিজের সমস্যা না: এন্টাইটেলমেন্ট
লিমিট কলামে (`included_factories`/`included_assets`/`included_users`)
মডেলের `casts()`-এ `'integer'` cast ছিল না, তাই fresh DB fetch-এ PDO
সেগুলো string হিসেবে ফেরত দিত আর `declare(strict_types=1)`-এর
`?int` রিটার্ন-টাইপ চেক ভেঙে যেত। commit `3843b42`-এ ঠিক করা হয়েছে।
`git pull` করে সিডারটা আবার চালান — স্কিমা বদলায়নি, তাই `migrate` লাগবে
না।

**Laravel অংশ শেষ করুন:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

যাচাই করুন: `curl -s https://api.yourdomain.com/api/v1/health` এমন
কিছু দেখানো উচিত — `{"success":true,"data":{"status":"ok"},...}`।

---

## ৫. প্রথম ডিপ্লয়মেন্ট — Next.js

```bash
cd ~/<সাবডোমেইনের ফোল্ডার>/frontend
```

`.env.production` বানান (§৩) পরের স্টেপের **আগে** — এটা build সময়ে বেক
হয়, চালু হওয়ার সময় পড়া হয় না।

শেলের নিজস্ব `npm` না, cPanel → **Setup Node.js App** → আপনার অ্যাপ →
**Run NPM Install** ব্যবহার করুন — এটা §১.৫-এ বেছে নেওয়া Node ভার্সনের
সাথে মেলা virtual environment-এর ভেতরে চলে, যেটা শেলের বেয়ার `npm`
সাধারণত মেলে না। এটা কিছুক্ষণ সময় নিতে পারে; কয়েক মিনিট ধরে কোনো CPU
নড়াচড়া ছাড়াই আটকে থাকলে মনে হয়, সেটা সম্ভবত ওপরের একই LVE
process-limit সমস্যা — অনন্তকাল অপেক্ষা না করে বাতিল করে আবার চেষ্টা
করুন।

তারপর build করুন, আর অ্যাপ রিস্টার্ট করুন যাতে নতুন build ধরে:

```bash
npm run build
```

cPanel → **Setup Node.js App** → আপনার অ্যাপ → **Restart**।

যাচাই করুন: `curl -s -o /dev/null -w "%{http_code}\n" https://app.yourdomain.com/login` এর ফলাফল `200` হওয়া উচিত।

---

## ৬. প্রথমটার পরের প্রতিটা ডিপ্লয়মেন্ট

**cPanel-এর Git Version Control দিয়ে** (§১.৪-এ পদ্ধতি ক ব্যবহার করলে):
**Pull or Deploy** → **Update from Remote**-এ ক্লিক করুন, তারপর নিচের
বাকিটা SSH টার্মিনালে চালিয়ে যান (cPanel-এর Git UI শুধু কোড pull করে —
Composer, migration, বা frontend build চালায় না)।

**SSH দিয়ে**:

```bash
cd ~/<সাবডোমেইনের ফোল্ডার>
php artisan down --retry=60

git pull                                  # ওপরে cPanel-এর Git UI দিয়ে ডিপ্লয় করলে এই লাইন বাদ

composer install --no-dev --optimize-autoloader   # মাঝপথে ব্যর্থ হলে একবার রিট্রাই করুন — §৪ দেখুন
php artisan migrate --force
php artisan db:seed --force                       # প্রতিটা ডিপ্লয়মেন্টে, শুধু প্রথমটাতে না — §৮ দেখুন
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd frontend
npm run build                                     # package.json বদলালে আগে cPanel-এর "Run NPM Install"

cd ..
php artisan up
```

তারপর cPanel → **Setup Node.js App** → **Restart** থেকে Node.js App
রিস্টার্ট করুন — রিস্টার্ট না করা পর্যন্ত এটা পুরনো build মেমরিতে ধরে
রাখে, `docs/11-Deployment.md` §৬-এ প্রতিটা ডিপ্লয়মেন্টের পর queue
worker রিস্টার্ট করার একই কারণ।

---

## ৭. Cron জব

cPanel → **Cron Jobs**, দুইটা এন্ট্রি, দুটোই **প্রতি মিনিটে**:

```
* * * * * cd ~/<সাবডোমেইনের ফোল্ডার> && php artisan schedule:run >/dev/null 2>&1
* * * * * cd ~/<সাবডোমেইনের ফোল্ডার> && php artisan queue:work --stop-when-empty --max-time=55 >/dev/null 2>&1
```

§১.১-এ শেলের ডিফল্ট PHP পুরনো পেলে ওপরের বেয়ার `php`-এর বদলে পুরো path
ব্যবহার করুন — cron-এর নিজস্ব `php` সিস্টেম ডিফল্ট যা-ই হোক তা-ই, আর
এভাবে ব্যর্থ হওয়া cron entry কোনো এরর দেখায় না, কোনো স্পষ্ট লক্ষণও না।

প্রথম লাইনটা maintenance-schedule তৈরি, KPI snapshot, escalation
delivery, subscription billing — এটা ছাড়া প্রোডাক্ট দেখতে ঠিকই লাগবে
কিন্তু চুপচাপ সময়মতো কিছুই করবে না। দ্বিতীয়টা queue: notification,
webhook, export — সাথে সাথে না, এক মিনিটের মধ্যে ধরা হবে, যা সুপারভাইজড
`queue:work` ড্যায়মন ছাড়া shared hosting-এ সম্ভাব্য সেরা।

---

## ৮. কেন `db:seed --force` প্রতিটা ডিপ্লয়মেন্টে চলবে, শুধু প্রথমটাতে না

Reference data — permissions, roles, setting definitions,
asset/failure taxonomy — *কোডের* সাথে আসে, স্কিমার সাথে না। কোনো রিলিজ
যদি নতুন একটা যোগ করে আর সিডার না চালায়, তাহলে অ্যাপ্লিকেশন ডাটাবেসের
কাছে এমন একটা সারি চাইবে যেটা শুধু নতুন কোডেই আছে। বিশেষ করে setting
definition-এর জন্য এটা কোনো নীরব অবনতি না — resolver অজানা key-কে সরাসরি
প্রত্যাখ্যান করে (ADR-054), যেটা **প্রতিটা** পেজ — লগইন সহ — বন্ধ করে
দেয় যতক্ষণ না সিডার চলে। এখানকার প্রতিটা সিডার আবার চালানোর জন্য নিরাপদ
(প্রতিটাই একটা natural key দিয়ে `updateOrCreate` ব্যবহার করে), তাই
বারবার চালানোর কোনো খরচ নেই।

এর মধ্যে `App\Modules\Platform\Database\Seeders\PlatformAdminSeeder`-ও
আছে, যেটা ইচ্ছাকৃতভাবে প্রতিটা `migrate:fresh`/reseed-এ
`admin@noirban.com`-কে একই fixed পাসওয়ার্ড দিয়ে রাখে (সেই সিডারের
নিজের ডকব্লক দেখুন) — এই অ্যাকাউন্ট এই পাসওয়ার্ড দিয়ে প্রোডাকশনে থাকা
উচিত **না** হলে, প্রথম প্রোডাকশন সিডের আগেই এটা সরান বা বদলান, পরে না।

`DemoCustomerSeeder` (§৪) এর উল্টো: **কখনো** স্বয়ংক্রিয়ভাবে চলে না,
শুধু ক্লাসের নাম সরাসরি উল্লেখ করলেই চলে।

---

## ৯. এখানে যা কাজ করে না, আর কেন সেটা ঠিক আছে

`docs/11-Deployment.md` §13.5 যে ট্রেড-অফ Blade অ্যাপের জন্য আগেই
লিখেছে, সেটাই Next.js অংশের জন্যও প্রযোজ্য:

- **লাইভ আপডেট নেই।** `reverb:start`-এর একটা খোলা পোর্টে স্থায়ীভাবে
  চলা প্রসেস লাগে; shared hosting-এ কোনোটাই নেই। টপবারের sync indicator
  "Reconnecting" দেখাবে (সত্যিই — কখনো লাইভ বলে মিথ্যা দেখানো হয় না),
  আর notification badge/breakdown list/work-order board পর্দায়
  push করার বদলে পেজ লোড বা রিফ্রেশে আপডেট হবে। কিছুই হারায় না; একজন
  টেকনিশিয়ানকে শুধু রিফ্রেশ করতে হবে। এটা মেনে নেওয়া বন্ধ হলে VPS-এ
  যান।
- **Queue-তে সর্বোচ্চ ~১ মিনিট দেরি**, তাৎক্ষণিক না — §৭-এর cron-চালিত
  `queue:work --stop-when-empty`, কোনো সুপারভাইজড ড্যায়মন না।
- **`max_execution_time`** (shared hosting-এ প্রায়ই 30 সেকেন্ড) একটা
  বড় import/export ব্রাউজারে কেটে দিতে পারে — দুটোই এই কারণেই আগে থেকে
  queue-তে চলে; individual import ছোট রাখুন।

---

## ১০. সমস্যা সমাধান (Troubleshooting)

| লক্ষণ | সম্ভাব্য কারণ |
|---|---|
| "Receiving objects" শেষ হওয়ার পর `git clone` "unable to create thread" দিয়ে ব্যর্থ | LVE process/thread লিমিট — `git -c pack.threads=1 clone ...` দিয়ে রিট্রাই (§১.৪) |
| `git clone` "destination path '.' already exists" দিয়ে ব্যর্থ | cPanel আগে থেকেই সাবডোমেইন ফোল্ডারে কিছু বানিয়ে রেখেছে (`cgi-bin`, `public`, `.well-known`) — একটা টেম্প ফোল্ডারে ক্লোন করে merge করুন (§১.৪) |
| সব ডাউনলোড হওয়ার পর `composer install` "Unable to launch a new process" দিয়ে মাঝপথে ব্যর্থ | একই LVE লিমিট, archive extraction-এর সময় — ঠিক একই কমান্ড আবার চালান (§৪) |
| `composer install` সাথে সাথে "does not satisfy" এরর দেয় | PHP < 8.4.1 — §১.১ দেখুন |
| `npm run` / "Run NPM Install" কয়েক মিনিট ধরে কোনো অগ্রগতি ছাড়াই আটকে থাকে | সম্ভবত একই LVE লিমিট — অনন্তকাল অপেক্ষা না করে বাতিল করে রিট্রাই করুন (§৫) |
| `.env` ফাইল `/api/v1/.env`-এ 200 দিয়ে লোড হয় | Document root রিপো রুটে পয়েন্ট করছে, `public/`-এ না — §১.৩ দেখুন |
| `php artisan migrate` `2026_09_03_083842_migrate_legacy_rbac_to_spatie`-তে "Key column 'role_id' doesn't exist" দিয়ে ব্যর্থ | commit `d324516`-এ ফিক্স করা হয়েছে — `git pull` করুন, ব্যর্থ চেষ্টা আগে মাঝপথে চলে থাকলে `migrate:fresh --force` চালান (কেন প্লেইন রিট্রাই না, §৪ দেখুন) |
| `php artisan migrate` কোনো টেবিলে "1067 Invalid default value for '\<column\>'" দিয়ে ব্যর্থ | MySQL-এর `explicit_defaults_for_timestamp` off + `NO_ZERO_DATE` strict mode — commit `3d51967`-এ ফিক্স করা হয়েছে, `git pull` করে `migrate:fresh --force` চালান — §৪ দেখুন |
| `db:seed` (Demo সিডার) "SubscriptionContract::limitFor(): Return value must be of type ?int, string returned" দিয়ে ব্যর্থ | মডেলের `casts()`-এ integer cast মিসিং ছিল — commit `3843b42`-এ ফিক্স করা হয়েছে, `git pull` করে সিডার আবার চালান, migrate লাগবে না — §৪ দেখুন |
| §২-এর `sed` কমান্ডের পরও `APP_ENV` এখনো `local` দেখায় | সেই একটা `sed` substitution একবার চুপচাপ কাজ করেনি দেখা গেছে — `grep "^APP_ENV" .env` দিয়ে চেক করুন, দরকার হলে `nano`-তে হাতে ঠিক করুন |
| ডিপ্লয়মেন্টের পর প্রতিটা পেজ 500 এরর দেয় | `db:seed --force` ভুলে গেছেন আর একটা নতুন setting definition মিসিং — §৮ দেখুন |
| ডিপ্লয়মেন্টের পরও ফ্রন্টএন্ড পুরনো কনটেন্ট দেখায় | Node.js App রিস্টার্ট করা হয়নি — §৬ দেখুন |
| লগইন কাজ করে কিন্তু তারপর প্রতিটা পেজ `/login`-এ লুপে redirect করে | Laravel-এর `.env`-এ `FRONTEND_URL` আসল `app.yourdomain.com` origin-এর সাথে মেলে না, অথবা `frontend/.env.production`-এ `LARAVEL_API_URL` `api.yourdomain.com`-এর সাথে মেলে না |
| পাসওয়ার্ড রিসেট "পাঠানো হয়েছে" বলে কিন্তু কিছু আসে না | `MAIL_MAILER` এখনো `log` — §২ দেখুন |

এখানে যা কভার করা হয়নি — backup, এই হোস্টের বাইরে scaling, alerting,
ভবিষ্যতের PostgreSQL cutover — সেগুলোর জন্য `docs/11-Deployment.md`
দেখুন।
