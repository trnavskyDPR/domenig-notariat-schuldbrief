<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use Schuldbrief\Order\UploadValidator;
use PHPUnit\Framework\TestCase;

final class UploadValidatorTest extends TestCase
{
    /** Tests: die Dateien stammen nicht aus einem echten HTTP-Upload, is_uploaded_file() würde sie ablehnen. */
    private static function validator(int $maxBytes = 5 * 1048576, int $maxFiles = 2, bool $uploaded = true): UploadValidator
    {
        return new UploadValidator($maxBytes, $maxFiles, static fn (string $tmp): bool => $uploaded);
    }

    public function testAcceptsJpegPngAndPdfAndUsesNeutralNames(): void
    {
        $files = UploadFixtures::files([UploadFixtures::jpeg(), UploadFixtures::png()]);
        [$attachments, $error] = self::validator()->check($files);
        self::assertNull($error);
        self::assertCount(2, $attachments);
        self::assertSame(['ausweis-1.jpg', 'ausweis-2.png'], [$attachments[0]->filename, $attachments[1]->filename]);
        self::assertSame(['image/jpeg', 'image/png'], [$attachments[0]->mime, $attachments[1]->mime]);
        self::assertSame(UploadFixtures::jpeg(), $attachments[0]->content);
        foreach ($attachments as $a) {
            self::assertStringNotContainsString('Muster', $a->filename, 'Originalname der Datei wird nicht übernommen');
        }

        [$pdf, $pdfError] = self::validator()->check(UploadFixtures::files([UploadFixtures::pdf()]));
        self::assertNull($pdfError);
        self::assertSame(['ausweis-1.pdf', 'application/pdf'], [$pdf[0]->filename, $pdf[0]->mime]);
    }

    public function testTemporaryFilesAreDeletedImmediately(): void
    {
        $files = UploadFixtures::files([UploadFixtures::png(), 'kein Bild']);
        self::validator()->check($files);
        foreach ($files['tmp_name'] as $tmp) {
            self::assertFileDoesNotExist($tmp);
        }
    }

    public function testNoFileIsRequiredError(): void
    {
        self::assertSame(['error.ausweis.required', []], self::validator()->check(null)[1]);
        self::assertSame(['error.ausweis.required', []], self::validator()->check([])[1]);
        self::assertSame(['error.ausweis.required', []], self::validator()->check(UploadFixtures::files([null]))[1]);
        self::assertSame(['error.ausweis.required', []], self::validator()->check('ausweis')[1]);
    }

    public function testTooManyFiles(): void
    {
        $files = UploadFixtures::files([UploadFixtures::png(), UploadFixtures::png(), UploadFixtures::png()]);
        [$attachments, $error] = self::validator()->check($files);
        self::assertSame([], $attachments);
        self::assertSame(['error.ausweis.too_many', ['max' => 2]], $error);
        foreach ($files['tmp_name'] as $tmp) {
            self::assertFileDoesNotExist($tmp);
        }
    }

    public function testSixFilesForSeveralOwners(): void
    {
        $contents = [UploadFixtures::png(), UploadFixtures::jpeg(), UploadFixtures::pdf(), UploadFixtures::png(), UploadFixtures::jpeg(), UploadFixtures::pdf()];
        [$attachments, $error] = self::validator(maxFiles: 6)->check(UploadFixtures::files($contents));
        self::assertNull($error);
        self::assertSame(
            ['ausweis-1.png', 'ausweis-2.jpg', 'ausweis-3.pdf', 'ausweis-4.png', 'ausweis-5.jpg', 'ausweis-6.pdf'],
            array_map(static fn ($a): string => $a->filename, $attachments),
        );
    }

    public function testTooBig(): void
    {
        $big = UploadFixtures::png() . str_repeat('0', 2 * 1048576);
        [, $error] = self::validator(1048576)->check(UploadFixtures::files([$big]));
        self::assertSame(['error.ausweis.too_big', ['max' => 1]], $error);
    }

    public function testPhpSizeLimitErrorsAreTooBig(): void
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE] as $code) {
            $files = UploadFixtures::files([UploadFixtures::png()]);
            $files['error'] = [$code];
            self::assertSame('error.ausweis.too_big', self::validator()->check($files)[1][0], (string) $code);
        }
    }

    public function testOtherUploadErrorsAreFailed(): void
    {
        $files = UploadFixtures::files([UploadFixtures::png()]);
        $files['error'] = [UPLOAD_ERR_PARTIAL];
        self::assertSame('error.ausweis.failed', self::validator()->check($files)[1][0]);
    }

    public function testFileThatWasNotUploadedIsRejected(): void
    {
        $files = UploadFixtures::files([UploadFixtures::png()]);
        [$attachments, $error] = self::validator(uploaded: false)->check($files);
        self::assertSame([], $attachments);
        self::assertSame('error.ausweis.failed', $error[0]);
        self::assertFileExists($files['tmp_name'][0], 'Eine Datei, die nicht aus dem Upload stammt, wird nie angefasst');
        unlink($files['tmp_name'][0]);
    }

    public function testTypeIsDetectedByContentNotByNameOrBrowserType(): void
    {
        // Browser meldet «image/jpeg» und «.jpg» (siehe UploadFixtures) – der Inhalt ist aber Text, HTML, GIF, HEIC …
        $others = [
            'text' => 'Das ist kein Bild.',
            'html' => '<html><script>alert(1)</script></html>',
            'gif' => "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;",
            'heic' => "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic",
            'zip' => "PK\x03\x04" . str_repeat("\0", 30),
        ];
        foreach ($others as $name => $content) {
            self::assertSame('error.ausweis.type', self::validator()->check(UploadFixtures::files([$content]))[1][0] ?? null, $name);
        }
    }

    public function testImagesMustBeReadableAndEmptyFilesFail(): void
    {
        // Richtiger Anfang, aber kein lesbares Bild.
        self::assertSame('error.ausweis.invalid', self::validator()->check(UploadFixtures::files(["\x89PNG\r\n\x1A\n" . 'Müll']))[1][0]);
        self::assertSame('error.ausweis.invalid', self::validator()->check(UploadFixtures::files(["\xFF\xD8\xFF" . 'Müll']))[1][0]);
        self::assertSame('error.ausweis.invalid', self::validator()->check(UploadFixtures::files(['']))[1][0]);
    }

    public function testOneBadFileRejectsAllAndErrorHasNoFileData(): void
    {
        [$attachments, $error] = self::validator()->check(UploadFixtures::files([UploadFixtures::png(), 'kein Bild']));
        self::assertSame([], $attachments);
        self::assertSame(['error.ausweis.type', []], $error);
    }

    public function testDetectType(): void
    {
        self::assertSame('image/jpeg', UploadValidator::detectType(UploadFixtures::jpeg()));
        self::assertSame('image/png', UploadValidator::detectType(UploadFixtures::png()));
        self::assertSame('application/pdf', UploadValidator::detectType(UploadFixtures::pdf()));
        self::assertNull(UploadValidator::detectType(''));
    }
}
