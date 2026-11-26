<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

use Closure;

/**
 * Prüft die hochgeladenen Ausweiskopien der Grundeigentümer (Formularfeld «ausweis[]»).
 *
 * Grundsätze:
 *  - Der Dateityp wird am INHALT erkannt (JPEG, PNG, PDF). Dateiendung und der vom
 *    Browser gemeldete Typ werden nie geglaubt.
 *  - Die Datei wird in den Arbeitsspeicher gelesen und die temporäre Datei sofort
 *    gelöscht. Nichts bleibt auf dem Server liegen.
 *  - Der Originalname der Datei wird nicht übernommen (siehe Attachment).
 *  - Es ist KEIN Virenscan. Die Mail ans Notariat wird von Exchange Online geprüft.
 *  - Fehlermeldungen enthalten nie Dateinamen oder Inhalte.
 */
final class UploadValidator
{
    /** Erlaubte Typen: MIME-Typ => Dateiendung für den neutralen Dateinamen. */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    private ?Closure $isUploaded;

    /**
     * @param int          $maxBytes   höchste Grösse pro Datei
     * @param int          $maxFiles   höchste Anzahl Dateien
     * @param Closure|null $isUploaded prüft, ob eine Datei wirklich per Upload kam (Standard: is_uploaded_file).
     *                                 Nur Tests ersetzen das.
     */
    public function __construct(private int $maxBytes, private int $maxFiles, ?Closure $isUploaded = null)
    {
        $this->isUploaded = $isUploaded;
    }

    /**
     * @param mixed $field Inhalt von $_FILES['ausweis'] (Browser sendet «name», «tmp_name», «error», «size» als Listen)
     *
     * @return array{0: list<Attachment>, 1: array{0: string, 1: array<string, int>}|null} Dateien, Fehler (Textschlüssel + Parameter)
     */
    public function check(mixed $field): array
    {
        $files = self::normalize($field);
        if ($files === []) {
            return [[], ['error.ausweis.required', []]];
        }
        if (count($files) > $this->maxFiles) {
            $isUploaded = $this->isUploaded ?? is_uploaded_file(...);
            foreach ($files as $file) {
                if ($file['tmp_name'] !== '' && $isUploaded($file['tmp_name'])) {
                    @unlink($file['tmp_name']);
                }
            }

            return [[], ['error.ausweis.too_many', ['max' => $this->maxFiles]]];
        }

        $attachments = [];
        $error = null;
        foreach ($files as $index => $file) {
            [$attachment, $fileError] = $this->checkOne($file, $index + 1);
            if ($attachment !== null) {
                $attachments[] = $attachment;
            } else {
                // Alle temporären Dateien werden trotzdem gelesen und gelöscht; gemeldet wird der erste Fehler.
                $error ??= $fileError;
            }
        }

        return $error === null ? [$attachments, null] : [[], $error];
    }

    /**
     * @param array{error: int, tmp_name: string} $file
     *
     * @return array{0: Attachment|null, 1: array{0: string, 1: array<string, int>}|null}
     */
    private function checkOne(array $file, int $number): array
    {
        $tooBig = ['error.ausweis.too_big', ['max' => (int) ceil($this->maxBytes / 1048576)]];

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return [null, $tooBig];
        }
        $tmp = $file['tmp_name'];
        $isUploaded = $this->isUploaded ?? is_uploaded_file(...);
        if ($file['error'] !== UPLOAD_ERR_OK || $tmp === '' || !$isUploaded($tmp)) {
            return [null, ['error.ausweis.failed', []]];
        }

        $size = filesize($tmp);
        $content = $size !== false && $size <= $this->maxBytes ? file_get_contents($tmp) : false;
        // Temporäre Datei sofort entfernen – PHP würde es erst am Ende der Anfrage tun.
        @unlink($tmp);

        if ($size !== false && $size > $this->maxBytes) {
            return [null, $tooBig];
        }
        if ($content === false || $content === '') {
            return [null, ['error.ausweis.invalid', []]];
        }

        $mime = self::detectType($content);
        if ($mime === null) {
            return [null, ['error.ausweis.type', []]];
        }
        // Bilder müssen sich wirklich als Bild lesen lassen (Kopf mit Breite und Höhe).
        if ($mime !== 'application/pdf') {
            $info = @getimagesizefromstring($content);
            if ($info === false || ($info['mime'] ?? '') !== $mime) {
                return [null, ['error.ausweis.invalid', []]];
            }
        }

        return [new Attachment('ausweis-' . $number . '.' . self::TYPES[$mime], $mime, $content), null];
    }

    /** Typ an den ersten Bytes erkennen. Alles andere (auch HEIC, GIF, Word) ergibt null. */
    public static function detectType(string $content): ?string
    {
        return match (true) {
            str_starts_with($content, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($content, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($content, '%PDF-') => 'application/pdf',
            default => null,
        };
    }

    /**
     * PHP liefert mehrere Dateien als «name[0], name[1] …». Hier wird daraus eine Liste von Dateien;
     * Felder ohne gewählte Datei (UPLOAD_ERR_NO_FILE) fallen weg.
     *
     * @return list<array{error: int, tmp_name: string}>
     */
    private static function normalize(mixed $field): array
    {
        if (!is_array($field) || !isset($field['error'], $field['tmp_name'])) {
            return [];
        }
        $errors = is_array($field['error']) ? $field['error'] : [$field['error']];
        $tmpNames = is_array($field['tmp_name']) ? $field['tmp_name'] : [$field['tmp_name']];

        $files = [];
        foreach ($errors as $i => $error) {
            if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $tmp = $tmpNames[$i] ?? '';
            $files[] = ['error' => $error, 'tmp_name' => is_string($tmp) ? $tmp : ''];
        }

        return $files;
    }
}
