# Projekt: Kontaktformular mit Terminbuchung für Kanzlei-Website

Handoff-Dokument für die Weiterarbeit in Claude Code. Fasst alle bisher getroffenen Entscheidungen und den aktuellen Stand zusammen, damit direkt mit der Implementierung begonnen werden kann.

## Kontext

- **Kunde:** Rechtsanwalt/Kanzlei (Berufsgeheimnisträger)
- **Theme:** `kanzlei-theme` – WordPress-Block-Theme, liegt als ZIP vor
- **Relevante Datei:** `patterns/contact.php` (Original) wird **aufgeteilt** in zwei eigenständige Patterns – `patterns/contact-form.php` (Custom-Code-Ansatz) und `patterns/contact-booking.php` (FluentBooking-Ansatz, siehe `alternative-echtzeit-buchung-mit-upload.md`). Beide werden von Anfang an gemeinsam gebaut, nicht nacheinander – siehe Abschnitt "Theme-Struktur" weiter unten.
- **Hosting:** Hetzner (EU, Rechenzentren DE/FI) – AVV muss abgeschlossen werden
- **Aktuell im Einsatz:** Google Workspace (E-Mail, Kalender) für die Kanzlei

## Ursprüngliche Anforderung

Besucher der Website sollen:
1. Einen Termin für ein Gespräch anfragen (Terminwunsch, kein Live-Kalender mit Slot-Buchung)
2. Eine Nachricht hinzufügen
3. Optional eine Datei hochladen

Das Ganze soll als E-Mail an die Kanzlei gehen; die Kanzlei soll direkt per Antworten-Klick (Reply-To) an den Absender zurückschreiben können.

## Zentrale Entscheidung: kein Formular-Plugin, sondern Custom Code

Ursprünglich war **Fluent Forms** angedacht (Plugin-Platzhalter existierte bereits im Theme). Nach Abwägung wurde entschieden, **stattdessen eigenen Code zu schreiben**, weil:

- Die Anforderungen klein und fokussiert sind (5 Felder, kein komplexer Kalender)
- Sensible Mandantendaten involviert sind → möglichst kleine Angriffsfläche gewünscht
- Ein General-Purpose-Plugin ungenutzte Features (Zahlungen, KI-Formularbuilder, Drittanbieter-Integrationen) mitbringt, die unnötiges Risiko darstellen
- Die sichere Architektur drumherum (Datei-Handling, Metadaten-only-Mail, Login-Download) ohnehin custom gebaut werden muss – der Zusatzaufwand für ein komplett eigenes Formular ist dadurch klein

**Geschätzter Aufwand:** ca. 1 Arbeitstag für Formular + Validierung + sichere Datei-Handhabung + einfache Admin-Übersicht + Anti-Spam.

## Rechtlicher Hintergrund (bestimmt alle Architekturentscheidungen)

- Der Anwalt ist **Berufsgeheimnisträger** und unterliegt **§ 203 StGB** sowie **§ 43e BRAO** – das geht über normale DSGVO-Anforderungen hinaus.
- Ein normaler DSGVO-AVV (Art. 28 DSGVO) reicht **nicht aus**, wenn ein externer Dienstleister mit mandatsbezogenen Daten in Berührung kommt. Es braucht eine zusätzliche, strafbewehrte Verschwiegenheitsverpflichtung des Dienstleisters.
- **Google Workspace bietet keine explizite § 203-StGB-Verschwiegenheitsvereinbarung** (anders als z. B. Open Telekom Cloud oder spezialisierte Legal-Tech-Anbieter). Deshalb: **keine mandatsrelevanten Inhalte (Dateien, Nachrichtentext) dürfen über Google Workspace laufen.**
- **Wichtig:** Diese Einschätzung ist eine technisch-organisatorische Risikoeinschätzung, keine Rechtsberatung. Der Kunde sollte sie mit einem auf IT-/Berufsrecht spezialisierten Kollegen oder seiner Kammer final absichern lassen, bevor produktiv mit echten Mandatsdaten gearbeitet wird.

## Architekturentscheidung (final besprochen, noch nicht implementiert)

**Grundprinzip:** Sensible Daten verlassen den eigenen Hetzner-Server nie. Google Workspace erhält ausschließlich Metadaten-Benachrichtigungen (kein Dateianhang, kein Nachrichtentext).

### Datenfluss Erstkontakt (kein Login nötig, öffentlich zugänglich)

1. **Frontend-Formular** – reines HTML/JS, clientseitige Validierung (Pflichtfelder, Dateigröße/-typ vorab prüfen)
2. **PHP-Handler** – serverseitige Validierung, CSRF-Nonce, Honeypot-Feld gegen Spam-Bots
3. **Datei-Handling** – Whitelist (z. B. PDF/JPG/PNG), Umbenennung mit zufälligem Hash (verhindert Erraten von Dateinamen), Speicherung außerhalb des direkt aufrufbaren Webroots
4. **Datenbank** – eigene Tabelle in der bestehenden WordPress-MySQL-Instanz via `$wpdb`/`dbDelta`. **Keine externe DB (Supabase, externes Postgres etc.) nötig oder sinnvoll** – das würde einen zusätzlichen externen Auftragsverarbeiter einführen und die gleiche § 203-StGB-Problematik wie bei Google Workspace erzeugen.
5. **Metadaten-only E-Mail an Google Workspace** – enthält nur Name, Terminwunsch, kurzen Hinweis ("Neue Anfrage – bitte im Dashboard prüfen"); **kein** Nachrichtentext, **kein** Datei-Anhang/-Link. Reply-To wird auf die E-Mail-Adresse des Besuchers gesetzt.
6. **Anwalt-Login** – vollständige Einreichung (inkl. Nachrichtentext und Datei-Download) ist nur über eine login-geschützte Admin-Oberfläche im WP-Backend einsehbar. Eigener eingeschränkter Benutzer, 2FA empfohlen. Datei-Download nur über authentifizierten Endpunkt, nicht über direkten Dateipfad.

### Laufender Austausch nach Mandatsübernahme (separates Vorhaben, nicht Teil des Formulars)

- **Nextcloud**, self-hosted auf Hetzner via Docker, als Mandantenportal für beidseitigen Dokumentenaustausch
- Anwalt legt nach Mandatsübernahme manuell ein Konto oder einen befristeten Freigabelink pro Mandant an
- Login-basiert, Verschlüsselung at rest, Audit-Log, 2FA
- Google Workspace bleibt auch hier nur Benachrichtigungskanal ("Neues Dokument verfügbar"), nie Ort der eigentlichen Inhalte
- Aufwand grob: Installation via Docker (2–4 Std.), TLS via Let's Encrypt; optionales SSO mit WordPress wäre 1–2 Tage zusätzlich, aktuell nicht priorisiert

### Konkretisierte Sicherheits-/DSGVO-Details (final besprochen)

**Consent-Checkbox**
- Frontend: `<input type="checkbox" name="privacy_consent" required>`, nicht vorausgewählt
- Backend: serverseitige Pflichtprüfung (`$_POST['privacy_consent'] === '1'`), da `required` allein umgehbar ist
- Zeitpunkt der Zustimmung wird als eigene Spalte (`consent_given_at`) mit jeder Einreichung gespeichert (Nachweispflicht, Art. 5 Abs. 2 DSGVO)
- Text der Datenschutzerklärung selbst: kümmert sich der Kunde später eigenständig drum

**Löschkonzept**
- Aufbewahrungsdauer für unbearbeitete Anfragen als **Backend-Einstellung** (Zahl in Tagen), nicht hartcodiert – Startwert-Vorschlag: 30 Tage
- Anfragen, die zu einem Mandat führen: manuell in reguläre Akte übernehmen, aus Formular-Tabelle löschen
- Tägliches WP-Cron-Job löscht abgelaufene, unbearbeitete Einträge inkl. zugehöriger Datei

**IP-Logging**
- Wird gespeichert (Missbrauchserkennung), aber automatisiert nach **7 Tagen** per Cron auf `NULL` gesetzt – unabhängig von der regulären 30-Tage-Löschfrist des restlichen Datensatzes
- Kein separates System/keine zweite Tabelle nötig

**Login-Sicherheit für den Anwalt-Zugang**
- **Rate-Limiting:** Plugin "Limit Login Attempts Reloaded" (bewusst Plugin statt Eigenbau – eng abgegrenztes, gelöstes Problem, berührt keine sensiblen Daten)
- **2FA:** Plugin "Two-Factor" (TOTP-basiert, läuft lokal ohne externen Cloud-Dienst)
- **Separates, eingeschränktes Nutzerkonto** für den Anwalt (eigene WP-Rolle via `add_role()`) statt Haupt-Admin-Zugang – Least-Privilege-Prinzip
- **Login-URL verschleiern:** optional, niedrige Priorität (nur Schutz gegen automatisierte Massen-Scans, kein Schutz gegen gezielte Angriffe), z. B. Plugin "WPS Hide Login"

### Terminverhandlung nach der Erstanfrage (Zusage/Absage/Alternativtermin) — **STATUS: GELÖST, siehe README.md**

**Update:** Dieser Abschnitt beschreibt den Planungsstand, *bevor* die Umsetzung entschieden wurde. Die Lösung ist keine der vier unten diskutierten Optionen, sondern ein eigenes Slot-Auswahl-System (Custom-Code, Ansatz A): Der Besucher wählt direkt einen freien Termin-Slot statt eines Freitext-Wunsches; die Anfrage ist zunächst vorläufig, der Anwalt bestätigt oder lehnt sie im Dashboard ab (bei Ablehnung meldet er sich – Stufe 1 aus Option 1/2 unten – selbst mit einer Alternative). Details, Implementierung und aktueller Stand stehen in `README.md`, Abschnitt „Variante A: Custom-Formular". Der Rest dieses Abschnitts bleibt als Dokumentation der ursprünglichen Abwägung stehen.

**Wichtig zur Einordnung:** Das Erstkontakt-Formular selbst (Name, E-Mail, Terminwunsch, Nachricht, Datei) ist entschieden und beschrieben unter "Datenfluss Erstkontakt" oben. Dieser Abschnitt behandelt **nur**, was passiert, *nachdem* diese erste Anfrage eingegangen ist und der vorgeschlagene Termin nicht passt. Nachricht und Datei sind zu diesem Zeitpunkt bereits übermittelt – in keiner der folgenden Optionen wird nochmal eine Nachricht oder Datei mitgeschickt, es geht nur noch um die Terminfindung selbst.

**Gap, die identifiziert wurde:** Die bisherige Architektur schützt nur die *erste* eingehende Nachricht. Antwortet der Anwalt per Reply-To über Google Workspace, ist das unkritisch (Original-Nachricht wurde ihm nie als Volltext zugestellt, kann also nicht versehentlich zitiert werden). Antwortet der **Klient** aber ein zweites Mal per normaler E-Mail, landet dieser Folgeaustausch direkt und ungefiltert in Google Workspace – der kontrollierte Kanal ist ab dann durchbrochen.

**Vier Optionen wurden besprochen – keine ist ausgewählt, das ist eine offene Entscheidung für den Kunden:**

| # | Ansatz | Wie Termin-Hin-und-Her abläuft |
|---|---|---|
| 1 | **Telefon statt Mail** | Anwalt ruft an, klärt Termin mündlich. Kein Bau nötig. |
| 2 | **Eine Google-Workspace-Antwortrunde tolerieren** | Anwalt antwortet einmal per Reply-To, bei weiterem Austausch Wechsel auf Nextcloud-Portal. |
| 3 | **Magic-Link-Eigenbau** | Anwalt schlägt im eigenen Backend Alternativtermin vor → System-Mail mit signiertem Einmal-Link (eigener SMTP) → Klient bestätigt/lehnt auf Hetzner-Antwortseite ohne Login → beliebig viele Runden möglich, bleibt komplett selbst gebaut. Zusatzaufwand ca. 0,5–1 Tag. |
| 4 | **Echter Kalender statt Vorschlag-Hin-und-Her** | Klient wählt direkt einen freien Zeitslot aus einem echten Kalender (wie Calendly) – ersetzt das Vorschlagen/Ablehnen komplett durch Slot-Auswahl. Kehrt zur ursprünglich (ganz am Projektanfang) verworfenen "Variante 1" zurück, aber nur für diese Nachbesserungs-Phase, nicht für den Erstkontakt. |

**Zu Option 4 – Tool-Empfehlung (falls diese Option gewählt wird):**
- ~~Cal.com~~ – **nicht empfohlen**: Cal.com hat sich laut eigenem Blogbeitrag 2026 bewusst von der offenen Codebasis entfernt, der Community-Self-Host-Fork "Cal.diy" ist in der eigenen Doku explizit nur für **"personal, non-production use"** freigegeben, nicht für den produktiven Geschäftseinsatz.
- **FluentBooking – empfohlen**, falls Option 4 gewählt wird: natives WordPress-Plugin statt separater Anwendung, Buchungsdaten bleiben in der bestehenden WP-MySQL-Datenbank, keine zusätzliche Datenbank-Technologie (kein Postgres/Docker), einmalige Lizenzkosten statt Pro-Nutzer-Abo.

**Empfehlung für den Start (unverbindlich):** Option 1 (Telefon) als Standardweg, Option 2 als Rückfallebene. Option 3 oder 4 nur, falls der Anwalt erwartungsgemäß viele schriftliche Terminverhandlungen ohne Telefonate erwartet – beide würden den Aufwand des Formular-Projekts spürbar erweitern.

### Offene rechtliche Frage (bewusst nicht gelöst, für später)

Sobald der Anwalt eine Anfrage über Google Workspace beantwortet oder dort Dokumente verschickt, ist er wieder im normalen Workspace-Kontext – das kann diese Formular-Architektur nicht lösen. Mögliche spätere Optionen (nur besprochen, keine Entscheidung getroffen):
1. Vollständiges Mandanten-Portal auch für ausgehende Kommunikation (Erweiterung von Nextcloud)
2. Ende-zu-Ende-Verschlüsselung der Mails (S/MIME oder PGP) – Nachteil: Mandant braucht kompatible Mailumgebung
3. Separater, selbst gehosteter Mailserver nur für Mandatskorrespondenz, Google Workspace nur für Kalender/interne Organisation
4. Spezialisierter Legal-Cloud-Anbieter mit expliziter § 203-Vereinbarung
5. beA nur für Schriftverkehr mit Gerichten/Behörden (ohnehin oft vorgeschrieben, deckt aber nicht die allgemeine Mandantenkommunikation ab)

## Technischer Stack (Entscheidungen)

- WordPress Block-Theme `kanzlei-theme`
- Hosting: Hetzner (EU) – AVV mit Hetzner nötig
- Formular: **Custom PHP**, kein Formular-Plugin
- Datenbank: bestehende WordPress-MySQL-Instanz (`$wpdb`), keine externe DB
- Datei-Uploads: lokal, außerhalb Webroot, Hash-Dateinamen, Typ-Whitelist
- Mail-Versand: über SMTP-Plugin (z. B. WP Mail SMTP) mit TLS-Verschlüsselung, **nicht** PHP `mail()`
- Anti-Spam: Honeypot-Feld; **kein** Google reCAPTCHA (Datentransfer USA/Schrems II) – Alternative wäre Cloudflare Turnstile, falls Honeypot nicht ausreicht
- Perspektivisch (separates Vorhaben): Nextcloud via Docker für Mandantenportal

## Allgemeine Theme-Standards (Referenz + Audit-Ergebnis)

Zusätzlich zum projektspezifischen Sicherheits-/DSGVO-Konzept gilt für jedes FSE-Theme in diesem Workflow ein allgemeiner Qualitätsstandard (vollständiges Dokument: `Allgemeine-Standards-WordPress-Themes.md`, sollte im selben Repo/Kontext wie diese Datei liegen). Kernpunkte: FSE-Theme ohne jQuery/Page-Builder, DSGVO (lokale Fonts, kein Runtime-Tracking), WCAG AA, mobile responsive über Core-Block-Mechanismen, keine hartcodierten Werte außerhalb zentraler `theme.json`-Variablen, vollständige WordPress-Baseline (`ABSPATH`-Guards, Theme-Supports, Editor-Styles, i18n, Font-Preload, vollständiger Theme-Header).

**Audit des aktuellen `kanzlei-theme` gegen diese Standards (Stand: hochgeladenes Theme-ZIP):**

| Punkt | Status | Befund |
|---|---|---|
| `ABSPATH`-Guard in jeder PHP-Datei | ✅ | Vorhanden in allen Pattern-Dateien und `functions.php` |
| Theme-Supports-Baseline | ✅ | `custom-logo`, `post-thumbnails`, `html5`, `responsive-embeds`, `editor-styles` + `add_editor_style()` gesetzt |
| Font-Preload | ✅ | Nur die zwei Above-the-Fold-Fonts (Inter 400, Playfair 700), korrekt per `wp_head` |
| `load_theme_textdomain()` + i18n-Grundgerüst | ✅ | Vorhanden, Pattern-Kategorie-Label bereits mit `__()` |
| Legal-Pages als eigene Patterns mit `Inserter: false` | ✅ | `imprint.php` und `privacy-policy.php` korrekt gesetzt |
| Heading-Hierarchie in `contact.php` | ✅ | H2 → H3 (Bürozeiten), keine übersprungenen Level, keine doppelte H1 |
| Kein jQuery | ✅ | Keine Treffer im gesamten Theme |
| Google Maps/Fonts zur Laufzeit | ✅ | Nur ein normaler `<a>`-Link zu Google Maps (kein eingebettetes iFrame/Tracking), Fonts vollständig lokal |
| **Theme-Header in `style.css` vollständig** | ❌ | Nur `Theme Name`, `Description`, `Version`, `Author` gesetzt – **`Requires at least`, `Tested up to`, `Requires PHP`, `Text Domain` fehlen** |
| **Keine hartcodierten Hex-Werte außerhalb der Variablen** | ❌ | `patterns/hero.php`: Button nutzt `#ffffff` als Literal für Text-/Rahmenfarbe, obwohl in `theme.json` bereits ein passender `white`-Slug definiert ist – sollte `var(--wp--preset--color--white)` referenzieren |

**Einordnung:** Die sicherheits-/datenschutzrelevanten Grundlagen (Guards, kein Tracking, lokale Fonts, Legal-Pages-Struktur) sind sauber umgesetzt. Die zwei gefundenen Lücken sind kein Sicherheitsrisiko, sondern Wartbarkeits-/Konsistenzthemen – sollten aber vor Projektabschluss behoben werden, weil sie genau die Art von "vergisst man leicht"-Punkten sind, die der Standard explizit benennt.

**Für das neue Kontaktformular gilt der gleiche Maßstab zusätzlich zu den bereits notierten Sicherheitsanforderungen:**
- Echte `<label>`-Elemente pro Feld (nicht nur Placeholder-Text) – WCAG-Pflicht
- Sichtbarer `:focus-visible`-Zustand für alle Formularfelder und den Submit-Button, über zentrale Variable statt Individual-Styling
- ARIA-Live-Region (`aria-live="polite"`) für Erfolgs-/Fehlermeldungen nach dem Absenden, da das Formular ohne Seiten-Reload läuft
- Alle UI-Textstrings des Formulars (Button-Beschriftung, Fehlermeldungen, Consent-Text-Baustein außer dem eigentlichen Rechtstext) mit `__()`/`_e()` und dem Theme-Textdomain `kanzlei-theme` versehen
- Farben (Fehlerzustand, Fokus-Ring) über bestehende `theme.json`-Preset-Slugs, keine neuen Hex-Literale
- Mobile: Spalten-Layout ist bereits `isStackedOnMobile:true` – Formularfelder sollten diese Breite einfach übernehmen, keine eigenen Breakpoints nötig

## Theme-Struktur: beide Ansätze werden von Anfang an gemeinsam integriert

**Entscheidung:** Custom-Code-Ansatz (dieses Dokument) und Kalender-Alternative (`alternative-echtzeit-buchung-mit-upload.md`) werden **beide direkt jetzt** als wählbare Patterns im selben Theme gebaut, nicht nacheinander oder in getrennten Themes. Das ursprüngliche `patterns/contact.php` wird dafür in zwei eigenständige Pattern-Dateien aufgeteilt. Damit nicht nur das Pattern selbst löschbar ist, sondern auch die zugehörige technische Voraussetzung dahinter sauber getrennt bleibt, gilt folgende Struktur:

```
kanzlei-theme/
├── functions.php                          ← nur kurze, kommentierte require-Zeilen
├── inc/
│   ├── contact-form-custom.php            ← ALLES für Custom-Code-Ansatz (Handler, DB, Mail)
│   └── contact-form-booking-hooks.php     ← ALLES für FluentBooking-Absicherung (Hooks)
├── template-parts/
│   └── contact-form.php                   ← nur für Custom-Code-Ansatz gebraucht
└── patterns/
    ├── contact-form.php                   ← Pattern für Custom-Code-Ansatz
    └── contact-booking.php                ← Pattern für FluentBooking-Ansatz
```

`functions.php` enthält dann nur:
```php
// Ansatz 1: Custom-Code-Formular (Datei löschen + Zeile entfernen, falls nicht genutzt)
require_once get_theme_file_path( 'inc/contact-form-custom.php' );

// Ansatz 2: FluentBooking-Absicherung (Datei löschen + Zeile entfernen, falls nicht genutzt)
require_once get_theme_file_path( 'inc/contact-form-booking-hooks.php' );
```

**Unterschied zwischen den beiden Kapselungen:**
- **Custom-Code-Datei** (`inc/contact-form-custom.php`) ist echter Theme-Code (Handler, DB-Tabelle, Formular-Template) – muss komplett entfernbar sein: eine Datei löschen + eine `require`-Zeile entfernen, fertig
- **FluentBooking-Hooks-Datei** (`inc/contact-form-booking-hooks.php`) ist nur eine schlanke Absicherungsschicht um ein externes Plugin, sollte defensiv geschrieben sein (`if ( ! class_exists( 'FluentBooking\App' ) ) { return; }`), damit selbst ein versehentlich verbleibender Datei-Rest ohne installiertes Plugin keinen Fehler verursacht

## Dokumentationspflicht bei Fertigstellung

Sobald beide Ansätze fertig implementiert sind, müssen die nötigen Schritte **in der `README.md` des Theme-Repos** dokumentiert werden – nicht im `style.css`-Header, der laut den allgemeinen Theme-Standards nur für die kurzen Metadaten-Felder (Theme Name, Version, Requires at least usw.) gedacht ist, keine Anleitungstexte. Die README sollte pro Ansatz klar trennen, welche Schritte nötig sind, z. B.:

```markdown
## Kontaktformular einrichten

Dieses Theme unterstützt zwei Pattern-Varianten für den Kontaktbereich – wählt eine, löscht die andere.

### Variante A: Custom-Code-Formular (`patterns/contact-form.php`)
1. Datenbank-Tabelle wird automatisch bei Theme-Aktivierung angelegt (dbDelta)
2. SMTP-Plugin (z. B. WP Mail SMTP) installieren und konfigurieren
3. Aufbewahrungsfrist unter [Backend-Menüpfad] einstellen (Standard: 30 Tage)
4. Login-Härtung aktivieren: Plugins "Limit Login Attempts Reloaded" + "Two-Factor" installieren
5. Falls nicht genutzt: `inc/contact-form-custom.php` und `patterns/contact-form.php` löschen

### Variante B: FluentBooking-Kalenderbuchung (`patterns/contact-booking.php`)
1. Plugin "FluentBooking" installieren (Pro-Version für Kalender-Sync nötig)
2. Manual Confirmation in den Calendar-Einstellungen aktivieren
3. Booking Questions konfigurieren: Textarea (Nachricht), File Field (Datei), Terms & Conditions (Consent)
4. E-Mail-Benachrichtigung auf Metadaten-only trimmen (siehe Sicherheits-Dokument)
5. Falls nicht genutzt: `inc/contact-form-booking-hooks.php` und `patterns/contact-booking.php` löschen
```

Diese README-Struktur sollte als eigener To-do-Punkt (siehe unten) mit umgesetzt werden, nicht erst nachträglich ergänzt werden – sonst ist sie beim nächsten Wiedereinstieg in Claude Code nicht aktuell.

## Mehrfachnutzung als Basis-Theme (perspektivisch, Entscheidung bewusst vertagt)

Es ist absehbar, dass künftige Kunden jeweils nur **einen** der beiden Ansätze brauchen werden (unterschiedlicher Bedarf je Kanzlei/Kunde). Empfohlenes Modell dafür, sobald relevant:

- `kanzlei-theme` wird zum **Basis-Theme** mit beiden Modulen (wie oben beschrieben)
- Pro Neukunde: Basis-Theme klonen, passendes Modul auswählen, anderes tatsächlich löschen (Datei + Pattern + `require`-Zeile) – jedes Kunden-Repo läuft am Ende nur mit einem der beiden Ansätze, nie beide gleichzeitig live
- **Noch offen, bewusst nicht jetzt entschieden:** Wie Verbesserungen am Basis-Theme (Bugfixes, neue Patterns, Design-Updates) später an bereits ausgelieferte Kunden-Repos weitergegeben werden. Zwei Optionen zur späteren Prüfung:
  - Git-Branches/-Forks pro Kunde vom Basis-Theme, mit manuellem Cherry-Picking von Fixes bei Bedarf
  - Geteiltes "Core"-Package (z. B. Composer-Dependency), das Kundenprojekte einbinden – sauberer, aber mehr initialer Aufwand, lohnt sich eher ab mehreren parallelen Kundenprojekten
- Relevant wird das erst, sobald tatsächlich ein zweiter Kunde dazukommt – bis dahin keine weitere Aktion nötig

## Nächste Schritte (noch zu implementieren)

1. [x] PHP-Handler für Formularverarbeitung (serverseitige Validierung, Nonce/CSRF-Schutz, Honeypot, Consent-Pflichtprüfung) – `inc/contact-form-custom.php`
2. [x] Datenbank-Schema + `dbDelta`-Setup – **weiterentwickelt** gegenüber der ursprünglichen Planung: statt Freitext-Terminwunsch ein `appointment_slot` (echte Slot-Auswahl, siehe README), Dateien in eigener Tabelle `kanzlei_contact_files` (mehrere pro Anfrage möglich) statt einer einzelnen Datei-Pfad-Spalte, plus `kanzlei_blocked_slots` für manuelle Sperren
3. [x] Sichere Datei-Handling-Logik (Whitelist, Umbenennung, Speicherort außerhalb Webroot) – jetzt mehrere Dateien pro Anfrage, siehe README
4. [x] Metadaten-only Mail-Template inkl. Reply-To-Logik
5. [x] `patterns/contact.php` aufgeteilt in `patterns/contact-form.php` (Custom-Code-Ansatz) – Frontend inkl. echter `<label>`-Elemente, `:focus-visible`, ARIA-Live-Region, i18n-Strings, Farben über bestehende `theme.json`-Slugs
5b. [x] `patterns/contact-booking.php` (FluentBooking-Ansatz) parallel angelegt – beide Patterns im Editor wählbar, jeweils andere löschbar
6. [x] Admin-Oberfläche im WP-Backend: Liste der Einreichungen + sicherer, authentifizierter Datei-Download-Endpunkt (inkl. ZIP-Sammel-Download + Name/E-Mail-Filter), eigene eingeschränkte Nutzerrolle
7. [x] WP-Cron: Löschung unbearbeiteter Einträge nach konfigurierbarer Frist + IP-Adresse nach 7 Tagen auf `NULL` setzen – **erweitert** um einen zweiten, stündlichen Cron für den 48-Stunden-Verfall vorläufiger Terminanfragen
8. [ ] Login-Härtung: Plugin "Limit Login Attempts Reloaded" (Rate-Limiting), Plugin "Two-Factor" (2FA), optional "WPS Hide Login" (niedrige Priorität) – **echter Deployment-Schritt, nicht Code**, siehe README Einrichtung Schritt 5
9. [ ] DSGVO: Datenschutzerklärung um den Formular-Prozess ergänzen (welche Daten, Zweck, Speicherdauer, IP-Handling) – **technische Vorbereitung erledigt** (Consent-Checkbox + `consent_given_at`-Zeitstempel, Teil von Punkt 1/2), **Text selbst steht noch aus** und übernimmt der Kunde
10. [x] Theme-Standards-Lücken aus dem Audit geschlossen: vollständiger Theme-Header in `style.css` (`Requires at least`, `Tested up to`, `Requires PHP`); hartcodiertes `#ffffff` in `patterns/hero.php` durch `var(--wp--preset--color--white)` ersetzt
11. [ ] Optional/später: Nextcloud-Setup für Mandantenportal (separates Vorhaben, nicht Teil dieses Formulars) – weiterhin nicht begonnen
12. [x] **Gelöst:** Statt einer der vier ursprünglich diskutierten Optionen wurde ein eigenes Slot-Auswahl-System gebaut (Custom-Code, Ansatz A) – Besucher wählt direkt einen freien Termin, Anwalt bestätigt/lehnt im Dashboard ab. Siehe `README.md`, Abschnitt "Variante A: Custom-Formular", sowie die aktualisierte Anmerkung im Abschnitt "Terminverhandlung nach der Erstanfrage" oben.
13. [x] `README.md` im Theme-Repo angelegt: Einrichtungsschritte pro Ansatz dokumentiert (siehe Abschnitt "Dokumentationspflicht bei Fertigstellung" oben)

## Dateien

- Theme-ZIP mit vorbereitetem, präzisiertem Platzhalter in `patterns/contact.php` liegt vor (`kanzlei-theme.zip`) – der ursprüngliche Fluent-Forms-Hinweis wurde bereits durch einen Hinweis auf die künftige Custom-Code-Lösung ersetzt.
