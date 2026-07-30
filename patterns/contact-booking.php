<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Contact – FluentBooking-Kalender
 * Slug:       kanzlei/contact-booking
 * Categories: kanzlei
 *
 * Ansatz 2 (FluentBooking): echte Kalender-Buchung mit Nachricht & Datei in
 * einem Schritt. Details/Trade-offs siehe alternative-echtzeit-buchung-mit-upload.md.
 *
 * Bewusst breiteres Layout als beim Custom-Formular: Der Kalender braucht
 * horizontale Breite, deshalb steht er hier über die volle Spalte und die
 * Kontaktkarten liegen als Reihe darunter (nicht daneben).
 *
 * Gegenstück: patterns/contact-form.php (Custom-Code). Eine Variante wählen,
 * die andere löschen – siehe README.
 */
?>

<!-- wp:group {"align":"full","anchor":"contact","className":"section-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull section-light" id="contact">

	<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|l"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--l);">

		<!-- wp:heading {"level":2,"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center">Termin direkt buchen</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">Wählen Sie einen freien Termin, schildern Sie Ihr Anliegen und laden Sie bei Bedarf ein Dokument hoch – alles in einem Schritt.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"booking-embed","layout":{"type":"constrained","contentSize":"900px"}} -->
	<div class="wp-block-group booking-embed">

		<!--
			FLUENTBOOKING HIER EINFÜGEN, nachdem das Plugin installiert und ein
			Kalender angelegt wurde:
			Block (+) → „FluentBooking“ auswählen und den passenden Kalender wählen,
			oder alternativ den Shortcode-Block mit [fluent_booking id="…"] einsetzen.
			Konfiguration (Manual Confirmation, Booking Questions, Metadaten-only-Mail)
			siehe README, Abschnitt „Variante B“.
		-->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"contact-cards-row","style":{"spacing":{"margin":{"top":"var:preset|spacing|l"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group contact-cards-row" style="margin-top:var(--wp--preset--spacing--l);">
		<?php include get_theme_file_path( 'template-parts/contact-cards.php' ); ?>
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
