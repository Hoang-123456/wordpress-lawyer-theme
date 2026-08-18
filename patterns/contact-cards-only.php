<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Contact – Info Cards Only
 * Slug:       kanzlei/contact-cards-only
 * Categories: kanzlei
 *
 * Approach 3 (cards only): kein Formular, kein Buchungswidget, kein
 * PHP-Backend – nur die statischen Kontaktinfos aus
 * template-parts/contact-cards.php. Für Kanzleien, die direkten
 * Kontakt (Anruf/E-Mail) statt Online-Formular oder Buchungsflow wollen.
 *
 * Gegenstück: patterns/contact-form.php (eigenes Formular), patterns/
 * contact-booking.php (FluentBooking). Genau eine der drei Varianten
 * wählen, die anderen zwei löschen – siehe README.
 */
?>

<!-- wp:group {"align":"full","anchor":"contact","className":"section-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull section-light" id="contact">

	<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|l"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--l);">

		<!-- wp:heading {"level":2,"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center">Kontaktieren Sie mich direkt</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">Rufen Sie an, schreiben Sie eine E-Mail oder besuchen Sie mich in der Kanzlei – ich freue mich auf Ihre Nachricht.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"contact-cards-row","layout":{"type":"default"}} -->
	<div class="wp-block-group contact-cards-row">
		<?php include get_theme_file_path( 'template-parts/contact-cards.php' ); ?>
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
