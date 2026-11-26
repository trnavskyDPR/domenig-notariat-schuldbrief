<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/**
 * Serverseitige Prüfung aller Eingaben. Die Browser-Validierung ist abgeschaltet
 * (novalidate), damit Meldungen einheitlich und in der gewählten Sprache erscheinen.
 *
 * Prinzip wie BookingValidator im Beglaubigungstool: zuerst bereinigen, dann jedes Feld
 * prüfen, Fehler in Formular-Reihenfolge sammeln. Nur wenn alles stimmt, entsteht ein Order.
 */
final class OrderValidator
{
    /** Reihenfolge wie im Formular – bestimmt auch die Fehlerübersicht. */
    public const FIELDS = [
        'grundbuchblatt', 'adresse', 'rang', 'betrag', 'glaeubigerin', 'bank',
        'vorname', 'nachname', 'email', 'vorwahl', 'telefon', 'ausweis', 'datenschutz',
    ];

    /** Höchstlänge der Textfelder. Vorwahl und Checkbox werden gegen feste Werte geprüft. */
    public const MAX_LENGTH = [
        'grundbuchblatt' => 30, 'adresse' => 150, 'rang' => 100, 'betrag' => 30,
        'glaeubigerin' => 100, 'bank' => 100,
        'vorname' => 60, 'nachname' => 60, 'email' => 254, 'telefon' => 30,
    ];

    /** Grundbuchblatt: mindestens eine Ziffer, z. B. «1234», «1234-1», «Bern 4/1234». */
    private const GRUNDBUCHBLATT = '/^(?=.*\d)[\p{L}\p{M}\d][\p{L}\p{M}\d\s\-\/.,()]*$/u';
    /** Adresse: mindestens ein Buchstabe, übliche Satzzeichen einer Adresse. */
    private const PLACE = '/^(?=.*\p{L})[\p{L}\p{M}\d\s\'’.,()\/\-]+$/u';
    /** Freitext (Rang, Firmennamen): Buchstaben, Ziffern, Satzzeichen – keine Zeichen wie < > = $. */
    private const TEXT = '/^(?=.*[\p{L}\d])[\p{L}\p{M}\p{N}\s\p{P}+|]+$/u';
    private const NAME = '/^[\p{L}\p{M}][\p{L}\p{M}\s\'’.\-]*$/u';
    private const PHONE_CHARS = '/^\+?[\d\s()\-\/.]+$/';

    /** @var array<string, array{0: string, 1: array<string, string|int>}> */
    private array $errors = [];

    /** @param UploadValidator|null $uploads null = keine Ausweiskopien prüfen (nur für Tests der übrigen Felder) */
    public function __construct(private ?UploadValidator $uploads = null)
    {
    }

    /**
     * @param array<string, mixed> $input $_POST
     * @param array<string, mixed> $files $_FILES
     */
    public function validate(array $input, string $lang, array $files = []): ValidationResult
    {
        $this->errors = [];
        $v = self::cleanValues($input);
        if ($v['vorwahl'] === '') {
            $v['vorwahl'] = Options::DIAL_DEFAULT;
        }

        foreach (self::MAX_LENGTH as $field => $max) {
            if (!preg_match('/^.{0,' . $max . '}$/us', $v[$field])) {
                $this->error($field, 'error.too_long', ['max' => $max]);
            }
        }

        $this->checkPattern($v, 'grundbuchblatt', self::GRUNDBUCHBLATT);
        $this->checkPattern($v, 'adresse', self::PLACE);
        $this->checkPattern($v, 'rang', self::TEXT);
        $betrag = $this->checkAmount($v['betrag']);
        $this->checkPattern($v, 'glaeubigerin', self::TEXT);
        $this->checkPattern($v, 'bank', self::TEXT);
        $this->checkPattern($v, 'vorname', self::NAME);
        $this->checkPattern($v, 'nachname', self::NAME);
        $this->checkEmail($v['email']);
        $telefon = $this->checkPhone($v['vorwahl'], $v['telefon']);
        $attachments = [];
        if ($this->uploads !== null) {
            [$attachments, $uploadError] = $this->uploads->check($files['ausweis'] ?? null);
            if ($uploadError !== null) {
                $this->error('ausweis', $uploadError[0], $uploadError[1]);
            }
        }
        if ($v['datenschutz'] !== '1') {
            $this->error('datenschutz', 'error.datenschutz.required');
        }

        // Ein Browser kann Dateien nicht selbst wieder ins Formular einsetzen. Gibt es andere Fehler,
        // müssen die (an sich gültigen) Dateien erneut gewählt werden – wir speichern sie nicht zwischen.
        if ($attachments !== [] && $this->errors !== []) {
            $this->error('ausweis', 'error.ausweis.again');
        }

        $errors = $this->sortedErrors();
        if ($errors !== [] || $betrag === null || $telefon === null) {
            return new ValidationResult($v, $errors, null);
        }

        return new ValidationResult($v, [], new Order(
            grundbuchblatt: $v['grundbuchblatt'],
            adresse: $v['adresse'],
            rang: $v['rang'],
            betrag: $betrag,
            glaeubigerin: $v['glaeubigerin'],
            bank: $v['bank'],
            vorname: $v['vorname'],
            nachname: $v['nachname'],
            email: $v['email'],
            telefon: $telefon,
            lang: $lang,
            attachments: $attachments,
        ));
    }

    /**
     * Bereinigte Textfelder zum erneuten Anzeigen (ohne Prüfung).
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function cleanValues(array $input): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            if ($field === 'ausweis') {
                continue; // Datei, kein Textfeld: kommt aus $_FILES und wird nie zum erneuten Anzeigen aufbewahrt.
            }
            $raw = $input[$field] ?? '';
            $values[$field] = is_string($raw) ? self::clean($raw) : '';
        }

        return $values;
    }

    /** Einzeilige Eingaben: ungültiges UTF-8 verwerfen, Steuerzeichen und Mehrfach-Leerraum entfernen. */
    public static function clean(string $value): string
    {
        if (!preg_match('//u', $value)) {
            return '';
        }
        $value = (string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Telefonnummer ins internationale Format bringen (unverändert aus dem Beglaubigungstool).
     * «079 123 45 67» mit +41 => «+41 79 123 45 67». Mit + oder 00 eingegebene Nummern gelten unverändert.
     */
    public static function normalizePhone(string $dialCode, string $number): ?string
    {
        if (!preg_match(self::PHONE_CHARS, $number)) {
            return null;
        }
        $digits = (string) preg_replace('/\D/', '', $number);

        if (str_starts_with($number, '+')) {
            $international = '+' . $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = '+' . substr($digits, 2);
        } elseif ($dialCode === Options::DIAL_OTHER || !isset(Options::DIAL_CODES[$dialCode])) {
            return null;
        } else {
            if (!in_array($dialCode, Options::KEEP_LEADING_ZERO, true) && str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
            $international = $dialCode . $digits;
        }

        // Häufiger Fehler: «+41 079 …» – die nationale 0 gehört nicht zur internationalen Nummer.
        $code = self::knownCode($international);
        if ($code !== null && !in_array($code, Options::KEEP_LEADING_ZERO, true)
            && str_starts_with(substr($international, strlen($code)), '0')) {
            $international = $code . substr($international, strlen($code) + 1);
        }

        $total = strlen($international) - 1;
        if ($total < 8 || $total > 15) {
            return null;
        }
        if (preg_match('/^\+41(\d{2})(\d{3})(\d{2})(\d{2})$/', $international, $m)) {
            return sprintf('+41 %s %s %s %s', $m[1], $m[2], $m[3], $m[4]);
        }

        return $code !== null ? $code . ' ' . substr($international, strlen($code)) : $international;
    }

    /** Längste passende bekannte Vorwahl, z. B. +423 vor +4. */
    private static function knownCode(string $international): ?string
    {
        $match = null;
        foreach (array_keys(Options::DIAL_CODES) as $code) {
            if (str_starts_with($international, $code) && ($match === null || strlen($code) > strlen($match))) {
                $match = $code;
            }
        }

        return $match;
    }

    /** @param array<string, string> $v */
    private function checkPattern(array $v, string $field, string $pattern): void
    {
        if ($v[$field] === '') {
            $this->error($field, 'error.' . $field . '.required');
        } elseif (!preg_match($pattern, $v[$field])) {
            $this->error($field, 'error.' . $field . '.invalid');
        }
    }

    /** Höhe des Schuldbriefes: ganze Franken zwischen Amount::MIN und Amount::MAX. */
    private function checkAmount(string $value): ?int
    {
        if ($value === '') {
            $this->error('betrag', 'error.betrag.required');

            return null;
        }
        $francs = Amount::parse($value);
        if ($francs === Amount::INVALID) {
            $this->error('betrag', 'error.betrag.invalid');

            return null;
        }
        if ($francs < Amount::MIN) {
            $this->error('betrag', 'error.betrag.too_small', ['min' => Amount::format(Amount::MIN)]);

            return null;
        }
        if ($francs > Amount::MAX) {
            $this->error('betrag', 'error.betrag.too_big', ['max' => Amount::format(Amount::MAX)]);

            return null;
        }

        return $francs;
    }

    private function checkEmail(string $email): void
    {
        if ($email === '') {
            $this->error('email', 'error.email.required');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('email', 'error.email.invalid');
        }
    }

    private function checkPhone(string $dialCode, string $number): ?string
    {
        if ($dialCode !== Options::DIAL_OTHER && !isset(Options::DIAL_CODES[$dialCode])) {
            $this->error('vorwahl', 'error.vorwahl.invalid');

            return null;
        }
        if ($number === '') {
            $this->error('telefon', 'error.telefon.required');

            return null;
        }
        $normalized = self::normalizePhone($dialCode, $number);
        if ($normalized === null) {
            $needsCode = $dialCode === Options::DIAL_OTHER && !str_starts_with($number, '+') && !str_starts_with($number, '00');
            $this->error('telefon', $needsCode ? 'error.telefon.needs_code' : 'error.telefon.invalid');
        }

        return $normalized;
    }

    /** Pro Feld nur der erste Fehler (z. B. «zu lang» vor «ungültig»). @param array<string, string|int> $params */
    private function error(string $field, string $key, array $params = []): void
    {
        $this->errors[$field] ??= [$key, $params];
    }

    /** @return array<string, array{0: string, 1: array<string, string|int>}> */
    private function sortedErrors(): array
    {
        $sorted = [];
        foreach (self::FIELDS as $field) {
            if (isset($this->errors[$field])) {
                $sorted[$field] = $this->errors[$field];
            }
        }

        return $sorted;
    }
}
