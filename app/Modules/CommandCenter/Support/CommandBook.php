<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Support;

/**
 * Command Center (AnnotechERP 27): the lists its screens choose from.
 */
final class CommandBook
{
    public const REFRESH = ['LIVE', 'HOURLY', 'DAILY', 'WEEKLY'];

    /** Who a board is for. */
    public const AUDIENCES = ['BOARD', 'DEPT_HEADS', 'ALL_MANAGERS', 'EVERYONE'];

    public const DOMAINS = ['COMMERCIAL', 'PRODUCTION', 'QUALITY', 'PEOPLE', 'FINANCE'];

    /** The three levels a metric opens along, coarse to fine. */
    public const LEVEL_1 = ['DIVISION', 'BUYER', 'SEASON', 'DEPARTMENT', 'COST_CENTRE'];

    public const LEVEL_2 = ['STYLE', 'LINE', 'ORDER', 'MACHINE', 'ACCOUNT'];

    public const LEVEL_3 = ['SHIFT', 'OPERATOR', 'COLOUR', 'SIZE', 'DAY'];

    /** The module a metric's rows come from. */
    public const SOURCES = ['MERCHANDISING', 'KNIT', 'ASSEMBLY', 'CUTTING', 'ACCOUNTING', 'HR'];

    public const GRAINS = ['HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY'];

    public const LAYOUTS = ['TWO', 'THREE', 'SINGLE', 'GRID', 'FULL'];

    /** Who a composition is shared with. */
    public const SHARED = ['BOARD', 'DIVISION_HEADS', 'ALL_MANAGERS', 'EVERYONE'];

    public const COMPOSITION_STATES = ['DRAFT', 'RELEASED', 'ARCHIVED'];
}
