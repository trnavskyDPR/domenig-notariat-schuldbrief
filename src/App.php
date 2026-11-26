<?php

declare(strict_types=1);

namespace Schuldbrief;

use Closure;
use DateTimeImmutable;
use Schuldbrief\Http\Response;
use Schuldbrief\Graph\GraphException;
use Schuldbrief\Http\NetworkException;
use Schuldbrief\Order\AttachmentPreparer;
use Schuldbrief\Order\OrderService;
use Schuldbrief\Order\OrderValidator;
use Schuldbrief\Order\UploadValidator;
use Schuldbrief\Security\RateLimiter;
use Schuldbrief\Security\Signer;
use Schuldbrief\Security\SpamGuard;
use Throwable;

/**
 * Einziger Controller. Routen über Query-Parameter, damit es ohne Rewrite-Regeln läuft:
 *   GET  ?lang=de                  Formular
 *   POST ?lang=de                  Antrag absenden (multipart: inkl. Ausweiskopien)
 *   GET  ?view=confirmed&t=…       Bestätigung (signiertes Token, keine Antragsdaten)
 */
final class App
{
    /** So lange (Sekunden) ist der Link auf die Bestätigungsseite gültig. */
    private const CONFIRM_TTL = 7200;

    public function __construct(
        private Config $config,
        private string $root,
        private ?DateTimeImmutable $now = null,
        /** Nur für Tests: ersetzt is_uploaded_file(). */
        private ?Closure $isUploadedFile = null,
        /** null = Mock-Modus: es wird nichts gesendet. */
        private ?OrderService $orders = null,
    ) {
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files        $_FILES
     * @param bool                 $bodyTooLarge true, wenn PHP die ganze Anfrage verworfen hat (post_max_size überschritten)
     * @param array<string, mixed> $server       $_SERVER (für IP-Adresse beim Limit pro IP)
     */
    public function handle(string $method, array $get, array $post, array $files = [], bool $bodyTooLarge = false, array $server = []): Response
    {
        $lang = I18n::pick($method === 'POST' ? ($post['lang'] ?? $get['lang'] ?? null) : ($get['lang'] ?? null));
        $i18n = new I18n($lang, $this->root . '/lang');
        $view = new View($this->root, $i18n, $this->config);

        try {
            $page = is_string($get['view'] ?? null) ? $get['view'] : '';

            return match (true) {
                $method === 'POST' && $bodyTooLarge => $this->form($view, [], [
                    'ausweis' => ['error.ausweis.post_too_big', ['max' => (int) ceil($this->config->uploadMaxBytes() / 1048576)]],
                ]),
                $method === 'POST' => $this->submit($post, $files, $i18n, $view, self::clientIp($server)),
                !in_array($method, ['GET', 'HEAD'], true) => Response::html(
                    $view->page('message', ['title' => 'error.method', 'text' => 'error.method', 'noindex' => true]),
                    405,
                    ['Allow' => 'GET, HEAD, POST'],
                ),
                $page === 'confirmed' => $this->confirmed($get, $view),
                default => $this->form($view),
            };
        } catch (Throwable $e) {
            // Nie Eingaben oder Antragsdaten loggen.
            error_log(self::logLine($e));

            return Response::html($view->page('message', [
                'title' => 'error.generic.title',
                'text' => $method === 'POST' ? 'error.generic' : 'error.unavailable',
                'noindex' => true,
            ]), 500);
        }
    }

    /**
     * @param array<string, string> $values
     * @param array<string, array{0: string, 1: array<string, string|int>}> $errors
     */
    private function form(View $view, array $values = [], array $errors = []): Response
    {
        return Response::html($view->page('form', [
            'values' => $values,
            'errors' => $errors,
            'formToken' => $this->spamGuard()->formToken(),
        ]));
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    private function submit(array $post, array $files, I18n $i18n, View $view, string $ip): Response
    {
        // Spamschutz: zuerst Limit pro IP, dann Fallenfeld und Mindest-Ausfüllzeit.
        // Bei diesen Meldungen bleiben die Eingaben erhalten (die Dateien müssen neu gewählt werden).
        $phone = ['phone' => $this->config->contactPhone()];
        $keep = OrderValidator::cleanValues($post);
        if ($ip !== '' && !$this->rateLimiter()->hit($ip, $this->now()->getTimestamp())) {
            return $this->form($view, $keep, ['form' => ['error.rate_limit', $phone]]);
        }
        $spam = $this->spamGuard()->check($post);
        if ($spam !== null) {
            return $this->form($view, $keep, ['form' => [$spam, $phone]]);
        }

        $uploads = new UploadValidator($this->config->uploadMaxBytes(), $this->config->uploadMaxFiles(), $this->isUploadedFile);
        $result = (new OrderValidator($uploads))->validate($post, $i18n->lang, $files);
        if (!$result->isValid()) {
            // Status 200 statt 422: manche Hoster ersetzen 4xx-Antworten durch eigene Fehlerseiten.
            return $this->form($view, $result->values, $result->errors);
        }

        // Ausweiskopien für den Mailversand vorbereiten (grosse Fotos verkleinern, Graph-Grenze prüfen).
        [$attachments, $attachmentError] = (new AttachmentPreparer())->prepare($result->order->attachments);
        if ($attachmentError !== null) {
            return $this->form($view, $result->values, ['ausweis' => $attachmentError]);
        }
        $order = $result->order->withAttachments($attachments);

        // Mails ans Notariat (mit Ausweiskopien) und Bestätigung an die Bank. Die Ausweiskopien gehen
        // NUR als Anhang der internen Mail hinaus – nicht an die Bank, nicht in Logs.
        // Im Mock-Modus ($this->orders === null) werden sie geprüft und verworfen.
        $mailed = true;
        if ($this->orders !== null) {
            try {
                $mailed = $this->orders->send($order);
            } catch (Throwable $e) {
                // Die interne Mail ist das einzige Ergebnis: Ohne sie ist der Antrag nicht angekommen.
                // Formular mit allen Eingaben erneut zeigen, damit die Bank es nochmals versuchen kann.
                error_log('Interne Mail fehlgeschlagen: ' . self::logLine($e));

                return $this->form($view, $result->values, [
                    'form' => ['error.send_failed', ['phone' => $this->config->contactPhone()]],
                    'ausweis' => ['error.ausweis.again', []],
                ]);
            }
        }

        // Die Bestätigungsseite erhält nur Sprache und Versandstatus – keine Antragsdaten in der URL.
        $token = (new Signer($this->config->secret()))->sign(
            ['l' => $i18n->lang, 'm' => $mailed ? 1 : 0],
            self::CONFIRM_TTL,
            $this->now(),
        );

        return Response::redirect('?' . http_build_query(['view' => 'confirmed', 'lang' => $i18n->lang, 't' => $token]));
    }

    /** @param array<string, mixed> $get */
    private function confirmed(array $get, View $view): Response
    {
        $token = self::param($get, 't') ?? '';
        $data = (new Signer($this->config->secret()))->verify($token, $this->now());
        if ($data === null) {
            return Response::html($view->page('message', [
                'title' => 'confirm.invalid.title',
                'text' => 'confirm.invalid',
                'noindex' => true,
            ]));
        }

        return Response::html($view->page('confirmation', [
            'mailFailed' => ($data['m'] ?? 1) === 0,
            'noindex' => true,
            'langParams' => ['view' => 'confirmed', 't' => $token],
        ]));
    }

    private function spamGuard(): SpamGuard
    {
        return new SpamGuard(new Signer($this->config->secret()), $this->now());
    }

    /** Limit pro IP: nur ein HMAC der IP-Adresse im temporären Ordner, eine Stunde lang. */
    private function rateLimiter(): RateLimiter
    {
        return new RateLimiter(
            sys_get_temp_dir() . '/schuldbrief-rate',
            hash_hmac('sha256', 'rate-limit', $this->config->secret()),
            $this->config->rateLimitPerHour(),
        );
    }

    /**
     * Öffentliche IP-Adresse des Besuchers fürs Limit pro IP (unverändert aus dem Beglaubigungstool).
     *
     * Hinter dem Atoll-Gateway steht sie im Header X-Forwarded-For. Gelesen wird von rechts
     * (diese Einträge setzen die eigenen Proxys, links kann der Besucher selbst etwas eintragen),
     * interne Adressen werden übersprungen. Findet sich keine öffentliche Adresse, gilt für die
     * Anfrage kein Limit – lieber kein Limit als alle Besucher gemeinsam zu sperren.
     *
     * @param array<string, mixed> $server
     */
    public static function clientIp(array $server): string
    {
        $public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($forwarded) && $forwarded !== '') {
            foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP, $public) !== false) {
                    return $candidate;
                }
            }
        }
        $remote = $server['REMOTE_ADDR'] ?? '';

        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP, $public) !== false ? $remote : '';
    }

    private function now(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable('now');
    }

    /** @param array<string, mixed> $source */
    private static function param(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Log-Zeile ohne Antragsdaten: Typ und Ort, zusätzlich bei Einstellungsfehlern der Name der
     * Einstellung, bei Graph-Fehlern HTTP-Status und Microsofts Fehlercode, bei Netzwerkfehlern die
     * curl-Nummer. Andere Meldungen kommen nie ins Log (sie könnten Eingaben enthalten).
     */
    public static function logLine(Throwable $e): string
    {
        $detail = match (true) {
            $e instanceof ConfigException => ' – ' . $e->getMessage(),
            $e instanceof GraphException => ' – ' . $e->logLine(),
            $e instanceof NetworkException => ' – ' . $e->getMessage(),
            default => '',
        };

        return sprintf('[schuldbrief] %s in %s:%d%s', get_class($e), basename($e->getFile()), $e->getLine(), $detail);
    }
}
