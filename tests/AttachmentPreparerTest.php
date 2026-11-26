<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Schuldbrief\Order\Attachment;
use Schuldbrief\Order\AttachmentPreparer;

/** Verkleinern grosser Fotos für den Mailversand (Graph-Grenze ~3 MB pro Mail). Aus dem Beglaubigungstool übernommen. */
#[RequiresPhpExtension('gd')]
final class AttachmentPreparerTest extends TestCase
{
    /**
     * JPEG wie vom Handy: Pixel liegen gedreht im Speicher, die EXIF-Angabe sagt, wie herum.
     * Das «richtige» Bild ist 300×200 (quer) mit rotem Feld oben links.
     */
    private static function phoneJpeg(int $orientation, bool $bigEndian = false): string
    {
        $display = imagecreatetruecolor(300, 200);
        imagefill($display, 0, 0, imagecolorallocate($display, 255, 255, 255));
        imagefilledrectangle($display, 0, 0, 59, 39, imagecolorallocate($display, 255, 0, 0));

        // Gespeichertes Bild = Umkehrung dessen, was die Ausrichtung beim Anzeigen macht.
        $stored = match ($orientation) {
            3 => imagerotate($display, 180, 0),
            6 => imagerotate($display, 90, 0),   // Anzeige dreht 90° im Uhrzeigersinn zurück
            8 => imagerotate($display, 270, 0),
            default => $display,
        };
        ob_start();
        imagejpeg($stored, null, 95);
        $jpeg = (string) ob_get_clean();

        $le = !$bigEndian;
        $tiff = ($le ? 'II' : 'MM')
            . pack($le ? 'v' : 'n', 42) . pack($le ? 'V' : 'N', 8)        // Kopf, IFD bei 8
            . pack($le ? 'v' : 'n', 1)                                     // 1 Eintrag
            . pack($le ? 'v' : 'n', 0x0112) . pack($le ? 'v' : 'n', 3)    // Orientation, SHORT
            . pack($le ? 'V' : 'N', 1) . pack($le ? 'v' : 'n', $orientation) . "\0\0"
            . pack($le ? 'V' : 'N', 0);                                    // kein weiteres IFD
        $app1 = "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\0\0" . $tiff;

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    /** @return array<string, array{int, bool}> */
    public static function orientations(): array
    {
        return [
            'normal' => [1, false],
            '180°' => [3, false],
            '90° (häufigste Handy-Lage)' => [6, false],
            '270°' => [8, false],
            '90°, Motorola-Byte-Reihenfolge' => [6, true],
        ];
    }

    #[DataProvider('orientations')]
    public function testPhoneOrientationIsKeptWhenShrinking(int $orientation, bool $bigEndian): void
    {
        $jpeg = self::phoneJpeg($orientation, $bigEndian);
        self::assertSame($orientation, AttachmentPreparer::exifOrientation($jpeg));

        // Schwelle tief setzen, damit auch das kleine Testbild neu gespeichert wird.
        [$out, $error] = (new AttachmentPreparer(shrinkAbove: 100, maxEdge: 150))
            ->prepare([new Attachment('ausweis-1.jpg', 'image/jpeg', $jpeg)]);
        self::assertNull($error);
        $image = imagecreatefromstring($out[0]->content);
        self::assertSame([150, 100], [imagesx($image), imagesy($image)], 'quer wie das Original');
        $rgb = imagecolorat($image, 5, 5);
        self::assertGreaterThan(200, ($rgb >> 16) & 0xFF, 'rot oben links');
        self::assertLessThan(80, ($rgb >> 8) & 0xFF);
        self::assertSame(1, AttachmentPreparer::exifOrientation($out[0]->content), 'keine EXIF-Drehung mehr nötig');
    }

    public function testSmallPhotosStayUntouched(): void
    {
        $jpeg = self::phoneJpeg(6);
        [$out] = (new AttachmentPreparer())->prepare([new Attachment('ausweis-1.jpg', 'image/jpeg', $jpeg)]);
        self::assertSame($jpeg, $out[0]->content, 'Original samt EXIF bleibt erhalten');
    }

    public function testBigPngBecomesSmallJpeg(): void
    {
        // Rauschen lässt sich kaum komprimieren: so entsteht ein grosses PNG wie ein Handy-Screenshot.
        $img = imagecreatetruecolor(3600, 2400);
        for ($i = 0; $i < 600000; $i++) {
            imagesetpixel($img, random_int(0, 3599), random_int(0, 2399), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        self::assertGreaterThan(1_500_000, strlen($png));

        [$out, $error] = (new AttachmentPreparer())->prepare([new Attachment('ausweis-2.png', 'image/png', $png)]);
        self::assertNull($error);
        self::assertSame('ausweis-2.jpg', $out[0]->filename, 'Nummer bleibt, Endung passt zum neuen Format');
        self::assertSame('image/jpeg', $out[0]->mime);
        self::assertLessThan(AttachmentPreparer::MAX_MAIL_BYTES, $out[0]->size());
        self::assertSame(2400, getimagesizefromstring($out[0]->content)[0]);
    }

    public function testPdfOverMailLimitIsRejected(): void
    {
        [, $error] = (new AttachmentPreparer())->prepare([
            new Attachment('ausweis-1.pdf', 'application/pdf', '%PDF-' . str_repeat('x', 1000)),
            new Attachment('ausweis-2.pdf', 'application/pdf', '%PDF-' . str_repeat('x', 3_000_000)),
        ]);
        self::assertSame(['error.ausweis.too_big_mail', ['max' => 2]], $error);
    }

    public function testSeveralSmallFilesPass(): void
    {
        $files = [];
        for ($i = 1; $i <= 6; $i++) {
            $files[] = new Attachment('ausweis-' . $i . '.pdf', 'application/pdf', '%PDF-' . str_repeat('x', 900_000));
        }
        [$out, $error] = (new AttachmentPreparer())->prepare($files);
        self::assertNull($error);
        self::assertCount(6, $out, 'mehrere Eigentümer: Aufteilen auf mehrere Mails folgt beim Versand');
    }

    public function testBrokenExifIsIgnored(): void
    {
        self::assertSame(1, AttachmentPreparer::exifOrientation("\xFF\xD8\xFF\xE1\x00\x10Exif\0\0MM\0*"));
        self::assertSame(1, AttachmentPreparer::exifOrientation("\xFF\xD8"));
        self::assertSame(1, AttachmentPreparer::exifOrientation(str_repeat("\xFF", 50)));
    }

    public function testHugeImagesAreNotDecoded(): void
    {
        $before = ini_set('memory_limit', '256M'); // wie im Docker-Image
        try {
            self::assertFalse(AttachmentPreparer::fitsInMemory(100_000, 100_000));
            self::assertFalse(AttachmentPreparer::fitsInMemory(8000, 6000), '48 Megapixel');
            self::assertTrue(AttachmentPreparer::fitsInMemory(4032, 3024), '12-MP-Handyfoto');
            self::assertTrue(AttachmentPreparer::fitsInMemory(6000, 4000), '24-MP-Kamera');
        } finally {
            ini_set('memory_limit', (string) $before);
        }
    }
}
