# খোলা প্রশ্ন (Open Questions)

> প্রতিটা তৈরি হওয়া সাব-মডিউলের ডকের **"১০. খোলা প্রশ্ন"** অংশ থেকে তোলা, এক জায়গায়।
> উত্তর পেলে মূল ডকে (আর কোডে) সমাধান লিখুন, তারপর আবার চালান: `python AnnotechERP/tools/build_open_questions.py`।

**মোট:** ১২৫টা সাব-মডিউলে প্রশ্ন বাকি।

**⬅ ফিরে যান:** [AnnotechERP Index](00_Index.md)

---

## Machinery Maintenance

### `00/07` [Technicians & Teams](00_Machinery_Maintenance/07_Technicians_Teams.md)

(এখন কোনো খোলা প্রশ্ন নেই — Technician ↔ HR কর্মী: HR & Payroll-এর তালিকা থেকে বাছাই, ২০২৬-১১-১৪-এ উত্তর ও বাস্তবায়ন।)


## Access Control

### `02/02` [Mappings](02_Access_Control/02_Mappings.md)

- Brickland-এ Fabric Defect আর Item Category-র matrix-এ বায়ার বাছলে পাতা ভেঙে পড়ে ("This page couldn't load")। তাই এ দুটো Buyer Store-এর matrix-এর ধাঁচে বানানো হয়েছে (টিক, সাথে প্রতি সারির পয়েন্ট / কোড / ফ্ল্যাগ)।
- Brickland-এ defect-এর "নিজের ডিফল্ট পয়েন্ট" আছে, কিন্তু ধরনের কোনো master স্ক্রিন দেখা যায়নি। এখানে ধরন system lookup `FABRIC_DEFECT`, ডিফল্ট পয়েন্ট নেই। Knit Production-এর roll inspection বানানোর সময় ঠিক হবে।


## Settings

### `03/01` [Device Integration](03_Settings/01_Device_Integration.md)

১. **TCP Pull (পোর্ট 4370):** রেজিস্টার আর Test Connection আছে, কিন্তু যন্ত্র থেকে টেনে পাঞ্চ পড়া (Pull Logs Now, Full Re-read) এখনো হয়নি — ZKTeco-র বাইনারি প্রোটোকল আসল যন্ত্র ছাড়া পরীক্ষা করা যায় না, তাই ঐ দুটো বাটন দেখানো হয়নি। Brickland-এর ছয়টা যন্ত্রই ADMS Push। পুল যন্ত্র ব্যবহার হলে জানাবেন — একটা যন্ত্রে পরীক্ষা করে বানাব।

২. **অচেনা যন্ত্র:** Brickland অচেনা সিরিয়ালের যন্ত্রকে নিজে থেকে "Pending approval" হিসেবে তুলে নেয় বলে মনে হয়। এখানে অনেক কোম্পানি, অচেনা যন্ত্র কোন কোম্পানির বোঝা যায় না — তাই আগে রেজিস্টার করতে হয় (অবস্থা Pending approval রেখে পরে Approve করা যায়)।

৩. **দৈনিক হাজিরা:** ✅ মিটেছে — 24/04 Attendance & Shift তৈরি হয়েছে; "Update daily attendance in/out time" চালু থাকলে পাঞ্চ এলেই ঐ কর্মীর দিন হিসাব হয়।

৪. **Integration Hub-এর DEVICE চ্যানেল:** যন্ত্রের প্রতিটা যোগাযোগ Integration Activity-তে লেখা হয় না (খুব বেশি — প্রতি দশ সেকেন্ডে)। শুধু শেষ যোগাযোগ আর শেষ ফল যন্ত্রের সারিতে থাকে।


## Merchandise Desk

### `06/01` [Master Data](06_Merchandise_Desk/01_Master_Data.md)

- **Item Type আর Nature of Work-এর তালিকা Brickland-এর ক্রলে ছিল না:** দুটো স্ক্রিন পরে Brickland-এ যোগ হয়েছে। তাই উপরের AUTO তালিকা (২৭টা) আর "লাইভ বিবরণ"-এ এগুলো নেই। ফিল্ড ধরা হয়েছে লাইভ প্রোব থেকে (`md2.json`, `md3.json`, `md4.json`)।
- **ডিফল্ট তালিকা:** Brickland-এ দুটো master-এ কোনো রেকর্ড নেই, তবু Garment Items-এ অপশন আসে। এখানে আগের স্থির তালিকাটাই প্রথমবারে master-এ বসানো হয় (৭৭টা টাইপ, চারটা কাজের ধরন)। এরপর তালিকা কোম্পানি নিজে বদলায়।
- **Upload-এর ধরন:**
  - একটা Order#-এর জন্য একটা কনফার্মড অর্ডার হয় (ধরন Bulk New, মুদ্রা USD)।
  - রেট হয় লাইনগুলোর ওজনসহ গড়।
  - Production Date শুধু আপলোড-লাইনে রাখা হয়, কারণ অর্ডারে এর জন্য কোনো ঘর নেই।
  - নতুন স্টাইলের অবস্থা Running আর বিবরণ Item। সাইজ রেঞ্জ ফাঁকা থাকে।


### `06/02` [Ai Merchandising Dashboard](06_Merchandise_Desk/02_Ai_Merchandising_Dashboard.md)

- **উৎপাদনের অগ্রগতি:** উৎপাদন মডিউল নেই, তাই ০%। ফলে নতুন অর্ডারেও কিছু production risk থাকে।
- **Claude:** dev-এ key নেই, তাই শুধু rule engine দিয়ে যাচাই হয়েছে। key দিলে narrative আর পদক্ষেপ Claude লিখবে; ব্যর্থ হলে rule engine-এর লেখাই থাকবে।
- **Merchandiser Scorecard:** owner আসে T&A Task Rights (can_update) থেকে। dev-এ কেউ সেট করা নেই, তাই খালি বার্তা দেখায়।


### `06/03` [Ai Order Risk & Health](06_Merchandise_Desk/03_Ai_Order_Risk_Health.md)

- **ওজন:** Brickland-এর কার্ড থেকে ওজন নেওয়া হয়েছে (২২/২২/২৪/১৮/১৪)। sub-score-এর সূত্র Brickland দেখায় না, তাই সেগুলো নিজের।
- **উৎপাদন:** উৎপাদন মডিউল নেই, তাই Production Risk শুধু সময় থেকে আসে।


### `06/04` [Ai Order Progress & Task Monitor](06_Merchandise_Desk/04_Ai_Order_Progress_Task_Monitor.md)

- **উৎপাদনের ধাপ:** Cutting … Shipped এখন ০%; উৎপাদন মডিউল এলে engine থেকে আসবে।
- **Priority-র সূত্র:** Brickland শুধু স্কোর দেখায় (যেমন ৬৬), সূত্র নয়। এখানে নিজের সূত্র, Brickland-এর নোটের নিয়ম মেনে।


### `06/05` [Inquiry](06_Merchandise_Desk/05_Inquiry.md)

- Brickland-এ Buyer A/C ড্রপডাউন সবসময় খালি দেখা গেছে। আমরা এটাকে বায়ারের লিংক করা অফিস হিসেবে নিয়েছি; ভুল হলে জানাবেন।


### `06/06` [Trims & Accessories](06_Merchandise_Desk/06_Trims_Accessories.md)

- **T1 "Add New":** Brickland-এর Records-এর "Add New" ডায়ালগ আসলে BOM-এর সার্চ। এখানে সেটা BOM ট্যাবে নিয়ে যায়।
- **T2 PO নম্বর:** Brickland-এ PO সেভ হচ্ছিল না (বাটন সক্রিয়, কোনো ভুলের বার্তা নেই, রেকর্ডও হয় না), তাই নম্বরের ধরন, সারির অ্যাকশন আর এডিট অবস্থা দেখা যায়নি। এখানে নম্বর `PO-ACC-YYYYMM####`, সারিতে Edit আর Delete।
- **T2 Requisition List:** Brickland-এ বায়ার অ্যাকাউন্ট নেই, তাই তালিকা সবসময় খালি দেখায়। ভেতরের অংশ বানানো হয়েছে পাতার লেখা থেকে ("requisitions … still waiting to be ordered")।
- **T3 Records:** Brickland-এ কোনো রিসিপ্ট নেই, আর সেভ হচ্ছিল না, তাই গ্রিডের কলাম দেখা যায়নি। কলামগুলো এখানে বেছে নেওয়া।
- **T3 খসড়া ভাউচার:** Brickland-এর Finalize খসড়া ভাউচার তৈরি করে। এখানে Accounting-এর ভাউচার আর T5 Posting Setup তৈরি হলে যোগ হবে।
- **T6 Finalize বাটন:** Brickland-এর নতুন চালানে শুধু Back To List, Clear আর Save দেখা যায়। কিন্তু Records-এ "Is Finalized" কলাম আছে, তাই সেভ করা চালানে Finalize আর Delete রাখা হয়েছে। Brickland-এ সেভ হয় না, তাই এটা যাচাই করা যায়নি।
- **T7 workflow বাটন:** Brickland-এ সেভ হচ্ছিল না, তাই সেভের পরের বাটন দেখা যায়নি। প্রবাহ আর Unpost/Lock বানানো হয়েছে পাতার নিয়মের বাক্য থেকে ("Draft → Submitted → Approved → Posted → Locked … a locked balance can no longer be unposted")। Reject করলে Draft-এ ফেরে, কারণ Records-এর Status ফিল্টারে আলাদা "Rejected" নেই।
- **T7 Attach:** Brickland-এ নতুন অবস্থাতেই Attach বাটন আছে। এখানে আগে সেভ করতে হয়, কারণ ফাইল OB-র সাথে যুক্ত থাকে। ফাইলের ধরন প্রজেক্টের নিয়মে সীমিত (ছবি/PDF), তাই Excel কাউন্ট শিট PDF করে দিতে হবে।
- **T7 OB নম্বর:** Brickland-এর ধরন দেখা যায়নি; এখানে `PFOB-YYYYMM####`।
- **T8 PR List-এর Show বাটন:** Brickland-এ ফিল্টার দিয়ে Show চাপতে হয়। এখানে ফিল্টার বদলালেই তালিকা বদলায়, অন্য তালিকাগুলোর মতো। লেখা খোঁজার ঘর Enter চাপলে বা ঘর ছাড়লে কাজ করে।
- **T8 নম্বর:** Brickland-এর ডামি indent-এর নম্বর দেখা যায়নি (সেভ হয় না); এখানে `PRQ-DUM-…`।
- **T10 Approved PR List-এ বাছা:** Brickland-এর লেখা "Pick an approved requisition … to fill the details"। এখানে বাছলে তার খোলা লাইন যোগ হয়, আগের লাইন মোছে না। তাই একাধিক PR এক PO-তে নেওয়া যায় (PR Number(s))।
- **T10 "Add to PO":** Brickland-এর খালি লাইনের ভেতর দেখা যায়নি। এখানে খোলা PR লাইন বাছার ঘর রাখা হয়েছে, কারণ নিয়মে প্রতিটা লাইনকে একটা indent লাইন অর্ডার করতে হয়।
- **T11 Source Type:** Brickland-এর নতুন MRR-এ ডিফল্ট IMPORT। এখানে নতুন ফর্মে IMPORT থাকে, তবে Receive List থেকে PO বাছলে PO-র Source বসে, কারণ রিসিপ্ট সেই অর্ডারেরই।
- **T11 Finalize আর Delete:** Brickland-এর নতুন MRR-এ শুধু Back To List, Clear আর Save দেখা যায় (সেভ হয় না)। T3-এর মতো সেভ করা খসড়ায় Finalize MRR আর Delete রাখা হয়েছে, কারণ Records-এ "Is Finalized" কলাম আছে।
- **T11 Receive List-এর "Customer":** Brickland-এ লেবেল Customer, কিন্তু তালিকায় সাপ্লায়ার। এখানেও লেবেল Customer, অপশন সাপ্লায়ার।
- **T12 MRR No ঘর:** Brickland-এর Master List-এ MRR No ঘর দেখা যায় না, কিন্তু চালান কোন MRR-এর তা জানতে হয়। T6-এর মতো শুধু-পড়ার ঘর রাখা হয়েছে।
- **T13 নম্বর:** Brickland-এর Existing-এ "OB-1" দেখা যায়। এখানে মাস অনুযায়ী `FFOB-YYYYMM####`।
- **T13 Records-এ Edit নেই:** Brickland-এর সারিতে শুধু Remove আছে, তাই এখানেও। এডিট হয় Entry-র Existing ড্রপডাউন দিয়ে।
- **Closing-এর exception কোনগুলো:** Brickland শুধু বলে "documents standing in the way of closing it"। এখানে ধরা হয়েছে: সেই kind-এর স্টকে প্রভাব ফেলা ডকুমেন্ট, যেগুলো মাসের তারিখে এখনো finalize বা post হয়নি। Indent আর PO স্টক বদলায় না, তাই ধরা হয়নি।
- **Closed মাসের guard:** Brickland-এ সেভ হয় না, তাই এটা দেখা যায়নি। কিন্তু পাতার নিয়মের বাক্য ("a later rate change cannot restate a period that has already closed") অনুযায়ী বন্ধ মাসের ডকুমেন্ট আর বদলানো যায় না।
- **Closing আবার খোলা:** Brickland-এ কোনো বাটন নেই, এখানেও নেই। দরকার হলে পরে যোগ করা যাবে।
- **Closing-এ ব্যালান্সের snapshot:** Brickland-এর Records-এ পরিমাণ বা মূল্যের কলাম নেই, তাই রাখা হয়নি। রিপোর্ট লাগলে ledger থেকে মাস-শেষের হিসাব বের করা যায়।


### `06/08` [Accessories & Trims](06_Merchandise_Desk/08_Accessories_Trims.md)

- **বুকিংয়ের নম্বর:** Brickland-এর মতো `ACB-00001` আর `TDP-00001` রাখা হয়েছে, মাস অনুযায়ী নয়।
- **বুকিং আর লাইনের Status বদল:** Brickland-এ কোনো বাটন দেখা যায়নি, তাই এখানে সব Draft থাকে।
- **Sourcing-এর Records-এ ACTION:** Brickland-এ এই কলামের বাটন দেখা যায়নি (রেকর্ড ছিল না)। এখানে "Edit", যা সেই অর্ডারের Details খোলে। URL-এ অর্ডার নম্বর থাকে, id নয়।
- **Trims Accessories Booking-এর Req Type ফিল্টার:** Brickland-এ ফিল্টারটা আছে, কিন্তু Records অর্ডারের তালিকা, বুকিংয়ের নয়। তাই এখানে বাদ রাখা হয়েছে।
- **নমিনেটেড সাপ্লায়ার:** Brickland-এর hint ("cannot be substituted without the buyer's agreement") অনুযায়ী টিক বহাল থাকলে সাপ্লায়ার বদলানো আটকানো হয়।
- **bn অনুবাদ:** ছোট শব্দগুলো বাংলায় অনুবাদ হয়েছে। লম্বা hint-বাক্যগুলো এখনো ইংরেজিতে।


### `06/09` [Costing & Budget](06_Merchandise_Desk/09_Costing_Budget.md)

১. **নিট বুকিংয়ের সুতার রেট:** বুকিং থেকে আনা নিট কাপড় (আর নিট ট্রিমস) বাজেটের "Yarn"-এ kg হিসেবে আসে, কিন্তু রেট ০ থাকে, কারণ বুকিংয়ের রেট কাপড়ের, সুতার নয়। সুতার দাম কোথা থেকে নেওয়া হবে (Yarn Spec, সুতা কেনার রেট, নাকি হাতে লেখা) জানাবেন।

২. **Additional / Buffer বুকিং:** এখন শুধু Bulk বুকিং থেকে আনা হয়। Additional বা Buffer বুকিংও বাজেটে যোগ হবে কিনা জানাবেন।

৩. **সাপ্লায়ারের দামের ক্রম:** এক্সেসরিজের রেট সাপ্লায়ারের দাম থেকে আসে। ক্রম: অনুমোদিত কোটেশন → শেষ এক্সেসরিজ PO → খোলা কোটেশন। আলাদা "সাপ্লায়ার প্রাইস লিস্ট" মাস্টার লাগবে কিনা, বা ক্রম বদলাতে হবে কিনা জানাবেন।

৪. **অন্য মুদ্রার দাম:** BDT দাম শিটের Conv. Loc. দিয়ে ভাগ হয়। BDT শিটে বিদেশি PO-র দাম সেই PO-র রেটে গুণ হয়। এর বাইরে (যেমন USD শিটে EUR কোটেশন) রেট বসে না, শুধু সাপ্লায়ারের পাশে দাম দেখায়। মুদ্রার রেট মাস্টার থেকে বদলাতে হবে কিনা জানাবেন।


### `06/11` [Fabric Booking](06_Merchandise_Desk/11_Fabric_Booking.md)

- Brickland-এ Records-এর Edit বাটনে কিছু হয় না। এখানে Edit ট্যাব খোলে।
- Finish Fab Qty-র সূত্র Brickland-এ দেখা যায়নি, কারণ সেখানে লাইভ ডাটা নেই। এখানে ধরা হয়েছে কনজাম্পশন × পিস × (১ + কাটিং ওয়েস্টেজ) + AddQty।
- YD / AOP ওয়েস্টেজ এখন শুধু সংরক্ষিত হয়, হিসাবে ঢোকে না। SME নিশ্চিত করবেন।

- Send Mail? আর Send Date এখন শুধু দেখানো হয়: বাল্কে "No" আর "—"। মেইল কাকে আর কখন যাবে, তা F4/F5 (যাদের ফর্মে Send Mail? আছে) বানানোর সময় ঠিক হবে।
- Brickland-এর তালিকায় Buffer বুকিং অনুমোদনের তারিখ ছাড়াই ("—") আসে, কারণ সেটা ফর্মের Status দিয়ে Approved করা। এখানে Status দিয়ে Approved করলেও তারিখ রাখা হয়।


### `06/12` [T&A Followup](06_Merchandise_Desk/12_T_A_Followup.md)

- Brickland-এর ডিপার্টমেন্ট (Merchandising, Production, Store, Packing, Logistics) টাস্কের নিজের ঘর বলে মনে হয়। এখানে টাস্ক গ্রুপের নাম ডিপার্টমেন্ট হিসেবে দেখানো হয়। আলাদা ঘর লাগবে কিনা, SME ঠিক করবেন।
- Brickland-এ "Planning থেকে আসা তারিখ" Recalculate-এ বদলায় না। Planning মডিউল এখনো নেই, তাই এখানে এই নিয়ম নেই।
- "In Progress" (আংশিক সম্পন্ন): কাজের আংশিক অগ্রগতি কোনো মডিউল এখনো দেয় না, তাই সংখ্যাটা ০।


### `06/13` [Reports](06_Merchandise_Desk/13_Reports.md)

- Brickland-এর ১৪টা রিপোর্ট (Sample Production, Budget Sheet, T&A Dept/Style/Buyer, CM Earning, Order wise Budget, Budget vs Actual + Chart, Accessories PR, Textile Division, Import Receive, PO Shipment, Purchase/Finish Fabric Stock, Trims Stock) Preview-তে আটকে থাকে। এগুলোর কলাম এখানে ধরে নেওয়া। SME দেখে কলাম বদলাতে চাইলে বদলানো যাবে।
- "Monthly" রিপোর্টে মাস ধরা হয়েছে শিপমেন্টের মাস দিয়ে। কনফার্মের মাস চাইলে বদলানো যাবে।


## Material Procurement

### `08/03` [Purchase Requisition](08_Material_Procurement/03_Purchase_Requisition.md)

- My Requisitions-এ এখন কোম্পানির সব requisition দেখায় (Brickland-এ admin হিসেবে তা-ই দেখা গেছে)। সাধারণ ব্যবহারকারী শুধু নিজেরগুলো দেখবেন কিনা, তা Brickland-এ অন্য ভূমিকার লগইন দিয়ে দেখা হয়নি।


### `08/05` [Price Review & Approval](08_Material_Procurement/05_Price_Review_Approval.md)

- **Follow-up merchandiser-এর তালিকা:** Brickland-এ শুধু merchandiser-দের নাম দেখায়। এখানে আলাদা merchandiser role নেই, তাই কোম্পানির সক্রিয় সব কর্মী থাকেন (সরবরাহকারীর লগইন বাদে)। ব্যবহারকারী চাইলে পরে বিভাগ বা role দিয়ে সীমিত করা যাবে।


### `08/06` [Approved Material & Follow-Up](08_Material_Procurement/06_Approved_Material_Follow_Up.md)

- **Follow-up merchandiser-এর তালিকা:** এখন অনুমতি-ভিত্তিক (`material.followup.view`), Brickland-এর "Merchandising Desk" role-এর মতো। ব্যবহারকারী পরে চাইলে আলাদা merchandiser role বা বিভাগ দিয়ে সীমিত করা যাবে।


## Store & Material

### `09/01` [Store & Material](09_Store_Material/01_Store_Material.md)

- **সময় অঞ্চল:** GRN-এর তারিখ server-এর দিন ধরে পড়ে। ঢাকায় মধ্যরাতের পরে কাজ করলে আগের দিনের তারিখ আসতে পারে; অন্য মডিউলেও একই আচরণ। app-এর timezone বদলানো হবে কিনা, তা আলাদা সিদ্ধান্ত।
- **দুটো আলাদা হিসাব:** 08/06-এর follow-up-এ পাওয়া পরিমাণ আর Merchandise-এর খসড়া MRR আলাদা হিসাব, store-এর GRN আলাদা। Brickland-এও তাই (PR-2026-00005-এ follow-up ৫০০ দেখালেও store-এ received ০ ছিল)।


### `09/02` [General](09_Store_Material/02_General.md)

_নেই।_


## General Store

### `10/01` [Master Data](10_General_Store/01_Master_Data.md)

- **Department:** স্টোরের ডিপার্টমেন্ট আলাদা তালিকা (`gs_departments`), Brickland-এর মতোই। Company Management-এর ডিপার্টমেন্টের সাথে এক করা হবে কিনা, তা ঠিক হবে HR & Payroll তৈরির সময়।
- **শুরুর ১২টা ডিপার্টমেন্ট:** স্ক্রিনশটে পড়া যায় প্রথম ১০টা (ACC … QA)। শেষ দুটো ধরে নেওয়া হয়েছে SEW (Sewing) আর STORE (Store)।


### `10/02` [General Store](10_General_Store/02_General_Store.md)

- Brickland-এর Dashboard-এর "Today …" tile সংখ্যা না পরিমাণ, ওদের ডাটায় বোঝা যায়নি (সবই 0 ছিল)। এখানে ডকুমেন্টের সংখ্যা।


### `10/03` [Inventory](10_General_Store/03_Inventory.md)

- **নম্বরের prefix:** Brickland-এ OP-, RC-, IS- দেখা গেছে। Return, Adjustment আর Transfer-এর কোনো ডকুমেন্ট ওদের সিস্টেমে নেই, তাই RT-, AD-, TR- ধরে নেওয়া হয়েছে।
- **Other ধরনের Adjustment:** Brickland-এ দিক বোঝা যায়নি। এখানে চিহ্নসহ পরিমাণ নেওয়া হয় (− দিলে কমে)।
- **Return-এর ফেরতযোগ্য সীমা:** "Up to 45"-এর server-বার্তা Brickland-এ দেখা হয়নি (লেখা হয়নি), এখানে নিজেদের লেখা।


### `10/05` [Requisition](10_General_Store/05_Requisition.md)

- Brickland-এ Approved তালিকায় কোনো Cancel নেই, তাই এখানেও অনুমোদিত requisition বাতিল করা যায় না। আংশিক ইস্যুর পরে বাকিটা বন্ধ করার উপায় দরকার হলে পরে যোগ করা যাবে।


### `10/06` [Reports](10_General_Store/06_Reports.md)

- Brickland-এর Print / PDF browser-এর প্রিন্ট না সার্ভারের PDF, বোঝা যায়নি। এখানে সার্ভারের PDF (TablePdf), নতুন ট্যাবে খোলে।


### `10/07` [Administration](10_General_Store/07_Administration.md)

- Brickland-এর Settings পাতা ভাঙা থাকায় ঘরগুলো ওদের এক লাইনের বর্ণনা থেকে বানানো। ওরা ঠিক করলে আবার মিলিয়ে দেখা দরকার।


## Gate Pass

### `11/02` [Gate Pass](11_Gate_Pass/02_Gate_Pass.md)

- Brickland-এর tile "Short Quantity" pass গোনে নাকি পরিমাণ, তা একটা উদাহরণ (দুটোই ১) থেকে বোঝা যায়নি। এখানে কম থাকা pass গোনা হয়।


### `11/03` [General](11_Gate_Pass/03_General.md)

- Brickland-এর Create-এর purpose তালিকায় "Official Work" আর "Personal" দেখা গেছে, অথচ 11/01-এর master প্রোবে ডিফল্ট ১২টা ছিল। এখানে ডিফল্ট ১২টাই; দরকার হলে Purpose / Reason master-এ যোগ করা যায়।
- Brickland কর্মীর ছবি দেখায়; আমাদের HR-এ এখনো ছবি নেই, তাই আইকন দেখানো হয়।


### `11/04` [Dashboard](11_Gate_Pass/04_Dashboard.md)

- Brickland-এর Out / In স্ক্রিনে বারকোড বা QR স্ক্যানার কীবোর্ডের মতো নম্বর লেখে ধরে নেওয়া হয়েছে; ক্যামেরা-স্ক্যান নেই।
- একাধিক ইউনিটের লাইন থাকলে তালিকার QTY-তে শুধু সংখ্যা দেখায় (যেমন "7"), Brickland-এর একক ইউনিটের উদাহরণ ছাড়া মেলানো যায়নি।


## Capacity Planning

### `12/01` [Master Data](12_Capacity_Planning/01_Master_Data.md)

- **Machine, Manpower আর Shift Master:** Brickland-এ এগুলো "Line Master যেভাবে রাখে" তা দেখায়। মানে ডাটা আসে অন্য মডিউলের একটা Line Master থেকে, যেটা এখনো তৈরি হয়নি, আর Brickland-এর demo-তে তালিকাগুলো ফাঁকা। এখানে এগুলোর নিজস্ব তালিকা, New আর Edit সহ, planning line-এর সাথে যুক্ত। Assembly Floor (20) বা Work Study (14) তৈরির সময় দেখা হবে, ডাটা সেখান থেকে আসবে কিনা।


### `12/02` [Production Plan](12_Capacity_Planning/02_Production_Plan.md)

- **সেলাই স্ক্যান:**
  - Brickland-এ লেখা আউটপুট না থাকলে "From scans" দেখায়, আর আসল আউটপুট আসে লাইনের সেলাই স্ক্যান থেকে।
  - এখানে এখনো সেলাই উৎপাদন মডিউল নেই (Garments Production), তাই আউটপুট শুধু দৈনিক প্ল্যানে লেখা সংখ্যা। খালি থাকলে "From scans" দেখায়।
  - Production Line-এর floor line ম্যাপিং আগে থেকেই আছে। সেলাই মডিউল তৈরি হলে স্ক্যান থেকে আউটপুট পড়া যোগ হবে।
- **Planning board:** Line Capacity View-এ Brickland বলে "planning board"-ও পড়া হয়। Brickland-এ আলাদা কোনো planning board স্ক্রিন পাওয়া যায়নি, তাই দৈনিক প্ল্যান আর লক করা মাস্টার প্ল্যান পড়া হয়।


## Fabric Requirement

### `13/01` [Master Data](13_Fabric_Requirement/01_Master_Data.md)

- **একাধিক নিয়ম মিললে কোনটা প্রাধান্য পাবে:** যেমন একটা Solid নিয়ম আর একটা "সব রং" নিয়ম। এটা ঠিক হবে ডাই ব্যাচ প্ল্যান তৈরির সময়। সম্ভবত সবচেয়ে নির্দিষ্টটা জিতবে।


### `13/02` [Operations](13_Fabric_Requirement/02_Operations.md)

- **Ruleset-এর স্কোরিং:** এরিয়া আর ব্যান্ড শুধু সংজ্ঞা। কোন ব্যাচ প্ল্যানের কোন এরিয়ায় কত স্কোর, সেই হিসাবের স্ক্রিন Brickland-এ পাওয়া যায়নি। Dye House-এর ব্যাচ অংশ বা Reports (13/03) তৈরির সময় দেখা হবে।
- **ছুটি আর ক্যাপাসিটি:** Brickland বলে "ক্যাপাসিটি বোর্ড বন্ধ দিনগুলো সপ্তাহ থেকে বাদ দেয়", কিন্তু ফর্মে HoliDays ঘরটা হাতে লিখতে হয়। এখানেও হাতে লেখা। চাইলে পরে Knitting & Dyeing সেকশনের বন্ধ দিন থেকে ডিফল্ট বসানো যায়।


### `13/03` [Reports](13_Fabric_Requirement/03_Reports.md)

- **Export to Excel? ঘর:** Brickland-এ run-এর এই ঘর সেভ হয়, কিন্তু কোথাও ফাইল তৈরি হতে দেখা যায়নি। এখানেও এটা run-এর তথ্য হিসেবে থাকে। Excel আসে Preview-এর Export থেকে।


## Work Study

### `14/01` [Master Data](14_Work_Study/01_Master_Data.md)

- **গার্মেন্টস আইটেম কোথায় থাকবে:** Garments Item Specification (14/04) বানানোর সময় `ws_garment_items` সেখানে সরবে, নাকি সেটাই এই টেবিল ব্যবহার করবে, তখন ঠিক হবে।


### `14/02` [Reports](14_Work_Study/02_Reports.md)

- **রিপোর্টের কলাম:** Brickland-এর demo কোনো রিপোর্ট দেখায় না, তাই কলাম রিপোর্টের নাম আর আমাদের শিটের ডাটা থেকে ঠিক করা হয়েছে। Brickland-এর আসল প্রিন্ট পাওয়া গেলে মিলিয়ে নেওয়া উচিত।
- **NPT-এর Gmt. Section আর Fin Teams:** Brickland-এর NPT রিপোর্ট এ দুটো দিয়েও ছাঁকে। আমাদের NPT-তে সেকশন বা ফিনিশিং টিম রাখা হয় না, তাই এ দুটো দেখানো হয়নি।
- **Qty Pluck Type আর Day#:** শুধু অপেক্ষার রিপোর্টে আছে। সেগুলো তৈরি হলে কাজে লাগবে।
- **Rows:** Brickland-এর run সবসময় ১ দেখায়। এখানে আসল সারির সংখ্যা রাখা হয়।
- **বাকি ১৯টা রিপোর্ট:** Garments Production, HR হাজিরা, Assembly Floor-এর লেআউট, Capacity booking, Printing/Embroidery আর Finance-এর প্রতি-মিনিট খরচ তৈরি হলে এখানে যোগ হবে।


### `14/03` [Target & Feeding](14_Work_Study/03_Target_Feeding.md)

- **"car" আর "D'R" কলাম:** Brickland-এর শিটে "car" একটা টিক-ঘর আর "D'R" একটা সংখ্যা, কোনো ব্যাখ্যা নেই। Brickland-এর নামেই রাখা হয়েছে (`is_car`, `day_run`)। D'R ধরা হয়েছে লাইনে স্টাইল চলার দিন, Start Copy-তে এক বাড়ে। এটা অনুমান।
- **Feeding Start/End:** Brickland-এর demo-তে দুটোই "—" ছিল। এখানে টার্গেট শিট থেকে বের করা হয় (প্রথম যেদিন শিটে এল, প্রথম যেদিন উৎপাদন হলো)। এটা অনুমান।
- **Production Entry আর টার্গেট শিট:** Brickland বলে "In Trgt? is what puts a plan on the day's target sheet"। এখানে In Trgt? চিহ্ন রাখা হয়, কিন্তু টার্গেট শিটে নিজে থেকে সারি বসায় না। demo-তে এর প্রভাব দেখা যায়নি।
- **Excess Yarn-এর ব্যবহার:** এই টার্গেট কোন রিপোর্ট বা খরচ ফেরত ধরায় ব্যবহার হয়, তা পরের মডিউলে (Knitting/Yarn) দেখা যাবে।
- **Lock-এর সীমা:** টেবিল বাড়তে বাড়তে টেস্টের `migrate:fresh` Postgres-এর `max_locks_per_transaction` (৬৪) ছাড়িয়েছে। dev Postgres-এ এটা ৫১২ করা হয়েছে (`ALTER SYSTEM`)।


### `14/04` [Item Specification](14_Work_Study/04_Item_Specification.md)

- **OB ফাইলের ধরন:** মীমাংসিত (২০২৬-১০-২৫)। প্ল্যাটফর্মের অনুমোদিত তালিকায় Excel (XLSX, XLS) যোগ হয়েছে, তাই OB ফাইল এখন Excel-এও যুক্ত করা যায়।
- **GMT Item List-এর কলাম:** Brickland-এর demo-তে Booking Month-এর তালিকা খালি, তাই আসল কলাম দেখা যায়নি। এখানে কলাম ঠিক করা হয়েছে অর্ডার, আইটেম, স্পেসিফিকেশন আর CM-এর নোট থেকে। CM/Dz আর BgtExp% এখন 14/05-এর Spec-Wise CM Rate থেকে আসে।
- **ক্রমিক:** Brickland-এ এই দুটো স্ক্রিন "10 of 22" আর "21 of 22", যা ফোল্ডারের ক্রম মানে না। Work Study-র বাকি গ্রুপ তৈরি হলে মেলানো হবে।


### `14/05` [Costing](14_Work_Study/05_Costing.md)

- **বেতন আর OT রেট (14/06):** মীমাংসিত (২০২৬-১০-২৬)। `WsCosting::salaryRate()` এখন Monthly Salary & OT Rates থেকে পড়ে, আর Sal[g] ও OTBill Brickland-এর সূত্রে পূরণ হয়।
- **টার্গেট শিট (14/03):** মীমাংসিত (২০২৬-১০-২৭)। Daily Line Cost এখন Garment Target Setting-এর শিট থেকে অর্ডার আর OP/ASO/WH/Target নেয়। সেদিন টার্গেট না থাকলে 12/02-এর লাইন প্ল্যান থেকে।
- **কোন কলামে গার্মেন্টের ওভারহেড:** Brickland-এর probe-এ দিনের ওভারহেডে Cutting আর Sewing দুটো কলামই গোনা হয়েছে। Finishing বা Packing ফ্লোরের জন্য আলাদা নিয়ম আছে কিনা তা demo থেকে বোঝা যায়নি।


### `14/06` [OT & Salary](14_Work_Study/06_Ot_Salary.md)

- **২৬ দিন:** Brickland বেতনকে ২৬ দিনে ভাগ করে (probe-এর সংখ্যা তাই বলে)। এটা নির্দিষ্ট নাকি OH শিটের কর্মদিবস থেকে আসে, demo-তে দুটোই ২৬ ছিল বলে আলাদা করা যায়নি। এখানে নির্দিষ্ট ২৬ (`WsCosting::SALARY_DAYS`)।
- **OT Plan-এর ব্যবহার:** Brickland-এ OT Plan লাইন কস্টিংয়ের ManOT পূরণ করে না, আলাদা থাকে। এখানেও তাই।
- **লাইন:** HR কর্মীর লাইন machinery-র `production_lines`। প্ল্যানের লাইন সেলাইয়ের `planning_lines`। দুটো যুক্ত হয় `planning_lines.floor_line_id` দিয়ে। dev-এ এই সংযোগ নেই, তাই HR গ্রুপের লাইন ফাঁকা দেখায়।


### `14/07` [Floor Operations](14_Work_Study/07_Floor_Operations.md)

- **Recorded কোথা থেকে আসবে:** ঘণ্টাভিত্তিক সেলাই উৎপাদন এখনো কোনো মডিউলে রেকর্ড হয় না, তাই Recorded আপাতত ০ (Brickland-এর demo-তেও ০ ছিল)। Garments Production-এর Sewing Output তৈরি হলে `WsFloorOps::recorded()` সেখান থেকে পড়বে।
- **New production adjustment-এর Hour:** Brickland-এর ফর্মে Hour একটা লেখার ঘর ("10AM"), কিন্তু শিটে ১–১১ বাছাই। এখানে ফর্মেও ১–১১ বাছাই রাখা হয়েছে, যাতে ফর্মে লেখা সংশোধন শিটে দেখা যায়।
- **Take Action-এর লেবেল:** Brickland দেখায় "39-Create Draft", "39-Verify & Submit Requisition" ইত্যাদি। "39-" Brickland-এর ভেতরের কোনো কোড মনে হয়, তাই বাদ দেওয়া হয়েছে।
- **Complete রিকুইজিশন:** Brickland-এর নোট বলে সম্পূর্ণ রিকুইজিশনে অ্যাকশন নেই, কিন্তু demo-তে select তখনো দেখা যায়। এখানে নোট অনুযায়ী করা হয়েছে। বাতিল রিকুইজিশনে অ্যাকশন খোলা থাকে।
- **কস্টিংয়ে প্রভাব:** সংশোধিত আউটপুট আর অন্য কাজে যাওয়া man-minute 14/05-এর Daily Line Cost বা efficiency-তে কাটা হবে কিনা, তা Brickland-এ দেখা যায়নি। আপাতত আলাদা রাখা হয়েছে। রিপোর্ট (14/02) বানানোর সময় দেখা হবে।


### `14/08` [Section Profitability](14_Work_Study/08_Section_Profitability.md)

- **OH Calculated কোথা থেকে আসবে:** Brickland-এর শিটে এটা শুধু দেখায় (demo-তে ০), New ফর্মে লেখা যায়। এখানেও তাই। 14/05-এর OH Cost Allocation থেকে সেকশনভিত্তিক হিসাব করে বসানো উচিত কিনা, Brickland-এ দেখা যায়নি।
- **"[BDT=]" Sub-Section:** Brickland নতুন সারিতে "Cutting [BDT=]" বসায়। এর মানে স্পষ্ট নয় (সম্ভবত টাকার অঙ্ক লেখার জায়গা)। হুবহু রাখা হয়েছে।
- **Records-এর Selection প্যানেল:** Brickland-এর Setup-এর Records ট্যাবে কোম্পানি/বছর/মাস/Copy From আর Show/Update/Excel থাকে। এখানে Records সাধারণ গ্রিড (খোঁজা আর Export সহ)। মাস ধরে দেখা আর Update Income Target Config ট্যাবে আছে, তাই একই জিনিস দুই জায়গায় রাখা হয়নি।
- **আয় আর খরচের উৎস:** Income, OH, Mat Cost আর Expense এখন হাতে লেখা হয় (Brickland-এর মতো)। পরে Accounting আর Store মডিউল থেকে আসবে কিনা, রিপোর্ট (14/02) বানানোর সময় দেখা হবে।


## Knit Production

### `15/01` [Master Data](15_Knit_Production/01_Master_Data.md)

- **অপারেটর:** HR & Payroll বানানোর পরে অপারেটর কর্মীর তালিকায় সরবে (এখন কোম্পানির ইউজার, Emp. Code আসে ইউজারের employee code থেকে)।
- **অফিস:** Brickland-এর "Company & Office" তালিকা ডেমোতে খালি ছিল, তাই কলামগুলো দেখা যায়নি। এখানে অফিস মানে Company Management-এর ফ্যাক্টরি।


### `15/02` [Yarn Procurement](15_Knit_Production/02_Yarn_Procurement.md)

- **Yarn Item-এর master:** Brickland-এর demo-তে Yarn Item তালিকা খালি, আর কোনো yarn item master স্ক্রিনও নেই। এখানে Merchandise-এর Fiber Composition ব্যবহার করা হয়েছে। আলাদা yarn item master দরকার হলে বলবেন।
- **Category:** Brickland-এর Indent-এ "Category" (store item category, যেমন Yarn) আছে; এটা শুধু Yarn Item তালিকা ছাঁকে। এখানে Yarn Item সবসময় সুতা, তাই রাখা হয়নি।
- **Brand:** Brickland-এ খালি select; কোথাও brand-এর master নেই। এখানে লেখার ঘর।
- **Present Stock:** Yarn Store (15/03) তৈরি হলে সেখান থেকে আসবে। এখন ০, কিন্তু CS-এ হাতে বদলানো যায়।
- **Received:** PO-র received কলাম আর Part Received/Received অবস্থা 15/03-এর Daily Yarn Receipt মিলিয়ে রাখবে।
- **Brickland-এ ডাটা দিয়ে প্রবাহ চালানো যায়নি:** demo-তে yarn item না থাকায় Indent সেভ করা যায়নি। তাই approval, CS আর PO-র ধারা Brickland-এর স্ক্রিনের নোট (যেমন "Submitting it from the register…", "Finalising it — from the list…", "Placing it from the list…") আর কলাম দেখে বানানো হয়েছে।
- **List-এর কলাম:** Brickland-এ কোনো রেকর্ড না থাকায় register-এর কলাম দেখা যায়নি। কলাম ঠিক করা হয়েছে ফর্মের ফিল্ড থেকে।


### `15/03` [Yarn Store](15_Knit_Production/03_Yarn_Store.md)

- **Receive By PI:** Commercial-এর PI তৈরি না হওয়া পর্যন্ত আটকানো। মেসেজ: "No proforma invoice is on record yet — receive by PO or direct."।
- **Opening Entry-র কিছু filter:** Brickland-এর Spin Type, Cotton Type, Slub?, Mellange?, Solid Yarn? আর Y/D Yarn? রাখা হয়নি। আমাদের Yarn Count master-এ এই বৈশিষ্ট্যগুলো নেই। লাগলে count master-এ যোগ করতে হবে।
- **Transfer:** Rack Pending-এর সূত্রে Brickland "Trns Recv. − Trns Sent" ধরে। store transfer তৈরি হলে যোগ হবে।
- **Brickland-এ ডাটা দিয়ে প্রবাহ চালানো যায়নি:** demo-তে Yarn Item নেই, তাই কোনো রিসিট বা OB সেভ করা যায়নি। Return, Exchange, desk check আর month close-এর ধারা বানানো হয়েছে Brickland-এর স্ক্রিন নোট থেকে ("A challan is raised from the register's Return or Exchange button…", "The four desk checks — store, audit and accounts — are taken on the register…", "Show Exception lists what would stop the close…")।
- **Requisition-এর "Job/W.Style" আর "Order":** Brickland-এ দুটো আলাদা select, দুটোই অর্ডার বোঝায়। এখানে একটাই, Job/W.Style (অর্ডারের "style · order#" লেবেলসহ)।
- **Against Claim:** claim আসে Yarn Test থেকে (এখনো তৈরি হয়নি)। তাই select-এ শুধু "-- No claim --"।
- **Issue / Party / Replacement-এর লাইন:**
  - Brickland-এ Yarn Item, Brand আর Lot # আলাদা ঘরে লেখা হয়।
  - এখানে store-এর লট (মজুদ লাইন) বাছা হয়, তাতে yarn, brand, lot আর মজুদ একসাথে দেখায়। খাতা লট ধরেই চলে।
- **Cat. ফিল্ড:** Brickland-এর MRR আর OB-তে "Cat." / "Category" (store item category) আছে। এখানে আইটেম সবসময় সুতা, তাই রাখা হয়নি।
- **S/C Yarn Receipt-এর তালিকা:** Brickland-এর S/C Program List-এ কোন প্রোগ্রাম আসে দেখা যায়নি (ফাঁকা)। এখানে Confirmed / In Production রিসিপ্ট, যার সুতার Required পুরো পাওয়া হয়নি। যে রিসিপ্টে সুতার লাইন নেই, সেটা তালিকায় আসে না।
- **Dyed receipt-এর Confirm কোথায়:** Brickland বলে "Confirm it on the Y/D Program List"; সেই ট্যাবের তালিকায় তাই প্রাপ্তিগুলো, সারিতে Confirm। Brickland-এ তালিকা ফাঁকা থাকায় কলাম দেখা যায়নি; এখানে প্রাপ্তির ফিল্ড থেকে বানানো।
- **Loss memo-র Issued Qty:** স্টোরের ইস্যু শেড ধরে হয় না (রিকুইজিশনের লাইনে শেড নেই), তাই শুরুতে প্রোগ্রামের Issue শেডের Qty অনুপাতে ভাগ করে বসে; হাতে বদলানো যায়।
- **Statement-এর Category:** Brickland-এর "-- Select Type --" আসলে store item category-র তালিকা (Yarn, Fabric, Button…)। এখানে সব সুতা, তাই রাখা হয়নি।


### `15/04` [Knit Production](15_Knit_Production/04_Knit_Production.md)

- **Grade-এর সীমা:** Brickland-এর দুটো বিন্দু দেখা গেছে (৩১.৩৯ → B, ৫৫.৮১ → C)। A ≤ ২০, B ≤ ৪০, C ≤ ৬০, D > ৬০ ধরা হয়েছে, যা দুটোর সাথেই মেলে। Brickland-এ পুরনো শিট খোলার সময় তাদের পেজ ভেঙে যাওয়ায় সীমা আর যাচাই করা যায়নি।
- **Defect-এর গ্রুপ:** Brickland-এর প্রিন্টে শুধু Hole/Crack-এর গ্রুপ (Structural) দেখা গেছে। বাকিগুলোর গ্রুপ (Yarn, Machine, Stain) আমাদের ধারণা।
- **"To Hole" বোতাম:** Brickland-এর ইন্সপেকশন তালিকায় Pass, Hold, Reject-এর পাশে "To Hole" নামে একটা বোতাম আছে। নামটা অর্থহীন (সম্ভবত "To Hold"-এর ভুল), আর Hold আলাদাই আছে, তাই রাখা হয়নি।
- **Job#/W.Style আর Style:** Brickland-এর ফর্মে Style আলাদা select। এখানে অর্ডার থেকে Style আর Buyer নিজে থেকে দেখায়।
- **Label Info:** Brickland-এর Roll List-এ কলামটা আছে, কিন্তু কোথাও লেখার ঘর নেই। এখানে কলাম রাখা আছে ("—")।
- **Shift:** Brickland রোল আর ইন্সপেকশনে HR work schedule দেখায়। এখানে 15/01-এর capacity plan-এর `shifts` নেওয়া হয়েছে, যাতে Daily Output-এর Target শিফট ধরে মেলে।
- **S/C নম্বর:** Brickland প্রোগ্রামে "SCP-26100001" (YYMM) আর রিসিপ্টে "SCR-2026100001" (YYYYMM) লেখে। এখানে দুটোই আমাদের নিয়মে PREFIX-YYYYMM####।
- **S/C-তে Cancelled:** Brickland-এর প্রোগ্রাম রেজিস্টারের Status filter-এ Cancelled আছে, কিন্তু কোনো বোতাম নেই। এখানেও তাই: অবস্থা আছে, বোতাম নেই।
- **IsTrnsf? আর Received:** সুতা ট্রান্সফার আর S/C রিসিভ ডেস্ক (15/05) এখনো নেই, তাই IsTrnsf? সবসময় No আর Received ০।
- **Receipt-এর Yarn:** Brickland-এর Yarn আর Brand select শুরুতে বন্ধ থাকে। এখানে Yarn Store বাছলে সেই স্টোরের লট থেকে Yarn (composition + count) আর Brand আসে; Lot# হাতে লেখা। Fab. Ord. SL Brickland-এ সংখ্যার ঘর, এখানে grid-এ থাকা সিরিয়াল থেকে select, যাতে ভুল সিরিয়ালে সুতা না বসে।
- **Yarn test-এ Brickland-এর demo ফাঁকা:** Brickland-এ Yarn Item select সবসময় বন্ধ আর খালি (তাদের yarn item তালিকা ভরা হয়নি), তাই সেখানে কোনো test order তোলা যায়নি; বাকি তিন স্ক্রিনেও ডাটা নেই। ফর্ম, লেবেল আর লেখা Brickland থেকে হুবহু; Yarn Item আর Brand এখানে স্টোরের লট থেকে।
- **Test order-এর বোতাম:** Brickland-এর তালিকায় কোনো সারি না থাকায় বোতাম দেখা যায়নি। তাদের লেখা ("The store issues against it from the register", "The register's Knit Card # button prints it") থেকে Knit Card #, Issue আর Cancel রাখা হয়েছে। Issue শুধু অবস্থা বদলায় — সুতা আসলে বের হয় 15/03-এর Store Requisition (Req. Type Yarn Test) দিয়ে।
- **Register-এর কলাম আর Basis:** Brickland-এ সারি না থাকায় কলাম দেখা যায়নি; এখানে লাইনের ফিল্ড থেকে বানানো। Brickland বলে "The Basis column says which" — Basis কলাম রাখা হয়েছে।
- **Waiver-এর ফলাফল:** টেস্ট ছাড়া ছাড় "Passed" হিসেবে রাখা হয়েছে, যাতে Status filter (Pending, Passed, Failed, Conditional)-এ মেলে।
- **ফ্লোরের আর স্টোরের একই কাগজ:** Brickland-এ General Yarn Issue Indent আর Issue Against Requisition-এর (এবং দুটো Return স্ক্রিনের) তালিকা ফাঁকা, তাই একই টেবিল কিনা দেখা যায়নি। ফিল্ড আর অবস্থা হুবহু এক, আর স্টোর রিকুইজিশনের বিপরীতেই ইস্যু করে — তাই এখানে এক কাগজ, দুই দিক থেকে। Brickland-এর General Indent-এর খালি-Save মেসেজ ("A requisition with no yarn on it asks for nothing.") আলাদা, এখানে 15/03-এর মেসেজই আসে।
- **General Indent-এর Status:** Brickland-এ Status* select আছে। অবস্থা স্টোরের ইস্যু থেকে হিসাব হয়, তাই এখানে শুধু দেখায়; হাতে নেওয়া সিদ্ধান্ত Closed টিক।
- **তালিকার বোতাম:** তিন তালিকাই Brickland-এ ফাঁকা, বোতাম দেখা যায়নি। General Indent-এ Close আর Cancel (15/03-এর মতো, Issue বাদে); Replacement-এ Submit, Issue আর Cancel — Brickland-এর লেখা "Submit and Issue are on the register" থেকে।
- **Sample plan-এর Registered Yarn:** Brickland-এ এই select বন্ধ আর খালি, তাই তাদের demo-তে Submit Requisition কাজ করে না (কোনো মেসেজ ছাড়াই Draft থাকে)। এখানে নিবন্ধিত সুতা স্টোরের লট থেকে, আর জমা হলে সত্যিই 15/03-এর রিকুইজিশন ওঠে। Issued আর Closed রিকুইজিশন থেকে পড়া — কারণ "Any requisition already raised against it stays with the store and has to be closed there"।
- **Direct Booking-এর Buyer A/C:** Brickland-এ ঘরটা বন্ধ আর খালি (তবু * চিহ্ন), তাদের demo-তে বায়ার ছাড়াই সেভ হয়। এখানে Buyer A/C সত্যিই আবশ্যক, অর্ডার বাছলে নিজে থেকে ভরে, আর অর্ডার সেই বায়ারের হতে হবে।
- **Direct Booking-এর Submit:** Brickland-এ Submit জিজ্ঞেস করে না, Approve করে — হুবহু রাখা হয়েছে।
- **Yarn Twisting-এ Brickland-এর demo ফাঁকা:** Yarn select সবসময় বন্ধ, তাই কোনো প্রোগ্রাম সেভ হয় না; তালিকার বোতাম দেখা যায়নি। এখানে প্রোগ্রামের তালিকায় শুধু Print (খোলা হয় নম্বরে ক্লিক করে); অবস্থা ফর্মে বাছা হয়, Brickland-এর ফর্মের মতো।
- **Transfer? আর স্টক:** "The twisted yarn has been taken onto the store's own stock" — এখানে আপাতত শুধু চিহ্ন, স্টকে পোস্ট হয় না। প্রোগ্রামের সুতা স্টোরের নির্দিষ্ট লট থেকে বেরোয় না (লাইনে শুধু Lot # লেখা), তাই ফেরত টুইস্ট সুতা যোগ করলে স্টক দুবার গোনা হতো। সিঙ্গেল সুতা বের হওয়া আর টুইস্ট সুতা ঢোকা দুটোই স্টকে আনতে হলে প্রোগ্রাম লাইনকে লটের সাথে জুড়তে হবে — ব্যবহারকারীর সিদ্ধান্ত লাগবে।
- **গ্রহণের সীমা:** Brickland বলে "what the party was sent is what they were sent"; এখানে কোনো প্রোগ্রাম লাইনের বিপরীতে মোট Grey পাঠানোর বেশি হতে পারে না, আর Receive Grey-এর বেশি নয়।
- **Machine Loading আর Operator-এ Brickland-এর demo ফাঁকা:** কোনো স্টেশন বা পরিকল্পিত শিফট নেই, তাই গ্রিড বা স্টেশনের ভেতর দেখা যায়নি। লেবেল আর লেখা Brickland থেকে; গ্রিড আর dialog তাদের লেখা ("Click a cell to plan it, or clear it…", "one card per shift") থেকে বানানো।
- **শিফট কলাম:** গ্রিডে কোম্পানির সব শিফট কলাম হয়। dev-এ দুটো "Day shift" আছে (দুই ফ্যাক্টরির), তাই দুটো DAY কলাম দেখায়; ফ্লোর বা ফ্যাক্টরি ধরে শিফট ছাঁকা লাগলে পরে যোগ করা যায়।
- **Grey Fabric Racking Board:** Brickland-এর স্ক্রিনে সুতার racking-এর filter (Count, Composition, Spin/Cotton Type, Brand, Lot) আর cones-per-kg-এর লেখা। এখানে filter হুবহু, কিন্তু মেলানো হয় রোলের নিট কার্ডের yarn details লেখার সাথে (গ্রে রোলের নিজের কোনো সুতা-ফিল্ড নেই)। রোল র‍্যাকে যায় Store Receipt-এ; অন্য স্টোরে গেলে র‍্যাক থাকে না, সেখানে র‍্যাক বসানোর স্ক্রিন Brickland-এ দেখা যায়নি।
- **To Batch:** Brickland-এ ডাইং মডিউলের চলমান ব্যাচ থেকে select ("Only batches that have not finished are offered")। ডাইং মডিউল এখনো নেই, তাই এখানে ব্যাচ নম্বর লেখার ঘর; ব্যাচের লাইন একই স্টোরে ব্যাচ নম্বরসহ আলাদা। ডাইং তৈরি হলে select-এ বদলাবে।
- **Collar/Cuff-এর রোল:** Brickland-এর demo-তে picker-এ সাধারণ নিটিং রোলও আসে, তাই এখানেও From Order-এর ফ্লোরের যেকোনো রোল (শুধু Flat Bed RIB নয়)।
- **Collar/Cuff-এর অর্ডারের লেবেল:** Brickland-এর তালিকার কলাম দেখা যায়নি (ফাঁকা); এখানে ফর্মের ঘর থেকে।
- **Y/D প্রোগ্রাম কোথায় তৈরি হয়:** Brickland-এ তৈরির স্ক্রিন নেই (Register শুধু revision রাখে, আর Party Y/D S/C programme Y/D তালিকায় আসে না)। ব্যবহারকারী Register-এই তৈরি বেছে নিয়েছেন (২০২৬-১১-০১)। Brickland Party (Y/D) S/C programme-এ "SCP-YD-…" উপসর্গ দেয়; আমাদের S/C programme সব ধরনেই SCP-…। পরে মেলানো যেতে পারে।
- **Brickland probe:** ২৯ অক্টোবর কিছুক্ষণ এই কম্পিউটারের browser থেকে Brickland-এ connection reset আসছিল, পরে নিজে থেকে ঠিক হয়ে যায়। Brickland demo-তে probe-এর জন্য একটা Bulk order, KP-2026100001, KC-2026100001, দুটো রোল আর দুটো ইন্সপেকশন (QCI-2026100001/2) তৈরি করা হয়েছে।


### `15/05` [Fabric Store](15_Knit_Production/05_Fabric_Store.md)

- **Fabric Receive-এর Select টিক:** Brickland-এ টিকের সংখ্যা দেখায় ("0 ticked"), কিন্তু টিক দিয়ে কোন বোতাম চলে তা দেখা যায়নি (তালিকা ফাঁকা)। এখানে টিক আর সংখ্যা আছে; Withdraw সারি ধরে, Confirm day পুরো দিন।
- **Confirm day বোতাম:** Brickland-এর লেখায় "Confirming closes the day's receipt", কিন্তু ফাঁকা দিনে বোতামটা দেখা যায়নি। এখানে দিনে রোল থাকলে Clear day-এর পাশে দেখায়।
- **S/C In-house opening-এর পার্টি:** Brickland-এর ফর্মে পার্টির ঘর নেই, তাই এখানেও নেই (খাতায় party_id ফাঁকা)। S/C challan বানানোর সময় দরকার হলে যোগ হবে।
- **Rejected রোল:** গ্রে স্টোরে নেওয়া হয় না (Brickland-এ যাচাই করা যায়নি)। Pending (ইন্সপেকশন হয়নি) আর Hold রোল নেওয়া যায়।
- **In-House Return-এর "Against Rcv#":** Brickland বলে "The receive challan this fabric came in on"; ইন-হাউস ব্যবস্থায় এখানে পার্টির S/C প্রোগ্রাম রিসিপ্ট (SCR / পার্টির নম্বর)। Brickland-এ তালিকা ফাঁকা থাকায় যাচাই করা যায়নি।
- **"New receive/return/delivery challan" ট্যাব:** Brickland-এ তৃতীয় একটা ট্যাব আছে, শুধু লেখা "Challans are raised on the entry tab…" আর Reset/Save। ফর্ম আগেই আছে বলে এই ফাঁকা ট্যাব রাখা হয়নি।
- **Delivery-র একটাই স্টোর:** Brickland-এর ফর্মে Store* একটাই, তাই এক চালানে এক স্টোরের গ্রে আর সেই স্টোরেই রাখা পার্টির সুতা। সুতা আলাদা yarn store-এ থাকলে আলাদা চালান লাগবে।
- **কলার/কাফ পিসে:** খাতা একক রাখে না; কলার/কাফের লাইন (kind COLLAR_CUFF) পিসে, ফেব্রিক কেজিতে। Opening-এ কলার/কাফ কেজিতে লেখা হলে একই খাতায় মিশে যেতে পারে — একক আলাদা রাখা লাগলে লাইনে uom যোগ করতে হবে।
- **Correction-এর রং:** Brickland-এ Color select; এখানে লেখার ঘর, রঙের master-এর নাম suggestion হিসেবে (datalist) — কারণ খাতায় opening-এর "Color (as written)"-ও থাকে।

- **সুতার কাগজের ধাপ:** Brickland-এর লেখায় শুধু "Finalising it on the list" আর "Saving writes a draft" আছে। Status-এর অপশন Draft / Issued / Finalized / Cancelled। তাই এখানে Issue = মাল গেট পার হওয়া (খাতা নড়ে), আর Finalize = দুই পক্ষের স্বাক্ষর। Dyed receipt-এ খাতা নড়ে Finalize-এ ("the store signing for the count")। Brickland-এর তালিকা ফাঁকা বলে সারির বোতাম দেখা যায়নি।
- **Programme transfer-এর "New programme transfer" ট্যাব:** Brickland-এ তৃতীয় একটা ফাঁকা ট্যাব আছে, যা S/C চালানের মতোই রাখা হয়নি।
- **Programme transfer-এর সুতা:** লাইনগুলো শুধু কাগজের তথ্য, খাতা নড়ে না। পার্টির হাতে থাকা সুতার হিসাব (S/C খাতা) পার্টি ধরে রাখা হলে, পরে সেটাও নতুন পার্টিতে সরানো লাগতে পারে।
- **Y/D প্রোগ্রামের শেডে "বাকি":** প্রোগ্রামের মোট Issue শেডের পরিমাণের অনুপাতে ভাগ করা হয় (`issued_share`), কারণ requisition-এর ইস্যু শেড ধরে রাখা হয় না।

- **ইন-হাউস bill-এর প্রোগ্রাম:** Brickland বলে "a party working inside the mill … charge each line to the programme it was knitted under"। এখানে ইন-হাউস S/C ব্যবস্থার প্রোগ্রাম মানে পার্টির S/C programme receipt (SCR / পার্টির নম্বর)। এগুলোর জন্য "উৎপাদনের সাথে মিলিয়ে দেখা" এখনো শুধু billed বনাম planned; উৎপাদিত পরিমাণের সাথে মেলানো রিপোর্টের কাজ।
- **Debit memo আর bill-এর যোগ:** Brickland-এর Deduction-এর লেখা "Debit memos and agreed deductions taken off the gross"। এখানে Deduction হাতে লেখা অঙ্ক; কোন মেমো কোন বিলে কাটা হলো তার যোগ নেই। **Settle** হাতে চাপা হয় — Brickland-এ Settled অবস্থা আছে, কিন্তু সারির বোতাম দেখা যায়নি (তালিকা ফাঁকা)।
- **Debit memo-র Part:** Brickland-এর অপশন দেখা যায়নি; এখানে Fabric, Collar, Cuff, Yarn।
- **Revision কোন বিলের:** শুধু Submitted বিলের (Approved = পেমেন্টযোগ্য, তাই সংশোধন নয়)।
- **তৃতীয় "New … bill" ট্যাব:** Brickland-এ শুধু লেখা আর Reset/Save, আগের মতোই রাখা হয়নি।


### `15/06` [Collar/Cuff](15_Knit_Production/06_Collar_Cuff.md)

১. **Yarn issue কোথা থেকে বিয়োগ হবে:** Brickland-এ "store যা ছেড়েছে তা টাইপ করুন" লেখা, কিন্তু কোন স্টক লাইন থেকে সেটা বলা নেই। এখানে প্রতিটা release indent-এর স্টোরের একটা মিলে যাওয়া স্টক লাইন থেকে yarn ledger-এ বিয়োগ হয় (`CC_YARN_ISSUE`), যাতে স্টক সত্যি থাকে।

২. **Store Receipt আর স্টক:** গ্রহণ করা চালান কোনো stock ledger-এ (যেমন grey store) পিস তোলে না। শুধু requirement-এর delivery আর প্রোগ্রামের received-এ গোনা হয়। কলার/কাফ স্টক রিপোর্ট চাইলে চালান থেকে হিসাব হবে।

৩. **Develop requirement কোন স্ক্রিনে:** স্যাম্পল স্ক্রিনে রাখা হয়েছে, বাল্কে নয়। Brickland-এ পরিষ্কার নয়।

৪. **Production লাইনের Status কলাম:** Brickland-এ এই কলামে উৎসের ব্যাজ (In-house / S/C) দেখায়, এখানেও তাই রাখা হয়েছে।

৫. **বন্ধ requirement আবার খোলা:** Brickland-এ বন্ধ সারির বোতাম দেখা যায়নি, তাই Re-open রাখা হয়নি।

৬. **Indent তালিকার বোতাম:** Brickland-এ তালিকা খালি ছিল, তাই Submit / Approve / Close / Cancel / Print অনুমান করে রাখা হয়েছে। Send Back রাখা হয়নি।

৭. **Requisition Type:** yarn store-এর মাস্টার তালিকা থেকে আসে। "Collar and/or Cuff (Bulk)" ছাড়া আলাদা ওয়াই/ডি ধরন সেখানে নেই।


### `15/07` [Needle Store](15_Knit_Production/07_Needle_Store.md)

১. **Item মাস্টার:** General Store-এর item, category আর store-ই ব্যবহার করেছি। needle store-এর আলাদা মাস্টার নেই। Brickland-এর demo-তে needle item ছিল না, তাই dev-এ নমুনা item যোগ করেছি।

২. **PR / PO বাদ:** রিসিপ্ট ফর্মে PR আর PO রাখিনি। Brickland-এ তালিকা খালি ছিল, আর needle কেনার কোনো সোর্স মডিউল এখনো নেই।

৩. **Issue-র স্টোরের দিক:** Brickland-এর ব্যাখ্যা অনুযায়ী From Store হলো অনুরোধকারী (সাব-স্টোর), To Store হলো যে ছাড়ে (মেইন)। তাই ইস্যু To Store থেকে বিয়োগ হয়, From Store-এ যোগ হয়। দুটো একই হলে শুধু বিয়োগ হয়।

৪. **Issue List-এ কী দেখায়:** এখানে ইস্যুর কাগজগুলো দেখায় (প্রতিটার Withdraw আছে)। Brickland-এ তালিকা খালি ছিল, আর সেখানকার ব্যাখ্যায় যাচাই হওয়া রিকুইজিশন আর "Mark as Closed"-এর কথা আছে। রিকুইজিশন বন্ধ করা হয় indent তালিকা থেকে।

৫. **Loan opening স্টকে যায় না:** এটা শুধু রেজিস্টার। স্টকের ওপেনিং আলাদা স্ক্রিনে (Needle Opening Balance)। লোন ফেরত বা লোন রিসিভ 15/08 আর 15/09-এ আসবে।

৬. **Loan Issue Qty-র post তারিখ:** সেভ করার দিনের তারিখে post হয়। পুরো লাইনের post প্রতিবার নতুন করে লেখা হয়।

৭. **Receipt-এর ধাপ:** অবস্থাগুলো Brickland-এর ফিল্টার থেকে নেওয়া। তালিকার বোতাম (Submit, Verify, Send to Accounts, Accounts Approve, Cancel, Raise Return, Print) অনুমান করে রাখা, কারণ Brickland-এ তালিকা খালি ছিল।

৮. **Return নম্বর:** Brickland `MRR-ND-…/R01` লেখে। এখানে `NMR-…-R01`, কারণ নম্বর URL-এ যায় আর `/` সেখানে চলে না।


### `15/08` [Loan Issue to Party](15_Knit_Production/08_Loan_Issue_To_Party.md)

১. **দুই খাতার ledger:** নিডল আর তেলের জন্য আলাদা টেবিল না বানিয়ে একই ledger-এ `book` কলাম দিয়েছি। টেবিলের নাম এখনো `knit_needle_*`। ক্লোজিং খাতা ধরে আলাদা।

২. **তেলের স্টক:** তেলের রিসিপ্ট আর ওপেনিং আসবে 15/10-এ। ততদিন OIL খাতায় স্টক নেই, তাই তেলের loan ইস্যু আটকায় (UI চেকে এটাই দেখা গেছে)।

৩. **একই কাগজ দুই স্ক্রিনে:** Needle Loan Issue Indent আর 15/07-এর Needle Loan Indent একই কাগজ দেখায়। Brickland-এ নম্বরের সিরিজও একই।

৪. **Against Requisition ঐচ্ছিক:** না দিলে রিটার্ন কোনো indent-এর পাওনা কমায় না, শুধু স্টকে যোগ হয়।

৫. **লাইন মেলানো:** রিটার্নের লাইন indent-এর একই item-এর প্রথম লাইনের সাথে মেলে।


### `15/09` [Loan Rcv from Party](15_Knit_Production/09_Loan_Rcv_From_Party.md)

১. **ফেরতের সাথে রিসিপ্টের যোগ:** Brickland-এর ফেরত ইস্যুতে কোন রিসিপ্টের বিপরীতে ফেরত, তা বলা নেই। তাই রিসিপ্টের Returned হিসাব করা হয় আগে opening ধরে, তারপর পুরোনো রিসিপ্ট থেকে শুরু করে।

২. **তেলের opening (মীমাংসিত, 15/10):** Oil Loan Opening Balance-এর Loan Received এখন তেলের ধারের হিসাবে ধরা হয়।

৩. **ফেরতের সীমা কীভাবে গোনা হয়:** সীমা মাপা হয় ফেরত indent-এর Issue Qty দিয়ে। অন্য পার্টি থেকে ধার নেওয়া একই item এই হিসাবে মেলে না।

৪. **একই কাগজ দুই স্ক্রিনে:** 15/07-এর Needle Loan Indent-এ "Loan Return to Party" দিকটা Borrowed Needle Return Issue-এর সাথে একই কাগজ।


### `15/10` [Oil Store](15_Knit_Production/10_Oil_Store.md)

১. **PR/PO:** Brickland-এর রিসিপ্টে PR আর PO বাছাই আছে, তালিকায় PR, PO, Is Exchanged আর Exchanged MRR কলামও আছে। আমাদের তেলের PR/PO উৎস এখনো নেই, তাই এগুলো রাখা হয়নি। Procurement-এর সাথে যুক্ত হলে যোগ হবে।

২. **রিটার্ন কোন রিসিপ্টের বিপরীতে:** Brickland Draft রিসিপ্টের বিপরীতেও রিটার্ন নিতে দেয়। আমরা শুধু Finalize হওয়া রিসিপ্টের বিপরীতে নিই, কারণ তার আগে স্টকে কিছু ওঠে না।

৩. **Indent-এর Re-open:** Brickland Issued অবস্থাতেও Re-open দেখায়। আমাদের Re-open যা ইস্যু হয়েছিল তা ফেরত নেয়, তারপর Draft করে। এই অর্থ ঠিক আছে কিনা মিলিয়ে দেখা দরকার।

৪. **Consumption-এ মেশিন:** Brickland demo-তে মেশিন নেই, তাই শিট সেভ করে row-এর বাটন দেখা যায়নি। Finalize আর Print রাখা হয়েছে।

৫. **Oil Stock Closing:** Brickland-এর Oil Store-এ closing স্ক্রিন নেই। OIL খাতার মাস বন্ধ করার কোনো পথ এখনো নেই, শুধু exception-এর হিসাব তৈরি রাখা আছে।


### `15/11` [Reports](15_Knit_Production/11_Reports.md)

১. **Brickland-এর ডেমোতে ডাটা নেই:** ১৪৩টা রিপোর্টের ৯৪টায় Brickland ডেমোতে কোনো সারি দেখায়নি, তাই তাদের কলাম দেখা যায়নি। এদের কলাম আমরা ঠিক করেছি স্ট্যাট টাইল (যা Brickland দেখায়) আর একই ধাঁচের রিপোর্ট দেখে। ডাটা ঢুকলে Brickland-এর সাথে আবার মেলাতে হবে।

২. **Adv. Issue (Yarn Stock Position):** Brickland বলে না এটা কী। আমরা ধরেছি lot থেকে indent-এর চাওয়ার বেশি যা ইস্যু হয়েছে।

৩. **Saved (Yarn to Finish / Save Fabric Quantity):** Brickland-এর সংখ্যা (830) বুকিং − ইস্যু হওয়া সুতার সাথে মেলে, তাই সেভাবেই রাখা হয়েছে। আসল অর্থ মিলিয়ে দেখা দরকার।

৪. **Excess Yarn Target:** ৩% ধরা হয়েছে, Process Loss রিপোর্টের "Over 3%"-এর মতো। কারখানার নিজের লক্ষ্যমাত্রা আলাদা হলে setting লাগবে।

৫. **Daily Knitting Production with Value:** ইন-হাউস নিটিংয়ের রেট master নেই। রেট নেওয়া হয় অর্ডারের approved S/C প্রোগ্রামের গড় রেট থেকে, না থাকলে 0 (Brickland-এও 0.00)।

৬. **মেশিনের breakdown:** knit machine আর Machinery-এর asset সরাসরি যুক্ত নয়। মেলানো হয় serial নম্বরে, না হলে asset code = machine no হলে। এখন কোনো knit machine মেলে না, তাই Breakdowns/Downtime 0।

৭. **Yarn Stock-এর Spin/Cotton Type, Slub, Mellange:** সুতার master-এ আলাদা ঘর নেই। এগুলো composition-এর বিবরণের শব্দ থেকে মেলানো হয়।

৮. **QC deduction-এর return challan:** এমন কাগজ এখনো নেই। Daily Return Challan for Grey QC Deduction-এ সব deduction "Challan Pending" দেখায়।

৯. **Pending module:** S/C Pre-Treatment Ledger (দুটো) ডাই হাউস আসলে, Yarn Proforma Invoice কমার্শিয়াল ডেস্ক আসলে পূর্ণ হবে।


## Colour Lab

### `16/01` [Operations](16_Colour_Lab/01_Operations.md)

১. **Notify BF Days:** Brickland-এর তালিকায় কলামটা আছে, কিন্তু ফর্মে কোনো ঘর নেই। আমরা ফর্মে ঘরটা যোগ করেছি (ডিফল্ট 30), আর রিমাইন্ডার সেটা মেনে চলে।

২. **Lab / DyeLab:** Brickland ডেমোতে "Select Lab Floor" ফাঁকা দেখায়। মনে হয় ল্যাব হিসেবে চিহ্নিত ফ্লোরই সেখানে আসে। আমাদের ফ্লোর master-এ এমন চিহ্ন নেই, তাই সব ফ্লোর দেখায়।

৩. **Upload Doc:** Brickland-এ ঘরটা লেখা যায়, পাশে 📎 "Pick the filed document"। আমরা 📎 দিয়ে Company Management-এর filed document বাছাই করাই। আসল ফাইল আপলোড এখনো নেই।

৪. **In-House Fabric Testing:** Brickland-এর Add New "Page not found" দেয়। শিট বানানো হয়েছে তালিকার কলাম ধরে। Summary (Pass/Fail/Conditional), Drying (Tumble/Line/Flat/Drip), গ্রে-স্কেল গ্রেড আর Submit → Approve/Reject ধাপ আমাদের অনুমান।

৫. **Trial-এর ধাপ:** Brickland ডেমোতে কোনো ট্রায়াল সেভ করা যায় না (Lab-এর তালিকা ফাঁকা), তাই WorkFlow কলামে কী দেখায় তা দেখা যায়নি। Start Trial আর Complete আমাদের অনুমান, Brickland-এর অবস্থাগুলোর সঙ্গে মিলিয়ে।

৬. **খালি সেভের বার্তা:** Brickland প্রথম ভুলটাই দেখায়। আমরা সব একসাথে দেখাই।


### `16/02` [Reports](16_Colour_Lab/02_Reports.md)

১. **Chemical Trial Register-এর Buyer:** Brickland রিপোর্টে কলামটা আছে, কিন্তু ট্রায়ালের ফর্মে কোনো Buyer ঘর নেই। তাই কলামটা ফাঁকা থাকে। ট্রায়ালে Buyer রাখা হবে কিনা জানাবেন।

২. **Lab Master List-এর In Charge:** ল্যাবের দায়িত্বপ্রাপ্ত ব্যক্তি রাখার কোনো জায়গা নেই (ল্যাব মানে ফ্লোর), তাই কলামটা ফাঁকা থাকে।

৩. **Light আর Perspiration:** Brickland-এর Fabric Test তালিকায় এই দুটো কলাম নেই, শুধু রিপোর্টে আছে। আমরা শিটে ঘর দুটো যোগ করেছি, তালিকায় দেখাইনি।

৪. **Renewal Due-এর সীমা:** প্রতিটা সার্টিফিকেটের নিজের Notify BF Days দিয়ে ঠিক হয়। কারখানা সবার জন্য একটাই সীমা চাইলে সেটা setting হবে।


## Dye House

### `17/01` [Master Data](17_Dye_House/01_Master_Data.md)

- **ডাই মেশিনের উৎস দেশ:** এটা Merchandise Desk-এর Country & Origin Master থেকে আসে। সেই তালিকা ফাঁকা থাকলে মেশিন যোগ করা যায় না। Brickland-এর demo-তে সেখানে ২৮টা দেশ ছিল।
- **দায়িত্বপ্রাপ্ত কর্মী:** HR & Payroll তৈরি হলে "Responsible Employee" কর্মী তালিকা থেকে আসবে, আর বিভাগ ও পদবি নিজে থেকে বসবে।
- **Finishing & Quality Owner আর In-House Sub-Contract Party Rate:** In-House Sub-Contract Party Rate (S/C IN HOUSE ফোল্ডার) এই মাস্টারের পাশের স্ক্রিন। এটা তৈরি হবে S/C IN HOUSE গ্রুপের সাথে।


### `17/02` [Lab-Dip](17_Dye_House/02_Lab_Dip.md)

১. **Brickland-এর এজেন্ট লেবেল:** Dyes/Chemical Agent-এর ঘরে Brickland "-- Select Category --" লেখে, যদিও তালিকায় থাকে কেমিক্যাল। আমরা হুবহু সেটাই রেখেছি। "-- Select Agent --" চাইলে জানাবেন।

২. **টেমপ্লেট মোছা:** এখন যেকোনো টেমপ্লেট মোছা যায়। ল্যাব ডিপ বা বাল্ক ব্যাচ (17/03–17/04) টেমপ্লেট ব্যবহার শুরু করলে ব্যবহৃত টেমপ্লেট মোছা আটকানো হবে।

৩. **Lookup-এ "approved":** Brickland-এর বর্ণনা বলে "approved lab dip recipes", কিন্তু রেসিপিতে কোনো অনুমোদনের ঘর নেই, আর Brickland-এর Lookup-ও সব রেসিপি দেখায়। আমরাও সব দেখাই।


### `17/03` [Batch (Bulk)](17_Dye_House/03_Batch_Bulk.md)

১. **Grey to Leftover-এর তালিকা:** Brickland বলে "fabric still on a finished batch"। কিন্তু batch শেষ হয় production-এ (17/07), যা এখনো তৈরি হয়নি। তাই এখন batch-store-এর সব batch lot দেখানো হয়, সঙ্গে কার্ডের অবস্থা। 17/07 তৈরি হলে শুধু শেষ হওয়া batch দেখানো হবে।

২. **Order-Wise Grey Transfer আর Batch Transfer to Leftover-এ লাইন:** Brickland-এ এই দুটো শুধু header (কোনো রোল বা লট লাইন নেই), আর grey ledger নড়ে না। আমরা হুবহু তাই রেখেছি। স্টক সত্যিই নড়ানো দরকার হলে Knit-এর Order-to-Order বা Batch to Leftover movement দিয়ে হবে, অথবা পরে লাইন যোগ করা যাবে।

৩. **SC Pre-Treatment-এর পরিমাণ:** Brickland-এর Programme, Challan আর Receipt-এ কোনো কেজি বা লস ঘর নেই ("with quantity and loss booked" লেখা থাকলেও)। তাই এখন শুধু কাগজের সংযোগ রাখা হয়েছে।

৪. **Plan Code:** Brickland-এর ঘরে লেখা "Left blank, the screen issues one", কিন্তু ফাঁকা রাখলে Brickland নিজেই আটকায়। আমরা লেখার উদ্দেশ্য মেনেছি: ফাঁকা রাখলে PLN-YYMM#### বসে।

৫. **Knit-এর Grey→Batch transfer:** এখনো যেকোনো batch no লিখতে দেয়। কার্ড নেই এমন নম্বর আটকানো হবে কিনা জানাবেন।


### `17/04` [Batch (Sample)](17_Dye_House/04_Batch_Sample.md)

১. **Sample card কোথা থেকে আসে:** Brickland-এ sample batch তৈরির স্ক্রিন শুধু "Sample Batch from Bulk Lot"। আলাদাভাবে ডাই হওয়া sample batch (bulk থেকে নয়) কোথা থেকে আসবে জানাবেন। হাতে লেখা হবে (Manual Dye Batch Entry-তে S/B বেছে), নাকি Merchandise-এর lab dip বা sample program থেকে?

২. **Batch Card Split আর Colour Replacement:** এখন শুধু bulk কার্ডের জন্য, Brickland-এর মতো। sample কার্ডের জন্যও লাগবে কিনা জানাবেন।

৩. **Pre-Treatment requisition:** Issue Register-এর ফিল্টারে আছে, কিন্তু Brickland-এ এমন requisition তোলার কোনো স্ক্রিন নেই।


### `17/05` [Requisition](17_Dye_House/05_Requisition.md)

১. **10-Issue আর স্টক:** _সমাধান হয়েছে (17/06)।_ 10-Issue এখন কেমিক্যাল স্টোরের লেজার থেকে স্টক কমায়, যেটার মেয়াদ আগে শেষ সেই লট থেকে। স্টোরে কম থাকলে আটকায়। আংশিক ইস্যু হয় Chemical Store-এর issue স্ক্রিন থেকে।

২. **Approve/Reject বাটন:** Pending না হলেও সারিতে বাটন দেখায়, তবে বন্ধ (disabled) অবস্থায়। Brickland-এ সিদ্ধান্তের পরে বাটন থাকে কিনা demo-তে দেখা যায়নি।

৩. **Re-Process Update Entry-র "Amend an approved re-process":** Brickland-এর বর্ণনা বলে অনুমোদিত রি-প্রসেস বদলানো যায়, কিন্তু সেখানকার ফর্ম আসলে নতুন রি-প্রসেস তোলে। আমরা অনুমোদনের পরেও, ইস্যু না হওয়া পর্যন্ত, বদলানোর সুযোগ রেখেছি। বদলালে আবার অনুমোদন লাগবে কিনা জানাবেন।

৪. **Dye Batch Re-Process-এর "extra dye and chemical it cost":** check-roll return-এ কোনো কেমিক্যালের লাইন নেই (Brickland-এর ফর্মেও নেই)। খরচ আসে রি-প্রসেস রিকুইজিশন থেকে।


### `17/06` [Chemical Store](17_Dye_House/06_Chemical_Store.md)

১. **Loan Return from Party-র Return Type:** Brickland-এর ফর্ম খোলে না, তাই অপশন জানা নেই। আমরা দুটো রেখেছি: Full Return আর Partial Return। আসল অপশনগুলো জানাবেন।

২. **Sales To আর লোনের পার্টি:** এখন সাপ্লায়ার মাস্টার (`mp_suppliers`) থেকে আসে। সিস্টার কনসার্ন বা সাবকন্ট্রাক্টরের জন্য আলাদা পার্টি মাস্টার লাগবে কিনা জানাবেন।

৩. **Is Accounting:** এখন রিসিপ্ট Verify করলে Yes হয়। আসল অ্যাকাউন্টিং (ভাউচার) জোড়া হবে Accounts মডিউলের সময়।

৪. **CS-এর Fab Unit আর Mou Code:** Fab Unit-কে ফ্যাক্টরি ইউনিট ধরা হয়েছে (`factories`), Mou Code রাখা হয়েছে লেখা হিসেবে। Brickland-এর demo-তে কোনো CS নেই, তাই এর মানে নিশ্চিত হওয়া যায়নি।

৫. **CS থেকে Purchase Order:** অনুমোদিত CS থেকে এখনো কিছু তৈরি হয় না। কেমিক্যাল কেনার PO-র স্ক্রিন কোন মডিউলে থাকবে (Material Procurement নাকি এখানে), সেটা ঠিক হলে জোড়া হবে।

৬. **17/05 রিকুইজিশন আর 17/06 ইস্যু:** দুটো আলাদা কাগজ, আর দুটোই স্টক কমায়। 10-Issue পুরো পরিমাণ যেটার মেয়াদ আগে শেষ সেই লট থেকে নেয়। Batch Programme Chemical Issue-এর মতো ইস্যু কি রিকুইজিশনের বিপরীতে হবে (রিকুইজিশন বাছলে লাইন আসবে), জানাবেন।

৭. **Month Closing:** কোম্পানির সব কেমিক্যাল স্টোর একসাথে ক্লোজ হয় (Brickland-এর ফর্মে স্টোর নেই)। ক্লোজ ফেরানোর (reopen) কোনো উপায় নেই।


### `17/07` [Production Board](17_Dye_House/07_Production_Board.md)

১. **বুকিং আর প্রোগ্রামের নম্বর:** Brickland-এর demo-তে কোনো রেকর্ড নেই, তাই নম্বরের ফরম্যাট জানা যায়নি। আমরা DFB-, GFB- আর TDP- (সঙ্গে YYYYMM####) রেখেছি। আসল ফরম্যাট জানাবেন।

২. **D&F Closing-এর কলাম:** Fab., Bk Rpt আর Req Qty-র মানে ধরে নেওয়া হয়েছে: ফেব্রিকের সংখ্যা, ডিরেক্ট বুকিংয়ের কেজি আর ব্যাচের কেজি। ঠিক না হলে জানাবেন। ক্লোজ ফেরানোর (Reopen) কোনো উপায় রাখা হয়নি। দরকার হলে জানাবেন।

৩. **Machine Wash Output আর স্টক:** ওয়াশ প্রোডাকশন স্টক থেকে কিছু কমায় না, কারণ স্টক কমে 10-Issue বা Chemical Store-এর ইস্যু থেকে। প্রোডাকশনে দেওয়া পরিমাণ রিকুইজিশনের ইস্যু থেকে আলাদা হলে কী হবে (ফেরত বা বাড়তি ইস্যু), জানাবেন।

৪. **Check Roll Parameter-এর ঘর:** Brickland-এ স্ক্রিনটা Scaffold, শুধু বর্ণনা আছে ("GSM, width and shade tolerances")। আমরা বায়ার আর ফেব্রিকের ধরন ধরে GSM %, প্রস্থ %, শেড ΔE আর সংকোচন % রেখেছি। আর কোনো ঘর লাগলে জানাবেন। চেক রোলের QC (17/08) এই সীমা ব্যবহার করবে।

৫. **খালি লোড:** Batch History-তে মেশিন, সময় বা অবস্থা ছাড়া একটা লোডও সেভ হয়। এগুলো সেভের সময় বাদ দেওয়া হবে কিনা জানাবেন।

৬. **Production Board-এর পারমিশন কে পাবে:** এখন শুধু Factory Admin আর Factory Manager পায়। ফ্লোরের কোনো role (যেমন Line Chief) পেলে জানাবেন।


### `17/08` [Dyeing Q.C](17_Dye_House/08_Dyeing_Q_C.md)

১. **চেক রোলে GSM আর প্রস্থ:** Brickland-এর বর্ণনায় "shade, GSM, width and defects recorded" আছে, কিন্তু ফর্মে GSM বা প্রস্থের ঘর নেই। আমরা শুধু ত্রুটির টিক রেখেছি। মাপা GSM আর প্রস্থ লিখে Check Roll Parameter (17/07)-এর সহনসীমার সাথে মেলানো লাগবে কিনা জানাবেন।

২. **ইন্সপেকশনের নম্বর:** Brickland-এ "[Auto Gen]" লেখা থাকে, ফরম্যাট দেখা যায়নি। আমরা CRI-YYYYMM#### রেখেছি।

৩. **Batch Programme Completion-এর ধাপ:** Brickland বলে "once every QC gate has passed"। আমরা চারটা ধাপ ধরেছি: প্রোডাকশন, চেক রোল পরীক্ষা, কোনো রোল hold-এ নেই, কোনো শেড খোলা নেই। আর কোনো ধাপ (যেমন ফিনিশিং QC) লাগলে জানাবেন। Complete ফেরানোর (undo) কোনো উপায় রাখা হয়নি।

৪. **Sample কার্ড:** চেক রোলের তালিকায় স্যাম্পল কার্ডও আসে (S-…), যদি সেটা মেশিনে উঠে থাকে। শুধু বাল্ক রাখতে হলে জানাবেন।

৫. **Hold হওয়া রোলের পরে কী হয়:** এখন hold হলে শুধু রেকর্ড থাকে। রি-প্রসেস রিকুইজিশন বা check-roll return নিজে থেকে তৈরি হয় না। দরকার হলে জানাবেন।


### `17/09` [S/C Dyeing](17_Dye_House/09_S_C_Dyeing.md)

১. **নম্বরের ফরম্যাট:** Brickland থেকে শুধু প্রোগ্রামের DF-SC-OH- জানা গেছে। বাকিগুলো আমাদের দেওয়া: DF-SC-RQ-, DF-SC-B-, DF-SC-CH-, DF-SC-FR-, DF-SC-MRR- আর DF-SC-BL-। আসল ফরম্যাট জানাবেন।

২. **গ্রে ইস্যু আর স্টক:** Grey Fabric Indent List-এর Issue এখন শুধু ইস্যুর পরিমাণ লেখে। নিটিংয়ের গ্রে স্টোর লেজার (`knit_grey_lots`) থেকে স্টক কমায় না, কারণ সাব-কন্ট্রাক্টের ফেব্রিক কোনো ব্যাচ কার্ডে নেই। কোন লট থেকে ইস্যু হবে তা বাছতে হলে জানাবেন।

৩. **ফেরত আসা ফেব্রিক কোথায় যায়:** Challan Receipt এখন শুধু রেকর্ড রাখে। ফিনিশড ফেব্রিক স্টোরের স্টকে (সাব-কন্ট্রাক্ট হিসেবে) ঢোকানো লাগবে কিনা, আর কোন মডিউলে, জানাবেন।

৪. **Completed হওয়ার নিয়ম:** স্টোরে পুরো চাওয়া কেজি উঠলে প্রোগ্রাম Completed হয়। প্রসেস লস ধরে কম উঠলেও Completed করা লাগবে কিনা জানাবেন। এখন ডেস্ক অবস্থা হাতে বদলে দিতে পারে।

৫. **বিল আর রিসিপ্ট:** বিল এখন কোনো রিসিপ্ট বা প্রোগ্রামের সাথে জোড়া নয়, শুধু পার্টি আর টাকা থাকে (Brickland-এর ফর্মেও তাই)। রেট ধরে বিল হিসাব করা লাগবে কিনা জানাবেন।


### `17/10` [S/C In House](17_Dye_House/10_S_C_In_House.md)

১. **প্রোগ্রামের অবস্থা:** Brickland-এর In-House প্রোগ্রামেও Out-House-এর অবস্থাগুলোই আছে ("S/C OH Submitted", "Despatched")। আমরাও তাই রেখেছি। In-House-এর আলাদা অবস্থা লাগবে কিনা জানাবেন।

২. **ইন-হাউস ফেব্রিক কোথায় যায়:** Fabric Receipt এখন শুধু রেকর্ড রাখে। পার্টির ফেব্রিক গ্রে স্টোরে (পার্টির নামে আলাদা) ঢুকবে কিনা, আর এর থেকে ব্যাচ তৈরি হবে কিনা, জানাবেন।

৩. **ইন-হাউস বিল:** পার্টিকে বিল করার স্ক্রিন Brickland-এর এই ফোল্ডারে নেই। In-House রেট থেকে বিল কোথায় হবে (Accounts নাকি এখানে), জানাবেন।


## Finishing Line

### `18/01` [Master Data](18_Finishing_Line/01_Master_Data.md)

- **ফিনিশিং মেশিনের ধরনের তালিকা:** Brickland-এ ফিনিশিং মেশিনের ধরন (Stenter, Compactor …) কোন স্ক্রিনে সম্পাদনা হয় তা দেখা যায়নি, তাই এখানে এটা একটা স্থির তালিকা।


### `18/02` [DF Production](18_Finishing_Line/02_Df_Production.md)

১. **ব্যাচ কখন হস্তান্তর হয়:** এখন যে ব্যাচের অন্তত একটা ডাইং প্রোডাকশন আছে, সেটাই রিসিভ করা যায়। Brickland-এর স্ক্রিন scaffold, তাই নিয়ম ধরে নেওয়া হয়েছে। Dyeing Q.C (17/08)-এর Batch Completion বা শেড অ্যাপ্রুভাল লাগবে কিনা জানাবেন।

২. **স্টেজের ক্রম:** পোস্ট করলে ব্যাচ ম্যাপিংয়ের next status-এ যায়, ব্যাচ এখন যে স্টেজে আছে তা ম্যাপিংয়ের batch status-এর সাথে না মিললেও। ক্রম কড়াভাবে মানাতে হবে কিনা (যেমন Stentering-এর আগে Compacting আটকানো) জানাবেন।

৩. **Sub Contract বুকিং:** Dye House ব্যাচের সাথে S/C In-House প্রোগ্রামের (17/10) এখনো কোনো যোগ নেই। তাই রিসিপ্টে Sub Contract হাতে বাছতে হয়। প্রোগ্রাম থেকে নিজে বসবে কিনা জানাবেন।

৪. **সংশোধনের পরে স্টেজ:** মেশিন বা প্রসেস সংশোধন করলে ব্যাচের স্টেজ বদলায় না। বদলানো উচিত কিনা জানাবেন।

৫. **Process-Wise Delivery-র কলাম:** Brickland-এর ডেমোতে বোর্ডে কোনো সারি ছিল না, তাই কলাম (ব্যাচ, রিসিপ্ট, বায়ার, স্টাইল, অর্ডার, রং, dia, বুকিং, কেজি, রোল, WIP, শেষ প্রসেস/মেশিন/লোড, Spent Time) বানানো হয়েছে হিন্ট আর ফিল্টার দেখে। আরও কোনো কলাম লাগবে কিনা জানাবেন।


### `18/03` [S/C Process](18_Finishing_Line/03_S_C_Process.md)

১. **ফেরত আসা ফেব্রিক কোথায় যায়:** রিসিপ্টে স্টোর বাছতে হয়, কিন্তু এখনো কোনো স্টক লেজারে কিছু ঢোকে না। ফিনিশড ফেব্রিক স্টোর (18/05) তৈরি হলে রিসিপ্ট সেখানে স্টক তুলবে কিনা জানাবেন।

২. **ব্যাচ কোন স্টেজে ফেরে:** সব লাইনের জবাব হলে ব্যাচ শেষ লাইনের প্রসেসের WIP স্টেজে ফেরে (যেমন Calendering → Calendering)। প্রসেস শেষ হয়েছে বলে পরের ধাপে (যেমন QC Inspection) পাঠানো উচিত কিনা জানাবেন।

৩. **শর্ট ওজন:** শর্ট ওজন লাইনের বাকি থেকে কাটা হয়, কিন্তু বিল হয় শুধু রিসিভ কেজির ওপর। পার্টির কাছ থেকে শর্টের দাম কাটার (ডেবিট নোট) কোনো ধাপ লাগবে কিনা জানাবেন।

৪. **বিলের অনুমোদন:** Submit থেকে Complete শুধু একটা ধাপ, আলাদা কোনো অনুমোদন নেই। Approval Center-এ পাঠাতে হবে কিনা জানাবেন।


### `18/04` [QC Process](18_Finishing_Line/04_Qc_Process.md)

১. **শেড সিদ্ধান্ত আর ইন্সপেকশন:** এখন শেড সিদ্ধান্ত Approved না হলেও ইন্সপেকশনে Pass দেওয়া যায়। Pass-এর আগে শেড অ্যাপ্রুভাল বাধ্যতামূলক হবে কিনা জানাবেন।

২. **গ্রেড:** রোলের গ্রেড হাতে লেখা হয় (Brickland-এর মতো)। ফোর-পয়েন্ট থেকে নিজে হিসাব হবে কিনা (যেমন প্রতি ১০০ বর্গগজে পয়েন্ট, A/B/C-এর সীমা) জানাবেন।

৩. **রিজেক্টের পরে ব্যাচ:** Hold আর Reject দুটোই ব্যাচকে On Hold-এ রাখে। পরামর্শ দিলে পুরো ব্যাচ Awaiting Load-এ যায়, শুধু বাতিল রোলগুলো নয়। পাস হওয়া রোল আলাদা করে স্টোরে পাঠানোর (ব্যাচ ভাগ করার) দরকার আছে কিনা জানাবেন।

৪. **Quality Entry-র কাজ:** রোলের রিডিং এখন শুধু রেকর্ড। ত্রুটিপূর্ণ রোল বা শেড ভুল হলে ব্যাচ নিজে থেকেই থামবে কিনা জানাবেন।


### `18/05` [Fabric Store](18_Finishing_Line/05_Fabric_Store.md)

১. **সাধারণ স্টোর লেজারের সাথে যোগ:** ফিনিশড ফেব্রিকের লট আর ব্যালান্স এখন Finishing মডিউলের নিজের লেজারে থাকে। Store & Material-এর স্টক রিপোর্টেও এগুলো দেখাতে হবে কিনা জানাবেন।

২. **রোল আর ইস্যু:** কাটিংয়ে ইস্যু লট আর কেজি ধরে হয়, রোলের বারকোড ধরে নয়, আর রোলের অবস্থা নিজে থেকে Issued হয় না। ইস্যুর সময় বারকোড স্ক্যান করে রোল ধরে দেওয়া লাগবে কিনা জানাবেন।

৩. **রিকুইজিশনের অবস্থা:** Brickland-এর মতো অবস্থা হাতে বাছা হয় (Draft/Partial/Complete/Cancelled), আর নিয়ম দিয়ে যাচাই হয়। ইস্যু থেকে অবস্থা নিজে বসবে কিনা জানাবেন।

৪. **নোট রিসিভ না হলে:** পাঠানো নোট স্টোর রিসিভ না করা পর্যন্ত ব্যাচ বোর্ডে Delivered থাকে, আর স্টকে কিছু থাকে না। পাঠানো নোট বাতিল বা ফেরতের ধাপ লাগবে কিনা জানাবেন।


### `18/06` [Report](18_Finishing_Line/06_Report.md)

১. **রিপোর্টের কলাম:** Brickland-এর ডেমোতে কোনো রিপোর্টে সারি ছিল না, তাই কলাম আর হিসাব আমরা নিজেদের ডাটা থেকে বানিয়েছি। বিশেষ করে "Finishing Production Report 4A", "Order Monitoring", "Day-Night/Shift Wise Production for Incentive"-এর ধরন বা ফর্মুলা আলাদা হলে জানাবেন।

২. **বক্স চালু/বন্ধ:** Brickland প্রতিটা রিপোর্টে শুধু দরকারি বক্স চালু রাখে (যেমন Process Type Register-এ কোনো বক্স নেই)। আমাদের কার্ডে সব বক্স সবসময় খোলা থাকে, যে রিপোর্টের যা দরকার নেই তা উপেক্ষা হয়। প্রতিটা রিপোর্টে বক্স বন্ধ রাখতে হবে কিনা জানাবেন।

৩. **লেফট-ওভারের সংজ্ঞা:** লেফট-ওভার ধরা হয়েছে সেই লট, যা থেকে কাটিং নিয়েছে, যাতে আর খোলা রিকুইজিশন নেই আর কিছু রয়ে গেছে। অর্ডার শিপ হওয়া বা বন্ধ হওয়ার সাথে যুক্ত করতে হবে কিনা জানাবেন।

৪. **দিন-রাত:** আনলোডের সময় ০৬:০০–১৭:৫৯ দিন, বাকিটা রাত ধরা হয়েছে। কারখানার নিজের সময় আলাদা হলে জানাবেন।


## Cutting Floor

### `19/01` [Cutting](19_Cutting_Floor/01_Cutting.md)

১. **PO কোথা থেকে আসে:** PO নং এখন মার্চেন্ডাইজ অর্ডারের `po_no` থেকে আসে (প্রতি অর্ডারে একটা)। Brickland-এ একই স্টাইলের অনেক PO আছে (যেমন "11565705 (BOTTOM)", "E-COM")। অর্ডারের ভেতরে আলাদা PO তালিকা লাগবে কিনা জানাবেন।

২. **অতিরিক্তের সীমা:** অতিরিক্ত গোনা হয় রঙের মোট পরিমাণ ধরে, সাইজ ধরে নয়, আর একেক পার্টস আলাদা করে। সাইজ ধরে হিসাব করতে হবে কিনা, বা কোনো ছাড়ের % (যেমন ৩%) থাকবে কিনা জানাবেন।

৩. **ফেব্রিক ইস্যুর সাথে যোগ:** ব্যাচ নং এখন তালিকা থেকে বা টাইপ করে দেওয়া যায়। এটা ফেব্রিক স্টোরের কাটিং ইস্যু (18/05)-এর লট থেকেই বাধ্যতামূলক আসবে কিনা জানাবেন।

৪. **প্রিন্টের পরে এডিট:** প্রিন্ট হওয়া কার্ডও এডিট করা যায় (Brickland-এর মতো Edit বাটন আছে)। এডিটের পরে কার্ড আবার Created হবে (রিপ্রিন্ট লাগবে) কিনা জানাবেন।

৫. **টিউব কাপড়ের ওজন:** মার্কার থেকে kg হিসাবে Dia/Width-কে খোলা চওড়া ধরা হয়। টিউব কাপড়ে (Dia দুই ভাঁজ) দ্বিগুণ ধরতে হবে কিনা জানাবেন।
৬. **আউটপুটের বাকি কাপড় স্টোরে ফেরত:** আউটপুটের "বাকি / ফেরত" এখন শুধু লেখা থাকে, ফিনিশড ফেব্রিক স্টোরে আপনা-আপনি return হয় না। স্টোরে ফেরত দেখাতে চাইলে জানাবেন।


### `19/02` [General](19_Cutting_Floor/02_General.md)

১. **PO কোথা থেকে আসে:** PO নং এখন মার্চেন্ডাইজ অর্ডারের `po_no` থেকে আসে (প্রতি অর্ডারে একটা)। Brickland-এ একই স্টাইলের অনেক PO আছে (যেমন "11565705 (BOTTOM)", "E-COM")। অর্ডারের ভেতরে আলাদা PO তালিকা লাগবে কিনা জানাবেন।

২. **অতিরিক্তের সীমা:** অতিরিক্ত গোনা হয় রঙের মোট পরিমাণ ধরে, সাইজ ধরে নয়, আর একেক পার্টস আলাদা করে। সাইজ ধরে হিসাব করতে হবে কিনা, বা কোনো ছাড়ের % (যেমন ৩%) থাকবে কিনা জানাবেন।

৩. **ফেব্রিক ইস্যুর সাথে যোগ:** ব্যাচ নং এখন তালিকা থেকে বা টাইপ করে দেওয়া যায়। এটা ফেব্রিক স্টোরের কাটিং ইস্যু (18/05)-এর লট থেকেই বাধ্যতামূলক আসবে কিনা জানাবেন।

৪. **প্রিন্টের পরে এডিট:** প্রিন্ট হওয়া কার্ডও এডিট করা যায় (Brickland-এর মতো Edit বাটন আছে)। এডিটের পরে কার্ড আবার Created হবে (রিপ্রিন্ট লাগবে) কিনা জানাবেন।


### `19/03` [Bundle Card](19_Cutting_Floor/03_Bundle_Card.md)

১. **PO কোথা থেকে আসে:** PO নং এখন মার্চেন্ডাইজ অর্ডারের `po_no` থেকে আসে (প্রতি অর্ডারে একটা)। Brickland-এ একই স্টাইলের অনেক PO আছে (যেমন "11565705 (BOTTOM)", "E-COM")। অর্ডারের ভেতরে আলাদা PO তালিকা লাগবে কিনা জানাবেন।

২. **অতিরিক্তের সীমা:** অতিরিক্ত গোনা হয় রঙের মোট পরিমাণ ধরে, সাইজ ধরে নয়, আর একেক পার্টস আলাদা করে। সাইজ ধরে হিসাব করতে হবে কিনা, বা কোনো ছাড়ের % (যেমন ৩%) থাকবে কিনা জানাবেন।

৩. **ফেব্রিক ইস্যুর সাথে যোগ:** ব্যাচ নং এখন তালিকা থেকে বা টাইপ করে দেওয়া যায়। এটা ফেব্রিক স্টোরের কাটিং ইস্যু (18/05)-এর লট থেকেই বাধ্যতামূলক আসবে কিনা জানাবেন।

৪. **প্রিন্টের পরে এডিট:** প্রিন্ট হওয়া কার্ডও এডিট করা যায় (Brickland-এর মতো Edit বাটন আছে)। এডিটের পরে কার্ড আবার Created হবে (রিপ্রিন্ট লাগবে) কিনা জানাবেন।

৩. **টিউব কাপড়ের ওজন:** মার্কার থেকে kg হিসাবে Dia/Width-কে খোলা চওড়া ধরা হয়। টিউব কাপড়ে (Dia দুই ভাঁজ) দ্বিগুণ ধরতে হবে কিনা জানাবেন।
৪. **আউটপুটের বাকি কাপড় স্টোরে ফেরত:** আউটপুটের "বাকি / ফেরত (kg)" এখন শুধু লেখা থাকে, ফিনিশড ফেব্রিক স্টোরে আপনা-আপনি return হয় না। স্টোরে ফেরত দেখাতে চাইলে জানাবেন।


### `19/04` [Reports](19_Cutting_Floor/04_Reports.md)

১. **রং ও পার্টস অনুযায়ী রিপোর্ট:** এখন PO, স্টাইল আর সাইজ অনুযায়ী গোনা হয়। রং বা পার্টস অনুযায়ী (একই গার্মেন্টের কয়েকটা পার্টস) আলাদা রিপোর্ট লাগবে কিনা জানাবেন।


## Assembly Floor

### `20/01` [Sewing](20_Assembly_Floor/01_Sewing.md)

১. **"Finalizing closes it":** Brickland-এর Bundle IN ডেস্কে লেখা আছে দিন finalize হলে রিসিপ্ট আর তোলা যায় না, কিন্তু finalize করার কোনো বাটন বা স্ক্রিন পাওয়া যায়নি। এখন দিন খোলা থাকে ৭ দিন (আজ আর আগের ৬ দিন)। আলাদা Day Finalize লাগবে কিনা, আর দিনটা কতদিন খোলা থাকবে, জানাবেন।

২. **পার্টস অনুযায়ী কার্ড:** কাটিংয়ে প্রতিটা পার্টসের (Front Panel, Back Panel, Sleeve…) আলাদা কার্ড হয়। সেলাইয়ে এখন প্রতিটা কার্ড আলাদা ঢোকে আর বের হয়, তাই এক গার্মেন্টের কয়েকটা পার্টস-কার্ড আউট হলে আউটপুট কয়েকবার গোনা হতে পারে। সেলাইয়ে কি পুরো সেট (সব পার্টস) একসাথে ঢোকে, নাকি শুধু একটা মূল কার্ড স্ক্যান হয়, জানাবেন।

৩. **প্রিন্ট না হওয়া কার্ড:** এখন শুধু প্রিন্ট হওয়া কার্ড সেলাইয়ে নেওয়া যায় (কাগজের কার্ড না থাকলে স্ক্যান হয় না, এই ধারণায়)। হাতে লিখে প্রিন্ট-না-হওয়া কার্ডও নেওয়া লাগবে কিনা জানাবেন।

৪. **স্টাইল না মিললে:** লাইনের প্ল্যানের স্টাইল আর বান্ডেলের স্টাইল আলাদা হলে এখন শুধু সতর্কবার্তা দেখায়, আটকায় না। আটকানো লাগবে কিনা জানাবেন।


### `20/02` [Finishing](20_Assembly_Floor/02_Finishing.md)

১. **ফিনিশিংয়ের পরিকল্পিত তারিখ:** Brickland-এর ডেস্কে "Planned Finishing Start / End" আছে। কিন্তু আমাদের master plan-এ শুধু সেলাইয়ের শুরু ও শেষের তারিখ থাকে, তাই এখন সেটাই দেখানো হচ্ছে। ফিনিশিংয়ের আলাদা তারিখ লাগবে কিনা (যেমন সেলাইয়ের পরে কতদিন), জানাবেন।

২. **শিফট অনুযায়ী টার্গেট:** Brickland বলে টার্গেট আসে লাইনের শিফট প্ল্যান বা দৈনিক টার্গেট থেকে। কিন্তু Company Management-এর শিফট প্ল্যানে কোনো টার্গেট রাখার জায়গা নেই। তাই এখন টার্গেট = লাইনের দৈনিক টার্গেট × দিন, একটা শিফট বাছলেও একই থাকে। শিফট অনুযায়ী আলাদা টার্গেট লাগবে কিনা জানাবেন।

৩. **ভুল স্ক্যান শোধরানো:** Brickland-এর Finishing ডেস্কে Withdraw বা Undo নেই, তাই আমরাও রাখিনি। একবার কনফার্ম হলে ভুল IN বা ভুল Good / Reject / Rework আর শোধরানো যায় না। সুপারভাইজারের জন্য শোধরানোর কোনো পথ লাগবে কিনা জানাবেন।

৪. **অন্য লাইনে আউট:** এখন যে লাইনে রিসিভ হয়েছে, সেই লাইন থেকেই আউট করতে হয়। বান্ডেল এক ফিনিশিং লাইন থেকে আরেক লাইনে সরানো হয় কিনা, আর হলে আউট অন্য লাইনে নেওয়া যাবে কিনা জানাবেন।

৫. **রিওয়ার্ক শেষ হলে:** রিওয়ার্ক Completed হলে পিসগুলো এখন Good-এ যোগ হয় না, আলাদাই থাকে। শেষ হওয়া রিওয়ার্ক ভালো আউটপুট (আর অর্জন) হিসেবে গোনা হবে কিনা জানাবেন।


### `20/03` [Quality Management](20_Assembly_Floor/03_Quality_Management.md)

১. **পুরো রিজেক্ট বান্ডেলের OUT:** কোনো পিস পাস না হলে এখন বান্ডেলটা "Reject" চূড়ান্ত অবস্থা নিয়ে Quality OUT হতে পারে (Brickland-এর অবস্থার তালিকায় Reject আছে)। এমন বান্ডেল OUT করা যাবে, নাকি Quality-তেই আটকে থাকবে, জানাবেন।

২. **রিওয়ার্কের সংখ্যা:** OUT-এ "Rework" = সব রাউন্ডের রিওয়ার্ক যোগ করে (একই পিস দুবার রিওয়ার্ক হলে দুবার গোনা হয়)। ড্যাশবোর্ডের Inspected-ও প্রতিটা রাউন্ড গোনে, তাই Inspected বান্ডেলের পরিমাণের চেয়ে বেশি হতে পারে (dev-এ ৬৩ পিস ইন, ৬৫ ইন্সপেকশন)। এই গোনার নিয়ম ঠিক আছে কিনা জানাবেন।

৩. **লাইনের সুপারভাইজার:** Brickland-এর ডেস্কে লাইনের আজকের হিসাবের পাশে "Supervisor" নাম দেখায়, কিন্তু Company Management-এর লাইন মাস্টারে সুপারভাইজারের ঘর নেই। তাই এখন নাম দেখানো হচ্ছে না। লাইন মাস্টারে সুপারভাইজার যোগ করা হবে কিনা জানাবেন।

৪. **ভুল স্ক্যান শোধরানো:** Finishing-এর মতোই Quality-র ডেস্কে Withdraw বা Undo নেই। ভুল ইন্সপেকশন কনফার্ম হলে আর শোধরানো যায় না। সুপারভাইজারের জন্য কোনো পথ লাগবে কিনা জানাবেন।

৫. **একই লাইনে ইন্সপেকশন আর OUT:** এখন যে Quality লাইনে রিসিভ, সেই লাইনেই ইন্সপেকশন আর OUT করতে হয়। ইন্সপেকশন আর রিলিজ আলাদা টেবিল বা লাইনে হয় কিনা জানাবেন।


### `20/04` [Packing](20_Assembly_Floor/04_Packing.md)

১. **প্যাকিং লোকেশন:** Brickland-এর Packing In-এ Section-এ শুধু "Finishing" আসে, আর আমাদের লাইন মাস্টারে PACKING টাইপ নেই। তাই এখন প্যাকিং লোকেশন = Finishing সেকশনের FINISHING লাইন (dev-এ "Packing Line1"-কে FINISHING টাইপ করা হয়েছে, তাই সেটা Finishing ডেস্কেও দেখায়)। লাইন মাস্টারে আলাদা PACKING টাইপ লাগবে কিনা জানাবেন।

২. **ব্যাচ আর লোকেশন:** ব্যাচ খোলে PO + রং অনুযায়ী। অন্য লোকেশনে একই PO-রঙের বান্ডেল স্ক্যান করলেও সেটা প্রথম লোকেশনের খোলা ব্যাচে যোগ হয়। লোকেশন অনুযায়ী আলাদা ব্যাচ লাগবে কিনা জানাবেন।

৩. **বান্ডেলের ভাগে প্যাকিং ইন:** একটা বান্ডেল ভাগে ভাগে আসতে পারে (বাকি পরিমাণ পরে, দরকার হলে পরের ব্যাচে)। এটা ঠিক আছে, নাকি প্রতিটা বান্ডেল একবারেই পুরোটা আসবে, জানাবেন।

৪. **Final Packed:** এখন Final Packed সিল করা কার্টনের মোট পরিমাণের বেশি হতে পারে না, কিন্তু কম হতে পারে (তখন Balance দেখায়)। কম হলে বাকি পিসের কী হবে (আবার প্যাকিং, নাকি লেফটওভার), জানাবেন।

৫. **QC Reference:** Brickland Packing In-এ "QC Reference · সময়" দেখায়। আমাদের Quality OUT-এর আলাদা ডকুমেন্ট নম্বর নেই, তাই এখন "QC-বান্ডেল নম্বর" আর Quality OUT-এর সময় দেখানো হচ্ছে। আলাদা QC নম্বর লাগবে কিনা জানাবেন।


### `20/05` [Packing Process](20_Assembly_Floor/05_Packing_Process.md)

১. **Reopen-এর সীমা:** Brickland প্রতিটা সম্পন্ন প্রসেসের পাশে Reopen দেখায়। আমরা শুধু তখনই Reopen দিচ্ছি যখন পরের প্রসেস এখনো শুরু হয়নি, যাতে পরের ধাপের কাজ (যেমন কার্টন) অর্থহীন না হয়। আগের প্রসেস খুললে পরেরগুলোও পেছনে যাবে কিনা জানাবেন।

২. **কার্টন নম্বরের ধরন:** এখন CTN-YYYYMMDD-#### (দিনভিত্তিক ক্রমিক), বারকোড = ড্যাশ ছাড়া নম্বর। Brickland-এর নমুনায় "SGI-CTN-000125" (কোম্পানির প্রিফিক্স + চলমান নম্বর) দেখায়। কোন ধরন লাগবে জানাবেন।

৩. **পলি লেবেল:** এখন প্রতিটা সাইজের জন্য একটা লেবেল (পরিমাণসহ)। প্রতিটা পিস বা প্রতিটা পলির জন্য আলাদা লেবেল লাগবে কিনা জানাবেন।

৪. **লেবেলে গন্তব্য:** Barcode & Label আগে, Carton Marking পরে, তাই পলি লেবেলের "Dest" ফাঁকা থাকে (Brickland-এর ক্রমও এটাই)। গন্তব্য আগে নেওয়া হবে কিনা জানাবেন।

৫. **সব পিস কার্টনে:** Carton Packing এখন যত পিস কার্টনে উঠেছে তা নিয়েই সম্পন্ন হয়, Assortment-এর সব পিস কার্টনে ওঠা বাধ্যতামূলক নয়। বাকি পিস থাকলে Complete আটকাবে কিনা জানাবেন।


### `20/06` [Ready For Shipment](20_Assembly_Floor/06_Ready_For_Shipment.md)

১. **Final Packed কম হলে কোন সাইজ কমবে:** Final Packed সিল করা কার্টনের মোটের চেয়ে কম হলে এখন ঘাটতিটা শেষ সাইজগুলো থেকে কাটা হয় (যেমন S ১০ + M ১০, Final ১৮ → স্টকে S ১০, M ৮)। কোন সাইজ থেকে কাটা হবে, নাকি Final Packed সবসময় কার্টনের সমান হবে, জানাবেন।

২. **একটা সারি = PO + রং:** Brickland-এর মতো Ready তালিকার প্রতিটা সারি এক অর্ডার/PO আর এক রং। আলাদা স্টাইল একই PO-তে থাকলে সেটা অর্ডার আলাদা হওয়ায় আলাদা সারি হয়। সাইজ অনুযায়ী Ready দেখানো লাগবে কিনা জানাবেন (সাইজভিত্তিক হিসাব এখন Finished Goods Stock-এ আছে)।

৩. **ফিনিশড গুডস স্টোর:** Brickland টাইলে "Finished Goods Store" লেখে। এখন একটাই স্টোর ধরা হয়েছে, Store/Inventory মডিউলের কোনো স্টোরের সাথে যুক্ত নয়। ফ্যাক্টরি বা গুদাম অনুযায়ী আলাদা FG স্টোর লাগবে কিনা জানাবেন।


### `20/07` [Shipment](20_Assembly_Floor/07_Shipment.md)

১. **Sales & Delivery-র সাথে যোগ:** Brickland-এর Shipment Entry-তে "Sales & Delivery Shipment" বাছাই করা যায় (শিপড হলে সেখানে FINAL packing list তৈরি হয়), আর তালিকায় "S&D …" নম্বর দেখায়। আমাদের Sales & Delivery মডিউল এখনো তৈরি হয়নি, তাই ঘরটা রাখা হয়নি। সেই মডিউল বানানোর সময় যোগ করা হবে কিনা জানাবেন।

২. **সংশোধনের নতুন শিপমেন্টের তারিখ:** সংশোধিত পরিমাণে আবার শিপ করলে নতুন শিপমেন্টের তারিখ এখন আজকের দিন, আর সেটা সরাসরি শিপড হয় (Brickland-এর "re-ship now")। মূল শিপমেন্টের তারিখ রাখা হবে কিনা জানাবেন।

৩. **শিপমেন্টের তারিখ বনাম শিপড হওয়ার দিন:** শিপমেন্টের রিপোর্ট (Daily ইত্যাদি) শিপড হওয়ার দিন ধরে গোনে, আর History ধরে শিপমেন্টের তারিখ (এন্ট্রিতে দেওয়া)। কোন তারিখে গোনা হবে জানাবেন।

৪. **রেডি রিপোর্টে তারিখ:** রেডি আর Finished Goods Stock-এর রিপোর্ট এখনকার অবস্থা দেখায়, From / To তারিখ মানে না। নির্দিষ্ট দিনের অবস্থা লাগবে কিনা জানাবেন।

৫. **শিপমেন্টের রোল:** Brickland "Shipment Operator" আর "Manager"-এর কথা বলে। আমাদের রোলে এই দুটো নেই, তাই এখন Factory Admin আর Factory Manager শিপমেন্ট চালান, Line Chief শুধু দেখেন। আলাদা Shipment / Commercial রোল লাগবে কিনা জানাবেন।


### `20/08` [Accessories Store](20_Assembly_Floor/08_Accessories_Store.md)

১. **Requisition Type:** Brickland-এ এটা একটা তালিকা থেকে বাছাই, কিন্তু ডেমোতে কোনো মান নেই। এখন Dye House-এর মতো লিখে দেওয়ার ঘর (ডিফল্ট লেখা "Store Requisition")। নির্দিষ্ট তালিকা লাগলে মানগুলো জানাবেন।

২. **ইস্যু আর রিকুইজিশন:** "Requisition#" এখন শুধু লেখা নম্বর; কোনো রিকুইজিশন কাগজের সাথে যুক্ত নয়, আর ইস্যু অর্ডারের ট্রিমস বুকিং / প্রয়োজনের সাথে মেলানো হয় না। অর্ডারের প্রয়োজনের বেশি ইস্যু আটকাতে হবে কিনা জানাবেন।

৩. **স্টোর আর 09 Store Material:** এই ফ্লোর স্টোরের স্টক আলাদা লেজারে (`asm_trims_moves`), 09 Store Material-এর Trims Warehouse-এর সাথে যুক্ত নয়। মূল গুদাম থেকে ফ্লোর স্টোরে ট্রান্সফার লাগবে কিনা, নাকি দুটো একই স্টক হবে, জানাবেন।

৪. **Receipt-এর অনুমোদন:** Brickland-এর MRR-এর অবস্থা শুধু Draft / Completed / Cancelled; Issue-তে Submitted / Approve / Rejected। তাই MRR-এ কোনো অনুমোদন ধাপ নেই। MRR-এও অনুমোদন লাগবে কিনা জানাবেন।


### `20/09` [Reports](20_Assembly_Floor/09_Reports.md)

১. **রিপোর্টের কলাম:** Brickland-এর ডেমোতে ৩০টার মধ্যে শুধু "Buyer A/C wise Actual Sewing Done Undone as per Booking" ডাটা দেখায় (তার কলাম হুবহু নেওয়া হয়েছে)। বাকি ২৮টার কলাম রিপোর্টের নাম আর আমাদের খাতা থেকে ঠিক করা হয়েছে (উপরের টেবিল দেখুন)। কোনো রিপোর্টের কাঙ্ক্ষিত ফরম্যাট / কলাম থাকলে নমুনা দেবেন।

২. **"Actual" বনাম "as per Booking":** এখন "Actual Sewing Done Undone" = এ পর্যন্ত মোট সেলাই বনাম অর্ডার (অর্ডার ধরে), আর "Sewing Done Undone" = বাছাই করা সময়ের মধ্যে সেলাই বনাম অর্ডার (বায়ার ধরে)। "Actual GMT Booking as per Capacity" = আসলে সেলাই হওয়া মিনিট, আর "GMT Booking as per Capacity" = অর্ডারের (বুকড) মিনিট। এই পার্থক্য ঠিক আছে কিনা জানাবেন।

৩. **End Line বনাম Final Inspection:** আমাদের সুইং-এ আলাদা এন্ড লাইন QC নেই; তাই "Daily End Line Quality Inspection" আর "Daily Finishing Final Inspection" দুটোই 20/03-এর কোয়ালিটি ইন্সপেকশন থেকে (প্রথমটা লাইনের সারসংক্ষেপ, দ্বিতীয়টা বান্ডেল ধরে)। "End Table Inspection" ফিনিশিংয়ের আউট থেকে। সুইং লাইনে আলাদা এন্ড লাইন QC লাগবে কিনা জানাবেন।

৪. **Sew S/C Production:** সেলাইয়ের সাব-কন্ট্রাক্ট (বাইরে সেলাই করানো) প্রোডাকশন রাখার কোনো স্ক্রিন / টেবিল এখনো নেই, তাই এই রিপোর্টটা বাকি রাখা হয়েছে। কোন মডিউলে সাব-কন্ট্রাক্ট সেলাই আসবে জানাবেন।


## Sales & Shipment

### `21/01` [Operation](21_Sales_Shipment/01_Operation.md)

১. **"Ship Against Delivered" কোন ডেলিভারি ধরবে:** নিয়মটা এখন সেভ হয়, কিন্তু কাজ করবে 21/02 Order & Delivery আর 21/05 Shipment বানানোর পরে। "নিট ডেলিভারি" মানে ডেলিভারি চালান বাদ চালান রিটার্ন, এভাবেই ধরা হবে। অন্য হিসাব লাগলে জানাবেন।

২. **দুই শিপমেন্ট:** 20/07 Assembly Floor-এর Shipment (ফ্লোরের Ready থেকে, SHP-YYYYMMDD-####) ইতিমধ্যে আছে। 21/05 Sales & Shipment-এর Shipment এই settings-এর প্রিফিক্স/নম্বর (SHP-2026-000001) নেবে। দুটো আলাদা কাগজ থাকবে, নাকি 21/05-এর শিপমেন্টই ফ্লোরের Ready থেকে কমাবে, জানাবেন।

৩. **প্রিফিক্স বদলালে আগের নম্বর:** প্রিফিক্স বদলালে আগের কাগজের নম্বর যেমন ছিল তেমনই থাকে; নতুন প্রিফিক্সে নম্বর ১ থেকে শুরু হয়। Brickland-এও তাই কিনা ডেমো থেকে বোঝা যায়নি।


### `21/02` [Order & Delivery](21_Sales_Shipment/02_Order_Delivery.md)

১. **বিল আর রিটার্ন:** Brickland-এর নিয়ম ধরে বিলযোগ্য = ডেলিভারি − অন্য বিল; ফেরত আসা পরিমাণ বাদ যায় না। ফলে UI চেকে ২০ পিস ফেরতের পরও পুরো ৮০০-র বিল হয়েছে। রিটার্ন বাদ দিয়ে বিল হবে কিনা জানাবেন।

২. **Sales Order Particular-এর মাস্টার:** Brickland-এ কোনো লাইব্রেরি মাস্টার থেকে আসে (Carton Dimension, Poly Thickness, Print Placement)। আমাদের এমন মাস্টার নেই, তাই এই তিনটা নিজে থেকে বসে; নতুন যোগের কোনো স্ক্রিন নেই। কোন স্ক্রিনে রাখা হবে জানাবেন।

৩. **Section-এর তালিকা:** Brickland-এ শুধু ৪টা ("Accessories — Store" ইত্যাদি), সম্ভবত Store ডিপার্টমেন্টের সেকশন। আমরা কোম্পানির সব সেকশন "সেকশন — ডিপার্টমেন্ট" হিসেবে দেখাচ্ছি। শুধু একটা ডিপার্টমেন্টের সেকশন দেখাতে হবে কিনা জানাবেন।

৪. **নম্বরের ধরন:** SO, চালান, গেট পাস, S/C আর বিলের নম্বর Brickland ডেমোতে দেখা যায়নি। এখন দিনভিত্তিক: SO-20261107-0001, DC-…, GP-…, SC-…, CB-…। সংশোধন আর চালান রিটার্ন 21/01-এর settings থেকে। অন্য ধরন লাগলে জানাবেন।

৫. **চালানের লাইন সাইজ অনুযায়ী:** Brickland-এর "SO item" কোন স্তরের, ডেমো খালি থাকায় বোঝা যায়নি। আমরা চালান, রিটার্ন আর বিল সাইজ (আইটেম · রং · সাইজ) অনুযায়ী রেখেছি, যাতে সংশোধনের সাইজ-হিসাবের সাথে মেলে।

৬. **Shipped Qty / Shipment Status:** সেলস অর্ডার তালিকায় এখন 0 / "Not Shipped"। 21/05 Shipment বানানোর পর আসল হিসাব আসবে। তখন মাস বন্ধের "শিপমেন্ট বাকি থাকা অর্ডার" ব্যতিক্রমও যোগ হবে; S/C রিটার্নের ব্যতিক্রম যোগ হবে 21/03-এ।


### `21/03` [Sub-Contract](21_Sales_Shipment/03_Sub_Contract.md)

১. **S/C কন্ট্র্যাক্ট = S/C Programme:** Brickland-এর "Sales Contract" আমরা 21/02-এর S/C Programme ধরেছি (SC No একই)। আলাদা কোনো কন্ট্র্যাক্ট কাগজ লাগলে জানাবেন।

২. **রিসিভের বিল:** Brickland-এর Receive তালিকায় "Bill Status" কলাম আছে, কিন্তু সাব-কন্ট্রাক্টরের বিলের কোনো স্ক্রিন এই মডিউলে নেই। এখন "Not Billed" দেখায়। সাব-কন্ট্রাক্টরের বিল কোথায় হবে (Commercial / Accounts) জানাবেন।

৩. **ডেলিভারি চালানের ক্যানসেল:** Brickland-এ Cancel দেখায় DRAFT / SUBMITTED অবস্থায়, কিন্তু এমন কোনো ধাপ নেই যা SUBMITTED-এ নেয়। আমরা শুধু ড্রাফট ক্যানসেল রেখেছি; ডেলিভারি হয়ে যাওয়া চালান ঠিক করতে হয় রিসিভ, রিটার্ন বা রিকনসিলিয়েশন দিয়ে। ডেলিভারি হওয়া চালানও ক্যানসেল করা দরকার কিনা জানাবেন।

৪. **নম্বরের ধরন:** S/C চালান SCD-…, গেট পাস SGP-…, রিসিভ (MRR) SCR-…, দিনভিত্তিক। Brickland ডেমোতে দেখা যায়নি। রিটার্ন আর রিকন 21/01-এর settings থেকে আসে।


### `21/04` [General](21_Sales_Shipment/04_General.md)

১. **প্ল্যানে বাড়তি (overage):** ডেলিভারি চালানে অর্ডারের "Additional Qty (%)" পর্যন্ত বেশি দেওয়া যায়। কিন্তু Brickland-এর নোটে আছে প্ল্যান "অর্ডারে যা প্ল্যান বাকি" তার বেশি নয়, তাই শিপমেন্ট প্ল্যানে বাড়তি নেই। বাড়তি পরিমাণও শিপমেন্টে প্ল্যান করা যাবে কিনা জানাবেন।

২. **Assembly Floor-এর শিপমেন্টের সাথে সম্পর্ক:** 21/01-এর খোলা প্রশ্ন ২-এর মতোই। Sales & Shipment-এর শিপমেন্ট (SHP-2026-…) আর 20/07-এর ফ্লোর শিপমেন্ট (SHP-YYYYMMDD-…) এখন আলাদা। 21/05-এর কনফার্মেশন ফ্লোরের Ready স্টক থেকে কমাবে কিনা জানাবেন।


### `21/05` [Shipment](21_Sales_Shipment/05_Shipment.md)

১. **Assembly Floor-এর Ready স্টক:** আগের খোলা প্রশ্নের মতোই। শিপমেন্ট কনফার্ম হলে 20/07-এর ফ্লোর Ready স্টক (`asm_fg_ledger`) থেকে এখন কিছু কমে না; Sales & Shipment আর ফ্লোরের হিসাব আলাদা। কনফার্মেশন ফ্লোরের স্টক থেকে কমাবে কিনা জানাবেন।

২. **Documents চেক (21/06-এ সমাধান):** এখন EXP আর B/L বা AWB লাগে; দেখুন 21/06 Export Documentation। আগের প্রশ্ন: settings-এ "Require Export Docs" চালু থাকলে এখন হস্তান্তর বা গ্রহণ হওয়া একটা Commercial Document Handover লাগে। 21/06 Export Documentation বানানোর পর কি EXP আর B/L বা AWB লাগবে (settings-এর ইঙ্গিত যেমন বলে)? আমরা তখন চেকটা সেভাবেই বাড়াব।

৩. **Commercial Invoice-এর সংযোগ:** Brickland-এ ইনভয়েসে Commercial মডিউলের ইনভয়েস বাছাই করা যায়। আমাদের Commercial মডিউলে এখনো ইনভয়েস নেই, তাই এখন লিখে রাখার ঘর। Commercial মডিউল বানানোর পর বাছাইয়ের তালিকা হবে।

৪. **কনফার্মে কোন পরিমাণ shipped হবে:** Brickland অনুযায়ী চূড়ান্ত ইনভয়েসের পরিমাণ shipped হিসেবে লেখা হয় (প্যাকিং বা প্ল্যান নয়)। Qty চেকে প্যাকিং আর ইনভয়েস সমান লাগে; প্ল্যানের চেয়ে কম শিপ হলে বাকিটা অর্ডারে থাকে। এই নিয়ম ঠিক আছে কিনা জানাবেন।


### `21/06` [Export Documentation](21_Sales_Shipment/06_Export_Documentation.md)

১. **Documents চেকে আর কী লাগবে:** এখন "Require Export Docs" চালু থাকলে লাগে একটা EXP আর একটা B/L বা AWB। COO, GSP / REX বা কোনো সার্টিফিকেটও কি বাধ্যতামূলক হবে (যেমন নির্দিষ্ট বায়ার বা দেশের জন্য)? আর ডকুমেন্টকে কি অন্তত "Received" হতে হবে, নাকি Pending অবস্থাতেই গোনা হবে?

২. **Certificate Master:** Brickland-এ Certificate Management একটা "Certificate Master" থেকে ধরন নেয়। আমরা 03-এর Company Documents মাস্টার ব্যবহার করছি, আলাদা মাস্টার বানাইনি। এতে চলবে কিনা জানাবেন।

৩. **চূড়ান্ত হস্তান্তর আর Commercial-এর Document Submission:** Document Handover (ব্যাংক বা বায়ারকে) এখন শুধু শিপমেন্ট পর্যায়ের রেকর্ড। Commercial মডিউলের Export Document Submission বানানোর পর এটা কি সেখান থেকে টানা হবে, নাকি দুটো আলাদা থাকবে?

৪. **বন্ধ শিপমেন্টের ডকুমেন্ট:** শিপমেন্ট বন্ধ হলে কোনো ডকুমেন্ট বা সংযুক্তি যোগ বা বদল করা যায় না। কিন্তু B/L-এর মূল কপি বা ব্যাংকের অনুমোদন অনেক সময় শিপমেন্টের পরে আসে। বন্ধের পরও কি ডকুমেন্টের অবস্থা (Received / Approved) বদলানো আর ফাইল যোগ করা যাবে?


### `21/07` [Report](21_Sales_Shipment/07_Report.md)

১. **২০টা রিপোর্টের কলাম:** Brickland-এর demo-তে ডাটা নেই, আর কলাম সার্ভারে তৈরি হয় (JS-এ নেই)। তাই শুধু "Sales & Shipment Summary (BDT)"-এর ১১টা কলাম হুবহু পাওয়া গেছে। বাকি ২০টা রিপোর্টের কলাম রিপোর্টের নাম, Brickland-এর নোট আর আমাদের টেবিল থেকে ডিজাইন করা। আপনার কাছে আসল রিপোর্টের নমুনা (PDF বা Excel) থাকলে দিন, কলাম মিলিয়ে দেব।

২. **Summary রিপোর্টের Section / Particular:** Brickland-এর মোটের সারিতে "Total · All orders" দেখা যায়, কিন্তু অন্য সারিতে কী থাকে তা দেখা যায়নি। আমরা Section = অর্ডার লাইনের সেকশন আর Particular = আইটেম রেখেছি। এটা কি অর্ডারের ধরন (General / Inter-company / Sub-contract) অনুযায়ী হওয়া উচিত?

৩. **তারিখের ঘর কোন তারিখ ধরবে:** Delivery Pending আর Shipment Pending-এ এখন SO Date ধরে, যাতে ডিফল্ট সময়সীমায় (মাসের ১ তারিখ থেকে আজ) সামনের টার্গেটের অর্ডার বাদ না পড়ে। Days কলামটা টার্গেট তারিখ থেকে। Brickland-এ কি টার্গেট বা শিপমেন্ট তারিখ ধরে ফিল্টার হয়?

৪. **সতর্কতা কাকে পাঠানো হবে:** এখন সতর্কতা শুধু স্ক্রিনে দেখায়। নোটিফিকেশন (বেল আইকন বা ইমেইল) হিসেবেও পাঠানো দরকার কি, আর কোন রোলকে?


## Trade & Export

### `22/01` [Master Data](22_Trade_Export/01_Master_Data.md)

- **Brickland-এর পুরনো ধাঁচের পেজ:** এই পাঁচটা স্ক্রিন Brickland-এর পুরনো লেআউটে: শিরোনাম-বার, fieldset বক্স, সবুজ Save বাটন। এখানে বক্সের নাম, ক্রম আর একই পেজে ফর্ম ও তালিকা রাখার ব্যাপারটা মেলানো হয়েছে, কিন্তু রং আর বাটন অ্যাপের বাকি অংশের মতোই রাখা হয়েছে।


### `22/02` [Operations](22_Trade_Export/02_Operations.md)

১. **সারির কাজ:** Brickland-এর demo-তে এই স্ক্রিনগুলোয় কোনো রেকর্ড নেই, তাই Action কলামে কী কী আছে দেখা যায়নি। আমরা Edit, Finalize আর Delete রেখেছি। ফাইনাল ফেরানোর (Undo) কোনো ব্যবস্থা নেই। লাগবে কিনা জানাবেন।

২. **Export Qty-র উৎস (UD Transfer):** Brickland-এ Export Qty স্ক্রিনে দেখায়, কিন্তু কীভাবে হিসাব হয় দেখা যায়নি; আমরা হাতে লেখার ঘর রেখেছি, আর Process-loss %-ও। কীভাবে আসবে জানাবেন।

৩. **L/C Amendment আর Marge / Replace:** তালিকায় "Amdt." আর "Marge/Replace?" কলাম আর ফিল্টার আছে, কিন্তু সেগুলো কীভাবে বসে তা দেখা যায়নি। এখন ০ আর N/A দেখায়। L/C সংশোধন আর মার্জের নিয়ম জানালে যোগ করব।

৪. **L/C-তে PI বাধ্যতামূলক:** Brickland-এর ফর্মে Export PI No.-এ `*` আছে, তাই অন্তত একটা PI লাগে। PI ছাড়া সরাসরি L/C বা Sales Contract খোলা কি চলবে?

৫. **Realisation-এর হিসাব পোস্টিং:** Detail Distribution শুধু রেকর্ড হয়; GL-এ কোনো ভাউচার পোস্ট হয় না। Finance & Ledger-এর ভাউচার বানানোর পর সংযোগ হবে। ধারাটা (কোন খাত DR, কোন খাত CR) জানালে আগে থেকে সারিগুলো ভরে দেওয়া যাবে।

৬. **Net Amount-এর সূত্র:** Net = Invoiced − Penalty − Agency Commission − Sales Discount ধরা হয়েছে; Freight আলাদা থাকে। ঠিক আছে কিনা জানাবেন।

৭. **Item Group ও Item Type:** Brickland-এর PI-তে Group-এর নিজস্ব তালিকা। আমরা সেই নামগুলো রেখেছি, আর Type আসে Material Procurement ও General Store-এর ক্যাটাগরি থেকে। Export PI-এর জন্য আলাদা মাস্টার লাগবে কিনা জানাবেন।

৮. **Sales & Shipment-এর সংযোগ:** 21/05-এর Shipment Invoice-এ "Commercial Invoice" এখনো লিখে রাখার ঘর। Export Commercial Invoice এখন আছে; ঘরটাকে বাছাইয়ের তালিকা করব, আর শিপমেন্ট কি ইনভয়েসের সাথে সরাসরি যুক্ত হবে?


### `22/03` [Import](22_Trade_Export/03_Import.md)

১. **সারির কাজ:** Brickland-এর demo-তে এখানেও কোনো রেকর্ড নেই। আমরা Edit, Approve / Finalize আর Delete রেখেছি।

২. **IFDBC Paid আর Payment:** Import Bill Acceptance-এর তালিকা আর ফিল্টারে IFDBC Paid Amt, Paid Date, Is Payment Done, Accounting Done আছে, কিন্তু ফর্মে সেগুলো লেখার ঘর নেই। Brickland-এ এগুলো কোন স্ক্রিন থেকে আসে (Finance-এর পেমেন্ট, না CM Payment Receive)? এখন ০ / No দেখায়।

৩. **"Show other information" / "Show Other Info":** Import L/C আর Acceptance ফর্মে এই টিক আছে; demo-তে কী খোলে তা দেখা যায়নি, তাই রাখা হয়নি।

৪. **Bill of Entry-র HS Code:** PO বা MRR-এ HS Code নেই, তাই ফাঁকা দেখায়। কোথা থেকে আসবে?

৫. **Insurance Company:** Brickland-এ ড্রপডাউন, কিন্তু কোনো মাস্টার পাইনি; এখন লিখে রাখার ঘর।

৬. **MRR-এর অবস্থা:** Accessories আর Fabric-এ শুধু Finalized MRR, Yarn-এ Draft বা Cancelled ছাড়া সব ধরা হয়েছে। ঠিক আছে কিনা জানাবেন।


### `22/04` [Cm Payment Receive](22_Trade_Export/04_Cm_Payment_Receive.md)

১. **Retired স্ক্রিন:** Brickland-এ Bill Adjustment আর Margin Closing-এর নামে "(Retired)" / "not-used" লেখা। নিয়ম অনুযায়ী দুটোই পুরো বানানো হয়েছে। এগুলো মেনুতে রাখবেন, না লুকাবেন?

২. **Margin Closing-এর ফর্ম:** Brickland-এর demo-তে শুধু তালিকা আর ফিল্টার পাওয়া গেছে। আমরা এটা Advance (IP) বিল হিসেবে বানিয়েছি, সাথে Accounting Type আর Dr/Cr বণ্টন। Brickland-এর আসল ফর্ম অন্য রকম হলে জানাবেন।

৩. **DFC Margin:** রসিদে DFC Margin লেখা আর মোট দেখানো হয়। এটা কি পরিমাণ থেকে কেটে আলাদা DFC হিসাবে যাবে? এখন শুধু রেকর্ড থাকে।

৪. **রসিদ আর Realisation:** 22/02-এর Export Bill Realisation আর এই CM রসিদ দুটোই বিলের টাকা আসা ধরে। দুটো কি একসাথে বিলের পরিমাণের ভেতরে থাকতে হবে? এখন রসিদের সীমা আলাদা, শুধু রসিদ দিয়ে হিসাব হয়।

৫. **হিসাব পোস্টিং:** রসিদ বা Margin Closing Final হলে Accounts-এ voucher যাবে কি? Accounts মডিউল এলে যোগ করা যাবে।


### `22/05` [Cash Incentive](22_Trade_Export/05_Cash_Incentive.md)

১. **PRC কোথায় রাখা হবে:** Brickland-এর "Manage PRC" Export L/C স্ক্রিনে নিয়ে যায়, কিন্তু সেখানে PRC-র কোনো ঘর নেই। আমরা Export L/C ফর্মের নিচে PRC Info বক্স রেখেছি (PRC ID, ইস্যুর তারিখ, ডকুমেন্ট, আলাদা Save PRC)। অন্য কোথাও রাখতে চাইলে জানাবেন।

২. **Count/Down-এর নিয়ম:** সর্বশেষ আদায়ের তারিখ থেকে ১৮০ দিন ধরে বাকি দিন দেখানো হয়েছে। আসল সময়সীমা কত, আর কোন তারিখ থেকে গোনা হবে (আদায়, না PRC)?

৩. **CIA Notification Date:** Brickland-এ এটা সবসময় ফাঁকা। কোথা থেকে আসবে?

৪. **Finalize-এর শর্ত:** আমরা আদায়ের তারিখ ছাড়া দাবি Final হতে দিইনি। ঠিক আছে কিনা জানাবেন।

৫. **দাবির পরিমাণ:** Regular (4) আর Special (1) মানে কি ৪% আর ১%? হলে আদায়ের মূল্য থেকে দাবির পরিমাণ নিজে থেকে বসবে কিনা? এখন হাতে লেখা হয়।

৬. **দাবি মোছা:** Brickland-এ দাবি মোছার বাটন নেই, তাই আমরাও রাখিনি। ভুল ড্রাফট মোছা লাগবে কিনা জানাবেন।


### `22/06` [Report](22_Trade_Export/06_Report.md)

১. **রিপোর্টের কলাম:** Brickland-এ এই স্ক্রিন শুধু প্যারামিটারের ফর্ম; রিপোর্টের ভেতরটা এখনো বানানো হয়নি ("report bodies are not wired to data yet")। তাই ৩১টা রিপোর্টের কলাম আর হিসাব আমরা নিজেদের ডাটা থেকে ঠিক করেছি (৫ নম্বর অংশে তালিকা)। কোনো রিপোর্টে নির্দিষ্ট কলাম বা ফরম্যাট (যেমন ব্যাংকের ছাপা ফর্ম) লাগলে জানাবেন।

২. **নিষ্ক্রিয় বক্স:** Brickland-এ সব রিপোর্টে সব বক্স খোলা থাকে। আমরা 21/07-এর মতো যে বক্স রিপোর্ট পড়ে না সেটা নিষ্ক্রিয় রেখেছি। সব খোলা চাইলে জানাবেন।

৩. **Fund Forecast-এর তারিখ:** সামনের দিকের রিপোর্ট, কিন্তু ডিফল্ট তারিখ আজ পর্যন্ত। আলাদা ডিফল্ট (যেমন আজ থেকে তিন মাস) চাইলে জানাবেন।

৪. **Weekly Margin & BBLC-র সপ্তাহ:** শনিবার থেকে শুক্রবার ধরা হয়েছে। অন্য দিন চাইলে জানাবেন।

৫. **Cost Against Realization-এর খরচ:** খরচ = Invoice Amount − Collected (ব্যাংকের কাটা), সাথে FBC আর জরিমানা আলাদা কলামে। Lodgement-এর চার্জ বা অন্য খরচ যোগ করতে হবে কিনা?


## Finance & Ledger

### `23/01` [Master Data](23_Finance_Ledger/01_Master_Data.md)

- **কোম্পানির ব্যাংক অ্যাকাউন্ট কোথায় থাকবে:** Brickland-এর Bank Account Master-এর বিবরণে "company accounts" আছে, কিন্তু ডেমোতে অ্যাকাউন্ট রাখার কোনো ট্যাব দেখা যায়নি। চেক বই রাখার জন্য অ্যাকাউন্ট লাগে, তাই এখানে একটা বাড়তি "Company Accounts" ট্যাব যোগ করা হয়েছে।
- **মেকার-চেকার প্রয়োগ:** Voucher Entry (23/02) বানানোর সময় ঠিক হবে মেকার নিজের ভাউচার অনুমোদন দিতে পারবেন কিনা। এখানে শুধু অনুমতিগুলো রাখা আছে।


### `23/02` [Voucher Entry](23_Finance_Ledger/02_Voucher_Entry.md)

১. **General MRR-এর উৎস:** Store & Material-এর GRN ধরা হয়েছে, কারণ General Store-এর সাপ্লায়ার আলাদা মাস্টারে (`gs_suppliers`) আর বিলের সাপ্লায়ার `mp_suppliers` থেকে। General Store-এর রসিদও আনতে হবে কিনা জানাবেন। এখন dev-এর GRN-এ দর না থাকায় মোট ০ আসে।

২. **কোন রসিদ বিলযোগ্য:** Trims শুধু Finalized; Yarn, Dyes, Oil, Needle ড্রাফট বা বাতিল ছাড়া সব; GRN উল্টানো ছাড়া। ঠিক আছে কিনা?

৩. **Yarn-এর একক:** সুতার রসিদে একক আলাদা করে নেই, তাই KG ধরা হয়েছে।

৪. **চূড়ান্ত MRR বিল থেকে ভাউচার:** Brickland-এ Finalize-এর পর চেক লেখা হয়, কিন্তু নিজে থেকে Bill Voucher তৈরি হয় কিনা দেখা যায়নি। চাইলে Finalize-এ Debit To / Credit To দিয়ে BV ড্রাফট বানানো যায়।

৫. **Select Bill-এর কলাম:** Brickland-এ Bill Ref#, REF No আর Bill NO তিনটা আলাদা কলাম, কিন্তু ফর্মে একটাই বিল নম্বর। আমরা দেখিয়েছি: বিল নম্বর, MRR নম্বর আর রসিদের চালান নম্বর। PAY AMT এখন ০ (চেক 23/05-এ আসবে)।

৬. **ব্যাংক লাইন ক্লিয়ার হলে Unpost আটকানো:** ✅ 23/04 Bank Statement Reconciliation-এর সাথে যোগ হয়েছে।

৭. **Maker-Checker ছাড়া অনুমোদন:** কোনো ধরনের grant না থাকলে voucher manage পারমিশনধারী যে কেউ, নিজের ভাউচারও, অনুমোদন করতে পারেন। grant না থাকলেও নিজে অনুমোদন আটকাতে চাইলে জানাবেন।


### `23/03` [Operation](23_Finance_Ledger/03_Operation.md)

১. **পোস্টের আগে অনুমোদন:** Brickland-এর ফর্মে Action Status আছে কিন্তু পোস্টের শর্ত দেখা যায়নি। আমরা শুধু অনুমোদিত (Approved) ভাউচার পোস্ট করতে দিচ্ছি। অনুমোদন ছাড়াও পোস্ট চাইলে জানাবেন।

২. **নম্বর পাওয়া ভাউচার মোছা:** পোস্টের আগে মোছা যায়, তাতে নম্বরের ক্রমে ফাঁক থাকে (লগে মোছা ভাউচার রয়ে যায়)। মোছার বদলে "Cancelled" করতে চাইলে জানাবেন।

৩. **Voucher For:** Brickland-এর ফিল্টারে "-- Select --" দেখায়, অর্থাৎ ধরন অনুযায়ী তালিকা (সাপ্লায়ার, ক্রেতা, কর্মী) হতে পারে। এখন লিখে দেওয়ার ঘর।


### `23/04` [Period Close](23_Finance_Ledger/04_Period_Close.md)

১. **স্টক হেড কোনগুলো:** Brickland-এর demo-তে তালিকা খালি। আমরা ধরেছি Account Type Configuration-এ INVENTORY শ্রেণির হিসাব, আর কোনোটা না থাকলে "Inventories" গ্রুপের নিচের হিসাব। ইনভেন্টরি মডিউলের স্টক থেকে নিজে থেকে মূল্য আনতে চাইলে (যেমন Store / Yarn Store-এর মাসশেষের মূল্য) জানাবেন।

২. **অর্থবছর বন্ধের শর্ত:** বছরে ড্রাফট বা আনপোস্টেড ভাউচার থাকলে বন্ধ হতে দিইনি। Brickland-এ এটা দেখা যায়নি — ঠিক আছে কিনা?

৩. **ক্লোজিং ব্যালান্স কী:** আমরা বছরের লাভ/ক্ষতি (রাজস্ব − খরচ) রেখেছি। আপনারা অন্য কিছু (যেমন বছরশেষের মোট ব্যালান্স) চাইলে জানাবেন।

৪. **লাভ/ক্ষতি পরের বছরে নেওয়া:** বন্ধের সময় Retained Earnings-এ closing ভাউচার বানানো হয় না। দরকার হলে কোন হিসাবে যাবে জানাবেন।

৫. **আবার খোলা:** ক্লোজিং মুছলে বছরটা খোলে। শুধু সর্বশেষ বন্ধ বছর খোলা যাবে, বা আলাদা পারমিশন লাগবে কিনা?


### `23/05` [Cheque Management](23_Finance_Ledger/05_Cheque_Management.md)

১. **"Finalized" কলাম:** Brickland-এর তালিকায় "Is Cheque Print" আর "Finalized" দুটো আলাদা কলাম, কিন্তু ফর্মে Finalize বাটন নেই। আমরা প্রিন্ট হওয়াকেই Finalized ধরেছি। আলাদা Finalize চাইলে জানাবেন।

২. **চেক থেকে ভাউচার:** চেক ইস্যু হলে নিজে থেকে Bank Payment Voucher (Dr কন্ট্রোল হেড, Cr ইস্যুর খাত) তৈরি হবে কিনা? এখন হয় না।

৩. **"Open book":** Brickland বলে "Available leaves on an open book"। dev-এর বইয়ে finalized = false, তাই আমরা বইয়ের অবস্থা না দেখে সব বইয়ের অব্যবহৃত সক্রিয় পাতা দেখাচ্ছি। শুধু চূড়ান্ত (গৃহীত) বই চাইলে জানাবেন।

৪. **Cheque Issue For / Issue For:** কর্মী কোড আর লাইনের "Issue For" এখন লেখার ঘর। HR-এর কর্মী তালিকা বা রিকুইজিশন থেকে বাছার ঘর চাইলে জানাবেন।

৫. **চেকের প্রিন্ট ফরম্যাট:** "Cheque Print" এখন শুধু প্রিন্ট হয়েছে বলে লেখে; ব্যাংকের চেকের পাতায় ছাপার ফরম্যাট (তারিখ, প্রাপক, কথায় অঙ্ক) দরকার হলে ব্যাংক অনুযায়ী মাপ জানাবেন।


### `23/06` [Voucher Audit](23_Finance_Ledger/06_Voucher_Audit.md)

১. **সংশোধনের বিস্তারিত:** Brickland-এর নোট বলে "What a voucher looked like before an edit is not reconstructable from here"। আমরা প্রতিটা কাজের সাথে পুরো ভাউচার রাখি, তাই কোন ঘর বদলেছে তা Note-এ দেখাচ্ছি। আগের আর পরের পুরো ভাউচার পাশাপাশি দেখার পাতা চাইলে জানাবেন।

২. **পুরোনো ভাউচার:** লগ 23/03 থেকে চালু; তার আগের ভাউচারের কাজ নেই। প্রোডাকশনে এটা কোনো সমস্যা নয়, কারণ সেখানে শুরু থেকেই লগ থাকবে।

৩. **Excel / PDF:** Brickland-এ শুধু Activity Trail-এ PDF আর Excel; অন্য দুটোয় নেই, আমরাও রাখিনি (সব তালিকার নিজস্ব Export আছে)।


### `23/07` [Reports](23_Finance_Ledger/07_Reports.md)

১. **রিপোর্টের নামের বানান:** Brickland-এ "Ledger Summery", "Subsidairy Ledger", "Acccounting Misc. Report" — আমরা বানান ঠিক করে দেখাচ্ছি (Ledger Summary, Subsidiary Ledger, Accounting Misc. Report)। Brickland-এর হুবহু বানান চাইলে জানাবেন।

২. **বিলের অবস্থা:** বিল চেনা হয় হিসাব খাত + বিল নং দিয়ে (লাইনের বিল নং, না থাকলে ভাউচারের)। বড় দিকটা বিল হয়েছে, ছোট দিকটা নিষ্পত্তি ধরা হয়েছে — প্রাপ্য আর প্রদেয় দুই খাতেই খাটে। অন্য নিয়ম চাইলে জানাবেন।

৩. **ব্যালান্স শিটের স্তর:** উপ-শ্রেণি (চার্টের দ্বিতীয় স্তর) পর্যন্ত গুটানো, আর বছরের লাভ/ক্ষতি মালিকানা স্বত্বে একটা লাইন হিসেবে। বিস্তারিত (Control বা Ledger স্তরে) চাইলে জানাবেন।

৪. **Preview-ও ইতিহাসে:** Brickland-এর নোট অনুযায়ী প্রতিটা চালানোই রেকর্ড, তাই Selection of Parameters-এর Preview-ও Run History-তে ওঠে।


## HR & Payroll

### `24/01` [Master Data](24_HR_Payroll/01_Master_Data.md)

- **কর্মী কে:** Employee মাস্টার (24/03) না আসা পর্যন্ত Allowance আর Deduction কোম্পানির ইউজার (employee_code) দিয়ে চলে। ওই মাস্টার এলে `user_id` কর্মীর foreign key-তে বদলাবে।
- **Designation-এর শ্রেণি:** Company Management-এর `designations.employee_category` এখনো লেখা ঘর। কর্মী শ্রেণি (`hr_designation_groups`) এসে গেছে, তাই Employee মাস্টারের সময় এটা foreign key করা যাবে।
- **GL Mapped:** Salary Processing (24/06) যখন হিসাবের খাতায় পোস্ট করবে, তখন খাতগুলো লেজারের সাথে যুক্ত হবে।
- **পুরনো Excel (.xls):** Brickland-এর লেবেলে .xls আছে, কিন্তু এখানে শুধু .xlsx পড়া যায়। .xls দিলে "ফাইলের ধরন মিলছে না" মেসেজ আসে।


### `24/02` [HR & Payroll](24_HR_Payroll/02_Hr_Payroll.md)

১. **Gross B রিপোর্ট (৪৮, ৪৯):** Brickland পরে "Complex Management" নামে ৮টা স্ক্রিনের নতুন ফোল্ডার যোগ করেছে, যেখানে Gross B পেরোল হয়। আমাদের ক্রলে সেটা নেই। তাই এখন Gross B রিপোর্ট Brickland-এর মতোই "Not processed in Complex" দেখায়। Complex Management বানাব কিনা জানাবেন।

২. **জনবলের ধরন:** Operator / Quality / Helper / Supervisor / Staff বের হয় পদবির সমান নামের Designation Group থেকে; না মিললে পদবির নাম দেখে (helper, quality, supervisor, line chief, operator …); তাতেও না মিললে Worker → Operator, অন্যরা → Staff। পদবিতে সরাসরি ধরন বসানোর ঘর লাগবে কিনা জানাবেন।

৩. **Bank Salary Sheet:** কর্মীর বেতনের ব্যাংক অ্যাকাউন্ট (ব্যাংক, শাখা, অ্যাকাউন্টের নাম ও নম্বর) রাখার ঘর এখনো নেই, তাই এসব কলাম "—" আর সবাই নগদ। কর্মীর প্রোফাইলে এই ঘর যোগ করব কিনা জানাবেন।

৪. **Latest Notifications:** Brickland HR-এর নিজের ঘটনা (কর্মী নিবন্ধন, প্রোফাইল বদল) দেখায়। আমাদের এখানে ব্যবহারকারীর নিজের নোটিফিকেশন। HR-এর ঘটনার আলাদা লগ চাইলে জানাবেন।


### `24/03` [Employee](24_HR_Payroll/03_Employee.md)

- **"manpower updates as you save":** Brickland বলে সেভ করলেই জনবলের হিসাব বদলায়। এখানে line-এর `manpower` ঘর Planning মডিউল নিজে ব্যবহার করে, তাই সেটা এখন বদলানো হয় না। গোনা হয় কর্মীর posting থেকে। Planning-কে কর্মীর সংখ্যা থেকে নিতে হবে কিনা, তা Planning-এর পালায় ঠিক হবে।

- **Employee Profile-এর Allowance/Deduction আর Back Ground Check:** Brickland-এ এই দুটো ট্যাব আলাদা প্যানেল — ভেতরে আবার যেকোনো কর্মী বাছা যায় (সব কর্মীর তালিকা)। এখানে দুটোই যে কর্মীর প্রোফাইল খোলা তাঁরই, আর প্রোফাইলের সাথে একবারের সেভে যায়। আলাদা "সব কর্মীর" প্যানেল চাইলে জানাবেন।
- **Employee Code:** Brickland-এ কোড হাতে লেখা হয় (GMT1315577)। এখানে Quick Registration-এর মতোই স্বয়ংক্রিয় (EMP-000001), বদলানো যায় না। হাতে কোড দিতে চাইলে জানাবেন।
- **Proxi# বাধ্যতামূলক:** Brickland-এ ঐচ্ছিক, এখানে বাধ্যতামূলক (Quick Registration-এর মতো)।
- **Gross Salary নতুন কর্মীর জন্য বাধ্যতামূলক:** বেতনের ভাগ আর salary রেকর্ড এখান থেকেই হয়।

- **ID Card-এর Mtex English:** Brickland-এর ডেমোতে Mtex English কার্ডের নমুনা দেখা যায়নি (কিউর একমাত্র কার্ডটা Bangla)। এখানে এটাকে খাড়া (portrait) ইংরেজি কার্ড ধরা হয়েছে। আসল Mtex নকশা অন্যরকম হলে একটা নমুনা দিলে মিলিয়ে দেব।
- **কার্ডের পেছনের দিক:** Brickland একদিকই ছাপে; এখানেও তাই। পেছনে ঠিকানা, জরুরি নম্বর বা হারানো কার্ড ফেরতের নির্দেশ চাইলে জানাবেন।

- **Biometric-এর PIN:** কার্ড নং-ই PIN (Brickland-এর মতো)। কার্ড না থাকলে কর্মী কোডের সংখ্যা (EMP-000004 → 000004) — যন্ত্র শুধু সংখ্যা নেয়। অন্য নিয়ম চাইলে জানাবেন।
- **যন্ত্রে আঙুল নেওয়া:** Queue enrolment কর্মীকে ইউজার হিসেবে যন্ত্রে পাঠায়; আঙুলের ছাপ যন্ত্রেই দিতে হয় (অপারেটর যন্ত্রের মেনু থেকে), তারপর যন্ত্র নিজে জানায়। ERP থেকে যন্ত্রকে "এখন আঙুল নাও" (ENROLL_FP) বলা সব ফার্মওয়্যারে চলে না, তাই রাখা হয়নি।


### `24/04` [Attendance & Shift](24_HR_Payroll/04_Attendance_Shift.md)

১. **OT কে পায়:** ✅ মিটেছে — 24/05-এর OT Eligibility থেকে আসে (গ্রেডের শ্রমিকের General OT, চালু করা স্টাফের Worker OT)।

২. **OT-র হিসাব পুরো ঘণ্টায়:** ✅ মিটেছে — 24/05-এর OT সীমায় রাউন্ডিং, গ্রেস, বিরতি, ন্যূনতম আর সর্বোচ্চ বদলানো যায়।

৩. **সাপ্তাহিক ছুটি:** রোস্টার না থাকলে শুক্রবার। কারখানায় আলাদা দিন থাকলে (বা Payroll Settings-এ রাখতে চাইলে) জানাবেন।

৪. **Leave:** ✅ মিটেছে — 24/05-এ অনুমোদিত ছুটির দিনগুলো হাজিরায় নিজে থেকেই Leave হয়।

৫. **কর্মীর শিফট:** খোলা রোস্টার (আজ বা আগে থেকে) কর্মীর চলতি শিফট — তবে `hr_employees.shift_id`-এ লেখা হয় না, কারণ সেটা Calendar-এর শিফট টেবিলের দিকে নির্দেশ করে, HR-এর `hr_shifts`-এর দিকে নয়।

৬. **Brickland-এর নতুন স্ক্রিন:** ক্রলের পরে Brickland-এ Monthly Attendance Summary (এখানে বানানো), OT Correction / Eligibility / Request / Approval, Final Settlement, Lefty Management আর একটা Complex-* গ্রুপ যোগ হয়েছে। বাকিগুলো নিজ নিজ সাব-মডিউলে (24/05, 24/08 ইত্যাদি) বানানো হবে।


### `24/05` [Leave & Overtime](24_HR_Payroll/05_Leave_Overtime.md)

১. **শিফটের "OT Approval" ঘর:** seed করা সব শিফটে এটা চালু, কিন্তু Brickland হাজিরা থেকে OT নিজেই দেয় (OT Correction-এ ৫১১ জনের OT, কোনো অনুরোধ ছাড়া)। তাই এখানে অনুরোধ ছাড়াও OT হয়; অনুমোদিত অনুরোধ থাকলে শুধু তার সময়ের ভেতরেরটুকু। শিফটে "OT Approval" চালু থাকলে অনুরোধ ছাড়া OT একেবারে বন্ধ রাখতে চাইলে জানাবেন।

২. **অপেক্ষমাণ ছুটি:** Brickland-এর Leave স্ক্রিনে এডিট বা Approve নেই — Pending সেভ করলে পরে অনুমোদনের পথ নেই (মুছে আবার লিখতে হয়)। Approval Center-এ ছুটির অনুমোদন চাইলে জানাবেন।

৩. **ছুটির হিসাব:** ছুটির বছর = অর্থবছরের ক্যালেন্ডার অংশ (জানুয়ারি–ডিসেম্বর); এক দিন = ৮ ঘণ্টা; Earn Leave-এ accrual চালু করলে প্রতি ১৮ দিন উপস্থিতিতে ১ দিন। আলাদা নিয়ম হলে জানাবেন।

৪. **Adjusted (সমন্বয়):** এখানে আগের বছর থেকে carry forward। Brickland-এ হাতে সমন্বয়ের আলাদা খাতা থাকলে সেটা দেখা যায়নি — দরকার হলে জানাবেন।

৫. **Records-এ Status কলাম:** Brickland-এর কলাম তালিকায় নেই, কিন্তু টেবিলের বর্ণনায় "status" আছে; Pending আর Approved আলাদা চেনার জন্য রাখা হয়েছে।

৬. **চূড়ান্ত পে-রোলে তালা:** ✅ মিটেছে — 24/06-এ Brickland-এর মতো তালা নেই; চূড়ান্ত স্লিপ "Adjustment Required" হয়, পার্থক্য Payroll Adjustment-এ তোলা হয়।


### `24/06` [Salary Processing](24_HR_Payroll/06_Salary_Processing.md)

১. **কর্মীর বেতনের ব্যাংক হিসাব:** Brickland-এর Bank Payment ট্যাব "assigned salary bank account" চায়, কিন্তু Brickland-এর কোথায় এটা দেওয়া হয় পাওয়া যায়নি; Employee Profile বা Bank Account Master কোনোটাতেই নেই। এখন সবাই নগদে। কোথায় দেওয়া হবে জানালে Bank Payment ট্যাব ভরবে।

২. **লোন / অগ্রিম:** লোনের কোনো স্ক্রিন এখনো নেই, তাই ০। লোন মডিউল এলে কিস্তি এখানে কাটা যাবে।

৩. **Gross B (Complex Management):** Brickland-এর HR-এ পরে একটা Complex-* গ্রুপ এসেছে। সেটা না বানানো পর্যন্ত Gross B শুধু কর্মীর Gross B দেখায়, "Not processed in Complex"।

৪. **স্ট্যাম্প চার্জ:** Brickland প্রতিটা স্লিপে ৳১০ কাটে ("Stamp Charge")। এখানেও স্থির ৳১০; Payroll Settings-এ বদলানো যায় না। বদলানো যাক চাইলে জানাবেন।

৫. **নিট রাউন্ডিং:** Payroll Settings-এর ROUND_NET_TO (১) মেনে নিট পুরো টাকায় রাউন্ড হয়; Brickland-এর ডেমোতে পয়সাসহ দেখায়। পয়সাসহ রাখতে চাইলে জানাবেন।

৬. **বোনাসের %:** dev / Brickland-এর নিয়মে ০.৫ আর ১.০ — এগুলো ভগ্নাংশ ধরা হয়েছে (০.৫ = Basic-এর অর্ধেক)। ১-এর বেশি লিখলে শতাংশ (৫০ = ৫০%)। Designation Group আর Shift Team দেওয়া নিয়ম এখন মেলে না (কর্মীর সাথে এগুলোর যোগ নেই)।

৭. **উৎসবের ন্যূনতম চাকরি:** Festival Calendar-এর "Minimum Service" দিন ধরা হয়েছে (Brickland কার্ডে "N days' service" লেখে), আর বোনাস নিয়মের চাকরির সীমা মাসে।

৮. **হাজিরা না থাকা দিন:** মাসিক বেতনে কাটা হয় শুধু Absent আর বিনা বেতনের ছুটির দিন; যেদিনের হাজিরা নেই সেদিন কাটা হয় না। দৈনিক বেতনে হাজিরা না থাকা দিনে বেতন হয় না।


### `24/07` [Employee Movement](24_HR_Payroll/07_Employee_Movement.md)

১. **পদোন্নতি / ইনক্রিমেন্টের অনুমোদন কোথায়:** Brickland-এ অনুমোদন এই স্ক্রিনেই (Approval Center-এ নয়)। এখানেও তাই; FACTORY_ADMIN আর FACTORY_MANAGER পারেন। Approval Center-এর workflow দিয়ে চাইলে জানাবেন।

২. **প্রত্যাহার করা অনুরোধ:** Withdraw করলে অনুরোধ মুছে যায়, তাই তার নম্বর পরের অনুরোধ আবার পায়। নম্বর ধরে রাখতে চাইলে জানাবেন (তাহলে "Withdrawn" অবস্থা লাগবে)।

৩. **পুনঃযোগদানের আগের ফাঁক:** কর্মী আগের রেকর্ডেই ফেরেন, যোগদানের তারিখ একই থাকে। ছেড়ে যাওয়া আর ফেরার মাঝের দিনগুলো হাজিরায় Absent দেখাতে পারে। পুনঃযোগদানের তারিখকে নতুন চাকরির শুরু (বোনাস, ছুটি) ধরতে চাইলে জানাবেন।

৪. **পুনঃযোগদানের পে পিরিয়ড:** এখন শুধু রেকর্ডে থাকে। মাসিক বেতনে কর্মী এমনিতেই আসেন, কারণ তিনি আবার Active আর বিচ্ছেদের তারিখ নেই।


### `24/08` [Separation](24_HR_Payroll/08_Separation.md)

১. **ছুটির নগদায়নের হার:** অর্জিত Earn Leave-এর দিন × Gross A ÷ ৩০ ধরা হয়েছে। Brickland-এর ডেমোতে নগদায়ন সবসময় ০ ("no encashable leave balance") দেখা গেছে। Basic দিয়ে হিসাব (বা অন্য নিয়ম) চাইলে জানাবেন।

২. **নোটিশ ঘাটতির কর্তন:** শ্রম আইনে নোটিশ কম হলে বেতন কাটার নিয়ম আছে। এখানে কর্তন হাতে লিখতে হয়, নিজে থেকে হিসাব হয় না। নিজে থেকে চাইলে জানাবেন।

৩. **Release-এর সেটেলমেন্টের ধরন:** Release হলে ধরন "Resign From Job" ধরা হয়েছে (Brickland-এর ধরনে Release নেই)। Retirement চাইলে জানাবেন।

৪. **লোন আদায়:** লোনের রেজিস্টার নেই, তাই ০ ("nothing outstanding")। লোন মডিউল এলে এখানে বকেয়া কিস্তি আসবে।

৫. **Final Settlement-এর অনুমোদন:** Brickland-এর মতো স্ক্রিনের সারি থেকেই। Approval Center দিয়ে চাইলে জানাবেন।


### `24/09` [Letter Management](24_HR_Payroll/09_Letter_Management.md)

১. **Send to Email:** এখন চিঠির PDF নতুন ট্যাবে খোলে আর মেইল অ্যাপ (mailto) কর্মীর ইমেইল, বিষয় ও লেখা সহ খোলে; PDF হাতে সংযুক্ত করতে হয়। সার্ভার থেকে PDF সংযুক্ত করে সরাসরি পাঠাতে চাইলে জানাবেন (মেইল সার্ভার সেটআপ লাগবে)।

২. **বাকি ১৫টা ধরনের টেমপ্লেট:** Brickland-এর ডেমোতে শুধু Appointment Letter-এর টেমপ্লেট আছে, তাই সেটাই দেওয়া হয়েছে। Promotion, Transfer, Increment, Experience ইত্যাদির লেখা HR Letter Master-এ লিখবেন — নাকি নমুনা লেখা দিয়ে দেব?

৩. **চিঠি বাতিল:** তৈরি হওয়া চিঠি মুছা বা বাতিল (void) করা যায় না; ভুল হলে নতুন চিঠি নতুন রেফে হয়। বাতিলের অপশন লাগলে জানাবেন।

৪. **Movement থেকে নিজে থেকে চিঠি:** Promotion / Transfer / Increment অনুমোদন হলে চিঠি নিজে থেকে তৈরি হবে কিনা — এখন Generate Letter থেকে হাতে করতে হয়।


## Buyer Gateway

### `25/01` [Access & Onboarding](25_Buyer_Gateway/01_Access_Onboarding.md)

১. **বায়ারের আসল পোর্টাল লগইন:** Brickland-এর এই স্ক্রিনগুলো "Demo data" — ভেতরের অ্যাডমিন তালিকা। এখানে সেগুলো পুরো কাজ করে (আসল টেবিল, আমাদের buyer master), কিন্তু বায়াররা নিজেরা ঢুকবে এমন আলাদা পোর্টাল (লগইন পেজ, বায়ারের নিজের অর্ডার / স্যাম্পল / শিপমেন্ট দেখা) এখনো নেই। তাই "Last seen" হাতে লেখা হয়, আর LOGIN switch শুধু অনুমতিটা রাখে। আলাদা বায়ার পোর্টাল বানাব কিনা জানাবেন।

২. **নোটিফিকেশন পাঠানো:** Notification Settings পছন্দগুলো রাখে, কিন্তু পোর্টাল না থাকায় এখনো কোনো মেইল বা পোর্টাল-বার্তা যায় না। পোর্টাল হলে এই পছন্দ মেনে পাঠানো হবে।

৩. **Onboarding-এর বায়ার:** Brickland-এর মতো লেখা নাম (Next Retail …), কারণ নতুন বায়ার তখনো master-এ নাও থাকতে পারে। Live হলে buyer master-এ নিজে থেকে বায়ার তৈরি হবে কিনা জানাবেন।


### `25/02` [Order & Shipment Visibility](25_Buyer_Gateway/02_Order_Shipment_Visibility.md)

১. **স্যাম্পল আর শিপমেন্ট নিজে থেকে আসবে কিনা:** Brickland-এর মতো এখানে স্যাম্পল আর শিপমেন্ট হাতে লেখা হয়। আমাদের Merchandise / Prototype-এ স্যাম্পল আর Sales & Shipment-এ শিপমেন্ট আছে। সেখান থেকে নিজে থেকে এই তালিকায় আনব (আর বায়ারের জন্য প্রকাশ করার আগে যাচাই রাখব) কিনা জানাবেন।

২. **Shared Document Vault-এর File কলাম:** Brickland-এ ফাইল নেই, আকার হাতে লেখা হয়। এখানে আসল ফাইল সংযুক্ত করা যায়, তাই তালিকায় একটা "File / Download" কলাম বেশি আছে। না চাইলে সরিয়ে দেব।

৩. **ডাউনলোডের সংখ্যা:** বায়ারের পোর্টাল না থাকায় ডাউনলোড এখনো হাতে লেখা হয়। পোর্টাল হলে বায়ার যতবার নামাবে, নিজে থেকে গোনা হবে।


### `25/03` [Pricing & Billing](25_Buyer_Gateway/03_Pricing_Billing.md)

১. **RFQ থেকে ইনকোয়ারি:** "Converted" এখন শুধু অবস্থা বদলায়। Converted হলে Merchandise-এ নিজে থেকে ইনকোয়ারি তৈরি হবে কিনা জানাবেন।

২. **ইনভয়েস আর পেমেন্ট কোথা থেকে:** Brickland-এর মতো এখানে হাতে লেখা হয়। আমাদের Sales & Shipment / Trade & Export-এ কমার্শিয়াল ইনভয়েস আর Finance-এ প্রাপ্তি আছে। সেখান থেকে নিজে থেকে আনব (আর বায়ারকে প্রকাশের আগে যাচাই রাখব) কিনা জানাবেন।

৩. **মুদ্রা:** Brickland-এর মতো সব USD-তে। অন্য মুদ্রায় বিল হলে মুদ্রার ঘর লাগবে কিনা জানাবেন।


### `25/04` [Buyer Engagement](25_Buyer_Gateway/04_Buyer_Engagement.md)

১. **অভিযোগ কোন শিপমেন্টের:** শিপমেন্ট এখন লেখা ঘর। Sales & Shipment-এর আসল শিপমেন্ট থেকে বাছাই করব কিনা জানাবেন। Quality-র CAPA-তে নিজে থেকে পাঠাব কিনা, সেটাও জানাবেন।

২. **জরিপের প্রশ্ন:** Brickland-এর মতো শুধু সারাংশ রাখা হয় — প্রাপক, উত্তর, গড় স্কোর। আসল প্রশ্নপত্র (প্রশ্ন, বায়ারের প্রতিটা উত্তর) লাগবে কিনা জানাবেন।

৩. **ডেস্ককে জানানো:** অনুরোধ কোনো ডেস্কে পাঠালে বা টিকিট কারও দায়িত্বে দিলে এখন কোনো নোটিফিকেশন যায় না। পাঠাব কিনা জানাবেন। ঘোষণার "পড়া" এখন হাতে লেখা; আসল পোর্টাল চালু হলে নিজে থেকে গোনা হবে (25/01-এর প্রশ্ন দেখুন)।


### `25/05` [Portal Administration](25_Buyer_Gateway/05_Portal_Administration.md)

১. **ব্যবহার কোথা থেকে আসবে:** Brickland-এর মতো এখন হাতে লেখা হয়। আসল বায়ার পোর্টাল চালু হলে লগইন, ডাউনলোড আর সেশন নিজে থেকে গোনা হবে কিনা জানাবেন (25/01-এর প্রশ্ন দেখুন)। ব্যবহারের হারও তখন সক্রিয় ইউজার ÷ পোর্টাল ইউজার থেকে হিসাব করা যাবে।

২. **ব্র্যান্ডিং কোথায় দেখাবে:** প্রোফাইল এখন শুধু রাখা থাকে। বায়ার পোর্টাল আর বায়ারকে পাঠানো ইমেইলে লোগো আর রং লাগানো হবে কিনা জানাবেন। "গ্রুপের ডিফল্ট" কোনটা, সেটাও জানাবেন (কোম্পানির নিজের লোগো?)।

৩. **খতিয়ানে কে:** এখন নাম লেখা হয়। 25/01-এর পোর্টাল ইউজার থেকে বাছাই করার ব্যবস্থা লাগবে কিনা জানাবেন।


## AI File & PO Import

### `26/01` [Intake](26_AI_File_PO_Import/01_Intake.md)

তিনটার উত্তরই পাওয়া গেছে (২০২৬-১১-১২), আর বানানো হয়েছে — নিচের "আসল AI দিয়ে পড়া" দেখুন:
১. **আসল AI দিয়ে ফাইল পড়া:** হ্যাঁ। সব AI-এর জন্য API key লাগবে; কোন AI কাজ করবে, তা আলাদা settings থেকে ঠিক হবে (Claude, OpenAI, Gemini)। আপলোড হলেই নিজে থেকে পড়বে, settings থেকে বন্ধ করা যায়।
২. **আর কোন ধরনের ফাইল:** হ্যাঁ — ইমেইল (.eml/.msg), CSV আর Word (.docx) নেওয়া হয়।
৩. **অনুমোদন কোথায়:** হ্যাঁ — ERP-র Approval Center-এ (entity `IMPORT_FILE`)।


## Command Center

### `27/01` [Command Center](27_Command_Center/01_Command_Center.md)

১. **বোর্ড আর কম্পোজিশন কি সত্যি ড্যাশবোর্ড দেখাবে:** Brickland-এর মতো এখন এগুলো শুধু রেজিস্টার (নাম, টাইলের সংখ্যা, দর্শক)। 27/02-এর KPI লাইব্রেরি থেকে টাইল বেছে আসল বোর্ড বানানো আর দেখানো হবে কিনা জানাবেন। "ডাটার মান"-ও তখন হাতে না লিখে নিজে থেকে হিসাব করা যাবে।

২. **Command Deck-এর সংজ্ঞা ঠিক আছে কিনা:**
- রাজস্ব এখন শুধু রপ্তানির কমার্শিয়াল ইনভয়েস থেকে। Sales & Shipment-এর দেশীয় বিক্রি ধরা হবে কিনা জানাবেন।
- "বিভাগ অনুযায়ী উৎপাদন" এখন শুধু সেলাই ফ্লোর থেকে। নিটিং, ডাইং আর কাটিং-এর উৎপাদন যোগ হবে কিনা জানাবেন। তাদের একক আলাদা (কেজি, পিস), তাই শতাংশে দেখাতে হবে।

৩. **কোন সতর্কতা "প্রধান":** এখন অর্ডারের AI সতর্কতা আর অপেক্ষমাণ অনুমোদন দেখায়। মেশিন ব্রেকডাউন, স্টক শেষ হওয়া ইত্যাদিও যোগ হবে কিনা জানাবেন।


## Production Tracking

### `30/02` [Dashboards](30_Production_Tracking/02_Dashboards.md)

- **Cutting-এর Planned bundles:** ERP পরিকল্পিত বান্ডেলের সংখ্যা রাখে না, তাই Planned/Remaining/Ready % Production Master Data-র কাটিং টার্গেট (পিস) থেকে আসে; KPI-তে "PCS" লেখা আছে। বান্ডেল সংখ্যায় চাইলে কাটিং প্ল্যানে বান্ডেল টার্গেট রাখার জায়গা লাগবে।


### `30/06` [Production Tracking](30_Production_Tracking/06_Production_Tracking.md)

- **পদ্ধতি B আর পরিকল্পিত ঘণ্টা:** "Fixed Daily Capacity"-তে টার্গেট = দৈনিক ক্যাপাসিটি, পরিকল্পিত ঘণ্টা কম দিলেও কমে না (যেমন ৬ ঘণ্টায়ও ১,০০০)। ঘণ্টার অনুপাতে কমবে কিনা ব্যবসা ঠিক করবে।
- **কারখানার টাইমজোন:** দিন আর ঘণ্টা `Asia/Dhaka` ধরে; অন্য দেশের কারখানা হলে কোম্পানি-ভিত্তিক টাইমজোন লাগবে।


## Dev Sample Status

### `31/01` [Dev Sample Status](31_Dev_Sample_Status/01_Dev_Sample_Status.md)

- **টিম লিডার:** Norban-এ টিম লিডার নিজের টিমের সবার স্যাম্পল দেখে। AnnotechERP-এ এখন `view_all` (সবার) আর নিজের — এই দুটো। টিমভিত্তিক দেখা লাগলে কোন টিম (HR ডিপার্টমেন্ট?) ধরে হবে?
- **নতুন আইটেম/রঙ লিখে যোগ:** Norban-এ আইটেম ঘরে নতুন নাম লিখে যোগ করা যায়। এখানে আইটেম আর রঙ শুধু মাস্টার থেকে বাছাই; নতুন হলে আগে মাস্টারে যোগ করতে হবে। চলবে কিনা।


## Order Summary

### `32/01` [Order Summary](32_Order_Summary/01_Order_Summary.md)

- **Discount % ব্যাখ্যা:** Norban-এর স্ক্রিন Final Discount/Penalty % ১-এর বেশি হলে শতাংশ, নইলে ভগ্নাংশ ধরত (৫ আর ০.০৫ দুটোই ৫%)। এখানে সবসময় শতাংশ (৫ = ৫%)। ঠিক আছে কিনা।
- **Fabric Details:** Norban-এ চারটা কলাম জুড়ে তৈরি; এখানে JOIN ফর্মুলা, Column Setup থেকে বদলানো যাবে।

- **Norban-এর আসল তালিকা:** ড্রপডাউনের OS_* তালিকাগুলোয় Norban-এর দেখা মান দিয়ে শুরু; Norban-এর MySQL চালু থাকলে পুরো তালিকা আনা যায়। লাগবে কিনা।
- **মেইল সার্ভিস:** মেইল যেতে Access Control → Mail Service Configuration-এ SMTP সার্ভিস লাগবে (পাসওয়ার্ড environment variable-এ)। dev-এ নেই, তাই এখনো আসল মেইল পাঠানো যাচাই হয়নি (টেস্টে Mail::fake দিয়ে যাচাই)।


## Quality Tally

### `36/01` [Quality Tally](36_Quality_Tally/01_Quality_Tally.md)

1. **Inline QC বনাম End-line QC:** rmg-তে একই ফ্লোরে কয়েকটা চেকপয়েন্ট ছিল, আউটপুট গুনত একটা। এখানে প্রতি সেকশনে লাইনই চেকপয়েন্ট। Sewing-এ inline আর end-line আলাদা লাগবে কি?
2. **ERP-র সাথে যুক্ত করা:** পরে Job কি Merchandise order / Order Summary থেকে আসবে, আর Quality-র পাস কি Production Tracking-এর আউটপুট হবে?
3. **26DAL00101 / 26DAL00102-এর আসল ওপেনিং:** আগের ট্যাপ মিলিয়ে ওপেনিং বসানো হয়েছে (101: কাটিং 10; 102: কাটিং 6, সুইং 3)। আসল সংখ্যা Jobs → Edit → Opening pieces-এ বসাতে হবে।

4. **"কমপ্লিট"-এর সংজ্ঞা (2026-11-28):** রিজেক্ট হওয়া পার্ট আর পাস হবে না — তাই কমপ্লিট ধরা হয়েছে "সব পার্ট চেক শেষ, কোনো ডিফেক্ট খোলা নেই" (রিজেক্ট বাদ দিয়ে বাকিটা পিস হিসেবে যায়)। যদি চান রিজেক্টের বদলি পার্ট আবার চেক হয়ে পাস না হওয়া পর্যন্ত আটকে থাকবে, বা অর্ডার Qty-র সমান পিস না হওয়া পর্যন্ত — জানাবেন। আরও: বান্ডেল এখনো সব ঢোকানো না হলে (Cutting Input চলছে) কিন্তু ঢোকানোগুলো সব কমপ্লিট হলে সুইং খুলে যায় — বান্ডেল Qty ≥ অর্ডার Qty-ও শর্ত হবে কি না?

## ১২. দ্বিতীয় ধাপের পরিকল্পনা (rmg-এর বাকি অংশ; user 2026-11-24 "প্লানিং করে শুরু করো")

ক্রম: আগে ভিত্তি (হিসাব ঠিক করে), তারপর রিপোর্ট, তারপর লাইভ, তারপর অ্যাক্সেস, শেষে Final Inspection ও Route। প্রতিটা ফেজ শেষে: টেস্ট, ব্রাউজারে নিজে ক্লিক করে দেখা, dev ডাটা রেখে দেওয়া।

**ফেজ A — হিসাবের ভিত্তি**
- A1 Job-এ `size` ও `due_date` (জবের পরিচয় = job no + PO + colour + size)।
- A2 শিফট: HR & Payroll-এর শিফট (`hr_shifts`) থেকে; ট্যাবলেটে শিফট বাছাই (চলতি শিফট আপনা থেকে), প্রতিটা ট্যাপে `shift_id`; রাত পার হওয়া শিফটের মাঝরাতের পরের ট্যাপ শিফট শুরুর দিনে গোনা হবে।
- A3 Line Attendance স্ক্রিন: তারিখ + সেকশন ধরে সব লাইনের উপস্থিতি ও ওভারটাইম মিনিট (শিফট অনুযায়ী), একবারে সেভ। Efficiency = পাস × SMV ÷ Σ(উপস্থিতি × (শিফটের মিনিট + OT)); হাজিরা না থাকলে টার্গেটের ম্যানপাওয়ার × ঘণ্টা।
- A4 টার্গেট একবারে (Plan the day গ্রিড) + Shortfall: আগের নির্ধারিত দিনের ঘাটতি দেখানো, এক ক্লিকে যোগ — নিজে থেকে যোগ হয় না।

**ফেজ B — অ্যানালিটিক্স ও রিপোর্ট**
- B1 Quality Analytics স্ক্রিন: তারিখের রেঞ্জ (≤৯০ দিন) — দৈনিক DHU/RFT/পাস ট্রেন্ড, টার্গেট বনাম অর্জন, লাইন-ওয়ারি, top defects, QC-ওয়ারি পারফরম্যান্স।
- B2 Reports (Reporting মডিউলের রিপোর্ট জব, PDF/Excel): Tally Sheet, Daily Quality Summary, Defect Analysis, QC Performance।

**ফেজ C — লাইভ**
- C1 রিয়েলটাইম: ট্যাপ হলেই ড্যাশবোর্ড/ট্যাবলেট আপডেট (Reverb)।
- C2 TV ডিসপ্লে: লাইন বা সেকশনের বোর্ড, লগইন ছাড়া একটা লিংকে (Production Tracking-এর মত লিংক দেওয়া/বাতিল)।
- C3 অ্যালার্ট: ঘণ্টার টার্গেটে পিছিয়ে থাকা লাইন, due date কাছে কিন্তু পাস কম এমন জব → নোটিফিকেশন; সীমা Settings-এ।

**ফেজ D — অ্যাক্সেস**
- D1 ম্যানেজার-লাইন: `qtally.view_all` না থাকলে শুধু নিজের লাইন (ড্যাশবোর্ড, শিট, হিস্টোরি, টার্গেট, অ্যানালিটিক্স)।
- D2 QC এসাইনমেন্ট ইতিহাস: কে কবে কোন লাইনে দেওয়া/সরানো হয়েছে।

**ফেজ E — Final Inspection**
- E1 AQL Standards (বায়ার-ওয়ারি লেভেল ও AQL) + ISO 2859-1 টেবিল (লট সাইজ → কোড লেটার → স্যাম্পল, accept/reject)।
- E2 AQL লট ইন্সপেকশন: প্ল্যান ইন্সপেকশনের সাথে সেভ; ফেল হলে আবার ইন্সপেকশন (attempt), এডিট নয়।
- E3 Pass/Fail চেক (মেটাল ডিটেক্টর, নিডল চেক)।
- E4 Waiver: `qtally.approve` + লিখিত কারণ; ফিরিয়ে নেওয়া যায়।

**ফেজ F — Route ও চেকপয়েন্ট**
- F1 চেকপয়েন্ট: সেকশনে Inline / End-line; আউটপুট গোনে একটাই; ট্যাপে স্ট্যাম্প।
- F2 Job Route: জব কোন সেকশন কোন ক্রমে যাবে; সেকশনের মাঝে WIP (আগের সেকশনের পাস − পরের সেকশনের চেক) ও পাইপলাইন ভিউ।


## Mobile App

### `37/01` [Mobile App](37_Mobile_App/01_Mobile_App.md)

1. **Push-এর Firebase:** নতুন Firebase প্রজেক্ট user-এর Google অ্যাকাউন্টে খুলতে হবে (ধাপ ৬-এর আগে)। Push-এ ফোনের ইন্টারনেটও লাগবে, শুধু কারখানার LAN-এ চলবে না।
2. **সার্ভারের IP বদলায় কি না:** সার্ভারের IP স্থির (static) হতে হবে, আর Windows Firewall-এ ৮০১০ পোর্ট খোলা। IP বদলালে সব ফোনে আবার লিখতে হবে।
3. **ব্রেকডাউন Close হলেও তার Work Order নিজে বন্ধ হয় না** (ওয়েবেও একই): Repair done দিলে ব্রেকডাউন এগোয়, কিন্তু WO "In progress" থেকে যায়; WO আলাদা করে Complete ও Close করতে হয়। ব্রেকডাউনের Repair done-এ WO-ও Complete হবে, নাকি এখনকার মতো আলাদা থাকবে?
4. **VPS-এ নতুন কোড:** VPS-এ চলছে শেষ commit-এর কোড (ধাপ ১–২)। ধাপ ৩–৫-এর সার্ভারের বদল (Review API ও তার migration, Dashboard-এর ঠিক, Dev Sample Audit Log, Pre-Budget Costing-এর ঠিক) commit হয়ে VPS-এ deploy না হওয়া পর্যন্ত অ্যাপের Review পাতা সেখানে চলবে না — কখন commit/push ও deploy করব?
5. **Segoe UI-র লাইসেন্স:** ওয়েব Segoe UI নিজে বহন করে, এখন অ্যাপও (APK-র ভেতরে)। Segoe UI Microsoft-এর ফন্ট; Play Store-এ দেওয়ার আগে লাইসেন্স দেখে নেওয়া ভালো, নইলে কাছাকাছি খোলা ফন্ট (যেমন Noto Sans / Open Sans)।
6. **Push-এর Firebase (ধাপ ৬):** user-এর Google অ্যাকাউন্টে Firebase প্রজেক্ট খুলে Android অ্যাপ `com.annotech.erp` যোগ করে `google-services.json` আর সার্ভারের জন্য service account চাবি দিতে হবে — তারপর Push চালু হবে।
7. **ডেলিভারির তারিখ = RFI/SD (যখন ডেলিভারির তারিখ লেখা নেই):** Pre-Budget-এর "Delivery Date Wise Summary" requisition-এর `delivery_date` ধরে ভাগ করত; বেশির ভাগে সেটা খালি, তাই ওয়েবেও তারিখ দেখাত না। এখন খালি থাকলে PO-wise RFI/SD তারিখ নেওয়া হয় (ওয়েব ও অ্যাপ দুটোতেই)। এটা ঠিক, নাকি ডেলিভারির তারিখ আলাদা কোথাও থেকে আসবে?
8. **নিচের মেনু:** রেফারেন্স ছবিতে নিচের মেনু Home · Production · Tally · Machines · More (বাজেটের ছবিতে Requisitions · Budgets · Approvals)। এখন আমাদের Home · Tally (= Production) · Order · Budget রাখা হয়েছে, শুধু নকশা ছবির মতো। ছবির মেনুতেই বদলাতে হবে?
9. **অ্যাপের আইকন:** নাম ও ভেতরের লোগো Norban; ফোনের অ্যাপ-আইকন এখনো আগের "A"। Norban-এর আইকন (যেমন "N") লাগবে?
10. **বাজেটের তালিকার টাকা:** ছবিতে Total Budget / Utilized / Remaining (৳); আমাদের বাজেটে আছে Net FOB / Total Cost (+CM) / Margin (USD) — সেগুলোই দেখানো হচ্ছে। ঠিক আছে?
11. **ফোন আড়াআড়ি (landscape):** অ্যাপ এখন `app.json`-এ portrait-এ বাঁধা, তাই ফোন ঘোরালেও ঘোরে না। Budget List আড়াআড়ি ও ট্যাবলেটের জন্য তৈরি (৭২০dp-এর বেশি হলে টেবিল), কিন্তু পুরো অ্যাপ ঘোরানো চালু করলে ট্যালি ও অন্য পাতাও ঘুরবে — চালু করব? (এটা পরের বিল্ডে কাজ করবে।)
12. **Budget List-এ এডিট:** বাজেট এডিট করার পাতা অ্যাপে নেই (ওয়েবে আছে); মেনুতে "Edit — ওয়েবে করতে হয়" দেখায়। ফোনে বাজেট এডিট লাগবে?
13. **ড্রাফট "Cancel":** সার্ভারে ড্রাফট বাতিলের আলাদা কাজ নেই; আছে ড্রাফট মোছা (Delete, prebudget.delete অনুমতিতে)। CANCELLED অবস্থা আসে অর্ডার বদলালে/বাদ পড়লে। মোছাই কি "Cancel" ধরা হবে?
