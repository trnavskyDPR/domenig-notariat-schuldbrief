<?php

// Deutsch (Schweiz) – immer «ss», nie Eszett. Platzhalter in {geschweiften Klammern}.
// Sie-Form gegenüber der Bank (Ansprechperson füllt das Formular aus).

return [
    'meta.html_lang' => 'de-CH',

    'org.name' => 'Domenig & Partner Notariat AG',
    'app.title' => 'Antrag Schuldbriefbestellung',
    'app.intro' => 'Mit diesem Formular bestellen Sie bei uns einen Schuldbrief. Sie erhalten anschliessend eine Eingangsbestätigung per E-Mail.',
    'form.required_note' => 'Alle mit {star} markierten Felder sind Pflichtfelder.',

    'a11y.skip' => 'Zum Inhalt springen',
    'a11y.new_window' => '(öffnet in neuem Fenster)',
    'lang.label' => 'Sprache wählen',

    'test.banner.mock' => 'Testmodus: Es wird nichts übermittelt und keine E-Mail versendet.',
    'test.banner.test' => 'Testmodus: Alle E-Mails gehen an das Testpostfach.',

    // Angaben zum Grundstück
    'section.property' => 'Angaben zum Grundstück',
    'field.grundbuchblatt' => 'Grundbuchblatt-Nummer',
    'field.grundbuchblatt.hint' => 'Wie im Grundbuchauszug angegeben, zum Beispiel 1234.',
    'field.adresse' => 'Adresse des Grundstücks',
    'field.adresse.hint' => 'Strasse, Hausnummer, PLZ und Ort.',
    'field.rang' => 'Rang des Grundpfandrechts',
    'field.rang.hint' => 'Zum Beispiel «1. Rang» oder «2. Rang, im Nachgang zu CHF 300\'000».',
    'field.betrag' => 'Höhe des Schuldbriefes (in CHF)',
    'field.betrag.hint' => 'In ganzen Franken, zum Beispiel 500\'000.',

    // Bank
    'section.bank' => 'Angaben zur Bank',
    'field.glaeubigerin' => 'Gläubigerin (Firma)',
    'field.bank' => 'Name bestehende Bank',

    // Ansprechperson
    'section.contact' => 'Ansprechperson (Bank)',
    'field.vorname' => 'Vorname',
    'field.nachname' => 'Nachname',
    'field.email' => 'E-Mail',
    'field.email.hint' => 'An diese Adresse senden wir die Eingangsbestätigung.',
    'field.telefon' => 'Telefonnummer',
    'field.vorwahl' => 'Ländervorwahl',
    'field.vorwahl.other' => 'Andere',
    'field.telefon.number' => 'Nummer',
    'field.telefon.hint' => 'Zum Beispiel 031 123 45 67. Bei «Andere» bitte mit Ländervorwahl, zum Beispiel +34 612 34 56 78.',

    // Ausweiskopien der Grundeigentümer
    'section.ausweis' => 'Ausweiskopien',
    'field.ausweis' => 'Ausweiskopien der Grundeigentümer',
    'field.ausweis.hint' => 'Bitte laden Sie gut lesbare Kopien von Pass oder ID aller Grundeigentümerinnen und Grundeigentümer hoch, bei einer ID-Karte Vorder- und Rückseite. Mehrere Dateien wählen Sie im selben Dialog gemeinsam aus. Erlaubt sind höchstens {files} Dateien: Fotos als JPG oder PNG (je bis {max} MB) oder PDF (je bis {pdfmax} MB, am besten ein PDF pro Person). Fotos im iPhone-Format HEIC werden nicht angenommen.',
    'field.ausweis.privacy' => 'Die Dateien werden bei uns nicht gespeichert. Sie werden nur zusammen mit Ihrem Antrag per E-Mail an das Notariat übermittelt.',

    // Gebühren (Text vom Notariat vorgegeben)
    'fees.heading' => 'Hinweise zu den Gebühren',
    'fees.text' => 'Unsere Gebühren betragen zwischen CHF 1\'000.- und 1\'500.-. Dazu kommen noch die Gebühren vom Grundbuchamt im Umfang von ca. CHF 150.00 (abhängig von den konkreten Eintragungen).',

    'field.datenschutz' => 'Ich habe die {link} gelesen und bin einverstanden, dass die Angaben in diesem Formular und die hochgeladenen Ausweiskopien zur Bearbeitung des Antrags verwendet werden.',
    'field.datenschutz.link' => 'Datenschutzerklärung',
    'submit' => 'Antrag absenden',
    'submit.busy' => 'Wird gesendet …',

    // Fehlermeldungen
    'errors.summary' => 'Bitte prüfen Sie Ihre Angaben',
    'error.prefix' => 'Fehler:',
    'error.too_long' => 'Die Eingabe ist zu lang (höchstens {max} Zeichen).',
    'error.grundbuchblatt.required' => 'Bitte geben Sie die Grundbuchblatt-Nummer ein.',
    'error.grundbuchblatt.invalid' => 'Bitte prüfen Sie die Grundbuchblatt-Nummer, zum Beispiel 1234.',
    'error.adresse.required' => 'Bitte geben Sie die Adresse des Grundstücks ein.',
    'error.adresse.invalid' => 'Bitte prüfen Sie die Adresse des Grundstücks.',
    'error.rang.required' => 'Bitte geben Sie den Rang des Grundpfandrechts ein.',
    'error.rang.invalid' => 'Bitte prüfen Sie die Angabe zum Rang.',
    'error.betrag.required' => 'Bitte geben Sie die Höhe des Schuldbriefes ein.',
    'error.betrag.invalid' => 'Bitte geben Sie den Betrag in ganzen Franken ein, zum Beispiel 500\'000.',
    'error.betrag.too_small' => 'Der Betrag muss mindestens CHF {min} betragen.',
    'error.betrag.too_big' => 'Bitte prüfen Sie den Betrag (höchstens CHF {max}).',
    'error.glaeubigerin.required' => 'Bitte geben Sie die Gläubigerin (Firma) ein.',
    'error.glaeubigerin.invalid' => 'Bitte prüfen Sie die Angabe zur Gläubigerin.',
    'error.bank.required' => 'Bitte geben Sie den Namen der bestehenden Bank ein.',
    'error.bank.invalid' => 'Bitte prüfen Sie den Namen der bestehenden Bank.',
    'error.vorname.required' => 'Bitte geben Sie den Vornamen der Ansprechperson ein.',
    'error.vorname.invalid' => 'Bitte verwenden Sie für den Vornamen nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe.',
    'error.nachname.required' => 'Bitte geben Sie den Nachnamen der Ansprechperson ein.',
    'error.nachname.invalid' => 'Bitte verwenden Sie für den Nachnamen nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe.',
    'error.email.required' => 'Bitte geben Sie die E-Mail-Adresse der Ansprechperson ein.',
    'error.email.invalid' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein, zum Beispiel name@bank.ch.',
    'error.vorwahl.invalid' => 'Bitte wählen Sie eine Ländervorwahl.',
    'error.telefon.required' => 'Bitte geben Sie die Telefonnummer der Ansprechperson ein.',
    'error.telefon.invalid' => 'Bitte geben Sie eine gültige Telefonnummer ein, zum Beispiel 031 123 45 67.',
    'error.telefon.needs_code' => 'Bitte geben Sie die Nummer mit Ländervorwahl ein, zum Beispiel +34 612 34 56 78.',
    'error.ausweis.required' => 'Bitte laden Sie die Ausweiskopien der Grundeigentümer hoch.',
    'error.ausweis.too_many' => 'Bitte laden Sie höchstens {max} Dateien hoch.',
    'error.ausweis.too_big' => 'Eine Datei ist zu gross (höchstens {max} MB pro Datei).',
    'error.ausweis.too_big_mail' => 'Eine Datei ist zu gross für den Versand (höchstens {max} MB). Bitte laden Sie kleinere PDFs hoch, zum Beispiel ein PDF pro Person, oder Fotos.',
    'error.ausweis.type' => 'Dieses Dateiformat wird nicht angenommen. Erlaubt sind JPG, PNG und PDF.',
    'error.ausweis.invalid' => 'Eine Datei ist leer oder beschädigt. Bitte wählen Sie sie erneut aus.',
    'error.ausweis.failed' => 'Eine Datei konnte nicht übertragen werden. Bitte versuchen Sie es erneut.',
    'error.ausweis.again' => 'Bitte wählen Sie die Dateien erneut aus. Aus Datenschutzgründen merkt sich das Formular sie nicht.',
    'error.ausweis.post_too_big' => 'Die Dateien sind insgesamt zu gross. Bitte füllen Sie das Formular erneut aus und wählen Sie kleinere Dateien (höchstens {max} MB pro Datei).',
    'error.datenschutz.required' => 'Bitte bestätigen Sie, dass Sie die Datenschutzerklärung gelesen haben.',
    'field.honeypot' => 'Bitte dieses Feld leer lassen',
    'error.spam' => 'Ihr Antrag konnte nicht verarbeitet werden. Bitte senden Sie das Formular erneut oder rufen Sie uns an: {phone}',
    'error.form_expired' => 'Das Formular war zu lange geöffnet. Bitte prüfen Sie Ihre Angaben und senden Sie es erneut.',
    'error.rate_limit' => 'Es wurden zu viele Anträge in kurzer Zeit gesendet. Bitte versuchen Sie es in einer Stunde erneut oder rufen Sie uns an: {phone}',
    'error.generic.title' => 'Es ist ein Fehler aufgetreten',
    'error.generic' => 'Ihr Antrag konnte nicht verarbeitet werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an: {phone}',
    'error.unavailable' => 'Das Antragsformular ist im Moment nicht verfügbar. Bitte versuchen Sie es später erneut oder rufen Sie uns an: {phone}',
    'error.method' => 'Diese Anfrage ist nicht zulässig.',

    // Bestätigungsseite
    'confirm.title' => 'Vielen Dank – Ihr Antrag ist eingegangen',
    'confirm.text' => 'Wir haben Ihnen eine Eingangsbestätigung per E-Mail gesendet. Falls sie nicht ankommt, prüfen Sie bitte Ihren Spam-Ordner.',
    'confirm.mock' => 'Testmodus: Es wurde nichts übermittelt und keine E-Mail versendet.',
    'confirm.test' => 'Testmodus: Antrag und Eingangsbestätigung gingen an das Testpostfach.',
    'confirm.mail_failed' => 'Ihr Antrag ist bei uns eingegangen. Die Eingangsbestätigung per E-Mail konnte leider nicht versendet werden.',
    'confirm.next' => 'Wir prüfen Ihren Antrag und melden uns bei Ihnen.',
    'confirm.questions' => 'Fragen zu Ihrem Antrag? Sie erreichen uns unter {phone} oder per E-Mail an {email}.',
    'confirm.again' => 'Weiteren Antrag erfassen',
    'confirm.invalid.title' => 'Seite nicht mehr gültig',
    'confirm.invalid' => 'Diese Bestätigungsseite ist abgelaufen. Ihr Antrag ist davon nicht betroffen – die Eingangsbestätigung haben Sie per E-Mail erhalten.',
    'back' => 'Zum Antragsformular',

    // Versand (Schritt c)
    'error.send_failed' => 'Ihr Antrag konnte leider nicht übermittelt werden. Ihre Angaben sind unten noch vorhanden: Bitte wählen Sie die Ausweiskopien erneut aus und senden Sie den Antrag in einigen Minuten nochmals. Oder rufen Sie uns an: {phone}',
    'mail.files_count' => '{count} Datei(en)',

    // Interne Mail ans Notariat (immer Deutsch)
    'mail.internal.subject' => 'Schuldbriefbestellung – {grundbuchblatt} – {adresse}',
    'mail.internal.intro' => 'Über das Online-Formular ist ein neuer Antrag auf Schuldbriefbestellung eingegangen.',
    'mail.internal.betrag' => 'Höhe des Schuldbriefes',
    'mail.internal.name' => 'Name',
    'mail.internal.lang' => 'Sprache',
    'mail.internal.attachment_here' => 'Die Ausweiskopien der Grundeigentümer sind im Anhang.',
    'mail.internal.attachment_parts' => 'Wegen der Grösse sind sie auf {total} E-Mails mit demselben Betreff verteilt.',
    'mail.internal.attachment_elsewhere' => 'Die Ausweiskopien wurden separat an {recipients} gesendet.',
    'mail.internal.no_attachment' => 'Es wurden keine Ausweiskopien mitgeschickt.',
    'mail.internal.part' => '(Teil {n} von {total})',
    'mail.internal.reply' => '«Antworten» geht direkt an die Ansprechperson der Bank.',

    // Eingangsbestätigung an die Bank
    'mail.bank.subject' => 'Eingangsbestätigung Schuldbriefbestellung – Grundbuchblatt {grundbuchblatt}',
    'mail.bank.greeting' => 'Guten Tag {name}',
    'mail.bank.intro' => 'Vielen Dank für Ihren Antrag auf Schuldbriefbestellung. Wir bestätigen Ihnen den Eingang:',
    'mail.bank.closing' => 'Freundliche Grüsse',

    'footer.privacy' => 'Datenschutz',
];
