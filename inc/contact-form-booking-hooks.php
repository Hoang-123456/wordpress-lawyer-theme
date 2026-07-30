<?php
/**
 * FluentBooking-Absicherung – schlanke, defensive Hook-Schicht.
 *
 * WICHTIG zur Einordnung: Dies ist KEIN Eigenformular, sondern nur eine dünne
 * Sicherungsschicht um das externe Plugin FluentBooking. Die eigentliche
 * DSGVO-/§203-Absicherung passiert in der Plugin-KONFIGURATION (Manual
 * Confirmation, Booking Questions, Metadaten-only-Benachrichtigung, Upload-
 * Limits) – siehe README, Abschnitt „Variante B“. Diese Datei ergänzt nur, was
 * sich sinnvoll in Code gießen lässt.
 *
 * Bewusst defensiv: Ist FluentBooking nicht (mehr) installiert, passiert hier
 * nichts – ein versehentlich verbliebener Datei-Rest verursacht keinen Fehler.
 *
 * Entfernen dieses Ansatzes: diese Datei löschen, require-Zeile in functions.php
 * streichen, patterns/contact-booking.php löschen.
 *
 * @package kanzlei-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Kein FluentBooking aktiv → nichts tun. Deckt frei/pro und Namespace-Varianten ab.
if ( ! class_exists( '\FluentBooking\App\App' ) && ! defined( 'FLUENT_BOOKING_VERSION' ) ) {
	return;
}

/**
 * Sicherheitsnetz für die Benachrichtigungs-E-Mail an die Kanzlei.
 *
 * Ziel wie beim Custom-Ansatz: an Google Workspace gehen nur Metadaten, kein
 * Nachrichtentext / kein Datei-Link (§ 203 StGB). FluentBooking bietet die
 * primäre Kontrolle über seine eigenen E-Mail-Einstellungen; dieser Filter ist
 * die zweite Verteidigungslinie, falls dort versehentlich sensible Platzhalter
 * eingebaut werden.
 *
 * HINWEIS: Der genaue Filtername ist versionsabhängig und MUSS gegen die
 * installierte FluentBooking-Version geprüft werden (Plugin-Doku/Quellcode:
 * nach „apply_filters( '…email…' )“ suchen). Bis dahin ist die Plugin-
 * Konfiguration die maßgebliche Absicherung, nicht dieser Hook.
 */
add_filter( 'kanzlei_fluent_booking_notification_body', 'kanzlei_booking_redact_sensitive', 10, 1 );
function kanzlei_booking_redact_sensitive( $body ) {
	if ( ! is_string( $body ) || '' === $body ) {
		return $body;
	}

	// Bekannte sensible Merge-Tags entschärfen, falls sie in der Vorlage stehen.
	$sensitive_tags = apply_filters(
		'kanzlei_booking_sensitive_tags',
		array( '{{booking.custom.message}}', '{{booking.custom.file}}', '{{booking.custom.attachment}}' )
	);

	$redacted = str_replace(
		$sensitive_tags,
		__( '[im Backend einsehen]', 'kanzlei-theme' ),
		$body
	);

	return $redacted;
}
