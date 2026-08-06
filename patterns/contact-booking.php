<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Contact – FluentBooking Calendar
 * Slug:       kanzlei/contact-booking
 * Categories: kanzlei
 *
 * Approach 2 (FluentBooking): real calendar booking with message & file
 * upload in one step. See README.md, "Variant B", for setup and
 * trade-offs.
 *
 * Deliberately wider layout than the custom form: the calendar needs
 * horizontal width, so it spans the full column here and the contact cards
 * sit in a row below it (not beside it).
 *
 * Counterpart: patterns/contact-form.php (custom code). Choose one variant,
 * delete the other – see README.
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
			INSERT FLUENTBOOKING HERE once the plugin is installed and a
			calendar has been created:
			Block (+) → select "FluentBooking" and choose the matching calendar,
			or alternatively use the shortcode block with [fluent_booking id="…"].
			For configuration (Manual Confirmation, Booking Questions, metadata-only
			email) see README, section "Variant B".
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
