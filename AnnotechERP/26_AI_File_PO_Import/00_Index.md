# AI File & PO Import

> **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **ফোল্ডার:** `26_AI_File_PO_Import`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `26/01` | [Intake](01_Intake.md) | ৫ | ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন) |
| `26/02` | [Documents](02_Documents.md) | ১২ | ⬜ শুরু হয়নি |
| `26/03` | [Learning](03_Learning.md) | ৫ | ⬜ শুরু হয়নি |
| `26/04` | [Data & Flow](04_Data_Flow.md) | ৪ | ⬜ শুরু হয়নি |
| `26/05` | [Intelligence](05_Intelligence.md) | ৩ | ⬜ শুরু হয়নি |
| `26/06` | [Governance](06_Governance.md) | ৩ | ⬜ শুরু হয়নি |
<!-- AUTO:status:end -->

## নোট

- **কোড:** নিজস্ব মডিউল `app/Modules/AiImport` (টেবিল `ai_*`, lang `aiimport`); frontend route `/ai-file-import/[screen]`।
- **পারমিশন:** প্রতি স্ক্রিন-গ্রুপে একজোড়া — `aiimport.{group}.view` / `.manage`।
  - Factory Admin / Factory Manager সব পান; Auditor শুধু দেখেন।
- **ধরন:** Brickland-এর ৩২টা স্ক্রিনই "Demo data" রেজিস্টার। Buyer Gateway (25)-এর মতো ERP resource kit দিয়ে বানানো, buyer আসল buyer master থেকে।
- **"AI" অংশ (২০২৬-১১-১২-এর সিদ্ধান্তে আসল):**
  - ইনটেকে আপলোড করা ফাইল AI সত্যিই পড়ে। কোন AI সার্ভিস পড়বে, তা ঠিক হয় Settings → Integration Hub → **AI Services** থেকে (Claude, OpenAI বা Gemini; প্রতিটার নিজের key)।
  - কোড `app/Modules/AiCore` (পুরো ERP-র জন্য): সার্ভিস আর key, কোন ফিচার কোন সার্ভিস ব্যবহার করবে, তিন সার্ভিসের client, আর ফাইলকে AI-এর পড়ার মতো করে সাজানো (`DocumentParts`)।
  - Merchandise-এর AI বিশ্লেষণ (06/02–04) একই settings থেকে চলে; `.env`-এর `ANTHROPIC_API_KEY` আর ব্যবহার হয় না।
  - পরের AI স্ক্রিন (26/02-এর ডকুমেন্ট রিডার, 26/05-এর কপাইলট) এই একই settings আর client ব্যবহার করবে।
