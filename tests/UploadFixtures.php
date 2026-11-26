<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

/**
 * Kleine Beispieldateien und ein nachgebautes $_FILES für die Upload-Tests.
 * Die temporären Dateien löscht der Upload-Check selbst (wie im Betrieb).
 */
final class UploadFixtures
{
    /** 1×1-Pixel-PNG */
    public static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    /** 1×1-Pixel-JPEG */
    public static function jpeg(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    }

    public static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    /**
     * $_FILES['ausweis'] so, wie PHP es für <input name="ausweis[]" multiple> liefert.
     *
     * @param list<string|null> $contents Dateiinhalte; null = Feld ohne gewählte Datei
     *
     * @return array{name: list<string>, type: list<string>, tmp_name: list<string>, error: list<int>, size: list<int>}
     */
    public static function files(array $contents): array
    {
        $files = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($contents as $i => $content) {
            if ($content === null) {
                $files['name'][] = '';
                $files['type'][] = '';
                $files['tmp_name'][] = '';
                $files['error'][] = UPLOAD_ERR_NO_FILE;
                $files['size'][] = 0;
                continue;
            }
            $tmp = (string) tempnam(sys_get_temp_dir(), 'ausweis');
            file_put_contents($tmp, $content);
            // Name und Typ stammen vom Browser und werden von der App nie geglaubt.
            $files['name'][] = 'Anna-Muster-Pass-' . $i . '.jpg';
            $files['type'][] = 'image/jpeg';
            $files['tmp_name'][] = $tmp;
            $files['error'][] = UPLOAD_ERR_OK;
            $files['size'][] = strlen($content);
        }

        return $files;
    }
}
