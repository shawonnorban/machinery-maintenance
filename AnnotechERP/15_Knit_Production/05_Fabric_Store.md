# Fabric Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (১৮টার ১৮টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/05`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | In-House Sub-Contract Bill | `/m/knitting/sc-in-house-bill` | 3 | 24 | 3 |
| 2 | Out-House Sub-Contract Bill | `/m/knitting/sc-out-house-bill` | 3 | 24 | 3 |
| 3 | 503 Service Temporarily Unavailable | `/m/knitting/yd-bill-sc-out-house` | 1 | 0 | 3 |
| 4 | Grey Fabric Store Receipt | `/m/knitting/grey-fabric-receive-to-store` | 2 | 10 | 0 |
| 5 | Out-House Grey Receipt Challan | `/m/knitting/grey-fabric-receive-challan-sc-out-house` | 3 | 14 | 3 |
| 6 | Out-House Collar & Cuff QC Receipt | `/m/knitting/collar-cuff-receive-sc-out-house-qc` | 1 | 1 | 0 |
| 7 | Out-House Grey Return Challan | `/m/knitting/grey-fabric-return-challan-sc-out-house` | 3 | 14 | 3 |
| 8 | In-House Grey Return Challan | `/m/knitting/grey-fabric-return-challan-sc-in-house` | 3 | 14 | 3 |
| 9 | In-House Grey Delivery Challan | `/m/knitting/grey-fabric-delivery-challan-sc-in-house` | 2 | 11 | 3 |
| 10 | Out-House Yarn Transfer | `/m/knitting/yarn-transfer-sc-out-house` | 3 | 11 | 3 |
| 11 | Grey Yarn Transfer for Dyeing | `/m/knitting/grey-yarn-transfer-challan-for-yd` | 2 | 15 | 1 |
| 12 | Dyed Yarn Receipt Challan | `/m/knitting/dyed-yarn-receive-challan` | 2 | 12 | 1 |
| 13 | Out-House Dyed Yarn Transfer Challan | `/m/knitting/dyed-yarn-trans-challan-sc-out-house-knitting` | 1 | 9 | 1 |
| 14 | Grey Fabric Stock Close | `/m/knitting/grey-fabric-stock-closing` | 1 | 1 | 0 |
| 15 | Out-House Debit Memo | `/m/knitting/debit-memo-sc-out-house` | 2 | 12 | 1 |
| 16 | Grey Fabric Opening Stock | `/m/knitting/grey-fabric-stock-opening` | 5 | 44 | 1 |
| 17 | Grey Fabric Stock Correction | `/m/knitting/grey-fabric-stock-adjustment` | 2 | 23 | 1 |
| 18 | Store-to-Store Grey Transfer | `/m/knitting/grey-fabric-transfer-store-to-store` | 2 | 25 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### In-House Sub-Contract Bill

`/m/knitting/sc-in-house-bill`

**ট্যাব: S/C In-house Bill Process 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Approved, Revised, Cancelled |
| Bill# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: S/C In-house Bill Process 0, New In-house Bill, New in-house bill, -- Select --, Show, Add New, Reset

**ট্যাব: New In-house Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  | The party's own reference |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  | Why the bill is being re-raised |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New In-house Bill, Key Information, Revision, Settlement, Bill Detail
- বাটন/অ্যাকশন: S/C In-house Bill Process 0, New In-house Bill, New in-house bill, -- Select --, Add Charge, Back To List, Clear, Save bill

**ট্যাব: New in-house bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  | The party's own reference |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  | Why the bill is being re-raised |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New In-house Bill, Key Information, Revision, Settlement, Bill Detail
- বাটন/অ্যাকশন: S/C In-house Bill Process 0, New In-house Bill, New in-house bill, -- Select --, Add Charge, Back To List, Clear, Save bill

**ডায়ালগ: New In-house Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: New in-house bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

#### Out-House Sub-Contract Bill

`/m/knitting/sc-out-house-bill`

**ট্যাব: S/C Out-house Bill Process 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Approved, Revised, Cancelled |
| Bill# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: S/C Out-house Bill Process 0, New Out-house Bill, New out-house bill, -- Select --, Show, Add New, Reset

**ট্যাব: New Out-house Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  | The party's own reference |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  | Why the bill is being re-raised |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New Out-house Bill, Key Information, Revision, Settlement, Bill Detail
- বাটন/অ্যাকশন: S/C Out-house Bill Process 0, New Out-house Bill, New out-house bill, -- Select --, Add Charge, Back To List, Clear, Save bill

**ট্যাব: New out-house bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  | The party's own reference |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  | Why the bill is being re-raised |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New Out-house Bill, Key Information, Revision, Settlement, Bill Detail
- বাটন/অ্যাকশন: S/C Out-house Bill Process 0, New Out-house Bill, New out-house bill, -- Select --, Add Charge, Back To List, Clear, Save bill

**ডায়ালগ: New Out-house Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: New out-house bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

#### 503 Service Temporarily Unavailable

`/m/knitting/yd-bill-sc-out-house` — মেনুতে: Out-House Yarn Dyeing Bill

**ভিউ**

**ডায়ালগ: New Y/D Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: New Y/D bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Party Bill# | text |  |  |
| Ref.Bill# | select |  |  |
| Rev.Descr | text |  |  |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Deduction | number |  |  |
| VAT | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A bill with no charges on it claims nothing.

#### Grey Fabric Store Receipt

`/m/knitting/grey-fabric-receive-to-store`

**ট্যাব: Fabric Stock 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Fabric Stock | radio |  |  |
| Receive Notes | radio |  |  |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Rack# | select |  | -N/A- |
| Date | date |  |  |
| Prod.Type | select |  | Bulk, Y/D, Sample, Flat Bed RIB |
- সেকশন: Print Parameter
- বাটন/অ্যাকশন: Fabric Stock 0, Fabric Receive, Show, Reset

**ট্যাব: Fabric Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Receive Date 09 Oct 2026 08 Oct 2026 07 Oct 2026 06 Oct 2026 05 Oct 2026 04 Oct  | select |  | 09 Oct 2026, 08 Oct 2026, 07 Oct 2026, 06 Oct 2026, 05 Oct 2026, 04 Oct 2026, 03 Oct 2026, 02 Oct 2026, 01 Oct 2026, 30  |
| Roll# | text |  | Roll# |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Rack# | select |  | -N/A- |
- গ্রিড কলাম: Select, Prod.Type, Buyer/Party, Style#, Order#, Color, Roll#, Fab.Type, F.Dia, F.Gsm, Store, Rack, Roll Wt(Kg), Status, Action
- সেকশন: Fabric Receive
- বাটন/অ্যাকশন: Fabric Stock 0, Fabric Receive, Receive, Clear day

#### Out-House Grey Receipt Challan

`/m/knitting/grey-fabric-receive-challan-sc-out-house`

**ট্যাব: S/C Grey Fabric Receive from Party 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Received, QC Passed, QC Hold, QC Rejected, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: S/C Grey Fabric Receive from Party 0, Add Challan, New receive challan, -- Select --, Show, Add New, Reset

**ট্যাব: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing receive challan | select |  |  |
| Rcv# | text |  | [Auto Gen] |
| Rcv. Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Challan#* | text | ✔ | The party's own challan number |
| Challan Date* | date | ✔ |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Note | textarea |  |  |
- সেকশন: Add Challan, Key Information, Party Challan, Gate, Challan Detail
- বাটন/অ্যাকশন: S/C Grey Fabric Receive from Party 0, Add Challan, New receive challan, -- Select --, Add Line, Back To List, Clear, Save receive challan

**ট্যাব: New receive challan**
- সেকশন: New receive challan
- বাটন/অ্যাকশন: S/C Grey Fabric Receive from Party 0, Add Challan, New receive challan, Reset, Save receive challan

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Received, QC Passed, QC Hold, QC Rejected, Cancelled |

**ডায়ালগ: New receive challan**
- খালি Save-এ মেসেজ: A receive challan is raised on the entry tab, against the party's own challan number.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receive challan | select |  |  |
| Rcv# | text |  |  |
| Rcv. Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Challan#* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: A receive challan with no lines on it receives nothing.

#### Out-House Collar & Cuff QC Receipt

`/m/knitting/collar-cuff-receive-sc-out-house-qc`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Received, QC Passed, QC Hold, QC Rejected, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: -- Select --, Show, Reset

#### Out-House Grey Return Challan

`/m/knitting/grey-fabric-return-challan-sc-out-house`

**ট্যাব: Grey Fabric Return Challan (S/C Out-house) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Issued, Received Back, Cancelled |
| Challan# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Add Challan, New return challan, -- Select --, Show, Add New, Reset

**ট্যাব: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  | Why it is going back |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- সেকশন: Add Challan, Challan Information, Against, Gate, Return Detail
- বাটন/অ্যাকশন: Add Challan, New return challan, -- Select --, Add Line, Back To List, Clear, Save return challan

**ট্যাব: New return challan**
- সেকশন: New return challan
- বাটন/অ্যাকশন: Add Challan, New return challan, Reset, Save return challan

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  |  |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines on it sends nothing back.

**ডায়ালগ: New return challan**
- খালি Save-এ মেসেজ: A return challan is raised on the entry tab, where the rolls going back are picked off what is in the store.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  |  |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines on it sends nothing back.

#### In-House Grey Return Challan

`/m/knitting/grey-fabric-return-challan-sc-in-house`

**ট্যাব: Grey Fabric Return Challan (S/C In-house) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Issued, Received Back, Cancelled |
| Challan# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Add Challan, New return challan, -- Select --, Show, Add New, Reset

**ট্যাব: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  | Why it is going back |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- সেকশন: Add Challan, Challan Information, Against, Gate, Return Detail
- বাটন/অ্যাকশন: Add Challan, New return challan, -- Select --, Add Line, Back To List, Clear, Save return challan

**ট্যাব: New return challan**
- সেকশন: New return challan
- বাটন/অ্যাকশন: Add Challan, New return challan, Reset, Save return challan

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  |  |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines on it sends nothing back.

**ডায়ালগ: New return challan**
- খালি Save-এ মেসেজ: A return challan is raised on the entry tab, where the rolls going back are picked off what is in the store.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Against Rcv# | select |  |  |
| Reason | text |  |  |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines on it sends nothing back.

#### In-House Grey Delivery Challan

`/m/knitting/grey-fabric-delivery-challan-sc-in-house`

**ট্যাব: Grey Fabric Delivery to Party**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing delivery challan | select |  |  |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Challan# | text |  | [Auto Gen] |
| Chln. Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Receiver | text |  |  |
| Rcver Mob# | text |  |  |
| Note | textarea |  |  |
- সেকশন: Grey Fabric Delivery to Party, Challan Information, Gate, Receiver, Fabric Delevery, Collar/Cuff Delevery, Yarn Return
- বাটন/অ্যাকশন: Challan List 0, Grey Fabric Delivery to Party, New delivery challan, -- Select --, Add Roll, Add Collar/Cuff, Add Yarn, Back To List, Clear, Save delivery challan

**ট্যাব: New delivery challan**
- সেকশন: New delivery challan
- বাটন/অ্যাকশন: Challan List 0, Grey Fabric Delivery to Party, New delivery challan, Reset, Save delivery challan

**ডায়ালগ: New delivery challan**
- খালি Save-এ মেসেজ: A delivery challan is raised on the entry tab, where the rolls going out are picked off what is in the store.

**ডায়ালগ: Add Roll**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing delivery challan | select |  |  |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Challan# | text |  |  |
| Chln. Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Receiver | text |  |  |
| Rcver Mob# | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: Every line needs a description.

**ডায়ালগ: Add Collar/Cuff**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing delivery challan | select |  |  |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Challan# | text |  |  |
| Chln. Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Driver | text |  |  |
| Receiver | text |  |  |
| Rcver Mob# | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: Every line needs a description.

#### Out-House Yarn Transfer

`/m/knitting/yarn-transfer-sc-out-house`

**ট্যাব: Program Transfer (S/C Out-house) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Issued, Finalized, Cancelled |
| Challan# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Program Transfer (S/C Out-house) 0, Add Challan, New programme transfer, -- Select --, Show, Add New, Reset

**ট্যাব: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer challan | select |  |  |
| Challan# | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| S/C Prog#* | select | ✔ |  |
| Yarn Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Note | textarea |  |  |
- সেকশন: Add Challan, Challan Information, Programme, Gate, Yarn Detail
- বাটন/অ্যাকশন: Program Transfer (S/C Out-house) 0, Add Challan, New programme transfer, -- Select --, Add Yarn, Back To List, Clear, Save transfer challan

**ট্যাব: New programme transfer**
- সেকশন: New programme transfer
- বাটন/অ্যাকশন: Program Transfer (S/C Out-house) 0, Add Challan, New programme transfer, Reset, Save programme transfer

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| S/C Prog#* | select | ✔ |  |
| Yarn Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: Challan Date is required.

**ডায়ালগ: New programme transfer**
- খালি Save-এ মেসেজ: A programme transfer is raised on the entry tab, where the programme and the yarn going with it are picked.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| S/C Prog#* | select | ✔ |  |
| Yarn Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Gate Pass# | text |  |  |
| Veicle# | text |  |  |
| Note | textarea |  |  |
- খালি Save-এ মেসেজ: Challan Date is required.

#### Grey Yarn Transfer for Dyeing

`/m/knitting/grey-yarn-transfer-challan-for-yd`

**ট্যাব: Grey Yarn Transfer Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Y/D Program | select |  |  |
| Challan# | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
- সেকশন: Grey Yarn Transfer Challan for YD
- বাটন/অ্যাকশন: Grey Yarn Transfer Challan List 0, Grey Yarn Transfer Challan for YD, -- Select --, Show, Add Challan, Reset

**ট্যাব: Grey Yarn Transfer Challan for YD**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Y/D Program List | search |  | Programme, party, shipment month… |
| Existing transfer challan | select |  |  |
| Challan# | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Y/D Program | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Vehicle# | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Y/D Program #, Party, Ship Month, To send (Kg)
- সেকশন: Y/D Program List, Grey Yarn Transfer Challan for YD, Challan Information, Against, Movement, Challan Details
- বাটন/অ্যাকশন: Grey Yarn Transfer Challan List 0, Grey Yarn Transfer Challan for YD, -- Select Party --, -- Select --, Add Shade, Back To List, Clear, Save transfer challan

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Y/D Program List | search |  |  |
| Existing transfer challan | select |  |  |
| Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Y/D Program | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Vehicle# | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A challan with no shades on it is not a challan. Pick a programme on the left first.

#### Dyed Yarn Receipt Challan

`/m/knitting/dyed-yarn-receive-challan`

**ট্যাব: Dyed Yarn Receive Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Program# | text |  | Search Program # |
| Buyer A/C | select |  |  |
| Work Style/Job# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
- সেকশন: Dyed Yarn Receive Challan
- বাটন/অ্যাকশন: Dyed Yarn Receive Challan List 0, Dyed Yarn Receive Challan, -- Select --, Show, Add Challan, Reset

**ট্যাব: Dyed Yarn Receive Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Program# | text |  | Search Program # |
| Buyer A/C | select |  |  |
| Work Style/Job# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Date | date |  |  |
| To Date | date |  |  |
| Status | select |  | Draft, Issued, Finalized, Cancelled |
- সেকশন: Dyed Yarn Receive Challan
- বাটন/অ্যাকশন: Dyed Yarn Receive Challan List 0, Dyed Yarn Receive Challan, -- Select --, Show, Add Challan, Reset

**ডায়ালগ: Add Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Grey Yarn Transfer Challan | search |  |  |
| Existing receive challan | select |  |  |
| Challan# | text |  |  |
| Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Grey Yarn Challan | select |  |  |
| Program Ref# | select |  |  |
| Buyer A/C | select |  |  |
| Work Style/Job# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
| G.Yarn Qty | text |  |  |
- খালি Save-এ মেসেজ: A receive challan with no shades on it is not a receipt. Pick a grey challan on the left first.

#### Out-House Dyed Yarn Transfer Challan

`/m/knitting/dyed-yarn-trans-challan-sc-out-house-knitting`

**ট্যাব: Dyed Yarn Transfer Challan (S/C Out-house) for Knitting Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search YD Receive Challan | search |  | Challan, party… |
| Existing transfer challan | select |  |  |
| Challan# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Vehicle# | text |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Date, Challan#, Qty(Kg), Include Challan
- সেকশন: YD Receive Challan, Challan Information, Movement, Challan Details
- বাটন/অ্যাকশন: Dyed Yarn Transfer Challan List 0, -- Select Party --, -- Select --, Add Line, Back To List, Clear, Save transfer challan

**ডায়ালগ: Add Line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search YD Receive Challan | search |  |  |
| Existing transfer challan | select |  |  |
| Challan# | text |  |  |
| Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Vehicle# | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Date is required.

#### Grey Fabric Stock Close

`/m/knitting/grey-fabric-stock-closing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Remarks | text |  |  |
- সেকশন: Parameters
- বাটন/অ্যাকশন: -- Select Month --, Show, Month Close, Show Exception, Reset

#### Out-House Debit Memo

`/m/knitting/debit-memo-sc-out-house`

**ট্যাব: Debit Memo List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Approved, Settled, Cancelled |
| Company | select |  | Brickland Composite Ltd. |
| Memo# | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Debit Memo (S/C Out-house)
- বাটন/অ্যাকশন: Debit Memo List 0, Debit Memo (S/C Out-house), -- Select --, Show, Add Memo, Reset

**ট্যাব: Debit Memo (S/C Out-house)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing debit memo | select |  |  |
| Memo# | text |  | [Auto Gen] |
| Memo Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Reason | text |  | Why the party is being charged — the sentence the memo is read by |
| Remarks | textarea |  |  |
- সেকশন: Debit Memo (S/C Out-house), Memo Information, Charge, Memo Detail
- বাটন/অ্যাকশন: Debit Memo List 0, Debit Memo (S/C Out-house), -- Select --, Add Line, Back To List, Clear, Save debit memo

**ডায়ালগ: Add Memo**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing debit memo | select |  |  |
| Memo# | text |  |  |
| Memo Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Currency | select |  | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A memo with no charged lines on it claims nothing. Add at least one.

#### Grey Fabric Opening Stock

`/m/knitting/grey-fabric-stock-opening`

**ট্যাব: Grey Fabric Stock Opening Balance 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Opening Balance For | select |  | In-house, Collar-Cuff, S/C In-house |
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Finalized? | select |  | Yes, No |
- সেকশন: Search Parameter
- বাটন/অ্যাকশন: Grey Fabric Stock Opening Balance 0, Opening Balance for In-house, Opening Balance for Collar-Cuff, Opening Balance for S/C In-house, Add Opening Line, -- Select --, -- Select Color --, Search, Reset

**ট্যাব: Opening Balance for In-house**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Finalized? | select |  | Yes, No |
- সেকশন: Opening Balance for In-house
- বাটন/অ্যাকশন: Grey Fabric Stock Opening Balance 0, Opening Balance for In-house, Opening Balance for Collar-Cuff, Opening Balance for S/C In-house, Add Opening Line, -- Select --, -- Select Color --, Search, Finalize, Reset

**ট্যাব: Opening Balance for Collar-Cuff**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Finalized? | select |  | Yes, No |
- সেকশন: Opening Balance for Collar-Cuff
- বাটন/অ্যাকশন: Grey Fabric Stock Opening Balance 0, Opening Balance for In-house, Opening Balance for Collar-Cuff, Opening Balance for S/C In-house, Add Opening Line, -- Select --, -- Select Color --, Search, Finalize, Reset

**ট্যাব: Opening Balance for S/C In-house**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Finalized? | select |  | Yes, No |
- সেকশন: Opening Balance for S/C In-house
- বাটন/অ্যাকশন: Grey Fabric Stock Opening Balance 0, Opening Balance for In-house, Opening Balance for Collar-Cuff, Opening Balance for S/C In-house, Add Opening Line, -- Select --, -- Select Color --, Search, Finalize, Reset

**ট্যাব: Add Opening Line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| In-house | radio |  |  |
| Collar-Cuff | radio |  |  |
| S/C In-house | radio |  |  |
| Opening Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Fabric* | text | ✔ | S/J 30/1 Combed 180 GSM |
| Color (as written) | text |  | Only when the shade is not a registered colour |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Opening* | number | ✔ |  |
| Cost/Unit | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New opening line
- বাটন/অ্যাকশন: Grey Fabric Stock Opening Balance 0, Opening Balance for In-house, Opening Balance for Collar-Cuff, Opening Balance for S/C In-house, Add Opening Line, -- Select --, -- Select Color --, Cancel, Save opening line

**ডায়ালগ: Add Opening Line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| In-house | radio |  |  |
| Collar-Cuff | radio |  |  |
| S/C In-house | radio |  |  |
| Opening Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Fabric* | text | ✔ |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Opening* | number | ✔ |  |
| Cost/Unit | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Opening is required.

#### Grey Fabric Stock Correction

`/m/knitting/grey-fabric-stock-adjustment`

**ট্যাব: Grey Fabric Adjustment List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All |
| Company | select |  | All |
| Adjustment Date from | date |  |  |
| Adjustment Date to | date |  |  |
| Search Physical-count corrections booked against the grey fabric store — where e | search |  | Search 0 records… |
| Filter by Activity Status | search |  |  |
| Filter by Status | search |  |  |
| Filter by Adjustment No | search |  |  |
| Filter by Adjustment Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store | search |  |  |
| Filter by Lines | search |  |  |
| Filter by Adjust Qty (Kg) | search |  |  |
| Filter by Value | search |  |  |
| Filter by Reason | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Activity Status, Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason, ACTION
- বাটন/অ্যাকশন: Grey Fabric Adjustment List 0, Grey Fabric Stock Adjustment, Clear, Add New, Export, Activity Status, Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason

**ট্যাব: Grey Fabric Stock Adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing adjustment | select |  |  |
| Adjustment No | text |  | [Auto Gen] |
| Adjustment Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Reason | text |  | What the count found — the sentence the adjustment is read by |
| Remarks | textarea |  |  |
- সেকশন: Grey Fabric Stock Adjustment, Adjustment Information, Why, Adjustment Detail
- বাটন/অ্যাকশন: Grey Fabric Adjustment List 0, Grey Fabric Stock Adjustment, Add Line, Back To List, Clear, Save adjustment

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing adjustment | select |  |  |
| Adjustment No | text |  |  |
| Adjustment Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Reason | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: An adjustment with no lines on it corrects nothing. Add at least one.

#### Store-to-Store Grey Transfer

`/m/knitting/grey-fabric-transfer-store-to-store`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Trans Ref# | text |  | Search… |
| Status | select |  | Create Draft, Submit Requisition, Receive Requisition, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Grey Fabric Transfer (Store to Store)
- বাটন/অ্যাকশন: Grey Fabric Transfer (Store to Store), Show, New, Reset

**ট্যাব: Grey Fabric Transfer (Store to Store)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Grey Fabric Transfer (Store to Store), Transfer, From / To, Fabric Detail
- বাটন/অ্যাকশন: Grey Fabric Transfer (Store to Store), Add Fabric, Cancel, -- Color --, Back To List, Clear, Save transfer

**ডায়ালগ: New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A transfer with no fabric on it moves nothing.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **রোল আর তার উৎস:** `knit_rolls` (15/04) — `movement` (IN_KNITTING → GREY_STORE …) আর `quality`। রোল → কার্ড → Fabric Production Order থেকে Prod.Type (order_kind), বায়ার, অর্ডার, ফেব্রিক, রং, ডায়া, GSM (`GreyStore::rollFacts`)।
- **Rack#:** Inventory-র `bins` master (স্টোর ধরে; in-transit বাদে)। নতুন rack master বানানো হয়নি।
- **খাতার ধাঁচ:** 15/03-এর `YarnStore` (lot + move, post/unpost, month lock) — grey খাতা একই নিয়মে, নিজের টেবিলে।
- **ফ্রন্টএন্ড:** 15/03 Y/D-র তালিকা/ফর্মের helper (`useShowFilters`, `useMoves`, `ListShell`, `MoveButton`, `FormHead`, `FormFoot`, `RemoveCell`) এখন `ystore-yd.jsx` থেকে export করা।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_13_000100_create_knitting_grey_store`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_grey_lots` | lot_key (অনন্য), store_id, kind (IN_HOUSE / COLLAR_CUFF / SC_IN_HOUSE), prod_type, buyer_id, order_id, party_id, roll_id (ওজন করা রোল নিজেই এক লাইন), roll_no, fabric_type_id, fabric, colour_id, colour_name, finish_dia, gsm, part, bin_id (র‍্যাক), rate, first_date |
| `knit_grey_moves` | lot_id, store_id, move_date, kind (RECEIVE / OPENING / TRANSFER_OUT / TRANSFER_IN / ADJUST), doc_no, doc_line_id, qty (signed), rolls (signed), value |
| `knit_grey_receipts` + rolls | note_no (GFR-…), receive_date, store_id (স্টোর আর দিন প্রতি একটা নোট), status (DRAFT / CONFIRMED), confirmed_at/by; রোলে roll_id (কোম্পানিতে অনন্য — একবারই নেওয়া), bin_id, lot_id, weight (weight − deducted), scanned_at/by |
| `knit_grey_openings` | opening_no (GOB-…), kind, opening_date, store_id, buyer_id, order_id, party_id, fabric, colour_id, colour_name, finish_dia, gsm, qty, rate, remarks, finalized, lot_id |
| `knit_grey_transfers` + lines | transfer_no (GFT-…), transfer_date, trans_type (DEV_TO_DEV / BULK_TO_BULK / SAMPLE_TO_SAMPLE / PROJECTION_TO_BULK), trans_ref, buyer_id, order_id, from_store_id, to_store_id, status (DRAFT / SUBMITTED / RECEIVED / CANCELLED), remarks; লাইনে lot_id (উৎস), roll_no, rolls, qty, to_lot_id (গ্রহণে), remarks |
| `knit_grey_adjustments` + lines | adj_no (GFA-…), adj_date, store_id, reason, remarks, status (DRAFT / VERIFIED / APPROVED), activity (ACTIVE / CANCELLED); লাইনে order_id, colour_id, colour_name, fabric, roll_no, part, qty (signed), uom, rate, reason, lot_id (অনুমোদনে) |
| `knit_grey_month_closes` | period (YYYY-MM, অনন্য), remarks, closed_at, closed_by |

মাইগ্রেশন `2027_01_15_000100_create_knitting_sc_grey_challans`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_sc_grey_receipts` + lines | rcv_no (OGR-…), rcv_date, party_id (বাইরের নিটার), store_id (গ্রহণের আগে আবশ্যক), challan_no (পার্টির, পার্টিতে অনন্য), challan_date, order_id, gate_pass_no, vehicle_no, note, status (DRAFT / RECEIVED / QC_PASSED / QC_HOLD / QC_REJECTED / CANCELLED); লাইনে type (FABRIC / COLLAR / CUFF), fabric_type_id, description, colour_id + colour_name, shade, roll_no, finish_dia, gsm, r_qty, qty, uom (ফেব্রিক কেজি, কলার/কাফ পিস), remarks, lot_id |
| `knit_sc_grey_returns` + lines | challan_no (OGT-… / IGT-…), kind (OUT_HOUSE / IN_HOUSE), challan_date, party_id, store_id, order_id, receipt_id (আউট-হাউস প্রাপ্তি) বা sc_receipt_id (ইন-হাউস প্রোগ্রাম রিসিপ্ট), reason, gate_pass_no, vehicle_no, driver, note, status (DRAFT / ISSUED / RECEIVED_BACK / CANCELLED); লাইনে type, lot_id (ঐচ্ছিক), description, colour, shade, roll_no, r_qty, qty, uom, remarks |
| `knit_sc_deliveries` + lines | challan_no (IGD-…), challan_date, party_id, store_id, gate_pass_no, vehicle_no, driver, receiver, receiver_mobile, note, status (DRAFT / ISSUED / CANCELLED); লাইনে section (FABRIC / COLLAR_CUFF / YARN), part (COLLAR / CUFF), lot_id (গ্রে) বা yarn_lot_id (সুতা), description, colour, shade, roll_no, lot_no, finish_dia, mc_gauge, r_qty, bags, qty, uom, remarks |

মাইগ্রেশন `2027_01_16_000100_create_knitting_yarn_transfers` (চারটা কাগজই DRAFT / ISSUED / FINALIZED / CANCELLED):

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_prog_transfers` + lines | challan_no (OYT-…), challan_date, sc_program_id, from_party_id (প্রোগ্রামের তখনকার পার্টি), to_party_id, store_id (Yarn Store), gate_pass_no, vehicle_no, note; লাইনে composition_id, yarn_count_id, colour_group, shade, lot_no, bags, qty, uom, remarks |
| `knit_yd_grey_transfers` + lines | challan_no (GYT-…), challan_date, gate_pass_no, vehicle_no, party_id (ডাই হাউস), program_id (Y/D প্রোগ্রাম, ঐচ্ছিক), store_id, remarks; লাইনে program_line_id (শেড, ঐচ্ছিক), lot_id (সুতার stock line, আবশ্যক), colour_id + colour_name, lot_no, pack_qty, qty, uom, remarks |
| `knit_yd_dyed_receipts` + lines | challan_no (DYR-…), challan_date, gate_pass_no, party_id, grey_transfer_id, program_id, buyer_id, order_id, store_id, remarks; লাইনে knit_prog_id আর collar_prog_id (S/C প্রোগ্রাম), composition_id, yarn_count_id, colour_id + colour_name, lot_no (আবশ্যক, বড় হাতে), pack_qty, qty, uom, remarks, lot_id (finalize-এ যে stock line-এ উঠল) |
| `knit_yd_dyed_transfers` + lines | challan_no (DYT-…), challan_date, gate_pass_no, vehicle_no, party_id (আউট-হাউস নিটার), store_id, remarks; লাইনে receipt_line_id (আবশ্যক), knit_prog_id, collar_prog_id, pack_qty, qty, uom, remarks |

মাইগ্রেশন `2027_01_17_000100_create_knitting_sc_bills`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_sc_bills` + lines | bill_no (SIB- / SOB- / YDB-…), kind (IN_HOUSE / OUT_HOUSE / YD), bill_date, party_id, party_bill_no, ref_bill_id (যে বিলের বদলে; নিজের টেবিলে FK, table তৈরির পরে যোগ করা), rev_descr, currency_id (ফাঁকা = মূল মুদ্রা), gross, deduction, vat, net, remarks, status (DRAFT / SUBMITTED / APPROVED / REVISED / CANCELLED), submitted_at, approved_at/by; লাইনে sc_receipt_id বা sc_program_id বা yd_program_id (kind অনুযায়ী একটা), order_id, description, colour_id + colour_name, shade, challan_no, qty, uom, rate, amount, remarks |
| `knit_sc_debit_memos` + lines | memo_no (DMO-…), memo_date, party_id, currency_id, reason, remarks, total, status (DRAFT / SUBMITTED / APPROVED / SETTLED / CANCELLED), approved_at/by; লাইনে part (FABRIC / COLLAR / CUFF / YARN), order_id, colour_id + colour_name, description (আবশ্যক), challan_no, qty, uom, rate, amount, remarks |

## ৫. Workflow / Status ও Business Rules

- **Grey খাতা (`GreyStore`):** stock line-এর key = স্টোর, kind, অর্ডার, পার্টি, রোল (id বা নম্বর), রং, ফেব্রিক, ডায়া, GSM, part। প্রতিটা ডকুমেন্ট নিজের kind আর নম্বরে signed ওজন (আর রোলের সংখ্যা) পোস্ট করে; আবার পোস্ট করলে আগেরটা বদলায়। বন্ধ মাসে কোনো পোস্টিং হয় না (`GreyStore::assertOpen`)।
- **Grey Fabric Store Receipt:**
  - Fabric Receive: Receive Date (আজ আর আগের ৩০ দিন), Roll#, Store*, Rack#* (সেই স্টোরের)। Roll# স্ক্যান বা লিখে Enter / Receive চাপলেই নেওয়া হয়, সেভ নেই।
  - রোল IN_KNITTING হতে হবে, একবারই নেওয়া যায়, ইন্সপেকশনে Rejected রোল নেওয়া হয় না। স্টোর আর দিনের নোট (GFR-…) না থাকলে তৈরি হয়; রোলের নিজের stock line-এ ওজন (weight − deducted) আর ১টা রোল পোস্ট হয় (`RECEIVE`); রোল GREY_STORE হয়।
  - দিন খোলা থাকতে Withdraw (রোল ফেরত IN_KNITTING) বা Clear day; Confirm day-এর পরে আর বদলায় না, Print Challan।
  - Fabric Stock ট্যাব: Report Type — Fabric Stock (স্টোরে থাকা লাইন, রোল ধরে; Store, Rack#, Date (যেদিন ঢুকেছে), Prod.Type) বা Receive Notes (দিনের নোট, সারিতে Print Challan)। Show চাপলে খোঁজে।
- **Grey Fabric Opening Stock:** Add Opening Line-এ লাইন লেখা হয় (খোলা, কিছু নড়ে না): kind (radio), Opening Date*, Store*, Buyer, Job/W.Style (বাছলে বায়ার বসে), Fabric*, Color বা Color (as written), Finish Dia, GSM, Opening*, Cost/Unit। তিন ট্যাবে (In-house, Collar-Cuff, S/C In-house) খোলা লাইন টিক দিয়ে **Finalize** — `OPENING` পোস্ট, মূল্য = qty × cost; finalized লাইন আর বদলায় না। প্রথম ট্যাবে তিনটাই একসাথে (টিক ছাড়া)।
- **Store-to-Store Grey Transfer:** Trans Type*, From Store* আর To Store* (আলাদা), Style/Order# দিলে শুধু সেই অর্ডারের লাইন। Roll picker-এ From Store-এর থাকা লাইন — ওজন করা রোল পুরোটা সরে (Qty বন্ধ); রোল ছাড়া ব্যালান্স থেকে হাতে Qty, স্টোরে যা আছে তার বেশি নয়। একই রোল দুবার নয়।
  - সেভ = Create Draft; তালিকার **Submit Requisition** প্রথম স্টোর থেকে নামায় (`TRANSFER_OUT`, ব্যালান্স আবার যাচাই); **Receive Requisition** দ্বিতীয় স্টোরে একই ফেব্রিকের লাইনে তোলে (`TRANSFER_IN`; রোল থাকলে রোলসহ); Draft বা Submitted থেকে Cancel (Submitted হলে ফেরত)।
- **Grey Fabric Stock Correction:** লাইনে Order, Color, Fabric*, Roll#, Part, Adjust Qty* (signed, শূন্য নয়), UoM*, Rate, Reason; Value = qty × rate। সেভে কিছু নড়ে না; **Verify** → **Approve** পোস্ট করে (`ADJUST`)।
  - কোন লাইনে বসে: Roll# কোনো রোলের হলে সেই রোলের লাইন; নইলে স্টোরের যে লাইন অর্ডার, শেড আর ফেব্রিকে মেলে (বেশি ব্যালান্সেরটা) — না থাকলে নতুন। ঋণাত্মক লাইন স্টোরে যা আছে তার বেশি নামাতে পারে না।
  - Cancel যেকোনো অবস্থায় (Activity Status = Cancelled); Approved হলে পোস্টিং ফেরত।
- **Grey Fabric Stock Close:** শেষ হওয়া মাসই বন্ধ হয়। Show Exception (কিছু না বদলে): সেই মাসের তারিখের খোলা Receive Note, finalize না হওয়া Opening, Draft/Submitted Transfer, Draft/Verified Correction। Month Close একই যাচাই আবার করে, পাস হলে মাস বন্ধ (Remarks সহ)। Show বন্ধ মাসের তালিকা (সামনে নেওয়া ব্যালান্সসহ)।

### S/C চালান (২০২৬-১১-০১)

- **Out-House Grey Receipt Challan:** Rcv. Date*, Party*, Store, Challan#* (পার্টির; একই পার্টির একই চালান দুবার নয়), Challan Date*, Order#, Gate Pass#, Veicle#, Note; Challan Detail-এ লাইন (Type, Fab.Type, Description*, Color, Shade, Roll#, F.Dia, F.Gsm, R.Qty, Qty*, UoM — Collar/Cuff বাছলে UoM পিস)। সেভ = Draft। তালিকার **Receive** স্টোর ছাড়া আটকায়; প্রতিটা লাইন খাতায় ওঠে (ফেব্রিক IN_HOUSE, কলার/কাফ COLLAR_CUFF + part; পার্টি, অর্ডার আর বায়ার সহ; R.Qty রোলের সংখ্যা), kind `SC_RECEIVE`। Cancel Draft বা Received থেকে — Received হলে ফেরত, কিন্তু কিছু বেরিয়ে গেলে আটকায়।
- **Out-House Collar & Cuff QC Receipt:** শুধু কলার বা কাফ থাকা, গৃহীত চালান। Status filter; সারিতে QC Pass (Received বা Hold থেকে), QC Hold (Received থেকে), QC Reject। QC স্টক নাড়ায় না — বাতিল কলার ফেরত যায় Return Challan-এ।
- **Grey Return Challan (Out-House / In-House):** Challan Date*, Party*, Store, Order#, Against Rcv# (আউট-হাউসে সেই পার্টির গৃহীত প্রাপ্তি; ইন-হাউসে সেই পার্টির প্রোগ্রাম রিসিপ্ট), Reason, Gate Pass#, Veicle#, Driver, Note। লাইনে Roll (স্টোরের লাইন; বাছলে বিবরণ, রং, রোল নম্বর আর ব্যালান্স বসে; রোল হলে পুরোটা) অথবা রোল ছাড়া বিবরণ আর পরিমাণ। তালিকার **Issue** রোল/লাইন স্টোর থেকে নামায় (`SC_RETURN`), রোল ছাড়া লাইন কিছু নড়ায় না; **Received Back** পার্টির স্বীকৃতি; Draft / Issued থেকে Cancel (Issued হলে ফেরত)।
- **In-House Grey Delivery Challan:** Party*, Store*, Chln. Date*, Gate (Gate Pass#, Veicle#, Driver), Receiver (Receiver, Rcver Mob#), Note; তিন ভাগ — Fabric Delevery (Add Roll: স্টোরের ফেব্রিক লাইন; ফেব্রিক, শেড, ডায়া, মেশিন গেজ (কার্ড থেকে), ওজন বসে), Collar/Cuff Delevery (Add Collar/Cuff: Part + কলার/কাফ লাইন, পিসে), Yarn Return (Add Yarn: সেই স্টোরে পার্টির নিজের সুতার লট — Bags, Qty)। কোনো এক ভাগে কিছু থাকলেই চলে। **Issue**: গ্রে লাইন নামে (রোল পুরোটা, রোল DELIVERED), সুতা yarn খাতা থেকে নামে (`SC_YARN_RETURN`)। Cancel Issued থেকেও — দুই খাতাতেই ফেরত, রোল আবার GREY_STORE।

### সুতার কাগজ (২০২৬-১১-০১)

চারটাই Draft হিসেবে সেভ হয়, তালিকা থেকে **Issue** → **Finalize**, আর Draft / Issued থেকে **Cancel**। শুধু Draft বদলানো যায়।

- **Out-House Yarn Transfer:**
  - ফর্মে Challan Information (Challan#, Challan Date*, Company*), Programme (S/C Prog#* — শুধু Approved প্রোগ্রাম, From Party* প্রোগ্রাম থেকে, To Party*, Yarn Store), Gate (Gate Pass#, Veicle#, Note), আর Yarn Detail (Yarn, Color, Shade, Lot#, Bags, Qty, UoM, Remarks)। গ্রিড খালি থাকতে পারে।
  - To Party প্রোগ্রামের এখনকার পার্টি হলে আটকায়। লাইনে সুতার আইটেম বা কাউন্ট লাগে।
  - **Finalize** প্রোগ্রামের `party_id` গ্রহণকারী পার্টিতে বদলায়। মাঝে প্রোগ্রাম অন্য কারো হাতে গেলে আটকায়। সুতা আগেই স্টোরের বাইরে, তাই খাতায় কিছু নড়ে না।
- **Grey Yarn Transfer for Dyeing:**
  - বাঁ দিকে Y/D Program List (Y/D Program #, Party, Ship Month, To send (Kg)), শুধু যেগুলোর পাঠানো বাকি আছে। বাছলে পার্টি বসে, আর শেডগুলো প্রতিটার বাকি পরিমাণসহ গ্রিডে আসে (শেডের পরিমাণ − প্রোগ্রামের Issue-এর আনুপাতিক ভাগ)।
  - ফর্মে Challan#, Challan Date*, Gate Pass#, From Party*, Y/D Program (ঐচ্ছিক), Company*, Store, Vehicle#, Remarks। লাইনে Programme Shade, Yarn Item (স্টোরের সুতার stock line, শেডের সুতা আর কাউন্টে মিলিয়ে), Color, Lot# (stock line থেকে), Pack Qty, Yrn Qty(Kg), UoM।
  - Store খালি থাকলে প্রথম লাইনের স্টোর ধরা হয়, আর সব লাইন সেখান থেকেই যায়। সেভ আর Issue দুই জায়গাতেই lot-এর ব্যালান্স যাচাই হয়।
  - **Issue** সুতা খাতা থেকে নামায় (`YD_GREY_OUT`, মূল্য = qty × lot rate, বন্ধ মাসে আটকায়)। প্রোগ্রাম থাকলে এটা প্রোগ্রামের Issue-তে যোগ হয় (`YarnDyeing::figures`)। Issued থেকে Cancel-এ ফেরত।
- **Dyed Yarn Receipt Challan:**
  - বাঁ দিকে Grey Yarn Transfer Challan (Date, Challan#, From Party, G.Yarn Qty): Issued / Finalized চালান, যার পুরোটা এখনো ফেরত আসেনি। বাছলে পার্টি, প্রোগ্রাম, বায়ার, অর্ডার আর শেডগুলো (সুতা, রং, লট, পরিমাণ) বসে।
  - ফর্মে Challan#, Date*, Gate Pass#, Party*, Against (Grey Yarn Challan — শুধু সেই পার্টির, Program Ref#, Buyer A/C, Work Style/Job#), Booked To (Company*, Store, G.Yarn Qty), Remarks। লাইনে Knitting Prog#, Collar Prog#, Yarn Item, Color, Lot#*, Pack Qty, Yrn Qty(Kg), UoM।
  - **Finalize**-এর আগে Store লাগে। Finalize-এ প্রতিটা লাইন সুতা খাতায় ওঠে (`YD_DYED_IN`): স্টোর, সুতা, রং, ডাইড লট, পার্টি = ডাই হাউস, বায়ার, অর্ডার, source YARN_DYED। Finalized-এর পরে Cancel হয় না।
  - তালিকায় G.Yarn Qty (গ্রে চালানের ওজন) আর Transfer? (Yes / Part / No, কতটা forward হয়েছে)।
- **Out-House Dyed Yarn Transfer Challan:**
  - বাঁ দিকে YD Receive Challan (Date, Challan#, Qty(Kg), Include Challan): Finalized রিসিভ যার কিছু এখনো forward হয়নি। Include করলে বাকি সব লাইন গ্রিডে আসে (একই লাইন দুবার নয়), আর স্টোর খালি থাকলে রিসিভের স্টোর বসে।
  - ফর্মে Challan#, Date*, Gate Pass#, Party*, Movement (Company*, Store, Vehicle#, Remarks)। লাইনে From Receive Line, Knitting Prog#, Collar Prog#, Color, Yarn Item, Lot#, Pack Qty, Yrn Qty(Kg), UoM।
  - রিসিভ লাইনে বাকি = রিসিভ − (বাতিল নয় এমন) forward। এই draft নিজেকে বাদ দিয়ে হিসাব করে, তাই আবার সেভ করলে দুবার গোনা হয় না।
  - **Issue** রিসিভ লাইনের stock line থেকে নামায় (`YD_DYED_OUT`); Issued থেকে Cancel-এ ফেরত।

### S/C bill আর Debit Memo (২০২৬-১১-০২)

- **তিন bill (In-House / Out-House Sub-Contract Bill, Out-House Yarn Dyeing Bill):**
  - একই ফর্ম। Key Information (Bill#, Date*, Company*, Party*, Party Bill#), Revision (Ref.Bill#, Rev.Descr), Settlement (Currency — ফাঁকা মানে মূল মুদ্রা, Deduction, VAT), Remarks।
  - Bill Detail-এ Add Charge: প্রথম কলাম S/C Prog# (ইন-হাউস bill-এ পার্টির S/C programme receipt, আউট-হাউস bill-এ পার্টির Approved S/C programme) বা Y/D Prog# (পার্টির Y/D programme)। তারপর Order#, Description, Color, Shade, Challan#, Qty, UoM, Rate, Amount, Remarks।
  - প্রোগ্রাম বাছলে খালি ঘরে অর্ডার, প্রথম লাইনের ফেব্রিক, রং আর দর বসে। Picker-এ লেখা থাকে কতটা বিল হয়েছে আর কতটা পরিকল্পিত।
  - লাইন ছাড়া বিল আটকায়। অন্য পার্টির বা অন্য ধরনের প্রোগ্রাম আটকায়। একই পার্টির একই Party Bill# দুবার নয় (বাতিল/Revised বাদে)। Deduction গ্রস + VAT-এর বেশি নয়।
  - Amount = qty × rate, Net = Gross − Deduction + VAT; সার্ভার আবার হিসাব করে।
  - তালিকা (Status, Bill#): **Submit** (দাবি) → **Approve** (পেমেন্টযোগ্য); Draft / Submitted থেকে **Cancel**।
  - **সংশোধন:** Ref.Bill#-এ সেই পার্টির একটা Submitted বিল আর Rev.Descr (আবশ্যক)। নতুন বিল Submit হলে পুরোনোটা **Revised** — আর Approve হয় না, আর তার ফর্মে "Replaced by …" দেখায়। নতুন বিল বাতিল করলে পুরোনোটা আবার Submitted হয়।
  - পরিমাণের মোট (তালিকা, গ্রিড, ফর্ম) এককপ্রতি আলাদা, যেমন "85.000 KG · 300.000 PCS"।
- **Out-House Debit Memo:**
  - Memo Information (Memo#, Memo Date*, Company*, Party*), Charge (Currency, Reason, Remarks)। Memo Detail-এ Part, Order, Color, Description*, Challan#, Qty, UoM, Rate, Amount। লাইন ছাড়া মেমো আটকায়।
  - তালিকা (Status, Company, Party, Memo#, From/To): **Submit** → **Approve** (দাবিযোগ্য) → **Settle**; Draft / Submitted থেকে Cancel।

## ৬. API ও পারমিশন

- `/knitting/fstore/…`: `options`; `receipts/day`, `receipts/scan`, `receipts/clear`, `receipts/rolls/{id}` (DELETE), `receipts` (+ `/{GFR}`, `/{GFR}/confirm`), `stock`; `openings` (+ PUT `/{GOB}`, `openings/finalize`); `closes` (+ `closes/exceptions`, POST); `lots?store=&order=`; `transfers` (+ `/{GFT}`, `/{GFT}/submit|receive|cancel`); `adjustments` (+ `/{GFA}`, `/{GFA}/verify|approve|cancel`)।
- **পারমিশন:** নতুন `knitting.fstore.view` আর `knitting.fstore.manage` — FACTORY_ADMIN আর FACTORY_MANAGER পায়। মেনুতে নতুন ফোল্ডার KNITTING.FABRIC_STORE ("Fabric Store")।
- **S/C চালান:** `/knitting/fstore/sc/options`, `sc/party-yarn?party=&store=`, `sc/receipts` (+ `?qc=1`, `/{OGR}`, `/{OGR}/receive|qc-pass|qc-hold|qc-reject|cancel`), `sc/returns/{out-house|in-house}` (+ `/{no}`, `/{no}/issue|received-back|cancel`), `sc/deliveries` (+ `/{IGD}`, `/{IGD}/issue|cancel`)। দেখা `knitting.fstore.view` অথবা `knitting.production.view`; লেখা `knitting.fstore.manage`।

- **সুতার কাগজ:** `/knitting/fstore/yarn/options` (S/C প্রোগ্রাম, Y/D প্রোগ্রাম আর শেডসহ, গ্রে চালান আর শেডসহ, সুতার stock line), `yarn/forwardable`, এবং `yarn/prog-transfers`, `yarn/grey-transfers`, `yarn/dyed-receipts`, `yarn/dyed-transfers` (প্রতিটাতে GET / POST, `/{no}` GET / PUT, `/{no}/issue|finalize|cancel`)। দেখা `knitting.fstore.view` অথবা `knitting.ystore.view`; লেখা `knitting.fstore.manage`।

- **Bill আর Debit Memo:** `/knitting/fstore/bills/options` (প্রতিটা kind-এর প্রোগ্রাম, পার্টি, অর্ডার, প্রথম লাইনের ফেব্রিক/রং/দর, planned, billed), `bills/{in-house|out-house|yd}` (+ `/{no}` GET / PUT, `/{no}/submit|approve|cancel`), `debit-memos` (+ `/{DMO}`, `/{DMO}/submit|approve|settle|cancel`)। দেখা `knitting.fstore.view` অথবা `knitting.production.view`; লেখা `knitting.fstore.manage`।

## ৭. Frontend পেজ (লাইট থিম)

- `fstore-screens.js` আর `fstore-grey.jsx`; translator `fs.*`; action `fstoreAction`।
- প্রিন্ট (`lib/print-document`, preview দেখে যাচাই): Grey Fabric Receive Challan (রোল নম্বরের ক্রমে, Knitting In-charge / Received By / Store In-charge), Grey Fabric Transfer Challan, Grey Fabric Stock Adjustment।
- Radio group (Report Type, Opening-এর kind) `<div role="radiogroup">`-এ — Field একটা `<label>`, আর label-এর ভেতরে label দিলে ভুল radio বাছাই হয়ে যায়।
- **S/C চালান** (`fstore-sc.jsx`): চার তালিকা (Show / Reset), তিন ফর্ম (সারি ধরে লাইন), QC তালিকা Receipt-এর তালিকারই আরেক রূপ। লাইনের picker-এর উৎস একসাথে দুবার আনলে শুধু শেষেরটা ধরা হয়; খোলা draft-এর উৎস server-এ।
  - প্রিন্ট (preview দেখে যাচাই): S/C Grey Fabric Receive Challan, Grey Fabric Return Challan, Grey Fabric Delivery Challan (তিন ভাগ)। মোট ঘরে কেজি আর পিস আলাদা ("85.000 KG · 300.000 PCS") — আগে যোগ হয়ে ৩৮৫ দেখাত।

- **সুতার কাগজ** (`fstore-ytr.jsx`, translator `ytr.*`):
  - একটা তালিকা component (`YtrList`, কাগজ ধরে ফিল্টার আর কলাম; সারিতে Issue / Finalize / Cancel / Print)। চারটা ফর্ম একটা `useDesk` hook-এ চলে।
  - তিনটা ফর্মের বাঁ দিকে `SideList` (এখন `ystore-yd.jsx` থেকে export)।
  - Dyed Transfer-এর রিসিভ লাইনের তালিকা server-এ আসে, আর সেভের পরে আবার আনা হয়। `TONE`-এ FINALIZED যোগ হয়েছে।
  - প্রিন্ট (preview দেখে যাচাই): Out-House Yarn Transfer Challan, Grey Yarn Transfer Challan for Dyeing, Dyed Yarn Receive Challan, Dyed Yarn Transfer Challan (S/C Out-house)। সবই `fstore-sc.jsx`-এর `printChallan` / `lineSection` দিয়ে।
    - গ্রে চালানের Yarn Item-এ লট আবার আসছিল (Lot# কলাম থাকতেও), সেটা বাদ দেওয়া হয়েছে।
    - প্রিন্টে "Vehicle#" লেখা হয়, যদিও ফর্মে Brickland-এর বানান "Veicle#" রাখা আছে।

- **Bill আর Debit Memo** (`fstore-bill.jsx`, translator `scb.*`):
  - `BillList` / `BillEntry` (kind ধরে), `MemoList` / `MemoEntry`। দুই ফর্মের গ্রিড একটা `ChargeGrid`-এ; লেখার সাথে সাথে Amount, Total Qty, Gross আর Net।
  - Ref.Bill#-এর তালিকা server-এ আনা register থেকে। Revision Submit হলে তালিকা আবার আনা হয়, যাতে পুরোনোটা Revised দেখায়। `TONE`-এ REVISED আর SETTLED যোগ হয়েছে।
  - প্রিন্ট (preview দেখে যাচাই): S/C In-house / Out-house Knitting Bill আর Yarn Dyeing Bill (Gross, Deduction, VAT, Net; Prepared / Checked / Approved By), আর Debit Memo (Total; Party Acknowledgement)। মেমোর প্রিন্টে শিরোনাম "Bill Detail" আসছিল, সেটা "Memo Detail" করা হয়েছে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১৮টার ১৮টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_13_000100_create_knitting_grey_store.php`
- `app/Modules/Knitting/Models/KnitGreyLot.php`, `KnitGreyMove.php`, `KnitGreyReceipt.php`, `KnitGreyReceiptRoll.php`, `KnitGreyOpening.php`, `KnitGreyTransfer.php`, `KnitGreyTransferLine.php`, `KnitGreyAdjustment.php`, `KnitGreyAdjustmentLine.php`, `KnitGreyMonthClose.php`
- `app/Modules/Knitting/Support/GreyStore.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitGreyStoreApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitGreyDocApiController.php` (Correction)
- `app/Modules/Knitting/Http/Controllers/Api/KnitGreyMoveApiController.php` (Store-to-Store; 15/04-এর grey চলাচলের সাথে এক কাগজ)
- `app/Modules/Knitting/Database/Migrations/2027_01_15_000100_create_knitting_sc_grey_challans.php`
- `app/Modules/Knitting/Models/KnitScGreyReceipt.php`, `KnitScGreyReceiptLine.php`, `KnitScGreyReturn.php`, `KnitScGreyReturnLine.php`, `KnitScDelivery.php`, `KnitScDeliveryLine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitScGreyApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_16_000100_create_knitting_yarn_transfers.php`
- `app/Modules/Knitting/Models/KnitYarnProgTransfer.php`, `KnitYarnProgTransferLine.php`, `KnitYdGreyTransfer.php`, `KnitYdGreyTransferLine.php`, `KnitYdDyedReceipt.php`, `KnitYdDyedReceiptLine.php`, `KnitYdDyedTransfer.php`, `KnitYdDyedTransferLine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYarnTransferApiController.php`
- `app/Modules/Knitting/Support/YarnDyeing.php` (গ্রে চালান প্রোগ্রামের Issue-তে)
- `app/Modules/Knitting/Database/Migrations/2027_01_17_000100_create_knitting_sc_bills.php`
- `app/Modules/Knitting/Models/KnitScBill.php`, `KnitScBillLine.php`, `KnitScDebitMemo.php`, `KnitScDebitMemoLine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitScBillApiController.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/knit-production/[screen]/fstore-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js` (`fstoreAction`)
- `frontend/src/components/knitting/fstore-grey.jsx`
- `frontend/src/components/knitting/fstore-sc.jsx` (`printChallan`, `lineSection` export)
- `frontend/src/components/knitting/fstore-ytr.jsx`
- `frontend/src/components/knitting/fstore-bill.jsx`
- `frontend/src/components/knitting/ystore-yd.jsx` (helper আর `SideList` export)
- `frontend/src/components/knitting/prod-yd.jsx` (`TONE`-এ SUBMITTED, RECEIVED, VERIFIED, FINALIZED, REVISED, SETTLED)

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php` (`fs.*`, `scg.*`, `ytr.*`, `scb.*`)

**Test**
- `tests/Feature/Knitting/KnittingGreyStoreTest.php`
- `tests/Feature/Knitting/KnittingScGreyTest.php`
- `tests/Feature/Knitting/KnittingYarnTransferTest.php`
- `tests/Feature/Knitting/KnittingScBillTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- ১৫/০৫-এর সব স্ক্রিন বানানো হয়ে গেছে। পরের কাজ Knit Production-এর বাকি sub-module (Index দেখুন)।

## ৯. টেস্ট ও যাচাই

- `KnittingGreyStoreTest` — ১টা টেস্ট, ৯৫টা assertion, test2 DB-তে পাস:
  - রিসিপ্ট: স্টোর/র‍্যাক ছাড়া Brickland-এর মেসেজ; অন্য স্টোরের র‍্যাক আটকায়; অজানা ট্যাগ; GFR-… নোট, রোল GREY_STORE, ওজন ২২.৪ − ০.২ = ২২.২, র‍্যাক আর কার্ডের রং; একই রোল দ্বিতীয়বার আর Rejected রোল আটকায়; Withdraw-এ রোল ফেরত IN_KNITTING; Confirm-এর পরে স্ক্যান আর Withdraw আটকায়; Fabric Stock আর Receive Notes।
  - Opening: "Opening is required."; অর্ডার থেকে বায়ার; টিক ছাড়া Finalize আটকায়; finalize-এ খাতায় ১০০; finalized লাইন বদলায় না।
  - Transfer: লাইন ছাড়া, একই স্টোর, বেশি পরিমাণ আটকায়; রোল পুরোটা (২২.২, ১ রোল); Draft থেকে Receive আটকায়; Submit-এ প্রথম স্টোর ০ / ৭০, Receive-এ দ্বিতীয় স্টোরে রোল আর ৩০; Received-এর পরে Cancel আটকায়।
  - Correction: লাইন ছাড়া আর শূন্য পরিমাণ আটকায়; Draft থেকে Approve আটকায়; Verify-তে কিছু নড়ে না, Approve-এ ৭০ → ৬৫.৫ (অর্ডার + শেড + ফেব্রিকে মিলে); বেশি নামানো আটকায়; Approved Cancel-এ ফেরত ৭০।
  - Close: চলতি মাস আটকায়; আগের মাসের খোলা Opening Exception-এ আসে আর বন্ধ আটকায়; finalize-এর পরে বন্ধ; বন্ধ মাসে finalize আটকায়।
  - MenuAccessTest আর KnittingProductionTest আবার পাস।
- **নিজে ব্রাউজারে যাচাই (dev, ডাটা রাখা আছে):**
  - **GFR-2026110001** (Fabric store, ০১ নভেম্বর): KC-2026100001/1 (২২.৫, Fabric rack 1), /2 (২১.৫, Fabric rack 1); /3 স্ক্যান করে Withdraw করা — এখনো নিটিং ফ্লোরে। র‍্যাক ছাড়া আর দ্বিতীয়বার স্ক্যানের মেসেজ দেখা। Confirm day, Print Challan।
  - **GOB-2026110001** Rib 1x1 30/1 Combed 220 GSM, Navy Blue, LPP / SS27-TEE01, ১২০ kg @ ৪.৫ (In-house), **GOB-2026110002** Flat knit collar 2x1 ১৫ kg (ভুলে In-house হয়ে finalize হয়ে গেছে — UI-র radio ঠিক করার আগে), **GOB-2026110003** Flat knit cuff 1x1 ৮ kg (Collar-Cuff); সব finalized।
  - **GFT-2026110001** Bulk To Bulk, CUT-REQ-1101, Fabric store → Spare parts store: KC-2026100001/1 (পুরো রোল) + Rib-এর ৩০ kg (B-01); Submit → Receive।
  - **GFA-2026110001** "November floor count, rack F1": Rib Navy −২.৫ @ ৪.৫ = −১১.২৫; Verify → Approve। Fabric store-এ Rib এখন ৮৭.৫।
  - Stock Close: অক্টোবর ২০২৬-এ Show Exception "Nothing stands in the way"; নভেম্বরে Month Close "has not finished yet"। কোনো মাস বন্ধ করা হয়নি।
  - তিনটা প্রিন্টের preview দেখা হয়েছে; চালানে রোল উল্টো ক্রমে আসছিল — নম্বরের ক্রমে করা হয়েছে।

- **S/C চালান (২০২৬-১১-০১):** `KnittingScGreyTest` — ১টা টেস্ট, ৫৭টা assertion, পাস; MenuAccessTest আবার পাস।
  - Receipt: লাইন আর পার্টির চালান ছাড়া আটকায়; "pcs" → PCS; একই পার্টির একই চালান দুবার আটকায়; স্টোর ছাড়া Receive আটকায়; Receive-এ ফেব্রিক ৬০ (রোল AK-R1, অর্ডারের বায়ার) আর কলার ৪০০ (part Collar); তালিকায় কেজি, পিস আর রোল আলাদা।
  - QC: শুধু কলার/কাফের চালান তালিকায়; Hold → Pass; Passed থেকে Reject আটকায়।
  - Return: অন্য পার্টির প্রাপ্তি আটকায়; বেশি পরিমাণ আটকায়; বিবরণ আর রোল নম্বর লাইন থেকে; Draft-এ কিছু নড়ে না, Issue-এ ৬০ → ৩৫; Received Back; যার ফেব্রিক বেরিয়ে গেছে সেই প্রাপ্তি বাতিল আটকায়; ভুল kind-এর route-এ 404; ইন-হাউস return প্রোগ্রাম রিসিপ্টের বিপরীতে।
  - Delivery: খালি চালান আটকায়; অন্য পার্টির সুতা আটকায়; রোল নম্বর, কার্ডের গেজ (24G), সুতার লট; Issue-এ রোল ০ (DELIVERED), কলার ৪০০ → ২৫০, সুতা ৫০ → ৩৮; Cancel-এ সব ফেরত।
- **নিজে ব্রাউজারে যাচাই (S/C চালান, dev, ডাটা রাখা আছে):**
  - **OGR-2026110001** Coats Bangladesh, CB/GR-1101, LPP / SS27-TEE01, Fabric store: S/J 30/1 Combed 160 GSM কালো CB-R1, ৪ রোল, ৮৫ kg + Flat knit collar 2x1 ৩০০ পিস। Receive → QC Hold → QC Pass। খালি সেভের মেসেজ দেখা।
  - **OGT-2026110001** Coats-কে, OGR-2026110001-এর বিপরীতে, "Holes along the selvedge": CB-R1-এর ১০ kg + Cut pieces ১.৫ kg (রোল ছাড়া); Issue → Received Back।
  - **IGT-2026110001** Square Textiles, SQT-KP-0091-এর বিপরীতে, Rib 1x1 30/1 ৩ kg (রোল ছাড়া), Draft।
  - **IGD-2026110001** Square Textiles, Fabric store, GP-2201, DM-TA-11-2233, Karim: CB-R1-এর ২০ kg + Flat knit collar ১০০ পিস; Issue।
  - তিনটা প্রিন্টের preview দেখা।

- **সুতার কাগজ (২০২৬-১১-০১):** `KnittingYarnTransferTest` — ২টা টেস্ট, ৭০টা assertion, test2-এ পাস। KnittingYarnDyeingTest, KnittingScGreyTest আর KnittingSubContractTest আবার পাস (৪টা টেস্ট, ২৩৭টা assertion)।
  - Programme transfer: শুধু Approved প্রোগ্রাম options-এ; Draft প্রোগ্রাম, একই পার্টি আর সুতা ছাড়া লাইন আটকায়; খালি গ্রিডে সেভ হয়; Draft থেকে Finalize আটকায়; Issue-এর পরে বদলানো আটকায়; Issue-তে পার্টি একই থাকে, Finalize-এ বদলায়; Finalized-এর পরে Cancel আটকায়।
  - গ্রে চালান: শেড ছাড়া আর lot-এর বেশি পরিমাণ আটকায়; স্টোর lot থেকে আসে; Draft-এ কিছু নড়ে না, Issue-এ ৩০০ → ১৮০ আর প্রোগ্রামের Issue ১২০; Cancel-এ ফেরত।
  - Dyed receipt: অন্য পার্টির গ্রে চালান আটকায়; G.Yarn Qty ১২০; স্টোর ছাড়া Finalize আটকায়; Finalize-এ ডাইড লট DY-1 ১১৫ (YARN_DYED, Navy, পার্টি ডাই হাউস); Finalized Cancel আটকায়।
  - Forward: রিসিভ লাইন forwardable-এ; ৭০ draft, আবার সেভে নিজেকে দুবার গোনে না; ৪৬ বেশি ("only 45 is left"); Issue-এ ১১৫ → ৪৫; তালিকায় forwarded ৭০ (Part); Cancel-এ ফেরত।
- **নিজে ব্রাউজারে যাচাই (সুতার কাগজ, dev, ডাটা রাখা আছে):**
  - **OYT-2026110001:** SCP-2026100001, Square Textiles → Coats Bangladesh, Yarn store, GP-OYT-01, DM-GA-1101। লাইন: 100% Cotton 30/1 Natural, শেড A, লট SQ-3011, ৪ ব্যাগ, ১০০ kg। Issue → Finalize। **SCP-2026100001 এখন Coats Bangladesh-এর।** খালি সেভের মেসেজ দেখা হয়েছে।
  - **GYT-2026110001:** Square Textiles, YDP-2026110001, Yarn store, GP-GYT-01। লাইন: Navy Blue শেড, SQ-3011-এর ২০ kg, ২ প্যাক। Issue → Finalize; SQ-3011 ১১৫ → ৯৫।
  - **DYR-2026110001:** GYT-2026110001-এর বিপরীতে (বাঁ তালিকা থেকে)। বায়ার LPP, অর্ডার SS27-TEE01 আপনা থেকে বসেছে; Knitting Prog# SCP-2026100001; ডাইড লট DY-NB-2611, ১৯.৫ kg, Yarn store। Issue → Finalize।
  - **DYT-2026110001:** Coats Bangladesh, Include Challan দিয়ে DYR-2026110001-এর ১২ kg, GP-DYT-01, DM-GA-3303। Issue করা হয়েছে, Finalize করা হয়নি। রিসিভের তালিকায় এখন "Part · 12.000"; DY-NB-2611-এ বাকি ৭.৫ kg।
  - চারটা প্রিন্টের preview দেখা হয়েছে।

- **Bill আর Debit Memo (২০২৬-১১-০২):** `KnittingScBillTest` — ১টা টেস্ট, ৬৩টা assertion, test2-এ পাস। MenuAccessTest আবার পাস।
  - Options-এ প্রতিটা kind-এর প্রোগ্রাম (ইন-হাউস: শুধু programme receipt; আউট-হাউস প্রোগ্রামের দর, ফেব্রিক, planned, received)।
  - আউট-হাউস bill: লাইন ছাড়া, অন্য পার্টির প্রোগ্রাম, অন্য ধরনের প্রোগ্রাম আর বেশি Deduction আটকায় ("2,931.00"); SOB-…, gross ২৮২০, net ২৮৯১, USD, KG; একই Party Bill# দুবার আটকায়; ভুল kind-এর route-এ 404; options-এ billed ১৫০।
  - Draft থেকে Approve আটকায়; Submit-এর পরে বদলানো আটকায়; Rev.Descr ছাড়া revision আটকায়; revision Submit-এ পুরোনোটা Revised (revised_by সহ) আর Approve আটকায়; revision বাতিলে পুরোনোটা আবার Submitted → Approve; Approved-এর পরে Cancel আটকায়; তালিকায় এককপ্রতি পরিমাণ।
  - ইন-হাউস (SIB-…, ৬০০) আর Y/D (YDB-…, ৩৮০০) বিল নিজের প্রোগ্রামে; Y/D বিলে ইন-হাউস প্রোগ্রাম আটকায়।
  - Debit memo: লাইন ছাড়া আর বিবরণ ছাড়া আটকায়; DMO-…, মোট ৬৫ (১২.৫ × ৪.৪ + ৪০ × ০.২৫), pcs → PCS; Draft থেকে Settle আটকায়; Submit → Approve → Settle; Settled-এর পরে Cancel আটকায়।
- **নিজে ব্রাউজারে যাচাই (bill ও memo, dev, ডাটা রাখা আছে):**
  - **SOB-2026110001:** Coats Bangladesh, CB/BILL-1101, BDT। লাইন: SCP-2026100001-এ S/J 30/1 Combed 160 GSM ৮৫ kg × ২২ আর Flat knit collar ৩০০ pcs × ১.৫। প্রোগ্রাম থেকে অর্ডার আর রং বসেছে। Deduction ৫২.৮, VAT ৯৩.৫, Net ২,৩৬০.৭০। Submit → Approve। খালি সেভের মেসেজ দেখা হয়েছে।
  - **SIB-2026110001:** Square Textiles, SQT/KB/0451, SQT-KP-0091-এ Rib 1x1 ৩ kg × ১৪। Submit করা।
  - **SIB-2026110002:** SIB-2026110001-এর revision, "Rate agreed at 12 per kg", ৩ kg × ১২ = ৩৬। Submit করার পরে SIB-2026110001 এখন Revised, আর তার ফর্মে "Replaced by SIB-2026110002"।
  - **YDB-2026110001:** Square Textiles, BDT, YDP-2026110001-এ Navy Blue yarn dyeing ১৯.৫ kg × ৯৫, চালান DYR-2026110001, VAT ৯২.৬৩, Net ১,৯৪৫.১৩। Submit করা।
  - **DMO-2026110001:** Coats Bangladesh, "Holes along the selvedge on roll CB-R1", Fabric · SS27-TEE01 · Black · CB-R1 rejected fabric · CB/GR-1101 · ১২ kg × ৪.৪ = ৫২.৮০। Submit → Approve (Settle করা হয়নি)।
  - চারটা প্রিন্টের preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

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

## ১১. As-built নোট

**আংশিক (২০২৬-১১-০১):** grey store-এর ভিত্তি — খাতা আর ৫টা স্ক্রিন।

- **15/04-এর grey চলাচল একই খাতায় (২০২৬-১১-০১):** Store-to-Store এখন `KnitGreyMoveApiController`-এ, kind STORE — Flat Knit, Grey to Batch, Order-to-Order, Reuse আর দুটো Leftover-এর সাথে এক কাগজ ([04_Knit_Production](04_Knit_Production.md))। রুট আর আচরণ আগের মতোই। খাতার লাইনে এখন batch_no আছে। Store Receipt-এ খোলা Collar/Cuff কাগজের রোল স্ক্যান আটকায়।

- **Brickland probe (fs01):** পাঁচটা স্ক্রিনের ফর্ম, লেখা, বোতাম, Month-এর অপশন আর Correction-এর লাইনের কলাম; Racking Board-ও (সেখানে সুতার মতো খোঁজার ফর্ম — পরের ভাগে)। Brickland demo-তে সব তালিকা ফাঁকা, তাই সেখানে কিছু তৈরি করা হয়নি।
- **রোল মুভমেন্ট:** রিসিপ্টই রোলকে GREY_STORE করে (আগে সবসময় IN_KNITTING থাকত)। Store-to-Store-এ মুভমেন্ট বদলায় না (তখনো grey store)।
- **S/C চালান ভাগ (sc01 probe, ২০২৬-১১-০১):** পাঁচ স্ক্রিনের ফর্ম, লেখা, লাইনের কলাম (Receipt: Type … UoM; Return: Type, Roll …; Delivery-র তিন grid) আর QC পাতা। Brickland demo-তে সব তালিকা ফাঁকা, কিছু তৈরি করা হয়নি।
- **সুতার কাগজ ভাগ (yt05 / yt06 probe, ২০২৬-১১-০১):** চার স্ক্রিনের ফর্ম, লেখা, বাঁ তালিকার কলাম, লাইনের কলাম, তালিকার ফিল্টার আর ট্যাব দেখা হয়েছে। Brickland-এ ক্রম ৭০–৭৩ of 116। Brickland demo-তে সব তালিকা ফাঁকা।
- **Bill ভাগ (bl01 probe, ২০২৬-১১-০২):** তিন bill আর Debit Memo-র ফর্ম, লেখা, লাইনের কলাম। Y/D Bill পাতা আগের ক্রলে "503" দেখাচ্ছিল, এবার খুলেছে ("Out-House Yarn Dyeing Bill", লাইনের প্রথম কলাম Y/D Prog#)। Brickland demo-তে সব তালিকা ফাঁকা।
