# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Finance & Ledger](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `23/01`

**⬅ ফিরে যান:** [Finance & Ledger](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Payment Method Master | `/m/accounting/payment-mode` | 2 | 14 | 1 |
| 2 | Maker-Checker Setup | `/m/accounting/checker-maker` | 1 | 0 | 0 |
| 3 | Chart of Accounts Master | `/m/accounting/chart-of-account` | 3 | 10 | 1 |
| 4 | Cost Centre Grouping | `/m/accounting/cost-center-group` | 2 | 4 | 1 |
| 5 | Cost Centre Master | `/m/accounting/cost-center` | 2 | 7 | 1 |
| 6 | Cheque Book Register | `/m/accounting/cheque-book-registers` | 3 | 25 | 1 |
| 7 | Bank Account Master | `/m/accounting/manage-bank` | 3 | 13 | 1 |
| 8 | Fiscal Year Setup | `/m/accounting/fiscal-year` | 2 | 10 | 1 |
| 9 | Account Type Configuration | `/m/accounting/coa-type-configuration` | 2 | 4 | 1 |
| 10 | Statutory Ledger Rules | `/m/accounting/compliance-ledger-rules` | 2 | 1 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Payment Method Master

`/m/accounting/payment-mode`

**ট্যাব: Records 9**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Payment Mode Name | text |  | Cheque |
| Voucher Type | select |  | Bank Payment Voucher, Bank Receive Voucher, Cash Payment Voucher, Cash Receive Voucher, Contra Voucher, Bill Voucher, Jo |
| Search How money moves, and the voucher types each mode is offered on | search |  | Search 9 records… |
| Filter by Name | search |  |  |
| Filter by Short | search |  |  |
| Filter by Voucher Type | search |  |  |
| Filter by Remarks | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Name, Short, Voucher Type, Remarks, Status, ACTION
- সেকশন: Searching Data
- বাটন/অ্যাকশন: New payment mode, Search, Refresh, Export, Name, Short, Voucher Type, Remarks, Status, Edit

**ট্যাব: New payment mode**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Voucher Type | text |  |  |
| Name* | text | ✔ |  |
| Short Name | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- সেকশন: New payment mode
- বাটন/অ্যাকশন: New payment mode, Reset, Save payment mode

**ডায়ালগ: New payment mode**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Voucher Type | text |  |  |
| Name* | text | ✔ |  |
| Short Name | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Name is required.

#### Maker-Checker Setup

`/m/accounting/checker-maker`

**ভিউ**
> 02 of 28 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Chart of Accounts Master

`/m/accounting/chart-of-account`

**ট্যাব: Records 128**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search The chart of accounts, with each node's parent and whether it takes posti | search |  | Search 128 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name, Parent, Type, Postable?, Voucher Ref., Children, Status, ACTION
- বাটন/অ্যাকশন: Chart Of Accounts, New account, Export, Code, Name, Parent, Type, Postable?, Voucher Ref., Children, Status, Edit

**ট্যাব: Chart Of Accounts**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search nodes | search |  | Search… |
- সেকশন: Chart Of Accounts, Chart of Account Entry Section
- বাটন/অ্যাকশন: Chart Of Accounts, New account, 1 · ASSETS group 2, — 101 · NON CURRENT ASSETS group 2, — 102 · CURRENT ASSETS group 4, — — 10201 · Inventories group 5, — — 10202 · Accounts Receivable group 2, 1100 · Inventory — Yarn ledger head 0, 1200 · Accounts Receivable ledger head 0, 2 · LIABILITIES group 2, — 201 · NON CURRENT LIABILITIES group 3, — — 20101 · Long Term Loan group 1, — — 20102 · Loan From Director group 1, — 202 · CURRENT LIABILITIES group 3, — — 20201 · Short Term Loan group 3, 2100 · Accounts Payable ledger head 0, 2200 · Accrued Wages ledger head 0, 3 · EQUITY group 1, — 301 · CAPITAL ACCOUNT group 1, — — 30101 · Owners Equity group 3

**ট্যাব: New account**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Code | text |  |  |
| Name* | text | ✔ |  |
| Voucher Ref. | select |  | Buyer, Supplier/Party, Employee, Bank, Bank Account |
| Voucher For | select |  |  |
| Type* | select | ✔ | Select…, Asset, Liability, Equity, Revenue, Expenditure |
| Takes postings? Clear for a heading that only groups the accounts beneath it. | checkbox |  |  |
| Display Order | number |  |  |
- সেকশন: New account
- বাটন/অ্যাকশন: Chart Of Accounts, New account, -- Top-level class --, Reset, Save account

**ডায়ালগ: New account**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code | text |  |  |
| Name* | text | ✔ |  |
| Voucher Ref. | select |  | Buyer, Supplier/Party, Employee, Bank, Bank Account |
| Voucher For | select |  |  |
| Type* | select | ✔ | Select…, Asset, Liability, Equity, Revenue, Expenditure |
| Takes postings? Clear for a heading that only groups the accounts beneath it. | checkbox |  |  |
| Display Order | number |  |  |
- খালি Save-এ মেসেজ: Name is required.

#### Cost Centre Grouping

`/m/accounting/cost-center-group`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New cost center group

**ট্যাব: New cost center group**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Cost Center* | text | ✔ |  |
| Display Order | number |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- সেকশন: New cost center group
- বাটন/অ্যাকশন: New cost center group, Reset, Save cost center group

**ডায়ালগ: New cost center group**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Cost Center* | text | ✔ |  |
| Display Order | number |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Cost Center is required.

#### Cost Centre Master

`/m/accounting/cost-center`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New cost center

**ট্যাব: New cost center**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Name* | text | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office | select |  | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Cost Center Group | select |  | Select… |
| Dept/Sec | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- সেকশন: New cost center
- বাটন/অ্যাকশন: New cost center, Reset, Save cost center

**ডায়ালগ: New cost center**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Name* | text | ✔ |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office | select |  | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Cost Center Group | select |  | Select… |
| Dept/Sec | text |  |  |
| Remarks | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Name is required.

#### Cheque Book Register

`/m/accounting/cheque-book-registers`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Branch Name | select |  |  |
| Account No. | text |  | Account No. |
| Account Type | text |  | Account Type |
| Cheque No. | text |  | Cheque No. |
| With Finalized? | select |  | Yes, No |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Cheque Leaf Info, New cheque book, -- Select Bank --, Search, Refresh

**ট্যাব: Cheque Leaf Info**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search cheque books | search |  | Search… |
- সেকশন: Cheque Books, Cheque Leaf Info
- বাটন/অ্যাকশন: Cheque Leaf Info, New cheque book

**ট্যাব: New cheque book**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Account Number* | select | ✔ | search account no... |
| Company Name* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Cheque Book Number* | text | ✔ |  |
| Required Leaf* | number | ✔ |  |
| Cheque Prefix* | text | ✔ | CHQ |
| Cheque No From* | number | ✔ |  |
| Cheque No To* | number | ✔ |  |
| No of Leaf | text |  |  |
| Account Type | text |  |  |
| Bank Name | text |  |  |
| Branch Name | text |  |  |
| Requisition Date* | date | ✔ |  |
| Est. Receive Date | date |  |  |
| Receive Date* | date | ✔ |  |
| Remarks | textarea |  |  |
| Finalize A finalised book's leaves are in circulation and can no longer be edite | checkbox |  |  |
- সেকশন: New cheque book, Cheque Leaf Info
- বাটন/অ্যাকশন: Cheque Leaf Info, New cheque book, Reset, Save cheque book

**ডায়ালগ: New cheque book**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Account Number* | select | ✔ | search account no... |
| Company Name* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Cheque Book Number* | text | ✔ |  |
| Required Leaf* | number | ✔ |  |
| Cheque Prefix* | text | ✔ |  |
| Cheque No From* | number | ✔ |  |
| Cheque No To* | number | ✔ |  |
| No of Leaf | text |  |  |
| Account Type | text |  |  |
| Bank Name | text |  |  |
| Branch Name | text |  |  |
| Requisition Date* | date | ✔ |  |
| Est. Receive Date | date |  |  |
| Receive Date* | date | ✔ |  |
| Remarks | textarea |  |  |
| Finalize A finalised book's leaves are in circulation and can no longer be edite | checkbox |  |  |
- খালি Save-এ মেসেজ: Cheque No From is required.

#### Bank Account Master

`/m/accounting/manage-bank`

**ট্যাব: Records 26**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search The bank register, with each bank's branches | search |  | Search 26 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Bank Code, Bank Name (EN), Swift Code, Country, Is Local, Branches, Active, Status, ACTION
- বাটন/অ্যাকশন: Branches, New bank, Export, Bank Code, Bank Name (EN), Swift Code, Country, Is Local, Active, Status, Edit

**ট্যাব: Branches**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search banks | search |  | Search… |
- সেকশন: Bank List, Bank Branch
- বাটন/অ্যাকশন: Branches, New bank, ABN — ABN AMRO BANK active 0, ABBL — AGRANI BANK LIMITED active 0, BASIC — BASIC BANK LIMITED active 0, BNK-BRAC — BRAC Bank Ltd active 0, BNK-ASIA — Bank Asia Ltd active 0, EBL — EASTERN BANK LIMITED active 0, BNK-HSBC — HSBC Bangladesh active 0, JBL — JANATA BANK LIMITED active 0, NBL — NATIONAL BANK LIMITED active 0, PBL — PRIME BANK LIMITED active 0, PUBALI — PUBALI BANK LIMITED active 0, RUPALI — RUPALI BANK LIMITED active 0, SBL — SONALI BANK LIMITED active 0, SEBL — SOUTHEAST BANK LIMITED active 0, TBL — TRUST BANK LIMITED active 0, BNK-CITY — The City Bank Ltd active 0, UTTARA — UTTARA BANK LIMITED active 0

**ট্যাব: New bank**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bank Name (EN)* | text | ✔ |  |
| Bank Name (BN) | text |  |  |
| Bank Prefix* | text | ✔ |  |
| Swift Code | text |  |  |
| IBN No | text |  |  |
| Call Center No | text |  |  |
| Web Url | text |  |  |
| Is Local | checkbox |  |  |
| Is Treasury | checkbox |  |  |
| Is Active | checkbox |  |  |
- সেকশন: New bank
- বাটন/অ্যাকশন: Branches, New bank, Select…, Reset, Save bank

**ডায়ালগ: New bank**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Bank Name (EN)* | text | ✔ |  |
| Bank Name (BN) | text |  |  |
| Bank Prefix* | text | ✔ |  |
| Swift Code | text |  |  |
| IBN No | text |  |  |
| Call Center No | text |  |  |
| Web Url | text |  |  |
| Is Local | checkbox |  |  |
| Is Treasury | checkbox |  |  |
| Is Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Bank Name (EN) is required.

#### Fiscal Year Setup

`/m/accounting/fiscal-year`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search The fiscal years each company keeps, and whether each is closed | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Company Name, Fiscal Year Name, Fiscal Year Start Date, Fiscal Year End Date, Calendar Year Start Date, Calendar Year End Date, Is Closed?, Created By, Status, ACTION
- বাটন/অ্যাকশন: New fiscal year, Export, Company Name, Fiscal Year Name, Fiscal Year Start Date, Fiscal Year End Date, Calendar Year Start Date, Calendar Year End Date, Is Closed?, Created By, Status, Edit

**ট্যাব: New fiscal year**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Fiscal Year Name* | text | ✔ | 2027 |
| Fiscal Year Start* | date | ✔ |  |
| Fiscal Year End* | date | ✔ |  |
| Calendar Year Start* | date | ✔ |  |
| Calendar Year End* | date | ✔ |  |
| Is Closed? | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New fiscal year
- বাটন/অ্যাকশন: New fiscal year, Reset, Save fiscal year

**ডায়ালগ: New fiscal year**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Fiscal Year Name* | text | ✔ |  |
| Fiscal Year Start* | date | ✔ |  |
| Fiscal Year End* | date | ✔ |  |
| Calendar Year Start* | date | ✔ |  |
| Calendar Year End* | date | ✔ |  |
| Is Closed? | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Fiscal Year Start is required.

#### Account Type Configuration

`/m/accounting/coa-type-configuration`

**ট্যাব: Records 3**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Which type and layer each chart-of-accounts node is classified under | search |  | Search 3 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Account Type, Account Layer, Chart Of Accounts, ACTION
- বাটন/অ্যাকশন: New COA type configuration, Export, Account Type, Account Layer, Chart Of Accounts, Edit

**ট্যাব: New COA type configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Account Type* | select | ✔ | Select…, Cash Accounts, Bank Accounts, Receivable Accounts, Payable Accounts, Inventory Accounts, General Accounts |
| Account Layer* | select | ✔ | Select…, Main Class, Sub Class, Control Account, Ledger |
- সেকশন: New COA type configuration
- বাটন/অ্যাকশন: New COA type configuration, Select…, Reset, Save COA type configuration

**ডায়ালগ: New COA type configuration**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Account Type* | select | ✔ | Select…, Cash Accounts, Bank Accounts, Receivable Accounts, Payable Accounts, Inventory Accounts, General Accounts |
| Account Layer* | select | ✔ | Select…, Main Class, Sub Class, Control Account, Ledger |
- খালি Save-এ মেসেজ: Account Type is required.

#### Statutory Ledger Rules

`/m/accounting/compliance-ledger-rules`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New compliance rule

**ট্যাব: New compliance rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Compliance PCT* | number | ✔ |  |
- সেকশন: New compliance rule
- বাটন/অ্যাকশন: New compliance rule, Select…, Reset, Save compliance rule

**ডায়ালগ: New compliance rule**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Compliance PCT* | number | ✔ |  |
- খালি Save-এ মেসেজ: Compliance PCT is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- নতুন মডিউল `app/Modules/Accounting`, সব টেবিল `acc_` নামে। পুরনো প্রজেক্টের Finance মডিউল ব্যবহার করা হয়নি।
- **ব্যাংক রেজিস্টার শেয়ার করা:** Trade & Export-এর `tx_banks` আর `tx_bank_branches`।
  - লাইভ probe-এ দেখা গেছে, Brickland-এর Accounting আর Commercial দুই জায়গায় একই ২৬টা ব্যাংক।
  - এই মডিউল সেখানে শুধু `is_treasury` কলাম যোগ করেছে।
- **অন্য ERP মডিউল থেকে নেওয়া:** Merchandise-এর buyer আর origin country, Material Procurement-এর সাপ্লায়ার। এগুলো Voucher Ref-এর জন্য।
- **প্ল্যাটফর্মের অংশ, যা সবাই ব্যবহার করে:** Settings-এর মুদ্রা, Company Management-এর ফ্যাক্টরি (অফিস), কোম্পানির ইউজার।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_11_08_000100_create_accounting_masters`:

| টেবিল | ফিল্ড |
|---|---|
| `acc_payment_modes` | name, short_name, voucher_types (json: BPV/BRV/CPV/CRV/CV/BV/JV), remarks, active |
| `acc_voucher_grants` | voucher_type, user_id, can_make, can_check |
| `acc_accounts` | code, name, parent_id (নিজের টেবিলে), type (ASSET/LIABILITY/EQUITY/REVENUE/EXPENSE), postable, voucher_ref_type (BUYER/SUPPLIER/EMPLOYEE/BANK/BANK_ACCOUNT), voucher_ref_id, display_order, active |
| `acc_cost_center_groups` | name, display_order, remarks, active |
| `acc_cost_centers` | code (৩ অঙ্ক, নিজে তৈরি), name, factory_id (অফিস), group_id, dept_section, remarks, active |
| `acc_bank_accounts` | bank_id, branch_id, account_no, account_name, account_type (CURRENT/SAVINGS/STD/SND/FC/CC/OD), currency, ledger_id, active |
| `acc_cheque_books` | bank_account_id, book_no, required_leaf, prefix, no_from, no_to, requisition_date, est_receive_date, receive_date, remarks, finalized |
| `acc_cheque_leaves` | book_id, number, cheque_no, status (UNUSED/ISSUED/CANCELLED), active |
| `acc_fiscal_years` | name, fy_start, fy_end, cal_start, cal_end, closed, remarks, created_by |
| `acc_account_classes` | account_type (CASH/BANK/RECEIVABLE/PAYABLE/INVENTORY/GENERAL), layer (MAIN_CLASS/SUB_CLASS/CONTROL/LEDGER), account_id |
| `acc_compliance_rules` | account_id, pct |
| `tx_banks` (যোগ) | is_treasury |

## ৫. Workflow / Status ও Business Rules

- **শুরুর ডাটা (Brickland-এর মতো):**
  - ৯টা পেমেন্ট পদ্ধতি।
  - ১২৮ নোডের হিসাবের তালিকা: ৩৯টা গ্রুপ আর ৮৯টা লেজার হেড।
  - ৩টা অ্যাকাউন্টের ধরন কনফিগারেশন।
  - ২টা অর্থবছর (FY2026-27, FY2027-28)।
  - প্রথমবার খোলার সময় তৈরি হয়। একসাথে কয়েকটা রিকোয়েস্ট এলেও দুবার হয় না।
- **মোছা যায় না।** শুধু অ্যাকাউন্টের ধরন কনফিগারেশন আর কমপ্লায়েন্স নিয়ম মোছা যায় (Brickland-এর মতো)।
- **Payment Method Master:** নাম ইউনিক। ভাউচারের ধরন শুধু জানা সাতটা থেকে, আলাদা করে রাখা হয়।
- **Maker-Checker Setup:** Brickland-এ এটা scaffold; এখানে পুরো বানানো হয়েছে।
  - প্রতিটা ভাউচারের ধরনে কোন ইউজার তৈরি (Maker) আর কে অনুমোদন (Checker) করতে পারেন, দুটো আলাদা অনুমতি।
  - একজন ইউজার একবারই থাকেন। প্রতিটা লাইনে অন্তত একটা অনুমতি লাগে।
  - পুরো গ্রিড একবারে সেভ হয়।
- **Chart of Accounts Master:**
  - **কোড:** চাইল্ডের কোড = প্যারেন্টের কোড আর তার পরে দুই অঙ্ক, নিজে থেকে পরেরটা। সবচেয়ে ওপরের শ্রেণির কোড পরের একক অঙ্ক।
  - হাতে কোড দিলে সেটাও এই নিয়ম মানতে হয়।
  - পোস্টিং নেওয়া নোডের নিচে অ্যাকাউন্ট রাখা যায় না।
  - যে নোডের নিচে অ্যাকাউন্ট আছে তাতে পোস্টিং চালু করা যায় না, আর সেটা অন্য প্যারেন্টে সরানোও যায় না।
  - চাইল্ড প্যারেন্টের ধরনই পায়।
  - **Voucher Ref:** আগে রেজিস্টার বাছাই করতে হয়, তারপর সেই রেজিস্টারের একটা মান। রেজিস্টার হতে পারে বায়ার, সাপ্লায়ার, কর্মী, ব্যাংক বা ব্যাংক অ্যাকাউন্ট।
- **Cost Centre:** গ্রুপের নাম ইউনিক। কস্ট সেন্টারের কোড ০০১, ০০২ … নিজে থেকে।
- **Bank Account Master:**
  - Finance-এ ব্যাংক কোড প্রিফিক্স থেকে নিজে তৈরি হয়, আর SWIFT পরে দিলেও চলে।
  - শাখা পুরো গ্রিড একবারে সেভ হয়। যে শাখায় কোম্পানির অ্যাকাউন্ট আছে সেটা মোছা যায় না।
  - কোম্পানির অ্যাকাউন্টের নম্বর ইউনিক। শাখা অবশ্যই বাছাই করা ব্যাংকের হতে হবে।
- **Cheque Book Register:**
  - প্রিফিক্স আর নম্বরের পরিসর থেকে পাতা নিজে তৈরি হয়। Required Leaf অবশ্যই পরিসরের পাতার সংখ্যা হতে হবে।
  - একটা অ্যাকাউন্টে বইয়ের নম্বর আর চেক নম্বরের পরিসর একটার ওপর আরেকটা পড়ে না।
  - প্রাপ্তির তারিখ চাহিদার তারিখের আগে হতে পারে না।
  - ফাইনাল করা বই আর সম্পাদনা করা যায় না।
  - পাতা মোছা হয় না, শুধু Active? বদলায়।
- **Fiscal Year Setup:**
  - শেষ তারিখ শুরুর পরে হতে হয়। বছরগুলো একটার ওপর আরেকটা পড়ে না। নাম ইউনিক।
  - কে তৈরি করেছেন তা রাখা হয়।
- **Account Type Configuration:** প্রতিটা অ্যাকাউন্টের প্রতিটা স্তরে একটাই শ্রেণিবিভাগ।
- **Statutory Ledger Rules:**
  - শুধু পোস্টিং নেওয়া হেডে নিয়ম দেওয়া যায়।
  - একই হেড আবার সেভ করলে আগের নিয়মটা বদলে যায় (Brickland-এর মতো)।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{acc-payment-modes|acc-accounts|acc-cost-center-groups|acc-cost-centers|acc-bank-accounts|acc-cheque-books|acc-fiscal-years|acc-account-classes|acc-compliance-rules|acc-banks}`। `acc-banks` হলো `tx_banks`-এর ওপর Finance-এর নিয়ম।
- **`/api/v1/accounting/...`:**
  - `lookups`
  - `tree`, `tree/{account}`
  - `voucher-types`, `voucher-types/{type}` (GET/PUT)
  - `banks`, `banks/{bank}` (শাখা GET/PUT)
  - `cheque-books`, `cheque-books/{book}` (পাতা GET/PUT)
- **পারমিশন:** নতুন `accounting.master.view` আর `accounting.master.manage`।
  - কোম্পানি-স্তরের: Owner আর Company Admin সব পারমিশন পায়।
  - AUDITOR পায় শুধু view।
  - ফ্যাক্টরির ভূমিকাগুলো পায় না।
  - `RoleMatrixTest`-এর `DELIBERATELY_ADMINISTRATIVE` তালিকায় `accounting.master.manage` যোগ হয়েছে, কারণসহ (`finance.gl.manage`-এর মতো)। Accountant ভূমিকা এখনো নেই।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/finance-ledger/<screen>`। সাইডবারে FINANCE & ACCOUNTING → Finance & Ledger → MASTER DATA।
- প্রতিটা স্ক্রিন Brickland-এর ট্যাবে: Records আর New। এর বাইরে:
  - **Chart Of Accounts:** গাছের মতো তালিকা। বামে ৫৪টা নোড, ড্যাশ দিয়ে ইনডেন্ট করা; ডানে বাছাই করা নোডের অ্যাকাউন্ট।
  - **Cheque Leaf Info:** বামে বই, ডানে পাতা আর Active?।
  - **Bank Account Master → Branches:** বামে ব্যাংক, ডানে শাখার editable গ্রিড।
- **Maker-Checker Setup:** বামে ৭টা ভাউচারের ধরন, ডানে ইউজার, Maker? আর Checker?।
- **ফর্ম:**
  - Voucher For-এর অপশন Voucher Ref-এর ওপর নির্ভর করে।
  - চেক বইয়ের ফর্মে অ্যাকাউন্ট বাছাই করলে Account Type, Bank আর Branch নিজে থেকে দেখায়।
  - কোম্পানির ঘর শুধু দেখার জন্য।
- **ফিল্টার:** Payment Method-এ নাম আর ভাউচারের ধরন। Cheque Book-এ ব্যাংক, শাখা, অ্যাকাউন্ট নং, চেক নং (যে বইয়ের পরিসরে পড়ে), ফাইনাল আর তারিখ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Accounting/Database/Migrations/2026_11_08_000100_create_accounting_masters.php`
- `app/Modules/Accounting/Models/AccPaymentMode.php`
- `app/Modules/Accounting/Models/AccVoucherGrant.php`
- `app/Modules/Accounting/Models/AccAccount.php`
- `app/Modules/Accounting/Models/AccCostCenterGroup.php`
- `app/Modules/Accounting/Models/AccCostCenter.php`
- `app/Modules/Accounting/Models/AccBankAccount.php`
- `app/Modules/Accounting/Models/AccChequeBook.php`
- `app/Modules/Accounting/Models/AccChequeLeaf.php`
- `app/Modules/Accounting/Models/AccFiscalYear.php`
- `app/Modules/Accounting/Models/AccAccountClass.php`
- `app/Modules/Accounting/Models/AccComplianceRule.php`
- `app/Modules/Accounting/Support/AccountingDefaults.php`
- `app/Modules/Accounting/Resources/AccountingResource.php`
- `app/Modules/Accounting/Resources/PaymentModeResource.php`
- `app/Modules/Accounting/Resources/AccountResource.php`
- `app/Modules/Accounting/Resources/CostCenterGroupResource.php`
- `app/Modules/Accounting/Resources/CostCenterResource.php`
- `app/Modules/Accounting/Resources/BankAccountResource.php`
- `app/Modules/Accounting/Resources/ChequeBookResource.php`
- `app/Modules/Accounting/Resources/FiscalYearResource.php`
- `app/Modules/Accounting/Resources/AccountClassResource.php`
- `app/Modules/Accounting/Resources/ComplianceRuleResource.php`
- `app/Modules/Accounting/Resources/AccBankResource.php`
- `app/Modules/Accounting/Http/Controllers/Api/AccountingApiController.php`
- `app/Modules/Accounting/Routes/api.php`
- `app/Modules/TradeExport/Models/TxBank.php`
- `app/Modules/TradeExport/Support/TradeExportDefaults.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/finance-ledger/[screen]/page.js`
- `frontend/src/app/(app)/finance-ledger/[screen]/screens.js`
- `frontend/src/app/(app)/finance-ledger/[screen]/actions.js`
- `frontend/src/components/workstudy/side-grid.jsx`
- `lang/en/accounting.php`
- `lang/bn/accounting.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Accounting/AccountingMasterTest.php`
- `tests/Feature/TradeExport/TradeExportMasterTest.php`
- `tests/Feature/Identity/RoleMatrixTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- `AccountingMasterTest` ৪/৪ (১২৯টা assertion)। `TradeExportMasterTest` ৫/৫, ব্যাংকের সংখ্যা এখন ২৬।
  - **পেমেন্ট পদ্ধতি ও মেকার-চেকার:** ৯টা পদ্ধতি আছে। নাম ছাড়া মেসেজ আসে। অজানা ভাউচারের ধরন চলে না। ধরনের কোড বড় হাতের হয়। একই নাম চলে না। অনুমতি ছাড়া লাইন বা একই ইউজার দুবার চলে না। গ্রিড সেভ হয়। অজানা ধরনে 404।
  - **হিসাবের তালিকা:**
    - ১২৮টা নোড, প্যারেন্ট আর চাইল্ডের সংখ্যাসহ। গাছে ৫৪টা নোড।
    - নতুন অ্যাকাউন্টের কোড 1010106। প্যারেন্টের ধরন না মানলে, পোস্টিং নেওয়া প্যারেন্ট দিলে বা ভুল কোড দিলে চলে না। গ্রুপকে পোস্টিং করা যায় না।
    - সবচেয়ে ওপরের নতুন শ্রেণির কোড 6। রেজিস্টার ছাড়া রেফ চলে না। মোছা যায় না।
    - শ্রেণিবিভাগ একই স্তরে দুবার চলে না। কমপ্লায়েন্স নিয়ম গ্রুপে চলে না, আর একই হেড আবার দিলে আগেরটা বদলে যায়।
  - **কস্ট সেন্টার, ব্যাংক, অ্যাকাউন্ট, চেক বই:**
    - কস্ট সেন্টারের কোড ০০১, ০০২। ব্যাংক ২৬টা।
    - Finance-এ নতুন ব্যাংকের কোড প্রিফিক্স থেকে হয় (MBL), আর treasury চিহ্ন থাকে; Trade & Export-এর তালিকাতেও সেটা আসে (২৭টা)।
    - শাখার কোড দুবার চলে না। অ্যাকাউন্টে অন্য ব্যাংকের শাখা চলে না; মুদ্রা বড় হাতের হয়। অ্যাকাউন্ট থাকা শাখা মোছা যায় না।
    - চেক বইয়ে "চেক নং থেকে" ছাড়া মেসেজ আসে। পাতার সংখ্যা না মিললে চলে না। ১০টা পাতা তৈরি হয় (CHQ1000001 …)। পরিসর মিলে গেলে চলে না।
    - পাতা কম পাঠালে চলে না। Active? বদলায়। ফাইনাল করা বই সম্পাদনা করা যায় না।
  - **অর্থবছর ও পারমিশন:** ২টা বছর আছে। শুরু ছাড়া মেসেজ আসে। মিলে যাওয়া বছর বা উল্টো তারিখ চলে না। কে তৈরি করেছেন তা থাকে। অডিটর দেখতে পারে, বদলাতে পারে না। ফ্যাক্টরি ম্যানেজার দেখতেও পারে না।
- **ব্রাউজার (dev):** দশটা স্ক্রিনই খোলা হয়েছে। কনসোলে কোনো error নেই। যা সেভ বা যাচাই হয়েছে:
  - Bank Payment Voucher-এর মেকার।
  - গাছে 10101 বাছাই করলে ৫টা অ্যাকাউন্ট আসে।
  - নতুন অ্যাকাউন্ট "Vehicles", Voucher Ref = Bank।
  - ABN-এ Motijheel শাখা।
  - কোম্পানির অ্যাকাউন্ট 0111-7654321।
  - চেক বই CB-01, ১০টা পাতাসহ।

## ১০. খোলা প্রশ্ন

- **কোম্পানির ব্যাংক অ্যাকাউন্ট কোথায় থাকবে:** Brickland-এর Bank Account Master-এর বিবরণে "company accounts" আছে, কিন্তু ডেমোতে অ্যাকাউন্ট রাখার কোনো ট্যাব দেখা যায়নি। চেক বই রাখার জন্য অ্যাকাউন্ট লাগে, তাই এখানে একটা বাড়তি "Company Accounts" ট্যাব যোগ করা হয়েছে।
- **মেকার-চেকার প্রয়োগ:** Voucher Entry (23/02) বানানোর সময় ঠিক হবে মেকার নিজের ভাউচার অনুমোদন দিতে পারবেন কিনা। এখানে শুধু অনুমতিগুলো রাখা আছে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১১):**
- লাইভ probe দিয়ে আগে থেকে দেখে নেওয়া হয়েছে:
  - প্রতিটা ট্যাব।
  - হিসাবের তালিকার ১২৮টা নোড। ১০০-র পরের পাতা পেতে কোড উল্টো ক্রমে সাজিয়ে পড়া হয়েছে।
  - ৯টা পেমেন্ট পদ্ধতি, ২৬টা ব্যাংক, ২টা অর্থবছর আর ৩টা শ্রেণিবিভাগ।
  - প্রতিটা ফর্মের হিন্ট।
- **Brickland-এর সাথে তফাত:**
  - **Maker-Checker:** Brickland-এ scaffold, এখানে পুরো বানানো।
  - **বাড়তি "Company Accounts" ট্যাব:** ১০ নম্বর খোলা প্রশ্ন দেখুন।
  - **পেমেন্ট পদ্ধতির ভাউচারের ধরন:** Brickland-এ কমা দিয়ে লেখা কোড, এখানে টিক দেওয়ার বাক্স।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - শ্রেণিবিভাগের ক্রম এখন যোগ করার ক্রমে, Brickland-এর মতো।
  - গাছের নোডের ইনডেন্ট ড্যাশ ডানের শিরোনামে আসছিল; সরানো হয়েছে।

**Brickland parity ক্রস-চেক (২০২৬-১০-১২):** Brickland-এ Create/Edit/Remove চালিয়ে, ডায়ালগ, Edit ট্যাব আর রো-বাটনের ভেতরের অবস্থাসহ মিলিয়ে দেখা হয়েছে।
- Payment Method Master-এ Voucher Type এখন Brickland-এর মতো একটা টেক্সট বক্স: কমা দিয়ে কোড ("BPV, BRV, CV")। সার্ভার প্রতিটা কোড যাচাই করে, অজানা কোড হলে 422।
- Cost Center, Fiscal Year আর Cheque Book-এ Company এখন select, আগে টেক্সট ছিল। Cheque Book-এর Records ফিল্টারে Company, "Account No." আর Account Type যোগ হয়েছে; New ফর্মে "No of Leaf" (Cheque No From/To থেকে হিসাব করা, শুধু দেখার জন্য)।
- বাকি পার্থক্য: Chart of Accounts-এর ট্রি-এডিটরে Brickland-এর Add / Revert / Save বাটন নেই; Manage Bank-এর Branches-এ "New Branch" বাটন নেই।
