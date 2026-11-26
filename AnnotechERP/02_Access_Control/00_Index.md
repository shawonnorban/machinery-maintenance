# Access Control

> **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **ফোল্ডার:** `02_Access_Control`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `02/01` | [Structure](01_Structure.md) | ৯ | ✅ সম্পন্ন (ধাপ A, B, C: ৯টা স্ক্রিনই; বিচ্যুতি আর বাকি কাজ As-built নোটে) |
| `02/02` | [Mappings](02_Mappings.md) | ১০ | ✅ সম্পন্ন |
| `02/03` | [Configuration](03_Configuration.md) | ৪ | 🟡 আংশিক (Approval Workflow Designer, Report Template Setup, Mail Service Configuration সম্পন্ন; Ledger Integration Finance-এর সাথে) |
| `02/04` | [Operations](04_Operations.md) | ৪ | ✅ সম্পন্ন |
<!-- AUTO:status:end -->

## নোট

**মূল সিদ্ধান্ত: Brickland-এর "Menu × ফ্ল্যাগ" মডেল আমাদের পারমিশন-ইঞ্জিনের ওপর একটা স্তর, প্রতিস্থাপন না।**
- বর্তমানে সব কোড পারমিশন-স্ট্রিং দিয়ে চেক করে (`$this->allow('procurement.order.view_any')`)। ১৪৫টা পারমিশন আছে, আর সাইডবার `/auth/permissions` দিয়ে ফিল্টার হয়। এটা বদলালে পুরো সিস্টেম নতুন করে লিখতে হবে, তাই বদলাবে না।
- Brickland-এ Role-কে মেনু দেওয়া হয় ৭টা ফ্ল্যাগে: V(iew) C(reate) E(dit) D(elete) A(pprove) P(rint) X (Export)। আমাদের এখানে প্রতিটা মেনুর প্রতিটা ফ্ল্যাগ এক বা একাধিক পারমিশন-স্ট্রিংয়ের সাথে বাঁধা (`menu_permissions`)। Role-কে কোনো ফ্ল্যাগ দিলে নিচে আসলে সেই পারমিশনগুলোই `role_permissions`-এ যায়। এক পারমিশন যদি দুটো মেনুতে থাকে, একটা থেকে ফ্ল্যাগ সরালেও অন্যটার জন্য থেকে যায়।
- **সাইডবার আসবে DB-র মেনু-ট্রি থেকে** (এখন `app/(app)/layout.js`-এ হাতে লেখা)। কোড একটা `MenuRegistry` থেকে মডিউল আর মেনু seed করবে। অ্যাডমিন নাম, বাংলা নাম, ক্রম, আইকন আর Active বদলাতে পারবেন, আর নতুন ফোল্ডার বানাতে পারবেন। কিন্তু নতুন **স্ক্রিন** (leaf) কোড ছাড়া বানানো যাবে না, কারণ পেজ না থাকলে লিংক অর্থহীন।
- **বিদ্যমান সমতুল্য অংশ পুনর্ব্যবহার হবে:** System Parameter = `setting_definitions` + `settings`; Approval Workflow Designer = `approval_workflows` (Approval মডিউল); Ledger Integration = `gl_event_mappings` (Finance)। Brickland-এর স্ক্রিনগুলো এগুলোর ওপর নতুন UI।
- **বায়ার-নির্ভর ম্যাপিং** (Buyer-Wise Fabric Rights, Buyer Store, Buyer Item Category, Fabric Defect × Buyer) Buyer master (Merchandise Desk 06/01) তৈরি হওয়ার পরে হবে।
