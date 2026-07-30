# Kanzlei Theme

WordPress-Block-Theme (FSE) für eine Solo-Kanzlei. Kein Page-Builder, kein
jQuery, lokale Fonts, WCAG-AA-orientiert. Die meiste Gestaltung läuft über
`theme.json`; PHP/CSS nur, wo Blöcke das nicht abdecken.

## Projektkontext (für den Wiedereinstieg in einem neuen Chat)

Kunde ist ein Rechtsanwalt (Berufsgeheimnisträger, § 203 StGB) – das begründet
die durchgängig sicherheits-/datenschutzorientierten Entscheidungen unten
(Metadaten-only-Mail, kein externer Kalenderzugriff, private Datei-Ablage etc.).
Die vollständige Historie, rechtliche Einordnung und ursprüngliche Abwägungen
stehen in zwei Begleitdokumenten:
- `projekt-kontaktformular-kanzlei.md` – Hauptdokument, Custom-Code-Ansatz (A)
- `alternative-echtzeit-buchung-mit-upload.md` – FluentBooking-Ansatz (B)

**Wichtig beim Lesen dieser beiden Dokumente:** Sie beschreiben den
ursprünglichen Planungsstand. Der dort in „Terminverhandlung nach der
Erstanfrage“ beschriebene offene Punkt (Telefon/Antwortrunde/Magic-Link/
Kalender, 4 Optionen) ist **für Ansatz A inzwischen gelöst** – und zwar nicht
durch eine der vier ursprünglich diskutierten Optionen, sondern durch das
unten beschriebene Slot-Auswahl-System mit Bestätigen/Ablehnen. Die
Begleitdokumente wurden dafür nicht mehr aktualisiert; maßgeblich für den
aktuellen Stand ist diese README, nicht die beiden `.md`-Dateien.

Aktueller Stand: Beide Ansätze sind vollständig implementiert und getestet
(siehe unten), aber **es ist noch nicht entschieden, welcher der beiden
tatsächlich live geht** – siehe „Nicht genutzte Variante entfernen“ weiter
unten. Bis diese Entscheidung fällt, bleiben bewusst beide im Theme.

## Kontaktbereich einrichten

Das Theme bringt **zwei wählbare Kontakt-Varianten** mit. Auf einer Seite wird
genau **eine** genutzt – die andere wird gelöscht (siehe „Nicht genutzte
Variante entfernen“).

| | Variante A – Custom-Formular | Variante B – FluentBooking |
|---|---|---|
| Pattern | `patterns/contact-form.php` | `patterns/contact-booking.php` |
| Zweck | Slot-Auswahl aus freien Terminen, vom Anwalt zu bestätigen | echte Kalenderbuchung, optional mit externem Kalender-Sync |
| Plugin nötig | nein (nur SMTP, siehe unten) | ja (FluentBooking) |

Beide Patterns erscheinen im Editor unter der Kategorie **„Kanzlei“**. Der
Sprungmenü-Link „Kontakt“ (`#contact`) funktioniert bei beiden, weil beide den
Anker `contact` setzen.

---

### Variante A: Custom-Formular (`patterns/contact-form.php`)

Eigenes, sicheres Formular mit **Slot-Auswahl**. Sensible Inhalte (Nachricht,
Datei) verlassen den Server nie; die Kanzlei bekommt nur eine
**Metadaten-E-Mail** (Name + Termin) mit Reply-To auf den Absender. Volle
Einsicht ausschließlich im Backend unter **„Kontaktanfragen”**.

**Ablauf der Terminbuchung**
1. Besucher wählt einen freien Slot (ab morgen, bis 30 Tage im Voraus).
2. Die Anfrage ist zunächst **vorläufig** – der Slot ist damit für andere
   Besucher blockiert, aber noch nicht verbindlich.
3. Der Anwalt **bestätigt** oder **lehnt ab**. Bei Ablehnung meldet er sich
   selbst mit einem Alternativvorschlag (Telefon/E-Mail) – es gibt bewusst
   keinen automatischen Verhandlungs-Mechanismus.
4. Reagiert niemand, verfällt die vorläufige Anfrage nach 48 Stunden
   automatisch und der Slot wird wieder frei.

**Verfügbarkeit & Kalender**
Es gibt **keine Anbindung an einen externen Kalender** (z. B. Google Workspace)
– bewusst, damit keine mandatsbezogenen Kalendereinträge über einen
Drittanbieter laufen. Belegte Zeiten ergeben sich aus:
- bestätigten Terminen (automatisch),
- vorläufigen Anfragen (automatisch, für 48 Stunden),
- **manuell gesperrten Zeiten** – dafür gibt es im Dashboard das Formular
  „Termine sperren“ (Gerichtstermine, private Termine, Urlaub).

*Konsequenz:* Termine außerhalb des Formulars muss der Anwalt dort manuell
nachtragen, sonst kann ein Besucher sie belegen.

**Sprechzeiten anpassen** (aktuell Platzhalter: Mo–Fr 9–18 Uhr, 30-Min-Raster):
über die Filter `kanzlei_cf_availability_rules`, `kanzlei_cf_slot_duration_minutes`,
`kanzlei_cf_booking_window_days` und `kanzlei_cf_pending_expiry_hours`.

**Bausteine**
- `blocks/contact-form/` – serverseitig gerenderter Block `kanzlei/contact-form`
  (frischer CSRF-Nonce pro Aufruf).
- `inc/contact-form-custom.php` – komplette Server-Logik (Validierung, Datei,
  DB, Mail, Backend, Cron).
- `assets/js/contact-form.js` – optionales Progressive Enhancement (Absenden
  ohne Reload). Das Formular funktioniert auch ohne JavaScript.
- `template-parts/contact-cards.php` – gemeinsame Kontaktkarten (auch von B genutzt).

**Datei-Uploads (mehrere pro Anfrage)**
- Standard-Limits: max. **3 Dateien** pro Anfrage, je max. **4 MB**, in Summe
  max. **12 MB** – änderbar über die Filter unten.
- Jede Datei durchläuft dieselbe Prüfung (Endungs-Whitelist + echter MIME-Typ
  per `finfo`), unabhängig von der Anzahl – keine Abstriche bei Mehrfach-Upload.
- Im Dashboard werden die Dateien einer Anfrage **gebündelt** angezeigt (nicht
  als separate Zeilen), mit Einzel-Downloads sowie einem
  „Alle als ZIP herunterladen"-Button ab zwei Dateien. Fehlt die
  `ZipArchive`-PHP-Erweiterung auf dem Server, wird der ZIP-Button automatisch
  ausgeblendet – Einzel-Downloads funktionieren trotzdem.
- Anfragen derselben Person werden **nicht** automatisch zusammengeführt
  (bewusst, siehe Konsistenz mit dem Löschkonzept) – im Dashboard aber über
  das Suchfeld nach Name/E-Mail auffindbar.
- **Hosting-Hinweis:** PHPs eigene Grenzen (`upload_max_filesize`,
  `post_max_size`, `max_file_uploads` in der `php.ini`) müssen mindestens so
  großzügig sein wie die hier konfigurierten Werte, sonst schlägt der Upload
  schon vor der eigenen Prüfung fehl. Vor dem Go-Live beim Hoster prüfen.

**Einrichtung**
1. **Theme aktivieren** – legt automatisch an: DB-Tabellen `…_kanzlei_contact`
   und `…_kanzlei_blocked_slots`, das private Upload-Verzeichnis
   `wp-content/uploads/kanzlei-private/`, die Rolle **„Kanzlei-Verwaltung”**
   sowie zwei Cron-Jobs (täglich aufräumen, stündlich abgelaufene vorläufige
   Anfragen verfallen lassen). **Pattern erscheint dadurch noch nicht auf der
   Seite** – `templates/front-page.html` bindet den Seiteninhalt über
   `wp:post-content` ein, das Pattern „Contact – Custom-Formular“ muss daher
   im Block-Editor auf der gewünschten Seite eingefügt werden (Muster
   durchsuchen → Kategorie „Kanzlei“).
2. **SMTP-Plugin** installieren und konfigurieren (z. B. WP Mail SMTP, TLS) –
   `wp_mail()` soll nicht über PHP `mail()` gehen.
3. **Aufbewahrungsfrist** unter **Kontaktanfragen** einstellen (Standard: 30
   Tage). Unbearbeitete Anfragen werden danach automatisch gelöscht; IP-Adressen
   generell nach 7 Tagen anonymisiert.
4. **Anwalt-Zugang** anlegen: neuen Benutzer mit Rolle „Kanzlei-Verwaltung“
   (Least Privilege statt Voll-Admin). Administratoren sehen den Menüpunkt ohnehin.
5. **Login-Härtung** aktivieren: Plugins „Limit Login Attempts Reloaded“ +
   „Two-Factor“ (optional „WPS Hide Login“).
6. **Datenschutzerklärung** unter `/datenschutz/` bereitstellen (der Consent-Link
   im Formular zeigt dorthin) und um den Formularprozess ergänzen.
7. **HTTPS für die gesamte Seite** sicherstellen (nicht nur für einzelne
   Endpunkte) – sonst reisen Formulardaten und Datei-Uploads beim Absenden
   unverschlüsselt durchs Netz. Reine Hosting-Konfiguration (TLS-Zertifikat,
   z. B. Let's Encrypt), kein Theme-Code.
8. **AVV mit dem Hoster abschließen** und dessen Serverstandort/Datenschutz-
   Zusagen gegen die Anforderungen aus `projekt-kontaktformular-kanzlei.md`
   prüfen (Berufsgeheimnisträger-Kontext, § 203 StGB) – unabhängig davon,
   welcher Hoster am Ende gewählt wird.
9. **Vor dem Go-Live in einem echten Browser testen** (nicht nur per
   Code-Review): kompletten Buchungs-Flow durchklicken – Slot wählen,
   Datei(en) anhängen, absenden, im Dashboard bestätigen/ablehnen/sperren,
   Downloads prüfen. Bisher wurde die Logik headless (CLI/HTTP-Requests)
   getestet, nicht in einem echten Browser mit Screenreader/Tastatur.

**Wichtig – privates Upload-Verzeichnis absichern**
Die Uploads liegen unter `wp-content/uploads/kanzlei-private/` mit
Zufallsnamen und werden nur über den authentifizierten Download-Endpunkt
ausgeliefert. Zusätzlich liegt dort eine `.htaccess` (greift nur bei **Apache**).
**Bei nginx** muss der Direktzugriff per Server-Konfiguration gesperrt werden:

```nginx
location ^~ /wp-content/uploads/kanzlei-private/ { deny all; return 403; }
```

**Caching:** Die Kontaktseite darf **nicht** per Full-Page-Cache ausgeliefert
werden, sonst friert der CSRF-Nonce ein. Seite in der Cache-Konfiguration
ausschließen.

**Cron zuverlässig machen (empfohlen):** WP-Cron läuft nur, wenn die Seite
Besucher hat. Bei einer Kanzlei-Website mit wenig Traffic können der
48-Stunden-Verfall vorläufiger Anfragen und die Löschfristen dadurch spürbar
verspätet greifen. Deshalb WP-Cron auf einen echten System-Cron umstellen:

```php
// in wp-config.php
define( 'DISABLE_WP_CRON', true );
```
```bash
# crontab -e  – z. B. alle 15 Minuten
*/15 * * * * cd /pfad/zur/website && php wp-cron.php >/dev/null 2>&1
```

**Konfigurierbar (via Filter, optional)**
- `kanzlei_cf_notify_email` – Empfänger der Benachrichtigung (Default: Admin-Mail)
- `kanzlei_cf_max_upload_mb` – max. Größe pro Datei (Default: 4)
- `kanzlei_cf_max_files` – max. Anzahl Dateien pro Anfrage (Default: 3)
- `kanzlei_cf_max_total_upload_mb` – max. Gesamtgröße pro Anfrage (Default: 12)
- `kanzlei_cf_allowed_types` – erlaubte Datei-Endungen/MIME-Typen
- `kanzlei_cf_rate_limit` / `kanzlei_cf_rate_window` – Rate-Limit pro IP (Default 5/Stunde)

**Variante A entfernen (falls B genutzt wird)**
1. `require_once … 'inc/contact-form-custom.php'`-Zeile in `functions.php` löschen
2. Dateien löschen: `inc/contact-form-custom.php`, `blocks/contact-form/`,
   `assets/js/contact-form.js`, `patterns/contact-form.php`
3. Optional: DB-Tabelle `…_kanzlei_contact`, Rolle „Kanzlei-Verwaltung“ und das
   private Upload-Verzeichnis manuell entfernen (bleiben aus Datensicherheit stehen).

---

### Variante B: FluentBooking-Kalender (`patterns/contact-booking.php`)

Echte Terminbuchung. Der Kalender/das Formular kommt vom Plugin; das Pattern
liefert nur Layout (breiter Kalenderbereich, Kontaktkarten darunter).

**Einrichtung**
1. **FluentBooking** installieren (Pro-Version für externe Kalender-Synchronisation
   nötig).
2. In `patterns/contact-booking.php` an der markierten Stelle den
   **FluentBooking-Block** einsetzen (oder Shortcode `[fluent_booking id="…"]`).
3. **Manual Confirmation** in den Kalender-Einstellungen aktivieren, damit
   Buchungen erst nach Bestätigung durch den Anwalt verbindlich sind.
4. **Booking Questions** konfigurieren: Textarea (Nachricht), File Field (Datei),
   Terms & Conditions (Einwilligung, Pflichtfeld).
5. **E-Mail-Benachrichtigung auf Metadaten-only trimmen** – keinen Nachrichtentext
   und keinen Datei-Link an Google Workspace senden (§ 203 StGB).
6. Upload-Verzeichnis des Plugins gegen Direktzugriff absichern (analog zu A).

`inc/contact-form-booking-hooks.php` ist nur eine **defensive Sicherungsschicht**:
Sie tut nichts, solange FluentBooking nicht aktiv ist. Der darin enthaltene
E-Mail-Redaktions-Hook ist eine zweite Verteidigungslinie – der genaue
Filtername ist **versionsabhängig und vor Einsatz gegen die installierte
FluentBooking-Version zu prüfen**. Maßgeblich bleibt die Plugin-Konfiguration.

**Ungeklärt, vor Kauf/Einsatz zu prüfen:** Ob Manual Confirmation sowie
File-Field, Textarea und Terms-&-Conditions-Fragen gemeinsam in der
kostenlosen FluentBooking-Version nutzbar sind, oder ob dafür zusätzlich zur
ohnehin für Kalender-Sync nötigen Pro-Version eine weitere Einschränkung
gilt – ließ sich beim letzten Stand nicht abschließend klären (siehe
`alternative-echtzeit-buchung-mit-upload.md`, Abschnitt „Offene Punkte“).

**Variante B entfernen (falls A genutzt wird)**
1. `require_once … 'inc/contact-form-booking-hooks.php'`-Zeile in `functions.php` löschen
2. Dateien löschen: `inc/contact-form-booking-hooks.php`, `patterns/contact-booking.php`

---

## Hinweis für Folgeprojekte

Künftige Kunden brauchen erfahrungsgemäß nur **einen** der beiden Ansätze. Modell:
`kanzlei-theme` als Basis-Theme mit beiden Modulen führen, pro Kunde klonen und
den nicht genutzten Ansatz wie oben löschen. Details/offene Punkte dazu siehe
`projekt-kontaktformular-kanzlei.md`.
