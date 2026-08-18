<?php

declare(strict_types=1);

namespace App\Modules\Asset\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a scannable QR as inline SVG (Data Dictionary 5.5).
 *
 * SVG rather than PNG so labels stay sharp at any print size and need no GD
 * or Imagick extension. Error correction level M, because a label on an oily
 * machine frame collects smears and scratches; L would stop scanning first.
 */
class QrCodeRenderer
{
    /**
     * Minimum printed size is 20 mm square (Data Dictionary 5.5). At 96 dpi
     * that is roughly 76 px, so the default rendering leaves headroom.
     */
    private const DEFAULT_SIZE = 220;

    private const QUIET_ZONE_MODULES = 2;

    public function svg(string $payload, int $size = self::DEFAULT_SIZE): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, self::QUIET_ZONE_MODULES),
                new SvgImageBackEnd,
            ),
        );

        return $writer->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M());
    }

    /**
     * SVG with the XML declaration stripped, so it can be embedded inline in a
     * Blade page rather than served as its own document.
     */
    public function inlineSvg(string $payload, int $size = self::DEFAULT_SIZE): string
    {
        $svg = $this->svg($payload, $size);

        return preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
