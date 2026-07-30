<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Contact – Custom-Formular
 * Slug:       kanzlei/contact-form
 * Categories: kanzlei
 *
 * Ansatz 1 (Custom-Code): schlankes, sicheres Eigenformular. Die Karten links
 * kommen aus template-parts/contact-cards.php, das Formular rechts ist der
 * serverseitig gerenderte Block „kanzlei/contact-form“.
 *
 * Gegenstück: patterns/contact-booking.php (FluentBooking). Eine Variante
 * wählen, die andere löschen – siehe README.
 */
?>

<!-- wp:group {"align":"full","anchor":"contact","className":"section-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull section-light" id="contact">

	<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|l"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--l);">

		<!-- wp:heading {"level":2,"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center">Lassen Sie uns sprechen</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">Wählen Sie einen freien Termin für eine kostenlose Erstberatung. Ich bestätige Ihren Wunschtermin innerhalb von 24 Stunden.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"top","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|l"}}}} -->
	<div class="wp-block-columns is-layout-flex are-vertically-aligned-top">

		<!-- wp:column {"width":"33%"} -->
		<div class="wp-block-column" style="flex-basis:33%;">
			<?php include get_theme_file_path( 'template-parts/contact-cards.php' ); ?>
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"67%"} -->
		<div class="wp-block-column" style="flex-basis:67%;">

			<!-- wp:kanzlei/contact-form /-->

		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->
