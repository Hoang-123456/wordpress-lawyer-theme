# Alternative: Echtzeit-Kalenderbuchung mit Nachricht & Datei in einem Schritt

Wird **gemeinsam mit dem Hauptansatz** (`projekt-kontaktformular-kanzlei.md`) im selben Theme (`kanzlei-theme`) umgesetzt, als zweites, wählbares Pattern (`patterns/contact-booking.php`) neben `patterns/contact-form.php`. Beide Patterns entstehen von Anfang an nebeneinander, nicht nacheinander – siehe im Hauptprojekt-Dokument den Abschnitt "Theme-Struktur: beide Ansätze werden von Anfang an gemeinsam integriert" für die genaue Ordnerstruktur und Kapselung.

## Grundidee

Statt des zweistufigen Ablaufs im Hauptprojekt (1. schlankes Formular mit Terminwunsch/Nachricht/Datei → 2. separate, noch offene Terminverhandlung falls der Wunschtermin nicht passt) läuft hier **alles in einem einzigen Schritt**:

1. Besucher sieht einen echten Kalender mit tatsächlich freien Zeitslots (wie Calendly)
2. Wählt direkt einen freien Slot aus
3. Trägt im selben Formular Nachricht + Datei-Upload ein
4. Sendet ab – der Termin ist damit **sofort verbindlich gebucht**, keine Zusage/Absage-Runde mehr nötig

Das schließt die im Hauptprojekt identifizierte "Terminverhandlungs-Lücke" architektonisch, statt sie über eine separate Policy-Entscheidung (Telefon/Magic-Link/etc.) zu lösen – es gibt schlicht keine Verhandlung mehr, weil von vornherein nur echte freie Slots wählbar sind.

## Wichtiger Trade-off, der zuerst geklärt werden muss

**Das ist keine rein technische Entscheidung, sondern berührt, wie der Anwalt arbeiten möchte:**

Im Hauptprojekt sichtet der Anwalt jede Anfrage im Backend, *bevor* er sich auf einen Termin festlegt – er kann Anfragen ablehnen, filtern, priorisieren, bevor Kalenderzeit blockiert wird. Bei der Instant-Booking-Variante hier bucht **jeder x-beliebige Besucher sofort und automatisch** einen echten Termin, ohne dass der Anwalt vorher gegenprüfen kann, ob die Anfrage überhaupt sinnvoll/seriös ist. Das ist bei einer Kanzlei ein relevanter Unterschied zu z. B. einem Frisörsalon – dort will man i. d. R. jede Anfrage selbst bewerten können, bevor man sich verbindlich Zeit reserviert.

**Update nach genauerer Recherche:** FluentBooking bietet seit Version 1.4.0 eine eingebaute **"Manual Confirmation"**-Funktion – der Anwalt kann eine Buchung so konfigurieren, dass sie erst nach seiner manuellen Bestätigung verbindlich wird, statt automatisch sofort zu fixieren. Der oben beschriebene Trade-off lässt sich damit auflösen: Instant-Booking und "Anwalt sichtet vorher" schließen sich nicht mehr gegenseitig aus, das ist eine Einstellung. Ob diese Option in der kostenlosen Version enthalten ist oder Pro voraussetzt, sollte vor Projektstart im aktuellen Backend geprüft werden.

## Technische Umsetzung

**Ein Plugin statt zwei – Korrektur gegenüber einer früheren Annahme:**

- **FluentBooking** übernimmt nicht nur die Kalenderlogik (Verfügbarkeit, Zeitzonen, Konflikterkennung, Slot-Reservierung), sondern bietet über eigene "Booking Questions" auch die restlichen Felder direkt an:
  - **File Field** (seit Version 1.5.22) – Datei-Upload als Buchungsfrage, mit einstellbarem Größen-/Anzahl-/Format-Limit
  - **Textarea** – für die Nachricht
  - **Terms & Conditions** – HTML-fähiges Consent-Feld mit Link zur Datenschutzerklärung, als Pflichtfeld setzbar (entspricht der Consent-Checkbox aus dem Hauptprojekt)
- **Fluent Forms ist damit nicht nötig** – ein einzelnes Plugin deckt Kalender + Nachricht + Datei + Consent komplett ab, statt zwei Plugins zu kombinieren. Das reduziert die Angriffsfläche gegenüber einer ursprünglich angedachten Zwei-Plugin-Lösung.
- **Zu prüfender Vorbehalt:** Die kostenlose FluentBooking-Version begrenzt laut Anbieter-Blog die Anzahl gleichzeitig nutzbarer Custom-Questions ("only a limited number of questions can be set with the free version"). Ob File-Field, Textarea und Terms & Conditions gemeinsam noch in der Free-Version nutzbar sind oder zusätzlich zur ohnehin für Kalender-Sync nötigen Pro-Version vorausgesetzt werden, ließ sich nicht abschließend klären – vor Projektstart direkt im aktuellen Backend gegenchecken.
- Das ist weiterhin **nicht** die "kein Plugin, weil kleine Angriffsfläche"-Logik aus dem Hauptprojekt – hier ist der Umfang der benötigten Funktionalität (Verfügbarkeitsberechnung, Zeitzonen, Slot-Konflikte) groß genug, dass ein etabliertes Plugin sinnvoller ist als Eigenbau. Bewusst andere Abwägung als beim schlanken 5-Felder-Formular, kein Widerspruch dazu.

**Kostenpunkt:** Echte Kalender-Synchronisation (Google/Outlook/Apple Calendar) ist laut aktuellem Stand nur in der **FluentBooking-Pro-Version** enthalten, die kostenlose Version bietet nur einen plugin-internen Kalender ohne externen Abgleich. Für einen Anwalt, der seinen bestehenden Kalender nicht doppelt pflegen möchte, ist die Pro-Version voraussichtlich nötig.

## Sicherheit & DSGVO – gleiche Prinzipien wie im Hauptprojekt, andere Umsetzung

Die inhaltlichen Anforderungen aus dem Hauptprojekt gelten unverändert, müssen hier aber über Plugin-Konfiguration statt eigenen Code umgesetzt werden:

- **Datei-Uploads bleiben lokal** – FluentBooking-Uploads landen im eigenen `wp-content/uploads`-Verzeichnis auf dem Hetzner-Server, keine externe Cloud-Anbindung. Trotzdem: Verzeichnis-Zugriffsschutz, Typ-Whitelist und ggf. authentifizierter Download-Endpunkt müssen genauso konfiguriert werden wie im Hauptprojekt beschrieben – das Plugin bringt das nicht automatisch in der benötigten Strenge mit
- **Metadaten-only-Mail an Google Workspace** – die FluentBooking-E-Mail-Benachrichtigungen müssen exakt so beschnitten werden wie im Hauptprojekt (nur Name, Terminzeit, kein Nachrichtentext, kein Datei-Link), inkl. Reply-To auf die Besucher-Adresse
- **§ 203 StGB / Berufsgeheimnisträger-Thematik** gilt identisch – ändert sich durch Plugin- statt Custom-Code-Ansatz nicht. Sensible Inhalte dürfen weiterhin nicht über Google Workspace laufen
- **Consent-Checkbox, Löschkonzept, IP-Logging** – gleiche Anforderungen wie im Hauptprojekt, technisch über FluentBooking-eigene Optionen (Terms & Conditions-Feld) bzw. Custom-Hooks umzusetzen statt komplett selbst geschriebenem Code
- **Login-Härtung für den Anwalt-Zugang** (Rate-Limiting, 2FA, eingeschränktes Konto) – unverändert relevant, unabhängig vom Formular-Ansatz

**Unterschied zum Hauptprojekt:** Hier kommt ein vollständiges, funktionsreiches Plugin zum Einsatz statt eines schlanken Custom-Formulars – das bedeutet mehr Angriffsfläche und mehr Update-Abhängigkeit von einem Drittanbieter als beim Hauptprojekt, aber auch deutlich weniger Eigenentwicklungs- und Wartungsaufwand für die komplexe Verfügbarkeitslogik (Zeitzonen, Konflikterkennung, Pufferzeiten). Diese Abwägung fällt hier anders aus als im Hauptprojekt, weil die Aufgabe selbst (echte Kalenderverfügbarkeit) ungleich komplexer ist als ein einfaches 5-Felder-Formular.

## Theme-Implikation

`patterns/contact.php` aus dem bestehenden `kanzlei-theme` wird für den Custom-Code-Ansatz nach `patterns/contact-form.php` aufgeteilt (schmale Spalte, ca. 67% Breite neben Kontaktkarten – passt für das schlanke Formular). Für dieses eigene Pattern `patterns/contact-booking.php` braucht es aber ein **eigenes, breiteres Layout**, weil ein Kalender-UI mit Zeitslot-Auswahl mehr horizontale Breite braucht, um übersichtlich zu bleiben:

- Eigenes, breiteres Pattern für den Buchungsbereich (ggf. volle Breite statt zweispaltig neben Kontaktkarten)
- Kontaktkarten (Telefon/E-Mail/Adresse/Bürozeiten) ggf. oberhalb oder unterhalb des Kalenders statt daneben
- Gleiche allgemeine Theme-Standards wie im Hauptprojekt-Audit gelten unverändert (ABSPATH-Guards, Theme-Supports-Baseline, lokale Fonts, WCAG AA, `theme.json`-Variablen statt Hex-Literale, vollständiger Theme-Header)

## Theme-Struktur & Dokumentation

Dieser Ansatz wird als zweites, wählbares Pattern **im selben Theme** wie das Hauptprojekt gebaut. Die Kapselungs-Struktur (`inc/contact-form-booking-hooks.php`, defensiv mit `class_exists()`-Check) und die README-Dokumentationspflicht sind identisch zum Hauptprojekt beschrieben – siehe dort den Abschnitt "Theme-Struktur: beide Ansätze werden von Anfang an gemeinsam integriert" und "Dokumentationspflicht bei Fertigstellung". Hier nicht dupliziert, um Divergenz zwischen den beiden Dokumenten zu vermeiden.

## Offene Punkte vor Projektstart

1. ~~Grundsatzfrage klären: Automatische Instant-Buchung akzeptabel, oder Bestätigung durch den Anwalt?~~ **Gelöst:** FluentBookings "Manual Confirmation"-Funktion (seit v1.4.0) erlaubt beides – muss nur aktiviert werden
2. Prüfen, ob Manual Confirmation sowie File-Field/Textarea/Terms-&-Conditions-Fragen zusammen in der kostenlosen Version verfügbar sind oder Pro voraussetzen
3. Kostenvergleich: FluentBooking Pro (für Kalender-Sync, ggf. weitere Frage-Typen) vs. Aufwand des Custom-Code-Ansatzes aus dem Hauptprojekt
4. Trotz Manual Confirmation: Wie soll mit Spam-/Fake-Buchungsanfragen umgegangen werden, die Slots vorläufig blockieren, bis der Anwalt sie manuell ablehnt? (Anti-Spam-Maßnahmen weiterhin relevant, auch wenn die automatische Fixierung durch Manual Confirmation entschärft ist)
