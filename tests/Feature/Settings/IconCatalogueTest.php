<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Tests\TestCase;

/**
 * Every icon a screen asks for has to exist (Frontend 3).
 *
 * CoreUI's free set does not contain every name in its paid one, and a class
 * that is not in the font produces no glyph and no error — the button renders
 * as a coloured square with nothing in it. That is invisible in a test that
 * only asserts a 200, invisible in review, and obvious to the first person who
 * opens the screen. So the names are checked against the stylesheet instead.
 */
class IconCatalogueTest extends TestCase
{
    private const ICON_CSS = 'node_modules/@coreui/icons/css/free.css';

    public function test_every_icon_used_in_a_view_exists_in_the_icon_font(): void
    {
        $cssPath = base_path(self::ICON_CSS);

        if (! is_file($cssPath)) {
            // A backend-only checkout has no node_modules. Skipping is honest;
            // asserting against a file that is not there is not.
            $this->markTestSkipped('CoreUI icon stylesheet is not installed.');
        }

        preg_match_all('/\.(ci[lfb]-[a-z0-9-]+):before/', (string) file_get_contents($cssPath), $found);

        $available = array_flip($found[1]);

        $missing = [];

        foreach ($this->iconsUsed() as $icon => $files) {
            if (! isset($available[$icon])) {
                $missing[$icon] = $files;
            }
        }

        $this->assertSame([], $missing, $this->describe($missing));
    }

    /**
     * @return array<string, list<string>> icon => files that ask for it
     */
    private function iconsUsed(): array
    {
        $used = [];

        foreach ($this->viewFiles() as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all('/\b(ci[lfb]-[a-z0-9-]+)\b/', $contents, $matches) === 0) {
                continue;
            }

            foreach (array_unique($matches[1]) as $icon) {
                $used[$icon][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        return $used;
    }

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $files = [];

        // The sidebar names its icons in PHP rather than in a template, so the
        // navigation menu is scanned as well as the views.
        foreach ([app_path('Modules'), app_path('Shared'), resource_path('views')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * @param  array<string, list<string>>  $missing
     */
    private function describe(array $missing): string
    {
        $lines = [];

        foreach ($missing as $icon => $files) {
            $lines[] = sprintf('%s is used in %s but has no glyph', $icon, implode(', ', $files));
        }

        return implode("\n", $lines);
    }
}
