<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Renders the downloadable "navy hero" profile card (portrait 1080x1350) that
 * professionals and visitors share. Pure GD; fonts bundled under resources/fonts.
 *
 * Deliberately carries no fee or pricing — the card is an identity/booking
 * card, not a quote, and a shared image can go stale if a fee changes.
 *
 * $data keys: name, subtitle, hospital, verified (bool), slug, photo (binary string|null).
 */
class ShareCardRenderer
{
    private const W = 1080;
    private const H = 1350;
    private const CENTER = 540;

    private $im;
    private array $font;
    private array $c = [];

    public static function render(array $data): string
    {
        return (new self())->draw($data);
    }

    private function draw(array $data): string
    {
        $base = base_path('resources/fonts');
        $this->font = [
            'serif' => "$base/EBGaramond-SemiBold.ttf",
            'sans'  => "$base/IBMPlexSans-Regular.ttf",
            'sansb' => "$base/IBMPlexSans-SemiBold.ttf",
            'mono'  => "$base/IBMPlexMono-Regular.ttf",
        ];

        $this->im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($this->im, true);
        imageantialias($this->im, true);

        $this->c = [
            'navy'    => imagecolorallocate($this->im, 18, 33, 63),    // #12213F
            'white'   => imagecolorallocate($this->im, 255, 255, 255),
            'ink'     => imagecolorallocate($this->im, 16, 25, 58),    // #10193A
            'signal'  => imagecolorallocate($this->im, 0, 85, 170),    // #0055AA
            'subtle'  => imagecolorallocate($this->im, 157, 180, 217), // #9db4d9
            'muted'   => imagecolorallocate($this->im, 127, 147, 187), // #7f93bb
            'label'   => imagecolorallocate($this->im, 142, 166, 206), // #8ea6ce
            'teal'    => imagecolorallocate($this->im, 15, 118, 110),  // #0F766E
            'tealtxt' => imagecolorallocate($this->im, 143, 227, 211), // #8fe3d3
        ];

        imagefilledrectangle($this->im, 0, 0, self::W, self::H, $this->c['navy']);

        // Masthead
        $this->text('Phoenix', 'serif', 54, 90, 150, $this->c['white']);
        if (!empty($data['verified'])) {
            $tag = 'VERIFIED';
            $w = $this->width($tag, 'mono', 26, 4);
            imagefilledellipse($this->im, 990 - $w - 28, 134, 14, 14, $this->c['tealtxt']);
            $this->text($tag, 'mono', 26, 990 - $w, 144, $this->c['label'], 4);
        }

        // Portrait
        $this->portrait($data['photo'] ?? null, self::CENTER, 470, 195, !empty($data['verified']));

        // Name (shrink to fit)
        $name = trim($data['name'] ?? 'Phoenix professional');
        $size = $this->fit($name, 'serif', 74, 900, 44);
        $this->centered($name, 'serif', $size, 800, $this->c['white']);

        $y = 862;
        if (!empty($data['subtitle'])) {
            $this->centered($data['subtitle'], 'sans', 38, $y, $this->c['subtle']);
            $y += 50;
        }
        if (!empty($data['hospital'])) {
            $this->centered($data['hospital'], 'sans', 30, $y, $this->c['muted']);
        }

        // Divider
        imagesetthickness($this->im, 2);
        $line = imagecolorallocatealpha($this->im, 255, 255, 255, 110);
        imageline($this->im, 140, 1040, 940, 1040, $line);

        // QR strip
        $this->roundedRect(90, 1170, 990, 1300, 22, $this->c['white']);
        $qr = $this->qr('https://phoenixmed.online/' . ($data['slug'] ?? ''));
        if ($qr) {
            imagecopyresampled($this->im, $qr, 122, 1190, 0, 0, 90, 90, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
        $this->text('Scan to book a consultation', 'sansb', 33, 250, 1232, $this->c['ink']);
        $this->text('phoenixmed.online/' . ($data['slug'] ?? ''), 'mono', 27, 250, 1278, $this->c['signal']);

        ob_start();
        imagepng($this->im);
        $png = ob_get_clean();
        imagedestroy($this->im);
        return $png;
    }

    // --- drawing helpers ---------------------------------------------------

    private function text(string $s, string $font, int $size, int $x, int $y, int $color, float $spacing = 0): void
    {
        if ($spacing <= 0) {
            imagettftext($this->im, $size, 0, $x, $y, $color, $this->font[$font], $s);
            return;
        }
        // Manual letter-spacing for small-caps labels.
        foreach (mb_str_split($s) as $ch) {
            imagettftext($this->im, $size, 0, $x, $y, $color, $this->font[$font], $ch);
            $x += $this->width($ch, $font, $size) + $spacing;
        }
    }

    private function width(string $s, string $font, int $size, float $spacing = 0): int
    {
        if ($spacing > 0) {
            $w = 0;
            foreach (mb_str_split($s) as $ch) {
                $w += $this->width($ch, $font, $size) + $spacing;
            }
            return (int) ($w - $spacing);
        }
        $b = imagettfbbox($size, 0, $this->font[$font], $s);
        return (int) ($b[2] - $b[0]);
    }

    private function centered(string $s, string $font, int $size, int $y, int $color, float $spacing = 0): void
    {
        $x = (int) (self::CENTER - $this->width($s, $font, $size, $spacing) / 2);
        $this->text($s, $font, $size, $x, $y, $color, $spacing);
    }

    private function fit(string $s, string $font, int $size, int $maxWidth, int $min): int
    {
        while ($size > $min && $this->width($s, $font, $size) > $maxWidth) {
            $size -= 2;
        }
        return $size;
    }

    private function roundedRect(int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        imagefilledrectangle($this->im, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledrectangle($this->im, $x0, $y0 + $r, $x1, $y1 - $r, $color);
        imagefilledellipse($this->im, $x0 + $r, $y0 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x1 - $r, $y0 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x0 + $r, $y1 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x1 - $r, $y1 - $r, $r * 2, $r * 2, $color);
    }

    private function portrait(?string $bytes, int $cx, int $cy, int $r, bool $verified): void
    {
        imagefilledellipse($this->im, $cx, $cy, $r * 2 + 16, $r * 2 + 16, $this->c['white']);

        $photo = $bytes ? @imagecreatefromstring($bytes) : null;
        if ($photo) {
            $pw = imagesx($photo); $ph = imagesy($photo);
            $side = min($pw, $ph);
            $d = $r * 2;
            $sq = imagecreatetruecolor($d, $d);
            imagecopyresampled($sq, $photo, 0, 0, (int) (($pw - $side) / 2), (int) (($ph - $side) / 2), $d, $d, $side, $side);
            for ($yy = 0; $yy < $d; $yy++) {
                for ($xx = 0; $xx < $d; $xx++) {
                    if (($xx - $r) ** 2 + ($yy - $r) ** 2 <= $r * $r) {
                        imagesetpixel($this->im, $cx - $r + $xx, $cy - $r + $yy, imagecolorat($sq, $xx, $yy));
                    }
                }
            }
            imagedestroy($sq);
            imagedestroy($photo);
        } else {
            imagefilledellipse($this->im, $cx, $cy, $r * 2, $r * 2, imagecolorallocate($this->im, 36, 58, 104));
        }

        if ($verified) {
            $bx = (int) ($cx + $r * 0.70);
            $by = (int) ($cy + $r * 0.70);
            imagefilledellipse($this->im, $bx, $by, 92, 92, $this->c['navy']);
            imagefilledellipse($this->im, $bx, $by, 72, 72, $this->c['teal']);
            imagesetthickness($this->im, 7);
            imageline($this->im, $bx - 16, $by + 2, $bx - 4, $by + 15, $this->c['white']);
            imageline($this->im, $bx - 4, $by + 15, $bx + 18, $by - 13, $this->c['white']);
        }
    }

    private function qr(string $url)
    {
        try {
            $opt = new QROptions([
                'version'    => 5,
                'scale'      => 12,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'imageBase64' => false,
                'eccLevel'   => QRCode::ECC_M,
                'addQuietzone' => true,
            ]);
            return @imagecreatefromstring((new QRCode($opt))->render($url));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
