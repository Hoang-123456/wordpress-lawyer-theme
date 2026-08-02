/**
 * Progressive enhancement for the law firm's contact form.
 *
 * Without JavaScript the form works fully (classic POST + redirect). With
 * JavaScript it submits without a page reload and writes the feedback into
 * the aria-live region – which makes screen readers announce it and moves
 * focus there.
 */
( function () {
	'use strict';

	if ( ! ( 'fetch' in window && 'FormData' in window ) ) {
		return; // Older browsers use the no-JS fallback.
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
					// Network error → fall back to the robust classic submit.
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
