# Brickland ERP ক্রলার

**⬅ ফিরে যান:** [AnnotechERP Index](../../00_Index.md)

Brickland ERP-তে লগইন করে প্রতিটা মেনু-স্ক্রিন খোলে। প্রতিটা স্ক্রিন থেকে স্ক্রিনশট, ফিল্ড (label, type, required, select option), ট্যাব, গ্রিড-কলাম, বাটন সংগ্রহ করে, আর চাইলে New/Add ডায়ালগ খুলে খালি Save-এ আসা ভ্যালিডেশনও। আউটপুট থেকে [`build_erp_docs.py`](../build_erp_docs.py) প্রতিটা গ্রুপ-ফাইলের §১-২ লেখে।

## অনুমতি ও নিরাপত্তা

- ক্রেডেনশিয়াল থাকে প্রজেক্ট রুটের `.annotech-erp-audit.env`-এ (`ERP_URL`, `ERP_USER`, `ERP_PASS`), যেটা `.gitignore`-এ আছে। ক্রলার এটা পড়ে `ERP_AUDIT_ENV` এনভায়রনমেন্ট ভ্যারিয়েবল থেকে, ডিফল্ট `~/.annotech-erp-audit.env`।
- ডিফল্ট মোডে লগইনের পরে সব non-GET রিকোয়েস্ট ব্লক থাকে আর `out/blocked.log`-এ লেখা হয়।
- `--allow-load-posts`: পেজ লোড আর ট্যাব বদলানোর সময় পেজ নিজে যে POST পাঠায় (ডাটা-লোডার), সেগুলো চলে আর `out/allowed.log`-এ লেখা থাকে। মালিকের অনুমোদিত।
- `--allow-writes` (শুধু `--dialogs`-এর সাথে): সব POST চলে, আর New/Add ডায়ালগে খালি Save চাপা হয়। মালিক নিশ্চিত করেছেন এটা **ডেমো ইনস্ট্যান্স, আসল ডাটা নেই**। আসল ডাটার সিস্টেমে কখনো চালাবেন না।
- `out/` গিটের বাইরে (স্ক্রিনশট আর ড্রপডাউন অপশনে ব্যবসার ডাটা থাকতে পারে)।

## ব্যবহার (প্রজেক্ট রুট থেকে)

```
export ERP_AUDIT_ENV="$PWD/.annotech-erp-audit.env"

# ১. মেনু ট্রি (collapsed গ্রুপ খুলে, সব মডিউল পেজে ঢুকে)
node AnnotechERP/tools/erp_crawler/crawl.mjs --menu-only --discover --max-toggles 300

# ২. প্রতিটা স্ক্রিন: ফিল্ড, ট্যাব, গ্রিড, স্ক্রিনশট (resumable; --refresh দিলে আবার)
node AnnotechERP/tools/erp_crawler/crawl.mjs --screens --allow-load-posts [--only knitting,dyeing]

# ৩. New/Add ডায়ালগ + খালি Save-এ ভ্যালিডেশন (ডেমো ইনস্ট্যান্স)
node AnnotechERP/tools/erp_crawler/crawl.mjs --dialogs --allow-writes [--only merchandising]

# ৪. ডক আপডেট
python AnnotechERP/tools/build_erp_docs.py
```

আউটপুট (`out/`): `menu.json` (প্রতিটা রানে শুধু বাড়ে, মোছে না), `modules/*.png`, `screens/<module>/<screen>[__<tab>|__dlg_<button>].png`, `fields/<module>/<screen>.json` (`views[]`, `dialogs[]`), `blocked.log`, `allowed.log`।

> **নোট:** `SELECTORS` (লগইন, সাইডবার, সাইডবারের toggle) এখন Brickland-এর সাথে মেলানো। অন্য সিস্টেমে চালাতে হলে প্রথম রানের স্ক্রিনশট দেখে এগুলো বদলাতে হবে।

## লাইভ probe (একটা স্ক্রিনের প্রতিটা ট্যাব আর অবস্থা)

মডিউল বানানোর আগে Brickland-এর প্রতিটা ট্যাব খোলা, বামের তালিকা থেকে একটা আইটেম বাছাই আর Edit খোলা, আর প্রতিটা অবস্থার স্ক্রিনশট আর DOM-এর সারাংশ নেওয়ার জন্য:

```
node AnnotechERP/tools/erp_crawler/probe.mjs AnnotechERP/tools/erp_crawler/plans/work_study.json <out-dir>
```

- **plan:** একটা JSON তালিকা। প্রতিটা ধাপে থাকতে পারে `goto` (পাথ), `click` (লেখা), `clickSel`, `peek` (শুধু ডায়ালগ খোলার ক্লিক, এখানে POST ব্লক থাকে), `shot` (নাম) আর `escape`।
- **আউটপুট:** প্রতিটা `shot`-এর PNG, আর `report.json`-এ সেই অবস্থার সারাংশ: টেবিলের হেডার আর সারির কন্ট্রোল, select-এর অপশন, বাটন, হিন্ট আর ফুটারের লেখা, label।
  - খোলা ডায়ালগ `<main>`-এর বাইরে থাকে, তাই `dialogs`-এ আলাদা করে আসে: শিরোনাম, হিন্ট, label, ঘর আর বাটন।
- **Edit আর গাছের নোড:** Brickland এগুলো খোলার সময় সার্ভার থেকে সারি লোড করে, তাই `peek` নয়, `clickSel` বা `click` দিয়ে খুলতে হয়। নইলে পেজ "This page couldn’t load" দেখায়। Save কখনো চাপা হয় না।
- **নিরাপত্তা:**
  - লগইনের পরে সব non-GET ব্লক থাকে। শুধু পেজ লোড বা ট্যাব/আইটেম বদলানোর সময় পেজ নিজে যে ডাটা-লোডার POST পাঠায়, সেগুলো চলে।
  - probe কখনো Save চাপে না।
