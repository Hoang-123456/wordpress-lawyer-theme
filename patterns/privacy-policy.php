<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Privacy Policy
 * Slug:       kanzlei/privacy-policy
 * Categories: kanzlei
 * Inserter:   false
 *
 * Note: the H1 now comes exclusively from the WordPress page title
 * (post-title in templates/page.html). When creating the page in the
 * backend, enter "Datenschutzerklärung" as the title, otherwise the H1
 * will not match the page content.
 */
?>

<!-- wp:group {"className":"legal-page","style":{"spacing":{"padding":{"top":"var:preset|spacing|l","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group legal-page">

    <!-- wp:html -->
    <a href="/" class="legal-back-link">← Zurück zur Startseite</a>
    <!-- /wp:html -->

    <!-- wp:paragraph {"className":"legal-meta"} -->
    <p class="legal-meta">Stand: Juni 2026</p>
    <!-- /wp:paragraph -->

    <!-- wp:html -->
    <section>
        <h2>1. Verantwortlicher</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
        <ul class="legal-list">
            <li><strong>Name:</strong> Max Mustermann</li>
            <li><strong>Adresse:</strong> Musterstraße 1, 50667 Köln</li>
            <li><strong>E-Mail:</strong> info@kanzlei-nguyen.de</li>
            <li><strong>Telefon:</strong> +49 221 123 456</li>
        </ul>
    </section>
    <!-- /wp:html -->

    <!-- wp:html -->
    <section>
        <h2>2. Erhebung und Verarbeitung personenbezogener Daten</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>

        <h3>2.1 Kontaktformular</h3>
        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
        <ul class="legal-list">
            <li>Name und Vorname</li>
            <li>E-Mail-Adresse</li>
            <li>Telefonnummer (optional)</li>
            <li>Inhalt Ihrer Nachricht</li>
        </ul>

        <h3>2.2 Rechtsgrundlage</h3>
        <p>Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur.</p>

        <table class="legal-table">
            <thead>
                <tr>
                    <th>Verarbeitung</th>
                    <th>Zweck</th>
                    <th>Rechtsgrundlage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Kontaktformular</td>
                    <td>Bearbeitung Ihrer Anfrage</td>
                    <td>Art. 6 Abs. 1 lit. f DSGVO</td>
                </tr>
                <tr>
                    <td>Server-Logfiles</td>
                    <td>Technischer Betrieb</td>
                    <td>Art. 6 Abs. 1 lit. f DSGVO</td>
                </tr>
            </tbody>
        </table>
    </section>
    <!-- /wp:html -->

    <!-- wp:html -->
    <section>
        <h2>3. Ihre Rechte</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.</p>
        <ul class="legal-list">
            <li><strong>Auskunft</strong> (Art. 15 DSGVO)</li>
            <li><strong>Berichtigung</strong> (Art. 16 DSGVO)</li>
            <li><strong>Löschung</strong> (Art. 17 DSGVO)</li>
            <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO)</li>
            <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO)</li>
            <li><strong>Widerspruch</strong> (Art. 21 DSGVO)</li>
        </ul>
        <p>Zur Ausübung Ihrer Rechte wenden Sie sich an: info@kanzlei-nguyen.de</p>
    </section>
    <!-- /wp:html -->

</div>
<!-- /wp:group -->
