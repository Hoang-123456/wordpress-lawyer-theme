# Lawyer Landingpage Theme

WordPress block theme (FSE) for a solo law firm. No page builder, no
jQuery, local fonts, WCAG-AA-oriented. Most of the design runs through
`theme.json`; PHP/CSS only where blocks can't cover it.

## Background

This theme is built for a law firm. Lawyers are bound by professional
confidentiality obligations under § 203 StGB (German Criminal Code) and
§ 43e BRAO (Federal Lawyers' Act) – this goes beyond normal DSGVO (GDPR)
requirements and is the reason for this theme's consistently
security-/privacy-oriented decisions: metadata-only notification emails, no
client-related content (file, message text) via third-party providers like
Google Workspace, private file storage outside the public webroot,
least-privilege access for the lawyer's login.

The theme ships with **two fully implemented, selectable contact
variants** (see below). A production site uses exactly one; the other is
deleted (see "Removing the unused variant" in the respective sections).

## Deployment

The repo root **is** the theme root – no build step changes that, and
that's deliberate: it lets you `git clone` straight into
`wp-content/themes/` or drop the folder in via SFTP as-is.

`.devcontainer/`, `.fallow/`, `.gitattributes`, `.gitignore`, and
`README.md` are dev-only tooling/documentation with no effect at runtime
(WordPress never reads them), but there's no reason to expose them on a
publicly reachable path either – especially README.md, which documents
the security architecture in detail. They're marked `export-ignore` in
`.gitattributes`, so `git archive` (and thus `bin/build-zip.sh`) leaves
them out automatically. A plain `git clone`/`git pull` still gets
everything, which is what you want for development.

**To upload via the WordPress dashboard** (Appearance → Themes → Add New
→ Upload Theme): commit your changes, then run

```bash
bin/build-zip.sh
```

This produces `dist/kanzlei-theme.zip` (gitignored) containing only the
actual theme files, ready to upload. Only committed changes are included
– uncommitted work is not.

**To deploy via SFTP/rsync instead:** either upload the whole repo folder
as-is (the excluded dev files are harmless, just unnecessary) or extract
`bin/build-zip.sh`'s output onto the server for a leaner copy.

## Setting up the contact section

The theme ships with **two selectable contact variants**. A site uses
exactly **one** – the other is deleted (see "Removing the unused
variant").

| | Variant A – Custom form | Variant B – FluentBooking |
|---|---|---|
| Pattern | `patterns/contact-form.php` | `patterns/contact-booking.php` |
| Purpose | slot selection from open appointments, to be confirmed by the lawyer | real calendar booking, optionally with external calendar sync |
| Plugin required | no (only SMTP, see below) | yes (FluentBooking) |

Both patterns appear in the editor under the **"Kanzlei"** category. The
jump-menu link "Kontakt" (`#contact`) works for both, because both set the
`contact` anchor.

---

### Variant A: custom form (`patterns/contact-form.php`)

A self-built, secure form with **slot selection**. Sensitive content
(message, file) never leaves the server; the law firm only receives a
**metadata email** (name + appointment) with reply-to set to the sender.
Full visibility is available exclusively in the backend under **"Kontaktanfragen"**.

**Appointment booking flow**
1. Visitor picks an open slot (from tomorrow, up to 30 days in advance).
2. The request is initially **provisional** – the slot is thereby blocked
   for other visitors, but not yet binding.
3. The lawyer **confirms** or **rejects**. On rejection, they reach out
   themselves with an alternative suggestion (phone/email) – there is
   deliberately no automatic negotiation mechanism.
4. If nobody responds, the provisional request automatically expires
   after 48 hours and the slot becomes free again.

**Availability & calendar**
There is **no connection to an external calendar** (e.g. Google Workspace)
– deliberately, so that no client-related calendar entries run through a
third-party provider. Taken times result from:
- confirmed appointments (automatic),
- provisional requests (automatic, for 48 hours),
- **manually blocked times** – there's a "Termine sperren" (block
  appointments) form in the dashboard for this (court dates, personal
  appointments, vacation).

*Consequence:* the lawyer has to manually add appointments made outside
the form there, otherwise a visitor could book that slot.

**Adjusting office hours** (currently a placeholder: Mon–Fri 9am–6pm,
30-min grid): via the filters `kanzlei_cf_availability_rules`,
`kanzlei_cf_slot_duration_minutes`, `kanzlei_cf_booking_window_days`, and
`kanzlei_cf_pending_expiry_hours`.

**Building blocks**
- `blocks/contact-form/` – server-rendered block `kanzlei/contact-form`
  (fresh CSRF nonce per request).
- `inc/contact-form-custom.php` – complete server logic (validation, file,
  DB, mail, backend, cron).
- `assets/js/contact-form.js` – optional progressive enhancement (submit
  without reload). The form also works without JavaScript.
- `template-parts/contact-cards.php` – shared contact cards (also used by B).

**File uploads (multiple per request)**
- Default limits: max. **3 files** per request, max. **4 MB** each, max.
  **12 MB** total – configurable via the filters below.
- Every file goes through the same check (extension whitelist + real MIME
  type via `finfo`), regardless of count – no shortcuts for multi-file
  uploads.
- In the dashboard, the files for a request are displayed **bundled**
  (not as separate rows), with individual downloads plus a
  "Download all as ZIP" button once there are two or more files. If the
  `ZipArchive` PHP extension is missing on the server, the ZIP button is
  automatically hidden – individual downloads still work.
- Requests from the same person are **not** automatically merged
  (deliberately, for consistency with the deletion policy) – but they can
  be found in the dashboard via the search field by name/email.
- **Hosting note:** PHP's own limits (`upload_max_filesize`,
  `post_max_size`, `max_file_uploads` in `php.ini`) must be at least as
  generous as the values configured here, otherwise the upload fails
  before the theme's own check even runs. Verify with the host before
  going live.

**Setup**
1. **Activate the theme** – this automatically creates: the DB tables
   `…_kanzlei_contact` and `…_kanzlei_blocked_slots`, the private upload
   directory `wp-content/uploads/kanzlei-private/`, the **"Kanzlei-Verwaltung"**
   role, and two cron jobs (daily cleanup, hourly expiry of pending
   requests). **This does not yet make the pattern appear on the site** –
   `templates/front-page.html` includes the page content via
   `wp:post-content`, so the "Contact – Custom Form" pattern must be
   inserted in the block editor on the desired page (browse patterns →
   category "Kanzlei").
2. **Install and configure an SMTP plugin** (e.g. WP Mail SMTP, TLS) –
   `wp_mail()` shouldn't go through PHP's `mail()`.
3. **Set the retention period** under **Kontaktanfragen** (default: 30
   days). Unprocessed requests are automatically deleted after that; IP
   addresses are generally anonymized after 7 days.
4. **Create the lawyer's account**: a new user with the "Kanzlei-Verwaltung"
   role (least privilege instead of full admin). Administrators see the
   menu item regardless.
5. **Enable login hardening**: "Limit Login Attempts Reloaded" +
   "Two-Factor" plugins (optionally "WPS Hide Login").
6. **Provide the privacy policy** at `/datenschutz/` (the consent link in
   the form points there) and extend it to cover the form process.
7. **Ensure HTTPS for the entire site** (not just individual endpoints) –
   otherwise form data and file uploads travel unencrypted across the
   network on submit. Pure hosting configuration (TLS certificate, e.g.
   Let's Encrypt), no theme code.
8. **Sign a DPA with the host** – and additionally check whether they
   offer a **legally binding confidentiality obligation** that goes beyond
   a normal DSGVO (GDPR) DPA (Art. 28 DSGVO). A standard DPA is not sufficient for
   professionals bound by confidentiality (§ 203 StGB) as soon as a
   service provider could come into contact with client-related content –
   regardless of which host is ultimately chosen.
9. **Test in a real browser before going live** (not just via code
   review): click through the complete booking flow – pick a slot, attach
   file(s), submit, confirm/reject/block in the dashboard, verify
   downloads. So far the logic has only been tested headless (CLI/HTTP
   requests), not in a real browser with a screen reader/keyboard.

**Important – securing the private upload directory**
Uploads live under `wp-content/uploads/kanzlei-private/` with random
names and are only served via the authenticated download endpoint. An
`.htaccess` also lives there (only takes effect on **Apache**).
**On nginx**, direct access must be blocked via server configuration:

```nginx
location ^~ /wp-content/uploads/kanzlei-private/ { deny all; return 403; }
```

**Caching:** the contact page must **not** be served via full-page cache,
otherwise the CSRF nonce freezes. Exclude the page in the cache
configuration.

**Making cron reliable (recommended):** WP-Cron only runs when the site
has visitors. On a low-traffic law firm website, the 48-hour expiry of
provisional requests and the retention deadlines can therefore be
noticeably delayed. It's recommended to switch WP-Cron to a real system
cron:

```php
// in wp-config.php
define( 'DISABLE_WP_CRON', true );
```
```bash
# crontab -e  – e.g. every 15 minutes
*/15 * * * * cd /path/to/website && php wp-cron.php >/dev/null 2>&1
```

**Configurable (via filter, optional)**
- `kanzlei_cf_notify_email` – notification recipient (default: admin email)
- `kanzlei_cf_max_upload_mb` – max. size per file (default: 4)
- `kanzlei_cf_max_files` – max. number of files per request (default: 3)
- `kanzlei_cf_max_total_upload_mb` – max. total size per request (default: 12)
- `kanzlei_cf_allowed_types` – allowed file extensions/MIME types
- `kanzlei_cf_rate_limit` / `kanzlei_cf_rate_window` – rate limit per IP (default 5/hour)

**Removing variant A (if B is used)**
1. Delete the `require_once … 'inc/contact-form-custom.php'` line in `functions.php`
2. Delete the files: `inc/contact-form-custom.php`, `blocks/contact-form/`,
   `assets/js/contact-form.js`, `patterns/contact-form.php`
3. Optional: manually remove the DB table `…_kanzlei_contact`, the
   "Kanzlei-Verwaltung" role, and the private upload directory (left in
   place for data-safety reasons).

---

### Variant B: FluentBooking calendar (`patterns/contact-booking.php`)

Real appointment booking. The calendar/form comes from the plugin; the
pattern only provides layout (wide calendar area, contact cards below).

**Setup**
1. **Install FluentBooking** (Pro version required for external calendar
   sync).
2. In `patterns/contact-booking.php`, insert the **FluentBooking block**
   at the marked spot (once the plugin is installed and a calendar has
   been created), or alternatively use the shortcode block with
   `[fluent_booking id="…"]`.
3. **Enable Manual Confirmation** in the calendar settings, so bookings
   only become binding once confirmed by the lawyer.
4. **Configure Booking Questions**: textarea (message), file field (file),
   Terms & Conditions (consent, required field).
5. **Trim the email notification to metadata-only** – don't send message
   text or a file link to Google Workspace (§ 203 StGB).
6. Secure the plugin's upload directory against direct access (analogous
   to A).

`inc/contact-form-booking-hooks.php` is only a **defensive safety layer**:
it does nothing as long as FluentBooking is not active. The email
redaction hook it contains is a second line of defense – the exact filter
name is **version-dependent and must be checked against the installed
FluentBooking version before use**. The plugin configuration remains
authoritative.

**Unresolved, to be checked before purchase/use:** whether Manual
Confirmation as well as the File Field, Textarea, and Terms & Conditions
questions can be used together in the free FluentBooking version, or
whether there's an additional restriction on top of the Pro version
already needed for calendar sync – check directly against the current
FluentBooking backend/pricing comparison before project start, as this
changes depending on the plugin version.

**Removing variant B (if A is used)**
1. Delete the `require_once … 'inc/contact-form-booking-hooks.php'` line in `functions.php`
2. Delete the files: `inc/contact-form-booking-hooks.php`, `patterns/contact-booking.php`

---

## Note for follow-up projects

Experience shows future clients only need **one** of the two approaches.
Recommended model: maintain `kanzlei-theme` as a base theme with both
modules, clone it per new client, and delete the unused approach as
described above (file + pattern + `require` line), instead of building a
separate theme from scratch for every client. How improvements to the
base theme are later passed on to already-delivered client repos (e.g.
Git branches per client with manual cherry-picking vs. a shared core
package as a Composer dependency) is deliberately still open – this only
becomes relevant once a second client actually comes along.
