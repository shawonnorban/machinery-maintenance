<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Support;

use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Buyer Gateway's shared lists (AnnotechERP 25): the portal roles, access
 * levels, onboarding steps, events and states Brickland's screens offer, and
 * the company's buyers, countries and staff by name.
 */
class PortalBook
{
    public const ROLES = ['MERCHANDISER', 'QUALITY_MANAGER', 'SOURCING_HEAD', 'LOGISTICS', 'FINANCE', 'READ_ONLY'];

    public const USER_STATES = ['ACTIVE', 'INVITED', 'SUSPENDED', 'LOCKED'];

    /** A suspended or locked user cannot log in. */
    public const NO_LOGIN = ['SUSPENDED', 'LOCKED'];

    public const ACCESS = ['FULL', 'READ_ONLY', 'HIDDEN'];

    public const DOCUMENTS = ['DOWNLOAD', 'VIEW_ONLY', 'HIDDEN'];

    /** The eight onboarding steps, in order; progress is the share completed. */
    public const STEPS = ['CONTRACT_SIGNED', 'ACCOUNT_CREATED', 'USERS_INVITED', 'PERMISSIONS_SET', 'BRANDING_APPLIED', 'VISIBILITY_AGREED', 'TRAINING_DONE', 'LIVE'];

    public const ONBOARDING_STATES = ['ON_TRACK', 'AT_RISK', 'BLOCKED', 'COMPLETE'];

    public const EVENTS = ['SAMPLE_APPROVED', 'SAMPLE_COMMENTED', 'SHIPMENT_BOOKED', 'SHIPMENT_DELIVERED', 'DOCUMENT_SHARED', 'INVOICE_PUBLISHED', 'ANNOUNCEMENT_POSTED', 'REQUEST_ANSWERED'];

    public const CHANNELS = ['EMAIL', 'IN_PORTAL', 'BOTH', 'MUTED'];

    public const DIGESTS = ['IMMEDIATE', 'DAILY', 'WEEKLY'];

    public const RECIPIENTS = ['ALL', 'MERCHANDISERS', 'NAMED', 'OWNER'];

    public const SCOPES = ['ALL', 'CONFIRMED', 'SEASON', 'NAMED'];

    public const DEPTHS = ['STATUS', 'MILESTONES', 'QUANTITIES', 'FULL'];

    public const DELAYS = ['ALWAYS', 'REVIEWED', 'NEVER'];

    public const SAMPLE_TYPES = ['PROTO', 'FIT', 'SIZE_SET', 'PP', 'SHIPMENT', 'PHOTO'];

    /** A sample's stages; past In development it has been sent. */
    public const SAMPLE_STAGES = ['IN_DEVELOPMENT', 'SENT', 'COMMENTED', 'REVISED', 'APPROVED', 'REJECTED'];

    public const COURIERS = ['DHL', 'FEDEX', 'ARAMEX', 'HAND', 'NOT_SENT'];

    public const MODES = ['SEA', 'AIR', 'SEA_AIR', 'ROAD'];

    /** A shipment's stages; Arrived and Delivered carry the actual date. */
    public const SHIPMENT_STAGES = ['BOOKED', 'GATE_OUT', 'ON_THE_WATER', 'ARRIVED', 'DELIVERED'];

    public const DOCUMENT_KINDS = ['TECH_PACK', 'TEST_REPORT', 'INSPECTION', 'PACKING_LIST', 'INVOICE', 'CERTIFICATE'];

    public const DOCUMENT_STATES = ['SHARED', 'WITHDRAWN', 'EXPIRED'];

    public const RFQ_STATES = ['NEW', 'UNDER_COSTING', 'QUOTED', 'DECLINED', 'CONVERTED'];

    public const INCOTERMS = ['FOB', 'CIF', 'DDP', 'EXW'];

    public const INVOICE_STATES = ['PUBLISHED', 'PAID', 'OVERDUE', 'UNPUBLISHED'];

    public const PAYMENT_TERMS = ['LC_SIGHT', 'LC_60', 'TT_30', 'TT_60', 'OPEN_90'];

    /** Ageing by days past the next due date. */
    public const AGEING = ['NOT_DUE', 'D30', 'D60', 'D90', 'OVER_90'];

    public const REQUEST_KINDS = ['ORDER_CHANGE', 'SAMPLE', 'DOCUMENT', 'PRICE', 'SHIPMENT', 'OTHER'];

    public const DESKS = ['MERCHANDISING', 'SAMPLE_ROOM', 'COMMERCIAL', 'PLANNING', 'QUALITY', 'UNROUTED'];

    public const PRIORITIES = ['URGENT', 'NORMAL', 'LOW'];

    public const REQUEST_STATES = ['NEW', 'IN_PROGRESS', 'ANSWERED', 'CLOSED'];

    public const CAUSES = ['SHADE', 'MEASUREMENT', 'STITCHING', 'PACKING', 'SHORT_SHIPMENT', 'LATE_DELIVERY', 'NOT_ESTABLISHED'];

    public const COMPLAINT_STATES = ['OPEN', 'INVESTIGATING', 'OFFER_MADE', 'SETTLED', 'REJECTED'];

    public const AUDIENCES = ['ALL', 'STRATEGIC', 'ONE', 'LOGISTICS'];

    public const NOTICE_KINDS = ['HOLIDAY', 'CAPACITY', 'POLICY', 'SYSTEM', 'GENERAL'];

    /** An announcement's state follows its window; a draft stays a draft. */
    public const NOTICE_STATES = ['LIVE', 'SCHEDULED', 'EXPIRED', 'DRAFT'];

    public const SURVEY_STATES = ['OPEN', 'CLOSED', 'DRAFT'];

    public const TICKET_PRIORITIES = ['URGENT', 'HIGH', 'NORMAL', 'LOW'];

    public const TICKET_STATES = ['NEW', 'IN_PROGRESS', 'WAITING', 'RESOLVED', 'CLOSED'];

    /** The portal page a buyer opened most in a month (25/05). */
    public const PAGES = ['ORDERS', 'SHIPMENTS', 'SAMPLES', 'DOCUMENTS', 'INVOICES'];

    /** A branding profile's theme; SYSTEM follows the viewer's own setting. */
    public const THEMES = ['LIGHT', 'DARK', 'SYSTEM', 'HIGH_CONTRAST'];

    public const ACCENT_SLOTS = 8;

    public const ACTIONS = ['SIGNED_IN', 'DOWNLOADED', 'APPROVED', 'COMMENTED', 'RAISED_REQUEST', 'SIGNED_OUT'];

    public const DEVICES = ['DESKTOP', 'MOBILE', 'TABLET'];

    /**
     * The months usage is held for: this month and the eleven before it,
     * newest first — "2026-11" => "Nov 2026".
     *
     * @return array<string, string>
     */
    public static function periods(): array
    {
        $month = CarbonImmutable::now('Asia/Dhaka')->startOfMonth();
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $month->subMonthsNoOverflow($i);
            $out[$m->format('Y-m')] = $m->format('M Y');
        }

        return $out;
    }

    /** Summer and Winter of last year, this year and the next two: "Winter 26". @return list<string> */
    public static function seasons(): array
    {
        $y = (int) date('y');
        $out = [];
        foreach (range($y - 1, $y + 2) as $year) {
            $out[] = sprintf('Summer %02d', $year);
            $out[] = sprintf('Winter %02d', $year);
        }

        return $out;
    }

    /** The ageing band of a balance whose next due date is $due, as of $today. */
    public static function ageing(?string $due, float $outstanding, string $today): string
    {
        if ($outstanding <= 0 || ! $due || $due >= $today) {
            return 'NOT_DUE';
        }
        $days = (int) ((strtotime($today) - strtotime($due)) / 86400);

        return match (true) {
            $days <= 30 => 'D30',
            $days <= 60 => 'D60',
            $days <= 90 => 'D90',
            default => 'OVER_90',
        };
    }

    /** The garment items a quotation can be for. @return array<string, string> */
    public static function products(): array
    {
        return self::$cache['products:'.self::company()] ??= DB::table('ws_garment_items')->where('company_id', self::company())->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    /** A step's progress: 1 of 8 is 12%, 8 of 8 is 100%. */
    public static function progress(string $step): int
    {
        $i = array_search($step, self::STEPS, true);

        // Half to even, as Brickland shows them: 12.5 → 12, 37.5 → 38, 62.5 → 62.
        return $i === false ? 0 : (int) round(($i + 1) * 100 / count(self::STEPS), 0, PHP_ROUND_HALF_EVEN);
    }

    /** The company's buyers by their short name. @return array<string, string> */
    public static function buyers(): array
    {
        return self::$cache['buyers:'.self::company()] ??= DB::table('buyers')->where('company_id', self::company())->orderBy('display_order')->orderBy('name')
            ->get(['id', 'name', 'short_name', 'code'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name])->all();
    }

    /** @return array<string, string> */
    public static function countries(): array
    {
        return self::$cache['countries:'.self::company()] ??= DB::table('country_configs')->where('company_id', self::company())->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** The company's people, for an onboarding's owner. @return array<string, string> */
    public static function staff(): array
    {
        return self::$cache['staff:'.self::company()] ??= DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')->where('cu.company_id', self::company())
            ->where('cu.status', 'ACTIVE')->orderBy('u.name')->pluck('u.name', 'u.id')->all();
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
