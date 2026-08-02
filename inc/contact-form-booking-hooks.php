<?php
/**
 * FluentBooking safeguard – a thin, defensive hook layer.
 *
 * IMPORTANT context: this is NOT an in-house form, only a thin safety layer
 * around the external FluentBooking plugin. The actual DSGVO (GDPR)/§203 safeguarding
 * happens in the plugin CONFIGURATION (Manual Confirmation, Booking Questions,
 * metadata-only notification, upload limits) – see README, section
 * "Variant B". This file only adds what can meaningfully be expressed in code.
 *
 * Deliberately defensive: if FluentBooking is not (or no longer) installed,
 * nothing happens here – an accidentally leftover file causes no error.
 *
 * To remove this approach: delete this file, remove the require line in
 * functions.php, delete patterns/contact-booking.php.
 *
 * @package kanzlei-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No FluentBooking active → do nothing. Covers free/pro and namespace variants.
if ( ! class_exists( '\FluentBooking\App\App' ) && ! defined( 'FLUENT_BOOKING_VERSION' ) ) {
	return;
}

/**
 * Safety net for the notification email sent to the law firm.
 *
 * Same goal as in the custom approach: only metadata goes to Google Workspace,
 * no message text / no file link (§ 203 StGB). FluentBooking provides the
 * primary control via its own email settings; this filter is the second line
 * of defense in case sensitive placeholders end up in the template by mistake.
 *
 * NOTE: the exact filter name is version-dependent and MUST be checked
 * against the installed FluentBooking version (plugin docs/source: search
 * for "apply_filters( '…email…' )"). Until then, the plugin configuration
 * is the authoritative safeguard, not this hook.
 */
add_filter( 'kanzlei_fluent_booking_notification_body', 'kanzlei_booking_redact_sensitive', 10, 1 );
function kanzlei_booking_redact_sensitive( $body ) {
	if ( ! is_string( $body ) || '' === $body ) {
		return $body;
	}

	// Neutralize known sensitive merge tags if they appear in the template.
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
