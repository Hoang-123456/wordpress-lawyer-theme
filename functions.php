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
 * Grundausstattung: Übersetzungen, Theme-Supports, Editor-Vorschau.
 * Alles Ein-Zeiler ohne Laufzeit-/Performance-Kosten – Standard-Baseline
 * für jedes WordPress-Theme.
 */
add_action( 'after_setup_theme', function () {
	// Übersetzbarkeit vorbereiten (Sprachdateien in /languages).
	load_theme_textdomain( 'kanzlei-theme', get_template_directory() . '/languages' );

	// Logo-Upload im Site-Editor zuverlässig aktivieren (wp:site-logo Block).
	add_theme_support( 'custom-logo' );

	// Für ein mögliches späteres Blog/News-Pattern (Beiträge mit Bild).
	add_theme_support( 'post-thumbnails' );

	// Saubere HTML5-Auszeichnung für Formulare/Kommentare/Galerien.
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Eingebettete Inhalte (z. B. YouTube) responsiv statt fixer Breite.
	add_theme_support( 'responsive-embeds' );

	// Editor-Vorschau nutzt dasselbe Stylesheet wie das Frontend – Redakteure
	// sehen im Block-Editor dieselben Farben/Schriften wie live auf der Seite.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
} );

/**
 * Pattern-Kategorie registrieren.
 */
add_action( 'init', function () {
	register_block_pattern_category(
		'kanzlei',
		array( 'label' => __( 'Kanzlei', 'kanzlei-theme' ) )
	);
} );

/**
 * Kontaktbereich – zwei wählbare Ansätze, jeweils eigenständig entfernbar.
 * Nicht genutzten Ansatz löschen: Datei löschen + zugehörige require-Zeile
 * streichen (+ zugehöriges Pattern/Block-Verzeichnis). Details siehe README.
 */
// Ansatz 1: Custom-Code-Formular (Handler, DB, Mail, Backend, Cron, Block).
require_once get_theme_file_path( 'inc/contact-form-custom.php' );

// Ansatz 2: FluentBooking-Absicherung (defensiv, tut nichts ohne das Plugin).
require_once get_theme_file_path( 'inc/contact-form-booking-hooks.php' );

/**
 * Kritische Above-the-Fold-Schriften vorladen (Body-Text + Überschrift).
 * Beide werden auf jeder Seite sofort gebraucht (Header, H1); ohne Preload
 * entdeckt der Browser sie erst nach dem CSS-Parsing, was die Textdarstellung
 * (LCP) unnötig verzögert.
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
