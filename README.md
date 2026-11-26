# Schuldbriefbestellung – Domenig & Partner Notariat AG

Online-Antrag für Schuldbriefbestellungen unter **https://schuldbrief.domenig.law**
(ersetzt das Jotform-Formular). Die Bank füllt das Formular aus; das Notariat erhält alle
Angaben per Mail, die Bank eine Eingangsbestätigung. Das Tool speichert **keine** Antragsdaten.

> Stand: Schritt a (Formular, Validierung, DE/FR/EN, Mock-Modus). Upload, Mailversand und
> Spamschutz folgen. Betrieb, Fehlersuche und Livegang-Checkliste kommen mit Schritt d.

## Voraussetzungen

- PHP **8.1 oder neuer** mit `json`, `openssl`, `pcre`
- Für die Tests lokal: Composer

Keine Datenbank, kein Node, kein Build-Schritt.

## Lokal starten

```bash
php -S 127.0.0.1:8080 -t public
```

Dann http://127.0.0.1:8080 öffnen. Ohne `.env` läuft automatisch der **Mock-Modus**:
Nichts wird übermittelt, keine Mail versendet.

Sprache per URL: `?lang=de`, `?lang=fr`, `?lang=en`.

## Tests

```bash
composer install
```

```bash
composer test
```

## Konfiguration

`.env.example` nach `.env` kopieren und anpassen. `.env` gehört **nie** ins Git.

| Variable | Standard | Bedeutung |
|---|---|---|
| `APP_MODE` | `mock` | `mock` = nichts wird gesendet · `test` = alle Mails ans Testpostfach · `live` = produktiv |
| `APP_SECRET` | – | Zufallsschlüssel, mind. 32 Zeichen. In `test`/`live` Pflicht. Erzeugen: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `CONTACT_PHONE` | `+41 58 531 20 00` | Telefon für Hinweise und Fehlerseiten |
| `CONTACT_EMAIL` | `notariat@domenig.law` | Kontaktadresse |
| `OFFICE_ADDRESS` | `Laupenstrasse 1, 3008 Bern` | Adresse in der Fusszeile |
| `PRIVACY_URL_DE` / `_FR` / `_EN` | `https://www.domenig.law/datenschutz` | Datenschutzerklärung je Sprache, leer = deutsche Fassung |

## Formular

| Feld | Prüfung |
|---|---|
| Grundbuchblatt-Nummer | Pflicht, max. 30 Zeichen, mindestens eine Ziffer (z. B. `1234`, `Bern 4/1234`) |
| Adresse des Grundstücks | Pflicht, max. 150 Zeichen |
| Rang des Grundpfandrechts | Pflicht, Freitext, max. 100 Zeichen |
| Höhe des Schuldbriefes (CHF) | Pflicht, ganze Franken, mindestens 1'000. Akzeptiert `1'000`, `1’000`, `1 000`, `1000.–`, `1'000.00`, `CHF 1'000` |
| Gläubigerin (Firma), Name bestehende Bank | Pflicht, je max. 100 Zeichen |
| Ansprechperson: Vorname, Nachname, E-Mail, Telefon mit Ländervorwahl | Pflicht, wie im Beglaubigungstool |
| Datenschutz | Häkchen Pflicht, Link auf `PRIVACY_URL_*` |

Vor dem Absenden steht der Hinweis zu den Gebühren (Text in `lang/*.php`, Schlüssel `fees.text`).

## Aufbau

```
public/      einziger öffentlicher Ordner
  index.php  Einstieg, alle Anfragen, Health-Check /metrics, HSTS
  assets/    CSS, JS, Barlow-Schrift (lokal, SIL OFL), Logo
src/
  App.php            Controller (Formular, Absenden, Bestätigung)
  Config.php         Einstellungen aus Umgebungsvariablen
  Order/             Antrag, Validierung, Betrag (Schweizer Format)
  Security/          Signer (signierte Links)
  Http/              Response mit Sicherheits-Headern (CSP), Health
templates/   HTML-Templates
lang/        Texte DE / FR / EN
tests/       PHPUnit
```
