<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared contact info cards (phone, email, address, office hours).
 *
 * Included by both contact patterns – contact-form.php (custom code)
 * and contact-booking.php (FluentBooking) – so the card markup is
 * maintained in only one place. Contains only static markup, no logic.
 */
?>
<!-- wp:html -->
<a href="tel:+4922112345" class="contact-info-link">
	<div class="contact-info-card">
		<div class="contact-info-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
		</div>
		<div class="contact-info-text">
			<span class="contact-info-label">Telefon</span>
			<span class="contact-info-value">+49 221 123 456</span>
		</div>
	</div>
</a>
<!-- /wp:html -->

<!-- wp:html -->
<a href="mailto:info@kanzlei-nguyen.de" class="contact-info-link">
	<div class="contact-info-card">
		<div class="contact-info-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
		</div>
		<div class="contact-info-text">
			<span class="contact-info-label">E-Mail</span>
			<span class="contact-info-value">info@kanzlei-nguyen.de</span>
		</div>
	</div>
</a>
<!-- /wp:html -->

<!-- wp:html -->
<a href="https://maps.google.com" target="_blank" rel="noopener noreferrer" class="contact-info-link">
	<div class="contact-info-card">
		<div class="contact-info-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
		</div>
		<div class="contact-info-text">
			<span class="contact-info-label">Adresse</span>
			<span class="contact-info-value">Musterstraße 1<br>50667 Köln</span>
		</div>
	</div>
</a>
<!-- /wp:html -->

<!-- wp:html -->
<div class="office-hours-card">
	<h3 class="office-hours-title">Bürozeiten</h3>
	<div class="office-hours-row">
		<span>Montag – Freitag</span>
		<span>09:00 – 18:00</span>
	</div>
	<div class="office-hours-row">
		<span>Samstag</span>
		<span>Nach Vereinbarung</span>
	</div>
	<div class="office-hours-row">
		<span>Sonntag</span>
		<span>Geschlossen</span>
	</div>
</div>
<!-- /wp:html -->
