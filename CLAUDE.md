# Schuldbriefbestellung – Domenig & Partner Notariat AG

Online-Antrag für Schuldbriefbestellungen. Ersetzt das Jotform-Formular «Antrag
Schuldbriefbestellung». Die **Bank** (Ansprechperson) füllt das Formular aus und lädt die
Ausweiskopien der Grundeigentümer hoch. Das Notariat erhält alle Angaben per Mail, die Bank
eine Eingangsbestätigung.

Vorlage und Vorbild: `domenig-partner/domenig-notariat-termine` (Beglaubigungstermin).
Design, Aufbau, Sicherheit und Betrieb sind von dort übernommen – nur ohne Terminwahl und
ohne Kalender.

Ansprechperson: Trevor Trnavsky (Operation Support, ICT-Fachmann EFZ – kein
Vollzeit-Entwickler). Code einfach und gut kommentiert halten.

## Stack

PHP 8.1+ ohne Framework, Vanilla-JS, eigenes CSS. Einzige Erweiterungen: curl, gd.
Betrieb als Docker-Container auf **Atoll** (Eutima), Environment «Production» aus `main`.
Kein Build-Schritt ausser dem Dockerfile. Composer nur lokal für PHPUnit.
Repo vorerst im Account von Trevor, später Übertragung nach `domenig-partner/domenig-notariat-schuldbrief`.
Domain: `schuldbrief.domenig.law`.

```
public/      einziger öffentlicher Ordner (index.php, assets/)
src/         Logik: Order/ (Antrag, Validierung, Betrag), Security/, Http/
templates/   PHP-Templates (layout, form, confirmation, message)
lang/        de.php · fr.php · en.php – alle Texte
tests/       PHPUnit
```

## Harte Regeln – nie brechen

- **Keine Antragsdaten speichern.** Keine Datenbank, keine Dateien, keine Session,
  kein localStorage. Daten gehen nur per Mail weiter.
- **Keine Antragsdaten in Logs.** `error_log` nur über `App::logLine()`: Exception-Typ, Datei,
  Zeile, bei `ConfigException` der Name der Einstellung, bei `GraphException` HTTP-Status und
  Microsofts Fehlercode. Nie Werte, Schlüssel oder Eingaben.
  Exceptions nie mit Eingabewerten im Text werfen.
- **Datei-Upload nur für die Ausweiskopien der Grundeigentümer.** Entscheid des Vorgesetzten
  von Trevor Trnavsky: **ausstehend** (der Entscheid vom 06.11.2026, Variante B, gilt nur für
  das Beglaubigungstool). Ohne Entscheid kein Livegang mit Upload. Sonst kein Upload, keine
  weiteren Dateiarten ohne neuen Entscheid. Regeln wie im Beglaubigungstool:
  - Nur JPG, PNG, PDF; Typ am **Inhalt** erkennen, nie an Endung oder Browser-Typ.
  - Datei nur im **Arbeitsspeicher**, temporäre Datei sofort löschen. Nie auf Platte, nie in die
    Bestätigung an die Bank, nie in Logs. Der Originalname wird nicht übernommen.
  - Sie verlässt den Server **nur als Anhang der Mail ans Notariat**. Im Mock-Modus wird sie
    geprüft und verworfen.
  - Nach einem Formularfehler muss die Datei erneut gewählt werden (nichts wird zwischengespeichert).
- **Keine Fremdquellen im Frontend**: kein CDN, kein Google Fonts, keine Analytics,
  kein reCAPTCHA. Die CSP in `src/Http/Response.php` erzwingt das – nicht lockern.
  Keine Inline-Skripte oder Inline-Styles (CSP).
- **Keine Secrets im Code.** Alles über `.env` (in `.gitignore`) bzw. Umgebungsvariablen.
  Neue Variablen immer auch in `.env.example` und README dokumentieren.
- **Serverseitige Validierung** aller Eingaben in `src/Order/OrderValidator.php`. Die
  Browser-Validierung ist absichtlich aus (`novalidate`).
- **Microsoft Graph nur mit `Mail.Send`** (kein Kalender) über Exchange «RBAC for Applications»,
  eigene Entra-App «Schuldbriefbestellung (Atoll)», beschränkt auf notariat@ und test-notariat@.
  In Entra keine API-Berechtigungen. Einrichtung und Änderungen nur über InfoPro.

## Fachliche Regeln

- Alle Felder sind Pflicht. «Rang des Grundpfandrechts» ist Freitext.
- **Höhe des Schuldbriefes** in ganzen Franken, mindestens CHF 1'000 (`src/Order/Amount.php`).
  Eingabe tolerant (1'000, 1’000, 1 000, 1000.–, CHF 1'000), Ausgabe immer `1'250'000`.
- Gebührenhinweis vor dem Absenden, Text vom Notariat vorgegeben (`fees.text`), nicht umformulieren.
- **Jeder Antrag geht per Mail an alle internen Empfänger** – je ein eigenes Exemplar:
  info@domenig.law, notariat@domenig.law, schneider@domenig.law (`INTERNAL_RECIPIENTS`).
  Absender notariat@. Ausweiskopien an alle drei (`ID_COPY_RECIPIENTS`, Entscheid Trevor 26.11.2026).
  Betreff: «Schuldbriefbestellung – <Grundbuchblatt-Nr.> – <Adresse Grundstück>».
- **Die interne Mail ist das einzige Ergebnis** (kein Kalender, keine Datenbank). Schlägt sie
  fehl, sieht die Bank eine Fehlerseite mit Telefonnummer und erhält keine Bestätigung.
- **Bestätigung an die Bank** (E-Mail der Ansprechperson) in DE/FR/EN, ohne Anhänge, nur mit
  Grundbuchblatt-Nr. und Adresse als Referenz (kein Betrag, keine Bankangaben – falls die
  Adresse vertippt ist).
- Die Bestätigungsseite zeigt keine Antragsdaten; der Link enthält nur Sprache und Versandstatus.

## Sprache und Texte

- Alle Texte in `lang/*.php`, nie im Template hart codieren. Neue Schlüssel in **allen
  drei** Dateien anlegen – `tests/I18nTest.php` prüft das.
- Deutsch: Schweizer Hochdeutsch, **immer «ss», nie Eszett** (Test prüft das).
  Sie-Form gegenüber der Bank.
- Französisch und Englisch vor dem Livegang von Muttersprachigen prüfen lassen
  (Fachbegriffe: cédule hypothécaire, feuillet du registre foncier, mortgage note).

## Arbeitsweise

- In kleinen Schritten bauen, nach jedem Schritt Tests laufen lassen:
  `composer test` (bzw. `vendor/bin/phpunit`).
- Lokal starten: `php -S 127.0.0.1:8080 -t public` – ohne `.env` läuft der Mock-Modus.
- Barrierearm: jedes Feld mit `<label>`, Fehler per `aria-describedby`,
  Fehlerübersicht oben, Tastaturbedienung, alles funktioniert auch ohne JavaScript.
- Unsicherheit benennen statt raten. Bei fehlenden Angaben bei Trevor nachfragen.

## Stand

- [x] a) Formular, Validierung (inkl. Betrag im Schweizer Format), DE/FR/EN, Mock-Modus
- [ ] b) Upload der Ausweiskopien (mehrere Dateien), Verkleinern grosser Fotos
- [ ] c) Microsoft Graph: interne Mails (Aufteilung bei ~3 MB), Bestätigung an die Bank, Testmodus
- [ ] d) Spamschutz (Fallenfeld, Mindest-Ausfüllzeit, Limit pro IP), README mit Betrieb,
  Schlüsselwechsel, Fehlersuche und Livegang-Checkliste
