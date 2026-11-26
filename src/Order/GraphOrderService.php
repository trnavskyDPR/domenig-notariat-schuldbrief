<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

use Schuldbrief\App;
use Schuldbrief\Config;
use Schuldbrief\ConfigException;
use Schuldbrief\Graph\GraphClient;
use Schuldbrief\I18n;
use Throwable;

/**
 * Sendet einen Antrag über Microsoft Graph (nur Recht «Mail.Send»):
 *  1. Interne Mail an INTERNAL_RECIPIENTS (info@, notariat@, schneider@) – jede Adresse erhält ein
 *     eigenes Exemplar im eigenen Posteingang. Die Ausweiskopien gehen an ID_COPY_RECIPIENTS
 *     (Standard: alle drei). Sind die Anhänge grösser als die Graph-Grenze, wird auf mehrere Mails
 *     verteilt («Teil 1 von 2»). «Antworten» geht direkt an die Ansprechperson der Bank.
 *  2. Eingangsbestätigung an die Bank in ihrer Sprache, ohne Anhänge, nur mit Grundbuchblatt-Nr.
 *     und Adresse als Referenz.
 *
 * Die interne Mail ist das einzige Ergebnis eines Antrags (keine Datenbank, kein Kalender).
 * Schlägt sie fehl, wird der Fehler weitergegeben und es geht KEINE Bestätigung an die Bank.
 *
 * Gesendet wird mit saveToSentItems=false: keine zusätzliche Kopie in «Gesendete Elemente».
 * Antragsdaten verlassen die Anfrage nur in Richtung Mail.
 */
final class GraphOrderService implements OrderService
{
    public function __construct(
        private GraphClient $graph,
        private Config $config,
        private string $langDir,
    ) {
    }

    public function send(Order $order): bool
    {
        // Interne Mail: Fehler gehen bewusst nach oben (App zeigt «nicht übermittelt»).
        $this->sendInternalMails($order, new I18n('de', $this->langDir));

        // Ab hier ist der Antrag beim Notariat. Fehler bei der Bestätigung werden nur geloggt.
        try {
            $this->sendBankMail($order, new I18n($order->lang, $this->langDir));

            return true;
        } catch (Throwable $e) {
            error_log('Bestätigung an Bank fehlgeschlagen: ' . App::logLine($e));

            return false;
        }
    }

    private function sendInternalMails(Order $o, I18n $de): void
    {
        $subject = $this->testPrefix() . $de->t('mail.internal.subject', [
            'grundbuchblatt' => $o->grundbuchblatt,
            'adresse' => $o->adresse,
        ]);
        $all = $this->config->internalRecipients();
        $withCopy = array_values(array_intersect($all, $this->config->idCopyRecipients()));
        $withoutCopy = array_values(array_diff($all, $withCopy));
        $replyTo = [$o->email];

        if ($o->attachments === []) {
            $this->sendMail($all, $subject, $this->details($o, $de, $de->t('mail.internal.no_attachment')), [], $replyTo);

            return;
        }

        // Sonst gingen die Ausweiskopien stillschweigend verloren: lieber klarer Fehler (steht im Log).
        if ($withCopy === []) {
            throw new ConfigException('ID_COPY_RECIPIENTS muss mindestens eine Adresse aus INTERNAL_RECIPIENTS enthalten.');
        }

        // Ausweiskopien: so viele Mails wie nötig, jede unter der Graph-Grenze.
        $chunks = self::chunk($o->attachments);
        $total = count($chunks);
        foreach ($chunks as $i => $chunk) {
            $part = $total > 1 ? ' ' . $de->t('mail.internal.part', ['n' => $i + 1, 'total' => $total]) : '';
            $note = $de->t('mail.internal.attachment_here')
                . ($total > 1 ? ' ' . $de->t('mail.internal.attachment_parts', ['total' => $total]) : '');
            $this->sendMail($withCopy, $subject . $part, $this->details($o, $de, $note), $chunk, $replyTo);
        }
        if ($withoutCopy !== []) {
            $note = $de->t('mail.internal.attachment_elsewhere', ['recipients' => implode(', ', $withCopy)]);
            $this->sendMail($withoutCopy, $subject, $this->details($o, $de, $note), [], $replyTo);
        }
    }

    private function sendBankMail(Order $o, I18n $t): void
    {
        $phone = $this->config->contactPhone();
        $body = implode("\n", [
            $t->t('mail.bank.greeting', ['name' => $o->vorname . ' ' . $o->nachname]),
            '',
            $t->t('mail.bank.intro'),
            '',
            // Nur Referenz – kein Betrag, keine Bankangaben (falls die E-Mail-Adresse vertippt ist).
            $t->t('field.grundbuchblatt') . ': ' . $o->grundbuchblatt,
            $t->t('field.adresse') . ': ' . $o->adresse,
            $t->t('section.ausweis') . ': ' . $t->t('mail.files_count', ['count' => count($o->attachments)]),
            '',
            $t->t('confirm.next'),
            '',
            $t->t('fees.heading'),
            $t->t('fees.text'),
            '',
            $t->t('confirm.questions', ['phone' => $phone, 'email' => $this->config->contactEmail()]),
            '',
            $t->t('mail.bank.closing'),
            $t->t('org.name'),
            $this->config->officeAddress(),
            $phone,
        ]);

        $this->sendMail(
            [$this->config->bankRecipient($o->email)],
            $this->testPrefix() . $t->t('mail.bank.subject', ['grundbuchblatt' => $o->grundbuchblatt]),
            $body,
            [],
            [$this->config->contactEmail()],
        );
    }

    /**
     * Eine Mail über Graph senden (POST /users/{absender}/sendMail).
     *
     * @param list<string> $to
     * @param list<Attachment> $attachments
     * @param list<string> $replyTo
     */
    private function sendMail(array $to, string $subject, string $body, array $attachments, array $replyTo): void
    {
        $address = static fn (string $mail): array => ['emailAddress' => ['address' => $mail]];
        $message = [
            'subject' => $subject,
            'body' => ['contentType' => 'Text', 'content' => $body],
            'toRecipients' => array_map($address, $to),
            'replyTo' => array_map($address, $replyTo),
        ];
        if ($attachments !== []) {
            $message['attachments'] = array_map(static fn (Attachment $a): array => [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $a->filename,
                'contentType' => $a->mime,
                'contentBytes' => base64_encode($a->content),
            ], $attachments);
        }

        $this->graph->post('/users/' . rawurlencode($this->config->senderMailbox()) . '/sendMail', [
            'message' => $message,
            'saveToSentItems' => false,
        ]);
    }

    /** Alle Angaben als Text für die interne Mail (immer Deutsch). */
    private function details(Order $o, I18n $de, string $attachmentNote): string
    {
        $langNames = ['de' => 'Deutsch', 'fr' => 'Französisch', 'en' => 'Englisch'];
        $sections = [
            $de->t('section.property') => [
                $de->t('field.grundbuchblatt') => $o->grundbuchblatt,
                $de->t('field.adresse') => $o->adresse,
                $de->t('field.rang') => $o->rang,
                $de->t('mail.internal.betrag') => $o->betragText(),
            ],
            $de->t('section.bank') => [
                $de->t('field.glaeubigerin') => $o->glaeubigerin,
                $de->t('field.bank') => $o->bank,
            ],
            $de->t('section.contact') => [
                $de->t('mail.internal.name') => $o->vorname . ' ' . $o->nachname,
                $de->t('field.email') => $o->email,
                $de->t('field.telefon') => $o->telefon,
                $de->t('mail.internal.lang') => $langNames[$o->lang] ?? $o->lang,
            ],
        ];

        $lines = [$de->t('mail.internal.intro'), ''];
        foreach ($sections as $heading => $rows) {
            $lines[] = $heading;
            foreach ($rows as $label => $value) {
                $lines[] = '  ' . $label . ': ' . $value;
            }
            $lines[] = '';
        }
        $lines[] = $de->t('section.ausweis') . ': ' . $de->t('mail.files_count', ['count' => count($o->attachments)]);
        $lines[] = $attachmentNote;
        $lines[] = '';
        $lines[] = $de->t('mail.internal.reply');

        return implode("\n", $lines);
    }

    /**
     * Anhänge so auf Mails verteilen, dass jede unter der Graph-Grenze bleibt.
     *
     * @param list<Attachment> $attachments
     * @return list<list<Attachment>>
     */
    private static function chunk(array $attachments): array
    {
        $chunks = [];
        $current = [];
        $size = 0;
        foreach ($attachments as $a) {
            if ($current !== [] && $size + $a->size() > AttachmentPreparer::MAX_MAIL_BYTES) {
                $chunks[] = $current;
                $current = [];
                $size = 0;
            }
            $current[] = $a;
            $size += $a->size();
        }
        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function testPrefix(): string
    {
        return $this->config->mode() === 'test' ? '[TEST] ' : '';
    }
}
