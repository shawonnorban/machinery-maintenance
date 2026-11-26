# Command Center

> **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **ফোল্ডার:** `27_Command_Center`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `27/01` | [Command Center](01_Command_Center.md) | ৩ | ✅ সম্পন্ন (৩টা রেজিস্টার + Command Deck) |
| `27/02` | [Kpi & Targets](02_Kpi_Targets.md) | ৫ | ⬜ শুরু হয়নি |
| `27/03` | [Alerts & Exceptions](03_Alerts_Exceptions.md) | ৩ | ⬜ শুরু হয়নি |
| `27/04` | [Strategy & Risk](04_Strategy_Risk.md) | ৩ | ⬜ শুরু হয়নি |
| `27/05` | [Analytics & Reporting](05_Analytics_Reporting.md) | ৫ | ⬜ শুরু হয়নি |
<!-- AUTO:status:end -->

## নোট

- **কোড:** নিজস্ব মডিউল `app/Modules/CommandCenter` (টেবিল `cc_*`, lang `commandcenter`); frontend route `/command-center/[screen]`।
- **পারমিশন:** প্রতি স্ক্রিন-গ্রুপে একজোড়া — `commandcenter.{group}.view` / `.manage`।
  - Factory Admin / Factory Manager সব পান; Auditor শুধু দেখেন।
- **দুই ধরনের স্ক্রিন:**
  - Brickland-এর "Demo data" রেজিস্টারগুলো ERP resource kit-এ বানানো। 27 থেকে শেয়ার করা ছাঁচ: backend `Erp/Support/DemoResource`, frontend `lib/demo-screen.js`।
  - **Chief Executive Command Deck** (Brickland-এর CEO Command Center) আসল ড্যাশবোর্ড — সংখ্যাগুলো মডিউলের নিজের রেকর্ড থেকে হিসাব হয়।
- **স্ক্রিন সংখ্যা:** crawler-এর তালিকায় ১৯টা। Brickland-এর সাইডবারে Command Center ফোল্ডারে চতুর্থটা Command Deck (Brickland-এ "Dashboard"), তাই এখানে মোট ২০টা।
