/**
 * Deactivation feedback popup. Vanilla JS, no dependencies, no external requests.
 *
 * Skip and Cancel never talk to the server. Submit posts to admin-ajax.php on this
 * site, and the plugin is deactivated whatever the outcome (success, error, timeout).
 *
 * @package StandaloneTech\Telemetry
 */

( function () {
	'use strict';

	var FOCUSABLE         = 'a[href], button:not([disabled]), textarea, input:not([disabled])';
	var SUBMIT_TIMEOUT_MS = 4000;

	function init( overlay ) {
		var plugin        = overlay.getAttribute( 'data-plugin' );
		var dialog        = overlay.querySelector( '.sts-telemetry-dialog' );
		var submit        = overlay.querySelector( '.sts-telemetry-submit' );
		var skip          = overlay.querySelector( '.sts-telemetry-skip' );
		var cancel        = overlay.querySelector( '.sts-telemetry-cancel' );
		var text          = overlay.querySelector( '.sts-telemetry-text' );
		var support       = overlay.querySelector( '.sts-telemetry-support' );
		var deactivateUrl = '';
		var opener        = null;

		function selected() {
			return overlay.querySelector( 'input[name="sts_telemetry_reason"]:checked' );
		}

		function open( link ) {
			opener         = link;
			deactivateUrl  = link.href;
			overlay.hidden = false;
			document.body.classList.add( 'sts-telemetry-open' );
			dialog.querySelector( 'input[type="radio"]' ).focus();
		}

		function close() {
			overlay.hidden = true;
			document.body.classList.remove( 'sts-telemetry-open' );
			if ( opener ) {
				opener.focus();
			}
		}

		function deactivate() {
			window.location.href = deactivateUrl;
		}

		// Open on this plugin's Deactivate link only.
		document.addEventListener(
			'click',
			function ( event ) {
				var link = event.target.closest ? event.target.closest( 'span.deactivate a' ) : null;
				var row  = link ? link.closest( 'tr' ) : null;

				if ( row && row.getAttribute( 'data-plugin' ) === plugin ) {
					event.preventDefault();
					open( link );
				}
			}
		);

		overlay.addEventListener(
			'change',
			function () {
				var reason      = selected();
				submit.disabled = ! reason;
				if ( support ) {
					support.hidden = ! reason || 'not_working' !== reason.value;
				}
			}
		);

		skip.addEventListener( 'click', deactivate );
		cancel.addEventListener( 'click', close );

		overlay.addEventListener(
			'click',
			function ( event ) {
				if ( event.target === overlay ) {
					close();
				}
			}
		);

		submit.addEventListener(
			'click',
			function () {
				var reason   = selected();
				var finished = false;
				var body     = new FormData();

				if ( ! reason ) {
					return;
				}

				submit.disabled = true;
				body.append( 'action', overlay.getAttribute( 'data-action' ) );
				body.append( 'nonce', overlay.getAttribute( 'data-nonce' ) );
				body.append( 'reason_code', reason.value );
				body.append( 'reason_text', text.value.slice( 0, 500 ) );

				function done() {
					if ( ! finished ) {
						finished = true;
						window.clearTimeout( timer );
						deactivate();
					}
				}

				var timer = window.setTimeout( done, SUBMIT_TIMEOUT_MS );

				try {
					window.fetch( overlay.getAttribute( 'data-ajax-url' ), { method: 'POST', credentials: 'same-origin', body: body } ).then( done, done );
				} catch ( e ) {
					done();
				}
			}
		);

		// Esc closes; Tab is trapped inside the dialog.
		overlay.addEventListener(
			'keydown',
			function ( event ) {
				var items, first, last;

				if ( 'Escape' === event.key ) {
					event.preventDefault();
					close();
					return;
				}

				if ( 'Tab' !== event.key ) {
					return;
				}

				items = dialog.querySelectorAll( FOCUSABLE );
				items = Array.prototype.filter.call(
					items,
					function ( el ) {
						return null !== el.offsetParent;
					}
				);
				first = items[ 0 ];
				last  = items[ items.length - 1 ];

				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		);
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.sts-telemetry-overlay' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
