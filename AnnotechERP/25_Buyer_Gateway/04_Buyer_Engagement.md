# Buyer Engagement

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Buyer Gateway](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `25/04`

**⬅ ফিরে যান:** [Buyer Gateway](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Buyer Request Inbox | `/m/buyer-portal/buyer-requests` | 1 | 3 | 0 |
| 2 | Complaint & Claim Register | `/m/buyer-portal/complaint-register` | 1 | 3 | 0 |
| 3 | Portal Announcements | `/m/buyer-portal/announcements` | 1 | 3 | 1 |
| 4 | Buyer Feedback Survey | `/m/buyer-portal/feedback-survey` | 1 | 3 | 1 |
| 5 | Support Ticket Desk | `/m/buyer-portal/support-tickets` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Buyer Request Inbox

`/m/buyer-portal/buyer-requests`

**ভিউ**
> 06 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer request inbox | search |  | Search requests… |
| Filter by kind | select |  | All kinds, Order change, Sample request, Document request, Price query, Shipment query, Other |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request, Kind, Routed to, Age, Priority, State
- সেকশন: Request inbox
- বাটন/অ্যাকশন: Log request, Request, Kind, Routed to, Age, Priority, State

#### Complaint & Claim Register

`/m/buyer-portal/complaint-register`

**ভিউ**
> 07 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Complaint and claim register | search |  | Search complaints… |
| Filter by state | select |  | All states, Open, Investigating, Offer made, Settled, Rejected |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Shipment, Cause, Claim, State
- সেকশন: Complaints and claims
- বাটন/অ্যাকশন: Log complaint, Reference, Buyer, Shipment, Cause, Claim, State

#### Portal Announcements

`/m/buyer-portal/announcements`

**ভিউ**
> 10 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal announcements | search |  | Search announcements… |
| Filter by audience | select |  | All audiences, All buyers, Strategic buyers, One buyer, Logistics contacts |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Announcement, Kind, Audience, Expires, Reads, State
- সেকশন: Announcements
- বাটন/অ্যাকশন: New announcement, Announcement, Kind, Audience, Expires, Reads, State

**ডায়ালগ: New announcement** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Title* | text | ✔ |  |
| Audience | select |  | Select…, All buyers, Strategic buyers, One buyer, Logistics contacts |
| Kind | select |  | Select…, Holiday, Capacity, Policy, System, General |
| Published | date |  |  |
| Expires | date |  |  |
| Reads | number |  |  |
| State | select |  | Select…, Live, Scheduled, Expired, Draft |
| Body | textarea |  |  |

#### Buyer Feedback Survey

`/m/buyer-portal/feedback-survey`

**ভিউ**
> 15 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer feedback surveys | search |  | Search surveys… |
| Filter by state | select |  | All states, Open, Closed, Draft |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Survey, Season, Sent, Replies, Score, Sent on, State
- সেকশন: Feedback surveys
- বাটন/অ্যাকশন: New survey, Survey, Season, Sent, Replies, Score, Sent on, State

**ডায়ালগ: New survey** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Survey* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Season | select |  | Select…, Winter 26, Summer 26, Winter 25 |
| Sent on | date |  |  |
| Recipients | number |  |  |
| Responses | number |  |  |
| Average score | number |  |  |
| State | select |  | Select…, Open, Closed, Draft |

#### Support Ticket Desk

`/m/buyer-portal/support-tickets`

**ভিউ**
> 16 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Portal support tickets | search |  | Search tickets… |
| Filter by state | select |  | All states, New, In progress, Waiting on buyer, Resolved, Closed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Subject, Owner, Hours, Priority, State
- সেকশন: Support tickets
- বাটন/অ্যাকশন: New ticket, Reference, Subject, Owner, Hours, Priority, State

**ডায়ালগ: New ticket** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Reference* | text | ✔ |  |
| Subject* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Priority | select |  | Select…, Urgent, High, Normal, Low |
| Owner | select |  | Select…, Unassigned, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam |
| Raised on | date |  |  |
| Hours open | number |  |  |
| State | select |  | Select…, New, In progress, Waiting on buyer, Resolved, Closed |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **25/01–03-এর কাঠামো:** `BuyerGatewayResource` (এখন `nextNumber()` সহ), `PortalBook`, `portalScreen()`, টাইল।
- **Masters:** buyer master; টিকিটের দায়িত্বে কে — কোম্পানির স্টাফ (`company_users`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_23_000100_create_buyer_gateway_engagement`:
- **`bg_requests`:**
  - বিষয়, buyer, ধরন (Order change / Sample / Document / Price / Shipment / Other)।
  - যে ডেস্কে যাবে (Merchandising / Sample room / Commercial / Planning / Quality / Unrouted)।
  - তোলার তারিখ, অগ্রাধিকার (Urgent / Normal / Low), অবস্থা (New / In progress / Answered / Closed), বন্ধের তারিখ।
- **`bg_complaints`:**
  - রেফারেন্স (অনন্য, CMP-YYYY-NNN), buyer, শিপমেন্ট (বড় হাতে)।
  - কারণ (Shade / Measurement / Stitching / Packing / Short shipment / Late delivery / Not established)।
  - ক্লেইম (USD, খালি থাকতে পারে), তোলার তারিখ, অবস্থা (Open / Investigating / Offer made / Settled / Rejected), সমাধান।
- **`bg_announcements`:**
  - শিরোনাম, শ্রোতা (All / Strategic / One buyer / Logistics), buyer (শুধু One buyer-এ)।
  - ধরন (Holiday / Capacity / Policy / System / General), প্রকাশ ও মেয়াদ শেষের তারিখ, পড়া, `draft`, লেখা।
- **`bg_surveys`:** নাম, buyer, সিজন, পাঠানোর তারিখ, প্রাপক, উত্তর, গড় স্কোর (০–১০), অবস্থা (Open / Closed / Draft)।
- **`bg_tickets`:**
  - রেফারেন্স (অনন্য, TKT-YYYY-NNN), বিষয়, buyer, অগ্রাধিকার (Urgent / High / Normal / Low)।
  - দায়িত্বে (স্টাফ বা কেউ না), তোলার তারিখ।
  - অবস্থা (New / In progress / Waiting on buyer / Resolved / Closed), `resolved_at`।

## ৫. Workflow / Status ও Business Rules

- **অনুরোধ:**
  - কোনো ডেস্কে না পাঠানো অনুরোধ In progress বা Answered হয় না ("আগে ডেস্কে পাঠান")। একই সেভে ডেস্ক বাছলে চলে।
  - তারিখ না দিলে আজ।
  - Closed করলে বন্ধের দিন বসে, বয়স সেখানেই থামে। আবার খুললে বন্ধের দিন মুছে যায়।
  - বয়স (দিন) = তোলার দিন থেকে বন্ধের দিন বা আজ পর্যন্ত।
- **অভিযোগ:**
  - রেফারেন্স খালি রাখলে সে বছরের পরের নম্বর; হাতে দেওয়া নম্বরের পর থেকে গোনে।
  - Settled বা Rejected করতে সমাধান লিখতে হয়। পরে সমাধান মুছে ফেলাও আটকায়।
- **ঘোষণা:**
  - শুধু Draft রাখা হয়। বাকি অবস্থা তারিখ দেখে আসে: প্রকাশের দিন আসেনি → Scheduled, মেয়াদ পেরিয়েছে → Expired, নইলে Live।
  - প্রকাশের তারিখ না দিলে আজ। প্রকাশের আগে মেয়াদ শেষ হয় না।
  - One buyer-এ buyer লাগে (পরে buyer মুছলেও আটকায়)। অন্য শ্রোতায় buyer সরে যায়।
- **জরিপ:**
  - প্রাপকের বেশি উত্তর হয় না; পরে প্রাপক কমিয়েও না।
  - উত্তর ছাড়া স্কোর হয় না; স্কোর থাকলে উত্তর ০ করা আটকায়।
  - Open বা Closed করতে পাঠানোর তারিখ লাগে।
- **টিকিট:**
  - রেফারেন্স খালি রাখলে TKT-বছর-পরের নম্বর।
  - দায়িত্বে শুধু নিজের কোম্পানির স্টাফ।
  - Resolved বা Closed করলে সমাধানের সময় বসে; আবার খুললে মুছে যায়।
  - ঘণ্টা = তোলার দিনের শুরু থেকে সমাধান পর্যন্ত (বা এখন পর্যন্ত)।

## ৬. API ও পারমিশন

পারমিশন: `buyergateway.engagement.view`, `buyergateway.engagement.manage` (Factory Admin / Factory Manager; Auditor শুধু দেখেন)।

API: `/api/v1/erp/bg-requests`, `bg-complaints`, `bg-announcements`, `bg-surveys`, `bg-tickets`। Picker-এর জন্য `GET /api/v1/buyer-gateway/options`।

## ৭. Frontend পেজ (লাইট থিম)

একই `buyer-gateway/[screen]` route; সাইডবারে Buyer Engagement ফোল্ডার।
- **Buyer Request Inbox** (`/buyer-gateway/buyer-requests`):
  - কলাম: অনুরোধ (নিচে buyer), ধরন, ডেস্ক, বয়স, অগ্রাধিকার, অবস্থা।
  - ধরন দিয়ে ফিল্টার।
  - টাইল: মোট, নতুন, ডেস্কে না পাঠানো, জরুরি (শেষ দুটো বন্ধ ছাড়া)।
- **Complaint & Claim Register** (`/buyer-gateway/complaint-register`):
  - কলাম: রেফারেন্স, buyer (নিচে সমাধান), শিপমেন্ট, কারণ, ক্লেইম ($18.4K), অবস্থা।
  - টাইল: মোট, খোলা, ক্লেইমের যোগফল, নিষ্পত্তি।
- **Portal Announcements** (`/buyer-gateway/announcements`):
  - কলাম: ঘোষণা (নিচে লেখা), ধরন, শ্রোতা (One buyer হলে buyer-এর নাম), মেয়াদ, পড়া, অবস্থা।
  - টাইল: মোট, চালু, নির্ধারিত, পড়ার যোগফল।
- **Buyer Feedback Survey** (`/buyer-gateway/feedback-survey`):
  - কলাম: জরিপ (নিচে buyer), সিজন, প্রাপক, উত্তর, স্কোর, পাঠানোর তারিখ, অবস্থা।
  - টাইল: মোট, উত্তরের যোগফল, গড় স্কোর (স্কোর থাকা জরিপগুলোর গড়), খোলা।
- **Support Ticket Desk** (`/buyer-gateway/support-tickets`):
  - কলাম: রেফারেন্স, বিষয় (নিচে buyer), দায়িত্বে, ঘণ্টা, অগ্রাধিকার, অবস্থা।
  - টাইল: মোট, খোলা, কারও দায়িত্বে নেই, জরুরি (শেষ দুটো শুধু খোলা টিকিট)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/BuyerGateway`)**
- `Database/Migrations/2027_03_23_000100_create_buyer_gateway_engagement.php`
- `Models/BuyerRequest.php`, `Complaint.php`, `Announcement.php`, `Survey.php`, `Ticket.php`
- `Resources/BuyerRequestResource.php`, `ComplaintResource.php`, `AnnouncementResource.php`, `SurveyResource.php`, `TicketResource.php`
- `Resources/BuyerGatewayResource.php` (`nextNumber()`), `Support/PortalBook.php` (বদলানো)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` (বদলানো)
- `lang/en/buyergateway.php`, `lang/bn/buyergateway.php` (বদলানো)

**Frontend**
- `app/(app)/buyer-gateway/[screen]/screens.js`, `components/buyergateway/tiles.jsx` (বদলানো)

**Test**
- `tests/Feature/BuyerGateway/BuyerGatewayEngagementTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`BuyerGatewayEngagementTest.php`, ৪টা টেস্ট / ১৩০টা assertion):** test2 DB-তে পাস। Buyer Gateway-এর মোট ১৩টা টেস্ট / ৩৫৩টা assertion পাস; MenuAccess ৬ / ৩৪ পাস।
- **অনুরোধ:**
  - বিষয় ছাড়া আটকায়; নাম ছেঁটে সেভ; ডেস্ক Unrouted, বয়স ১০ দিন; তারিখ না দিলে আজ।
  - না পাঠানো অনুরোধ In progress / Answered আটকায়; ডেস্কসহ একই সেভে চলে; অজানা ডেস্ক আটকায়।
  - Closed করলে বন্ধের দিন বসে, বয়স ১২-তে থামে (৭ দিন পরেও ১২); আবার খুললে মুছে যায়।
- **অভিযোগ:**
  - buyer ছাড়া আটকায়; CMP-2026-001, শিপমেন্ট বড় হাতে; cmp-2026-040 → পরেরটা 041; একই রেফারেন্স আটকায়; অজানা কারণ আটকায়।
  - সমাধান ছাড়া Settled আটকায়; সমাধান দিয়ে চলে; Settled-এ সমাধান মোছা আটকায়; Open-এ ফিরলে মোছা যায়; খালি রেফারেন্স পুরোনোটা রাখে।
- **ঘোষণা:**
  - শিরোনাম ছাড়া আটকায়; প্রকাশ আজ → Live; সামনে → Scheduled; পেরোনো → Expired; Draft থাকে; একদিনের ঘোষণা আজ Live।
  - Draft থেকে অন্য অবস্থায় → তারিখ দেখে Live।
  - One buyer-এ buyer ছাড়া আটকায়; পরে buyer মোছাও আটকায়; Strategic-এ গেলে buyer সরে যায়।
  - প্রকাশের আগে মেয়াদ শেষ আটকায়; অজানা শ্রোতা আটকায়।
- **জরিপ:**
  - নাম ছাড়া আটকায়; Draft, ০/০; অজানা সিজন আটকায়।
  - পাঠানোর তারিখ ছাড়া Open আটকায়; উত্তর ছাড়া স্কোর আটকায়; প্রাপকের বেশি উত্তর আটকায়।
  - প্রাপক কমানো আর স্কোর থাকতে উত্তর ০ করা আটকায়; ১০-এর বেশি স্কোর আটকায়; DB-তে ২৪/১৮/৮.২ ঠিক থাকে।
- **টিকিট:**
  - বিষয় ছাড়া আটকায়; TKT-2026-001, আজ, ১০ ঘণ্টা (সকাল ১০টা); গতকালের টিকিট ৩৪ ঘণ্টা; একই রেফারেন্স আটকায়; অজানা অগ্রাধিকার আটকায়।
  - অন্য কোম্পানির ইউজার দায়িত্বে দেওয়া আটকায়; নিজের স্টাফের নাম দেখায়।
  - Resolved-এ সময় বসে, ৪০ ঘণ্টা; পরে Closed-এও ৪০; Waiting-এ ফিরলে সময় মুছে যায়।
- **কে পারেন:** Auditor দেখেন, যোগ করতে পারেন না। Manager পারেন। Storekeeper দেখেন না। অন্য কোম্পানি কিছু দেখে না, এই buyer নিতে পারে না।

**UI চেক (`app_check/plans/bg04`, `bg04b`, ২০২৬-১১-১২, owner; error 0; dev ডাটা রাখা আছে):**
- অনুরোধ: "Move ex-factory of SO-4402 by a week" (H&M, Urgent) আর "Lab dips for Winter 26 navy" (KIABI, Sample room)। প্রথমটাকে ডেস্ক ছাড়া In progress করলে লাল বার্তা; Planning দিয়ে সেভ → In progress, বয়স ৯ দিন ✔
- অভিযোগ: CMP-2026-001 (H&M, SHP-26-118, রঙ, $18.4K) আর CMP-2026-002 (KIABI, কম শিপমেন্ট)। সমাধান ছাড়া Settled-এ বার্তা; সমাধান দিয়ে Settled ✔
- ঘোষণা: Victory Day (১ ডিসেম্বর → Scheduled), capacity (Strategic, Live), L/C (One buyer; buyer ছাড়া বার্তা, KIABI দিয়ে Live) ✔
- জরিপ: Winter 26 review (H&M, ২৪ জনে ৩০ উত্তরে বার্তা → ১৮, ৮.২, Open) আর Summer 27 (KIABI, Draft)। পরে প্রাপক ১০ করা আটকায়, DB-তে ২৪ ✔
- টিকিট: TKT-2026-001 (H&M, Urgent, New) আর TKT-2026-002 (KIABI, ১০ নভেম্বর → Resolved, ৪৮ ঘণ্টা)। টাইলে কারও দায়িত্বে নেই = ১ (সমাধান হওয়াটা বাদ) ✔

## ১০. খোলা প্রশ্ন

১. **অভিযোগ কোন শিপমেন্টের:** শিপমেন্ট এখন লেখা ঘর। Sales & Shipment-এর আসল শিপমেন্ট থেকে বাছাই করব কিনা জানাবেন। Quality-র CAPA-তে নিজে থেকে পাঠাব কিনা, সেটাও জানাবেন।

২. **জরিপের প্রশ্ন:** Brickland-এর মতো শুধু সারাংশ রাখা হয় — প্রাপক, উত্তর, গড় স্কোর। আসল প্রশ্নপত্র (প্রশ্ন, বায়ারের প্রতিটা উত্তর) লাগবে কিনা জানাবেন।

৩. **ডেস্ককে জানানো:** অনুরোধ কোনো ডেস্কে পাঠালে বা টিকিট কারও দায়িত্বে দিলে এখন কোনো নোটিফিকেশন যায় না। পাঠাব কিনা জানাবেন। ঘোষণার "পড়া" এখন হাতে লেখা; আসল পোর্টাল চালু হলে নিজে থেকে গোনা হবে (25/01-এর প্রশ্ন দেখুন)।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/bg04`। Brickland-এর ক্রম 06, 07, 10, 15, 16 of 20; সাইডবারের ক্রম আমাদের ফোল্ডার অনুযায়ী।
- **হিসাবের ঘর:** Brickland টিকিটে "Hours open" আর রেফারেন্স হাতে লেখায়। এখানে ঘণ্টা আর বয়স নিজে হিসাব হয়, রেফারেন্স খালি রাখলে নিজে বসে।
- **দায়িত্বে কে:** Brickland নির্দিষ্ট পাঁচটা নাম দেখায়। এখানে কোম্পানির আসল স্টাফ।
- **ঘোষণার অবস্থা:** Live / Scheduled / Expired বাছাই রাখা হয়েছে (Brickland-এর মতো)। তবে শুধু Draft রাখা হয়, বাকিটা তারিখ থেকে আসে।
- **Test আর UI চেকে ধরা পড়া:**
  - One buyer ঘোষণায় পরে buyer মুছলে আটকাত না।
  - জরিপে পরে প্রাপক কমানো যেত।
  - অভিযোগে Settled-এ সমাধান মোছা যেত।
  - টিকিট/অনুরোধের Unassigned / Unrouted / Urgent টাইলে বন্ধগুলোও গোনা হতো।
  - সব ঠিক করা হয়েছে।
