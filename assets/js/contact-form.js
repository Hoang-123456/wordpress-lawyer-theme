/**
 * Progressive Enhancement für das Kanzlei-Kontaktformular.
 *
 * Ohne JavaScript funktioniert das Formular vollständig (klassischer POST +
 * Redirect). Mit JavaScript wird ohne Seiten-Reload abgesendet und die Rück-
 * meldung in die aria-live-Region geschrieben – dadurch wird sie von Screen-
 * readern angesagt und der Fokus dorthin gesetzt.
 */
( function () {
	'use strict';

	if ( ! ( 'fetch' in window && 'FormData' in window ) ) {
		return; // Ältere Browser nutzen den No-JS-Fallback.
	}

	document.querySelectorAll( '.kanzlei-form' ).forEach( function ( form ) {
		var wrap   = form.closest( '.kanzlei-form-wrap' ) || form.parentNode;
		var status = wrap.querySelector( '.kanzlei-form__status' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var data = new FormData( form );
			data.append( '_ajax', '1' );

			var button = form.querySelector( '.kanzlei-form__submit' );
			if ( button ) {
				button.disabled = true;
			}

			fetch( form.action, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					showStatus( status, result.ok, result.message );
					if ( result.ok ) {
						form.reset();
					}
				} )
				.catch( function () {
					// Netzwerkfehler → auf den robusten klassischen Submit zurückfallen.
					form.submit();
				} )
				.finally( function () {
					if ( button ) {
						button.disabled = false;
					}
				} );
		} );
	} );

	function showStatus( status, ok, message ) {
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.classList.remove( 'is-success', 'is-error' );
		status.classList.add( ok ? 'is-success' : 'is-error' );
		status.focus();
	}
} )();
