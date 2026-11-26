# Company Management

> **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **ফোল্ডার:** `01_Company_Management`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `01/01` | [Company Management](01_Company_Management.md) | ৩ | ✅ সম্পন্ন |
| `01/02` | [Factory Setup](02_Factory_Setup.md) | ৩ | ✅ সম্পন্ন |
| `01/03` | [Organization](03_Organization.md) | ৪ | ✅ সম্পন্ন |
| `01/04` | [Machine Management](04_Machine_Management.md) | ২ | ✅ সম্পন্ন |
| `01/05` | [Designation & Grade](05_Designation_Grade.md) | ২ | ✅ সম্পন্ন |
<!-- AUTO:status:end -->

## নোট

**মূল সিদ্ধান্ত: নতুন টেবিল না, বিদ্যমান টেবিলের সম্প্রসারণ।** `companies`, `factories`, `buildings`, `floors`, `departments`, `sections`, `production_lines`, `workstations` বর্তমান `app/Modules/Tenancy`-তে আগে থেকেই আছে। Machinery (Asset location, Breakdown, Work Order) এগুলোর ওপর চলে। তাই Brickland-এর বাড়তি কলাম এই টেবিলেই যোগ হবে, আর Machinery অক্ষত থাকবে। নতুন টেবিল লাগবে শুধু `designations`, `grades`, `company_documents`-এর জন্য।

**Machine Master = বিদ্যমান `assets`। Machine Allocation = বিদ্যমান `asset_locations`।** Brickland-এর মেশিন-স্ক্রিন Machinery মডিউলের ডাটাই দেখাবে আর এডিট করবে, কোনো দ্বিতীয় মেশিন-টেবিল হবে না।

**UI-র ধরন (Brickland থেকে, সব স্ক্রিনে একই):**
- পেজ-হেডার: আইকন, শিরোনাম, এক লাইনের বর্ণনা
- উপরে ট্যাব-সারি: Records (সংখ্যাসহ) · Company Profile · Factory · Building · Floor · Department · Section · Line · Workstation · Machine Master · Machine Allocation · Designation · Grade · Company Documents
- প্রতিটা ট্যাব একটা কার্ড, তাতে শিরোনাম আর বর্ণনা, Export, "Add X" বাটন, আর **ইনলাইন-এডিটযোগ্য গ্রিড**
- নিচে নিয়ম-বার্তা, Revert আর Save (পুরো গ্রিড একসাথে সেভ)
- "Add X" একটা মডাল খোলে: "New x" কার্ড, নিয়ম-বার্তা, ফিল্ড, Reset আর "Add X"। খালি সেভে লাল ব্যানার আর একটা toast-এ ভ্যালিডেশন দেখায়।
- নতুন সিস্টেমে **লাইট থিম**। বিদ্যমান shadcn/ui কম্পোনেন্ট আর Card-wrap ট্যাব প্যাটার্ন ব্যবহার হবে।

**সাধারণ নিয়ম (সব মাস্টারে):**
- Code খালি রাখলে সিস্টেম `NumberSequenceGenerator` থেকে ইস্যু করে (BLD-001, FLR-001 …)।
- কোনো রেকর্ড অন্য রেকর্ডে ব্যবহৃত থাকলে মোছা যাবে না। 409 আসবে, আর মেসেজে বলা থাকবে কী কী বাধা দিচ্ছে ("২টা ফ্লোর, ৩টা লাইন")। সমাধান: Status = Inactive।
- সব সেভ অডিট-লগে যায় (`AuditServiceProvider::AUDITED`)।
- Status: Active/Inactive (মেশিনের জন্য আলাদা, নিচে দেখুন)।

**পারমিশন (নতুন):** `company.structure.view_any`, `company.structure.manage`। এগুলো বিদ্যমান Settings → Master Data পারমিশনের জায়গা নেবে। পুরনো Master Data পেজ (Building/Floor/Department/Section/Line/Workstation টাইপ) সরিয়ে Company Management-এ নির্দেশ (redirect) করা হবে।
