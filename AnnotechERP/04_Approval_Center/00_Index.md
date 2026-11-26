# Approval Center

> **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **ফোল্ডার:** `04_Approval_Center`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `04/01` | [Request Inbox](01_Request_Inbox.md) | ৪ | ✅ সম্পন্ন (৪টা স্ক্রিন; New Request, বিস্তারিত আর সিদ্ধান্তসহ) |
| `04/02` | [Decisions](02_Decisions.md) | ৪ | ✅ সম্পন্ন (৪টা স্ক্রিন) |
| `04/03` | [Dashboard & Setup](03_Dashboard_Setup.md) | ২ | ✅ সম্পন্ন (২টা স্ক্রিন; escalation কমান্ডসহ) |
<!-- AUTO:status:end -->

## নোট

**মূল সিদ্ধান্ত (২০২৬-১০-১০):**
- **একটাই ইনবক্স।** Brickland-এর All Requests "সব মডিউলের" অনুমোদন দেখায়। তাই নতুন কোনো আলাদা ইঞ্জিন বানানো হবে না। বিদ্যমান `approval_requests` (work order, PR, PO, stock count, material issue, purchase return …) আর নতুন হাতে-লেখা request (`approval_tickets`), সবই একই তালিকায় আসবে।
- **হাতে-লেখা request (New Request)** নিজেই একটা Approvable entity: `MANUAL_REQUEST`। তাই অনুমোদন, প্রত্যাখ্যান আর ইতিহাস বিদ্যমান `DecideApproval`-ই চালাবে।
- **ধাপের নিয়ম (Brickland-এর কথায়):** Request To সব সময় লেভেল ১। request type-এর approval chain পরের ধাপগুলো যোগ করে, request-এর amount অনুযায়ী (band)।
- **Approval Workflow Designer** (Access Control 02/03, আগে স্থগিত) এখানেই তৈরি হবে, কারণ chain ছাড়া request type অর্থহীন। ভিত্তি `approval_workflows` (action type) আর `approval_rules` (action/ধাপ)।
- **Buyer / Order / Style:** Brickland-এ এগুলো select, Merchandise-এর মাস্টার থেকে আসে। সেই মাস্টার এখনো নেই, তাই আপাতত লেখার ঘর। Merchandise Desk তৈরি হলে select হবে (`buyer_id`, `order_id`, `style_id`), আর পুরনো লেখা মান থেকে মিলিয়ে নেওয়া হবে।
- **Return / Revision** আপাতত শুধু হাতে-লেখা request-এ। মডিউলের document (PR, PO …) নিজে "ফেরত এসেছে" অবস্থা বোঝে না। সেগুলো নিজ মডিউল নতুন করে তৈরির সময় যুক্ত হবে।

**ধাপে ধাপে কাজ:**
- *ধাপ A (backend):*
  - টেবিল আর মাইগ্রেশন।
  - ইঞ্জিনে বদল: Request To লেভেল, return, পূর্ব → নতুন অবস্থা, নির্দিষ্ট ইউজার।
  - Request Types, tickets, ইনবক্স আর ইতিহাসের API, Designer-এর API।
  - টেস্ট।
- *ধাপ B (frontend):* ৮টা তালিকা-স্ক্রিন, New Request ডায়ালগ, বিস্তারিত আর সিদ্ধান্তের প্যানেল, Request Types, Designer-এর ৪টা ট্যাব।
- *ধাপ C:* Request Dashboard (১০টা টাইল, ১১টা বিশ্লেষণ) আর escalation কমান্ড।
