<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      Hero
 * Slug:       kanzlei/hero
 * Categories: kanzlei
 */
?>

<!-- wp:group {"align":"full","className":"","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"backgroundColor":"primary","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-primary-background-color has-background">

    <!-- wp:group {"layout":{"type":"constrained","contentSize":"640px"}} -->
    <div class="wp-block-group">

        <!-- wp:paragraph {"style":{"typography":{"letterSpacing":"0.12em","textTransform":"uppercase","fontWeight":"600"}},"textColor":"accent","fontSize":"sm"} -->
        <p class="has-accent-color has-text-color has-sm-font-size" style="letter-spacing:0.12em;text-transform:uppercase;font-weight:600;">Rechtsanwalt · Köln</p>
        <!-- /wp:paragraph -->

        <!-- wp:heading {"level":1,"textColor":"white"} -->
        <h1 class="wp-block-heading has-white-color has-text-color">Ihr Anwalt für <em>Asyl-, Miet-, Arbeits- und Strafrecht</em></h1>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"textColor":"white","style":{"typography":{"fontSize":"var:preset|font-size|md"},"spacing":{"margin":{"top":"1.5rem"}}}} -->
        <p class="has-white-color has-text-color" style="font-size:var(--wp--preset--font-size--md);margin-top:1.5rem;">Kompetente und persönliche Rechtsberatung in Köln. Ich setze mich konsequent für Ihre Rechte ein – klar, transparent und auf Augenhöhe.</p>
        <!-- /wp:paragraph -->

        <!-- wp:buttons {"style":{"spacing":{"margin":{"top":"2.5rem"}}}} -->
        <div class="wp-block-buttons" style="margin-top:2.5rem;">

            <!-- wp:button {"backgroundColor":"accent","textColor":"white"} -->
            <div class="wp-block-button">
                <a class="wp-block-button__link has-accent-background-color has-white-color has-text-color has-background wp-element-button" href="#contact">Kostenlose Ersteinschätzung</a>
            </div>
            <!-- /wp:button -->

            <!-- wp:button {"className":"is-style-outline","textColor":"white","style":{"color":{"background":"transparent"},"border":{"color":"var:preset|color|white","width":"1.5px"}}} -->
            <div class="wp-block-button is-style-outline">
                <a class="wp-block-button__link has-white-color has-text-color wp-element-button" href="#services" style="background:transparent;border-color:var(--wp--preset--color--white);border-width:1.5px;">Leistungen ansehen</a>
            </div>
            <!-- /wp:button -->

        </div>
        <!-- /wp:buttons -->

    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->
