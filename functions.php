<?php
/**
 * Kanzlei Theme – functions.php
 * FSE theme: theme.json handles most styling.
 * Only what theme.json cannot cover goes here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic setup: translations, theme supports, editor preview.
 * All one-liners with no runtime/performance cost – standard baseline
 * for every WordPress theme.
 */
add_action( 'after_setup_theme', function () {
	// Prepare translatability (language files in /languages).
	load_theme_textdomain( 'kanzlei-theme', get_template_directory() . '/languages' );

	// Reliably enable logo upload in the Site Editor (wp:site-logo block).
	add_theme_support( 'custom-logo' );

	// For a possible future blog/news pattern (posts with image).
	add_theme_support( 'post-thumbnails' );

	// Clean HTML5 markup for forms/comments/galleries.
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Embedded content (e.g. YouTube) responsive instead of a fixed width.
	add_theme_support( 'responsive-embeds' );

	// Editor preview uses the same stylesheet as the frontend – editors
	// see the same colors/fonts in the block editor as live on the site.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
} );

/**
 * Register pattern category.
 */
add_action( 'init', function () {
	register_block_pattern_category(
		'kanzlei',
		array( 'label' => __( 'Kanzlei', 'kanzlei-theme' ) )
	);
} );

/**
 * Contact section – three selectable approaches, each independently removable.
 * To remove an unused approach: delete the file + the associated require
 * line (+ the associated pattern/block directory). See README for details.
 * Approach 3 (cards only, patterns/contact-cards-only.php) has no PHP
 * backend of its own, so there's no require line for it here.
 */
// Approach 1: custom-code form (handler, DB, mail, backend, cron, block).
require_once get_theme_file_path( 'inc/contact-form-custom.php' );

// Approach 2: FluentBooking safeguard (defensive, does nothing without the plugin).
require_once get_theme_file_path( 'inc/contact-form-booking-hooks.php' );

/**
 * Preload critical above-the-fold fonts (body text + heading).
 * Both are needed immediately on every page (header, H1); without preload
 * the browser only discovers them after CSS parsing, which unnecessarily
 * delays text rendering (LCP).
 */
add_action( 'wp_head', function () {
	$uri = get_template_directory_uri();
	printf(
		'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
		esc_url( $uri . '/assets/fonts/inter-400.woff2' )
	);
	printf(
		'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
		esc_url( $uri . '/assets/fonts/playfair-display-700.woff2' )
	);
}, 1 );
