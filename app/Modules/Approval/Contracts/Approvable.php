<?php

declare(strict_types=1);

namespace App\Modules\Approval\Contracts;

/**
 * A document the approval engine can route (SRS 14, Procurement spec §5).
 *
 * The engine itself knows nothing about requisitions or purchase orders. A
 * document tells it what the rules are evaluated against, how to describe
 * itself to an approver, and what to do when the decision comes back; the
 * engine owns everything in between. The model must also carry an
 * `approval_status` and `approval_request_id` column.
 */
interface Approvable
{
    /**
     * The values the rules are evaluated against, frozen into the request.
     * Must include `cost` (4dp string) and `currency`; `factory_id` when the
     * document belongs to one.
     *
     * @return array<string, mixed>
     */
    public function approvalContext(): array;

    /**
     * How the document is named to an approver and where they go to see it.
     *
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array;

    /**
     * The engine's verdict, after `approval_status` has been written:
     * APPROVED, REJECTED, or NOT_REQUIRED (no matching step, or cancelled).
     * The document moves its own workflow status here.
     */
    public function onApprovalDecided(string $status): void;
}
