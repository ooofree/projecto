<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

/**
 * Share-card thumbnail for chat clients.
 *
 * WeChat/QQ build a link card from the page's og:image, so the share needs a
 * real, fetchable picture. This renders a 600x600 PNG in the site's own visual
 * language (deep site green, blueprint grid, safety-amber rule, hard-hat mark)
 * carrying the project name and the crew/trade counts — no phone numbers, no
 * personal data. It is generated once per data change and cached next to the
 * static HTML, outside the web root.
 *
 * GD is optional: without it the caller simply omits og:image and the card
 * still shows title + description.
 */
final class CrewShareImage
{
    private const SIZE = 600;

    /** CJK-capable font candidates; first readable one wins. */
    private const FONTS = [
        '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJKsc-Regular.otf',
        '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
        '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
        '/usr/share/fonts/truetype/arphic/uming.ttc',
    ];

    /** Latin/number font: CJK fallback fonts often lack ASCII glyphs. */
    private const LATIN_FONTS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    ];

    public static function isSupported(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    private static function latinFontPath(): ?string
    {
        foreach (self::LATIN_FONTS as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Draw mixed CJK/Latin text: each run uses a font that actually has the
     * glyphs, so numbers never render as tofu boxes.
     *
     * @return int x position after the drawn text
     */
    private static function drawMixed($image, int $size, int $x, int $y, int $color, string $cjkFont, ?string $latinFont, string $text): int
    {
        if ($text === '') {
            return $x;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $run = '';
        $runIsLatin = null;
        $flush = function () use (&$run, &$runIsLatin, &$x, $image, $size, $y, $color, $cjkFont, $latinFont): void {
            if ($run === '') {
                return;
            }
            $font = ($runIsLatin && $latinFont !== null) ? $latinFont : $cjkFont;
            imagettftext($image, $size, 0, $x, $y, $color, $font, $run);
            $box = imagettfbbox($size, 0, $font, $run);
            if ($box !== false) {
                $x += (int) round(abs($box[2] - $box[0]));
            }
            $run = '';
        };
        foreach ($chars as $char) {
            // Prefer the CJK font when it actually has the glyph; otherwise use
            // the Latin font (covers digits and punctuation such as "·" that
            // many CJK fallback fonts lack). This avoids tofu boxes.
            $isLatin = $latinFont !== null && !self::fontHasGlyph($cjkFont, $size, $char);
            if ($runIsLatin !== null && $isLatin !== $runIsLatin) {
                $flush();
            }
            $runIsLatin = $isLatin;
            $run .= $char;
        }
        $flush();

        return $x;
    }

    /**
     * A missing glyph renders as a box whose width matches the font's notdef
     * box; comparing against a known-missing codepoint detects that reliably.
     */
    private static function fontHasGlyph(string $font, int $size, string $char): bool
    {
        static $cache = [];
        $key = $font . ':' . $size . ':' . $char;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $box = imagettfbbox($size, 0, $font, $char);
        $notdef = imagettfbbox($size, 0, $font, "\u{FFFF}");
        if ($box === false || $notdef === false) {
            return $cache[$key] = true;
        }
        $width = abs($box[2] - $box[0]);
        $height = abs($box[7] - $box[1]);
        $notdefWidth = abs($notdef[2] - $notdef[0]);
        $notdefHeight = abs($notdef[7] - $notdef[1]);
        // Whitespace has no ink but is still valid.
        if (trim($char) === '') {
            return $cache[$key] = true;
        }

        return $cache[$key] = !($width === $notdefWidth && $height === $notdefHeight);
    }

    public static function fontPath(): ?string
    {
        foreach (self::FONTS as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $project
     * @return string|null PNG bytes, or null when GD/font is unavailable
     */
    public static function render(array $project, int $crewCount, int $tradeCount): ?string
    {
        if (!self::isSupported()) {
            return null;
        }
        $font = self::fontPath();
        $size = self::SIZE;
        $image = imagecreatetruecolor($size, $size);
        if ($image === false) {
            return null;
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Deep site-green field with a vertical gradient into teal.
        $top = [18, 53, 39];
        $bottom = [31, 93, 82];
        for ($y = 0; $y < $size; $y++) {
            $t = $y / ($size - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $t)
            );
            imageline($image, 0, $y, $size, $y, $color);
        }

        // Blueprint grid, echoing the page hero.
        $grid = imagecolorallocatealpha($image, 255, 255, 255, 118);
        for ($x = 0; $x <= $size; $x += 40) {
            imageline($image, $x, 0, $x, $size, $grid);
        }
        for ($y = 0; $y <= $size; $y += 40) {
            imageline($image, 0, $y, $size, $y, $grid);
        }
        // Soft corner rings for depth.
        $ring = imagecolorallocatealpha($image, 255, 255, 255, 112);
        imagesetthickness($image, 2);
        imagearc($image, 540, 60, 300, 300, 0, 360, $ring);
        imagearc($image, 540, 60, 420, 420, 0, 360, $ring);
        imagesetthickness($image, 1);

        $white = imagecolorallocate($image, 255, 255, 255);
        $mint = imagecolorallocate($image, 169, 203, 182);
        $amber = imagecolorallocate($image, 245, 166, 35);

        // Hard-hat mark: dome + brim, drawn as filled shapes.
        $hatX = 62;
        $hatY = 96;
        imagefilledarc($image, $hatX + 46, $hatY + 44, 92, 84, 180, 360, $amber, IMG_ARC_PIE);
        imagefilledrectangle($image, $hatX + 34, $hatY + 2, $hatX + 58, $hatY + 24, $amber);
        imagefilledrectangle($image, $hatX, $hatY + 44, $hatX + 92, $hatY + 58, $amber);
        imagefilledrectangle($image, $hatX + 8, $hatY + 62, $hatX + 84, $hatY + 68, $white);

        $latin = self::latinFontPath();
        if ($font !== null) {
            self::drawMixed($image, 22, 62, 214, $mint, $font, $latin, '施工班组 · 公开联系页');

            // Project name, wrapped to at most two lines.
            $name = (string) ($project['project_name'] ?? '');
            $lines = self::wrap($name, $font, 46, $size - 124, 2);
            $y = 286;
            foreach ($lines as $line) {
                self::drawMixed($image, 46, 62, $y, $white, $font, $latin, $line);
                $y += 62;
            }

            // Counts, the useful part of the card.
            $countText = $crewCount . ' 个班组 · ' . $tradeCount . ' 个工种';
            self::drawMixed($image, 27, 62, $y + 22, $mint, $font, $latin, $countText);
        }

        // Safety-amber rule and the dial affordance.
        imagefilledrectangle($image, 62, 470, 210, 478, $amber);
        if ($font !== null) {
            self::drawMixed($image, 26, 62, 534, $white, $font, $latin, '点击卡片直接拨号');
        }

        // Handset badge, bottom-right. The classic receiver silhouette is drawn
        // as one filled polygon (two ear pieces joined by a curved bridge), so
        // it reads clearly at chat-card thumbnail size.
        $badge = imagecolorallocate($image, 52, 120, 85);
        imagefilledellipse($image, 508, 508, 116, 116, $badge);
        $handset = [
            478, 470, 496, 470, 504, 492, 494, 502,
            500, 516, 514, 530, 528, 536, 538, 526,
            560, 534, 560, 552, 542, 560, 520, 556,
            498, 542, 482, 520, 472, 496, 470, 480,
        ];
        imagefilledpolygon($image, $handset, $white);

        ob_start();
        imagepng($image, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /**
     * Greedy wrap by measured width; the last kept line gets an ellipsis when
     * the text does not fit, so a long project name never overflows the card.
     *
     * @return array<int, string>
     */
    private static function wrap(string $text, string $font, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['班组联系人'];
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';
        foreach ($chars as $char) {
            $candidate = $current . $char;
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $width = $box === false ? 0 : abs($box[2] - $box[0]);
            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $char;
                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }
        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
        }
        if ($lines === []) {
            $lines = ['班组联系人'];
        }
        // Ellipsize the final line when characters remain unrendered.
        $rendered = mb_strlen(implode('', $lines), 'UTF-8');
        if ($rendered < mb_strlen($text, 'UTF-8')) {
            $last = $lines[count($lines) - 1];
            while ($last !== '') {
                $candidate = mb_substr($last, 0, mb_strlen($last, 'UTF-8') - 1, 'UTF-8') . '…';
                $box = imagettfbbox($fontSize, 0, $font, $candidate);
                if ($box !== false && abs($box[2] - $box[0]) <= $maxWidth) {
                    $lines[count($lines) - 1] = $candidate;
                    break;
                }
                $last = mb_substr($last, 0, mb_strlen($last, 'UTF-8') - 1, 'UTF-8');
            }
        }

        return $lines;
    }
}
