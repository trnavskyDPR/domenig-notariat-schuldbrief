<?php

declare(strict_types=1);

namespace Schuldbrief\Security;

/**
 * Begrenzt die Anzahl Absendeversuche pro IP-Adresse und Stunde.
 *
 * Datenschutz: Gespeichert wird nur ein nicht umkehrbarer HMAC der IP-Adresse
 * (mit geheimem Schlüssel) als Dateiname und darin die Zeitpunkte der Versuche –
 * keine Formulardaten. Einträge verfallen nach einer Stunde und werden gelöscht.
 * Abgesegnet von Trevor Trnavsky am 17.11.2026 (Beglaubigungstool), gleich übernommen.
 */
final class RateLimiter
{
    private const WINDOW = 3600;

    public function __construct(
        private string $dir,
        private string $key,
        private int $limit,
    ) {
    }

    /** Zählt einen Versuch. false = Grenze erreicht, Versuch abweisen. */
    public function hit(string $ip, int $now): bool
    {
        if ($this->limit <= 0) {
            return true;
        }
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            return true; // Ohne Speicherort lieber absenden lassen als alle abweisen.
        }
        if (random_int(1, 50) === 1) {
            $this->cleanup($now);
        }

        $file = $this->dir . '/' . hash_hmac('sha256', $ip, $this->key);
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return true;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $times = array_values(array_filter(
                array_map('intval', explode(',', (string) $raw)),
                static fn (int $t): bool => $t > $now - self::WINDOW,
            ));
            if (count($times) >= $this->limit) {
                return false;
            }
            $times[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, implode(',', $times));

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Abgelaufene Einträge löschen. */
    private function cleanup(int $now): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $now - self::WINDOW) {
                @unlink($file);
            }
        }
    }
}
