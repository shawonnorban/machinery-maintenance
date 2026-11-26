# Annotech ERP — Implementation Index

**লক্ষ্য:** ব্যবহারকারীর নিজের **Brickland ERP**-এর মডিউল কাঠামো হুবহু অনুসরণ করে একটা পূর্ণাঙ্গ গার্মেন্টস ERP বানানো, সাথে বর্তমান **Machinery Maintenance** সিস্টেম যোগ করে।
**পার্থক্য:** Brickland ডার্ক থিমে, নতুন সিস্টেম **লাইট থিমে**।
**বর্তমান প্রজেক্ট থেকে যা থাকবে:** শুধু Machinery (মেইনটেন্যান্স)। বাকি সব Brickland-এর ধাঁচে নতুন বা পুনর্গঠিত হবে।
**`Erp.xlsx`:** শুধু MIS (ম্যানেজমেন্ট রিপোর্ট) অংশ, কলাম ব্যবহারকারী পরে দেবেন ([29 MIS](29_MIS_Reports/00_Index.md))।
**গিট:** এই কাজ বর্তমান গিটে কমিট বা পুশ হবে না, যতক্ষণ না ব্যবহারকারী বলেন।

- কাজের নিয়ম, ফাইল টেমপ্লেট, Definition of Done: [00_Conventions.md](00_Conventions.md)
- লাইভ Brickland পরিদর্শন টুল: [tools/erp_crawler/README.md](tools/erp_crawler/README.md)

---

## কীভাবে সাজানো

- **একটা মডিউল = একটা ফোল্ডার** (Brickland-এর সাইডবারের মডিউল), নম্বর বাস্তবায়নের ক্রমে।
- **একটা সাইডবার-গ্রুপ = একটা `.md` ফাইল** (যেমন MASTER DATA, OPERATION, REPORTS)। প্রতিটা মডিউলে MASTER DATA সবসময় `01`।
- প্রতিটা ফাইলের §১-২-এ থাকে Brickland-এর প্রতিটা স্ক্রিনের লাইভ বিবরণ: ফিল্ড, টাইপ, বাধ্যতামূলক কিনা, ড্রপডাউন অপশন, ট্যাব, গ্রিড-কলাম, বাটন, New/Add ডায়ালগ আর খালি Save-এ আসা ভ্যালিডেশন। এই অংশ জেনারেটর লেখে। বাকি অংশ (ডাটা মডেল, নিয়ম, API, ফাইল-তালিকা, As-built) বাস্তবায়নের আগে আর পরে হাতে লেখা হয়।

```
node AnnotechERP/tools/erp_crawler/crawl.mjs --screens --allow-load-posts   # Brickland থেকে নতুন করে পড়া
node AnnotechERP/tools/erp_crawler/crawl.mjs --dialogs --allow-writes       # New/Add ডায়ালগ + ভ্যালিডেশন (ডেমো)
python AnnotechERP/tools/build_erp_docs.py                                  # ডক আপডেট
python AnnotechERP/tools/build_erp_docs.py --check                          # শুধু যাচাই
```

## বাস্তবায়নের ক্রম

1. **ফেজ ১ — ভিত্তি ও মাস্টার ডাটা:** Company Management, Access Control, Settings, Approval Center। তারপর **সব মডিউলের MASTER DATA** একসাথে (নিচের টেবিলের প্রথম অংশ), কারণ বাকি সব এন্ট্রি-স্ক্রিন এগুলোর ড্রপডাউন থেকে চলে।
2. **ফেজ ২-৮:** নির্ভরতা অনুযায়ী। বিক্রয় (CRM → Merchandise → Prototype) → সাপ্লাই/স্টোর → প্ল্যানিং/IE → প্রোডাকশন (Knit → Colour Lab → Dye → Finishing → Cutting → Assembly) → শিপমেন্ট/কমার্শিয়াল → ফাইন্যান্স/HR → পোর্টাল/AI/অ্যানালিটিক্স।
3. **ফেজ ৯ — MIS:** `Erp.xlsx`-এর ম্যানেজমেন্ট রিপোর্ট, কলাম পাওয়ার পরে।
4. **ফেজ ০ — Machinery** আগে থেকেই আছে। শুধু নতুন নেভিগেশন আর লাইট থিমে বসবে, আর Company Management-এর Machine Master-এর সাথে যুক্ত হবে।

একবারে একটাই গ্রুপ-ফাইল নিয়ে কাজ হবে: স্পেক → বাস্তবায়ন → টেস্ট → As-built → ✅ → তারপর পরেরটা।

---

## স্ট্যাটাস (জেনারেটর লেখে)

<!-- AUTO:status:start -->
**মোট:** ৩৫টা মডিউল · ১৮৩টা গ্রুপ-ফাইল · Brickland-এর ৮৪১টা স্ক্রিন, Norban-এর ২৭টা

### ফেজ ১-এর আগে: সব মডিউলের MASTER DATA (একসাথে)

| মডিউল | মাস্টার ফাইল | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| Merchandise Desk | [Master Data](06_Merchandise_Desk/01_Master_Data.md) | ২৭ | ✅ সম্পন্ন (Brickland-এর এখনকার ২৯টা স্ক্রিনই চালু; ধাপ D-তে যোগ হয়েছে Item Type Master, Nature of Work Master আর Style & Order Upload) |
| Prototype Desk | [Master Data](07_Prototype_Desk/01_Master_Data.md) | ১ | ✅ সম্পন্ন (Merchandise Desk-এর বায়ার-মাস্টারের সাথে তৈরি) |
| Material Procurement | [Master Data](08_Material_Procurement/01_Master_Data.md) | ৩ | ✅ সম্পন্ন |
| General Store | [Master Data](10_General_Store/01_Master_Data.md) | ৬ | ✅ সম্পন্ন |
| Gate Pass | [Master Data](11_Gate_Pass/01_Master_Data.md) | ৫ | ✅ সম্পন্ন |
| Capacity Planning | [Master Data](12_Capacity_Planning/01_Master_Data.md) | ৮ | ✅ সম্পন্ন |
| Fabric Requirement | [Master Data](13_Fabric_Requirement/01_Master_Data.md) | ৩ | ✅ সম্পন্ন |
| Work Study | [Master Data](14_Work_Study/01_Master_Data.md) | ৬ | ✅ সম্পন্ন |
| Knit Production | [Master Data](15_Knit_Production/01_Master_Data.md) | ৭ | ✅ সম্পন্ন |
| Dye House | [Master Data](17_Dye_House/01_Master_Data.md) | ৬ | ✅ সম্পন্ন |
| Finishing Line | [Master Data](18_Finishing_Line/01_Master_Data.md) | ৪ | ✅ সম্পন্ন |
| Production Tracking | [Master Data](30_Production_Tracking/01_Master_Data.md) | ২ | ✅ সম্পন্ন |
| Trade & Export | [Master Data](22_Trade_Export/01_Master_Data.md) | ৫ | ✅ সম্পন্ন |
| Finance & Ledger | [Master Data](23_Finance_Ledger/01_Master_Data.md) | ১০ | ✅ সম্পন্ন |
| HR & Payroll | [Master Data](24_HR_Payroll/01_Master_Data.md) | ১৪ | ✅ সম্পন্ন |

### ফেজ ০ — Machinery (বিদ্যমান, অপরিবর্তিত)

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 00 | [Machinery Maintenance](00_Machinery_Maintenance/00_Index.md) | ৯ | — | ৯/৯ |

### ফেজ ১ — ভিত্তি ও মাস্টার ডাটা

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 01 | [Company Management](01_Company_Management/00_Index.md) | ৫ | ১৪ | ৫/৫ |
| 02 | [Access Control](02_Access_Control/00_Index.md) | ৪ | ২৭ | ৩/৪ |
| 03 | [Settings](03_Settings/00_Index.md) | ৪ | ১৯ | ৪/৪ |
| 04 | [Approval Center](04_Approval_Center/00_Index.md) | ৩ | ১০ | ৩/৩ |

### ফেজ ২ — বিক্রয় ও মার্চেন্ডাইজিং

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 05 | [CRM & Marketing](05_CRM_Marketing/00_Index.md) | ৭ | ৪৮ | ০/৭ |
| 06 | [Merchandise Desk](06_Merchandise_Desk/00_Index.md) | ১৩ | ১১০ | ১৩/১৩ |
| 07 | [Prototype Desk](07_Prototype_Desk/00_Index.md) | ৫ | ২৩ | ৩/৫ |
| 31 | [Dev Sample Status](31_Dev_Sample_Status/00_Index.md) | ১ | ৩ | ১/১ |
| 32 | [Order Summary](32_Order_Summary/00_Index.md) | ১ | ৭ | ১/১ |
| 33 | [Knit Woven](33_Knit_Woven/00_Index.md) | ১ | ১৪ | ০/১ |
| 34 | [Accessories Pre-Budget](34_Accessories_Pre_Budget/00_Index.md) | ১ | ৩ | ০/১ |

### ফেজ ৩ — সাপ্লাই ও স্টোর

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 08 | [Material Procurement](08_Material_Procurement/00_Index.md) | ৭ | ১৫ | ৭/৭ |
| 09 | [Store & Material](09_Store_Material/00_Index.md) | ২ | ১০ | ২/২ |
| 10 | [General Store](10_General_Store/00_Index.md) | ৭ | ৩৪ | ৭/৭ |
| 11 | [Gate Pass](11_Gate_Pass/00_Index.md) | ৪ | ১৩ | ৪/৪ |

### ফেজ ৪ — প্ল্যানিং ও IE

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 12 | [Capacity Planning](12_Capacity_Planning/00_Index.md) | ২ | ১৭ | ২/২ |
| 13 | [Fabric Requirement](13_Fabric_Requirement/00_Index.md) | ৩ | ৯ | ৩/৩ |
| 14 | [Work Study](14_Work_Study/00_Index.md) | ৮ | ২২ | ৭/৮ |

### ফেজ ৫ — প্রোডাকশন

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 15 | [Knit Production](15_Knit_Production/00_Index.md) | ১১ | ১১৬ | ১১/১১ |
| 16 | [Colour Lab](16_Colour_Lab/00_Index.md) | ২ | ৫ | ২/২ |
| 17 | [Dye House](17_Dye_House/00_Index.md) | ১১ | ৭৪ | ১০/১১ |
| 18 | [Finishing Line](18_Finishing_Line/00_Index.md) | ৬ | ২২ | ৬/৬ |
| 19 | [Cutting Floor](19_Cutting_Floor/00_Index.md) | ৪ | ১০ | ৪/৪ |
| 20 | [Assembly Floor](20_Assembly_Floor/00_Index.md) | ৯ | ৩২ | ৮/৯ |
| 30 | [Production Tracking](30_Production_Tracking/00_Index.md) | ৬ | ১৩ | ৬/৬ |

### ফেজ ৬ — শিপমেন্ট ও কমার্শিয়াল

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 21 | [Sales & Shipment](21_Sales_Shipment/00_Index.md) | ৭ | ৩২ | ৭/৭ |
| 22 | [Trade & Export](22_Trade_Export/00_Index.md) | ৬ | ২২ | ৬/৬ |

### ফেজ ৭ — ফাইন্যান্স ও HR

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 23 | [Finance & Ledger](23_Finance_Ledger/00_Index.md) | ৭ | ২৮ | ৭/৭ |
| 24 | [HR & Payroll](24_HR_Payroll/00_Index.md) | ৯ | ৪৪ | ৯/৯ |

### ফেজ ৮ — পোর্টাল, AI ও অ্যানালিটিক্স

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 25 | [Buyer Gateway](25_Buyer_Gateway/00_Index.md) | ৫ | ২০ | ৫/৫ |
| 26 | [AI File & PO Import](26_AI_File_PO_Import/00_Index.md) | ৬ | ৩২ | ১/৬ |
| 27 | [Command Center](27_Command_Center/00_Index.md) | ৫ | ১৯ | ১/৫ |
| 28 | [Insight Studio](28_Insight_Studio/00_Index.md) | ১ | ১ | ০/১ |

### ফেজ ৯ — MIS (Erp.xlsx — ম্যানেজমেন্ট রিপোর্ট)

| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |
|---|---|---|---|---|
| 29 | [MIS Reports](29_MIS_Reports/00_Index.md) | ১ | — | ০/১ |
<!-- AUTO:status:end -->
