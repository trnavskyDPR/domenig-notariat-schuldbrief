<?php

declare(strict_types=1);

namespace Schuldbrief;

use DateTimeImmutable;
use Schuldbrief\Http\Response;
use Schuldbrief\Order\OrderValidator;
use Schuldbrief\Security\Signer;
use Throwable;

/**
 * Einziger Controller. Routen über Query-Parameter, damit es ohne Rewrite-Regeln läuft:
 *   GET  ?lang=de                  Formular
 *   POST ?lang=de                  Antrag absenden
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
    ) {
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     */
    public function handle(string $method, array $get, array $post): Response
    {
        $lang = I18n::pick($method === 'POST' ? ($post['lang'] ?? $get['lang'] ?? null) : ($get['lang'] ?? null));
        $i18n = new I18n($lang, $this->root . '/lang');
        $view = new View($this->root, $i18n, $this->config);

        try {
            $page = is_string($get['view'] ?? null) ? $get['view'] : '';

            return match (true) {
                $method === 'POST' => $this->submit($post, $i18n, $view),
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
        ]));
    }

    /** @param array<string, mixed> $post */
    private function submit(array $post, I18n $i18n, View $view): Response
    {
        $result = (new OrderValidator())->validate($post, $i18n->lang);
        if (!$result->isValid()) {
            // Status 200 statt 422: manche Hoster ersetzen 4xx-Antworten durch eigene Fehlerseiten.
            return $this->form($view, $result->values, $result->errors);
        }

        // Schritt c: Hier gehen die Mails ans Notariat und die Bestätigung an die Bank.
        // Im Mock-Modus wird nichts gesendet.
        $mailed = true;

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

    /** Log-Zeile ohne Antragsdaten: Typ und Ort, bei Einstellungsfehlern zusätzlich der Name der Einstellung. */
    public static function logLine(Throwable $e): string
    {
        $detail = match (true) {
            $e instanceof ConfigException => ' – ' . $e->getMessage(),
            default => '',
        };

        return sprintf('[schuldbrief] %s in %s:%d%s', get_class($e), basename($e->getFile()), $e->getLine(), $detail);
    }
}
