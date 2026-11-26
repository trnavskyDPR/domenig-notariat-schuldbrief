# Schuldbriefbestellung – Domenig & Partner Notariat AG

Online-Antrag für Schuldbriefbestellungen unter **https://schuldbrief.domenig.law**
(ersetzt das Jotform-Formular). Die Bank füllt das Formular aus; das Notariat erhält alle
Angaben per Mail, die Bank eine Eingangsbestätigung. Das Tool speichert **keine** Antragsdaten.

> Stand: Schritte a–d umgesetzt (Formular, Upload der Ausweiskopien, Mailversand über
> Microsoft Graph, Spamschutz). **Noch nicht im Modus `test` erprobt** – wartet auf die
> Entra-App von InfoPro. Für den Livegang die **Checkliste** unten abarbeiten.
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
| `GRAPH_TENANT_ID` | – | Verzeichnis-ID (Mandant) von Entra ID. In `test`/`live` Pflicht |
| `GRAPH_CLIENT_ID` | – | Anwendungs-ID der App «Schuldbriefbestellung (Atoll)». In `test`/`live` Pflicht |
| `GRAPH_CLIENT_SECRET` | – | **Geheim.** Clientschlüssel der App (LastPass). Nur in Atoll bzw. der Server-`.env`, nie im Git. Läuft nach 24 Monaten ab |
| `TEST_MAILBOX` | `test-notariat@domenig.law` | Im Modus `test` Absender **und** Empfänger aller Mails |
| `GRAPH_TIMEOUT_SECONDS` | `10` | Wartezeit auf Microsoft |
| `SENDER_MAILBOX` | `notariat@domenig.law` | Absender aller Mails (im Modus `test`: `TEST_MAILBOX`) |
| `INTERNAL_RECIPIENTS` | `info@…,notariat@…,schneider@…` | Wer bei jedem Antrag eine Mail erhält, je ein eigenes Exemplar (im Modus `test`: nur `TEST_MAILBOX`) |
| `ID_COPY_RECIPIENTS` | `info@…,notariat@…,schneider@…` | Wer davon die Ausweiskopien als Anhang erhält. Muss in `INTERNAL_RECIPIENTS` stehen. Die übrigen erhalten die Mail ohne Anhang |
| `RATE_LIMIT_PER_HOUR` | `10` | Höchstzahl Absendeversuche pro IP-Adresse und Stunde, `0` = kein Limit |

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

## Was beim Absenden passiert (Modus `test` und `live`)

1. Alle Eingaben und Dateien werden geprüft (siehe Formular und Upload).
2. **Interne Mail** von `SENDER_MAILBOX` an `INTERNAL_RECIPIENTS` (info@, notariat@, schneider@),
   Betreff «Schuldbriefbestellung – <Grundbuchblatt-Nr.> – <Adresse Grundstück>», mit allen Angaben
   und den Ausweiskopien. «Antworten» geht direkt an die Ansprechperson der Bank. Sind die Anhänge
   zusammen grösser als die Graph-Grenze, kommen mehrere Mails mit «(Teil 1 von 2)» usw.
3. **Eingangsbestätigung an die Bank** in ihrer Sprache (DE/FR/EN), ohne Anhänge. Inhalt: Grundbuchblatt-Nr.
   und Adresse als Referenz, Anzahl Ausweiskopien, Gebührenhinweis, Kontakt. Kein Betrag, keine
   Bankangaben (falls die E-Mail-Adresse vertippt ist). «Antworten» geht an `CONTACT_EMAIL`.
4. **Bestätigungsseite** ohne Antragsdaten.

**Die interne Mail ist das einzige Ergebnis** (keine Datenbank, kein Kalender). Schlägt sie fehl,
sieht die Bank das Formular mit allen Eingaben wieder, eine Meldung «konnte leider nicht übermittelt
werden» mit Telefonnummer, und es geht **keine** Bestätigung hinaus. Werden die Ausweiskopien auf
mehrere Mails verteilt und bricht der Versand mittendrin ab, können nach erneutem Absenden einzelne
Teile doppelt ankommen. Schlägt nur die Bestätigung fehl, ist der Antrag trotzdem angekommen; die
Bestätigungsseite sagt der Bank, dass keine Mail kam.

Alle Mails werden mit `saveToSentItems=false` gesendet: keine zusätzliche Kopie in «Gesendete
Elemente». Im Modus `test` gehen **alle** Mails ans Testpostfach (auch die Bestätigung) und die
Betreffs beginnen mit «[TEST]».

### Microsoft Graph

- Eigene Entra-App «Schuldbriefbestellung (Atoll)», eingerichtet durch InfoPro. In Entra **keine**
  API-Berechtigungen.
- Die Rechte vergibt Exchange Online über «RBAC for Applications»: nur die Rolle
  `Application Mail.Send`, beschränkt auf den Management Scope «Schuldbriefbestellung Postfaecher»
  (notariat@, test-notariat@). **Keine** Kalenderrechte. Empfänger (info@, schneider@) müssen nicht
  im Scope sein.
- Die App kann nur Mails senden – keine Postfächer lesen.

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

## Spamschutz

- **Fallenfeld** «website»: unsichtbar für Menschen; Bots füllen es aus → abgewiesen.
- **Mindest-Ausfüllzeit:** Wer das Formular in unter 3 Sekunden absendet, wird abgewiesen. Das
  Formular trägt dafür einen signierten Zeitstempel (2 Tage gültig).
- **Limit pro IP:** höchstens `RATE_LIMIT_PER_HOUR` (Standard 10) Absendeversuche pro IP-Adresse
  und Stunde. Gespeichert wird nur ein nicht umkehrbarer Code (HMAC) der IP-Adresse mit den
  Zeitpunkten, im temporären Ordner des Containers; Einträge verfallen nach einer Stunde. Hinter
  dem Atoll-Gateway zählt der letzte öffentliche Eintrag im Header `X-Forwarded-For`. Sieht die App
  nur eine interne Adresse, gilt für diese Anfrage kein Limit – so werden nie alle Besucher
  gemeinsam gesperrt. (Gleiches Vorgehen wie im Beglaubigungstool, abgesegnet 17.11.2026.)
- Bei allen Spam-Meldungen bleiben die Eingaben erhalten; nur die Dateien müssen neu gewählt werden.
- Kein reCAPTCHA, keine Drittanbieter.

## Links für die Website (Statamic)

| Website-Sprache | Link |
|---|---|
| Deutsch | `https://schuldbrief.domenig.law/?lang=de` |
| Französisch | `https://schuldbrief.domenig.law/?lang=fr` |
| Englisch | `https://schuldbrief.domenig.law/?lang=en` |

Das Tool wird verlinkt, nicht eingebettet: Einbetten per iframe ist aus Sicherheitsgründen
gesperrt (`frame-ancestors 'none'`).

## Betrieb auf Atoll

Die App läuft als Docker-Container auf **Atoll** (Eutima), Environment **Production**, Branch
`main`, Playbook «Webapp» (Profil «Generic»). Atoll baut das `Dockerfile` und prüft den
Health-Check `/metrics` auf Port 8000.

**Voraussetzung:** Das Repo liegt in der Organisation `domenig-partner`
(`domenig-partner/domenig-notariat-schuldbrief`) – Atoll ist mit der Organisation verbunden.

### Einrichten (einmalig)

1. Atoll → neues Environment **Production**, Branch `main`, Deployment-Host wählen,
   eigene Domain `schuldbrief.domenig.law`. Atoll zeigt dann die **CNAME-Anweisung** für den DNS.
   Weicht sie vom Eintrag ab, den InfoPro gesetzt hat («gleiches Ziel wie
   beglaubigungstermin.domenig.law»), InfoPro den richtigen Wert schicken.
2. **Variablen** setzen (Production → Variablen). Atoll erwartet **jeden** Schlüssel aus
   `.env.example` und blockiert das Deployment, solange einer leer ist:

   | Variable | Wert |
   |---|---|
   | `APP_MODE` | zuerst `test`, nach dem Praxistest `live` |
   | `APP_SECRET` | neu erzeugen: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` – **nicht** den des Beglaubigungstools übernehmen |
   | `GRAPH_TENANT_ID` | `ae4b51b9-1564-4bd2-a852-6100861922ec` |
   | `GRAPH_CLIENT_ID` | Anwendungs-ID der neuen App (von InfoPro) |
   | `GRAPH_CLIENT_SECRET` | geheimer Clientschlüssel (LastPass) |
   | alle übrigen | Werte aus `.env.example` übernehmen |

3. Deployen, Seite aufrufen, Testantrag (siehe Checkliste).

### Laufender Betrieb

- **Neue Version:** Pull Request auf `main` mergen → Atoll baut neu (bei Production mit
  Freigabe). Nach dem Deployment die Seite kurz aufrufen.
- **Logs:** in Atoll beim Environment. Zeilen der App beginnen mit `[schuldbrief]` und enthalten
  nie Antragsdaten.

### Entra-ID-App und Exchange (einmalig, durch InfoPro)

Neue Apps, Postfächer oder Rechte immer über den InfoPro-Support.

| Was | Wert |
|---|---|
| App-Name | Schuldbriefbestellung (Atoll) |
| Anwendungs-ID (Client) | _nach Einrichtung durch InfoPro eintragen_ |
| Verzeichnis-ID (Tenant) | `ae4b51b9-1564-4bd2-a852-6100861922ec` |
| Besitzer | adm-trnavsky@domenig.law |
| API-Berechtigungen in Entra | **keine**, kein Admin-Consent |
| Exchange RBAC for Applications | nur `Application Mail.Send` |
| Management Scope | «Schuldbriefbestellung Postfaecher»: notariat@, test-notariat@ |

Empfänger von Mails (info@, schneider@) müssen **nicht** im Scope sein.

### Geheimen Schlüssel erneuern (alle 24 Monate)

1. Entra → App-Registrierungen → *Besitzte Anwendungen* → «Schuldbriefbestellung (Atoll)»
   → Zertifikate & Geheimnisse → **Neuer geheimer Clientschlüssel** (24 Monate).
2. Wert in LastPass ablegen, in Atoll `GRAPH_CLIENT_SECRET` ersetzen, neu deployen.
3. Testantrag senden, dann den **alten** Schlüssel in Entra löschen.
4. Neues Ablaufdatum in den Kalender eintragen (Erinnerung 1 Monat vorher).

Läuft der Schlüssel ab, sieht die Bank beim Absenden «konnte leider nicht übermittelt werden»
mit Telefonnummer, und im Log steht `invalid_client`.

### Fehlersuche

| Im Log | Bedeutung | Lösung |
|---|---|---|
| `ConfigException – GRAPH_TENANT_ID …` | Variable fehlt oder ist falsch | in Atoll prüfen |
| `ConfigException – APP_SECRET …` | Schlüssel fehlt oder ist zu kurz | in Atoll setzen (mind. 32 Zeichen) |
| `ConfigException – ID_COPY_RECIPIENTS …` | Ausweiskopie-Empfänger nicht in `INTERNAL_RECIPIENTS` | Listen in Atoll angleichen |
| `Graph HTTP 401 invalid_client` | Schlüssel falsch oder abgelaufen | Schlüssel erneuern (oben) |
| `Graph HTTP 403 ErrorAccessDenied` | Absender-Postfach nicht im Exchange-Scope (oder Rechte noch nicht aktiv: bis 2 h nach Einrichtung) | warten bzw. InfoPro |
| `ErrorMailboxNotFound` | Postfachadresse falsch | `SENDER_MAILBOX` / `TEST_MAILBOX` prüfen |
| `ErrorMessageSizeExceeded` / `Graph HTTP 413` | Mail zu gross | sollte nicht vorkommen (Grenze 2.8 MB pro Mail) – melden |
| `Netzwerkfehler (curl 6)` / `(curl 28)` | Microsoft nicht erreichbar bzw. Zeitüberschreitung | später erneut; hält es an: Atoll |
| `Graph HTTP 429` / `503` | Microsoft überlastet (die App versucht es einmal erneut) | später erneut |
| `Interne Mail fehlgeschlagen` | Antrag **nicht** angekommen, Bank sah eine Fehlermeldung | Ursache in derselben Zeile; Bank meldet sich ggf. telefonisch |
| `Bestätigung an Bank fehlgeschlagen` | Antrag angekommen, Bestätigung an die Bank nicht | Bank kurz informieren |

## Livegang-Checkliste

1. [ ] **Entscheid des Vorgesetzten zum Upload** der Ausweiskopien (Datum in CLAUDE.md eintragen)
2. [ ] Juristisch geklärt: Muss die Bank bestätigen, dass die Grundeigentümer mit der Weitergabe
       ihrer Ausweiskopien einverstanden sind? (ggf. Text der Datenschutz-Checkbox anpassen)
3. [ ] Französisch und Englisch von Muttersprachigen geprüft (`lang/fr.php`, `lang/en.php`)
4. [ ] Datenschutzerklärung auf domenig.law ergänzt (Schuldbrief-Formular, Ausweiskopien Dritter,
       Microsoft 365, Atoll), Links `PRIVACY_URL_DE/FR/EN` gesetzt
5. [ ] InfoPro: Entra-App und Exchange-Rechte eingerichtet; Schlüssel erstellt und in LastPass;
       Anwendungs-ID oben eingetragen
6. [ ] Repo in `domenig-partner` übertragen, `aufbau` nach `main` gemergt
7. [ ] Atoll: Environment Production, Variablen mit `APP_MODE=test` (siehe oben)
8. [ ] DNS-CNAME `schuldbrief.domenig.law` gesetzt (InfoPro), Domain in Atoll «Verified»
9. [ ] Testanträge im Modus `test` in allen drei Sprachen: mit Foto und PDF, einmal mit mehreren
       grösseren Dateien («Teil 1 von 2»). In test-notariat@ kommen interne Mail und Bestätigung an
10. [ ] info@, notariat@ und Christian Schneider wissen, dass Anträge neu so per Mail kommen
11. [ ] In Atoll `APP_MODE=live` setzen, neu deployen
12. [ ] Ein echter Probeantrag mit eigener Adresse → alle drei Postfächer prüfen
13. [ ] Links auf der Website (Statamic) umstellen, Jotform-Formular deaktivieren
14. [ ] Ablaufdatum des Schlüssels im Kalender

## Datenschutz

- Keine Datenbank, keine gespeicherten Dateien, keine Session, keine Cookies.
- Antragsdaten werden nur während der Anfrage im Arbeitsspeicher verarbeitet und gehen
  ausschliesslich per Mail an das Notariat (und als Referenz an die Bank).
- **Ausweiskopien:** PHP legt hochgeladene Dateien zwingend kurz in den temporären Ordner. Die App
  liest sie sofort in den Arbeitsspeicher und löscht die temporären Dateien. Sie gehen
  ausschliesslich als Anhang der internen Mail an `ID_COPY_RECIPIENTS` hinaus – nicht an die Bank,
  nicht in Logs, nicht in «Gesendete Elemente». Der Dateiname der Bank wird nicht übernommen. Im
  Mock-Modus werden sie geprüft und verworfen.
- Die Bestätigung an die Bank enthält nur Grundbuchblatt-Nr. und Adresse als Referenz (kein
  Betrag, keine Bankangaben), falls die E-Mail-Adresse vertippt ist.
- Keine Antragsdaten in Logs. Die Bestätigungsseite erhält nur Sprache und Versandstatus in einem
  signierten Link.
- Keine Drittanbieter im Frontend (Schrift lokal, keine CDN, keine Analytics).
- Limit pro IP: nur ein nicht umkehrbarer Code der IP-Adresse, eine Stunde lang.
- Hinweis: Die Webserver-Logs von Atoll enthalten IP-Adressen. Aufbewahrungsdauer bei Eutima
  klären und in der Datenschutzerklärung erwähnen.

## Aufbau

```
public/      einziger öffentlicher Ordner
  index.php  Einstieg, alle Anfragen, Health-Check /metrics, HSTS
  assets/    CSS, JS, Barlow-Schrift (lokal, SIL OFL), Logo
src/
  App.php            Controller (Formular, Absenden, Bestätigung)
  Config.php         Einstellungen aus Umgebungsvariablen
  ServiceFactory.php wählt je nach APP_MODE: nichts senden (mock) oder Graph (test/live)
  Order/             Antrag, Validierung, Betrag (Schweizer Format), Upload, Anhänge verkleinern,
                     Mailversand (GraphOrderService)
  Graph/             GraphClient (Anmeldung, Wiederholung bei 429/503), GraphException
  Security/          Signer (signierte Links), SpamGuard, RateLimiter
  Http/              Response mit Sicherheits-Headern (CSP), curl-Client, Health
templates/   HTML-Templates
lang/        Texte DE / FR / EN
tests/       PHPUnit
```
