<?php
/**
 * Server render of the "kanzlei/contact-form" block.
 *
 * Runs on EVERY page load (render_callback) so the CSRF nonce stays fresh.
 * Logged-out and logged-in visitors see the same form.
 *
 * The complete processing (validation, file, DB, mail) lives in
 * inc/contact-form-custom.php. This file only produces the markup.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Success/error comes back after submission via redirect (?kanzlei_contact=…).
$kanzlei_state = isset( $_GET['kanzlei_contact'] ) ? sanitize_key( wp_unslash( $_GET['kanzlei_contact'] ) ) : '';

// Define messages once – used both for the no-JS display (below) and the
// JS enhancement (data attributes), so the texts aren't maintained twice.
$kanzlei_msg_success = __( 'Vielen Dank! Ihr Wunschtermin wurde vorläufig reserviert – ich bestätige ihn innerhalb von 24 Stunden.', 'kanzlei-theme' );
$kanzlei_msg_error   = __( 'Ihre Anfrage konnte nicht gesendet werden. Bitte prüfen Sie Ihre Eingaben und versuchen Sie es erneut.', 'kanzlei-theme' );

$kanzlei_status_text  = '';
$kanzlei_status_type  = '';
if ( 'success' === $kanzlei_state ) {
	$kanzlei_status_text = $kanzlei_msg_success;
	$kanzlei_status_type = 'success';
} elseif ( 'error' === $kanzlei_state ) {
	$kanzlei_status_text = $kanzlei_msg_error;
	$kanzlei_status_type = 'error';
}

$kanzlei_max_mb    = (int) apply_filters( 'kanzlei_cf_max_upload_mb', 4 );
$kanzlei_max_files = (int) apply_filters( 'kanzlei_cf_max_files', 3 );

// Free slots are recalculated on every request – already taken or blocked
// times therefore never even appear in the selection.
$kanzlei_slots = kanzlei_cf_available_slots();
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'kanzlei-form-wrap' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core already returns escaped attributes. ?>>

	<div class="kanzlei-form__status<?php echo $kanzlei_status_type ? ' is-' . esc_attr( $kanzlei_status_type ) : ''; ?>"
		role="status" aria-live="polite" tabindex="-1"
		data-success="<?php echo esc_attr( $kanzlei_msg_success ); ?>"
		data-error="<?php echo esc_attr( $kanzlei_msg_error ); ?>">
		<?php echo esc_html( $kanzlei_status_text ); ?>
	</div>

	<form class="kanzlei-form" method="post" enctype="multipart/form-data" novalidate
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

		<input type="hidden" name="action" value="kanzlei_contact_submit">
		<?php wp_nonce_field( 'kanzlei_contact_submit', '_kanzlei_nonce' ); ?>

		<?php // Honeypot: invisible to humans (CSS), bots fill it in → discarded server-side. ?>
		<div class="kanzlei-form__hp" aria-hidden="true">
			<label for="kanzlei-website"><?php esc_html_e( 'Ihre Website (bitte frei lassen)', 'kanzlei-theme' ); ?></label>
			<input type="text" id="kanzlei-website" name="kanzlei_website" tabindex="-1" autocomplete="off">
		</div>

		<p class="kanzlei-field">
			<label for="kanzlei-name"><?php esc_html_e( 'Name', 'kanzlei-theme' ); ?> <span class="kanzlei-req" aria-hidden="true">*</span></label>
			<input type="text" id="kanzlei-name" name="kanzlei_name" required maxlength="120" autocomplete="name">
		</p>

		<p class="kanzlei-field">
			<label for="kanzlei-email"><?php esc_html_e( 'E-Mail', 'kanzlei-theme' ); ?> <span class="kanzlei-req" aria-hidden="true">*</span></label>
			<input type="email" id="kanzlei-email" name="kanzlei_email" required maxlength="180" autocomplete="email">
		</p>

		<p class="kanzlei-field">
			<label for="kanzlei-slot"><?php esc_html_e( 'Wunschtermin', 'kanzlei-theme' ); ?> <span class="kanzlei-req" aria-hidden="true">*</span></label>
			<?php if ( empty( $kanzlei_slots ) ) : ?>
				<span class="kanzlei-hint"><?php esc_html_e( 'Zurzeit sind leider keine Termine frei. Bitte kontaktieren Sie mich telefonisch.', 'kanzlei-theme' ); ?></span>
			<?php else : ?>
				<select id="kanzlei-slot" name="kanzlei_slot" required aria-describedby="kanzlei-slot-hint">
					<option value=""><?php esc_html_e( '– Bitte wählen –', 'kanzlei-theme' ); ?></option>
					<?php foreach ( $kanzlei_slots as $kanzlei_date => $kanzlei_times ) : ?>
						<optgroup label="<?php echo esc_attr( wp_date( 'l, d. F Y', strtotime( $kanzlei_date . ' 12:00:00' ) ) ); ?>">
							<?php foreach ( $kanzlei_times as $kanzlei_label => $kanzlei_value ) : ?>
								<option value="<?php echo esc_attr( $kanzlei_value ); ?>"><?php echo esc_html( $kanzlei_label ); ?><?php esc_html_e( ' Uhr', 'kanzlei-theme' ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<span id="kanzlei-slot-hint" class="kanzlei-hint"><?php esc_html_e( 'Der Termin wird zunächst vorläufig reserviert und von mir bestätigt.', 'kanzlei-theme' ); ?></span>
			<?php endif; ?>
		</p>

		<p class="kanzlei-field">
			<label for="kanzlei-message"><?php esc_html_e( 'Nachricht', 'kanzlei-theme' ); ?> <span class="kanzlei-req" aria-hidden="true">*</span></label>
			<textarea id="kanzlei-message" name="kanzlei_message" required rows="6" maxlength="5000"></textarea>
		</p>

		<p class="kanzlei-field">
			<label for="kanzlei-file"><?php esc_html_e( 'Dateien (optional)', 'kanzlei-theme' ); ?></label>
			<input type="file" id="kanzlei-file" name="kanzlei_file[]" multiple
				accept=".pdf,.jpg,.jpeg,.png" aria-describedby="kanzlei-file-hint">
			<span id="kanzlei-file-hint" class="kanzlei-hint">
				<?php
				printf(
					/* translators: 1: maximum number of files, 2: maximum size per file in megabytes. */
					esc_html__( 'PDF, JPG oder PNG. Max. %1$d Dateien, je max. %2$d MB.', 'kanzlei-theme' ),
					(int) $kanzlei_max_files,
					(int) $kanzlei_max_mb
				);
				?>
			</span>
		</p>

		<p class="kanzlei-field kanzlei-field--consent">
			<input type="checkbox" id="kanzlei-consent" name="kanzlei_consent" value="1" required>
			<label for="kanzlei-consent">
				<?php
				printf(
					/* translators: %s: link to the privacy policy. */
					wp_kses(
						__( 'Ich habe die <a href="%s">Datenschutzerklärung</a> gelesen und willige in die Verarbeitung meiner Daten zur Bearbeitung meiner Anfrage ein.', 'kanzlei-theme' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( home_url( '/datenschutz/' ) )
				);
				?>
				<span class="kanzlei-req" aria-hidden="true">*</span>
			</label>
		</p>

		<button type="submit" class="kanzlei-form__submit">
			<?php esc_html_e( 'Anfrage senden', 'kanzlei-theme' ); ?>
		</button>
	</form>
</div>
