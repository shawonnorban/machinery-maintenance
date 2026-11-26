<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

/**
 * Turns a file into the parts an AI service reads (see AiClient): a PDF and
 * an image go as they are; a workbook or CSV, a Word document, an e-mail and
 * an Outlook message are read here into text — an e-mail's PDF, image,
 * workbook and Word attachments are read too. Nothing is cut short: a file
 * whose text runs past the limit is refused, not truncated, so the AI never
 * answers from half a document without anyone knowing.
 */
class DocumentParts
{
    /** The most text sent for one file (characters). */
    public const MAX_TEXT = 400_000;

    public const PDF = 'application/pdf';

    public const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const XLS = 'application/vnd.ms-excel';

    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public const DOC = 'application/msword';

    public const EML = 'message/rfc822';

    /** What Outlook's .msg sniffs as, depending on the libmagic in use. */
    public const MSG = ['application/vnd.ms-outlook', 'application/CDFV2', 'application/x-ole-storage'];

    /** Plain text and CSV (CSV usually sniffs as text/plain). */
    public const TEXT = ['text/plain', 'text/csv'];

    /** Every type this reader takes — the extra types the intake accepts on top of the ERP's file rules. */
    public static function readableTypes(): array
    {
        return [self::PDF, ...ClaudeClient::IMAGE_TYPES, self::XLSX, self::XLS, self::DOCX, self::EML, ...self::MSG, ...self::TEXT];
    }

    /** @return list<array{kind: string, text?: string, data?: string, mime?: string}> */
    public function fromBytes(string $bytes, string $mime, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $parts = match (true) {
            $mime === self::PDF => [['kind' => 'pdf', 'data' => $bytes]],
            in_array($mime, ClaudeClient::IMAGE_TYPES, true) => [['kind' => 'image', 'mime' => $mime, 'data' => $bytes]],
            $mime === self::XLSX || $mime === self::XLS || $ext === 'csv' || $mime === 'text/csv' => [$this->text($name, $this->sheet($bytes, $ext ?: 'xlsx', $name))],
            $mime === self::DOCX || $ext === 'docx' => [$this->text($name, $this->docx($bytes, $name))],
            $mime === self::EML || $ext === 'eml' => $this->email($bytes, $name),
            in_array($mime, self::MSG, true) || $ext === 'msg' => [$this->text($name, $this->outlook($bytes))],
            in_array($mime, self::TEXT, true) => [$this->text($name, $this->utf8($bytes))],
            default => throw new AiException(__('aicore.e.unreadable', ['name' => $name])),
        };
        $length = array_sum(array_map(fn ($p) => mb_strlen((string) ($p['text'] ?? '')), $parts));
        if ($length > self::MAX_TEXT) {
            throw new AiException(__('aicore.e.too_long', ['name' => $name]));
        }

        return $parts;
    }

    /** @return array{kind: string, text: string} */
    private function text(string $name, string $text): array
    {
        return ['kind' => 'text', 'text' => "File: {$name}\n\n".trim($text)];
    }

    /** Every sheet of a workbook (or a CSV), row by row, cells separated by " | ". */
    private function sheet(string $bytes, string $ext, string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aisheet').'.'.$ext;
        file_put_contents($path, $bytes);
        try {
            $book = IOFactory::load($path);
            $out = [];
            foreach ($book->getWorksheetIterator() as $sheet) {
                $out[] = '## Sheet: '.$sheet->getTitle();
                foreach ($sheet->toArray(null, true, true, false) as $row) {
                    $cells = array_map(fn ($c) => trim((string) $c), $row);
                    if (implode('', $cells) !== '') {
                        $out[] = rtrim(implode(' | ', $cells), ' |');
                    }
                }
            }

            return implode("\n", $out);
        } catch (Throwable $e) {
            throw new AiException(__('aicore.e.unreadable', ['name' => $name]));
        } finally {
            @unlink($path);
        }
    }

    /** A Word document's paragraphs and table cells. */
    private function docx(string $bytes, string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aidoc');
        file_put_contents($path, $bytes);
        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
                throw new AiException(__('aicore.e.unreadable', ['name' => $name]));
            }
            $zip->close();
            $xml = preg_replace(['#</w:p>#', '#</w:tc>#', '#<w:tab/>#', '#<w:br[^>]*/>#'], ["\n", ' | ', "\t", "\n"], $xml);

            return html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        } finally {
            @unlink($path);
        }
    }

    /** An Outlook .msg: the readable text runs it holds (its attachments are not opened). */
    private function outlook(string $bytes): string
    {
        preg_match_all('/(?:[\x20-\x7E\x0A\x0D\x09]\x00){6,}/', $bytes, $m);
        $runs = array_unique(array_map(fn ($r) => trim((string) mb_convert_encoding($r, 'UTF-8', 'UTF-16LE')), $m[0]));

        return implode("\n", array_filter($runs, fn ($r) => $r !== ''));
    }

    /**
     * An e-mail: who sent it, to whom, when and about what, its text, and
     * whatever readable files it carries.
     *
     * @return list<array{kind: string, text?: string, data?: string, mime?: string}>
     */
    private function email(string $raw, string $name): array
    {
        [$headers, $body] = $this->split($raw);
        $head = [];
        foreach (['from', 'to', 'cc', 'date', 'subject'] as $h) {
            if (isset($headers[$h])) {
                $head[] = ucfirst($h).': '.mb_decode_mimeheader($headers[$h]);
            }
        }
        $texts = [];
        $files = [];
        $this->walk($headers, $body, $texts, $files);
        $parts = [$this->text($name, implode("\n", $head)."\n\n".implode("\n\n", $texts))];
        foreach ($files as $f) {
            try {
                array_push($parts, ...$this->fromBytes($f['data'], $f['mime'], $f['name']));
            } catch (AiException) {
                $parts[] = ['kind' => 'text', 'text' => __('aicore.e.attachment_skipped', ['name' => $f['name']])];
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $texts
     * @param  list<array{name: string, mime: string, data: string}>  $files
     */
    private function walk(array $headers, string $body, array &$texts, array &$files): void
    {
        $type = strtolower(trim(explode(';', $headers['content-type'] ?? 'text/plain')[0]));
        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'] ?? '', $b)) {
            foreach (preg_split('/\r?\n--'.preg_quote($b[1], '/').'(?:--)?\r?\n?/', "\n".$body) ?: [] as $chunk) {
                if (trim($chunk) === '' || trim($chunk) === '--') {
                    continue;
                }
                [$h, $c] = $this->split(ltrim($chunk, "\r\n"));
                $this->walk($h, $c, $texts, $files);
            }

            return;
        }
        $data = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $filename = null;
        if (preg_match('/(?:filename|name)\*?="?([^";\r\n]+)"?/i', ($headers['content-disposition'] ?? '').';'.($headers['content-type'] ?? ''), $f)) {
            $filename = mb_decode_mimeheader(trim($f[1]));
        }
        // Anything with a file name is an attachment (a CSV too), read as a file of its own.
        if ($filename !== null) {
            $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: $type;
            $files[] = ['name' => $filename, 'mime' => $sniffed === 'application/octet-stream' ? $type : $sniffed, 'data' => $data];

            return;
        }
        if ($type === 'text/plain') {
            $texts[] = $this->utf8($data, $headers['content-type'] ?? '');
        } elseif ($type === 'text/html' && $texts === []) {
            $texts[] = trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/div|/tr)[^>]*>#i', "\n", $this->utf8($data, $headers['content-type'] ?? '')) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    /** @return array{0: array<string, string>, 1: string} */
    private function split(string $raw): array
    {
        $at = preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($raw);
        $head = substr($raw, 0, $at);
        $body = (string) substr($raw, $at + (isset($m[0][0]) ? strlen($m[0][0]) : 0));
        $headers = [];
        foreach (preg_split('/\r?\n(?![ \t])/', $head) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim(preg_replace('/\r?\n[ \t]+/', ' ', $v) ?? '');
            }
        }

        return [$headers, $body];
    }

    private function utf8(string $data, string $contentType = ''): string
    {
        $charset = preg_match('/charset="?([^";]+)"?/i', $contentType, $c) ? $c[1] : null;
        if ($charset && strtolower($charset) !== 'utf-8') {
            return (string) mb_convert_encoding($data, 'UTF-8', $charset);
        }

        return mb_check_encoding($data, 'UTF-8') ? $data : (string) mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
    }
}
