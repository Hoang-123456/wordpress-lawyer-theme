<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title:      About
 * Slug:       kanzlei/about
 * Categories: kanzlei
 */
?>

<!-- wp:group {"align":"full","anchor":"about","className":"about-section","backgroundColor":"primary","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull about-section has-primary-background-color has-background" id="about">

    <!-- Decorative blur circles (CSS in style.css → .about-blur) -->
    <!-- wp:html -->
    <div class="about-blur about-blur--top" aria-hidden="true"></div>
    <div class="about-blur about-blur--bottom" aria-hidden="true"></div>
    <!-- /wp:html -->

    <!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|xl"}}}} -->
    <div class="wp-block-columns is-layout-flex are-vertically-aligned-center">

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%;">

            <!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"8px"}},"alt":"Rechtsanwalt"} -->
            <figure class="wp-block-image size-large" style="border-radius:8px;">
                <img src="" alt="Rechtsanwalt" style="border-radius:8px;" />
            </figure>
            <!-- /wp:image -->

        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%;">

            <!-- wp:paragraph {"style":{"typography":{"letterSpacing":"0.12em","textTransform":"uppercase","fontWeight":"600"}},"textColor":"accent","fontSize":"sm"} -->
            <p class="has-accent-color has-text-color has-sm-font-size" style="letter-spacing:0.12em;text-transform:uppercase;font-weight:600;">Über mich</p>
            <!-- /wp:paragraph -->

            <!-- wp:heading {"level":2,"textColor":"white"} -->
            <h2 class="wp-block-heading has-white-color has-text-color">Max Mustermann<br>Rechtsanwalt</h2>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"textColor":"white","fontSize":"md"} -->
            <p class="has-white-color has-text-color has-md-font-size">Als Rechtsanwalt mit vietnamesischen Wurzeln kenne ich die besonderen Herausforderungen meiner Mandanten aus erster Hand. Ich berate und verteide konsequent, klar und auf Augenhöhe – in Deutsch und Vietnamesisch.</p>
            <!-- /wp:paragraph -->

            <!-- wp:paragraph {"style":{"color":{"text":"rgba(255,255,255,0.75)"}}} -->
            <p class="has-text-color" style="color:rgba(255,255,255,0.75);">Ich bin Mitglied der Rechtsanwaltskammer Köln und habe mich direkt nach dem Studium selbstständig gemacht. Persönliche Betreuung und ehrliche Einschätzungen stehen bei mir an erster Stelle – keine Versprechen, die ich nicht halten kann.</p>
            <!-- /wp:paragraph -->

        </div>
        <!-- /wp:column -->

    </div>
    <!-- /wp:columns -->

</div>
<!-- /wp:group -->
