# Schuldbriefbestellung – Domenig & Partner Notariat AG

Online-Antrag für Schuldbriefbestellungen unter **https://schuldbrief.domenig.law**
(ersetzt das Jotform-Formular). Die Bank füllt das Formular aus; das Notariat erhält alle
Angaben per Mail, die Bank eine Eingangsbestätigung. Das Tool speichert **keine** Antragsdaten.

> Stand: Schritte a und b (Formular, Validierung, DE/FR/EN, Mock-Modus, Upload der
> Ausweiskopien). Mailversand und Spamschutz folgen. Betrieb, Fehlersuche und
> Livegang-Checkliste kommen mit Schritt d.
>
> **Upload:** Entscheid des Vorgesetzten von Trevor Trnavsky noch ausstehend – ohne Entscheid
> kein Livegang mit Upload.

## Voraussetzungen

- PHP **8.1 oder neuer** mit `json`, `openssl`, `pcre` und `gd` (Verkleinern grosser Fotos;
  ohne `gd` läuft die App, verkleinert aber nicht). Im Docker-Image ist alles enthalten.
- Für den Upload (PHP-Einstellungen, siehe unten): `upload_max_filesize` mindestens `6M`,
  `post_max_size` mindestens `40M`, `max_file_uploads` mindestens `10`
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
| `UPLOAD_MAX_MB` | `5` | höchste Grösse pro hochgeladener Datei (Ausweiskopie) in MB |
| `UPLOAD_MAX_FILES` | `6` | höchste Anzahl Dateien (mehrere Grundeigentümer, je Vorder- und Rückseite) |
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
| Ausweiskopien der Grundeigentümer | Pflicht, bis 6 Dateien: JPG/PNG je bis 5 MB, PDF je bis 2 MB (Typ am Inhalt geprüft) |
| Datenschutz | Häkchen Pflicht, Link auf `PRIVACY_URL_*` |

Vor dem Absenden steht der Hinweis zu den Gebühren (Text in `lang/*.php`, Schlüssel `fees.text`).

## Upload der Ausweiskopien

Die Bank lädt Kopien von Pass oder ID aller Grundeigentümer hoch: bis 6 Dateien, Fotos (JPG, PNG)
je bis 5 MB, PDF je bis 2 MB. Ablauf wie im Beglaubigungstool:

- Der Dateityp wird am **Inhalt** erkannt, nicht an Endung oder Browser-Angabe. HEIC, GIF, Word
  usw. werden abgelehnt. Das ist kein Virenscan; die Mail wird beim Eingang von Exchange Online geprüft.
- PHP legt jede Datei kurz in den temporären Ordner. Die App liest sie sofort in den
  Arbeitsspeicher und löscht die temporäre Datei. Der Dateiname der Bank wird nicht übernommen
  (neutral: `ausweis-1.pdf`, `ausweis-2.jpg` …).
- Nach einem Formularfehler müssen die Dateien neu gewählt werden – nichts wird zwischengespeichert.
- **Grenze für Anhänge:** Mit dem Recht «Mail.Send» nimmt Graph pro Mail nur rund 3 MB Anhang an.
  Fotos über 1.5 MB verkleinert die App auf 2400 px (GD) und dreht sie dabei nach der EXIF-Angabe
  richtig herum. PDFs über 2.8 MB werden mit einer klaren Meldung abgelehnt («ein PDF pro Person»).
  Mehrere Dateien werden beim Versand auf mehrere Mails verteilt (Schritt c). Grössere Anhänge
  bräuchten zusätzlich «Mail.ReadWrite» (Entwürfe) – bewusst nicht.
- Im Mock-Modus werden die Dateien geprüft und verworfen.

PHP-Einstellungen (im `Dockerfile` gesetzt, bei anderem Hosting selbst eintragen):

| PHP-Einstellung | Mindestwert | PHP-Standard |
|---|---|---|
| `upload_max_filesize` | `6M` | `2M` |
| `post_max_size` | `40M` (6 × 5 MB + Reserve) | `8M` |
| `max_file_uploads` | `10` | `20` |
| `memory_limit` | `256M` (Verkleinern von Fotos bis ca. 40 Megapixel) | `128M` |

Ohne diese Werte lehnt PHP grössere Dateien ab; die App zeigt dann «Eine Datei ist zu gross» bzw.
«Die Dateien sind insgesamt zu gross».

## Aufbau

```
public/      einziger öffentlicher Ordner
  index.php  Einstieg, alle Anfragen, Health-Check /metrics, HSTS
  assets/    CSS, JS, Barlow-Schrift (lokal, SIL OFL), Logo
src/
  App.php            Controller (Formular, Absenden, Bestätigung)
  Config.php         Einstellungen aus Umgebungsvariablen
  Order/             Antrag, Validierung, Betrag (Schweizer Format), Upload, Anhänge verkleinern
  Security/          Signer (signierte Links)
  Http/              Response mit Sicherheits-Headern (CSP), Health
templates/   HTML-Templates
lang/        Texte DE / FR / EN
tests/       PHPUnit
```
