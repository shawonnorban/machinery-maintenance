<?php

declare(strict_types=1);

namespace App\Shared\Files\Support;

use App\Shared\Files\Models\FileAttachment;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Small pictures for a list (user, 2026-11-24): the artwork column of Order
 * Summary and the requisition sheet showed each picture only after its own
 * two requests (the permission check, then the signed download), one after
 * another — a page of fifty rows filled in slowly. A list now carries each
 * row's picture inline as a data URI, so the pictures are there with the
 * page; a click still opens the full picture.
 *
 * A small picture (most artwork is a few KB) goes as it is. A bigger one is
 * shrunk to 160 px on its longest side, once, and kept beside the file — as
 * PNG: this server's GD reads and writes PNG, GIF and BMP but has no JPEG or
 * WebP support, so a big JPEG it cannot read is left to load on its own.
 */
final class Thumbnails
{
    public const SIZE = 160;

    /** A picture up to this size goes inline as it is. */
    public const AS_IS_BYTES = 40 * 1024;

    /** The most a list carries inline (beyond, the cells load the pictures themselves). */
    public const MAX_ROWS = 300;

    /**
     * @param  list<string|null>  $ids  file ids (blanks and repeats are fine)
     * @return array<string, string> file id => data URI, for the pictures that could be read
     */
    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && strlen($id) === 26)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (FileAttachment::query()->whereIn('id', $ids)->get(['id', 'company_id', 'disk', 'path', 'mime_type', 'size_bytes', 'scan_status']) as $file) {
            if (! str_starts_with((string) $file->mime_type, 'image/') || ! $file->isDownloadable()) {
                continue;
            }
            $uri = self::uri($file);
            if ($uri !== null) {
                $out[$file->id] = $uri;
            }
        }

        return $out;
    }

    private static function uri(FileAttachment $file): ?string
    {
        $disk = Storage::disk($file->disk);
        try {
            if ((int) $file->size_bytes > 0 && (int) $file->size_bytes <= self::AS_IS_BYTES) {
                return $disk->exists($file->path) ? 'data:'.$file->mime_type.';base64,'.base64_encode((string) $disk->get($file->path)) : null;
            }
            $png = self::shrunk($file);

            return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
        } catch (Throwable) {
            return null;
        }
    }

    /** A big picture shrunk: made once, then read from where it was kept; null when GD cannot read it. */
    private static function shrunk(FileAttachment $file): ?string
    {
        $disk = Storage::disk($file->disk);
        $kept = 'thumbs/'.($file->company_id ?: 'shared').'/'.$file->id.'-'.self::SIZE.'.png';
        if ($disk->exists($kept)) {
            return $disk->get($kept);
        }
        if (! function_exists('imagecreatefromstring') || ! $disk->exists($file->path)) {
            return null;
        }
        $src = @imagecreatefromstring((string) $disk->get($file->path));
        if ($src === false) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::SIZE / max($w, $h, 1));
        [$tw, $th] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // a transparent PNG on white, as the sheet shows it
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        imagepng($dst, null, 9);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        $disk->put($kept, $bytes);

        return $bytes;
    }
}
