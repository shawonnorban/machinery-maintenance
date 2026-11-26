<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Concerns;

use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtFlow;

/**
 * Each section's people see and change their own section only (user,
 * 2026-11-25): `qtally.view` reads every section, `qtally.{section}.view` one;
 * `.manage` sets its input, targets and attendance; `.tally` taps in it.
 */
trait GuardsSections
{
    /** Reading a section; no section named = all of them at once. */
    protected function allowView(?string $section): void
    {
        if ($section === null || ! in_array($section, QtBook::SECTIONS, true)) {
            $this->allow('qtally.view');
        } elseif (! QtFlow::sees($this->caller(), $section)) {
            $this->allow('qtally.'.strtolower($section).'.view');
        }
    }

    /** Changing a section; no section named = all of them at once. */
    protected function allowManage(?string $section): void
    {
        if ($section === null || ! in_array($section, QtBook::SECTIONS, true)) {
            $this->allow('qtally.target.manage');
        } elseif (! QtFlow::manages($this->caller(), $section)) {
            $this->allow('qtally.'.strtolower($section).'.manage');
        }
    }

    /** Tapping (or reading the tablet's own lists) in a section. */
    protected function allowTap(?string $section): void
    {
        if ($section === null || ! in_array($section, QtBook::SECTIONS, true)) {
            $this->allow('qtally.tally.create');
        } elseif (! QtFlow::taps($this->caller(), $section)) {
            $this->allow('qtally.'.strtolower($section).'.tally');
        }
    }

    protected function allowViewOrTap(string $section): void
    {
        if (! QtFlow::taps($this->caller(), $section)) {
            $this->allowView($section);
        }
    }

    /** Anyone who works in the module at all. */
    protected function allowAnySection(): void
    {
        if (QtFlow::sections($this->caller()) === []) {
            $this->allow('qtally.view');
        }
    }
}
