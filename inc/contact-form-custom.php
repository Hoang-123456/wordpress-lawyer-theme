<?php
/**
 * Custom-code appointment booking – complete server logic in one file.
 *
 * Deliberately encapsulated: to fully remove this approach, it's enough to
 * delete this file, remove the require line in functions.php, and remove
 * the associated frontend parts (blocks/contact-form/, patterns/contact-form.php).
 * No scattered hooks are left behind.
 *
 * Flow: visitor picks an open slot (no free-text appointment request) →
 * request lands as "pending" in the backend → lawyer confirms or rejects.
 * On rejection, the lawyer reaches out themselves with an alternative
 * (phone/email) – no automatic negotiation mechanism.
 *
 * No access to external calendars (e.g. Google Workspace): availability is
 * calculated exclusively from the theme's own database (confirmed +
 * pending requests + manually blocked times). This prevents client-related
 * calendar entries (e.g. appointment titles with client names) from passing
 * through a third-party provider.
 *
 * Security/DSGVO (GDPR) architecture (see README.md, "Background"):
 * - Sensitive content (message, file) never leaves the server.
 * - The email to the law firm contains only metadata (name, appointment),
 *   no message text, no file – due to § 203 StGB / attorney confidentiality.
 * - Full visibility only via the login-protected backend dashboard.
 *
 * @package kanzlei-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Constants & small helpers
 * ─────────────────────────────────────────────────────────────────────────
 */

/** Capability allowed to view contact requests (least-privilege dedicated role). */
const KANZLEI_CF_CAP = 'manage_kanzlei_submissions';

/** Cron hook for the daily cleanup task (deletion policy, IP anonymization). */
const KANZLEI_CF_CRON = 'kanzlei_cf_cleanup';

/** Cron hook for the hourly check for expired, unconfirmed requests. */
const KANZLEI_CF_EXPIRE_CRON = 'kanzlei_cf_expire_pending';

/** Name of the requests table (without prefix). */
function kanzlei_cf_table() {
	return $GLOBALS['wpdb']->prefix . 'kanzlei_contact';
}

/** Name of the table for manually blocked times (without prefix). */
function kanzlei_cf_blocked_table() {
	return $GLOBALS['wpdb']->prefix . 'kanzlei_blocked_slots';
}

/** Name of the table for file uploads per request (without prefix). */
function kanzlei_cf_files_table() {
	return $GLOBALS['wpdb']->prefix . 'kanzlei_contact_files';
}

/**
 * Allowed file uploads: extension => real MIME type (verified via finfo).
 * Deliberately a short whitelist instead of a blacklist.
 */
function kanzlei_cf_allowed_types() {
	return apply_filters(
		'kanzlei_cf_allowed_types',
		array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
		)
	);
}

/** Maximum size per file in bytes (default 4 MB, kept in sync with render.php). */
function kanzlei_cf_max_upload_bytes() {
	return (int) apply_filters( 'kanzlei_cf_max_upload_mb', 4 ) * MB_IN_BYTES;
}

/** Maximum number of files per request (default 3). */
function kanzlei_cf_max_files() {
	return (int) apply_filters( 'kanzlei_cf_max_files', 3 );
}

/** Maximum combined size of all files in a request, in bytes (default 12 MB = 3 × 4 MB). */
function kanzlei_cf_max_total_upload_bytes() {
	return (int) apply_filters( 'kanzlei_cf_max_total_upload_mb', 12 ) * MB_IN_BYTES;
}

/** Private upload directory (outside the publicly linked paths). */
function kanzlei_cf_private_dir() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'kanzlei-private';
}

/**
 * Ensures the private directory exists and is protected against direct access.
 * The .htaccess only takes effect on Apache; the actual security comes from
 * random filenames + the exclusively authenticated download. On nginx, also
 * set a location block (see README).
 */
function kanzlei_cf_ensure_private_dir() {
	$dir = kanzlei_cf_private_dir();
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
	}
	if ( ! file_exists( $dir . '/index.html' ) ) {
		file_put_contents( $dir . '/index.html', '' );
	}
}

/** Client IP – deliberately only REMOTE_ADDR (X-Forwarded-For can be spoofed). */
function kanzlei_cf_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	$ip = filter_var( $ip, FILTER_VALIDATE_IP );
	return apply_filters( 'kanzlei_cf_client_ip', $ip ? $ip : '' );
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Availability & slot calculation
 *  Everything deliberately from the theme's own DB only – no external
 *  calendar access.
 * ─────────────────────────────────────────────────────────────────────────
 */

/**
 * Office hours rules: ISO weekday (1=Monday … 7=Sunday) => [start, end].
 * Placeholder values until the lawyer sets the actual hours/consultation
 * duration – then simply adjust here or via filter.
 */
function kanzlei_cf_availability_rules() {
	return apply_filters(
		'kanzlei_cf_availability_rules',
		array(
			1 => array( '09:00', '18:00' ), // Monday
			2 => array( '09:00', '18:00' ), // Tuesday
			3 => array( '09:00', '18:00' ), // Wednesday
			4 => array( '09:00', '18:00' ), // Thursday
			5 => array( '09:00', '18:00' ), // Friday
		)
	);
}

/** Slot duration in minutes (placeholder, see kanzlei_cf_availability_rules()). */
function kanzlei_cf_slot_duration_minutes() {
	return (int) apply_filters( 'kanzlei_cf_slot_duration_minutes', 30 );
}

/** How many days in advance can be booked (starting tomorrow, not same-day). */
function kanzlei_cf_booking_window_days() {
	return (int) apply_filters( 'kanzlei_cf_booking_window_days', 30 );
}

/** After how many hours an unconfirmed request expires automatically. */
function kanzlei_cf_pending_expiry_hours() {
	return (int) apply_filters( 'kanzlei_cf_pending_expiry_hours', 48 );
}

/**
 * Formats a stored slot ('Y-m-d H:i:s') for display,
 * e.g. "Monday, 21 July 2026, 09:00".
 */
function kanzlei_cf_format_slot( $slot ) {
	$dt = date_create( $slot, wp_timezone() );
	if ( ! $dt ) {
		return $slot;
	}
	return wp_date( 'l, d. F Y, H:i \U\h\r', $dt->getTimestamp(), wp_timezone() );
}

/**
 * Already taken times within the given range: confirmed appointments,
 * not-yet-expired pending requests, manually blocked times.
 */
function kanzlei_cf_taken_slots( $from, $to ) {
	global $wpdb;
	$table         = kanzlei_cf_table();
	$blocked_table = kanzlei_cf_blocked_table();

	$confirmed = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT appointment_slot FROM {$table} WHERE status = 'confirmed' AND appointment_slot BETWEEN %s AND %s", // phpcs:ignore WordPress.DB
			$from,
			$to
		)
	);

	$pending_cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - kanzlei_cf_pending_expiry_hours() * HOUR_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$pending        = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT appointment_slot FROM {$table} WHERE status = 'pending' AND created_at > %s AND appointment_slot BETWEEN %s AND %s", // phpcs:ignore WordPress.DB
			$pending_cutoff,
			$from,
			$to
		)
	);

	$blocked = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT blocked_at FROM {$blocked_table} WHERE blocked_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB
			$from,
			$to
		)
	);

	return array_unique( array_merge( $confirmed, $pending, $blocked ) );
}

/**
 * All currently free slots within the booking window, grouped by date:
 * [ 'Y-m-d' => [ 'H:i' => 'Y-m-d H:i:s', … ], … ]
 */
function kanzlei_cf_available_slots() {
	$rules    = kanzlei_cf_availability_rules();
	$duration = kanzlei_cf_slot_duration_minutes();
	$window   = kanzlei_cf_booking_window_days();

	$now_local = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$start_day = strtotime( gmdate( 'Y-m-d', $now_local ) . ' +1 day' );
	$end_day   = strtotime( gmdate( 'Y-m-d', $start_day ) . ' +' . ( $window - 1 ) . ' days' );

	$taken = kanzlei_cf_taken_slots(
		gmdate( 'Y-m-d H:i:s', $start_day ),
		gmdate( 'Y-m-d H:i:s', $end_day + DAY_IN_SECONDS - 1 )
	);

	$slots = array();
	for ( $day = $start_day; $day <= $end_day; $day += DAY_IN_SECONDS ) {
		$weekday = (int) gmdate( 'N', $day );
		if ( ! isset( $rules[ $weekday ] ) ) {
			continue;
		}
		list( $from, $to ) = $rules[ $weekday ];
		$date_str = gmdate( 'Y-m-d', $day );
		$cursor   = strtotime( $date_str . ' ' . $from );
		$day_end  = strtotime( $date_str . ' ' . $to );

		while ( $cursor + $duration * MINUTE_IN_SECONDS <= $day_end ) {
			$value = gmdate( 'Y-m-d H:i:s', $cursor );
			if ( ! in_array( $value, $taken, true ) ) {
				$slots[ $date_str ][ gmdate( 'H:i', $cursor ) ] = $value;
			}
			$cursor += $duration * MINUTE_IN_SECONDS;
		}
	}
	return $slots;
}

/** Checks whether a given slot value is actually still free right now. */
function kanzlei_cf_is_slot_available( $value ) {
	foreach ( kanzlei_cf_available_slots() as $times ) {
		if ( in_array( $value, $times, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Theme activation / deactivation
 * ─────────────────────────────────────────────────────────────────────────
 */

add_action( 'after_switch_theme', 'kanzlei_cf_activate' );
function kanzlei_cf_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = kanzlei_cf_table();
	$blocked = kanzlei_cf_blocked_table();
	$files   = kanzlei_cf_files_table();
	$collate = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		name varchar(120) NOT NULL,
		email varchar(180) NOT NULL,
		appointment_slot datetime NOT NULL,
		message text NOT NULL,
		ip_address varchar(45) DEFAULT NULL,
		consent_given_at datetime NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		PRIMARY KEY  (id),
		KEY status (status),
		KEY appointment_slot (appointment_slot),
		KEY created_at (created_at)
	) {$collate};
	CREATE TABLE {$blocked} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		blocked_at datetime NOT NULL,
		reason varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY blocked_at (blocked_at)
	) {$collate};
	CREATE TABLE {$files} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL,
		file_name varchar(255) NOT NULL,
		original_filename varchar(255) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY contact_id (contact_id)
	) {$collate};";
	dbDelta( $sql );

	kanzlei_cf_ensure_private_dir();

	// Dedicated, restricted role for the lawyer's access (least privilege).
	add_role(
		'kanzlei_manager',
		__( 'Kanzlei-Verwaltung', 'kanzlei-theme' ),
		array(
			'read'         => true,
			KANZLEI_CF_CAP => true,
		)
	);

	// Schedule the daily cleanup task (deletion policy, IP anonymization).
	if ( ! wp_next_scheduled( KANZLEI_CF_CRON ) ) {
		wp_schedule_event( time(), 'daily', KANZLEI_CF_CRON );
	}
	// Hourly check for expired, unconfirmed requests – deliberately tighter
	// than the daily cron, so a blocked slot doesn't stay unnecessarily
	// reserved for up to three days.
	if ( ! wp_next_scheduled( KANZLEI_CF_EXPIRE_CRON ) ) {
		wp_schedule_event( time(), 'hourly', KANZLEI_CF_EXPIRE_CRON );
	}
}

// Unschedule cron on theme switch (data & tables remain untouched).
add_action( 'switch_theme', 'kanzlei_cf_deactivate' );
function kanzlei_cf_deactivate() {
	wp_clear_scheduled_hook( KANZLEI_CF_CRON );
	wp_clear_scheduled_hook( KANZLEI_CF_EXPIRE_CRON );
}

// Deliberately NO automatic pass-through of manage_options to KANZLEI_CF_CAP:
// client data (§ 203 StGB) should only be visible to whoever explicitly has
// the kanzlei_manager role – not every WP administrator (e.g. a maintaining
// agency/developer). Admin rights for user management (resetting passwords,
// reassigning roles, lifting 2FA/login locks) are unaffected by this, since
// they don't require a dedicated capability grant.

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Register block & frontend script
 * ─────────────────────────────────────────────────────────────────────────
 */

add_action( 'init', 'kanzlei_cf_register_block' );
function kanzlei_cf_register_block() {
	// View script (progressive enhancement) – loaded via block.json "viewScript"
	// only when the block actually appears on the page.
	wp_register_script(
		'kanzlei-contact-form-view',
		get_theme_file_uri( 'assets/js/contact-form.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type( get_theme_file_path( 'blocks/contact-form' ) );
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Form processing (admin-post: logged-in as well as logged-out)
 * ─────────────────────────────────────────────────────────────────────────
 */

add_action( 'admin_post_kanzlei_contact_submit', 'kanzlei_cf_handle_submit' );
add_action( 'admin_post_nopriv_kanzlei_contact_submit', 'kanzlei_cf_handle_submit' );

function kanzlei_cf_handle_submit() {
	$is_ajax = ! empty( $_POST['_ajax'] );

	// 1) CSRF nonce.
	if ( ! isset( $_POST['_kanzlei_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_kanzlei_nonce'] ), 'kanzlei_contact_submit' ) ) {
		kanzlei_cf_respond( false, __( 'Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.', 'kanzlei-theme' ), $is_ajax );
	}

	// 2) Honeypot: filled field = bot → reports "success" outwardly, saves nothing.
	if ( ! empty( $_POST['kanzlei_website'] ) ) {
		kanzlei_cf_respond( true, kanzlei_cf_success_text(), $is_ajax );
	}

	// 3) Simple rate limiting per IP (contain spam floods).
	if ( ! kanzlei_cf_rate_ok() ) {
		kanzlei_cf_respond( false, __( 'Zu viele Anfragen in kurzer Zeit. Bitte versuchen Sie es später erneut.', 'kanzlei-theme' ), $is_ajax );
	}

	// 4) Required consent (server-side, not just the required attribute).
	if ( empty( $_POST['kanzlei_consent'] ) || '1' !== (string) wp_unslash( $_POST['kanzlei_consent'] ) ) {
		kanzlei_cf_respond( false, __( 'Bitte stimmen Sie der Datenschutzerklärung zu.', 'kanzlei-theme' ), $is_ajax );
	}

	// 5) Validate & sanitize fields.
	$name    = isset( $_POST['kanzlei_name'] ) ? sanitize_text_field( wp_unslash( $_POST['kanzlei_name'] ) ) : '';
	$email   = isset( $_POST['kanzlei_email'] ) ? sanitize_email( wp_unslash( $_POST['kanzlei_email'] ) ) : '';
	$slot    = isset( $_POST['kanzlei_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['kanzlei_slot'] ) ) : '';
	$message = isset( $_POST['kanzlei_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['kanzlei_message'] ) ) : '';

	if ( '' === $name || '' === $message || ! is_email( $email ) ) {
		kanzlei_cf_respond( false, __( 'Bitte füllen Sie alle Pflichtfelder korrekt aus.', 'kanzlei-theme' ), $is_ajax );
	}

	// 5b) The chosen slot is RE-verified server-side, not blindly trusted from
	// the form – prevents booking already-taken/made-up times (e.g. when two
	// visitors pick the same slot at the same time).
	if ( '' === $slot || ! kanzlei_cf_is_slot_available( $slot ) ) {
		kanzlei_cf_respond( false, __( 'Der gewählte Termin ist leider nicht mehr verfügbar. Bitte wählen Sie einen anderen.', 'kanzlei-theme' ), $is_ajax );
	}

	// 6) Check files & store them securely (optional, multiple allowed).
	$files_input = isset( $_FILES['kanzlei_file'] ) ? kanzlei_cf_normalize_files( $_FILES['kanzlei_file'] ) : array();

	if ( count( $files_input ) > kanzlei_cf_max_files() ) {
		kanzlei_cf_respond(
			false,
			sprintf(
				/* translators: %d: maximum number of files. */
				__( 'Sie können maximal %d Dateien anhängen.', 'kanzlei-theme' ),
				kanzlei_cf_max_files()
			),
			$is_ajax
		);
	}

	$total_size = 0;
	foreach ( $files_input as $f ) {
		$total_size += (int) $f['size'];
	}
	if ( $total_size > kanzlei_cf_max_total_upload_bytes() ) {
		kanzlei_cf_respond( false, __( 'Die Dateien sind in Summe zu groß.', 'kanzlei-theme' ), $is_ajax );
	}

	// [ ['stored' => uuid.ext, 'original' => original filename], … ]
	$uploaded_files = array();
	foreach ( $files_input as $f ) {
		$upload = kanzlei_cf_store_upload( $f );
		if ( is_wp_error( $upload ) ) {
			// Remove files already stored for this request again (no orphans).
			foreach ( $uploaded_files as $done ) {
				@unlink( trailingslashit( kanzlei_cf_private_dir() ) . $done['stored'] );
			}
			kanzlei_cf_respond( false, $upload->get_error_message(), $is_ajax );
		}
		$uploaded_files[] = array(
			'stored'   => $upload,
			'original' => sanitize_file_name( $f['name'] ),
		);
	}

	// 7) Persist – status "pending" until the lawyer confirms/rejects.
	global $wpdb;
	$now      = current_time( 'mysql' );
	$inserted = $wpdb->insert(
		kanzlei_cf_table(),
		array(
			'created_at'       => $now,
			'name'             => $name,
			'email'            => $email,
			'appointment_slot' => $slot,
			'message'          => $message,
			'ip_address'       => kanzlei_cf_client_ip(),
			'consent_given_at' => $now,
			'status'           => 'pending',
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( ! $inserted ) {
		// Remove already-stored files again so no orphans remain.
		foreach ( $uploaded_files as $done ) {
			@unlink( trailingslashit( kanzlei_cf_private_dir() ) . $done['stored'] );
		}
		kanzlei_cf_respond( false, __( 'Ihre Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.', 'kanzlei-theme' ), $is_ajax );
	}

	$contact_id = $wpdb->insert_id;
	foreach ( $uploaded_files as $done ) {
		$wpdb->insert(
			kanzlei_cf_files_table(),
			array(
				'contact_id'        => $contact_id,
				'file_name'         => $done['stored'],
				'original_filename' => $done['original'],
				'created_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	kanzlei_cf_notify( $name, $email, $slot );

	kanzlei_cf_respond( true, kanzlei_cf_success_text(), $is_ajax );
}

/** Default success text (kept in sync with render.php). */
function kanzlei_cf_success_text() {
	return __( 'Vielen Dank! Ihr Wunschtermin wurde vorläufig reserviert – ich bestätige ihn innerhalb von 24 Stunden.', 'kanzlei-theme' );
}

/**
 * Response to the frontend: JSON for the JS enhancement, otherwise redirect
 * (PRG pattern). Terminates execution.
 */
function kanzlei_cf_respond( $success, $message, $is_ajax ) {
	if ( $is_ajax ) {
		wp_send_json( array( 'ok' => (bool) $success, 'message' => $message ) );
	}
	$target = wp_get_referer();
	if ( ! $target ) {
		$target = home_url( '/' );
	}
	$target = add_query_arg( 'kanzlei_contact', $success ? 'success' : 'error', $target ) . '#contact';
	wp_safe_redirect( $target );
	exit;
}

/** Rate limit: max. N requests per IP within the time window (default 5/hour). */
function kanzlei_cf_rate_ok() {
	$ip = kanzlei_cf_client_ip();
	if ( '' === $ip ) {
		return true; // Don't block without an IP (e.g. CLI/tests).
	}
	$limit  = (int) apply_filters( 'kanzlei_cf_rate_limit', 5 );
	$window = (int) apply_filters( 'kanzlei_cf_rate_window', HOUR_IN_SECONDS );
	$key    = 'kanzlei_cf_rl_' . md5( $ip );
	$count  = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Reshapes PHP's nested $_FILES structure for multi-file uploads
 * (name="kanzlei_file[]") into a list of individual file arrays, as expected
 * by kanzlei_cf_store_upload(). Empty slots (nothing selected) are skipped.
 */
function kanzlei_cf_normalize_files( $files ) {
	$normalized = array();
	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $normalized;
	}
	foreach ( $files['name'] as $i => $name ) {
		if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] ) {
			continue;
		}
		$normalized[] = array(
			'name'     => $name,
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);
	}
	return $normalized;
}

/**
 * Validates and stores an upload securely in the private directory.
 * Returns: the (random) filename as a string, or WP_Error.
 */
function kanzlei_cf_store_upload( array $file ) {
	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'upload', __( 'Die Datei konnte nicht hochgeladen werden.', 'kanzlei-theme' ) );
	}
	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'upload', __( 'Ungültiger Upload.', 'kanzlei-theme' ) );
	}
	if ( (int) $file['size'] > kanzlei_cf_max_upload_bytes() ) {
		return new WP_Error( 'upload', __( 'Die Datei ist zu groß.', 'kanzlei-theme' ) );
	}

	$allowed = kanzlei_cf_allowed_types();

	// Extension from the filename …
	$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( ! isset( $allowed[ $ext ] ) ) {
		return new WP_Error( 'upload', __( 'Nur PDF-, JPG- oder PNG-Dateien sind erlaubt.', 'kanzlei-theme' ) );
	}

	// … and the real MIME type via finfo must match the whitelist.
	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$mime  = $finfo->file( $file['tmp_name'] );
	if ( $mime !== $allowed[ $ext ] ) {
		return new WP_Error( 'upload', __( 'Der Dateityp stimmt nicht mit der Endung überein.', 'kanzlei-theme' ) );
	}

	kanzlei_cf_ensure_private_dir();

	// A random name prevents guessing/collisions; only keep the extension.
	$safe_name = wp_generate_uuid4() . '.' . $ext;
	$target    = trailingslashit( kanzlei_cf_private_dir() ) . $safe_name;

	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
		return new WP_Error( 'upload', __( 'Die Datei konnte nicht gespeichert werden.', 'kanzlei-theme' ) );
	}
	@chmod( $target, 0640 );

	return $safe_name;
}

/**
 * Metadata-only notification to the law firm.
 * NO message text, NO file – only name + appointment. Reply-To =
 * visitor, so a reply can be sent directly.
 */
function kanzlei_cf_notify( $name, $email, $slot ) {
	$to      = apply_filters( 'kanzlei_cf_notify_email', get_option( 'admin_email' ) );
	$subject = __( 'Neue Terminanfrage – bitte im Dashboard bestätigen', 'kanzlei-theme' );
	$body    = sprintf(
		"%s\n\n%s: %s\n%s: %s\n\n%s",
		__( 'Es ist eine neue, vorläufige Terminanfrage eingegangen.', 'kanzlei-theme' ),
		__( 'Name', 'kanzlei-theme' ),
		$name,
		__( 'Gewünschter Termin', 'kanzlei-theme' ),
		kanzlei_cf_format_slot( $slot ),
		__( 'Nachricht und ggf. Datei sind aus Gründen der anwaltlichen Verschwiegenheit nur im Backend unter „Kontaktanfragen“ einsehbar. Bitte dort bestätigen oder ablehnen.', 'kanzlei-theme' )
	);
	$headers = array( 'Reply-To: ' . sanitize_text_field( $name ) . ' <' . $email . '>' );

	wp_mail( $to, $subject, $body, $headers );
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Backend: dashboard, settings, secure actions
 * ─────────────────────────────────────────────────────────────────────────
 */

add_action( 'admin_menu', 'kanzlei_cf_admin_menu' );
function kanzlei_cf_admin_menu() {
	add_menu_page(
		__( 'Kontaktanfragen', 'kanzlei-theme' ),
		__( 'Kontaktanfragen', 'kanzlei-theme' ),
		KANZLEI_CF_CAP,
		'kanzlei-contact',
		'kanzlei_cf_render_admin_page',
		'dashicons-email-alt',
		26
	);
}

function kanzlei_cf_render_admin_page() {
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	global $wpdb;
	$table = kanzlei_cf_table();

	// Save the retention period.
	if ( isset( $_POST['kanzlei_cf_retention'] ) && check_admin_referer( 'kanzlei_cf_settings' ) ) {
		$days = max( 1, min( 3650, (int) $_POST['kanzlei_cf_retention'] ) );
		update_option( 'kanzlei_cf_retention_days', $days );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Einstellung gespeichert.', 'kanzlei-theme' ) . '</p></div>';
	}
	$retention = (int) get_option( 'kanzlei_cf_retention_days', 30 );

	// Filter: search by name/email to group requests from the same person
	// (requests remain unchanged, separate records regardless – see docs).
	$search = isset( $_GET['kanzlei_search'] ) ? sanitize_text_field( wp_unslash( $_GET['kanzlei_search'] ) ) : '';

	// Simple pagination.
	$per_page = 20;
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$offset   = ( $paged - 1 ) * $per_page;

	$where  = '';
	$params = array();
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where  = 'WHERE name LIKE %s OR email LIKE %s';
		$params = array( $like, $like );
	}

	$total = '' !== $search
		? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) ) // phpcs:ignore WordPress.DB
		: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB

	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) // phpcs:ignore WordPress.DB
	);

	// Bundle files for all displayed requests into a single query
	// (instead of one query per row) and group by request.
	$files_by_contact = array();
	if ( ! empty( $rows ) ) {
		$ids          = wp_list_pluck( $rows, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$file_rows    = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . kanzlei_cf_files_table() . " WHERE contact_id IN ({$placeholders}) ORDER BY id ASC", $ids ) // phpcs:ignore WordPress.DB
		);
		foreach ( $file_rows as $fr ) {
			$files_by_contact[ $fr->contact_id ][] = $fr;
		}
	}

	$status_labels = array(
		'pending'   => array( __( 'vorläufig', 'kanzlei-theme' ), '#b5841a' ),
		'confirmed' => array( __( 'bestätigt', 'kanzlei-theme' ), '#2e7d5e' ),
		'rejected'  => array( __( 'abgelehnt', 'kanzlei-theme' ), '#757067' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Kontaktanfragen', 'kanzlei-theme' ); ?></h1>

		<form method="post" style="margin:1rem 0;padding:1rem;background:#fff;border:1px solid #ccd0d4;max-width:640px;">
			<?php wp_nonce_field( 'kanzlei_cf_settings' ); ?>
			<label for="kanzlei_cf_retention"><strong><?php esc_html_e( 'Abgelehnte Anfragen automatisch löschen nach (Tagen):', 'kanzlei-theme' ); ?></strong></label>
			<input type="number" min="1" max="3650" id="kanzlei_cf_retention" name="kanzlei_cf_retention" value="<?php echo esc_attr( $retention ); ?>" style="width:6rem;">
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Speichern', 'kanzlei-theme' ); ?></button>
			<p class="description"><?php esc_html_e( 'IP-Adressen werden unabhängig davon nach 7 Tagen automatisch anonymisiert. Bestätigte Termine werden nie automatisch gelöscht – erst manuell, z. B. nach Übernahme ins Mandat.', 'kanzlei-theme' ); ?></p>
		</form>

		<h2><?php esc_html_e( 'Termine sperren', 'kanzlei-theme' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Für Termine, die nicht über das Formular kamen (Gerichtstermine, private Termine, Urlaub). Uhrzeit bitte auf das 30-Minuten-Raster runden (z. B. 14:00, 14:30) – sonst hat die Sperre keine Wirkung.', 'kanzlei-theme' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1rem 0;padding:1rem;background:#fff;border:1px solid #ccd0d4;max-width:640px;display:flex;gap:0.75rem;align-items:end;flex-wrap:wrap;">
			<input type="hidden" name="action" value="kanzlei_cf_block">
			<?php wp_nonce_field( 'kanzlei_cf_block' ); ?>
			<span>
				<label for="kanzlei_block_datetime" style="display:block;"><?php esc_html_e( 'Datum & Uhrzeit', 'kanzlei-theme' ); ?></label>
				<input type="datetime-local" id="kanzlei_block_datetime" name="kanzlei_block_datetime" required>
			</span>
			<span>
				<label for="kanzlei_block_reason" style="display:block;"><?php esc_html_e( 'Grund (optional)', 'kanzlei-theme' ); ?></label>
				<input type="text" id="kanzlei_block_reason" name="kanzlei_block_reason" maxlength="255" style="width:12rem;">
			</span>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Sperren', 'kanzlei-theme' ); ?></button>
		</form>

		<?php
		$blocked_upcoming = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . kanzlei_cf_blocked_table() . ' WHERE blocked_at >= %s ORDER BY blocked_at ASC',
				gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
			)
		);
		if ( ! empty( $blocked_upcoming ) ) :
			?>
			<ul style="max-width:640px;">
				<?php foreach ( $blocked_upcoming as $b ) : ?>
					<li>
						<?php echo esc_html( kanzlei_cf_format_slot( $b->blocked_at ) ); ?>
						<?php if ( $b->reason ) : ?> – <?php echo esc_html( $b->reason ); ?><?php endif; ?>
						– <a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_unblock', $b->id ) ); ?>" style="color:#b32d2e;">
							<?php esc_html_e( 'Aufheben', 'kanzlei-theme' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Anfragen', 'kanzlei-theme' ); ?></h2>
		<form method="get" style="margin-bottom:1rem;">
			<input type="hidden" name="page" value="kanzlei-contact">
			<label for="kanzlei_search" class="screen-reader-text"><?php esc_html_e( 'Nach Name oder E-Mail suchen', 'kanzlei-theme' ); ?></label>
			<input type="search" id="kanzlei_search" name="kanzlei_search" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Nach Name oder E-Mail suchen …', 'kanzlei-theme' ); ?>" style="width:20rem;">
			<button type="submit" class="button"><?php esc_html_e( 'Filtern', 'kanzlei-theme' ); ?></button>
			<?php if ( '' !== $search ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'kanzlei_search', 'paged' ) ) ); ?>" class="button">
					<?php esc_html_e( 'Zurücksetzen', 'kanzlei-theme' ); ?>
				</a>
				<span class="description">
					<?php
					printf(
						/* translators: %d: Anzahl gefundener Anfragen. */
						esc_html__( '%d Treffer für alle Anfragen dieser Person – bleiben als eigenständige Datensätze bestehen.', 'kanzlei-theme' ),
						(int) $total
					);
					?>
				</span>
			<?php endif; ?>
		</form>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'Noch keine Anfragen vorhanden.', 'kanzlei-theme' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Eingang', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'Termin', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'Name', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'E-Mail', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'Nachricht / Datei', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'Status', 'kanzlei-theme' ); ?></th>
					<th><?php esc_html_e( 'Aktionen', 'kanzlei-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $row->created_at ) ); ?></td>
					<td><?php echo esc_html( kanzlei_cf_format_slot( $row->appointment_slot ) ); ?></td>
					<td><?php echo esc_html( $row->name ); ?></td>
					<td><a href="<?php echo esc_url( 'mailto:' . $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
					<td>
						<details>
							<summary><?php esc_html_e( 'Nachricht anzeigen', 'kanzlei-theme' ); ?></summary>
							<p style="white-space:pre-wrap;"><?php echo esc_html( $row->message ); ?></p>
						</details>
						<?php
						$row_files = $files_by_contact[ $row->id ] ?? array();
						if ( ! empty( $row_files ) ) :
							?>
							<ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
								<?php foreach ( $row_files as $rf ) : ?>
									<li>
										<a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_download', $rf->id ) ); ?>">
											<?php echo esc_html( $rf->original_filename ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( count( $row_files ) > 1 ) : ?>
								<a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_download_all', $row->id ) ); ?>">
									<?php esc_html_e( '↓ Alle als ZIP herunterladen', 'kanzlei-theme' ); ?>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$info  = $status_labels[ $row->status ] ?? array( $row->status, '#333' );
						$label = $info[0];
						$color = $info[1];
						?>
						<span style="color:<?php echo esc_attr( $color ); ?>;">● <?php echo esc_html( $label ); ?></span>
					</td>
					<td>
						<?php if ( 'pending' === $row->status ) : ?>
							<a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_confirm', $row->id ) ); ?>"><?php esc_html_e( 'Bestätigen', 'kanzlei-theme' ); ?></a><br>
							<a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_reject', $row->id ) ); ?>"><?php esc_html_e( 'Ablehnen', 'kanzlei-theme' ); ?></a><br>
						<?php endif; ?>
						<a href="<?php echo esc_url( kanzlei_cf_action_url( 'kanzlei_cf_delete', $row->id ) ); ?>"
							style="color:#b32d2e;"
							onclick="return confirm('<?php echo esc_js( __( 'Diese Anfrage inklusive aller Dateien endgültig löschen?', 'kanzlei-theme' ) ); ?>');">
							<?php esc_html_e( 'Löschen', 'kanzlei-theme' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
			<?php
			// Pagination links.
			$pages = (int) ceil( $total / $per_page );
			if ( $pages > 1 ) {
				echo '<p class="tablenav-pages" style="margin-top:1rem;">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				echo '</p>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

/** Nonce-protected action URL for download/confirm/reject/delete/unblock. */
function kanzlei_cf_action_url( $action, $id ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=' . $action . '&id=' . (int) $id ),
		$action . '_' . (int) $id
	);
}

// Single-file download – authenticated only, streams from the private directory.
// $_GET['id'] here refers to kanzlei_contact_files.id, not the request.
add_action( 'admin_post_kanzlei_cf_download', 'kanzlei_cf_download' );
function kanzlei_cf_download() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_download_' . $id );

	global $wpdb;
	$file = $wpdb->get_row( $wpdb->prepare( 'SELECT file_name, original_filename FROM ' . kanzlei_cf_files_table() . ' WHERE id = %d', $id ) );
	if ( ! $file ) {
		wp_die( esc_html__( 'Datei nicht gefunden.', 'kanzlei-theme' ) );
	}

	// basename() prevents any path traversal from the DB value.
	$path = trailingslashit( kanzlei_cf_private_dir() ) . basename( $file->file_name );
	if ( ! is_file( $path ) ) {
		wp_die( esc_html__( 'Datei nicht gefunden.', 'kanzlei-theme' ) );
	}

	$type = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	// The download carries the original filename; the storage path stays the random name.
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file->original_filename ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	// Prevents the browser from interpreting the file based on its content
	// instead of the declared Content-Type (MIME-sniffing hardening).
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path );
	exit;
}

// Download all files of a request bundled as a ZIP. $_GET['id'] here is the
// request ID (kanzlei_contact.id), not the file ID.
add_action( 'admin_post_kanzlei_cf_download_all', 'kanzlei_cf_download_all' );
function kanzlei_cf_download_all() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_download_all_' . $id );

	// Defensive: without the ZipArchive extension, fail cleanly rather than
	// risk a cryptic fatal error. Single-file downloads remain unaffected.
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'ZIP-Download ist auf diesem Server nicht verfügbar. Bitte Dateien einzeln herunterladen.', 'kanzlei-theme' ) );
	}

	global $wpdb;
	$files = $wpdb->get_results( $wpdb->prepare( 'SELECT file_name, original_filename FROM ' . kanzlei_cf_files_table() . ' WHERE contact_id = %d', $id ) );
	if ( empty( $files ) ) {
		wp_die( esc_html__( 'Keine Dateien gefunden.', 'kanzlei-theme' ) );
	}

	$private_dir = trailingslashit( kanzlei_cf_private_dir() );
	$zip_path    = wp_tempnam( 'kanzlei-anfrage-' . $id . '.zip' );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::OVERWRITE ) ) {
		wp_die( esc_html__( 'ZIP-Datei konnte nicht erstellt werden.', 'kanzlei-theme' ) );
	}

	// Avoid name collisions in the ZIP if two files share the same name.
	$used_names = array();
	foreach ( $files as $file ) {
		$path = $private_dir . basename( $file->file_name );
		if ( ! is_file( $path ) ) {
			continue;
		}
		$entry_name = sanitize_file_name( $file->original_filename );
		if ( isset( $used_names[ $entry_name ] ) ) {
			++$used_names[ $entry_name ];
			$info       = pathinfo( $entry_name );
			$suffix     = ' (' . $used_names[ $entry_name ] . ')';
			$entry_name = $info['filename'] . $suffix . ( isset( $info['extension'] ) ? '.' . $info['extension'] : '' );
		} else {
			$used_names[ $entry_name ] = 1;
		}
		$zip->addFile( $path, $entry_name );
	}
	$zip->close();

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="anfrage-' . (int) $id . '.zip"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $zip_path );
	wp_delete_file( $zip_path );
	exit;
}

// Confirm the appointment – slot is then bindingly blocked for other visitors.
add_action( 'admin_post_kanzlei_cf_confirm', 'kanzlei_cf_confirm' );
function kanzlei_cf_confirm() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_confirm_' . $id );

	global $wpdb;
	$wpdb->update( kanzlei_cf_table(), array( 'status' => 'confirmed' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=kanzlei-contact' ) );
	exit;
}

// Reject the appointment – step 1: the lawyer reaches out with an alternative.
add_action( 'admin_post_kanzlei_cf_reject', 'kanzlei_cf_reject' );
function kanzlei_cf_reject() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_reject_' . $id );

	global $wpdb;
	$wpdb->update( kanzlei_cf_table(), array( 'status' => 'rejected' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=kanzlei-contact' ) );
	exit;
}

// Delete the request including its file.
add_action( 'admin_post_kanzlei_cf_delete', 'kanzlei_cf_delete' );
function kanzlei_cf_delete() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_delete_' . $id );

	kanzlei_cf_delete_row( $id );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=kanzlei-contact' ) );
	exit;
}

/** Deletes a record along with all associated files (central, used by UI & cron). */
function kanzlei_cf_delete_row( $id ) {
	global $wpdb;
	$table       = kanzlei_cf_table();
	$files_table = kanzlei_cf_files_table();
	$private_dir = trailingslashit( kanzlei_cf_private_dir() );

	$files = $wpdb->get_col( $wpdb->prepare( "SELECT file_name FROM {$files_table} WHERE contact_id = %d", $id ) ); // phpcs:ignore WordPress.DB
	foreach ( $files as $file ) {
		$path = $private_dir . basename( $file );
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}
	$wpdb->delete( $files_table, array( 'contact_id' => (int) $id ), array( '%d' ) );
	$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
}

// Add a manual block for a slot (court date, personal, vacation …).
add_action( 'admin_post_kanzlei_cf_block', 'kanzlei_cf_block' );
function kanzlei_cf_block() {
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_block' );

	$datetime = isset( $_POST['kanzlei_block_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['kanzlei_block_datetime'] ) ) : '';
	$reason   = isset( $_POST['kanzlei_block_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['kanzlei_block_reason'] ) ) : '';

	// Expected format from <input type="datetime-local">: YYYY-MM-DDTHH:MM.
	$normalized = str_replace( 'T', ' ', $datetime );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized ) ) {
		global $wpdb;
		$wpdb->insert(
			kanzlei_cf_blocked_table(),
			array(
				'blocked_at' => $normalized . ':00',
				'reason'     => $reason,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=kanzlei-contact' ) );
	exit;
}

// Lift a manual block again.
add_action( 'admin_post_kanzlei_cf_unblock', 'kanzlei_cf_unblock' );
function kanzlei_cf_unblock() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( KANZLEI_CF_CAP ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'kanzlei-theme' ) );
	}
	check_admin_referer( 'kanzlei_cf_unblock_' . $id );

	global $wpdb;
	$wpdb->delete( kanzlei_cf_blocked_table(), array( 'id' => $id ), array( '%d' ) );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=kanzlei-contact' ) );
	exit;
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Cron: deletion policy, IP anonymization, expiry of unconfirmed requests
 * ─────────────────────────────────────────────────────────────────────────
 */

add_action( KANZLEI_CF_CRON, 'kanzlei_cf_run_cleanup' );
function kanzlei_cf_run_cleanup() {
	global $wpdb;
	$table = kanzlei_cf_table();

	// created_at is stored in local time (current_time('mysql')), so the
	// cutoff values are calculated in local time as well.
	$now_local = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	// 1) Anonymize IP after 7 days – regardless of status.
	$ip_cutoff = gmdate( 'Y-m-d H:i:s', $now_local - 7 * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET ip_address = NULL WHERE ip_address IS NOT NULL AND created_at < %s", $ip_cutoff ) ); // phpcs:ignore WordPress.DB

	// 2) Delete rejected requests once the retention period has elapsed (incl. file).
	// Confirmed appointments remain untouched – the lawyer takes them over into
	// the case file manually and deletes them themselves via the dashboard afterward.
	$retention = (int) get_option( 'kanzlei_cf_retention_days', 30 );
	$cutoff    = gmdate( 'Y-m-d H:i:s', $now_local - $retention * DAY_IN_SECONDS );
	$ids       = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'rejected' AND created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		kanzlei_cf_delete_row( (int) $id );
	}
}

// Hourly: automatically let unconfirmed requests expire once the deadline has
// passed, so that an unanswered or malicious request doesn't permanently
// block an appointment.
add_action( KANZLEI_CF_EXPIRE_CRON, 'kanzlei_cf_expire_pending' );
function kanzlei_cf_expire_pending() {
	global $wpdb;
	$table = kanzlei_cf_table();

	$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - kanzlei_cf_pending_expiry_hours() * HOUR_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'pending' AND created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		kanzlei_cf_delete_row( (int) $id );
	}
}

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  DSGVO (GDPR): integration with WordPress's built-in privacy tools
 *  (Tools → Export / Erase Personal Data)
 * ─────────────────────────────────────────────────────────────────────────
 */

add_filter( 'wp_privacy_personal_data_exporters', 'kanzlei_cf_register_exporter' );
function kanzlei_cf_register_exporter( $exporters ) {
	$exporters['kanzlei-contact-form'] = array(
		'exporter_friendly_name' => __( 'Kanzlei-Kontaktanfragen', 'kanzlei-theme' ),
		'callback'                => 'kanzlei_cf_export_data',
	);
	return $exporters;
}

/**
 * Returns all contact requests for an email address for the export.
 * With the typical case volumes of a solo law firm, one page is enough –
 * so $page is ignored and 'done' => true is always returned.
 */
function kanzlei_cf_export_data( $email_address, $page = 1 ) {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM ' . kanzlei_cf_table() . ' WHERE email = %s', $email_address )
	);

	$export_items = array();
	foreach ( $rows as $row ) {
		$data_points = array(
			array( 'name' => __( 'Name', 'kanzlei-theme' ), 'value' => $row->name ),
			array( 'name' => __( 'E-Mail', 'kanzlei-theme' ), 'value' => $row->email ),
			array( 'name' => __( 'Termin', 'kanzlei-theme' ), 'value' => kanzlei_cf_format_slot( $row->appointment_slot ) ),
			array( 'name' => __( 'Status', 'kanzlei-theme' ), 'value' => $row->status ),
			array( 'name' => __( 'Nachricht', 'kanzlei-theme' ), 'value' => $row->message ),
			array( 'name' => __( 'Eingegangen am', 'kanzlei-theme' ), 'value' => $row->created_at ),
			array( 'name' => __( 'Einwilligung erteilt am', 'kanzlei-theme' ), 'value' => $row->consent_given_at ),
		);
		$row_files = $wpdb->get_col( $wpdb->prepare( 'SELECT original_filename FROM ' . kanzlei_cf_files_table() . ' WHERE contact_id = %d', $row->id ) );
		if ( ! empty( $row_files ) ) {
			// The export doesn't embed binary files; the originals remain
			// retrievable exclusively via the protected backend.
			$data_points[] = array(
				'name'  => __( 'Angehängte Dateien', 'kanzlei-theme' ),
				'value' => implode( ', ', $row_files ) . ' – ' . __( 'Original im Backend unter „Kontaktanfragen“ einsehbar.', 'kanzlei-theme' ),
			);
		}

		$export_items[] = array(
			'group_id'    => 'kanzlei-contact-form',
			'group_label' => __( 'Kanzlei-Kontaktanfragen', 'kanzlei-theme' ),
			'item_id'     => 'kanzlei-contact-form-' . $row->id,
			'data'        => $data_points,
		);
	}

	return array(
		'data' => $export_items,
		'done' => true,
	);
}

add_filter( 'wp_privacy_personal_data_erasers', 'kanzlei_cf_register_eraser' );
function kanzlei_cf_register_eraser( $erasers ) {
	$erasers['kanzlei-contact-form'] = array(
		'eraser_friendly_name' => __( 'Kanzlei-Kontaktanfragen', 'kanzlei-theme' ),
		'callback'              => 'kanzlei_cf_erase_data',
	);
	return $erasers;
}

/**
 * Deletes all contact requests for an email address (row + file) via the
 * same central delete function used by the backend UI and cron.
 */
function kanzlei_cf_erase_data( $email_address, $page = 1 ) {
	global $wpdb;

	$ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . kanzlei_cf_table() . ' WHERE email = %s', $email_address )
	);

	foreach ( $ids as $id ) {
		kanzlei_cf_delete_row( (int) $id );
	}

	return array(
		'items_removed'  => ! empty( $ids ),
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}
