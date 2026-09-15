import { Badge } from "@/components/ui/badge";

/**
 * docs/UI-DESIGN-SYSTEM.md §5: maps a domain status to a tone, consistently
 * across the app. Colour is never the only signal — the word is always
 * shown too, since colour alone is unreadable to a colour-blind user and
 * meaningless on a monochrome wall display.
 *
 * The map below is the vocabulary table from §5. A status not listed falls
 * back to neutral rather than guessing a tone for it.
 */
const TONE_BY_STATUS = {
  ACTIVE: "success",
  APPROVED: "success",
  // WorkOrder::STATUSES' own COMPLETED means "done, not yet verified" and
  // is genuinely "info" there (work_order/_status.blade.php), not
  // "success" — corrected here since no screen built so far actually
  // relied on the old generic value.
  COMPLETED: "info",
  PASSED: "success",
  RUNNING: "success",
  PENDING: "warning",
  GRACE: "warning",
  REWORK: "warning",
  ON_HOLD: "warning",
  REJECTED: "danger",
  EXPIRED: "danger",
  SUSPENDED: "danger",
  CANCELLED: "danger",
  FAILED: "danger",
  BREAKDOWN: "danger",
  DRAFT: "neutral",
  INACTIVE: "neutral",
  TRIAL: "info",
  // InventoryTransfer::STATUSES (API 14) — requested, approved, in transit
  // between a dispatch and a receipt, or received/rejected.
  REQUESTED: "warning",
  IN_TRANSIT: "brand",
  RECEIVED: "success",
  // Asset status machine (Asset::STATUSES) — mirrors
  // resources/views/assets/_status.blade.php's tone match exactly.
  COMMISSIONED: "success",
  UNDER_REPAIR: "danger",
  UNDER_MAINTENANCE: "warning",
  IDLE: "warning",
  RETIRED: "neutral",
  SCRAPPED: "neutral",
  LOST: "neutral",
  PURCHASED: "info",
  INSTALLED: "info",
  // Breakdown status machine (Breakdown::STATUSES) — mirrors
  // resources/views/breakdowns/_status.blade.php's tone match. Its own
  // CANCELLED maps to a neutral "secondary" there, but CANCELLED is
  // already "danger" above for other domains (a cancelled approval, for
  // one) — left as danger here too rather than splitting this map by
  // domain for one status's tone.
  REPORTED: "danger",
  ACKNOWLEDGED: "warning",
  // Breakdown's ASSIGNED ("still needs attention") and WorkOrder's own
  // ASSIGNED ("queued, routine") genuinely mean different things — this
  // flat map can't hold both. Breakdown's meaning wins here since it was
  // set first; WorkOrder's ASSIGNED shows as warning instead of its own
  // Blade view's neutral "secondary" as a result.
  ASSIGNED: "warning",
  IN_REPAIR: "brand",
  REPAIRED: "info",
  PRODUCTION_RESUMED: "success",
  CLOSED: "success",
  // WorkOrder status machine (WorkOrder::STATUSES) — mirrors
  // work-orders/_status.blade.php's tone match, aside from the ASSIGNED/
  // COMPLETED notes above.
  PENDING_APPROVAL: "warning",
  IN_PROGRESS: "brand",
  VERIFIED: "success",
  // Vendor::STATUSES.
  BLACKLISTED: "danger",
  // WarrantyClaim::STATUSES (API 18) — mirrors warranties/show.blade.php's
  // own tone match for the two values no other domain already claimed.
  SUBMITTED: "warning",
  SETTLED: "success",
  // SubscriptionInvoice/SubscriptionContract::STATUSES (SRS 40) — mirrors
  // billing/index.blade.php and invoice.blade.php's own tone matches.
  ISSUED: "info",
  PAID: "success",
  OVERDUE: "danger",
  PARTIALLY_PAID: "warning",
  VOID: "neutral",
  WRITTEN_OFF: "neutral",
  PAST_DUE: "warning",
  READ_ONLY: "danger",
  ARCHIVED: "neutral",
};

/** @param {{ status: string, label?: string, className?: string }} props */
function StatusBadge({ status, label, className }) {
  const tone = TONE_BY_STATUS[status] ?? "neutral";

  return (
    <Badge variant={tone} className={className}>
      {label ?? formatStatus(status)}
    </Badge>
  );
}

function formatStatus(status) {
  if (!status) return "";
  return status
    .toLowerCase()
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

export { StatusBadge, TONE_BY_STATUS, formatStatus };
