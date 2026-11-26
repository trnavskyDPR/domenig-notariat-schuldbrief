<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/**
 * Bereitet die Ausweiskopien für den Mailversand vor – nur im Arbeitsspeicher.
 *
 * Hintergrund: Mit dem Recht «Mail.Send» darf eine Mail über Graph nur rund 3 MB
 * Anhang tragen (grössere bräuchten Entwürfe und damit mehr Rechte). Darum:
 * - Fotos (JPG/PNG) über 1.5 MB werden auf höchstens 2400 px verkleinert und als
 *   JPG gespeichert – für eine Ausweiskopie mehr als genug.
 * - Handyfotos sind oft nur per EXIF-Angabe «gedreht». Beim Neuspeichern geht diese
 *   Angabe verloren, deshalb wird das Bild vorher richtig herum gedreht.
 * - Bilder, die zu viel Arbeitsspeicher bräuchten, werden nicht verkleinert.
 * - Was danach immer noch grösser als MAX_MAIL_BYTES ist (z. B. ein grosses PDF),
 *   wird abgelehnt, bevor etwas gesendet wird.
 */
final class AttachmentPreparer
{
    /** Höchstgrösse eines Anhangs pro Mail (roh, vor Base64), mit Reserve unter der Graph-Grenze. */
    public const MAX_MAIL_BYTES = 2_800_000;
    private const JPEG_QUALITY = 82;

    public function __construct(
        private int $shrinkAbove = 1_500_000,
        private int $maxEdge = 2400,
    ) {
    }

    /** Grenze in ganzen MB für Hinweise und Fehlermeldungen (abgerundet). */
    public static function maxMailMb(): int
    {
        return (int) floor(self::MAX_MAIL_BYTES / 1_000_000);
    }

    /**
     * @param list<Attachment> $attachments
     * @return array{0: list<Attachment>, 1: ?array{0: string, 1: array<string, int>}} vorbereitete Anhänge, Fehler
     */
    public function prepare(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $attachment) {
            if ($attachment->size() > $this->shrinkAbove && in_array($attachment->mime, ['image/jpeg', 'image/png'], true)) {
                $attachment = $this->shrink($attachment) ?? $attachment;
            }
            if ($attachment->size() > self::MAX_MAIL_BYTES) {
                return [[], ['error.ausweis.too_big_mail', ['max' => self::maxMailMb()]]];
            }
            $out[] = $attachment;
        }

        return [$out, null];
    }

    /**
     * Reicht der Arbeitsspeicher, um ein Bild dieser Grösse zu bearbeiten?
     * GD braucht rund 4–5 Bytes pro Pixel für das Original plus das verkleinerte Bild.
     */
    public static function fitsInMemory(int $width, int $height, int $maxEdge = 2400): bool
    {
        $limit = self::memoryLimitBytes();
        if ($limit < 0) {
            return true;
        }
        $needed = ($width * $height + $maxEdge * $maxEdge) * 5 + 16 * 1024 * 1024;

        return memory_get_usage() + $needed < $limit;
    }

    /** Verkleinert ein Foto mit GD. Ohne GD, bei zu grossen Bildern oder Fehlern: null (Original bleibt). */
    private function shrink(Attachment $attachment): ?Attachment
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }
        $info = @getimagesizefromstring($attachment->content);
        if ($info === false || !self::fitsInMemory((int) $info[0], (int) $info[1], $this->maxEdge)) {
            return null;
        }
        $image = @imagecreatefromstring($attachment->content);
        if ($image === false) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $this->maxEdge / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // Weisser Hintergrund, falls ein PNG transparent ist.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        if ($attachment->mime === 'image/jpeg') {
            $canvas = self::applyOrientation($canvas, self::exifOrientation($attachment->content));
        }

        ob_start();
        $ok = imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if (!$ok || $jpeg === '' || strlen($jpeg) >= $attachment->size()) {
            return null;
        }

        // Gleicher neutraler Name, neue Endung: «ausweis-2.png» wird «ausweis-2.jpg».
        return new Attachment((string) preg_replace('/\.[a-z]+$/', '', $attachment->filename) . '.jpg', 'image/jpeg', $jpeg);
    }

    /**
     * Liest die EXIF-Ausrichtung (1–8) aus einem JPEG, ohne Zusatz-Erweiterung.
     * 1 = normal, 3 = 180°, 6 = 90° im Uhrzeigersinn, 8 = 90° gegen den Uhrzeigersinn,
     * 2/4/5/7 = gespiegelt. Bei fehlender oder kaputter Angabe: 1.
     */
    public static function exifOrientation(string $jpeg): int
    {
        $len = strlen($jpeg);
        $pos = 2; // nach SOI (FF D8)
        while ($pos + 4 <= $len && $jpeg[$pos] === "\xFF") {
            $marker = ord($jpeg[$pos + 1]);
            if ($marker === 0xD9 || $marker === 0xDA) {
                break; // Bildende oder Bilddaten: kein EXIF mehr zu erwarten
            }
            $segment = unpack('n', substr($jpeg, $pos + 2, 2))[1];
            if ($marker === 0xE1 && substr($jpeg, $pos + 4, 6) === "Exif\0\0") {
                return self::orientationFromTiff(substr($jpeg, $pos + 10, max(0, $segment - 8)));
            }
            $pos += 2 + $segment;
        }

        return 1;
    }

    private static function orientationFromTiff(string $tiff): int
    {
        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') {
            return 1;
        }
        $le = $order === 'II';
        $u16 = static function (int $offset) use ($tiff, $le): ?int {
            $bytes = substr($tiff, $offset, 2);

            return strlen($bytes) === 2 ? unpack($le ? 'v' : 'n', $bytes)[1] : null;
        };
        $u32 = static function (int $offset) use ($tiff, $le): ?int {
            $bytes = substr($tiff, $offset, 4);

            return strlen($bytes) === 4 ? unpack($le ? 'V' : 'N', $bytes)[1] : null;
        };

        $ifd = $u32(4);
        $count = $ifd === null ? null : $u16($ifd);
        for ($i = 0; $count !== null && $i < min($count, 200); $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value !== null && $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /** Dreht bzw. spiegelt das Bild so, dass es ohne EXIF-Angabe richtig herum steht. */
    private static function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        // imagerotate dreht gegen den Uhrzeigersinn.
        $angle = match ($orientation) {
            3 => 180,
            6, 7 => 270, // 90° im Uhrzeigersinn
            5, 8 => 90,  // 90° gegen den Uhrzeigersinn
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    private static function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
