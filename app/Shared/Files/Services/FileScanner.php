<?php

declare(strict_types=1);

namespace App\Shared\Files\Services;

use App\Shared\Files\Models\FileAttachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Checks an uploaded file before it can be downloaded (API 19.1 rule 3).
 *
 * Deliberately not a bundled scanner. Nothing in a PHP application can inspect
 * a file for malware credibly; what it can do is hand the file to something
 * that can, and refuse to serve it until an answer comes back. So this shells
 * out to whatever the deployment has — `clamdscan` by default — and the
 * important part is not the scanner but the default when there is none.
 *
 * That default is the whole design. `files.scan.enabled` off means every
 * upload is recorded as SKIPPED and stays downloadable: a factory with no
 * scanner keeps working, and the audit trail says plainly that these files
 * were never checked. Turned on with no scanner installed, uploads stay
 * PENDING and refuse to download — which is inconvenient and correct, because
 * the alternative is a setting that claims a check nobody performed.
 */
class FileScanner
{
    /**
     * A scan is quick or it is broken. A minute of a queue worker held open by
     * a wedged scanner is a minute it is not delivering notifications.
     */
    private const TIMEOUT_SECONDS = 30;

    public function enabled(): bool
    {
        return (bool) config('files.scan.enabled');
    }

    /**
     * Look at one file and record what was found.
     *
     * Never throws. A scanner that is missing, misconfigured or hung must not
     * lose the upload — the file stays PENDING and undownloadable, which is a
     * state somebody can investigate, rather than an exception that leaves the
     * row in whatever state it happened to be in.
     */
    public function scan(FileAttachment $attachment): string
    {
        if (! $this->enabled()) {
            return $this->record($attachment, 'SKIPPED', 'scanning disabled');
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            return $this->record($attachment, 'INFECTED', 'file missing at scan time');
        }

        // Checked before running, not inferred afterwards. A scanner's exit
        // code 1 means "found something", and a missing binary also exits
        // non-zero — on Windows with exactly 1. Reading that as a virus would
        // reject every upload on a machine where scanning was switched on and
        // nothing installed, and blame the file for it.
        if (! $this->scannerAvailable()) {
            Log::warning('File scanning is enabled but the scanner is not installed', [
                'command' => config('files.scan.command'),
            ]);

            return 'PENDING';
        }

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
                ...config('files.scan.command'),
                $disk->path($attachment->path),
            ]);
        } catch (Throwable $e) {
            // Left PENDING on purpose. The file is not downloadable and the
            // reason is in the log; calling it clean because the scanner fell
            // over is the one outcome that must not happen.
            Log::warning('File scan could not run', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);

            return 'PENDING';
        }

        // ClamAV's convention, which most scanners follow: 0 clean, 1 found
        // something, anything else is the scanner itself failing.
        return match ($result->exitCode()) {
            0 => $this->record($attachment, 'CLEAN', 'clean'),
            1 => $this->record($attachment, 'INFECTED', $this->firstLine($result->output())),
            default => $this->pendingAfterFailure($attachment, $result->errorOutput()),
        };
    }

    /**
     * Delete the stored bytes of an infected file, keeping the row.
     *
     * The row is the record that somebody uploaded something harmful, which is
     * worth keeping; the bytes are not worth keeping anywhere.
     */
    public function quarantine(FileAttachment $attachment): void
    {
        if ($attachment->scan_status !== 'INFECTED') {
            return;
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
    }

    /**
     * Is the configured scanner actually on this machine?
     *
     * There is no portable `which` in PHP, so PATH is searched the way the
     * shell would — including PATHEXT on Windows, where a command is `.exe`
     * without anybody writing the extension.
     */
    private function scannerAvailable(): bool
    {
        $command = config('files.scan.command');
        $binary = is_array($command) ? ($command[0] ?? '') : (string) $command;

        if ($binary === '') {
            return false;
        }

        // An absolute or relative path is taken at its word.
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary) && is_executable($binary);
        }

        $extensions = windows_os()
            ? explode(';', (string) (getenv('PATHEXT') ?: '.EXE;.BAT;.CMD'))
            : [''];

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$binary.$extension;

                if (is_file($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pendingAfterFailure(FileAttachment $attachment, string $error): string
    {
        Log::warning('File scanner returned an unexpected status', [
            'attachment_id' => $attachment->id,
            'error' => $this->firstLine($error),
        ]);

        return 'PENDING';
    }

    private function record(FileAttachment $attachment, string $status, string $result): string
    {
        $attachment->forceFill([
            'scan_status' => $status,
            'scanned_at' => Carbon::now(),
            'scan_result' => mb_substr($result, 0, 255),
        ])->save();

        return $status;
    }

    private function firstLine(string $output): string
    {
        $line = strtok(trim($output), "\n");

        return $line === false ? '' : $line;
    }
}
