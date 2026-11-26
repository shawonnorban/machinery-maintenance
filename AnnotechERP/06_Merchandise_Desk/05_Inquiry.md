# Inquiry

> **স্ট্যাটাস:** ✅ সম্পন্ন (দুটো স্ক্রিন; Item/Combo/Model ডেস্কের ৮টা ট্যাবই চালু — Registration, Colour Mapping, Fabrication, Services, BOM, Order Confirmation, Yarn/Fabric Mapping, Packing Instruction — আর Style Gallery)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/05`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Price Quotation Entry | `/m/merchandising/price-quotation` | 4 | 15 | 1 |
| 2 | Style Development Sheet | `/m/merchandising/style-development` | 3 | 20 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Price Quotation Entry

`/m/merchandising/price-quotation`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Quotation, Buyer Rates, New inquiry

**ট্যাব: Quotation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | search |  | Search… |
- সেকশন: Buyers, Inquiries
- বাটন/অ্যাকশন: Quotation, Buyer Rates, New inquiry

**ট্যাব: Buyer Rates**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search inquirys | search |  | Search… |
- সেকশন: Inquiry, Buyer Rates
- বাটন/অ্যাকশন: Quotation, Buyer Rates, New inquiry

**ট্যাব: New inquiry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| IncoTerm | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| LC Term | select |  | Select…, LC at sight, LC 30 days, LC 60 days, LC 90 days, TT in advance, TT on delivery |
| Cons. Unit | select |  | Select…, Kg, Lbs, Yds, Mtr |
| Calc. Unit | select |  | Select…, Dz, Pcs, Set |
| Currency | select |  | Select…, USD, EUR, GBP, CAD, BDT |
| Exch. Rate | number |  |  |
| Style No #* | text | ✔ | 123 |
| Style Ext. # | text |  | ERRRRA |
| Style Desc.* | text | ✔ | Mens T Shirt |
| Inquiry DT* | date | ✔ |  |
| Quoted FOB | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New inquiry
- বাটন/অ্যাকশন: Quotation, Buyer Rates, New inquiry, Reset, Save inquiry

**ডায়ালগ: New inquiry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| IncoTerm | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| LC Term | select |  | Select…, LC at sight, LC 30 days, LC 60 days, LC 90 days, TT in advance, TT on delivery |
| Cons. Unit | select |  | Select…, Kg, Lbs, Yds, Mtr |
| Calc. Unit | select |  | Select…, Dz, Pcs, Set |
| Currency | select |  | Select…, USD, EUR, GBP, CAD, BDT |
| Exch. Rate | number |  |  |
| Style No #* | text | ✔ |  |
| Style Ext. # | text |  |  |
| Style Desc.* | text | ✔ |  |
| Inquiry DT* | date | ✔ |  |
| Quoted FOB | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Inquiry DT is required.

#### Style Development Sheet

`/m/merchandising/style-development`

**ট্যাব: Records 17**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Buyer | select |  | All, LPP |
| Main Style Ref # | search |  |  |
| Season | select |  | All, Autumn/Winter 2026, Spring/Summer 2026, Spring/Summer 2027 |
| Status | select |  | All, Draft, Development, In Registration, Style Approved, Active, Inactive, On Hold, Cancelled |
| Search Styles with their buyer, account, description, season, origin and status | search |  | Search 17 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Main Style Ref #, Style Desc., Buyer, Buyer A/C, Inquiry #, Season, Qty Unit, Origin, Orders, Status, ACTION
- বাটন/অ্যাকশন: Size Range, New development style, Clear, Export, Main Style Ref #, Style Desc., Buyer, Buyer A/C, Inquiry #, Season, Qty Unit, Origin, Orders, Status, Sheet, Edit

**ট্যাব: Size Range**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search styles | search |  | Search… |
- সেকশন: Styles, Size Ranges
- বাটন/অ্যাকশন: Size Range, New development style, 529 T-SHIRT 1, 1111 gwewrtert 1, 121 1321 1, 529 539 1, 234234-32443 234234 1, 867MI 867MI 1, 531 Ladies Leggings 1, 927 Ladies Trousers 1, 867MI-05X 867MI-05X 1, Summer Set TOP + BUTTOM SET 1, bclltd mmm 1, bcl1 bcl 1, 123 T SHIRT 1, GRACE TEE SHIRT 1, mkmkm jjhb 1

**ট্যাব: New development style**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Ext# | text |  | Ext# |
| Set? | checkbox |  |  |
| Model? | checkbox |  |  |
| Combo? | checkbox |  |  |
| Col.Pair? | checkbox |  |  |
| PcsPerUnit | number |  | PcsPerUnit |
| Ref# | text |  | Ref# |
| — | number |  |  |
| Search... | text |  | Search... |
| Style | text |  | Style |
- সেকশন: Style Registration, Style Information
- বাটন/অ্যাকশন: Size Range, New development style, Records, Save, Cancel

**ডায়ালগ: New development style**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| — | select |  |  |
| — | text |  |  |
| Ext# | text |  |  |
| Set? | checkbox |  |  |
| Model? | checkbox |  |  |
| Combo? | checkbox |  |  |
| Col.Pair? | checkbox |  |  |
| PcsPerUnit | number |  |  |
| Ref# | text |  |  |
| — | number |  |  |
| Search... | text |  |  |
| Style | text |  |  |
- খালি Save-এ মেসেজ: Required · Every size range line needs at least one size from Size Master.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Merchandise-এর মাস্টার: বায়ার, বায়ার অফিস ও তাদের লিংক (`buyer_office_links`), Inquiry Rate Chart (`rate_lines` আর অফিসভিত্তিক `rate_line_rates`), Buyer Supplier Config, Size Master, Country of Origin।
- Material Procurement-এর সাপ্লায়ার (`mp_suppliers`)।
- স্ক্রিন-কিট: `masterScreen` (Records, New, Edit ট্যাব আর Remove), `RecordsGrid`, `EntryForm`, `FilterBar` (instant)।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_inquiries`**: `inquiry_no` (INQ-বছর+৪ অঙ্ক, কোম্পানিতে ইউনিক), `buyer_id`, `inco_term`, `lc_term`, `cons_unit`, `calc_unit`, `currency`, `exchange_rate`, `style_no`, `style_ext`, `style_desc`, `inquiry_date`, `quoted_fob`, `remarks`, `revision`, `status` (DRAFT, QUOTATION_SENT, REVISED, CONFIRMED, CANCELLED), `created_by`।
- **`merch_styles`**: `buyer_id`, `buyer_office_id` (Buyer A/C, বায়ারের সাথে লিংক করা অফিস), `inquiry_id` (বায়ারের কনফার্মড ইনকোয়ারি), `style_ref` + `style_ext` (একসাথে কোম্পানিতে ইউনিক; "MOOD SHIRT-327632.01"), `description`, `is_set`, `is_model`, `is_combo`, `is_colour_pair`, `prev_style_id`, `qty_unit` (PCS, SET, DZN), `pcs_per_unit`, `season`, `ref_no`, `nature_of_work`, `supplier_id`, `origin_country_id`, `status` (DRAFT, DEVELOPMENT, STYLE_APPROVED, RUNNING, CLOSED, ON_HOLD, CANCELLED), `created_by`।
- **`merch_style_size_ranges`**: `style_id`, `sl_no` (স্টাইলে ইউনিক), `size_ids` (Size Master-এর সাইজের তালিকা), `sub_style` (ফাঁকা হলে স্টাইলের মূল লাইন)।
- Season-এর কোনো মাস্টার Brickland-এও নেই। তাই তালিকাটা তৈরি করা হয়: গত বছর থেকে আগামী দুই বছর পর্যন্ত Spring/Summer আর Autumn/Winter।

## ৫. Workflow / Status ও Business Rules

**Inquiry:**
- নতুন ইনকোয়ারি DRAFT হিসেবে শুরু হয়; ডিফল্ট FOB, Kg, Dz, USD আর বিনিময় হার 0।
- নম্বর দেওয়ার সময় কোম্পানিভিত্তিক advisory lock থাকে, যাতে দুজন একই নম্বর না পায়।
- Send Quotation → QUOTATION_SENT। চলে Draft, Revised আর Sent থেকে।
- Revise (আগে জিজ্ঞেস করে) → রিভিশন নম্বর এক বাড়ে, স্ট্যাটাস REVISED।
- Confirm → CONFIRMED। এটাই শেষ ধাপ: এরপর Revise, Cancel, Send বা Edit কিছুই চলে না, আর সার্ভার কারণসহ জানায়।
- Cancel (আগে জিজ্ঞেস করে) → CANCELLED। ইতিহাস হিসেবে তালিকায় থেকে যায়, এরপর আর কোনো move চলে না।
- যে ইনকোয়ারির বিপরীতে স্টাইল রেজিস্টার করা আছে, সেটা মোছা যায় না (in use)।
- Buyer Rates দেখায় বায়ারের প্রতিটা অফিসের জন্য রেট চার্টের প্রতিটা সক্রিয় লাইন: ইনকোয়ারির তারিখে বা তার আগে কার্যকর সবচেয়ে নতুন রেট, সাথে বায়ারের ডিফল্ট (ক্যাটাগরি ছাড়া) মনোনীত ও বিকল্প সাপ্লায়ার।

**Style:**
- বায়ার, Main Style Ref # আর Style Desc. বাধ্যতামূলক। অন্তত একটা সাইজ রেঞ্জ লাইন লাগে, আর প্রতিটা লাইনে Size Master থেকে অন্তত একটা সাইজ।
- Buyer A/C হতে হবে বায়ারের সাথে লিংক করা অফিস। Inquiry # হতে হবে একই বায়ারের কনফার্মড ইনকোয়ারি।
- নতুন বা Edit ফর্মে স্ট্যাটাস Draft, Development, Style Approved, On Hold আর Cancelled। Sheet-এ এর সাথে Running আর Closed, আর Nature Of Work ঘর।
- Pcs হলে প্রতি ইউনিটে পিস ১; Set বা Dzn হলে প্রতি ইউনিটে কয়টা পিস।
- Size Range ট্যাবে একটা লাইন আলাদাভাবে যোগ বা মোছা যায়।

## ৬. API ও পারমিশন

- পারমিশন: `merchandise.inquiry.view/manage`, `merchandise.style.view/manage`। FACTORY_ADMIN পায় সবগুলো, FACTORY_MANAGER পায় দেখার দুটো।
- `/erp/merch-inquiries`: স্ক্রিন-কিট resource (তালিকা, তৈরি, Edit, মোছা)।
- `GET /merchandise/inquiries/buyers` আর `/buyers/{buyer}`: Quotation প্যানেল।
- `POST /merchandise/inquiries/{id}/{send|revise|confirm|cancel}`: workflow।
- `GET /merchandise/inquiries/{id}/rates`: Buyer Rates।
- `GET/POST /merchandise/styles`, `GET/PUT/DELETE /merchandise/styles/{id}`, `GET /merchandise/styles/lookups`।
- `GET/POST /merchandise/styles/{id}/size-ranges`, `DELETE /merchandise/style-size-ranges/{id}`।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবারে Merchandise Desk → INQUIRY ফোল্ডার, তার নিচে দুটো স্ক্রিন।
- **Price Quotation Entry:**
  - Records (Brickland-এর কলাম, Edit ট্যাব "Edit inquiry", "Remove this inquiry?")।
  - Quotation: বাঁয়ে সার্চসহ বায়ারের তালিকা, ডানে ইনকোয়ারির টেবিল আর Revise / Send Quotation / Confirm / Cancel। Revise আর Cancel আগে জিজ্ঞেস করে।
  - Buyer Rates: বাঁয়ে ইনকোয়ারি, ডানে রেট।
  - New inquiry (৩ কলামের ফর্ম, সাহায্য-লেখাসহ)।
- **Style Development Sheet:**
  - Records: তাৎক্ষণিক ফিল্টার (Buyer A/C, Buyer, Main Style Ref #, Season, Status), রো-তে Sheet, Edit আর Remove।
  - Size Range: বাঁয়ে স্টাইল, ডানে লাইন, আর Add size range ডায়ালগ।
  - New development style, Edit development style আর development style sheet: Brickland-এর মতো পাশে লেবেল দেওয়া "Style Information" বক্স, সাইজ বাছাইয়ের চিপ-পিকার, আর "+" দিয়ে লাইন যোগ।
- নতুন কিট-কম্পোনেন্ট: `PickPanel` (দুই প্যানেলের বাছাই)। এছাড়া `RecordsGrid`-এ `rowLink` যোগ হয়েছে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (হাতে লেখা):

| ধরন | ফাইল |
|---|---|
| Migration | `app/Modules/Merchandise/Database/Migrations/2026_11_12_000100_create_merchandise_inquiries_and_styles.php` |
| Model | `app/Modules/Merchandise/Models/Inquiry.php`, `Style.php`, `StyleSizeRange.php` |
| Resource | `app/Modules/Merchandise/Resources/InquiryResource.php` |
| Controller | `app/Modules/Merchandise/Http/Controllers/Api/InquiryApiController.php`, `StyleApiController.php` |
| Route | `app/Modules/Merchandise/Routes/api.php` |
| পারমিশন / মেনু | `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php`, `ResourceRegistry.php` |
| Frontend | `frontend/src/app/(app)/merchandise-desk/[screen]/inquiry.js`, `page.js`, `actions.js` |
| Component | `frontend/src/components/erp/pick-panel.jsx`, `components/merchandise/inquiry-quotation.jsx`, `style-form.jsx`, `style-size-ranges.jsx`; `components/erp/records-grid.jsx` (`rowLink`) |
| Lang | `lang/en/merchandise.php`, `lang/bn/merchandise.php` (`master.inq`, `master.sty`, `inquiry`, `style`) |
| Item desk | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000100_create_merchandise_style_items.php`, `Models/StyleItem.php`, `Models/StyleItemStyle.php`, `Support/StyleItemOptions.php`, `Http/Controllers/Api/StyleItemApiController.php`; `frontend/src/components/merchandise/style-item-desk.jsx`, `style-gallery.jsx`; `frontend/src/app/api/merchandise/style-item-images/route.js` |
| Colour Mapping | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000200_create_merchandise_style_colours.php`, `Models/StyleColour.php`, `Http/Controllers/Api/StyleColourApiController.php`; `frontend/src/components/merchandise/style-desk.jsx`, `style-colour-grid.jsx` |
| Fabrication | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000300_create_merchandise_style_fabrication.php`, `Models/StyleFabric.php`, `Models/StyleKnitInstruction.php`, `Http/Controllers/Api/StyleFabricApiController.php`; `frontend/src/components/merchandise/style-fabrication.jsx` |
| Services | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000400_create_merchandise_style_services.php`, `Models/StyleProcess.php`, `Http/Controllers/Api/StyleServiceApiController.php`; `frontend/src/components/merchandise/style-services.jsx` |
| BOM | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000500_create_merchandise_style_boms.php`, `Models/StyleBom.php`, `Models/StyleBomVersion.php`, `Models/StyleBomLine.php`, `Http/Controllers/Api/StyleBomApiController.php`; `frontend/src/components/merchandise/style-bom.jsx` |
| Yarn/Fabric Mapping | `app/Modules/Merchandise/Database/Migrations/2026_11_13_000600_create_merchandise_style_fabric_maps.php`, `Models/StyleFabricMap.php`, `Http/Controllers/Api/StyleFabricMapApiController.php` (Fabrication-এর save এখন আইডি ধরে আপডেট করে: `StyleFabricApiController.php`); `frontend/src/components/merchandise/style-fabric-mapping.jsx` |
| Packing Instruction | `app/Modules/Merchandise/Database/Migrations/2026_11_15_000100_create_merchandise_style_packing.php`, `Models/StylePacking.php`, `Http/Controllers/Api/StylePackingApiController.php`; `frontend/src/components/merchandise/style-packing.jsx` |
| Test | `tests/Feature/Merchandise/MerchandiseInquiryTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- B1–B6 শেষ। Order Confirmation ট্যাব এসেছে Style & Order C2-এর সাথে (দেখুন [07_Style_Order.md](07_Style_Order.md))। Packing Instruction (B7) দিয়ে ডেস্কের সব ট্যাব শেষ হয়েছে।
- Order Confirmation আর Packing Instruction অর্ডারের উপর নির্ভর করে, তাই এগুলো Style & Order (06/07)-এর সাথে হবে।

## ৯. টেস্ট ও যাচাই

- `MerchandiseInquiryTest` (৪টা টেস্ট) যাচাই করে:
  - নম্বর দেওয়া, ডিফল্ট আর Brickland-এর মেসেজ।
  - একমুখী workflow আর কনফার্মের পর সব নিষেধ।
  - Buyer Rates-এ ইনকোয়ারির তারিখের রেট (পরের রেট বাদ)।
  - স্টাইলের সাইজ লাইন, কনফার্মড ইনকোয়ারির শর্ত, ইউনিক রেফ, Size Range যোগ ও মোছা, Running স্ট্যাটাস, ফিল্টার।
  - স্টাইলে ব্যবহৃত ইনকোয়ারি মোছা নিষেধ।
  - টেকনিশিয়ানের অনুমতি নেই।
- ব্রাউজারে পুরো flow চালানো হয়েছে: নতুন ইনকোয়ারি → Revise ডায়ালগ → Confirm → নতুন স্টাইল (সাইজসহ) → Sheet → Add size range।

## ১০. খোলা প্রশ্ন

- Brickland-এ Buyer A/C ড্রপডাউন সবসময় খালি দেখা গেছে। আমরা এটাকে বায়ারের লিংক করা অফিস হিসেবে নিয়েছি; ভুল হলে জানাবেন।

## ১১. As-built নোট

**সম্পন্ন অংশ (২০২৬-১০-১২):** Price Quotation Entry পুরোটা, আর Style Development Sheet-এর Records, Size Range, New, Edit, Sheet ও Remove।

**Brickland থেকে যেখানে আলাদা:**
- Brickland-এ Confirmed ইনকোয়ারিতেও Send Quotation চাপলে স্ট্যাটাস আবার "Quotation Sent" হয়ে যায় (বাগ)। আমাদেরটায় কনফার্মের পর Send আটকানো।
- Brickland-এর Cancel ডায়ালগের দুটো বাটনই "Cancel" লেখা। আমাদেরটায় "Close" আর "Cancel inquiry"।
- Brickland Inquiry # ড্রপডাউনে সব বায়ারের কনফার্মড ইনকোয়ারি দেখায়। আমাদেরটায় শুধু বাছাই করা বায়ারের।
- Brickland-এর Style Gallery ছবি রাখে শুধু ব্রাউজারে। আমাদেরটা সার্ভারে রাখা হবে, পরের ধাপে।
- Item/Combo/Model(Fresh), Item/Combo/Model(Copy) আর Style Gallery বাটন এখনো দেখানো হয় না; তৈরি হলে আসবে।
- Records-এর Orders কলাম এখন ০। Style & Order (06/07) তৈরি হলে আসল সংখ্যা দেখাবে।

**Brickland-এ তৈরি করা টেস্ট ডাটা:** INQ-20260001 (AN-TEST-01, কনফার্মড) আর INQ-20260002 (AN-TEST-02, বাতিল)।

**B1 — Item/Combo/Model Registration আর Style Gallery (২০২৬-১০-১২):**
- Sheet-এ এখন তিনটা বাটন: Item/Combo/Model(Fresh), (Copy) আর Style Gallery। Fresh বা Copy চাপলে ফর্মের নিচে Brickland-এর মতো ডেস্ক খোলে (`&desk=fresh|copy`), Close চাপলে বন্ধ হয়।
- ডেস্কে আছে Add New, Item List, আর Previous / Next (যেমন 1 / 1)।
- Set স্টাইল হলে Set Item বাছাই করা যায়। For Style(s)# দিয়ে একটা আইটেম একাধিক স্টাইলের জন্য রাখা যায়।
- Item Type নির্ভর করে Item Group-এর উপর; Brickland-এর পুরো তালিকা নেওয়া হয়েছে, আর "Other" গ্রুপে কোনো টাইপ নেই।
- নাম বড় হাতে রাখা হয়।
- সার্ভিস টাইপ (Solid, Y/D) আর সার্ভিস (AOP, Gmt Wash, Heat Seal, Print, Embroidery) টিক দিয়ে দেওয়া যায়। এছাড়া Nature of Work, আর IE-এর ঘর: No. Of M/C, Exp. Prod./Hr, SMV.Marketing।
- ছবি: Key Image, 2nd Image, Item Image, আর চারটা সার্টিফিকেটের ছবি (প্রতিটার পাশে টিক)। এগুলো প্ল্যাটফর্মের ফাইল স্টোরে যায় (`StoreFileAttachment`), আপলোড হয় `/api` route handler দিয়ে।
- Technical Specification, আর কেয়ার লেবেল লাইন: গার্মেন্ট পার্ট ও সার্ভিস, "+" দিয়ে যোগ।
- "Same as" দিয়ে অন্য আইটেম থেকে তথ্য কপি করা যায়। Copy ডেস্কে সব স্টাইলের আইটেম আসে, Fresh ডেস্কে শুধু এই স্টাইলের।
- Style Gallery দেখায় স্টাইলের সব আইটেমের ছবি। এগুলো সার্ভারে রাখা, তাই সবাই একই গ্যালারি দেখে (Brickland রাখে শুধু ব্রাউজারে)।
- প্যানেলে আপাতত শুধু Registration ট্যাব দেখানো হয়; বাকি ট্যাব তৈরি হলে যোগ হবে।

**B2 — Colour Mapping (২০২৬-১০-১২):**
- ডেস্কের ট্যাবগুলো এখন আলাদা লিংক (`&panel=registration|colours`), তাই সার্ভার শুধু খোলা ট্যাবের ডাটা আনে।
- `merch_style_colours`-এ স্টাইল ধরে রঙের গ্রিড; একটা রং একটা স্টাইলে একবারই থাকে। কলামগুলো Brickland-এর মতো:
  - ছবি আর রং (Colour Master)।
  - ধরন: Solid, Melange, Y/D, AOP বা Print।
  - গার্মেন্ট রং কিনা, Colour Group (Dye House), Dye Method আর Composition।
  - বায়ারের রেফারেন্স: ColourRef #, Shade #, Swatch?, Panton # আর Buyer Colour Name; সাথে Remarks।
- রং বাছাই করলে Colour Master থেকে ধরন, Pantone, Colour Group আর Swatch নিজে থেকে বসে যায়।
- Add New দিয়ে লাইন যোগ হয়; Save করলে পুরো গ্রিড একসাথে সেভ হয়। পরে যে রঙের উপর অন্য কিছু (সার্ভিস, ফ্যাব্রিক ম্যাপিং) রেকর্ড হবে, সেটা সরানো যাবে না।
- Brickland-এর Labdip Program আর Sample Program বাটন এখনো নেই; ওই মডিউল তৈরি হলে আসবে।

**B3 — Fabrication (২০২৬-১০-১২):**
- **বাঁ দিক:**
  - Knitting/YD Instruction Entry: ছবির শিরোনাম, একাধিক ছবি, G/Color (গার্মেন্ট রং), আর রং / মান / একক (Kg থেকে Course আর %) লাইন। লাইনের নিচে নতুন লাইন যোগ বা সরানো যায়।
  - Fabrication List: প্রতিটা ফ্যাব্রিকে Edit আর Remove।
- **ডান দিক, ফ্যাব্রিকের ফর্ম:**
  - কোন আইটেম আর কোন গার্মেন্ট পার্টের জন্য। আইটেম শুধু এই স্টাইলের, পার্ট আসে Garment Part মাস্টার থেকে।
  - Fabric Code, Category, আর নিজস্ব উৎপাদন না কেনা।
  - Composition, সাথে New Composition বাটন (নতুন ট্যাবে Fiber Composition খোলে)।
  - Spin Type, Slub?, Mellange?, Main Fab?
  - Fabric Type (মাস্টার থেকে), ওজনের সীমা আর একক, Width, Supplier, Remarks।
  - Top / Binding / Back count আর শতাংশ। Suggest চাপলে GSM-wise count চার্ট থেকে ফ্যাব্রিক টাইপ আর ওজন মিলিয়ে count বসে।
  - Wash Type আর Finish Type (চিপ, সাথে N/A), Other Spec.
  - Fabric Description ঘরগুলো থেকে নিজে লেখা হয়, যেমন "Knit Fabric — 100% Cotton Single Jersey · Combed · 180 GSM · Manufactured"।
- Add চাপলে ফ্যাব্রিক তালিকায় যায়; Save করলে তালিকা আর নির্দেশনা একসাথে সেভ হয় (`merch_style_fabrics`, `merch_style_knit_instructions`)।
- **Brickland থেকে আলাদা:** Brickland Wash আর Finish-এ একই তালিকা দেয়, সেটাই রাখা হয়েছে। Brickland-এর "For Gmt Part(s)" তালিকায় আইটেমের নাম আসে, যা বাগ মনে হয়; আমাদেরটায় Garment Part মাস্টার আসে।

**B4 — Services (২০২৬-১০-১২):**
- **রং অনুযায়ী সার্ভিস:** ম্যাপ করা প্রতিটা রঙের একটা লাইন। Color আর Type শুধু দেখায়; AOP, Y/D, Print, Embroidery আর GMT Wash Type বাছাই করা যায়। তালিকাগুলো Brickland-এর মতো। এগুলো `merch_style_colours`-এর কলামে থাকে, তাই Colour Mapping আবার সেভ করলেও মুছে যায় না।
- **Process Details:** Add Process দিয়ে লাইন যোগ হয়। প্রতিটা লাইনে ছবি, Process (Y/D, Dyeing, Print, Embroidery, Wash, AOP, Heat Seal, Garment Dye), Type, Supplier (Supplier Master থেকে), Remarks আর সরানোর বাটন (`merch_style_processes`)।
- Save করলে দুটো অংশ একসাথে সেভ হয়; Cancel করলে শেষ সেভ করা অবস্থায় ফেরে। রং ম্যাপ না থাকলে টেবিলে লেখা থাকে "আগে একটা রং ম্যাপ করুন"।

**B5 — BOM (২০২৬-১০-১২):**
- **উপরের সারি:** BOM ID, Version, Draft/Approved ব্যাজ, একটা বার্তা আর Approve BOM বাটন।
  - প্রথম Save-এ স্টাইলের BOM নম্বর হয় (কোম্পানিতে BOM-000001 থেকে), V1 খসড়া।
  - খসড়া Save করলে সেটাই বদলায়।
  - অনুমোদিত ভার্সনের পরে Save করলে পরের ভার্সন খসড়া হিসেবে খোলে। বার্তা: "চালু আছে: V1। অনুমোদনের পর V2 চালু হবে।"
  - সবচেয়ে নতুন অনুমোদিত ভার্সনটাই চালু ভার্সন। ফাঁকা খসড়া অনুমোদন করা যায় না।
- **বাঁয়ে Material Category:** All Items, Main Fabric, Sewing Accessories, Trims, Labels, Packaging, Accessories, Others, প্রতিটার সাথে লাইনের সংখ্যা। ক্যাটাগরি ছাড়া লাইন Others-এ গোনা হয়।
- **টেবিল:** Item Name, Cons Type, Consumption, Cons.Unit, Per No of GMT, Purc.Unit, Conv. to Purch Unit, Addi% (টিক), Rate, Remarks, Category, Item Code, Allow %, Amount, Supplier। হেডারের + দিয়ে লাইন যোগ হয় (কোনো ক্যাটাগরি বাছাই করা থাকলে নতুন লাইন সেই ক্যাটাগরিতে যায়), ✕ দিয়ে সরে।
- **নিচে:** Save, Order Qty, Addl %, Print BOM, PDF, Excel, Cancel।
- **হিসাব:**
  - Amount = Consumption × Rate, Brickland-এর মতোই।
  - প্রিন্ট আর Excel-এ একটা বাড়তি কলাম আছে: অর্ডারের মোট প্রয়োজন, ক্রয় এককে। সূত্র: Order Qty × (Consumption ÷ Per GMT) × (১ + Allow% + টিক থাকলে Addl%) ÷ Conversion।
- **Brickland থেকে আলাদা:**
  - Brickland-এর সারির Edit বাটন চাপলে কিছু হয় না। সারি এমনিতেই সরাসরি এডিট করা যায়, তাই বাটনটা রাখা হয়নি।
  - Print/PDF ব্রাউজারের প্রিন্ট উইন্ডো খোলে (সেখান থেকে PDF সেভ করা যায়)। Excel একটা CSV ফাইল নামায়।
  - Brickland-এর মতো Item Name আর Supplier লেখার ঘর, মাস্টার থেকে বাছাই নয়। ট্রিমস আইটেম মাস্টার তৈরি হলে বাছাই যোগ হবে।

**B6 — Yarn/Fabric Mapping (২০২৬-১০-১২):**
- **বাঁয়ে Garment Part vs Fabric তালিকা:** Part Name, Ratio, Fabric, Colour, Gmt Color, Item, Action (Edit, Remove)। নিচে Save আর Cancel; Save করলে পুরো তালিকা একসাথে সেভ হয় (`merch_style_fabric_maps`)।
- **ডানে Fabric Mapping Form:**
  - New Entry; Prod Category (Bulk, Develop, Short-Bulk, Projection — চিপ, শুরুতে চারটাই)।
  - Gmt Item: এই স্টাইলের আইটেম।
  - Garment Part: Garment Part মাস্টার থেকে।
  - Fabric: Fabrication-এর ফ্যাব্রিক। আইটেম বাছাই করা থাকলে শুধু সেই আইটেমের ফ্যাব্রিক দেখায়।
  - Gmt Color আর All Col?: ম্যাপ করা রং থেকে, অথবা সব রং।
  - Fab Color: শুরুতে "গার্মেন্টের রঙের মতো"।
  - Ratio %: শুরুতে ১০০।
  - Add (এডিটের সময় Update) আর Cancel।
- **নিয়ম:**
  - একই আইটেম, পার্ট আর গার্মেন্ট রঙের অনুপাতের যোগফল ১০০%-এর বেশি হতে পারে না।
  - ম্যাপিংয়ে ব্যবহৃত ফ্যাব্রিক Fabrication থেকে, আর ব্যবহৃত রং Colour Mapping থেকে সরানো যায় না ("in use" বার্তা আসে)।
- **Fabrication-এ বদল:** save এখন সব মুছে নতুন করে লেখে না। আইডি ধরে বিদ্যমান ফ্যাব্রিক আপডেট হয়, নতুন যোগ হয় আর সরানো ফ্যাব্রিক মোছে। তাই এডিট করলেও ম্যাপিং টিকে থাকে।
- Brickland-এ 637PK-এ কোনো ফ্যাব্রিক নেই, তাই সেখানে Fabric তালিকার লেবেল কেমন দেখায় তা দেখা যায়নি। আমাদেরটায় ফ্যাব্রিকের Description দেখায়।

**B7 — Packing Instruction (২০২৬-১০-১৩):**
- **বাঁয়ে:**
  - উপরে Work Style/Job# বাছাই, যা দিয়ে অর্ডার অনুযায়ী তালিকা ছাঁকা যায়।
  - তালিকার কলাম: SKU Ref#, Order, Item, Color, Pcs/Pack, Qty, আর Edit/Remove। নিচে মোট, Save আর Cancel।
- **ডানে ফর্ম:**
  - SKU Ref#, শুরুতে "N/A"।
  - প্যাকের ধরন: Single Pcs Pack, Multi-P.Pack [Uniform Ratio] বা Multi-P.Pack [Random Ratio]।
  - Pcs Per Pack: Single হলে ১, আর ঘরটা বন্ধ থাকে।
  - Order: এই স্টাইলের বাতিল নয় এমন অর্ডার।
  - Gmt Item: স্টাইলের আইটেম।
  - Color: স্টাইলের রং, অথবা All Col? টিক।
  - Add/Update আর Cancel (`merch_style_packing`)।
- **Qty** মানে অর্ডারের জন্য কতটা প্যাক লাগবে:
  - অর্ডারে Size Breakdown থাকলে, সেখানে ওই রঙের পিস নেওয়া হয় (রঙের নাম মিলিয়ে, বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)। All Col? হলে সব রঙের পিস।
  - Size Breakdown না থাকলে পুরো অর্ডারের পিস।
  - পিস ভাগ Pcs Per Pack, উপরের দিকে গোল করা। Set/Dzn অর্ডারে পিস ধরা হয় একক × প্রতি এককে পিস।
- প্যাকিংয়ে ব্যবহৃত রং Colour Mapping থেকে সরানো যায় না। অর্ডার বা আইটেম মুছে গেলে তার প্যাকিং লাইনও মুছে যায়।
- Order Confirmation-এর Auto Fill-এ "Packing Instructions" এখন আসল সংখ্যা দেখায়।
- **Brickland থেকে আলাদা:**
  - Brickland-এর Order / Gmt Item / Color পিকার খুললে কিছু আসে না, আর Work Style/Job# তালিকায় সব স্টাইল দেখায়।
  - আমাদেরটায় পিকারগুলো এই স্টাইলের অর্ডার, আইটেম আর রং দেখায়, আর Work Style/Job# এই স্টাইলের অর্ডার অনুযায়ী ছাঁকে।
  - Brickland-এ Qty সবসময় ০ দেখায়। আমাদেরটায় তা হিসাব হয়।

**রিলোড ছাড়া ডেস্ক আর পড়ার মতো URL (২০২৬-১০-১২, ব্যবহারকারীর অনুরোধে):**
- Item/Combo/Model ডেস্ক এখন `StyleSheetDesk` (client component)। Fresh, Copy, Close আর ট্যাব বদলাতে পেজ রিলোড বা navigation হয় না, URL-ও একই থাকে। প্রতিটা ট্যাবের ডাটা খোলার সময় server action দিয়ে আসে।
- URL-এ আর ভেতরের আইডি (ULID) যায় না। রেকর্ডকে তার পড়ার মতো নামে ডাকা হয় (`frontend/src/lib/row-ref.js`):
  - স্টাইল: `?tab=sheet&style=FLOW-STYLE-01`।
  - ইনকোয়ারি নম্বর আর বায়ার কোড: `?buyer=LPP`।
  - মাস্টারের Edit: `?tab=edit&ref=…`, গ্রিডের প্রথম কলাম বা `refKey` দিয়ে।
  - HR আর Finance-এর মতো মডিউলের Edit ও in-place edit: `?edit=Account-Transfer`।
  - বাঁয়ে তালিকা-ডানে বিস্তারিত প্যানেল (রেট চার্টের অফিস, TNA টেমপ্লেট, টাস্ক গ্রুপ, বায়ারের ট্রিমস, Notify Party-র বায়ার) একইভাবে।
- একই নামের দুটো রেকর্ড থাকলে দ্বিতীয়টা `NAME~2` হয়।

