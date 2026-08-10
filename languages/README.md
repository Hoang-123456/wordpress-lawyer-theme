# Translations

This theme is built for a German/English site via [Polylang](https://wordpress.org/plugins/polylang/)
(see the "Multilingual (Polylang)" section in the main README for setup).
Two separate mechanisms are involved:

- **Page/template content** (header, footer, patterns once inserted into
  pages) is translated per-language directly in the Site Editor, using
  Polylang's own duplicate/translate workflow. No files here are involved.
- **PHP-generated strings** (e.g. the contact-form frontend in
  `blocks/contact-form/render.php`) are translated via classic WordPress
  gettext. Polylang switches the frontend locale per language; WordPress
  then loads the matching `.mo` file via `load_theme_textdomain()`
  (already set up in `functions.php`).

Files in this directory:

- `kanzlei-theme.pot` — template listing every translatable string in the
  theme's PHP files. Regenerate after adding/changing `__()`/`esc_html_e()`
  calls with:
  ```
  wp i18n make-pot . languages/kanzlei-theme.pot --domain=kanzlei-theme
  ```
- `kanzlei-theme-en_US.po` / `.mo` — English translations. Currently covers
  the **visitor-facing** contact-form strings only; the wp-admin-only
  strings (request management screen, internal booking-notification text)
  are intentionally left untranslated, since wp-admin's language follows
  each logged-in user's own profile setting, not the frontend Polylang
  switch. Add more languages the same way, e.g. `kanzlei-theme-fr_FR.po`.
- After editing a `.po` file, recompile it with:
  ```
  msgfmt languages/kanzlei-theme-en_US.po -o languages/kanzlei-theme-en_US.mo
  ```
