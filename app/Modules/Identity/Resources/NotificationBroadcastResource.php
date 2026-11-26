<?php

declare(strict_types=1);

namespace App\Modules\Identity\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\NotificationBroadcast;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Access Control → Global Notification Broadcast (AnnotechERP 02/04): a
 * message to everyone in the company, one role, or one user, delivered as an
 * in-app notification the moment it is saved active. The record stays as the
 * log of what was sent, to how many.
 */
class NotificationBroadcastResource extends ResourceDefinition
{
    /** Brickland's four severities onto the notification system's three. */
    private const SEVERITY = ['LOW' => 'INFO', 'MEDIUM' => 'INFO', 'HIGH' => 'WARNING', 'CRITICAL' => 'CRITICAL'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function key(): string
    {
        return 'broadcasts';
    }

    public function model(): string
    {
        return NotificationBroadcast::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['title', 'notification_type', 'message', 'audience', 'audience_id', 'severity', 'message_time', 'offline_time', 'active', 'recipients_count', 'sent_by'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'notification_type' => [$required, Rule::in(NotificationBroadcast::TYPES)],
            'message' => [$required, 'string', 'max:4000'],
            'audience' => [$required, Rule::in(NotificationBroadcast::AUDIENCES)],
            'audience_id' => ['nullable', 'required_if:audience,ROLE,USER', 'string', 'max:64'],
            'severity' => ['nullable', Rule::in(NotificationBroadcast::SEVERITIES)],
            'message_time' => [$required, 'date'],
            'offline_time' => ['nullable', 'date', 'after_or_equal:message_time'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('access.config.broadcast_title_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row === null) {
            $data['sent_by'] = $this->actingUserId();
        }
        if (($data['audience'] ?? $row?->audience) === 'ALL') {
            $data['audience_id'] = null;
        }

        return $data;
    }

    /** A new active broadcast is delivered once; editing the log later sends nothing again. */
    public function afterSave(Model $row, bool $created): void
    {
        if (! $created || ! $row->active) {
            return;
        }

        $recipients = $this->recipients($row);
        $this->dispatcher->sendToMany($recipients, 'GLOBAL_BROADCAST', [
            'title' => $row->title,
            'message' => $row->message,
            'type' => $row->notification_type,
        ], self::SEVERITY[$row->severity ?? 'LOW'] ?? 'INFO', entityType: 'notification_broadcast', entityId: $row->getKey());

        $row->forceFill(['recipients_count' => $recipients->count()])->save();
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['sent_by_name'] = $row->sent_by ? User::query()->whereKey($row->sent_by)->value('name') : null;

        return $out;
    }

    /** @return Collection<int, User> */
    private function recipients(NotificationBroadcast $row): Collection
    {
        $companyId = $this->context->companyId();
        $active = CompanyUser::query()->where('company_id', $companyId)->where('status', 'ACTIVE')->pluck('user_id');

        $ids = match ($row->audience) {
            'USER' => $active->intersect([$row->audience_id]),
            'ROLE' => UserRole::query()->where('company_id', $companyId)->where('role_id', $row->audience_id)->pluck('user_id')->intersect($active),
            default => $active,
        };

        return User::query()->whereIn('id', $ids->unique())->get();
    }
}
