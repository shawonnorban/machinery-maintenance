<?php

declare(strict_types=1);

/*
 * docs/12-Stack-Migration-Implementation-Plan.md Phase C §6: an automated
 * extraction from lang/{locale}/*.php into next-intl JSON namespaces, so
 * drift between the two is diffable instead of hand-copied. Run again
 * whenever lang/en or lang/bn changes; the output is fully regenerated,
 * never hand-edited.
 *
 * php scripts/extract-i18n.php
 */

$root = dirname(__DIR__);
$locales = ['en', 'bn'];
$outputDir = $root.'/frontend/messages';

/*
 * Values keep Laravel's own `:placeholder` interpolation syntax exactly as
 * written (not converted to next-intl's ICU `{placeholder}` — see
 * frontend/src/lib/i18n.js for why). A blind syntactic rewrite is not safe:
 * lang/en/import.php's `datetime_format` string contains the literal
 * "HH:MM", which reads as a colon-placeholder but is not one — there is no
 * way to tell the two apart without cross-referencing every __()/trans()
 * call site's parameter names, which this script does not attempt. Zero
 * characters change here on purpose.
 */
foreach ($locales as $locale) {
    $sourceDir = $root.'/lang/'.$locale;
    $files = glob($sourceDir.'/*.php');
    sort($files);

    $targetDir = $outputDir.'/'.$locale;
    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $merged = [];

    foreach ($files as $file) {
        $namespace = basename($file, '.php');
        $messages = require $file;
        $merged[$namespace] = $messages;

        $json = json_encode(
            $messages,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        file_put_contents($targetDir.'/'.$namespace.'.json', $json."\n");
    }

    // The single file next-intl actually loads per locale (see
    // frontend/src/i18n/request.js) — namespaced files above exist so a
    // diff against lang/{locale}/*.php stays one-to-one and reviewable.
    file_put_contents(
        $outputDir.'/'.$locale.'.json',
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
    );

    echo sprintf("Wrote %d namespaces for '%s' to %s (+ %s.json)\n", count($files), $locale, $targetDir, $locale);
}
