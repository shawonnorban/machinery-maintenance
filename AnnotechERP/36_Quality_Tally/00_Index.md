# Quality Tally

> **ফেজ ৫:** প্রোডাকশন · **ফোল্ডার:** `36_Quality_Tally`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `36/01` | [Quality Tally](01_Quality_Tally.md) | ৮ | ✅ সম্পন্ন |
<!-- AUTO:status:end -->

## নোট

- annotech-rmg প্রজেক্টের QC টালি সিস্টেম থেকে আনা (user, 2026-11-24)। Brickland-এ এই স্ক্রিন নেই — তাই চেহারা AnnotechERP-এর, নিয়ম rmg-এর।
- **আলাদা মডিউল:** নিজের Job ও Line Target ইনপুট, নিজের টালি আউটপুট; বাকি ERP-র সাথে (Merchandise order, Production Tracking) এখনো যুক্ত নয় — দরকার হলে পরে যুক্ত হবে।
- **আলাদা বিক্রি:** Platform → Customer → **Modules** ট্যাব থেকে চালু করলে তবেই কোম্পানি দেখে (`company_modules`, `LicensedModules`)। বন্ধ থাকলে সব `qtally.*` পারমিশন বাতিল — সাইডবারে নেই, API 403, টাইপ করা ঠিকানায় "মডিউল চালু নেই"।
